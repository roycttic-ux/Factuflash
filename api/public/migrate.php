<?php
/**
 * FacturaYa - Web Migration Runner
 * Accedé por: /api/public/migrate.php?secret=facturaya2026
 * 
 * ⚠️  IMPORTANTE: Eliminá este archivo del servidor después de correr las migraciones.
 */

define('SECRET_KEY', 'facturaya2026');

if (!isset($_GET['secret']) || $_GET['secret'] !== SECRET_KEY) {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso denegado. Pasá ?secret=facturaya2026 en la URL.']));
}

// Bootstrap Laravel
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace; background:#111; color:#0f0; padding:20px; font-size:14px;">';
echo "=== FacturaYa - Ejecutor de Migraciones ===\n\n";

// 1. Run standard migrations
echo "--- Paso 1: Migraciones principales ---\n";
$exitCode = $kernel->call('migrate', ['--force' => true]);
echo $kernel->output();

// 2. Check & create personal_access_tokens (Sanctum) if missing
echo "\n--- Paso 2: Verificar tabla de Sanctum (personal_access_tokens) ---\n";
try {
    $schema = app('db')->connection()->getSchemaBuilder();
    
    if (!$schema->hasTable('personal_access_tokens')) {
        echo "⚠️  Tabla 'personal_access_tokens' no existe. Creándola...\n";
        
        app('db')->connection()->statement("
            CREATE TABLE personal_access_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tokenable_type VARCHAR(191) NOT NULL,
                tokenable_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                abilities TEXT NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        echo "✅  Tabla 'personal_access_tokens' creada con éxito.\n";
    } else {
        echo "✅  Tabla 'personal_access_tokens' ya existe.\n";
    }
} catch (Exception $e) {
    echo "❌  Error con Sanctum: " . $e->getMessage() . "\n";
}

// 3. List all tables
echo "\n--- Paso 3: Verificando todas las tablas de FacturaYa ---\n";
$requiredTables = [
    'users', 'personal_access_tokens', 'products', 'recipients',
    'invoices', 'invoice_items', 'user_settings', 'subscriptions',
    'sessions', 'cache', 'migrations'
];

try {
    foreach ($requiredTables as $table) {
        $exists = $schema->hasTable($table);
        $icon = $exists ? '✅' : '❌';
        $count = $exists ? app('db')->connection()->table($table)->count() : 0;
        echo "  {$icon} {$table}" . ($exists ? " ({$count} filas)" : " — NO EXISTE") . "\n";
    }
} catch (Exception $e) {
    echo "Error verificando tablas: " . $e->getMessage() . "\n";
}

echo "\n=== Proceso completado ===\n";
echo "⚠️  Eliminá este archivo (migrate.php) del servidor por seguridad!\n";
echo '</pre>';
