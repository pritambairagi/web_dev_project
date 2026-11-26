<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer-auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

if ($method === 'POST' && !csrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Refresh the page and try again.']);
    exit;
}

try {
    $pdo = getDbConnection();
    if ($method === 'GET' && ($action === 'get' || $action === '')) {
        echo json_encode(['success' => true, 'cart' => getCartDetails($pdo)]);
        exit;
    }
    if ($method !== 'POST' || !in_array($action, ['add', 'update', 'remove', 'clear'], true)) {
        echo json_encode(['success' => false, 'message' => $method === 'POST' ? 'Unknown action' : 'Method not allowed']);
        exit;
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    $delta = filter_var(
        $action === 'add' ? ($_POST['qty'] ?? 1) : ($_POST['change'] ?? 0),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => $action === 'add' ? 1 : -2147483647, 'max_range' => 2147483647]]
    );
    if (($action !== 'clear' && !$id) || $delta === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item or quantity.']);
        exit;
    }

    $customer = currentCustomer($pdo);
    $customerId = $customer['id'] ?? null;
    if ($customerId !== null) {
        $pdo->beginTransaction();
        lockCustomerCart($pdo, (int)$customerId);
        if (!currentCustomer($pdo)) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Your session expired. Sign in and try again.']);
            exit;
        }
    }
    $cart = cartQuantities($pdo);
    $quantity = $cart[$id] ?? 0;
    $message = null;

    if ($action === 'add' || ($action === 'update' && $quantity && $delta > 0)) {
        $stmt = $pdo->prepare('SELECT name, stock FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product || $quantity + $delta > $product['stock']) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $product ? 'Not enough stock is available.' : 'Product not found']);
            exit;
        }
        if ($action === 'add') {
            $message = $product['name'] . ' added to cart';
        }
    }

    if ($action === 'clear') {
        if ($customerId !== null) {
            $pdo->prepare('DELETE FROM customer_cart_items WHERE customer_id = ?')->execute([$customerId]);
        } else {
            unset($_SESSION['cart']);
        }
        $message = 'Cart cleared';
    } else {
        $quantity = $action === 'remove' || ($action === 'update' && $quantity === 0)
            ? 0 : max(0, $quantity + $delta);
        if ($customerId !== null) {
            if ($quantity > 0) {
                $pdo->prepare('INSERT INTO customer_cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)')->execute([$customerId, $id, $quantity]);
            } else {
                $pdo->prepare('DELETE FROM customer_cart_items WHERE customer_id = ? AND product_id = ?')->execute([$customerId, $id]);
            }
        } else {
            if ($quantity > 0) {
                $cart[$id] = $quantity;
            } else {
                unset($cart[$id]);
            }
            $_SESSION['cart'] = $cart;
        }
        if ($action === 'remove') {
            $message = 'Item removed';
        }
    }

    $details = getCartDetails($pdo);
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    $response = ['success' => true, 'count' => $details['count'], 'cart' => $details];
    if ($message !== null) {
        $response['message'] = $message;
    }
    echo json_encode($response);
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not access your cart. Please try again.']);
}
