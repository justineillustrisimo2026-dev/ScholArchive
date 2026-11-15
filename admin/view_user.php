<?php
// admin/view_user.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash_set('error', 'Student account not found.');
    header('Location: users.php');
    exit;
}

$hasAvatar = !empty($user['avatar']) && file_exists(__DIR__ . '/../uploads/' . $user['avatar']);
$joinedTs  = strtotime($user['created_at']);
$daysActive = max(0, (int)floor((time() - $joinedTs) / 86400));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User · ScholArchive Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1900a6;
            --bg-light: #f8f9fc;
            --white: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --primary: #1900a6;
            --primary-hover: #100075;
            --primary-light: #e0e7ff;
            --border-light: #e2e8f0;
            --font-family: 'Inter', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-family);
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
            background-image: radial-gradient(at 100% 0%, rgba(25, 0, 166, 0.05) 0px, transparent 40%),
                              radial-gradient(at 0% 100%, rgba(25, 0, 166, 0.03) 0px, transparent 40%);
            background-attachment: fixed;
        }

        /* Sidebar */
        .sidebar { width: 280px; background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0a0045 100%); color: var(--white); display: flex; flex-direction: column; flex-shrink: 0; z-index: 10; box-shadow: 4px 0 15px rgba(25, 0, 166, 0.1); height: 100vh; position: relative; overflow: hidden; }
        .sidebar::before { content: ''; position: absolute; bottom: -50px; left: -50px; width: 300px; height: 300px; border-radius: 50%; border: 40px solid rgba(255,255,255,0.05); pointer-events: none; }
        .sidebar::after { content: ''; position: absolute; bottom: -100px; right: -50px; width: 250px; height: 250px; border-radius: 50%; border: 30px solid rgba(255,255,255,0.03); pointer-events: none; }
        .brand { padding: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 800; font-size: 1.15rem; letter-spacing: 0.5px; position: relative; z-index: 2; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .brand img { width: 32px; height: 32px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; }
        .user-profile { margin: 1rem; background: rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; border: 1px solid rgba(255,255,255,0.1); }
        .avatar { width: 40px; height: 40px; background-color: #3b82f6; color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem; }
        .user-info h4 { font-size: 0.85rem; margin-bottom: 2px; font-weight: 600; }
        .user-info span { font-size: 0.7rem; color: rgba(255,255,255,0.7); }
        .nav-links { flex: 1; padding: 0 1rem; list-style: none; overflow-y: auto; position: relative; z-index: 2; }
        .nav-links li { margin-bottom: 0.25rem; }
        .nav-links a { display: flex; align-items: center; gap: 12px; padding: 0.85rem 1rem; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 10px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255,255,255,0.15); color: var(--white); }
        .logout-wrap { padding: 1.25rem; position: relative; z-index: 2; border-top: 1px solid rgba(255,255,255,0.08); }
        .btn-logout { display: flex; align-items: center; gap: 10px; width: 100%; background: transparent; color: var(--white); padding: 0.85rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,255,255,0.15); }

        /* Main workspace: fills the full viewport height */
        .main-content { flex: 1; padding: 1.75rem 2.5rem; display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; height: 100vh; overflow-y: auto; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; flex-shrink: 0; }
        .titles h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 2px; }
        .titles p { color: var(--text-muted); font-size: 0.9rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; padding: 0.65rem 1.1rem; border-radius: 10px; background: var(--white); border: 1px solid var(--border-light); color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s; }
        .back-link:hover { background: var(--primary); color: var(--white); border-color: var(--primary); }

        /* Two-column layout that stretches to fill remaining height */
        .layout { flex: 1; display: grid; grid-template-columns: 340px 1fr; gap: 1.25rem; min-height: 520px; }

        /* Left identity card */
        .identity-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; }
        .identity-top { background: linear-gradient(135deg, var(--primary) 0%, #0a0045 100%); color: var(--white); padding: 2.25rem 1.5rem 1.75rem; text-align: center; position: relative; overflow: hidden; }
        .identity-top::before { content: ''; position: absolute; top: -60px; right: -60px; width: 180px; height: 180px; border-radius: 50%; border: 24px solid rgba(255,255,255,0.06); }
        .identity-top::after { content: ''; position: absolute; bottom: -70px; left: -50px; width: 160px; height: 160px; border-radius: 50%; border: 20px solid rgba(255,255,255,0.04); }
        .profile-avatar { width: 110px; height: 110px; border-radius: 50%; background: var(--primary-light); color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 2.2rem; object-fit: cover; margin: 0 auto 1rem; border: 4px solid rgba(255,255,255,0.35); position: relative; z-index: 1; }
        .identity-top h2 { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; position: relative; z-index: 1; }
        .identity-top .email { font-size: 0.85rem; color: rgba(255,255,255,0.8); word-break: break-all; position: relative; z-index: 1; }
        .status-pill { display: inline-flex; padding: 6px 16px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 1rem; position: relative; z-index: 1; }
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-suspended { background: #ffedd5; color: #c2410c; }

        .identity-stats { flex: 1; display: flex; flex-direction: column; }
        .stat-row { flex: 1; display: flex; align-items: center; gap: 14px; padding: 0 1.5rem; border-bottom: 1px solid var(--border-light); }
        .stat-row:last-child { border-bottom: none; }
        .stat-icon { width: 38px; height: 38px; border-radius: 10px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 18px; height: 18px; }
        .stat-text label { display: block; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px; }
        .stat-text div { font-size: 0.95rem; font-weight: 700; }

        /* Right column: three section cards sharing the full height */
        .sections { display: grid; grid-template-rows: auto auto 1fr; gap: 1.25rem; min-width: 0; }
        .section-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; }
        .section-head { display: flex; align-items: center; gap: 10px; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light); background: #f8fafc; }
        .section-head svg { width: 18px; height: 18px; color: var(--primary); }
        .section-head h3 { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-dark); }
        .field-grid { flex: 1; display: grid; gap: 0; }
        .cols-2 { grid-template-columns: repeat(2, 1fr); }
        .cols-3 { grid-template-columns: repeat(3, 1fr); }
        .field { padding: 1.25rem 1.5rem; border-right: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); display: flex; flex-direction: column; justify-content: center; min-width: 0; }
        .cols-2 .field:nth-child(2n), .cols-3 .field:nth-child(3n) { border-right: none; }
        .field:nth-last-child(-n+2):nth-child(2n+1), .field:nth-last-child(-n+2):nth-child(2n+1) ~ .field { }
        .field label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px; }
        .field div { font-size: 1rem; font-weight: 600; word-break: break-word; }
        .field .mono { font-family: monospace; color: var(--primary); font-weight: 700; }
        .section-card .field-grid > .field:last-child, .section-card .field-grid > .field:nth-last-child(2):nth-child(odd) { border-bottom: none; }
        .cols-3 .field { border-bottom: none; }
        .cols-2.last-row-fix .field:nth-last-child(-n+2) { border-bottom: none; }

        @media (max-width: 1100px) {
            .layout { grid-template-columns: 1fr; }
            .cols-3 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 700px) {
            .main-content { padding: 1.25rem; }
            .cols-2, .cols-3 { grid-template-columns: 1fr; }
            .field { border-right: none; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand">
            <img src="../assets/logo1.png" alt="Logo">
            ScholArchive
        </div>
        <div class="user-profile">
            <div class="avatar"><?= get_initials($admin['first_name'], $admin['last_name']) ?></div>
            <div class="user-info">
                <h4><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></h4>
                <span>System Administrator</span>
            </div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a></li>
            <li><a href="users.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="categories.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
            <li><a href="settings.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a></li>
        </ul>
        <div class="logout-wrap">
            <a href="../logout.php" class="btn-logout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="titles">
                <h1>Student Credentials</h1>
                <p>Complete profile and account details for this student.</p>
            </div>
            <a href="users.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Users
            </a>
        </div>

        <div class="layout">
            <!-- Identity card -->
            <div class="identity-card">
                <div class="identity-top">
                    <?php if ($hasAvatar): ?>
                        <img src="../uploads/<?= e($user['avatar']) ?>" alt="Avatar" class="profile-avatar">
                    <?php else: ?>
                        <div class="profile-avatar"><?= get_initials($user['first_name'], $user['last_name']) ?></div>
                    <?php endif; ?>
                    <h2><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                    <div class="email"><?= e($user['email']) ?></div>
                    <span class="status-pill status-<?= e($user['status']) ?>"><?= e($user['status']) ?></span>
                </div>
                <div class="identity-stats">
                    <div class="stat-row">
                        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg></div>
                        <div class="stat-text"><label>Student ID</label><div><?= e($user['student_id']) ?></div></div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                        <div class="stat-text"><label>Program</label><div><?= e($user['course']) ?> · <?= e($user['year_level']) ?></div></div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <div class="stat-text"><label>Member Since</label><div><?= date('M j, Y', $joinedTs) ?></div></div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <div class="stat-text"><label>Account Age</label><div><?= $daysActive ?> day<?= $daysActive === 1 ? '' : 's' ?></div></div>
                    </div>
                </div>
            </div>

            <!-- Detail sections -->
            <div class="sections">
                <div class="section-card">
                    <div class="section-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <h3>Personal Information</h3>
                    </div>
                    <div class="field-grid cols-2">
                        <div class="field"><label>First Name</label><div><?= e($user['first_name']) ?></div></div>
                        <div class="field"><label>Last Name</label><div><?= e($user['last_name']) ?></div></div>
                        <div class="field" style="grid-column: 1 / -1; border-right: none; border-bottom: none;"><label>Email Address</label><div><?= e($user['email']) ?></div></div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        <h3>Academic Details</h3>
                    </div>
                    <div class="field-grid cols-3">
                        <div class="field"><label>Student ID</label><div class="mono"><?= e($user['student_id']) ?></div></div>
                        <div class="field"><label>Program</label><div><?= e($user['course']) ?></div></div>
                        <div class="field"><label>Year Level</label><div><?= e($user['year_level']) ?></div></div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <h3>Account Information</h3>
                    </div>
                    <div class="field-grid cols-2">
                        <div class="field"><label>Account Status</label><div><?= e(ucfirst($user['status'])) ?></div></div>
                        <div class="field"><label>Role</label><div>Student</div></div>
                        <div class="field"><label>Date Joined</label><div><?= date('F j, Y', $joinedTs) ?></div></div>
                        <div class="field"><label>Time Registered</label><div><?= date('g:i A', $joinedTs) ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>