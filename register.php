<?php
require_once __DIR__ . '/includes/customer-auth.php';
require_once __DIR__ . '/includes/customer-mail.php';
require_once __DIR__ . '/includes/customer-page.php';
header('Cache-Control: private, no-store');
$pdo = getDbConnection();
$error = $message = '';
$name = $username = $email = $phone = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = authInput('name'); $username = authInput('username'); $email = authInput('email'); $phone = authInput('phone'); $password = authPassword();
    if (!csrfValid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    elseif (!authAllowed($pdo, 'register', $email, 5)) $error = 'Too many attempts. Please try again later.';
    elseif ($name === '' || strlen($name) > 150 || !preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $username) || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($phone) > 50 || !authPasswordValid($password)) $error = 'Check your details. Usernames need 3 to 50 letters, numbers, dots, dashes or underscores. Passwords need 12 to 72 bytes.';
    else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO customers (name, username, email, phone, password_hash, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
            $stmt->execute([$name, $username, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
            $token = authIssueToken($pdo, (int)$pdo->lastInsertId(), 'verify');
            $pdo->commit();
            customerMail($email, 'verify', $token);
            $message = 'If your account can be created, a verification email will be sent. You can also request another verification email.';
            $name = $username = $email = $phone = '';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() !== '23000') throw $e;
            $message = 'If your account can be created, a verification email will be sent. You can also request another verification email.';
        }
    }
}
authPageStart('Create account', $message, $error);
authFormStart();
authField('name', 'Full name', 'text', 'name', $name, true, 'maxlength="150"');
authField('username', 'Username', 'text', 'username', $username, true, 'minlength="3" maxlength="50"');
authField('email', 'Email', 'email', 'email', $email, true, 'maxlength="150"');
authField('phone', 'Phone', 'tel', 'tel', $phone, false, 'maxlength="50"');
authField('password', 'Password', 'password', 'new-password', '', true, 'minlength="12" maxlength="72" aria-describedby="password-help"');
echo '<small id="password-help">Use 12 to 72 bytes.</small><button class="button button-blue" type="submit">Create account</button></form><p style="margin-top:18px">Already have an account? <a href="login.php">Sign in</a></p>';
authPageEnd();
