#!/usr/bin/env python3
"""Validate checksums, compressed streams and safe paths without extracting secrets."""
import argparse
import gzip
import hashlib
from pathlib import Path, PurePosixPath
import tarfile

FILES = {
    'database.sql.gz', 'storage.tar.gz', 'config.tar.gz', 'source.tar.gz',
    'database-name.txt', 'api-image.txt', 'nginx-image.txt', 'format-version.txt',
}
CONFIG_FILES = {'.env', 'docker-compose.yml', 'docker/nginx/conf.d/default.conf',
                'docker/nginx/conf.d/selfsigned.crt', 'docker/nginx/conf.d/selfsigned.key'}


def check_tar(path, kind):
    found = set()
    with tarfile.open(path, 'r:gz') as archive:
        for member in archive:
            name = PurePosixPath(member.name)
            if name.is_absolute() or '..' in name.parts or '\\' in member.name:
                raise ValueError(f'Unsafe archive path in {path.name}')
            if not (member.isfile() or member.isdir()):
                raise ValueError(f'Links/devices are forbidden in {path.name}')
            normalized = str(name)
            found.add(normalized)
            if kind == 'config' and normalized not in CONFIG_FILES:
                raise ValueError('Unexpected config backup entry')
            if kind == 'release':
                if any(part.startswith('.env') for part in name.parts) or name.suffix in {'.key', '.pem', '.crt', '.sql'}:
                    raise ValueError('Private file found in release')
                if name.parts[:2] == ('api', 'storage'):
                    raise ValueError('Storage found in release')
            if member.isfile():
                stream = archive.extractfile(member)
                while stream.read(1024 * 1024):
                    pass
    if kind == 'release' and not {'api/artisan', 'frontend/package.json', 'docker-compose.yml', 'deploy_release.sh', 'ops/lib.sh'}.issubset(found):
        raise ValueError('Incomplete release')
    if kind == 'config' and not {'.env', 'docker-compose.yml', 'docker/nginx/conf.d/default.conf'}.issubset(found):
        raise ValueError('Incomplete configuration backup')


def verify(directory):
    directory = Path(directory)
    listed = {}
    for line in (directory / 'SHA256SUMS').read_text().splitlines():
        digest, name = line.split(maxsplit=1)
        name = name.lstrip('*')
        if name not in FILES or name in listed:
            raise ValueError('Unexpected or duplicated checksum entry')
        listed[name] = digest
    if set(listed) != FILES:
        raise ValueError('Incomplete checksum manifest')
    for name, expected in listed.items():
        path = directory / name
        if not path.is_file() or path.is_symlink():
            raise ValueError(f'Missing or invalid file: {name}')
        digest = hashlib.sha256()
        with path.open('rb') as source:
            for chunk in iter(lambda: source.read(1024 * 1024), b''):
                digest.update(chunk)
        if digest.hexdigest() != expected:
            raise ValueError(f'Checksum mismatch: {name}')
    if (directory / 'format-version.txt').read_text().strip() != '1':
        raise ValueError('Unsupported backup version')
    count = 0
    with gzip.open(directory / 'database.sql.gz', 'rb') as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            count += len(chunk)
    if count == 0:
        raise ValueError('Empty database dump')
    check_tar(directory / 'storage.tar.gz', 'storage')
    check_tar(directory / 'config.tar.gz', 'config')
    check_tar(directory / 'source.tar.gz', 'release')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--release', action='store_true')
    parser.add_argument('path')
    args = parser.parse_args()
    try:
        if args.release:
            check_tar(Path(args.path), 'release')
        else:
            verify(args.path)
    except (OSError, ValueError, tarfile.TarError, EOFError) as error:
        parser.exit(1, f'Validation failed: {error}\n')
    print('ARCHIVE_VERIFIED' if args.release else 'BACKUP_VERIFIED')
