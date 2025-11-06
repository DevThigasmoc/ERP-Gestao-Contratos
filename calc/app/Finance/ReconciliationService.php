<?php
declare(strict_types=1);

namespace App\Finance;

use App\Repositories\InvoiceRepository;
use App\Repositories\SubscriptionRepository;
use DateTimeImmutable;

final class ReconciliationService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CommissionService $commissionService,
    ) {
    }

    public function handleEfiWebhook(array $payload): void
    {
        if (!isset($payload['txid'])) {
            return;
        }

        $companyId = (int) ($payload['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $invoice = $this->invoiceRepository->findByCharge($companyId, $payload['txid']);
        if ($invoice === null) {
            return;
        }

        if ($invoice->status === 'paid') {
            return;
        }

        $paidAt = $payload['pagamento']['horario'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $updated = $this->invoiceRepository->updateStatusByCharge($companyId, $payload['txid'], 'paid', $paidAt);
        if ($updated === null) {
            return;
        }

        $subscription = $this->subscriptionRepository->findById($companyId, $invoice->subscriptionId);
        if ($subscription === null) {
            return;
        }

        $this->commissionService->createFromPaidInvoice([
            'id' => $invoice->id,
            'amount_net' => $invoice->amountNet,
        ], [
            'id' => $subscription->id,
            'proposal_id' => $subscription->proposalId,
            'vendor_user_id' => $subscription->vendorUserId,
        ], $companyId);
    }
}
