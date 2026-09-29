<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$swal_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $new_pass = trim($_POST['new_password']);

    $stmt1 = $pdo->prepare("UPDATE members SET fullname = ?, phone = ? WHERE user_id = ?");
    $stmt1->execute([$fullname, $phone, $_SESSION['user_id']]);

    if (!empty($new_pass)) {
        $hash_pw = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt2 = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE user_id = ?");
        $stmt2->execute([$email, $hash_pw, $_SESSION['user_id']]);
    } else {
        $stmt2 = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt2->execute([$email, $_SESSION['user_id']]);
    }
    $swal_status = 'profile_updated';
}

$stmt = $pdo->prepare("SELECT m.*, u.email, u.username, p.package_name FROM members m JOIN users u ON m.user_id = u.user_id LEFT JOIN packages p ON m.package_id = p.package_id WHERE m.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$is_expired = false;
$days_left = 0;

if ($member) {
    if (!$member['expire_date'] || $member['expire_date'] <= $today) {
        $is_expired = true;
    } else {
        $diff = (strtotime($member['expire_date']) - strtotime($today)) / (60 * 60 * 24);
        $days_left = ceil($diff);
    }
}

$payments = [];
$total_spent = 0;
$approved_count = 0;

if ($member) {
    $p_stmt = $pdo->prepare("SELECT p.*, COALESCE(pk1.package_name, pk2.package_name, 'แพ็กเกจฟิตเนส') AS display_package_name FROM payments p LEFT JOIN packages pk1 ON p.package_id = pk1.package_id LEFT JOIN members m ON p.member_id = m.member_id LEFT JOIN packages pk2 ON m.package_id = pk2.package_id WHERE p.member_id = ? ORDER BY p.payment_date DESC");
    $p_stmt->execute([$member['member_id']]);
    $payments = $p_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payments as $pay) {
        if ($pay['status'] === 'approved') {
            $total_spent += floatval($pay['amount']);
            $approved_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติและใบเสร็จ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-red: #e50914; --primary-red-hover: #ff1e27; --glass-bg: rgba(18, 22, 30, 0.88); --border-glass: rgba(255, 255, 255, 0.08); }
        body { font-family: 'Kanit', sans-serif; background: radial-gradient(circle at 50% 20%, rgba(229, 9, 20, 0.1) 0%, transparent 65%), linear-gradient(180deg, rgba(10, 12, 16, 0.82) 0%, rgba(10, 12, 16, 0.95) 100%), url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=1470&auto=format&fit=crop') center center / cover no-repeat fixed; color: #e2e8f0; min-height: 100vh; padding-bottom: 50px; }
        .top-navbar-floating { background: rgba(18, 22, 30, 0.8); backdrop-filter: blur(16px); border: 1px solid var(--border-glass); border-radius: 40px; margin: 15px auto 25px auto; max-width: 1050px; padding: 7px 20px; display: flex; align-items: center; justify-content: space-between; }
        .brand-logo { font-family: 'Anton', sans-serif; letter-spacing: 1.5px; font-size: 1.35rem; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .nav-center-links { display: flex; align-items: center; gap: 20px; }
        .nav-center-links a { color: #94a3b8; font-size: 0.85rem; font-weight: 500; text-decoration: none; display: flex; align-items: center; gap: 4px; }
        .nav-center-links a:hover, .nav-center-links a.active { color: #fff; }
        .user-profile-badge { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 4px 10px; color: #cbd5e1; font-size: 0.78rem; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-card-access { background: #ffffff; color: #0a0c10; font-weight: 700; font-size: 0.78rem; border-radius: 30px; padding: 5px 14px; border: none; cursor: pointer; }
        .btn-logout-circle { background: rgba(229, 9, 20, 0.9); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
        .stat-glass-card { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--border-glass); border-radius: 12px; padding: 12px 18px; }
        .stat-val-white { font-size: 1.25rem; font-weight: 700; color: #ffffff; }
        .order-glass-box { background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--border-glass); border-radius: 12px; padding: 14px 20px; margin-bottom: 10px; }
        .price-white-text { font-size: 1.25rem; font-weight: 700; color: #ffffff; }
        .btn-action-view { background: rgba(255, 255, 255, 0.06); border: 1px solid var(--border-glass); color: #cbd5e1; border-radius: 6px; padding: 5px 12px; font-size: 0.78rem; text-decoration: none; }
        .btn-action-receipt { background: var(--primary-red); border: 1px solid var(--primary-red); color: #fff; border-radius: 6px; padding: 5px 14px; font-size: 0.78rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action-receipt:hover { background: var(--primary-red-hover); color: #fff; }
    </style>
</head>
<body>
<header class="top-navbar-floating container-fluid">
    <a class="brand-logo" href="dashboard.php"><i class="bi bi-fire text-danger"></i> TRAINUP</a>
    <nav class="nav-center-links d-none d-md-flex">
        <a href="dashboard.php">หน้าหลัก</a>
        <a href="packages_view.php">แพ็กเกจฟิตเนส</a>
        <a href="payment.php" class="active">ประวัติ & ใบเสร็จ</a>
        <a href="report_issue.php"><i class="bi bi-chat-square-dots text-danger"></i> แจ้งปัญหา</a>
    </nav>
    <div class="d-flex align-items-center gap-2">
        <div class="user-profile-badge" onclick="window.location.href='dashboard.php'" title="ข้อมูลส่วนตัว">
            <i class="bi bi-person-circle text-secondary"></i>
            <span><?= htmlspecialchars($member['fullname'] ?? '') ?></span>
        </div>
        <button type="button" class="btn-logout-circle" onclick="confirmLogout('../logout.php')" title="ออกจากระบบ"><i class="bi bi-box-arrow-right" style="font-size: 0.75rem;"></i></button>
    </div>
</header>

<div class="container" style="max-width: 860px;">
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="stat-glass-card">
                <div class="text-secondary small mb-1" style="font-size: 0.78rem;"><i class="bi bi-wallet2 text-danger me-1"></i> ยอดชำระสะสม</div>
                <div class="stat-val-white">฿<?= number_format($total_spent, 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-glass-card">
                <div class="text-secondary small mb-1" style="font-size: 0.78rem;"><i class="bi bi-check2-circle text-success me-1"></i> รายการที่อนุมัติแล้ว</div>
                <div class="stat-val-white"><?= $approved_count ?> <span class="fs-6 text-secondary fw-normal">ครั้ง</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-glass-card">
                <div class="text-secondary small mb-1" style="font-size: 0.78rem;"><i class="bi bi-clock-history text-warning me-1"></i> สถานะสมาชิกปัจจุบัน</div>
                <div class="fs-6 fw-bold mt-1">
                    <?= $is_expired ? '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>หมดอายุแล้ว</span>' : "<span class='text-success'><i class='bi bi-check-circle me-1'></i>เหลือ $days_left วัน</span>" ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
        <span class="fw-semibold text-white small"><i class="bi bi-receipt-cutoff text-danger me-1"></i> ประวัติรายการคำสั่งซื้อ</span>
        <span class="text-secondary small" style="font-size: 0.75rem;">ทั้งหมด <?= count($payments) ?> รายการ</span>
    </div>

    <?php if (count($payments) > 0): ?>
        <?php foreach ($payments as $pay): 
            $pkg_title = $pay['display_package_name'];
            $is_approved = ($pay['status'] === 'approved');
        ?>
        <div class="order-glass-box">
            <div class="row align-items-center g-2">
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-secondary font-monospace" style="font-size: 0.72rem;">#INV-<?= str_pad($pay['payment_id'], 5, '0', STR_PAD_LEFT) ?></span>
                        <span class="badge bg-secondary bg-opacity-50 text-light py-0 px-2" style="font-size: 0.65rem;"><?= $pay['payment_type'] === 'renew' ? 'ต่ออายุ' : 'สมัครใหม่' ?></span>
                    </div>
                    <div class="fw-bold text-white" style="font-size: 0.95rem;"><?= htmlspecialchars($pkg_title) ?></div>
                    <div class="text-secondary" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-1"></i> ทำรายการ: <?= date('d/m/Y H:i น.', strtotime($pay['payment_date'])) ?></div>
                </div>
                <div class="col-md-3 text-md-center">
                    <div class="price-white-text">฿<?= number_format($pay['amount'], 2) ?></div>
                    <div>
                        <?php if ($is_approved): ?>
                            <span class="text-success small" style="font-size: 0.72rem;"><i class="bi bi-check-circle-fill me-1"></i>ชำระเงินเรียบร้อย</span>
                        <?php elseif ($pay['status'] === 'pending'): ?>
                            <span class="text-warning small" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i>รอตรวจสอบสลิป</span>
                        <?php else: ?>
                            <span class="text-danger small" style="font-size: 0.72rem;"><i class="bi bi-x-circle me-1"></i>ไม่อนุมัติ</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-inline-flex gap-2">
                        <?php if ($pay['slip_image']): ?>
                            <a href="../uploads/<?= htmlspecialchars($pay['slip_image']) ?>" target="_blank" class="btn-action-view"><i class="bi bi-image"></i> สลิป</a>
                        <?php endif; ?>
                        <?php if ($is_approved): ?>
                            <!-- ปุ่มเปิดใบเสร็จ A4 ฉบับทางการ -->
                            <a href="print_receipt.php?payment_id=<?= $pay['payment_id'] ?>" target="_blank" class="btn-action-receipt">
                                <i class="bi bi-printer"></i> ดูใบเสร็จ (A4)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="stat-glass-card text-center py-4">
            <i class="bi bi-inbox text-secondary fs-3 mb-1"></i>
            <div class="text-secondary small">ยังไม่มีประวัติการสั่งซื้อแพ็กเกจ</div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmLogout(logoutUrl) {
    Swal.fire({
        title: 'ยืนยันการออกจากระบบ?', text: 'คุณต้องการออกจากระบบ TRAINUP ใช่หรือไม่', icon: 'question',
        showCancelButton: true, confirmButtonColor: '#e50914', cancelButtonColor: '#30363d', confirmButtonText: 'ใช่, ออกจากระบบ', cancelButtonText: 'ยกเลิก', background: '#161b22', color: '#fff'
    }).then((r) => { if (r.isConfirmed) window.location.href = logoutUrl; });
}
</script>
</body>
</html>