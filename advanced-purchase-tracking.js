// ADVANCED PURCHASE TRACKING
// Add this to shopify-snippet.js if you need more detailed tracking

// Replace the simple purchase tracking with this enhanced version:

if (window.location.pathname.includes('/thank_you') ||
    window.location.pathname.includes('/orders/') ||
    window.location.pathname.includes('/checkouts/')) {

    let purchaseData = {
        revenue: 0,
        order_id: null,
        currency: 'USD',
        items: [],
        quantity: 0
    };

    if (typeof Shopify !== 'undefined' && Shopify.checkout) {
        const checkout = Shopify.checkout;

        purchaseData = {
            revenue: parseFloat(checkout.total_price) || 0,
            order_id: checkout.order_id || checkout.order_number,
            currency: checkout.currency || 'USD',
            items: checkout.line_items ? checkout.line_items.map(item => ({
                product_id: item.product_id,
                variant_id: item.variant_id,
                title: item.title,
                quantity: item.quantity,
                price: item.price
            })) : [],
            quantity: checkout.line_items ? checkout.line_items.reduce((sum, item) => sum + item.quantity, 0) : 0
        };
    }

    // Send to your API
    fetch(`${API_URL}/index.php?page=api/track`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            test_id: test.id,
            variant_id: variant.id,
            user_id: getUserId(),
            event_type: 'purchase',
            revenue: purchaseData.revenue,
            website: getCurrentWebsite(),
            // Store additional data as JSON string (you'd need to add a column for this)
            metadata: JSON.stringify({
                order_id: purchaseData.order_id,
                currency: purchaseData.currency,
                item_count: purchaseData.quantity,
                items: purchaseData.items
            })
        })
    });

    console.log('Enhanced Purchase Tracked:', purchaseData);
}
