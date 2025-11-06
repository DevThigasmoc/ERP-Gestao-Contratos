<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Subscription;
use PDO;

final class SubscriptionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function createOrUpdateFromProposal(int $companyId, array $proposal, array $payload): Subscription
    {
        $subscription = $this->findByProposal($companyId, (int) $proposal['id']);
        $params = [
            ':company_id' => $companyId,
            ':proposal_id' => $proposal['id'],
            ':customer_doc' => $payload['customer_doc'],
            ':customer_name' => $payload['customer_name'],
            ':vendor_user_id' => $payload['vendor_user_id'] ?? null,
            ':plan_key' => $payload['plan_key'],
            ':users_qtd' => $payload['users_qtd'],
            ':base_price' => $payload['base_price'],
            ':pague_em_dia_percent' => $payload['pague_em_dia_percent'] ?? 0,
            ':status' => $payload['status'] ?? 'active',
            ':start_date' => $payload['start_date'],
            ':end_date' => $payload['end_date'] ?? null,
        ];

        if ($subscription) {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET customer_doc = :customer_doc, customer_name = :customer_name, vendor_user_id = :vendor_user_id, plan_key = :plan_key, users_qtd = :users_qtd, base_price = :base_price, pague_em_dia_percent = :pague_em_dia_percent, status = :status, start_date = :start_date, end_date = :end_date, updated_at = NOW() WHERE id = :id AND company_id = :company_id');
            $params[':id'] = $subscription->id;
            $stmt->execute($params);
            return $this->findById($companyId, $subscription->id);
        }

        $stmt = $this->pdo->prepare('INSERT INTO subscriptions (company_id, proposal_id, customer_doc, customer_name, vendor_user_id, plan_key, users_qtd, base_price, pague_em_dia_percent, status, start_date, end_date, created_at, updated_at) VALUES (:company_id, :proposal_id, :customer_doc, :customer_name, :vendor_user_id, :plan_key, :users_qtd, :base_price, :pague_em_dia_percent, :status, :start_date, :end_date, NOW(), NOW())');
        $stmt->execute($params);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($companyId, $id);
    }

    public function findByProposal(int $companyId, int $proposalId): ?Subscription
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE company_id = :company_id AND proposal_id = :proposal_id LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':proposal_id' => $proposalId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Subscription::fromArray($row) : null;
    }

    public function findById(int $companyId, int $id): ?Subscription
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Subscription::fromArray($row) : null;
    }

    public function listActiveByCompany(int $companyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE company_id = :company_id AND status = "active" ORDER BY start_date DESC');
        $stmt->execute([':company_id' => $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $item) => Subscription::fromArray($item), $rows);
    }

    public function updateStatus(int $companyId, int $subscriptionId, string $status, ?string $endDate = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = :status, end_date = :end_date, updated_at = NOW() WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':status' => $status,
            ':end_date' => $endDate,
            ':company_id' => $companyId,
            ':id' => $subscriptionId,
        ]);
    }

    public function updatePricing(int $companyId, int $subscriptionId, float $basePrice, float $paguePercent): void
    {
        $stmt = $this->pdo->prepare('UPDATE subscriptions SET base_price = :base_price, pague_em_dia_percent = :pague, updated_at = NOW() WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':base_price' => $basePrice,
            ':pague' => $paguePercent,
            ':company_id' => $companyId,
            ':id' => $subscriptionId,
        ]);
    }
}
