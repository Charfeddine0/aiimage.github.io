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
        addFlash('error', 'تعذر التحقق من الحماية. أعد المحاولة.');
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

?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محول الروابط | منصة تقصير مجانية مثل Bitly</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="hero">
        <div class="hero__content">
            <p class="eyebrow">منصة مجانية بالكامل</p>
            <h1>منشئ روابط قصير شبيه بـ Bitly مع لوحة تحكم وإحصائيات</h1>
            <p class="lede">اصنع روابط قصيرة بعلامة مميزة، راقب النقرات، عطّل أو حدّد صلاحية الرابط، وكل ذلك بواجهة حديثة مدعومة بـ PHP وSQLite بدون أي تكاليف.</p>
            <div class="hero__actions">
                <a class="btn primary" href="#create">ابدأ مجاناً</a>
                <a class="btn ghost" href="#features">اكتشف المزايا</a>
            </div>
            <div class="hero__metrics">
                <div>
                    <p class="metric__label">إجمالي الروابط</p>
                    <p class="metric__value"><?php echo number_format($summary['total_links']); ?></p>
                </div>
                <div>
                    <p class="metric__label">إجمالي النقرات</p>
                    <p class="metric__value"><?php echo number_format($summary['total_clicks']); ?></p>
                </div>
                <div>
                    <p class="metric__label">نشاط اليوم</p>
                    <p class="metric__value"><?php echo number_format($summary['today_clicks']); ?></p>
                </div>
            </div>
            <?php if ($ads): ?>
                <div class="ads ads--inline">
                    <?php foreach (array_slice($ads, 0, 3) as $ad): ?>
                        <a class="ad-card" href="<?php echo htmlspecialchars($ad['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                            <p class="ad-eyebrow"><?php echo htmlspecialchars($ad['tag'], ENT_QUOTES); ?></p>
                            <h3><?php echo htmlspecialchars($ad['title'], ENT_QUOTES); ?></h3>
                            <p><?php echo htmlspecialchars($ad['description'], ENT_QUOTES); ?></p>
                            <span>اعرف المزيد →</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero__panel">
            <div class="card">
                <p class="card__title">دخول سريع</p>
                <form method="post" class="stacked">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="login">
                    <label>البريد الإلكتروني</label>
                    <input name="email" type="email" required placeholder="name@example.com">
                    <label>كلمة المرور</label>
                    <input name="password" type="password" required minlength="8" placeholder="••••••••">
                    <button class="btn primary" type="submit">تسجيل الدخول</button>
                </form>
                <div class="divider"><span>أو</span></div>
                <form method="post" class="stacked">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="register">
                    <label>إنشاء حساب مجاني</label>
                    <input name="email" type="email" required placeholder="name@example.com">
                    <input name="password" type="password" required minlength="8" placeholder="كلمة مرور قوية">
                    <button class="btn secondary" type="submit">إنشاء حساب</button>
                </form>
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
            <section class="card wide">
                <div class="section__header">
                    <div>
                        <p class="eyebrow">مرحبا، <?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES); ?></p>
                        <h2>أنشئ رابطًا قصيرًا</h2>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="btn ghost" type="submit">تسجيل الخروج</button>
                    </form>
                </div>
                <form class="grid" method="post">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                    <input type="hidden" name="action" value="create_link">
                    <div>
                        <label>الرابط الأصلي</label>
                        <input name="target_url" type="url" required placeholder="https://example.com/landing">
                    </div>
                    <div>
                        <label>عنوان داخلي (اختياري)</label>
                        <input name="title" type="text" maxlength="80" placeholder="حملة العروض الشتوية">
                        <small>يساعدك على تمييز الرابط في لوحة التحكم.</small>
                    </div>
                    <div>
                        <label>الرابط القصير (اختياري)</label>
                        <input name="custom_slug" type="text" pattern="[A-Za-z0-9-]{3,30}" placeholder="brand-offer">
                        <small>حروف وأرقام وشرطة فقط، طول 3-30.</small>
                    </div>
                    <div>
                        <label>تاريخ الانتهاء (اختياري)</label>
                        <input name="expires_at" type="date">
                        <small>يتم تعطيل الرابط تلقائياً بعد التاريخ.</small>
                    </div>
                    <div>
                        <label>تفعيل الرابط فوراً</label>
                        <label class="toggle"><input type="checkbox" name="is_active" checked> <span>تشغيل الرابط بعد الإنشاء</span></label>
                    </div>
                    <div class="full">
                        <button class="btn primary" type="submit">قصّر الرابط</button>
                    </div>
                </form>
            </section>

            <section class="card wide" id="dashboard">
                <div class="section__header">
                    <div>
                        <p class="eyebrow">لوحة التحكم</p>
                        <h2>روابطك وإحصائياتها</h2>
                    </div>
                    <p class="muted">انسخ، عطّل، احذف أو راقب أداء كل رابط.</p>
                </div>

                <?php if (!$linkRows): ?>
                    <p class="muted">لم تنشئ روابط بعد. ابدأ بصنع رابطك الأول.</p>
                <?php else: ?>
                    <div class="table">
                        <div class="table__filters">
                            <input type="search" id="link-search" placeholder="ابحث بالعنوان أو الرابط القصير...">
                        </div>
                        <div class="table__head">
                            <span>الرابط القصير</span>
                            <span>العنوان</span>
                            <span>الهدف</span>
                            <span>الحالة</span>
                            <span>النقرات</span>
                            <span>نشاط اليوم</span>
                            <span>إجراءات</span>
                        </div>
                        <?php foreach ($linkRows as $link): ?>
                            <?php $shortUrl = $baseUrl . '/' . $link['slug']; ?>
                            <div class="table__row" data-search="<?php echo htmlspecialchars($shortUrl . ' ' . ($link['title'] ?? '') . ' ' . $link['target_url'], ENT_QUOTES); ?>">
                                <div>
                                    <p class="table__title"><?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?></p>
                                    <button class="btn tiny copy" type="button" data-copy="<?php echo htmlspecialchars($shortUrl, ENT_QUOTES); ?>">نسخ</button>
                                </div>
                                <span class="truncate" title="<?php echo htmlspecialchars($link['title'] ?? '—', ENT_QUOTES); ?>"><?php echo htmlspecialchars($link['title'] ?? '—', ENT_QUOTES); ?></span>
                                <span class="truncate" title="<?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?></span>
                                <span class="badge <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'success' : 'warning'; ?>">
                                    <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'نشط' : 'موقوف'; ?>
                                </span>
                                <span><?php echo number_format((int) $link['clicks']); ?></span>
                                <span><?php echo number_format((int) $link['today_clicks']); ?></span>
                                <div class="actions">
                                    <button class="btn tiny secondary edit-toggle" type="button" data-target="edit-<?php echo (int) $link['id']; ?>">تحرير</button>
                                    <form method="post">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="toggle_link">
                                        <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                        <button class="btn tiny ghost" type="submit"><?php echo (int) $link['is_active'] === 1 ? 'تعطيل' : 'تفعيل'; ?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('هل أنت متأكد من حذف الرابط؟');">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                        <button class="btn tiny danger" type="submit">حذف</button>
                                    </form>
                                </div>
                            </div>
                            <div class="edit-row" id="edit-<?php echo (int) $link['id']; ?>">
                                <form class="grid" method="post">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                    <input type="hidden" name="action" value="edit_link">
                                    <input type="hidden" name="link_id" value="<?php echo (int) $link['id']; ?>">
                                    <div>
                                        <label>العنوان</label>
                                        <input name="title" type="text" maxlength="80" value="<?php echo htmlspecialchars($link['title'] ?? '', ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>الرابط القصير</label>
                                        <input name="custom_slug" type="text" pattern="[A-Za-z0-9-]{3,30}" value="<?php echo htmlspecialchars($link['slug'], ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>الرابط الأصلي</label>
                                        <input name="target_url" type="url" required value="<?php echo htmlspecialchars($link['target_url'], ENT_QUOTES); ?>">
                                    </div>
                                    <div>
                                        <label>تاريخ الانتهاء</label>
                                        <input name="expires_at" type="date" value="<?php echo $link['expires_at'] ? htmlspecialchars($link['expires_at'], ENT_QUOTES) : ''; ?>">
                                    </div>
                                    <div>
                                        <label>الحالة</label>
                                        <label class="toggle"><input type="checkbox" name="is_active" <?php echo ((int) $link['is_active'] === 1 && !isExpired($link['expires_at'])) ? 'checked' : ''; ?>> <span>تفعيل الرابط</span></label>
                                    </div>
                                    <div class="full">
                                        <button class="btn primary" type="submit">حفظ التعديلات</button>
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
                                    <span>اعرف المزيد →</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card wide callout">
                <div>
                    <p class="eyebrow">مجاناً للأبد</p>
                    <h2>سجل لتحصل على لوحة تحكم كاملة</h2>
                    <p class="muted">إنشاء حساب يمكّنك من التحكم في روابطك، مشاهدة التحليلات، وتعطيل الروابط عند الحاجة.</p>
                    <a class="btn primary" href="#">ابدأ الآن</a>
                </div>
                <ul>
                    <li>مصادقة آمنة بكلمات مرور مشفرة</li>
                    <li>إحصائيات فورية للنقرات مع تتبع الجهاز والمصدر</li>
                    <li>تحديد صلاحية الرابط أو إيقافه بنقرة</li>
                </ul>
            </section>
        <?php endif; ?>

        <section class="grid features" id="features">
            <div class="card feature">
                <h3>تقصير مخصص</h3>
                <p>أضف كلمات مفتاحية وعلامة تجارية خاصة بك للرابط القصير لتزيد الثقة وتحسّن معدل النقر.</p>
            </div>
            <div class="card feature">
                <h3>إحصائيات فورية</h3>
                <p>نحسب النقرات، المصدر والمتصفح مع حفظ الخصوصية عبر تشفير الـ IP.</p>
            </div>
            <div class="card feature">
                <h3>التحكم الكامل</h3>
                <p>عطّل الروابط مؤقتاً، حدّد تاريخ انتهاء أو احذفها نهائياً في أي وقت.</p>
            </div>
            <div class="card feature">
                <h3>جاهز للنشر</h3>
                <p>يعمل عبر PHP + SQLite فقط. لا حاجة لخدمات مدفوعة أو إعدادات معقدة.</p>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>مشروع مفتوح المصدر لبناء بديل مجاني لـ Bitly باستخدام PHP وHTML وCSS وJavaScript.</p>
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
            addFlash('success', 'تم تسجيل الخروج بنجاح.');
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
            addFlash('error', 'طلب غير معروف.');
    }
}

