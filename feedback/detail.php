<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);
$feedbackId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

if (!$feedbackId || $feedbackId <= 0) {
    jsonResponse(false, 'A valid feedback id is required.', [], 422);
}

$stmt = $pdo->prepare(
    'SELECT id, subject, message, status, created_at
     FROM feedback
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);
$stmt->execute([$feedbackId, $userId]);
$feedback = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$feedback) {
    jsonResponse(false, 'Feedback not found.', [], 404);
}

jsonResponse(true, 'Feedback details retrieved successfully.', [
    'feedback' => $feedback
]);