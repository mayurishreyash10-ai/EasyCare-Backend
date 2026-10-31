<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$error = '';
$success = false;

try {
    $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Unable to access the admin table. Check your database schema.');
}

if ($adminCount > 0) {
    http_response_code(403);
    exit(
        'An administrator account already exists. ' .
        'Use the Admin Login page instead.'
    );
}

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['setup_csrf'], $token)
    ) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid name and email address.';
        } elseif (!is_string($password) || strlen($password) < 10) {
            $error = 'Password must contain at least 10 characters.';
        } else {
            try {
                $pdo->beginTransaction();

                // Recheck inside the transaction before creating the first admin.
                $count = (int)$pdo->query(
                    'SELECT COUNT(*) FROM admin'
                )->fetchColumn();

                if ($count > 0) {
                    $pdo->rollBack();
                    $error = 'An administrator account already exists.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO admin (full_name, email, password_hash)
                         VALUES (:name, :email, :password_hash)'
                    );

                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                    ]);

                    $pdo->commit();
                    $success = true;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('EasyCare admin setup error: ' . $exception->getMessage());
                $error = 'Unable to create the account. Check the admin table and database.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Initial Admin Setup | EasyCare</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            box-sizing: border-box;
            background: #eef5ff;
            font-family: Arial, sans-serif;
            color: #172b4d;
        }
        main {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 12px 35px #18305518;
        }
        h1 { margin-top: 0; }
        label {
            display: block;
            margin: 17px 0 7px;
            font-weight: 600;
        }
        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd6e3;
            border-radius: 8px;
        }
        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            border: 0;
            border-radius: 8px;
            background: #087e75;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        .message {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            background: #fff1f2;
            color: #a61b1b;
        }
        .success {
            background: #e7f8ef;
            color: #166534;
        }
        a { color: #087e75; }
    </style>
</head>
<body>
<main>
    <h1>EasyCare Admin Setup</h1>
    <p>Create the initial administrator account.</p>

    <?php if ($success): ?>
        <div class="message success">
            Administrator account created successfully.
        </div>
        <p><a href="login.php">Continue to Admin Login</a></p>
        <p><strong>Security:</strong> Delete setup.php from the server now.</p>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="message"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['setup_csrf']) ?>">

            <label for="full_name">Full Name</label>
            <input id="full_name" name="full_name"
                   maxlength="100" required>

            <label for="email">Email Address</label>
            <input id="email" type="email" name="email"
                   maxlength="150" required>

            <label for="password">Password (minimum 10 characters)</label>
            <input id="password" type="password" name="password"
                   minlength="10" autocomplete="new-password" required>

            <button type="submit">Create Admin Account</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
