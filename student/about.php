<?php
// student/about.php
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

// 2. Fetch Dynamic Notifications for the Navbar
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

// 3. Live system stats (read-only, for the About page visuals)
$statDocs = 0; $statCats = 0;
try { $statDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'published'")->fetchColumn(); } catch (PDOException $e) {}
try { $statCats = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(); } catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About ScholArchive</title>
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

        .btn-icon-nav.active { opacity: 1; background: rgba(255,255,255,0.15); }

        /* ---------- About page ---------- */
        .page-hero-inner { max-width: 1280px; }
        .hero-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1.3rem; }
        .hero-stat-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.28); padding: 7px 18px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .hero-stat-pill strong { font-size: 1.15rem; color: #fcd34d; }

        .main-content { padding: 0 4rem 4rem; max-width: 1360px; margin: 2.5rem auto 0; width: 100%; flex: 1; position: relative; z-index: 10; display: flex; flex-direction: column; gap: 2rem; }
        .sec-ico { width: 34px; height: 34px; border-radius: 11px; background: #eceeff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); }

        .top-cards-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; align-items: stretch; }
        .about-card { background: #fff; border-radius: 22px; padding: 2.8rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-md); display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .about-card.accent::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); }
        .eyebrow { font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.8rem; display: inline-flex; align-items: center; gap: 8px; }
        .about-card h2 { font-size: 1.7rem; font-weight: 800; color: var(--navy); line-height: 1.3; margin-bottom: 1.1rem; letter-spacing: -0.5px; }
        .about-card > p { font-size: 1.03rem; color: #475569; line-height: 1.75; margin-bottom: 2rem; flex-grow: 1; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; text-align: center; border-top: 1px solid var(--border-light); padding-top: 1.8rem; }
        .feature-item { display: flex; flex-direction: column; align-items: center; }
        .feature-icon { width: 52px; height: 52px; background: #eceeff; color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; transition: transform 0.25s, background 0.25s, color 0.25s; }
        .feature-item:hover .feature-icon { transform: translateY(-4px) rotate(-4deg); background: var(--grad); color: #fff; }
        .feature-title { font-size: 0.95rem; font-weight: 800; color: var(--navy); margin-bottom: 6px; line-height: 1.3; }
        .feature-desc { font-size: 0.84rem; color: var(--text-muted); line-height: 1.55; }

        .campus-img { width: 100%; height: 230px; object-fit: cover; border-radius: 16px; margin-bottom: 1.8rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); }
        .about-card h3 { font-size: 1.35rem; font-weight: 800; color: var(--navy); margin-bottom: 0.9rem; }
        .campus-desc { font-size: 0.98rem; line-height: 1.75; color: var(--text-muted); }

        /* Section blocks */
        .section-card { background: #fff; border-radius: 22px; padding: 2.6rem 2.8rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); }
        .section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 0.5rem; }
        .section-head h2 { font-size: 1.45rem; font-weight: 800; color: var(--navy); letter-spacing: -0.5px; }
        .section-sub { color: var(--text-muted); font-size: 0.98rem; line-height: 1.6; margin-bottom: 2rem; max-width: 640px; }

        /* How it works */
        .steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4rem; position: relative; counter-reset: step; }
        .steps::before { content: ""; position: absolute; top: 28px; left: 8%; right: 8%; height: 2px; background: repeating-linear-gradient(90deg, #c7d2fe 0 8px, transparent 8px 16px); z-index: 0; }
        .step { position: relative; z-index: 1; text-align: center; padding: 0 0.4rem; }
        .step-num { width: 56px; height: 56px; border-radius: 50%; background: #fff; border: 2px solid #c7d2fe; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 0 0 6px #fff; transition: background 0.25s, color 0.25s, transform 0.25s; }
        .step:hover .step-num { background: var(--grad); color: #fff; border-color: transparent; transform: scale(1.08); }
        .step h4 { font-size: 0.98rem; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
        .step p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.55; }

        /* Feature cards */
        .feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.3rem; }
        .feat-card { background: #fbfbff; border: 1px solid var(--border-light); border-radius: 18px; padding: 1.6rem; transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s, background 0.25s; position: relative; overflow: hidden; }
        .feat-card::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); transform: scaleY(0); transform-origin: top; transition: transform 0.35s; }
        .feat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: #c7d2fe; background: #fff; }
        .feat-card:hover::before { transform: scaleY(1); }
        .feat-card .sec-ico { width: 44px; height: 44px; border-radius: 14px; margin-bottom: 1rem; }
        .feat-card h4 { font-size: 1rem; font-weight: 800; color: var(--navy); margin-bottom: 6px; }
        .feat-card p { font-size: 0.87rem; color: var(--text-muted); line-height: 1.6; }

        /* System info */
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4rem; }
        .info-block { display: flex; flex-direction: column; gap: 8px; padding: 1.2rem 1.3rem; border-radius: 16px; background: #fbfbff; border: 1px solid var(--border-light); transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s; }
        .info-block:hover { transform: translateY(-3px); border-color: #c7d2fe; box-shadow: var(--shadow-sm); }
        .info-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; display: flex; align-items: center; gap: 8px; }
        .info-label svg { color: var(--primary); }
        .info-value { font-size: 1.02rem; font-weight: 800; color: var(--navy); word-break: break-word; }
        .badge-stable { display: inline-block; margin-left: 6px; font-size: 0.65rem; font-weight: 800; color: #065f46; background: #d1fae5; padding: 3px 9px; border-radius: 999px; vertical-align: middle; letter-spacing: 0.4px; }

        .footer-strip { background: var(--navy); color: rgba(255,255,255,0.85); text-align: center; padding: 1.5rem; font-size: 0.88rem; font-weight: 500; margin-top: auto; }

        @media (max-width: 1300px) { .hero-bg .deco-ico { display: none; } }
        @media (max-width: 1100px) {
            .navbar { padding: 0 2rem; }
            .page-hero { padding: 2.6rem 2rem 2.8rem; }
            .main-content { padding: 0 2rem 3rem; }
            .top-cards-grid { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .feat-grid { grid-template-columns: 1fr 1fr; }
            .steps { grid-template-columns: 1fr 1fr; row-gap: 2rem; }
            .steps::before { display: none; }
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
            .main-content { padding: 0 1.1rem 2.5rem; }
            .about-card, .section-card { padding: 1.7rem; }
            .features-grid, .feat-grid, .info-grid, .steps { grid-template-columns: 1fr; }
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
            <a href="about.php" class="btn-icon-nav active" title="About ScholArchive">
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
            <h1 class="reveal">
                <span class="title-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span>
                About ScholArchive
            </h1>
            <p class="reveal" style="--d:100ms">Learn more about the premier academic repository designed specifically for the students and faculty of ACLC College of Mandaue.</p>
            <div class="hero-pills reveal" style="--d:200ms">
                <span class="hero-stat-pill"><strong data-count="<?= $statDocs ?>"><?= number_format($statDocs) ?></strong> Published Documents</span>
                <span class="hero-stat-pill"><strong data-count="<?= $statCats ?>"><?= number_format($statCats) ?></strong> Programs</span>
            </div>
        </div>
    </header>

    <!-- FLOATING MAIN CONTENT -->
    <main class="main-content">

        <div class="top-cards-grid">

            <!-- Left Card: System Intro & Features -->
            <div class="about-card accent reveal">
                <span class="eyebrow">The Platform</span>
                <h2>ScholArchive<br>Academic Repository System.</h2>
                <p>ScholArchive is a dedicated digital platform that allows ACLC College of Mandaue students to seamlessly access, explore, and learn from a curated collection of capstone projects and research papers created by their peers and predecessors.</p>

                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </div>
                        <div class="feature-title">Access Quality Research</div>
                        <div class="feature-desc">Easily browse and read verified capstone projects and research papers.</div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        </div>
                        <div class="feature-title">Learn &amp; Get Inspired</div>
                        <div class="feature-desc">Gain knowledge and structural inspiration from real, graded student projects.</div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </div>
                        <div class="feature-title">Academic Excellence</div>
                        <div class="feature-desc">Promote continuous collaboration and innovation in future academic work.</div>
                    </div>
                </div>
            </div>

            <!-- Right Card: Campus Info -->
            <div class="about-card reveal" style="--d:100ms">
                <img src="../assets/backgroundaclc.png" alt="ACLC College Mandaue Campus" class="campus-img">
                <span class="eyebrow">The Institution</span>
                <h3>About ACLC College of Mandaue</h3>
                <p class="campus-desc">ACLC College of Mandaue is deeply committed to providing high-quality IT and business education. By empowering students with modern tools like ScholArchive, the institution ensures graduates become competent professionals through innovative, technology-driven learning.</p>
            </div>

        </div>

        <!-- How it works -->
        <section class="section-card reveal">
            <div class="section-head">
                <span class="sec-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                <h2>How ScholArchive Works</h2>
            </div>
            <p class="section-sub">From finding a study reference to sharing your own capstone, everything happens in four simple steps.</p>
            <div class="steps">
                <div class="step">
                    <div class="step-num"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
                    <h4>1. Search &amp; Browse</h4>
                    <p>Look up papers by title, author, program or year from the Browse page.</p>
                </div>
                <div class="step">
                    <div class="step-num"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
                    <h4>2. Read the Abstract</h4>
                    <p>Preview the metadata and abstract, then open the full PDF in the viewer.</p>
                </div>
                <div class="step">
                    <div class="step-num"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg></div>
                    <h4>3. Save to Library</h4>
                    <p>Bookmark useful papers so they are always one click away in My Library.</p>
                </div>
                <div class="step">
                    <div class="step-num"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
                    <h4>4. Share Your Research</h4>
                    <p>Submit your own work. Once reviewed and approved, it is published for everyone.</p>
                </div>
            </div>
        </section>

        <!-- Key features -->
        <section class="section-card reveal">
            <div class="section-head">
                <span class="sec-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></span>
                <h2>What You Can Do</h2>
            </div>
            <p class="section-sub">Everything a student researcher needs, in one place.</p>
            <div class="feat-grid">
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                    <h4>Smart Search &amp; Filters</h4>
                    <p>Find papers fast by keyword, program and year, with matching terms highlighted in the results.</p>
                </div>
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    <h4>Built-in PDF Viewer</h4>
                    <p>Read the full document right on the page, or open it in a new tab for a larger view.</p>
                </div>
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg></span>
                    <h4>Personal Library</h4>
                    <p>Keep your own collection of saved research and sort it by date, title or year.</p>
                </div>
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
                    <h4>Submit Your Capstone</h4>
                    <p>Upload your research with its title, authors, program and abstract for review.</p>
                </div>
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
                    <h4>Instant Notifications</h4>
                    <p>Get notified when new research is published or when your own submission is approved.</p>
                </div>
                <div class="feat-card">
                    <span class="sec-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                    <h4>Secure Student Access</h4>
                    <p>Only verified, active student accounts can sign in and view the repository.</p>
                </div>
            </div>
        </section>

        <!-- Bottom Full Width Card: System Info -->
        <section class="section-card reveal">
            <div class="section-head">
                <span class="sec-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span>
                <h2>System Information</h2>
            </div>
            <p class="section-sub">Details about this release of ScholArchive.</p>

            <div class="info-grid">
                <div class="info-block">
                    <span class="info-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>System Version</span>
                    <span class="info-value">1.1.0<span class="badge-stable">STABLE</span></span>
                </div>

                <div class="info-block">
                    <span class="info-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>Developed By</span>
                    <span class="info-value">The Quintessential</span>
                </div>

                <div class="info-block">
                    <span class="info-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Release Date</span>
                    <span class="info-value">September 30, 2026</span>
                </div>

                <div class="info-block">
                    <span class="info-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Contact / Support</span>
                    <span class="info-value">justineillustrisimo@gmail.com</span>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer Strip -->
    <footer class="footer-strip">
        © <?= date('Y') ?> ACLC College of Mandaue. All rights reserved.
    </footer>

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