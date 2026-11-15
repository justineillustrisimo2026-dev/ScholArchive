<?php
// admin/view_document.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$categories_map = [ 1 => 'BSIT', 2 => 'BSCS', 3 => 'BSBA', 4 => 'BSA', 5 => 'BSHM' ];

// Fetch document and uploader details using your db schema
$stmt = $pdo->prepare("
    SELECT d.*, u.first_name, u.last_name, u.student_id, u.email 
    FROM documents d 
    LEFT JOIN users u ON d.uploaded_by = u.id 
    WHERE d.id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    flash_set('error', 'Document not found or has been deleted.');
    header('Location: documents.php');
    exit;
}

$catName = $categories_map[$doc['category_id']] ?? 'Unknown';

// Dynamic Category UI helper (Matches documents.php)
function getCategoryUI($catName) {
    $name = strtolower($catName);
    if (strpos($name, 'information technology') !== false || strpos($name, 'bsit') !== false) {
        return ['icon' => '<path d="M2 3h20v14H2z"/><path d="M8 21h8"/><path d="M12 17v4"/>', 'color' => 'blue'];
    } elseif (strpos($name, 'computer science') !== false || strpos($name, 'bscs') !== false) {
        return ['icon' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>', 'color' => 'green'];
    } elseif (strpos($name, 'business') !== false || strpos($name, 'bsba') !== false) {
        return ['icon' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>', 'color' => 'purple'];
    } elseif (strpos($name, 'accountancy') !== false || strpos($name, 'bsa') !== false) {
        return ['icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>', 'color' => 'red'];
    } elseif (strpos($name, 'hospitality') !== false || strpos($name, 'bshm') !== false) {
        return ['icon' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>', 'color' => 'yellow'];
    }
    return ['icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>', 'color' => 'cyan'];
}
$ui = getCategoryUI($catName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Document · ScholArchive Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1900a6; 
            --sidebar-hover: #2100d4; 
            --bg-light: #f8f9fc;
            --white: #ffffff; 
            --text-dark: #1e293b; 
            --text-muted: #64748b;
            --primary: #1900a6; 
            --primary-hover: #100075; 
            --primary-light: #e0e7ff; 
            --danger: #ef4444; 
            --success: #10b981; 
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

        /* Flush Sidebar (Not Floating) */
        .sidebar { 
            width: 280px; 
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0a0045 100%); 
            color: var(--white); 
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
            z-index: 10; 
            box-shadow: 4px 0 15px rgba(25, 0, 166, 0.1);
            height: 100vh;
            position: relative;
            overflow: hidden;
        }
        .sidebar::before {
            content: ''; position: absolute; bottom: -50px; left: -50px; width: 300px; height: 300px;
            border-radius: 50%; border: 40px solid rgba(255,255,255,0.05); pointer-events: none;
        }
        .sidebar::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 250px; height: 250px;
            border-radius: 50%; border: 30px solid rgba(255,255,255,0.03); pointer-events: none;
        }

        .brand { padding: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 800; font-size: 1.15rem; letter-spacing: 0.5px; position: relative; z-index: 2; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .brand img { width: 32px; height: 32px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; }
        
        .user-profile { margin: 1rem; background: rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; border: 1px solid rgba(255,255,255,0.1);}
        .avatar { width: 40px; height: 40px; background-color: #3b82f6; color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem; position: relative; }
        .status-dot { position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background-color: #22c55e; border-radius: 50%; border: 2px solid var(--sidebar-bg); }
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
        
        /* Interactive Back Button */
        .header-top { margin-bottom: 1.5rem; display: flex; align-items: center; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--white); color: var(--text-dark); padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; border: 1px solid var(--border-light); text-decoration: none; transition: all 0.2s; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .btn-back:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateX(-4px); box-shadow: 0 6px 15px rgba(25, 0, 166, 0.2); }

        <?php if (!empty($success)): ?>
            .alert-success { background:rgba(255,255,255,0.9); backdrop-filter: blur(10px); color:#065f46; border-left:4px solid #10b981; padding:1.25rem; border-radius:12px; font-size:0.9rem; font-weight:600; margin-bottom:2rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);}
        <?php endif; ?>

        /* Split-Screen Interactive Layout */
        .review-layout { display: grid; grid-template-columns: 1fr 1.3fr; gap: 2rem; flex: 1; min-height: 0; }

        /* Left Side: Metadata Card (Glassmorphism) */
        .meta-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 15px 35px rgba(0,0,0,0.05); display: flex; flex-direction: column; overflow-y: auto; }
        .meta-header { padding: 2.5rem; border-bottom: 1px solid var(--border-light); background: linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(248,250,252,0.8) 100%); }
        
        /* Status Pill */
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.25rem; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; }
        .status-published { background: #dcfce7; color: #16a34a; border: 1px solid #a7f3d0; }
        .status-published .status-dot { background: #16a34a; }
        .status-draft { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .status-draft .status-dot { background: #64748b; }
        .status-pending { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
        .status-pending .status-dot { background: #ea580c; }
        
        .doc-title { font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem; line-height: 1.25; letter-spacing: -0.5px; }
        
        /* New Styled Tag Pills */
        .meta-tags { display: flex; flex-wrap: wrap; gap: 1rem; }
        .tag-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--white); border: 1px solid var(--border-light); padding: 8px 16px; border-radius: 10px; font-size: 0.85rem; font-weight: 700; color: var(--text-dark); box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s;}
        .tag-btn:hover { border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .tag-icon-wrap { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; }
        
        .icon-blue { background: #e0e7ff; color: #4f46e5; }
        .icon-green { background: #dcfce7; color: #16a34a; }
        .icon-purple { background: #f3e8ff; color: #9333ea; }
        .icon-red { background: #fee2e2; color: #dc2626; }
        .icon-yellow { background: #fef3c7; color: #d97706; }
        .icon-cyan { background: #cffafe; color: #0891b2; }
        .tag-year .tag-icon-wrap { background: #fefce8; color: #a16207; }
        .tag-date .tag-icon-wrap { background: #f1f5f9; color: #475569; }

        .meta-body { padding: 2.5rem; flex: 1; }
        .section-title { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; }
        .section-title svg { color: var(--primary); }
        
        .abstract-text { font-size: 1rem; line-height: 1.8; color: #334155; background: #f8fafc; padding: 1.75rem; border-radius: 12px; border: 1px solid var(--border-light); margin-bottom: 2.5rem; text-align: justify; }
        .authors-text { font-size: 1rem; font-weight: 600; margin-bottom: 2.5rem; padding: 1rem 1.5rem; background: #fff; border: 1px solid var(--border-light); border-left: 4px solid var(--primary); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

        .uploader-info { display: flex; align-items: center; gap: 1.25rem; background: var(--white); border: 1px solid var(--border-light); padding: 1.25rem; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .uploader-avatar { width: 48px; height: 48px; background: #e0e7ff; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; }
        .uploader-details h4 { font-size: 1rem; color: var(--text-dark); margin-bottom: 4px; font-weight: 800;}
        .uploader-details span { font-size: 0.8rem; color: var(--text-muted); display: block; font-weight: 500;}

        /* Right Side: Interactive PDF Preview Card */
        .preview-card { background: var(--text-dark); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .preview-header { background: #0f172a; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1e293b; }
        .preview-header h3 { color: white; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .btn-fullscreen { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-fullscreen:hover { background: rgba(255,255,255,0.2); transform: translateY(-1px);}
        
        .pdf-container { flex: 1; width: 100%; background: #020617; }
        .pdf-container iframe { width: 100%; height: 100%; border: none; }
        
        .missing-file { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; text-align: center; padding: 3rem; background: #0f172a;}
        .missing-file svg { margin-bottom: 1.5rem; color: #334155; }
        
        @media (max-width: 1024px) {
            .review-layout { grid-template-columns: 1fr; }
            .preview-card { min-height: 600px; }
            .sidebar { height: 100vh; }
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
            <div class="avatar">
                <?= get_initials($admin['first_name'], $admin['last_name']) ?>
                <div class="status-dot"></div>
            </div>
            <div class="user-info">
                <h4><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></h4>
                <span>System Administrator</span>
            </div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a></li>
            <li><a href="users.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="categories.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
            <li><a href="settings.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a></li>
        </ul>
        <div class="logout-wrap">
            <a href="../logout.php" class="btn-logout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 
                Log Out Securely
            </a>
        </div>
    </aside>

    <main class="main-content">
        
        <div class="header-top">
            <a href="documents.php" class="btn-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                Back to Documents
            </a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert-success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <!-- Split Layout -->
        <div class="review-layout">
            
            <!-- LEFT: Metadata Card -->
            <div class="meta-card">
                <div class="meta-header">
                    <span class="status-pill status-<?= $doc['status'] ?>">
                        <span class="status-dot"></span>
                        <?= $doc['status'] === 'pending' ? 'Pending' : $doc['status'] ?> Document
                    </span>
                    <h1 class="doc-title"><?= e($doc['title']) ?></h1>
                    
                    <div class="meta-tags">
                        <div class="tag-btn tag-primary">
                            <div class="tag-icon-wrap icon-<?= $ui['color'] ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><?= $ui['icon'] ?></svg>
                            </div>
                            <?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $catName)) ?>
                        </div>
                        <div class="tag-btn tag-year">
                            <div class="tag-icon-wrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                            </div>
                            A.Y. <?= e($doc['pub_year']) ?>
                        </div>
                        <div class="tag-btn tag-date">
                            <div class="tag-icon-wrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            Uploaded <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <div class="meta-body">
                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Research Abstract
                    </div>
                    <div class="abstract-text">
                        <?= nl2br(e($doc['abstract'])) ?>
                    </div>
                    
                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Authors / Researchers
                    </div>
                    <div class="authors-text">
                        <?= e($doc['authors']) ?>
                    </div>

                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        System Uploader
                    </div>
                    <div class="uploader-info">
                        <div class="uploader-avatar">
                            <?= $doc['first_name'] ? get_initials($doc['first_name'], $doc['last_name']) : 'A' ?>
                        </div>
                        <div class="uploader-details">
                            <h4><?= $doc['first_name'] ? e($doc['first_name'] . ' ' . $doc['last_name']) : 'System Administrator' ?></h4>
                            <span><?= $doc['first_name'] ? e($doc['student_id']) . ' • ' . e($doc['email']) : 'Official System Account' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: PDF Viewer Card -->
            <div class="preview-card">
                <div class="preview-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
                        Live PDF Preview
                    </h3>
                    <a href="../uploads/documents/<?= e($doc['file_path']) ?>" target="_blank" class="btn-fullscreen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                        Fullscreen
                    </a>
                </div>
                
                <div class="pdf-container">
                    <?php 
                    $filePath = __DIR__ . '/../uploads/documents/' . $doc['file_path'];
                    if (!empty($doc['file_path']) && file_exists($filePath)): 
                    ?>
                        <!-- Interactive Embedded PDF Player -->
                        <iframe src="../uploads/documents/<?= e($doc['file_path']) ?>#toolbar=0&navpanes=0&scrollbar=1" title="PDF Preview"></iframe>
                    <?php else: ?>
                        <div class="missing-file">
                            <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            <h2>File Not Found</h2>
                            <p style="margin-top: 8px;">The PDF file associated with this record could not be found on the server.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </main>
    <ElicitationsGroup></ElicitationsGroup>
</body>
</html>