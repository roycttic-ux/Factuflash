<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=factura_ya', 'root', '');
$stmt = $pdo->query('DESCRIBE users');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | Key:' . $r['Key'] . PHP_EOL;
}
