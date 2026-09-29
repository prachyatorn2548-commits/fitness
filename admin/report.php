<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$swal_status = '';

// จัดการตอบกลับและอัปเดตสถานะปัญหา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_issue'])) {
    $report_id = intval($_POST['report_id']);
    $status = trim($_POST['status']);
    $admin_response = trim($_POST['admin_response']);

    $stmt = $pdo->prepare("UPDATE issue_reports SET status = ?, admin_response = ? WHERE report_id = ?");
    $stmt->execute([$status, $admin_response, $report_id]);
    $swal_status = 'issue_updated';
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'finance';

// 1. ดึงข้อมูลรายรับทั้งหมด
$stmt_income = $pdo->prepare("SELECT p.*, m.fullname, m.card_code, m.phone, u.email,
                             COALESCE(pk1.package_name, pk2.package_name, 'แพ็กเกจฟิตเนส') AS package_name
                             FROM payments p
                             JOIN members m ON p.member_id = m.member_id
                             JOIN users u ON m.user_id = u.user_id
                             LEFT JOIN packages pk1 ON p.package_id = pk1.package_id
                             LEFT JOIN packages pk2 ON m.package_id = pk2.package_id
                             WHERE p.status = 'approved' AND DATE(p.payment_date) BETWEEN ? AND ?
                             ORDER BY p.payment_date DESC");
$stmt_income->execute([$start_date, $end_date]);
$incomes = $stmt_income->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = 0;
$new_members_count = 0;
$renew_count = 0;
foreach ($incomes as $inc) {
    $total_revenue += floatval($inc['amount']);
    if ($inc['payment_type'] === 'new') {
        $new_members_count++;
    } else {
        $renew_count++;
    }
}

// 2. ดึงยอดการเช็กอินเข้าใช้บริการ
$stmt_checkin = $pdo->prepare("SELECT COUNT(*) as total FROM checkins WHERE DATE(checkin_time) BETWEEN ? AND ?");
$stmt_checkin->execute([$start_date, $end_date]);
$total_checkins = $stmt_checkin->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 3. ดึงรายการแจ้งปัญหาจากสมาชิกทั้งหมด
$reports = $pdo->query("SELECT r.*, m.fullname, m.card_code, m.phone, u.email 
                        FROM issue_reports r
                        JOIN members m ON r.member_id = m.member_id
                        JOIN users u ON m.user_id = u.user_id
                        ORDER BY FIELD(r.status, 'pending', 'in_progress', 'resolved', 'closed'), r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pending_issues = 0;
foreach ($reports as $r) {
    if ($r['status'] === 'pending') $pending_issues++;
}

// ฟังก์ชันแปลงชื่อหมวดหมู่ปัญหาเป็นภาษาไทย
function getCategoryName($cat) {
    switch ($cat) {
        case 'equipment': return '🏋️ เครื่องเล่น/อุปกรณ์';
        case 'facility': return '🚿 สิ่งอำนวยความสะดวก';
        case 'trainer': return '🥊 เทรนเนอร์/คลาส';
        case 'payment': return '💳 ชำระเงิน/แพ็กเกจ';
        case 'checkin': return '🚪 บัตร/ประตูสแกน';
        default: return '💬 ข้อเสนอแนะทั่วไป';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report & Helpdesk - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-body: #080a0f;
            --surface-card: #11151f;
            --surface-elevated: #161b28;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --primary-red: #e50914;
        }

        body { 
            font-family: 'Kanit', sans-serif; 
            background-color: var(--bg-body); 
            color: #cbd5e1; 
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar { 
            width: 250px; 
            background-color: var(--surface-card); 
            min-height: 100vh; 
            position: fixed; 
            top: 0; 
            left: 0; 
            border-right: 1px solid var(--border-subtle); 
            z-index: 100; 
        }
        .sidebar .brand { 
            padding: 24px 20px; 
            font-family: 'Anton', sans-serif; 
            font-size: 1.45rem; 
            color: #fff; 
            letter-spacing: 1.5px;
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border-bottom: 1px solid var(--border-subtle); 
        }
        .sidebar .nav-link { 
            color: #8b949e; 
            padding: 13px 22px; 
            font-size: 0.92rem; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            border-left: 3px solid transparent; 
            transition: all 0.2s ease;
        }
        .sidebar .nav-link:hover { color: #fff; background-color: var(--surface-elevated); }
        .sidebar .nav-link.active { 
            color: #fff; 
            background: linear-gradient(90deg, rgba(229, 9, 20, 0.15) 0%, transparent 100%); 
            border-left-color: var(--primary-red); 
            font-weight: 500; 
        }
        
        .main-content { margin-left: 250px; padding: 30px 40px; }

        /* Tabs Pill Selector */
        .tab-nav-switcher {
            display: flex;
            gap: 8px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 25px;
            width: fit-content;
        }
        .tab-btn-pill {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .tab-btn-pill.active {
            background: var(--surface-elevated);
            color: #ffffff;
            font-weight: 600;
            border: 1px solid var(--border-subtle);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* UI Panels */
        .glass-panel {
            background-color: var(--surface-card);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        /* Filter Controls */
        .form-date-control {
            background-color: #0c0f17;
            border: 1px solid var(--border-subtle);
            color: #f1f5f9;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
        }
        .form-date-control:focus {
            background-color: #0c0f17;
            border-color: var(--primary-red);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.15);
        }

        /* Luxury Metric Cards */
        .metric-card {
            background: linear-gradient(145deg, #131724 0%, #0d1018 100%);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 22px;
            position: relative;
            transition: transform 0.2s, border-color 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.18);
        }
        
        .metric-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .icon-box-red { background: rgba(229, 9, 20, 0.15); color: #ff3b44; border: 1px solid rgba(229, 9, 20, 0.3); }
        .icon-box-cyan { background: rgba(6, 182, 212, 0.15); color: #22d3ee; border: 1px solid rgba(6, 182, 212, 0.3); }
        .icon-box-green { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .icon-box-gold { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .metric-val {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            line-height: 1.15;
        }

        /* Pro Tables */
        .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
        .table-pro thead th {
            background-color: rgba(12, 15, 23, 0.8);
            color: #8b949e;
            font-weight: 500;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .table-pro tbody td {
            padding: 15px 18px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.88rem;
            color: #e2e8f0;
        }
        .table-pro tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Badge สถานะและประเภทแบบใหม่ คมชัด ไม่มีปัญหาตัวหนังสือหาย */
        .badge-type-new {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-type-renew {
            background-color: rgba(6, 182, 212, 0.15);
            color: #38bdf8 !important;
            border: 1px solid rgba(6, 182, 212, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-status-pending {
            background-color: rgba(229, 9, 20, 0.15);
            color: #ff4d55 !important;
            border: 1px solid rgba(229, 9, 20, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-status-inprogress {
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-status-resolved {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-status-closed {
            background-color: rgba(148, 163, 184, 0.15);
            color: #94a3b8 !important;
            border: 1px solid rgba(148, 163, 184, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-receipt-action {
            background: rgba(56, 189, 248, 0.08);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-receipt-action:hover {
            background: #38bdf8;
            color: #080a0f;
            font-weight: 600;
        }

        /* Modal Theme */
        .modal-dark-surface {
            background-color: #121620;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.8);
        }

        /* Print Settings */
        @media print {
            .sidebar, .main-topbar, .tab-nav-switcher, .filter-wrapper, .screen-metrics, .btn-print-group, .action-col, footer, .screen-table-panel, .screen-issue-panel {
                display: none !important;
            }
            body, .main-content {
                background-color: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            #accountingPrintSheet {
                display: block !important;
                font-family: 'Sarabun', sans-serif !important;
                color: #0f172a !important;
                padding: 15mm 20mm !important;
            }
        }
        #accountingPrintSheet { display: none; }

        .print-doc-header { font-family: 'Anton', sans-serif; font-size: 2rem; letter-spacing: 1px; color: #b91c1c; }
        .print-doc-table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px; }
        .print-doc-table th { background-color: #f1f5f9; color: #1e293b; font-weight: 600; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.85rem; }
        .print-doc-table td { border: 1px solid #e2e8f0; padding: 9px 12px; font-size: 0.85rem; color: #334155; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-fire text-danger"></i><span>TRAINUP ADMIN</span></div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item"><a class="nav-link" href="../admin_dashboard.php"><i class="bi bi-grid-fill"></i> หน้าภาพรวม</a></li>
        <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people-fill"></i> สมาชิก & ตรวจสลิป</a></li>
        <li class="nav-item"><a class="nav-link" href="packages.php"><i class="bi bi-tags-fill"></i> จัดการแพ็กเกจ</a></li>
        <li class="nav-item"><a class="nav-link active" href="report.php"><i class="bi bi-bar-chart-line-fill"></i> รายงานและสถิติ</a></li>
        <li class="nav-item mt-4"><a class="nav-link text-danger" href="javascript:void(0)" onclick="confirmLogout('../logout.php')"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
    </ul>
</div>

<div class="main-content">
    
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 main-topbar">
        <div>
            <div class="text-secondary small mb-1"><i class="bi bi-house-door me-1"></i> / <span class="text-white">ศูนย์รายงาน & ช่วยเหลือ</span></div>
            <h4 class="fw-bold mb-0 text-white" style="font-family: 'Anton', sans-serif; letter-spacing: 0.8px;">FINANCIAL REPORT & HELPDESK INBOX</h4>
        </div>
        <div class="d-flex gap-2 btn-print-group">
            <button type="button" onclick="window.print()" class="btn btn-danger btn-sm px-3 fw-semibold shadow-sm d-flex align-items-center gap-2" style="border-radius: 8px;">
                <i class="bi bi-printer-fill"></i> พิมพ์ใบรายงานสรุปทางบัญชี (รวม)
            </button>
            <a href="../admin_dashboard.php" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 8px; font-size: 0.85rem;">กลับหน้าภาพรวม</a>
        </div>
    </div>

    <!-- Tab Switcher -->
    <div class="tab-nav-switcher">
        <a href="report.php?tab=finance&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="tab-btn-pill <?= $active_tab === 'finance' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack text-warning"></i> รายงานรายรับ & สถิติ
        </a>
        <a href="report.php?tab=inbox" class="tab-btn-pill <?= $active_tab === 'inbox' ? 'active' : '' ?>">
            <i class="bi bi-chat-square-dots-fill text-danger"></i> กล่องรับแจ้งปัญหาจากสมาชิก
            <?php if ($pending_issues > 0): ?>
                <span class="badge bg-danger rounded-pill px-2" style="font-size: 0.72rem;"><?= $pending_issues ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($active_tab === 'finance'): ?>
    <!-- ================= TAB 1: รายงานการเงินและสถิติ ================= -->

        <!-- Filter Form -->
        <div class="glass-panel p-3 mb-4 filter-wrapper">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="finance">
                <div class="col-md-4">
                    <label class="form-label small text-secondary mb-1"><i class="bi bi-calendar-event me-1"></i> ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control form-date-control" value="<?= htmlspecialchars($start_date) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-secondary mb-1"><i class="bi bi-calendar-check me-1"></i> ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control form-date-control" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-secondary btn-sm w-100 fw-semibold" style="background-color: #1e2433; border-color: #30363d; height: 42px; border-radius: 10px;">
                        <i class="bi bi-funnel-fill text-danger me-1"></i> ประมวลผลช่วงเวลาที่เลือก
                    </button>
                </div>
            </form>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4 screen-metrics">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary small fw-medium">รายได้รวมช่วงนี้</span>
                        <div class="metric-icon-wrapper icon-box-red"><i class="bi bi-wallet2"></i></div>
                    </div>
                    <div class="metric-val text-white">฿<?= number_format($total_revenue, 2) ?></div>
                    <small class="text-secondary" style="font-size: 0.78rem;">ทั้งหมด <?= count($incomes) ?> รายการอนุมัติ</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary small fw-medium">การสแกนเข้าใช้บริการ</span>
                        <div class="metric-icon-wrapper icon-box-cyan"><i class="bi bi-person-walking"></i></div>
                    </div>
                    <div class="metric-val text-white"><?= number_format($total_checkins) ?> <span class="fs-6 text-secondary fw-normal">ครั้ง</span></div>
                    <small class="text-secondary" style="font-size: 0.78rem;">บันทึกการเข้ายิมแบบเรียลไทม์</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary small fw-medium">สมัครสมาชิกใหม่</span>
                        <div class="metric-icon-wrapper icon-box-green"><i class="bi bi-person-check-fill"></i></div>
                    </div>
                    <div class="metric-val text-white"><?= number_format($new_members_count) ?> <span class="fs-6 text-secondary fw-normal">คน</span></div>
                    <small class="text-secondary" style="font-size: 0.78rem;">สมาชิกลงทะเบียนแพ็กเกจใหม่</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-secondary small fw-medium">ต่ออายุสมาชิก</span>
                        <div class="metric-icon-wrapper icon-box-gold"><i class="bi bi-arrow-repeat"></i></div>
                    </div>
                    <div class="metric-val text-white"><?= number_format($renew_count) ?> <span class="fs-6 text-secondary fw-normal">ครั้ง</span></div>
                    <small class="text-secondary" style="font-size: 0.78rem;">การต่ออายุแพ็กเกจต่อเนื่อง</small>
                </div>
            </div>
        </div>

        <!-- Income Records Table -->
        <div class="glass-panel screen-table-panel">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-subtle) !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-white"><i class="bi bi-shield-check text-success me-2"></i>ประวัติรายการชำระเงินที่อนุมัติแล้ว</span>
                    <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary border-opacity-25"><?= count($incomes) ?> รายการ</span>
                </div>
                <small class="text-secondary" style="font-size: 0.8rem;">เรียงตามเวลาล่าสุด</small>
            </div>

            <table class="table-pro">
                <thead>
                    <tr>
                        <th>วัน-เวลาที่ทำรายการ</th>
                        <th>รหัสบัตร</th>
                        <th>ชื่อสมาชิก</th>
                        <th>แพ็กเกจ / คอร์ส</th>
                        <th>ประเภท</th>
                        <th>ยอดชำระสุทธิ</th>
                        <th class="text-end action-col">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($incomes) > 0): ?>
                        <?php foreach ($incomes as $inc): ?>
                        <tr>
                            <td class="text-secondary" style="font-size: 0.84rem;">
                                <i class="bi bi-clock me-1 text-secondary opacity-50"></i><?= date('d/m/Y H:i น.', strtotime($inc['payment_date'])) ?>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-warning border-opacity-25 text-warning font-monospace px-2 py-1">
                                    <?= htmlspecialchars($inc['card_code'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="fw-semibold text-white"><?= htmlspecialchars($inc['fullname']) ?></td>
                            <td><?= htmlspecialchars($inc['package_name']) ?></td>
                            
                            <td>
                                <?php if ($inc['payment_type'] === 'renew'): ?>
                                    <span class="badge-type-renew">
                                        <i class="bi bi-arrow-repeat"></i> ต่ออายุ
                                    </span>
                                <?php else: ?>
                                    <span class="badge-type-new">
                                        <i class="bi bi-check2-circle"></i> สมัครใหม่
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="fw-bold font-monospace" style="color: #fbbf24 !important; font-size: 0.95rem;">
                                ฿<?= number_format($inc['amount'], 2) ?>
                            </td>
                            <td class="text-end action-col">
                                <a href="print_receipt.php?payment_id=<?= $inc['payment_id'] ?>" target="_blank" class="btn-receipt-action">
                                    <i class="bi bi-printer"></i> พิมพ์ใบเสร็จเดี่ยว
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                ไม่พบข้อมูลรายรับในช่วงเวลาที่ระบุ
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
    <!-- ================= TAB 2: กล่องรับแจ้งปัญหาจากสมาชิก ================= -->

        <div class="glass-panel screen-issue-panel">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-subtle) !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold text-white"><i class="bi bi-inbox-fill text-danger me-2"></i>รายการแจ้งปัญหาและข้อเสนอแนะจากสมาชิก</span>
                    <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary border-opacity-25"><?= count($reports) ?> รายการ</span>
                </div>
                <div>
                    <span class="badge-status-pending">
                        <i class="bi bi-hourglass-split"></i> รอดำเนินการ: <?= $pending_issues ?> เรื่อง
                    </span>
                </div>
            </div>

            <table class="table-pro">
                <thead>
                    <tr>
                        <th style="width: 140px;">วัน-เวลา</th>
                        <th>สมาชิก</th>
                        <th>หมวดหมู่</th>
                        <th>หัวข้อเรื่อง / รายละเอียดปัญหา</th>
                        <th style="width: 150px;">สถานะ</th>
                        <th class="text-end" style="width: 120px;">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reports) > 0): ?>
                        <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td class="text-secondary small">
                                <i class="bi bi-clock me-1 opacity-50"></i><?= date('d/m/Y H:i น.', strtotime($rep['created_at'])) ?>
                            </td>
                            <td>
                                <strong class="text-white d-block"><?= htmlspecialchars($rep['fullname']) ?></strong>
                                <small class="text-warning font-monospace"><?= htmlspecialchars($rep['card_code'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary border-opacity-50 text-light px-2 py-1" style="font-size: 0.78rem;">
                                    <?= getCategoryName($rep['category']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-white mb-1"><?= htmlspecialchars($rep['topic']) ?></div>
                                <div class="text-secondary small text-truncate" style="max-width: 320px;"><?= htmlspecialchars($rep['description']) ?></div>
                                <?php if (!empty($rep['admin_response'])): ?>
                                    <small class="text-info d-block mt-1"><i class="bi bi-reply-fill me-1"></i>ตอบแล้ว: <?= htmlspecialchars($rep['admin_response']) ?></small>
                                <?php endif; ?>
                            </td>
                            
                            <!-- คอลัมน์สถานะพร้อมข้อความและไอคอนชัดเจน -->
                            <td>
                                <?php if ($rep['status'] === 'resolved'): ?>
                                    <span class="badge-status-resolved">
                                        <i class="bi bi-check-circle-fill"></i> แก้ไขเสร็จสิ้น
                                    </span>
                                <?php elseif ($rep['status'] === 'in_progress'): ?>
                                    <span class="badge-status-inprogress">
                                        <i class="bi bi-gear-fill"></i> กำลังแก้ไข
                                    </span>
                                <?php elseif ($rep['status'] === 'closed'): ?>
                                    <span class="badge-status-closed">
                                        <i class="bi bi-x-circle-fill"></i> ปิดเรื่อง
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status-pending">
                                        <i class="bi bi-clock-history"></i> รอดำเนินการ
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <button type="button" class="btn btn-outline-info btn-sm py-1 px-3 fw-semibold" style="font-size: 0.8rem; border-radius: 6px;"
                                        onclick="openRespondModal(<?= $rep['report_id'] ?>, '<?= htmlspecialchars(addslashes($rep['fullname'])) ?>', '<?= htmlspecialchars(addslashes($rep['topic'])) ?>', '<?= htmlspecialchars(addslashes($rep['description'])) ?>', '<?= $rep['status'] ?>', '<?= htmlspecialchars(addslashes($rep['admin_response'] ?? '')) ?>')">
                                    <i class="bi bi-pencil-square me-1"></i> จัดการ
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success opacity-50"></i>
                                ไม่มีรายการแจ้งปัญหาในระบบ
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<!-- Modal จัดการเรื่องร้องเรียนและตอบกลับ -->
<div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content modal-dark-surface p-3">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-2">
                <h6 class="modal-title fw-bold text-white"><i class="bi bi-chat-square-quote text-info me-2"></i>จัดการเรื่องร้องเรียน</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="report_id" id="modalReportId">
                <div class="modal-body py-3">
                    <div class="p-3 bg-black bg-opacity-40 rounded-3 border border-secondary border-opacity-25 mb-3 small">
                        <div class="text-secondary mb-1">สมาชิกผู้แจ้ง: <strong class="text-white" id="modalMemberName">-</strong></div>
                        <div class="text-white fw-bold mb-1" id="modalTopic">-</div>
                        <div class="text-secondary" id="modalDesc" style="white-space: pre-line;">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-secondary">ปรับสถานะการดำเนินงาน</label>
                        <select name="status" id="modalStatus" class="form-select form-date-control" required>
                            <option value="pending">⏳ รอดำเนินการ</option>
                            <option value="in_progress">⚙️ กำลังดำเนินการแก้ไข</option>
                            <option value="resolved">✅ แก้ไขเสร็จสิ้น / เรียบร้อย</option>
                            <option value="closed">📁 ปิดเรื่อง</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-secondary">ข้อความตอบกลับสมาชิก</label>
                        <textarea name="admin_response" id="modalAdminResponse" class="form-control form-date-control" rows="3" placeholder="ระบุผลการตรวจสอบ หรือสิ่งที่ได้ดำเนินการแก้ไขแล้ว..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="update_issue" class="btn btn-sm btn-danger fw-semibold">บันทึกการดำเนินการ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= Printable Accounting Report ================= -->
<div id="accountingPrintSheet">
    <div class="d-flex justify-content-between align-items-start border-bottom border-2 pb-3 mb-3">
        <div>
            <div class="print-doc-header"><i class="bi bi-fire"></i> TRAINUP FITNESS CLUB</div>
            <div style="font-size: 0.85rem; color: #475569; line-height: 1.45;">
                <strong>บริษัท เทรนอัพ ฟิตเนส คลับ จำกัด (สำนักงานใหญ่)</strong><br>
                เลขที่ 123 อาคารสปอร์ตคอมเพล็กซ์ ถ.สุขุมวิท กรุงเทพฯ 10110<br>
                เลขประจำตัวผู้เสียภาษี: 0-1055-67890-12-3 | โทร. 02-123-4567
            </div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold mb-1" style="color: #0f172a;">รายงานสรุปรายรับทางบัญชี</h5>
            <div style="font-size: 0.82rem; color: #475569;">
                รอบวันที่: <strong><?= date('d/m/Y', strtotime($start_date)) ?></strong> ถึง <strong><?= date('d/m/Y', strtotime($end_date)) ?></strong><br>
                วันที่พิมพ์ออกรายงาน: <?= date('d/m/Y H:i น.') ?>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 15px; background: #f8fafc; padding: 12px 18px; border-radius: 6px; border: 1px solid #e2e8f0;">
        <div class="row">
            <div class="col-6">
                <span style="font-size: 0.88rem; color: #475569;">จำนวนรายการอนุมัติทั้งหมด:</span>
                <strong style="font-size: 0.95rem; color: #0f172a; margin-left: 6px;"><?= count($incomes) ?> รายการ</strong>
            </div>
            <div class="col-6 text-end">
                <span style="font-size: 0.88rem; color: #475569;">ยอดรายรับสุทธิรวมทั้งสิ้น:</span>
                <strong style="font-size: 1.2rem; color: #b91c1c; margin-left: 6px;">฿<?= number_format($total_revenue, 2) ?> บาท</strong>
            </div>
        </div>
    </div>

    <table class="print-doc-table">
        <thead>
            <tr>
                <th style="width: 50px; text-align: center;">ลำดับ</th>
                <th style="width: 130px;">วัน-เวลา</th>
                <th style="width: 90px;">รหัสสมาชิก</th>
                <th>ชื่อ-นามสกุลสมาชิก</th>
                <th>รายการแพ็กเกจ</th>
                <th style="width: 90px; text-align: center;">ประเภท</th>
                <th style="width: 120px; text-align: right;">จำนวนเงิน (บาท)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($incomes) > 0): ?>
                <?php foreach ($incomes as $idx => $row): ?>
                <tr>
                    <td style="text-align: center;"><?= $idx + 1 ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['payment_date'])) ?></td>
                    <td style="font-family: monospace; font-weight: bold;"><?= htmlspecialchars($row['card_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><?= htmlspecialchars($row['package_name']) ?></td>
                    <td style="text-align: center;"><?= $row['payment_type'] === 'renew' ? 'ต่ออายุ' : 'สมัครใหม่' ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($row['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <td colspan="6" style="text-align: right; font-size: 0.9rem;">ยอดรวมสุทธิทั้งสิ้น (Total Revenue)</td>
                    <td style="text-align: right; font-size: 1rem; color: #b91c1c;">฿<?= number_format($total_revenue, 2) ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 25px;">ไม่มีข้อมูลรายการชำระเงินในช่วงเวลาดังกล่าว</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 220px;">
            <div style="border-bottom: 1px dotted #94a3b8; padding-bottom: 40px;"></div>
            <div style="font-size: 0.85rem; margin-top: 8px;">( .................................................... )</div>
            <div style="font-size: 0.8rem; color: #64748b;">เจ้าหน้าที่ผู้จัดทำรายงาน</div>
        </div>
        <div style="text-align: center; width: 220px;">
            <div style="border-bottom: 1px dotted #94a3b8; padding-bottom: 40px;"></div>
            <div style="font-size: 0.85rem; margin-top: 8px;">( .................................................... )</div>
            <div style="font-size: 0.8rem; color: #64748b;">ผู้จัดการฝ่ายบัญชีและการเงิน</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openRespondModal(id, name, topic, desc, status, response) {
    document.getElementById('modalReportId').value = id;
    document.getElementById('modalMemberName').innerText = name;
    document.getElementById('modalTopic').innerText = topic;
    document.getElementById('modalDesc').innerText = desc;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalAdminResponse').value = response;
    new bootstrap.Modal(document.getElementById('respondModal')).show();
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
        background: '#121620',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = logoutUrl;
        }
    });
}

<?php if ($swal_status === 'issue_updated'): ?>
Swal.fire({
    icon: 'success',
    title: 'สำเร็จ',
    text: 'อัปเดตสถานะและบันทึกการตอบกลับเรียบร้อยแล้ว!',
    timer: 1800,
    showConfirmButton: false,
    background: '#121620',
    color: '#fff'
});
<?php endif; ?>
</script>
</body>
</html>