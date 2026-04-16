<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=factura_ya', 'root', '');
$hash = password_hash('password', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (name, email, password, plan_status, role, created_at, updated_at) VALUES (?, ?, ?, 'trial', 'admin', NOW(), NOW())");
$result = $stmt->execute(['Admin Test', 'admin@test.com', $hash]);
if ($result) {
    echo "Usuario creado OK. ID: " . $pdo->lastInsertId() . PHP_EOL;
} else {
    echo "Ya existe o error." . PHP_EOL;
}
// Verificar
$user = $pdo->query("SELECT id, name, email, plan_status FROM users WHERE email='admin@test.com'")->fetch(PDO::FETCH_ASSOC);
print_r($user);
