<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireAdmin();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT id, title, content, category, language, is_published, created_at
         FROM wellness ORDER BY created_at DESC, id DESC LIMIT 300'
    );
    jsonResponse(true, 'Wellness content retrieved successfully.', [
        'wellness' => $stmt->fetchAll()
    ]);
}

if ($method === 'POST' || $method === 'PUT') {
    $input = getJsonInput();
    $title = trim(requiredString($input, 'title', 150));
    $content = trim(requiredString($input, 'content', 100000));
    $category = isset($input['category']) ? trim((string)$input['category']) : null;
    $language = $input['language'] ?? 'en';
    $published = $input['is_published'] ?? 1;

    if ($title === '' || $content === '') {
        jsonResponse(false, 'Title and content are required.', [], 422);
    }
    if ($category !== null && strlen($category) > 60) {
        jsonResponse(false, 'Category must be at most 60 characters.', [], 422);
    }
    if (!in_array($language, ['en', 'hi', 'mr'], true)) {
        jsonResponse(false, 'Language must be en, hi, or mr.', [], 422);
    }
    if (!in_array($published, [true, false, 0, 1, '0', '1'], true)) {
        jsonResponse(false, 'is_published must be true or false.', [], 422);
    }
    $published = in_array($published, [true, 1, '1'], true) ? 1 : 0;

    if ($method === 'POST') {
        $stmt = $pdo->prepare(
            'INSERT INTO wellness (title, content, category, language, is_published)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $content, $category ?: null, $language, $published]);

        jsonResponse(true, 'Wellness content created successfully.', [
            'id' => (int)$pdo->lastInsertId()
        ], 201);
    }

    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonResponse(false, 'A valid id is required for updates.', [], 422);
    }

    $stmt = $pdo->prepare(
        'UPDATE wellness SET title = ?, content = ?, category = ?,
         language = ?, is_published = ? WHERE id = ?'
    );
    $stmt->execute([$title, $content, $category ?: null, $language, $published, $id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM wellness WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonResponse(false, 'Wellness item not found.', [], 404);
        }
    }

    jsonResponse(true, 'Wellness content updated successfully.');
}

if ($method === 'DELETE') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonResponse(false, 'A valid id query parameter is required.', [], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM wellness WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Wellness item not found.', [], 404);
    }

    jsonResponse(true, 'Wellness content deleted successfully.');
}

jsonResponse(false, 'Method not allowed. Use GET, POST, PUT or DELETE.', [], 405);