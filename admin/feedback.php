<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

requireAdmin();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

if ($method === 'GET') {
    $status = trim((string)($_GET['status'] ?? ''));
    $sql = 'SELECT f.id, f.user_id, u.full_name, u.email, f.subject,
                   f.message, f.status, f.created_at
            FROM feedback f
            LEFT JOIN users u ON u.id = f.user_id';
    $params = [];

    if ($status !== '') {
        if (!in_array($status, ['New', 'In Progress', 'Resolved'], true)) {
            jsonResponse(false, 'Invalid feedback status.', [], 422);
        }
        $sql .= ' WHERE f.status = ?';
        $params[] = $status;
    }

    $sql .= ' ORDER BY f.created_at DESC, f.id DESC LIMIT 300';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, 'Feedback retrieved successfully.', [
        'feedback' => $stmt->fetchAll()
    ]);
}

if ($method === 'PUT') {
    $input = getJsonInput();
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $input['status'] ?? '';

    if (!$id || $id <= 0 ||
        !in_array($status, ['New', 'In Progress', 'Resolved'], true)) {
        jsonResponse(false, 'Valid feedback id and status are required.', [], 422);
    }

    $stmt = $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM feedback WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            jsonResponse(false, 'Feedback not found.', [], 404);
        }
    }

    jsonResponse(true, 'Feedback status updated successfully.');
}

jsonResponse(false, 'Method not allowed. Use GET or PUT.', [], 405);