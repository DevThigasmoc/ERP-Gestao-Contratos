<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function all(array $filters = []): array
    {
        $sql = 'SELECT * FROM users';
        $conditions = [];
        $params = [];

        if (isset($filters['perfil']) && $filters['perfil'] !== '') {
            $conditions[] = 'perfil = :perfil';
            $params[':perfil'] = $filters['perfil'];
        }

        if (isset($filters['ativo'])) {
            $conditions[] = 'ativo = :ativo';
            $params[':ativo'] = (int) $filters['ativo'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY nome ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (nome, email, password_hash, perfil, ativo, created_at, updated_at) VALUES (:nome, :email, :password_hash, :perfil, :ativo, NOW(), NOW())');
        $stmt->execute([
            ':nome' => $data['nome'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':perfil' => $data['perfil'],
            ':ativo' => $data['ativo'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = ['nome = :nome', 'email = :email', 'perfil = :perfil', 'ativo = :ativo', 'updated_at = NOW()'];
        $params = [
            ':id' => $id,
            ':nome' => $data['nome'],
            ':email' => $data['email'],
            ':perfil' => $data['perfil'],
            ':ativo' => $data['ativo'] ?? 1,
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $data['password_hash'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET ativo = 0, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
