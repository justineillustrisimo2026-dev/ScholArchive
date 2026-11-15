<?php
// Set timezone to Philippine Time for accurate lockdown calculations
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = get_db();

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'notactive') {
    $error = "Your account has not been activated by an administrator yet.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both your Email Address and Password.";
    } else {
        // Look up the user by email instead of student_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 1. Check if the account is currently locked out
            if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
                $remaining_seconds = strtotime($user['lockout_until']) - time();
                $remaining_minutes = ceil($remaining_seconds / 60);
                $error = "Account locked due to too many failed attempts. Please try again in {$remaining_minutes} minute(s).";
            } else {
                // 2. Verify Password
                if (password_verify($password, $user['password'])) {
                    
                    // Reset login attempts on successful login
                    $reset = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE id = ?");
                    $reset->execute([$user['id']]);

                    if ($user['role'] === 'student' && $user['status'] !== 'active') {
                        $error = "Your account is pending admin approval. Please check back later.";
                    } else {
                        $_SESSION['user_id'] = $user['id'];

                        // Remember the PREVIOUS sign-in (shown on the profile page), then record this one.
                        // Wrapped in try/catch so login still works if the last_login column isn't added yet.
                        $_SESSION['prev_login'] = $user['last_login'] ?? null;
                        try {
                            $stamp = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                            $stamp->execute([date('Y-m-d H:i:s'), $user['id']]);
                        } catch (PDOException $e) { /* last_login column not created yet */ }

                        // Automatic Role-Based Routing
                        if ($user['role'] === 'admin') {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: student/dashboard.php");
                        }
                        exit();
                    }
                } else {
                    // 3. Handle Failed Login (Increment Attempts)
                    $attempts = $user['login_attempts'] + 1;
                    
                    if ($attempts >= 5) {
                        // Lock the account for 10 minutes
                        $lockout_time = date('Y-m-d H:i:s', time() + (10 * 60)); // Current time + 10 mins
                        $update = $pdo->prepare("UPDATE users SET login_attempts = ?, lockout_until = ? WHERE id = ?");
                        $update->execute([$attempts, $lockout_time, $user['id']]);
                        
                        $error = "Account locked due to 5 failed attempts. Please try again in 10 minutes.";
                    } else {
                        // Update attempts but don't lock yet
                        $update = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                        $update->execute([$attempts, $user['id']]);
                        
                        $attempts_left = 5 - $attempts;
                        $error = "Invalid Password. You have {$attempts_left} attempt(s) remaining before lockdown.";
                    }
                }
            }
        } else {
            $error = "Invalid Email Address or Password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #090d38;        /* headings */
            --indigo: #1a01b6;      /* main brand blue: button, 'Back', links */
            --vivid: #2300ff;       /* bright blue: 'Archive' and Sign Up link */
            --deep: #001cae;        /* the curved stripe */
            --lavender: #f3f3ff;    /* right-side background */
            --blue: #3b82f6;
            --violet: #7c3aed;
            --periwinkle: #a5b4fc;
            --text-dark: #0f172a;
            --text-muted: #64779b;
            --border: #dfe3ee;
            --error-bg: #fef2f2;
            --font: 'Inter', sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: var(--font);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
            background: var(--lavender);
        }

        /* ---------- Page canvas ---------- */
        .page {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            background: var(--lavender);
        }

        /* Deep blue stripe that follows the curve */
        .stripe {
            position: absolute;
            inset: 0;
            background: var(--deep);
            clip-path: url(#arcStripe);
            z-index: 0;
        }

        /* Dark photo panel with curved right edge */
        .hero {
            position: absolute;
            inset: 0;
            clip-path: url(#arc);
            background:
                linear-gradient(100deg,  rgba(30, 24, 112, 0.93) 0%, rgba(44, 38, 124, 0.86) 55%, rgba(64, 56, 146, 0.78) 100%),
                url('assets/backgroundpic.png') center / cover no-repeat;
            z-index: 1;
        }

        /* Dotted decorations */
        .dots {
            position: absolute;
            pointer-events: none;
            background-image: radial-gradient(circle, currentColor 1.5px, transparent 1.6px);
            background-size: 22px 22px;
        }
        .dots-tl { top: 24px; left: 24px; width: 210px; height: 170px; color: rgba(255, 255, 255, 0.28); z-index: 2; }
        .dots-br { bottom: 24px; right: 24px; width: 260px; height: 190px; color: rgba(99, 102, 241, 0.22); z-index: 1; }

        /* ---------- Layout grid ---------- */
        .stage {
            position: relative;
            z-index: 3;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 470px;
            align-items: center;
            gap: 3rem;
            padding: 3rem clamp(2rem, 10vw, 11rem) 3rem clamp(2rem, 9vw, 10rem);
        }

        /* ---------- Left content ---------- */
        .left-text { color: #fff; min-width: 0; }

        .top-brand { display: flex; align-items: center; gap: 1.1rem; }
        .top-brand img { width: 86px; height: 86px; border-radius: 50%; background: #fff; padding: 2px; flex-shrink: 0; }
        .top-brand .name { font-size: 1.85rem; font-weight: 800; line-height: 1.1; letter-spacing: 0.3px; }
        .top-brand .name small { display: block; font-size: 1.05rem; font-weight: 500; letter-spacing: 3px; opacity: 0.85; margin-top: 6px; }

        .brand-underline {
            width: 90px;
            height: 3px;
            border-radius: 2px;
            margin: 2.4rem 0 2.2rem;
            background: linear-gradient(90deg, #c026d3 0%, #3b82f6 100%);
        }

        .welcome-sub { font-size: 1.5rem; font-weight: 400; opacity: 0.95; margin-bottom: 0.2rem; }
        .main-title { font-size: clamp(3.4rem, 6vw, 5.2rem); font-weight: 800; line-height: 1.05; letter-spacing: -2px; margin-bottom: 1.4rem; }
        .main-title .grad { color: var(--vivid); }
        .description { font-size: 1.05rem; line-height: 1.65; max-width: 460px; color: rgba(255, 255, 255, 0.88); margin-bottom: 3rem; }

        /* Feature strip */
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            width: min(100%, 780px);
            padding: 1.1rem 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(6px);
        }
        .feature { display: flex; align-items: center; gap: 0.85rem; padding: 0.25rem 1.1rem; }
        .feature + .feature { border-left: 1px solid rgba(255, 255, 255, 0.18); }
        .feature .ico {
            width: 52px; height: 52px; flex-shrink: 0;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: rgba(99, 102, 241, 0.22);
            color: #8f93ff;
        }
        .feature .ico svg { width: 26px; height: 26px; }
        .feature strong { display: block; font-size: 0.92rem; font-weight: 700; color: #fff; margin-bottom: 3px; }
        .feature span { display: block; font-size: 0.74rem; line-height: 1.45; color: rgba(255, 255, 255, 0.75); }

        /* ---------- Auth card ---------- */
        .auth-card {
            background: #fff;
            border-radius: 24px;
            padding: 2.4rem 2.4rem 2rem;
            box-shadow: 0 30px 60px -20px rgba(40, 30, 140, 0.30), 0 8px 24px rgba(15, 23, 42, 0.06);
            width: 100%;
        }
        .card-header { text-align: center; margin-bottom: 2rem; }
        .school-logo { width: 76px; height: 76px; object-fit: contain; border-radius: 50%; margin-bottom: 0.9rem; }
        .card-header h3 { font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; color: var(--navy); margin-bottom: 0.35rem; }
        .card-header h3 .grad { color: var(--indigo); }
        .card-header p { color: var(--text-muted); font-size: 0.95rem; }

        .alert { padding: 0.9rem 1.1rem; border-radius: 10px; margin-bottom: 1.4rem; font-size: 0.85rem; font-weight: 500; display: flex; align-items: flex-start; gap: 8px; line-height: 1.4; background: var(--error-bg); color: #991b1b; border: 1px solid #fecaca; }
        .alert svg { flex-shrink: 0; margin-top: 1px; }

        .field { margin-bottom: 1.2rem; }
        .field label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-dark); }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 16px; color: #8796b6; width: 18px; height: 18px; pointer-events: none; }
        .input {
            width: 100%;
            height: 52px;
            padding: 0 1rem 0 2.9rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            background: #fbfbfe;
            color: var(--text-dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input::placeholder { color: #a6aec4; }
        .input:focus { outline: none; border-color: var(--indigo); box-shadow: 0 0 0 3px rgba(35, 0, 255, 0.12); background: #fff; }
        .pw-toggle { position: absolute; right: 10px; background: none; border: none; color: #8a93ad; cursor: pointer; display: flex; padding: 6px; border-radius: 6px; }
        .pw-toggle:hover { color: var(--text-dark); background: #f1f5f9; }
        .pw-toggle:focus-visible { outline: 2px solid var(--indigo); }

        .form-options { display: flex; justify-content: space-between; align-items: center; margin: 0.4rem 0 1.6rem; font-size: 0.85rem; }
        .remember-me { display: flex; align-items: center; gap: 8px; color: #4b5578; cursor: pointer; }
        .remember-me input { width: 16px; height: 16px; accent-color: var(--indigo); cursor: pointer; }
        .forgot-link { color: var(--indigo); text-decoration: none; font-weight: 600; }
        .forgot-link:hover { text-decoration: underline; }

        .btn {
            width: 100%;
            height: 54px;
            background: var(--indigo);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex; justify-content: center; align-items: center; gap: 10px;
            box-shadow: 0 8px 18px rgba(26, 1, 182, 0.30);
            transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .btn:hover { background: var(--vivid); transform: translateY(-1px); box-shadow: 0 12px 22px rgba(35, 0, 255, 0.34); }
        .btn:active { transform: translateY(0); }
        .btn:focus-visible { outline: 3px solid #c7d2fe; outline-offset: 2px; }

        .divider { display: flex; align-items: center; gap: 1rem; margin: 1.6rem 0 1.3rem; color: var(--text-muted); font-size: 0.85rem; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--border); }

        .auth-foot { text-align: center; color: #4b5578; font-size: 0.92rem; }
        .auth-foot a { color: var(--vivid); text-decoration: none; font-weight: 700; margin-left: 4px; }
        .auth-foot a:hover { text-decoration: underline; }

        /* ---------- Tablet / mobile ---------- */
        @media (max-width: 1100px) {
            .features { grid-template-columns: 1fr; gap: 0.5rem; }
            .feature + .feature { border-left: none; border-top: 1px solid rgba(255, 255, 255, 0.18); padding-top: 0.75rem; margin-top: 0.25rem; }
        }
        @media (max-width: 960px) {
            .hero { clip-path: none; }
            .stripe, .dots-br { display: none; }
            .stage { grid-template-columns: 1fr; max-width: 560px; margin: 0 auto; padding: 2.5rem 1.25rem; gap: 2.5rem; }
            .left-text { text-align: center; }
            .top-brand { justify-content: center; text-align: left; }
            .brand-underline { margin-left: auto; margin-right: auto; }
            .description { margin-left: auto; margin-right: auto; }
            .features { text-align: left; margin: 0 auto; }
            .auth-card { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Curve shapes (scale with the page) -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <clipPath id="arc" clipPathUnits="objectBoundingBox">
            <path d="M0,0 H0.763 C0.72,0.45 0.68,0.75 0.48,1 H0 Z"/>
        </clipPath>
        <clipPath id="arcStripe" clipPathUnits="objectBoundingBox">
            <path d="M0,0 H0.79 C0.745,0.45 0.705,0.76 0.505,1 H0 Z"/>
        </clipPath>
    </defs>
</svg>

<div class="page">
    <div class="stripe"></div>
    <div class="hero"></div>
    <div class="dots dots-tl"></div>    
    <div class="dots dots-br"></div>

    <div class="stage">

        <!-- Left Side Content -->
        <div class="left-text">
            <div class="top-brand">
                <img src="assets/logo1.png" alt="ACLC Logo">
                <div class="name">ACLC<br>COLLEGE<small>OF MANDAUE</small></div>
            </div>

            <div class="brand-underline"></div>

            <div class="welcome-sub">Welcome to</div>
            <h1 class="main-title">Schol<span class="grad">Archive</span></h1>

            <p class="description">A digital platform that allows ACLC College of Mandaue students and administrators to securely access, explore, and learn from capstone projects and research papers.</p>

            <div class="features">
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10 11V9a2 2 0 0 1 4 0v2"/></svg>
                    </div>
                    <div><strong>Secure Access</strong><span>Your data is protected with enterprise-grade security.</span></div>
                </div>
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.9 1.2 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                    </div>
                    <div><strong>Explore Knowledge</strong><span>Discover innovative projects and valuable research.</span></div>
                </div>
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/><path d="M22 10v6"/></svg>
                    </div>
                    <div><strong>Empower Learning</strong><span>Learn, collaborate, and create a better future together.</span></div>
                </div>
            </div>
        </div>

        <!-- Right Side Auth Card -->
        <div class="auth-card">
            <div class="card-header">
                <img src="assets/logo1.png" alt="School Logo" class="school-logo">
                <h3>Welcome <span class="grad">Back</span></h3>
                <p>Sign in to continue to ScholArchive</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <input type="email" class="input" name="email" id="email" placeholder="e.g. user@scholarchive.edu" required value="<?= isset($_POST['email']) ? e($_POST['email']) : '' ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" class="input" name="password" id="password" placeholder="Enter your password" required style="padding-right:48px;">
                        <button type="button" class="pw-toggle" id="togglePw" aria-label="Toggle password visibility">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eyeOffIcon" style="display:none;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="20" y2="12"/><polyline points="13 5 20 12 13 19"/></svg>
                    Sign In to Dashboard
                </button>
            </form>

            <div class="divider">OR</div>

            <p class="auth-foot">Don't have an account? <a href="register.php">Sign Up</a></p>
        </div>
    </div>
</div>

<script>
    // Password Visibility Toggle
    const togglePwBtn = document.getElementById('togglePw');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    const eyeOffIcon = document.getElementById('eyeOffIcon');

    if (togglePwBtn) {
        togglePwBtn.addEventListener('click', () => {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            eyeIcon.style.display = show ? 'none' : 'block';
            eyeOffIcon.style.display = show ? 'block' : 'none';
        });
    }
</script>
</body>
</html>