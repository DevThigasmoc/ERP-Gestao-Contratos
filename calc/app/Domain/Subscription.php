<?php
declare(strict_types=1);

namespace App\Domain;

final class Subscription
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $companyId,
        public readonly int $proposalId,
        public readonly ?int $vendorUserId,
        public readonly string $customerDoc,
        public readonly string $customerName,
        public readonly string $planKey,
        public readonly int $usersQtd,
        public readonly float $basePrice,
        public readonly float $pagueEmDiaPercent,
        public readonly string $status,
        public readonly string $startDate,
        public readonly ?string $endDate,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            companyId: (int) $data['company_id'],
            proposalId: (int) $data['proposal_id'],
            customerDoc: (string) $data['customer_doc'],
            customerName: (string) $data['customer_name'],
            vendorUserId: isset($data['vendor_user_id']) ? (int) $data['vendor_user_id'] : null,
            planKey: (string) $data['plan_key'],
            usersQtd: (int) $data['users_qtd'],
            basePrice: (float) $data['base_price'],
            pagueEmDiaPercent: (float) ($data['pague_em_dia_percent'] ?? 0),
            status: (string) ($data['status'] ?? 'active'),
            startDate: (string) $data['start_date'],
            endDate: $data['end_date'] ?? null,
        );
    }
}
