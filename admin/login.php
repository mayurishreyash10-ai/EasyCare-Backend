<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$input = getJsonInput();
$email = strtolower(trim(requiredString($input, 'email', 150)));
$password = requiredString($input, 'password', 1024);

$stmt = $pdo->prepare(
    'SELECT id, full_name, email, password_hash FROM admin WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    jsonResponse(false, 'Invalid admin email or password.', [], 401);
}

$token = createAuthToken((int)$admin['id'], 'admin');

jsonResponse(true, 'Admin login successful.', [
    'token' => $token,
    'admin' => [
        'id' => (int)$admin['id'],
        'full_name' => $admin['full_name'],
        'email' => $admin['email']
    ]
]);