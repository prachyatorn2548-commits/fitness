<?php
date_default_timezone_set('Asia/Bangkok');
session_start();
require '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$pay_id = intval($_GET['payment_id'] ?? 0);

// ดึงข้อมูลใบเสร็จเฉพาะของสมาชิกที่ล็อกอินอยู่
$stmt = $pdo->prepare("SELECT p.*, m.fullname, m.card_code, m.phone, u.email,
                       COALESCE(pk1.package_name, pk2.package_name, 'แพ็กเกจฟิตเนส') AS package_name,
                       COALESCE(pk1.details, pk2.details, 'เข้าใช้งานสิ่งอำนวยความสะดวกครบครันตามเงื่อนไข') AS package_details
                       FROM payments p
                       JOIN members m ON p.member_id = m.member_id
                       JOIN users u ON m.user_id = u.user_id
                       LEFT JOIN packages pk1 ON p.package_id = pk1.package_id
                       LEFT JOIN packages pk2 ON m.package_id = pk2.package_id
                       WHERE p.payment_id = ? AND m.user_id = ? AND p.status = 'approved'");
$stmt->execute([$pay_id, $_SESSION['user_id']]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    die("ไม่พบข้อมูลใบเสร็จรับเงิน หรือรายการนี้ยังไม่ได้รับการอนุมัติ");
}

$inv_number = 'INV-' . str_pad($receipt['payment_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จรับเงิน - <?= $inv_number ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #525659;
            margin: 0;
            padding: 30px 10px;
            color: #1f2937;
        }

        .receipt-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 20mm;
            margin: 0 auto;
            border-radius: 4px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.35);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-logo {
            font-family: 'Anton', sans-serif;
            font-size: 2rem;
            letter-spacing: 1.5px;
            color: #dc2626;
            line-height: 1;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 25px;
        }
        .table-items th {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
        }
        .table-items td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            font-size: 0.88rem;
        }

        .action-bar {
            width: 210mm;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .action-bar {
                display: none !important;
            }
            .receipt-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                padding: 15mm 15mm !important;
            }
        }
    </style>
</head>
<body>

<!-- แถบควบคุมการพิมพ์ -->
<div class="action-bar">
    <a href="payment.php" class="btn btn-outline-light btn-sm px-3"><i class="bi bi-arrow-left me-1"></i> กลับหน้าประวัติ</a>
    <button onclick="window.print()" class="btn btn-danger btn-sm px-4 fw-semibold shadow"><i class="bi bi-printer-fill me-1"></i> พิมพ์ใบเสร็จ (Print / PDF)</button>
</div>

