<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('DELETE');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$input = getJsonInput();
$password = $input['password'] ?? '';

if (!is_string($password) || $password === '') {
    jsonResponse(false, 'Current password is required to delete your account.', [], 422);
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account || !password_verify($password, $account['password_hash'])) {
    jsonResponse(false, 'Current password is incorrect.', [], 401);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'DELETE FROM progress WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare(
        'DELETE FROM feedback WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare(
        'DELETE FROM emergency_contacts WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare(
        'DELETE FROM reminders WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare(
        'DELETE FROM medicine_schedule_times
         WHERE medicine_id IN (
             SELECT id FROM medicines WHERE user_id = ?
         )'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare(
        'DELETE FROM medicines WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Account could not be deleted.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('EasyCare account deletion failed: ' . $e->getMessage());
    jsonResponse(false, 'Account deletion failed. Please check related database records.', [], 500);
}

jsonResponse(true, 'Account and associated care records deleted.');