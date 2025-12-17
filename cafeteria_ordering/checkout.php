<?php
session_start();
require_once __DIR__ . '/db.php';

function checkout_error(string $message): void {
    $_SESSION['checkout_error'] = $message;
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    checkout_error('Please log in before submitting an order.');
}

// Admin accounts are not allowed to place orders
if (($_SESSION['role'] ?? 'user') === 'admin' || (($_SESSION['username'] ?? '') === 'admin')) {
    checkout_error('Admin accounts cannot place orders. Please log out and log in with a normal user account to make an order.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    checkout_error('Method not allowed.');
}

$customerName    = trim($_POST['customer_name'] ?? '');
$customerEmail   = trim($_POST['customer_email'] ?? '');
$customerPhone   = trim($_POST['customer_phone'] ?? '');
$paymentMethod   = trim($_POST['payment_method'] ?? '');
$paymentNote     = trim($_POST['payment_note'] ?? '');
$cardName        = trim($_POST['card_name'] ?? '');
$cardNumber      = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
$cardExpiry      = trim($_POST['card_expiry'] ?? '');
$cardCvv         = preg_replace('/\D+/', '', $_POST['card_cvv'] ?? '');
$walletProvider  = trim($_POST['wallet_provider'] ?? '');
$walletId        = trim($_POST['wallet_id'] ?? '');
$cartDataJson    = $_POST['cart_data'] ?? '';

if ($customerName === '' || $customerPhone === '' || $paymentMethod === '' || $cartDataJson === '') {
    checkout_error('Order data is incomplete. Please fill in all required fields.');
}

$malaysiaPattern = '/^01[0-46-9][0-9]{7,8}$/';
if (!preg_match($malaysiaPattern, $customerPhone)) {
    checkout_error('Phone number must follow Malaysia format (e.g., 0123456789).');
}

$allowedPayments = ['Cash', 'Card', 'eWallet'];
if (!in_array($paymentMethod, $allowedPayments, true)) {
    checkout_error('Invalid payment method selected.');
}

$paymentRef = $paymentNote !== '' ? $paymentNote : null;
$paymentStatus = ($paymentMethod === 'Cash') ? 'UNPAID' : 'PAID';

if ($paymentMethod === 'Card') {
    if ($cardName === '' || $cardNumber === '' || $cardExpiry === '' || $cardCvv === '') {
        checkout_error('Card details are required for card payments.');
    }
    if (strlen($cardNumber) !== 16) {
        checkout_error('Card number must be 16 digits.');
    }
    if (strlen($cardCvv) !== 3) {
        checkout_error('CVV must be 3 digits.');
    }
    // Validate expiry MM/YY and ensure not in the past
    if (!preg_match('/^(\d{2})\/(\d{2})$/', $cardExpiry, $m)) {
        checkout_error('Expiry must be in MM/YY format.');
    }
    $mm = (int)$m[1];
    $yy = (int)$m[2];
    if ($mm < 1 || $mm > 12) {
        checkout_error('Expiry month must be between 01 and 12.');
    }
    // Interpret YY as 20YY
    $currentYear = (int)date('y');
    $currentMonth = (int)date('m');
    if ($yy < $currentYear || ($yy === $currentYear && $mm < $currentMonth)) {
        checkout_error('Card has expired.');
    }
    $last4 = substr($cardNumber, -4);
}

if ($paymentMethod === 'eWallet') {
    if ($walletProvider === '' || $walletId === '') {
        checkout_error('eWallet details are required for eWallet payments.');
    }
    if (!preg_match('/^01[0-46-9][0-9]{7,8}$/', $walletId)) {
        checkout_error('eWallet phone must follow Malaysia format (e.g., 0123456789).');
    }
}

$cart = json_decode($cartDataJson, true);
if (!is_array($cart) || empty($cart)) {
    checkout_error('Cart data is invalid. Please return and select items again.');
}

try {
    $pdo->beginTransaction();

    // Calculate total
    $total = 0;
    foreach ($cart as $item) {
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $price    = isset($item['price']) ? (float)$item['price'] : 0;
        if ($quantity > 0 && $price >= 0) {
            $total += $price * $quantity;
        }
    }

    if ($total <= 0) {
        throw new Exception('Order amount is invalid.');
    }

    // Insert order
    $stmt = $pdo->prepare(
        "INSERT INTO orders (customer_name, customer_email, customer_phone, total_amount, status, user_id, payment_method, payment_status, payment_reference) 
         VALUES (:name, :email, :phone, :total, 'Pending', :user_id, :payment_method, :payment_status, :payment_ref)"
    );
    $stmt->execute([
        ':name'          => $customerName,
        ':email'         => $customerEmail !== '' ? $customerEmail : null,
        ':phone'         => $customerPhone,
        ':total'         => $total,
        ':user_id'       => (int)$_SESSION['user_id'],
        ':payment_method'=> $paymentMethod,
        ':payment_status'=> $paymentStatus,
        ':payment_ref'   => $paymentRef,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // 插入订单明细
    $itemStmt = $pdo->prepare(
        "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price)
         VALUES (:order_id, :menu_item_id, :quantity, :unit_price)"
    );

    foreach ($cart as $item) {
        $menuItemId = (int)($item['id'] ?? 0);
        $quantity   = (int)($item['quantity'] ?? 0);
        $price      = (float)($item['price'] ?? 0);

        if ($menuItemId > 0 && $quantity > 0 && $price >= 0) {
            $itemStmt->execute([
                ':order_id'     => $orderId,
                ':menu_item_id' => $menuItemId,
                ':quantity'     => $quantity,
                ':unit_price'   => $price,
            ]);
        }
    }

    $pdo->commit();

    header('Location: order_success.php?id=' . urlencode((string)$orderId));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Order failed, please try again later. Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}



