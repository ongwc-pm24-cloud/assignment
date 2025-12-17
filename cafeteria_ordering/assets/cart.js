(function () {
    const cartState = [];
    const cartPanel = document.getElementById('cart-panel');
    const cartItemsContainer = document.getElementById('cart-items');
    const cartTotalEl = document.getElementById('cart-total');
    const cartCountEl = document.getElementById('cart-count');
    const cartDataInput = document.getElementById('cart_data');
    const checkoutForm = document.getElementById('checkout-form');
    const viewCartBtn = document.getElementById('view-cart-btn');
    const cardNumberInput = document.getElementById('card_number');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvvInput = document.getElementById('card_cvv');
    const cardNumberError = document.getElementById('card_number_error');
    const cardExpiryError = document.getElementById('card_expiry_error');
    const cardCvvError = document.getElementById('card_cvv_error');
    const paymentMethodSelect = document.getElementById('payment_method');
    const walletIdInput = document.getElementById('wallet_id');
    const walletIdError = document.getElementById('wallet_id_error');

    function findIndex(id) {
        return cartState.findIndex((item) => item.id === id);
    }

    function renderCart() {
        cartItemsContainer.innerHTML = '';

        if (cartState.length === 0) {
            cartItemsContainer.innerHTML = '<p>Your cart is empty. Please add items.</p>';
            cartPanel.style.display = 'none';
            cartCountEl.textContent = '0';
            cartTotalEl.textContent = '0.00';
            cartDataInput.value = '';
            return;
        }

        cartPanel.style.display = 'block';

        let total = 0;
        let count = 0;

        cartState.forEach((item) => {
            const lineTotal = item.price * item.quantity;
            total += lineTotal;
            count += item.quantity;

            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="cart-item-name">${item.name}</div>
                <div>Price: RM ${item.price.toFixed(2)}</div>
                <div>
                    <label>
                        Qty:
                        <input type="number" min="1" class="quantity-input" value="${item.quantity}">
                    </label>
                </div>
                <button class="btn btn-secondary remove-btn">Remove</button>
            `;

            // quantity change
            row.querySelector('.quantity-input').addEventListener('change', (e) => {
                const val = parseInt(e.target.value, 10);
                const newQty = Number.isFinite(val) && val > 0 ? val : 1;
                item.quantity = newQty;
                renderCart();
            });

            // remove item
            row.querySelector('.remove-btn').addEventListener('click', () => {
                const idx = findIndex(item.id);
                if (idx >= 0) {
                    cartState.splice(idx, 1);
                    renderCart();
                }
            });

            cartItemsContainer.appendChild(row);
        });

        cartCountEl.textContent = String(count);
        cartTotalEl.textContent = total.toFixed(2);
        cartDataInput.value = JSON.stringify(cartState);
    }

    function addToCart(item) {
        const idx = findIndex(item.id);
        if (idx >= 0) {
            cartState[idx].quantity += 1;
        } else {
            cartState.push({ ...item, quantity: 1 });
        }
        renderCart();
    }

    // bind add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const article = btn.closest('.menu-item');
            if (!article) return;
            const id = Number(article.dataset.id);
            const name = article.dataset.name || 'Unnamed item';
            const price = Number(article.dataset.price) || 0;
            if (!id || price < 0) return;
            addToCart({ id, name, price });
        });
    });

    if (viewCartBtn) {
        viewCartBtn.addEventListener('click', () => {
            cartPanel.style.display = cartPanel.style.display === 'none' ? 'block' : 'none';
            if (cartPanel.style.display === 'block') {
                cartPanel.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // Card input helpers
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 16) v = v.slice(0, 16);
            e.target.value = v;
        });
    }

    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 4) v = v.slice(0, 4);
            if (v.length >= 3) {
                v = v.slice(0, 2) + '/' + v.slice(2);
            }
            e.target.value = v;
        });
    }

    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 3) v = v.slice(0, 3);
            e.target.value = v;
        });
    }

    function clearFieldErrors() {
        if (cardNumberError) cardNumberError.textContent = '';
        if (cardExpiryError) cardExpiryError.textContent = '';
        if (cardCvvError) cardCvvError.textContent = '';
        if (walletIdError) walletIdError.textContent = '';
    }

    function validateCardFields() {
        clearFieldErrors();
        if (!paymentMethodSelect || paymentMethodSelect.value !== 'Card') {
            return true;
        }
        let ok = true;
        const num = cardNumberInput ? cardNumberInput.value.replace(/\D/g, '') : '';
        const exp = cardExpiryInput ? cardExpiryInput.value : '';
        const cvv = cardCvvInput ? cardCvvInput.value.replace(/\D/g, '') : '';

        if (!num || num.length !== 16) {
            if (cardNumberError) cardNumberError.textContent = 'Card number must be 16 digits.';
            ok = false;
        }
        if (!exp || !/^\d{2}\/\d{2}$/.test(exp)) {
            if (cardExpiryError) cardExpiryError.textContent = 'Use MM/YY format.';
            ok = false;
        } else {
            const mm = parseInt(exp.slice(0, 2), 10);
            const yy = parseInt(exp.slice(3), 10);
            const now = new Date();
            const currentYear = now.getFullYear() % 100;
            const currentMonth = now.getMonth() + 1; // 1–12

            if (mm < 1 || mm > 12) {
                if (cardExpiryError) cardExpiryError.textContent = 'Month must be between 01 and 12.';
                ok = false;
            } else if (yy < currentYear || (yy === currentYear && mm < currentMonth)) {
                if (cardExpiryError) cardExpiryError.textContent = 'Card has expired.';
                ok = false;
            }
        }
        if (!cvv || cvv.length !== 3) {
            if (cardCvvError) cardCvvError.textContent = 'CVV must be 3 digits.';
            ok = false;
        }
        return ok;
    }

    function validateWalletFields() {
        clearFieldErrors();
        if (!paymentMethodSelect || paymentMethodSelect.value !== 'eWallet') {
            return true;
        }
        let ok = true;
        const id = walletIdInput ? walletIdInput.value.replace(/\D/g, '') : '';
        if (!id) {
            if (walletIdError) walletIdError.textContent = 'Wallet phone is required.';
            ok = false;
        } else if (!/^01[0-46-9][0-9]{7,8}$/.test(id)) {
            if (walletIdError) walletIdError.textContent = 'Use Malaysia phone format (e.g., 0123456789).';
            ok = false;
        }
        return ok;
    }

    if (walletIdInput) {
        walletIdInput.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            e.target.value = v;
        });
    }

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', (e) => {
            const isLoggedIn = checkoutForm.dataset.loggedIn === '1';
            const loginUrl = checkoutForm.dataset.loginUrl;
            const isAdmin = checkoutForm.dataset.isAdmin === '1';

            if (!isLoggedIn) {
                e.preventDefault();
                alert('Please log in before submitting an order.');
                if (loginUrl) {
                    window.location.href = loginUrl;
                }
                return;
            }

            if (isAdmin) {
                e.preventDefault();
                alert('Admin accounts cannot place orders. Please log out and log in with a normal user account to make an order.');
                return;
            }

            if (cartState.length === 0) {
                e.preventDefault();
                alert('Please add items to the cart first.');
                return;
            }

            const method = paymentMethodSelect ? paymentMethodSelect.value : '';

            if (method === 'Card' && !validateCardFields()) {
                e.preventDefault();
                return;
            }

            if (method === 'eWallet' && !validateWalletFields()) {
                e.preventDefault();
                return;
            }

            cartDataInput.value = JSON.stringify(cartState);
        });
    }

    // initial render
    renderCart();
})();

