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
    'SELECT id, full_name, email, phone, profile_photo,
            preferred_language, font_size, eye_comfort_mode,
            theme, created_at, email_verified_at
     FROM users WHERE id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    jsonResponse(false, 'User profile not found.', [], 404);
}

$profile['eye_comfort_mode'] = (bool)$profile['eye_comfort_mode'];

jsonResponse(true, 'Profile retrieved successfully.', [
    'profile' => $profile
]);