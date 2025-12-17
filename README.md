# Dead-Simple A/B Testing Platform for Shopify

A blazing-fast, no-nonsense A/B testing platform built with pure PHP and vanilla JavaScript. Zero frameworks, zero bloat.

## Features

- Single-file PHP application (index.php)
- SQLite database (auto-created)
- Bayesian statistics (see results immediately, even with 1 view)
- Chart.js visualizations
- Simple password authentication
- API for tracking events
- Shopify integration snippet

## Stack

- **Backend**: Pure PHP 7.4+
- **Database**: SQLite 3
- **Frontend**: Vanilla JavaScript
- **Charts**: Chart.js (CDN)

## Quick Start

### 1. Installation

Upload these files to your server:
- `index.php`
- `shopify-snippet.js`

The `database.sqlite` file will be created automatically on first run.

### 2. Server Setup

#### Option A: Digital Ocean / Ubuntu Server with Nginx

```bash
# SSH into your server
ssh root@your-server-ip

# Create directory
mkdir -p /var/www/ab-testing
cd /var/www/ab-testing

# Upload files (use SFTP or git clone)
# Then set permissions
chown -R www-data:www-data /var/www/ab-testing
chmod 755 /var/www/ab-testing
chmod 666 /var/www/ab-testing/database.sqlite # Will be created on first run

# Configure Nginx
nano /etc/nginx/sites-available/ab-testing
```

Nginx configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/ab-testing;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
# Enable site
ln -s /etc/nginx/sites-available/ab-testing /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# SSL (recommended)
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com
```

#### Option B: Apache / cPanel

1. Upload files via FTP to `public_html/ab-testing/`
2. Ensure PHP 7.4+ is enabled
3. Create `.htaccess` file:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?page=$1 [QSA,L]

# Security
<Files database.sqlite>
    Order allow,deny
    Deny from all
</Files>
```

4. Set permissions:
```bash
chmod 755 index.php
chmod 666 database.sqlite # After first run
```

#### Option C: Local Development (Laravel Herd / XAMPP / MAMP)

1. Copy files to your local server directory
2. Navigate to `http://localhost/ab-testing/index.php`
3. Done! No configuration needed.

### 3. Configure Authentication

Open `index.php` and change the password:

```php
define('PASSWORD', 'your-secure-password-here');
```

### 4. Add Snippet to Shopify

1. Open `shopify-snippet.js`
2. Update the API URL:
```javascript
const API_URL = 'https://your-domain.com';
```

3. In Shopify Admin:
   - Go to **Online Store** → **Themes** → **Edit Code**
   - Open `theme.liquid`
   - Paste the entire contents of `shopify-snippet.js` inside `<script>` tags before `</body>`

```html
<script>
// Paste shopify-snippet.js contents here
</script>
</body>
```

Alternatively, you can:
- Host `shopify-snippet.js` on your server
- Add to theme.liquid: `<script src="https://your-domain.com/shopify-snippet.js"></script>`

### 5. Create Your First Test

1. Navigate to `https://your-domain.com/index.php`
2. Login with your password
3. Click **Create Test**
4. Fill in the form:
   - **Name**: "Red vs Blue Button"
   - **Page Type**: Product Page
   - **URL Pattern**: `/products/.*`
   - **Traffic Split**: 50
   - **Variant B JavaScript**:
   ```javascript
   document.querySelector('.add-to-cart-button').style.background = '#FF0000';
   ```
5. Click **Create Test**
6. Visit your Shopify store and watch the magic happen!

## How It Works

### Test Assignment
- User visits your Shopify store
- Snippet checks for active tests matching the current URL
- User is assigned to Control or Variant B based on traffic split
- Assignment is saved in cookies (sticky sessions)
- Variant JavaScript executes (if assigned to Variant B)

### Event Tracking
- **View**: Tracked immediately when user loads the page
- **Add to Cart**: Tracked when user clicks add-to-cart button
- **Purchase**: Tracked on thank you page with revenue

### Bayesian Statistics
Results are calculated using Beta distributions:
- **Control**: Beta(conversions + 1, views - conversions + 1)
- **Variant B**: Beta(conversions + 1, views - conversions + 1)
- **Probability**: Monte Carlo simulation with 10,000 draws
- **Winner**: Variant with >95% probability of being better

You'll see results **immediately**, even with just 1 view. The probability will update as more data comes in.

### Multi-Website Support

The platform supports running tests across multiple websites with a single installation:

**How it works:**
1. When creating a test, select which website it applies to (e.g., jbracks.com.au or jbracks.com)
2. Paste the **same snippet** on both websites
3. The snippet automatically detects which site it's running on (`window.location.hostname`)
4. Tests and events are filtered by website - each site only sees its own tests

**Benefits:**
- One server, multiple sites
- Separate test results per website
- No configuration needed in the snippet
- Tests won't leak across different domains

**Example:** Create a "Red Button Test" for jbracks.com.au and another "Blue Button Test" for jbracks.com. Each site will only run its own tests and track its own events.

## Usage Guide

### Finding CSS Selectors

