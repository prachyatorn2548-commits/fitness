<?php
require 'db.php';

$swal_type = '';
$swal_title = '';
$swal_msg = '';
$redirect_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username']);
    $pword = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$uname]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pword, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $swal_type = 'success';
        $swal_title = 'เข้าสู่ระบบสำเร็จ!';
        $swal_msg = 'ยินดีต้อนรับคุณ ' . htmlspecialchars($user['username']);
        $redirect_url = ($user['role'] === 'admin') ? 'admin_dashboard.php' : 'member/dashboard.php';
    } else {
        $swal_type = 'error';
        $swal_title = 'เข้าสู่ระบบไม่สำเร็จ';
        $swal_msg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #0d0f12; color: #f8fafc; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .login-wrapper { width: 100%; max-width: 900px; min-height: 500px; background-color: #14171d; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.07); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6); }
        .brand-side { background: linear-gradient(rgba(10, 12, 16, 0.45), rgba(10, 12, 16, 0.88)), url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1000&auto=format&fit=crop') center/cover no-repeat; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; }
        .brand-logo { font-family: 'Anton', sans-serif; letter-spacing: 2px; font-size: 1.6rem; color: #fff; }
        .form-side { padding: 45px; display: flex; flex-direction: column; justify-content: center; }
        .form-control-custom { background-color: #0d0f12; border: 1px solid rgba(255, 255, 255, 0.12); color: #fff; border-radius: 10px; padding: 12px 16px; font-size: 0.9rem; }
        .form-control-custom:focus { background-color: #0d0f12; border-color: #e50914; color: #fff; box-shadow: none; }
        .btn-trainup { background-color: #e50914; color: #fff; font-weight: 500; border-radius: 10px; padding: 12px; border: none; transition: all 0.2s; }
        .btn-trainup:hover { background-color: #b80710; color: #fff; }
    </style>
</head>
<body class="p-3">
<div class="login-wrapper row g-0">
    <div class="col-md-5 d-none d-md-flex brand-side">
        <div class="brand-logo"><i class="bi bi-fire text-danger me-1"></i> TRAINUP</div>
        <div>
            <h2 class="fw-bold text-white mb-2" style="font-family: 'Anton', sans-serif;">WELCOME BACK</h2>
            <p class="small text-secondary mb-0">ระบบบริหารจัดการคลับฟิตเนสและสิทธิประโยชน์สมาชิก</p>
        </div>
        <small class="text-secondary opacity-75">© 2026 TRAINUP CLUB</small>
    </div>
    <div class="col-md-7 form-side">
        <div class="mb-4">
            <h4 class="fw-bold text-white mb-1">เข้าสู่ระบบ</h4>
            <small class="text-secondary">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าใช้งานบัญชีของคุณ</small>
        </div>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small text-secondary">ชื่อผู้ใช้ (Username)</label>
                <input type="text" name="username" class="form-control form-control-custom" placeholder="กรอกชื่อผู้ใช้" required autofocus>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small text-secondary mb-0">รหัสผ่าน (Password)</label>
                    <a href="forgot_password.php" class="text-secondary text-decoration-none small" style="font-size: 0.78rem;">ลืมรหัสผ่าน?</a>
                </div>
                <input type="password" name="password" class="form-control form-control-custom" placeholder="กรอกรหัสผ่าน" required>
            </div>
            <button type="submit" class="btn btn-trainup w-100 mt-3 mb-3">เข้าสู่ระบบ</button>
            <div class="text-center">
                <span class="text-secondary small">ยังไม่มีบัญชีสมาชิก?</span>
                <a href="register.php" class="text-danger text-decoration-none small fw-semibold ms-1">สมัครสมาชิกใหม่</a>
            </div>
        </form>
    </div>
</div>
<script>
<?php if ($swal_type === 'success'): ?>
Swal.fire({ icon: 'success', title: '<?= $swal_title ?>', text: '<?= $swal_msg ?>', background: '#14171d', color: '#fff', showConfirmButton: false, timer: 1500 }).then(() => { window.location.href = '<?= $redirect_url ?>'; });
<?php elseif ($swal_type === 'error'): ?>
Swal.fire({ icon: 'error', title: '<?= $swal_title ?>', text: '<?= $swal_msg ?>', background: '#14171d', color: '#fff', confirmButtonColor: '#e50914' });
<?php endif; ?>
</script>
</body>
</html>