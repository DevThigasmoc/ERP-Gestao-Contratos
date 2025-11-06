<?php
declare(strict_types=1);

namespace App\Domain;

final class Commission
{
    /** @param CommissionInstallment[] $installments */
    public function __construct(
        public readonly ?int $id,
        public readonly int $companyId,
        public readonly int $vendorUserId,
        public readonly int $proposalId,
        public readonly int $subscriptionId,
        public readonly int $invoiceId,
        public readonly float $baseAmount,
        public readonly float $rate,
        public readonly float $totalCommission,
        public readonly int $splits,
        public readonly array $installments = [],
    ) {
    }

    public static function fromArray(array $data, array $installments = []): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            companyId: (int) $data['company_id'],
            vendorUserId: (int) $data['vendor_user_id'],
            proposalId: (int) $data['proposal_id'],
            subscriptionId: (int) $data['subscription_id'],
            invoiceId: (int) $data['invoice_id'],
            baseAmount: (float) $data['base_amount'],
            rate: (float) $data['rate'],
            totalCommission: (float) $data['total_commission'],
            splits: (int) ($data['splits'] ?? count($installments) ?: 6),
            installments: $installments,
        );
    }
}
