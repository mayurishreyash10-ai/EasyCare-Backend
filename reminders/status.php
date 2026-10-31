<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('PUT');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$input = getJsonInput();
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$status = $input['status'] ?? '';

$allowed = ['pending', 'completed', 'missed', 'remind_later'];

if (!$id || $id < 1) {
    jsonResponse(false, 'A valid reminder id is required.', [], 422);
}

if (!is_string($status) || !in_array($status, $allowed, true)) {
    jsonResponse(false, 'Invalid reminder status.', [], 422);
}

$stmt = $pdo->prepare(
    'UPDATE reminders SET status = ? WHERE id = ? AND user_id = ?'
);
$stmt->execute([$status, $id, $userId]);

if ($stmt->rowCount() === 0) {
    $check = $pdo->prepare(
        'SELECT id, status FROM reminders WHERE id = ? AND user_id = ?'
    );
    $check->execute([$id, $userId]);
    $existing = $check->fetch();

    if (!$existing) {
        jsonResponse(false, 'Reminder not found.', [], 404);
    }
}

jsonResponse(true, 'Reminder status updated.', [
    'id' => (int)$id,
    'status' => $status
]);