<?php
$pageTitle = 'Shopping Cart | SalesPro';
$pageDescription = 'Review your cart items and complete checkout at SalesPro.';
$activeNav = '';

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customer-auth.php';
header('Cache-Control: private, no-store');

$error = '';
$cartData = ['items' => [], 'subtotal' => 0, 'shipping' => 0, 'total' => 0];
$addresses = [];
$defaultAddress = [];
$customer = null;
$addressId = $name = $email = $phone = $address = $paymentMethod = $notes = '';
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$postText = static fn ($key, $default = '') => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : $default;
$payments = [
    'Cash on Delivery' => 'Cash on Delivery (COD)',
    'Bank Transfer' => 'Direct Bank Transfer',
    'Mobile Payment' => 'bKash / Nagad / Rocket',
    'Credit / Debit Card' => 'Credit / Debit Card',
];

try {
    $pdo = getDbConnection();
    $customer = currentCustomer($pdo);
    $customerId = $customer['id'] ?? null;
    if ($customerId !== null) {
        $stmt = $pdo->prepare('SELECT id, label, recipient_name, phone, address_text, is_default
            FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id');
        $stmt->execute([$customerId]);
        $addresses = $stmt->fetchAll(PDO::FETCH_UNIQUE);
        $firstAddress = reset($addresses);
        if (!empty($firstAddress['is_default'])) {
            $defaultAddress = $firstAddress;
        }
    }
    $addressId = $post ? $postText('address_id') : ($defaultAddress ? (string)key($addresses) : '');
    $name = $postText('name', $defaultAddress['recipient_name'] ?? $customer['name'] ?? '');
    $email = $customer['email'] ?? $postText('email');
    $defaultPhone = trim($defaultAddress['phone'] ?? '');
    $phone = $post
        ? $postText('phone')
        : ($defaultPhone !== '' ? $defaultPhone : trim($customer['phone'] ?? ''));
    $address = $postText('address', $defaultAddress['address_text'] ?? '');
    $paymentMethod = $postText('payment_method', 'Cash on Delivery');
    $notes = $postText('notes');

    if ($post) {
        if (!csrfValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            throw new DomainException('Refresh the page and try again.');
        }
        if ($addressId !== '') {
            if (!isset($addresses[$addressId])) {
                throw new DomainException('Choose one of your saved addresses or enter a delivery address.');
            }
            $name = $addresses[$addressId]['recipient_name'];
            $savedPhone = trim($addresses[$addressId]['phone'] ?? '');
            if ($savedPhone !== '') {
                $phone = $savedPhone;
            }
            $address = $addresses[$addressId]['address_text'];
        }
        if ($name === '' || $phone === '' || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($name) > 150 || strlen($email) > 150 || strlen($phone) > 50
            || strlen($address) > 65535 || strlen($notes) > 65535 || !isset($payments[$paymentMethod])) {
            throw new DomainException('Check your shipping information and payment method.');
        }

        $pdo->beginTransaction();
        if ($customerId !== null) {
            lockCustomerCart($pdo, (int)$customerId);
            if (!currentCustomer($pdo)) {
                throw new DomainException('Your session expired. Sign in and try again.');
            }
        }
        $cartData = getCartDetails($pdo);
        if (!$cartData['items']) {
            throw new DomainException('Your cart is empty. Add items before placing an order.');
        }

        $orderNumber = 'SP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(9)));
        $stmt = $pdo->prepare("INSERT INTO orders
            (order_number, customer_id, customer_name, customer_email, customer_phone, shipping_address,
             payment_method, subtotal, shipping_fee, total_amount, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
        $stmt->execute([$orderNumber, $customerId, $name, $email, $phone, $address, $paymentMethod,
            $cartData['subtotal'], $cartData['shipping'], $cartData['total'], $notes]);
        $orderId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items
            (order_id, product_id, product_name, price, quantity, total_price) VALUES (?, ?, ?, ?, ?, ?)');
        $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $items = $cartData['items'];
        usort($items, static fn ($a, $b) => $a['id'] <=> $b['id']);
        foreach ($items as $item) {
            $stockStmt->execute([$item['quantity'], $item['id'], $item['quantity']]);
            if ($stockStmt->rowCount() !== 1) {
                throw new DomainException('An item no longer has enough stock. Update your cart and try again.');
            }
            $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['line_total']]);
        }

        if ($customerId !== null) {
            $pdo->prepare('DELETE FROM customer_cart_items WHERE customer_id = ?')->execute([$customerId]);
        }
        $pdo->commit();
        if ($customerId === null) {
            unset($_SESSION['cart']);
            $_SESSION['guest_order_receipts'][$orderNumber] = (int)$orderId;
        }
        header('Location: order-success.php?order=' . urlencode($orderNumber), true, 303);
        exit;
    }
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $ex instanceof DomainException ? $ex->getMessage() : 'Could not process checkout. Please try again.';
}

if (isset($pdo)) {
    try {
        $cartData = getCartDetails($pdo);
    } catch (Throwable $ex) {
        $error = 'Could not load your cart. Please try again.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main">
    <section class="page-head">
      <div class="wrap">
        <p class="crumbs"><a href="index.php">Home</a> / Cart</p>
        <h1>Your cart</h1>
        <p>Review your items and complete your order.</p>
      </div>
    </section>

    <section class="section" style="padding-top:36px">
      <div class="wrap">
        <?php if (!empty($error)): ?>
          <div style="background:#fde8e8;color:#9b1c1c;padding:14px 18px;border-radius:var(--r);margin-bottom:24px;font-weight:600;">
            <?= e($error) ?>
          </div>
        <?php endif; ?>

        <div class="split" id="cartContainer">
          <!-- Cart Items List -->
          <div>
            <div id="cartList">
              <?php if (empty($cartData['items'])): ?>
                <div class="panel" style="text-align:center;padding:50px 20px;">
                  <svg viewBox="0 0 24 24" style="width:60px;height:60px;stroke:var(--muted);fill:none;margin-bottom:16px;">
                    <path d="M3 4h2l2.5 11h10L20 7H6.5"/><circle cx="9" cy="19" r="1.2"/><circle cx="17" cy="19" r="1.2"/>
                  </svg>
                  <p class="empty" style="padding:0 0 16px;">Your cart is currently empty.</p>
                  <a class="button button-dark" href="products.php">Browse products</a>
                </div>
              <?php else: ?>
                <?php foreach ($cartData['items'] as $item): ?>
                  <div class="cart-row" data-item-id="<?= (int)$item['id'] ?>">
                    <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
                    <div>
                      <strong><a href="product-detail.php?id=<?= (int)$item['id'] ?>" style="color:inherit;"><?= e($item['name']) ?></a></strong>
                      <div style="color:var(--muted);font-size:13px;margin:2px 0;"><?= formatPrice($item['price']) ?> each</div>
                      <div class="qty">
                        <button type="button" data-q="-1" data-id="<?= (int)$item['id'] ?>" aria-label="Decrease quantity">−</button>
                        <span><?= (int)$item['quantity'] ?></span>
                        <button type="button" data-q="1" data-id="<?= (int)$item['id'] ?>" aria-label="Increase quantity">+</button>
                      </div>
                      <button type="button" class="link-btn" data-rm="<?= (int)$item['id'] ?>">Remove</button>
                    </div>
                    <div>
                      <strong><?= formatPrice($item['line_total']) ?></strong>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Checkout Form (Visible when cart has items) -->
            <?php if (!empty($cartData['items'])): ?>
              <div class="panel" style="margin-top:32px;">
                <h2 style="font-size:22px;margin-bottom:16px;">Shipping & Customer Information</h2>
                <form method="POST" action="cart.php" class="form" id="checkoutForm" data-validate>
                  <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                  <?php if ($addresses): ?>
                    <div class="field">
                      <label for="address_id">Delivery address *</label>
                      <select id="address_id" name="address_id">
                        <option value="">Enter an address manually</option>
                        <?php foreach ($addresses as $id => $saved): ?>
                          <option value="<?= (int)$id ?>" <?= $addressId === (string)$id ? 'selected' : '' ?>
                            data-name="<?= e($saved['recipient_name']) ?>" data-phone="<?= e(trim($saved['phone'] ?? '')) ?>"
                            data-address="<?= e($saved['address_text']) ?>">
                            <?= e($saved['label'] . ' - ' . $saved['address_text']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  <?php endif; ?>
                  <div class="grid g2">
                    <div class="field">
                      <label for="name">Full Name *</label>
                      <input id="name" name="name" type="text" <?= !$addresses ? 'required' : '' ?> maxlength="150" placeholder="John Doe" value="<?= e($name) ?>">
                      <span class="err"></span>
                    </div>
                    <div class="field">
                      <label for="email">Email Address *</label>
                      <input id="email" name="email" type="email" required <?= $customer ? 'readonly' : '' ?> maxlength="150" placeholder="john@example.com" value="<?= e($email) ?>">
                      <span class="err"></span>
                    </div>
                  </div>

                  <div class="grid g2">
                    <div class="field">
                      <label for="phone">Phone Number *</label>
                      <input id="phone" name="phone" type="tel" <?= !$addresses ? 'required' : '' ?> maxlength="50" placeholder="+880 1XXXXXXXXX" value="<?= e($phone) ?>">
                      <span class="err"></span>
                    </div>
                    <div class="field">
                      <label for="payment_method">Payment Method</label>
                      <select id="payment_method" name="payment_method">
                        <?php foreach ($payments as $value => $label): ?>
                          <option value="<?= e($value) ?>" <?= $value === $paymentMethod ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <span class="err"></span>
                    </div>
                  </div>

                  <div class="field">
                    <label for="address">Delivery Street Address *</label>
                    <textarea id="address" name="address" rows="3" <?= !$addresses ? 'required' : '' ?> placeholder="Full street address, city, postal code"><?= e($address) ?></textarea>
                    <span class="err"></span>
                  </div>

                  <div class="field">
                    <label for="notes">Order Notes (Optional)</label>
                    <input id="notes" name="notes" type="text" placeholder="Special delivery instructions, preferred delivery time, etc." value="<?= e($notes) ?>">
                  </div>

                  <button type="submit" name="place_order" value="1" class="button button-blue" style="width:100%;margin-top:10px;font-size:16px;">
                    Place Order (<?= formatPrice($cartData['total']) ?>)
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>

          <!-- Order Summary Panel -->
          <aside class="panel" id="cartSummary" <?= empty($cartData['items']) ? 'hidden' : '' ?>>
            <h2 style="font-size:20px;margin-bottom:14px">Order summary</h2>
            <div class="sum-line">
              <span>Subtotal</span>
              <span id="sub"><?= formatPrice($cartData['subtotal']) ?></span>
            </div>
            <div class="sum-line">
              <span>Shipping</span>
              <span id="ship"><?= $cartData['shipping'] > 0 ? formatPrice($cartData['shipping']) : 'Free' ?></span>
            </div>
            <div class="sum-line total">
              <span>Total</span>
              <span id="tot"><?= formatPrice($cartData['total']) ?></span>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-top:14px">
              Free shipping on orders over $500.<br>All taxes included.
            </p>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--line);font-size:13px;color:var(--muted);">
              <div>🔒 Safe & Secure Checkout</div>
              <div style="margin-top:6px;">📦 30-Day Return Guarantee</div>
              <div style="margin-top:6px;">🛡️ 3-Year Warranty on PCs</div>
            </div>
          </aside>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
