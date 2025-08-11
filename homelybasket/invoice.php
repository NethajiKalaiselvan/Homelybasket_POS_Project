<?php
require_once 'config/database.php';
$db = new Database();
$dbh = $db->getConnection();

$invoice_number = $_GET['invoice'] ?? '';
if (empty($invoice_number)) {
    die("Invoice not found.");
}

// Fetch bill details
$bill_stmt = $dbh->prepare("SELECT * FROM bills WHERE bill_number = ?");
$bill_stmt->execute([$invoice_number]);
$bill = $bill_stmt->fetch(PDO::FETCH_ASSOC);
if (!$bill) {
    die("Invoice not found.");
}

// Fetch bill items
$item_stmt = $dbh->prepare("SELECT bi.*, p.name as product_name FROM bill_items bi INNER JOIN products p ON bi.product_id = p.id WHERE bi.bill_id = ?");
$item_stmt->execute([$bill['id']]);
$item_result = $item_stmt;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice - <?= $invoice_number ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { text-align: center; }
        .invoice-box { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        .totals { text-align: right; margin-top: 20px; }
        .print-button { margin-top: 20px; text-align: center; }
        .print-button button { padding: 10px 20px; font-size: 16px; }

        /* Print styles for TVS 3220 Star thermal printer (80mm paper) */
        @media print {
            @page {
                margin: 0;
                size: 72mm auto;
 }
            /* Hide browser header/footer (where possible) */
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }
            .invoice-box {
                max-width: none;
                width: 72mm;
                min-width: 72mm;
                border: none;
                padding: 0 4mm;
                font-size: 12px;
            }
            table, th, td {
                border: none;
                padding: 2px 0;
                font-size: 12px;
            }
            th {
                background: none;
                font-weight: bold;
            }
            .totals {
                margin-top: 10px;
                font-size: 12px;
            }
            .print-button {
                display: none;
            }
            h2 {
                font-size: 16px;
                margin-bottom: 8px;
            }
            p, span, strong {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <h2>Homely Basket Supermarket</h2>
        <p><strong>Invoice Number:</strong> <?= $bill['bill_number'] ?></p>
        <p><strong>Date:</strong> <?= $bill['created_at'] ?></p>
        <p><strong>Customer:</strong> <?= isset($bill['name']) && $bill['name'] ? htmlspecialchars($bill['name']) : 'Walk-in Customer' ?></p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                while ($item = $item_result->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr>
                    <td><?= $count++ ?></td>
                    <td><?= $item['product_name'] ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($item['unit_price'], 2) ?></td>
                    <td><?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totals">
            <p><strong>Total Amount:</strong> ₹<?= number_format($bill['total_amount'], 2) ?></p>
            <?php if (isset($bill['paid_amount'])): ?>
                <p><strong>Paid:</strong> ₹<?= number_format($bill['paid_amount'], 2) ?></p>
            <?php endif; ?>
            <?php if (isset($bill['change_amount'])): ?>
                <p><strong>Change:</strong> ₹<?= number_format($bill['change_amount'], 2) ?></p>
            <?php endif; ?>
        </div>

        <div class="print-button">
            <button onclick="window.print()">🖨️ Print Invoice</button>
        </div>
    </div>
</body>
</html>
