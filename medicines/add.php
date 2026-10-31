<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$identity = requireUser();
$userId = (int) $identity['user_id'];
$data = getJsonInput();

$name = requiredString($data, 'medicine_name', 150);
$dosage = isset($data['dosage']) && is_string($data['dosage'])
    ? trim($data['dosage']) : null;
$instructions = isset($data['instructions']) && is_string($data['instructions'])
    ? trim($data['instructions']) : null;

$quantity = $data['quantity'] ?? null;
$threshold = $data['low_stock_threshold'] ?? 5;
$scheduleTimes = $data['schedule_times'] ?? [];

if ($quantity !== null &&
    (!filter_var($quantity, FILTER_VALIDATE_INT) || (int)$quantity < 0)) {
    jsonResponse(false, 'Quantity must be a non-negative integer or null.', [], 422);
}

if (!filter_var($threshold, FILTER_VALIDATE_INT) || (int)$threshold < 0) {
    jsonResponse(false, 'Low-stock threshold must be a non-negative integer.', [], 422);
}

if (!is_array($scheduleTimes) || count($scheduleTimes) > 8) {
    jsonResponse(false, 'Provide at most 8 schedule times.', [], 422);
}

$validatedTimes = [];

foreach ($scheduleTimes as $item) {
    if (!is_array($item) ||
        !isset($item['time']) ||
        !is_string($item['time']) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $item['time'])) {
        jsonResponse(false, 'Each schedule time must use HH:MM or HH:MM:SS.', [], 422);
    }

    $time = strlen($item['time']) === 5 ? $item['time'] . ':00' : $item['time'];
    $label = isset($item['label']) && is_string($item['label'])
        ? trim($item['label']) : null;

    if ($label !== null && mb_strlen($label, 'UTF-8') > 50) {
        jsonResponse(false, 'Schedule label cannot exceed 50 characters.', [], 422);
    }

    $validatedTimes[] = ['time' => $time, 'label' => $label];
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO medicines
         (user_id, medicine_name, dosage, quantity, low_stock_threshold, instructions)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $name,
        $dosage !== '' ? $dosage : null,
        $quantity === null ? null : (int)$quantity,
        (int)$threshold,
        $instructions !== '' ? $instructions : null
    ]);

    $medicineId = (int)$pdo->lastInsertId();

    if ($validatedTimes) {
        $timeStmt = $pdo->prepare(
            'INSERT INTO medicine_schedule_times (medicine_id, intake_time, label)
             VALUES (?, ?, ?)'
        );

        foreach ($validatedTimes as $time) {
            $timeStmt->execute([$medicineId, $time['time'], $time['label']]);
        }
    }

    $pdo->commit();

    jsonResponse(true, 'Medicine added successfully.', [
        'medicine_id' => $medicineId
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('EasyCare add medicine failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to add medicine. Please check the submitted details.', [], 500);
}