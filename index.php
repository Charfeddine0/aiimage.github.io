<?php
declare(strict_types=1);

session_start();

const DB_FILE = __DIR__ . '/data/shortlinks.db';
const IP_SALT = 'make-me-unique-and-secret';

$pdo = initializeDatabase();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    handleRedirect($pdo, ltrim($path, '/'));
    exit;
}

$csrfToken = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        addFlash('error', 'CSRF validation failed. Please try again.');
        redirectHome();
    }

    handlePost($pdo);
    redirectHome();
}

$currentUser = getCurrentUser($pdo);
$linkRows = $currentUser ? fetchLinks($pdo, (int) $currentUser['id']) : [];
$summary = getSummaryStats($pdo, $currentUser ? (int) $currentUser['id'] : null);
$ads = loadAds(__DIR__ . '/data/ads.json');
$flash = consumeFlash();
$baseUrl = getBaseUrl();
$activeLinks = array_filter($linkRows, function ($link) {
    return (int) $link['is_active'] === 1 && !isExpired($link['expires_at']);
});
$averageClicks = count($linkRows) > 0 ? array_sum(array_column($linkRows, 'clicks')) / count($linkRows) : 0;

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Shortener | Free Bitly Alternative</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="topbar">
        <div class="topbar__brand">
            <span class="logo-dot"></span>
            <div>
                <strong>Link Shortener</strong>
                <small>Smart control panel</small>
            </div>
        </div>
        <nav class="topbar__nav">
            <a href="#create">Create</a>
            <a href="#dashboard">Links</a>
            <a href="#features">Features</a>
        </nav>
        <div class="topbar__cta">
            <?php if ($currentUser): ?>
                <span class="pill pill--ghost"><?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES); ?></span>
            <?php else: ?>
                <a class="btn ghost tiny" href="#create">Log in</a>
            <?php endif; ?>
        </div>
    </header>

    <header class="hero">
        <div class="hero__content">
            <div class="hero__pill">Fully free · Zero ads · Ready to deploy</div>
            <h1>Modern dashboard to shorten, brand, and track every link</h1>
            <p class="lede">Create branded short URLs, watch live clicks, and control expirations from a sleek English-first interface that runs anywhere PHP runs.</p>
            <ul class="hero__highlights">
                <li>Crisp tables with instant state toggles</li>
                <li>One-click copy and today’s activity chips</li>
                <li>Secure auth with CSRF protection enabled</li>
            </ul>
            <div class="hero__metrics">
                <div>
                    <p class="metric__label">Total links</p>
                    <p class="metric__value"><?php echo number_format($summary['total_links']); ?></p>
                </div>
                <div>
                    <p class="metric__label">Total clicks</p>
                    <p class="metric__value"><?php echo number_format($summary['total_clicks']); ?></p>
                </div>
                <div>
                    <p class="metric__label">Today’s activity</p>
                    <p class="metric__value"><?php echo number_format($summary['today_clicks']); ?></p>
                </div>
            </div>
            <div class="hero__actions">
                <a class="btn primary" href="#create">Start free</a>
                <a class="btn ghost" href="#features">See features</a>
            </div>
            <div class="hero__quick card">
                <div class="hero__quick-head">
                    <div>
                        <p class="eyebrow">Drop your link</p>
                        <h3>Shorten right from the homepage</h3>
                    </div>
                    <?php if ($currentUser): ?>
                        <span class="pill pill--ghost">Signed in</span>
                    <?php else: ?>
                        <span class="pill pill--ghost">Login required</span>
                    <?php endif; ?>
                </div>
                <?php if ($currentUser): ?>
                    <form class="quick-grid" method="post">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="create_link">
                        <input type="hidden" name="is_active" value="1">
                        <input type="hidden" name="expires_at" value="">
                        <div>
                            <label>Destination URL</label>
                            <input name="target_url" type="url" required placeholder="https://example.com/landing">
                        </div>
                        <div>
                            <label>Custom slug (optional)</label>
                            <input name="custom_slug" type="text" pattern="[A-Za-z0-9-]{3,30}" placeholder="brand-offer">
                        </div>
                        <div class="quick-actions">
                            <button class="btn primary" type="submit">Shorten now</button>
                            <a class="btn ghost tiny" href="#create">Go to full form</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="quick-grid quick-grid--disabled">
                        <div>
                            <label>Destination URL</label>
                            <input type="url" placeholder="https://example.com/landing" disabled>
                        </div>
                        <div>
                            <label>Custom slug (optional)</label>
                            <input type="text" placeholder="brand-offer" disabled>
                        </div>
                        <div class="quick-actions">
                            <a class="btn primary" href="#create">Sign in to shorten</a>
                            <span class="muted">Login required to create links.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($ads): ?>
                <div class="ads ads--inline">
                            <?php foreach (array_slice($ads, 0, 3) as $ad): ?>
                                <a class="ad-card" href="<?php echo htmlspecialchars($ad['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                                    <p class="ad-eyebrow"><?php echo htmlspecialchars($ad['tag'], ENT_QUOTES); ?></p>
                                    <h3><?php echo htmlspecialchars($ad['title'], ENT_QUOTES); ?></h3>
                                    <p><?php echo htmlspecialchars($ad['description'], ENT_QUOTES); ?></p>
                                    <span>Learn more →</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
            <?php endif; ?>
        </div>
        <div class="hero__panel">
            <div class="card auth-card">
                <div class="auth-card__header">
                    <div>
                        <p class="eyebrow">Your account</p>
                        <h3>Sign in or create a free profile</h3>
                        <p class="muted">Pick what you need and manage links from the first minute.</p>
                    </div>
                    <span class="pill pill--success">Passwords hashed & salted</span>
                </div>
                <div class="auth-card__grid">
                    <form method="post" class="stacked">
                        <p class="card__title">Log in</p>
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="login">
                        <label>Email</label>
                        <input name="email" type="email" required placeholder="name@example.com">
                        <label>Password</label>
                        <input name="password" type="password" required minlength="8" placeholder="••••••••">
                        <button class="btn primary" type="submit">Log in</button>
                    </form>
                    <form method="post" class="stacked secondary-panel">
                        <p class="card__title">Create account</p>
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="register">
                        <label>Email</label>
                        <input name="email" type="email" required placeholder="name@example.com">
                        <label>Password</label>
                        <input name="password" type="password" required minlength="8" placeholder="Strong password">
                        <button class="btn secondary" type="submit">Create profile</button>
                    </form>
                </div>
                <div class="auth-card__footer">
                    <p class="muted">No credit card required—just sign up and shorten links with CSRF protection enabled.</p>
                </div>
            </div>
        </div>
    </header>

    <main class="container" id="create">
        <?php foreach ($flash['success'] as $message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
        <?php endforeach; ?>
        <?php foreach ($flash['error'] as $message): ?>
            <div class="alert error"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
        <?php endforeach; ?>

        <?php if ($currentUser): ?>
            <section class="status-grid">
                <div class="card status-card">
                    <div class="status-card__head">
                        <p class="eyebrow">User panel</p>
                        <span class="pill pill--ghost">Online</span>
                    </div>
                    <h2>Welcome back, <?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES); ?>. Everything is ready.</h2>
                    <p class="muted">Brand links, pause or expire them in one click. Everything is organized in one tidy panel.</p>
                    <div class="status-card__meta">
                        <span>Links: <?php echo number_format(count($linkRows)); ?></span>
                        <span>Active now: <?php echo number_format(count($activeLinks)); ?></span>
                        <span>Avg clicks: <?php echo number_format($averageClicks, 1); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <p class="stat-card__label">Total links</p>
                    <p class="stat-card__value"><?php echo number_format($summary['total_links']); ?></p>
                    <p class="muted">Every link you have created so far.</p>
                </div>
                <div class="stat-card">
                    <p class="stat-card__label">All clicks</p>
                    <p class="stat-card__value"><?php echo number_format($summary['total_clicks']); ?></p>
                    <p class="muted">Accurate tracking with hashed IPs for privacy.</p>
                </div>
                <div class="stat-card">
                    <p class="stat-card__label">Today</p>
                    <p class="stat-card__value"><?php echo number_format($summary['today_clicks']); ?></p>
                    <p class="muted">A quick pulse of today’s performance.</p>
                </div>
            </section>

            <section class="card wide">
                <div class="section__header section__header--stacked">
                    <div>
                        <p class="eyebrow">Create link</p>
                        <h2>Branded short link</h2>
                        <p class="muted">Drop in your target URL, pick a custom slug, set an expiry date, and decide if it goes live now.</p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="btn ghost" type="submit">Log out</button>
                    </form>
                </div>
                <div class="create-layout">
                    <form class="grid" method="post">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="create_link">
                        <div>
                            <label>Destination URL</label>
                            <input name="target_url" type="url" required placeholder="https://example.com/landing">
                        </div>
                        <div>
                            <label>Internal title (optional)</label>
                            <input name="title" type="text" maxlength="80" placeholder="Winter promo landing">
                            <small>Helpful for identifying links in your dashboard.</small>
                        </div>
                        <div>
                            <label>Custom slug (optional)</label>
                            <input name="custom_slug" type="text" pattern="[A-Za-z0-9-]{3,30}" placeholder="brand-offer">
                            <small>Letters, numbers, and hyphens only. 3-30 length.</small>
                        </div>
                        <div>
                            <label>Expiration date (optional)</label>
                            <input name="expires_at" type="date">
                            <small>Link automatically disables after this date.</small>
                        </div>
                        <div>
                            <label>Activate instantly</label>
                            <label class="toggle"><input type="checkbox" name="is_active" checked> <span>Turn on after creation</span></label>
                        </div>
                        <div class="full">
                            <div class="form__actions">
                                <div class="pill pill--ghost">Duplicate slugs checked automatically</div>
                                <button class="btn primary" type="submit">Shorten link</button>
                            </div>
                        </div>
                    </form>
                    <div class="card mini-panel">
                        <p class="eyebrow">Workflow tips</p>
                        <h3>Keep campaigns tidy</h3>
                        <ul class="mini-panel__list">
                            <li>Name links by campaign so you can find them fast.</li>
                            <li>Use custom slugs for branded paths and clarity.</li>
                            <li>Schedule expirations for limited-time offers.</li>
                        </ul>
                        <div class="pill pill--ghost">Privacy-friendly click tracking</div>
                    </div>
                </div>
            </section>

            <section class="card wide" id="dashboard">
                <div class="section__header section__header--stacked">
                    <div>
                        <p class="eyebrow">Control center</p>
                        <h2>Your links & live stats</h2>
                        <p class="muted">Clean grid with status, clicks, today’s activity, and quick actions.</p>
                    </div>
                    <div class="section__chips">
                        <span class="pill pill--ghost">Instant copy</span>
                        <span class="pill pill--ghost">Inline edits</span>
                        <span class="pill pill--ghost">Safe delete</span>
                    </div>
                </div>

                <?php if (!$linkRows): ?>
                    <p class="muted">You haven’t created any links yet. Start with your first one.</p>
                <?php else: ?>
                    <div class="table">
                        <div class="table__filters">
                            <input type="search" id="link-search" placeholder="Search by title or short link...">
                        </div>
                        <div class="table__head">
                            <span>Short link</span>
                            <span>Title</span>
                            <span>Destination</span>
                            <span>Status</span>
                            <span>Clicks</span>
                            <span>Today</span>
                            <span>Actions</span>
                        </div>
                        <?php foreach ($linkRows as $link): ?>
                            <?php $shortUrl = $baseUrl . '/' . $link['slug']; ?>
                            <div class="table__row" data-search="<?php echo htmlspecialchars($shortUrl . ' ' . ($link['title'] ?? '') . ' ' . $link['target_url'], ENT_QUOTES); ?>">
                                <div>
                                    <p class="table__title"><?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?></p>
                                    <button class="btn tiny copy" type="button" data-copy="<?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?>">Copy</button>
                                </div>
                                <span class="truncate" title="<?php echo htmlspecialchars($link['title'] ?? '—', ENT_QUOTES); ?>"><?php echo htmlspecialchars($link['title'] ?? '—', ENT_QUOTES); ?></span>
                                <span class="truncate" title="<?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?></span>
                                <span class="badge <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'success' : 'warning'; ?>">
                                    <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'Active' : 'Paused'; ?>
                                </span>
                                <span><?php echo number_format((int) $link['clicks']); ?></span>
                                <span><?php echo number_format((int) $link['today_clicks']); ?></span>
                                <div class="actions">
                                    <button class="btn tiny secondary edit-toggle" type="button" data-target="edit-<?php echo (int) $link['id']; ?>">Edit</button>
                                    <form method="post">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="toggle_link">
                                        <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                        <button class="btn tiny ghost" type="submit"><?php echo (int) $link['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this link?');">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                        <button class="btn tiny danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <div class="edit-row" id="edit-<?php echo (int) $link['id']; ?>">
                                <form class="grid" method="post">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="edit_link">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                    <div>
                                        <label>Title</label>
                                        <input name="title" type="text" maxlength="80" value="<?php echo htmlspecialchars($link['title'] ?? '', ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>Short link</label>
                                        <input name="custom_slug" type="text" pattern="[A-Za-z0-9-]{3,30}" value="<?php echo htmlspecialchars($link['slug'], ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>Destination</label>
                                        <input name="target_url" type="url" required value="<?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>Expiration</label>
                                        <input name="expires_at" type="date" value="<?php echo $link['expires_at'] ? htmlspecialchars($link['expires_at'], ENT_QUOTES) : ''; ?>">
                                    </div>
                                    <div>
                                        <label>Status</label>
                                        <label class="toggle"><input type="checkbox" name="is_active" <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'checked' : ''; ?>> <span>Enable link</span></label>
                                    </div>
                                    <div class="full">
                                        <button class="btn primary" type="submit">Save changes</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($ads): ?>
                        <div class="ads ads--dashboard">
                            <?php foreach (array_slice($ads, 0, 4) as $ad): ?>
                                <a class="ad-card" href="<?php echo htmlspecialchars($ad['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                                    <p class="ad-eyebrow"><?php echo htmlspecialchars($ad['tag'], ENT_QUOTES); ?></p>
                                    <h3><?php echo htmlspecialchars($ad['title'], ENT_QUOTES); ?></h3>
                                    <p><?php echo htmlspecialchars($ad['description'], ENT_QUOTES); ?></p>
                                    <span>Learn more →</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card wide callout">
                <div>
                    <p class="eyebrow">Free forever</p>
                    <h2>Create an account to unlock the full dashboard</h2>
                    <p class="muted">Sign up to control your links, view analytics, and disable or expire them anytime.</p>
                    <a class="btn primary" href="#create">Get started</a>
                </div>
                <ul>
                    <li>Secure auth with encrypted passwords</li>
                    <li>Live click stats with device and referrer insights</li>
                    <li>Expire or pause links instantly</li>
                </ul>
            </section>
        <?php endif; ?>

        <section class="section" id="features">
            <div class="section__header section__header--stacked">
                <div>
                    <p class="eyebrow">Features</p>
                    <h2>Everything you need to run links in one place</h2>
                    <p class="muted">From creation to performance monitoring, every control is arranged cleanly.</p>
                </div>
                <div class="section__chips">
                    <span class="pill pill--ghost">Custom slugs</span>
                    <span class="pill pill--ghost">Live analytics</span>
                    <span class="pill pill--ghost">Secure toggles</span>
                </div>
            </div>
            <div class="feature-grid">
                <div class="card feature">
                    <span class="feature__icon">🔗</span>
                    <h3>Branded links</h3>
                    <p>Pick a short path that matches your campaign and boost trust with clear status tags.</p>
                </div>
                <div class="card feature">
                    <span class="feature__icon">📈</span>
                    <h3>Instant metrics</h3>
                    <p>See total clicks and today’s activity at a glance in a searchable grid.</p>
                </div>
                <div class="card feature">
                    <span class="feature__icon">🛡️</span>
                    <h3>Full control</h3>
                    <p>Disable, delete, or edit links safely with instant confirmations.</p>
                </div>
                <div class="card feature">
                    <span class="feature__icon">⚡</span>
                    <h3>Ready to ship</h3>
                    <p>Runs on PHP + SQLite only—no paid services or complex setup.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>Open-source Bitly alternative built with PHP, HTML, CSS, and JavaScript.</p>
    </footer>

    <script src="/assets/app.js"></script>
    <script src="/assets/external.js"></script>
