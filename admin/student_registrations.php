<?php
// admin/student_registrations.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

// 1. Capture Filter, Search, and Sort Parameters
$search = $_GET['search'] ?? '';
$course = $_GET['course'] ?? 'all';
$year = $_GET['year'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? 'all';
$status = $_GET['status'] ?? 'pending'; // Default to pending as shown in design
$sort = $_GET['sort'] ?? 'newest';

// 2. Build the Dynamic SQL Query
$query = "SELECT id, first_name, last_name, email, student_id, course, year_level, status, created_at FROM users WHERE role = 'student'";
$params = [];

// Search Filter
if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ? OR email LIKE ?)";
    $searchParam = "%{$search}%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
}

// Course Filter
if ($course !== 'all') {
    $query .= " AND course = ?";
    $params[] = $course;
}

// Year Level Filter
if ($year !== 'all') {
    $query .= " AND year_level = ?";
    $params[] = $year;
}

// Status Filter
if ($status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status;
}

// Date Filter
if ($date_filter === 'today') {
    $query .= " AND DATE(created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $query .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
}

// Sort Logic
if ($sort === 'oldest') {
    $query .= " ORDER BY created_at ASC";
} elseif ($sort === 'name_asc') {
    $query .= " ORDER BY last_name ASC, first_name ASC";
} else {
    $query .= " ORDER BY created_at DESC"; // Default: Newest
}

