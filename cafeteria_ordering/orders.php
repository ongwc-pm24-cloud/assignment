<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=orders.php');
    exit;
}

if (($_SESSION['role'] ?? 'user') !== 'admin' && (($_SESSION['username'] ?? '') !== 'admin')) {
    http_response_code(403);
    die('Forbidden: admin access only.');
}

// Fetch orders with aggregated counts
$ordersStmt = $pdo->query("
    SELECT o.id,
           o.customer_name,
           o.customer_email,
           o.customer_phone,
           o.total_amount,
           o.status,
           o.payment_method,
           o.payment_status,
           o.payment_reference,
           o.created_at,
           u.username AS user_username,
           u.display_name AS user_display_name,
           COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN users u ON u.id = o.user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders = $ordersStmt->fetchAll();

// Fetch order items grouped by order
$itemsStmt = $pdo->query("
    SELECT oi.order_id,
           oi.quantity,
           oi.unit_price,
           m.name AS item_name
    FROM order_items oi
    LEFT JOIN menu_items m ON m.id = oi.menu_item_id
    ORDER BY oi.order_id ASC
");

$itemsByOrder = [];
foreach ($itemsStmt as $row) {
    $itemsByOrder[$row['order_id']][] = $row;
}

$statusOptions = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
function status_class(string $status): string {
    return 'status-' . str_replace(' ', '-', strtolower($status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Management - TAR UMT Cafeteria</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-content">
        <h1 class="logo">Order Management</h1>
        <div class="header-actions">
            <a class="btn btn-secondary" href="index.php">Back to Menu</a>
            <a class="btn btn-secondary" href="logout.php">Logout</a>
        </div>
    </div>
</header>

<main class="container">
    <section class="menu-section">
        <h2>All Orders</h2>

        <div class="table-toolbar" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
            <input
                type="text"
                id="order-search"
                placeholder="Search by order #, customer, phone, user"
                style="flex:1 1 260px;max-width:360px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">

            <select id="order-filter-status"
                    style="flex:0 0 180px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt); ?>"><?= htmlspecialchars($opt); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="order-filter-pay"
                    style="flex:0 0 160px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                <option value="">All payments</option>
                <option value="PAID">PAID</option>
                <option value="UNPAID">UNPAID</option>
            </select>

            <select id="order-sort"
                    style="flex:0 0 200px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                <option value="">Sort: newest first</option>
                <option value="created-asc">Oldest first</option>
                <option value="total-asc">Total low → high</option>
                <option value="total-desc">Total high → low</option>
            </select>
        </div>
        <?php if (empty($orders)): ?>
            <p>No orders yet.</p>
        <?php else: ?>
            <div class="order-grid order-grid--wide" id="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $rawPay = strtoupper(trim($order['payment_status'] ?? ''));
                    $normalizedPay = in_array($rawPay, ['PAID', 'UNPAID'], true) ? $rawPay : 'UNPAID';
                    $payStatusClass = 'pill-paystatus ' . strtolower(str_replace(' ', '-', $normalizedPay));
                    ?>
                    <article class="order-card"
                             data-order-id="<?= (int)$order['id']; ?>"
                             data-customer="<?= htmlspecialchars(strtolower($order['customer_name'])); ?>"
                             data-phone="<?= htmlspecialchars($order['customer_phone']); ?>"
                             data-user="<?= htmlspecialchars(strtolower($order['user_display_name'] ?: $order['user_username'] ?: '-')); ?>"
                             data-status="<?= htmlspecialchars($order['status']); ?>"
                             data-pay-status="<?= htmlspecialchars($normalizedPay); ?>"
                             data-total="<?= htmlspecialchars($order['total_amount']); ?>"
                             data-created="<?= htmlspecialchars($order['created_at']); ?>">
                        <div class="order-card__header">
                            <div>
                                <div class="order-id">#<?= htmlspecialchars($order['id']); ?></div>
                                <div class="order-created"><?= htmlspecialchars($order['created_at']); ?></div>
                            </div>
                            <div class="pill-row">
                                <span class="status-pill <?= status_class($order['status']); ?>"><?= htmlspecialchars($order['status']); ?></span>
                                <span class="pill pill-method"><?= htmlspecialchars($order['payment_method'] ?? ''); ?></span>
                                <span class="pill <?= $payStatusClass; ?>"><?= htmlspecialchars($normalizedPay); ?></span>
                            </div>
                        </div>

                        <div class="order-card__grid order-card__grid--two">
                            <div class="order-card__section">
                                <div class="order-row"><span class="label">Customer</span><span><?= htmlspecialchars($order['customer_name']); ?></span></div>
                                <?php if (!empty($order['customer_email'])): ?>
                                    <div class="order-row"><span class="label">Email</span><span><?= htmlspecialchars($order['customer_email']); ?></span></div>
                                <?php endif; ?>
                                <div class="order-row"><span class="label">Contact</span><span><?= htmlspecialchars($order['customer_phone']); ?></span></div>
                                <div class="order-row"><span class="label">Total</span><span>RM <?= number_format((float)$order['total_amount'], 2); ?></span></div>
                                <div class="order-row"><span class="label">User</span><span><?= htmlspecialchars($order['user_display_name'] ?: $order['user_username'] ?: '-'); ?></span></div>
                                <?php if (!empty($order['payment_reference'])): ?>
                                    <div class="order-row"><span class="label">Note</span><span class="note-text"><?= htmlspecialchars($order['payment_reference']); ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="order-card__section order-card__section--items">
                                <div class="order-row"><span class="label">Items</span>
                                    <div>
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
                                            <span>-</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="order-card__actions">
                            <form method="post" action="update_order.php" class="inline-form order-update-form">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                                <select name="status">
                                    <?php foreach ($statusOptions as $opt): ?>
                                        <option value="<?= $opt; ?>" <?= $opt === $order['status'] ? 'selected' : ''; ?>>
                                            <?= $opt; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="payment_status">
                                    <?php foreach (['PAID','UNPAID'] as $p): ?>
                                        <option value="<?= $p; ?>" <?= $p === $normalizedPay ? 'selected' : ''; ?>>
                                            <?= $p; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-small">Save</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <p>© <?= date('Y'); ?> TAR UMT Cafeteria Online Ordering POC</p>
    </div>
</footer>

<script>
    (function () {
        const forms = document.querySelectorAll('.order-update-form');
        const grid = document.getElementById('orders-grid');
        const searchInput = document.getElementById('order-search');
        const statusFilter = document.getElementById('order-filter-status');
        const payFilter = document.getElementById('order-filter-pay');
        const sortSelect = document.getElementById('order-sort');

        if (!grid) return;

        function statusClass(status) {
            return 'status-' + status.toLowerCase().replace(/\s+/g, '-');
        }

        function payStatusClass(pay) {
            return 'pill-paystatus ' + pay.toLowerCase().replace(/\s+/g, '-');
        }

        function applyOrderFilters() {
            const cards = Array.from(grid.querySelectorAll('.order-card'));
            const term = (searchInput?.value || '').trim().toLowerCase();
            const statusVal = statusFilter?.value || '';
            const payVal = payFilter?.value || '';
            const sortVal = sortSelect?.value || '';

            cards.forEach(card => {
                const id = String(card.dataset.orderId || '');
                const customer = (card.dataset.customer || '').toLowerCase();
                const phone = (card.dataset.phone || '').toLowerCase();
                const user = (card.dataset.user || '').toLowerCase();
                const status = card.dataset.status || '';
                const pay = card.dataset['payStatus'] || card.dataset.payStatus || card.dataset.paystatus || card.dataset['pay-status'] || card.dataset['pay_status'] || '';

                const matchesSearch =
                    !term ||
                    id.includes(term) ||
                    customer.includes(term) ||
                    phone.includes(term) ||
                    user.includes(term);
                const matchesStatus = !statusVal || status === statusVal;
                const matchesPay = !payVal || pay === payVal;

                card.style.display = matchesSearch && matchesStatus && matchesPay ? '' : 'none';
            });

            if (sortVal) {
                const visible = cards.filter(c => c.style.display !== 'none');
                visible.sort((a, b) => {
                    const totalA = parseFloat(a.dataset.total || '0');
                    const totalB = parseFloat(b.dataset.total || '0');
                    const createdA = new Date(a.dataset.created || '');
                    const createdB = new Date(b.dataset.created || '');

                    switch (sortVal) {
                        case 'created-asc':
                            return createdA - createdB;
                        case 'total-asc':
                            return totalA - totalB;
                        case 'total-desc':
                            return totalB - totalA;
                        default:
                            return 0;
                    }
                });

                visible.forEach(card => grid.appendChild(card));
            }
        }

        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const fd = new FormData(form);
                fd.append('ajax', '1');

                fetch(form.action, {
                    method: 'POST',
                    body: fd
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.success) {
                            alert(data && data.message ? data.message : 'Failed to update order.');
                            return;
                        }

                        const card = form.closest('.order-card');
                        if (card) {
                            const statusPill = card.querySelector('.status-pill');
                            if (statusPill && data.status) {
                                statusPill.textContent = data.status;
                                statusPill.className = 'status-pill ' + statusClass(data.status);
                                card.dataset.status = data.status;
                            }

                            const payPill = card.querySelector('.pill-paystatus');
                            if (payPill && data.payment_status) {
                                const norm = data.payment_status.toUpperCase();
                                payPill.textContent = norm;
                                payPill.className = payStatusClass(norm);
                                card.dataset.payStatus = norm;
                            }
                        }
                        applyOrderFilters();
                    })
                    .catch(() => {
                        alert('Failed to update order.');
                    });
            });
        });

        searchInput?.addEventListener('input', applyOrderFilters);
        statusFilter?.addEventListener('change', applyOrderFilters);
        payFilter?.addEventListener('change', applyOrderFilters);
        sortSelect?.addEventListener('change', applyOrderFilters);

        applyOrderFilters();
    })();
</script>
</body>
</html>

