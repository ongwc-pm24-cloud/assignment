<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin' && (($_SESSION['username'] ?? '') !== 'admin'))) {
    http_response_code(403);
    die('Forbidden: admin access only.');
}

$itemsStmt = $pdo->query("SELECT id, name, description, category, price, image_path FROM menu_items ORDER BY id ASC");
$menuItems = $itemsStmt->fetchAll();

// Default images by product name (case-insensitive)
$defaultImages = [
    'nasi lemak'     => 'assets/images/nasi-lemak.jpg',
    'fried rice'     => 'assets/images/fried-rice.jpg',
    'chicken chop'   => 'assets/images/chicken-chop.jpg',
    'iced lemon tea' => 'assets/images/iced-lemon-tea.jpg',
];

// Category options for dropdown
$categories = ['Rice', 'Western', 'Beverage', 'Dessert', 'Snack', 'Drink', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products - TAR UMT Cafeteria</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-content">
        <h1 class="logo">Manage Products</h1>
        <div class="header-actions">
            <a class="btn btn-secondary" href="index.php">Back to Menu</a>
            <a class="btn btn-secondary" href="orders.php">Manage Orders</a>
            <a class="btn btn-secondary" href="logout.php">Logout</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_success']); ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <section class="menu-section">
        <h2>Add New Product</h2>
        <form class="checkout-form" method="post" action="products_save.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="price">Price (RM)</label>
                <input type="number" step="0.01" min="0" id="price" name="price" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" required>
            </div>
            <div class="form-group">
                <label for="image">Image (optional)</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    </section>

    <section class="menu-section">
        <h2>Existing Products</h2>

        <div class="table-toolbar" style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
            <input
                type="text"
                id="product-search"
                placeholder="Search by name or description"
                style="flex:1 1 220px;max-width:320px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">

            <select id="product-filter-category"
                    style="flex:0 0 180px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat); ?>"><?= htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="product-sort"
                    style="flex:0 0 200px;padding:0.4rem 0.6rem;border-radius:0.375rem;border:1px solid #d1d5db;">
                <option value="">Sort: default</option>
                <option value="name-asc">Name A → Z</option>
                <option value="name-desc">Name Z → A</option>
                <option value="price-asc">Price low → high</option>
                <option value="price-desc">Price high → low</option>
            </select>
        </div>
        <?php if (empty($menuItems)): ?>
            <p>No products found.</p>
        <?php else: ?>
            <div class="products-grid" id="products-grid">
                <?php foreach ($menuItems as $item): ?>
                    <?php
                        // Resolve best image path with safe fallbacks.
                        $resolvedPath = '';
                        if (!empty($item['image_path'])) {
                            $candidate = $item['image_path'];
                            // If the file exists under the project directory, use it.
                            if (file_exists(__DIR__ . '/' . ltrim($candidate, '/'))) {
                                $resolvedPath = $candidate;
                            }
                        }
                        if ($resolvedPath === '') {
                            $key = strtolower(trim($item['name']));
                            $resolvedPath = $defaultImages[$key] ?? 'assets/images/placeholder.jpg';
                        }
                        $imgPath = htmlspecialchars($resolvedPath);
                    ?>
                    <article class="product-card"
                             data-product-id="<?= (int)$item['id']; ?>"
                             data-name="<?= htmlspecialchars(strtolower($item['name'])); ?>"
                             data-description="<?= htmlspecialchars(strtolower($item['description'])); ?>"
                             data-category="<?= htmlspecialchars($item['category']); ?>"
                             data-price="<?= htmlspecialchars($item['price']); ?>">
                        <div class="product-card__header">
                            <div>
                                <div class="order-id">ID #<?= htmlspecialchars($item['id']); ?></div>
                                <div class="order-created"><?= htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></div>
                            </div>
                            <div class="pill-row">
                                <span class="pill pill-method">RM <?= number_format((float)$item['price'], 2); ?></span>
                            </div>
                        </div>
                        <div class="product-card__thumb">
                            <img src="<?= $imgPath; ?>" alt="<?= htmlspecialchars($item['name']); ?>" onerror="this.onerror=null;this.src='assets/images/placeholder.jpg';">
                        </div>
                        <div class="product-card__body">
                            <div class="order-row"><span class="label">Name</span><span><?= htmlspecialchars($item['name']); ?></span></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="order-row"><span class="label">Desc</span><span><?= htmlspecialchars($item['description']); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-card__form">
                            <form method="post" action="products_save.php" class="checkout-form product-edit-form" enctype="multipart/form-data">
                                <input type="hidden" name="id" value="<?= (int)$item['id']; ?>">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($item['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category" required>
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat); ?>" <?= ($item['category'] === $cat) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($cat); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Price (RM)</label>
                                    <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars($item['price']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="description" value="<?= htmlspecialchars($item['description']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Image</label>
                                    <input type="file" name="image" accept="image/*">
                                </div>
                                <div class="inline-form">
                                    <button type="submit" class="btn btn-primary btn-small">Update</button>
                                    <a class="btn btn-secondary btn-small product-delete-link" href="products_delete.php?id=<?= (int)$item['id']; ?>">Delete</a>
                                </div>
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
        const grid = document.getElementById('products-grid');
        if (!grid) return;

        const searchInput = document.getElementById('product-search');
        const categoryFilter = document.getElementById('product-filter-category');
        const sortSelect = document.getElementById('product-sort');

        function showToast(message) {
            let el = document.getElementById('product-toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'product-toast';
                el.style.position = 'fixed';
                el.style.right = '1rem';
                el.style.bottom = '1rem';
                el.style.padding = '0.75rem 1rem';
                el.style.backgroundColor = '#065f46';
                el.style.color = '#fff';
                el.style.borderRadius = '0.375rem';
                el.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                el.style.zIndex = '9999';
                document.body.appendChild(el);
            }
            el.textContent = message;
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.opacity = '0';
            }, 2000);
        }

        function applyProductFilters() {
            const cards = Array.from(grid.querySelectorAll('.product-card'));
            const term = (searchInput?.value || '').trim().toLowerCase();
            const cat = categoryFilter?.value || '';
            const sort = sortSelect?.value || '';

            cards.forEach(card => {
                const name = (card.dataset.name || '').toLowerCase();
                const desc = (card.dataset.description || '').toLowerCase();
                const category = card.dataset.category || '';

                const matchesSearch =
                    !term ||
                    name.includes(term) ||
                    desc.includes(term);
                const matchesCat = !cat || category === cat;

                card.style.display = matchesSearch && matchesCat ? '' : 'none';
            });

            // Sorting (only among visible cards)
            if (sort) {
                const visible = cards.filter(c => c.style.display !== 'none');
                visible.sort((a, b) => {
                    const nameA = a.dataset.name || '';
                    const nameB = b.dataset.name || '';
                    const priceA = parseFloat(a.dataset.price || '0');
                    const priceB = parseFloat(b.dataset.price || '0');

                    switch (sort) {
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

                visible.forEach(card => grid.appendChild(card));
            }
        }

        searchInput?.addEventListener('input', applyProductFilters);
        categoryFilter?.addEventListener('change', applyProductFilters);
        sortSelect?.addEventListener('change', applyProductFilters);

        // Handle inline update via AJAX
        grid.querySelectorAll('.product-edit-form').forEach(form => {
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
                            alert(data && data.message ? data.message : 'Failed to save product.');
                            return;
                        }
                        const item = data.item || {};
                        const card = form.closest('.product-card');
                        if (card) {
                            card.dataset.productId = item.id;
                            card.dataset.name = (item.name || '').toLowerCase();
                            card.dataset.description = (item.description || '').toLowerCase();
                            card.dataset.category = item.category || '';
                            card.dataset.price = item.price;

                            // Update header and body text
                            const rows = card.querySelectorAll('.order-row');
                            if (rows[0]) {
                                const span = rows[0].querySelector('span:last-child');
                                if (span) span.textContent = item.name || '';
                            }

                            if (rows[1]) {
                                const span = rows[1].querySelector('span:last-child');
                                if (span) span.textContent = item.description || '';
                            }

                            const pricePill = card.querySelector('.pill-method');
                            if (pricePill && item.price !== undefined) {
                                pricePill.textContent = 'RM ' + parseFloat(item.price).toFixed(2);
                            }

                            const categoryEl = card.querySelector('.order-created');
                            if (categoryEl && item.category !== undefined) {
                                categoryEl.textContent = item.category || 'Uncategorized';
                            }

                            const img = card.querySelector('.product-card__thumb img');
                            if (img && item.image_path) {
                                img.src = item.image_path;
                            }
                        }

                        applyProductFilters();
                        showToast(data.message || 'Product saved.');
                    })
                    .catch(() => {
                        alert('Failed to save product.');
                    });
            });
        });

        // Handle delete via AJAX
        grid.querySelectorAll('.product-delete-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                if (!confirm('Delete this product?')) return;

                const url = this.href + (this.href.indexOf('?') === -1 ? '?ajax=1' : '&ajax=1');
                const card = this.closest('.product-card');

                fetch(url, {
                    method: 'GET'
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.success) {
                            alert(data && data.message ? data.message : 'Failed to delete product.');
                            return;
                        }
                        if (card) {
                            card.remove();
                        }
                        showToast(data.message || 'Product deleted.');
                        applyProductFilters();
                    })
                    .catch(() => {
                        alert('Failed to delete product.');
                    });
            });
        });

        applyProductFilters();
    })();
</script>
</body>
</html>

