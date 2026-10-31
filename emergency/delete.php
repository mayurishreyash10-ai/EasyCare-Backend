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
    jsonResponse(false, 'Valid contact id is required.', [], 422);
}

try {
    $stmt = $pdo->prepare(
        'DELETE FROM emergency_contacts WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([(int)$id, $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Emergency contact not found.', [], 404);
    }

    jsonResponse(true, 'Emergency contact deleted.');
} catch (Throwable $e) {
    error_log('EasyCare emergency contact delete failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to delete emergency contact.', [], 500);
}