<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');

$data = getJsonInput();

$token = trim((string)($data['token'] ?? ''));
$password = (string)($data['new_password'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    jsonResponse(false, 'Invalid or expired reset token.', [], 400);
}

if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, user_id
         FROM auth_tokens
         WHERE token_hash = ?
           AND purpose = 'reset_password'
           AND used_at IS NULL
           AND expires_at > UTC_TIMESTAMP()
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([hash('sha256', $token)]);
    $record = $stmt->fetch();

    if (!$record) {
        $pdo->rollBack();
        jsonResponse(false, 'Invalid or expired reset token.', [], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET password_hash = ? WHERE id = ?'
    );

    $stmt->execute([
        password_hash($password, PASSWORD_DEFAULT),
        (int)$record['user_id']
    ]);

    $stmt = $pdo->prepare(
        "UPDATE auth_tokens
         SET used_at = UTC_TIMESTAMP()
         WHERE user_id = ?
           AND purpose = 'reset_password'
           AND used_at IS NULL"
    );
    $stmt->execute([(int)$record['user_id']]);

    $pdo->commit();

    jsonResponse(true, 'Password reset successful. Please log in again.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Password reset failed: ' . $e->getMessage());
    jsonResponse(false, 'Could not reset password.', [], 500);
}