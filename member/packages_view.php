<?php
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT m.*, u.email, u.username FROM members m JOIN users u ON m.user_id = u.user_id WHERE m.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

$swal_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_package'])) {
    $package_id = intval($_POST['package_id']);
    $amount = floatval($_POST['amount']);
    $payment_type = trim($_POST['payment_type']);

    $slip_name = '';
    if (!empty($_FILES['slip_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $slip_name = 'slip_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['slip_image']['tmp_name'], '../uploads/' . $slip_name);
        }
    }

    if ($slip_name !== '') {
        $ins = $pdo->prepare("INSERT INTO payments (member_id, package_id, amount, slip_image, payment_type, status, payment_date) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $ins->execute([$member['member_id'], $package_id, $amount, $slip_name, $payment_type]);
        $swal_status = 'pending_saved';
    } else {
        $swal_status = 'upload_error';
    }
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกแพ็กเกจสมาชิกและเทรนเนอร์ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Kanit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-red: #e50914;
            --primary-red-hover: #ff1e27;
            --bg-dark: #0a0c10;
            --card-surface: #14171f;
            --card-border: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-dark);
            color: #f1f5f9;
            min-height: 100vh;
            padding-bottom: 70px;
        }

        .top-action-bar {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-back-home {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 30px;
            padding: 7px 18px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back-home:hover { background: rgba(255, 255, 255, 0.15); color: #fff; }

        .main-headline {
            font-family: 'Anton', sans-serif;
            font-size: clamp(2.2rem, 5vw, 4rem);
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.05;
        }

        .filter-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            font-size: 0.85rem;
            border-radius: 30px;
            padding: 8px 18px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-pill:hover, .filter-pill.active {
            background: var(--primary-red);
            border-color: var(--primary-red);
            color: #fff;
            font-weight: 600;
        }

        .pkg-card {
            background-color: var(--card-surface);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s;
        }
        .pkg-card:hover {
            transform: translateY(-4px);
            border-color: rgba(229, 9, 20, 0.5);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
        }

        .pkg-image-box {
            position: relative;
            height: 180px;
            overflow: hidden;
            background-color: #0d0f14;
        }
        .pkg-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s;
        }
        .pkg-card:hover .pkg-image-box img { transform: scale(1.05); }

        .category-badge-chip {
            position: absolute;
            top: 12px;
            right: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .access-right-badge {
            background: rgba(13, 17, 23, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #38bdf8;
            font-size: 0.76rem;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .price-text-huge {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .btn-order-cta {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: 10px;
            padding: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-order-cta:hover { background: var(--primary-red); border-color: var(--primary-red); color: #fff; }

        .modal-order-content {
            background-color: #14171f;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            color: #fff;
        }

        .pay-tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            padding: 8px 14px;
            font-size: 0.84rem;
            border-radius: 8px;
            cursor: pointer;
            width: 50%;
            transition: all 0.2s;
        }
        .pay-tab-btn.active {
            background: #21262d;
            border-color: #58a6ff;
            color: #fff;
            font-weight: 600;
        }

        .carousel-pkg-preview {
            border-radius: 12px;
            overflow: hidden;
            height: 230px;
            background: #000;
        }
        .carousel-pkg-preview img {
            width: 100%;
            height: 230px;
            object-fit: cover;
        }
    </style>
</head>
<body>

<div class="top-action-bar">
    <a href="dashboard.php" class="btn-back-home"><i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก</a>
    <span class="text-secondary small"><?= count($packages) ?> Plans Available</span>
</div>

<div class="container" style="max-width: 1200px;">
    
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="text-danger fs-4 fw-bold">❯❯</span>
            <h1 class="main-headline text-white mb-0">CHOOSE YOUR MEMBERSHIP OR PRIVATE COACH</h1>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <button class="filter-pill active" onclick="filterPackages('all', this)"><i class="bi bi-grid-fill me-1"></i> ทั้งหมด</button>
        <button class="filter-pill" onclick="filterPackages('fitness', this)"><i class="bi bi-building me-1"></i> ฟิตเนสทั่วไป</button>
        <button class="filter-pill" onclick="filterPackages('trainer', this)"><i class="bi bi-person-arms-up me-1"></i> จ้างเทรนเนอร์ส่วนตัว</button>
    </div>

    <!-- Package Grid -->
    <div class="row g-4">
        <?php foreach ($packages as $pkg): 
            $cat = $pkg['category'] ?? 'fitness';
            $access = $pkg['access_type'] ?? 'เข้าได้ 24 ชม.';
            $cover = !empty($pkg['image']) ? "../uploads/{$pkg['image']}" : 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=800&auto=format&fit=crop';
            
            $raw_gallery = json_decode($pkg['gallery_images'] ?? '[]', true) ?: [];
            $full_gallery = [$cover];
            foreach ($raw_gallery as $g_img) {
                if (!empty($g_img)) {
                    $full_gallery[] = "../uploads/{$g_img}";
                }
            }
            $gallery_json = htmlspecialchars(json_encode($full_gallery), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="col-md-6 col-lg-3 pkg-item" data-category="<?= htmlspecialchars($cat) ?>">
            <div class="pkg-card">
                <div>
                    <div class="pkg-image-box">
                        <img src="<?= $cover ?>" alt="<?= htmlspecialchars($pkg['package_name']) ?>">
                        <?php if ($cat === 'trainer'): ?>
                            <span class="category-badge-chip bg-danger text-white"><i class="bi bi-award-fill me-1"></i>TRAINER</span>
                        <?php else: ?>
                            <span class="category-badge-chip bg-primary text-white"><i class="bi bi-lightning-charge-fill me-1"></i>MEMBERSHIP</span>
                        <?php endif; ?>
                    </div>

                    <div class="p-3">
                        <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($pkg['package_name']) ?></h5>
                        
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <span class="price-text-huge">฿<?= number_format($pkg['price']) ?></span>
                            <span class="text-secondary small">/ <?= $pkg['duration_days'] ?> วัน</span>
                        </div>

                        <div class="mb-2">
                            <span class="access-right-badge">
                                <i class="bi bi-shield-check text-info"></i> <?= htmlspecialchars($access) ?>
                            </span>
                        </div>

                        <p class="text-secondary small mb-0" style="min-height: 40px; line-height: 1.4; white-space: pre-line;">
                            <?= htmlspecialchars($pkg['details'] ?? 'เข้าใช้งานสิ่งอำนวยความสะดวกครบครัน') ?>
                        </p>
                    </div>
                </div>

                <div class="p-3 pt-0">
                    <button type="button" class="btn-order-cta btn-open-modal" 
                            data-id="<?= $pkg['package_id'] ?>"
                            data-name="<?= htmlspecialchars($pkg['package_name']) ?>"
                            data-price="<?= $pkg['price'] ?>"
                            data-days="<?= $pkg['duration_days'] ?>"
                            data-access="<?= htmlspecialchars($access) ?>"
                            data-details="<?= htmlspecialchars($pkg['details'] ?? '') ?>"
                            data-gallery='<?= $gallery_json ?>'>
                        ดูรายละเอียด & สั่งซื้อ <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Modal รายละเอียดเต็ม + แกลเลอรีรูปภาพ + ฟอร์มชำระเงิน -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-order-content p-4">
            
            <div class="d-flex justify-content-between align-items-start mb-3 border-bottom border-secondary border-opacity-25 pb-2">
                <div>
                    <span class="badge bg-danger mb-1">TRAINUP PLAN DETAILS</span>
                    <h4 class="fw-bold text-white mb-0" id="modalPkgName">-</h4>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="row g-4">
                <!-- ซ้าย: แกลเลอรีภาพ + สิทธิ์การใช้งาน -->
                <div class="col-md-6">
                    <div id="pkgCarousel" class="carousel slide carousel-pkg-preview mb-3">
                        <div class="carousel-inner" id="carouselInner"></div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#pkgCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#pkgCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    </div>

                    <div class="bg-black bg-opacity-40 p-3 rounded-3 border border-secondary border-opacity-25 small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">สิทธิ์เข้าใช้งาน:</span>
                            <strong class="text-info" id="modalPkgAccess">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">ระยะเวลาใช้งาน:</span>
                            <strong class="text-white" id="modalPkgDuration">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">ราคาสุทธิ:</span>
                            <strong class="text-warning fs-5" id="modalPkgPrice">฿0.00</strong>
                        </div>
                        <div class="pt-2 border-top border-secondary border-opacity-25 text-secondary" id="modalPkgDetails" style="white-space: pre-line;">
                            -
                        </div>
                    </div>
                </div>

                <!-- ขวา: ช่องทางชำระเงิน & แนบสลิป -->
                <div class="col-md-6">
                    <label class="form-label small text-secondary mb-2"><i class="bi bi-wallet2 text-danger me-1"></i> เลือกวิธีชำระเงิน</label>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="pay-tab-btn active" id="btnTabQr" onclick="switchPayTab('qr')"><i class="bi bi-qr-code me-1"></i> PromptPay QR</button>
                        <button type="button" class="pay-tab-btn" id="btnTabBank" onclick="switchPayTab('bank')"><i class="bi bi-bank me-1"></i> โอนผ่านบัญชี</button>
                    </div>

                    <!-- PromptPay QR -->
                    <div id="payAreaQr" class="text-center p-3 bg-secondary bg-opacity-10 rounded-3 border border-secondary border-opacity-25 mb-3">
                        <div class="bg-white p-2 rounded-3 d-inline-block mb-2 shadow">
                            <img id="dynamicQrImg" src="" alt="QR Code" style="width: 130px; height: 130px;">
                        </div>
                        <div class="text-white small fw-semibold">สแกนชำระเงินผ่าน Mobile Banking</div>
                        <div class="text-warning font-monospace small">พร้อมเพย์: 095-398-6610</div>
                    </div>

                    <!-- โอนธนาคาร -->
                    <div id="payAreaBank" class="d-none p-3 bg-secondary bg-opacity-10 rounded-3 border border-secondary border-opacity-25 mb-3 small">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;"><i class="bi bi-bank2"></i></div>
                            <div>
                                <strong class="text-white d-block">ธนาคารกสิกรไทย (K-Bank)</strong>
                                <span class="text-secondary" style="font-size: 0.75rem;">ชื่อบัญชี: บจก. เทรนอัพ คลับ (TRAINUP)</span>
                            </div>
                        </div>
                        <div class="p-2 bg-black bg-opacity-50 rounded-2 border border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                            <span class="font-monospace text-warning fw-bold fs-6">123-4-56789-0</span>
                            <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size: 0.75rem;" onclick="copyAccount('1234567890')">คัดลอก</button>
                        </div>
                    </div>

                    <!-- ฟอร์มแนบสลิป -->
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="package_id" id="formPkgId">
                        <input type="hidden" name="amount" id="formAmount">

                        <div class="mb-2">
                            <label class="form-label small text-secondary mb-1">ประเภทการทำรายการ</label>
                            <select name="payment_type" class="form-select form-select-sm bg-dark text-white border-secondary">
                                <option value="new">สมัครแพ็กเกจใหม่</option>
                                <option value="renew">ต่ออายุจากแพ็กเกจเดิม</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-secondary mb-1"><i class="bi bi-receipt text-warning me-1"></i> แนบหลักฐานสลิปการโอนเงิน</label>
                            <input type="file" name="slip_image" class="form-control form-control-sm bg-dark text-white border-secondary" accept="image/*" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-50" data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" name="buy_package" class="btn btn-danger btn-sm w-50 fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> ยืนยันชำระเงิน
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let autoSlideTimer = null;

function filterPackages(category, btn) {
    document.querySelectorAll('.filter-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.pkg-item').forEach(item => {
        if (category === 'all' || item.getAttribute('data-category') === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function switchPayTab(type) {
    if (type === 'qr') {
        document.getElementById('btnTabQr').classList.add('active');
        document.getElementById('btnTabBank').classList.remove('active');
        document.getElementById('payAreaQr').classList.remove('d-none');
        document.getElementById('payAreaBank').classList.add('d-none');
    } else {
        document.getElementById('btnTabBank').classList.add('active');
        document.getElementById('btnTabQr').classList.remove('active');
        document.getElementById('payAreaBank').classList.remove('d-none');
        document.getElementById('payAreaQr').classList.add('d-none');
    }
}

function copyAccount(accNum) {
    navigator.clipboard.writeText(accNum);
    Swal.fire({ icon: 'success', title: 'คัดลอกเลขบัญชีแล้ว', timer: 1200, showConfirmButton: false, background: '#14171f', color: '#fff' });
}

// Event Delegation สำหรับปุ่มเปิด Modal
document.querySelectorAll('.btn-open-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const price = this.getAttribute('data-price');
        const days = this.getAttribute('data-days');
        const access = this.getAttribute('data-access');
        const details = this.getAttribute('data-details');
        const galleryImages = JSON.parse(this.getAttribute('data-gallery') || '[]');

        document.getElementById('formPkgId').value = id;
        document.getElementById('formAmount').value = price;

        document.getElementById('modalPkgName').innerText = name;
        document.getElementById('modalPkgPrice').innerText = '฿' + Number(price).toLocaleString('th-TH', {minimumFractionDigits: 2});
        document.getElementById('modalPkgDuration').innerText = days + ' วัน';
        document.getElementById('modalPkgAccess').innerText = access;
        document.getElementById('modalPkgDetails').innerText = details ? details : 'เข้าใช้งานสิ่งอำนวยความสะดวกครบครันตามเงื่อนไข';

        document.getElementById('dynamicQrImg').src = 'https://promptpay.io/0953986610/' + Number(price) + '.png';

        const cInner = document.getElementById('carouselInner');
        cInner.innerHTML = '';
        
        if (galleryImages && galleryImages.length > 0) {
            galleryImages.forEach((imgSrc, idx) => {
                const item = document.createElement('div');
                item.className = 'carousel-item' + (idx === 0 ? ' active' : '');
                item.innerHTML = `<img src="${imgSrc}" class="d-block w-100" alt="Image ${idx + 1}" onerror="this.src='https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=800&auto=format&fit=crop'">`;
                cInner.appendChild(item);
            });
        }

        switchPayTab('qr');

        const orderModalEl = document.getElementById('orderModal');
        const orderModal = bootstrap.Modal.getOrCreateInstance(orderModalEl);
        orderModal.show();

        if (autoSlideTimer) clearInterval(autoSlideTimer);

        const carouselEl = document.getElementById('pkgCarousel');
        const carouselInstance = bootstrap.Carousel.getOrCreateInstance(carouselEl, {
            interval: false,
            wrap: true
        });

        if (galleryImages && galleryImages.length > 1) {
            autoSlideTimer = setInterval(() => {
                carouselInstance.next();
            }, 2500);
        }

        orderModalEl.addEventListener('hidden.bs.modal', function () {
            if (autoSlideTimer) clearInterval(autoSlideTimer);
        }, { once: true });
    });
});

<?php if ($swal_status === 'pending_saved'): ?>
Swal.fire({
    icon: 'success',
    title: 'ส่งสลิปเรียบร้อยแล้ว!',
    text: 'ระบบได้บันทึกรายการชำระเงินของคุณแล้ว กรุณารอผู้ดูแลระบบตรวจสอบและอนุมัติสิทธิ์',
    background: '#14171f',
    color: '#fff',
    confirmButtonColor: '#e50914',
    confirmButtonText: 'ไปหน้าประวัติ & ใบเสร็จ'
}).then(() => {
    window.location.href = 'payment.php';
});
<?php elseif ($swal_status === 'upload_error'): ?>
Swal.fire({
    icon: 'error',
    title: 'เกิดข้อผิดพลาด',
    text: 'กรุณาเลือกไฟล์สลิปการโอนเงิน (รองรับ jpg, png, webp)',
    background: '#14171f',
    color: '#fff',
    confirmButtonColor: '#e50914'
});
<?php endif; ?>
</script>
</body>
</html>