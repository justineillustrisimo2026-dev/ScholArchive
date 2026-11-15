<?php
// admin/documents.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

// 1. Fetch Categories for the Pill Navigation and Upload Form
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $db_categories = $catStmt->fetchAll();
    $categories_map = [];
    foreach ($db_categories as $c) { $categories_map[$c['id']] = $c['name']; }
} catch (PDOException $e) { 
    $db_categories = []; $categories_map = [];
}

// Map specific icons and colors to categories for the UI
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

// 2. Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Upload Action
    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $authors = trim($_POST['authors'] ?? '');
        $keywords = trim($_POST['keywords'] ?? ''); 
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $pubYear = (int)($_POST['pub_year'] ?? date('Y'));
        $abstract = trim($_POST['abstract'] ?? '');
        $status = $_POST['status'] ?? 'draft';

        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['document_file']['tmp_name'];
            $fileName = basename($_FILES['document_file']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if ($ext === 'pdf') {
                $uniqueName = 'doc_' . time() . '_' . uniqid() . '.pdf';
                $destination = $uploadDir . $uniqueName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $pdo->prepare("INSERT INTO documents (title, abstract, authors, category_id, pub_year, file_path, status, uploaded_by, keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $abstract, $authors, $categoryId, $pubYear, $uniqueName, $status, $admin['id'], $keywords]);
                    flash_set('success', 'Document successfully uploaded and saved to the repository.');
                } else { flash_set('error', 'Failed to move the uploaded file. Check folder permissions.'); }
            } else { flash_set('error', 'Invalid file format. Only PDF files are allowed.'); }
        } else { flash_set('error', 'Please attach a valid PDF document.'); }
        
        header('Location: documents.php');
        exit;
    }

    // Handle Edit Action (Inline Modal)
    if ($action === 'edit') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $authors = trim($_POST['authors'] ?? '');
        $keywords = trim($_POST['keywords'] ?? ''); 
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $pubYear = (int)($_POST['pub_year'] ?? date('Y'));
        $abstract = trim($_POST['abstract'] ?? '');

        if ($docId > 0 && !empty($title)) {
            $stmt = $pdo->prepare("UPDATE documents SET title=?, abstract=?, authors=?, category_id=?, pub_year=?, keywords=? WHERE id=?");
            $stmt->execute([$title, $abstract, $authors, $categoryId, $pubYear, $keywords, $docId]);
            flash_set('success', 'Document details successfully updated.');
        } else {
            flash_set('error', 'Failed to update document. Required fields missing.');
        }
        
        header('Location: documents.php');
        exit;
    }

    $docId = $_POST['doc_id'] ?? 0;

    // Handle Delete Action
    if ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if ($doc && !empty($doc['file_path'])) {
            $filePath = $uploadDir . $doc['file_path'];
            if (file_exists($filePath)) { unlink($filePath); }
        }
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        flash_set('success', "Document permanently deleted from the repository.");
        
    } 
    // Handle Toggle Status Action
    elseif ($action === 'toggle_status') {
        $currentStatus = $_POST['current_status'];
        $newStatus = ($currentStatus === 'published') ? 'draft' : 'published';
        $stmt = $pdo->prepare("UPDATE documents SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $docId]);
        if ($currentStatus === 'pending') {
            flash_set('success', "Student submission approved and published.");
        } else {
            flash_set('success', "Document status updated to {$newStatus}.");
        }
    }
    
    if (in_array($action, ['delete', 'toggle_status'])) {
        header('Location: documents.php');
        exit;
    }
}

// 3. Build Dynamic Search and Filter Query
$activeCat = $_GET['cat'] ?? 'all';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

$where = "";
$params = [];

