<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/response.php';

requireMethod('GET');

jsonResponse(true, 'Official emergency numbers retrieved.', [
    'emergency_numbers' => [
        ['number' => '112', 'name' => 'National Emergency Number'],
        ['number' => '108', 'name' => 'Ambulance'],
        ['number' => '100', 'name' => 'Police'],
        ['number' => '101', 'name' => 'Fire Service'],
        ['number' => '181', 'name' => 'Women Helpline'],
        ['number' => '14567', 'name' => 'Elderline - Senior Citizens Helpline']
    ]
]);