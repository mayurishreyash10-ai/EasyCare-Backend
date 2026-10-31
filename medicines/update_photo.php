<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$user = requireUser();
$userId = (int)($user['user_id'] ?? $user['id'] ?? $user['sub'] ?? 0);

if ($userId <= 0) {
    jsonResponse(false, 'Invalid user session.', [], 401);
}

$medicineId = filter_var($_POST['medicine_id'] ?? null, FILTER_VALIDATE_INT);
if (!$medicineId || $medicineId < 1) {
    jsonResponse(false, 'A valid medicine_id is required.', [], 422);
}

$stmt = $pdo->prepare('SELECT id FROM medicines WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$medicineId, $userId]);

if (!$stmt->fetch()) {
    jsonResponse(false, 'Medicine not found for this user.', [], 404);
}

if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
    jsonResponse(false, 'Choose a medicine photo.', [], 422);
}

$file = $_FILES['photo'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Photo upload failed. Please try again.', [], 422);
}

if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
    jsonResponse(false, 'Photo must be smaller than 5 MB.', [], 422);
}

$tmp = $file['tmp_name'] ?? '';
if (!is_uploaded_file($tmp)) {
    jsonResponse(false, 'Invalid uploaded file.', [], 422);
}

$imageInfo = @getimagesize($tmp);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (!$imageInfo || !isset($extensions[$mime])) {
    jsonResponse(false, 'Only JPG, PNG or WEBP images are allowed.', [], 422);
}

$dir = __DIR__ . '/../uploads/medicine';
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    jsonResponse(false, 'Upload folder is unavailable.', [], 500);
}

$name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
$destination = $dir . '/' . $name;

if (!move_uploaded_file($tmp, $destination)) {
    jsonResponse(false, 'Unable to save medicine photo.', [], 500);
}

$relativePath = 'uploads/medicine/' . $name;

try {
    $stmt = $pdo->prepare('UPDATE medicines SET photo_path = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$relativePath, $medicineId, $userId]);
} catch (Throwable $e) {
    @unlink($destination);
    error_log('EasyCare medicine photo save failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to save photo information in the database.', [], 500);
}

jsonResponse(true, 'Medicine photo uploaded successfully.', [
    'medicine_id' => (int)$medicineId,
    'photo_path' => $relativePath
]);