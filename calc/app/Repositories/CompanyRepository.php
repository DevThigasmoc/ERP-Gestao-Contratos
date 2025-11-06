<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CompanyRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM companies ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO companies (name, document, created_at, updated_at) VALUES (:name, :document, NOW(), NOW())');
        $stmt->execute([
            ':name' => $data['name'],
            ':document' => $data['document'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
