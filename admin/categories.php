<?php
// admin/categories.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

// Dynamic Category UI helper to match documents page
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

// 1. Handle Admin Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name']);
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            flash_set('success', "Category '{$name}' was successfully added.");
        } else {
            flash_set('error', "Category name cannot be empty.");
        }
        
    } elseif ($action === 'edit') {
        $id = (int) $_POST['category_id'];
        $name = trim($_POST['name']);
        if (!empty($name)) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            flash_set('success', "Category updated successfully.");
        } else {
            flash_set('error', "Category name cannot be empty.");
        }
        
    } elseif ($action === 'delete') {
        $id = (int) $_POST['category_id'];
        try {
            // Attempt to delete
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            flash_set('success', "Category successfully deleted.");
        } catch (PDOException $e) {
            // Error 1451 means documents are still attached to this category
            if ($e->getCode() == 23000) {
                flash_set('error', "Cannot delete this category because there are documents attached to it. Please reassign or delete those documents first.");
            } else {
                flash_set('error', "Database error: " . $e->getMessage());
            }
        }
    }
    
    header('Location: categories.php');
    exit;
}

// 2. Fetch Categories and count how many documents are in each
$query = "
    SELECT c.id, c.name, COUNT(d.id) as doc_count 
    FROM categories c 
    LEFT JOIN documents d ON c.id = d.category_id 
    GROUP BY c.id 
    ORDER BY c.id ASC
";
$stmt = $pdo->query($query);
$categories = $stmt->fetchAll();

$success = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs & Categories · ScholArchive Admin</title>
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

        .btn-primary { background: var(--primary); color: white; padding: 0.85rem 1.5rem; border-radius: 10px; border: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; cursor: pointer; box-shadow: 0 4px 6px rgba(25, 0, 166, 0.2);}
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 12px rgba(25, 0, 166, 0.3);}

        .alert { padding: 1.25rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600; margin-bottom: 2rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .alert-success { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); color: #991b1b; border-left: 4px solid #ef4444; }

        /* Premium Table Card */
        .table-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; flex: 1;}
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        th { background: var(--table-header); color: var(--white); padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; }
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; vertical-align: middle; transition: background 0.2s; }
        tr { transition: all 0.2s ease; }
        tr:hover td { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }

        .cat-name { font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 12px;}
        .cat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;}
        
        /* Dynamic Colors for Icons */
        .icon-blue { background: #e0e7ff; color: #4f46e5; }
        .icon-green { background: #dcfce7; color: #16a34a; }
        .icon-purple { background: #f3e8ff; color: #9333ea; }
        .icon-red { background: #fee2e2; color: #dc2626; }
        .icon-yellow { background: #fef3c7; color: #d97706; }
        .icon-cyan { background: #cffafe; color: #0891b2; }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; background: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border-light); }

        /* Actions */
        .action-cell { display: flex; gap: 6px; justify-content: flex-end; align-items: center; }
        .btn-action { background: none; border: 1px solid transparent; padding: 8px; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: var(--text-muted); display: flex; align-items: center; justify-content: center; }
        .btn-action:hover { background: var(--border-light); color: var(--text-dark); border-color: #cbd5e1;}
        
        /* Specific Hover Colors for Action Buttons */
        .btn-edit:hover { background: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
        .btn-delete:hover { background: #fef2f2; color: var(--danger); border-color: #fecaca; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 1rem;}
        .modal-card { background: white; width: 100%; max-width: 480px; border-radius: 16px; padding: 2.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); position: relative; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-modal { position: absolute; top: 20px; right: 24px; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; transition: color 0.2s;}
        .close-modal:hover { color: var(--danger); }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 0.85rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.95rem; font-family: inherit; transition: all 0.2s; background: #f8fafc; outline: none; color: var(--text-dark); }
        .form-control:focus { background: var(--white); border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        
        .btn-submit { width: 100%; padding: 0.85rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-submit:hover { background: var(--sidebar-hover); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(25, 0, 166, 0.2); }
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
            <li><a href="users.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="categories.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
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
                <h1>Programs & Categories</h1>
                <p>Manage the list of academic programs and document classifications.</p>
            </div>
            
            <button class="btn-primary" onclick="openAddModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Category
            </button>
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

        <div class="table-card">
            <?php if (count($categories) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 120px;">ID</th>
                            <th>Program / Category Name</th>
                            <th>Total Documents</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): 
                            $ui = getCategoryUI($cat['name']);
                        ?>
                            <tr>
                                <td style="font-family: monospace; font-weight:600; color:var(--text-muted);">#<?= str_pad($cat['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="cat-name">
                                        <div class="cat-icon icon-<?= $ui['color'] ?>">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ui['icon'] ?></svg>
                                        </div>
                                        <?= e($cat['name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge"><?= number_format($cat['doc_count']) ?> Documents</span>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        <button class="btn-action btn-edit" title="Edit Category" onclick="openEditModal(<?= $cat['id'] ?>, '<?= e($cat['name'], true) ?>')">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                        </button>
                                        
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('WARNING: Are you sure you want to delete this category? If there are documents assigned to it, the deletion will fail to protect your data.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Delete Category">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 5rem 2rem; text-align: center; color: var(--text-muted);">
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem; color: #cbd5e1;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;">No Categories Found</h3>
                    <p>Click "Add Category" to create your first academic program or classification.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal for Adding/Editing -->
    <div id="categoryModal" class="modal">
        <div class="modal-card">
            <span class="close-modal" onclick="document.getElementById('categoryModal').style.display='none'">&times;</span>
            <h2 id="modalTitle" style="margin-bottom: 1.5rem; font-size: 1.35rem; font-weight:800; color: var(--text-dark);">Add New Category</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="category_id" id="categoryId" value="">
                
                <div class="form-group">
                    <label>Category / Program Name</label>
                    <input type="text" name="name" id="categoryName" class="form-control" placeholder="e.g. BS Information Technology" required>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">Save Category</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Category';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryName').value = '';
            document.getElementById('btnSubmit').innerText = 'Save Category';
            document.getElementById('categoryModal').style.display = 'flex';
        }

        function openEditModal(id, name) {
            document.getElementById('modalTitle').innerText = 'Edit Category Name';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryId').value = id;
            document.getElementById('categoryName').value = name;
            document.getElementById('btnSubmit').innerText = 'Update Changes';
            document.getElementById('categoryModal').style.display = 'flex';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            let modal = document.getElementById('categoryModal');
            if (event.target == modal) { modal.style.display = "none"; }
        }
    </script>

<ElicitationsGroup></ElicitationsGroup>
</body>
</html>