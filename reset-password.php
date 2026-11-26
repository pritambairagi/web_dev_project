<?php
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-page.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$pdo = getDbConnection();
$error = '';
$message = $_SESSION['reset_message'] ?? '';
unset($_SESSION['reset_message']);
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : authInput('token');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = authPassword();
    if (!csrfValid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    elseif (!authAllowed($pdo, 'reset', '', 10)) $error = 'Too many attempts. Please try again later.';
    elseif (!authPasswordValid($password)) $error = 'Password must be 12 to 72 bytes.';
    elseif (authConsumeToken($pdo, $token, 'reset', $password)) {
        $_SESSION['reset_message'] = 'Your password has been reset. You can now sign in.';
        header('Location: reset-password.php', true, 303); exit;
    }
    else $error = 'This link is invalid or has expired. Please request another.';
}
authPageStart('Reset password', $message, $error);
if ($token !== '') {
    authFormStart();
    echo '<input type="hidden" name="token" value="' . e($token) . '">';
    authField('password', 'New password', 'password', 'new-password', '', true, 'minlength="12" maxlength="72" aria-describedby="password-help"');
    echo '<small id="password-help">Use 12 to 72 bytes.</small><button class="button button-blue" type="submit">Reset password</button></form>';
}
echo '<p style="margin-top:18px"><a href="forgot-password.php">Request a reset link</a> · <a href="login.php">Sign in</a></p>';
authPageEnd();