// 3. Execute Query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registrations · ScholArchive Admin</title>
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
            --table-header: #1900a6;
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

        /* Main Workspace */
        .main-content { flex: 1; padding: 2rem 3rem; overflow-y: auto; display: flex; flex-direction: column; }
        .page-titles { margin-bottom: 1.5rem; }
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.4rem; letter-spacing: -0.5px; }
        .page-titles p { color: var(--text-muted); font-size: 0.95rem; }

        .alert { padding: 1.25rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 2rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .alert-success { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #991b1b; border-left: 4px solid #ef4444; }

        /* Filter Bar */
        .filter-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 1rem;}
        .search-row { position: relative; width: 100%; }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;}
        .search-input { width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; outline: none; transition: all 0.2s; color: var(--text-dark); background: #f8fafc; font-family: inherit;}
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: var(--white); }
        
        .controls-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .filters-group { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .filter-select { padding: 0.6rem 2rem 0.6rem 1rem; border: none; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); background-color: transparent; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; outline: none; font-family: inherit;}
        .btn-add-filter { display: inline-flex; align-items: center; gap: 6px; color: var(--primary); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: opacity 0.2s; }
        .btn-add-filter:hover { opacity: 0.8; }
        
        .sort-group { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .sort-group .filter-select { padding-left: 0.5rem; color: var(--text-dark); font-weight: 700; }

        /* Premium Table */
        .table-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; flex: 1;}
        .table-scroll-wrap { width: 100%; overflow-y: auto; overflow-x: auto; padding-bottom: 2rem; } 
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 950px; }
        th { background: var(--table-header); color: var(--white); padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; position: sticky; top: 0; z-index: 5; white-space: nowrap;}
        th svg { width: 14px; height: 14px; margin-right: 6px; vertical-align: text-bottom; opacity: 0.8;}
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; vertical-align: middle; transition: background 0.2s; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .student-cluster { display: flex; align-items: center; gap: 12px; }
        .table-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-shrink: 0;}
        .student-name { font-weight: 700; color: var(--text-dark); display: block; margin-bottom: 2px; }
        .student-email { font-size: 0.75rem; color: var(--text-muted); }

        /* Status Pill with Dot */
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; }
        
        .status-active { background: #dcfce7; color: #16a34a; }
        .status-active .status-dot { background: #16a34a; }
        
        .status-pending { background: #ffedd5; color: #c2410c; }
        .status-pending .status-dot { background: #ea580c; }
        
        .status-resubmission { background: #e0f2fe; color: #0369a1; }
        .status-resubmission .status-dot { background: #0284c7; }
        
        .status-rejected { background: #fef2f2; color: #991b1b; }
        .status-rejected .status-dot { background: #dc2626; }
        
        .status-deactivated { background: #f1f5f9; color: #475569; }
        .status-deactivated .status-dot { background: #64748b; }

        /* Action Buttons */
        .btn-review { 
            background: #e0e7ff; 
            color: var(--primary); 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 0.8rem; 
            font-weight: 700; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
            transition: all 0.2s; 
            white-space: nowrap;
        }
        .btn-review:hover { 
            background: var(--primary); 
            color: var(--white); 
            box-shadow: 0 4px 10px rgba(25, 0, 166, 0.2); 
        }

        .empty-state { text-align: center; padding: 5rem 2rem; color: var(--text-muted); }
        .empty-state svg { color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-dark); margin: 0.5rem 0; font-weight: 800; }
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
            <li><a href="dashboard.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> Dashboard</a></li>
            <li><a href="student_registrations.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg> Registrations</a></li>
            <li><a href="users.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="categories.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
            <li><a href="settings.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a></li>
        </ul>
        <div class="logout-wrap">
            <a href="../logout.php" class="btn-logout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 
                Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-titles">
            <h1>Student Registrations</h1>
            <p>Manage and review student account verification requests.</p>
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

        <!-- Functional Filter Bar -->
        <form method="GET" action="" class="filter-card">
            <div class="search-row">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="search-input" placeholder="Search by name, student ID, email..." value="<?= e($search) ?>">
            </div>
            
            <div class="controls-row">
                <div class="filters-group">
                    <select name="course" class="filter-select" onchange="this.form.submit()">
                        <option value="all">Program</option>
                        <option value="BSIT" <?= $course === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                        <option value="BSCS" <?= $course === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                        <option value="BSBA" <?= $course === 'BSBA' ? 'selected' : '' ?>>BSBA</option>
                        <option value="BSA" <?= $course === 'BSA' ? 'selected' : '' ?>>BSA</option>
                        <option value="BSHM" <?= $course === 'BSHM' ? 'selected' : '' ?>>BSHM</option>
                    </select>

                    <select name="year" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Year Levels</option>
                        <option value="1st Year" <?= $year === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= $year === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= $year === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= $year === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                    </select>

                    <select name="date_filter" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Dates</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>

                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Status: All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Status: Pending</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Status: Approved</option>
                        <option value="resubmission" <?= $status === 'resubmission' ? 'selected' : '' ?>>Status: Resubmit</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Status: Rejected</option>
                    </select>

                    <a href="student_registrations.php" class="btn-add-filter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Clear Filters
                    </a>
                </div>

                <div class="sort-group">
                    <span>Sort by:</span>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="table-card">
            <div class="table-scroll-wrap">
                <?php if (count($students) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Student</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg> Student ID</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Program</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Date Submitted</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $stu): ?>
                                <tr>
                                    <td>
                                        <div class="student-cluster">
                                            <div class="table-avatar"><?= get_initials($stu['first_name'], $stu['last_name']) ?></div>
                                            <div>
                                                <span class="student-name"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></span>
                                                <span class="student-email"><?= e($stu['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.9rem; color: var(--primary); font-weight:700;"><?= e($stu['student_id']) ?></td>
                                    <td>
                                        <span style="display:block; font-weight:600; color:var(--text-dark);"><?= e($stu['course']) ?></span>
                                        <span style="color:var(--text-muted); font-size:0.75rem; font-weight:500;"><?= e($stu['year_level']) ?></span>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:0.85rem; font-weight: 500;"><?= date('F j, Y', strtotime($stu['created_at'])) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= $stu['status'] ?>">
                                            <span class="status-dot"></span>
                                            <?= $stu['status'] === 'resubmission' ? 'Needs Resubmit' : $stu['status'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="review.php?id=<?= $stu['id'] ?>" class="btn-review">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <h3>No Records Found</h3>
                        <p>No student registrations match your current search and filter settings.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>