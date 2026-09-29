<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$swal_status = '';

// อัปเดตสถานะและตอบกลับสมาชิก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_report'])) {
    $report_id = intval($_POST['report_id']);
    $status = trim($_POST['status']);
    $admin_response = trim($_POST['admin_response']);

    $stmt = $pdo->prepare("UPDATE issue_reports SET status = ?, admin_response = ? WHERE report_id = ?");
    $stmt->execute([$status, $admin_response, $report_id]);
    $swal_status = 'updated';
}

// ลบรายการรายงานปัญหา
if (isset($_GET['delete'])) {
    $report_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM issue_reports WHERE report_id = ?")->execute([$report_id]);
    header("Location: reports_inbox.php?status=deleted");
    exit();
}

$reports = $pdo->query("SELECT r.*, m.fullname, m.card_code, m.phone, u.email 
                        FROM issue_reports r
                        JOIN members m ON r.member_id = m.member_id
                        JOIN users u ON m.user_id = u.user_id
                        ORDER BY FIELD(r.status, 'pending', 'in_progress', 'resolved', 'closed'), r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pending_count = 0;
foreach ($reports as $r) {
    if ($r['status'] === 'pending') $pending_count++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กล่องรับเรื่องร้องเรียนและปัญหา - TRAINUP</title>
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
        body { font-family: 'Kanit', sans-serif; background-color: var(--bg-dark); color: #c9d1d9; }
        .sidebar { width: 240px; background-color: var(--panel-bg); min-height: 100vh; position: fixed; top: 0; left: 0; border-right: 1px solid var(--border-color); z-index: 100; }
        .sidebar .brand { padding: 20px; font-family: 'Anton', sans-serif; font-size: 1.4rem; color: #fff; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border-color); }
        .sidebar .nav-link { color: #8b949e; padding: 12px 20px; font-size: 0.92rem; display: flex; align-items: center; gap: 10px; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover { color: #fff; background-color: #21262d; }
        .sidebar .nav-link.active { color: #fff; background-color: #21262d; border-left-color: var(--primary-red); font-weight: 500; }
        .main-content { margin-left: 240px; padding: 25px 35px; }

        .box-panel { background-color: var(--panel-bg); border-radius: 12px; border: 1px solid var(--border-color); }
        .table-custom { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .table-custom thead th { background-color: #0d1117; color: #8b949e; font-weight: 500; font-size: 0.84rem; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
        .table-custom tbody td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #21262d; font-size: 0.88rem; }
        .table-custom tbody tr:hover { background-color: #1c2128; }
        
        .modal-trainup { background-color: #161b22; border: 1px solid #30363d; border-radius: 14px; }
        .form-control-dark { background-color: #0d1117; border: 1px solid #30363d; color: #fff; border-radius: 8px; font-size: 0.88rem; padding: 8px 12px; }
        .form-control-dark:focus { background-color: #0d1117; border-color: #e50914; color: #fff; box-shadow: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-fire text-danger"></i><span>TRAINUP ADMIN</span></div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item"><a class="nav-link" href="../admin_dashboard.php"><i class="bi bi-grid-fill"></i> หน้าภาพรวม</a></li>
        <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people-fill"></i> สมาชิก & ตรวจสลิป</a></li>
        <li class="nav-item"><a class="nav-link" href="packages.php"><i class="bi bi-tags-fill"></i> จัดการแพ็กเกจ</a></li>
        <li class="nav-item"><a class="nav-link" href="report.php"><i class="bi bi-bar-chart-line-fill"></i> รายงานและสถิติ</a></li>
        <li class="nav-item"><a class="nav-link active" href="reports_inbox.php"><i class="bi bi-inbox-fill"></i> กล่องแจ้งปัญหา <?= $pending_count > 0 ? "<span class='badge bg-danger ms-auto'>$pending_count</span>" : '' ?></a></li>
        <li class="nav-item mt-4"><a class="nav-link text-danger" href="javascript:void(0)" onclick="confirmLogout('../logout.php')"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="text-secondary small"><i class="bi bi-house-door me-1"></i> / <span class="text-white">กล่องแจ้งปัญหา</span></div>
            <h5 class="fw-bold mb-0 mt-1 text-white">กล่องรับเรื่องร้องเรียนและรายงานปัญหาจากสมาชิก</h5>
        </div>
        <a href="../admin_dashboard.php" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 6px; font-size: 0.85rem;">กลับหน้าภาพรวม</a>
    </div>

    <!-- ตารางรายการรายงานปัญหาทั้งหมด -->
    <div class="box-panel">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-color) !important;">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-white">รายการเรื่องร้องเรียนทั้งหมด</span>
                <span class="badge bg-secondary"><?= count($reports) ?> รายการ</span>
            </div>
            <div>
                <span class="badge bg-danger bg-opacity-20 text-danger border border-danger border-opacity-50">รอดำเนินการ: <?= $pending_count ?> เรื่อง</span>
            </div>
        </div>

        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 130px;">วัน-เวลา</th>
                    <th>สมาชิก</th>
                    <th>หมวดหมู่</th>
                    <th>หัวข้อเรื่อง / รายละเอียด</th>
                    <th>สถานะ</th>
                    <th class="text-end" style="width: 140px;">การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($reports) > 0): ?>
                    <?php foreach ($reports as $rep): ?>
                    <tr>
                        <td class="text-secondary small"><?= date('d/m/Y H:i น.', strtotime($rep['created_at'])) ?></td>
                        <td>
                            <strong class="text-white"><?= htmlspecialchars($rep['fullname']) ?></strong><br>
                            <small class="text-warning font-monospace"><?= htmlspecialchars($rep['card_code'] ?? '-') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-40 text-light" style="font-weight: 400;">
                                <?= htmlspecialchars($rep['category']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-white mb-1"><?= htmlspecialchars($rep['topic']) ?></div>
                            <div class="text-secondary small text-truncate" style="max-width: 320px;"><?= htmlspecialchars($rep['description']) ?></div>
                            <?php if (!empty($rep['admin_response'])): ?>
                                <small class="text-info d-block mt-1"><i class="bi bi-reply-fill me-1"></i>ตอบแล้ว: <?= htmlspecialchars($rep['admin_response']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rep['status'] === 'resolved'): ?>
                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50">แก้ไขแล้ว</span>
                            <?php elseif ($rep['status'] === 'in_progress'): ?>
                                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50">กำลังดำเนินการ</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50">รอดำเนินการ</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-info btn-sm py-0 px-2 me-1" style="font-size: 0.8rem; border-radius: 4px;"
                                    onclick="openRespondModal(<?= $rep['report_id'] ?>, '<?= htmlspecialchars(addslashes($rep['fullname'])) ?>', '<?= htmlspecialchars(addslashes($rep['topic'])) ?>', '<?= htmlspecialchars(addslashes($rep['description'])) ?>', '<?= $rep['status'] ?>', '<?= htmlspecialchars(addslashes($rep['admin_response'] ?? '')) ?>')">
                                ตรวจสอบ
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.8rem; border-radius: 4px;" onclick="confirmDelete(<?= $rep['report_id'] ?>)">
                                ลบ
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-secondary small">ไม่มีเรื่องร้องเรียนหรือปัญหาในระบบ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal จัดการและตอบกลับปัญหา -->
<div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content modal-trainup p-3">
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
                        <select name="status" id="modalStatus" class="form-select form-control-dark" required>
                            <option value="pending">⏳ รอดำเนินการ</option>
                            <option value="in_progress">⚙️ กำลังดำเนินการแก้ไข</option>
                            <option value="resolved">✅ แก้ไขเสร็จสิ้น / เรียบร้อย</option>
                            <option value="closed">📁 ปิดเรื่อง</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-secondary">ข้อความตอบกลับสมาชิก</label>
                        <textarea name="admin_response" id="modalAdminResponse" class="form-control form-control-dark" rows="3" placeholder="ระบุผลการตรวจสอบ หรือสิ่งที่ได้ดำเนินการแก้ไขแล้ว..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="update_report" class="btn btn-sm btn-danger fw-semibold">บันทึกการดำเนินการ</button>
                </div>
            </form>
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

function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ต้องการลบรายการแจ้งปัญหานี้ใช่หรือไม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก',
        background: '#161b22',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'reports_inbox.php?delete=' + id;
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

<?php if ($swal_status === 'updated'): ?>
Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'อัปเดตสถานะและตอบกลับสมาชิกเรียบร้อยแล้ว!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
<?php endif; ?>
</script>
</body>
</html>