<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.medicine_id, r.title, r.category,
                r.reminder_date, r.reminder_time, r.repeat_frequency,
                r.custom_days, r.notifications_enabled, r.sound_enabled,
                r.vibration_enabled, r.status, r.created_at,
                m.medicine_name
         FROM reminders r
         LEFT JOIN medicines m
           ON m.id = r.medicine_id AND m.user_id = r.user_id
         WHERE r.user_id = ?
         ORDER BY r.reminder_date ASC, r.reminder_time ASC, r.id DESC'
    );
    $stmt->execute([$userId]);

    jsonResponse(true, 'Reminders retrieved successfully.', [
        'reminders' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('EasyCare list reminders failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve reminders.', [], 500);
}