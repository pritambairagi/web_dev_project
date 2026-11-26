<?php
$adminTitle = 'Order Details';
$activeAdminNav = 'orders';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/customer-auth.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$trackingUrlValid = static fn (string $url): bool => strlen($url) <= 500
    && filter_var($url, FILTER_VALIDATE_URL)
    && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id ?: 0]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: orders.php");
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT oi.*, p.image_url
                               FROM order_items oi
                               LEFT JOIN products p ON oi.product_id = p.id
                               WHERE oi.order_id = ?");
    $itemStmt->execute([$order['id']]);
    $items = $itemStmt->fetchAll();
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Could not load this order. Please try again.');
}

$message = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    $tracking = [];
    foreach (['tracking_carrier' => 100, 'tracking_number' => 120, 'tracking_url' => 500] as $field => $limit) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value) || strlen(trim($value)) > $limit) {
            $error = 'Check the tracking field lengths.';
            break;
        }
        $tracking[$field] = trim($value) === '' ? null : trim($value);
    }
    if (!csrfValid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Refresh the page and try again.';
    } elseif (!in_array($newStatus, $statuses, true)) {
        $error = 'Choose a valid order status.';
    } elseif (isset($tracking['tracking_url']) && !$trackingUrlValid($tracking['tracking_url'])) {
        $error = 'Enter a valid HTTP or HTTPS tracking URL.';
    }
    if ($error === '') {
        try {
            $updateStmt = $pdo->prepare('UPDATE orders SET status = ?, tracking_carrier = ?, tracking_number = ?, tracking_url = ? WHERE id = ?');
            $updateStmt->execute([$newStatus, ...array_values($tracking), $order['id']]);
            $order = array_replace($order, $tracking, ['status' => $newStatus]);
            $message = 'Order updated.';
        } catch (PDOException $ex) {
            $error = 'Could not update this order. Please try again.';
        }
    }
}
try {
    require_once __DIR__ . '/includes/header.php';
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Could not load this order. Please try again.');
}
?>

<div class="wrap" style="padding-top:32px;">
  <?php if (!empty($message)): ?>
    <div style="background:#def7ec;color:#03543f;padding:12px 18px;border-radius:var(--r);margin-bottom:20px;font-weight:600;">
      <?= e($message) ?>
    </div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div role="alert" style="background:#fde8e8;color:#9b1c1c;padding:12px 18px;border-radius:var(--r);margin-bottom:20px;">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <div class="panel" style="max-width:860px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:16px;flex-wrap:wrap;gap:12px;">
      <div>
        <a href="orders.php" style="color:var(--blue-d);font-size:13px;font-weight:600;">← Back to Orders</a>
        <h1 style="font-size:24px;margin-top:6px;">Order <?= e($order['order_number']) ?></h1>
        <span style="font-size:13px;color:var(--muted);">Placed: <?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></span>
      </div>
    </div>

    <form method="POST" class="form" style="margin-bottom:24px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <div class="grid g2">
        <div class="field">
          <label for="status">Status *</label>
          <select id="status" name="status" required>
            <?php foreach ($statuses as $st): ?>
              <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="tracking_carrier">Carrier (optional)</label>
          <input id="tracking_carrier" name="tracking_carrier" maxlength="100" value="<?= e($order['tracking_carrier'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="tracking_number">Tracking number (optional)</label>
          <input id="tracking_number" name="tracking_number" maxlength="120" value="<?= e($order['tracking_number'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="tracking_url">Tracking URL (optional)</label>
          <input id="tracking_url" name="tracking_url" type="url" maxlength="500" value="<?= e($order['tracking_url'] ?? '') ?>">
        </div>
      </div>
      <div>
        <button type="submit" name="update_status" value="1" class="button button-blue button-sm">Update order</button>
        <?php if ($trackingUrlValid($order['tracking_url'] ?? '')): ?>
          <a class="button button-ghost button-sm" href="<?= e($order['tracking_url']) ?>" target="_blank" rel="noopener noreferrer">Open tracking link</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="grid g2" style="margin-bottom:24px;">
      <div style="background:var(--soft);padding:18px;border-radius:var(--r);">
        <h3 style="font-size:15px;margin-bottom:8px;">Customer Information</h3>
        <p style="font-size:14px;color:var(--ink);line-height:1.6;">
          <strong>Name:</strong> <?= e($order['customer_name']) ?><br>
          <strong>Email:</strong> <?= e($order['customer_email']) ?><br>
          <strong>Phone:</strong> <?= e($order['customer_phone']) ?>
        </p>
      </div>
      <div style="background:var(--soft);padding:18px;border-radius:var(--r);">
        <h3 style="font-size:15px;margin-bottom:8px;">Shipping & Payment</h3>
        <p style="font-size:14px;color:var(--ink);line-height:1.6;">
          <strong>Address:</strong><br><?= nl2br(e($order['shipping_address'])) ?><br>
          <strong>Payment Method:</strong> <?= e($order['payment_method']) ?>
          <?php if (!empty($order['notes'])): ?>
            <br><strong>Notes:</strong> <?= e($order['notes']) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <h3 style="font-size:16px;margin-bottom:12px;">Items Ordered</h3>
    <table class="admin-table" style="margin-bottom:24px;">
      <thead>
        <tr>
          <th>Item</th>
          <th>Unit Price</th>
          <th>Qty</th>
          <th style="text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:12px;">
                <?php if (!empty($it['image_url'])): ?>
                  <img src="<?= e($it['image_url']) ?>" alt="" style="width:40px;height:34px;object-fit:cover;border-radius:4px;">
                <?php endif; ?>
                <strong><?= e($it['product_name']) ?></strong>
              </div>
            </td>
            <td><?= formatPrice($it['price']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td style="text-align:right;"><strong><?= formatPrice($it['total_price']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="max-width:300px;margin-left:auto;">
      <div class="sum-line"><span>Subtotal:</span> <span><?= formatPrice($order['subtotal']) ?></span></div>
      <div class="sum-line"><span>Shipping Fee:</span> <span><?= $order['shipping_fee'] > 0 ? formatPrice($order['shipping_fee']) : 'Free' ?></span></div>
      <div class="sum-line total"><span>Grand Total:</span> <span><?= formatPrice($order['total_amount']) ?></span></div>
    </div>

    <div style="margin-top:32px;display:flex;gap:12px;justify-content:flex-end;">
      <button onclick="window.print()" class="button button-ghost button-sm">Print Invoice</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
