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
    jsonResponse(false, 'Valid medicine id is required.', [], 422);
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id FROM medicines WHERE id = ? AND user_id = ? FOR UPDATE');
    $check->execute([(int)$id, $userId]);

    if (!$check->fetch()) {
        $pdo->rollBack();
        jsonResponse(false, 'Medicine not found.', [], 404);
    }

    $pdo->prepare('DELETE FROM medicine_schedule_times WHERE medicine_id = ?')
        ->execute([(int)$id]);

    $delete = $pdo->prepare('DELETE FROM medicines WHERE id = ? AND user_id = ?');
    $delete->execute([(int)$id, $userId]);

    $pdo->commit();
    jsonResponse(true, 'Medicine deleted successfully.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('EasyCare delete medicine failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to delete medicine. Related records may need attention.', [], 500);
}