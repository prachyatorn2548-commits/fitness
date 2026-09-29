<?php
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$swal_status = '';

// บันทึกการแก้ไขข้อมูลส่วนตัวของสมาชิก
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

// ข้อมูลสมาชิก & แพ็กเกจ
$stmt = $pdo->prepare("SELECT m.*, u.email, u.username, p.package_name, p.duration_days, p.price, p.details 
                       FROM members m 
                       JOIN users u ON m.user_id = u.user_id 
                       LEFT JOIN packages p ON m.package_id = p.package_id 
                       WHERE m.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบวันหมดอายุ
$today = date('Y-m-d');
$is_expired = false;
$days_left = 0;

if (!$member['expire_date'] || $member['expire_date'] <= $today) {
    $is_expired = true;
    $days_left = 0;
} else {
    $diff = (strtotime($member['expire_date']) - strtotime($today)) / (60 * 60 * 24);
    $days_left = ceil($diff);
}

// ตรวจสอบสถานะการเข้าใช้งานวันนี้
$today_chk_stmt = $pdo->prepare("SELECT checkin_time FROM checkins WHERE member_id = ? AND DATE(checkin_time) = ? ORDER BY checkin_time DESC LIMIT 1");
$today_chk_stmt->execute([$member['member_id'], $today]);
$today_checkin = $today_chk_stmt->fetch(PDO::FETCH_ASSOC);

// ดึงประวัติการเข้าใช้งาน 5 ครั้งล่าสุด
$history_chk_stmt = $pdo->prepare("SELECT checkin_time FROM checkins WHERE member_id = ? ORDER BY checkin_time DESC LIMIT 5");
$history_chk_stmt->execute([$member['member_id']]);
$checkin_history = $history_chk_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRAINUP - Unleash Your Power</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-red: #e50914;
            --primary-red-hover: #ff1e27;
            --bg-dark: #0a0c10;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hero-poster-container {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: 
                radial-gradient(circle at 50% 50%, rgba(229, 9, 20, 0.15) 0%, transparent 65%),
                linear-gradient(180deg, rgba(10, 12, 16, 0.4) 0%, rgba(10, 12, 16, 0.7) 65%, #0a0c10 100%),
                url('https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1770&auto=format&fit=crop') center center / cover no-repeat;
        }

        .top-navbar-floating {
            background: rgba(18, 22, 30, 0.75);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            margin: 18px auto 0 auto;
            max-width: 1120px;
            padding: 9px 24px;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            font-family: 'Anton', sans-serif;
            letter-spacing: 2px;
            font-size: 1.45rem;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-center-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-center-links a {
            color: #cbd5e1;
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .nav-center-links a:hover, .nav-center-links a.active { color: #fff; }

        .user-profile-badge {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 5px 12px;
            color: #e2e8f0;
            font-size: 0.82rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .user-profile-badge:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .btn-card-access {
            background: #ffffff;
            color: #0a0c10;
            font-weight: 700;
            font-size: 0.82rem;
            border-radius: 30px;
            padding: 7px 18px;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-card-access:hover { background: #e2e8f0; transform: translateY(-1px); }

        .btn-logout-circle {
            background: rgba(229, 9, 20, 0.9);
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-logout-circle:hover { background: #ff1e27; color: #fff; }

        .poster-center-stage {
            position: relative;
            z-index: 10;
            margin: auto 0;
            padding: 40px 20px;
            text-align: center;
        }

        .giant-headline-back {
            font-family: 'Anton', sans-serif;
            font-size: clamp(4.5rem, 16vw, 15rem);
            line-height: 0.85;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.08);
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .giant-headline-front {
            font-family: 'Anton', sans-serif;
            font-size: clamp(3.2rem, 10vw, 8.5rem);
            line-height: 0.95;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #ffffff;
            position: relative;
            z-index: 5;
            text-shadow: 0 10px 40px rgba(0, 0, 0, 0.9);
        }
        .giant-headline-front span { color: var(--primary-red); }

        .poster-sub-desc {
            max-width: 500px;
            margin: 15px auto 25px auto;
            color: #cbd5e1;
            font-size: 0.95rem;
            font-weight: 300;
            position: relative;
            z-index: 5;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }

        .btn-mingle-cta {
            background: var(--primary-red);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 30px;
            padding: 12px 30px;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.25s;
            position: relative;
            z-index: 5;
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
        }
        .btn-mingle-cta:hover { background: var(--primary-red-hover); color: #fff; transform: translateY(-2px); }

        .modal-clean {
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }
        .form-input-dark {
            background-color: #0d1117;
            border: 1px solid #30363d;
            color: #f0f6fc;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.88rem;
        }
        .form-input-dark:focus {
            background-color: #0d1117;
            border-color: #e50914;
            color: #fff;
            box-shadow: none;
        }
    </style>
</head>
<body>

<div class="hero-poster-container">
    
    <header class="top-navbar-floating container-fluid">
        <a class="brand-logo" href="dashboard.php">
            <i class="bi bi-fire text-danger"></i> TRAINUP
        </a>
        
        <nav class="nav-center-links d-none d-md-flex">
            <a href="dashboard.php" class="active">หน้าหลัก</a>
            <a href="packages_view.php">แพ็กเกจฟิตเนส</a>
            <a href="payment.php">ใบเสร็จ</a>
            <a href="report_issue.php"><i class="bi bi-chat-square-dots text-danger"></i> แจ้งปัญหา</a>
        </nav>

        <div class="d-flex align-items-center gap-2">
            <div class="user-profile-badge" onclick="openProfileModal()" title="คลิกเพื่อแก้ไขข้อมูลส่วนตัว">
                <i class="bi bi-person-circle text-secondary"></i>
                <span><?= htmlspecialchars($member['fullname'] ?? '') ?></span>
                <i class="bi bi-pencil-square text-secondary ms-1" style="font-size: 0.75rem;"></i>
            </div>
            
            <?php if ($member && $member['status'] === 'active'): ?>
            <button class="btn-card-access" onclick="openPassModal()">
                <i class="bi bi-qr-code"></i> บัตรสมาชิก
            </button>
            <?php endif; ?>

            <button type="button" class="btn-logout-circle" onclick="confirmLogout('../logout.php')" title="ออกจากระบบ">
                <i class="bi bi-box-arrow-right" style="font-size: 0.85rem;"></i>
            </button>
        </div>
    </header>

    <main class="poster-center-stage">
        <div class="giant-headline-back">TRAINUP</div>
        <div class="giant-headline-front">
            STRONGER<br>
            <span>EVERY DAY</span>
        </div>
        <p class="poster-sub-desc">
            Your journey to peak fitness starts here — build strength, improve endurance, and feel your absolute best.
        </p>
        <a href="packages_view.php" class="btn-mingle-cta">
            EXPLORE MEMBERSHIPS <i class="bi bi-arrow-right"></i>
        </a>
    </main>

    <footer class="py-3 text-center text-secondary" style="font-size: 0.78rem; position: relative; z-index: 10;">
        © 2026 TRAINUP Fitness Club. All Rights Reserved.
    </footer>

</div>

<!-- Modal แก้ไขข้อมูลส่วนตัว -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content modal-clean text-white p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary border-opacity-25 pb-2">
                <div class="small fw-semibold text-warning">
                    <i class="bi bi-person-gear me-1"></i> แก้ไขข้อมูลส่วนตัว
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
            </div>

            <form method="POST">
                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" class="form-control form-input-dark text-secondary" value="<?= htmlspecialchars($member['username'] ?? '') ?>" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">ชื่อ - นามสกุล</label>
                    <input type="text" name="fullname" class="form-control form-input-dark" value="<?= htmlspecialchars($member['fullname'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control form-input-dark" value="<?= htmlspecialchars($member['phone'] ?? '') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-secondary mb-1">อีเมล (E-mail)</label>
                    <input type="email" name="email" class="form-control form-input-dark" value="<?= htmlspecialchars($member['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary mb-1">รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
                    <input type="password" name="new_password" class="form-control form-input-dark" placeholder="กรอกรหัสผ่านใหม่">
                </div>
                <div class="d-flex gap-2 pt-2 border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary btn-sm w-50" style="background-color: #21262d; border-color: #30363d; border-radius: 6px;" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="update_profile" class="btn btn-danger btn-sm w-50 fw-semibold" style="border-radius: 6px;">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal บัตรสมาชิก + สถานะเช็กอินวันนี้ + ประวัติย้อนหลัง -->
<div class="modal fade" id="passModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content modal-clean text-white p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="small fw-semibold <?= $is_expired ? 'text-danger' : 'text-light' ?>">
                    <i class="bi bi-qr-code me-1 text-warning"></i> ข้อมูลบัตรสมาชิก
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
            </div>

            <div class="text-center mb-3">
                <div class="bg-white p-2 rounded-2 d-inline-block position-relative">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($member['card_code'] ?? '') ?>" alt="QR" style="width: 130px; height: 130px; <?= $is_expired ? 'opacity: 0.15; filter: grayscale(100%);' : '' ?>">
                    <?php if ($is_expired): ?>
                        <div class="position-absolute top-50 start-50 translate-middle text-danger fw-bold small text-center" style="width: 100%;">
                            <i class="bi bi-lock-fill fs-4 d-block"></i> หมดอายุ
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold text-white mt-2 mb-0"><?= htmlspecialchars($member['fullname'] ?? '') ?></h6>
                <div class="text-warning small font-monospace">รหัส: <?= htmlspecialchars($member['card_code'] ?? '-') ?></div>
            </div>

            <!-- ข้อมูลแพ็กเกจ & เช็กอินวันนี้ -->
            <div class="p-2 rounded-2 border border-secondary border-opacity-25 mb-3 small" style="background-color: #0d1117;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secondary">แพ็กเกจ:</span>
                    <span class="text-white fw-medium"><?= htmlspecialchars($member['package_name'] ?? '-') ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secondary">วันหมดอายุ:</span>
                    <span class="<?= $is_expired ? 'text-danger fw-bold' : 'text-light' ?> fw-medium">
                        <?= $member['expire_date'] ? date('d/m/Y', strtotime($member['expire_date'])) : '-' ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secondary">สถานะแพ็กเกจ:</span>
                    <span>
                        <?= $is_expired ? '<span class="badge bg-danger">หมดอายุแล้ว</span>' : "<span class='text-success fw-medium'>เหลืออีก $days_left วัน</span>" ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between pt-1 border-top border-secondary border-opacity-25">
                    <span class="text-secondary">เช็กอินวันนี้:</span>
                    <span>
                        <?php if ($today_checkin): ?>
                            <span class="text-success fw-medium"><i class="bi bi-check2-circle me-1"></i>เข้าใช้เมื่อ <?= date('H:i น.', strtotime($today_checkin['checkin_time'])) ?></span>
                        <?php else: ?>
                            <span class="text-secondary">ยังไม่ได้เข้าใช้บริการ</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if ($is_expired): ?>
                <a href="packages_view.php" class="btn btn-danger btn-sm w-100 fw-semibold py-2 mb-2" style="border-radius: 6px;">
                    <i class="bi bi-arrow-repeat me-1"></i> ต่ออายุ / เปลี่ยนแพ็กเกจใหม่
                </a>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm w-50" style="border-radius: 6px; font-size: 0.78rem;" onclick="openHistoryCheckinModal()">
                    <i class="bi bi-clock-history me-1"></i> ประวัติเข้ายิม
                </button>
                <button type="button" class="btn btn-secondary btn-sm w-50 text-light" style="background-color: #21262d; border-color: #30363d; border-radius: 6px; font-size: 0.78rem;" data-bs-dismiss="modal">
                    ปิด
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal ประวัติการเข้าใช้บริการ 5 ครั้งล่าสุด -->
<div class="modal fade" id="historyCheckinModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content modal-clean text-white p-3">
            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary border-opacity-25 pb-2">
                <div class="small fw-semibold text-info">
                    <i class="bi bi-clock-history me-1"></i> ประวัติการเข้ายิม (5 ครั้งล่าสุด)
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
            </div>

            <div class="py-2">
                <?php if (count($checkin_history) > 0): ?>
                    <ul class="list-group list-group-flush small bg-transparent">
                        <?php foreach ($checkin_history as $idx => $chk): ?>
                        <li class="list-group-item bg-transparent text-light border-secondary border-opacity-25 px-0 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-calendar3 text-secondary me-2"></i><?= date('d/m/Y', strtotime($chk['checkin_time'])) ?></span>
                            <span class="text-success font-monospace"><?= date('H:i:s น.', strtotime($chk['checkin_time'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center text-secondary py-3 small">ยังไม่มีประวัติการสแกนเข้าใช้บริการ</div>
                <?php endif; ?>
            </div>

            <button type="button" class="btn btn-secondary btn-sm w-100 mt-2" style="background-color: #21262d; border-color: #30363d; border-radius: 6px; font-size: 0.8rem;" data-bs-dismiss="modal">
                ปิด
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openProfileModal() { new bootstrap.Modal(document.getElementById('profileModal')).show(); }
function openPassModal() { new bootstrap.Modal(document.getElementById('passModal')).show(); }
function openHistoryCheckinModal() {
    bootstrap.Modal.getInstance(document.getElementById('passModal')).hide();
    new bootstrap.Modal(document.getElementById('historyCheckinModal')).show();
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

<?php if ($swal_status === 'profile_updated'): ?>
Swal.fire({
    icon: 'success',
    title: 'สำเร็จ',
    text: 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว!',
    timer: 1800,
    showConfirmButton: false,
    background: '#161b22',
    color: '#fff'
});
<?php endif; ?>
</script>
</body>
</html>