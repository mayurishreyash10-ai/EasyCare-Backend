<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');

$token = trim((string)($_GET['token'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    jsonResponse(false, 'Invalid or expired verification link.', [], 400);
}

$hash = hash('sha256', $token);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, user_id
         FROM auth_tokens
         WHERE token_hash = ?
           AND purpose = 'verify_email'
           AND used_at IS NULL
           AND expires_at > UTC_TIMESTAMP()
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$hash]);
    $record = $stmt->fetch();

    if (!$record) {
        $pdo->rollBack();
        jsonResponse(false, 'Invalid or expired verification link.', [], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([(int)$record['user_id']]);

    $stmt = $pdo->prepare(
        'UPDATE auth_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $stmt->execute([(int)$record['id']]);

    $pdo->commit();

    jsonResponse(true, 'Email verified successfully. You can now log in.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Email verification failed: ' . $e->getMessage());
    jsonResponse(false, 'Could not verify email.', [], 500);
}