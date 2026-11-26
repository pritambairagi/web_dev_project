<?php
require_once __DIR__ . '/../config/db.php';

$error = '';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = getDbConnection()->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                header("Location: index.php");
                exit;
            }
            $error = 'Invalid username or password.';
        } catch (PDOException $ex) {
            $error = 'Could not sign in. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | SalesPro</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    .login-box {
      max-width: 420px;
      margin: 80px auto;
      padding: 36px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--r);
      box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
  </style>
</head>
<body style="background:var(--soft);">
  <div class="wrap">
    <div class="login-box">
      <div style="text-align:center;margin-bottom:24px;">
        <img src="../salespro-logo.png" alt="SalesPro" style="max-width:180px;margin:0 auto 12px;">
        <h2 style="font-size:22px;">Admin Portal</h2>
        <p style="color:var(--muted);font-size:14px;">Sign in to manage products, orders & messages</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background:#fde8e8;color:#9b1c1c;padding:12px;border-radius:var(--r);margin-bottom:18px;font-size:14px;font-weight:600;">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="form">
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autocomplete="username" value="<?= e($username ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="button button-blue" style="width:100%;margin-top:8px;">Sign In</button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:13px;">
        <a href="../index.php" style="color:var(--blue-d);font-weight:600;">← Back to SalesPro Store</a>
      </p>
    </div>
  </div>
</body>
</html>
