<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=user_orders.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$displayName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';

$ordersStmt = $pdo->prepare("
    SELECT o.id,
           o.total_amount,
           o.status,
           o.payment_method,
           o.payment_status,
           o.created_at
    FROM orders o
    WHERE o.user_id = :uid
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([':uid' => $userId]);
$orders = $ordersStmt->fetchAll();

$itemsStmt = $pdo->prepare("
    SELECT oi.order_id,
           oi.quantity,
           oi.unit_price,
           m.name AS item_name
    FROM order_items oi
    LEFT JOIN menu_items m ON m.id = oi.menu_item_id
    WHERE oi.order_id IN (
        SELECT id FROM orders WHERE user_id = :uid
    )
    ORDER BY oi.order_id ASC
");
$itemsStmt->execute([':uid' => $userId]);

$itemsByOrder = [];
foreach ($itemsStmt as $row) {
    $itemsByOrder[$row['order_id']][] = $row;
}

function status_class(string $status): string {
    return 'status-' . str_replace(' ', '-', strtolower($status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - TAR UMT Cafeteria</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-content">
        <h1 class="logo">My Orders</h1>
        <div>
            <a class="btn btn-secondary" href="index.php">Back to Menu</a>
            <a class="btn btn-secondary" href="logout.php">Logout</a>
        </div>
    </div>
</header>

<main class="container">
    <section class="menu-section">
        <h2>Hi, <?= htmlspecialchars($displayName); ?></h2>
        <?php if (empty($orders)): ?>
            <p>No orders yet. <a href="index.php">Start ordering</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Total (RM)</th>
                        <th>Order Status</th>
                        <th>Payment</th>
                        <th>Created</th>
                        <th>Items</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['id']); ?></td>
                            <td><?= number_format((float)$order['total_amount'], 2); ?></td>
                            <td><span class="status-pill <?= status_class($order['status']); ?>"><?= htmlspecialchars($order['status']); ?></span></td>
                            <td>
                                <div><strong>Method:</strong> <?= htmlspecialchars($order['payment_method'] ?? ''); ?></div>
                                <div><strong>Status:</strong> <?= htmlspecialchars($order['payment_status'] ?? ''); ?></div>
                            </td>
                            <td><?= htmlspecialchars($order['created_at']); ?></td>
                            <td>
                                <?php if (!empty($itemsByOrder[$order['id']])): ?>
                                    <ul class="item-list">
                                        <?php foreach ($itemsByOrder[$order['id']] as $item): ?>
                                            <li>
                                                <?= htmlspecialchars($item['item_name'] ?? 'Item'); ?> x <?= (int)$item['quantity']; ?>
                                                (RM <?= number_format((float)$item['unit_price'], 2); ?>)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <p>© <?= date('Y'); ?> TAR UMT Cafeteria Online Ordering POC</p>
    </div>
</footer>
</body>
</html>

