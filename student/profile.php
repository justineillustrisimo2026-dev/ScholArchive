<?php
// student/profile.php
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

// Fetch Quick Stats for the UI (Library count)
$savedStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_documents WHERE user_id = ?");
$savedStmt->execute([$student['id']]);
$savedCount = $savedStmt->fetchColumn();

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

// Programs a student can pick (same list as the registration page)
$courseOptions = ['BSIT', 'BSCS', 'BSA', 'BSBA', 'BSHM'];

// 3. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Change Password
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current, $student['password'])) {
            flash_set('error', 'Your current password is incorrect. No changes were made.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'Your new passwords do not match. Please retype them carefully.');
        } elseif (strlen($new) < 6) {
            flash_set('error', 'Security requirement not met: Your new password must be at least 6 characters long.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hash, $student['id']]);
            flash_set('success', 'Success! Your account password has been safely updated.');
        }
        header("Location: profile.php");
        exit;
    }

    // Action B: Update Profile Picture
    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['avatar']['tmp_name'];
            $fileName = basename($_FILES['avatar']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            
            if (in_array($ext, $allowed)) {
                $newName = 'avatar_' . uniqid() . '.' . $ext;
                $uploadPath = __DIR__ . '/../uploads/' . $newName;
                
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Delete old selfie to save server space
                    if (!empty($student['selfie']) && file_exists(__DIR__ . '/../uploads/' . $student['selfie'])) {
                        unlink(__DIR__ . '/../uploads/' . $student['selfie']);
                    }
                    
                    $upd = $pdo->prepare("UPDATE users SET selfie = ? WHERE id = ?");
                    $upd->execute([$newName, $student['id']]);
                    flash_set('success', 'Your profile picture has been updated perfectly.');
                } else {
                    flash_set('error', 'Server Error: Failed to save the image to the secure directory.');
                }
            } else {
                flash_set('error', 'Invalid file type. Only standard JPG and PNG images are allowed.');
            }
        } else {
            flash_set('error', 'Please select a valid image file before clicking save.');
        }
        header("Location: profile.php");
        exit;
    }

    // Action C: Update Email & Course
    if ($action === 'update_details') {
        $newEmail = trim($_POST['email'] ?? '');
        $newCourse = trim($_POST['course'] ?? '');
        $confirmPw = $_POST['details_password'] ?? '';
        $emailChanged = (strcasecmp($newEmail, (string)$student['email']) !== 0);
        $courseChanged = ($newCourse !== (string)$student['course']);

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Please provide a valid email address.');
        } elseif ($courseChanged && !in_array($newCourse, $courseOptions, true)) {
            flash_set('error', 'Please choose a valid course / program.');
        } elseif (!$emailChanged && !$courseChanged) {
            flash_set('success', 'No changes to save. Your details are already up to date.');
        } elseif ($emailChanged && !password_verify($confirmPw, $student['password'])) {
            flash_set('error', 'Enter your current password to change your sign-in email. No changes were made.');
        } else {
            $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupStmt->execute([$newEmail, $student['id']]);
            if ($emailChanged && $dupStmt->fetch()) {
                flash_set('error', 'That email address is already used by another account.');
            } else {
                try {
                    $upd = $pdo->prepare("UPDATE users SET email = ?, course = ? WHERE id = ?");
                    $upd->execute([$newEmail, $newCourse, $student['id']]);
                    flash_set('success', 'Your account details have been updated.');
                } catch (PDOException $e) {
                    flash_set('error', 'That email address is already used by another account.');
                }
            }
        }
        header("Location: profile.php");
        exit;
    }

    // Action D: Remove Profile Picture
    if ($action === 'remove_avatar') {
        if (!empty($student['selfie'])) {
            $oldPath = __DIR__ . '/../uploads/' . basename($student['selfie']);
            if (is_file($oldPath)) { @unlink($oldPath); }
            $upd = $pdo->prepare("UPDATE users SET selfie = '' WHERE id = ?");
            $upd->execute([$student['id']]);
            flash_set('success', 'Your profile photo has been removed.');
        } else {
            flash_set('error', 'You do not have a profile photo to remove.');
        }
        header("Location: profile.php");
        exit;
    }
}

$success = flash_get('success');
$error = flash_get('error');

