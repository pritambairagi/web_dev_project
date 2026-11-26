<?php
// Run from the project root with PHP CLI. This test uses no database or mail server.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/customer-auth.php';

function check(bool $ok, string $label): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "PASS: $label\n";
}

$_SESSION = [];
check(!csrfValid('') && !csrfValid(['token']), 'Missing and malformed CSRF tokens are rejected');
$token = csrfToken();
check(strlen($token) === 64 && csrfValid($token) && csrfToken() === $token, 'CSRF token works in the issuing session');
$_SESSION = [];
check(!csrfValid($token) && csrfToken() !== $token, 'CSRF token does not work in another session');
check(authPasswordValid(str_repeat('a', 12)) && authPasswordValid(str_repeat('a', 72)), 'Password length boundaries are accepted');
check(!authPasswordValid(str_repeat('a', 11)) && !authPasswordValid(str_repeat('a', 73)) && !authPasswordValid(str_repeat('a', 12) . "\0"), 'Unsafe password inputs are rejected');
