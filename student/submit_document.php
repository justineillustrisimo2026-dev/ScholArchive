<?php
// student/submit_document.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();

// 1. Strict Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header("Location: ../index.php?error=notactive");
    exit;
}

// 2. Fetch Categories for the Dropdown
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) { 
    $categories = []; 
}

// 3. Handle Document Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_doc') {
    $title = trim($_POST['title'] ?? '');
    $authors = trim($_POST['authors'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $pubYear = (int)($_POST['pub_year'] ?? date('Y'));
    $abstract = trim($_POST['abstract'] ?? '');
    
    // FORCE status to pending so students cannot auto-publish
    $status = 'pending'; 
    $uploadDir = __DIR__ . '/../uploads/documents/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['document_file']['tmp_name'];
        $fileName = basename($_FILES['document_file']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($ext === 'pdf') {
            $uniqueName = 'student_req_' . time() . '_' . uniqid() . '.pdf';
            $destination = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $destination)) {
                $insertStmt = $pdo->prepare("INSERT INTO documents (title, abstract, authors, category_id, pub_year, file_path, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$title, $abstract, $authors, $categoryId, $pubYear, $uniqueName, $status, $student['id']]);
                flash_set('success', 'Your document has been successfully submitted and is currently awaiting admin approval.');
            } else {
                flash_set('error', 'Server error: Failed to save the document.');
            }
        } else {
            flash_set('error', 'Invalid file format. Please upload a PDF file.');
        }
    } else {
        flash_set('error', 'Please attach a valid PDF document.');
    }
    
    header('Location: submit_document.php');
    exit;
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
    <title>Submit Document · ScholArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1900a6; --primary-hover: #100075; --primary-light: #e0e7ff;
            --bg-light: #f4f6f9; --white: #ffffff; --text-dark: #0f172a; 
            --text-muted: #64748b; --border-light: #e2e8f0; --accent-orange: #f59e0b;
            --font-family: 'Inter', sans-serif;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
            --shadow-md: 0 10px 25px -5px rgba(0,0,0,0.05);
            --shadow-lg: 0 15px 35px -5px rgba(0,0,0,0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-family); background-color: var(--bg-light); color: var(--text-dark); display: flex; flex-direction: column; min-height: 100vh;}

        /* Top Navigation Bar */
        .navbar { background-color: var(--primary); color: var(--white); padding: 0 4rem; height: 75px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
        .nav-brand img { width: 40px; height: 40px; border-radius: 50%; object-fit: contain; background: #fff; padding: 2px; }
        .nav-brand span { font-weight: 800; font-size: 1.25rem; letter-spacing: -0.5px; }
        .nav-links { display: flex; gap: 2.5rem; align-items: center; height: 100%; }
        .nav-links a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 0.95rem; font-weight: 600; transition: color 0.2s; position: relative; display: flex; align-items: center; height: 100%; }
        .nav-links a:hover { color: var(--white); }
        .nav-links a.active { color: var(--white); font-weight: 700; }
        .nav-links a.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background-color: var(--white); border-radius: 4px 4px 0 0; }
        .nav-user { display: flex; align-items: center; gap: 1.5rem; }
        .btn-bell { background: none; border: none; color: white; cursor: pointer; position: relative; opacity: 0.85; transition: opacity 0.2s; }
        .btn-bell:hover { opacity: 1; }
        .bell-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; border: 2px solid var(--primary); }
        .avatar-wrap { display: flex; align-items: center; gap: 10px; cursor: pointer; text-decoration: none; color: white;}
        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e0e7ff; border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        .avatar-initials { width: 40px; height: 40px; border-radius: 50%; background: #3b82f6; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem; border: 2px solid rgba(255,255,255,0.2); }

        /* Integrated Hero Banner Background */
        .hero-banner {
            background: linear-gradient(135deg, rgba(15, 0, 104, 0.92) 0%, rgba(25, 0, 166, 0.75) 100%), url('../assets/backgroundaclc.png');
            background-size: cover;
            background-position: center 40%;
            height: 300px;
            width: 100%;
            position: relative;
        }

        /* Main Content Workspace */
        .main-content { padding: 0 4rem 4rem 4rem; max-width: 900px; margin: 0 auto; width: 100%; flex: 1; display: flex; flex-direction: column; margin-top: -200px; position: relative; z-index: 10;}

        /* Header Info */
        .header-title { color: var(--white); margin-bottom: 2rem; }
        .header-title h1 { font-size: 2.2rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 8px; }
        .header-title p { font-size: 1rem; opacity: 0.9; font-weight: 400; line-height: 1.5;}

        /* Alerts */
        .alert { padding: 1rem 1.5rem; border-radius: 12px; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);}
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Form Card */
        .form-card { background: var(--white); border-radius: 16px; padding: 3rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-lg); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .full-width { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); }
        
        .form-control { padding: 0.85rem 1.25rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; font-family: inherit; transition: all 0.2s; color: var(--text-dark); background: #f8fafc; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-light); background: var(--white);}
        .form-control::placeholder { color: #94a3b8; }
        textarea.form-control { resize: vertical; min-height: 120px; line-height: 1.5; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; }

        /* File Upload Box */
        .file-upload-box { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 2.5rem 2rem; text-align: center; background: #f8fafc; transition: all 0.2s; cursor: pointer; position: relative; }
        .file-upload-box:hover { border-color: var(--primary); background: #eff6ff; }
        .file-upload-box svg { color: var(--primary); margin-bottom: 10px; }
        .file-upload-text { font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .file-upload-sub { font-size: 0.8rem; color: var(--text-muted); }
        .file-upload-box input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        .btn-submit { background: var(--primary); color: white; border: none; padding: 1rem 2rem; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(25, 0, 166, 0.2); width: 100%; margin-top: 1.5rem;}
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(25, 0, 166, 0.25);}

        @media (max-width: 768px) {
            .navbar { padding: 0 2rem; }
            .main-content { padding: 0 2rem 2rem 2rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-card { padding: 2rem; }
        }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION -->
    <nav class="navbar">
        <a href="dashboard.php" class="nav-brand">
            <img src="../assets/logo1.png" alt="Logo">
            <span>ScholArchive</span>
        </a>
        <div class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="browse.php">Browse Documents</a>
            <a href="submit_document.php" class="active">Submit Research</a>
            <a href="library.php">My Library</a>
            <a href="profile.php">My Profile</a>
            <a href="about.php">About the System</a>
        </div>
        <div class="nav-user">
            <button class="btn-bell"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><div class="bell-dot"></div></button>
            <a href="profile.php" class="avatar-wrap">
                <?php if (!empty($student['selfie']) && file_exists('../uploads/' . $student['selfie'])): ?>
                    <img src="../uploads/<?= e($student['selfie']) ?>" alt="Avatar" class="avatar">
                <?php else: ?>
                    <div class="avatar-initials"><?= get_initials($student['first_name'], $student['last_name']) ?></div>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <div class="hero-banner"></div>

    <main class="main-content">
        
        <div class="header-title">
            <h1>Contribute to the Repository</h1>
            <p>Submit your group's capstone or research paper to the central system. Your document will become publicly available once verified and approved by the system administrator.</p>
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

        <form action="" method="POST" enctype="multipart/form-data" class="form-card">
            <input type="hidden" name="action" value="submit_doc">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Research Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="Enter the complete title of your research paper">
                </div>

                <div class="form-group full-width">
                    <label>Authors / Researchers</label>
                    <input type="text" name="authors" class="form-control" required placeholder="Format: Lastname, F., Lastname, F. (Separate with commas)">
                </div>

                <div class="form-group">
                    <label>Program Category</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Select your course program...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e(str_replace(['BS ', 'Bachelor of Science in '], '', $cat['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Publication Year</label>
                    <select name="pub_year" class="form-control" required>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>>A.Y. <?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Research Abstract</label>
                    <textarea name="abstract" class="form-control" required placeholder="Paste your document's abstract or summary here..."></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Attach Capstone Document</label>
                    <div class="file-upload-box">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                        <div class="file-upload-text" id="fileNameDisplay">Click to browse or drag PDF file here</div>
                        <div class="file-upload-sub">Only strictly formatted .PDF files are accepted</div>
                        <input type="file" name="document_file" id="fileInput" accept="application/pdf" required onchange="updateFileName(this)">
                    </div>
                </div>

                <div class="full-width">
                    <button type="submit" class="btn-submit">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Submit for Admin Verification
                    </button>
                </div>
            </div>
        </form>

    </main>

    <script>
        function updateFileName(input) {
            const display = document.getElementById('fileNameDisplay');
            if (input.files && input.files[0]) {
                display.innerText = 'Selected file: ' + input.files[0].name;
                display.style.color = 'var(--primary)';
            } else {
                display.innerText = 'Click to browse or drag PDF file here';
                display.style.color = 'var(--text-dark)';
            }
        }
    </script>
</body>
</html>