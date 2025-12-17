// ============================================
// A/B Testing Snippet for Shopify
// ============================================
// INSTRUCTIONS:
// 1. Update API_URL below to your server URL
// 2. Paste this entire script in your theme.liquid before </body>
// 3. Or paste in Settings > Custom JS
// ============================================

const API_URL = 'https://ab.reediredale.com'; // CHANGE THIS!

// ============================================
// CONFIGURATION
// ============================================
const COOKIE_NAME = 'ab_tests';
const COOKIE_DAYS = 30;

// ============================================
// HELPER FUNCTIONS
// ============================================

// Get or generate user ID
function getUserId() {
    let userId = getCookie('ab_user_id');
    if (!userId) {
        userId = 'user_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        setCookie('ab_user_id', userId, 365);
    }
    return userId;
}

// Cookie helpers
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
}

function getTestAssignments() {
    const cookie = getCookie(COOKIE_NAME);
    return cookie ? JSON.parse(decodeURIComponent(cookie)) : {};
}

function saveTestAssignments(assignments) {
    setCookie(COOKIE_NAME, encodeURIComponent(JSON.stringify(assignments)), COOKIE_DAYS);
}

// Get current website domain
function getCurrentWebsite() {
    return window.location.hostname;
}

// Track event to API
function trackEvent(testId, variantId, eventType, revenue = 0, metadata = {}) {
    const userId = getUserId();
    const website = getCurrentWebsite();

    fetch(`${API_URL}/index.php?page=api/track`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            test_id: testId,
            variant_id: variantId,
            user_id: userId,
            event_type: eventType,
            revenue: revenue,
            website: website,
            metadata: metadata
        })
    }).catch(err => console.error('Tracking error:', err));
}

// Match URL pattern
function matchesPattern(pattern, url) {
    try {
        const regex = new RegExp(pattern);
        return regex.test(url);
    } catch (e) {
        return false;
    }
}

// Extract product data from page
function getProductData(element) {
    const productData = {
        product_id: null,
        variant_id: null,
        product_name: null,
        price: 0,
        quantity: 1
    };

    // Try to find the form element
    const form = element.closest('form[action*="/cart/add"]');

    if (form) {
        // Get variant ID
        const variantInput = form.querySelector('[name="id"], [name="variant_id"]');
        if (variantInput) {
            productData.variant_id = variantInput.value;
        }

        // Get quantity
        const quantityInput = form.querySelector('[name="quantity"]');
        if (quantityInput) {
            productData.quantity = parseInt(quantityInput.value) || 1;
        }
    }

    // Try to get price from various locations
    let priceText = null;

    // Method 1: Shopify product object (most reliable)
    if (typeof ShopifyAnalytics !== 'undefined' && ShopifyAnalytics.meta && ShopifyAnalytics.meta.product) {
        const product = ShopifyAnalytics.meta.product;
        productData.product_id = product.id;
        productData.product_name = product.name;
        productData.price = parseFloat(product.variants[0]?.price || 0);
    }
    // Method 2: Look for price in DOM
    else {
        const priceElements = document.querySelectorAll('.price, [data-price], .product-price, .money');
        for (const priceEl of priceElements) {
            const text = priceEl.textContent || priceEl.getAttribute('data-price') || '';
            const cleaned = text.replace(/[^0-9.]/g, '');
            if (cleaned && parseFloat(cleaned) > 0) {
                productData.price = parseFloat(cleaned);
                break;
            }
        }
    }

    // Get product name if not already set
    if (!productData.product_name) {
        const titleEl = document.querySelector('.product-title, .product__title, h1[itemprop="name"]');
        if (titleEl) {
            productData.product_name = titleEl.textContent.trim();
        }
    }

    return productData;
}

// Assign variant based on traffic split
function assignVariant(test) {
    const assignments = getTestAssignments();

    // Check if already assigned
    if (assignments[test.id]) {
        return test.variants.find(v => v.id === assignments[test.id]);
    }

    // Assign new variant
    const rand = Math.random() * 100;
    const variant = rand < test.traffic_split ? test.variants[1] : test.variants[0]; // B or A

    // Save assignment
    assignments[test.id] = variant.id;
    saveTestAssignments(assignments);

    return variant;
}

