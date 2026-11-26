<?php
/**
 * SalesPro Database Configuration & Helper Functions
 * Database: salespro
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true) ? '1' : '0');
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'salespro');
define('DB_PORT', '3306');

/**
 * Get PDO Database Connection
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Format currency
 */
function formatPrice($number) {
    return '$' . number_format((float)$number, 2);
}

/**
 * Sanitize string for output
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Total item count in the active cart
 */
function getCartCount() {
    if (!isset($_SESSION['customer_id'], $_SESSION['customer_auth_version'])) {
        return array_sum(guestCartQuantities());
    }
    try {
        return array_sum(cartQuantities(getDbConnection()));
    } catch (PDOException $ex) {
        return 0;
    }
}

function guestCartQuantities(): array {
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

function cartQuantities(PDO $pdo): array {
    require_once __DIR__ . '/../includes/customer-auth.php';
    $customer = currentCustomer($pdo);
    if (!$customer) {
        return guestCartQuantities();
    }
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM customer_cart_items WHERE customer_id = ? ORDER BY product_id');
    $stmt->execute([$customer['id']]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Every account cart writer takes this lock before reading or changing its rows.
function lockCustomerCart(PDO $pdo, int $customerId): void {
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? FOR UPDATE');
    $stmt->execute([$customerId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Customer unavailable');
    }
}

function mergeGuestCart(PDO $pdo, int $customerId): void {
    $cart = guestCartQuantities();
    if (!$cart) {
        return;
    }
    ksort($cart, SORT_NUMERIC);
    $pdo->beginTransaction();
    try {
        lockCustomerCart($pdo, $customerId);
        $stmt = $pdo->prepare('INSERT INTO customer_cart_items (customer_id, product_id, quantity)
            SELECT ?, id, ? FROM products WHERE id = ?
            ON DUPLICATE KEY UPDATE quantity = LEAST(2147483647, customer_cart_items.quantity + VALUES(quantity))');
        foreach ($cart as $id => $quantity) {
            if ((int)$quantity > 0) {
                $stmt->execute([$customerId, min(2147483647, (int)$quantity), (int)$id]);
            }
        }
        $pdo->commit();
        unset($_SESSION['cart']);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/**
 * Fetch detailed cart items with database prices & subtotal
 */
function getCartDetails($pdo) {
    $cart = cartQuantities($pdo);
    $items = [];
    $subtotal = 0.0;

    if (!empty($cart)) {
        $ids = array_map('intval', array_keys($cart));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE p.id IN ($placeholders)");
            $stmt->execute($ids);
            $products = $stmt->fetchAll();

            $productsById = [];
            foreach ($products as $p) {
                $productsById[$p['id']] = $p;
            }

            foreach ($cart as $id => $qty) {
                $id = (int)$id;
                $qty = (int)$qty;
                if ($qty > 0 && isset($productsById[$id])) {
                    $prod = $productsById[$id];
                    $lineTotal = $prod['price'] * $qty;
                    $subtotal += $lineTotal;
                    $items[] = [
                        'id' => $id,
                        'name' => $prod['name'],
                        'slug' => $prod['slug'],
                        'price' => (float)$prod['price'],
                        'quantity' => $qty,
                        'line_total' => $lineTotal,
                        'image_url' => $prod['image_url'],
                        'category' => $prod['category_name'] ?? 'General'
                    ];
                }
            }
        }
    }

    $shipping = ($subtotal > 500 || $subtotal == 0) ? 0.00 : 25.00;
    $total = $subtotal + $shipping;

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total,
        'count' => array_sum(array_column($items, 'quantity'))
    ];
}

/**
 * Flash message helper
 */
function setFlash($key, $message, $type = 'success') {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}
