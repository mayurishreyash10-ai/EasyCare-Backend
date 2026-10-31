<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_recorded,
            SUM(activity_status = "completed") AS completed,
            SUM(activity_status = "missed") AS missed,
            COUNT(DISTINCT DATE(created_at)) AS active_days
         FROM progress
         WHERE user_id = ?
           AND created_at >= CURDATE() - INTERVAL 29 DAY'
    );
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();

    $completed = (int)($stats['completed'] ?? 0);
    $missed = (int)($stats['missed'] ?? 0);
    $total = (int)($stats['total_recorded'] ?? 0);

    jsonResponse(true, 'Progress statistics retrieved.', [
        'period_days' => 30,
        'total_recorded' => $total,
        'completed' => $completed,
        'missed' => $missed,
        'active_days' => (int)($stats['active_days'] ?? 0),
        'completion_percentage' => $total > 0
            ? round(($completed / $total) * 100, 2)
            : 0
    ]);
} catch (Throwable $e) {
    error_log('EasyCare progress statistics failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve progress statistics.', [], 500);
}