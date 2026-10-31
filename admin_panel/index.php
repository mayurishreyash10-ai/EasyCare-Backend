<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_admin();

$section = $_GET['section'] ?? 'dashboard';
$allowedSections = [
    'dashboard', 'users', 'medicines', 'reminders',
    'feedback', 'wellness', 'activities'
];

if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

$error = '';
$success = '';

/* Handle feedback status updates. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_feedback') {
            $id = filter_var($_POST['feedback_id'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';

            if (!$id || !in_array(
                $status,
                ['New', 'In Progress', 'Resolved'],
                true
            )) {
                throw new RuntimeException('Invalid feedback details.');
            }

            $stmt = $pdo->prepare(
                'UPDATE feedback SET status = :status WHERE id = :id'
            );
            $stmt->execute(['status' => $status, 'id' => $id]);
            $success = 'Feedback status updated successfully.';
            $section = 'feedback';
        } elseif ($action === 'add_wellness') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? '');

            if ($title === '' || $content === '') {
                throw new RuntimeException(
                    'Title and content are required.'
                );
            }

            $stmt = $pdo->prepare(
                'INSERT INTO wellness
                    (title, content, category, language, is_published)
                 VALUES
                    (:title, :content, :category, :language, :published)'
            );

            $stmt->execute([
                'title' => $title,
                'content' => $content,
                'category' => $category !== '' ? $category : 'General',
                'language' => 'en',
                'published' => 0
            ]);

            $success = 'Wellness content saved as a draft.';
            $section = 'wellness';
        }
    } catch (Throwable $exception) {
        error_log('EasyCare admin action error: ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'The action could not be completed. Check the database schema.';
    }
}

function safeCount(PDO $pdo, string $table, string $condition = ''): int
{
    $allowed = ['users', 'medicines', 'reminders', 'feedback', 'progress', 'wellness'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    try {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM `$table` $condition"
        )->fetchColumn();
    } catch (Throwable $exception) {
        error_log("EasyCare count error ($table): " . $exception->getMessage());
        return 0;
    }
}

function safeRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('EasyCare dashboard query error: ' . $exception->getMessage());
        return [];
    }
}

$counts = [
    'users' => safeCount($pdo, 'users'),
    'medicines' => safeCount($pdo, 'medicines'),
    'reminders' => safeCount($pdo, 'reminders'),
    'feedback' => safeCount(
        $pdo,
        'feedback',
        "WHERE status IS NULL OR status <> 'Resolved'"
    ),
    'activities' => safeCount($pdo, 'progress'),
    'wellness' => safeCount($pdo, 'wellness')
];

$data = [];

switch ($section) {
    case 'users':
        $data = safeRows(
            $pdo,
            'SELECT id, full_name, email, phone
             FROM users ORDER BY id DESC LIMIT 200'
        );
        break;

    case 'medicines':
        $data = safeRows(
            $pdo,
            'SELECT m.id, m.medicine_name, m.dosage, m.quantity,
                    m.low_stock_threshold, u.full_name
             FROM medicines m
             LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.id DESC LIMIT 200'
        );
        break;

    case 'reminders':
        $data = safeRows(
            $pdo,
            'SELECT r.id, r.title, r.category, r.reminder_date,
                    r.reminder_time, r.repeat_frequency, r.status,
                    u.full_name
             FROM reminders r
             LEFT JOIN users u ON u.id = r.user_id
             ORDER BY r.id DESC LIMIT 200'
        );
        break;

    case 'feedback':
        $data = safeRows(
            $pdo,
            'SELECT id, subject, message, status, created_at
             FROM feedback ORDER BY id DESC LIMIT 200'
        );
        break;

    case 'wellness':
        $data = safeRows(
            $pdo,
            'SELECT id, title, content, category, language, is_published
             FROM wellness ORDER BY id DESC LIMIT 200'
        );
        break;

    case 'activities':
        $data = safeRows(
            $pdo,
            'SELECT p.id, p.user_id, p.activity_name,
                    p.activity_status, p.completed_at, p.notes,
                    u.full_name
             FROM progress p
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.id DESC LIMIT 200'
        );
        break;
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'users' => 'Users',
    'medicines' => 'Medicine Records',
    'reminders' => 'Reminder Records',
    'feedback' => 'Feedback Management',
    'wellness' => 'Wellness Content',
    'activities' => 'Activity Records'
];

function navLink(string $key, string $label, string $current): string
{
    $active = $key === $current ? 'active' : '';
    return '<a class="' . $active . '" href="?section=' .
        rawurlencode($key) . '">' . e($label) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitles[$section]) ?> | EasyCare Admin</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f4f7fb;
            color: #1c2b43;
            font-family: Arial, Helvetica, sans-serif;
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: #142b45;
            color: #fff;
            padding: 24px 14px;
        }
        .brand { font-size: 22px; font-weight: bold; padding: 0 12px 8px; }
        .brand-sub { padding: 0 12px 25px; color: #b7c7d9; font-size: 12px; }
        .nav-label {
            padding: 12px;
            color: #9fb2c9;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
        }
        .sidebar a {
            display: block;
            padding: 12px;
            margin: 4px 0;
            color: #dce7f4;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #087e75;
            color: white;
        }
        .main { flex: 1; min-width: 0; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 22px 30px;
            background: white;
            border-bottom: 1px solid #e4eaf1;
        }
        .topbar h1 { margin: 0; font-size: 23px; }
        .muted { color: #738198; font-size: 13px; }
        .admin-name { font-weight: bold; font-size: 14px; }
        .content { padding: 28px 30px; }
        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }
        .card, .panel {
            background: white;
            border: 1px solid #e5ebf3;
            border-radius: 13px;
            padding: 21px;
        }
        .card-label { color: #6b7b90; font-size: 13px; }
        .card-value { font-size: 29px; font-weight: bold; margin-top: 12px; }
        .panel { margin-bottom: 22px; }
        .panel h2 { margin: 0 0 18px; font-size: 18px; }
        .quick-links { display: flex; flex-wrap: wrap; gap: 10px; }
        .quick-links a, button, .button {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 10px 14px;
            background: #087e75;
            color: white;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }
        .logout { background: #f1f5f9 !important; color: #273b52 !important; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td {
            text-align: left;
            padding: 13px 12px;
            border-bottom: 1px solid #e9eef5;
            vertical-align: top;
        }
        th { background: #f8fafc; color: #61718a; white-space: nowrap; }
        td { overflow-wrap: anywhere; }
        select, input, textarea {
            width: 100%;
            max-width: 340px;
            padding: 10px;
            border: 1px solid #d6dfeb;
            border-radius: 7px;
            background: white;
            font: inherit;
        }
        textarea { max-width: 600px; min-height: 100px; }
        label { display: block; margin: 14px 0 7px; font-size: 13px; font-weight: bold; }
        .notice { padding: 13px; margin-bottom: 18px; border-radius: 8px; }
        .notice.success { background: #e8f8ee; color: #17663a; }
        .notice.error { background: #fff0f0; color: #a32121; }
        .empty { color: #738198; padding: 20px 0; }
        .status-form { display: flex; align-items: center; gap: 8px; min-width: 200px; }
        .status-form select { min-width: 120px; }
        .status-form button { padding: 9px; }
        .footer { color: #8290a3; font-size: 12px; padding: 10px 0 24px; }
        @media (max-width: 950px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 700px) {
            .layout { display: block; }
            .sidebar { width: 100%; padding: 15px; }
            .sidebar nav { display: flex; flex-wrap: wrap; gap: 4px; }
            .sidebar a { padding: 9px; }
            .nav-label { width: 100%; }
            .topbar { padding: 18px; align-items: flex-start; }
            .content { padding: 18px; }
            .cards { grid-template-columns: 1fr 1fr; gap: 10px; }
            .card, .panel { padding: 15px; }
            .card-value { font-size: 24px; }
        }
        @media (max-width: 420px) {
            .cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">+ EasyCare</div>
        <div class="brand-sub">Administration Panel</div>

        <nav>
            <div class="nav-label">Main Menu</div>
            <?= navLink('dashboard', 'Dashboard', $section) ?>
            <?= navLink('users', 'Users', $section) ?>
            <?= navLink('medicines', 'Medicine Records', $section) ?>
            <?= navLink('reminders', 'Reminder Records', $section) ?>
            <?= navLink('activities', 'Activity Records', $section) ?>
            <?= navLink('feedback', 'Feedback', $section) ?>
            <?= navLink('wellness', 'Wellness Content', $section) ?>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= e($pageTitles[$section]) ?></h1>
                <div class="muted">EasyCare Management System</div>
            </div>
            <div>
                <div class="admin-name"><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></div>
                <div class="muted"><?= e($_SESSION['admin_email'] ?? '') ?></div>
                <form method="POST" action="logout.php" style="margin-top:10px">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="logout" type="submit">Sign Out</button>
                </form>
            </div>
        </header>

        <div class="content">
            <?php if ($success !== ''): ?>
                <div class="notice success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="notice error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
                <div class="cards">
                    <div class="card"><div class="card-label">Registered Users</div><div class="card-value"><?= $counts['users'] ?></div></div>
                    <div class="card"><div class="card-label">Medicine Records</div><div class="card-value"><?= $counts['medicines'] ?></div></div>
                    <div class="card"><div class="card-label">Reminder Records</div><div class="card-value"><?= $counts['reminders'] ?></div></div>
                    <div class="card"><div class="card-label">Open Feedback</div><div class="card-value"><?= $counts['feedback'] ?></div></div>
                    <div class="card"><div class="card-label">Activity Records</div><div class="card-value"><?= $counts['activities'] ?></div></div>
                    <div class="card"><div class="card-label">Wellness Articles</div><div class="card-value"><?= $counts['wellness'] ?></div></div>
                </div>

                <section class="panel">
                    <h2>Quick Access</h2>
                    <div class="quick-links">
                        <a href="?section=users">View Users</a>
                        <a href="?section=medicines">View Medicines</a>
                        <a href="?section=reminders">View Reminders</a>
                        <a href="?section=feedback">Manage Feedback</a>
                        <a href="?section=wellness">Manage Wellness</a>
                        <a href="?section=activities">View Activities</a>
                    </div>
                </section>

                <section class="panel">
                    <h2>Administration Overview</h2>
                    <p class="muted">
                        Use the menu to review records stored in the EasyCare database.
                        Counts reflect the records available in the connected database.
                    </p>
                </section>

            <?php elseif ($section === 'wellness'): ?>
                <section class="panel">
                    <h2>Add Wellness Content</h2>
                    <p class="muted">New content is saved as a draft, not published automatically.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_wellness">

                        <label for="title">Title</label>
                        <input id="title" name="title" maxlength="200" required>

                        <label for="category">Category</label>
                        <input id="category" name="category" maxlength="100"
                               placeholder="For example, Healthy Living">

                        <label for="content">Content</label>
                        <textarea id="content" name="content" required></textarea>

                        <p><button type="submit">Save Draft</button></p>
                    </form>
                </section>
                <section class="panel">
                    <h2>Existing Wellness Content</h2>
                    <?php if (!$data): ?>
                        <p class="empty">No wellness records found, or the query could not be completed.</p>
                    <?php else: ?>
                        <div class="table-wrap"><table>
                            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Language</th><th>Published</th></tr></thead>
                            <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= e($row['id'] ?? '') ?></td>
                                    <td><?= e($row['title'] ?? '') ?></td>
                                    <td><?= e($row['category'] ?? '') ?></td>
                                    <td><?= e($row['language'] ?? '') ?></td>
                                    <td><?= !empty($row['is_published']) ? 'Yes' : 'No' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </section>

            <?php elseif ($section === 'feedback'): ?>
                <section class="panel">
                    <h2>Submitted Feedback</h2>
                    <?php if (!$data): ?>
                        <p class="empty">No feedback records found</p>
                    <?php else: ?>
                        <div class="table-wrap"><table>
                            <thead><tr><th>ID</th><th>Subject</th><th>Message</th><th>Date</th><th>Status</th><th>Update</th></tr></thead>
                            <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><?= e($row['id'] ?? '') ?></td>
                                    <td><?= e($row['subject'] ?? '') ?></td>
                                    <td><?= e($row['message'] ?? '') ?></td>
                                    <td><?= e($row['created_at'] ?? '') ?></td>
                                    <td><?= e($row['status'] ?? 'New') ?></td>
                                    <td>
                                        <form class="status-form" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="update_feedback">
                                            <input type="hidden" name="feedback_id" value="<?= e($row['id'] ?? '') ?>">
                                            <select name="status" aria-label="Feedback status">
                                                <?php foreach (['New', 'In Progress', 'Resolved'] as $option): ?>
                                                    <option value="<?= e($option) ?>"
                                                        <?= ($row['status'] ?? 'New') === $option ? 'selected' : '' ?>>
                                                        <?= e($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </section>

            <?php else: ?>
                <section class="panel">
                    <h2><?= e($pageTitles[$section]) ?></h2>
                    <?php if (!$data): ?>
                        <p class="empty">
                            No records are available, or the database query needs adjustment
                            to match your existing table columns.
                        </p>
                    <?php else: ?>
                        <div class="table-wrap"><table>
                            <thead><tr>
                                <?php foreach (array_keys($data[0]) as $column): ?>
                                    <th><?= e(ucwords(str_replace('_', ' ', $column))) ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= e($value) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="footer">EasyCare Admin Panel &copy; <?= date('Y') ?></div>
        </div>
    </main>
</div>
</body>
</html>