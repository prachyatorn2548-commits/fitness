<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// 1. AJAX ดึงประวัติการซื้อแพ็กเกจ
if (isset($_GET['get_history'])) {
    $mid = intval($_GET['get_history']);
    $h_stmt = $pdo->prepare("SELECT p.*, COALESCE(pk.package_name, 'แพ็กเกจฟิตเนส') AS package_name 
                             FROM payments p 
                             LEFT JOIN packages pk ON p.package_id = pk.package_id 
                             WHERE p.member_id = ? 
                             ORDER BY p.payment_date DESC");
    $h_stmt->execute([$mid]);
    $history = $h_stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($history);
    exit();
}

// 2. บันทึกการแก้ไขข้อมูลสมาชิก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_member'])) {
    $m_id = intval($_POST['member_id']);
    $u_id = intval($_POST['user_id']);
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $pkg_id = intval($_POST['package_id']);
    $expire_date = trim($_POST['expire_date']);

    $stmt1 = $pdo->prepare("UPDATE members SET fullname = ?, phone = ?, package_id = ?, expire_date = ? WHERE member_id = ?");
    $stmt1->execute([$fullname, $phone, $pkg_id, $expire_date, $m_id]);

    $stmt2 = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
    $stmt2->execute([$email, $u_id]);

    header("Location: members.php?status=edited");
    exit();
}

// 3. อนุมัติสลิป
if (isset($_GET['approve'])) {
    $pay_id = intval($_GET['approve']);
    
    $stmt = $pdo->prepare("SELECT p.member_id, p.package_id, p.payment_type, 
                           COALESCE(pk1.duration_days, pk2.duration_days, 30) AS duration_days,
                           COALESCE(p.package_id, m.package_id) AS final_pkg_id,
                           m.expire_date, m.card_code 
                           FROM payments p 
                           JOIN members m ON p.member_id = m.member_id 
                           LEFT JOIN packages pk1 ON p.package_id = pk1.package_id 
                           LEFT JOIN packages pk2 ON m.package_id = pk2.package_id 
                           WHERE p.payment_id = ?");
    $stmt->execute([$pay_id]);
    $data = $stmt->fetch();

    if ($data) {
        $today = date('Y-m-d');
        $base = ($data['expire_date'] && $data['expire_date'] >= $today) ? $data['expire_date'] : $today;
        $expire = date('Y-m-d', strtotime("$base + {$data['duration_days']} days"));
        
        $card_code = $data['card_code'] ? $data['card_code'] : ("GYM-" . str_pad($data['member_id'], 4, '0', STR_PAD_LEFT));

        $pdo->prepare("UPDATE members SET package_id = ?, card_code = ?, start_date = ?, expire_date = ?, status = 'active' WHERE member_id = ?")
            ->execute([$data['final_pkg_id'], $card_code, $today, $expire, $data['member_id']]);

        $pdo->prepare("UPDATE payments SET package_id = ?, start_date = ?, expire_date = ?, status = 'approved' WHERE payment_id = ?")
            ->execute([$data['final_pkg_id'], $today, $expire, $pay_id]);
    }
    header("Location: members.php?status=approved");
    exit();
}

// 4. ปฏิเสธสลิป
if (isset($_GET['reject'])) {
    $pay_id = intval($_GET['reject']);
    $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE payment_id = ?")->execute([$pay_id]);
    header("Location: members.php?status=rejected");
    exit();
}

// 5. ลบสมาชิก
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);
    header("Location: members.php?status=deleted");
    exit();
}

$today = date('Y-m-d');
$search = trim($_GET['search'] ?? '');

$all_packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC")->fetchAll();

