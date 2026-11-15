<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

// New uniform credentials
$newStudentId = 'C00-00-0000-MAN000';
$newPassword = 'Admin@1234';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    // Update user with ID 1 (your admin account)
    $stmt = $pdo->prepare("
        UPDATE users 
        SET student_id = ?, password = ?, role = 'admin', status = 'active' 
        WHERE id = 1
    ");
    $stmt->execute([$newStudentId, $hashedPassword]);

    echo "<h2 style='color: green;'>Admin credentials successfully updated!</h2>";
    echo "<b>Student ID:</b> C00-00-0000-MAN000<br>";
    echo "<b>Password:</b> Admin@1234<br><br>";
    echo "<a href='index.php'>Click here to go to Login</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>