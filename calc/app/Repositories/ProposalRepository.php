<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProposalRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM proposals WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $companyId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM proposals WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':company_id' => $companyId,
            ':id' => $id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markAsAccepted(int $companyId, int $proposalId, array $acceptance): void
    {
        $stmt = $this->pdo->prepare('UPDATE proposals SET status = :status, accepted_at = NOW(), accepted_by = :accepted_by, accepted_ip = :accepted_ip WHERE company_id = :company_id AND id = :id');
        $stmt->execute([
            ':status' => $acceptance['status'] ?? 'accepted',
            ':accepted_by' => $acceptance['accepted_by'] ?? null,
            ':accepted_ip' => $acceptance['accepted_ip'] ?? null,
            ':company_id' => $companyId,
            ':id' => $proposalId,
        ]);
    }
}