$pending_list = $pdo->query("SELECT p.payment_id, p.amount, p.slip_image, p.payment_date, p.payment_type, 
                             m.fullname, m.phone, 
                             COALESCE(pk1.package_name, pk2.package_name, 'แพ็กเกจฟิตเนส') AS package_name 
                             FROM payments p 
                             JOIN members m ON p.member_id = m.member_id 
                             LEFT JOIN packages pk1 ON p.package_id = pk1.package_id 
                             LEFT JOIN packages pk2 ON m.package_id = pk2.package_id 
                             WHERE p.status = 'pending' 
                             ORDER BY p.payment_date DESC")->fetchAll();

// ดึงสมาชิก Active พร้อมดึงเวลาเช็กอินล่าสุด
$sql = "SELECT m.*, u.username, u.email, pk.package_name,
        (SELECT COUNT(*) FROM payments WHERE member_id = m.member_id AND status = 'approved') AS total_orders,
        (SELECT slip_image FROM payments WHERE member_id = m.member_id AND status = 'approved' ORDER BY payment_date DESC LIMIT 1) AS last_slip,
        (SELECT checkin_time FROM checkins WHERE member_id = m.member_id AND DATE(checkin_time) = '$today' ORDER BY checkin_time DESC LIMIT 1) AS today_checkin,
        (SELECT checkin_time FROM checkins WHERE member_id = m.member_id ORDER BY checkin_time DESC LIMIT 1) AS last_any_checkin
        FROM members m 
        JOIN users u ON m.user_id = u.user_id 
        LEFT JOIN packages pk ON m.package_id = pk.package_id 
        WHERE m.status = 'active'";

if ($search !== '') {
    $sql .= " AND (m.fullname LIKE ? OR m.card_code LIKE ? OR m.phone LIKE ? OR u.username LIKE ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query($sql);
}
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิกและสลิป - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-dark: #0e1117;
            --panel-bg: #161b22;
            --border-color: #30363d;
            --primary-red: #e50914;
        }
        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: var(--bg-dark); 
            color: #c9d1d9; 
        }
        .sidebar { 
            width: 240px; 
            background-color: var(--panel-bg); 
            min-height: 100vh; 
            position: fixed; 
            top: 0; 
            left: 0; 
            border-right: 1px solid var(--border-color); 
            z-index: 100; 
        }
        .sidebar .brand { 
            padding: 20px; 
            font-family: 'Anton', sans-serif; 
            font-size: 1.4rem; 
            color: #fff; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border-bottom: 1px solid var(--border-color); 
        }
        .sidebar .nav-link { 
            color: #8b949e; 
            padding: 12px 20px; 
            font-size: 0.92rem;
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border-left: 3px solid transparent; 
        }
        .sidebar .nav-link:hover { color: #fff; background-color: #21262d; }
        .sidebar .nav-link.active { color: #fff; background-color: #21262d; border-left-color: var(--primary-red); font-weight: 500; }
        .main-content { margin-left: 240px; padding: 25px 35px; }
        
        .box-panel { 
            background-color: var(--panel-bg); 
            border-radius: 12px; 
            border: 1px solid var(--border-color); 
        }
        .table-standard {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }
        .table-standard thead th { 
            background-color: #0d1117; 
            color: #8b949e; 
            font-weight: 500; 
            font-size: 0.85rem; 
            border-bottom: 1px solid var(--border-color); 
            padding: 14px 16px;
        }
        .table-standard tbody td { 
            padding: 14px 16px; 
            vertical-align: middle; 
            border-bottom: 1px solid #21262d;
            color: #e6edf3;
            font-size: 0.88rem;
        }
        .table-standard tbody tr:hover {
            background-color: #1c2128;
        }
        .search-box { 
            background-color: #0d1117; 
            border: 1px solid var(--border-color); 
            color: #fff; 
            font-size: 0.85rem;
            border-radius: 6px;
            padding: 6px 12px;
        }
        .search-box:focus { 
            background-color: #0d1117; 
            border-color: #58a6ff; 
            color: #fff; 
            outline: none; 
            box-shadow: none; 
        }

        .modal-trainup {
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
        }
        .form-input-pro {
            background-color: #0d1117;
            border: 1px solid #30363d;
            color: #f0f6fc;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.88rem;
        }
        .form-input-pro:focus {
            background-color: #0d1117;
            border-color: #e50914;
            color: #fff;
            box-shadow: none;
        }
        
        .dropdown-menu-dark-custom {
            background-color: #1c2128;
            border: 1px solid #30363d;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .dropdown-menu-dark-custom .dropdown-item {
            color: #c9d1d9;
            font-size: 0.85rem;
            padding: 8px 16px;
        }
        .dropdown-menu-dark-custom .dropdown-item:hover {
            background-color: #262c36;
            color: #fff;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-fire text-danger"></i><span>TRAINUP ADMIN</span></div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item"><a class="nav-link" href="../admin_dashboard.php"><i class="bi bi-grid-fill"></i> หน้าภาพรวม</a></li>
        <li class="nav-item"><a class="nav-link active" href="members.php"><i class="bi bi-people-fill"></i> สมาชิก & ตรวจสลิป</a></li>
        <li class="nav-item"><a class="nav-link" href="packages.php"><i class="bi bi-tags-fill"></i> จัดการแพ็กเกจ</a></li>
        <li class="nav-item"><a class="nav-link" href="report.php"><i class="bi bi-bar-chart-line-fill"></i> รายงานและสถิติ</a></li>
        <li class="nav-item mt-4"><a class="nav-link text-danger" href="javascript:void(0)" onclick="confirmLogout('../logout.php')"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="text-secondary small"><i class="bi bi-house-door me-1"></i> / <span class="text-white">จัดการสมาชิก</span></div>
            <h5 class="fw-bold mb-0 mt-1 text-white">จัดการข้อมูลสมาชิกและตรวจสอบสลิป</h5>
        </div>
        <div class="d-flex gap-2">
            <!-- ปุ่มเปิดจุดสแกนเช็กอินสำหรับแอดมิน -->
            <a href="checkin.php" target="_blank" class="btn btn-danger btn-sm px-3 fw-semibold d-flex align-items-center gap-1" style="border-radius: 6px; font-size: 0.85rem;">
                <i class="bi bi-qr-code-scan"></i> เปิดจุดสแกนเช็กอิน
            </a>
            <a href="../admin_dashboard.php" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 6px; font-size: 0.85rem;">กลับหน้าภาพรวม</a>
        </div>
    </div>

    <!-- ส่วนที่ 1: รายการรอตรวจสอบสลิป -->
    <?php if (count($pending_list) > 0): ?>
    <div class="box-panel mb-4" style="border-color: #d29922;">
        <div class="p-3 bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history me-2"></i>รายการสมัคร/ต่ออายุ รอตรวจสอบสลิป</span>
            <span class="badge bg-dark text-warning"><?= count($pending_list) ?> รายการ</span>
        </div>
        <table class="table-standard">
            <thead>
                <tr>
                    <th>ชื่อสมาชิก</th>
                    <th>เบอร์โทร</th>
                    <th>ประเภท</th>
                    <th>แพ็กเกจ</th>
                    <th>ยอดโอน</th>
                    <th>สลิป</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_list as $p): ?>
                <tr>
                    <td class="fw-semibold text-white"><?= htmlspecialchars($p['fullname']) ?></td>
                    <td class="text-secondary"><?= htmlspecialchars($p['phone']) ?></td>
                    <td>
                        <span class="badge <?= $p['payment_type'] === 'renew' ? 'bg-info text-dark' : 'bg-primary' ?>" style="font-weight: 500;">
                            <?= $p['payment_type'] === 'renew' ? 'ต่ออายุ' : 'สมัครใหม่' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($p['package_name']) ?></td>
                    <td class="text-warning fw-semibold">฿<?= number_format($p['amount'], 2) ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 0.8rem; border-radius: 4px;" onclick="viewSlip('../uploads/<?= htmlspecialchars($p['slip_image']) ?>')">
                            <i class="bi bi-image"></i> ดูสลิป
                        </button>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-success btn-sm py-0 px-2 me-1" style="font-size: 0.8rem; border-radius: 4px;" onclick="confirmApprove(<?= $p['payment_id'] ?>)">อนุมัติ</button>
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.8rem; border-radius: 4px;" onclick="confirmReject(<?= $p['payment_id'] ?>)">ปฏิเสธ</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ส่วนที่ 2: ตารางสมาชิก Active พร้อมเช็กวันหมดอายุและเช็กอินวันนี้ Real-time -->
    <div class="box-panel">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-color) !important;">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-white">รายชื่อสมาชิกที่ชำระเงินเรียบร้อย</span>
                <span class="badge bg-secondary text-light" style="font-size: 0.75rem; font-weight: normal;"><?= count($members) ?> คน</span>
            </div>
            <form method="GET" style="max-width: 240px;">
                <input type="text" name="search" class="form-control search-box" placeholder="ค้นหาชื่อ, รหัส, เบอร์..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <table class="table-standard">
            <thead>
                <tr>
                    <th style="width: 110px;">รหัสบัตร</th>
                    <th>สมาชิก</th>
                    <th>เบอร์โทร</th>
                    <th>แพ็กเกจปัจจุบัน</th>
                    <th>สถานะวันหมดอายุ</th>
                    <th>เช็กอินวันนี้</th>
                    <th class="text-end" style="width: 100px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($members) > 0): ?>
                    <?php foreach ($members as $m): 
                        // คำนวณวันหมดอายุแบบ Real-time
                        $m_expired = false;
                        $days_diff = 0;
                        if (!$m['expire_date'] || $m['expire_date'] < $today) {
                            $m_expired = true;
                        } else {
                            $diff = (strtotime($m['expire_date']) - strtotime($today)) / (60 * 60 * 24);
                            $days_diff = ceil($diff);
                        }

                        // ตรวจสอบเวลาเช็กอิน
                        $display_checkin = null;
                        if (!empty($m['today_checkin'])) {
                            $display_checkin = $m['today_checkin'];
                        } elseif (!empty($m['last_any_checkin'])) {
                            if ((time() - strtotime($m['last_any_checkin'])) < (18 * 3600)) {
                                $display_checkin = $m['last_any_checkin'];
                            }
                        }
                    ?>
                    <tr>
                        <td class="text-warning" style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($m['card_code'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold text-white"><?= htmlspecialchars($m['fullname']) ?></div>
                            <small class="text-secondary" style="font-size: 0.75rem;">@<?= htmlspecialchars($m['username']) ?></small>
                        </td>
                        <td class="text-secondary"><?= htmlspecialchars($m['phone']) ?></td>
                        <td>
                            <div class="text-light"><?= htmlspecialchars($m['package_name'] ?? 'ไม่มีแพ็กเกจ') ?></div>
                            <small class="text-info" style="font-size: 0.75rem; cursor: pointer;" onclick="openHistoryModal(<?= $m['member_id'] ?>, '<?= htmlspecialchars(addslashes($m['fullname'])) ?>')">
                                <i class="bi bi-clock-history"></i> ประวัติ <?= $m['total_orders'] ?> ครั้ง
                            </small>
                        </td>

                        <!-- แสดงวันหมดอายุ -->
                        <td>
                            <div>
                                <span class="<?= $m_expired ? 'text-danger fw-bold' : 'text-light' ?>">
                                    <?= $m['expire_date'] ? date('d/m/Y', strtotime($m['expire_date'])) : '-' ?>
                                </span>
                            </div>
                            <?php if ($m_expired): ?>
                                <span class="badge bg-danger text-white mt-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i>หมดอายุแล้ว
                                </span>
                            <?php elseif ($days_diff <= 3): ?>
                                <span class="badge bg-warning text-dark mt-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-hourglass-split me-1"></i>เหลือ <?= $days_diff ?> วัน
                                </span>
                            <?php else: ?>
                                <small class="text-success" style="font-size: 0.75rem;">
                                    <i class="bi bi-check2"></i> เหลือ <?= $days_diff ?> วัน
                                </small>
                            <?php endif; ?>
                        </td>

                        <!-- แสดงสถานะเช็กอินวันนี้ พร้อมระบุเวลาเข้ายิม -->
                        <td>
                            <?php if ($display_checkin): ?>
                                <span class="text-success small" style="font-weight: 500;">
                                    <i class="bi bi-check-circle-fill me-1"></i>เข้าใช้แล้ว 
                                    <span class="text-secondary">(<?= date('H:i น.', strtotime($display_checkin)) ?>)</span>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary small">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary py-1 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: 6px; font-size: 0.8rem;">
                                    จัดการ
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark-custom">
                                    <?php if ($m['last_slip']): ?>
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0)" onclick="viewSlip('../uploads/<?= htmlspecialchars($m['last_slip']) ?>')">
                                            <i class="bi bi-receipt me-2 text-info"></i>ดูสลิปล่าสุด
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0)" onclick="openHistoryModal(<?= $m['member_id'] ?>, '<?= htmlspecialchars(addslashes($m['fullname'])) ?>')">
                                            <i class="bi bi-clock-history me-2 text-primary"></i>ประวัติการซื้อทั้งหมด
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0)" onclick="openEditModal(<?= $m['member_id'] ?>, <?= $m['user_id'] ?>, '<?= htmlspecialchars(addslashes($m['fullname'])) ?>', '<?= htmlspecialchars(addslashes($m['phone'])) ?>', '<?= htmlspecialchars(addslashes($m['email'])) ?>', <?= $m['package_id'] ?? 0 ?>, '<?= $m['expire_date'] ?>')">
                                            <i class="bi bi-pencil-square me-2 text-warning"></i>แก้ไขข้อมูล
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?= $m['user_id'] ?>)">
                                            <i class="bi bi-trash me-2"></i>ลบสมาชิก
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-secondary small">ไม่พบข้อมูลสมาชิกในระบบ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal ประวัติการซื้อแพ็กเกจ -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-trainup p-3">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                <div>
                    <h6 class="modal-title fw-bold text-white mb-0"><i class="bi bi-clock-history text-info me-2"></i>ประวัติการซื้อ/ต่ออายุแพ็กเกจ</h6>
                    <small class="text-secondary" id="historyMemberName">สมาชิก: -</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <table class="table table-dark table-hover mb-0" style="font-size: 0.88rem;">
                    <thead>
                        <tr class="text-secondary border-bottom border-secondary">
                            <th>ลำดับ</th>
                            <th>วันที่ทำรายการ</th>
                            <th>แพ็กเกจ</th>
                            <th>ประเภท</th>
                            <th>ยอดชำระ</th>
                            <th>สถานะ</th>
                            <th class="text-end">สลิป</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <tr><td colspan="7" class="text-center py-4 text-secondary">กำลังโหลดข้อมูล...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25 pt-2">
                <button type="button" class="btn btn-sm btn-secondary rounded-2" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal แก้ไขข้อมูลสมาชิก -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content modal-trainup p-2">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger bg-opacity-25 text-danger d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0">แก้ไขข้อมูลสมาชิก</h6>
                        <small class="text-secondary" style="font-size: 0.75rem;">อัปเดตรายละเอียดและสิทธิ์การใช้งาน</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="member_id" id="editMemberId">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-1"><i class="bi bi-person me-1"></i> ชื่อ - นามสกุล</label>
                        <input type="text" name="fullname" id="editFullname" class="form-control form-input-pro" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary mb-1"><i class="bi bi-telephone me-1"></i> เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" id="editPhone" class="form-control form-input-pro" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary mb-1"><i class="bi bi-envelope me-1"></i> อีเมล</label>
                            <input type="email" name="email" id="editEmail" class="form-control form-input-pro" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-1"><i class="bi bi-tag me-1"></i> แพ็กเกจที่ใช้งาน</label>
                        <select name="package_id" id="editPackageId" class="form-select form-input-pro" required>
                            <?php foreach ($all_packages as $pkg): ?>
                                <option value="<?= $pkg['package_id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?> (฿<?= number_format($pkg['price']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small text-secondary mb-1"><i class="bi bi-calendar-event me-1"></i> วันหมดอายุสมาชิก</label>
                        <input type="date" name="expire_date" id="editExpireDate" class="form-control form-input-pro" required>
                    </div>
                </div>

                <div class="modal-footer border-top border-secondary border-opacity-25 pt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">ยกเลิก</button>
                    <button type="submit" name="edit_member" class="btn btn-sm btn-danger fw-semibold" style="border-radius: 6px;">
                        <i class="bi bi-check2 me-1"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ดูสลิป -->
