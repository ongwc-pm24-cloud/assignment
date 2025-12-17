<?php
require_once __DIR__ . '/db.php';
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare("SELECT id, payment_method, payment_status, total_amount, status FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Submitted - TAR UMT Online Ordering</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <h1 class="logo">Order submitted successfully</h1>
        </div>
    </header>

    <main class="container">
        <section class="success-section">
            <h2>Thank you for your order!</h2>
            <?php if ($orderId > 0 && $order): ?>
                <p>Your order number is: <strong><?= htmlspecialchars((string)$orderId); ?></strong></p>
                <p>Total: RM <?= number_format((float)$order['total_amount'], 2); ?></p>
                <p>Order status: <strong><?= htmlspecialchars($order['status']); ?></strong></p>
                <p>Payment: <strong><?= htmlspecialchars($order['payment_method']); ?></strong> (<?= htmlspecialchars($order['payment_status']); ?>)</p>
            <?php else: ?>
                <p>Your order has been submitted.</p>
            <?php endif; ?>
            <p>Please wait at the cafeteria to collect or watch for notification.</p>
            <div style="display:flex; gap:0.5rem; justify-content:center; flex-wrap:wrap;">
                <a href="index.php" class="btn btn-primary">Return to menu</a>
                <a href="user_orders.php" class="btn btn-secondary">View my orders</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>© <?= date('Y'); ?> TAR UMT Cafeteria Online Ordering POC</p>
        </div>
    </footer>
</body>
</html>



