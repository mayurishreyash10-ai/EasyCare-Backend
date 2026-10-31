<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/mailer.php';

requireMethod('POST');

$data = getJsonInput();

$name = requiredString($data, 'full_name', 100);
$email = strtolower(requiredString($data, 'email', 150));
$password = (string)($data['password'] ?? '');
$phone = trim((string)($data['phone'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Enter a valid email address.', [], 422);
}

if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
}

if ($phone !== '' && strlen($phone) > 20) {
    jsonResponse(false, 'Phone number is too long.', [], 422);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    jsonResponse(false, 'This email is already registered.', [], 409);
}

$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $name,
        $email,
        $phone !== '' ? $phone : null,
        password_hash($password, PASSWORD_DEFAULT)
    ]);

    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO auth_tokens
         (user_id, token_hash, purpose, expires_at)
         VALUES (?, ?, 'verify_email', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 24 HOUR))"
    );

    $stmt->execute([$userId, $tokenHash]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('EasyCare registration failed: ' . $e->getMessage());
    jsonResponse(false, 'Could not register. Check the database configuration.', [], 500);
}

$baseUrl = rtrim(getenv('EASYCARE_PUBLIC_URL') ?: '', '/');

if ($baseUrl === '') {
    error_log('EASYCARE_PUBLIC_URL is not configured.');
    jsonResponse(
        true,
        'Account created. Email verification link configuration is pending.',
        ['email_verification_sent' => false],
        201
    );
}

$link = $baseUrl . '/auth/verify_email.php?token=' . urlencode($rawToken);

$sent = sendEasyCareEmail(
    $email,
    'Verify your EasyCare account',
    "Hello {$name},\n\nOpen this link to verify your email within 24 hours:\n\n{$link}\n\nIf you did not create this account, ignore this email."
);

jsonResponse(
    true,
    $sent
        ? 'Account created. Check your email to verify it.'
        : 'Account created, but the verification email could not be sent. Email service configuration is required.',
    [
        'user_id' => $userId,
        'email_verification_sent' => $sent
    ],
    201
);