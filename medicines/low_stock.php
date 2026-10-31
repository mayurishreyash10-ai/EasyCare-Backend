<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT id, medicine_name, dosage, quantity, low_stock_threshold, photo_path
         FROM medicines
         WHERE user_id = ? AND quantity IS NOT NULL
           AND quantity <= low_stock_threshold
         ORDER BY quantity ASC, medicine_name ASC'
    );
    $stmt->execute([$userId]);

    jsonResponse(true, 'Low-stock medicines retrieved.', [
        'medicines' => $stmt->fetchAll()
    ]);
} catch (Throwable $e) {
    error_log('EasyCare low stock failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve low-stock medicines.', [], 500);
}