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

try {
    $stmt = $pdo->prepare('DELETE FROM reminders WHERE id = ? AND user_id = ?');
    $stmt->execute([(int)$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Reminder not found.', [], 404);
    }

    jsonResponse(true, 'Reminder deleted successfully.');
} catch (Throwable $e) {
    error_log('EasyCare delete reminder failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to delete reminder. Related progress history may prevent deletion.', [], 500);
}