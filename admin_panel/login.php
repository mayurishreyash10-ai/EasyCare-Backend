<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';

/* Demo login is permitted only on the local computer. */
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$localHosts = ['localhost', 'localhost:80', 'localhost:8080',
               '127.0.0.1', '127.0.0.1:80', '127.0.0.1:8080'];

if (!in_array($host, $localHosts, true)) {
    http_response_code(403);
    exit('Demo login is available only on localhost.');
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (empty($_SESSION['demo_login_csrf'])) {
    $_SESSION['demo_login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['demo_login_csrf'], $token)) {
        $error = 'Page expired. Refresh and try again.';
    } else {
        try {
            $stmt = $pdo->query(
                'SELECT id, full_name, email FROM admin ORDER BY id ASC LIMIT 1'
            );
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                $error = 'No admin record found. Create an admin account first.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_name'] = (string)$admin['full_name'];
                $_SESSION['admin_email'] = (string)$admin['email'];
                $_SESSION['demo_login_csrf'] = bin2hex(random_bytes(32));

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('EasyCare demo login error: ' . $e->getMessage());
            $error = 'Database error. Check the PHP error log.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EasyCare Admin Login</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;
background:#edf5ff;font-family:Arial,sans-serif;color:#18324b}
.card{width:min(420px,92%);background:#fff;padding:32px;border-radius:18px;
box-shadow:0 12px 35px #173c5b18;text-align:center}
h1{margin:0 0 10px;color:#1769aa}
p{line-height:1.6;color:#52677a}
button{width:100%;padding:14px;border:0;border-radius:10px;
background:#1976d2;color:white;font-size:16px;font-weight:bold;cursor:pointer}
button:hover{background:#125ca7}
.error{color:#b42318;background:#fff0ef;padding:10px;border-radius:8px}
small{display:block;margin-top:18px;color:#6c7f90}
</style>
</head>
<body>
<main class="card">
<h1>EasyCare Admin</h1>
<p>Admin Login</p>
<p>Continue to the admin dashboard without entering email or password.</p>

<?php if ($error !== ''): ?>
<p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf_token"
value="<?= htmlspecialchars($_SESSION['demo_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<button type="submit">Login</button>
</form>

<small>EasyCare Admin Panel</small>
</main>
</body>
</html>