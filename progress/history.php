<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT id, reminder_id, activity_name, activity_status,
                completed_at, notes, created_at
         FROM progress
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 200'
    );
    $stmt->execute([$userId]);
    jsonResponse(true, 'Progress history retrieved.', [
        'history' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('EasyCare progress history failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve progress history.', [], 500);
}