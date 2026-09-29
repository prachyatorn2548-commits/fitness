<?php
date_default_timezone_set('Asia/Bangkok');
require 'db.php';

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
        $swal_title = 'ไม่พบข้อมูลบัตร';
        $swal_msg = 'รหัสบัตรสมาชิกไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
    } elseif ($member['status'] !== 'active') {
        $swal_type = 'warning';
        $swal_title = 'ยังไม่เปิดใช้งาน';
        $swal_msg = 'บัญชีของคุณอยู่ระหว่างรอตรวจสอบสลิป หรือยังไม่ได้ชำระเงิน';
    } elseif (!$member['expire_date'] || $member['expire_date'] < $today) {
        $swal_type = 'error';
        $swal_title = 'แพ็กเกจของคุณหมดอายุแล้ว!';
        $swal_msg = 'หมดอายุเมื่อวันที่ ' . date('d/m/Y', strtotime($member['expire_date'])) . '<br><strong class="text-warning">กรุณาต่อแพ็กเกจก่อนเข้าใช้บริการ</strong>';
    } else {
        // ตรวจสอบสิทธิ์ 24 ชม. / Unlimited ทั้งแบบภาษาไทยและภาษาอังกฤษ
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
                // แพ็กเกจ 24 ชม.: เข้าได้ไม่จำกัดรอบ แต่เว้น Cooldown 2 นาทีป้องกันการสแกนซ้ำทันที
                if ($diff_minutes < 2) {
                    $allow_checkin = false;
                    $swal_type = 'warning';
                    $swal_title = 'ตรวจพบการสแกนซ้ำ!';
                    $swal_msg = 'บัตรของคุณเพิ่งผ่านการสแกนเมื่อไม่กี่นาทีที่ผ่านมา<br><small class="text-secondary">(กรุณารอ 2 นาทีเพื่อสแกนเข้าใหม่อีกครั้ง)</small>';
                }
            } else {
                // แพ็กเกจจำกัด 1 ครั้ง/วัน
                if (date('Y-m-d', $last_time) === $today) {
                    $allow_checkin = false;
                    $swal_type = 'warning';
                    $swal_title = 'คุณได้เช็กอินไปแล้วในวันนี้!';
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
            $swal_msg = 'คุณ ' . htmlspecialchars($member['fullname']) . ' ' . $badge_info . '<br>เวลาเช็กอิน: ' . date('H:i:s น.') . '<br>ขอให้มีความสุขกับการออกกำลังกาย';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จุดสแกนเช็กอินเข้าใช้บริการ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(rgba(10, 12, 16, 0.85), rgba(10, 12, 16, 0.92)), 
                        url('https://images.unsplash.com/photo-1540497077202-7c8a3999166f?q=80&w=1470&auto=format&fit=crop') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .terminal-box {
            width: 100%;
            max-width: 500px;
            background-color: #14171d;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.7);
            padding: 35px 28px;
            text-align: center;
        }
        .clock-display {
            font-family: 'Anton', sans-serif;
            font-size: 2.6rem;
            letter-spacing: 2px;
            color: #fff;
            line-height: 1;
        }
        .input-code {
            background-color: #0d0f12;
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: #ffcc00;
            font-size: 1.15rem;
            font-weight: 600;
            text-align: center;
            border-radius: 10px;
            padding: 12px;
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
            padding: 12px;
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
        <h4 class="fw-bold text-white mb-0" style="font-family: 'Anton', sans-serif; letter-spacing: 1.5px;">TRAINUP ACCESS POINT</h4>
        <small class="text-secondary" style="font-size: 0.8rem;">ระบบสแกนบัตรผ่านประตูอัตโนมัติ</small>
    </div>

    <div class="p-3 bg-black bg-opacity-50 rounded-3 border border-secondary border-opacity-25 mb-4">
        <div class="clock-display" id="liveClock">00:00:00</div>
        <small class="text-secondary" id="liveDate">-</small>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small text-secondary mb-2"><i class="bi bi-qr-code-scan text-warning me-1"></i> สแกน QR Code หรือ กรอกรหัสบัตรสมาชิก</label>
            <input type="text" name="card_code" class="form-control input-code" placeholder="เช่น GYM-0005" required autofocus autocomplete="off">
        </div>

        <button type="submit" class="btn btn-checkin w-100 mb-3">
            <i class="bi bi-box-arrow-in-right me-1"></i> ยืนยันการเข้าใช้งาน (CHECK-IN)
        </button>
    </form>

    <div class="pt-2 border-top border-secondary border-opacity-25">
        <a href="index.php" class="text-secondary text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้าเข้าสู่ระบบ
        </a>
    </div>
</div>

<script>
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

<?php if ($swal_type): ?>
Swal.fire({
    icon: '<?= $swal_type ?>',
    title: '<?= $swal_title ?>',
    html: '<?= $swal_msg ?>',
    background: '#14171d',
    color: '#fff',
    confirmButtonColor: '<?= $swal_type === "success" ? "#198754" : ($swal_type === "warning" ? "#d29922" : "#e50914") ?>',
    confirmButtonText: 'ตกลง',
    timer: <?= $swal_type === 'success' ? 3000 : 'null' ?>
});
<?php endif; ?>
</script>
</body>
</html>