<?php
require_once __DIR__ . '/../config/db.php';

function currentCustomer(PDO $pdo): ?array {
    if (!isset($_SESSION['customer_id'], $_SESSION['customer_auth_version'])) return null;
    $stmt = $pdo->prepare('SELECT id, username, email, name, phone, auth_version FROM customers WHERE id = ? AND verified_at IS NOT NULL');
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer || (int)$customer['auth_version'] !== (int)$_SESSION['customer_auth_version']) {
        unset($_SESSION['customer_id'], $_SESSION['customer_auth_version']);
        return null;
    }
    unset($customer['auth_version']);
    return $customer;
}
function requireCustomer(PDO $pdo): array {
    header('Cache-Control: private, no-store');
    $customer = currentCustomer($pdo);
    if (!$customer) { header('Location: login.php'); exit; }
    return $customer;
}
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfValid(mixed $token): bool {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function authInput(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}
function authPassword(): string {
    return isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
}
function authPasswordValid(string $password): bool {
    return strlen($password) >= 12 && strlen($password) <= 72 && !str_contains($password, "\0");
}
function authLimit(PDO $pdo, string $action, string $identity = '', int $limit = 10): bool {
    $bucket = hash('sha256', $action . '|' . $identity);
    $stmt = $pdo->prepare('INSERT INTO customer_auth_limits (bucket, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= UTC_TIMESTAMP(), 1, attempts + 1), expires_at = IF(expires_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), expires_at)');
    $stmt->execute([$bucket]);
    $stmt = $pdo->prepare('SELECT attempts FROM customer_auth_limits WHERE bucket = ?');
    $stmt->execute([$bucket]);
    return (int)$stmt->fetchColumn() <= $limit;
}
function authAllowed(PDO $pdo, string $action, string $identity = '', int $limit = 10): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipAllowed = authLimit($pdo, $action . ':ip', $ip, $limit);
    $identityAllowed = $identity === '' || authLimit($pdo, $action . ':identity', strtolower($identity), $limit);
    return $ipAllowed && $identityAllowed;
}
function authIssueToken(PDO $pdo, int $id, string $purpose): string {
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO customer_tokens (customer_id, purpose, token_hash, expires_at, created_at) VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), created_at = VALUES(created_at)');
    $stmt->execute([$id, $purpose, hash('sha256', $token)]);
    return $token;
}
function authConsumeToken(PDO $pdo, string $token, string $purpose, ?string $password = null): bool {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) return false;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT customer_id FROM customer_tokens WHERE token_hash = ? AND purpose = ? AND expires_at > UTC_TIMESTAMP()');
        $stmt->execute([hash('sha256', $token), $purpose]);
        $id = $stmt->fetchColumn();
        if (!$id) { $pdo->rollBack(); return false; }
        $stmt = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        if (!$hash) { $pdo->rollBack(); return false; }
        $stmt = $pdo->prepare('SELECT customer_id FROM customer_tokens WHERE customer_id = ? AND token_hash = ? AND purpose = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
        $stmt->execute([$id, hash('sha256', $token), $purpose]);
        if (!$stmt->fetchColumn()) { $pdo->rollBack(); return false; }
        if ($purpose === 'verify') {
            if (!is_string($password) || !authPasswordValid($password) || !password_verify($password, $hash)) {
                $pdo->rollBack();
                return false;
            }
            $stmt = $pdo->prepare('UPDATE customers SET verified_at = COALESCE(verified_at, UTC_TIMESTAMP()) WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare('UPDATE customers SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        $stmt = $pdo->prepare('DELETE FROM customer_tokens WHERE customer_id = ? AND purpose = ?');
        $stmt->execute([$id, $purpose]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
