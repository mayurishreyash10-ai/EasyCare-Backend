<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');
requireAdmin();

$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
$limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
$limit = ($limit !== false && $limit >= 1) ? min($limit, 300) : 100;

$sql = 'SELECT p.id, p.user_id, u.full_name, u.email, p.reminder_id,
               p.activity_name, p.activity_status, p.completed_at,
               p.notes, p.created_at
        FROM progress p
        LEFT JOIN users u ON u.id = p.user_id';
$params = [];

if ($userId !== false && $userId !== null && $userId > 0) {
    $sql .= ' WHERE p.user_id = ?';
    $params[] = $userId;
}

$sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int)$limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

jsonResponse(true, 'Activity records retrieved successfully.', [
    'activities' => $stmt->fetchAll()
]);