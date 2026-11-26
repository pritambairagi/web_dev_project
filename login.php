<?php
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-page.php';
header('Cache-Control: private, no-store');
$pdo = getDbConnection();
$error = '';
$identity = '';
$flash = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' ? getFlash('auth') : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = authInput('identity');
    $password = authPassword();
    if (!csrfValid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    elseif (!authAllowed($pdo, 'login', $identity)) $error = 'Too many attempts. Please try again later.';
    else {
        $stmt = $pdo->prepare('SELECT id, password_hash, verified_at, auth_version FROM customers WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer && authPasswordValid($password) && password_verify($password, $customer['password_hash']) && $customer['verified_at'] !== null) {
            mergeGuestCart($pdo, (int)$customer['id']);
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['customer_id'] = (int)$customer['id'];
            $_SESSION['customer_auth_version'] = (int)$customer['auth_version'];
            header('Location: account.php'); exit;
        }
        $error = 'Incorrect details or email not verified. Please try again.';
    }
}
authPageStart('Sign in', $flash['message'] ?? '', $error);
authFormStart();
authField('identity', 'Username or email', 'text', 'username', $identity, true, 'maxlength="150"');
authField('password', 'Password', 'password', 'current-password');
echo '<button class="button button-blue" type="submit">Sign in</button></form><p style="margin-top:18px"><a href="forgot-password.php">Forgot password?</a> · <a href="verify-email.php">Resend verification</a> · <a href="register.php">Create account</a></p>';
authPageEnd();