</body>
</html>
<?php

function initializeDatabase(): PDO
{
    if (!is_dir(dirname(DB_FILE))) {
        mkdir(dirname(DB_FILE), 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        target_url TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        title TEXT NULL,
        expires_at TEXT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS clicks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        link_id INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        referrer TEXT,
        user_agent TEXT,
        ip_hash TEXT,
        FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clicks_link ON clicks(link_id)');
    ensureLinkColumns($pdo);

    return $pdo;
}

function handleRedirect(PDO $pdo, string $slug): void
{
    $slug = trim($slug);
    if ($slug === '') {
        showNotFound();
    }

    $stmt = $pdo->prepare('SELECT * FROM links WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link || (int) $link['is_active'] !== 1 || isExpired($link['expires_at'])) {
        showNotFound();
    }

    recordClick($pdo, (int) $link['id']);

    header('Location: ' . $link['target_url'], true, 302);
}

function handlePost(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'register':
            handleRegister($pdo);
            break;
        case 'login':
            handleLogin($pdo);
            break;
        case 'logout':
            session_destroy();
            session_start();
            addFlash('success', 'You have been logged out.');
            break;
        case 'create_link':
            requireAuth();
            handleCreateLink($pdo);
            break;
        case 'toggle_link':
            requireAuth();
            handleToggleLink($pdo);
            break;
        case 'delete_link':
            requireAuth();
            handleDeleteLink($pdo);
            break;
        case 'edit_link':
            requireAuth();
            handleEditLink($pdo);
            break;
        default:
            addFlash('error', 'Unknown request.');
    }
}

function handleRegister(PDO $pdo): void
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        addFlash('error', 'Please enter a valid email.');
        return;
    }

    if (strlen($password) < 8) {
        addFlash('error', 'Password must be at least 8 characters.');
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        addFlash('error', 'Email is already registered.');
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (:email, :hash, :created)');
    $insert->execute([
        'email' => $email,
        'hash' => $hash,
        'created' => date('c'),
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    addFlash('success', 'Account created and logged in.');
}

function handleLogin(PDO $pdo): void
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        addFlash('error', 'Invalid credentials.');
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    addFlash('success', 'Logged in successfully.');
}

function handleCreateLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $target = trim($_POST['target_url'] ?? '');
    $customSlug = trim($_POST['custom_slug'] ?? '');
    $expires = trim($_POST['expires_at'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!filter_var($target, FILTER_VALIDATE_URL)) {
        addFlash('error', 'Destination URL is invalid.');
        return;
    }

    if ($customSlug !== '' && !preg_match('/^[A-Za-z0-9-]{3,30}$/', $customSlug)) {
        addFlash('error', 'Short link format is invalid.');
        return;
    }

    if ($expires !== '' && !DateTime::createFromFormat('Y-m-d', $expires)) {
        addFlash('error', 'Expiration date format is invalid.');
        return;
    }

    $slug = $customSlug !== '' ? strtolower($customSlug) : generateSlug($pdo);

    $insert = $pdo->prepare('INSERT INTO links (user_id, slug, target_url, is_active, expires_at, title, created_at) VALUES (:user_id, :slug, :target, :active, :expires, :title, :created)');

    try {
        $insert->execute([
            'user_id' => $userId,
            'slug' => $slug,
            'target' => $target,
            'active' => $isActive,
            'expires' => $expires !== '' ? $expires : null,
            'title' => $title !== '' ? $title : null,
            'created' => date('c'),
        ]);
    } catch (PDOException $e) {
        addFlash('error', 'Short link already in use, try another word.');
        return;
    }

    addFlash('success', 'Short link created: ' . getBaseUrl() . '/' . $slug);
}

function handleToggleLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $linkId = (int) ($_POST['link_id'] ?? 0);

    $link = fetchLinkOwned($pdo, $linkId, $userId);
    if (!$link) {
        addFlash('error', 'Link not found.');
        return;
    }

    $newState = (int) $link['is_active'] === 1 ? 0 : 1;
    $update = $pdo->prepare('UPDATE links SET is_active = :state WHERE id = :id');
    $update->execute(['state' => $newState, 'id' => $linkId]);

    addFlash('success', $newState === 1 ? 'Link enabled.' : 'Link disabled.');
}

function handleDeleteLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $linkId = (int) ($_POST['link_id'] ?? 0);

    $link = fetchLinkOwned($pdo, $linkId, $userId);
    if (!$link) {
        addFlash('error', 'Unable to find link to delete.');
        return;
    }

    $delete = $pdo->prepare('DELETE FROM links WHERE id = :id');
    $delete->execute(['id' => $linkId]);

    addFlash('success', 'Link deleted.');
}

function handleEditLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $linkId = (int) ($_POST['link_id'] ?? 0);
    $target = trim($_POST['target_url'] ?? '');
    $customSlug = trim($_POST['custom_slug'] ?? '');
    $expires = trim($_POST['expires_at'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $link = fetchLinkOwned($pdo, $linkId, $userId);
    if (!$link) {
        addFlash('error', 'Unable to find link to edit.');
        return;
    }

    if (!filter_var($target, FILTER_VALIDATE_URL)) {
        addFlash('error', 'Destination URL is invalid.');
        return;
    }

    if ($customSlug !== '' && !preg_match('/^[A-Za-z0-9-]{3,30}$/', $customSlug)) {
        addFlash('error', 'Short link format is invalid.');
        return;
    }

    if ($expires !== '' && !DateTime::createFromFormat('Y-m-d', $expires)) {
        addFlash('error', 'Expiration date format is invalid.');
        return;
    }

    $newSlug = $customSlug !== '' ? strtolower($customSlug) : $link['slug'];
    $update = $pdo->prepare('UPDATE links SET target_url = :target, slug = :slug, expires_at = :expires, title = :title, is_active = :active WHERE id = :id AND user_id = :user_id');

    try {
        $update->execute([
            'target' => $target,
            'slug' => $newSlug,
            'expires' => $expires !== '' ? $expires : null,
            'title' => $title !== '' ? $title : null,
            'active' => $isActive,
            'id' => $linkId,
            'user_id' => $userId,
        ]);
    } catch (PDOException $e) {
        addFlash('error', 'Short link already in use. Choose another.');
        return;
    }

    addFlash('success', 'Link updated.');
}

function fetchLinkOwned(PDO $pdo, int $linkId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM links WHERE id = :id AND user_id = :user LIMIT 1');
    $stmt->execute(['id' => $linkId, 'user' => $userId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    return $link ?: null;
}

function fetchLinks(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT l.*, COUNT(c.id) AS clicks, SUM(CASE WHEN date(c.created_at) = date("now") THEN 1 ELSE 0 END) AS today_clicks
        FROM links l
        LEFT JOIN clicks c ON c.link_id = l.id
        WHERE l.user_id = :user_id
        GROUP BY l.id
        ORDER BY l.created_at DESC');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSummaryStats(PDO $pdo, ?int $userId = null): array
{
    $scope = $userId !== null ? 'WHERE user_id = :uid' : '';
    $bindings = $userId !== null ? ['uid' => $userId] : [];

    $totalLinksStmt = $pdo->prepare('SELECT COUNT(*) FROM links ' . $scope);
    $totalLinksStmt->execute($bindings);

    $clicksStmt = $pdo->prepare('SELECT COUNT(*) FROM clicks ' . ($userId !== null ? 'WHERE link_id IN (SELECT id FROM links WHERE user_id = :uid)' : ''));
    $clicksStmt->execute($bindings);

    $todayStmt = $pdo->prepare('SELECT COUNT(*) FROM clicks WHERE date(created_at) = date("now") ' . ($userId !== null ? 'AND link_id IN (SELECT id FROM links WHERE user_id = :uid)' : ''));
    $todayStmt->execute($bindings);

    return [
        'total_links' => (int) $totalLinksStmt->fetchColumn(),
        'total_clicks' => (int) $clicksStmt->fetchColumn(),
        'today_clicks' => (int) $todayStmt->fetchColumn(),
    ];
}

function getCurrentUser(PDO $pdo): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function generateSlug(PDO $pdo, int $length = 6): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $slug = strtolower($slug);

        $stmt = $pdo->prepare('SELECT 1 FROM links WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $exists = $stmt->fetchColumn();
    } while ($exists);

    return $slug;
}

function recordClick(PDO $pdo, int $linkId): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $ipHash = hash('sha256', $ip . (getenv('IP_SALT') ?: IP_SALT));

    $stmt = $pdo->prepare('INSERT INTO clicks (link_id, created_at, referrer, user_agent, ip_hash) VALUES (:link, :created, :referrer, :ua, :ip_hash)');
    $stmt->execute([
        'link' => $linkId,
        'created' => date('c'),
        'referrer' => $referrer,
        'ua' => $userAgent,
        'ip_hash' => $ipHash,
    ]);
}

function isExpired($expiresAt): bool
{
    if ($expiresAt === null || $expiresAt === '') {
        return false;
    }

    return strtotime($expiresAt . ' 23:59:59') < time();
}

function addFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function consumeFlash(): array
{
    $flash = $_SESSION['flash'] ?? ['success' => [], 'error' => []];
    unset($_SESSION['flash']);

    return array_merge(['success' => [], 'error' => []], $flash);
}

function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        addFlash('error', 'Please log in first.');
        redirectHome();
    }
}