1. Visit your Shopify store
2. Right-click the element you want to change → **Inspect**
3. Look for classes or IDs in the HTML
4. Test in browser console:
```javascript
document.querySelector('.add-to-cart-button')
```
5. If it highlights the element, use that selector!

### Common Shopify Selectors

- Add to cart button: `.product-form__submit`, `.add-to-cart-button`, `button[name="add"]`
- Product title: `.product__title`, `.product-single__title`
- Price: `.product__price`, `.price`, `.product-single__price`
- Product form: `.product-form`
- Images: `.product__media img`, `.product-single__media`

### Test Ideas

See **EXAMPLES.md** for 22 ready-to-use test examples:
- Button color changes
- CTA text optimization
- Urgency messages
- Social proof
- Trust signals
- Pricing displays
- And more!

### Interpreting Results

Visit **Results** page for any test:

- **95%+ probability**: Clear winner! Ship it.
- **5% or less**: Control is better. Kill the variant.
- **Between 5-95%**: Inconclusive. Collect more data.

**Key Metrics:**
- **Views**: Unique visitors who saw the variant
- **Conversions**: Unique visitors who purchased
- **Conversion Rate**: Conversions / Views
- **Revenue**: Total revenue from this variant
- **AOV**: Average Order Value

### Best Practices

1. **One change at a time**: Don't test button color AND text together
2. **Let it run**: Minimum 100 views per variant before making decisions
3. **Statistical significance**: Wait for 95%+ probability
4. **Mobile matters**: Test on mobile devices too
5. **High traffic pages**: Product pages convert better than collections
6. **Big swings first**: Test major changes before micro-optimizations

## Troubleshooting

### Database Permissions Error
```bash
chmod 666 database.sqlite
chmod 755 /var/www/ab-testing
```

### Events Not Tracking
1. Check browser console for errors
2. Verify API_URL in shopify-snippet.js
3. Ensure CORS is working (should be automatic)
4. Check network tab for failed API calls

### JavaScript Not Executing
1. Verify CSS selector is correct
2. Check browser console for errors
3. Test JavaScript in browser console first
4. Ensure test is active and URL pattern matches

### No Tests Showing in Snippet
1. Verify API endpoint works: `https://your-domain.com/index.php?page=api/tests`
2. Check that test is marked as active
3. Verify URL pattern matches current page

### Purchase Events Not Tracking
1. Ensure you're on the thank you page (`/thank_you` or `/orders/`)
2. Check that `Shopify.checkout` object exists
3. Some themes may need custom integration

## Security Notes

- Change the default password in `index.php`
- Use HTTPS (get free SSL with Let's Encrypt)
- Database file is protected by `.htaccess`
- API endpoints are intentionally public (needed for tracking)
- Session-based authentication for admin pages

## Performance

- **Lightweight**: Single PHP file + SQLite
- **Fast**: No framework overhead
- **Minimal**: ~15KB JavaScript snippet
- **Scalable**: SQLite handles 100k+ events easily
- **Cached**: Browser caches variant assignments

## Backup & Maintenance

### Backup Database
```bash
cp database.sqlite database-backup-$(date +%Y%m%d).sqlite
```

### Clear Old Events (optional)
```sql
sqlite3 database.sqlite "DELETE FROM events WHERE created_at < date('now', '-90 days');"
```

### Archive Completed Tests
Just pause them in the UI. Data persists in the database.

## Upgrading

Simply replace `index.php` with the new version. Database schema is backwards compatible.

## Cost Estimate

**Digital Ocean Droplet**: $6/month (Basic)
- Handles 50,000+ tests/month
- Includes bandwidth
- Add Cloudflare (free) for CDN

**Total**: ~$6/month for unlimited A/B testing

## FAQ

**Q: Can I run multiple tests at once?**
A: Yes! Create as many tests as you want. Each user can be in multiple tests simultaneously.

**Q: Can I test across multiple websites?**
A: Yes! Select the website when creating each test. The snippet automatically detects which site it's on and only runs tests for that website. Paste the same snippet on all your sites.

**Q: What happens if I change the JavaScript mid-test?**
A: New users will see the new version. Existing users (in cookies) continue seeing their assigned variant. Best practice: create a new test.

**Q: How long should I run tests?**
A: Until you reach 95%+ probability or 100+ conversions per variant. Usually 1-4 weeks depending on traffic.

**Q: Can I test checkout pages?**
A: Shopify doesn't allow JavaScript on checkout. Test product pages, cart, and pre-checkout instead.

**Q: Does this slow down my store?**
A: No. The snippet is ~15KB and executes after page load. Negligible impact.

**Q: What if my server goes down?**
A: The snippet fails gracefully. Your store works normally, just no A/B tests run.

## Support

This is a simple, self-hosted solution. No official support, but the code is straightforward:
- Stuck? Read the code in `index.php`
- Check `EXAMPLES.md` for test ideas
- Most issues are CSS selector problems (use browser inspector)

## License

MIT License - Do whatever you want with it.

## Credits

Built for Shopify store owners who want fast, simple A/B testing without the $200/month SaaS bloat.

---

**Now go make more money.**
