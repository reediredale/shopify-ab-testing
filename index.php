<?php
// ============================================
// CONFIGURATION
// ============================================
define('PASSWORD', 'admin123'); // Change this!
define('DB_FILE', __DIR__ . '/database.sqlite');

session_start();

// ============================================
// DATABASE SETUP
// ============================================
function initDatabase() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS tests (
        id INTEGER PRIMARY KEY,
        name TEXT,
        website TEXT,
        page_type TEXT,
        url_pattern TEXT,
        traffic_split INTEGER DEFAULT 50,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS variants (
        id INTEGER PRIMARY KEY,
        test_id INTEGER,
        name TEXT,
        javascript TEXT,
        is_control INTEGER DEFAULT 0
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY,
        test_id INTEGER,
        variant_id INTEGER,
        user_id TEXT,
        event_type TEXT,
        revenue REAL DEFAULT 0,
        website TEXT,
        metadata TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: Add website column to existing tables if it doesn't exist
    try {
        $db->exec("SELECT website FROM tests LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $db->exec("ALTER TABLE tests ADD COLUMN website TEXT");
    }

    try {
        $db->exec("SELECT website FROM events LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $db->exec("ALTER TABLE events ADD COLUMN website TEXT");
    }

    try {
        $db->exec("SELECT metadata FROM events LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $db->exec("ALTER TABLE events ADD COLUMN metadata TEXT");
    }

    return $db;
}

$db = initDatabase();

// ============================================
// BAYESIAN PROBABILITY CALCULATION
// ============================================
function calculateBayesianProbability($viewsA, $convsA, $viewsB, $convsB) {
    if ($viewsA == 0 || $viewsB == 0) {
        return 50.0;
    }

    // Beta distribution parameters
    $alphaA = $convsA + 1;
    $betaA = $viewsA - $convsA + 1;
    $alphaB = $convsB + 1;
    $betaB = $viewsB - $convsB + 1;

    // Monte Carlo simulation: 10000 draws
    $bWins = 0;
    $samples = 10000;

    for ($i = 0; $i < $samples; $i++) {
        // Generate random beta samples
        $sampleA = betaVariate($alphaA, $betaA);
        $sampleB = betaVariate($alphaB, $betaB);

        if ($sampleB > $sampleA) {
            $bWins++;
        }
    }

    return ($bWins / $samples) * 100;
}

// Beta distribution random variate using rejection sampling
function betaVariate($alpha, $beta) {
    $y1 = gammaVariate($alpha, 1);
    $y2 = gammaVariate($beta, 1);
    return $y1 / ($y1 + $y2);
}

// Gamma distribution random variate
function gammaVariate($shape, $scale) {
    if ($shape < 1) {
        return gammaVariate($shape + 1, $scale) * pow(mt_rand() / mt_getrandmax(), 1 / $shape);
    }

    $d = $shape - 1/3;
    $c = 1 / sqrt(9 * $d);

    while (true) {
        $x = normalVariate();
        $v = 1 + $c * $x;

        if ($v <= 0) continue;

        $v = $v * $v * $v;
        $u = mt_rand() / mt_getrandmax();

        if ($u < 1 - 0.0331 * $x * $x * $x * $x) {
            return $d * $v * $scale;
        }

        if (log($u) < 0.5 * $x * $x + $d * (1 - $v + log($v))) {
            return $d * $v * $scale;
        }
    }
}

// Box-Muller transform for normal distribution
function normalVariate() {
    static $hasSpare = false;
    static $spare;

    if ($hasSpare) {
        $hasSpare = false;
        return $spare;
    }

    $u = mt_rand() / mt_getrandmax();
    $v = mt_rand() / mt_getrandmax();

    $spare = sqrt(-2 * log($u)) * sin(2 * pi() * $v);
    $hasSpare = true;

    return sqrt(-2 * log($u)) * cos(2 * pi() * $v);
}

// ============================================
// ROUTING
// ============================================
$page = $_GET['page'] ?? 'tests';

// Handle logout
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// Handle login form submission
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: ?page=tests');
        exit;
    } else {
        $loginError = 'Invalid password';
    }
}

// API endpoints (no auth required)
if ($page === 'api/tests') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $website = $_GET['website'] ?? null;

    if ($website) {
        $stmt = $db->prepare("SELECT * FROM tests WHERE is_active = 1 AND website = ?");
        $stmt->execute([$website]);
    } else {
        $stmt = $db->query("SELECT * FROM tests WHERE is_active = 1");
    }

    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tests as &$test) {
        $stmt = $db->prepare("SELECT * FROM variants WHERE test_id = ?");
        $stmt->execute([$test['id']]);
        $test['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($tests);
    exit;
}

if ($page === 'api/track') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Convert metadata to JSON string if present
    $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;

    $stmt = $db->prepare("INSERT INTO events (test_id, variant_id, user_id, event_type, revenue, website, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['test_id'],
        $data['variant_id'],
        $data['user_id'],
        $data['event_type'],
        $data['revenue'] ?? 0,
        $data['website'] ?? null,
        $metadata
    ]);

    echo json_encode(['success' => true]);
    exit;
}

