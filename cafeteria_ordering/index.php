<?php
session_start();
require_once __DIR__ . '/db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && ((($_SESSION['role'] ?? 'user') === 'admin') || (($_SESSION['username'] ?? '') === 'admin'));
$displayName = $isLoggedIn ? ($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User') : '';

// Fetch menu
$stmt = $pdo->query("SELECT * FROM menu_items ORDER BY category, name");
$menuItems = $stmt->fetchAll();

// Group items by category for display
$groupedMenu = [];
foreach ($menuItems as $item) {
    $cat = trim($item['category'] ?? '');
    if ($cat === '') {
        $cat = 'Uncategorized';
    }
    if (!isset($groupedMenu[$cat])) {
        $groupedMenu[$cat] = [];
    }
    $groupedMenu[$cat][] = $item;
}

// Map menu item names (case-insensitive) to image paths to avoid ID mismatches.
// Update filenames to match your actual images in assets/images/.
$itemImagesByName = [
    'nasi lemak'      => 'assets/images/nasi-lemak.jpg',
    'fried rice'      => 'assets/images/fried-rice.jpg',
    'chicken chop'    => 'assets/images/chicken-chop.jpg',
    'iced lemon tea'  => 'assets/images/iced-lemon-tea.jpg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TAR UMT Cafeteria Online Ordering Platform</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <h1 class="logo">TAR UMT Cafeteria Online Ordering Platform</h1>
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <span class="header-greet">Hi, <?= htmlspecialchars($displayName); ?></span>
                    <button id="view-cart-btn" class="btn btn-secondary">
                        Cart (<span id="cart-count">0</span>)
                    </button>
                    <a class="btn btn-secondary" href="user_orders.php">My Orders</a>
                    <?php if ($isAdmin): ?>
                        <a class="btn btn-secondary" href="orders.php">Manage Orders</a>
                        <a class="btn btn-secondary" href="products.php">Manage Products</a>
                    <?php endif; ?>
                    <a class="btn btn-secondary" href="logout.php">Logout</a>
                <?php else: ?>
                    <button id="view-cart-btn" class="btn btn-secondary">
                        Cart (<span id="cart-count">0</span>)
                    </button>
                    <a class="btn btn-secondary" href="login.php">Login</a>
                    <a class="btn btn-secondary" href="register.php">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero" style="background-image: url('assets/images/hero.jpg');">
        <div class="hero-overlay">
            <div class="container hero-content">
                <p class="eyebrow">Fresh. Fast. Friendly.</p>
                <h2>Order your campus favorites in seconds</h2>
                <p>Browse the menu, customize your order, and skip the queue.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#menu">Start ordering</a>
                    <button id="hero-view-cart" class="btn btn-secondary">View cart</button>
                </div>
            </div>
        </div>
    </section>

    <main class="container">
        <section class="intro">
            <h2>Welcome to the online ordering system</h2>
            <p>Select the items you want, add them to your cart, then fill in your details to place an order.</p>
        </section>

        <section class="menu-section" id="menu">
            <h2>Today&apos;s Menu</h2>

            <div class="table-toolbar" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin:0.5rem 0 1rem;">
                <input
                    type="text"
                    id="menu-search"
                    placeholder="Search by name or description"
                    style="flex:1 1 260px;max-width:360px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">

                <select id="menu-filter-category"
                        style="flex:0 0 180px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                    <option value="">All categories</option>
                    <?php foreach (array_keys($groupedMenu) as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="menu-sort"
                        style="flex:0 0 200px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                    <option value="">Sort: default</option>
                    <option value="name-asc">Name A → Z</option>
                    <option value="name-desc">Name Z → A</option>
                    <option value="price-asc">Price low → high</option>
                    <option value="price-desc">Price high → low</option>
                </select>
            </div>

            <?php if (empty($groupedMenu)): ?>
                <p>No menu items available right now. Please try again later.</p>
            <?php else: ?>
                <?php foreach ($groupedMenu as $category => $itemsInCat): ?>
                    <div class="menu-category-block" data-category="<?= htmlspecialchars($category); ?>">
                        <h3 style="margin-top:2rem;"><?= htmlspecialchars($category); ?></h3>
                        <div class="menu-list">
                            <?php foreach ($itemsInCat as $item): ?>
                                <?php
                                // Prefer the image uploaded/stored with the product and verify the file exists.
                                $resolvedPath = '';
                                if (!empty($item['image_path'])) {
                                    $candidate = $item['image_path'];
                                    if (file_exists(__DIR__ . '/' . ltrim($candidate, '/'))) {
                                        $resolvedPath = $candidate;
                                    }
                                }
                                if ($resolvedPath === '') {
                                    // Fallback to default image mapping by name, then to a generic placeholder.
                                    $nameKey = strtolower(trim($item['name']));
                                    $resolvedPath = $itemImagesByName[$nameKey] ?? 'assets/images/placeholder.jpg';
                                }
                                $imgPath = $resolvedPath;
                                ?>
                                <article class="menu-item"
                                         data-id="<?= htmlspecialchars($item['id']); ?>"
                                         data-name="<?= htmlspecialchars(strtolower($item['name'])); ?>"
                                         data-description="<?= htmlspecialchars(strtolower($item['description'] ?? '')); ?>"
                                         data-category="<?= htmlspecialchars($category); ?>"
                                         data-price="<?= htmlspecialchars($item['price']); ?>">
                                    <div class="menu-thumb">
                                        <img src="<?= htmlspecialchars($imgPath); ?>"
                                             alt="<?= htmlspecialchars($item['name']); ?>"
                                             onerror="this.onerror=null;this.src='assets/images/placeholder.jpg';">
                                    </div>
                                    <div class="menu-item-info">
                                        <h3><?= htmlspecialchars($item['name']); ?></h3>
                                        <?php if (!empty($item['description'])): ?>
                                            <p class="description"><?= htmlspecialchars($item['description']); ?></p>
                                        <?php endif; ?>
                                        <p class="price">Price: RM <?= number_format($item['price'], 2); ?></p>
                                    </div>
                                    <div class="menu-item-actions">
                                        <button class="btn btn-primary add-to-cart-btn">Add to Cart</button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="cart-panel" id="cart-panel" style="display:none;">
            <h2>Cart</h2>
            <div id="cart-items" class="cart-items"></div>
            <div class="cart-summary">
                <p>Total: <span class="amount">RM <span id="cart-total">0.00</span></span></p>
            </div>

            <section class="checkout-section">
                <h3>Enter order details</h3>
                <?php if (!$isLoggedIn): ?>
                    <p style="color: #b91c1c; font-weight: 600;">Please log in before submitting an order. <a href="login.php">Login</a></p>
                <?php elseif (!$isAdmin): ?>
                    <p style="color: #065f46; font-weight: 600;">Logged in as <?= htmlspecialchars($displayName); ?>.</p>
                <?php endif; ?>
                <?php if (!empty($_SESSION['checkout_error'])): ?>
                    <p class="field-error"><?= htmlspecialchars($_SESSION['checkout_error']); ?></p>
                    <?php unset($_SESSION['checkout_error']); ?>
                <?php endif; ?>
                <form id="checkout-form"
                      class="checkout-form"
                      method="post"
                      action="checkout.php"
                      data-logged-in="<?= $isLoggedIn ? '1' : '0'; ?>"
                      data-login-url="login.php"
                      data-is-admin="<?= $isAdmin ? '1' : '0'; ?>">
                    <input type="hidden" name="cart_data" id="cart_data">

                    <div class="form-group">
                        <label for="customer_name">Name (required)</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>

                    <div class="form-group">
                        <label for="customer_phone">Phone (Malaysia format, required)</label>
                        <input type="text"
                               id="customer_phone"
                               name="customer_phone"
                               pattern="01[0-46-9][0-9]{7,8}"
                               placeholder="e.g., 0123456789"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment method (required)</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select a payment method</option>
                            <option value="Cash">Cash at pickup</option>
                            <option value="Card">Card</option>
                            <option value="eWallet">eWallet</option>
                        </select>
                        <small>No real charge is processed.</small>
                    </div>

                    <div class="form-group payment-card" style="display:none;">
                        <label for="card_name">Cardholder name (required for card)</label>
                        <input type="text" id="card_name" name="card_name">
                    </div>
                    <div class="form-group payment-card" style="display:none;">
                        <label for="card_number">Card number</label>
                        <input type="text" id="card_number" name="card_number"
                               maxlength="16"
                               inputmode="numeric"
                               placeholder="16-digit number">
                        <div class="field-error" id="card_number_error"></div>
                    </div>
                    <div class="form-group payment-card" style="display:none;">
                        <label for="card_expiry">Expiry (MM/YY)</label>
                        <input type="text" id="card_expiry" name="card_expiry"
                               maxlength="5"
                               inputmode="numeric"
                               placeholder="MM/YY">
                        <div class="field-error" id="card_expiry_error"></div>
                    </div>
                    <div class="form-group payment-card" style="display:none;">
                        <label for="card_cvv">CVV</label>
                        <input type="password" id="card_cvv" name="card_cvv"
                               maxlength="3"
                               inputmode="numeric"
                               placeholder="3 digits">
                        <div class="field-error" id="card_cvv_error"></div>
                    </div>

                    <div class="form-group payment-wallet" style="display:none;">
                        <label for="wallet_provider">eWallet provider (Touch 'n Go only)</label>
                        <select id="wallet_provider" name="wallet_provider">
                            <option value="Touch 'n Go">Touch 'n Go</option>
                        </select>
                    </div>
                    <div class="form-group payment-wallet" style="display:none;">
                        <label for="wallet_id">eWallet account / phone</label>
                        <input type="text" id="wallet_id" name="wallet_id"
                               inputmode="numeric"
                               maxlength="11"
                               placeholder="e.g., 0123456789">
                        <div class="field-error" id="wallet_id_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="payment_note">Payment note (optional)</label>
                        <input type="text" id="payment_note" name="payment_note" placeholder="e.g., pay with student card">
                    </div>

                    <?php if ($isAdmin): ?>
                        <button type="submit" class="btn btn-primary" disabled>Submit Order</button>
                        <p style="margin-top:0.5rem;color:#b91c1c;font-weight:600;">
                            You must log in as a normal user to make an order.
                        </p>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary">Submit Order</button>
                    <?php endif; ?>
                </form>
            </section>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>© <?= date('Y'); ?> TAR UMT Cafeteria Online Ordering POC</p>
        </div>
    </footer>

    <script src="assets/cart.js"></script>
    <script>
        document.getElementById('hero-view-cart')?.addEventListener('click', () => {
            document.getElementById('view-cart-btn')?.click();
        });

        // Toggle payment detail fields
        const paymentSelect = document.getElementById('payment_method');
        const cardFields = document.querySelectorAll('.payment-card');
        const walletFields = document.querySelectorAll('.payment-wallet');

        function togglePaymentFields() {
            const val = paymentSelect.value;
            const showCard = val === 'Card';
            const showWallet = val === 'eWallet';

            cardFields.forEach(f => {
                f.style.display = showCard ? 'block' : 'none';
                f.querySelectorAll('input').forEach(inp => inp.required = showCard);
            });
            walletFields.forEach(f => {
                f.style.display = showWallet ? 'block' : 'none';
                f.querySelectorAll('input').forEach(inp => inp.required = showWallet);
            });
        }
        paymentSelect?.addEventListener('change', togglePaymentFields);
        togglePaymentFields();

        // Menu search / filter / sort
        const menuSearch = document.getElementById('menu-search');
        const menuFilterCategory = document.getElementById('menu-filter-category');
        const menuSort = document.getElementById('menu-sort');
        const menuSection = document.getElementById('menu');

        function applyMenuFilters() {
            if (!menuSection) return;
            const blocks = Array.from(menuSection.querySelectorAll('.menu-category-block'));
            const term = (menuSearch?.value || '').trim().toLowerCase();
            const catFilter = menuFilterCategory?.value || '';
            const sortVal = menuSort?.value || '';

            let allItems = [];

            blocks.forEach(block => {
                const blockCategory = block.dataset.category || '';
                const items = Array.from(block.querySelectorAll('.menu-item'));
                let visibleCount = 0;

                items.forEach(item => {
                    const name = (item.dataset.name || '').toLowerCase();
                    const desc = (item.dataset.description || '').toLowerCase();
                    const itemCat = item.dataset.category || '';
                    const price = parseFloat(item.dataset.price || '0');

                    const matchesSearch =
                        !term ||
                        name.includes(term) ||
                        desc.includes(term);
                    const matchesCat = !catFilter || itemCat === catFilter;

                    const visible = matchesSearch && matchesCat;
                    item.style.display = visible ? '' : 'none';
                    if (visible) {
                        visibleCount++;
                        allItems.push({ element: item, name, price });
                    }
                });

                // Hide entire category block if nothing inside matches
                block.style.display = visibleCount > 0 ? '' : 'none';
            });

            // Cross-category sorting: reorder items within each block, preserving category grouping
            if (sortVal) {
                blocks.forEach(block => {
                    const items = Array.from(block.querySelectorAll('.menu-item'))
                        .filter(i => i.style.display !== 'none');
                    if (!items.length) return;

                    items.sort((a, b) => {
                        const nameA = (a.dataset.name || '').toLowerCase();
                        const nameB = (b.dataset.name || '').toLowerCase();
                        const priceA = parseFloat(a.dataset.price || '0');
                        const priceB = parseFloat(b.dataset.price || '0');

                        switch (sortVal) {
                            case 'name-asc':
                                return nameA.localeCompare(nameB);
                            case 'name-desc':
                                return nameB.localeCompare(nameA);
                            case 'price-asc':
                                return priceA - priceB;
                            case 'price-desc':
                                return priceB - priceA;
                            default:
                                return 0;
                        }
                    });

                    const list = block.querySelector('.menu-list');
                    if (list) {
                        items.forEach(i => list.appendChild(i));
                    }
                });
            }
        }

        menuSearch?.addEventListener('input', applyMenuFilters);
        menuFilterCategory?.addEventListener('change', applyMenuFilters);
        menuSort?.addEventListener('change', applyMenuFilters);

        applyMenuFilters();
    </script>
</body>
</html>



