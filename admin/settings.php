<?php
// admin/settings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();$pdo = get_db();

// 1. Handle Form Submissions & Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action =$_POST['action'] ?? '';

    // Update Admin Profile
    if ($action === 'update_profile') {
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        $stmt =$pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
        $stmt->execute([$fname,$lname, $email,$admin['id']]);
        
        if (function_exists('log_audit')) log_audit($pdo,$admin['id'], 'Updated Admin Profile', "Updated personal account details.");
        flash_set('success', 'Admin profile details updated successfully.');
        header('Location: settings.php?tab=general');
        exit;
    }

    // Update System Identity
    elseif ($action === 'update_system') {
        $acadYear = trim($_POST['academic_year']);
        $sysName = trim($_POST['system_name']);
        $contactEmail = trim($_POST['contact_email']);
        
        $updateStmt =$pdo->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key=?");
        $updateStmt->execute([$acadYear, 'academic_year']);
        $updateStmt->execute([$sysName, 'system_name']);
        $updateStmt->execute([$contactEmail, 'contact_email']);
        
        if (function_exists('log_audit')) log_audit($pdo,$admin['id'], 'Updated System Identity', "Modified academic year and platform details.");
        flash_set('success', 'System identity updated successfully.');
        header('Location: settings.php?tab=general');
        exit;
    }

    // Update Repository Configurations
    elseif ($action === 'update_settings') {
        $allow_registrations = isset($_POST['allow_registrations']) ? '1' : '0';
        $auto_approve_users = isset($_POST['auto_approve_users']) ? '1' : '0';
        $max_upload_mb =$_POST['max_upload_mb'] ?? '10';
        $default_doc_status =$_POST['default_doc_status'] ?? 'draft';

        $updateStmt =$pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $updateStmt->execute([$allow_registrations, 'allow_registrations']);
        $updateStmt->execute([$auto_approve_users, 'auto_approve_users']);
        $updateStmt->execute([$max_upload_mb, 'max_upload_mb']);
        $updateStmt->execute([$default_doc_status, 'default_doc_status']);

        if (function_exists('log_audit')) log_audit($pdo,$admin['id'], 'Updated Repository Rules', "Changed registration and upload limits.");
        flash_set('success', 'Repository configurations successfully updated.');
        header("Location: settings.php?tab=security");
        exit;
    }

    // Update Password
    elseif ($action === 'update_password') {
        $currentPw =$_POST['current_password'];
        $newPw =$_POST['new_password'];
        $confirmPw =$_POST['confirm_password'];
        
        $stmt =$pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$admin['id']]);
        $hash =$stmt->fetchColumn();
        
        if (!password_verify($currentPw, $hash)) { flash_set('error', 'Current password is incorrect.'); } elseif ($newPw !== $confirmPw) { flash_set('error', 'New passwords do not match.'); } elseif (strlen($newPw) < 6) {
            flash_set('error', 'New password must be at least 6 characters.');
        } else {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt =$pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$newHash,$admin['id']]);
            if (function_exists('log_audit')) log_audit($pdo,$admin['id'], 'Changed Password', "Successfully updated admin security password.");
            flash_set('success', 'Security password successfully updated.');
        }
        header('Location: settings.php?tab=security');
        exit;
    }

    // Database Backup
    elseif ($action === 'backup_db') {
        if (function_exists('log_audit')) log_audit($pdo,$admin['id'], 'Database Backup Initiated', "Requested a manual SQL export.");
        flash_set('success', 'Database backup script initiated.');
        header("Location: settings.php?tab=data");
        exit;
    }
}

// 2. Fetch Data
try {
    $stmt =$pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings_data =$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {$settings_data = []; }

// Fallbacks
$sys_allow_reg =$settings_data['allow_registrations'] ?? '1';
$sys_auto_approve =$settings_data['auto_approve_users'] ?? '0';
$sys_max_mb =$settings_data['max_upload_mb'] ?? '10';
$sys_doc_status =$settings_data['default_doc_status'] ?? 'draft';
$sys_acad_year =$settings_data['academic_year'] ?? 'A.Y. 2026-2027';
$sys_name =$settings_data['system_name'] ?? 'ScholArchive';
$sys_contact =$settings_data['contact_email'] ?? 'admin@scholarchive.edu';

// Fetch Current Admin User Data
$stmt =$pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin['id']]);
$currentAdmin =$stmt->fetch();

