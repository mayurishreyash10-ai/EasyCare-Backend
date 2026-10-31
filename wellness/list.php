<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
requireUser();

$language = $_GET['language'] ?? 'en';

if (!is_string($language) || !in_array($language, ['en', 'hi', 'mr'], true)) {
    jsonResponse(false, 'Language must be en, hi, or mr.', [], 422);
}

$stmt = $pdo->prepare(
    'SELECT id, title, content, category, language, created_at
     FROM wellness
     WHERE is_published = 1 AND language = ?
     ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$language]);

jsonResponse(true, 'Wellness content retrieved.', [
    'items' => $stmt->fetchAll()
]);