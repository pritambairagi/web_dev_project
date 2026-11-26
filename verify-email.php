<?php
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-mail.php';
require_once __DIR__ . '/includes/customer-page.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$pdo = getDbConnection();
$error = '';
$message = $_SESSION['verify_message'] ?? '';
unset($_SESSION['verify_message']);
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : authInput('token');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    elseif ($token !== '') {
        if (!authAllowed($pdo, 'verify', $token, 10)) $error = 'Too many attempts. Please try again later.';
        elseif (authConsumeToken($pdo, $token, 'verify', authPassword())) {
            $_SESSION['verify_message'] = 'Your email is verified. You can now sign in.';
            header('Location: verify-email.php', true, 303); exit;
        } else $error = 'The password or verification link is incorrect or expired.';
    } else {
        $email = authInput('email');
        if (authAllowed($pdo, 'verify-mail', $email, 5) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT id, email FROM customers WHERE email = ? AND verified_at IS NULL');
            $stmt->execute([$email]);
            if ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) customerMail($customer['email'], 'verify', authIssueToken($pdo, (int)$customer['id'], 'verify'));
        }
        $message = 'If an unverified account exists for that email, a verification link will be sent.';
    }
}
authPageStart('Verify email', $message, $error);
authFormStart();
if ($token !== '') {
    echo '<input type="hidden" name="token" value="' . e($token) . '">';
    authField('password', 'Registration password', 'password', 'current-password', '', true, 'minlength="12" maxlength="72"');
    echo '<button class="button button-blue" type="submit">Verify email</button><p><a href="verify-email.php">Request another verification link</a></p>';
}
else { authField('email', 'Email', 'email', 'email', '', true, 'maxlength="150"'); echo '<button class="button button-blue" type="submit">Send verification link</button>'; }
echo '</form><p style="margin-top:18px"><a href="login.php">Back to sign in</a></p>';
authPageEnd();