function handleRegister(PDO $pdo): void
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        addFlash('error', 'يرجى إدخال بريد إلكتروني صحيح.');
        return;
    }

    if (strlen($password) < 8) {
        addFlash('error', 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.');
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        addFlash('error', 'البريد الإلكتروني مسجل بالفعل.');
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (:email, :hash, :created)');
    $insert->execute([
        'email' => $email,
        'hash' => $hash,
        'created' => date('c'),
    ]);

    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    addFlash('success', 'تم إنشاء الحساب وتسجيل الدخول.');
}

function handleLogin(PDO $pdo): void
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        addFlash('error', 'بيانات الدخول غير صحيحة.');
        return;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    addFlash('success', 'تم تسجيل الدخول بنجاح.');
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
        addFlash('error', 'رابط الهدف غير صالح.');
        return;
    }

    if ($customSlug !== '' && !preg_match('/^[A-Za-z0-9-]{3,30}$/', $customSlug)) {
        addFlash('error', 'صيغة الرابط القصير غير صالحة.');
        return;
    }

    if ($expires !== '' && !DateTime::createFromFormat('Y-m-d', $expires)) {
        addFlash('error', 'صيغة تاريخ الانتهاء غير صالحة.');
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
        addFlash('error', 'الرابط القصير مستخدم بالفعل، جرّب كلمة أخرى.');
        return;
    }

    addFlash('success', 'تم إنشاء الرابط القصير بنجاح: ' . getBaseUrl() . '/' . $slug);
}

function handleToggleLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $linkId = (int) ($_POST['link_id'] ?? 0);

    $link = fetchLinkOwned($pdo, $linkId, $userId);
    if (!$link) {
        addFlash('error', 'تعذر العثور على الرابط.');
        return;
    }

    $newState = (int) $link['is_active'] === 1 ? 0 : 1;
    $update = $pdo->prepare('UPDATE links SET is_active = :state WHERE id = :id');
    $update->execute(['state' => $newState, 'id' => $linkId]);

    addFlash('success', $newState === 1 ? 'تم تفعيل الرابط.' : 'تم تعطيل الرابط.');
}

function handleDeleteLink(PDO $pdo): void
{
    $userId = (int) $_SESSION['user_id'];
    $linkId = (int) ($_POST['link_id'] ?? 0);

    $link = fetchLinkOwned($pdo, $linkId, $userId);
    if (!$link) {
        addFlash('error', 'تعذر العثور على الرابط لحذفه.');
        return;
    }

    $delete = $pdo->prepare('DELETE FROM links WHERE id = :id');
    $delete->execute(['id' => $linkId]);

    addFlash('success', 'تم حذف الرابط بنجاح.');
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
        addFlash('error', 'تعذر العثور على الرابط لتعديله.');
        return;
    }

    if (!filter_var($target, FILTER_VALIDATE_URL)) {
        addFlash('error', 'رابط الهدف غير صالح.');
        return;
    }

    if ($customSlug !== '' && !preg_match('/^[A-Za-z0-9-]{3,30}$/', $customSlug)) {
        addFlash('error', 'صيغة الرابط القصير غير صالحة.');
        return;
    }

    if ($expires !== '' && !DateTime::createFromFormat('Y-m-d', $expires)) {
        addFlash('error', 'صيغة تاريخ الانتهاء غير صالحة.');
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
        addFlash('error', 'الرابط القصير مستخدم بالفعل. اختر كلمة مختلفة.');
        return;
    }

    addFlash('success', 'تم تحديث الرابط بنجاح.');
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
        addFlash('error', 'يجب تسجيل الدخول أولاً.');
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
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . $host;
}

function showNotFound(): void
{
    http_response_code(404);
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>الرابط غير موجود</title><style>body{font-family:Cairo,system-ui;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;padding:24px;}a{color:#7dd3fc;}</style></head><body><div><h1>الرابط غير متاح</h1><p>الرابط المطلوب غير موجود أو تم تعطيله.</p><a href="/">العودة للصفحة الرئيسية</a></div></body></html>';
    exit;
}

function ensureLinkColumns(PDO $pdo): void
{
    $columns = $pdo->query("PRAGMA table_info('links')")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'name');

    if (!in_array('title', $names, true)) {
        $pdo->exec('ALTER TABLE links ADD COLUMN title TEXT NULL');
    }
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
