<?php
// admin/users.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

// 1. Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    // Edit User Action
    if ($action === 'edit' && $userId > 0) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $studentId = trim($_POST['student_id'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');

        if (!empty($firstName) && !empty($email)) {
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, student_id=?, course=?, year_level=? WHERE id=?");
            $stmt->execute([$firstName, $lastName, $email, $studentId, $course, $yearLevel, $userId]);
            flash_set('success', 'Student account details successfully updated.');
        } else {
            flash_set('error', 'Required fields cannot be empty.');
        }
        header('Location: users.php');
        exit;
    }

    // Toggle Suspend / Activate Action
    if ($action === 'toggle_status' && $userId > 0) {
        $currentStatus = $_POST['current_status'] ?? 'active';
        $newStatus = ($currentStatus === 'active') ? 'suspended' : 'active';
        
        $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->execute([$newStatus, $userId]);
        
        $actionWord = ($newStatus === 'active') ? 'reactivated' : 'suspended';
        flash_set('success', "Student account has been successfully {$actionWord}.");
        header('Location: users.php');
        exit;
    }

    // Permanent Delete Action
    if ($action === 'delete' && $userId > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$userId]);
        flash_set('success', 'Student account permanently deleted from the database.');
        header('Location: users.php');
        exit;
    }
}

// 2. Build Dynamic Search and Filter Query
$activeCourse = $_GET['course'] ?? 'all';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

$query = "SELECT * FROM users WHERE role = 'student' AND status IN ('active', 'suspended')";
$params = [];

if ($activeCourse !== 'all') { 
    $query .= " AND course = ?"; 
    $params[] = $activeCourse; 
}
if (!empty($search)) { 
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)"; 
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]); 
}
if ($status !== 'all') { 
    $query .= " AND status = ?"; 
    $params[] = $status; 
}

