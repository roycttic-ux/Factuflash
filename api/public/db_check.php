<?php
/**
 * DB Connection Tester for FactuFlash
 * Place this in api/public/db_check.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Verificador de Conexión DB - FactuFlash</h1>";

// Try to load .env manually to show what we are testing
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die("<p style='color:red'>❌ Error: No se encontró el archivo .env en " . realpath(__DIR__ . '/..') . "</p>");
}

$env = parse_ini_file($envFile);

$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? '3306';
$db   = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';

echo "<ul>";
echo "<li><strong>Host:</strong> $host</li>";
echo "<li><strong>Puerto:</strong> $port</li>";
echo "<li><strong>Base de Datos:</strong> $db</li>";
echo "<li><strong>Usuario:</strong> $user</li>";
echo "<li><strong>Password:</strong> " . (empty($pass) ? '<em>(vacío)</em>' : '********') . "</li>";
echo "</ul>";

echo "<h3>Intentando conectar...</h3>";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $start = microtime(true);
    $pdo = new PDO($dsn, $user, $pass, $options);
    $end = microtime(true);
    
    echo "<p style='color:green; font-weight:bold;'>✅ ¡CONEXIÓN EXITOSA!</p>";
    echo "<p>Tiempo de respuesta: " . round(($end - $start) * 1000, 2) . " ms</p>";
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "<p style='color:blue'>✔️ La tabla 'users' existe.</p>";
    } else {
        echo "<p style='color:orange'>⚠️ La tabla 'users' NO existe. ¿Corriste las migraciones? (php artisan migrate)</p>";
    }

} catch (\PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ FALLÓ LA CONEXIÓN</p>";
    echo "<pre style='background:#fee; padding:10px; border:1px solid red;'>" . $e->getMessage() . "</pre>";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p>💡 <em>Tip: El usuario o la contraseña parecen ser incorrectos.</em></p>";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'timed out') !== false) {
        echo "<p>💡 <em>Tip: El Host o el Puerto parecen estar mal, o el servidor de MySQL no está corriendo en esa dirección.</em></p>";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p>💡 <em>Tip: El nombre de la base de datos no existe.</em></p>";
    }
}

echo "<hr><p>Borra este archivo (<code>api/public/db_check.php</code>) una vez termines por seguridad.</p>";
