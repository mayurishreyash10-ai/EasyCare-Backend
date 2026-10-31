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

$name = requiredString($data, 'medicine_name', 150);
$dosage = isset($data['dosage']) && is_string($data['dosage']) ? trim($data['dosage']) : null;
$instructions = isset($data['instructions']) && is_string($data['instructions']) ? trim($data['instructions']) : null;
$threshold = $data['low_stock_threshold'] ?? 5;

if (!filter_var($threshold, FILTER_VALIDATE_INT) || (int)$threshold < 0) {
    jsonResponse(false, 'Low-stock threshold must be a non-negative integer.', [], 422);
}

$quantity = $data['quantity'] ?? null;
if ($quantity !== null && (!filter_var($quantity, FILTER_VALIDATE_INT) || (int)$quantity < 0)) {
    jsonResponse(false, 'Quantity must be a non-negative integer or null.', [], 422);
}

try {
    $stmt = $pdo->prepare(
        'UPDATE medicines
         SET medicine_name = ?, dosage = ?, quantity = ?,
             low_stock_threshold = ?, instructions = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        $name,
        $dosage !== '' ? $dosage : null,
        $quantity === null ? null : (int)$quantity,
        (int)$threshold,
        $instructions !== '' ? $instructions : null,
        (int)$id,
        $userId
    ]);

    $check = $pdo->prepare('SELECT id FROM medicines WHERE id = ? AND user_id = ?');
    $check->execute([(int)$id, $userId]);

    if (!$check->fetch()) {
        jsonResponse(false, 'Medicine not found.', [], 404);
    }

    jsonResponse(true, 'Medicine updated successfully.');
} catch (Throwable $e) {
    error_log('EasyCare update medicine failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to update medicine.', [], 500);
} 