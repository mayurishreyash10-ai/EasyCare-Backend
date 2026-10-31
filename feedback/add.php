<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$input = getJsonInput();
$subject = trim(requiredString($input, 'subject', 150));
$message = trim(requiredString($input, 'message', 10000));

$stmt = $pdo->prepare(
    "INSERT INTO feedback (user_id, subject, message, status)
     VALUES (?, ?, ?, 'New')"
);
$stmt->execute([$userId, $subject, $message]);

jsonResponse(true, 'Feedback submitted successfully.', [
    'feedback_id' => (int)$pdo->lastInsertId()
], 201);