if ($sort === 'oldest') { 
    $query .= " ORDER BY created_at ASC"; 
} elseif ($sort === 'name_asc') { 
    $query .= " ORDER BY last_name ASC, first_name ASC"; 
} else { 
    $query .= " ORDER BY created_at DESC"; 
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$success = flash_get('success');
$error = flash_get('error');

$courses = ['BSIT', 'BSCS', 'BSA', 'BSBA', 'BSHM'];

// Overview counts (not affected by the filters)
$stats = ['total' => 0, 'active' => 0, 'suspended' => 0, 'new' => 0];
try {
    $statRows = $pdo->query("SELECT status, COUNT(*) AS c FROM users WHERE role = 'student' AND status IN ('active', 'suspended') GROUP BY status")->fetchAll();
    foreach ($statRows as $r) { $stats[$r['status']] = (int)$r['c']; $stats['total'] += (int)$r['c']; }
    $newStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status IN ('active', 'suspended') AND created_at >= ?");
    $newStmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
    $stats['new'] = (int)$newStmt->fetchColumn();
} catch (PDOException $e) { /* counts stay at 0 */ }

// The student's profile photo. The profile page saves it in `selfie`
// (an `avatar` column is used first if you ever add one). Returns '' when there is no photo.
if (!function_exists('users_photo_file')) {
    function users_photo_file(array $u): string {
        foreach (['avatar', 'selfie'] as $col) {
            if (!empty($u[$col])) {
                $f = basename((string)$u[$col]);
                if (is_file(__DIR__ . '/../uploads/' . $f)) return $f;
            }
        }
        return '';
    }
}
// Soft background/text colour for the initials circle (same student = same colour)
if (!function_exists('users_avatar_style')) {
    function users_avatar_style(int $id): string {
        $p = [['#e0e7ff', '#4338ca'], ['#dcfce7', '#15803d'], ['#fef3c7', '#b45309'], ['#fce7f3', '#be185d'], ['#e0f2fe', '#0369a1'], ['#ede9fe', '#6d28d9']];
        $c = $p[$id % count($p)];
        return "--av-bg:{$c[0]};--av-fg:{$c[1]}";
    }
}
if (!function_exists('users_ago')) {
    function users_ago(string $dt): string {
        $d = (int)floor((time() - strtotime($dt)) / 86400);
        if ($d <= 0) return 'Today';
        if ($d === 1) return 'Yesterday';
        if ($d < 30) return $d . ' days ago';
        if ($d < 365) return floor($d / 30) . ' mo ago';
        return floor($d / 365) . ' yr ago';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users · ScholArchive Admin</title>
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
        
        .header-top { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.4rem; letter-spacing: -0.5px; }
        .page-titles p { color: var(--text-muted); font-size: 0.95rem; }

        .alert { padding: 1.25rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 2rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .alert-success { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #991b1b; border-left: 4px solid #ef4444; }

        /* Full Width Search & Filter Card */
        .filter-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 1.25rem;}
        
        .filter-primary-row { display: flex; gap: 1.25rem; align-items: center; width: 100%; flex-wrap: wrap; }
        
        .category-pills { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0; flex-shrink: 0; }
        .category-pills::-webkit-scrollbar { display: none; }
        .cat-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 24px; border: 1px solid var(--border-light); background: var(--white); color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; white-space: nowrap; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .cat-pill:hover { border-color: var(--primary-light); color: var(--primary); background: #f8fafc; }
        .cat-pill.active { background: var(--primary); color: var(--white); border-color: var(--primary); box-shadow: 0 4px 10px rgba(25, 0, 166, 0.25); }

        .search-row { position: relative; flex: 1; min-width: 250px; }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; height: 18px;}
        .search-input { width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; outline: none; transition: all 0.2s; color: var(--text-dark); background: #f8fafc; font-family: inherit;}
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: var(--white); }
        
        .controls-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 1px solid var(--border-light); padding-top: 1rem; }
        .filters-group { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .filter-select { padding: 0.6rem 2rem 0.6rem 1rem; border: none; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); background-color: transparent; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right center; outline: none; font-family: inherit;}
        .btn-add-filter { display: inline-flex; align-items: center; gap: 6px; color: var(--primary); font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: opacity 0.2s; }
        .btn-add-filter:hover { opacity: 0.8; }
        
        .sort-group { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .sort-group .filter-select { padding-left: 0.5rem; color: var(--text-dark); font-weight: 700; }

        /* Premium Table Card */
        .table-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; flex: 1;}
        .table-scroll-wrap { width: 100%; overflow-y: auto; overflow-x: auto; padding-bottom: 3rem; } 
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 950px; }
        
        th { background: var(--table-header); color: var(--white); padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; position: sticky; top: 0; z-index: 5; white-space: nowrap;}
        th svg { width: 14px; height: 14px; margin-right: 6px; vertical-align: text-bottom; opacity: 0.8;}
        
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; vertical-align: middle; transition: background 0.2s; }
        tr { transition: all 0.2s ease; }
        tr:hover td { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }

        .student-cluster { display: flex; align-items: center; gap: 12px; }
        .table-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; object-fit: cover;}
        
        /* Clickable Title Name */
        .student-name-link { font-weight: 700; color: var(--primary); display: block; margin-bottom: 2px; text-decoration: none; font-size: 0.95rem; transition: color 0.2s; cursor: pointer;}
        .student-name-link:hover { text-decoration: underline; color: var(--primary-hover); }
        .student-email { font-size: 0.75rem; color: var(--text-muted); }

        /* Status Pill (NO DOT) */
        .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-active { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0;}
        .status-suspended { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;}

        /* INLINE ICON ACTION BAR */
        .actions-cell { text-align: right; }
        .icon-action-bar { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
        
        .btn-icon-action { width: 34px; height: 34px; border-radius: 8px; border: none; background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; color: var(--text-muted); }
        .btn-icon-action svg { width: 18px; height: 18px; }
        
        .btn-edit:hover { background: #e0e7ff; color: #4f46e5; }
        .btn-suspend:hover { background: #ffedd5; color: #ea580c; }
        .btn-activate:hover { background: #dcfce7; color: #16a34a; }
        .btn-delete:hover { background: #fee2e2; color: #dc2626; }

        .empty-state { text-align: center; padding: 5rem 2rem; color: var(--text-muted); }
        .empty-state svg { color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-dark); margin: 0.5rem 0; font-weight: 800; }

        /* MODALS (shared) */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: var(--white); width: 100%; max-width: 600px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h2 { font-size: 1.2rem; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .btn-close { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; }
        .btn-close:hover { color: var(--danger); }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.8rem; font-weight: 700; color: var(--text-dark); }
        .form-control { padding: 0.75rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.9rem; font-family: inherit; color: var(--text-dark); background: #f8fafc; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-light); }
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border-light); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border-light); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #f1f5f9; }
        .btn-primary { background: var(--primary); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-primary:hover { background: var(--primary-hover); }

        /* VIEW CREDENTIALS MODAL */
        .view-card { max-width: 1000px; background: var(--bg-light); }
        .view-header { padding: 1.25rem 1.75rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: var(--white); flex-shrink: 0; }
        .view-header h2 { font-size: 1.25rem; font-weight: 800; letter-spacing: -0.3px; }
        .view-header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 2px; }
        .vc-layout { display: grid; grid-template-columns: 300px 1fr; gap: 1.25rem; padding: 1.25rem; overflow-y: auto; }

        .vc-identity { background: var(--white); border: 1px solid var(--border-light); border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; }
        .vc-top { background: linear-gradient(135deg, var(--primary) 0%, #0a0045 100%); color: var(--white); padding: 1.75rem 1.25rem 1.5rem; text-align: center; position: relative; overflow: hidden; }
        .vc-top::before { content: ''; position: absolute; top: -60px; right: -60px; width: 170px; height: 170px; border-radius: 50%; border: 22px solid rgba(255,255,255,0.06); }
        .vc-top::after { content: ''; position: absolute; bottom: -70px; left: -50px; width: 150px; height: 150px; border-radius: 50%; border: 18px solid rgba(255,255,255,0.04); }
        .vc-avatar { width: 96px; height: 96px; border-radius: 50%; background: var(--primary-light); color: #4f46e5; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 2rem; object-fit: cover; margin: 0 auto 0.9rem; border: 4px solid rgba(255,255,255,0.35); position: relative; z-index: 1; }
        .vc-top h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: 4px; position: relative; z-index: 1; }
        .vc-top .vc-email { font-size: 0.8rem; color: rgba(255,255,255,0.8); word-break: break-all; position: relative; z-index: 1; }
        .vc-top .status-pill { margin-top: 0.9rem; position: relative; z-index: 1; }

        .vc-stats { flex: 1; display: flex; flex-direction: column; }
        .vc-stat { flex: 1; display: flex; align-items: center; gap: 12px; padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--border-light); }
        .vc-stat:last-child { border-bottom: none; }
        .vc-stat-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .vc-stat-icon svg { width: 17px; height: 17px; }
        .vc-stat-text { min-width: 0; }
        .vc-stat-text label { display: block; font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 2px; }
        .vc-stat-text div { font-size: 0.92rem; font-weight: 700; word-break: break-word; }

        .vc-sections { display: grid; grid-template-rows: auto auto 1fr; gap: 1.25rem; min-width: 0; }
        .vc-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; }
        .vc-card-head { display: flex; align-items: center; gap: 10px; padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--border-light); background: #f8fafc; }
        .vc-card-head svg { width: 17px; height: 17px; color: var(--primary); }
        .vc-card-head h4 { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; }
        .vc-grid { flex: 1; display: grid; }
        .vc-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .vc-cols-3 { grid-template-columns: repeat(3, 1fr); }
        .vc-field { padding: 1rem 1.25rem; border-right: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); display: flex; flex-direction: column; justify-content: center; min-width: 0; }
        .vc-cols-2 .vc-field:nth-child(2n), .vc-cols-3 .vc-field:nth-child(3n) { border-right: none; }
        .vc-grid .vc-field:last-child { border-bottom: none; }
        .vc-cols-2 .vc-field:nth-last-child(2):nth-child(odd) { border-bottom: none; }
        .vc-cols-3 .vc-field { border-bottom: none; }
        .vc-field.vc-full { grid-column: 1 / -1; border-right: none; }
        .vc-field label { font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 5px; }
        .vc-field div { font-size: 0.95rem; font-weight: 600; word-break: break-word; }
        .vc-field .mono { font-family: monospace; font-weight: 700; color: var(--primary); }

        @media (max-width: 860px) {
            .vc-layout { grid-template-columns: 1fr; }
            .vc-cols-3 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 520px) {
            .vc-cols-2, .vc-cols-3 { grid-template-columns: 1fr; }
            .vc-field { border-right: none; border-bottom: 1px solid var(--border-light) !important; }
        }

        /* =====================================================
           ENHANCED LAYOUT
           ===================================================== */
        .header-top { align-items: center; }

        /* Overview cards */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 14px; padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(25, 0, 166, 0.10); border-color: #c7d2fe; }
        .stat-card.current { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon svg { width: 22px; height: 22px; }
        .stat-num { font-size: 1.6rem; font-weight: 800; line-height: 1; color: var(--text-dark); }
        .stat-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-top: 5px; }

        /* Filter card */
        .filter-card { border-radius: 16px; }
        .search-input { height: 46px; border-radius: 10px; }

        /* Table card */
        .table-card { border-radius: 16px; }
        .table-scroll-wrap { padding-bottom: 0; }
        th { padding: 1.1rem 1.5rem; }
        td { padding: 1rem 1.5rem; }

        /* Profile photo circle (photo on top of initials) */
        .av { position: relative; width: 44px; height: 44px; border-radius: 50%; background: var(--av-bg, #e0e7ff); color: var(--av-fg, #4f46e5); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; overflow: hidden; box-shadow: 0 0 0 2px #fff, 0 2px 8px rgba(15, 23, 42, 0.14); }
        .av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

        .prog-badge { display: inline-block; padding: 3px 10px; border-radius: 8px; background: #eef2ff; color: #4338ca; font-weight: 700; font-size: 0.78rem; }
        .cell-sub { display: block; font-size: 0.74rem; color: var(--text-muted); font-weight: 500; margin-top: 3px; }

        .status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 7px; }

        .btn-view:hover { background: #e0f2fe; color: #0284c7; }
        .btn-icon-action { position: relative; }

        .table-foot { padding: 0.9rem 1.5rem; border-top: 1px solid var(--border-light); background: #fbfbfe; font-size: 0.8rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .table-foot strong { color: var(--text-dark); }

        .btn-reset { display: inline-flex; align-items: center; gap: 8px; margin-top: 1rem; padding: 0.7rem 1.4rem; border-radius: 999px; background: var(--primary); color: #fff; font-weight: 700; font-size: 0.85rem; text-decoration: none; }

        /* View modal photo + footer */
        .vc-avatar { position: relative; overflow: hidden; }
        .vc-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .view-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-light); background: var(--white); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }

        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
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
        <div class="header-top">
            <div class="page-titles">
                <h1>User Management</h1>
                <p>View, edit, and control access for all approved student accounts.</p>
            </div>
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

        <!-- Full Width Search & Filter Card -->
        <form method="GET" action="" class="filter-card">
            <input type="hidden" name="course" value="<?= e($activeCourse) ?>">
            <div class="filter-primary-row">
                <!-- Program Filters Placed BEFORE Search -->
                <div class="category-pills">
                    <?php $preserveURL = "&search=" . urlencode($search) . "&status=" . urlencode($status) . "&sort=" . urlencode($sort); ?>
                    <a href="users.php?course=all<?= $preserveURL ?>" class="cat-pill <?= $activeCourse === 'all' ? 'active' : '' ?>">
                        All Programs
                    </a>
                    
                    <?php foreach ($courses as $c): ?>
                        <a href="users.php?course=<?= $c ?><?= $preserveURL ?>" class="cat-pill <?= $activeCourse == $c ? 'active' : '' ?>">
                            <?= e($c) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="search-row">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or ID..." value="<?= e($search) ?>">
                </div>
            </div>
            
            <div class="controls-row">
                <div class="filters-group">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all">Status: All</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Status: Active</option>
                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Status: Suspended</option>
                    </select>
                    
                    <a href="users.php" class="btn-add-filter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Clear Filters
                    </a>
                </div>
                
                <div class="sort-group">
                    <span>Sort by:</span>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Accounts</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest Accounts</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Premium Table Card -->
        <div class="table-card">
            <div class="table-scroll-wrap">
                <?php if (count($users) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Student Profile</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg> Student ID</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Program & Year</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Status</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Date Joined</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php $photo = users_photo_file($user); ?>
                                <tr>
                                    <td>
                                        <div class="student-cluster">
                                            <div class="av" style="<?= users_avatar_style((int)$user['id']) ?>">
                                                <span><?= e(get_initials($user['first_name'], $user['last_name'])) ?></span>
                                                <?php if ($photo !== ''): ?>
                                                    <img src="../uploads/<?= e($photo) ?>" alt="" loading="lazy" onerror="this.remove()">
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <!-- Clickable Name opens the credentials pop-up -->
                                                <a href="#" class="student-name-link" title="View Full Credentials"
                                                    data-id="<?= $user['id'] ?>"
                                                    data-first="<?= e($user['first_name']) ?>"
                                                    data-last="<?= e($user['last_name']) ?>"
                                                    data-email="<?= e($user['email']) ?>"
                                                    data-sid="<?= e($user['student_id']) ?>"
                                                    data-course="<?= e($user['course']) ?>"
                                                    data-year="<?= e($user['year_level']) ?>"
                                                    data-status="<?= e($user['status']) ?>"
                                                    data-joined="<?= e(date('F j, Y', strtotime($user['created_at']))) ?>"
                                                    data-time="<?= e(date('g:i A', strtotime($user['created_at']))) ?>"
                                                    data-joinedshort="<?= e(date('M j, Y', strtotime($user['created_at']))) ?>"
                                                    data-days="<?= max(0, (int)floor((time() - strtotime($user['created_at'])) / 86400)) ?>"
                                                    data-initials="<?= e(get_initials($user['first_name'], $user['last_name'])) ?>"
                                                    data-avatar="<?= $photo !== '' ? e('../uploads/' . $photo) : '' ?>"
                                                    onclick="openViewModal(this); return false;">
                                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                                </a>
                                                <span class="student-email"><?= e($user['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td style="font-family: monospace; font-weight:700; color:var(--primary); white-space:nowrap;">
                                        <?= e($user['student_id']) ?>
                                    </td>
                                    
                                    <td>
                                        <span class="prog-badge"><?= e($user['course']) ?></span>
                                        <span class="cell-sub"><?= e($user['year_level']) ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-pill status-<?= e($user['status']) ?>">
                                            <?= e($user['status']) ?>
                                        </span>
                                    </td>
                                    
                                    <td style="color:var(--text-dark); font-size:0.85rem; font-weight:600;">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                        <span class="cell-sub"><?= e(users_ago($user['created_at'])) ?></span>
                                    </td>
                                    
                                    <td class="actions-cell">
                                        <!-- INLINE ICON ACTION BAR -->
                                        <div class="icon-action-bar">

                                            <!-- View Profile Icon -->
                                            
                                            
                                            <!-- Edit Account Icon -->
                                            <button type="button" class="btn-icon-action btn-edit" title="Edit Student Profile"
                                                data-id="<?= $user['id'] ?>"
                                                data-first="<?= e($user['first_name']) ?>"
                                                data-last="<?= e($user['last_name']) ?>"
                                                data-email="<?= e($user['email']) ?>"
                                                data-sid="<?= e($user['student_id']) ?>"
                                                data-course="<?= e($user['course']) ?>"
                                                data-year="<?= e($user['year_level']) ?>"
                                                onclick="openEditModal(this)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            
                                            <!-- Suspend / Activate Toggle Icon -->
                                            <form method="POST" action="" style="margin:0;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= e($user['status']) ?>">
                                                
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button type="submit" class="btn-icon-action btn-suspend" title="Suspend User Access" onclick="return confirm('Suspend this user account? They will not be able to log in.');">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn-icon-action btn-activate" title="Reactivate User" onclick="return confirm('Restore access for this user?');">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete Icon -->
                                            <form method="POST" action="" style="margin:0;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn-icon-action btn-delete" title="Permanently Delete Account" onclick="return confirm('WARNING: Are you sure you want to permanently delete this account? All associated data will be removed.');">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <h3>No Users Found</h3>
                        <p>There are no student accounts matching this filter.</p>
                        <a href="users.php" class="btn-reset">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (count($users) > 0): ?>
                <div class="table-foot">
                    <span>Showing <strong><?= count($users) ?></strong> of <strong><?= number_format($stats['total']) ?></strong> students</span>
                    <span>Photos update automatically when a student changes their profile picture.</span>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- VIEW CREDENTIALS MODAL -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-card view-card">
            <div class="view-header">
                <div>
                    <h2>Student Credentials</h2>
                    <p>Complete profile and account details for this student.</p>
                </div>
                <button type="button" class="btn-close" onclick="closeViewModal()">&times;</button>
            </div>

            <div class="vc-layout">
                <!-- Identity card -->
                <div class="vc-identity">
                    <div class="vc-top">
                        <div id="vc_avatar_wrap"></div>
                        <h3 id="vc_name"></h3>
                        <div class="vc-email" id="vc_email"></div>
                        <span class="status-pill" id="vc_status_pill"></span>
                    </div>
                    <div class="vc-stats">
                        <div class="vc-stat">
                            <div class="vc-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2"/><path d="M15 12h2"/><path d="M7 16h10"/></svg></div>
                            <div class="vc-stat-text"><label>Student ID</label><div id="vc_sid_stat"></div></div>
                        </div>
                        <div class="vc-stat">
                            <div class="vc-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                            <div class="vc-stat-text"><label>Program</label><div id="vc_prog_stat"></div></div>
                        </div>
                        <div class="vc-stat">
                            <div class="vc-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                            <div class="vc-stat-text"><label>Member Since</label><div id="vc_since_stat"></div></div>
                        </div>
                        <div class="vc-stat">
                            <div class="vc-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                            <div class="vc-stat-text"><label>Account Age</label><div id="vc_age_stat"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Detail sections -->
                <div class="vc-sections">
                    <div class="vc-card">
                        <div class="vc-card-head">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <h4>Personal Information</h4>
                        </div>
                        <div class="vc-grid vc-cols-2">
                            <div class="vc-field"><label>First Name</label><div id="vc_first"></div></div>
                            <div class="vc-field"><label>Last Name</label><div id="vc_last"></div></div>
                            <div class="vc-field vc-full"><label>Email Address</label><div id="vc_email2"></div></div>
                        </div>
                    </div>

                    <div class="vc-card">
                        <div class="vc-card-head">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            <h4>Academic Details</h4>
                        </div>
                        <div class="vc-grid vc-cols-3">
                            <div class="vc-field"><label>Student ID</label><div class="mono" id="vc_sid"></div></div>
                            <div class="vc-field"><label>Program</label><div id="vc_course"></div></div>
                            <div class="vc-field"><label>Year Level</label><div id="vc_year"></div></div>
                        </div>
                    </div>

                    <div class="vc-card">
                        <div class="vc-card-head">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <h4>Account Information</h4>
                        </div>
                        <div class="vc-grid vc-cols-2">
                            <div class="vc-field"><label>Account Status</label><div id="vc_status"></div></div>
                            <div class="vc-field"><label>Role</label><div>Student</div></div>
                            <div class="vc-field"><label>Date Joined</label><div id="vc_joined"></div></div>
                            <div class="vc-field"><label>Time Registered</label><div id="vc_time"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="view-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
                <button type="button" class="btn-primary" onclick="editFromView()">Edit Profile</button>
            </div>
        </div>
    </div>

    <!-- INLINE EDIT USER MODAL -->
    <div id="editModal" class="modal-overlay">
        <form action="" method="POST" class="modal-card">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="modal-header">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit User Profile
                </h2>
                <button type="button" class="btn-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="edit_first" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="edit_last" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Student ID Number</label>
                        <input type="text" name="student_id" id="edit_sid" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Academic Program</label>
                        <select name="course" id="edit_course" class="form-control" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" id="edit_year" class="form-control" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        const editModal = document.getElementById('editModal');
        const viewModal = document.getElementById('viewModal');
        let currentLink = null;

        /* ---------- View Credentials ---------- */
        function openViewModal(el) {
            if (!el) return;
            currentLink = el;
            const d = el.dataset;
            const set = (id, v) => { document.getElementById(id).textContent = v; };

            // Profile photo on top of the initials (falls back to initials if the image is missing)
            const wrap = document.getElementById('vc_avatar_wrap');
            wrap.innerHTML = '';
            const circle = document.createElement('div');
            circle.className = 'vc-avatar';
            const initials = document.createElement('span');
            initials.textContent = d.initials;
            circle.appendChild(initials);
            if (d.avatar) {
                const img = document.createElement('img');
                img.src = d.avatar;
                img.alt = 'Profile photo';
                img.onerror = function () { img.remove(); };
                circle.appendChild(img);
            }
            wrap.appendChild(circle);

            const statusLabel = d.status.charAt(0).toUpperCase() + d.status.slice(1);
            const pill = document.getElementById('vc_status_pill');
            pill.className = 'status-pill status-' + d.status;
            pill.textContent = d.status;

            const days = parseInt(d.days, 10) || 0;

            set('vc_name', d.first + ' ' + d.last);
            set('vc_email', d.email);
            set('vc_sid_stat', d.sid);
            set('vc_prog_stat', d.course + ' · ' + d.year);
            set('vc_since_stat', d.joinedshort);
            set('vc_age_stat', days + (days === 1 ? ' day' : ' days'));

            set('vc_first', d.first);
            set('vc_last', d.last);
            set('vc_email2', d.email);
            set('vc_sid', d.sid);
            set('vc_course', d.course);
            set('vc_year', d.year);
            set('vc_status', statusLabel);
            set('vc_joined', d.joined);
            set('vc_time', d.time);

            viewModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            viewModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // "Edit Profile" button inside the view window
        function editFromView() {
            const link = currentLink;
            closeViewModal();
            if (link) openEditModal(link);
        }

        /* ---------- Edit User ---------- */
        function openEditModal(btn) {
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_first').value = btn.getAttribute('data-first');
            document.getElementById('edit_last').value = btn.getAttribute('data-last');
            document.getElementById('edit_email').value = btn.getAttribute('data-email');
            document.getElementById('edit_sid').value = btn.getAttribute('data-sid');
            document.getElementById('edit_course').value = btn.getAttribute('data-course');
            document.getElementById('edit_year').value = btn.getAttribute('data-year');
            
            editModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        window.addEventListener('click', function(e) {
            if (e.target === editModal) { closeEditModal(); }
            if (e.target === viewModal) { closeViewModal(); }
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeViewModal(); closeEditModal(); }
        });
    </script>
</body>
</html>