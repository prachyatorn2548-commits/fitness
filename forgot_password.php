<?php
session_start();
require 'db.php';

$swal_type = '';
$swal_title = '';
$swal_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        $swal_type = 'warning';
        $swal_title = 'รหัสผ่านไม่ตรงกัน';
        $swal_msg = 'กรุณายืนยันรหัสผ่านใหม่อีกครั้งให้ตรงกัน';
    } else {
        // ตรวจสอบว่าชื่อผู้ใช้และอีเมลตรงกับในระบบหรือไม่
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND email = ?");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->execute([$hashed, $user['user_id']]);

            $swal_type = 'success';
            $swal_title = 'รีเซ็ตรหัสผ่านสำเร็จ!';
            $swal_msg = 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว เข้าสู่ระบบได้ทันที';
        } else {
            $swal_type = 'error';
            $swal_title = 'ไม่พบข้อมูล';
            $swal_msg = 'ชื่อผู้ใช้หรืออีเมลที่ระบุไม่ถูกต้องในระบบ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #0d0f12;
            color: #f8fafc;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-box {
            width: 100%;
            max-width: 480px;
            background-color: #14171d;
            border-radius: 20px;
            padding: 35px 30px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }
        .form-control-custom {
            background-color: #0d0f12;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.88rem;
        }
        .form-control-custom:focus {
            background-color: #0d0f12;
            border-color: #e50914;
            color: #fff;
            box-shadow: none;
        }
        .btn-trainup {
            background-color: #e50914;
            color: #fff;
            font-weight: 500;
            border-radius: 8px;
            padding: 10px;
            border: none;
            transition: all 0.2s;
        }
        .btn-trainup:hover {
            background-color: #b80710;
            color: #fff;
        }
    </style>
</head>
<body class="p-3">

<div class="form-box">
    <div class="text-center mb-4">
        <h4 class="fw-bold text-white mb-1" style="font-family: 'Anton', sans-serif; letter-spacing: 1.5px;">
            <i class="bi bi-shield-lock text-danger me-1"></i> RESET PASSWORD
        </h4>
        <small class="text-secondary">กรอกชื่อผู้ใช้และอีเมลที่ลงทะเบียนไว้เพื่อตั้งรหัสผ่านใหม่</small>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small text-secondary mb-1">ชื่อผู้ใช้ (Username)</label>
            <input type="text" name="username" class="form-control form-control-custom" placeholder="กรอกชื่อผู้ใช้ของคุณ" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label small text-secondary mb-1">อีเมลที่ลงทะเบียน (E-mail)</label>
            <input type="email" name="email" class="form-control form-control-custom" placeholder="example@email.com" required>
        </div>

        <div class="mb-3">
            <label class="form-label small text-secondary mb-1">รหัสผ่านใหม่ (New Password)</label>
            <input type="password" name="new_password" class="form-control form-control-custom" placeholder="ตั้งรหัสผ่านใหม่" required minlength="4">
        </div>

        <div class="mb-4">
            <label class="form-label small text-secondary mb-1">ยืนยันรหัสผ่านใหม่ (Confirm Password)</label>
            <input type="password" name="confirm_password" class="form-control form-control-custom" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required>
        </div>

        <button type="submit" class="btn btn-trainup w-100 mb-3">
            <i class="bi bi-check2-circle me-1"></i> ตั้งรหัสผ่านใหม่
        </button>

        <div class="text-center border-top border-secondary border-opacity-25 pt-3">
            <a href="index.php" class="text-secondary text-decoration-none small">
                <i class="bi bi-arrow-left me-1"></i> กลับหน้าเข้าสู่ระบบ
            </a>
        </div>
    </form>
</div>

<script>
<?php if ($swal_type === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: '<?= $swal_title ?>',
    text: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    confirmButtonColor: '#e50914',
    confirmButtonText: 'ไปหน้าเข้าสู่ระบบ'
}).then(() => {
    window.location.href = 'index.php';
});
<?php elseif ($swal_type): ?>
Swal.fire({
    icon: '<?= $swal_type ?>',
    title: '<?= $swal_title ?>',
    text: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    confirmButtonColor: '#e50914',
    confirmButtonText: 'ตกลง'
});
<?php endif; ?>
</script>
</body>
</html>