<?php
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

$packages = $pdo->query("SELECT * FROM packages")->fetchAll();
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = $_POST['package_id'];
    
    $p_stmt = $pdo->prepare("SELECT * FROM packages WHERE package_id = ?");
    $p_stmt->execute([$package_id]);
    $pkg = $p_stmt->fetch();

    $today = date('Y-m-d');
    $current_expire = $member['expire_date'];

    $base_date = ($current_expire && $current_expire >= $today) ? $current_expire : $today;
    $new_expire = date('Y-m-d', strtotime("$base_date + {$pkg['duration_days']} days"));

    $up_stmt = $pdo->prepare("UPDATE members SET package_id = ?, expire_date = ?, status = 'active' WHERE member_id = ?");
    $up_stmt->execute([$package_id, $new_expire, $member['member_id']]);

    $pay_stmt = $pdo->prepare("INSERT INTO payments (member_id, amount, payment_type) VALUES (?, ?, 'renew')");
    $pay_stmt->execute([$member['member_id'], $pkg['price']]);

    $success = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ต่ออายุสมาชิก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width: 500px;">
    <div class="card p-4 shadow-sm border-0">
        <h4 class="mb-3 text-center">ต่ออายุสมาชิก</h4>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ต่ออายุสมาชิกและบันทึกการชำระเงินเรียบร้อยแล้ว
                <div class="mt-2"><a href="dashboard.php" class="btn btn-success btn-sm">กลับหน้าหลัก</a></div>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">เลือกแพ็กเกจที่ต้องการ</label>
                    <select name="package_id" class="form-select" required>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?= $pkg['package_id'] ?>">
                                <?= $pkg['package_name'] ?> - <?= number_format($pkg['price'], 2) ?> บาท
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning w-100 mb-2">ยืนยันการต่ออายุ</button>
                <a href="dashboard.php" class="btn btn-light w-100">ยกเลิก</a>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>