function redirectHome(): void
{
    header('Location: /');
    exit;
}

function getBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $parsedHost = parse_url($scheme . $hostHeader, PHP_URL_HOST);
    $port = parse_url($scheme . $hostHeader, PHP_URL_PORT);

    $host = filter_var($parsedHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ?: 'localhost';
    $portPart = ($port !== null && !in_array([$scheme, (int) $port], [
        ['http://', 80],
        ['https://', 443],
    ], true)) ? ':' . $port : '';

    return $scheme . $host . $portPart;
}

function showNotFound(): void
{
    http_response_code(404);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Link not found</title><style>body{font-family:Cairo,system-ui;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;padding:24px;}a{color:#7dd3fc;}</style></head><body><div><h1>Link unavailable</h1><p>The requested link does not exist or has been disabled.</p><a href=\"/\">Go back home</a></div></body></html>';
    exit;
}

function ensureLinkColumns(PDO $pdo): void
{
    $columns = $pdo->query("PRAGMA table_info('links')")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'name');

    if (!in_array('title', $names, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN title TEXT NULL');
    }

    if (!in_array('expires_at', $names, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN expires_at TEXT NULL');
    }

    if (!in_array('is_active', $names, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
    }

    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS links_slug_unique ON links(slug)');
}

function loadAds(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [];
    }

    return array_slice($data, 0, 9);
}
?>
