<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT m.*, u.email, u.username FROM members m JOIN users u ON m.user_id = u.user_id WHERE m.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

$swal_status = '';

// บันทึกการส่งเรื่องร้องเรียน/แจ้งปัญหา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_report'])) {
    $topic = trim($_POST['topic']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if (!empty($topic) && !empty($description)) {
        $ins = $pdo->prepare("INSERT INTO issue_reports (member_id, topic, category, description, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $ins->execute([$member['member_id'], $topic, $category, $description]);
        $swal_status = 'sent';
    }
}

// ดึงประวัติการแจ้งปัญหาของสมาชิกคนนี้
$reports_stmt = $pdo->prepare("SELECT * FROM issue_reports WHERE member_id = ? ORDER BY created_at DESC");
$reports_stmt->execute([$member['member_id']]);
$my_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงชื่อหมวดหมู่ภาษาไทย
function getCatName($cat) {
    switch ($cat) {
        case 'equipment': return '🏋️ เครื่องเล่น / อุปกรณ์ชำรุด';
        case 'facility': return '🚿 ห้องน้ำ / สิ่งอำนวยความสะดวก';
        case 'trainer': return '🥊 ปัญหาเทรนเนอร์ / คลาส';
        case 'payment': return '💳 การชำระเงิน / แพ็กเกจ';
        case 'checkin': return '🚪 ระบบประตู / บัตรสแกน';
        default: return '💬 ข้อเสนอแนะอื่นๆ';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งปัญหาและข้อเสนอแนะ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-red: #e50914;
            --bg-dark: #0a0c10;
            --card-surface: #14171f;
            --card-border: rgba(255, 255, 255, 0.08);
        }
        body { font-family: 'Kanit', sans-serif; background-color: var(--bg-dark); color: #f1f5f9; min-height: 100vh; padding-bottom: 50px; }
        .top-action-bar { max-width: 1000px; margin: 20px auto; padding: 0 15px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back-home { background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.15); color: #e2e8f0; font-size: 0.85rem; border-radius: 30px; padding: 7px 18px; text-decoration: none; }
        .btn-back-home:hover { background: rgba(255, 255, 255, 0.15); color: #fff; }
        .glass-box { background-color: var(--card-surface); border: 1px solid var(--card-border); border-radius: 16px; padding: 25px; margin-bottom: 20px; }
        .form-dark { background-color: #0d0f14; border: 1px solid var(--card-border); color: #fff; border-radius: 10px; font-size: 0.9rem; padding: 10px 14px; }
        .form-dark:focus { background-color: #0d0f14; border-color: var(--primary-red); color: #fff; box-shadow: none; }
        
        /* ป้ายสถานะสไตล์ชัดเจน */
        .badge-status-resolved {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-inprogress {
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-pending {
            background-color: rgba(229, 9, 20, 0.15);
            color: #ff4d55 !important;
            border: 1px solid rgba(229, 9, 20, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-closed {
            background-color: rgba(148, 163, 184, 0.15);
            color: #94a3b8 !important;
            border: 1px solid rgba(148, 163, 184, 0.4);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>

<div class="top-action-bar">
    <a href="dashboard.php" class="btn-back-home"><i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก</a>
    <span class="text-secondary small">Helpdesk & Support</span>
</div>

<div class="container" style="max-width: 1000px;">
    
    <div class="mb-4">
        <h2 class="fw-bold text-white mb-1" style="font-family: 'Anton', sans-serif; letter-spacing: 1.5px;">
            <i class="bi bi-chat-square-dots text-danger me-2"></i>MEMBER HELPDESK & FEEDBACK
        </h2>
        <small class="text-secondary">แจ้งปัญหาการใช้งาน เครื่องเล่น สิ่งอำนวยความสะดวก หรือสอบถามข้อสงสัยกับเจ้าหน้าที่</small>
    </div>

    <div class="row g-4">
        <!-- ฝั่งซ้าย: ฟอร์มส่งรายงานปัญหา -->
        <div class="col-md-5">
            <div class="glass-box">
                <h5 class="fw-bold text-white mb-3"><i class="bi bi-send-plus text-danger me-2"></i>ส่งรายงานปัญหาใหม่</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small text-secondary">หมวดหมู่ปัญหา</label>
                        <select name="category" class="form-select form-dark" required>
                            <option value="equipment">🏋️ เครื่องเล่น / อุปกรณ์ชำรุด</option>
                            <option value="facility">🚿 ห้องน้ำ / สิ่งอำนวยความสะดวก</option>
                            <option value="trainer">🥊 ปัญหาเกี่ยวกับเทรนเนอร์ / คลาส</option>
                            <option value="payment">💳 การชำระเงิน / แพ็กเกจ / ใบเสร็จ</option>
                            <option value="checkin">🚪 ระบบประตู / บัตรสแกนเข้ายิม</option>
                            <option value="other">💬 ข้อเสนอแนะอื่นๆ</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-secondary">หัวข้อเรื่อง</label>
                        <input type="text" name="topic" class="form-control form-dark" placeholder="เช่น ลู่วิ่งหมายเลข 4 สายพานสะดุด" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-secondary">รายละเอียดของปัญหา</label>
                        <textarea name="description" class="form-control form-dark" rows="4" placeholder="ระบุตำแหน่ง อาการ หรือช่วงเวลาที่พบปัญหา..." required></textarea>
                    </div>

                    <button type="submit" name="send_report" class="btn btn-danger w-100 fw-semibold" style="border-radius: 10px; padding: 10px;">
                        <i class="bi bi-arrow-up-right-circle me-1"></i> ส่งรายงานปัญหา
                    </button>
                </form>
            </div>
        </div>

        <!-- ฝั่งขวา: รายการประวัติที่เคยแจ้งไว้และการตอบกลับ -->
        <div class="col-md-7">
            <div class="glass-box">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-white mb-0"><i class="bi bi-clock-history text-info me-2"></i>ประวัติการแจ้งเรื่องของคุณ</h5>
                    <span class="badge bg-secondary bg-opacity-40 text-light border border-secondary border-opacity-25"><?= count($my_reports) ?> รายการ</span>
                </div>

                <?php if (count($my_reports) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($my_reports as $rep): ?>
                        <div class="p-3 rounded-3 border border-secondary border-opacity-25" style="background-color: #0d0f14;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong class="text-white d-block fs-6"><?= htmlspecialchars($rep['topic']) ?></strong>
                                    <small class="text-secondary" style="font-size: 0.75rem;">
                                        <i class="bi bi-tag me-1 text-warning"></i><?= getCatName($rep['category']) ?> &nbsp;|&nbsp; 
                                        <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y H:i น.', strtotime($rep['created_at'])) ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($rep['status'] === 'resolved'): ?>
                                        <span class="badge-status-resolved"><i class="bi bi-check-circle-fill"></i> แก้ไขเสร็จสิ้น</span>
                                    <?php elseif ($rep['status'] === 'in_progress'): ?>
                                        <span class="badge-status-inprogress"><i class="bi bi-gear-fill"></i> กำลังแก้ไข</span>
                                    <?php elseif ($rep['status'] === 'closed'): ?>
                                        <span class="badge-status-closed"><i class="bi bi-x-circle-fill"></i> ปิดเรื่อง</span>
                                    <?php else: ?>
                                        <span class="badge-status-pending"><i class="bi bi-clock-history"></i> รอดำเนินการ</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="text-secondary small mb-2" style="white-space: pre-line;"><?= htmlspecialchars($rep['description']) ?></p>

                            <?php if (!empty($rep['admin_response'])): ?>
                                <div class="p-2 rounded-2 border border-info border-opacity-25 bg-info bg-opacity-10 small mt-2">
                                    <strong class="text-info d-block mb-1"><i class="bi bi-reply-fill me-1"></i>การตอบกลับจากผู้ดูแลระบบ:</strong>
                                    <span class="text-white"><?= nl2br(htmlspecialchars($rep['admin_response'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success opacity-50"></i>
                        ยังไม่มีประวัติการแจ้งปัญหา
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
<?php if ($swal_status === 'sent'): ?>
Swal.fire({
    icon: 'success',
    title: 'ส่งรายงานเรียบร้อยแล้ว!',
    text: 'เจ้าหน้าที่ได้รับเรื่องแล้ว และจะดำเนินการตรวจสอบโดยเร็วที่สุด',
    background: '#14171f',
    color: '#fff',
    confirmButtonColor: '#e50914'
});
<?php endif; ?>
</script>
</body>
</html>