<?php
declare(strict_types=1);

namespace App\Finance;

use App\Domain\Invoice;
use App\Domain\Subscription;
use App\Repositories\InvoiceRepository;
use App\Repositories\SubscriptionRepository;
use DateTimeImmutable;
use RuntimeException;

final class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EfiClient $efiClient,
        private readonly string $pixKey,
    ) {
    }

    /**
     * @return Invoice[]
     */
    public function generateMonthlyInvoices(int $companyId, string $referenceMonth): array
    {
        $referenceDate = DateTimeImmutable::createFromFormat('Y-m', $referenceMonth) ?: new DateTimeImmutable('first day of this month');
        $start = $referenceDate->format('Y-m-01');
        $dueDate = $referenceDate->format('Y-m-') . '10';

        $subscriptions = $this->subscriptionRepository->listActiveByCompany($companyId);
        $invoices = [];

        foreach ($subscriptions as $subscription) {
            $invoices[] = $this->createInvoiceForSubscription($companyId, $subscription, $start, $dueDate);
        }

        return $invoices;
    }

    private function createInvoiceForSubscription(int $companyId, Subscription $subscription, string $competence, string $dueDate): Invoice
    {
        $gross = $subscription->basePrice * $subscription->usersQtd;
        $discount = 0.0;
        if ($subscription->pagueEmDiaPercent > 0) {
            $discount = $gross * ($subscription->pagueEmDiaPercent / 100);
        }
        $net = max($gross - $discount, 0);

        $document = preg_replace('/\D+/', '', $subscription->customerDoc);
        $payerKey = strlen((string) $document) === 14 ? 'cnpj' : 'cpf';

        $payload = [
            'calendario' => ['expiracao' => 3600],
            'devedor' => [
                $payerKey => $document,
                'nome' => $subscription->customerName,
            ],
            'valor' => [
                'original' => number_format($net, 2, '.', ''),
            ],
            'chave' => $this->pixKey,
            'solicitacaoPagador' => 'Assinatura ' . $subscription->planKey,
        ];

        $response = $this->efiClient->createChargePix($payload);

        if (!isset($response['txid'])) {
            throw new RuntimeException('Falha ao criar cobrança PIX');
        }

        return $this->invoiceRepository->create([
            'company_id' => $companyId,
            'subscription_id' => $subscription->id,
            'vendor_user_id' => $subscription->vendorUserId,
            'efi_charge_id' => $response['txid'] ?? null,
            'number' => $response['txid'] ?? null,
            'txid' => $response['txid'] ?? null,
            'due_date' => $dueDate,
            'amount_gross' => $gross,
            'amount_discount' => $discount,
            'amount_net' => $net,
            'status' => 'pending',
            'payment_method' => 'pix',
        ]);
    }
}
