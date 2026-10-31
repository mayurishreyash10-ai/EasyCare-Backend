<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$identity = requireUser();
$userId = (int)$identity['user_id'];
$data = getJsonInput();

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1 || !$amount || $amount < 1) {
    jsonResponse(false, 'Valid medicine id and positive restock amount are required.', [], 422);
}

try {
    $stmt = $pdo->prepare(
        'UPDATE medicines
         SET quantity = quantity + ?
         WHERE id = ? AND user_id = ? AND quantity IS NOT NULL'
    );
    $stmt->execute([(int)$amount, (int)$id, $userId]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT id, quantity FROM medicines WHERE id = ? AND user_id = ?'
        );
        $check->execute([(int)$id, $userId]);
        $medicine = $check->fetch();

        if (!$medicine) {
            jsonResponse(false, 'Medicine not found.', [], 404);
        }
        if ($medicine['quantity'] === null) {
            jsonResponse(false, 'Stock tracking is not enabled for this medicine.', [], 422);
        }
    }

    $result = $pdo->prepare('SELECT quantity FROM medicines WHERE id = ? AND user_id = ?');
    $result->execute([(int)$id, $userId]);

    jsonResponse(true, 'Medicine restocked successfully.', [
        'quantity' => (int)$result->fetchColumn()
    ]);
} catch (Throwable $e) {
    error_log('EasyCare restock failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to restock medicine.', [], 500);
}