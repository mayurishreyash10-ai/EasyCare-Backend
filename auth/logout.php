<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/response.php';

requireMethod('POST');

// Verify that a valid EasyCare token was supplied.
$identity = authenticatedIdentity();

if ($identity === null) {
    jsonResponse(false, 'Invalid or expired authentication token.', [], 401);
}

// This API uses stateless tokens.
// The Flutter app must delete the saved token after logout.
jsonResponse(true, 'Logout successful. Please clear the saved login token.');