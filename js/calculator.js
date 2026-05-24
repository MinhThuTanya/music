document.addEventListener('DOMContentLoaded', function() {
    // Merch data
    const merchItems = [
        {
            id: 1,
            name: 'Chase Atlantic Hoodie',
            description: 'Черный худи с логотипом группы',
            price: 3999,
            image: 'assets/1.jpg',
            category: 'clothing'
        },
        {
            id: 2,
            name: 'Beauty In Death T-Shirt',
            description: 'Футболка с обложкой альбома',
            price: 1999,
            image: 'assets/2.png',
            category: 'clothing'
        },
        {
            id: 3,
            name: 'Signed CD',
            description: 'Автографованный CD альбома',
            price: 2999,
            image: 'assets/3.jpg',
            category: 'music',
            badge: 'Подпись'
        },
        {
            id: 4,
            name: 'Tour Poster',
            description: 'Официальный постер тура 2023',
            price: 1499,
            image: 'assets/4.jpg',
            category: 'posters'
        },
        {
            id: 5,
            name: 'Limited Edition Vinyl',
            description: 'Ограниченное издание на виниле',
            price: 4499,
            image: 'assets/5.jpg',
            category: 'music',
            badge: 'Limited'
        },
        {
            id: 6,
            name: 'Band Cap',
            description: 'Бейсболка с вышитым логотипом',
            price: 1799,
            image: 'assets/6.jpg',
            category: 'accessories'
        },
        {
            id: 7,
            name: 'Phone Case',
            description: 'Чехол для телефона с дизайном',
            price: 1299,
            image: 'assets/7.jpg',
            category: 'accessories'
        },
        {
            id: 8,
            name: 'Sticker Pack',
            description: 'Набор стикеров с символикой',
            price: 499,
            image: 'assets/8.jpg',
            category: 'accessories'
        }
    ];

    // Promo codes
    const promoCodes = {
        'CHASE20': 20, // 20% discount
        'FAN10': 10,   // 10% discount
        'ATLANTIC50': 50, // 50% on one item
        'WELCOME15': 15   // 15% discount
    };

    // Cart state
    let cart = [];
    let appliedPromo = null;
    let discountAmount = 0;
    const shippingCost = 500;
    const freeShippingThreshold = 5000;

    // DOM Elements
    const merchGrid = document.getElementById('merchGrid');
    const orderItems = document.getElementById('orderItems');
    const itemsCount = document.getElementById('itemsCount');
    const subtotal = document.getElementById('subtotal');
    const discount = document.getElementById('discount');
    const shipping = document.getElementById('shipping');
    const total = document.getElementById('total');
    const promoCodeInput = document.getElementById('promoCode');
    const applyPromoBtn = document.getElementById('applyPromo');
    const promoMessage = document.getElementById('promoMessage');
    const freeShippingToggle = document.getElementById('freeShippingToggle');
    const shippingNote = document.getElementById('shippingNote');
    const checkoutBtn = document.getElementById('checkoutBtn');

    // Initialize the calculator
    function initCalculator() {
        renderMerchItems();
        renderCart();
        updateTotals();
        setupEventListeners();
    }

    // Render merch items
    function renderMerchItems() {
        merchGrid.innerHTML = '';
        
        merchItems.forEach(item => {
            const cartItem = cart.find(cartItem => cartItem.id === item.id);
            const quantity = cartItem ? cartItem.quantity : 0;
            
            const merchItem = document.createElement('div');
            merchItem.className = 'merch-item';
            merchItem.innerHTML = `
                ${item.badge ? `<span class="merch-badge">${item.badge}</span>` : ''}
                <img src="${item.image}" alt="${item.name}" class="merch-image">
                <h4 class="merch-name">${item.name}</h4>
                <p class="merch-description">${item.description}</p>
                <div class="merch-price">${item.price.toLocaleString()} ₽</div>
                <div class="merch-controls">
                    <div class="quantity-control">
                        <button class="quantity-btn" data-id="${item.id}" data-action="decrease" ${quantity === 0 ? 'disabled' : ''}>-</button>
                        <input type="text" class="quantity-input" value="${quantity}" data-id="${item.id}" readonly>
                        <button class="quantity-btn" data-id="${item.id}" data-action="increase">+</button>
                    </div>
                    <button class="add-to-cart ${quantity > 0 ? 'added' : ''}" data-id="${item.id}">
                        ${quantity > 0 ? '✓ В корзине' : 'В корзину'}
                    </button>
                </div>
            `;
            merchGrid.appendChild(merchItem);
        });
    }

    // Render cart items
    function renderCart() {
        if (cart.length === 0) {
            orderItems.innerHTML = `
                <div class="empty-cart">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p>Корзина пуста</p>
                    <small>Добавьте товары из каталога</small>
                </div>
            `;
            return;
        }

        orderItems.innerHTML = '';
        cart.forEach(item => {
            const merchItem = merchItems.find(m => m.id === item.id);
            const orderItem = document.createElement('div');
            orderItem.className = 'order-item';
            orderItem.innerHTML = `
                <img src="${merchItem.image}" alt="${merchItem.name}" class="order-item-img">
                <div class="order-item-details">
                    <div class="order-item-name">${merchItem.name}</div>
                    <div class="order-item-price">${merchItem.price.toLocaleString()} ₽ × ${item.quantity}</div>
                </div>
                <div class="order-item-quantity">
                    <button class="decrease-quantity" data-id="${item.id}">-</button>
                    <span>${item.quantity}</span>
                    <button class="increase-quantity" data-id="${item.id}">+</button>
                </div>
                <button class="remove-item" data-id="${item.id}" title="Удалить">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            `;
            orderItems.appendChild(orderItem);
        });
    }

    // Update totals
    function updateTotals() {
        const itemsTotal = cart.reduce((sum, item) => {
            const merchItem = merchItems.find(m => m.id === item.id);
            return sum + (merchItem.price * item.quantity);
        }, 0);

        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        
        // Calculate discount
        let calculatedDiscount = 0;
        if (appliedPromo && promoCodes[appliedPromo]) {
            calculatedDiscount = Math.floor(itemsTotal * (promoCodes[appliedPromo] / 100));
        }
        discountAmount = calculatedDiscount;

        // Calculate shipping
        let calculatedShipping = shippingCost;
        let isFreeShipping = freeShippingToggle.checked && itemsTotal >= freeShippingThreshold;
        
        if (isFreeShipping) {
            calculatedShipping = 0;
        }

        // Update shipping note
        if (itemsTotal >= freeShippingThreshold) {
            shippingNote.textContent = 'Доступна бесплатная доставка!';
            shippingNote.style.color = '#10b981';
        } else {
            const needed = freeShippingThreshold - itemsTotal;
            shippingNote.textContent = `Добавьте товаров на ${needed.toLocaleString()} ₽ для бесплатной доставки`;
            shippingNote.style.color = 'var(--gray-text)';
        }

        // Update UI
        itemsCount.textContent = itemCount;
        subtotal.textContent = itemsTotal.toLocaleString() + ' ₽';
        discount.textContent = `-${calculatedDiscount.toLocaleString()} ₽`;
        shipping.textContent = isFreeShipping ? 'Бесплатно' : calculatedShipping.toLocaleString() + ' ₽';
        
        const grandTotal = itemsTotal - calculatedDiscount + calculatedShipping;
        total.textContent = grandTotal.toLocaleString() + ' ₽';
    }

    // Add to cart
    function addToCart(itemId) {
        const itemIndex = cart.findIndex(item => item.id === itemId);
        
        if (itemIndex === -1) {
            cart.push({ id: itemId, quantity: 1 });
        } else {
            cart[itemIndex].quantity++;
        }
        
        renderMerchItems();
        renderCart();
        updateTotals();
        animateAddToCart(itemId);
    }

    // Remove from cart
    function removeFromCart(itemId) {
        const itemIndex = cart.findIndex(item => item.id === itemId);
        
        if (itemIndex !== -1) {
            cart.splice(itemIndex, 1);
        }
        
        renderMerchItems();
        renderCart();
        updateTotals();
    }

    // Update quantity
    function updateQuantity(itemId, change) {
        const itemIndex = cart.findIndex(item => item.id === itemId);
        
        if (itemIndex !== -1) {
            cart[itemIndex].quantity += change;
            
            if (cart[itemIndex].quantity <= 0) {
                cart.splice(itemIndex, 1);
            }
        }
        
        renderMerchItems();
        renderCart();
        updateTotals();
    }

    // Apply promo code
    function applyPromoCode() {
        const code = promoCodeInput.value.trim().toUpperCase();
        promoMessage.className = 'promo-message';
        
        if (!code) {
            promoMessage.textContent = 'Введите промокод';
            promoMessage.classList.add('promo-error');
            return;
        }
        
        if (promoCodes[code]) {
            appliedPromo = code;
            promoMessage.textContent = `Промокод применен! Скидка ${promoCodes[code]}%`;
            promoMessage.classList.add('promo-success');
            promoCodeInput.value = '';
            updateTotals();
        } else {
            promoMessage.textContent = 'Неверный промокод';
            promoMessage.classList.add('promo-error');
            appliedPromo = null;
            updateTotals();
        }
    }

    // Animate add to cart
    function animateAddToCart(itemId) {
        const button = document.querySelector(`.add-to-cart[data-id="${itemId}"]`);
        if (button) {
            button.classList.add('added');
            setTimeout(() => {
                button.textContent = '✓ В корзине';
            }, 300);
        }
    }

    // Setup event listeners
    function setupEventListeners() {
        // Merch item controls
        merchGrid.addEventListener('click', (e) => {
            const target = e.target;
            
            if (target.classList.contains('add-to-cart')) {
                const itemId = parseInt(target.dataset.id);
                addToCart(itemId);
            }
            
            if (target.classList.contains('quantity-btn')) {
                const itemId = parseInt(target.dataset.id);
                const action = target.dataset.action;
                updateQuantity(itemId, action === 'increase' ? 1 : -1);
            }
        });

        // Cart controls
        orderItems.addEventListener('click', (e) => {
            const target = e.target;
            const button = target.closest('button');
            
            if (!button) return;
            
            if (button.classList.contains('remove-item')) {
                const itemId = parseInt(button.dataset.id);
                removeFromCart(itemId);
            }
            
            if (button.classList.contains('decrease-quantity')) {
                const itemId = parseInt(button.dataset.id);
                updateQuantity(itemId, -1);
            }
            
            if (button.classList.contains('increase-quantity')) {
                const itemId = parseInt(button.dataset.id);
                updateQuantity(itemId, 1);
            }
        });

        // Promo code
        applyPromoBtn.addEventListener('click', applyPromoCode);
        promoCodeInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                applyPromoCode();
            }
        });

        // Shipping toggle
        freeShippingToggle.addEventListener('change', updateTotals);

        // Checkout button
        // Внутри setupEventListeners() функции calculator.js замените checkoutBtn обработчик:
