// ============================================
// A/B Testing Snippet for Shopify
// ============================================
// INSTRUCTIONS:
// 1. Update API_URL below to your server URL
// 2. Paste this entire script in your theme.liquid before </body>
// 3. Or paste in Settings > Custom JS
// ============================================

const API_URL = 'https://your-domain.com'; // CHANGE THIS!

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
function trackEvent(testId, variantId, eventType, revenue = 0) {
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
            website: website
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
                    trackEvent(test.id, variant.id, 'add_to_cart');
                }
            });

            // Track purchase on thank you page
            if (window.location.pathname.includes('/thank_you') ||
                window.location.pathname.includes('/orders/') ||
                window.location.pathname.includes('/checkouts/') ||
                window.location.search.includes('checkout')) {

                // Try to get order value from Shopify checkout object
                let revenue = 0;

                // Method 1: Shopify.checkout (most common)
                if (typeof Shopify !== 'undefined' && Shopify.checkout && Shopify.checkout.total_price) {
                    revenue = parseFloat(Shopify.checkout.total_price);
                }
                // Method 2: Shopify.Checkout (alternative)
                else if (typeof Shopify !== 'undefined' && Shopify.Checkout && Shopify.Checkout.total_price) {
                    revenue = parseFloat(Shopify.Checkout.total_price);
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
                        }
                    }
                }

                // Debug logging (remove in production if desired)
                console.log('A/B Test Purchase Tracked:', {
                    test_id: test.id,
                    variant_id: variant.id,
                    revenue: revenue,
                    shopify_data_available: typeof Shopify !== 'undefined' && !!Shopify.checkout
                });

                trackEvent(test.id, variant.id, 'purchase', revenue);
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
