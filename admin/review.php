<?php
// admin/review.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash_set('error', 'Student not found.');
    header('Location: student_registrations.php');
    exit;
}

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $update = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $update->execute([$studentId]);
        if (function_exists('log_audit')) log_audit($pdo, $admin['id'], 'Approved Student', "Approved registration for {$student['first_name']} {$student['last_name']} ({$student['student_id']}).");
        flash_set('success', "Student {$student['first_name']} has been approved and granted access.");
    } elseif ($action === 'reject') {
        $update = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $update->execute([$studentId]);
        if (function_exists('log_audit')) log_audit($pdo, $admin['id'], 'Rejected Student', "Rejected registration for {$student['first_name']} {$student['last_name']}.");
        flash_set('success', "Student {$student['first_name']}'s registration was rejected.");
    } elseif ($action === 'resubmission') {
        $update = $pdo->prepare("UPDATE users SET status = 'resubmission' WHERE id = ?");
        $update->execute([$studentId]);
        if (function_exists('log_audit')) log_audit($pdo, $admin['id'], 'Requested Resubmission', "Requested document resubmission for {$student['first_name']} {$student['last_name']}.");
        flash_set('success', "Requested credentials resubmission from {$student['first_name']}.");
    }

    header('Location: student_registrations.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Credentials · ScholArchive Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1900a6; --sidebar-hover: #2100d4; 
            --bg-light: #f8f9fc; --white: #ffffff; 
            --text-dark: #1e293b; --text-muted: #64748b;
            --primary: #1900a6; --primary-hover: #100075; --primary-light: #e0e7ff; 
            --danger: #ef4444; --success: #10b981; --warning: #f59e0b; 
            --border-light: #e2e8f0; --font-family: 'Inter', sans-serif;
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
        .main-content { flex: 1; padding: 2rem 3rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.25rem; }
        
        .header-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--white); color: var(--text-dark); padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; border: 1px solid var(--border-light); text-decoration: none; transition: all 0.2s; font-size: 0.85rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .btn-back:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateX(-4px); box-shadow: 0 6px 10px rgba(0,0,0,0.05); }
        
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; margin: 0; }

        /* Review Layout Grid */
        .review-grid { display: grid; grid-template-columns: 350px 1fr; gap: 1.5rem; align-items: start; }

        /* Profile Card */
        .profile-card { background: var(--white); border-radius: 12px; padding: 1.75rem; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .profile-header { text-align: center; margin-bottom: 1.75rem; }
        .large-avatar { width: 80px; height: 80px; background: #e0e7ff; color: var(--primary); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; margin-bottom: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .profile-header h2 { font-size: 1.3rem; font-weight: 800; margin-bottom: 8px; color: var(--text-dark); }
        
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .status-active { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .status-rejected { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .status-resubmission { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .info-group { margin-bottom: 1.25rem; }
        .info-group:last-child { margin-bottom: 0; }
        .info-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .info-value { font-size: 0.95rem; font-weight: 600; color: var(--text-dark); background: #f8fafc; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-light); display: flex; align-items: center; gap: 10px;}
        .info-value svg { color: #94a3b8; flex-shrink: 0; }

        /* Photos & Actions Card */
        .photos-card { background: var(--white); border-radius: 12px; padding: 1.75rem; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
        .photos-header h3 { font-size: 1.2rem; font-weight: 800; margin-bottom: 1.25rem; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .photos-header svg { color: var(--primary); }

        .photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.75rem; }
        .photo-item { border: 1px solid var(--border-light); border-radius: 10px; overflow: hidden; background: #f8fafc; display: flex; flex-direction: column; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        .photo-label { background: var(--white); padding: 0.85rem; font-size: 0.85rem; font-weight: 700; text-align: center; border-bottom: 1px solid var(--border-light); color: var(--text-dark); display: flex; align-items: center; justify-content: center; gap: 8px; }
        
        /* Image Wrapper for better framing */
        .photo-img-wrap { 
            padding: 1.5rem; text-align: center; height: auto; min-height: 250px; 
            display: flex; align-items: center; justify-content: center; position: relative; 
            background-image: linear-gradient(45deg, #f1f5f9 25%, transparent 25%), 
                              linear-gradient(-45deg, #f1f5f9 25%, transparent 25%), 
                              linear-gradient(45deg, transparent 75%, #f1f5f9 75%), 
                              linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
        
        .photo-img-wrap img { max-width: 100%; max-height: 350px; object-fit: contain; border-radius: 8px; cursor: zoom-in; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background: white; padding: 6px; }
        .photo-img-wrap img:hover { transform: scale(1.03); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        
        .zoom-hint { position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px); color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; pointer-events: none; opacity: 0; transition: opacity 0.2s; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .photo-img-wrap:hover .zoom-hint { opacity: 1; }

        /* Action Buttons */
        .action-bar { display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1.5rem; border-top: 1px solid var(--border-light); }
        .btn { padding: 0.85rem 1.5rem; border-radius: 8px; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .btn-approve { background: var(--success); color: white; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25); }
        .btn-approve:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(16, 185, 129, 0.35); }
        
        .btn-resubmit { background: var(--warning); color: white; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.25); }
        .btn-resubmit:hover { background: #d97706; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(245, 158, 11, 0.35); }
        
        .btn-reject { background: white; color: var(--danger); border: 2px solid var(--danger); box-shadow: none; padding: 0.75rem 1.5rem;}
        .btn-reject:hover { background: var(--danger); color: white; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3); }

        /* Image Lightbox/Zoom Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.9); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
        .modal-content { max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); animation: zoomIn 0.2s ease-out; object-fit: contain; }
        @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .close { position: absolute; top: 20px; right: 40px; color: #94a3b8; font-size: 40px; font-weight: bold; transition: 0.2s; cursor: pointer; }
        .close:hover, .close:focus { color: var(--danger); text-decoration: none; }
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
            <li><a href="users.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
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
        
        <div class="header-top">
            <a href="student_registrations.php" class="btn-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                Back to Registrations
            </a>
            <div class="page-titles">
                <h1>Review Credentials</h1>
            </div>
        </div>

        <div class="review-grid">
            
            <!-- Left Info Panel -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="large-avatar"><?= get_initials($student['first_name'], $student['last_name']) ?></div>
                    <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                    <div class="status-badge status-<?= $student['status'] ?>"><?= e($student['status']) ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Student ID No.</div>
                    <div class="info-value">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg>
                        <span style="font-family: monospace; font-size:1.05rem; color:var(--primary);"><?= e($student['student_id']) ?></span>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Email Address</div>
                    <div class="info-value">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <?= e($student['email']) ?>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Course / Program</div>
                    <div class="info-value">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        <?= e($student['course']) ?> (<?= e($student['year_level']) ?>)
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Date Applied</div>
                    <div class="info-value">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        <?= date('M j, Y - g:i A', strtotime($student['created_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Right Photos Panel -->
            <div class="photos-card">
                <div class="photos-header">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <h3>Verification Documents</h3>
                </div>

                <div class="photo-grid">
                    <div class="photo-item">
                        <div class="photo-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg>
                            Student ID Card
                        </div>
                        <div class="photo-img-wrap">
                            <?php if(!empty($student['id_photo'])): ?>
                                <img src="../uploads/<?= e($student['id_photo']) ?>" alt="Student ID" class="zoomable-img">
                                <span class="zoom-hint"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg> Zoom</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size: 0.9rem; font-weight: 500;">No ID uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="photo-item">
                        <div class="photo-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg>
                            Live Verification Selfie
                        </div>
                        <div class="photo-img-wrap">
                            <?php if(!empty($student['selfie'])): ?>
                                <img src="../uploads/<?= e($student['selfie']) ?>" alt="Selfie" class="zoomable-img">
                                <span class="zoom-hint"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg> Zoom</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size: 0.9rem; font-weight: 500;">No image uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Forms -->
                <form method="POST" action="">
                    <div class="action-bar">
                        <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Are you sure you want to REJECT this application? This action will mark the account as rejected.');">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg> Reject
                        </button>
                        
                        <button type="submit" name="action" value="resubmission" class="btn btn-resubmit" onclick="return confirm('Are you sure you want to request a document resubmission? The student will remain pending until they provide better credentials.');">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg> Request Resubmission
                        </button>
                        
                        <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Approve this student? They will immediately gain full access to the ScholArchive repository.');">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> Approve Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- The Image Zoom Modal -->
    <div id="imageModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="zoomedImg">
    </div>

    <script>
        var modal = document.getElementById("imageModal");
        var modalImg = document.getElementById("zoomedImg");
        var images = document.getElementsByClassName("zoomable-img");
        
        for (var i = 0; i < images.length; i++) {
            images[i].onclick = function(){
                modal.style.display = "flex";
                modalImg.src = this.src;
            }
        }
        
        var span = document.getElementsByClassName("close")[0];
        span.onclick = function() { modal.style.display = "none"; }
        
        modal.onclick = function(e) {
            if (e.target !== modalImg) { modal.style.display = "none"; }
        }
    </script>
</body>
</html>
```<ElicitationsGroup></ElicitationsGroup>