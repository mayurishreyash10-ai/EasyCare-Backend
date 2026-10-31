<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('PUT');
requireAdmin();

$input = getJsonInput();
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!$userId || $userId <= 0) {
    jsonResponse(false, 'A valid user_id is required.', [], 422);
}

$allowed = ['full_name', 'phone', 'preferred_language', 'font_size',
            'eye_comfort_mode', 'theme'];
$updates = [];
$params = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $value = $input[$field];

    if ($field === 'full_name') {
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > 100) {
            jsonResponse(false, 'Invalid full name.', [], 422);
        }
        $value = trim($value);
    } elseif ($field === 'phone') {
        if ($value !== null && (!is_string($value) || strlen($value) > 20)) {
            jsonResponse(false, 'Invalid phone number.', [], 422);
        }
        $value = $value === null ? null : trim($value);
    } elseif ($field === 'preferred_language') {
        if (!in_array($value, ['en', 'hi', 'mr'], true)) {
            jsonResponse(false, 'Language must be en, hi, or mr.', [], 422);
        }
    } elseif ($field === 'font_size') {
        if (!in_array($value, ['Small', 'Medium', 'Large'], true)) {
            jsonResponse(false, 'Invalid font size.', [], 422);
        }
    } elseif ($field === 'eye_comfort_mode') {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            jsonResponse(false, 'Invalid eye comfort value.', [], 422);
        }
        $value = in_array($value, [true, 1, '1'], true) ? 1 : 0;
    } elseif ($field === 'theme') {
        $themes = ['Soft Blue', 'Light Green', 'Lavender', 'Beige',
            'Soft Grey', 'Mint', 'Sky Blue', 'Cream', 'Light Peach', 'Light Teal'];
        if (!is_string($value) || !in_array($value, $themes, true)) {
            jsonResponse(false, 'Invalid theme.', [], 422);
        }
    }

    $updates[] = "`$field` = ?";
    $params[] = $value;
}

if (!$updates) {
    jsonResponse(false, 'No supported fields supplied.', [], 422);
}

$params[] = $userId;
$stmt = $pdo->prepare(
    'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?'
);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    $check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $check->execute([$userId]);
    if (!$check->fetch()) {
        jsonResponse(false, 'User not found.', [], 404);
    }
}

jsonResponse(true, 'User updated successfully.');