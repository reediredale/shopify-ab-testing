# A/B Test Examples

Copy and paste these JavaScript snippets into your tests. Modify selectors to match your theme.

## Button Color Tests

### 1. Red CTA Button
```javascript
document.querySelector('.add-to-cart-button')?.setAttribute('style', 'background: #FF0000 !important; color: white !important;');
```

### 2. Green CTA Button
```javascript
document.querySelector('.product-form__submit')?.setAttribute('style', 'background: #28a745 !important; border-color: #28a745 !important;');
```

### 3. Orange High-Contrast Button
```javascript
const btn = document.querySelector('button[name="add"]');
if (btn) {
    btn.style.cssText = 'background: #FF6600 !important; color: white !important; font-weight: 700 !important; box-shadow: 0 4px 12px rgba(255,102,0,0.3) !important;';
}
```

## CTA Text Changes

### 4. Add Benefit to Button Text
```javascript
const btn = document.querySelector('.add-to-cart-button');
if (btn) btn.textContent = 'Add to Cart - Free Shipping';
```

### 5. Action-Oriented CTA
```javascript
document.querySelector('.product-form__submit')?.textContent = 'Get Yours Now';
```

### 6. Urgency in Button
```javascript
const btn = document.querySelector('button[name="add"]');
if (btn) btn.innerHTML = 'Add to Cart <span style="font-size:0.85em">(Limited Stock)</span>';
```

## Urgency & Scarcity

### 7. Low Stock Warning
```javascript
const productForm = document.querySelector('.product-form');
if (productForm) {
    const warning = document.createElement('div');
    warning.style.cssText = 'background: #fff3cd; color: #856404; padding: 12px; margin: 15px 0; border-radius: 4px; font-weight: 600; border-left: 4px solid #ffc107;';
    warning.innerHTML = '⚠️ Only 3 left in stock!';
    productForm.insertBefore(warning, productForm.firstChild);
}
```

### 8. Popular Item Badge
```javascript
const productTitle = document.querySelector('.product__title');
if (productTitle) {
    const badge = document.createElement('span');
    badge.style.cssText = 'background: #28a745; color: white; padding: 4px 10px; border-radius: 3px; font-size: 0.8em; margin-left: 10px; vertical-align: middle;';
    badge.textContent = 'BESTSELLER';
    productTitle.appendChild(badge);
}
```

### 9. Timer Countdown
```javascript
const productPrice = document.querySelector('.product__price');
if (productPrice) {
    const timer = document.createElement('div');
    timer.style.cssText = 'background: #dc3545; color: white; padding: 10px; margin: 15px 0; text-align: center; font-weight: 700; border-radius: 4px;';
    timer.innerHTML = '🔥 Flash Sale Ends in: <span id="countdown">15:00</span>';
    productPrice.parentNode.insertBefore(timer, productPrice.nextSibling);

    let seconds = 900;
    setInterval(() => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        document.getElementById('countdown').textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
        if (seconds > 0) seconds--;
    }, 1000);
}
```

## Social Proof

### 10. Reviews Counter
```javascript
const productForm = document.querySelector('.product-form');
if (productForm) {
    const social = document.createElement('div');
    social.style.cssText = 'padding: 10px; margin: 10px 0; color: #666; font-size: 0.95em;';
    social.innerHTML = '⭐⭐⭐⭐⭐ <strong>4.8/5</strong> from 1,247 reviews';
    productForm.appendChild(social);
}
```

### 11. Recent Purchase Notification
```javascript
setTimeout(() => {
    const notification = document.createElement('div');
    notification.style.cssText = 'position: fixed; bottom: 20px; left: 20px; background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; max-width: 300px; animation: slideIn 0.3s ease;';
    notification.innerHTML = '<strong>Sarah from Texas</strong><br><span style="color: #666; font-size: 0.9em;">Just purchased this item</span>';
    document.body.appendChild(notification);

    setTimeout(() => notification.remove(), 5000);
}, 3000);
```

### 12. People Viewing Counter
```javascript
const productTitle = document.querySelector('.product__title');
if (productTitle) {
    const viewers = document.createElement('div');
    viewers.style.cssText = 'color: #FF6600; font-size: 0.9em; margin-top: 5px; font-weight: 600;';
    viewers.innerHTML = '👁 ' + (Math.floor(Math.random() * 20) + 15) + ' people viewing this right now';
    productTitle.parentNode.insertBefore(viewers, productTitle.nextSibling);
}
```

## Trust Signals

### 13. Money-Back Guarantee
```javascript
const addToCart = document.querySelector('.add-to-cart-button');
if (addToCart) {
    const guarantee = document.createElement('div');
    guarantee.style.cssText = 'text-align: center; margin-top: 15px; padding: 12px; background: #d4edda; border-radius: 4px; color: #155724; font-weight: 600;';
    guarantee.innerHTML = '✓ 30-Day Money-Back Guarantee';
    addToCart.parentNode.insertBefore(guarantee, addToCart.nextSibling);
}
```

