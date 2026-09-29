<?php
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$today = date('Y-m-d');

// ดึงตัวเลขสถิติ
$count_members = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$count_packages = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
$reports_income = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'approved'")->fetchColumn();
$reports_checkin = $pdo->query("SELECT COUNT(*) FROM checkins WHERE DATE(checkin_time) = '$today'")->fetchColumn();
$pending_slips = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

// ดึงสมาชิก 5 คนล่าสุด
$members = $pdo->query("SELECT m.*, u.username, pk.package_name 
                        FROM members m 
                        JOIN users u ON m.user_id = u.user_id 
                        LEFT JOIN packages pk ON m.package_id = pk.package_id 
                        WHERE m.status = 'active' ORDER BY m.member_id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบผู้ดูแล - TRAINUP GYM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #0d1117;
            color: #c9d1d9;
            margin: 0;
            overflow-x: hidden;
        }
        .sidebar {
            width: 240px;
            background-color: #161b22;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid #30363d;
            z-index: 100;
        }
        .sidebar .brand {
            padding: 20px;
            font-family: 'Anton', sans-serif;
            font-size: 1.4rem;
            letter-spacing: 1px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #30363d;
        }
        .sidebar .nav-link {
            color: #8b949e;
            padding: 12px 20px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            color: #f0f6fc;
            background-color: #21262d;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #21262d;
            border-left-color: #e50914;
            font-weight: 500;
        }
        .main-content {
            margin-left: 240px;
            padding: 25px 35px;
        }
        .banner-trainup {
            background: linear-gradient(135deg, #b80710 0%, #1f0406 100%);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 14px;
            color: #fff;
            padding: 22px 28px;
            margin-bottom: 24px;
        }
        .stat-card {
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 18px 20px;
            transition: border-color 0.2s;
        }
        .stat-card:hover {
            border-color: #58a6ff;
        }
        .table-custom {
            background-color: #161b22;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #30363d;
        }
        .table-custom thead th {
            background-color: #0d1117;
            color: #8b949e;
            font-weight: 500;
            font-size: 0.85rem;
            border-bottom: 1px solid #30363d;
            padding: 12px 18px;
        }
        .table-custom tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <i class="bi bi-fire text-danger"></i>
        <span>TRAINUP ADMIN</span>
    </div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item">
            <a class="nav-link active" href="admin_dashboard.php">
                <i class="bi bi-grid-fill"></i> หน้าภาพรวม
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin/members.php">
                <i class="bi bi-people-fill"></i> สมาชิก & ตรวจสลิป
                <?php if ($pending_slips > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;"><?= $pending_slips ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin/packages.php">
                <i class="bi bi-tags-fill"></i> จัดการแพ็กเกจ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin/report.php">
                <i class="bi bi-bar-chart-line-fill"></i> รายงานและสถิติ
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-secondary small">
            <i class="bi bi-house-door me-1"></i> / <span class="text-white">หน้าภาพรวมระบบ</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                <i class="bi bi-shield-fill"></i>
            </div>
            <div>
                <div class="fw-semibold text-white small lh-1">ผู้ดูแลระบบ</div>
                <small class="text-secondary" style="font-size: 0.72rem;">Administrator</small>
            </div>
        </div>
    </div>

    <!-- Banner -->
    <div class="banner-trainup d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white text-danger rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; font-size: 1.4rem;">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-white" style="font-family: 'Anton', sans-serif; letter-spacing: 1px;">TRAINUP FITNESS CENTER</h4>
                <small class="opacity-75">ศูนย์ควบคุมและบริหารจัดการระบบฟิตเนสดิจิทัล</small>
            </div>
        </div>
        <div class="d-flex gap-4 text-end">
            <div>
                <small class="opacity-75 d-block">สมาชิกพร้อมใช้งาน</small>
                <h3 class="fw-bold mb-0 text-white"><?= number_format($count_members) ?> <span class="fs-6 fw-normal opacity-75">คน</span></h3>
            </div>
            <div>
                <small class="opacity-75 d-block">เช็กอินวันนี้</small>
                <h3 class="fw-bold mb-0 text-white"><?= number_format($reports_checkin) ?> <span class="fs-6 fw-normal opacity-75">ครั้ง</span></h3>
            </div>
        </div>
    </div>

    <!-- สรุปสถิติ 4 ช่อง -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>สมาชิกที่ Active</span>
                    <i class="bi bi-person-check text-success"></i>
                </div>
                <h4 class="fw-bold text-white mb-0"><?= number_format($count_members) ?> <small class="fs-6 fw-normal text-secondary">คน</small></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>แพ็กเกจทั้งหมด</span>
                    <i class="bi bi-tags text-warning"></i>
                </div>
                <h4 class="fw-bold text-white mb-0"><?= number_format($count_packages) ?> <small class="fs-6 fw-normal text-secondary">รายการ</small></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>รายได้รวมสะสม</span>
                    <i class="bi bi-cash-stack text-danger"></i>
                </div>
                <h4 class="fw-bold text-danger mb-0">฿<?= number_format($reports_income ?? 0, 2) ?></h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>สลิปรอตรวจสอบ</span>
                    <i class="bi bi-hourglass-split text-info"></i>
                </div>
                <h4 class="fw-bold text-info mb-0"><?= number_format($pending_slips) ?> <small class="fs-6 fw-normal text-secondary">รายการ</small></h4>
            </div>
        </div>
    </div>

    <!-- รายชื่อสมาชิกที่ชำระเงินเรียบร้อย -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0 text-white"><i class="bi bi-people-fill text-danger me-1"></i> สมาชิกที่ใช้งานล่าสุด</h6>
        <a href="admin/members.php" class="text-decoration-none small text-danger fw-semibold">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="table-custom">
        <table class="table table-dark table-hover mb-0">
            <thead>
                <tr>
                    <th>ชื่อสมาชิก</th>
                    <th>รหัสบัตร</th>
                    <th>แพ็กเกจปัจจุบัน</th>
                    <th>เบอร์โทร</th>
                    <th>วันหมดอายุ</th>
                    <th class="text-end">รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($members) > 0): ?>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="fw-semibold text-white"><?= htmlspecialchars($m['fullname']) ?></td>
                        <td><span class="badge bg-secondary bg-opacity-25 text-warning border border-secondary"><?= htmlspecialchars($m['card_code']) ?></span></td>
                        <td><?= htmlspecialchars($m['package_name']) ?></td>
                        <td><?= htmlspecialchars($m['phone']) ?></td>
                        <td class="text-warning"><?= htmlspecialchars($m['expire_date']) ?></td>
                        <td class="text-end">
                            <a href="admin/members.php" class="btn btn-sm btn-outline-light rounded-pill px-3 py-0" style="font-size: 0.8rem;">ตรวจสอบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-secondary small">ยังไม่มีข้อมูลสมาชิกที่เปิดใช้งานในระบบ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>