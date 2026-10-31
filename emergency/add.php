<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('POST');
$identity = requireUser();
$userId = (int)$identity['user_id'];
$data = getJsonInput();

$name = requiredString($data, 'contact_name', 100);
$phone = requiredString($data, 'phone', 20);
$relationship = isset($data['relationship']) && is_string($data['relationship'])
    ? trim($data['relationship']) : null;

if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $phone) ||
    strlen(preg_replace('/\D/', '', $phone)) < 7) {
    jsonResponse(false, 'Enter a valid phone number.', [], 422);
}

if ($relationship !== null && mb_strlen($relationship, 'UTF-8') > 50) {
    jsonResponse(false, 'Relationship cannot exceed 50 characters.', [], 422);
}

try {
    $pdo->beginTransaction();

    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM emergency_contacts WHERE user_id = ? FOR UPDATE'
    );
    $count->execute([$userId]);

    if ((int)$count->fetchColumn() >= 3) {
        $pdo->rollBack();
        jsonResponse(false, 'You can save a maximum of 3 personal emergency contacts.', [], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO emergency_contacts (user_id, contact_name, phone, relationship)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $name, trim($phone), $relationship ?: null]);

    $contactId = (int)$pdo->lastInsertId();
    $pdo->commit();

    jsonResponse(true, 'Emergency contact added.', [
        'contact_id' => $contactId
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('EasyCare emergency contact add failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to add emergency contact.', [], 500);
}