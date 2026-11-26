<?php
$adminTitle = 'Order Management';
$activeAdminNav = 'orders';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/customer-auth.php';

$message = '';
$error = '';
$validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$orders = [];

try {
    $pdo = getDbConnection();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
        $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $newStatus = $_POST['status'] ?? '';
        if (!csrfValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $error = 'Refresh the page and try again.';
        } elseif (!$orderId || !in_array($newStatus, $validStatuses, true)) {
            $error = 'Choose a valid order and status.';
        } else {
            $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $orderId]);
            $message = "Order #{$orderId} status changed to {$newStatus}.";
        }
    }
    $orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();
    require_once __DIR__ . '/includes/header.php';
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Could not process your order request. Please try again.');
}
?>

<div class="wrap" style="padding-top:32px;">
  <?php if (!empty($message)): ?>
    <div style="background:#def7ec;color:#03543f;padding:12px 18px;border-radius:var(--r);margin-bottom:20px;font-weight:600;">
      <?= e($message) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div style="background:#fde8e8;color:#9b1c1c;padding:12px 18px;border-radius:var(--r);margin-bottom:20px;font-weight:600;">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <div>
        <h2 style="font-size:22px;">Customer Orders</h2>
        <p style="color:var(--muted);font-size:14px;">Total of <?= count($orders) ?> orders recorded in salespro</p>
      </div>
    </div>

    <?php if (empty($orders)): ?>
      <p style="text-align:center;padding:40px 0;color:var(--muted);">No orders found. When customers place orders in the shop, they will appear here.</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $ord): ?>
              <tr>
                <td><strong><?= e($ord['order_number']) ?></strong></td>
                <td>
                  <?= e($ord['customer_name']) ?>
                  <div style="font-size:12px;color:var(--muted);"><?= e($ord['customer_email']) ?></div>
                </td>
                <td><?= e($ord['customer_phone']) ?></td>
                <td><strong><?= formatPrice($ord['total_amount']) ?></strong></td>
                <td><span style="font-size:12px;color:var(--muted);"><?= e($ord['payment_method']) ?></span></td>
                <td>
                  <form method="POST" action="orders.php" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                    <select name="status" aria-label="Order status" style="padding:4px 8px;font-size:12px;border-radius:4px;">
                      <?php foreach ($validStatuses as $st): ?>
                        <option value="<?= $st ?>" <?= $ord['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_status" value="1" class="button button-ghost button-sm" style="padding:4px 8px;min-height:28px;">Save</button>
                  </form>
                </td>
                <td><?= date('M j, Y', strtotime($ord['created_at'])) ?></td>
                <td>
                  <a href="order-view.php?id=<?= (int)$ord['id'] ?>" class="button button-dark button-sm" style="padding:4px 10px;min-height:28px;">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
