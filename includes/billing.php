<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensure_tenant_billing_advances_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $sql = 'CREATE TABLE IF NOT EXISTS tenant_billing_advances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        amount_available DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        notes VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tenant_billing_advances_tenant (tenant_id),
        CONSTRAINT fk_tenant_billing_advances_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        db()->query($sql);
    } catch (Throwable $exception) {
    }
}

function tenant_billing_advance_balance(int $tenantId): float
{
    if ($tenantId <= 0) {
        return 0.0;
    }

    ensure_tenant_billing_advances_table();
    $stmt = db()->prepare('SELECT amount_available FROM tenant_billing_advances WHERE tenant_id = ? LIMIT 1');
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (float) ($row['amount_available'] ?? 0.0);
}

function recalculate_billing_invoice_totals(int $tenantId, int $billingInvoiceId): void
{
    if ($tenantId <= 0 || $billingInvoiceId <= 0) {
        return;
    }

    $mysqli = db();
    $invoiceStmt = $mysqli->prepare('SELECT amount_charged FROM tenant_billing_invoices WHERE id = ? AND tenant_id = ? LIMIT 1');
    if (!$invoiceStmt) {
        return;
    }
    $invoiceStmt->bind_param('ii', $billingInvoiceId, $tenantId);
    $invoiceStmt->execute();
    $invoice = $invoiceStmt->get_result()->fetch_assoc();
    $invoiceStmt->close();
    if (!$invoice) {
        return;
    }

    $charged = (float) ($invoice['amount_charged'] ?? 0);

    $sumStmt = $mysqli->prepare('SELECT COALESCE(SUM(amount), 0) AS total_paid FROM tenant_billing_payments WHERE tenant_id = ? AND billing_invoice_id = ?');
    if (!$sumStmt) {
        return;
    }
    $sumStmt->bind_param('ii', $tenantId, $billingInvoiceId);
    $sumStmt->execute();
    $sumRow = $sumStmt->get_result()->fetch_assoc();
    $sumStmt->close();

    $totalPaidRaw = (float) ($sumRow['total_paid'] ?? 0);
    $amountPaid = min($totalPaidRaw, $charged);
    $balance = max($charged - $totalPaidRaw, 0);

    $status = 'unpaid';
    if ($balance <= 0.00001) {
        $status = 'paid';
    } elseif ($amountPaid > 0.00001) {
        $status = 'partial';
    }

    $upd = $mysqli->prepare('UPDATE tenant_billing_invoices SET amount_paid = ?, balance = ?, payment_status = ? WHERE id = ? AND tenant_id = ?');
    if (!$upd) {
        return;
    }
    $upd->bind_param('ddsii', $amountPaid, $balance, $status, $billingInvoiceId, $tenantId);
    $upd->execute();
    $upd->close();
}

function recalculate_tenant_billing_advance(int $tenantId): void
{
    if ($tenantId <= 0) {
        return;
    }

    ensure_tenant_billing_advances_table();
    $mysqli = db();

    $sql = 'SELECT COALESCE(SUM(CASE WHEN p.total_paid > i.amount_charged THEN p.total_paid - i.amount_charged ELSE 0 END), 0) AS advance_total
            FROM tenant_billing_invoices i
            LEFT JOIN (
                SELECT billing_invoice_id, SUM(amount) AS total_paid
                FROM tenant_billing_payments
                WHERE tenant_id = ?
                GROUP BY billing_invoice_id
            ) p ON p.billing_invoice_id = i.id
            WHERE i.tenant_id = ?';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $tenantId, $tenantId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $advance = max((float) ($row['advance_total'] ?? 0), 0);
    $note = 'Auto-calculated from overpayments';

    $upsert = $mysqli->prepare('INSERT INTO tenant_billing_advances (tenant_id, amount_available, notes, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE amount_available = VALUES(amount_available), notes = VALUES(notes), updated_at = NOW()');
    if (!$upsert) {
        return;
    }
    $upsert->bind_param('ids', $tenantId, $advance, $note);
    $upsert->execute();
    $upsert->close();
}

function tenant_billing_summary(int $tenantId): array
{
    $mysqli = db();
    $summary = [
        'invoices' => 0,
        'charged' => 0.0,
        'paid' => 0.0,
        'balance' => 0.0,
        'overdue' => 0,
    ];

    $stmt = $mysqli->prepare('SELECT COUNT(*) AS invoices, COALESCE(SUM(amount_charged),0) AS charged, COALESCE(SUM(amount_paid),0) AS paid, COALESCE(SUM(balance),0) AS balance, SUM(CASE WHEN payment_status = "overdue" THEN 1 ELSE 0 END) AS overdue FROM tenant_billing_invoices WHERE tenant_id = ?');
    if (!$stmt) {
        return $summary;
    }
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $summary['invoices'] = (int) $row['invoices'];
        $summary['charged'] = (float) $row['charged'];
        $summary['paid'] = (float) $row['paid'];
        $summary['balance'] = (float) $row['balance'];
        $summary['overdue'] = (int) $row['overdue'];
    }

    return $summary;
}
