<?php
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username']);
    $pword = trim($_POST['password']);
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $card = trim($_POST['card_code']);
    $pkg_id = intval($_POST['package_id']);

    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $chk->execute([$uname]);
    if ($chk->fetchColumn() > 0) {
        $error = "ชื่อผู้ใช้นี้มีในระบบแล้ว";
    } else {
        $chk_card = $pdo->prepare("SELECT COUNT(*) FROM members WHERE card_code = ?");
        $chk_card->execute([$card]);
        if ($chk_card->fetchColumn() > 0) {
            $error = "รหัสบัตรนี้มีในระบบแล้ว";
        } else {
            $hash_pw = password_hash($pword, PASSWORD_DEFAULT);
            $u_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'member')");
            $u_stmt->execute([$uname, $hash_pw]);
            $new_user_id = $pdo->lastInsertId();

            $p_stmt = $pdo->prepare("SELECT duration_days, price FROM packages WHERE package_id = ?");
            $p_stmt->execute([$pkg_id]);
            $pkg = $p_stmt->fetch();

            $start = date('Y-m-d');
            $expire = date('Y-m-d', strtotime("+$pkg[duration_days] days"));

            $m_stmt = $pdo->prepare("INSERT INTO members (user_id, fullname, phone, card_code, package_id, start_date, expire_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $m_stmt->execute([$new_user_id, $fullname, $phone, $card, $pkg_id, $start, $expire]);
            $new_member_id = $pdo->lastInsertId();

            $pay_stmt = $pdo->prepare("INSERT INTO payments (member_id, amount, payment_type) VALUES (?, ?, 'new')");
            $pay_stmt->execute([$new_member_id, $pkg['price']]);

            $success = "ลงทะเบียนและบันทึกการชำระเงินเรียบร้อยแล้ว";
        }
    }
}

$packages = $pdo->query("SELECT * FROM packages")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ลงทะเบียนสมาชิกใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width: 650px;">
    <div class="card shadow-sm border-0 p-4">
        <h4 class="mb-3 text-center">ลงทะเบียนสมาชิกใหม่</h4>

        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
                <div class="mt-2"><a href="members.php" class="btn btn-sm btn-success">ไปหน้ารายชื่อสมาชิก</a></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">ชื่อ - นามสกุล</label>
                <input type="text" name="fullname" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">รหัสบัตรสมาชิก / Barcode</label>
                    <input type="text" name="card_code" class="form-control" placeholder="เช่น MEM001" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">เลือกแพ็กเกจ</label>
                <select name="package_id" class="form-select" required>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= $pkg['package_id'] ?>">
                            <?= $pkg['package_name'] ?> (<?= $pkg['duration_days'] ?> วัน) - <?= number_format($pkg['price'], 2) ?> บาท
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-2">บันทึกข้อมูลและรับชำระเงิน</button>
            <a href="members.php" class="btn btn-light w-100">ยกเลิก</a>
        </form>
    </div>
</div>
</body>
</html>