<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Commission;
use App\Domain\CommissionInstallment;
use PDO;

final class CommissionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function create(array $data, array $installments): Commission
    {
        $stmt = $this->pdo->prepare('INSERT INTO commissions (company_id, vendor_user_id, proposal_id, subscription_id, invoice_id, base_amount, rate, total_commission, splits, created_at, updated_at) VALUES (:company_id, :vendor_user_id, :proposal_id, :subscription_id, :invoice_id, :base_amount, :rate, :total_commission, :splits, NOW(), NOW())');
        $stmt->execute([
            ':company_id' => $data['company_id'],
            ':vendor_user_id' => $data['vendor_user_id'],
            ':proposal_id' => $data['proposal_id'],
            ':subscription_id' => $data['subscription_id'],
            ':invoice_id' => $data['invoice_id'],
            ':base_amount' => $data['base_amount'],
            ':rate' => $data['rate'],
            ':total_commission' => $data['total_commission'],
            ':splits' => $data['splits'],
        ]);

        $commissionId = (int) $this->pdo->lastInsertId();
        $this->storeInstallments($commissionId, $installments);

        return $this->findById($data['company_id'], $commissionId);
    }

    private function storeInstallments(int $commissionId, array $installments): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO commission_installments (commission_id, n, due_date, amount, status, paid_at, created_at, updated_at) VALUES (:commission_id, :n, :due_date, :amount, :status, :paid_at, NOW(), NOW())');
        foreach ($installments as $installment) {
            $stmt->execute([
                ':commission_id' => $commissionId,
                ':n' => $installment['n'],
                ':due_date' => $installment['due_date'],
                ':amount' => $installment['amount'],
                ':status' => $installment['status'] ?? 'open',
                ':paid_at' => $installment['paid_at'] ?? null,
            ]);
        }
    }

    public function findById(int $companyId, int $id): ?Commission
    {
        $stmt = $this->pdo->prepare('SELECT * FROM commissions WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return Commission::fromArray($row, $this->fetchInstallments($id));
    }

    /**
     * @return CommissionInstallment[]
     */
    private function fetchInstallments(int $commissionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM commission_installments WHERE commission_id = :commission_id ORDER BY n ASC');
        $stmt->execute([':commission_id' => $commissionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $row) => CommissionInstallment::fromArray($row), $rows);
    }

    public function updateInstallmentStatus(int $companyId, int $installmentId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE commission_installments ci INNER JOIN commissions c ON c.id = ci.commission_id SET ci.status = :status, ci.paid_at = CASE WHEN :status = "paid" THEN NOW() ELSE ci.paid_at END, ci.updated_at = NOW() WHERE ci.id = :id AND c.company_id = :company_id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $installmentId,
            ':company_id' => $companyId,
        ]);
    }
}