// Check authentication for admin pages
if ($page !== 'login' && !isset($_SESSION['authenticated'])) {
    header('Location: ?page=login');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'create') {
    $stmt = $db->prepare("INSERT INTO tests (name, website, page_type, url_pattern, traffic_split) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'],
        $_POST['website'],
        $_POST['page_type'],
        $_POST['url_pattern'],
        $_POST['traffic_split']
    ]);
    $testId = $db->lastInsertId();

    // Create control variant
    $stmt = $db->prepare("INSERT INTO variants (test_id, name, javascript, is_control) VALUES (?, ?, ?, ?)");
    $stmt->execute([$testId, 'Control', '', 1]);

    // Create variant B
    $stmt = $db->prepare("INSERT INTO variants (test_id, name, javascript, is_control) VALUES (?, ?, ?, ?)");
    $stmt->execute([$testId, 'Variant B', $_POST['javascript'], 0]);

    header('Location: ?page=results&id=' . $testId);
    exit;
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'edit') {
    $testId = $_POST['test_id'];

    // Update test details
    $stmt = $db->prepare("UPDATE tests SET name = ?, website = ?, page_type = ?, url_pattern = ?, traffic_split = ? WHERE id = ?");
    $stmt->execute([
        $_POST['name'],
        $_POST['website'],
        $_POST['page_type'],
        $_POST['url_pattern'],
        $_POST['traffic_split'],
        $testId
    ]);

    // Update variant B JavaScript
    $stmt = $db->prepare("UPDATE variants SET javascript = ? WHERE test_id = ? AND is_control = 0");
    $stmt->execute([$_POST['javascript'], $testId]);

    header('Location: ?page=results&id=' . $testId);
    exit;
}

