<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$identity = requireUser();
$userId = (int)$identity['user_id'];
$data = getJsonInput();

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    jsonResponse(false, 'Valid reminder id is required.', [], 422);
}

$fields = [];
$params = [];

if (array_key_exists('title', $data)) {
    if (!is_string($data['title']) || trim($data['title']) === '' ||
        mb_strlen(trim($data['title']), 'UTF-8') > 150) {
        jsonResponse(false, 'Valid title is required.', [], 422);
    }
    $fields[] = 'title = ?';
    $params[] = trim($data['title']);
}

if (array_key_exists('category', $data)) {
    $allowed = ['Medicine', 'Water intake', 'Blood pressure check', 'Doctor visit', 'Custom reminder'];
    if (!is_string($data['category']) || !in_array($data['category'], $allowed, true)) {
        jsonResponse(false, 'Invalid category.', [], 422);
    }
    $fields[] = 'category = ?';
    $params[] = $data['category'];
}

if (array_key_exists('reminder_date', $data)) {
    $date = $data['reminder_date'];
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
        jsonResponse(false, 'Invalid reminder date.', [], 422);
    }
    $fields[] = 'reminder_date = ?';
    $params[] = $date;
}

if (array_key_exists('reminder_time', $data)) {
    $time = $data['reminder_time'];
    if (!is_string($time) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
        jsonResponse(false, 'Invalid reminder time.', [], 422);
    }
    $fields[] = 'reminder_time = ?';
    $params[] = strlen($time) === 5 ? $time . ':00' : $time;
}

if (array_key_exists('repeat_frequency', $data)) {
    $allowed = ['Once', 'Daily', 'Weekly', 'Monthly', 'Custom'];
    if (!is_string($data['repeat_frequency']) || !in_array($data['repeat_frequency'], $allowed, true)) {
        jsonResponse(false, 'Invalid repeat frequency.', [], 422);
    }
    $fields[] = 'repeat_frequency = ?';
    $params[] = $data['repeat_frequency'];
}

if (array_key_exists('custom_days', $data)) {
    $days = $data['custom_days'];
    if ($days !== null && !is_string($days)) {
        jsonResponse(false, 'custom_days must be a string or null.', [], 422);
    }
    $fields[] = 'custom_days = ?';
    $params[] = $days;
}

foreach (['notifications_enabled', 'sound_enabled', 'vibration_enabled'] as $field) {
    if (array_key_exists($field, $data)) {
        $value = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            jsonResponse(false, "$field must be boolean.", [], 422);
        }
        $fields[] = "$field = ?";
        $params[] = (int)$value;
    }
}

if (array_key_exists('medicine_id', $data)) {
    $medicineId = $data['medicine_id'];

    if ($medicineId !== null) {
        $medicineId = filter_var($medicineId, FILTER_VALIDATE_INT);
        if (!$medicineId || $medicineId < 1) {
            jsonResponse(false, 'Invalid medicine_id.', [], 422);
        }

        $check = $pdo->prepare('SELECT id FROM medicines WHERE id = ? AND user_id = ?');
        $check->execute([(int)$medicineId, $userId]);
        if (!$check->fetch()) {
            jsonResponse(false, 'Medicine not found.', [], 404);
        }
    }

    $fields[] = 'medicine_id = ?';
    $params[] = $medicineId === null ? null : (int)$medicineId;
}

if (!$fields) {
    jsonResponse(false, 'No editable fields provided.', [], 422);
}

try {
    $params[] = (int)$id;
    $params[] = $userId;

    $stmt = $pdo->prepare(
        'UPDATE reminders SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?'
    );
    $stmt->execute($params);

    $check = $pdo->prepare('SELECT id FROM reminders WHERE id = ? AND user_id = ?');
    $check->execute([(int)$id, $userId]);

    if (!$check->fetch()) {
        jsonResponse(false, 'Reminder not found.', [], 404);
    }

    jsonResponse(true, 'Reminder updated successfully.');
} catch (Throwable $e) {
    error_log('EasyCare update reminder failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to update reminder.', [], 500);
}