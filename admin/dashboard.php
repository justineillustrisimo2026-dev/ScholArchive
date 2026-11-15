<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Protect this page: Only admins allowed
$admin = require_admin();
$pdo = get_db();

// 1. Fetch Dynamic Counts for Stat Cards
try {
    $activeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
    // Students who joined in the last 7 days (they are approved automatically by the roster check)
    $newStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active' AND created_at >= ?");
    $newStmt->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
    $newThisWeek = $newStmt->fetchColumn();
    
    // Document Stats
    $publishedDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'published'")->fetchColumn();
    $pendingDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) { 
    $activeCount = 0; $newThisWeek = 0; $publishedDocs = 0; $pendingDocs = 0; 
}

// 2. Fetch Real-Time Data for the Document Category Chart (Bar Chart)
try {
    $catStmt = $pdo->query("
        SELECT c.name, COUNT(d.id) as doc_count 
        FROM categories c 
        LEFT JOIN documents d ON c.id = d.category_id 
        GROUP BY c.id 
        ORDER BY doc_count DESC
    ");
    $chartData = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = [];
    $chartValues = [];
    foreach ($chartData as $row) {
        $cleanName = str_replace(['BS ', 'Bachelor of Science in '], '', $row['name']);
        $chartLabels[] = $cleanName;
        $chartValues[] = (int)$row['doc_count'];
    }
} catch (Exception $e) {
    $chartLabels = [];
    $chartValues = [];
}

// 3. Fetch Real-Time Data for Student Distribution (Doughnut Chart)
try {
    $studentStmt = $pdo->query("
        SELECT course, COUNT(id) as student_count 
        FROM users 
        WHERE role = 'student' AND status = 'active' 
        GROUP BY course 
        ORDER BY student_count DESC
    ");
    $studentData = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $studentLabels = [];
    $studentValues = [];
    foreach ($studentData as $row) {
        $studentLabels[] = empty($row['course']) ? 'Unspecified' : $row['course'];
        $studentValues[] = (int)$row['student_count'];
    }
} catch (Exception $e) {
    $studentLabels = [];
    $studentValues = [];
}

// 4. Fetch Recent Pending Documents (waiting for admin review)
try {
    $stmtPendingDocs = $pdo->query("
        SELECT d.id, d.title, d.uploaded_by, d.created_at, c.name AS category
        FROM documents d
        LEFT JOIN categories c ON c.id = d.category_id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $pending_docs = $stmtPendingDocs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending_docs = [];
}

$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js for real-time graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --table-header: #1900a6;
            --accent-orange: #f59e0b;
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

        /* Flush Sidebar */
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
        .user-info h4 { font-size: 0.85rem; margin-bottom: 2px; font-weight: 600;}
        .user-info span { font-size: 0.7rem; color: rgba(255,255,255,0.7); }
        
        .nav-links { flex: 1; padding: 0 1rem; list-style: none; overflow-y: auto; position: relative; z-index: 2;}
        .nav-links li { margin-bottom: 0.25rem; }
        .nav-links a { display: flex; align-items: center; gap: 12px; padding: 0.85rem 1rem; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 10px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255,255,255,0.15); color: var(--white); }
        
        .logout-wrap { padding: 1.25rem; position: relative; z-index: 2; border-top: 1px solid rgba(255,255,255,0.08); }
        .btn-logout { display: flex; align-items: center; justify-content: flex-start; gap: 10px; width: 100%; background: transparent; color: var(--white); padding: 0.85rem 1rem; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,255,255,0.15); }

        /* Main Workspace FIX: Added flex-shrink to prevent collapsing */
        .main-content { 
            flex: 1; 
            padding: 2rem 3rem 4rem 3rem; 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 1.5rem; 
        }
        
        /* This prevents the tables and charts from squishing vertically */
        .main-content > * {
            flex-shrink: 0;
        }
        
        .top-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.4rem; letter-spacing: -0.5px; }
        .page-titles p { color: var(--text-muted); font-size: 0.95rem; }
        .ay-badge { background: var(--white); color: var(--primary); padding: 0.6rem 1.25rem; border-radius: 20px; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.5px; box-shadow: 0 4px 10px rgba(25, 0, 166, 0.08); display: flex; align-items: center; gap: 8px; border: 1px solid var(--border-light); }

        /* Premium Hero Banner */
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
            padding: 3rem 3rem;
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
        /* Stat Cards Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
        .stat-card { background: var(--white); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-light); box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.2s; cursor: pointer; text-decoration: none;}
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        .stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .stat-label { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--text-dark); display: flex; justify-content: space-between; align-items: center;}
        
        /* Middle Grid (2 Columns for Balanced Charts) */
        .middle-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; }
        .card { background: var(--white); border-radius: 12px; padding: 1.75rem; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; width: 100%; }
        .card-header h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); }
        .card-sub { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
        .pill-badge { background: var(--primary-light); padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; color: var(--primary); }
        
        .chart-container { position: relative; height: 300px; width: 100%; }

        /* Premium Table Section */
        .table-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 2rem;}
        .table-header { padding: 1.5rem 1.75rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); background: #f8fafc;}
        .table-header h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); }
        .btn-view-all { background: var(--white); border: 1px solid var(--border-light); padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: var(--primary); text-decoration: none; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
        .btn-view-all:hover { background: var(--primary-light); border-color: var(--primary-light); transform: translateY(-1px);}

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: var(--table-header); color: var(--white); padding: 1.25rem 1.75rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; white-space: nowrap;}
        th svg { width: 14px; height: 14px; margin-right: 6px; vertical-align: text-bottom; opacity: 0.8;}
        td { padding: 1.25rem 1.75rem; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; vertical-align: middle; transition: background 0.2s;}
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .student-cluster { display: flex; align-items: center; gap: 12px; }
        .table-avatar { width: 38px; height: 38px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0;}
        .student-name { font-weight: 700; color: var(--text-dark); display: block; margin-bottom: 2px;}
        .student-email { font-size: 0.75rem; color: var(--text-muted); }
        
        .btn-review { background: var(--primary-light); color: var(--primary); padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-review:hover { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(25, 0, 166, 0.2); transform: translateY(-1px);}
        
        .empty-state { text-align: center; padding: 4rem; color: var(--text-muted); font-size: 0.95rem; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;}
        .empty-state svg { color: #cbd5e1; margin-bottom: 16px; }
        .empty-state h3 { font-weight: 800; color: var(--text-dark); margin-bottom: 8px; font-size: 1.1rem;}

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .middle-grid { grid-template-columns: 1fr; }
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
            </div>
            <div class="user-info">
                <h4><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></h4>
                <span>System Administrator</span>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a></li>
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
        
        <div class="top-header">
            <div class="page-titles">
                <h1>Dashboard</h1>
                <p>Overview of repository stats and metrics</p>
            </div>
            <div class="ay-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                A.Y. 2026-2027
            </div>
        </div>

        <!-- HERO BANNER -->
        <div class="hero-banner">
            <div class="hero-content">
                <h1>Schol<span class="highlight">Archive</span></h1>
                <h2>ACLC College of Mandaue</h2>
                <p>Preserving Knowledge. Empowering Learners.</p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="stats-grid">
            <a href="users.php" class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Active Students</span>
                    <div class="stat-icon" style="background:#e0e7ff; color:#4f46e5;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
                </div>
                <div class="stat-value">
                    <?= number_format($activeCount) ?> 
                    <svg style="color:#cbd5e1; width:22px; height:22px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </div>
            </a>

            <a href="users.php?sort=newest" class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">New This Week</span>
                    <div class="stat-icon" style="background:#cffafe; color:#0891b2;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                </div>
                <div class="stat-value">
                    <?= number_format($newThisWeek) ?> 
                    <svg style="color:#cbd5e1; width:22px; height:22px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </div>
            </a>

            <a href="documents.php" class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Published Docs</span>
                    <div class="stat-icon" style="background:#dcfce7; color:#16a34a;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/></svg></div>
                </div>
                <div class="stat-value">
                    <?= number_format($publishedDocs) ?> 
                    <svg style="color:#cbd5e1; width:22px; height:22px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </div>
            </a>

            <a href="documents.php?status=pending" class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Pending Requests</span>
                    <div class="stat-icon" style="background:#fee2e2; color:#dc2626;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                </div>
                <div class="stat-value" style="color: <?= $pendingDocs > 0 ? '#dc2626' : 'var(--text-dark)' ?>;">
                    <?= number_format($pendingDocs) ?> 
                    <svg style="color:#cbd5e1; width:22px; height:22px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </div>
            </a>
        </div>

        <!-- Middle Grid: 2 Columns for Charts -->
        <div class="middle-grid">
            
            <!-- Left Chart: Documents by Category -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3>Documents by Category</h3>
                        <div class="card-sub">Real-time breakdown of all published capstones.</div>
                    </div>
                    <span class="pill-badge">Live Analytics</span>
                </div>
                <div class="chart-container">
                    <?php if (count($chartValues) > 0 && array_sum($chartValues) > 0): ?>
                        <canvas id="categoryChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            <p style="margin-top:10px; font-weight:500;">No published documents to graph.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Chart: Student Distribution -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3>Student Distribution</h3>
                        <div class="card-sub">Active users across academic programs.</div>
                    </div>
                </div>
                <div class="chart-container">
                    <?php if (count($studentValues) > 0 && array_sum($studentValues) > 0): ?>
                        <canvas id="studentChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <p style="margin-top:10px; font-weight:500;">No active students to graph.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Bottom Table: Pending Documents Review -->
        <div class="table-card">
            <div class="table-header">
                <h3 style="font-size:1.1rem; color:var(--text-dark); font-weight:800;">Pending Documents Review</h3>
                <a href="documents.php?status=pending" class="btn-view-all">View All Pending</a>
            </div>

            <?php if (count($pending_docs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Document</th>
                            <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Category</th>
                            <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Uploaded By</th>
                            <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Date Submitted</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_docs as $doc): ?>
                            <tr>
                                <td>
                                    <div class="student-cluster">
                                        <div class="table-avatar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                                        <span class="student-name" style="margin:0;"><?= e($doc['title']) ?></span>
                                    </div>
                                </td>
                                <td style="font-weight:600; color:var(--text-dark);"><?= e($doc['category'] ?? 'Uncategorized') ?></td>
                                <td style="font-weight:500; color:var(--text-dark);"><?= e($doc['uploaded_by']) ?></td>
                                <td style="color:var(--text-muted); font-weight:500;"><?= date('F j, Y', strtotime($doc['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="documents.php?status=pending" class="btn-review">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <h3>All Caught Up</h3>
                    <p>There are no documents waiting for review right now.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Chart.js Scripts -->
    <script>
        // Shared Color Palette
        const palette = [
            'rgba(25, 0, 166, 0.85)',   // Primary Blue
            'rgba(16, 185, 129, 0.85)',  // Emerald Green
            'rgba(245, 158, 11, 0.85)',  // Amber Orange
            'rgba(168, 85, 247, 0.85)',  // Purple
            'rgba(59, 130, 246, 0.85)'   // Light Blue
        ];

        // 1. Bar Chart: Documents by Category
        <?php if (count($chartValues) > 0 && array_sum($chartValues) > 0): ?>
        const ctxBar = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Published Documents',
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: palette,
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', titleFont: { size: 13, family: "'Inter', sans-serif" }, bodyFont: { size: 14, weight: 'bold', family: "'Inter', sans-serif" }, padding: 12, cornerRadius: 8 }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Inter', sans-serif", size: 11 } }, grid: { color: 'rgba(226, 232, 240, 0.8)', drawBorder: false } },
                    x: { ticks: { font: { family: "'Inter', sans-serif", size: 12, weight: 600 } }, grid: { display: false, drawBorder: false } }
                }
            }
        });
        <?php endif; ?>

        // 2. Doughnut Chart: Student Distribution
        <?php if (count($studentValues) > 0 && array_sum($studentValues) > 0): ?>
        const ctxDoughnut = document.getElementById('studentChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($studentLabels) ?>,
                datasets: [{
                    data: <?= json_encode($studentValues) ?>,
                    backgroundColor: palette,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, padding: 20, font: { family: "'Inter', sans-serif", size: 12, weight: 600 } } },
                    tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', titleFont: { size: 13, family: "'Inter', sans-serif" }, bodyFont: { size: 14, weight: 'bold', family: "'Inter', sans-serif" }, padding: 12, cornerRadius: 8 }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>