// Toggle test active status
if (isset($_GET['toggle'])) {
    $stmt = $db->prepare("UPDATE tests SET is_active = 1 - is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle']]);

    // Redirect back to results page if coming from there, otherwise to test list
    if (isset($_GET['return']) && $_GET['return'] === 'results') {
        header('Location: ?page=results&id=' . $_GET['toggle']);
    } else {
        header('Location: ?page=tests');
    }
    exit;
}

// ============================================
// HTML LAYOUT
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/B Testing Platform</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 30px; color: #333; }
        h2 { margin: 30px 0 20px 0; color: #555; }
        .nav { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; font-weight: 500; }
        .nav a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f8f9fa; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn:hover { background: #0056b3; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        textarea { min-height: 150px; font-family: 'Courier New', monospace; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; }
        .stat-card h3 { margin-bottom: 15px; color: #333; font-size: 18px; }
        .stat-row { display: flex; justify-content: space-between; margin: 10px 0; }
        .stat-label { color: #666; }
        .stat-value { font-weight: 600; color: #333; font-size: 18px; }
        .winner { background: #d4edda; border-left-color: #28a745; }
        .probability { background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0; border-left: 4px solid #ffc107; }
        .probability-value { font-size: 36px; font-weight: 700; color: #333; margin: 10px 0; }
        .chart-container { margin: 30px 0; }
        .login-box { max-width: 400px; margin: 100px auto; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0; overflow-x: auto; }
        .code-block pre { font-family: 'Courier New', monospace; font-size: 13px; }
    </style>
</head>
<body>

<?php if ($page === 'login'): ?>
    <!-- LOGIN PAGE -->
    <div class="login-box">
        <div class="container">
            <h1>Login</h1>
            <?php if (isset($loginError)): ?>
                <div class="error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required autofocus>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </div>

<?php elseif ($page === 'tests'): ?>
    <!-- TEST LIST PAGE -->
    <div class="container">
        <div class="nav">
            <a href="?page=tests">Tests</a>
            <a href="?page=create">Create Test</a>
            <a href="?page=logout" style="float: right;">Logout</a>
        </div>

        <h1>A/B Tests</h1>

        <?php
        $websiteFilter = $_GET['website_filter'] ?? null;

        if ($websiteFilter) {
            $stmt = $db->prepare("SELECT * FROM tests WHERE website = ? ORDER BY created_at DESC");
            $stmt->execute([$websiteFilter]);
        } else {
            $stmt = $db->query("SELECT * FROM tests ORDER BY created_at DESC");
        }
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div style="margin-bottom: 20px;">
            <label style="margin-right: 10px; font-weight: 600;">Filter by website:</label>
            <select onchange="window.location.href='?page=tests&website_filter=' + this.value" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">All Websites</option>
                <option value="jbracks.com.au" <?= $websiteFilter === 'jbracks.com.au' ? 'selected' : '' ?>>jbracks.com.au</option>
                <option value="jbracks.com" <?= $websiteFilter === 'jbracks.com' ? 'selected' : '' ?>>jbracks.com</option>
            </select>
        </div>

        <?php if (empty($tests)): ?>
            <p>No tests yet. <a href="?page=create">Create your first test</a></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Website</th>
                        <th>Page Type</th>
                        <th>URL Pattern</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Conversions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                        <?php
                        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as views FROM events WHERE test_id = ? AND event_type = 'view'");
                        $stmt->execute([$test['id']]);
                        $views = $stmt->fetchColumn();

                        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as conversions FROM events WHERE test_id = ? AND event_type = 'purchase'");
                        $stmt->execute([$test['id']]);
                        $conversions = $stmt->fetchColumn();
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($test['name']) ?></strong></td>
                            <td><span class="badge" style="background: #e9ecef; color: #495057;"><?= htmlspecialchars($test['website'] ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($test['page_type']) ?></td>
                            <td><?= htmlspecialchars($test['url_pattern']) ?></td>
                            <td>
                                <?php if ($test['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Paused</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $views ?></td>
                            <td><?= $conversions ?></td>
                            <td>
                                <a href="?page=results&id=<?= $test['id'] ?>" class="btn btn-small">View Results</a>
                                <a href="?page=edit&id=<?= $test['id'] ?>" class="btn btn-small" style="background: #ffc107; color: #000;">Edit</a>
                                <a href="?page=tests&toggle=<?= $test['id'] ?>" class="btn btn-small <?= $test['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $test['is_active'] ? 'Pause' : 'Activate' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($page === 'create'): ?>
    <!-- CREATE TEST PAGE -->
    <div class="container">
        <div class="nav">
            <a href="?page=tests">Tests</a>
            <a href="?page=create">Create Test</a>
            <a href="?page=logout" style="float: right;">Logout</a>
        </div>

        <h1>Create New Test</h1>

        <form method="POST">
            <div class="form-group">
                <label>Test Name</label>
                <input type="text" name="name" placeholder="e.g., Red vs Blue Button" required>
            </div>

            <div class="form-group">
                <label>Website</label>
                <select name="website" required>
                    <option value="jbracks.com.au">jbracks.com.au</option>
                    <option value="jbracks.com">jbracks.com</option>
                </select>
            </div>

            <div class="form-group">
                <label>Page Type</label>
                <select name="page_type" required>
                    <option value="product">Product Page</option>
                    <option value="collection">Collection Page</option>
                    <option value="homepage">Homepage</option>
                    <option value="cart">Cart Page</option>
                    <option value="all">All Pages</option>
                </select>
            </div>

            <div class="form-group">
                <label>URL Pattern (regex)</label>
                <input type="text" name="url_pattern" placeholder="e.g., /products/.* or leave empty for all" value=".*">
                <small style="color: #666; display: block; margin-top: 5px;">Use .* to match all pages, or specific patterns like /products/.*</small>
            </div>

            <div class="form-group">
                <label>Traffic Split (%)</label>
                <input type="number" name="traffic_split" value="50" min="1" max="99" required>
                <small style="color: #666; display: block; margin-top: 5px;">Percentage of traffic that sees Variant B (rest sees Control)</small>
            </div>

            <div class="form-group">
                <label>Variant B JavaScript</label>
                <textarea name="javascript" placeholder="e.g., document.querySelector('.btn').style.background = '#FF0000';" required></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">This code will run for users in Variant B. See <a href="EXAMPLES.md" target="_blank">EXAMPLES.md</a> for ideas.</small>
            </div>

            <button type="submit" class="btn">Create Test</button>
        </form>
    </div>

<?php elseif ($page === 'edit'): ?>
    <!-- EDIT TEST PAGE -->
    <?php
    $testId = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo "Test not found";
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM variants WHERE test_id = ? AND is_control = 0");
    $stmt->execute([$testId]);
    $variantB = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <div class="container">
        <div class="nav">
            <a href="?page=tests">Tests</a>
            <a href="?page=create">Create Test</a>
            <a href="?page=logout" style="float: right;">Logout</a>
        </div>

        <h1>Edit Test</h1>

        <form method="POST" action="?page=edit">
            <input type="hidden" name="test_id" value="<?= $test['id'] ?>">

            <div class="form-group">
                <label>Test Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($test['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Website</label>
                <select name="website" required>
                    <option value="jbracks.com.au" <?= $test['website'] === 'jbracks.com.au' ? 'selected' : '' ?>>jbracks.com.au</option>
                    <option value="jbracks.com" <?= $test['website'] === 'jbracks.com' ? 'selected' : '' ?>>jbracks.com</option>
                </select>
            </div>

            <div class="form-group">
                <label>Page Type</label>
                <select name="page_type" required>
                    <option value="product" <?= $test['page_type'] === 'product' ? 'selected' : '' ?>>Product Page</option>
                    <option value="collection" <?= $test['page_type'] === 'collection' ? 'selected' : '' ?>>Collection Page</option>
                    <option value="homepage" <?= $test['page_type'] === 'homepage' ? 'selected' : '' ?>>Homepage</option>
                    <option value="cart" <?= $test['page_type'] === 'cart' ? 'selected' : '' ?>>Cart Page</option>
                    <option value="all" <?= $test['page_type'] === 'all' ? 'selected' : '' ?>>All Pages</option>
                </select>
            </div>

            <div class="form-group">
                <label>URL Pattern (regex)</label>
                <input type="text" name="url_pattern" value="<?= htmlspecialchars($test['url_pattern']) ?>" required>
                <small style="color: #666; display: block; margin-top: 5px;">Use .* to match all pages, or specific patterns like /products/.*</small>
            </div>

            <div class="form-group">
                <label>Traffic Split (%)</label>
                <input type="number" name="traffic_split" value="<?= $test['traffic_split'] ?>" min="1" max="99" required>
                <small style="color: #666; display: block; margin-top: 5px;">Percentage of traffic that sees Variant B (rest sees Control)</small>
            </div>

            <div class="form-group">
                <label>Variant B JavaScript</label>
                <textarea name="javascript" required><?= htmlspecialchars($variantB['javascript']) ?></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">This code will run for users in Variant B.</small>
            </div>

            <button type="submit" class="btn">Save Changes</button>
            <a href="?page=results&id=<?= $test['id'] ?>" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>

<?php elseif ($page === 'results'): ?>
    <!-- RESULTS PAGE -->
    <?php
    $testId = $_GET['id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo "Test not found";
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM variants WHERE test_id = ? ORDER BY is_control DESC");
    $stmt->execute([$testId]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats for each variant
    $stats = [];
    foreach ($variants as $variant) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as views FROM events WHERE variant_id = ? AND event_type = 'view'");
        $stmt->execute([$variant['id']]);
        $views = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as add_to_carts FROM events WHERE variant_id = ? AND event_type = 'add_to_cart'");
        $stmt->execute([$variant['id']]);
        $addToCarts = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as conversions FROM events WHERE variant_id = ? AND event_type = 'purchase'");
        $stmt->execute([$variant['id']]);
        $conversions = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(revenue), 0) as revenue FROM events WHERE variant_id = ? AND event_type = 'purchase'");
        $stmt->execute([$variant['id']]);
        $revenue = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(revenue), 0) as cart_revenue FROM events WHERE variant_id = ? AND event_type = 'add_to_cart'");
        $stmt->execute([$variant['id']]);
        $cartRevenue = $stmt->fetchColumn();

        $cr = $views > 0 ? ($conversions / $views) * 100 : 0;
        $atcRate = $views > 0 ? ($addToCarts / $views) * 100 : 0;
        $aov = $conversions > 0 ? $revenue / $conversions : 0;
        $revenuePerSession = $views > 0 ? $revenue / $views : 0;

        $stats[$variant['id']] = [
            'variant' => $variant,
            'views' => $views,
            'add_to_carts' => $addToCarts,
            'conversions' => $conversions,
            'cr' => $cr,
            'atc_rate' => $atcRate,
            'revenue' => $revenue,
            'cart_revenue' => $cartRevenue,
            'aov' => $aov,
            'revenue_per_session' => $revenuePerSession
        ];
    }

    // Calculate Bayesian probability
    $variantA = $stats[$variants[0]['id']];
    $variantB = $stats[$variants[1]['id']];
    $probability = calculateBayesianProbability(
        $variantA['views'],
        $variantA['conversions'],
        $variantB['views'],
        $variantB['conversions']
    );

    // Get daily data for charts
    $stmt = $db->prepare("
        SELECT
            DATE(created_at) as date,
            variant_id,
            COUNT(DISTINCT CASE WHEN event_type = 'view' THEN user_id END) as views,
            COUNT(DISTINCT CASE WHEN event_type = 'purchase' THEN user_id END) as conversions,
            COALESCE(SUM(CASE WHEN event_type = 'purchase' THEN revenue ELSE 0 END), 0) as revenue
        FROM events
        WHERE test_id = ?
        GROUP BY DATE(created_at), variant_id
        ORDER BY date
    ");
    $stmt->execute([$testId]);
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data for charts
    $chartData = [];
    foreach ($dailyData as $row) {
        $date = $row['date'];
        $variantId = $row['variant_id'];
        $cr = $row['views'] > 0 ? ($row['conversions'] / $row['views']) * 100 : 0;

        if (!isset($chartData[$date])) {
            $chartData[$date] = [];
        }

        $chartData[$date][$variantId] = [
            'cr' => $cr,
            'revenue' => $row['revenue']
        ];
    }
    ?>

    <div class="container">
        <div class="nav">
            <a href="?page=tests">Tests</a>
            <a href="?page=create">Create Test</a>
            <a href="?page=logout" style="float: right;">Logout</a>
        </div>

        <h1>
            <?= htmlspecialchars($test['name']) ?>
            <?php if ($test['is_active']): ?>
                <span class="badge badge-success" style="font-size: 16px; vertical-align: middle;">RUNNING</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 16px; vertical-align: middle;">PAUSED</span>
            <?php endif; ?>
        </h1>
        <p style="color: #666; margin-bottom: 20px;">
            Website: <strong><?= htmlspecialchars($test['website'] ?? 'N/A') ?></strong> |
            Page Type: <?= htmlspecialchars($test['page_type']) ?> |
            URL Pattern: <?= htmlspecialchars($test['url_pattern']) ?> |
            Traffic Split: <?= $test['traffic_split'] ?>%
        </p>
        <div style="margin-bottom: 30px;">
            <a href="?page=edit&id=<?= $test['id'] ?>" class="btn" style="background: #ffc107; color: #000;">Edit Test</a>
            <a href="?toggle=<?= $test['id'] ?>&return=results" class="btn <?= $test['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                <?= $test['is_active'] ? 'Pause Test' : 'Resume Test' ?>
            </a>
            <a href="?page=tests" class="btn" style="background: #6c757d;">Back to Tests</a>
        </div>

        <!-- Bayesian Probability -->
        <div class="probability">
            <div style="font-size: 18px; color: #666; margin-bottom: 10px;">Probability of Variant B beating Control</div>
            <div class="probability-value"><?= number_format($probability, 1) ?>%</div>
            <?php if ($probability > 95): ?>
                <div style="color: #28a745; font-weight: 600; margin-top: 10px;">Variant B is winning! Ship it.</div>
            <?php elseif ($probability < 5): ?>
                <div style="color: #dc3545; font-weight: 600; margin-top: 10px;">Control is winning. Variant B is worse.</div>
            <?php else: ?>
                <div style="color: #666; margin-top: 10px;">No clear winner yet. Keep collecting data.</div>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-card <?= $probability > 95 && !$stat['variant']['is_control'] ? 'winner' : ($probability < 5 && $stat['variant']['is_control'] ? 'winner' : '') ?>">
                    <h3><?= htmlspecialchars($stat['variant']['name']) ?></h3>
                    <div class="stat-row">
                        <span class="stat-label">Views</span>
                        <span class="stat-value"><?= number_format($stat['views']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Add to Carts</span>
                        <span class="stat-value"><?= number_format($stat['add_to_carts']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">ATC Rate</span>
                        <span class="stat-value"><?= number_format($stat['atc_rate'], 2) ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Cart Revenue</span>
                        <span class="stat-value">$<?= number_format($stat['cart_revenue'], 2) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Conversions</span>
                        <span class="stat-value"><?= number_format($stat['conversions']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Conversion Rate</span>
                        <span class="stat-value"><?= number_format($stat['cr'], 2) ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Revenue</span>
                        <span class="stat-value">$<?= number_format($stat['revenue'], 2) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Revenue/Session</span>
                        <span class="stat-value">$<?= number_format($stat['revenue_per_session'], 2) ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">AOV</span>
                        <span class="stat-value">$<?= number_format($stat['aov'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Charts -->
        <?php if (!empty($chartData)): ?>
            <h2>Conversion Rate Over Time</h2>
            <div class="chart-container">
                <canvas id="crChart"></canvas>
            </div>

            <h2>Revenue Over Time</h2>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                const dates = <?= json_encode(array_keys($chartData)) ?>;
                const chartData = <?= json_encode($chartData) ?>;
                const variantAId = <?= $variants[0]['id'] ?>;
                const variantBId = <?= $variants[1]['id'] ?>;

                // Conversion Rate Chart
                const crData = {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Control',
                            data: dates.map(date => chartData[date][variantAId]?.cr || 0),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.1
                        },
                        {
                            label: 'Variant B',
                            data: dates.map(date => chartData[date][variantBId]?.cr || 0),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.1
                        }
                    ]
                };

                new Chart(document.getElementById('crChart'), {
                    type: 'line',
                    data: crData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        }
                    }
                });

                // Revenue Chart
                const revenueData = {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Control',
                            data: dates.map(date => chartData[date][variantAId]?.revenue || 0),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.1
                        },
                        {
                            label: 'Variant B',
                            data: dates.map(date => chartData[date][variantBId]?.revenue || 0),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.1
                        }
                    ]
                };

                new Chart(document.getElementById('revenueChart'), {
                    type: 'line',
                    data: revenueData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
            </script>
        <?php endif; ?>

        <!-- Variant Code -->
        <h2>Variant B Code</h2>
        <div class="code-block">
            <pre><?= htmlspecialchars($variantB['variant']['javascript']) ?></pre>
        </div>
    </div>

<?php endif; ?>

</body>
</html>
