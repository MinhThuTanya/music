// Простой калькулятор мерча для Malcom Todd
document.addEventListener('DOMContentLoaded', function() {
    // Список товаров (название, цена)
    const merchItems = [
        { id: 1, name: 'Футболка "Sweet Boy"', price: 1999 },
        { id: 2, name: 'Худи "Chest Pain"', price: 3999 },
        { id: 3, name: 'Постер автограф', price: 999 },
        { id: 4, name: 'Значок (набор 5 шт.)', price: 499 },
        { id: 5, name: 'Кепка с логотипом', price: 1299 },
        { id: 6, name: 'Термокружка', price: 899 }
    ];

    let cart = new Array(merchItems.length).fill(0);

    function renderMerch() {
        const container = document.getElementById('merchList');
        if (!container) return;
        container.innerHTML = '';
        for (let i = 0; i < merchItems.length; i++) {
            const item = merchItems[i];
            const quantity = cart[i];
            const itemTotal = quantity * item.price;
            const div = document.createElement('div');
            div.className = 'merch-item';
            div.innerHTML = `
                <div class="merch-info">
                    <div class="merch-name">${item.name}</div>
                    <div class="merch-price">${item.price.toLocaleString()} ₽</div>
                </div>
                <div class="merch-control">
                    <div class="quantity-control">
                        <button class="quantity-btn" data-idx="${i}" data-delta="-1">-</button>
                        <input type="text" class="quantity-input" value="${quantity}" data-idx="${i}" readonly>
                        <button class="quantity-btn" data-idx="${i}" data-delta="1">+</button>
                    </div>
                    <div class="item-total">${itemTotal.toLocaleString()} ₽</div>
                </div>
            `;
            container.appendChild(div);
        }
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        for (let i = 0; i < merchItems.length; i++) {
            total += cart[i] * merchItems[i].price;
        }
        const totalSpan = document.getElementById('totalPrice');
        if (totalSpan) totalSpan.textContent = total.toLocaleString();
    }

    function updateQuantity(idx, delta) {
        let newVal = cart[idx] + delta;
        if (newVal < 0) newVal = 0;
        cart[idx] = newVal;
        renderMerch();
    }

    function resetCart() {
        cart.fill(0);
        renderMerch();
    }

    const container = document.getElementById('merchList');
    if (container) {
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.quantity-btn');
            if (btn && btn.dataset.idx !== undefined) {
                const idx = parseInt(btn.dataset.idx);
                const delta = parseInt(btn.dataset.delta);
                updateQuantity(idx, delta);
            }
        });
    }
    const resetBtn = document.getElementById('resetBtn');
    if (resetBtn) resetBtn.addEventListener('click', resetCart);

    renderMerch();
});