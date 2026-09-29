<?php
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$swal_status = '';

// เพิ่มแพ็กเกจใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $category = $_POST['category'] ?? 'fitness';
    $name = trim($_POST['package_name']);
    $access_type = trim($_POST['access_type']);
    $days = intval($_POST['duration_days']);
    $price = floatval($_POST['price']);
    $details = trim($_POST['details']);

    $cover_image = '';
    if (!empty($_FILES['package_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['package_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $cover_image = 'cover_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['package_image']['tmp_name'], '../uploads/' . $cover_image);
        }
    }

    $gallery_arr = [];
    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $g_name = 'gal_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    move_uploaded_file($tmp_name, '../uploads/' . $g_name);
                    $gallery_arr[] = $g_name;
                }
            }
        }
    }
    $gallery_json = json_encode($gallery_arr);

    $stmt = $pdo->prepare("INSERT INTO packages (category, package_name, access_type, duration_days, price, details, image, gallery_images) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$category, $name, $access_type, $days, $price, $details, $cover_image, $gallery_json]);
    $swal_status = 'added';
}

// แก้ไขแพ็กเกจ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_package'])) {
    $pkg_id = intval($_POST['package_id']);
    $category = $_POST['category'] ?? 'fitness';
    $name = trim($_POST['package_name']);
    $access_type = trim($_POST['access_type']);
    $days = intval($_POST['duration_days']);
    $price = floatval($_POST['price']);
    $details = trim($_POST['details']);

    $stmt_old = $pdo->prepare("SELECT image, gallery_images FROM packages WHERE package_id = ?");
    $stmt_old->execute([$pkg_id]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

    $cover_image = $old_data['image'];
    if (!empty($_FILES['package_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['package_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $cover_image = 'cover_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['package_image']['tmp_name'], '../uploads/' . $cover_image);
        }
    }

    $gallery_arr = json_decode($old_data['gallery_images'] ?? '[]', true) ?: [];
    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            if (!empty($tmp_name)) {
                $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $g_name = 'gal_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    move_uploaded_file($tmp_name, '../uploads/' . $g_name);
                    $gallery_arr[] = $g_name;
                }
            }
        }
    }
    $gallery_json = json_encode($gallery_arr);

    $stmt = $pdo->prepare("UPDATE packages SET category = ?, package_name = ?, access_type = ?, duration_days = ?, price = ?, details = ?, image = ?, gallery_images = ? WHERE package_id = ?");
    $stmt->execute([$category, $name, $access_type, $days, $price, $details, $cover_image, $gallery_json, $pkg_id]);
    $swal_status = 'edited';
}

