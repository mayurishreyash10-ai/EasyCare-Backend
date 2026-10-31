<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
requireAdmin();

$report = [];

$queries = [
    'total_users' => 'SELECT COUNT(*) FROM users',
    'total_medicines' => 'SELECT COUNT(*) FROM medicines',
    'total_reminders' => 'SELECT COUNT(*) FROM reminders',
    'pending_reminders' => "SELECT COUNT(*) FROM reminders WHERE status = 'pending'",
    'completed_reminders' => "SELECT COUNT(*) FROM reminders WHERE status = 'completed'",
    'missed_reminders' => "SELECT COUNT(*) FROM reminders WHERE status = 'missed'",
    'total_feedback' => 'SELECT COUNT(*) FROM feedback',
    'new_feedback' => "SELECT COUNT(*) FROM feedback WHERE status = 'New'",
    'total_wellness' => 'SELECT COUNT(*) FROM wellness',
    'published_wellness' => 'SELECT COUNT(*) FROM wellness WHERE is_published = 1',
    'total_activities' => 'SELECT COUNT(*) FROM progress',
    'completed_activities' => "SELECT COUNT(*) FROM progress WHERE activity_status = 'completed'",
    'missed_activities' => "SELECT COUNT(*) FROM progress WHERE activity_status = 'missed'"
];

foreach ($queries as $key => $sql) {
    $report[$key] = (int)$pdo->query($sql)->fetchColumn();
}

$stmt = $pdo->query(
    'SELECT DATE(created_at) AS report_date, COUNT(*) AS total
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY report_date'
);

$report['new_users_last_7_days'] = $stmt->fetchAll();

jsonResponse(true, 'Admin report generated successfully.', [
    'report' => $report
]);