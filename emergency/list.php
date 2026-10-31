<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
$identity = requireUser();
$userId = (int)$identity['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT id, contact_name, phone, relationship, created_at
         FROM emergency_contacts
         WHERE user_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$userId]);

    jsonResponse(true, 'Emergency contacts retrieved.', [
        'contacts' => $stmt->fetchAll(),
        'maximum_contacts' => 3
    ]);
} catch (Throwable $e) {
    error_log('EasyCare emergency contact list failed: ' . $e->getMessage());
    jsonResponse(false, 'Unable to retrieve emergency contacts.', [], 500);
}