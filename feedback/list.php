<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$stmt = $pdo->prepare(
    'SELECT id, subject, message, status, created_at
     FROM feedback
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
);
$stmt->execute([$userId]);

jsonResponse(true, 'Feedback history retrieved successfully.', [
    'feedback' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);