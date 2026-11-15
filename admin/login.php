<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();
$error = '';

// If the admin is already logged in, redirect them straight to the dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter your administrative email and password.";
    } else {
        // STRICT CHECK: Only look for active users where role = 'admin'
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Authentication successful
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['role'] = 'admin';
            
            // Redirect to Admin Dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            // Vague error message for security (don't reveal if email exists but password fails)
            $error = "Unauthorized access or invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Access · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #020617;
            --bg-gradient: linear-gradient(135deg, #020617 0%, #0f172a 100%);
            --card-bg: rgba(30, 41, 59, 0.7);
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --danger: #ef4444;
            --font-family: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: var(--font-family); 
            background: var(--bg-gradient); 
            color: var(--text-light); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Ambient Glow Effects for Premium Look */
        body::before {
            content: ''; position: absolute; top: -20%; left: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        body::after {
            content: ''; position: absolute; bottom: -20%; right: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(25, 0, 166, 0.15) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 2rem;
            position: relative;
            z-index: 10;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .brand-header h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 4px; }
        .brand-header p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 1px;}

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input-wrap { position: relative; }
        .input-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; }
        
        .form-control { 
            width: 100%; 
            padding: 0.85rem 1rem 0.85rem 2.75rem; 
            background: rgba(0, 0, 0, 0.2); 
            border: 1px solid var(--border-color); 
            border-radius: 10px; 
            font-size: 0.95rem; 
            color: var(--text-light); 
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); background: rgba(0,0,0,0.3); }
        .form-control::placeholder { color: #475569; }

        .btn-login { 
            width: 100%; 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 0.9rem; 
            border-radius: 10px; 
            font-weight: 700; 
            font-size: 1rem; 
            cursor: pointer; 
            transition: all 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
            margin-top: 2rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-login:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4); }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 2rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .back-link:hover { color: var(--text-light); }

    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="brand-header">
                <div class="brand-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h1>ScholArchive</h1>
                <p>Administrative Portal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label>Admin Email</label>
                    <div class="input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <input type="email" name="email" class="form-control" placeholder="admin@scholarchive.edu" required autocomplete="email" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Admin Password</label>
                    <div class="input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Authorize Access
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

        </div>

        <a href="../index.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            Return to Student Portal
        </a>
    </div>

</body>
</html>