// ลบแพ็กเกจ
if (isset($_GET['delete'])) {
    $pkg_id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM packages WHERE package_id = ?")->execute([$pkg_id]);
    header("Location: packages.php?status=deleted");
    exit();
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY price ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแพ็กเกจและรูปภาพ - TRAINUP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        .form-control-custom, .form-select-custom { background-color: #0d1117; border: 1px solid var(--border-color); color: #fff; border-radius: 8px; font-size: 0.88rem; padding: 8px 12px; }
        .form-control-custom:focus, .form-select-custom:focus { background-color: #0d1117; border-color: #e50914; color: #fff; box-shadow: none; }
        
        .table-custom { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .table-custom thead th { background-color: #0d1117; color: #8b949e; font-weight: 500; font-size: 0.84rem; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
        .table-custom tbody td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #21262d; font-size: 0.88rem; }
        .table-custom tbody tr:hover { background-color: #1c2128; }
        
        .modal-trainup { background-color: #161b22; border: 1px solid #30363d; border-radius: 14px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-fire text-danger"></i><span>TRAINUP ADMIN</span></div>
    <ul class="nav flex-column mt-2">
        <li class="nav-item"><a class="nav-link" href="../admin_dashboard.php"><i class="bi bi-grid-fill"></i> หน้าภาพรวม</a></li>
        <li class="nav-item"><a class="nav-link" href="members.php"><i class="bi bi-people-fill"></i> สมาชิก & ตรวจสลิป</a></li>
        <li class="nav-item"><a class="nav-link active" href="packages.php"><i class="bi bi-tags-fill"></i> จัดการแพ็กเกจ</a></li>
        <li class="nav-item"><a class="nav-link" href="report.php"><i class="bi bi-bar-chart-line-fill"></i> รายงานและสถิติ</a></li>
        <li class="nav-item mt-4"><a class="nav-link text-danger" href="javascript:void(0)" onclick="confirmLogout('../logout.php')"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="text-secondary small"><i class="bi bi-house-door me-1"></i> / <span class="text-white">จัดการแพ็กเกจ</span></div>
            <h5 class="fw-bold mb-0 mt-1 text-white">กำหนดข้อมูลราคา สิทธิ์ และอัลบั้มรูปภาพ</h5>
        </div>
        <a href="../admin_dashboard.php" class="btn btn-outline-secondary btn-sm px-3" style="border-radius: 6px; font-size: 0.85rem;">กลับหน้าภาพรวม</a>
    </div>

    <!-- ฟอร์มสร้างแพ็กเกจใหม่ -->
    <div class="box-panel p-4 mb-4">
        <h6 class="fw-bold text-white mb-3"><i class="bi bi-plus-circle text-danger me-2"></i>สร้างแพ็กเกจ / คอร์สเทรนเนอร์ใหม่</h6>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <label class="form-label small text-secondary">ประเภทรายการ</label>
                    <select name="category" class="form-select form-select-custom" required>
                        <option value="fitness">🏋️ แพ็กเกจฟิตเนส</option>
                        <option value="trainer">🥊 เทรนเนอร์ส่วนตัว</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">ชื่อแพ็กเกจ / ชื่อเทรนเนอร์</label>
                    <input type="text" name="package_name" class="form-control form-control-custom" placeholder="เช่น Jetts 24 Hr หรือ Coach Alex" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">สิทธิ์การเข้าใช้บริการ</label>
                    <input type="text" name="access_type" class="form-control form-control-custom" placeholder="เช่น เข้าได้ตลอด 24 ชม. หรือ จำกัด 1 ครั้ง/วัน" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">ระยะเวลา (วัน)</label>
                    <input type="number" name="duration_days" class="form-control form-control-custom" value="30" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">ราคา (บาท)</label>
                    <input type="number" step="0.01" name="price" class="form-control form-control-custom" placeholder="1500" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small text-secondary">รายละเอียดสิทธิประโยชน์ / คุณสมบัติ</label>
                    <textarea name="details" class="form-control form-control-custom" rows="3" placeholder="ระบุสิ่งที่สมาชิกจะได้รับ..."></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">รูปภาพหน้าปกหลัก</label>
                    <input type="file" name="package_image" class="form-control form-control-custom" accept="image/*">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">รูปภาพเพิ่มเติมในแกลเลอรี (เลือกได้หลายรูป)</label>
                    <input type="file" name="gallery_images[]" class="form-control form-control-custom" accept="image/*" multiple>
                </div>
            </div>

            <button type="submit" name="add_package" class="btn btn-danger btn-sm px-3 fw-semibold" style="border-radius: 6px;">
                <i class="bi bi-download me-1"></i> บันทึกข้อมูล
            </button>
        </form>
    </div>

    <!-- ตารางรายการแพ็กเกจทั้งหมด -->
    <div class="box-panel">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--border-color) !important;">
            <span class="fw-semibold text-white">รายการแพ็กเกจและคอร์สทั้งหมด</span>
            <span class="badge bg-secondary"><?= count($packages) ?> รายการ</span>
        </div>

        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 70px;">รูปภาพ</th>
                    <th>ประเภท</th>
                    <th>ชื่อแพ็กเกจ</th>
                    <th>สิทธิ์เข้าใช้บริการ</th>
                    <th>ระยะเวลา</th>
                    <th>ราคา</th>
                    <th>รูปเพิ่มเติม</th>
                    <th class="text-end" style="width: 120px;">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): 
                    $gal = json_decode($pkg['gallery_images'] ?? '[]', true) ?: [];
                ?>
                <tr>
                    <td>
                        <?php if (!empty($pkg['image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($pkg['image']) ?>" class="rounded-2" style="width: 48px; height: 36px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-dark rounded-2 text-center text-secondary d-flex align-items-center justify-content-center" style="width: 48px; height: 36px;"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= ($pkg['category'] ?? 'fitness') === 'fitness' ? 'bg-primary' : 'bg-warning text-dark' ?>" style="font-weight: 500;">
                            <?= ($pkg['category'] ?? 'fitness') === 'fitness' ? 'ฟิตเนส' : 'เทรนเนอร์' ?>
                        </span>
                    </td>
                    <td class="fw-semibold text-white"><?= htmlspecialchars($pkg['package_name']) ?></td>
                    <td>
                        <span class="badge bg-dark border border-secondary text-info fw-normal">
                            <i class="bi bi-shield-check me-1"></i><?= htmlspecialchars($pkg['access_type'] ?? 'เข้าได้ 24 ชม.') ?>
                        </span>
                    </td>
                    <td class="text-secondary"><?= $pkg['duration_days'] ?> วัน</td>
                    <td class="text-white fw-bold">฿<?= number_format($pkg['price'], 2) ?></td>
                    <td><span class="badge bg-secondary"><?= count($gal) ?> รูป</span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2 btn-edit-pkg" style="font-size: 0.8rem; border-radius: 4px;"
                                data-id="<?= $pkg['package_id'] ?>"
                                data-cat="<?= $pkg['category'] ?? 'fitness' ?>"
                                data-name="<?= htmlspecialchars($pkg['package_name']) ?>"
                                data-access="<?= htmlspecialchars($pkg['access_type'] ?? 'เข้าได้ 24 ชม.') ?>"
                                data-days="<?= $pkg['duration_days'] ?>"
                                data-price="<?= $pkg['price'] ?>"
                                data-details="<?= htmlspecialchars($pkg['details'] ?? '') ?>">
                            แก้ไข
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.8rem; border-radius: 4px;" onclick="confirmDelete(<?= $pkg['package_id'] ?>)">
                            ลบ
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal แก้ไขแพ็กเกจ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content modal-trainup p-3">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-2">
                <h6 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square text-warning me-2"></i>แก้ไขข้อมูลแพ็กเกจ</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="package_id" id="editPkgId">
                <div class="modal-body py-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">ประเภท</label>
                            <select name="category" id="editCategory" class="form-select form-select-custom" required>
                                <option value="fitness">แพ็กเกจฟิตเนส</option>
                                <option value="trainer">เทรนเนอร์ส่วนตัว</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">สิทธิ์เข้าใช้บริการ</label>
                            <input type="text" name="access_type" id="editAccessType" class="form-control form-control-custom" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-secondary">ชื่อแพ็กเกจ</label>
                        <input type="text" name="package_name" id="editPkgName" class="form-control form-control-custom" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">ระยะเวลา (วัน)</label>
                            <input type="number" name="duration_days" id="editDays" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">ราคา (บาท)</label>
                            <input type="number" step="0.01" name="price" id="editPrice" class="form-control form-control-custom" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">เปลี่ยนภาพหน้าปก</label>
                            <input type="file" name="package_image" class="form-control form-control-custom" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">เพิ่มรูปแกลเลอรี</label>
                            <input type="file" name="gallery_images[]" class="form-control form-control-custom" accept="image/*" multiple>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-secondary">รายละเอียด</label>
                        <textarea name="details" id="editDetails" class="form-control form-control-custom" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="edit_package" class="btn btn-sm btn-danger fw-semibold">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-edit-pkg').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editPkgId').value = this.getAttribute('data-id');
        document.getElementById('editCategory').value = this.getAttribute('data-cat');
        document.getElementById('editPkgName').value = this.getAttribute('data-name');
        document.getElementById('editAccessType').value = this.getAttribute('data-access');
        document.getElementById('editDays').value = this.getAttribute('data-days');
        document.getElementById('editPrice').value = this.getAttribute('data-price');
        document.getElementById('editDetails').value = this.getAttribute('data-details');
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: 'ต้องการลบแพ็กเกจนี้ออกจากระบบใช่หรือไม่',
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
            window.location.href = 'packages.php?delete=' + id;
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

<?php if ($swal_status === 'added'): ?>
Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'เพิ่มแพ็กเกจใหม่เรียบร้อยแล้ว!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
<?php elseif ($swal_status === 'edited'): ?>
Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'แก้ไขข้อมูลแพ็กเกจเรียบร้อยแล้ว!', timer: 1800, showConfirmButton: false, background: '#161b22', color: '#fff' });
<?php endif; ?>
</script>
</body>
</html>