<div class="modal fade" id="slipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary rounded-3 p-3">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-white">หลักฐานสลิปการโอนเงิน</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="slipImagePreview" src="" alt="Slip" class="img-fluid rounded-2" style="max-height: 460px;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openHistoryModal(memberId, memberName) {
    document.getElementById('historyMemberName').innerText = 'สมาชิก: ' + memberName;
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-secondary">กำลังโหลดประวัติ...</td></tr>';
    
    new bootstrap.Modal(document.getElementById('historyModal')).show();

    fetch('members.php?get_history=' + memberId)
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-secondary">ไม่พบประวัติการซื้อแพ็กเกจ</td></tr>';
                return;
            }

            let html = '';
            data.forEach((item, index) => {
                const orderNum = data.length - index;
                const statusBadge = item.status === 'approved' 
                    ? '<span class="badge bg-success bg-opacity-25 text-success border border-success">อนุมัติแล้ว</span>'
                    : (item.status === 'pending' ? '<span class="badge bg-warning text-dark">รอตรวจ</span>' : '<span class="badge bg-danger">ไม่อนุมัติ</span>');
                
                const typeText = item.payment_type === 'renew' ? 'ต่ออายุ' : 'สมัครใหม่';
                const slipBtn = item.slip_image 
                    ? `<button class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 0.75rem;" onclick="viewSlip('../uploads/${item.slip_image}')">ดูสลิป</button>`
                    : '-';

                html += `
                    <tr>
                        <td class="text-secondary">#${orderNum}</td>
                        <td>${item.payment_date}</td>
                        <td class="fw-semibold text-white">${item.package_name}</td>
                        <td><span class="badge bg-secondary">${typeText}</span></td>
                        <td class="text-warning fw-semibold">฿${parseFloat(item.amount).toLocaleString('th-TH', {minimumFractionDigits: 2})}</td>
                        <td>${statusBadge}</td>
                        <td class="text-end">${slipBtn}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
        });
}

function openEditModal(mId, uId, name, phone, email, pkgId, expire) {
    document.getElementById('editMemberId').value = mId;
    document.getElementById('editUserId').value = uId;
    document.getElementById('editFullname').value = name;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPackageId').value = pkgId;
    document.getElementById('editExpireDate').value = expire;

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function viewSlip(src) {
    document.getElementById('slipImagePreview').src = src;
    new bootstrap.Modal(document.getElementById('slipModal')).show();
}

function confirmApprove(id) {
    Swal.fire({
        title: 'ยืนยันการอนุมัติ?',
        text: 'ระบบจะอัปเดตสิทธิ์และขยายวันหมดอายุของสมาชิกทันที',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, อนุมัติ',
        cancelButtonText: 'ยกเลิก',
        background: '#161b22',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'members.php?approve=' + id;
        }
    });
}

function confirmReject(id) {
    Swal.fire({
        title: 'ปฏิเสธรายการนี้?',
        text: 'ยอดเงินไม่ถูกต้อง หรือสลิปไม่ผ่านการตรวจสอบใช่หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ปฏิเสธ',
        cancelButtonText: 'ยกเลิก',
        background: '#161b22',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'members.php?reject=' + id;
        }
    });
}

function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ข้อมูลสมาชิกและประวัติทั้งหมดจะถูกลบถาวร!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        background: '#161b22',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'members.php?delete=' + id;
        }
    });
}

function confirmLogout(logoutUrl) {
    Swal.fire({
        title: 'ยืนยันการออกจากระบบ?',
        text: 'คุณต้องการออกจากระบบ TRAINUP ใช่หรือไม่',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e50914',
        cancelButtonColor: '#30363d',
        confirmButtonText: 'ใช่, ออกจากระบบ',
        cancelButtonText: 'ยกเลิก',
        background: '#161b22',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = logoutUrl;
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
if (status === 'edited') {
    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'อัปเดตข้อมูลสมาชิกเรียบร้อยแล้ว!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
} else if (status === 'approved') {
    Swal.fire({ icon: 'success', title: 'อนุมัติเรียบร้อย', text: 'ขยายวันหมดอายุและบันทึกสิทธิ์สำเร็จ!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
} else if (status === 'rejected') {
    Swal.fire({ icon: 'info', title: 'ปฏิเสธรายการแล้ว', text: 'ปรับสถานะเป็นไม่อนุมัติเรียบร้อย', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
} else if (status === 'deleted') {
    Swal.fire({ icon: 'success', title: 'ลบสำเร็จ', text: 'ลบข้อมูลสมาชิกออกจากระบบแล้ว!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
}
</script>
</body>
</html>