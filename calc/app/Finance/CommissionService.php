<?php
declare(strict_types=1);

namespace App\Finance;

use App\Repositories\CommissionRepository;
use DateInterval;
use DateTimeImmutable;

final class CommissionService
{
    public function __construct(
        private readonly CommissionRepository $commissionRepository,
        private readonly float $defaultRate,
        private readonly int $splits,
        private readonly int $dueDay,
    ) {
    }

    public function createFromPaidInvoice(array $invoice, array $subscription, int $companyId): void
    {
        if (empty($subscription['vendor_user_id'])) {
            return;
        }

        $baseAmount = (float) $invoice['amount_net'];
        $config = \load_company_finance_config($companyId, [
            'rate' => $this->defaultRate,
            'splits' => $this->splits,
            'due_day' => $this->dueDay,
        ]);

        $rate = $subscription['commission_rate'] ?? (float) ($config['rate'] ?? $this->defaultRate);
        $splits = (int) ($config['splits'] ?? $this->splits);
        $dueDay = (int) ($config['due_day'] ?? $this->dueDay);

        $totalCommission = round($baseAmount * $rate, 2);
        if ($totalCommission <= 0) {
            return;
        }

        $installments = $this->buildInstallments($totalCommission, $splits, $dueDay);

        $this->commissionRepository->create([
            'company_id' => $companyId,
            'vendor_user_id' => (int) $subscription['vendor_user_id'],
            'proposal_id' => (int) $subscription['proposal_id'],
            'subscription_id' => (int) $subscription['id'],
            'invoice_id' => (int) $invoice['id'],
            'base_amount' => $baseAmount,
            'rate' => $rate,
            'total_commission' => $totalCommission,
            'splits' => $splits,
        ], $installments);
    }

    private function buildInstallments(float $total, int $splits, int $dueDay): array
    {
        $baseValue = floor(($total / $splits) * 100) / 100;
        $installments = [];
        $accumulated = 0.0;
        $dueDate = new DateTimeImmutable('first day of next month');
        for ($i = 1; $i <= $splits; $i++) {
            $amount = $i === $splits ? round($total - $accumulated, 2) : $baseValue;
            $accumulated += $amount;
            $installments[] = [
                'n' => $i,
                'due_date' => $dueDate->setDate((int) $dueDate->format('Y'), (int) $dueDate->format('m'), $dueDay)->format('Y-m-d'),
                'amount' => $amount,
                'status' => 'open',
            ];
            $dueDate = $dueDate->add(new DateInterval('P1M'));
        }

        return $installments;
    }
}