// ============================================
// MAIN EXECUTION
// ============================================

async function initABTests() {
    try {
        // Get current website
        const website = getCurrentWebsite();

        // Fetch active tests for this website
        const response = await fetch(`${API_URL}/index.php?page=api/tests&website=${encodeURIComponent(website)}`);
        const tests = await response.json();

        if (!tests || tests.length === 0) {
            return;
        }

        const currentUrl = window.location.pathname;

        // Process each test
        for (const test of tests) {
            // Check if URL matches test pattern
            if (!matchesPattern(test.url_pattern, currentUrl)) {
                continue;
            }

            // Assign variant
            const variant = assignVariant(test);

            // Execute variant JavaScript (if not control)
            if (!variant.is_control && variant.javascript) {
                try {
                    eval(variant.javascript);
                } catch (e) {
                    console.error('A/B test execution error:', e);
                }
            }

            // Track view event
            trackEvent(test.id, variant.id, 'view');

            // Track add-to-cart events
            document.addEventListener('click', function(e) {
                const target = e.target.closest('form[action*="/cart/add"], button[name="add"], .add-to-cart, [data-add-to-cart]');
                if (target) {
                    // Extract product data
                    const productData = getProductData(target);
                    const cartValue = productData.price * productData.quantity;

                    // Track with product details and cart value as revenue
                    trackEvent(test.id, variant.id, 'add_to_cart', cartValue, {
                        product_id: productData.product_id,
                        variant_id: productData.variant_id,
                        product_name: productData.product_name,
                        price: productData.price,
                        quantity: productData.quantity
                    });

                    console.log('Add-to-cart tracked:', {
                        cart_value: cartValue,
                        product: productData
                    });
                }
            });

            // Track purchase on thank you page
            if (window.location.pathname.includes('/thank_you') ||
                window.location.pathname.includes('/orders/') ||
                window.location.pathname.includes('/checkouts/') ||
                window.location.search.includes('checkout')) {

                // Try to get order details from Shopify checkout object
                let revenue = 0;
                let orderMetadata = {};

                // Method 1: Shopify.checkout (most common)
                if (typeof Shopify !== 'undefined' && Shopify.checkout) {
                    const checkout = Shopify.checkout;
                    revenue = parseFloat(checkout.total_price) || 0;

                    orderMetadata = {
                        order_id: checkout.order_id,
                        subtotal: parseFloat(checkout.subtotal_price) || 0,
                        total: parseFloat(checkout.total_price) || 0,
                        tax: parseFloat(checkout.total_tax) || 0,
                        shipping: parseFloat(checkout.shipping_rate?.price) || 0,
                        discount: parseFloat(checkout.discount?.amount) || 0,
                        currency: checkout.currency,
                        item_count: checkout.line_items?.length || 0
                    };
                }
                // Method 2: Shopify.Checkout (alternative)
                else if (typeof Shopify !== 'undefined' && Shopify.Checkout) {
                    revenue = parseFloat(Shopify.Checkout.total_price) || 0;
                    orderMetadata.total = revenue;
                }
                // Method 3: Look for order data in meta tags (some themes)
                else {
                    const orderMeta = document.querySelector('meta[name="shopify-checkout-api-token"]');
                    if (orderMeta) {
                        // Try to extract from page content
                        const priceElements = document.querySelectorAll('[data-order-total], .order-total, .payment-due__price');
                        if (priceElements.length > 0) {
                            const priceText = priceElements[0].textContent.replace(/[^0-9.]/g, '');
                            revenue = parseFloat(priceText) || 0;
                            orderMetadata.total = revenue;
                        }
                    }
                }

                // Debug logging (remove in production if desired)
                console.log('A/B Test Purchase Tracked:', {
                    test_id: test.id,
                    variant_id: variant.id,
                    revenue: revenue,
                    order_details: orderMetadata,
                    shopify_data_available: typeof Shopify !== 'undefined' && !!Shopify.checkout
                });

                trackEvent(test.id, variant.id, 'purchase', revenue, orderMetadata);
            }
        }

    } catch (error) {
        console.error('A/B testing initialization error:', error);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initABTests);
} else {
    initABTests();
}
