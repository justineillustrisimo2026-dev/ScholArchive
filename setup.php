<?php
// setup_admin.php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

// New Admin Credentials following the Student Format!
$adminId = 'A24-01-0000-MAN001'; 
$password = 'Admin@1234'; 
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO users 
        (first_name, last_name, email, course, year_level, student_id, password, id_photo, selfie, role, status) 
        VALUES ('System', 'Admin', 'admin@scholarchive.edu', 'N/A', 'N/A', ?, ?, 'N/A', 'N/A', 'admin', 'active')
    ");
    
    $stmt->execute([$adminId, $hashedPassword]);
    echo "<h2 style='color: green;'>Admin account successfully created!</h2>";
    echo "<b>Admin ID No:</b> A24-01-0000-MAN001 <br>";
    echo "<b>Password:</b> Admin@1234 <br><br>";
    echo "<a href='index.php'>Click here to go to the Login Page</a>";
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "<h2 style='color: red;'>Admin already exists!</h2><a href='index.php'>Go to Login</a>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>