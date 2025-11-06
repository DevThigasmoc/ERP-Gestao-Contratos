<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

class ProposalRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, 
            c.pessoa_tipo AS client_pessoa_tipo,
            c.doc AS client_doc,
            c.empresa_nome AS client_empresa_nome,
            c.contato_nome AS client_contato_nome,
            c.telefone AS client_telefone,
            c.cep AS client_cep,
            c.endereco AS client_endereco,
            c.numero AS client_numero,
            c.complemento AS client_complemento,
            c.bairro AS client_bairro,
            c.cidade AS client_cidade,
            c.uf AS client_uf,
            u.nome AS vendedor_nome,
            u.email AS vendedor_email
            FROM proposals p 
            JOIN clients c ON c.id = p.client_id 
            JOIN users u ON u.id = p.user_id 
            WHERE p.id = :id');
        $stmt->execute([':id' => $id]);
        $proposal = $stmt->fetch();
        return $proposal ?: null;
    }

    public function findItems(int $proposalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM proposal_items WHERE proposal_id = :id ORDER BY id');
        $stmt->execute([':id' => $proposalId]);
        return $stmt->fetchAll();
    }

    public function findByShareToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, 
            c.pessoa_tipo AS client_pessoa_tipo,
            c.doc AS client_doc,
            c.empresa_nome AS client_empresa_nome,
            c.contato_nome AS client_contato_nome,
            c.telefone AS client_telefone,
            c.cep AS client_cep,
            c.endereco AS client_endereco,
            c.numero AS client_numero,
            c.complemento AS client_complemento,
            c.bairro AS client_bairro,
            c.cidade AS client_cidade,
            c.uf AS client_uf,
            u.nome AS vendedor_nome,
            u.email AS vendedor_email
            FROM proposals p 
            JOIN clients c ON c.id = p.client_id 
            JOIN users u ON u.id = p.user_id 
            WHERE p.share_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $proposal = $stmt->fetch();
        return $proposal ?: null;
    }

    public function create(array $data, array $items): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO proposals (user_id, client_id, plano_key, usuarios_qtd, descontos_mensal, descontos_addons, pague_em_dia_percent, mensalidade_base, mensalidade_pague, primeiro_venc_mensal, implantacao_tipo, implantacao_valor, implantacao_parcelas, primeiro_venc_implant, addons_json, texto_proposta, status, share_token, pdf_path, created_at, updated_at) VALUES (:user_id, :client_id, :plano_key, :usuarios_qtd, :descontos_mensal, :descontos_addons, :pague_em_dia_percent, :mensalidade_base, :mensalidade_pague, :primeiro_venc_mensal, :implantacao_tipo, :implantacao_valor, :implantacao_parcelas, :primeiro_venc_implant, :addons_json, :texto_proposta, :status, :share_token, :pdf_path, NOW(), NOW())');
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':client_id' => $data['client_id'],
                ':plano_key' => $data['plano_key'],
                ':usuarios_qtd' => $data['usuarios_qtd'],
                ':descontos_mensal' => $data['descontos_mensal'],
                ':descontos_addons' => $data['descontos_addons'],
                ':pague_em_dia_percent' => $data['pague_em_dia_percent'],
                ':mensalidade_base' => $data['mensalidade_base'],
                ':mensalidade_pague' => $data['mensalidade_pague'],
                ':primeiro_venc_mensal' => $data['primeiro_venc_mensal'],
                ':implantacao_tipo' => $data['implantacao_tipo'],
                ':implantacao_valor' => $data['implantacao_valor'],
                ':implantacao_parcelas' => $data['implantacao_parcelas'],
                ':primeiro_venc_implant' => $data['primeiro_venc_implant'],
                ':addons_json' => $data['addons_json'],
                ':texto_proposta' => $data['texto_proposta'],
                ':status' => $data['status'],
                ':share_token' => $data['share_token'],
                ':pdf_path' => $data['pdf_path'],
            ]);
            $proposalId = (int) $this->pdo->lastInsertId();

            $this->insertItems($proposalId, $items);

            $this->pdo->commit();
            return $proposalId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE proposals SET plano_key = :plano_key, usuarios_qtd = :usuarios_qtd, descontos_mensal = :descontos_mensal, descontos_addons = :descontos_addons, pague_em_dia_percent = :pague_em_dia_percent, mensalidade_base = :mensalidade_base, mensalidade_pague = :mensalidade_pague, primeiro_venc_mensal = :primeiro_venc_mensal, implantacao_tipo = :implantacao_tipo, implantacao_valor = :implantacao_valor, implantacao_parcelas = :implantacao_parcelas, primeiro_venc_implant = :primeiro_venc_implant, addons_json = :addons_json, texto_proposta = :texto_proposta, status = :status, pdf_path = :pdf_path, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':plano_key' => $data['plano_key'],
                ':usuarios_qtd' => $data['usuarios_qtd'],
                ':descontos_mensal' => $data['descontos_mensal'],
                ':descontos_addons' => $data['descontos_addons'],
                ':pague_em_dia_percent' => $data['pague_em_dia_percent'],
                ':mensalidade_base' => $data['mensalidade_base'],
                ':mensalidade_pague' => $data['mensalidade_pague'],
                ':primeiro_venc_mensal' => $data['primeiro_venc_mensal'],
                ':implantacao_tipo' => $data['implantacao_tipo'],
                ':implantacao_valor' => $data['implantacao_valor'],
                ':implantacao_parcelas' => $data['implantacao_parcelas'],
                ':primeiro_venc_implant' => $data['primeiro_venc_implant'],
                ':addons_json' => $data['addons_json'],
                ':texto_proposta' => $data['texto_proposta'],
                ':status' => $data['status'],
                ':pdf_path' => $data['pdf_path'],
                ':id' => $id,
            ]);

            $this->deleteItems($id);
            $this->insertItems($id, $items);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertItems(int $proposalId, array $items): void
    {
        if (!$items) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO proposal_items (proposal_id, item_label, per_user, unit_price, qty, subtotal) VALUES (:proposal_id, :item_label, :per_user, :unit_price, :qty, :subtotal)');
        foreach ($items as $item) {
            $stmt->execute([
                ':proposal_id' => $proposalId,
                ':item_label' => $item['item_label'],
                ':per_user' => $item['per_user'],
                ':unit_price' => $item['unit_price'],
                ':qty' => $item['qty'],
                ':subtotal' => $item['subtotal'],
            ]);
        }
    }

    private function deleteItems(int $proposalId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM proposal_items WHERE proposal_id = :id');
        $stmt->execute([':id' => $proposalId]);
    }

    public function updateStatus(int $proposalId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE proposals SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $proposalId,
        ]);
    }

    public function updatePdfPath(int $proposalId, string $path): void
    {
        $stmt = $this->pdo->prepare('UPDATE proposals SET pdf_path = :pdf_path, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':pdf_path' => $path,
            ':id' => $proposalId,
        ]);
    }

    public function list(array $filters = [], ?array $user = null): array
    {
        $sql = 'SELECT p.*, c.empresa_nome, u.nome AS vendedor_nome FROM proposals p JOIN clients c ON c.id = p.client_id JOIN users u ON u.id = p.user_id';
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
            $conditions[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $conditions[] = 'DATE(p.created_at) >= :from';
            $params[':from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'DATE(p.created_at) <= :to';
            $params[':to'] = $filters['to'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
