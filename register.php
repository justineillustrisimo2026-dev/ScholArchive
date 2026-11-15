
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$error = '';
$success = '';

$firstName = '';
$lastName = '';
$email = '';
$course = '';
$yearLevel = '';
$studentId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $yearLevel = trim($_POST['yearLevel'] ?? '');
    $studentId = strtoupper(trim($_POST['studentId'] ?? ''));

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    $studentIdPattern = '/^C\d{2}-\d{2}-\d{4}-MAN\d{2,4}$/i';

    if (
        $firstName === '' ||
        $lastName === '' ||
        $email === '' ||
        $course === '' ||
        $yearLevel === '' ||
        $studentId === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $error = 'Please fill in all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif (!preg_match($studentIdPattern, $studentId)) {
        $error = 'Invalid Student ID format. Example: C24-01-9197-MAN121.';

    } elseif (!preg_match($passwordPattern, $password)) {
        $error = 'Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number, and one special character.';

    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';

    } else {

        try {
            $pdo->beginTransaction();

            // Check whether the email is already being used.
            $emailCheck = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );
            $emailCheck->execute([$email]);

            if ($emailCheck->fetch()) {
                throw new Exception('An account with this email address already exists.');
            }

            // Lock the matching whitelist record during registration.
            $checkStudent = $pdo->prepare("
                SELECT id, student_id, firstname, lastname, course, is_registered
                FROM student_whitelist
                WHERE student_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $checkStudent->execute([$studentId]);
            $officialStudent = $checkStudent->fetch(PDO::FETCH_ASSOC);

            if (!$officialStudent) {
                throw new Exception(
                    'Your Student ID is not found in the official student records.'
                );
            }

            // Normalize names and course to avoid case-related mismatches.
            $submittedFirstName = strtoupper(trim($firstName));
            $submittedLastName = strtoupper(trim($lastName));
            $submittedCourse = strtoupper(trim($course));

            $officialFirstName = strtoupper(trim($officialStudent['firstname']));
            $officialLastName = strtoupper(trim($officialStudent['lastname']));
            $officialCourse = strtoupper(trim($officialStudent['course']));

            // Verify all submitted information against the whitelist.
            if (
                $submittedFirstName !== $officialFirstName ||
                $submittedLastName !== $officialLastName ||
                $submittedCourse !== $officialCourse
            ) {
                throw new Exception(
                    'Your name or course does not match the official student records. Please check your information.'
                );
            }

            // Prevent a student from registering more than once.
            if ((int)$officialStudent['is_registered'] === 1) {
                throw new Exception(
                    'This Student ID has already been registered.'
                );
            }

            // Hash the password before saving it.
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Create the student account as active.
            $insertUser = $pdo->prepare("
                INSERT INTO users
                (
                    first_name,
                    last_name,
                    email,
                    course,
                    year_level,
                    student_id,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 'active')
            ");

            $insertUser->execute([
                $firstName,
                $lastName,
                $email,
                $course,
                $yearLevel,
                $studentId,
                $hashedPassword
            ]);

            // Mark the official student record as already registered.
            $updateWhitelist = $pdo->prepare("
                UPDATE student_whitelist
                SET is_registered = 1
                WHERE id = ?
            ");

            $updateWhitelist->execute([
                $officialStudent['id']
            ]);

            // Save both operations together.
            $pdo->commit();

            header('Location: index.php');
            exit();

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() == '23000') {
                $error = 'This email address or Student ID is already associated with an account.';
            } else {
                error_log($e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up · ScholArchive</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #1a01b6;
            --vivid: #2300ff;
            --deep: #001cae;
            --lavender: #f3f3ff;
            --primary-light: #e0e7ff;
            --navy: #090d38;
            --text-dark: #0f172a;
            --text-muted: #64779b;
            --border: #dfe3ee;
            --success: #10b981;
            --font: 'Inter', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
            color: var(--text-dark);
            background: var(--lavender);
            -webkit-font-smoothing: antialiased;
        }

        .page {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            background: var(--lavender);
        }

        .stripe {
            position: absolute;
            inset: 0;
            background: var(--deep);
            clip-path: url(#arcStripe);
            z-index: 0;
        }

        .hero {
            position: absolute;
            inset: 0;
            clip-path: url(#arc);
            background:
                linear-gradient(
                    100deg,
                    rgba(30, 24, 112, 0.93),
                    rgba(44, 38, 124, 0.86) 55%,
                    rgba(64, 56, 146, 0.78)
                ),
                url('assets/backgroundpic.png') center / cover no-repeat;
            z-index: 1;
        }

        .dots {
            position: absolute;
            pointer-events: none;
            background-image: radial-gradient(
                circle,
                currentColor 1.5px,
                transparent 1.6px
            );
            background-size: 22px 22px;
        }

        .dots-tl {
            top: 24px;
            left: 24px;
            width: 210px;
            height: 170px;
            color: rgba(255,255,255,.28);
            z-index: 2;
        }

        .dots-br {
            bottom: 24px;
            right: 24px;
            width: 260px;
            height: 190px;
            color: rgba(99,102,241,.22);
            z-index: 1;
        }

        .stage {
            position: relative;
            z-index: 3;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 600px;
            align-items: center;
            gap: 2rem;
            padding: 1.5rem clamp(2rem, 6vw, 8rem);
        }

        .left-text {
            color: #fff;
            min-width: 0;
        }

        .top-brand {
            display: flex;
            align-items: center;
            gap: 1.1rem;
        }

        .top-brand img {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: #fff;
            padding: 2px;
            object-fit: contain;
        }

        .top-brand .name {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .top-brand .name small {
            display: block;
            font-size: 1.05rem;
            font-weight: 500;
            letter-spacing: 3px;
            opacity: .85;
            margin-top: 6px;
        }

        .brand-underline {
            width: 90px;
            height: 3px;
            border-radius: 2px;
            margin: 2.2rem 0 2rem;
            background: linear-gradient(90deg, #c026d3, #3b82f6);
        }

        .welcome-sub {
            font-size: 1.5rem;
            margin-bottom: .2rem;
        }

        .main-title {
            font-size: clamp(3rem, 5vw, 5rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -2px;
            margin-bottom: 1.3rem;
        }

        .main-title .grad {
            color: #7777ff;
        }

        .description {
            font-size: 1.02rem;
            line-height: 1.65;
            max-width: 460px;
            color: rgba(255,255,255,.88);
            margin-bottom: 2rem;
        }

        .features {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
            width: min(100%, 460px);
            padding: 1rem .5rem;
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 14px;
            background: rgba(255,255,255,.06);
            backdrop-filter: blur(6px);
        }

        .feature {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .5rem 1rem;
        }

        .feature + .feature {
            border-top: 1px solid rgba(255,255,255,.18);
        }

        .feature .ico {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99,102,241,.22);
            color: #b4b7ff;
        }

        .feature svg {
            width: 24px;
            height: 24px;
        }

        .feature strong {
            display: block;
            font-size: .9rem;
            margin-bottom: 3px;
        }

        .feature span {
            display: block;
            font-size: .73rem;
            color: rgba(255,255,255,.75);
        }

        .auth-card {
            background: #fff;
            border-radius: 22px;
            padding: 1.5rem 1.75rem;
            box-shadow:
                0 30px 60px -20px rgba(40,30,140,.30),
                0 8px 24px rgba(15,23,42,.06);
            width: 100%;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .school-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .card-header h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--navy);
        }

        .card-header h3 .grad {
            color: var(--primary);
        }

        .card-header p {
            color: var(--text-muted);
            font-size: .84rem;
            margin-top: 3px;
        }

        .alert {
            padding: .7rem .9rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: .8rem;
            line-height: 1.5;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .section-title {
            font-size: .85rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: .75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title svg {
            width: 17px;
            height: 17px;
        }

        .form-section {
            margin-bottom: 1.2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .8rem;
        }

        .field {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .field label {
            font-size: .74rem;
            font-weight: 600;
            margin-bottom: .3rem;
            color: var(--text-dark);
        }

        .span-2 {
            grid-column: 1 / -1;
        }

        .input {
            width: 100%;
            height: 42px;
            padding: 0 .8rem;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-family: inherit;
            font-size: .84rem;
            background: #fbfbfe;
            color: var(--text-dark);
            transition: .2s;
        }

        .input::placeholder {
            color: #a6aec4;
        }

        .input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: #fff;
        }

        select.input {
            cursor: pointer;
        }

        .login-badge {
            font-size: .6rem;
            color: var(--primary);
            background: var(--primary-light);
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 700;
            margin-left: 5px;
        }

        .password-reqs-container {
            background: #f6f7fd;
            padding: .65rem .7rem;
            border-radius: 9px;
            border: 1px solid var(--border);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .req-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            color: var(--text-muted);
        }

        .req-item svg {
            width: 13px;
            height: 13px;
            color: #cbd5e1;
        }

        .req-item.met {
            color: var(--success);
            font-weight: 600;
        }

        .req-item.met svg {
            color: var(--success);
        }

        .verification-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: .8rem;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            border-radius: 10px;
            color: #1e40af;
            font-size: .75rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .verification-note svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .btn {
            width: 100%;
            height: 46px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 18px rgba(26,1,182,.30);
            transition: .2s;
        }

        .btn:hover {
            background: var(--vivid);
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(35,0,255,.34);
        }

        .btn:disabled {
            opacity: .7;
            cursor: wait;
            transform: none;
        }

        .auth-foot {
            text-align: center;
            color: var(--text-muted);
            font-size: .85rem;
            margin-top: 1.1rem;
        }

        .auth-foot a {
            color: var(--vivid);
            text-decoration: none;
            font-weight: 700;
            margin-left: 4px;
        }

        .auth-foot a:hover {
            text-decoration: underline;
        }

        @media (max-width: 1100px) {
            .stage {
                grid-template-columns: 1fr 540px;
                padding: 1.5rem 2rem;
            }

            .features {
                display: none;
            }
        }

        @media (max-width: 900px) {
            .hero {
                clip-path: none;
            }

            .stripe,
            .dots-br {
                display: none;
            }

            .stage {
                grid-template-columns: 1fr;
                max-width: 600px;
                margin: 0 auto;
                padding: 2rem 1.25rem;
                gap: 2rem;
            }

            .left-text {
                text-align: center;
            }

            .top-brand {
                justify-content: center;
                text-align: left;
            }

            .brand-underline {
                margin-left: auto;
                margin-right: auto;
            }

            .description {
                margin: 0 auto;
            }

            .main-title {
                font-size: 3rem;
            }
        }

        @media (max-width: 480px) {
            .stage {
                padding: 1.5rem .9rem;
            }

            .auth-card {
                padding: 1.3rem 1.1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .span-2 {
                grid-column: auto;
            }

            .top-brand img {
                width: 65px;
                height: 65px;
            }

            .top-brand .name {
                font-size: 1.4rem;
            }

            .main-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <clipPath id="arc" clipPathUnits="objectBoundingBox">
            <path d="M0,0 H0.763 C0.72,0.45 0.68,0.75 0.48,1 H0 Z"/>
        </clipPath>

        <clipPath id="arcStripe" clipPathUnits="objectBoundingBox">
            <path d="M0,0 H0.79 C0.745,0.45 0.705,0.76 0.505,1 H0 Z"/>
        </clipPath>
    </defs>
</svg>

<div class="page">

    <div class="stripe"></div>
    <div class="hero"></div>
    <div class="dots dots-tl"></div>
    <div class="dots dots-br"></div>

    <div class="stage">

        <!-- LEFT BRANDING SECTION -->
         <div class="left-text">
            <div class="top-brand">
                <img src="assets/logo1.png" alt="ACLC Logo">
                <div class="name">ACLC<br>COLLEGE<small>OF MANDAUE</small></div>
            </div>

            <div class="brand-underline"></div>

            <div class="welcome-sub">Welcome to</div>
            <h1 class="main-title">Schol<span class="grad">Archive</span></h1>

            <p class="description">A digital platform that allows ACLC College of Mandaue students and administrators to securely access, explore, and learn from capstone projects and research papers.</p>

            <div class="features">
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10 11V9a2 2 0 0 1 4 0v2"/></svg>
                    </div>
                    <div><strong>Secure Access</strong><span>Your data is protected with enterprise-grade security.</span></div>
                </div>
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.9 1.2 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                    </div>
                    <div><strong>Explore Knowledge</strong><span>Discover innovative projects and valuable research.</span></div>
                </div>
                <div class="feature">
                    <div class="ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/><path d="M22 10v6"/></svg>
                    </div>
                    <div><strong>Empower Learning</strong><span>Learn, collaborate, and create a better future together.</span></div>
                </div>
            </div>
        </div>

        <!-- REGISTRATION CARD -->
        <div class="auth-card">

            <div class="card-header">
                <img src="assets/logo1.png" alt="School Logo" class="school-logo">

                <div>
                    <h3>Create <span class="grad">Account</span></h3>
                    <p>Register to access the repository</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">

                <!-- STUDENT PROFILE -->
                <div class="form-section">

                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>

                        1. Student Profile
                    </div>

                    <div class="form-row">

                        <div class="field">
                            <label for="firstName">First Name</label>

                            <input
                                type="text"
                                class="input"
                                id="firstName"
                                name="firstName"
                                placeholder="Juan"
                                required
                                value="<?= e($firstName) ?>"
                            >
                        </div>

                        <div class="field">
                            <label for="lastName">Last Name</label>

                            <input
                                type="text"
                                class="input"
                                id="lastName"
                                name="lastName"
                                placeholder="Dela Cruz"
                                required
                                value="<?= e($lastName) ?>"
                            >
                        </div>

                        <div class="field span-2">
                            <label for="studentId">Student ID No.</label>

                            <input
                                type="text"
                                class="input"
                                id="studentId"
                                name="studentId"
                                placeholder="C24-01-9197-MAN121"
                                required
                                value="<?= e($studentId) ?>"
                            >
                        </div>

                        <div class="field span-2">
                            <label for="email">
                                Email Address
                                <span class="login-badge">Sign-in</span>
                            </label>

                            <input
                                type="email"
                                class="input"
                                id="email"
                                name="email"
                                placeholder="student@example.com"
                                required
                                value="<?= e($email) ?>"
                            >
                        </div>

                        <div class="field">
                            <label for="course">Course / Program</label>

                            <select class="input" id="course" name="course" required>
                                <option value="">Select course</option>

                                <?php
                                $courses = ['BSIT', 'BSCS', 'BSA', 'BSBA', 'BSHM'];

                                foreach ($courses as $c):
                                ?>
                                    <option
                                        value="<?= e($c) ?>"
                                        <?= $course === $c ? 'selected' : '' ?>
                                    >
                                        <?= e($c) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="field">
                            <label for="yearLevel">Year Level</label>

                            <select class="input" id="yearLevel" name="yearLevel" required>
                                <option value="">Select year level</option>

                                <?php
                                $years = [
                                    '1st Year',
                                    '2nd Year',
                                    '3rd Year',
                                    '4th Year'
                                ];

                                foreach ($years as $year):
                                ?>
                                    <option
                                        value="<?= e($year) ?>"
                                        <?= $yearLevel === $year ? 'selected' : '' ?>
                                    >
                                        <?= e($year) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                    </div>
                </div>

                <!-- PASSWORD SECTION -->
                <div class="form-section">

                    <div class="section-title">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>

                        2. Account Security
                    </div>

                    <div class="form-row">

                        <div class="field">
                            <label for="password">Password</label>

                            <input
                                type="password"
                                class="input"
                                id="password"
                                name="password"
                                placeholder="Create password"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="confirmPassword">Confirm Password</label>

                            <input
                                type="password"
                                class="input"
                                id="confirmPassword"
                                name="confirmPassword"
                                placeholder="Confirm password"
                                required
                            >
                        </div>

                        <div class="span-2 password-reqs-container">

                            <div class="req-item" id="req-length">
                                ✓ 8+ characters
                            </div>

                            <div class="req-item" id="req-upper">
                                ✓ 1 uppercase
                            </div>

                            <div class="req-item" id="req-lower">
                                ✓ 1 lowercase
                            </div>

                            <div class="req-item" id="req-num-spec">
                                ✓ 1 number & symbol
                            </div>

                        </div>

                    </div>
                </div>

                <!-- AUTOMATIC VERIFICATION NOTICE -->
                <div class="verification-note">

                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>

                    <div>
                        <strong>Student Verification</strong><br>
                        Your information will be checked against the
                        official ACLC student records. Make sure your
                        name, Student ID, and course are correct.
                    </div>

                </div>

                <button type="submit" class="btn" id="submitBtn">
                    Create Account

                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <line x1="4" y1="12" x2="20" y2="12"/>
                        <polyline points="13 5 20 12 13 19"/>
                    </svg>
                </button>

            </form>

            <p class="auth-foot">
                Already have an account?
                <a href="index.php">Sign In</a>
            </p>

        </div>

    </div>
</div>

<script>
    // Automatically format the ACLC Student ID.
    const studentIdInput = document.getElementById('studentId');

    studentIdInput.addEventListener('input', function () {

        let raw = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

        let formatted = '';

        if (raw.length > 0) formatted += raw.substring(0, 3);
        if (raw.length > 3) formatted += '-' + raw.substring(3, 5);
        if (raw.length > 5) formatted += '-' + raw.substring(5, 9);
        if (raw.length > 9) formatted += '-' + raw.substring(9, 15);

        this.value = formatted;
    });

    // Live password requirement indicators.
    const passwordInput = document.getElementById('password');

    const requirements = {
        length: document.getElementById('req-length'),
        upper: document.getElementById('req-upper'),
        lower: document.getElementById('req-lower'),
        numberSymbol: document.getElementById('req-num-spec')
    };

    passwordInput.addEventListener('input', function () {

        const value = this.value;

        requirements.length.classList.toggle('met', value.length >= 8);
        requirements.upper.classList.toggle('met', /[A-Z]/.test(value));
        requirements.lower.classList.toggle('met', /[a-z]/.test(value));

        requirements.numberSymbol.classList.toggle(
            'met',
            /\d/.test(value) && /[\W_]/.test(value)
        );
    });

    // Prevent accidental repeated submission.
    document.getElementById('registerForm').addEventListener('submit', function () {

        const button = document.getElementById('submitBtn');

        button.disabled = true;
        button.textContent = 'Verifying Student Information...';
    });
</script>

</body>
</html>