checkoutBtn.addEventListener('click', async () => {
    if (cart.length === 0) {
        alert('Добавьте товары в корзину перед оформлением заказа!');
        return;
    }

    async function checkAuth() {
        try {
            const res = await fetch(API_BASE + '?action=profile', { method: 'GET' });
            return res.ok;
        } catch(e) { return false; }
    }

    const isAuth = await checkAuth();
    if (!isAuth) {
        localStorage.setItem('pending_order', JSON.stringify({
            items: cart.map(item => {
                const merch = merchItems.find(m => m.id === item.id);
                return { id: item.id, name: merch.name, quantity: item.quantity, price: merch.price };
            }),
            total: parseFloat(total.textContent.replace(/\D/g, ''))
        }));
        window.location.href = 'fan_login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }

    const itemsForOrder = cart.map(item => {
        const merch = merchItems.find(m => m.id === item.id);
        return { id: item.id, name: merch.name, quantity: item.quantity, price: merch.price };
    });
    const totalAmount = parseFloat(total.textContent.replace(/\D/g, ''));

    checkoutBtn.innerHTML = `<div class="btn-loading"><div class="spinner"></div>Оформление...</div>`;
    checkoutBtn.disabled = true;

    try {
        const url = API_BASE + '?action=order&data=' + encodeURIComponent(JSON.stringify({ items: itemsForOrder, total: totalAmount }));
        const response = await fetch(url, { method: 'GET' });
        const data = await response.json();
        if (response.ok) {
            alert(`Заказ оформлен! Номер заказа: ${data.order_id}`);
            cart = [];
            appliedPromo = null;
            freeShippingToggle.checked = false;
            renderMerchItems();
            renderCart();
            updateTotals();
        } else {
            alert('Ошибка: ' + (data.error || 'Не удалось оформить заказ'));
        }
    } catch (err) {
        alert('Ошибка сети. Попробуйте позже.');
    } finally {
        checkoutBtn.innerHTML = '<span>Оформить заказ</span><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M8 1a.75.75 0 0 1 .75.75v5.69l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V1.75A.75.75 0 0 1 8 1z"></path><path d="M2 9.75A.75.75 0 0 1 2.75 9h10.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75z"></path></svg>';
        checkoutBtn.disabled = false;
    }
});
    }

    // Initialize the calculator
    initCalculator();
});