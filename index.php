<?php
declare(strict_types=1);

require_once __DIR__ . '/config/response.php';
require_once __DIR__ . '/config/database.php';

try {
    $stmt = $pdo->query('SELECT DATABASE() AS db_name');
    $result = $stmt->fetch();

    send_json(
        true,
        'EasyCare database connection successful',
        ['database' => $result['db_name']]
    );
} catch (PDOException $e) {
    error_log('EasyCare health check failed: ' . $e->getMessage());

    send_json(false, 'Database test failed.', null, 500);
}