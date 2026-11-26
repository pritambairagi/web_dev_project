<?php
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-mail.php';
require_once __DIR__ . '/includes/customer-page.php';
header('Cache-Control: private, no-store');
$pdo = getDbConnection();
$error = $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = authInput('email');
    if (!csrfValid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    else {
        if (authAllowed($pdo, 'reset-mail', $email, 5) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT id, email FROM customers WHERE email = ? AND verified_at IS NOT NULL');
            $stmt->execute([$email]);
            if ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) customerMail($customer['email'], 'reset', authIssueToken($pdo, (int)$customer['id'], 'reset'));
        }
        $message = 'If a verified account exists for that email, a password reset link will be sent.';
    }
}
authPageStart('Forgot password', $message, $error);
authFormStart();
authField('email', 'Email', 'email', 'email', '', true, 'maxlength="150"');
echo '<button class="button button-blue" type="submit">Send reset link</button></form><p style="margin-top:18px"><a href="login.php">Back to sign in</a></p>';
authPageEnd();
