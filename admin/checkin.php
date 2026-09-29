<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

// ล็อกสิทธิ์เฉพาะ Admin เท่านั้น
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$swal_title = '';
$swal_msg = '';
$swal_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_code = trim($_POST['card_code']);

    $stmt = $pdo->prepare("SELECT m.*, pk.package_name, pk.access_type FROM members m 
                           LEFT JOIN packages pk ON m.package_id = pk.package_id 
                           WHERE m.card_code = ?");
    $stmt->execute([$card_code]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');

    if (!$member) {
        $swal_type = 'error';
        $swal_title = 'ไม่พบข้อมูลบัตร / รหัสไม่ถูกต้อง';
        $swal_msg = 'รหัสบัตร <b>' . htmlspecialchars($card_code) . '</b> ไม่มีในระบบ กรุณาตรวจสอบรูปภาพ QR Code อีกครั้ง';
    } elseif ($member['status'] !== 'active') {
        $swal_type = 'warning';
        $swal_title = 'ยังไม่เปิดใช้งาน';
        $swal_msg = 'บัญชีของสมาชิกอยู่ระหว่างรอตรวจสอบสลิป หรือยังไม่ได้ชำระเงิน';
    } elseif (!$member['expire_date'] || $member['expire_date'] < $today) {
        $swal_type = 'error';
        $swal_title = 'แพ็กเกจหมดอายุแล้ว!';
        $swal_msg = 'หมดอายุเมื่อวันที่ ' . date('d/m/Y', strtotime($member['expire_date'])) . '<br><strong class="text-warning">กรุณาแจ้งสมาชิกให้ต่อแพ็กเกจ</strong>';
    } else {
        $access_str = trim($member['access_type'] ?? '');
        $is_24h = ($access_str === '24h' || 
                   stripos($access_str, '24') !== false || 
                   stripos($access_str, 'ไม่จำกัด') !== false ||
                   stripos($access_str, 'unlimited') !== false);

        $last_chk = $pdo->prepare("SELECT checkin_time FROM checkins WHERE member_id = ? ORDER BY checkin_time DESC LIMIT 1");
        $last_chk->execute([$member['member_id']]);
        $last_record = $last_chk->fetch(PDO::FETCH_ASSOC);

        $allow_checkin = true;

        if ($last_record) {
            $last_time = strtotime($last_record['checkin_time']);
            $diff_minutes = (time() - $last_time) / 60;

            if ($is_24h) {
                if ($diff_minutes < 2) {
                    $allow_checkin = false;
                    $swal_type = 'warning';
                    $swal_title = 'ตรวจพบการสแกนซ้ำ!';
                    $swal_msg = 'บัตรนี้เพิ่งผ่านการสแกนไปเมื่อสักครู่<br><small class="text-secondary">(กรุณารอ 2 นาทีเพื่อสแกนใหม่อีกครั้ง)</small>';
                }
            } else {
                if (date('Y-m-d', $last_time) === $today) {
                    $allow_checkin = false;
                    $swal_type = 'warning';
                    $swal_title = 'สมาชิกเช็กอินไปแล้วในวันนี้!';
                    $swal_msg = 'คุณ ' . htmlspecialchars($member['fullname']) . '<br>ได้สแกนเข้าใช้บริการไปแล้วเมื่อเวลา <strong>' . date('H:i น.', $last_time) . '</strong><br><small class="text-secondary">(แพ็กเกจนี้จำกัดสิทธิ์เข้าใช้ 1 ครั้งต่อวัน)</small>';
                }
            }
        }

        if ($allow_checkin) {
            $current_datetime = date('Y-m-d H:i:s');
            $ins = $pdo->prepare("INSERT INTO checkins (member_id, checkin_time) VALUES (?, ?)");
            $ins->execute([$member['member_id'], $current_datetime]);

            $badge_info = $is_24h ? '<span class="badge bg-danger">24 HOURS PASS</span>' : '<span class="badge bg-secondary">1 DAY PASS</span>';
            $swal_type = 'success';
            $swal_title = 'เช็กอินสำเร็จ ยินดีต้อนรับ!';
            $swal_msg = 'คุณ ' . htmlspecialchars($member['fullname']) . ' ' . $badge_info . '<br>เวลาบันทึก: ' . date('H:i:s น.') . '<br>เปิดประตูให้สมาชิกเรียบร้อย';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จุดสแกนเช็กอินสมาชิก (เจ้าหน้าที่) - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ไลบรารีถอดรหัส QR จากไฟล์รูปภาพโดยตรง -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(rgba(10, 12, 16, 0.88), rgba(10, 12, 16, 0.95)), 
                        url('https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px 0;
        }
        .terminal-box {
            width: 100%;
            max-width: 500px;
            background-color: #14171d;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.85);
            padding: 32px 25px;
            text-align: center;
        }
        .clock-display {
            font-family: 'Anton', sans-serif;
            font-size: 2.5rem;
            letter-spacing: 2px;
            color: #fff;
            line-height: 1;
        }
        
        /* กล่องลากวาง/เลือกไฟล์รูป QR */
        .upload-dropzone {
            border: 2px dashed rgba(229, 9, 20, 0.5);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.02);
            padding: 24px 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            background: rgba(229, 9, 20, 0.08);
            border-color: #e50914;
        }

        .input-code {
            background-color: #0d0f12;
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: #ffcc00;
            font-size: 1.15rem;
            font-weight: 600;
            text-align: center;
            border-radius: 10px;
            padding: 11px;
            letter-spacing: 1.5px;
        }
        .input-code:focus {
            background-color: #0d0f12;
            border-color: #e50914;
            color: #ffcc00;
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.2);
        }
        .btn-checkin {
            background-color: #e50914;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 10px;
            padding: 11px;
            border: none;
            transition: all 0.2s;
        }
        .btn-checkin:hover {
            background-color: #b80710;
            color: #fff;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="p-3">

<div class="terminal-box">
    <div class="mb-3">
        <div class="text-danger fs-2 mb-1"><i class="bi bi-fire"></i></div>
        <h4 class="fw-bold text-white mb-0" style="font-family: 'Anton', sans-serif; letter-spacing: 1.5px;">STAFF CHECK-IN TERMINAL</h4>
        <small class="text-secondary" style="font-size: 0.8rem;">จุดตรวจเช็กอินสมาชิกผ่านภาพ QR Code และรหัสบัตร</small>
    </div>

    <!-- นาฬิกา Real-time -->
    <div class="p-3 bg-black bg-opacity-50 rounded-3 border border-secondary border-opacity-25 mb-3">
        <div class="clock-display" id="liveClock">00:00:00</div>
        <small class="text-secondary" id="liveDate">-</small>
    </div>

    <!-- กล่องเลือก/อัปโหลดไฟล์รูปภาพ QR Code -->
    <div class="upload-dropzone" onclick="document.getElementById('qrFileInput').click()">
        <i class="bi bi-qr-code-scan text-danger fs-1 d-block mb-2"></i>
        <strong class="text-white d-block mb-1">คลิกเพื่อเลือกรูปภาพ QR Code สมาชิก</strong>
        <span class="text-secondary small">รองรับไฟล์รูปภาพ (PNG, JPG, JPEG, WEBP)</span>
        <input type="file" id="qrFileInput" accept="image/*" class="d-none" onchange="decodeQrFromFile(event)">
    </div>

    <!-- Div ที่ซ่อนไว้สำหรับไลบรารีใช้ถอดรหัสรูป -->
    <div id="qr-reader-hidden" style="display: none;"></div>

    <!-- ฟอร์มส่งรหัสเช็กอิน -->
    <form method="POST" id="checkinForm">
        <div class="mb-3 text-start">
            <label class="form-label small text-secondary mb-1">
                <i class="bi bi-keyboard text-info me-1"></i> หรือ กรอกรหัสบัตรสมาชิก (กรณีไม่มีรูป QR)
            </label>
            <input type="text" name="card_code" id="cardCodeInput" class="form-control input-code" placeholder="เช่น GYM-0005" required autofocus autocomplete="off">
        </div>

        <button type="submit" class="btn btn-checkin w-100 mb-3">
            <i class="bi bi-box-arrow-in-right me-1"></i> ยืนยันการเข้าใช้งาน (CHECK-IN)
        </button>
    </form>

    <div class="pt-2 border-top border-secondary border-opacity-25">
        <a href="members.php" class="text-secondary text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้าจัดการสมาชิก
        </a>
    </div>
</div>

<script>
// 1. นาฬิกา
function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('liveClock').textContent = `${hours}:${minutes}:${seconds}`;

    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('liveDate').textContent = now.toLocaleDateString('th-TH', options);
}
setInterval(updateClock, 1000);
updateClock();

// 2. ระบบสแกนและถอดรหัส QR Code จากไฟล์รูปภาพ
const html5QrCode = new Html5Qrcode("qr-reader-hidden");

function decodeQrFromFile(e) {
    if (e.target.files.length === 0) return;
    const imageFile = e.target.files[0];

    Swal.fire({
        title: 'กำลังตรวจสอบ QR Code...',
        text: 'กรุณารอสักครู่',
        background: '#14171d',
        color: '#fff',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    html5QrCode.scanFile(imageFile, true)
        .then(decodedText => {
            if (decodedText) {
                // ถ้าอ่าน QR สำเร็จ ให้นำรหัสใส่ในฟอร์มแล้ว Submit ทันที
                document.getElementById('cardCodeInput').value = decodedText.trim();
                document.getElementById('checkinForm').submit();
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'ไม่พบ QR Code ในรูปภาพ',
                text: 'รูปภาพที่เลือกไม่มี QR Code หรือรูปไม่ชัดเจน กรุณาลองใหม่อีกครั้ง',
                background: '#14171d',
                color: '#fff',
                confirmButtonColor: '#e50914'
            });
            document.getElementById('qrFileInput').value = '';
        });
}

<?php if ($swal_type): ?>
Swal.fire({
    icon: '<?= $swal_type ?>',
    title: '<?= $swal_title ?>',
    html: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    confirmButtonColor: '<?= $swal_type === "success" ? "#198754" : ($swal_type === "warning" ? "#d29922" : "#e50914") ?>',
    confirmButtonText: 'ตกลง',
    timer: <?= $swal_type === 'success' ? 2500 : 'null' ?>
});
<?php endif; ?>
</script>
</body>
</html>