<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Invoice;
use PDO;

final class InvoiceRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function create(array $data): Invoice
    {
        $stmt = $this->pdo->prepare('INSERT INTO invoices (company_id, subscription_id, vendor_user_id, efi_charge_id, number, txid, due_date, amount_gross, amount_discount, amount_net, status, paid_at, payment_method, created_at, updated_at) VALUES (:company_id, :subscription_id, :vendor_user_id, :efi_charge_id, :number, :txid, :due_date, :amount_gross, :amount_discount, :amount_net, :status, :paid_at, :payment_method, NOW(), NOW())');
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':subscription_id' => $data['subscription_id'],
            ':vendor_user_id' => $data['vendor_user_id'] ?? null,
            ':efi_charge_id' => $data['efi_charge_id'] ?? null,
            ':number' => $data['number'] ?? null,
            ':txid' => $data['txid'] ?? $data['efi_charge_id'] ?? null,
            ':due_date' => $data['due_date'],
            ':amount_gross' => $data['amount_gross'],
            ':amount_discount' => $data['amount_discount'] ?? 0,
            ':amount_net' => $data['amount_net'],
            ':status' => $data['status'] ?? 'pending',
            ':paid_at' => $data['paid_at'] ?? null,
            ':payment_method' => $data['payment_method'] ?? 'pix',
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($data['company_id'], $id);
    }

    public function updateStatusByCharge(int $companyId, string $chargeId, string $status, ?string $paidAt = null): ?Invoice
    {
        $stmt = $this->pdo->prepare('UPDATE invoices SET status = :status, paid_at = :paid_at, updated_at = NOW() WHERE company_id = :company_id AND (efi_charge_id = :charge OR txid = :charge)');
        $stmt->execute([
            ':status' => $status,
            ':paid_at' => $paidAt,
            ':company_id' => $companyId,
            ':charge' => $chargeId,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->findByCharge($companyId, $chargeId);
    }

    public function findById(int $companyId, int $id): ?Invoice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Invoice::fromArray($row) : null;
    }

    public function findByCharge(int $companyId, string $chargeId): ?Invoice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoices WHERE company_id = :company_id AND (efi_charge_id = :charge OR txid = :charge) ORDER BY id DESC LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':charge' => $chargeId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Invoice::fromArray($row) : null;
    }

    public function updateStatus(int $companyId, int $invoiceId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE invoices SET status = :status, updated_at = NOW() WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':status' => $status,
            ':company_id' => $companyId,
            ':id' => $invoiceId,
        ]);
    }
}