// 3. Fetch Recent Audit Logs
try {
    $logStmt =$pdo->query("SELECT a.*, u.first_name, u.last_name FROM audit_logs a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 15");
    $audit_logs =$logStmt->fetchAll();
} catch (Exception $e) {$audit_logs = []; }

$success = flash_get('success');$error = flash_get('error');
$activeTab =$_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1900a6; --sidebar-hover: #2100d4; 
            --bg-light: #f8f9fc; --white: #ffffff; 
            --text-dark: #1e293b; --text-muted: #64748b;
            --primary: #1900a6; --primary-hover: #100075; --primary-light: #e0e7ff; 
            --danger: #ef4444; --success: #10b981; 
            --border-light: #e2e8f0; --accent-orange: #f59e0b;
            --font-family: 'Inter', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-family); background-color: var(--bg-light); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; background-image: radial-gradient(at 100% 0%, rgba(25, 0, 166, 0.05) 0px, transparent 40%), radial-gradient(at 0% 100%, rgba(25, 0, 166, 0.03) 0px, transparent 40%); background-attachment: fixed; }

        /* Unified Flush Sidebar */
        .sidebar { width: 280px; background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0a0045 100%); color: var(--white); display: flex; flex-direction: column; flex-shrink: 0; z-index: 10; box-shadow: 4px 0 15px rgba(25, 0, 166, 0.1); height: 100vh; position: relative; overflow: hidden; }
        .sidebar::before { content: ''; position: absolute; bottom: -50px; left: -50px; width: 300px; height: 300px; border-radius: 50%; border: 40px solid rgba(255,255,255,0.05); pointer-events: none; }
        .sidebar::after { content: ''; position: absolute; bottom: -100px; right: -50px; width: 250px; height: 250px; border-radius: 50%; border: 30px solid rgba(255,255,255,0.03); pointer-events: none; }
        .brand { padding: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 800; font-size: 1.15rem; letter-spacing: 0.5px; position: relative; z-index: 2; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .brand img { width: 32px; height: 32px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; }
        .user-profile { margin: 1rem; background: rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; border: 1px solid rgba(255,255,255,0.1);}
        .avatar { width: 40px; height: 40px; background-color: #3b82f6; color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem; position: relative; }
        .user-info h4 { font-size: 0.85rem; margin-bottom: 2px; font-weight: 600;}
        .user-info span { font-size: 0.7rem; color: rgba(255,255,255,0.7); }
        .nav-links { flex: 1; padding: 0 1rem; list-style: none; overflow-y: auto; position: relative; z-index: 2;}
        .nav-links li { margin-bottom: 0.25rem; }
        .nav-links a { display: flex; align-items: center; gap: 12px; padding: 0.85rem 1rem; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 10px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255,255,255,0.15); color: var(--white); }
        .logout-wrap { padding: 1.25rem; position: relative; z-index: 2; border-top: 1px solid rgba(255,255,255,0.08); }
        .btn-logout { display: flex; align-items: center; justify-content: flex-start; gap: 10px; width: 100%; background: transparent; color: var(--white); padding: 0.85rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,255,255,0.15); }

        /* Main Workspace */
        .main-content { flex: 1; padding: 2rem 3rem; overflow-y: auto; display: flex; flex-direction: column; }
        
        /* Dashboard-Matched Hero Banner */
                /* Premium Hero Banner */
        .hero-banner {
            background:
                linear-gradient(90deg,
                    #100451 0%,
                    #100451 42%,
                    rgba(21, 16, 144, 0.92) 58%,
                    rgba(21, 16, 144, 0.35) 78%,
                    rgba(21, 16, 144, 0) 100%),
                url('../assets/backgroundaclc.png');
            background-size: cover;
            background-position: right 30%;
            border-radius: 16px;
            padding: 5rem 5rem;
            color: var(--white);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(16, 4, 81, 0.15);
            display: flex;
            align-items: center;
        }
        .hero-content { position: relative; z-index: 2; margin-left: 0.5rem; }
        .hero-content h1 { font-size: 1.9rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.25rem; line-height: 1.15; }
        .hero-content h1 .highlight { color: #4930f5; }
        .hero-content h2 { font-size: 0.95rem; font-weight: 600; margin-bottom: 0.2rem; }
        .hero-content p { font-size: 0.8rem; color: #e2e8f0; font-weight: 400; }

        .page-titles { margin-bottom: 1.5rem; }
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.4rem; letter-spacing: -0.5px; }
        .page-titles p { color: var(--text-muted); font-size: 0.95rem; }

        .alert { padding: 1.25rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .alert-success { background: rgba(255,255,255,0.9); color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: rgba(255,255,255,0.9); color: #991b1b; border-left: 4px solid #ef4444; }

        /* Sleek Horizontal Tabs */
        .tab-header { display: flex; gap: 1.5rem; border-bottom: 1px solid var(--border-light); margin-bottom: 2rem; }
        .tab-btn { background: none; border: none; padding: 0.75rem 0.5rem; font-size: 0.95rem; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .tab-btn:hover { color: var(--text-dark); }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

        .tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Layout Grid */
        .settings-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: flex-start; }
        .settings-forms { display: flex; flex-direction: column; gap: 2rem; }
        
        /* Cards */
        .card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; }
        .card-header { padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-light); background: #f8fafc; display: flex; align-items: center; gap: 12px;}
        .card-header svg { color: var(--primary); }
        .card-header h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); }
        .card-body { padding: 1.75rem; display: flex; flex-direction: column; gap: 1.5rem; }

        /* Form Inputs */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.8rem; font-weight: 700; color: var(--text-dark); }
        .form-control { padding: 0.75rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.9rem; font-family: inherit; color: var(--text-dark); background: #f8fafc; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-light); }
        .form-control:disabled { background: #e2e8f0; color: var(--text-muted); cursor: not-allowed; }

        .setting-group { display: flex; justify-content: space-between; align-items: center; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-light); }
        .setting-group:last-child { border-bottom: none; padding-bottom: 0; }
        .setting-text h4 { font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .setting-text p { font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; max-width: 350px;}
        
        /* Toggle Switch CSS */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2);}
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }

        .select-input { padding: 0.6rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.85rem; font-family: inherit; font-weight: 600; color: var(--text-dark); outline: none; transition: border 0.2s;}
        .select-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        .card-footer { padding: 1.25rem 1.75rem; background: #f8fafc; border-top: 1px solid var(--border-light); text-align: right; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(25, 0, 166, 0.2); display: inline-flex; align-items: center; gap: 8px;}
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 12px rgba(25, 0, 166, 0.3);}
        .btn-danger-outline { background: transparent; color: var(--danger); border: 2px solid var(--danger); padding: 0.75rem 1.5rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;}
        .btn-danger-outline:hover { background: var(--danger); color: white; }

        /* Audit Log Timeline */
        .audit-feed { display: flex; flex-direction: column; gap: 0; padding: 0 1rem; max-height: 600px; overflow-y: auto;}
        .audit-item { display: flex; gap: 1rem; padding-bottom: 1.5rem; position: relative; }
        .audit-item::before { content: ''; position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background: var(--border-light); }
        .audit-item:last-child::before { display: none; }
        .audit-icon { width: 32px; height: 32px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; z-index: 2; flex-shrink: 0; border: 3px solid var(--white);}
        .audit-content { flex: 1; padding-top: 4px; }
        .audit-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
        .audit-action { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
        .audit-time { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        .audit-user { font-size: 0.8rem; color: var(--primary); font-weight: 600; display: block; margin-bottom: 2px;}
        .audit-desc { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }
        
        .empty-log { text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.9rem; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand">
            <img src="../assets/logo1.png" alt="Logo">
            ScholArchive
        </div>
        <div class="user-profile">
            <div class="avatar">
                <?= get_initials($currentAdmin['first_name'], $currentAdmin['last_name']) ?>
            </div>
            <div class="user-info">
                <h4><?= e($currentAdmin['first_name'] . ' ' . $currentAdmin['last_name']) ?></h4>
                <span>System Administrator</span>
            </div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a></li>
            <li><a href="users.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="categories.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
            <li><a href="settings.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a></li>
        </ul>
        <div class="logout-wrap">
            <a href="../logout.php" class="btn-logout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 
                Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        
        <div class="hero-banner">
            <div class="hero-content">
                <h3>Welcome to</h3>
                <h1>Schol<span class="highlight">Archive</span></h1>
                <p>Preserving Knowledge. Empowering Learners.</p>
            </div>
        </div>

        <div class="page-titles">
            <h1>Platform Settings</h1>
            <p>Configure system preferences, security policies, and administrative details.</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= e($success) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            
            <!-- Left Column: Tabbed Interface -->
            <div class="settings-forms">
                
                <div class="tab-header">
                    <button class="tab-btn <?= $activeTab === 'general' ? 'active' : '' ?>" onclick="switchTab('general')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg> General
                    </button>
                    <button class="tab-btn <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchTab('security')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Security & Rules
                    </button>
                    <button class="tab-btn <?= $activeTab === 'data' ? 'active' : '' ?>" onclick="switchTab('data')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg> Data Backup
                    </button>
                </div>

                <!-- TAB 1: General -->
                <div id="general" class="tab-pane <?= $activeTab === 'general' ? 'active' : '' ?>">
                    <form method="POST" action="" class="card" style="margin-bottom: 2rem;">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="card-header">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="14" cy="7" r="4"/></svg>
                            <h3>Admin Profile</h3>
                        </div>
                        <div class="card-body form-grid">
                            <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?= e($currentAdmin['first_name']) ?>" required></div>
                            <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?= e($currentAdmin['last_name']) ?>" required></div>
                            <div class="form-group full-width"><label>Email Address</label><input type="email" name="email" class="form-control" value="<?= e($currentAdmin['email']) ?>" required></div>
                            <div class="form-group full-width"><label>Admin System ID</label><input type="text" class="form-control" value="<?= e($currentAdmin['student_id']) ?>" disabled></div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn-primary">Save Profile Changes</button></div>
                    </form>

                    <form method="POST" action="" class="card">
                        <input type="hidden" name="action" value="update_system">
                        <div class="card-header">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                            <h3>System Identity</h3>
                        </div>
                        <div class="card-body form-grid">
                            <div class="form-group"><label>Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?= e($sys_acad_year) ?>" required></div>
                            <div class="form-group"><label>Platform Name</label><input type="text" name="system_name" class="form-control" value="<?= e($sys_name) ?>" required></div>
                            <div class="form-group full-width"><label>Global Support Email</label><input type="email" name="contact_email" class="form-control" value="<?= e($sys_contact) ?>" required></div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn-primary">Update Identity</button></div>
                    </form>
                </div>

                <!-- TAB 2: Security & Rules -->
                <div id="security" class="tab-pane <?= $activeTab === 'security' ? 'active' : '' ?>">
                    <form method="POST" action="" class="card" style="margin-bottom: 2rem;">
                        <input type="hidden" name="action" value="update_password">
                        <div class="card-header">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <h3>Change Password</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group" style="margin-bottom: 0.5rem;"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                            <div class="form-grid">
                                <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
                            </div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn-primary">Update Password</button></div>
                    </form>

                    <form action="" method="POST" class="card">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="card-header">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z"/><path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                            <h3>Repository Configurations</h3>
                        </div>
                        <div class="card-body">
                            <div class="setting-group">
                                <div class="setting-text">
                                    <h4>Allow New Registrations</h4>
                                    <p>Enable or disable the public student sign-up page.</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="allow_registrations" value="1" <?= $sys_allow_reg == '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-group">
                                <div class="setting-text">
                                    <h4>Auto-Approve Students</h4>
                                    <p>If enabled, students bypass manual ID verification.</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_approve_users" value="1" <?= $sys_auto_approve == '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-group">
                                <div class="setting-text">
                                    <h4>Default Upload Status</h4>
                                    <p>Do newly uploaded documents start as public or hidden?</p>
                                </div>
                                <select name="default_doc_status" class="select-input">
                                    <option value="draft" <?= $sys_doc_status == 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                                    <option value="published" <?= $sys_doc_status == 'published' ? 'selected' : '' ?>>Published (Public)</option>
                                </select>
                            </div>

                            <div class="setting-group">
                                <div class="setting-text">
                                    <h4>Max PDF File Size (MB)</h4>
                                    <p>Limit the maximum allowable size for uploaded research PDFs.</p>
                                </div>
                                <select name="max_upload_mb" class="select-input">
                                    <option value="5" <?= $sys_max_mb == '5' ? 'selected' : '' ?>>5 MB</option>
                                    <option value="10" <?= $sys_max_mb == '10' ? 'selected' : '' ?>>10 MB</option>
                                    <option value="25" <?= $sys_max_mb == '25' ? 'selected' : '' ?>>25 MB</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn-primary">Save Rules</button></div>
                    </form>
                </div>

                <!-- TAB 3: Data Backup -->
                <div id="data" class="tab-pane <?= $activeTab === 'data' ? 'active' : '' ?>">
                    <form action="" method="POST" class="card">
                        <input type="hidden" name="action" value="backup_db">
                        <div class="card-header" style="border-bottom:none; padding-bottom: 0.5rem;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><path d="M10 12h4"/><path d="M12 10v4"/></svg>
                            <h3 style="color: var(--danger);">Database Backup</h3>
                        </div>
                        <div class="card-body" style="flex-direction: row; justify-content: space-between; align-items: center; padding-top: 0;">
                            <p style="font-size:0.85rem; color:var(--text-muted); max-width:60%;">Generate a backup file containing all user records, settings, and document metadata.</p>
                            <button type="submit" class="btn-danger-outline" onclick="return confirm('Initiate full database backup?');">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Download SQL Backup
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            <!-- Right Column: Audit Log Timeline -->
            <div class="card">
                <div class="card-header">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <h3>Live Audit Trail</h3>
                </div>
                
                <div class="card-body">
                    <?php if (count($audit_logs) > 0): ?>
                        <div class="audit-feed">
                            <?php foreach ($audit_logs as $log): 
                                $icon = '<polyline points="20 6 9 17 4 12"/>'; 
                                $color = 'var(--primary)'; $bg = 'var(--primary-light)';
                                
                                $action = strtolower($log['action']);
                                if (strpos($action, 'delete') !== false) {
                                    $icon = '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>';
                                    $color = 'var(--danger)'; $bg = '#fee2e2';
                                } elseif (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'change') !== false) {
                                    $icon = '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>';
                                    $color = '#ea580c'; $bg = '#ffedd5';
                                }
                            ?>
                                <div class="audit-item">
                                    <div class="audit-icon" style="color:<?= $color ?>; background:<?= $bg ?>;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><?= $icon ?></svg>
                                    </div>
                                    <div class="audit-content">
                                        <div class="audit-header">
                                            <span class="audit-action"><?= e($log['action']) ?></span>
                                            <span class="audit-time"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></span>
                                        </div>
                                        <span class="audit-user"><?= e($log['first_name'] . ' ' . $log['last_name']) ?></span>
                                        <p class="audit-desc"><?= e($log['details']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-log">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:#cbd5e1; margin-bottom:10px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <p>No system activity logged yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelector(`.tab-btn[onclick="switchTab('${tabId}')"]`).classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }
    </script>
</body>
</html>