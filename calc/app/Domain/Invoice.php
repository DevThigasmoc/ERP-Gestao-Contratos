<?php
declare(strict_types=1);

namespace App\Domain;

final class Invoice
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $companyId,
        public readonly int $subscriptionId,
        public readonly ?int $vendorUserId,
        public readonly ?string $efiChargeId,
        public readonly ?string $number,
        public readonly string $dueDate,
        public readonly float $amountGross,
        public readonly float $amountDiscount,
        public readonly float $amountNet,
        public readonly string $status,
        public readonly ?string $paidAt,
        public readonly string $paymentMethod,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            companyId: (int) $data['company_id'],
            subscriptionId: (int) $data['subscription_id'],
            vendorUserId: isset($data['vendor_user_id']) ? (int) $data['vendor_user_id'] : null,
            efiChargeId: $data['efi_charge_id'] ?? null,
            number: $data['number'] ?? null,
            dueDate: (string) $data['due_date'],
            amountGross: (float) $data['amount_gross'],
            amountDiscount: (float) ($data['amount_discount'] ?? 0),
            amountNet: (float) $data['amount_net'],
            status: (string) $data['status'],
            paidAt: $data['paid_at'] ?? null,
            paymentMethod: $data['payment_method'] ?? 'pix',
        );
    }
}
