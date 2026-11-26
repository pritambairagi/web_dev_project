<?php
require_once __DIR__ . '/includes/customer-auth.php';
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!csrfValid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request.'); }
unset($_SESSION['customer_id'], $_SESSION['customer_auth_version'], $_SESSION['csrf_token']);
session_regenerate_id(true);
header('Location: login.php');
exit;
