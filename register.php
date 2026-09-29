<?php
session_start();
require 'db.php';
$swal_type = '';
$swal_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username']);
    $pword = trim($_POST['password']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);

    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $chk->execute([$uname]);
    if ($chk->fetchColumn() > 0) {
        $swal_type = 'error';
        $swal_msg = 'ชื่อผู้ใช้ (Username) นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น';
    } else {
        $hash_pw = password_hash($pword, PASSWORD_DEFAULT);
        $u_stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'member')");
        $u_stmt->execute([$uname, $hash_pw, $email]);
        $new_user_id = $pdo->lastInsertId();

        $m_stmt = $pdo->prepare("INSERT INTO members (user_id, fullname, status) VALUES (?, ?, 'pending')");
        $m_stmt->execute([$new_user_id, $fullname]);

        $swal_type = 'success';
        $swal_msg = 'สร้างบัญชีสำเร็จ! กำลังพาท่านไปยังหน้าเข้าสู่ระบบ';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - TRAINUP</title>
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
        .register-wrapper {
            width: 100%;
            max-width: 900px;
            min-height: 560px;
            background-color: #14171d;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.07);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }
        .brand-side {
            background: linear-gradient(rgba(10, 12, 16, 0.45), rgba(10, 12, 16, 0.88)), 
                        url('https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1000&auto=format&fit=crop') center/cover no-repeat;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .brand-logo {
            font-family: 'Anton', sans-serif;
            letter-spacing: 2px;
            font-size: 1.6rem;
            color: #fff;
        }
        .form-side {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-control-custom {
            background-color: #0d0f12;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: 10px;
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
            border-radius: 10px;
            padding: 11px;
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

<div class="register-wrapper row g-0">
    <!-- ฝั่งซ้าย: แบนเนอร์ภาพฟิตเนส -->
    <div class="col-md-5 d-none d-md-flex brand-side">
        <div class="brand-logo"><i class="bi bi-fire text-danger me-1"></i> TRAINUP</div>
        <div>
            <h2 class="fw-bold text-white mb-2" style="font-family: 'Anton', sans-serif; letter-spacing: 1px;">JOIN THE CLUB</h2>
            <p class="small text-secondary mb-0">เริ่มต้นเส้นทางการออกกำลังกายของคุณ พร้อมรับสิทธิ์เลือกแพ็กเกจที่ดีที่สุดได้ทันทีหลังลงทะเบียน</p>
        </div>
        <small class="text-secondary opacity-75">© 2026 TRAINUP CLUB</small>
    </div>

    <!-- ฝั่งขวา: ฟอร์มสมัครสมาชิก -->
    <div class="col-md-7 form-side">
        <div class="mb-3">
            <h4 class="fw-bold text-white mb-1">สร้างบัญชีสมาชิก</h4>
            <small class="text-secondary">กรอกรายละเอียดเพื่อสมัครสมาชิกใหม่</small>
        </div>

        <form method="POST">
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" class="form-control form-control-custom" placeholder="ตั้งชื่อผู้ใช้" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-secondary">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control form-control-custom" placeholder="ตั้งรหัสผ่าน" required>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label small text-secondary">ชื่อ - นามสกุล</label>
                <input type="text" name="fullname" class="form-control form-control-custom" placeholder="ระบุชื่อจริง นามสกุลจริง" required>
            </div>

            <div class="mb-3">
                <label class="form-label small text-secondary">อีเมล (E-mail)</label>
                <input type="email" name="email" class="form-control form-control-custom" placeholder="name@example.com" required>
            </div>

            <button type="submit" class="btn btn-trainup w-100 mb-3">ลงทะเบียนสมาชิก</button>

            <div class="text-center">
                <span class="text-secondary small">มีบัญชีอยู่แล้ว?</span>
                <a href="index.php" class="text-danger text-decoration-none small fw-semibold ms-1">เข้าสู่ระบบ</a>
            </div>
        </form>
    </div>
</div>

<script>
<?php if ($swal_type === 'error'): ?>
Swal.fire({
    icon: 'error',
    title: 'สมัครสมาชิกไม่สำเร็จ',
    text: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    confirmButtonColor: '#e50914'
});
<?php elseif ($swal_type === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: 'ยินดีต้อนรับ',
    text: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    showConfirmButton: false,
    timer: 1800
}).then(() => {
    window.location.href = 'index.php';
});
<?php endif; ?>
</script>
</body>
</html>