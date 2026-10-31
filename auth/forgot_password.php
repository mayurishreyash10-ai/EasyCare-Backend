<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/mailer.php';

requireMethod('POST');

$data = getJsonInput();
$email = strtolower(requiredString($data, 'email', 150));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Enter a valid email address.', [], 422);
}

$stmt = $pdo->prepare(
    'SELECT id, full_name FROM users WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    try {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE auth_tokens
             SET used_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND purpose = 'reset_password'
               AND used_at IS NULL"
        );
        $stmt->execute([(int)$user['id']]);

        $stmt = $pdo->prepare(
            "INSERT INTO auth_tokens
             (user_id, token_hash, purpose, expires_at)
             VALUES (?, ?, 'reset_password',
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))"
        );
        $stmt->execute([(int)$user['id'], $tokenHash]);

        $pdo->commit();

        $baseUrl = rtrim(getenv('EASYCARE_PUBLIC_URL') ?: '', '/');

        if ($baseUrl !== '') {
            $link = $baseUrl . '/auth/reset_password_form.php?token=' . urlencode($rawToken);

            $sent = sendEasyCareEmail(
                $email,
                'Reset your EasyCare password',
                "Hello {$user['full_name']},\n\nUse this link within 30 minutes to reset your password:\n\n{$link}\n\nIf you did not request this, ignore this email."
            );

            if (!$sent) {
                error_log('EasyCare password reset email could not be sent.');
            }
        } else {
            error_log('EASYCARE_PUBLIC_URL is not configured for password reset.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Password reset request failed: ' . $e->getMessage());
    }
}

jsonResponse(
    true,
    'If the account exists, password reset instructions will be sent.'
);