### 14. Free Shipping Badge
```javascript
const price = document.querySelector('.product__price');
if (price) {
    const shipping = document.createElement('div');
    shipping.style.cssText = 'background: #007bff; color: white; display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; margin-left: 10px; font-weight: 600;';
    shipping.textContent = 'FREE SHIPPING';
    price.appendChild(shipping);
}
```

## Pricing Display

### 15. Strike-Through Original Price
```javascript
const price = document.querySelector('.product__price');
if (price) {
    const originalPrice = document.createElement('span');
    originalPrice.style.cssText = 'text-decoration: line-through; color: #999; margin-right: 10px; font-size: 0.9em;';
    originalPrice.textContent = '$49.99';
    price.insertBefore(originalPrice, price.firstChild);
}
```

### 16. Savings Badge
```javascript
const price = document.querySelector('.product__price');
if (price) {
    const savings = document.createElement('span');
    savings.style.cssText = 'background: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; font-size: 0.8em; margin-left: 10px; font-weight: 700;';
    savings.textContent = 'SAVE 25%';
    price.appendChild(savings);
}
```

## Product Page Layout

### 17. Sticky Add to Cart
```javascript
window.addEventListener('scroll', function() {
    const btn = document.querySelector('.add-to-cart-button');
    if (btn && window.scrollY > 500) {
        btn.style.cssText = 'position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999; padding: 18px; font-size: 18px; margin: 0; border-radius: 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);';
    } else if (btn) {
        btn.style.position = 'relative';
    }
});
```

### 18. Enlarge Product Images
```javascript
const images = document.querySelectorAll('.product__media img');
images.forEach(img => {
    img.style.cssText = 'transform: scale(1.15); transition: transform 0.3s;';
});
```

## Collection Page Tests

### 19. Sale Badge on Products
```javascript
const products = document.querySelectorAll('.product-card');
products.forEach((product, index) => {
    if (index % 3 === 0) { // Every 3rd product
        const badge = document.createElement('div');
        badge.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #FF0000; color: white; padding: 5px 10px; font-weight: 700; border-radius: 3px; font-size: 0.85em; z-index: 10;';
        badge.textContent = 'SALE';
        product.style.position = 'relative';
        product.appendChild(badge);
    }
});
```

### 20. Quick Buy Buttons
```javascript
const productCards = document.querySelectorAll('.product-card');
productCards.forEach(card => {
    const quickBuy = document.createElement('button');
    quickBuy.style.cssText = 'width: 100%; padding: 12px; background: #000; color: white; border: none; cursor: pointer; font-weight: 600; margin-top: 10px; border-radius: 4px;';
    quickBuy.textContent = 'Quick Add';
    quickBuy.onclick = function(e) {
        e.preventDefault();
        // Add your quick-add logic here
        alert('Quick add functionality - customize this!');
    };
    card.appendChild(quickBuy);
});
```

## Advanced Tests

### 21. Exit-Intent Popup
```javascript
let shown = false;
document.addEventListener('mouseout', function(e) {
    if (!shown && e.clientY < 10) {
        shown = true;
        const popup = document.createElement('div');
        popup.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); z-index: 99999; text-align: center; max-width: 500px;';
        popup.innerHTML = '<h2 style="margin-bottom: 15px;">Wait! Don\'t Leave Yet</h2><p style="font-size: 18px; margin-bottom: 20px;">Get <strong>15% OFF</strong> your first order</p><input type="email" placeholder="Enter your email" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;"><button style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; font-weight: 700; border-radius: 4px; cursor: pointer;">Get My Discount</button>';

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99998;';
        overlay.onclick = () => { popup.remove(); overlay.remove(); };

        document.body.appendChild(overlay);
        document.body.appendChild(popup);
    }
});
```

### 22. Quantity Selector Highlight
```javascript
const qty = document.querySelector('input[name="quantity"]');
if (qty) {
    qty.style.cssText = 'border: 2px solid #FF6600 !important; padding: 10px !important; font-size: 16px !important;';

    const label = document.createElement('div');
    label.style.cssText = 'color: #FF6600; font-weight: 600; margin-bottom: 5px;';
    label.textContent = 'Buy more, save more!';
    qty.parentNode.insertBefore(label, qty);
}
```

## Tips

- Always test your JavaScript in the browser console first
- Use `document.querySelector()` for single elements
- Use `document.querySelectorAll()` for multiple elements
- Inspect your theme to find the correct CSS selectors
- Use `?. ` (optional chaining) to prevent errors if elements don't exist
- Test on mobile and desktop
- Start with small, simple changes
- One test at a time for clear results