<!-- ตัวแผ่นใบเสร็จทางการ A4 -->
<div class="receipt-sheet">
    <div>
        <!-- หัวเอกสารและข้อมูลบริษัท -->
        <div class="d-flex justify-content-between align-items-start border-bottom border-2 border-danger pb-3 mb-4">
            <div>
                <div class="brand-logo mb-1"><i class="bi bi-fire"></i> TRAINUP FITNESS CLUB</div>
                <div style="font-size: 0.85rem; color: #475569; line-height: 1.45;">
                    <strong>บริษัท เทรนอัพ ฟิตเนส คลับ จำกัด (สำนักงานใหญ่)</strong><br>
                    เลขที่ 123 อาคารสปอร์ตคอมเพล็กซ์ ถ.สุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110<br>
                    เลขประจำตัวผู้เสียภาษี: 0-1055-67890-12-3 | เบอร์โทรศัพท์: 02-123-4567
                </div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-danger mb-1" style="font-family: 'Anton', sans-serif; letter-spacing: 1px;">ใบเสร็จรับเงิน</h4>
                <div class="text-secondary small">RECEIPT / TAX INVOICE</div>
                <div class="mt-2 text-dark" style="font-size: 0.85rem;">
                    เลขที่ใบเสร็จ: <strong class="font-monospace text-danger"><?= $inv_number ?></strong><br>
                    วันที่ชำระเงิน: <strong><?= date('d/m/Y H:i น.', strtotime($receipt['payment_date'])) ?></strong>
                </div>
            </div>
        </div>

        <!-- ข้อมูลสมาชิก -->
        <div class="p-3 mb-4 rounded-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.88rem;">
            <div class="row">
                <div class="col-7">
                    <span class="text-secondary">ชื่อลูกค้า / สมาชิก:</span> <strong class="text-dark fs-6"><?= htmlspecialchars($receipt['fullname']) ?></strong><br>
                    <span class="text-secondary">เบอร์โทรศัพท์:</span> <span class="text-dark"><?= htmlspecialchars($receipt['phone'] ?? '-') ?></span><br>
                    <span class="text-secondary">อีเมล:</span> <span class="text-dark"><?= htmlspecialchars($receipt['email'] ?? '-') ?></span>
                </div>
                <div class="col-5 text-end">
                    <span class="text-secondary">รหัสบัตรสมาชิก:</span> <strong class="text-danger font-monospace fs-6"><?= htmlspecialchars($receipt['card_code'] ?? '-') ?></strong><br>
                    <span class="text-secondary">ประเภทการทำรายการ:</span> <span class="badge bg-secondary"><?= $receipt['payment_type'] === 'renew' ? 'ต่ออายุสมาชิก' : 'สมัครสมาชิกใหม่' ?></span><br>
                    <span class="text-secondary">สถานะ:</span> <strong class="text-success"><i class="bi bi-check-circle-fill"></i> ชำระเงินเรียบร้อย</strong>
                </div>
            </div>
        </div>

        <!-- รายการแพ็กเกจ -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">ลำดับ</th>
                    <th>รายการแพ็กเกจ / สิทธิประโยชน์ (Description)</th>
                    <th style="width: 190px; text-align: center;">ช่วงเวลาสิทธิ์การใช้งาน</th>
                    <th style="width: 140px; text-align: right;">จำนวนเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center; vertical-align: top;">1</td>
                    <td>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($receipt['package_name']) ?></strong>
                        <div class="text-secondary small mt-1"><?= nl2br(htmlspecialchars($receipt['package_details'])) ?></div>
                    </td>
                    <td style="text-align: center; vertical-align: top; font-size: 0.85rem;">
                        <?php if ($receipt['start_date'] && $receipt['expire_date']): ?>
                            <?= date('d/m/Y', strtotime($receipt['start_date'])) ?> - <?= date('d/m/Y', strtotime($receipt['expire_date'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; vertical-align: top; font-weight: bold; font-size: 1rem;">
                        <?= number_format($receipt['amount'], 2) ?>
                    </td>
                </tr>
                <tr style="background-color: #f8fafc; font-weight: bold;">
                    <td colspan="3" style="text-align: right; font-size: 0.95rem;">ยอดเงินรวมทั้งสิ้น (Total Amount)</td>
                    <td style="text-align: right; font-size: 1.15rem; color: #dc2626;">฿<?= number_format($receipt['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- หมายเหตุ -->
        <div class="small text-secondary" style="font-size: 0.8rem; line-height: 1.4;">
            * เอกสารฉบับนี้ออกโดยระบบอิเล็กทรอนิกส์อัตโนมัติของ TRAINUP FITNESS CLUB<br>
            * สิทธิ์การเข้าใช้บริการและสิ่งอำนวยความสะดวกจะเริ่มนับตามช่วงเวลาที่ระบุข้างต้น
        </div>
    </div>

    <!-- ส่วนลงนาม -->
    <div style="margin-top: 60px;">
        <div class="row">
            <div class="col-6 text-center">
                <div style="border-bottom: 1px dotted #94a3b8; width: 70%; margin: 0 auto 10px auto; padding-bottom: 40px;"></div>
                <div class="small fw-semibold">( <?= htmlspecialchars($receipt['fullname']) ?> )</div>
                <div class="text-secondary small" style="font-size: 0.75rem;">ผู้รับบริการ / สมาชิก</div>
            </div>
            <div class="col-6 text-center">
                <div style="border-bottom: 1px dotted #94a3b8; width: 70%; margin: 0 auto 10px auto; padding-bottom: 40px;"></div>
                <div class="small fw-semibold">( .................................................... )</div>
                <div class="text-secondary small" style="font-size: 0.75rem;">ผู้รับเงิน / แคชเชียร์ TRAINUP</div>
            </div>
        </div>
    </div>
</div>

<script>
window.onload = function() {
    window.print();
};
</script>
</body>
</html>