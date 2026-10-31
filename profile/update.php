<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('PUT');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$input = getJsonInput();

$allowed = [
    'full_name',
    'phone',
    'preferred_language',
    'font_size',
    'eye_comfort_mode',
    'theme'
];

$updates = [];
$params = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $value = $input[$field];

    if ($field === 'full_name') {
        if (
            !is_string($value) ||
            trim($value) === '' ||
            strlen(trim($value)) > 100
        ) {
            jsonResponse(false, 'Full name must be 1-100 characters.', [], 422);
        }
        $value = trim($value);
    } elseif ($field === 'phone') {
        if ($value !== null && (!is_string($value) || strlen($value) > 20)) {
            jsonResponse(false, 'Phone must be at most 20 characters or null.', [], 422);
        }
        $value = $value === null ? null : trim($value);
    } elseif ($field === 'preferred_language') {
        if (!in_array($value, ['en', 'hi', 'mr'], true)) {
            jsonResponse(false, 'Language must be en, hi, or mr.', [], 422);
        }
    } elseif ($field === 'font_size') {
        if (!in_array($value, ['Small', 'Medium', 'Large'], true)) {
            jsonResponse(false, 'Font size must be Small, Medium, or Large.', [], 422);
        }
    } elseif ($field === 'eye_comfort_mode') {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            jsonResponse(false, 'Eye comfort mode must be true or false.', [], 422);
        }
        $value = in_array($value, [true, 1, '1'], true) ? 1 : 0;
    } elseif ($field === 'theme') {
        $themes = [
            'Soft Blue', 'Light Green', 'Lavender', 'Beige', 'Soft Grey',
            'Mint', 'Sky Blue', 'Cream', 'Light Peach', 'Light Teal'
        ];

        if (!is_string($value) || !in_array($value, $themes, true)) {
            jsonResponse(false, 'Please select a supported theme.', [], 422);
        }
    }

    $updates[] = "`$field` = ?";
    $params[] = $value;
}

if (!$updates) {
    jsonResponse(false, 'No supported profile fields were provided.', [], 422);
}

$params[] = $userId;

$stmt = $pdo->prepare(
    'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?'
);
$stmt->execute($params);

jsonResponse(true, 'Profile updated successfully.');