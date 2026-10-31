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

$fields = [];
$params = [];

if (array_key_exists('contact_name', $data)) {
    if (!is_string($data['contact_name']) || trim($data['contact_name']) === '' ||
        mb_strlen(trim($data['contact_name']), 'UTF-8') > 100) {
        jsonResponse(false, 'Enter a valid contact name.', [], 422);
    }
    $fields[] = 'contact_name = ?';
    $params[] = trim($data['contact_name']);
}

if (array_key_exists('phone', $data)) {
    if (!is_string($data['phone']) ||
        !preg_match('/^[0-9+()\-\s]{7,20}$/', $data['phone']) ||
        strlen(preg_replace('/\D/', '', $data['phone'])) < 7) {
        jsonResponse(false, 'Enter a valid phone number.', [], 422);
    }
    $fields[] = 'phone = ?';
    $params[] = trim($data['phone']);
}

if (array_key_exists('relationship', $data)) {
    if ($data['relationship'] !== null &&
        (!is_string($data['relationship']) ||
         mb_strlen(trim($data['relationship']), 'UTF-8') > 50)) {
        jsonResponse(false, 'Invalid relationship.', [], 422);
    }
    $fields[] = 'relationship = ?';
    $params[] = is_string($data['relationship']) && trim($data['relationship']) !== ''
        ? trim($data['relationship']) : null;
}

if (!$fields) {
    jsonResponse(false, 'No fields to update.', [], 422);
}

try {
    $params[] = (int)$id;
    $params[] = $userId;

    $stmt = $pdo->prepare(
        'UPDATE emergency_contacts SET ' . implode(', ', $fields) .
        ' WHERE id = ? AND user_id = ?'
    );
    $stmt->execute($params);

    $check = $pdo->prepare(
        'SELECT id FROM emergency_contacts WHERE id = ? AND user_id = ?'
    );
    $check->execute([(int)$id, $userId]);

    if (!$check->fetch()) {
        jsonResponse(false, 'Emergency contact not found.', [], 404);
    }

    jsonResponse(true, 'Emergency contact updated.');
} catch (Throwable $e) {
    error_log('EasyCare emergency contact update failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to update emergency contact.', [], 500);
}