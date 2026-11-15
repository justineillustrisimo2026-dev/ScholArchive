<?php
// student/view_document.php
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

// 2. Fetch Dynamic Notifications & Track Latest ID
try {
    $notifStmt = $pdo->query("
        SELECT id, title, uploaded_by, created_at 
        FROM documents 
        WHERE status = 'published' 
        ORDER BY id DESC LIMIT 6
    ");
    $notifications = $notifStmt->fetchAll();
    $latestNotifId = count($notifications) > 0 ? $notifications[0]['id'] : 0;
} catch (PDOException $e) {
    $notifications = [];
    $latestNotifId = 0;
}

// 3. Fetch the specific Document
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docStmt = $pdo->prepare("
    SELECT d.*, c.name as category_name 
    FROM documents d 
    LEFT JOIN categories c ON d.category_id = c.id 
    WHERE d.id = ? AND d.status = 'published'
");
$docStmt->execute([$docId]);
$document = $docStmt->fetch();

if (!$document) {
    flash_set('error', 'Document not found or is not available.');
    header("Location: browse.php");
    exit;
}

// Clean category name
$cleanCat = str_replace(['BS ', 'Bachelor of Science in '], '', $document['category_name']);

// 4. Verify if PDF File Actually Exists on the Server
$pdfFilename = $document['file_path'] ?? ''; 
$pdfServerPath = __DIR__ . '/../uploads/documents/' . $pdfFilename;
$pdfWebUrl = '../uploads/documents/' . htmlspecialchars($pdfFilename);

$pdfExists = false;
if (!empty($pdfFilename) && file_exists($pdfServerPath)) {
    $pdfExists = true;
}

// 5. Check if it is currently saved in the student's library
$checkSaved = $pdo->prepare("SELECT id FROM saved_documents WHERE user_id = ? AND document_id = ?");
$checkSaved->execute([$student['id'], $docId]);
$isSaved = $checkSaved->fetch() ? true : false;

// 6. Handle "Save / Unsave" Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_library') {
    if ($isSaved) {
        $del = $pdo->prepare("DELETE FROM saved_documents WHERE user_id = ? AND document_id = ?");
        $del->execute([$student['id'], $docId]);
        flash_set('success', 'Document removed from your library.');
    } else {
        $ins = $pdo->prepare("INSERT INTO saved_documents (user_id, document_id) VALUES (?, ?)");
        $ins->execute([$student['id'], $docId]);
        flash_set('success', 'Document saved to your library.');
    }
    header("Location: view_document.php?id=" . $docId);
    exit;
}

$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($document['title']) ?> · ScholArchive</title>
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
            --warning-yellow: #eab308;
            --warning-bg: #fefce8;
            --warning-border: #fef08a;
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
        .page-hero { position: relative; overflow: hidden; color: #fff; padding: 3rem 4rem 3.2rem;
            background: linear-gradient(100deg, rgba(10, 6, 70, 0.94) 0%, rgba(28, 18, 130, 0.82) 55%, rgba(60, 40, 170, 0.66) 100%), url('../assets/backgroundaclc.png') center 35% / cover no-repeat; }
        .page-hero-inner { position: relative; z-index: 2; max-width: 1100px; margin: 0 auto; }
        .crumbs { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; opacity: 0.85; margin-bottom: 0.9rem; flex-wrap: wrap; }
        .crumbs a { color: #fff; text-decoration: none; opacity: 0.8; transition: opacity 0.2s; }
        .crumbs a:hover { opacity: 1; }
        .page-hero h1 { font-size: clamp(1.9rem, 3.4vw, 2.6rem); font-weight: 800; letter-spacing: -1px; display: flex; align-items: center; gap: 14px; text-shadow: 0 4px 14px rgba(0,0,0,0.25); }
        .title-ico { width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.28); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); flex-shrink: 0; }
        .page-hero p { opacity: 0.92; margin-top: 0.6rem; max-width: 560px; line-height: 1.55; font-size: 1.02rem; }

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

        /* ---------- Main layout ---------- */
        .main-content { padding: 2.5rem 4rem 3rem; max-width: 1100px; margin: 0 auto; width: 100%; flex: 1; }
        .layout-stack { display: flex; flex-direction: column; gap: 1.6rem; align-items: stretch; }
        .header-top { display: flex; align-items: center; justify-content: flex-start; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: var(--primary); padding: 0.7rem 1.3rem; border-radius: 999px; font-weight: 700; border: 1px solid var(--border-light); text-decoration: none; transition: transform 0.2s, background 0.2s, border-color 0.2s; font-size: 0.88rem; box-shadow: var(--shadow-sm); }
        .btn-back:hover { background: #f1f2ff; transform: translateX(-4px); border-color: #c7d2fe; }

        /* ---------- Alerts ---------- */
        .alert { padding: 1rem 1.2rem 1rem 1.4rem; border-radius: 14px; font-size: 0.93rem; font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 1.6rem; box-shadow: var(--shadow-sm); background: #fff; animation: popIn 0.4s ease both; transition: opacity 0.4s, transform 0.4s; }
        .alert.hide { opacity: 0; transform: translateY(-8px); }
        .alert svg { flex-shrink: 0; }
        .alert > span { flex: 1; }
        .alert-close { background: none; border: none; font-size: 1.3rem; line-height: 1; color: inherit; opacity: 0.5; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; transition: opacity 0.2s, background 0.2s; }
        .alert-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .alert-success { color: #065f46; border: 1px solid #a7f3d0; border-left: 6px solid #10b981; }
        .alert-error { color: #991b1b; border: 1px solid #fecaca; border-left: 6px solid #ef4444; }

        /* ---------- Cards ---------- */
        .doc-header-card { background: #fff; border-radius: 22px; padding: 2.4rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
        .doc-header-card::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); }
        .doc-header-card::after { content: ""; position: absolute; top: 0; right: 0; width: 260px; height: 260px; background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%); opacity: 0.55; border-radius: 0 22px 0 100%; pointer-events: none; }
        .doc-header-card > * { position: relative; z-index: 1; }

        .meta-badges { display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem; flex-wrap: wrap; }
        .badge { padding: 6px 14px; border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-cat { background: var(--primary-light); color: var(--primary); }
        .badge-year { background: #fff; color: var(--text-dark); border: 1px solid var(--border-light); }

        .doc-title { font-size: 2rem; font-weight: 800; color: var(--navy); line-height: 1.3; margin-bottom: 1rem; letter-spacing: -0.5px; }
        .doc-authors { font-size: 0.98rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 2rem; flex-wrap: wrap; }

        .actions-bar { display: flex; flex-direction: row; gap: 1rem; border-top: 1px solid var(--border-light); padding-top: 1.5rem; align-items: center; flex-wrap: wrap; }
        .btn { padding: 0.85rem 1.7rem; border-radius: 999px; font-size: 0.93rem; font-weight: 700; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s, background 0.2s, border-color 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border: none; font-family: inherit; }
        .btn-view { background: var(--grad); color: #fff; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.32); }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.42); }
        .btn-view.disabled { background: #cbd5e1; color: #fff; cursor: not-allowed; box-shadow: none; pointer-events: none; }
        .btn-save { background: #fff; color: var(--text-dark); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); }
        .btn-save:hover { background: #f7f8ff; border-color: #c7d2fe; transform: translateY(-2px); }
        .btn-save.saved { background: var(--warning-bg); color: #854d0e; border-color: var(--warning-border); }
        .btn-save.saved:hover { background: #fef08a; }
        .btn-save.saved svg { fill: var(--warning-yellow); color: var(--warning-yellow); }

        .abstract-card { background: #fff; border-radius: 22px; padding: 2.4rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); }
        .section-title { font-size: 1.15rem; font-weight: 800; color: var(--navy); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; }
        .sec-ico { width: 34px; height: 34px; border-radius: 11px; background: #eceeff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); }
        .abstract-text { font-size: 1.04rem; line-height: 1.85; color: #334155; font-weight: 400; text-align: justify; }

        /* ---------- PDF viewer ---------- */
        .pdf-viewer-container { border-radius: 22px; overflow: hidden; border: 1px solid var(--border-light); background: #cbd5e1; display: flex; flex-direction: column; box-shadow: var(--shadow-md); height: 80vh; min-height: 700px; }
        .pdf-header { background: #fff; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); }
        .pdf-header h4 { font-size: 0.95rem; font-weight: 800; color: var(--navy); display: flex; align-items: center; gap: 10px; }
        .pdf-iframe { width: 100%; height: 100%; border: none; background: #fff; }
        .pdf-missing { background: #fff; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; text-align: center; }
        .pdf-missing .empty-icon { width: 84px; height: 84px; background: #eceeff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.4rem; color: var(--primary); animation: float 5s ease-in-out infinite; }
        .pdf-missing h3 { font-size: 1.25rem; font-weight: 800; color: var(--navy); margin-bottom: 8px; }
        .pdf-missing p { font-size: 0.95rem; color: var(--text-muted); max-width: 420px; line-height: 1.6; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1300px) { .hero-bg .deco-ico { display: none; } }
        @media (max-width: 1100px) {
            .navbar { padding: 0 2rem; }
            .page-hero { padding: 2.6rem 2rem 2.8rem; }
            .main-content { padding: 2rem; }
        }
        @media (max-width: 1024px) {
            .doc-title { font-size: 1.6rem; }
            .actions-bar { flex-direction: column; align-items: stretch; }
            .actions-bar > a, .actions-bar > form { width: 100%; display: block; }
            .btn { width: 100%; }
        }
        @media (max-width: 820px) {
            .navbar { height: auto; flex-wrap: wrap; padding: 0.7rem 1rem; row-gap: 0.4rem; }
            .nav-links { order: 3; width: 100%; justify-content: center; }
            .nav-links a { padding: 0.45rem 0.9rem; font-size: 0.85rem; }
            .page-hero { padding: 2.2rem 1.25rem 2.4rem; }
            .hero-bg .dots, .hero-bg .band { display: none; }
            #notifDropdown { width: min(340px, 88vw); position: fixed; right: 0.75rem; top: 70px; }
        }
        @media (max-width: 680px) {
            .main-content { padding: 1.5rem 1.1rem; }
            .doc-header-card, .abstract-card { padding: 1.6rem; }
            .pdf-viewer-container { min-height: 520px; }
        }
        /* ===== Back to top ===== */
        .back-top { position: fixed; right: 26px; bottom: 26px; width: 48px; height: 48px; border: none; border-radius: 50%; background: linear-gradient(90deg, #3b82f6, #7c3aed); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 26px rgba(91,70,230,0.4); opacity: 0; visibility: hidden; transform: translateY(14px); transition: opacity 0.25s, transform 0.25s, visibility 0.25s; z-index: 900; }
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

        <!-- Cleaned Center Navigation -->
        <div class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="browse.php">Browse Documents</a>
            <a href="library.php">My Library</a>
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
            <h1 class="reveal" style="--d:80ms">
                <span class="title-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
                Document Viewer
            </h1>
            <p class="reveal" style="--d:160ms">Review the capstone metadata, abstract, and full PDF document.</p>
        </div>
    </header>

    <main class="main-content">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= e($success) ?></span>
                <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= e($error) ?></span>
                <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <!-- STACKED LAYOUT -->
        <div class="layout-stack">

            <div class="header-top reveal">
                <a href="browse.php" class="btn-back">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                    Back to Repository
                </a>
            </div>

            <!-- Metadata Header Card -->
            <div class="doc-header-card reveal" style="--d:60ms">
                <div class="meta-badges">
                    <span class="badge badge-cat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        <?= e($cleanCat) ?>
                    </span>
                    <span class="badge badge-year">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        A.Y. <?= e($document['pub_year']) ?>
                    </span>
                </div>

                <h1 class="doc-title"><?= e($document['title']) ?></h1>

                <div class="doc-authors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <?= e($document['authors']) ?>
                </div>

                <div class="actions-bar">
                    <!-- Open Full Screen Button -->
                    <a href="<?= $pdfExists ? $pdfWebUrl : '#' ?>" target="<?= $pdfExists ? '_blank' : '_self' ?>" class="btn btn-view <?= !$pdfExists ? 'disabled' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        <?= $pdfExists ? 'Open in New Tab' : 'PDF Unavailable' ?>
                    </a>

                    <!-- Save Toggle -->
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="action" value="toggle_library">
                        <?php if ($isSaved): ?>
                            <button type="submit" class="btn btn-save saved">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                                Saved to Library
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-save">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                                Save to Library
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Abstract Card -->
            <div class="abstract-card reveal">
                <h3 class="section-title">
                    <span class="sec-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></span>
                    Research Abstract
                </h3>
                <div class="abstract-text">
                    <?= nl2br(e($document['abstract'])) ?>
                </div>
            </div>

            <!-- Full Width PDF Viewer (Placed below the abstract) -->
            <div class="pdf-viewer-container reveal">
                <div class="pdf-header">
                    <h4>
                        <span class="sec-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg></span>
                        Document Viewer
                    </h4>
                </div>

                <?php if ($pdfExists): ?>
                    <iframe src="<?= $pdfWebUrl ?>#toolbar=0" class="pdf-iframe" title="PDF Viewer"></iframe>
                <?php else: ?>
                    <div class="pdf-missing">
                        <div class="empty-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
                        <h3>Document file not found</h3>
                        <p>The PDF file associated with this record has been removed or is currently unavailable.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- DROPDOWN, NOTIFICATION & UI SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notifBtn = document.getElementById('notifBtn');
            const latestId = notifBtn.getAttribute('data-latest');
            const lastSeen = localStorage.getItem('scholarchive_last_notif');

            if (latestId == 0 || lastSeen === latestId) {
                notifBtn.classList.remove('has-unread');
            }
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

            // Dismissible alerts (auto-hide after 8s)
            document.querySelectorAll('.alert').forEach(a => {
                const close = a.querySelector('.alert-close');
                const hide = () => { a.classList.add('hide'); setTimeout(() => a.remove(), 400); };
                if (close) close.addEventListener('click', hide);
                setTimeout(hide, 8000);
            });
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
            function onScrollTop() { up.classList.toggle('show', window.scrollY > 400); }
            window.addEventListener('scroll', onScrollTop, { passive: true });
            onScrollTop();
        })();
    </script>
</body>
</html>