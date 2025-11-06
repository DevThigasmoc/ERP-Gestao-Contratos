<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ContractRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO contracts (proposal_id, pdf_path, status, signed_at, created_at) VALUES (:proposal_id, :pdf_path, :status, :signed_at, NOW())');
        $stmt->execute([
            ':proposal_id' => $data['proposal_id'],
            ':pdf_path' => $data['pdf_path'],
            ':status' => $data['status'],
            ':signed_at' => $data['signed_at'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function list(array $filters = [], ?array $user = null): array
    {
        $sql = 'SELECT ct.*, p.status AS proposal_status, c.empresa_nome, u.nome AS vendedor_nome FROM contracts ct JOIN proposals p ON p.id = ct.proposal_id JOIN clients c ON c.id = p.client_id JOIN users u ON u.id = p.user_id';
        $conditions = [];
        $params = [];

        if ($user && $user['perfil'] === 'vendedor') {
            $conditions[] = 'p.user_id = :user_id';
            $params[':user_id'] = $user['id'];
        } elseif (!empty($filters['user_id'])) {
            $conditions[] = 'p.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'ct.status = :status';
            $params[':status'] = $filters['status'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY ct.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $contract = $stmt->fetch();
        return $contract ?: null;
    }
}
