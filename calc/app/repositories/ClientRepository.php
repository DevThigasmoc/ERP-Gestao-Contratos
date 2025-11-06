<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ClientRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    public function findByDocument(string $doc): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE doc = :doc LIMIT 1');
        $stmt->execute([':doc' => $doc]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    public function upsert(array $data): int
    {
        $existing = null;
        if (!empty($data['doc'])) {
            $existing = $this->findByDocument($data['doc']);
        }

        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE clients SET pessoa_tipo = :pessoa_tipo, empresa_nome = :empresa_nome, contato_nome = :contato_nome, telefone = :telefone, cep = :cep, endereco = :endereco, numero = :numero, complemento = :complemento, bairro = :bairro, cidade = :cidade, uf = :uf WHERE id = :id');
            $stmt->execute([
                ':pessoa_tipo' => $data['pessoa_tipo'],
                ':empresa_nome' => $data['empresa_nome'],
                ':contato_nome' => $data['contato_nome'],
                ':telefone' => $data['telefone'],
                ':cep' => $data['cep'],
                ':endereco' => $data['endereco'],
                ':numero' => $data['numero'],
                ':complemento' => $data['complemento'],
                ':bairro' => $data['bairro'],
                ':cidade' => $data['cidade'],
                ':uf' => $data['uf'],
                ':id' => $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO clients (pessoa_tipo, doc, empresa_nome, contato_nome, telefone, cep, endereco, numero, complemento, bairro, cidade, uf, created_at) VALUES (:pessoa_tipo, :doc, :empresa_nome, :contato_nome, :telefone, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :uf, NOW())');
        $stmt->execute([
            ':pessoa_tipo' => $data['pessoa_tipo'],
            ':doc' => $data['doc'],
            ':empresa_nome' => $data['empresa_nome'],
            ':contato_nome' => $data['contato_nome'],
            ':telefone' => $data['telefone'],
            ':cep' => $data['cep'],
            ':endereco' => $data['endereco'],
            ':numero' => $data['numero'],
            ':complemento' => $data['complemento'],
            ':bairro' => $data['bairro'],
            ':cidade' => $data['cidade'],
            ':uf' => $data['uf'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
