<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function send_json(
    bool $success,
    string $message,
    mixed $data = null,
    int $status = 200
): never {
    http_response_code($status);

    $result = [
        'success' => $success,
        'message' => $message,
    ];

    if ($data !== null) {
        $result['data'] = $data;
    }

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/* Backward-compatible response helpers */
function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    send_json($success, $message, $data, $status);
}

function sendResponse(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    send_json($success, $message, $data, $status);
}

function request_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);

    if (!is_array($data)) {
        send_json(false, 'Invalid JSON request.', null, 400);
    }

    return $data;
}

function getJsonInput(): array {
    return request_json();
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        send_json(false, 'Method not allowed.', null, 405);
    }
}

function requireMethod(string $method): void {
    require_method($method);
}

function requiredString(
    array $data,
    string $key,
    int $maxLength = 255
): string {
    $value = $data[$key] ?? null;

    if (!is_string($value) || trim($value) === '') {
        send_json(false, "The {$key} field is required.", null, 422);
    }

    $value = trim($value);

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        send_json(false, "The {$key} field is too long.", null, 422);
    }

    return $value;
}

/*
 * Development fallback only. Configure EASYCARE_TOKEN_SECRET
 * as a strong environment variable before deployment.
 */
function tokenSecret(): string {
    $secret = getenv('EASYCARE_TOKEN_SECRET');

    if ($secret === false || strlen($secret) < 32) {
        $secret = require __DIR__ . '/token_secret.php';
    }

    return $secret;
}

function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function createAuthToken(int $userId, string $role = 'user'): string {
    $payload = json_encode([
        'sub' => $userId,
        'role' => $role,
        'exp' => time() + (7 * 24 * 60 * 60),
    ], JSON_THROW_ON_ERROR);

    $encoded = base64UrlEncode($payload);
    $signature = base64UrlEncode(
        hash_hmac('sha256', $encoded, tokenSecret(), true)
    );

    return $encoded . '.' . $signature;
}

function authenticatedIdentity(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        send_json(false, 'Authentication required.', null, 401);
    }

    $parts = explode('.', trim($matches[1]));

    if (count($parts) !== 2) {
        send_json(false, 'Invalid authentication token.', null, 401);
    }

    [$encoded, $signature] = $parts;

    $expected = base64UrlEncode(
        hash_hmac('sha256', $encoded, tokenSecret(), true)
    );

    if (!hash_equals($expected, $signature)) {
        send_json(false, 'Invalid authentication token.', null, 401);
    }

    $payloadJson = base64_decode(
        strtr($encoded, '-_', '+/') .
        str_repeat('=', (4 - strlen($encoded) % 4) % 4)
    );

    $payload = json_decode($payloadJson ?: '', true);

    if (
        !is_array($payload) ||
        empty($payload['sub']) ||
        empty($payload['role']) ||
        empty($payload['exp']) ||
        (int) $payload['exp'] < time()
    ) {
        send_json(false, 'Authentication token expired or invalid.', null, 401);
    }

    return [
        'user_id' => (int) $payload['sub'],
        'role' => (string) $payload['role'],
    ];
}

function requireUser(): array {
    $identity = authenticatedIdentity();

    if ($identity['role'] !== 'user') {
        send_json(false, 'User access required.', null, 403);
    }

    return $identity;
}

function requireAdmin(): array {
    $identity = authenticatedIdentity();

    if ($identity['role'] !== 'admin') {
        send_json(false, 'Admin access required.', null, 403);
    }

    return $identity;
}