// Previous sign-in time (stored in the session by the login page, see setup notes)
$hasPrevLoginInfo = array_key_exists('prev_login', $_SESSION);
$prevLogin = $_SESSION['prev_login'] ?? null;
if ($prevLogin) {
    $lastLoginText = date('M j, Y • g:i A', strtotime($prevLogin));
} elseif ($hasPrevLoginInfo) {
    $lastLoginText = 'First sign-in';
} else {
    $lastLoginText = 'Not recorded yet';
}
$hasPhoto = !empty($student['selfie']) && file_exists(__DIR__ . '/../uploads/' . $student['selfie']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile · ScholArchive</title>
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
        @keyframes shine { 0% { transform: translateX(-120%) skewX(-18deg); } 60%, 100% { transform: translateX(260%) skewX(-18deg); } }

        .js .reveal { opacity: 0; }
        .js .reveal.in { opacity: 1; animation: fadeUp 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) var(--d, 0ms) backwards; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .js .reveal { opacity: 1; }
        }

        /* ---------- Top navigation ---------- */
        .navbar { position: sticky; top: 0; z-index: 100; height: 74px; padding: 0 4rem; display: flex; align-items: center; justify-content: space-between; background: rgba(13, 10, 74, 0.94); backdrop-filter: blur(12px); color: var(--white); box-shadow: 0 6px 24px rgba(13, 10, 74, 0.25); transition: box-shadow 0.3s; }        .navbar.scrolled { box-shadow: 0 12px 34px rgba(13, 10, 74, 0.45); }
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

        /* ---------- Alerts ---------- */
        .alert { padding: 1rem 1.2rem 1rem 1.4rem; border-radius: 14px; font-size: 0.93rem; font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 1.6rem; box-shadow: var(--shadow-sm); background: #fff; animation: popIn 0.4s ease both; transition: opacity 0.4s, transform 0.4s; }
        .alert.hide { opacity: 0; transform: translateY(-8px); }
        .alert svg { flex-shrink: 0; }
        .alert > span { flex: 1; }
        .alert-close { background: none; border: none; font-size: 1.3rem; line-height: 1; color: inherit; opacity: 0.5; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; transition: opacity 0.2s, background 0.2s; }
        .alert-close:hover { opacity: 1; background: rgba(0,0,0,0.06); }
        .alert-success { color: #065f46; border: 1px solid #a7f3d0; border-left: 6px solid #10b981; }
        .alert-error { color: #991b1b; border: 1px solid #fecaca; border-left: 6px solid #ef4444; }

        .site-footer { text-align: center; padding: 1.5rem 1rem 2.5rem; font-size: 0.82rem; color: var(--text-muted); }

        /* ---------- Profile layout ---------- */
        .main-content { padding: 2.5rem 4rem 2rem; max-width: 1400px; margin: 0 auto; width: 100%; flex: 1; display: flex; flex-direction: column; }
        .profile-grid { display: grid; grid-template-columns: 380px 1fr; gap: 2.5rem; align-items: start; }

        /* Digital ID card */
        .id-card { position: relative; overflow: hidden; color: #fff; border-radius: 26px; padding: 2.2rem 1.9rem; box-shadow: var(--shadow-lg); border: 1px solid rgba(255,255,255,0.12);
            background: linear-gradient(145deg, #2a1fc4 0%, #1a1280 45%, #0d0a4a 100%); transition: transform 0.3s, box-shadow 0.3s; position: sticky; top: 96px; }
        .id-card:hover { transform: translateY(-4px); box-shadow: 0 40px 70px -24px rgba(40, 30, 140, 0.5); }
        .id-card > * { position: relative; z-index: 2; }
        .id-card .id-bg { position: absolute; inset: 0; z-index: 1; pointer-events: none; overflow: hidden; }
        .id-bg .glow { position: absolute; border-radius: 50%; filter: blur(50px); }
        .id-bg .g1 { width: 300px; height: 300px; top: -140px; right: -100px; background: radial-gradient(circle, rgba(124, 58, 237, 0.6), transparent 70%); }
        .id-bg .g2 { width: 280px; height: 280px; bottom: -140px; left: -110px; background: radial-gradient(circle, rgba(59, 130, 246, 0.5), transparent 70%); animation-delay: -3s; }
        .id-bg .ring { position: absolute; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.12);  }
        .id-bg .r1 { width: 260px; height: 260px; bottom: -120px; right: -70px; }
        .id-bg .r2 { width: 180px; height: 180px; bottom: -70px; right: -20px; border-color: rgba(255,255,255,0.08); animation-delay: -3s; }
        .id-bg .dots { position: absolute; top: 16px; left: 16px; width: 120px; height: 70px; color: rgba(255,255,255,0.2); background-image: radial-gradient(circle, currentColor 1.5px, transparent 1.6px); background-size: 20px 20px; }

        .id-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.8rem; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 1rem; }
        .id-card-brand { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; opacity: 0.95; }
        .id-card-brand img { width: 26px; height: 26px; border-radius: 50%; background: #fff; padding: 1px; object-fit: contain; }
        .id-card-tag { background: rgba(255,255,255,0.15); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.22); padding: 4px 12px; border-radius: 999px; font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }

        .avatar-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 1.8rem; }
        .profile-avatar-wrap { position: relative; margin-bottom: 1rem; display: inline-block; }
        .profile-avatar-wrap::before { content: ""; position: absolute; inset: -8px; border-radius: 50%; border: 1.5px dashed rgba(255,255,255,0.35); animation: ringPulse 5s ease-in-out infinite; }
        .large-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 10px 24px rgba(0,0,0,0.35); background: #eceeff; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: var(--primary); }
        .upload-btn { position: absolute; bottom: 2px; right: 2px; background: var(--accent-orange); color: #fff; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 6px 14px rgba(0,0,0,0.3); transition: transform 0.2s; border: 3px solid #1a1280; }
        .upload-btn:hover { transform: scale(1.12) rotate(-6deg); }
        #avatar-input { display: none; }
        .profile-name { font-size: 1.5rem; font-weight: 800; color: #fff; text-align: center; line-height: 1.2; margin-bottom: 8px; text-shadow: 0 2px 6px rgba(0,0,0,0.4); }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; color: #a7f3d0; background: rgba(16,185,129,0.15); border: 1px solid rgba(167,243,208,0.3); padding: 4px 12px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; }

        .id-details { display: flex; flex-direction: column; gap: 12px; }
        .glass-info { background: rgba(255,255,255,0.09); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.14); padding: 13px 15px; border-radius: 16px; display: flex; align-items: center; gap: 12px; transition: background 0.2s, transform 0.2s; }
        .glass-info:hover { background: rgba(255,255,255,0.15); transform: translateX(4px); }
        .glass-icon { width: 38px; height: 38px; background: rgba(255,255,255,0.16); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .glass-text { display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
        .glass-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.6px; opacity: 0.7; font-weight: 700; }
        .glass-value { font-size: 0.95rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff; }

        .btn-avatar-save { background: #fff; color: var(--primary); border: none; padding: 0.7rem 1.2rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; cursor: pointer; margin-top: 4px; margin-bottom: 14px; display: none; width: 100%; transition: transform 0.15s, box-shadow 0.15s; box-shadow: 0 8px 18px rgba(0,0,0,0.2); font-family: inherit; animation: popIn 0.3s ease both; }
        .btn-avatar-save:hover { transform: translateY(-2px); box-shadow: 0 12px 22px rgba(0,0,0,0.28); }

        /* Right panel */
        .right-panel { display: flex; flex-direction: column; gap: 2rem; min-width: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .stat-card { position: relative; overflow: hidden; background: #fff; border-radius: 20px; padding: 1.6rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1.2rem; transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s; }
        .stat-card > * { position: relative; z-index: 1; }
        .stat-card::before { content: ""; position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: linear-gradient(180deg, #3b82f6, #7c3aed); transform: scaleY(0); transform-origin: top; transition: transform 0.35s; z-index: 2; }
        .stat-card::after { content: ""; position: absolute; inset: 0; background: radial-gradient(380px circle at var(--mx, 50%) var(--my, 50%), rgba(99, 102, 241, 0.13), transparent 45%); opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 0; }
        .stat-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-md); border-color: #c7d2fe; }
        .stat-card:hover::before { transform: scaleY(1); }
        .stat-card::after { display: none; }        
        .stat-icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon.blue { background: #eceeff; color: var(--primary); }
        .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-info { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.6rem; font-weight: 800; color: var(--navy); line-height: 1; margin-bottom: 6px; letter-spacing: -0.5px; }
        .stat-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; }

        .settings-card { background: #fff; border-radius: 22px; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); overflow: hidden; transition: box-shadow 0.25s; }
        .settings-card:hover { box-shadow: var(--shadow-md); }
        .card-header { background: linear-gradient(135deg, #f4f5ff, #fff); padding: 1.6rem 2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .card-header-text h3 { font-size: 1.25rem; font-weight: 800; color: var(--navy); margin-bottom: 4px; letter-spacing: -0.3px; }
        .card-header-text p { font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; }
        .shield-icon { color: var(--success); background: #dcfce7; width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; animation: float 5s ease-in-out infinite; }
        .card-body { padding: 2rem; display: flex; flex-direction: column; gap: 1.4rem; background: #fbfbfe; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.4rem; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; }
        .form-control { height: 48px; padding: 0 1.1rem; border: 1px solid #dfe3ee; border-radius: 12px; font-size: 0.93rem; font-family: inherit; color: var(--text-dark); background: #fff; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .form-control::placeholder { color: #a6aec4; }

        .security-rules { background: #fff; border: 1px solid var(--border-light); border-radius: 14px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .rule { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
        .rule svg { color: var(--success); background: #dcfce7; border-radius: 50%; padding: 3px; width: 20px; height: 20px; flex-shrink: 0; }

        .card-footer { padding: 1.3rem 2rem; background: #fff; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; }
        .btn-submit { display: inline-flex; align-items: center; gap: 8px; background: var(--grad); color: #fff; border: none; padding: 0.85rem 2rem; border-radius: 999px; font-weight: 700; font-size: 0.93rem; cursor: pointer; font-family: inherit; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.32); transition: transform 0.15s, box-shadow 0.15s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.42); }

        @media (max-width: 1300px) { .hero-bg .deco-ico { display: none; } }
        @media (max-width: 1100px) {
            .navbar { padding: 0 2rem; }
            .page-hero { padding: 2.8rem 2rem 3rem; }
            .main-content { padding: 2rem; }
        }
        @media (max-width: 1024px) {
            .profile-grid { grid-template-columns: 1fr; }
            .id-card { max-width: 520px; margin: 0 auto; width: 100%; position: relative; top: 0; }
        }
        @media (max-width: 820px) {
            .navbar { height: auto; flex-wrap: wrap; padding: 0.7rem 1rem; row-gap: 0.4rem; }
            .nav-links { order: 3; width: 100%; justify-content: center; }
            .nav-links a { padding: 0.45rem 0.9rem; font-size: 0.85rem; }
            .page-hero { padding: 2.2rem 1.25rem 2.6rem; }
            .hero-bg .dots, .hero-bg .band { display: none; }
            #notifDropdown { width: min(340px, 88vw); position: fixed; right: 0.75rem; top: 70px; }
        }
        @media (max-width: 680px) {
            .main-content { padding: 1.5rem 1.1rem; }
            .stats-grid, .form-grid { grid-template-columns: 1fr; }
            .card-header, .card-body, .card-footer { padding-left: 1.3rem; padding-right: 1.3rem; }
            .security-rules { flex-direction: column; align-items: flex-start; }
            .shield-icon { display: none; }
        }

        /* ---------- Account details form ---------- */
        select.form-control { appearance: none; cursor: pointer; padding-right: 2.6rem; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; }
        .field-hint { font-size: 0.78rem; color: var(--text-muted); line-height: 1.5; }
        .form-group[hidden] { display: none; }
        .form-control[readonly] { background: #f1f2fa; color: var(--text-muted); cursor: not-allowed; }
        button.upload-btn { padding: 0; font-family: inherit; }

        /* ---------- Profile photo modal ---------- */
        .pm-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(13, 10, 74, 0.72); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; padding: 1rem; }
        .pm-overlay.open { display: flex; }
        .pm-card { background: #fff; width: 100%; max-width: 560px; max-height: 96vh; overflow-y: auto; border-radius: 24px; padding: 1.2rem 1.5rem 1.5rem; box-shadow: var(--shadow-lg); animation: popIn 0.3s ease both; }
        .pm-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; }
        .pm-head h4 { font-size: 1.2rem; font-weight: 800; color: var(--navy); letter-spacing: -0.3px; }
        .pm-x { background: none; border: none; font-size: 1.7rem; line-height: 1; color: var(--text-muted); cursor: pointer; width: 38px; height: 38px; border-radius: 50%; transition: background 0.2s, color 0.2s; }
        .pm-x:hover { background: #fef2f2; color: var(--danger); }
        .pm-step[hidden] { display: none; }
        .pm-hint { font-size: 0.88rem; color: #4b5578; line-height: 1.5; margin-bottom: 1rem; }
        .pm-options { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .pm-opt { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; padding: 1.6rem 1rem; border: 1.5px solid var(--border-light); border-radius: 18px; background: #fbfbfe; cursor: pointer; font-family: inherit; color: var(--text-dark); transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s, background 0.2s; }
        .pm-opt:not(:disabled):hover { transform: translateY(-4px); border-color: #c7d2fe; box-shadow: var(--shadow-md); background: #fff; }
        .pm-opt:disabled { opacity: 0.5; cursor: not-allowed; }
        .pm-opt .pm-ico { width: 56px; height: 56px; border-radius: 16px; background: #eceeff; color: var(--primary); display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
        .pm-opt strong { font-size: 0.98rem; font-weight: 800; color: var(--navy); }
        .pm-opt span.sub { font-size: 0.78rem; color: var(--text-muted); }
        .pm-note { margin-top: 0.9rem; font-size: 0.8rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 0.6rem 0.9rem; border-radius: 12px; }
        .pm-note[hidden] { display: none; }
        .pm-remove { margin-top: 1.1rem; width: 100%; height: 44px; border-radius: 999px; border: none; background: #fef2f2; color: var(--danger); font-family: inherit; font-weight: 700; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; }
        .pm-remove:hover { background: #fee2e2; }

        .pm-stage { position: relative; width: 100%; aspect-ratio: 4 / 3; background: #0b0b24; border-radius: 16px; overflow: hidden; }
        .pm-stage video, .pm-stage > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .pm-stage.mirror video { transform: scaleX(-1); }
        .pm-guide { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); height: 86%; aspect-ratio: 1 / 1; border-radius: 50%; border: 3px dashed rgba(255,255,255,0.95); box-shadow: 0 0 0 999px rgba(8, 6, 40, 0.5); pointer-events: none; }
        .pm-stage.failed .pm-guide { display: none; }
        .pm-count { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; font-size: 6rem; font-weight: 800; color: #fff; text-shadow: 0 6px 24px rgba(0,0,0,0.55); pointer-events: none; }
        .pm-msg { position: absolute; inset: 0; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 0.4rem; text-align: center; padding: 1.5rem; color: #fff; background: #1a1760; font-size: 0.9rem; line-height: 1.5; }
        .pm-msg strong { font-size: 1.02rem; }

        .pm-crop-wrap { display: flex; justify-content: center; }
        .pm-crop-wrap canvas { width: 360px; max-width: 100%; aspect-ratio: 1 / 1; border-radius: 16px; background: #0b0b24; cursor: grab; touch-action: none; }
        .pm-crop-wrap canvas.dragging { cursor: grabbing; }
        .pm-zoom { display: flex; align-items: center; gap: 10px; margin: 1rem auto 0; max-width: 360px; color: var(--text-muted); }
        .pm-zoom input[type=range] { flex: 1; accent-color: #5b46e6; cursor: pointer; }
        .pm-actions { display: flex; align-items: center; gap: 0.6rem; margin-top: 1.1rem; flex-wrap: wrap; }
        .pm-actions .grow { flex: 1; }
        .pm-select { height: 40px; max-width: 200px; border: 1px solid #dfe3ee; border-radius: 12px; font-family: inherit; font-size: 0.8rem; padding: 0 0.7rem; background: #fbfbfe; }
        .pm-select[hidden] { display: none; }
        .pm-btn { height: 44px; padding: 0 1.5rem; border-radius: 999px; border: none; font-family: inherit; font-size: 0.9rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.15s, box-shadow 0.15s, background 0.2s; }
        .pm-btn[hidden] { display: none; }
        .pm-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
        .pm-btn.primary { background: var(--grad); color: #fff; box-shadow: 0 10px 22px rgba(91, 70, 230, 0.32); }
        .pm-btn.primary:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(91, 70, 230, 0.42); }
        .pm-btn.ghost { background: #eceeff; color: var(--primary); }
        .pm-btn.ghost:not(:disabled):hover { background: #dfe3ff; }

        @media (max-width: 560px) { .pm-options { grid-template-columns: 1fr; } }

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
            <div style="position: relative;" class="avatar-wrap">
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
                    <span class="title-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    Account Settings
                </h1>
                <p class="reveal" style="--d:160ms">Manage your security credentials and view your digital scholar ID.</p>
            </div>
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

        <div class="profile-grid">

            <!-- LEFT PANEL: Digital ID Card -->
            <div class="id-card reveal">
                <div class="id-bg" aria-hidden="true">
                    <span class="glow g1"></span>
                    <span class="glow g2"></span>
                    <span class="ring r1"></span>
                    <span class="ring r2"></span>
                    <span class="dots"></span>
                    <span class="shine"></span>
                </div>

                <div class="id-card-header">
                    <div class="id-card-brand">
                        <img src="../assets/logo1.png" alt="Logo">
                        ScholArchive
                    </div>
                    <div class="id-card-tag">Student ID</div>
                </div>

                <div class="avatar-section">
                    <form action="profile.php" method="POST" enctype="multipart/form-data" id="avatarForm" style="display:flex; flex-direction:column; align-items:center; width:100%;">
                        <input type="hidden" name="action" value="update_avatar">
                        <div class="profile-avatar-wrap">
                            <?php if (!empty($student['selfie']) && file_exists('../uploads/' . $student['selfie'])): ?>
                                <img src="../uploads/<?= e($student['selfie']) ?>" alt="Profile Picture" class="large-avatar" id="avatarPreview">
                            <?php else: ?>
                                <div class="large-avatar" id="avatarPreview"><?= get_initials($student['first_name'], $student['last_name']) ?></div>
                            <?php endif; ?>

                            <button type="button" class="upload-btn" id="openPhotoModal" title="Change profile photo" aria-label="Change profile photo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg>
                            </button>
                            <input type="file" id="avatar-input" name="avatar" accept="image/png, image/jpeg">
                        </div>
                    </form>

                    <h2 class="profile-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                    <div class="status-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                        Verified Scholar
                    </div>
                </div>

                <div class="id-details">
                    <div class="glass-info">
                        <div class="glass-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg></div>
                        <div class="glass-text">
                            <span class="glass-label">ID Number</span>
                            <span class="glass-value" style="font-family: monospace; letter-spacing: 1px;"><?= e($student['student_id']) ?></span>
                        </div>
                    </div>

                    <div class="glass-info">
                        <div class="glass-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                        <div class="glass-text">
                            <span class="glass-label">Program &amp; Level</span>
                            <span class="glass-value"><?= e($student['course']) ?> - <?= e($student['year_level']) ?></span>
                        </div>
                    </div>

                    <div class="glass-info">
                        <div class="glass-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></div>
                        <div class="glass-text">
                            <span class="glass-label">Registered Email</span>
                            <span class="glass-value"><?= e($student['email']) ?></span>
                        </div>
                    </div>

                    <div class="glass-info">
                        <div class="glass-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <div class="glass-text">
                            <span class="glass-label">Last Login</span>
                            <span class="glass-value"><?= e($lastLoginText) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL -->
            <div class="right-panel">

                <div class="stats-grid">
                    <div class="stat-card reveal" style="--d:80ms">
                        <div class="stat-icon blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value" data-count="<?= (int)$savedCount ?>"><?= number_format($savedCount) ?></div>
                            <div class="stat-label">Documents Saved</div>
                        </div>
                    </div>

                    <div class="stat-card reveal" style="--d:160ms">
                        <div class="stat-icon green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= date('M Y', strtotime($student['created_at'])) ?></div>
                            <div class="stat-label">Member Since</div>
                        </div>
                    </div>
                </div>

                <!-- Account details form -->
                <form action="profile.php" method="POST" class="settings-card reveal" style="--d:140ms" id="detailsForm">
                    <input type="hidden" name="action" value="update_details">

                    <div class="card-header">
                        <div class="card-header-text">
                            <h3>Account Details</h3>
                            <p>Keep your sign-in email and program up to date. Changes apply right away.</p>
                        </div>
                        <div class="shield-icon" style="color: var(--primary); background: #eceeff;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?= e($student['first_name'] . ' ' . $student['last_name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control" value="<?= e($student['student_id']) ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="detEmail">Email Address (used to sign in)</label>
                                <input type="email" id="detEmail" name="email" class="form-control" required value="<?= e($student['email']) ?>" data-original="<?= e($student['email']) ?>" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="detCourse">Course / Program</label>
                                <select id="detCourse" name="course" class="form-control" required>
                                    <?php if (!in_array($student['course'], $courseOptions, true)): ?>
                                        <option value="<?= e($student['course']) ?>" selected><?= e($student['course']) ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($courseOptions as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= $student['course'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group full-width" id="detPwGroup" hidden>
                                <label class="form-label" for="detPassword">Current Password</label>
                                <input type="password" id="detPassword" name="details_password" class="form-control" placeholder="Enter your current password to confirm the new email" autocomplete="current-password">
                                <span class="field-hint">Required only when you change your email, because it is the address you sign in with.</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn-submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                            Save Changes
                        </button>
                    </div>
                </form>

                <!-- Security form -->
                <form action="profile.php" method="POST" class="settings-card reveal" style="--d:200ms">
                    <input type="hidden" name="action" value="change_password">

                    <div class="card-header">
                        <div class="card-header-text">
                            <h3>Security &amp; Authentication</h3>
                            <p>Update your password regularly to keep your research account secure.</p>
                        </div>
                        <div class="shield-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="form-group full-width">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="Enter your current password to verify identity">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required placeholder="Minimum 6 characters" minlength="6">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="Retype new password" minlength="6">
                            </div>
                        </div>

                        <div class="security-rules">
                            <div class="rule"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Minimum 6 characters long</div>
                            <div class="rule"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Cannot be empty</div>
                            <div class="rule"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Passwords must match</div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn-submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Update Password Securely
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <div class="site-footer">&copy; <?= date('Y') ?> ScholArchive &middot; ACLC College of Mandaue</div>

    <?php if ($hasPhoto): ?>
    <form id="removeAvatarForm" action="profile.php" method="POST" hidden>
        <input type="hidden" name="action" value="remove_avatar">
    </form>
    <?php endif; ?>

    <!-- PROFILE PHOTO MODAL -->
    <div class="pm-overlay" id="photoModal" role="dialog" aria-modal="true" aria-labelledby="pmTitle">
        <div class="pm-card">
            <div class="pm-head">
                <h4 id="pmTitle">Profile photo</h4>
                <button type="button" class="pm-x" id="pmClose" aria-label="Close">&times;</button>
            </div>

            <!-- Step 1: choose -->
            <div class="pm-step" id="pmChoose">
                <p class="pm-hint">Choose how you would like to update your profile photo. You can adjust the crop before saving.</p>
                <div class="pm-options">
                    <button type="button" class="pm-opt" id="pmOptCamera">
                        <span class="pm-ico"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg></span>
                        <strong>Take a photo</strong>
                        <span class="sub">Use this computer's webcam</span>
                    </button>
                    <button type="button" class="pm-opt" id="pmOptFile">
                        <span class="pm-ico"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
                        <strong>Choose from computer</strong>
                        <span class="sub">JPG or PNG image</span>
                    </button>
                </div>
                <p class="pm-note" id="pmCamNote" hidden>The webcam is not available on this connection, so only "Choose from computer" can be used.</p>
                <p class="pm-note" id="pmFileNote" hidden></p>
                <?php if ($hasPhoto): ?>
                <button type="button" class="pm-remove" id="pmRemove">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    Remove current photo
                </button>
                <?php endif; ?>
                <input type="file" id="pmFile" accept="image/png, image/jpeg" hidden>
            </div>

            <!-- Step 2: webcam -->
            <div class="pm-step" id="pmCamera" hidden>
                <p class="pm-hint">Face the camera in good light. A 3-second countdown starts when you press Capture.</p>
                <div class="pm-stage mirror" id="pmStage">
                    <video id="pmVideo" autoplay playsinline muted></video>
                    <div class="pm-guide"></div>
                    <div class="pm-count" id="pmCount"></div>
                    <div class="pm-msg" id="pmMsg"></div>
                </div>
                <div class="pm-actions">
                    <button type="button" class="pm-btn ghost" id="pmCamBack">Back</button>
                    <select id="pmSelect" class="pm-select" hidden aria-label="Choose camera"></select>
                    <span class="grow"></span>
                    <button type="button" class="pm-btn ghost" id="pmRetry" hidden>Retry</button>
                    <button type="button" class="pm-btn primary" id="pmCapture">Capture</button>
                </div>
            </div>

            <!-- Step 3: crop -->
            <div class="pm-step" id="pmCrop" hidden>
                <p class="pm-hint">Drag the photo to reposition it and use the slider to zoom. The circle shows what will be saved.</p>
                <div class="pm-crop-wrap"><canvas id="pmCanvas" width="360" height="360"></canvas></div>
                <div class="pm-zoom">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    <input type="range" id="pmZoom" min="1" max="3" step="0.01" value="1" aria-label="Zoom">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="11" y1="8" x2="11" y2="14"/></svg>
                </div>
                <div class="pm-actions">
                    <button type="button" class="pm-btn ghost" id="pmCropBack">Back</button>
                    <span class="grow"></span>
                    <button type="button" class="pm-btn primary" id="pmSave">Save photo</button>
                </div>
            </div>
        </div>
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

            // Stat card spotlight follows the mouse
            document.addEventListener('mousemove', function(e) {
                const card = e.target.closest && e.target.closest('.stat-card');
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
        })();

        // ---------- Profile photo: webcam / file -> crop -> save ----------
        (function () {
            const $ = id => document.getElementById(id);
            const modal = $('photoModal');
            if (!modal) return;
            const steps = { choose: $('pmChoose'), camera: $('pmCamera'), crop: $('pmCrop') };
            const video = $('pmVideo'), stage = $('pmStage'), countEl = $('pmCount'), msgEl = $('pmMsg');
            const selectEl = $('pmSelect'), captureBtn = $('pmCapture'), retryBtn = $('pmRetry');
            const canvas = $('pmCanvas'), ctx = canvas.getContext('2d'), zoomEl = $('pmZoom');
            const fileInput = $('pmFile'), fileNote = $('pmFileNote'), camNote = $('pmCamNote');
            const camOpt = $('pmOptCamera'), saveBtn = $('pmSave');
            const MAX_BYTES = 8 * 1024 * 1024;
            const CW = 360, D = 300, OUT = 512;

            let stream = null, countTimer = null;
            let img = null, scale = 1, minScale = 1, ox = 0, oy = 0, saving = false;

            const camSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && (window.isSecureContext !== false);
            if (!camSupported) { camOpt.disabled = true; camNote.hidden = false; }

            function show(name) {
                Object.keys(steps).forEach(k => { steps[k].hidden = (k !== name); });
            }
            function stopCamera() {
                clearInterval(countTimer); countTimer = null;
                countEl.style.display = 'none';
                if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
                video.srcObject = null;
            }
            function openModal() {
                fileNote.hidden = true;
                show('choose');
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
            function closeModal() {
                stopCamera();
                modal.classList.remove('open');
                document.body.style.overflow = '';
                img = null;
            }

            $('openPhotoModal').addEventListener('click', openModal);
            $('pmClose').addEventListener('click', closeModal);
            modal.addEventListener('mousedown', e => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

            // ----- Remove photo -----
            const rm = $('pmRemove');
            if (rm) rm.addEventListener('click', () => {
                if (confirm('Remove your profile photo? Your initials will be shown instead.')) {
                    $('removeAvatarForm').submit();
                }
            });

            // ----- Choose from computer -----
            $('pmOptFile').addEventListener('click', () => { fileInput.value = ''; fileInput.click(); });
            fileInput.addEventListener('change', () => {
                const f = fileInput.files && fileInput.files[0];
                if (!f) return;
                if (!/^image\/(jpeg|png)$/.test(f.type)) {
                    fileNote.textContent = 'Please choose a JPG or PNG image.'; fileNote.hidden = false; return;
                }
                if (f.size > MAX_BYTES) {
                    fileNote.textContent = 'That image is larger than 8 MB. Please choose a smaller one.'; fileNote.hidden = false; return;
                }
                const url = URL.createObjectURL(f);
                const im = new Image();
                im.onload = () => { URL.revokeObjectURL(url); startCrop(im, 'choose'); };
                im.onerror = () => { URL.revokeObjectURL(url); fileNote.textContent = 'That file could not be read as an image.'; fileNote.hidden = false; };
                im.src = url;
            });

            // ----- Webcam -----
            function showCamMsg(title, text, canRetry) {
                msgEl.innerHTML = '<strong></strong><span></span>';
                msgEl.firstChild.textContent = title;
                msgEl.lastChild.textContent = text;
                msgEl.style.display = 'flex';
                stage.classList.add('failed');
                captureBtn.hidden = true;
                retryBtn.hidden = !canRetry;
            }
            async function startCamera(deviceId) {
                stopCamera();
                msgEl.style.display = 'none';
                stage.classList.remove('failed');
                captureBtn.hidden = false; captureBtn.disabled = true; retryBtn.hidden = true;
                try {
                    const video_c = deviceId
                        ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
                        : { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } };
                    stream = await navigator.mediaDevices.getUserMedia({ video: video_c, audio: false });
                    video.srcObject = stream;
                    await video.play().catch(() => {});
                    captureBtn.disabled = false;
                    const devs = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
                    if (devs.length > 1) {
                        const cur = stream.getVideoTracks()[0].getSettings().deviceId;
                        selectEl.innerHTML = '';
                        devs.forEach((d, i) => {
                            const o = document.createElement('option');
                            o.value = d.deviceId; o.textContent = d.label || ('Camera ' + (i + 1));
                            if (d.deviceId === cur) o.selected = true;
                            selectEl.appendChild(o);
                        });
                        selectEl.hidden = false;
                    } else { selectEl.hidden = true; }
                } catch (err) {
                    const n = err && err.name;
                    if (n === 'NotAllowedError' || n === 'SecurityError') {
                        showCamMsg('Camera permission was blocked', 'Click the camera icon in the address bar, allow access, then press Retry. Or go back and choose a file instead.', true);
                    } else if (n === 'NotFoundError' || n === 'OverconstrainedError') {
                        showCamMsg('No camera found', 'Connect a webcam and press Retry, or go back and choose a file from this computer.', true);
                    } else if (n === 'NotReadableError') {
                        showCamMsg('Camera is busy', 'Another app or tab is using the camera. Close it and press Retry.', true);
                    } else {
                        showCamMsg('Camera unavailable', 'Go back and choose a file from this computer instead.', true);
                    }
                }
            }
            camOpt.addEventListener('click', () => { show('camera'); startCamera(); });
            selectEl.addEventListener('change', () => startCamera(selectEl.value));
            retryBtn.addEventListener('click', () => startCamera());
            $('pmCamBack').addEventListener('click', () => { stopCamera(); show('choose'); });

            function grabFrame() {
                const w = video.videoWidth, h = video.videoHeight;
                if (!w || !h) return;
                const c = document.createElement('canvas');
                c.width = w; c.height = h;
                const x = c.getContext('2d');
                x.translate(w, 0); x.scale(-1, 1);        // un-mirror so it matches the preview
                x.drawImage(video, 0, 0, w, h);
                const snap = new Image();
                snap.onload = () => { stopCamera(); startCrop(snap, 'camera'); };
                snap.src = c.toDataURL('image/jpeg', 0.95);
            }
            captureBtn.addEventListener('click', () => {
                if (countTimer) return;
                let n = 3;
                captureBtn.disabled = true;
                countEl.textContent = n; countEl.style.display = 'flex';
                countTimer = setInterval(() => {
                    n--;
                    if (n > 0) { countEl.textContent = n; return; }
                    clearInterval(countTimer); countTimer = null;
                    countEl.style.display = 'none';
                    grabFrame();
                }, 1000);
            });

            // ----- Cropper -----
            let backTo = 'choose';
            function clamp() {
                const w = img.width * scale, h = img.height * scale;
                const maxX = Math.max(0, (w - D) / 2), maxY = Math.max(0, (h - D) / 2);
                ox = Math.min(maxX, Math.max(-maxX, ox));
                oy = Math.min(maxY, Math.max(-maxY, oy));
            }
            function draw() {
                if (!img) return;
                ctx.clearRect(0, 0, CW, CW);
                ctx.fillStyle = '#0b0b24'; ctx.fillRect(0, 0, CW, CW);
                const w = img.width * scale, h = img.height * scale;
                ctx.drawImage(img, CW / 2 - w / 2 + ox, CW / 2 - h / 2 + oy, w, h);
                ctx.save();
                ctx.fillStyle = 'rgba(8,6,40,0.62)';
                ctx.beginPath();
                ctx.rect(0, 0, CW, CW);
                ctx.arc(CW / 2, CW / 2, D / 2, 0, Math.PI * 2, true);
                ctx.fill('evenodd');
                ctx.restore();
                ctx.beginPath();
                ctx.arc(CW / 2, CW / 2, D / 2, 0, Math.PI * 2);
                ctx.lineWidth = 2; ctx.strokeStyle = 'rgba(255,255,255,0.95)'; ctx.stroke();
            }
            function setZoom(z) {
                const prev = scale;
                scale = minScale * z;
                const r = scale / prev;
                ox *= r; oy *= r;
                clamp(); draw();
            }
            function startCrop(image, from) {
                img = image; backTo = from;
                minScale = Math.max(D / img.width, D / img.height);
                scale = minScale; ox = 0; oy = 0;
                zoomEl.value = 1;
                saveBtn.disabled = false; saveBtn.textContent = 'Save photo';
                show('crop'); draw();
            }
            zoomEl.addEventListener('input', () => setZoom(parseFloat(zoomEl.value)));
            canvas.addEventListener('wheel', e => {
                e.preventDefault();
                const z = Math.min(3, Math.max(1, parseFloat(zoomEl.value) + (e.deltaY < 0 ? 0.08 : -0.08)));
                zoomEl.value = z; setZoom(z);
            }, { passive: false });

            let drag = null;
            canvas.addEventListener('pointerdown', e => {
                drag = { x: e.clientX, y: e.clientY, ox: ox, oy: oy };
                canvas.setPointerCapture(e.pointerId);
                canvas.classList.add('dragging');
            });
            canvas.addEventListener('pointermove', e => {
                if (!drag) return;
                const k = CW / canvas.getBoundingClientRect().width;
                ox = drag.ox + (e.clientX - drag.x) * k;
                oy = drag.oy + (e.clientY - drag.y) * k;
                clamp(); draw();
            });
            const endDrag = () => { drag = null; canvas.classList.remove('dragging'); };
            canvas.addEventListener('pointerup', endDrag);
            canvas.addEventListener('pointercancel', endDrag);

            $('pmCropBack').addEventListener('click', () => {
                img = null;
                if (backTo === 'camera') { show('camera'); startCamera(); } else { show('choose'); }
            });

            saveBtn.addEventListener('click', () => {
                if (!img || saving) return;
                saving = true; saveBtn.disabled = true; saveBtn.textContent = 'Saving…';
                const out = document.createElement('canvas');
                out.width = OUT; out.height = OUT;
                const o = out.getContext('2d');
                o.fillStyle = '#ffffff'; o.fillRect(0, 0, OUT, OUT);
                const k = OUT / D;
                const w = img.width * scale * k, h = img.height * scale * k;
                o.drawImage(img, OUT / 2 - w / 2 + ox * k, OUT / 2 - h / 2 + oy * k, w, h);
                out.toBlob(blob => {
                    if (!blob) { saving = false; saveBtn.disabled = false; saveBtn.textContent = 'Save photo'; return; }
                    const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    $('avatar-input').files = dt.files;
                    $('avatarForm').submit();
                }, 'image/jpeg', 0.9);
            });
        })();

        // ---------- Email change: ask for the current password ----------
        (function () {
            const email = document.getElementById('detEmail');
            const grp = document.getElementById('detPwGroup');
            const pw = document.getElementById('detPassword');
            if (!email || !grp || !pw) return;
            const sync = () => {
                const changed = email.value.trim().toLowerCase() !== (email.dataset.original || '').trim().toLowerCase();
                grp.hidden = !changed;
                pw.required = changed;
                if (!changed) pw.value = '';
            };
            email.addEventListener('input', sync);
            sync();
        })();

        // ---------- Back to top ----------
        (function () {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'back-top'; b.setAttribute('aria-label', 'Back to top');
            b.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
            document.body.appendChild(b);
            const t = () => b.classList.toggle('show', window.scrollY > 400);
            window.addEventListener('scroll', t, { passive: true }); t();
            b.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        })();
    </script>
</body>
</html>