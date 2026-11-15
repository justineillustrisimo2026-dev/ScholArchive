<?php
// student/dashboard.php
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

// 2. Handle Document Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_doc') {
    $title = trim($_POST['title'] ?? '');
    $authors = trim($_POST['authors'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $pubYear = (int)($_POST['pub_year'] ?? date('Y'));
    $abstract = trim($_POST['abstract'] ?? '');
    $status = 'pending'; 
    $uploadDir = __DIR__ . '/../uploads/documents/';

    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['document_file']['tmp_name'];
        $fileName = basename($_FILES['document_file']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($ext === 'pdf') {
            $uniqueName = 'student_req_' . time() . '_' . uniqid() . '.pdf';
            if (move_uploaded_file($tmpName, $uploadDir . $uniqueName)) {
                $insertStmt = $pdo->prepare("INSERT INTO documents (title, abstract, authors, category_id, pub_year, file_path, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$title, $abstract, $authors, $categoryId, $pubYear, $uniqueName, $status, $student['id']]);
                flash_set('success', 'Your document was submitted successfully and is awaiting admin approval!');
            } else { flash_set('error', 'Server error: Failed to save the document.'); }
        } else { flash_set('error', 'Invalid file format. Please upload a PDF file.'); }
    } else { flash_set('error', 'Please attach a valid PDF document.'); }
    
    header('Location: dashboard.php');
    exit;
}

// 3. Fetch Data for UI
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    $shortcutCategories = array_slice($categories, 0, 5);
} catch (PDOException $e) { 
    $categories = []; 
    $shortcutCategories = []; 
}

$crStmt = $pdo->prepare("
    SELECT d.id, d.title, d.pub_year, c.name as category_name 
    FROM saved_documents sd 
    INNER JOIN documents d ON sd.document_id = d.id 
    LEFT JOIN categories c ON d.category_id = c.id 
    WHERE sd.user_id = ? AND d.status = 'published' 
    ORDER BY sd.saved_at DESC LIMIT 3
");
$crStmt->execute([$student['id']]);
$continueReading = $crStmt->fetchAll();

$studentCourse = $student['course'] ?? '';
$recStmt = $pdo->prepare("
    SELECT d.id, d.title, d.pub_year, d.abstract, c.name as category_name 
    FROM documents d 
    LEFT JOIN categories c ON d.category_id = c.id 
    WHERE d.status = 'published' AND (c.name LIKE ? OR ? LIKE CONCAT('%', c.name, '%'))
    ORDER BY d.created_at DESC LIMIT 3
");
$recStmt->execute(["%$studentCourse%", $studentCourse]);
$recommendedDocs = $recStmt->fetchAll();

$recentStmt = $pdo->query("
    SELECT d.id, d.title, d.pub_year, c.name as category_name
    FROM documents d
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.status = 'published'
    ORDER BY d.created_at DESC LIMIT 4
");
$recentDocs = $recentStmt->fetchAll();

// 4. Fetch Dynamic Notifications
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

$success = flash_get('success');
$error = flash_get('error');
$currentYear = date('Y');
$years = range($currentYear + 1, 2010);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home · ScholArchive</title>
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
            --grad: linear-gradient(90deg, #3b82f6 0%, #7c3aed 100%);
            --font-family: 'Inter', sans-serif;
            --shadow-sm: 0 2px 8px rgba(40, 30, 140, 0.05);
            --shadow-md: 0 14px 30px -12px rgba(40, 30, 140, 0.25);
            --shadow-lg: 0 30px 60px -20px rgba(40, 30, 140, 0.35);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-family); background: var(--bg-light); color: var(--text-dark); overflow-x: hidden; -webkit-font-smoothing: antialiased; }
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

        /* ---------- Hero ---------- */
        .hero-section { position: relative; min-height: clamp(440px, 60vh, 540px); display: flex; align-items: center; padding: 4rem 4rem 4.5rem; overflow: hidden;
            background: linear-gradient(100deg, rgba(10, 6, 70, 0.94) 0%, rgba(28, 18, 130, 0.82) 55%, rgba(60, 40, 170, 0.66) 100%), url('../assets/backgroundaclc.png') center 40% / cover no-repeat; }

        .hero-bg { position: absolute; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
        .hero-bg .glow { position: absolute; border-radius: 50%; filter: blur(60px); animation: pulseGlow 8s ease-in-out infinite; }
        .hero-bg .g1 { width: 520px; height: 520px; top: -230px; right: -130px; background: radial-gradient(circle, rgba(124, 58, 237, 0.55), transparent 70%); }
        .hero-bg .g2 { width: 480px; height: 480px; bottom: -250px; left: -150px; background: radial-gradient(circle, rgba(59, 130, 246, 0.50), transparent 70%); animation-delay: -3s; }
        .hero-bg .ring { position: absolute; border-radius: 50%; border: 1.5px solid rgba(255, 255, 255, 0.13); animation: ringPulse 9s ease-in-out infinite; }
        .hero-bg .r1 { width: 440px; height: 440px; top: -170px; left: -130px; }
        .hero-bg .r2 { width: 310px; height: 310px; top: -100px; left: -60px; border-color: rgba(255, 255, 255, 0.08); animation-delay: -2s; }
        .hero-bg .r3 { width: 480px; height: 480px; bottom: -240px; right: -100px; animation-delay: -4s; }
        .hero-bg .r4 { width: 340px; height: 340px; bottom: -170px; right: -30px; border-color: rgba(255, 255, 255, 0.08); animation-delay: -6s; }
        .hero-bg .band { position: absolute; top: -40%; width: 90px; height: 200%; transform: rotate(24deg); background: linear-gradient(180deg, rgba(165, 180, 252, 0), rgba(165, 180, 252, 0.15), rgba(165, 180, 252, 0)); animation: sway 10s ease-in-out infinite; }
        .hero-bg .b1 { right: 17%; }
        .hero-bg .b2 { right: 12%; width: 28px; opacity: 0.8; animation-delay: -4s; }
        .hero-bg .b3 { left: 14%; width: 40px; opacity: 0.6; animation-delay: -7s; }
        .hero-bg .dots { position: absolute; background-image: radial-gradient(circle, currentColor 1.5px, transparent 1.6px); background-size: 22px 22px; }
        .hero-bg .d1 { top: 28px; left: 28px; width: 220px; height: 150px; color: rgba(255, 255, 255, 0.28); }
        .hero-bg .d2 { bottom: 28px; right: 28px; width: 240px; height: 150px; color: rgba(255, 255, 255, 0.20); }
        .hero-bg .deco-ico { position: absolute; width: 48px; height: 48px; color: rgba(255, 255, 255, 0.16); animation: float 7s ease-in-out infinite; }
        .hero-bg .i1 { top: 46%; left: 3%; }
        .hero-bg .i2 { bottom: 11%; left: 8%; animation-delay: -2s; }
        .hero-bg .i3 { top: 17%; right: 4%; animation-delay: -4s; }
        .hero-bg .i4 { top: 50%; right: 3%; animation-delay: -5s; }

        .hero-content { position: relative; z-index: 2; width: 100%; max-width: 1120px; margin: 0 auto; color: #fff; text-align: center; }
        .hero-content h1 { font-size: clamp(2rem, 4.4vw, 3.6rem); font-weight: 800; line-height: 1.12; letter-spacing: -1px; margin-bottom: 0.7rem; text-shadow: 0 4px 14px rgba(0,0,0,0.25); }
        .hero-content p { font-size: clamp(0.95rem, 1.5vw, 1.1rem); line-height: 1.5; opacity: 0.92; margin: 0 auto 1.9rem; }

        .hero-actions { display: flex; gap: 1rem; flex-wrap: nowrap; align-items: center; width: 100%; }
        .search-wrapper { background: #fff; padding: 6px 6px 6px 20px; border-radius: 999px; display: flex; align-items: center; box-shadow: 0 18px 36px -14px rgba(0,0,0,0.45); flex: 1; min-width: 0; transition: transform 0.2s, box-shadow 0.2s; }
        .search-wrapper:focus-within { transform: translateY(-2px); box-shadow: 0 24px 44px -14px rgba(0,0,0,0.55), 0 0 0 4px rgba(165, 180, 252, 0.45); }
        .search-input { border: none; flex: 1; min-width: 0; font-size: 1rem; font-family: inherit; color: var(--text-dark); outline: none; background: transparent; }
        .btn-search { background: #1a0b9a; color: #fff; border: none; padding: 0.85rem 2.4rem; border-radius: 999px; font-weight: 700; font-size: 0.92rem; cursor: pointer; transition: background 0.2s, transform 0.15s; font-family: inherit; }
        .btn-search:hover { background: #12066f; transform: scale(1.03); }
        .btn-submit-hero { background: #2f6bf0; color: #fff; border: none; padding: 1rem 1.9rem; border-radius: 999px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s, background 0.15s; display: flex; align-items: center; gap: 8px; box-shadow: 0 10px 22px rgba(47, 107, 240, 0.45); font-family: inherit; white-space: nowrap; flex-shrink: 0; }
        .btn-submit-hero:hover { background: #2559d1; transform: translateY(-2px); box-shadow: 0 14px 28px rgba(47, 107, 240, 0.55); }

        .category-shortcuts { display: flex; flex-wrap: nowrap; gap: 10px; margin: 1.7rem -0.9rem 0; }
        .shortcut-pill { flex: 1 1 auto; text-align: center; background: rgba(255,255,255,0.14); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.30); color: #fff; padding: 9px 18px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background 0.2s, transform 0.2s; }
        .shortcut-pill:hover { background: rgba(255,255,255,0.26); transform: translateY(-2px); }

        /* ---------- Main content ---------- */
        .main-container { position: relative; z-index: 2; padding: 3rem 4rem 3rem; min-height: 40vh; }
        .section-wrapper { max-width: 1300px; margin: 0 auto 3.5rem; }
        .section-header { margin-bottom: 1.4rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .section-header h2 { font-size: 1.35rem; font-weight: 800; color: var(--navy); letter-spacing: -0.5px; display: flex; align-items: center; gap: 12px; }
        .sec-ico { width: 42px; height: 42px; border-radius: 12px; background: #eceeff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .view-all { color: var(--primary); background: #eceeff; text-decoration: none; font-size: 0.85rem; font-weight: 700; padding: 8px 16px; border-radius: 999px; transition: background 0.2s, gap 0.2s; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .view-all:hover { background: #dfe3ff; gap: 10px; }

        .alert { padding: 1rem 1.2rem 1rem 1.4rem; border-radius: 14px; font-size: 0.93rem; font-weight: 600; display: flex; align-items: center; gap: 12px; margin: 0 auto 2.2rem; max-width: 1300px; box-shadow: var(--shadow-sm); background: #fff; animation: popIn 0.4s ease both; transition: opacity 0.4s, transform 0.4s; }
        .alert.hide { opacity: 0; transform: translateY(-8px); }
        .alert svg { flex-shrink: 0; }
        .alert > span { flex: 1; }
        .alert-close { background: none; border: none; font-size: 1.3rem; line-height: 1; color: inherit; opacity: 0.5; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; transition: opacity 0.2s, background 0.2s; }
        .alert-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .alert-success { color: #065f46; border: 1px solid #a7f3d0; border-left: 6px solid #10b981; }
        .alert-error { color: #991b1b; border: 1px solid #fecaca; border-left: 6px solid #ef4444; }

        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }

        .doc-card { position: relative; overflow: hidden; background: #fff; border-radius: 20px; padding: 1.6rem; border: 1px solid var(--border-light); text-decoration: none; display: flex; flex-direction: column; box-shadow: var(--shadow-sm); transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s; }
        .doc-card > * { position: relative; z-index: 1; }
        .doc-card::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); transform: scaleY(0); transform-origin: top; transition: transform 0.35s; z-index: 2; }
        .doc-card.recommended::before { transform: scaleY(1); }
        .doc-card::after { content: ""; position: absolute; inset: 0; background: radial-gradient(380px circle at var(--mx, 50%) var(--my, 50%), rgba(99, 102, 241, 0.13), transparent 45%); opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 0; }
        .doc-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-md); border-color: #c7d2fe; }
        .doc-card:hover::before { transform: scaleY(1); }
        .doc-card::after { display: none; }
        .doc-card.continue { background: linear-gradient(135deg, #f4f5ff 0%, #fff 70%); }
        .doc-meta-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-cat { color: var(--primary); background: var(--primary-light); padding: 5px 12px; border-radius: 999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .doc-cat.neutral { background: #eceeff; color: var(--primary); }
        .doc-year { color: var(--text-muted); display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .doc-title { font-size: 1.05rem; font-weight: 800; color: var(--navy); line-height: 1.4; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; transition: color 0.2s; }
        .doc-card:hover .doc-title { color: var(--primary); }
        .doc-title.push { margin-bottom: auto; }
        .doc-abstract { font-size: 0.85rem; color: #4b5578; line-height: 1.6; margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
        .doc-footer { margin-top: auto; padding-top: 1.1rem; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; }
        .doc-footer.tight { margin-top: 1.1rem; }
        .btn-read { font-size: 0.85rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 4px; transition: gap 0.2s; }
        .doc-card:hover .btn-read { gap: 9px; }

        .empty-state { grid-column: 1 / -1; background: #fff; border: 1.5px dashed #c7cde0; border-radius: 22px; padding: 3rem 2rem; text-align: center; color: var(--text-muted); font-size: 0.95rem; display: flex; flex-direction: column; align-items: center; gap: 1rem; box-shadow: var(--shadow-sm); }
        .empty-icon { width: 70px; height: 70px; background: #eceeff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); animation: float 5s ease-in-out infinite; }

        .site-footer { text-align: center; padding: 1.5rem 1rem 2.5rem; font-size: 0.82rem; color: var(--text-muted); }

        /* ---------- Upload modal ---------- */
        .modal-overlay { position: fixed; inset: 0; background: rgba(13, 10, 74, 0.65); backdrop-filter: blur(6px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-card { background: #fff; width: 100%; max-width: 800px; border-radius: 24px; box-shadow: var(--shadow-lg); display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
        .modal-overlay.active .modal-card { animation: popIn 0.35s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
        .modal-header { padding: 1.4rem 2.2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #f4f5ff, #fff); }
        .modal-header h2 { font-size: 1.25rem; font-weight: 800; color: var(--navy); display: flex; align-items: center; gap: 12px; }
        .btn-close { background: none; border: none; font-size: 1.8rem; color: var(--text-muted); cursor: pointer; line-height: 1; width: 38px; height: 38px; border-radius: 50%; transition: all 0.2s; }
        .btn-close:hover { color: var(--danger); background: #fef2f2; transform: rotate(90deg); }
        .modal-body { padding: 2rem 2.2rem; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.3rem; }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; }
        .form-control { padding: 0.8rem 1rem; border: 1px solid #dfe3ee; border-radius: 12px; font-size: 0.93rem; font-family: inherit; color: var(--text-dark); background: #fbfbfe; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; }
        .form-control::placeholder { color: #a6aec4; }
        .form-control:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px var(--primary-light); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        select.form-control { appearance: none; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; padding-right: 2.5rem; }
        .file-upload-box { border: 2px dashed #c7cde0; border-radius: 18px; padding: 1.8rem; text-align: center; background: #fbfbfe; transition: all 0.2s; cursor: pointer; position: relative; color: var(--primary); }
        .file-upload-box:hover, .file-upload-box.dragover { border-color: var(--primary); background: #f3f4ff; transform: translateY(-2px); }
        .file-upload-box svg { animation: float 5s ease-in-out infinite; }
        .file-upload-text { font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 10px 0 4px; }
        .file-upload-sub { font-size: 0.8rem; color: var(--text-muted); }
        .file-upload-box input[type="file"] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .modal-footer { padding: 1.3rem 2.2rem; border-top: 1px solid var(--border-light); background: #fbfbfe; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { background: #eceeff; color: var(--primary); border: none; padding: 0.8rem 1.6rem; border-radius: 999px; font-weight: 700; cursor: pointer; transition: background 0.2s; font-family: inherit; }
        .btn-cancel:hover { background: #dfe3ff; }
        .btn-submit-form { background: var(--grad); color: #fff; border: none; padding: 0.8rem 2.1rem; border-radius: 999px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.32); font-family: inherit; }
        .btn-submit-form:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.42); }

        /* ---------- Responsive ---------- */
        @media (max-width: 1300px) { .hero-bg .deco-ico { display: none; } }
        @media (max-width: 1100px) {
            .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
            .navbar { padding: 0 2rem; }
            .hero-section { padding: 3.5rem 2rem 4rem; }
            .main-container { padding: 2.5rem 2rem 3rem; }
        }
        @media (max-width: 1000px) {
            .hero-actions { flex-wrap: wrap; }
            .search-wrapper { flex: 1 1 100%; }
            .category-shortcuts { flex-wrap: wrap; justify-content: center; margin-left: 0; margin-right: 0; }
            .shortcut-pill { flex: 0 1 auto; }
        }
        @media (max-width: 820px) {
            .navbar { height: auto; flex-wrap: wrap; padding: 0.7rem 1rem; row-gap: 0.4rem; }
            .nav-links { order: 3; width: 100%; justify-content: center; }
            .nav-links a { padding: 0.45rem 0.9rem; font-size: 0.85rem; }
            .hero-section { min-height: 0; padding: 2.5rem 1.25rem 3.5rem; }
            .hero-bg .dots, .hero-bg .deco-ico, .hero-bg .band { display: none; }
            #notifDropdown { width: min(340px, 88vw); position: fixed; right: 0.75rem; top: 70px; }
        }
        @media (max-width: 680px) {
            .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .modal-body { padding: 1.4rem; }
            .modal-header, .modal-footer { padding-left: 1.4rem; padding-right: 1.4rem; }
            .hero-actions { flex-direction: column; align-items: stretch; }
            .btn-submit-hero { justify-content: center; }
            .main-container { padding: 2rem 1.25rem 2.5rem; }
            .section-header { flex-wrap: wrap; }
        }

        /* ===== Back to top ===== */
        .back-top { position: fixed; right: 26px; bottom: 26px; width: 48px; height: 48px; border: none; border-radius: 50%; background: linear-gradient(90deg, #1a01b6, rgb(94, 85, 110)); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 26px rgba(91,70,230,0.4); opacity: 0; visibility: hidden; transform: translateY(14px); transition: opacity 0.25s, transform 0.25s, visibility 0.25s; z-index: 900; }
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
            <a href="dashboard.php" class="active">Home</a>
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

    <!-- HERO SECTION -->
    <div class="hero-section">

        <!-- Decorative background shapes -->
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

        <div class="hero-content">
            <h1 class="reveal">Discover. Learn. Innovate.</h1>
            <p class="reveal" style="--d:100ms">Access quality capstone projects, or submit your own research to the academic repository.</p>

            <div class="hero-actions reveal" style="--d:200ms">
                <form class="search-wrapper" action="browse.php" method="GET">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.5" style="margin-right:10px; flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" id="searchInput" class="search-input" placeholder="Search capstones or authors..." autocomplete="off">
                    <button type="submit" class="btn-search">Search</button>
                </form>

                <button type="button" class="btn-submit-hero" onclick="openUploadModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Submit Research
                </button>
            </div>

            <?php if (count($shortcutCategories) > 0): ?>
            <div class="category-shortcuts reveal" style="--d:300ms">
                <?php foreach ($shortcutCategories as $cat): ?>
                    <a href="browse.php?cat=<?= $cat['id'] ?>" class="shortcut-pill">
                        <?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $cat['name'])) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="main-container">

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

        <!-- FEATURE 1: Continue Reading / Jump Back In -->
        <?php if (count($continueReading) > 0): ?>
        <div class="section-wrapper">
            <div class="section-header reveal">
                <h2>
                    <span class="sec-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg></span>
                    Jump Back In
                </h2>
                <a href="library.php" class="view-all">Open Library <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
            </div>

            <div class="grid-3">
                <?php foreach ($continueReading as $i => $doc):
                    $cleanCat = str_replace(['BS ', 'Bachelor of Science in '], '', $doc['category_name']);
                ?>
                    <a href="view_document.php?id=<?= $doc['id'] ?>" class="doc-card continue reveal" style="--d:<?= $i * 80 ?>ms">
                        <div class="doc-meta-top">
                            <span class="doc-cat"><?= e($cleanCat) ?></span>
                        </div>
                        <h3 class="doc-title push"><?= e($doc['title']) ?></h3>
                        <div class="doc-footer tight">
                            <span class="btn-read">Continue Reading <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- FEATURE 2: Recommended for You -->
        <?php if (count($recommendedDocs) > 0): ?>
        <div class="section-wrapper">
            <div class="section-header reveal">
                <h2>
                    <span class="sec-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent-orange)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    Recommended for Your Program
                </h2>
                <a href="browse.php?search=<?= urlencode(str_replace(['BS ', 'Bachelor of Science in '], '', $student['course'])) ?>" class="view-all">View Program <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
            </div>

            <div class="grid-3">
                <?php foreach ($recommendedDocs as $i => $doc):
                    $cleanCat = str_replace(['BS ', 'Bachelor of Science in '], '', $doc['category_name']);
                ?>
                    <a href="view_document.php?id=<?= $doc['id'] ?>" class="doc-card recommended reveal" style="--d:<?= $i * 80 ?>ms">
                        <div class="doc-meta-top">
                            <span class="doc-cat"><?= e($cleanCat) ?></span>
                            <span class="doc-year"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= e($doc['pub_year']) ?></span>
                        </div>
                        <h3 class="doc-title"><?= e($doc['title']) ?></h3>
                        <p class="doc-abstract"><?= strip_tags(e($doc['abstract'])) ?></p>
                        <div class="doc-footer">
                            <span class="btn-read">View Document <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- FEATURE 3: General Recently Added Feed -->
        <div class="section-wrapper">
            <div class="section-header reveal">
                <h2>
                    <span class="sec-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg></span>
                    Recently Published
                </h2>
                <a href="browse.php?sort=newest" class="view-all">Browse All <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></a>
            </div>

            <div class="grid-4">
                <?php if (count($recentDocs) > 0): ?>
                    <?php foreach ($recentDocs as $i => $doc):
                        $cleanCat = str_replace(['BS ', 'Bachelor of Science in '], '', $doc['category_name']);
                    ?>
                        <a href="view_document.php?id=<?= $doc['id'] ?>" class="doc-card reveal" style="--d:<?= $i * 70 ?>ms">
                            <div class="doc-meta-top">
                                <span class="doc-cat neutral"><?= e($cleanCat) ?></span>
                                <span class="doc-year"><?= e($doc['pub_year']) ?></span>
                            </div>
                            <h3 class="doc-title push"><?= e($doc['title']) ?></h3>
                            <div class="doc-footer tight">
                                <span class="btn-read">Read Abstract <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state reveal">
                        <div class="empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                        No documents have been published yet. Check back soon!
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="site-footer">&copy; <?= date('Y') ?> ScholArchive &middot; ACLC College of Mandaue</div>

    <!-- SUBMIT RESEARCH MODAL OVERLAY -->
    <div id="uploadModal" class="modal-overlay">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-card">
            <input type="hidden" name="action" value="submit_doc">

            <div class="modal-header">
                <h2>
                    <span class="sec-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
                    Submit Capstone for Verification
                </h2>
                <button type="button" class="btn-close" onclick="closeUploadModal()" aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Research Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="Enter the complete title of your research paper">
                    </div>

                    <div class="form-group full-width">
                        <label>Authors / Researchers</label>
                        <input type="text" name="authors" class="form-control" required placeholder="Format: Lastname, F., Lastname, F. (Separate with commas)">
                    </div>

                    <div class="form-group">
                        <label>Program Category</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select your course program...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $cat['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Publication Year</label>
                        <select name="pub_year" class="form-control" required>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>A.Y. <?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Research Abstract</label>
                        <textarea name="abstract" class="form-control" required placeholder="Paste your document's abstract or summary here..."></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Attach Capstone Document</label>
                        <div class="file-upload-box" id="dropBox">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            <div class="file-upload-text" id="fileNameDisplay">Click to browse or drag PDF file here</div>
                            <div class="file-upload-sub">Only strictly formatted .PDF files are accepted (Max: 50MB)</div>
                            <input type="file" name="document_file" id="fileInput" accept="application/pdf" required onchange="updateFileName(this)">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel Submission</button>
                <button type="submit" class="btn-submit-form">Submit to Administrator</button>
            </div>
        </form>
    </div>

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

        // ---------- Upload modal ----------
        const modal = document.getElementById('uploadModal');

        function openUploadModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeUploadModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeUploadModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeUploadModal();
        });

        function updateFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.innerText = 'Selected file: ' + input.files[0].name;
                display.style.color = 'var(--primary)';
            } else {
                display.innerText = 'Click to browse or drag PDF file here';
                display.style.color = 'var(--text-dark)';
            }
        }

        // Highlight the drop box while dragging a file over it
        (function() {
            const box = document.getElementById('dropBox');
            ['dragenter', 'dragover'].forEach(ev => box.addEventListener(ev, () => box.classList.add('dragover')));
            ['dragleave', 'drop'].forEach(ev => box.addEventListener(ev, () => box.classList.remove('dragover')));
        })();
    </script>
    <script>
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