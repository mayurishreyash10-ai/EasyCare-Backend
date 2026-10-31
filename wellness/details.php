<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
requireUser();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    jsonResponse(false, 'A valid wellness content id is required.', [], 422);
}

$stmt = $pdo->prepare(
    'SELECT id, title, content, category, language, created_at
     FROM wellness
     WHERE id = ? AND is_published = 1
     LIMIT 1'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    jsonResponse(false, 'Wellness content not found.', [], 404);
}

jsonResponse(true, 'Wellness content retrieved.', [
    'item' => $item
]);