<?php
declare(strict_types=1);

namespace App\Domain;

final class CommissionInstallment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $commissionId,
        public readonly int $n,
        public readonly string $dueDate,
        public readonly float $amount,
        public readonly string $status,
        public readonly ?string $paidAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            commissionId: (int) $data['commission_id'],
            n: (int) $data['n'],
            dueDate: (string) $data['due_date'],
            amount: (float) $data['amount'],
            status: (string) $data['status'],
            paidAt: $data['paid_at'] ?? null,
        );
    }
}
