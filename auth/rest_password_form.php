<?php
$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    exit('Invalid reset link. Request a new password reset email.');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset EasyCare Password</title>
<style>
body{font-family:Arial,sans-serif;background:#eef5ff;padding:24px}
main{max-width:420px;margin:40px auto;background:white;padding:24px;border-radius:14px}
input,button{box-sizing:border-box;width:100%;padding:12px;margin:8px 0}
button{background:#087e75;color:white;border:0;border-radius:7px}
</style>
</head>
<body><main>
<h2>Reset EasyCare Password</h2>
<form id="resetForm">
<input type="hidden" id="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
<label>New password (minimum 8 characters)</label>
<input id="password" type="password" minlength="8" required autocomplete="new-password">
<label>Confirm new password</label>
<input id="confirm" type="password" minlength="8" required autocomplete="new-password">
<button type="submit">Reset Password</button>
<p id="message" role="status"></p>
</form>
<script>
document.getElementById('resetForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const password = document.getElementById('password').value;
  const confirm = document.getElementById('confirm').value;
  const message = document.getElementById('message');
  if (password.length < 8) { message.textContent = 'Password must be at least 8 characters.'; return; }
  if (password !== confirm) { message.textContent = 'Passwords do not match.'; return; }
  try {
    const response = await fetch('reset_password.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        token: document.getElementById('token').value,
        new_password: password
      })
    });
    const result = await response.json();
    message.textContent = result.message || (result.success ? 'Password reset successful.' : 'Could not reset password.');
    if (result.success) document.getElementById('resetForm').reset();
  } catch (_) {
    message.textContent = 'Request failed. Check your connection and try again.';
  }
});
</script>
</main></body></html>