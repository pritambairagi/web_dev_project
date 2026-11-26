<?php
require_once __DIR__ . '/includes/customer-auth.php';
$pdo = getDbConnection();
$customer = requireCustomer($pdo);
$id = (int)$customer['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $message = 'Please check your details and try again.';
    $type = 'error';
    $action = authInput('action');
    if (!csrfValid($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please try again.';
    } elseif ($action === 'profile') {
        $name = authInput('name'); $phone = authInput('phone');
        if ($name !== '' && mb_strlen($name) <= 150 && mb_strlen($phone) <= 50) {
            $stmt = $pdo->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $id]);
            $message = 'Profile updated.'; $type = 'success';
        }
    } elseif ($action === 'password') {
        $current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $password = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if (!authAllowed($pdo, 'change-password', (string)$id)) {
            $message = 'Too many attempts. Please try again later.';
        } elseif (!authPasswordValid($password) || $password !== $confirm) {
            $message = 'Passwords must match and be 12 to 72 bytes long. Some characters use more than one byte.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT password_hash FROM customers WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]); $hash = $stmt->fetchColumn();
                if ($hash && authPasswordValid($current) && password_verify($current, $hash)) {
                    $stmt = $pdo->prepare("DELETE FROM customer_tokens WHERE customer_id = ? AND purpose = 'reset'");
                    $stmt->execute([$id]);
                    $stmt = $pdo->prepare('UPDATE customers SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
                    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                    $pdo->commit();
                    unset($_SESSION['customer_id'], $_SESSION['customer_auth_version'], $_SESSION['csrf_token']);
                    session_regenerate_id(true);
                    setFlash('auth', 'Password updated. Please sign in again.');
                    header('Location: login.php'); exit;
                }
                $pdo->rollBack(); $message = 'Current password is incorrect.';
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }
    } elseif (in_array($action, ['save_address', 'delete_address', 'default_address'], true)) {
        $rawId = $_POST['address_id'] ?? '';
        $addressId = is_string($rawId) && ctype_digit($rawId) ? (int)$rawId : 0;
        $label = authInput('label'); $recipient = authInput('recipient_name');
        $phone = authInput('address_phone'); $address = authInput('address_text');
        $valid = $action !== 'save_address' || ($label !== '' && mb_strlen($label) <= 80 && $recipient !== '' && mb_strlen($recipient) <= 150 && mb_strlen($phone) <= 50 && $address !== '' && strlen($address) <= 65535);
        if ($valid && ($action === 'save_address' || $addressId > 0)) {
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare('SELECT id FROM customers WHERE id = ? FOR UPDATE'); $lock->execute([$id]);
                $owned = false;
                if ($addressId > 0) {
                    $stmt = $pdo->prepare('SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?');
                    $stmt->execute([$addressId, $id]); $owned = (bool)$stmt->fetchColumn();
                }
                if ($action === 'save_address' && ($addressId === 0 && $rawId === '' || $owned)) {
                    if ($owned) {
                        $stmt = $pdo->prepare('UPDATE customer_addresses SET label = ?, recipient_name = ?, phone = ?, address_text = ? WHERE id = ? AND customer_id = ?');
                        $stmt->execute([$label, $recipient, $phone, $address, $addressId, $id]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_text, is_default) SELECT ?, ?, ?, ?, ?, NOT EXISTS (SELECT 1 FROM customer_addresses WHERE customer_id = ?)');
                        $stmt->execute([$id, $label, $recipient, $phone, $address, $id]);
                    }
                    $message = 'Address saved.'; $type = 'success';
                } elseif ($owned && $action === 'default_address') {
                    $stmt = $pdo->prepare('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?'); $stmt->execute([$addressId, $id]);
                    $message = 'Default address updated.'; $type = 'success';
                } elseif ($owned && $action === 'delete_address') {
                    $stmt = $pdo->prepare('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?'); $stmt->execute([$addressId, $id]);
                    $stmt = $pdo->prepare('SELECT id FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id ASC LIMIT 1'); $stmt->execute([$id]); $first = $stmt->fetchColumn();
                    if ($first) { $stmt = $pdo->prepare('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?'); $stmt->execute([$first, $id]); }
                    $message = 'Address deleted.'; $type = 'success';
                }
                $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        }
    }
    setFlash('account', $message, $type);
    header('Location: account.php'); exit;
}
$stmt = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC'); $stmt->execute([$id]); $addresses = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT order_number, status, total_amount, created_at, tracking_carrier, tracking_number, tracking_url FROM orders WHERE customer_id = ? ORDER BY created_at DESC, id DESC'); $stmt->execute([$id]); $orders = $stmt->fetchAll();
$cart = getCartDetails($pdo); $flash = getFlash('account');
$pageTitle = 'Your account | SalesPro'; $pageDescription = 'Manage your SalesPro account and orders.';
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="account wrap">
  <div class="account-heading"><div><h1>Your account</h1><p>Welcome back, <?= e($customer['name']) ?>.</p></div><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="button button-ghost button-sm">Sign out</button></form></div>
  <?php if ($flash): ?><p class="account-notice" role="status"><?= e($flash['message']) ?></p><?php endif; ?>
  <div class="account-grid">
    <section class="account-card account-wide"><div class="account-card-head"><div><h2>Orders</h2><p>View your orders and delivery updates.</p></div></div>
      <?php if (!$orders): ?><p class="account-empty">No orders yet. <a href="products.php">Browse products</a></p><?php else: ?>
      <?php foreach ($orders as $order): $url = $order['tracking_url'] ?? ''; $safeUrl = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true); ?>
        <article class="account-order"><div><h3><?= e($order['order_number']) ?></h3><p><?= e(date('M j, Y', strtotime($order['created_at']))) ?> · <?= e(formatPrice($order['total_amount'])) ?></p><p class="account-status"><?= e($order['status']) ?></p>
        <?php if ($order['tracking_carrier'] || $order['tracking_number']): ?><p>Tracking: <?= e(trim(($order['tracking_carrier'] ?? '') . ' ' . ($order['tracking_number'] ?? ''))) ?></p><?php endif; ?></div><div class="account-links"><?php if ($safeUrl): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Track shipment ↗</a><?php endif; ?><a href="order-success.php?order=<?= rawurlencode($order['order_number']) ?>">View receipt →</a></div></article>
      <?php endforeach; endif; ?>
    </section>
    <section class="account-card"><h2>Your cart</h2><?php if ($cart['count']): ?><ul class="account-cart-items"><?php foreach ($cart['items'] as $item): ?><li><span><?= e($item['name']) ?></span><span>Qty: <?= (int)$item['quantity'] ?></span></li><?php endforeach; ?></ul><p><?= (int)$cart['count'] ?> item<?= $cart['count'] === 1 ? '' : 's' ?> · <?= e(formatPrice($cart['subtotal'])) ?> subtotal</p><?php else: ?><p>Your cart is empty.</p><?php endif; ?><a class="button button-dark button-sm account-cta" href="cart.php">Manage cart</a></section>
    <section class="account-card"><h2>Profile</h2><p class="account-meta">Username: <?= e($customer['username']) ?><br>Email: <?= e($customer['email']) ?></p><form class="account-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="profile"><label>Name *<input name="name" required maxlength="150" value="<?= e($customer['name']) ?>"></label><label>Phone (optional)<input name="phone" maxlength="50" value="<?= e($customer['phone'] ?? '') ?>"></label><button class="button button-dark button-sm">Save profile</button></form></section>
    <section class="account-card"><h2>Change password</h2><p>Use 12 to 72 bytes. Some characters use more than one byte.</p><form class="account-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="password"><label>Current password *<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password *<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Confirm new password *<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><button class="button button-dark button-sm">Update password</button></form></section>
    <section class="account-card account-wide"><h2>Shipping addresses</h2><p>Save addresses for your next order.</p>
      <?php if (!$addresses): ?><p class="account-empty">No saved addresses yet. Add one below.</p><?php endif; ?>
      <div class="account-addresses"><?php foreach ($addresses as $address): ?><article class="account-address"><h3><?= e($address['label']) ?> <?php if ($address['is_default']): ?><span class="account-status">Default</span><?php endif; ?></h3><p><?= e($address['recipient_name']) ?><br><?= nl2br(e($address['address_text'])) ?><?php if ($address['phone']): ?><br><?= e($address['phone']) ?><?php endif; ?></p><details><summary>Edit address</summary><form class="account-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_address"><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><label>Label *<input name="label" maxlength="80" required value="<?= e($address['label']) ?>"></label><label>Recipient *<input name="recipient_name" maxlength="150" required value="<?= e($address['recipient_name']) ?>"></label><label>Phone (optional)<input name="address_phone" maxlength="50" value="<?= e($address['phone']) ?>"></label><label>Address *<textarea name="address_text" required><?= e($address['address_text']) ?></textarea></label><button class="button button-dark button-sm">Save address</button></form></details><div class="account-actions"><?php if (!$address['is_default']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="default_address"><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><button>Set as default</button></form><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_address"><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><button>Delete</button></form></div></article><?php endforeach; ?></div>
      <details class="account-add"><summary>Add an address</summary><form class="account-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="save_address"><label>Label *<input name="label" maxlength="80" placeholder="Home" required></label><label>Recipient *<input name="recipient_name" maxlength="150" required></label><label>Phone (optional)<input name="address_phone" maxlength="50"></label><label>Address *<textarea name="address_text" required></textarea></label><button class="button button-dark button-sm">Save address</button></form></details>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
