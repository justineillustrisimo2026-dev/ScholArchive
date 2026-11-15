<?php
// reset_password.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = get_db();

$error = '';
$success = '';
$validToken = false;
$userEmail = '';

// 1. Validate the Token from the URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid or missing password reset token.";
} else {
    // Check if token exists and hasn't expired
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $validToken = true;
        $userEmail = $resetRecord['email'];
    } else {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
}

// 2. Handle the New Password Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Strict password requirements
    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Your passwords do not match. Please retype them carefully.";
    } elseif (!preg_match($passwordPattern, $newPassword)) {
        $error = "Password is too weak. Please ensure it meets all security requirements.";
    } else {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update the user's password and unlock their account if it was locked
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, login_attempts = 0, lockout_until = NULL WHERE email = ?");
        $updateStmt->execute([$hashedPassword, $userEmail]);

        // Delete the used token from the database so it can't be used again
        $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $delStmt->execute([$userEmail]);

        $success = "Your password has been successfully reset! You can now sign in.";
        $validToken = false; // Hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Keeping your exact premium styling from forgot_password.php */
        :root { 
            --primary: #1900a6; --primary-hover: #100075; --primary-light: #e0e7ff; 
            --accent-red: #ff1900; --text-dark: #0f172a; --text-muted: #64748b; 
            --border: #cbd5e1; --error: #ef4444; --error-bg: #fef2f2; 
            --success: #10b981; --success-bg: #ecfdf5; --font: 'Inter', sans-serif; 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); color: var(--text-dark); background-color: #f8fafc; overflow-x: hidden; position: relative; }
        
        body::before { content: ""; position: absolute; top: 0; left: 0; width: 250px; height: 250px; background: linear-gradient(135deg, var(--accent-red) 50%, transparent 50%); z-index: 10; }
        .blue-accent-top { position: absolute; top: 0; left: 0; width: 350px; height: 350px; background: linear-gradient(135deg, var(--primary) 50%, transparent 50%); z-index: 9; transform: translate(-50px, -50px); }
        body::after { content: ""; position: absolute; bottom: 0; right: 0; width: 400px; height: 400px; background: linear-gradient(315deg, var(--primary) 50%, transparent 50%); z-index: 10; }
        .red-accent-bottom { position: absolute; bottom: 0; right: 0; width: 250px; height: 250px; background: linear-gradient(315deg, var(--accent-red) 50%, transparent 50%); z-index: 9; transform: translate(50px, -150px); }

        .page-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; background: linear-gradient(110deg, rgba(13, 0, 94, 0.95) 10%, rgba(25, 0, 166, 0.85) 50%, rgba(25, 0, 166, 0) 75%), url('assets/libraryaclc(1).png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; position: relative; z-index: 20; }
        .content-grid { display: grid; grid-template-columns: 1.2fr 420px; max-width: 1200px; width: 100%; gap: 6rem; align-items: center; animation: fadeIn 0.8s ease-out; position: relative; z-index: 30; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .left-text { color: #ffffff; }
        .top-brand { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .top-brand img { width: 70px; height: 70px; border-radius: 50%; background: #fff; padding: 2px; }
        .top-brand h2 { font-size: 1.8rem; font-weight: 800; line-height: 1.1; letter-spacing: 0.5px; margin: 0; }
        .top-brand span { display: block; font-size: 1rem; font-weight: 400; opacity: 0.8; letter-spacing: 2px; margin-top: 2px;}
        .brand-underline { width: 50px; height: 4px; background: linear-gradient(90deg, #ff0000 0%, #a855f7 100%); margin-bottom: 2rem; border-radius: 2px; }
        .welcome-sub { font-size: 1.5rem; font-weight: 400; margin-bottom: 0.25rem; opacity: 0.9; }
        .main-title { font-size: 4rem; font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -1.5px; }
        .main-title .red-text { color: var(--accent-red); }
        .description { font-size: 1rem; line-height: 1.6; font-weight: 400; max-width: 85%; color: rgba(255, 255, 255, 0.85); margin-bottom: 3rem; }
        
        .auth-card { background: #ffffff; border-radius: 16px; padding: 3rem 2.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; position: relative; }
        .card-header { text-align: center; margin-bottom: 2rem; }
        .lock-icon-wrap { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: #e0e7ff; color: var(--primary); border-radius: 50%; margin-bottom: 1.25rem; }
        .card-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.5px; color: var(--text-dark); }
        .card-header p { color: var(--text-muted); font-size: 0.9rem; line-height: 1.5;}
        
        .alert { padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 500; display: flex; align-items: flex-start; gap: 8px; line-height: 1.4; }
        .alert-error { background-color: var(--error-bg); color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background-color: var(--success-bg); color: #065f46; border: 1px solid #a7f3d0; }
        .alert svg { flex-shrink: 0; margin-top: 1px; }
        
        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-dark); }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 14px; color: #94a3b8; width: 16px; height: 16px; pointer-events: none; }
        .input { width: 100%; padding: 0.85rem 1rem 0.85rem 2.5rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 0.9rem; transition: all 0.2s ease; background: #fff; color: var(--text-dark); }
        .input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        
        /* Password Requirements */
        .password-reqs-container { background: #f8fafc; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 1.5rem; }
        .req-item { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-muted); transition: color 0.2s; font-weight: 500;}
        .req-item svg { width: 14px; height: 14px; color: #cbd5e1; transition: color 0.2s; }
        .req-item.met { color: var(--success); font-weight: 600; }
        .req-item.met svg { color: var(--success); }

        .btn { width: 100%; padding: 0.85rem; background: linear-gradient(90deg, var(--primary) 0%, #3b82f6 100%); color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 10px rgba(25, 0, 166, 0.25); display: flex; justify-content: center; align-items: center; gap: 8px; margin-bottom: 1.5rem;}
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(25, 0, 166, 0.35); }
        
        .auth-foot { text-align: center; color: var(--text-muted); font-size: 0.85rem; }
        .auth-foot a { color: var(--text-dark); text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; transition: color 0.2s;}
        .auth-foot a:hover { color: var(--primary); }
        
        @media (max-width: 960px) { 
            .content-grid { grid-template-columns: 1fr; gap: 3rem; max-width: 500px; } 
            .left-text { text-align: center; } 
            .top-brand { justify-content: center; }
            .brand-underline { margin: 0 auto 2rem auto;}
            .description { margin: 0 auto 2rem auto; } 
            .auth-card { padding: 2.5rem 2rem; } 
            body::before, body::after, .blue-accent-top, .red-accent-bottom { display: none; }
        }
    </style>
</head>
<body>

<div class="blue-accent-top"></div>
<div class="red-accent-bottom"></div>

<div class="page-wrapper">
    <div class="content-grid">
        
        <div class="left-text">
            <div class="top-brand">
                <img src="assets/logo.jpg" alt="ACLC Logo">
                <h2>ACLC<br>COLLEGE<br><span>OF MANDAUE</span></h2>
            </div>
            <div class="brand-underline"></div>
            <div class="welcome-sub">Welcome to</div>
            <h1 class="main-title">Schol<span class="red-text">Archive</span></h1>
            <p class="description">A digital platform that allows ACLC College of Mandaue students to securely access, explore, and learn from capstone projects and research papers created by previous scholars.</p>
        </div>

        <div class="auth-card">
            
            <div class="card-header">
                <div class="lock-icon-wrap">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3>Set New Password</h3>
                <p>Create a strong, secure password for your account.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <div><?= e($success) ?></div>
                </div>
                <a href="index.php" class="btn" style="text-decoration:none; margin-top:1rem;">Return to Sign In</a>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form method="POST" action="">
                    
                    <div class="field">
                        <label for="new_password">New Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" class="input" name="new_password" id="new_password" placeholder="••••••••" required autofocus>
                        </div>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" class="input" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <!-- Password Requirement Checklist -->
                    <div class="password-reqs-container">
                        <div class="req-item" id="req-length"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> 8+ characters</div>
                        <div class="req-item" id="req-upper"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> 1 uppercase letter</div>
                        <div class="req-item" id="req-lower"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> 1 lowercase letter</div>
                        <div class="req-item" id="req-num-spec"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> 1 number & special char</div>
                    </div>

                    <button type="submit" class="btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Update Password
                    </button>
                </form>

                <script>
                    // Live Password Validation
                    const pwInput = document.getElementById('new_password');
                    const reqLength = document.getElementById('req-length');
                    const reqUpper = document.getElementById('req-upper');
                    const reqLower = document.getElementById('req-lower');
                    const reqNumSpec = document.getElementById('req-num-spec');

                    pwInput.addEventListener('input', function() {
                        const val = pwInput.value;
                        if(val.length >= 8) reqLength.classList.add('met'); else reqLength.classList.remove('met');
                        if(/[A-Z]/.test(val)) reqUpper.classList.add('met'); else reqUpper.classList.remove('met');
                        if(/[a-z]/.test(val)) reqLower.classList.add('met'); else reqLower.classList.remove('met');
                        if(/\d/.test(val) && /[\W_]/.test(val)) reqNumSpec.classList.add('met'); else reqNumSpec.classList.remove('met');
                    });
                </script>
            <?php endif; ?>

            <?php if (!$validToken && empty($success)): ?>
                <div class="auth-foot">
                    <a href="forgot_password.php">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                        Request New Link
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

</body>
</html>