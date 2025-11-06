<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function allActiveByCompany(int $companyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE company_id = :company_id AND active = 1 ORDER BY sort_order ASC, label ASC');
        $stmt->execute([':company_id' => $companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE company_id = :company_id AND key_slug = :slug LIMIT 1');
        $stmt->execute([
            ':company_id' => $companyId,
            ':slug' => $slug,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(int $companyId, array $data): array
    {
        $existing = $this->findBySlug($companyId, $data['key_slug']);

        $payload = [
            ':company_id' => $companyId,
            ':type' => $data['type'],
            ':key_slug' => $data['key_slug'],
            ':label' => $data['label'],
            ':unit_price' => $data['unit_price'],
            ':per_user' => $data['per_user'] ?? 0,
            ':recurring' => $data['recurring'] ?? 0,
            ':billing_cycle' => $data['billing_cycle'] ?? 'monthly',
            ':max_discount_percent' => $data['max_discount_percent'] ?? 0,
            ':active' => $data['active'] ?? 1,
            ':sort_order' => $data['sort_order'] ?? 0,
        ];

        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE products SET type = :type, label = :label, unit_price = :unit_price, per_user = :per_user, recurring = :recurring, billing_cycle = :billing_cycle, max_discount_percent = :max_discount_percent, active = :active, sort_order = :sort_order, updated_at = NOW() WHERE company_id = :company_id AND key_slug = :key_slug');
            $stmt->execute($payload);
            return $this->findBySlug($companyId, $data['key_slug']);
        }

        $stmt = $this->pdo->prepare('INSERT INTO products (company_id, type, key_slug, label, unit_price, per_user, recurring, billing_cycle, max_discount_percent, active, sort_order, created_at, updated_at) VALUES (:company_id, :type, :key_slug, :label, :unit_price, :per_user, :recurring, :billing_cycle, :max_discount_percent, :active, :sort_order, NOW(), NOW())');
        $stmt->execute($payload);

        return $this->findBySlug($companyId, $data['key_slug']);
    }
}
