<?php
// admin/edit_document.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin = require_admin();
$pdo = get_db();

// 1. Fetch Document Data
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    flash_set('error', 'Document not found.');
    header('Location: documents.php');
    exit;
}

// 2. Fetch Categories for Dropdown
try {
    $stmtCats = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $db_categories = $stmtCats->fetchAll();
} catch (PDOException $e) { 
    $db_categories = []; 
}

// 3. Handle Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $authors = trim($_POST['authors']);
    $abstract = trim($_POST['abstract']);
    $categoryId = (int) $_POST['category_id'];
    $pubYear = (int) $_POST['pub_year'];
    $keywords = trim($_POST['keywords'] ?? '');
    $status = $_POST['status'] ?? 'published';
    
    $newFileName = $doc['file_path']; // Keep existing file by default

    // If admin uploaded a NEW file to replace the old one
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['document_file']['tmp_name'];
        $fileName = $_FILES['document_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'pdf') {
            $uploadDir = __DIR__ . '/../uploads/documents/';
            
            // Delete the old file from server
            if (!empty($doc['file_path']) && file_exists($uploadDir . $doc['file_path'])) {
                unlink($uploadDir . $doc['file_path']);
            }
            
            // Save new file
            $newFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $fileName);
            move_uploaded_file($fileTmpPath, $uploadDir . $newFileName);
        } else {
            $error = "Update failed. Only PDF files are allowed.";
        }
    }

    if (empty($error)) {
        $updateStmt = $pdo->prepare("UPDATE documents SET title=?, abstract=?, authors=?, pub_year=?, category_id=?, keywords=?, file_path=?, status=? WHERE id=?");
        $updateStmt->execute([$title, $abstract, $authors, $pubYear, $categoryId, $keywords, $newFileName, $status, $docId]);
        
        flash_set('success', "Document metadata for '{$title}' was updated successfully.");
        header("Location: view_document.php?id={$docId}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Document · ScholArchive Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1900a6; --sidebar-hover: #100075; --bg-light: #f8fafc;
            --white: #ffffff; --text-dark: #0f172a; --text-muted: #64748b;
            --primary: #1900a6; --primary-light: #e0e7ff; --danger: #ef4444; 
            --success: #10b981; --border-light: #e2e8f0; 
            --font-family: 'Inter', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-family); background-color: var(--bg-light); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* Unified Sidebar matching admin exactly */
        .sidebar { width: 280px; background-color: var(--sidebar-bg); color: var(--white); display: flex; flex-direction: column; flex-shrink: 0; z-index: 10; box-shadow: 4px 0 10px rgba(0,0,0,0.05); }
        .brand { padding: 1.75rem 1.5rem; display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 1.1rem; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .brand img { width: 36px; height: 36px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; }
        
        .user-profile { margin: 1.25rem 1rem; background: rgba(255,255,255,0.08); border-radius: 10px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px; }
        .avatar { width: 38px; height: 38px; background-color: #3b82f6; color: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.95rem; }
        .user-info h4 { font-size: 0.9rem; margin-bottom: 2px; }
        .user-info span { font-size: 0.7rem; color: rgba(255,255,255,0.65); }
        
        .nav-links { flex: 1; padding: 0 0.75rem; list-style: none; overflow-y: auto; }
        .nav-links li { margin-bottom: 0.2rem; }
        .nav-links a { display: flex; align-items: center; gap: 12px; padding: 0.75rem 1rem; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background-color: var(--sidebar-hover); color: var(--white); }
        
        .logout-wrap { padding: 1.25rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .btn-logout { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: var(--white); padding: 0.75rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.3); }

        /* Main Workspace */
        .main-content { flex: 1; padding: 2.5rem 3rem; overflow-y: auto; display: flex; flex-direction: column; align-items: center; }
        .form-wrapper { width: 100%; max-width: 900px; }
        
        /* Interactive Back Button */
        .header-top { margin-bottom: 1.5rem; display: flex; align-items: center; width: 100%; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--white); color: var(--text-dark); padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; border: 1px solid var(--border-light); text-decoration: none; transition: all 0.2s; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-back:hover { background: #f1f5f9; border-color: #cbd5e1; transform: translateX(-4px); box-shadow: 0 4px 6px rgba(0,0,0,0.04); }

        .breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 1rem; }
        .breadcrumb a { color: var(--primary); text-decoration: none; transition: color 0.2s; }
        .breadcrumb a:hover { text-decoration: underline; }
        .page-titles h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2rem; letter-spacing: -0.5px; }

        /* Form Card */
        .upload-card { background: var(--white); border-radius: 16px; border: 1px solid var(--border-light); padding: 3rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .full-width { grid-column: span 2; }
        
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 0.85rem 1rem; border: 1px solid var(--border-light); border-radius: 8px; font-size: 0.95rem; font-family: inherit; transition: all 0.2s; background: #f8fafc; outline: none; color: var(--text-dark); }
        .form-control:focus { background: var(--white); border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; }
        textarea.form-control { resize: vertical; min-height: 120px; line-height: 1.6; }

        /* Drag & Drop Area */
        .file-upload-box { border: 2px dashed var(--border-light); border-radius: 12px; padding: 2.5rem 2rem; text-align: center; background: #f8fafc; transition: all 0.2s; cursor: pointer; position: relative; }
        .file-upload-box:hover { border-color: var(--primary); background: #eff6ff; }
        .upload-icon { color: var(--text-muted); margin-bottom: 1rem; transition: color 0.2s; }
        .file-upload-box:hover .upload-icon { color: var(--primary); }
        .upload-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.25rem; }
        .upload-subtitle { font-size: 0.85rem; color: var(--text-muted); }
        .file-input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        
        .selected-file-name { margin-top: 1rem; font-size: 0.9rem; font-weight: 600; color: var(--primary); display: none; background: var(--white); padding: 8px 16px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); align-items: center; gap: 8px; }
        .current-file { background: #f1f5f9; padding: 12px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-dark); font-weight: 600; margin-top: 10px;}

        .btn-submit { background: var(--primary); color: white; padding: 1rem; border-radius: 8px; font-size: 1rem; font-weight: 700; width: 100%; border: none; cursor: pointer; transition: all 0.2s; margin-top: 1rem; box-shadow: 0 4px 10px rgba(25, 0, 166, 0.2); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: var(--sidebar-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(25, 0, 166, 0.3); }

        .alert { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:1rem; border-radius:8px; font-size:0.9rem; font-weight:600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand"><img src="../assets/logo1.png" alt="Logo"> ScholArchive</div>
        <div class="user-profile">
            <div class="avatar"><?= get_initials($admin['first_name'], $admin['last_name']) ?></div>
            <div class="user-info"><h4><?= e($admin['first_name'] . ' ' . $admin['last_name']) ?></h4><span>System Administrator</span></div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a></li>
            <li><a href="users.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Users</a></li>
            <li><a href="documents.php" class="active"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Documents</a></li>
            <li><a href="upload_document.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Upload Document</a></li>
            <li><a href="categories.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Categories</a></li>
            <li><a href="settings.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a></li>
        </ul>
        <div class="logout-wrap"><a href="../logout.php" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
    </aside>

    <main class="main-content">
        <div class="form-wrapper">
            
            <div class="header-top">
                <a href="view_document.php?id=<?= $doc['id'] ?>" class="btn-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                    Back to Document Details
                </a>
            </div>

            <div class="breadcrumb">
                <a href="documents.php">Documents</a> 
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                <a href="view_document.php?id=<?= $doc['id'] ?>">Review Document</a> 
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                <span>Edit</span>
            </div>

            <div class="page-titles"><h1>Edit Document Metadata</h1></div>

            <?php if (!empty($error)): ?>
                <div class="alert"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= e($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="upload-card">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Document Title</label>
                        <input type="text" name="title" class="form-control" value="<?= e($doc['title']) ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Authors</label>
                        <input type="text" name="authors" class="form-control" value="<?= e($doc['authors']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Program / Category</label>
                        <select name="category_id" class="form-control" required>
                            <?php if (!empty($db_categories)): ?>
                                <?php foreach ($db_categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $doc['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No categories found.</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Publication Year</label>
                        <input type="number" name="pub_year" class="form-control" value="<?= e($doc['pub_year']) ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Abstract / Description</label>
                        <textarea name="abstract" class="form-control" required><?= e($doc['abstract']) ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Keywords</label>
                        <input type="text" name="keywords" class="form-control" value="<?= e($doc['keywords']) ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Visibility Status</label>
                        <select name="status" class="form-control">
                            <option value="published" <?= $doc['status'] === 'published' ? 'selected' : '' ?>>Published (Visible)</option>
                            <option value="draft" <?= $doc['status'] === 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                            <option value="archived" <?= $doc['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>

                    <div class="form-group full-width" style="margin-top: 0.5rem;">
                        <label>Replace PDF Document (Optional)</label>
                        
                        <div class="current-file">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Current File: <?= e($doc['file_path']) ?>
                        </div>

                        <div class="file-upload-box" style="margin-top: 15px;">
                            <svg class="upload-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
                            <div class="upload-title">Drop a new PDF here to replace the current file</div>
                            <div class="upload-subtitle">Leave empty to keep the existing file attached.</div>
                            <input type="file" name="document_file" id="pdfUpload" class="file-input" accept=".pdf">
                            
                            <div id="fileNameDisplay" class="selected-file-name" style="display:none;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Document Changes
                </button>
            </form>
        </div>
    </main>

    <script>
        document.getElementById('pdfUpload').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            const displayDiv = document.getElementById('fileNameDisplay');
            if (fileName) {
                displayDiv.style.display = 'inline-flex';
                displayDiv.querySelector('span').textContent = fileName;
            } else {
                displayDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>