if ($activeCat !== 'all') { $where .= " AND d.category_id = ?"; $params[] = $activeCat; }
if (!empty($search)) { $where .= " AND (d.title LIKE ? OR d.abstract LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($status !== 'all') { $where .= " AND d.status = ?"; $params[] = $status; }

// Pagination: count the matching rows first, then fetch only the current page
$perPage = 10;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM documents d WHERE 1=1" . $where);
$countStmt->execute($params);
$totalDocs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalDocs / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;
$showFrom = $totalDocs > 0 ? $offset + 1 : 0;
$showTo = min($offset + $perPage, $totalDocs);

$query = "SELECT d.*, u.first_name, u.last_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE 1=1" . $where;

if ($sort === 'oldest') { $query .= " ORDER BY d.created_at ASC"; }
elseif ($sort === 'title_asc') { $query .= " ORDER BY d.title ASC"; }
else { $query .= " ORDER BY CASE WHEN d.status = 'pending' THEN 1 ELSE 2 END, d.created_at DESC"; }

$query .= " LIMIT {$perPage} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Build a link to another page while keeping the current search / filters / sort
function page_url($p) {
    $q = $_GET;
    $q['page'] = $p;
    return 'documents.php?' . http_build_query($q);
}

$success = flash_get('success');
$error = flash_get('error');
$currentYear = date('Y');
$years = range($currentYear + 1, 2010);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents · ScholArchive Admin</title>
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

        /* Full Width Search & Filter Card */
        .filter-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 1.25rem;}
        
        /* Category Pills + Search in one inline row */
        .filter-primary-row { display: flex; gap: 1.25rem; align-items: center; width: 100%; flex-wrap: wrap; }
        
        .category-pills { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0; flex-shrink: 0; }
        .category-pills::-webkit-scrollbar { display: none; }
        .cat-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 24px; border: 1px solid var(--border-light); background: var(--white); color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; white-space: nowrap; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .cat-pill svg { width: 14px; height: 14px; }
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

        /* Premium Table Card Container */
        .table-card { background: var(--white); border-radius: 12px; border: 1px solid var(--border-light); box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; flex: 1;}
        .table-scroll-wrap { width: 100%; overflow-y: auto; overflow-x: auto; flex: 1; } 
        table { width: 100%; border-collapse: collapse; text-align: left; min-width: 950px; }
        
        /* Table Header */
        th { background: var(--table-header); color: var(--white); padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; position: sticky; top: 0; z-index: 5; white-space: nowrap;}
        th svg { width: 14px; height: 14px; margin-right: 6px; vertical-align: text-bottom; opacity: 0.8;}
        
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; vertical-align: middle; transition: background 0.2s; }
        tr { transition: all 0.2s ease; }
        tr:hover td { background: #f8fafc; }
        tr:last-child td { border-bottom: none; }

        /* Row Highlighting for Pending Documents */
        .row-pending { background: #fffbeb; }
        .row-pending:hover { background: #fef3c7; }

        /* Document Title Cell with Colored Icon */
        .doc-title-cell { display: flex; align-items: flex-start; gap: 1rem; }
        .doc-icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        /* Dynamic Colors for Doc Icons */
        .icon-blue { background: #e0e7ff; color: #4f46e5; }
        .icon-green { background: #dcfce7; color: #16a34a; }
        .icon-purple { background: #f3e8ff; color: #9333ea; }
        .icon-red { background: #fee2e2; color: #dc2626; }
        .icon-yellow { background: #fef3c7; color: #d97706; }
        .icon-cyan { background: #cffafe; color: #0891b2; }

        .doc-title-wrap { display: flex; flex-direction: column; gap: 4px; }
        .doc-title { font-weight: 700; color: var(--primary); font-size: 0.95rem; line-height: 1.3; text-decoration: none;}
        .doc-title:hover { text-decoration: underline; }
        .doc-meta { font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 12px; align-items: center; font-weight: 500;}
        .doc-meta svg { width: 12px; height: 12px; vertical-align: text-bottom; margin-right: 2px;}

        /* Category Text Pill */
        .cat-text-pill { background: var(--primary-light); color: var(--primary); padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; white-space: nowrap; }

        /* Status Pill (NO DOT) */
        .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-published { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0;}
        .status-draft { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;}
        .status-pending { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;}

        /* =========================================================
           INLINE ICON ACTION BAR (Option 2)
           ========================================================= */
        .actions-cell { text-align: right; }
        .icon-action-bar { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
        
        .btn-icon-action { width: 34px; height: 34px; border-radius: 8px; border: none; background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; color: var(--text-muted); }
        .btn-icon-action svg { width: 18px; height: 18px; }
        
        .btn-edit:hover { background: #e0e7ff; color: #4f46e5; }
        .btn-publish:hover { background: #dcfce7; color: #16a34a; }
        .btn-draft:hover { background: #ffedd5; color: #ea580c; }
        .btn-delete:hover { background: #fee2e2; color: #dc2626; }

        .empty-state { text-align: center; padding: 5rem 2rem; color: var(--text-muted); }
        .empty-state svg { color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-dark); margin: 0.5rem 0; font-weight: 800; }

        /* PAGINATION */
        .pagination-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-light); background: #f8fafc; flex-shrink: 0; }
        .pagination-info { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .pagination-info strong { color: var(--text-dark); font-weight: 700; }
        .pagination { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .page-link { min-width: 38px; height: 38px; padding: 0 12px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border-radius: 10px; border: 1px solid var(--border-light); background: var(--white); color: var(--text-dark); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
        .page-link svg { width: 14px; height: 14px; }
        .page-link:hover { border-color: var(--primary-light); color: var(--primary); background: #f1f0ff; }
        .page-link.current { background: var(--primary); border-color: var(--primary); color: var(--white); box-shadow: 0 4px 10px rgba(25, 0, 166, 0.25); pointer-events: none; }
        .page-link.disabled { opacity: 0.45; pointer-events: none; background: #f1f5f9; }
        .page-ellipsis { min-width: 28px; text-align: center; color: var(--text-muted); font-weight: 700; }
        @media (max-width: 700px) { .pagination-bar { justify-content: center; } }

        /* UPLOAD & EDIT MODALS OVERLAY */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: var(--white); width: 100%; max-width: 750px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .modal-header h2 { font-size: 1.2rem; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .btn-close { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; }
        .btn-close:hover { color: var(--danger); }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.8rem; font-weight: 700; color: var(--text-dark); }
        .form-control { padding: 0.75rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.9rem; font-family: inherit; color: var(--text-dark); background: #f8fafc; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px var(--primary-light); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border-light); background: #f8fafc; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border-light); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #f1f5f9; }
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
            <li><a href="documents.php" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
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
        <div class="header-top">
            <div class="page-titles">
                <h1>Document Repository</h1>
                <p>Manage, review, and organize uploaded capstones and research papers.</p>
            </div>
            <button type="button" class="btn-primary" onclick="openUploadModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Upload Document
            </button>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?= e($success) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Full Width Search & Filter Card -->
        <form method="GET" action="" class="filter-card">
            <input type="hidden" name="cat" value="<?= e($activeCat) ?>">
            
            <div class="filter-primary-row">
                <!-- Category Pills (Now placed BEFORE the search bar) -->
                <div class="category-pills">
                    <?php $preserveURL = "&search=" . urlencode($search) . "&status=" . urlencode($status) . "&sort=" . urlencode($sort); ?>
                    <a href="documents.php?cat=all<?= $preserveURL ?>" class="cat-pill <?= $activeCat === 'all' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        All Categories
                    </a>
                    
                    <?php foreach ($db_categories as $cat): 
                        $ui = getCategoryUI($cat['name']);
                    ?>
                        <a href="documents.php?cat=<?= $cat['id'] ?><?= $preserveURL ?>" class="cat-pill <?= $activeCat == $cat['id'] ? 'active' : '' ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ui['icon'] ?></svg>
                            <?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $cat['name'])) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search Bar -->
                <div class="search-row">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" class="search-input" placeholder="Search by title, abstract, or keyword..." value="<?= e($search) ?>">
                </div>
            </div>
            
            <div class="controls-row">
                <div class="filters-group">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all">Status: All</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Status: Published</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Status: Draft</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Status: Pending Review</option>
                    </select>
                    
                    <a href="documents.php?cat=<?= e($activeCat) ?>" class="btn-add-filter">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Clear Filters
                    </a>
                </div>
                
                <div class="sort-group">
                    <span>Sort by:</span>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Uploads</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest Uploads</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A-Z)</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Premium Table Card -->
        <div class="table-card">
            <div class="table-scroll-wrap">
                <?php if (count($documents) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Document Title</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Category</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Status</th>
                                <th><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Date Uploaded</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): 
                                $catName = $categories_map[$doc['category_id']] ?? 'Unknown';
                                $ui = getCategoryUI($catName);
                            ?>
                                <tr class="<?= $doc['status'] === 'pending' ? 'row-pending' : '' ?>">
                                    <td>
                                        <div class="doc-title-cell">
                                            <div class="doc-icon-box icon-<?= $ui['color'] ?>">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $ui['icon'] ?></svg>
                                            </div>
                                            <div class="doc-title-wrap">
                                                <a href="view_document.php?id=<?= $doc['id'] ?>" class="doc-title" title="Click to view full document details"><?= e($doc['title']) ?></a>
                                                <div class="doc-meta">
                                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= $doc['first_name'] ? e($doc['first_name'].' '.$doc['last_name']) : 'System Admin' ?></span>
                                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?= e($doc['pub_year']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="cat-text-pill"><?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $catName)) ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-pill status-<?= $doc['status'] ?>">
                                            <?= $doc['status'] === 'pending' ? 'Pending' : $doc['status'] ?>
                                        </span>
                                    </td>
                                    
                                    <td style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">
                                        <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                                    </td>
                                    
                                    <td class="actions-cell">
                                        <!-- OPTION 2: INLINE ICON ACTION BAR -->
                                        <div class="icon-action-bar">
                                            
                                            <!-- Edit Icon Button -->
                                            <button type="button" class="btn-icon-action btn-edit" title="Edit Document Details"
                                                data-id="<?= $doc['id'] ?>"
                                                data-title="<?= e($doc['title']) ?>"
                                                data-authors="<?= e($doc['authors']) ?>"
                                                data-keywords="<?= e($doc['keywords']) ?>"
                                                data-cat="<?= $doc['category_id'] ?>"
                                                data-year="<?= e($doc['pub_year']) ?>"
                                                data-abstract="<?= e($doc['abstract']) ?>"
                                                onclick="openEditModal(this)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            
                                            <!-- Publish/Draft Toggle Icon Button -->
                                            <form method="POST" action="" style="margin:0;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $doc['status'] === 'pending' ? 'draft' : $doc['status'] ?>">
                                                
                                                <?php if ($doc['status'] === 'published'): ?>
                                                    <!-- Show Hide/Draft Icon -->
                                                    <button type="submit" class="btn-icon-action btn-draft" title="Make Document Hidden (Draft)" onclick="return confirm('Change document visibility to Draft?');">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Show Publish Icon -->
                                                    <button type="submit" class="btn-icon-action btn-publish" title="<?= $doc['status'] === 'pending' ? 'Approve & Publish Document' : 'Publish Document' ?>" onclick="return confirm('Publish this document to the repository?');">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Delete Icon Button -->
                                            <form method="POST" action="" style="margin:0;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                <button type="submit" class="btn-icon-action btn-delete" title="Permanently Delete Document" onclick="return confirm('WARNING: Are you sure you want to permanently delete this document?');">
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
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15h6"/><path d="M9 11h6"/></svg>
                        <h3>No Documents Match This Filter</h3>
                        <p>There are no capstones uploaded for this specific program or search criteria yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalDocs > 0): ?>
                <!-- PAGINATION -->
                <div class="pagination-bar">
                    <div class="pagination-info">
                        Showing <strong><?= $showFrom ?>&ndash;<?= $showTo ?></strong> of <strong><?= $totalDocs ?></strong> document<?= $totalDocs === 1 ? '' : 's' ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Document pages">
                            <a href="<?= e(page_url($page - 1)) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                                Prev
                            </a>

                            <?php
                            $lastShown = 0;
                            for ($i = 1; $i <= $totalPages; $i++):
                                if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1):
                                    if ($lastShown && $i - $lastShown > 1): ?>
                                        <span class="page-ellipsis">&hellip;</span>
                                    <?php endif; ?>
                                    <a href="<?= e(page_url($i)) ?>" class="page-link <?= $i === $page ? 'current' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                                    <?php $lastShown = $i;
                                endif;
                            endfor; ?>

                            <a href="<?= e(page_url($page + 1)) ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                Next
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 1. EMBEDDED UPLOAD DOCUMENT MODAL -->
    <div id="uploadModal" class="modal-overlay">
        <form action="" method="POST" enctype="multipart/form-data" class="modal-card">
            <input type="hidden" name="action" value="upload">
            <div class="modal-header">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload New Document
                </h2>
                <button type="button" class="btn-close" onclick="closeUploadModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Research Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="Enter capstone or research title">
                    </div>
                    <div class="form-group full-width">
                        <label>Authors / Researchers</label>
                        <input type="text" name="authors" class="form-control" required placeholder="e.g., J. Cruz, M. Reyes">
                    </div>
                    <div class="form-group full-width">
                        <label>Keywords / Tags</label>
                        <input type="text" name="keywords" class="form-control" required placeholder="e.g., PHP, MySQL, Inventory System (Comma separated)">
                    </div>
                    <div class="form-group">
                        <label>Category / Program</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($db_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Publication Year</label>
                        <select name="pub_year" class="form-control" required>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Research Abstract</label>
                        <textarea name="abstract" class="form-control" required placeholder="Paste the abstract summary here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Initial Status</label>
                        <select name="status" class="form-control">
                            <option value="published">Published (Visible)</option>
                            <option value="draft">Draft (Hidden)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Attach PDF File (.pdf)</label>
                        <input type="file" name="document_file" class="form-control" accept="application/pdf" required style="padding: 7px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn-primary">Upload Document</button>
            </div>
        </form>
    </div>

    <!-- 2. EMBEDDED EDIT DOCUMENT MODAL -->
    <div id="editModal" class="modal-overlay">
        <form action="" method="POST" class="modal-card">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="doc_id" id="edit_doc_id">
            
            <div class="modal-header">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Document Details
                </h2>
                <button type="button" class="btn-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Research Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Authors / Researchers</label>
                        <input type="text" name="authors" id="edit_authors" class="form-control" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Keywords / Tags</label>
                        <input type="text" name="keywords" id="edit_keywords" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Category / Program</label>
                        <select name="category_id" id="edit_category_id" class="form-control" required>
                            <?php foreach ($db_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Publication Year</label>
                        <select name="pub_year" id="edit_pub_year" class="form-control" required>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Research Abstract</label>
                        <textarea name="abstract" id="edit_abstract" class="form-control" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- SCRIPT FOR MODALS & LOGIC -->
    <script>
        // Upload Modal Logic
        const uploadModal = document.getElementById('uploadModal');
        function openUploadModal() {
            uploadModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeUploadModal() {
            uploadModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Edit Modal Logic
        const editModal = document.getElementById('editModal');
        function openEditModal(btn) {
            document.getElementById('edit_doc_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_title').value = btn.getAttribute('data-title');
            document.getElementById('edit_authors').value = btn.getAttribute('data-authors');
            document.getElementById('edit_keywords').value = btn.getAttribute('data-keywords');
            document.getElementById('edit_category_id').value = btn.getAttribute('data-cat');
            document.getElementById('edit_pub_year').value = btn.getAttribute('data-year');
            document.getElementById('edit_abstract').value = btn.getAttribute('data-abstract');
            
            editModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close Modals on outside click
        window.addEventListener('click', function(e) {
            if (e.target === uploadModal) { closeUploadModal(); }
            if (e.target === editModal) { closeEditModal(); }
        });
    </script>
</body>
</html>