<?php
// student/library.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();

// 1. Strict Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header("Location: ../index.php?error=notactive");
    exit;
}

// 2. Handle Document Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $removeId = (int)$_POST['document_id'];
    $delStmt = $pdo->prepare("DELETE FROM saved_documents WHERE user_id = ? AND document_id = ?");
    $delStmt->execute([$student['id'], $removeId]);
    
    flash_set('success', 'Document successfully removed from your personal library.');
    header("Location: library.php");
    exit;
}

// 3. Handle Library Search & Sorting Features
$searchQuery = trim($_GET['search'] ?? '');
$sortOrder = $_GET['sort'] ?? 'newest';

$params = [$student['id']];
$whereSql = "WHERE sd.user_id = ? AND d.status = 'published'";

// Apply Search Filter within the Library
if (!empty($searchQuery)) {
    $whereSql .= " AND (d.title LIKE ? OR d.authors LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Apply Sorting
$orderSql = "ORDER BY sd.saved_at DESC"; // Default: Recently Saved
if ($sortOrder === 'oldest') $orderSql = "ORDER BY sd.saved_at ASC";
if ($sortOrder === 'title_a_z') $orderSql = "ORDER BY d.title ASC";
if ($sortOrder === 'year_new') $orderSql = "ORDER BY d.pub_year DESC";

// 4. Fetch the Curated Collection
$libStmt = $pdo->prepare("
    SELECT d.*, c.name as category_name, sd.saved_at 
    FROM saved_documents sd
    INNER JOIN documents d ON sd.document_id = d.id
    LEFT JOIN categories c ON d.category_id = c.id
    $whereSql
    $orderSql
");
$libStmt->execute($params);
$savedDocs = $libStmt->fetchAll();

// Get total count (ignoring search) for the stats panel
$totalCountStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_documents WHERE user_id = ?");
$totalCountStmt->execute([$student['id']]);
$absoluteTotal = $totalCountStmt->fetchColumn();

// 5. Fetch Dynamic Notifications & Track Latest ID
try {
    $notifStmt = $pdo->query("
        SELECT id, title, uploaded_by, created_at 
        FROM documents 
        WHERE status = 'published' 
        ORDER BY id DESC LIMIT 6
    ");
    $notifications = $notifStmt->fetchAll();
    // Grab the most recent document's ID to drive the notification animation
    $latestNotifId = count($notifications) > 0 ? $notifications[0]['id'] : 0;
} catch (PDOException $e) {
    $notifications = [];
    $latestNotifId = 0;
}

$success = flash_get('success');

// 6. UI helpers: removable filter chips + search-term highlighting
function lib_chip_url($params, $drop) {
    unset($params[$drop]);
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    if (isset($params['sort']) && $params['sort'] === 'newest') unset($params['sort']);
    return 'library.php' . (count($params) ? '?' . http_build_query($params) : '');
}

function lib_hl($text, $term) {
    $term = trim((string)$term);
    if ($term === '') return e($text);
    $parts = preg_split('/(' . preg_quote($term, '/') . ')/iu', (string)$text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return e($text);
    $out = '';
    foreach ($parts as $i => $part) {
        $out .= ($i % 2 === 1) ? '<mark>' . e($part) . '</mark>' : e($part);
    }
    return $out;
}

$baseParams = ['search' => $searchQuery, 'sort' => $sortOrder];
$sortLabels = ['newest' => 'Recently saved', 'oldest' => 'Oldest saved', 'title_a_z' => 'Title A–Z', 'year_new' => 'Year (newest)'];

$chips = [];
if ($searchQuery !== '')      $chips[] = ['label' => 'Search: ' . $searchQuery, 'drop' => 'search'];
if ($sortOrder !== 'newest')  $chips[] = ['label' => $sortLabels[$sortOrder] ?? $sortOrder, 'drop' => 'sort'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Library · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>document.documentElement.classList.add('js');</script>
    <style>
        :root {
            --navy: #0d0a4a;
            --primary: #2a1fc4;
            --primary-hover: #1f16a0;
            --primary-light: #e0e7ff;
            --blue-accent: #3b6cf6;
            --violet: #7c3aed;
            --bg-light: #f5f6ff;
            --white: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-light: #e6e8f5;
            --accent-orange: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
            --grad: linear-gradient(90deg, #1a01b6 0%, #1a01b6 100%);
            --font-family: 'Inter', sans-serif;
            --shadow-sm: 0 2px 8px rgba(40, 30, 140, 0.05);
            --shadow-md: 0 14px 30px -12px rgba(40, 30, 140, 0.25);
            --shadow-lg: 0 30px 60px -20px rgba(40, 30, 140, 0.35);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-family); background: var(--bg-light); color: var(--text-dark); overflow-x: hidden; display: flex; flex-direction: column; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        ::selection { background: #c7d2fe; }

        /* ---------- Animations ---------- */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(26px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-14px) rotate(4deg); } }
        @keyframes pulseGlow { 0%, 100% { opacity: 0.75; transform: scale(1); } 50% { opacity: 1; transform: scale(1.12); } }
        @keyframes ringPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.06); } }
        @keyframes sway { 0%, 100% { transform: rotate(24deg) translateX(0); } 50% { transform: rotate(24deg) translateX(18px); } }
        @keyframes popIn { from { opacity: 0; transform: scale(0.92) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes ring { 0% { transform: rotate(0); } 10% { transform: rotate(15deg); } 20% { transform: rotate(-10deg); } 30% { transform: rotate(5deg); } 40% { transform: rotate(-5deg); } 50%, 100% { transform: rotate(0); } }

        .js .reveal { opacity: 0; }
        .js .reveal.in { opacity: 1; animation: fadeUp 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms) backwards; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .js .reveal { opacity: 1; }
        }

        /* ---------- Top navigation ---------- */
        .navbar { position: sticky; top: 0; z-index: 100; height: 74px; padding: 0 4rem; display: flex; align-items: center; justify-content: space-between; background: rgba(13, 10, 74, 0.94); backdrop-filter: blur(12px); color: var(--white); box-shadow: 0 6px 24px rgba(13, 10, 74, 0.25); transition: box-shadow 0.3s; }
        .navbar.scrolled { box-shadow: 0 12px 34px rgba(13, 10, 74, 0.45); }
        .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; }
        .nav-brand img { width: 42px; height: 42px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; transition: transform 0.4s; }
        .nav-brand:hover img { transform: rotate(360deg); }
        .nav-brand span { font-weight: 800; font-size: 1.25rem; letter-spacing: -0.5px; }
        .nav-brand span em { font-style: normal; background: linear-gradient(90deg, #7f9bff, #b88cff); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .nav-links { display: flex; gap: 0.35rem; align-items: center; }
        .nav-links a { color: rgba(255,255,255,0.72); text-decoration: none; font-size: 0.92rem; font-weight: 600; padding: 0.55rem 1.1rem; border-radius: 999px; transition: background 0.2s, color 0.2s; }
        .nav-links a:hover { color: #fff; background: rgba(255,255,255,0.08); }
        .nav-links a.active { color: #fff; background: rgba(255,255,255,0.15); }
        .nav-user { display: flex; align-items: center; gap: 0.6rem; position: relative; }
        .btn-icon-nav { background: none; border: none; color: #fff; cursor: pointer; opacity: 0.85; transition: opacity 0.2s, background 0.2s, transform 0.2s; position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; text-decoration: none; }
        .btn-icon-nav:hover { opacity: 1; background: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .btn-icon-nav.has-unread svg { animation: ring 2s ease-in-out infinite; transform-origin: top center; }
        .bell-dot { position: absolute; top: 8px; right: 9px; width: 9px; height: 9px; background: var(--danger); border-radius: 50%; border: 2px solid var(--navy); display: none; }
        .btn-icon-nav.has-unread .bell-dot { display: block; }

        .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 14px; background: var(--white); border-radius: 16px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-light); display: none; flex-direction: column; z-index: 200; overflow: hidden; color: var(--text-dark); opacity: 0; transform: translateY(-10px); transition: all 0.2s ease; }
        .dropdown-menu.active { display: flex; opacity: 1; transform: translateY(0); animation: popIn 0.25s ease both; }
        #notifDropdown { width: 370px; }
        .notif-header { padding: 1rem 1.25rem; font-weight: 800; border-bottom: 1px solid var(--border-light); font-size: 0.95rem; color: var(--navy); }
        .notif-body { max-height: 400px; overflow-y: auto; }
        .notif-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light); display: flex; gap: 14px; text-decoration: none; color: var(--text-dark); transition: background 0.2s; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f7f8ff; }
        .notif-icon { width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-icon.approved { background: #dcfce7; color: #16a34a; }
        .notif-text { font-size: 0.85rem; line-height: 1.45; }
        .notif-time { font-size: 0.7rem; color: var(--text-muted); margin-top: 6px; display: block; font-weight: 600; }
        .notif-empty { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
        #profileDropdown { width: 250px; }
        .profile-header { padding: 1.25rem; border-bottom: 1px solid var(--border-light); display: flex; flex-direction: column; gap: 4px; background: linear-gradient(135deg, #f7f8ff, #fff); }
        .dd-name { font-weight: 800; font-size: 1rem; color: var(--navy); }
        .profile-role { font-size: 0.72rem; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; }
        .profile-links { display: flex; flex-direction: column; padding: 0.5rem; }
        .profile-link { padding: 0.75rem 1rem; text-decoration: none; color: var(--text-dark); font-size: 0.9rem; font-weight: 600; border-radius: 10px; transition: background 0.2s; display: flex; align-items: center; gap: 10px; }
        .profile-link:hover { background: #f1f2ff; color: var(--primary); }
        .profile-link.logout { color: var(--danger); margin-top: 0.4rem; }
        .profile-link.logout:hover { background: #fef2f2; color: var(--danger); }
        .btn-avatar { background: none; border: none; padding: 0; cursor: pointer; margin-left: 0.3rem; transition: transform 0.2s; }
        .btn-avatar:hover { transform: scale(1.06); }
        .avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #e0e7ff; border: 2px solid rgba(255,255,255,0.35); display: block; }
        .avatar-initials { width: 42px; height: 42px; border-radius: 50%; background: var(--grad); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; border: 2px solid rgba(255,255,255,0.35); color: #fff; }

        /* ---------- Page hero ---------- */
        .page-hero { position: relative; overflow: hidden; color: #fff; padding: 3.2rem 4rem 3.4rem;
            background: linear-gradient(100deg, rgba(10, 6, 70, 0.94) 0%, rgba(28, 18, 130, 0.82) 55%, rgba(60, 40, 170, 0.66) 100%), url('../assets/backgroundaclc.png') center 35% / cover no-repeat; }
        .page-hero-inner { position: relative; z-index: 2; max-width: 1300px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap; }
        .crumbs { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; opacity: 0.85; margin-bottom: 0.9rem; }
        .crumbs a { color: #fff; text-decoration: none; opacity: 0.8; transition: opacity 0.2s; }
        .crumbs a:hover { opacity: 1; }
        .page-hero h1 { font-size: clamp(1.9rem, 3.6vw, 2.9rem); font-weight: 800; letter-spacing: -1px; display: flex; align-items: center; gap: 14px; text-shadow: 0 4px 14px rgba(0,0,0,0.25); }
        .title-ico { width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.28); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); flex-shrink: 0; }
        .page-hero p { opacity: 0.92; margin-top: 0.6rem; max-width: 560px; line-height: 1.55; font-size: 1.02rem; }
        .hero-stat-pill { display: inline-flex; align-items: center; gap: 8px; margin-top: 1.1rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.28); padding: 7px 18px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .hero-stat-pill strong { font-size: 1.15rem; color: #fcd34d; }

        .hero-bg { position: absolute; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
        .hero-bg .glow { position: absolute; border-radius: 50%; filter: blur(60px); animation: pulseGlow 8s ease-in-out infinite; }
        .hero-bg .g1 { width: 520px; height: 520px; top: -250px; right: -130px; background: radial-gradient(circle, rgba(124, 58, 237, 0.55), transparent 70%); }
        .hero-bg .g2 { width: 480px; height: 480px; bottom: -280px; left: -150px; background: radial-gradient(circle, rgba(59, 130, 246, 0.50), transparent 70%); animation-delay: -3s; }
        .hero-bg .ring { position: absolute; border-radius: 50%; border: 1.5px solid rgba(255, 255, 255, 0.13); animation: ringPulse 9s ease-in-out infinite; }
        .hero-bg .r1 { width: 440px; height: 440px; top: -200px; left: -130px; }
        .hero-bg .r2 { width: 310px; height: 310px; top: -130px; left: -60px; border-color: rgba(255, 255, 255, 0.08); animation-delay: -2s; }
        .hero-bg .r3 { width: 480px; height: 480px; bottom: -260px; right: -100px; animation-delay: -4s; }
        .hero-bg .r4 { width: 340px; height: 340px; bottom: -190px; right: -30px; border-color: rgba(255, 255, 255, 0.08); animation-delay: -6s; }
        .hero-bg .band { position: absolute; top: -60%; width: 80px; height: 260%; transform: rotate(24deg); background: linear-gradient(180deg, rgba(165, 180, 252, 0), rgba(165, 180, 252, 0.15), rgba(165, 180, 252, 0)); animation: sway 10s ease-in-out infinite; }
        .hero-bg .b1 { right: 17%; }
        .hero-bg .b2 { right: 12%; width: 26px; opacity: 0.8; animation-delay: -4s; }
        .hero-bg .b3 { left: 40%; width: 36px; opacity: 0.5; animation-delay: -7s; }
        .hero-bg .dots { position: absolute; background-image: radial-gradient(circle, currentColor 1.5px, transparent 1.6px); background-size: 22px 22px; }
        .hero-bg .d1 { top: 22px; left: 28px; width: 220px; height: 110px; color: rgba(255, 255, 255, 0.28); }
        .hero-bg .d2 { bottom: 18px; right: 28px; width: 240px; height: 110px; color: rgba(255, 255, 255, 0.20); }
        .hero-bg .deco-ico { position: absolute; width: 46px; height: 46px; color: rgba(255, 255, 255, 0.17); animation: float 7s ease-in-out infinite; }
        .hero-bg .i1 { top: 40%; left: 2.5%; }
        .hero-bg .i2 { bottom: 14%; left: 7%; animation-delay: -2s; }
        .hero-bg .i3 { top: 16%; right: 3.5%; animation-delay: -4s; }
        .hero-bg .i4 { bottom: 16%; right: 8%; animation-delay: -5s; }

        /* ---------- Alerts / toast ---------- */
        .alert { padding: 1rem 1.2rem 1rem 1.4rem; border-radius: 14px; font-size: 0.93rem; font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 1.6rem; box-shadow: var(--shadow-sm); background: #fff; animation: popIn 0.4s ease both; transition: opacity 0.4s, transform 0.4s; }
        .alert.hide { opacity: 0; transform: translateY(-8px); }
        .alert svg { flex-shrink: 0; }
        .alert > span { flex: 1; }
        .alert-close { background: none; border: none; font-size: 1.3rem; line-height: 1; color: inherit; opacity: 0.5; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; transition: opacity 0.2s, background 0.2s; }
        .alert-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .alert-success { color: #065f46; border: 1px solid #a7f3d0; border-left: 6px solid #10b981; }
        .alert-error { color: #991b1b; border: 1px solid #fecaca; border-left: 6px solid #ef4444; }
        .toast { position: fixed; bottom: 26px; left: 50%; transform: translate(-50%, 30px); background: var(--navy); color: #fff; padding: 0.8rem 1.4rem; border-radius: 999px; font-weight: 600; font-size: 0.88rem; opacity: 0; pointer-events: none; transition: all 0.35s cubic-bezier(0.2, 0.7, 0.2, 1); z-index: 2000; box-shadow: 0 14px 30px rgba(13, 10, 74, 0.4); }
        .toast.show { opacity: 1; transform: translate(-50%, 0); }

        /* ---------- Document cards ---------- */
        .doc-card { position: relative; overflow: hidden; background: #fff; border-radius: 20px; padding: 1.6rem; border: 1px solid var(--border-light); text-decoration: none; display: flex; flex-direction: column; box-shadow: var(--shadow-sm); transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s, opacity 0.35s; }
        .doc-card > * { position: relative; z-index: 1; }
        .doc-card::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); transform: scaleY(0); transform-origin: top; transition: transform 0.35s; z-index: 2; }
        .doc-card::after { content: ""; position: absolute; inset: 0; background: radial-gradient(380px circle at var(--mx, 50%) var(--my, 50%), rgba(99, 102, 241, 0.13), transparent 45%); opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 0; }
        .doc-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-md); border-color: #c7d2fe; }
        .doc-card:hover::before { transform: scaleY(1); }
        .doc-card::after { display: none; }
        .doc-card.removing { transform: scale(0.9); opacity: 0; }
        .doc-badges { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; }
        .badge-cat { background: var(--primary-light); color: var(--primary); padding: 5px 12px; border-radius: 999px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .badge-saved { color: var(--text-muted); font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .doc-title { font-size: 1.1rem; font-weight: 800; color: var(--navy); line-height: 1.4; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-decoration: none; transition: color 0.2s; }
        .doc-card:hover .doc-title, a.doc-title:hover { color: var(--primary); }
        .doc-authors { font-size: 0.82rem; color: var(--text-muted); font-weight: 500; margin-bottom: 1.4rem; display: flex; align-items: center; gap: 8px; flex: 1; }
        .doc-footer { padding-top: 1.1rem; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; margin-top: auto; gap: 10px; }
        .btn-read { font-size: 0.85rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 4px; transition: gap 0.2s; text-decoration: none; }
        .doc-card:hover .btn-read { gap: 9px; }
        .btn-remove { background: #fff; border: 1px solid var(--border-light); color: var(--text-muted); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; font-family: inherit; }
        .btn-remove:hover { color: var(--danger); background: #fef2f2; border-color: #fecaca; transform: translateY(-1px); }
        mark { background: #fde68a; color: inherit; border-radius: 4px; padding: 0 2px; }

        .empty-state { grid-column: 1 / -1; background: #fff; border: 1.5px dashed #c7cde0; border-radius: 22px; padding: 4.5rem 2rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: var(--shadow-sm); }
        .empty-icon { width: 84px; height: 84px; background: #eceeff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.4rem; color: var(--primary); animation: float 5s ease-in-out infinite; }
        .empty-state h3 { font-size: 1.3rem; font-weight: 800; color: var(--navy); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.6rem; max-width: 420px; line-height: 1.6; }
        .btn-gradient { display: inline-flex; align-items: center; gap: 8px; background: var(--grad); color: #fff; text-decoration: none; padding: 0.85rem 2rem; border-radius: 999px; font-weight: 700; font-size: 0.93rem; border: none; cursor: pointer; font-family: inherit; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.32); transition: transform 0.15s, box-shadow 0.15s; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.42); }
        .btn-soft { display: inline-flex; align-items: center; gap: 8px; background: #eceeff; color: var(--primary); text-decoration: none; padding: 0.85rem 2rem; border-radius: 999px; font-weight: 700; font-size: 0.93rem; transition: background 0.2s; }
        .btn-soft:hover { background: #dfe3ff; }

        .site-footer { text-align: center; padding: 1.5rem 1rem 2.5rem; font-size: 0.82rem; color: var(--text-muted); }

        /* ---------- Library layout ---------- */
        .library-container { padding: 2.5rem 4rem 3rem; max-width: 1600px; margin: 0 auto; width: 100%; flex: 1; display: flex; flex-direction: column; gap: 1.2rem; }

        .filter-bar { background: #fff; border-radius: 22px; border: 1px solid var(--border-light); padding: 1.2rem 1.5rem; box-shadow: var(--shadow-sm); display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; }
        .filter-group { flex: 1 1 200px; }
        .filter-group.grow { flex: 2 1 300px; }
        .filter-group label { display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px; }
        .input-icon-wrap { position: relative; }
        .input-icon-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
        .filter-input { width: 100%; height: 46px; padding: 0 1rem; border: 1px solid #dfe3ee; border-radius: 12px; font-family: inherit; font-size: 0.9rem; color: var(--text-dark); background: #fbfbfe; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; }
        .filter-input.with-icon { padding-left: 2.6rem; }
        .filter-input::placeholder { color: #a6aec4; }
        .filter-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: #fff; }
        select.filter-input { appearance: none; padding-right: 2.4rem; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.85rem center; }
        .kbd-hint { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; font-weight: 700; color: #94a3b8; border: 1px solid #dfe3ee; border-radius: 6px; padding: 1px 7px; background: #fff; pointer-events: none; }
        .btn-apply { height: 46px; padding: 0 1.8rem; background: var(--grad); color: #fff; border: none; border-radius: 999px; font-weight: 700; font-size: 0.93rem; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.30); transition: transform 0.15s, box-shadow 0.15s; }
        .btn-apply:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.4); }
        .btn-clear { height: 46px; padding: 0 1.2rem; color: var(--danger); background: #fef2f2; text-decoration: none; font-size: 0.85rem; font-weight: 700; border-radius: 999px; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
        .btn-clear:hover { background: #fee2e2; }

        .results-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; background: #fff; padding: 0.9rem 1.2rem 0.9rem 1.5rem; border-radius: 18px; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); }
        .results-count { font-size: 0.95rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .results-count strong { color: var(--primary); font-weight: 800; font-size: 1.15rem; }
        .sec-ico { width: 34px; height: 34px; border-radius: 11px; background: #eceeff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); }
        .view-toggle { display: flex; background: #eceeff; border-radius: 12px; padding: 4px; gap: 2px; }
        .view-toggle button { width: 40px; height: 34px; border: none; background: transparent; border-radius: 9px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s, box-shadow 0.2s; }
        .view-toggle button.active { background: #fff; color: var(--primary); box-shadow: var(--shadow-sm); }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip { display: inline-flex; align-items: center; gap: 8px; background: #eceeff; color: var(--primary); padding: 6px 8px 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: background 0.2s, transform 0.2s; }
        .chip:hover { background: #dfe3ff; transform: translateY(-1px); }
        .chip .x { width: 20px; height: 20px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; }

        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 1.5rem; }
        @media (min-width: 1400px) { .doc-grid { grid-template-columns: repeat(3, 1fr); } }
        .doc-grid.list-view { grid-template-columns: 1fr; }
        .doc-grid.list-view .doc-title { font-size: 1.2rem; }

        @media (max-width: 1300px) { .hero-bg .deco-ico { display: none; } }
        @media (max-width: 1100px) {
            .navbar { padding: 0 2rem; }
            .page-hero { padding: 2.8rem 2rem 3rem; }
            .library-container { padding: 2rem; }
        }
        @media (max-width: 820px) {
            .navbar { height: auto; flex-wrap: wrap; padding: 0.7rem 1rem; row-gap: 0.4rem; }
            .nav-links { order: 3; width: 100%; justify-content: center; }
            .nav-links a { padding: 0.45rem 0.9rem; font-size: 0.85rem; }
            .page-hero { padding: 2.2rem 1.25rem 2.6rem; }
            .hero-bg .dots, .hero-bg .band { display: none; }
            #notifDropdown { width: min(340px, 88vw); position: fixed; right: 0.75rem; top: 70px; }
        }
        @media (max-width: 680px) { .library-container { padding: 1.5rem 1.1rem; } .filter-bar { padding: 1rem; } }
        /* ===== Back to top ===== */
        .back-top { position: fixed; right: 26px; bottom: 26px; width: 48px; height: 48px; border: none; border-radius: 50%; background: linear-gradient(90deg, #1a01b6, #1a01b6); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 26px rgba(91,70,230,0.4); opacity: 0; visibility: hidden; transform: translateY(14px); transition: opacity 0.25s, transform 0.25s, visibility 0.25s; z-index: 900; }
        .back-top.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .back-top:hover { transform: translateY(-3px); }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION -->
    <nav class="navbar">
        <a href="dashboard.php" class="nav-brand">
            <img src="../assets/logo1.png" alt="Logo">
            <span>Schol<em>Archive</em></span>
        </a>

        <div class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="browse.php">Browse Documents</a>
            <a href="library.php" class="active">My Library</a>
        </div>

        <div class="nav-user">

            <!-- About Info Icon -->
            <a href="about.php" class="btn-icon-nav" title="About ScholArchive">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            </a>

            <!-- Notification Wrapper -->
            <div style="position: relative;">
                <button class="btn-icon-nav has-unread" id="notifBtn" data-latest="<?= $latestNotifId ?>" onclick="toggleDropdown('notifDropdown')" title="Notifications">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <div class="bell-dot" id="bellDot"></div>
                </button>

                <div class="dropdown-menu" id="notifDropdown">
                    <div class="notif-header">Recent Notifications</div>
                    <div class="notif-body">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif):
                                $isMyUpload = ($notif['uploaded_by'] == $student['id']);
                            ?>
                                <a href="view_document.php?id=<?= $notif['id'] ?>" class="notif-item">
                                    <?php if ($isMyUpload): ?>
                                        <div class="notif-icon approved"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                                    <?php else: ?>
                                        <div class="notif-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
                                    <?php endif; ?>

                                    <div class="notif-content">
                                        <div class="notif-text">
                                            <?php if ($isMyUpload): ?>
                                                <strong>Approved!</strong> Your research "<?= e($notif['title']) ?>" has been published.
                                            <?php else: ?>
                                                <strong>New Upload:</strong> "<?= e($notif['title']) ?>" is now available.
                                            <?php endif; ?>
                                        </div>
                                        <span class="notif-time"><?= date('M j, Y • g:i A', strtotime($notif['created_at'])) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notif-empty">You're all caught up! No new notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Dropdown Wrapper -->
            <div style="position: relative;">
                <button class="btn-avatar" onclick="toggleDropdown('profileDropdown')" title="My Profile">
                    <?php if (!empty($student['selfie']) && file_exists('../uploads/' . $student['selfie'])): ?>
                        <img src="../uploads/<?= e($student['selfie']) ?>" alt="Avatar" class="avatar">
                    <?php else: ?>
                        <div class="avatar-initials"><?= get_initials($student['first_name'], $student['last_name']) ?></div>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu" id="profileDropdown">
                    <div class="profile-header">
                        <span class="dd-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        <span class="profile-role">Student Scholar</span>
                    </div>
                    <div class="profile-links">
                        <a href="profile.php" class="profile-link">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="14" cy="7" r="4"/></svg>
                            View Profile
                        </a>
                        <a href="../logout.php" class="profile-link logout">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Log Out
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </nav>

    <!-- PAGE HEADER -->
    <header class="page-hero">
        <div class="hero-bg" aria-hidden="true">
            <span class="glow g1"></span>
            <span class="glow g2"></span>
            <span class="ring r1"></span>
            <span class="ring r2"></span>
            <span class="ring r3"></span>
            <span class="ring r4"></span>
            <span class="band b1"></span>
            <span class="band b2"></span>
            <span class="band b3"></span>
            <span class="dots d1"></span>
            <span class="dots d2"></span>
        </div>

        <div class="page-hero-inner">
            <div>
            
                <h1 class="reveal" style="--d:80ms">
                    <span class="title-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg></span>
                    Personal Library
                </h1>
                <p class="reveal" style="--d:160ms">Your curated collection of saved research and capstone projects.</p>
                <div class="hero-stat-pill reveal" style="--d:240ms">
                    <strong data-count="<?= (int)$absoluteTotal ?>"><?= number_format($absoluteTotal) ?></strong> Saved Documents
                </div>
            </div>
        </div>
    </header>

    <!-- LIBRARY LAYOUT -->
    <div class="library-container">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= e($success) ?></span>
                <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Search & sort bar -->
        <form action="library.php" method="GET" class="filter-bar reveal">
            <div class="filter-group grow">
                <label for="searchInput">Search Collection</label>
                <div class="input-icon-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="searchInput" name="search" class="filter-input with-icon" placeholder="Find title or author..." value="<?= e($searchQuery) ?>" autocomplete="off">
                    <span class="kbd-hint">/</span>
                </div>
            </div>

            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" class="filter-input" onchange="this.form.submit()">
                    <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Recently Saved</option>
                    <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest Saved</option>
                    <option value="title_a_z" <?= $sortOrder === 'title_a_z' ? 'selected' : '' ?>>Title (A-Z)</option>
                    <option value="year_new" <?= $sortOrder === 'year_new' ? 'selected' : '' ?>>Year (Newest)</option>
                </select>
            </div>

            <button type="submit" class="btn-apply">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Apply
            </button>

            <?php if (!empty($searchQuery) || $sortOrder !== 'newest'): ?>
                <a href="library.php" class="btn-clear" title="Clear Filters">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Clear All
                </a>
            <?php endif; ?>
        </form>

        <!-- Results header -->
        <div class="results-header reveal" style="--d:80ms">
            <div class="results-count">
                <span class="sec-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg></span>
                Viewing <strong data-count="<?= count($savedDocs) ?>"><?= count($savedDocs) ?></strong> saved document<?= count($savedDocs) !== 1 ? 's' : '' ?>
            </div>
            <div class="view-toggle" role="group" aria-label="Change layout">
                <button type="button" id="viewGrid" class="active" title="Grid view" aria-label="Grid view">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </button>
                <button type="button" id="viewList" title="List view" aria-label="List view">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
            </div>
        </div>

        <?php if (count($chips) > 0): ?>
        <div class="chips reveal" style="--d:140ms">
            <?php foreach ($chips as $chip): ?>
                <a href="<?= e(lib_chip_url($baseParams, $chip['drop'])) ?>" class="chip" title="Remove this filter">
                    <?= e($chip['label']) ?>
                    <span class="x"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- DOCUMENT GRID -->
        <div class="doc-grid" id="docGrid">
            <?php if (count($savedDocs) > 0): ?>
                <?php foreach ($savedDocs as $i => $doc):
                    $cleanCat = str_replace(['BS ', 'Bachelor of Science in '], '', $doc['category_name']);
                ?>
                    <div class="doc-card reveal" style="--d:<?= min($i, 8) * 60 ?>ms">
                        <div class="doc-badges">
                            <span class="badge-cat"><?= e($cleanCat) ?></span>
                            <span class="badge-saved">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?= date('M j, Y', strtotime($doc['saved_at'])) ?>
                            </span>
                        </div>

                        <a href="view_document.php?id=<?= $doc['id'] ?>" class="doc-title" title="<?= e($doc['title']) ?>">
                            <?= lib_hl($doc['title'], $searchQuery) ?>
                        </a>

                        <div class="doc-authors">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span><?= lib_hl($doc['authors'], $searchQuery) ?></span>
                        </div>

                        <div class="doc-footer">
                            <a href="view_document.php?id=<?= $doc['id'] ?>" class="btn-read">
                                Read Document
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>

                            <form method="POST" action="" class="remove-form" style="margin:0;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn-remove" title="Remove from Library">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    Unsave
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state reveal">
                    <div class="empty-icon">
<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    </div>
                    <?php if (!empty($searchQuery)): ?>
                        <h3>No matches found</h3>
                        <p>We couldn't find any saved documents matching "<strong><?= e($searchQuery) ?></strong>".</p>
                        <a href="library.php" class="btn-soft">Clear Search</a>
                    <?php else: ?>
                        <h3>Your library is empty</h3>
                        <p>You haven't saved any documents yet. Start exploring the repository to build your personal collection.</p>
                        <a href="browse.php" class="btn-gradient">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            Browse Repository
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="toast" id="toast"></div>
    <div class="site-footer">&copy; <?= date('Y') ?> ScholArchive &middot; ACLC College of Mandaue</div>

    <script>
        // ---------- Dropdowns & notifications ----------
        document.addEventListener('DOMContentLoaded', function() {
            const notifBtn = document.getElementById('notifBtn');
            const latestId = notifBtn.getAttribute('data-latest');
            const lastSeen = localStorage.getItem('scholarchive_last_notif');
            if (latestId == 0 || lastSeen === latestId) notifBtn.classList.remove('has-unread');
        });

        function toggleDropdown(dropdownId) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== dropdownId) menu.classList.remove('active');
            });
            const target = document.getElementById(dropdownId);
            target.classList.toggle('active');
            if (dropdownId === 'notifDropdown' && target.classList.contains('active')) {
                const notifBtn = document.getElementById('notifBtn');
                const latestId = notifBtn.getAttribute('data-latest');
                if (latestId > 0) {
                    localStorage.setItem('scholarchive_last_notif', latestId);
                    notifBtn.classList.remove('has-unread');
                }
            }
        }
        window.addEventListener('click', function(e) {
            if (!document.querySelector('.nav-user').contains(e.target)) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
            }
        });

        // ---------- Toast ----------
        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('show');
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.classList.remove('show'), 2200);
        }

        (function() {
            // Navbar shadow on scroll
            const nb = document.querySelector('.navbar');
            const onScroll = () => nb.classList.toggle('scrolled', window.scrollY > 8);
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });

            // Scroll-reveal
            const reveals = document.querySelectorAll('.reveal');
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver(entries => {
                    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
                }, { threshold: 0.1 });
                reveals.forEach(el => io.observe(el));
            } else {
                reveals.forEach(el => el.classList.add('in'));
            }

            // Card spotlight follows the mouse
            document.addEventListener('mousemove', function(e) {
                const card = e.target.closest && e.target.closest('.doc-card');
                if (!card) return;
                const r = card.getBoundingClientRect();
                card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                card.style.setProperty('--my', (e.clientY - r.top) + 'px');
            });

            // Count-up numbers
            document.querySelectorAll('[data-count]').forEach(el => {
                const end = parseInt(el.dataset.count, 10) || 0;
                if (end === 0) return;
                let start = null;
                const dur = 1000;
                const tick = ts => {
                    if (!start) start = ts;
                    const p = Math.min((ts - start) / dur, 1);
                    el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString();
                    if (p < 1) requestAnimationFrame(tick);
                };
                el.textContent = '0';
                requestAnimationFrame(tick);
            });

            // Dismissible alerts (auto-hide after 8s)
            document.querySelectorAll('.alert').forEach(a => {
                const close = a.querySelector('.alert-close');
                const hide = () => { a.classList.add('hide'); setTimeout(() => a.remove(), 400); };
                if (close) close.addEventListener('click', hide);
                setTimeout(hide, 8000);
            });

            // Press "/" to jump to the search box
            document.addEventListener('keydown', function(e) {
                if (e.key !== '/' || /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
                const s = document.getElementById('searchInput');
                if (s) { e.preventDefault(); s.focus(); s.select(); }
            });
        })();

        // ---------- Library: confirm + shrink animation on Unsave ----------
        document.querySelectorAll('.remove-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                if (!confirm('Remove this document from your library?')) return;
                form.dataset.confirmed = '1';
                const card = form.closest('.doc-card');
                if (card) card.classList.add('removing');
                setTimeout(() => form.submit(), 300);
            });
        });

        // ---------- Library: grid / list toggle (remembered) ----------
        (function() {
            const grid = document.getElementById('docGrid');
            const bGrid = document.getElementById('viewGrid');
            const bList = document.getElementById('viewList');
            function setView(mode) {
                grid.classList.toggle('list-view', mode === 'list');
                bList.classList.toggle('active', mode === 'list');
                bGrid.classList.toggle('active', mode !== 'list');
                try { localStorage.setItem('scholarchive_view', mode); } catch (e) {}
            }
            bGrid.addEventListener('click', () => setView('grid'));
            bList.addEventListener('click', () => setView('list'));
            try { setView(localStorage.getItem('scholarchive_view') === 'list' ? 'list' : 'grid'); } catch (e) {}
        })();
        // Back to top
        (function() {
            var up = document.createElement('button');
            up.type = 'button';
            up.className = 'back-top';
            up.title = 'Back to top';
            up.setAttribute('aria-label', 'Back to top');
            up.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
            up.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
            document.body.appendChild(up);
            function onScrollTop() { up.classList.toggle('show', window.scrollY > 200); }
            window.addEventListener('scroll', onScrollTop, { passive: true });
            onScrollTop();
        })();
    </script>
</body>
</html>