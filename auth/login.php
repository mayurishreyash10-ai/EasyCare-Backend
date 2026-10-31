<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');

$data = getJsonInput();

$email = strtolower(trim(requiredString($data, 'email', 150)));
$password = requiredString($data, 'password', 255);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

$stmt = $pdo->prepare(
    'SELECT id, full_name, email, phone, password_hash,
            preferred_language, font_size, eye_comfort_mode, theme,
            email_verified_at
     FROM users
     WHERE email = ?
     LIMIT 1'
);

$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(false, 'Invalid email or password.', [], 401);
}


$token = createAuthToken((int) $user['id'], 'user');

unset($user['password_hash'], $user['email_verified_at']);

jsonResponse(true, 'Login successful.', [
    'token' => $token,
    'user' => $user
]);