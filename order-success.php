<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customer-auth.php';
header('Cache-Control: private, no-store');

$orderNumber = is_string($_GET['order'] ?? null) ? trim($_GET['order']) : '';

if (empty($orderNumber)) {
    header("Location: index.php");
    exit;
}

try {
    $pdo = getDbConnection();
    $customer = currentCustomer($pdo);
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if (!$order || ($order['customer_id'] === null
        ? ($_SESSION['guest_order_receipts'][$order['order_number']] ?? null) !== (int)$order['id']
        : !$customer || (int)$customer['id'] !== (int)$order['customer_id'])) {
        http_response_code(404);
        exit('Order not found.');
    }

    $itemsStmt = $pdo->prepare("SELECT oi.*, p.image_url
                                FROM order_items oi
                                LEFT JOIN products p ON oi.product_id = p.id
                                WHERE oi.order_id = ?");
    $itemsStmt->execute([$order['id']]);
    $orderItems = $itemsStmt->fetchAll();
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Could not load your order. Please try again.');
}

$pageTitle = 'Order Confirmed - ' . $order['order_number'] . ' | SalesPro';
$pageDescription = 'Thank you for your order with SalesPro.';
$activeNav = '';

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main">
    <section class="page-head" style="background:#eaf7ee;">
      <div class="wrap" style="text-align:center;">
        <div style="width:64px;height:64px;background:#28a745;color:#fff;border-radius:50%;display:inline-grid;place-items:center;font-size:32px;margin-bottom:14px;">
          ✓
        </div>
        <h1 style="color:#1e7e34;">Thank you for your order!</h1>
        <p style="color:#2b663b;font-size:16px;">We have received your order and will begin processing it right away.</p>
      </div>
    </section>

    <section class="section" style="padding-top:40px;">
      <div class="wrap" style="max-width:860px;">
        <div class="panel">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
            <div>
              <h2 style="font-size:24px;"><?= e($order['order_number']) ?></h2>
              <span style="font-size:13px;color:var(--muted);">Placed on <?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></span>
            </div>
            <div style="text-align:right;">
              <span style="display:inline-block;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;background:#e5f4fb;color:var(--blue-d);">
                Status: <?= e($order['status']) ?>
              </span>
            </div>
          </div>

          <div class="grid g2" style="margin-bottom:28px;">
            <div>
              <h3 style="font-size:16px;margin-bottom:8px;">Customer Information</h3>
              <p style="color:var(--muted);font-size:14px;line-height:1.6;">
                <strong><?= e($order['customer_name']) ?></strong><br>
                Email: <?= e($order['customer_email']) ?><br>
                Phone: <?= e($order['customer_phone']) ?>
              </p>
            </div>
            <div>
              <h3 style="font-size:16px;margin-bottom:8px;">Shipping & Payment</h3>
              <p style="color:var(--muted);font-size:14px;line-height:1.6;">
                <strong>Address:</strong><br><?= nl2br(e($order['shipping_address'])) ?><br>
                <strong>Payment:</strong> <?= e($order['payment_method']) ?>
              </p>
            </div>
          </div>

          <h3 style="font-size:18px;margin-bottom:14px;">Items in Your Order</h3>
          <div style="border-top:1px solid var(--line);margin-bottom:24px;">
            <?php foreach ($orderItems as $item): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--line);gap:16px;">
                <div style="display:flex;align-items:center;gap:14px;">
                  <?php if (!empty($item['image_url'])): ?>
                    <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>" style="width:50px;height:40px;object-fit:cover;border-radius:4px;">
                  <?php endif; ?>
                  <div>
                    <strong><?= e($item['product_name']) ?></strong>
                    <div style="font-size:13px;color:var(--muted);">Qty: <?= (int)$item['quantity'] ?> × <?= formatPrice($item['price']) ?></div>
                  </div>
                </div>
                <div>
                  <strong><?= formatPrice($item['total_price']) ?></strong>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="max-width:320px;margin-left:auto;">
            <div class="sum-line"><span>Subtotal:</span> <span><?= formatPrice($order['subtotal']) ?></span></div>
            <div class="sum-line"><span>Shipping:</span> <span><?= $order['shipping_fee'] > 0 ? formatPrice($order['shipping_fee']) : 'Free' ?></span></div>
            <div class="sum-line total"><span>Total:</span> <span><?= formatPrice($order['total_amount']) ?></span></div>
          </div>

          <div style="margin-top:36px;text-align:center;display:flex;justify-content:center;gap:16px;flex-wrap:wrap;">
            <a href="products.php" class="button button-dark">Continue Shopping</a>
            <button onclick="window.print()" class="button button-ghost">Print Receipt</button>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
