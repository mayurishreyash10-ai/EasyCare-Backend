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
           AND created_at >= CURDATE()
           AND created_at < CURDATE() + INTERVAL 1 DAY
         ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);
    $activities = $stmt->fetchAll();

    $completed = 0;
    $missed = 0;

    foreach ($activities as $activity) {
        if ($activity['activity_status'] === 'completed') {
            $completed++;
        } elseif ($activity['activity_status'] === 'missed') {
            $missed++;
        }
    }

    jsonResponse(true, "Today's progress retrieved.", [
        'date' => date('Y-m-d'),
        'completed' => $completed,
        'missed' => $missed,
        'total_recorded' => count($activities),
        'activities' => $activities
    ]);
} catch (Throwable $e) {
    error_log('EasyCare today progress failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve today’s progress.', [], 500);
}