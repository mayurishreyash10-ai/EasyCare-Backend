<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT id, medicine_name, dosage, quantity, low_stock_threshold,
                photo_path, instructions, created_at
         FROM medicines
         WHERE user_id = ?
         ORDER BY medicine_name ASC'
    );
    $stmt->execute([$userId]);
    $medicines = $stmt->fetchAll();

    $timeStmt = $pdo->prepare(
        'SELECT intake_time, label
         FROM medicine_schedule_times
         WHERE medicine_id = ?
         ORDER BY intake_time ASC'
    );

    foreach ($medicines as &$medicine) {
        $timeStmt->execute([(int)$medicine['id']]);
        $medicine['schedule_times'] = $timeStmt->fetchAll();
        $medicine['id'] = (int)$medicine['id'];
        $medicine['quantity'] = $medicine['quantity'] === null
            ? null : (int)$medicine['quantity'];
        $medicine['low_stock_threshold'] = (int)$medicine['low_stock_threshold'];
    }
    unset($medicine);

    jsonResponse(true, 'Medicines retrieved successfully.', [
        'medicines' => $medicines
    ]);
} catch (Throwable $e) {
    error_log('EasyCare list medicines failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve medicines.', [], 500);
}