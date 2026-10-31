<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
requireAdmin();

$search = trim((string)($_GET['search'] ?? ''));
$limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
$limit = ($limit !== false && $limit >= 1) ? min($limit, 200) : 100;

$sql = 'SELECT id, full_name, email, phone, preferred_language,
               font_size, eye_comfort_mode, theme, created_at, email_verified_at
        FROM users';
$params = [];

if ($search !== '') {
    $sql .= ' WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}

$sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

jsonResponse(true, 'Users retrieved successfully.', [
    'users' => $stmt->fetchAll()
]);