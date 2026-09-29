<?php
require 'db.php';

$username = 'admin';
$password = '1234';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// ลบ user admin เก่าออกก่อน (ถ้ามี) แล้วเพิ่มใหม่
$pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);

$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
if ($stmt->execute([$username, $hashed])) {
    echo "<h2 style='color:green;'>รีเซ็ตรหัสผ่านแอดมินสำเร็จ!</h2>";
    echo "<p>Username: <b>admin</b> | Password: <b>1234</b></p>";
    echo "<a href='index.php'>คลิกที่นี่เพื่อไปหน้า Login</a>";
} else {
    echo "<h2 style='color:red;'>เกิดข้อผิดพลาดในการบันทึกข้อมูล</h2>";
}
?>