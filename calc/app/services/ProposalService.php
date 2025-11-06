<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../repositories/ClientRepository.php';
require_once __DIR__ . '/../repositories/ProposalRepository.php';
require_once __DIR__ . '/../repositories/ContractRepository.php';

class ProposalService
{
    private ClientRepository $clients;
    private ProposalRepository $proposals;
    private ContractRepository $contracts;
    private array $config;

    public function __construct(array $config)
    {
        $this->clients = new ClientRepository();
        $this->proposals = new ProposalRepository();
        $this->contracts = new ContractRepository();
        $this->config = $config;
    }

    public function saveProposal(array $input, array $user): array
    {
        $clientData = $this->extractClient($input);
        if ($clientData['doc'] === '') {
            throw new RuntimeException('Informe um CPF ou CNPJ válido.');
        }
        $clientId = $this->clients->upsert($clientData);

        $proposalData = $this->extractProposal($input, $clientId, $user['id']);
        $items = $this->extractItems($input);
        $proposalData['addons_json'] = json_encode($items, JSON_UNESCAPED_UNICODE);
        $proposalId = isset($input['proposal_id']) && $input['proposal_id'] ? (int) $input['proposal_id'] : null;

        if (!$proposalId) {
            $proposalData['share_token'] = random_token(32);
            $proposalId = $this->proposals->create($proposalData, $items);
        } else {
            $existing = $this->proposals->findById($proposalId);
            if (!$existing) {
                throw new RuntimeException('Proposta não encontrada.');
            }
            if ($user['perfil'] === 'vendedor' && (int) $existing['user_id'] !== (int) $user['id']) {
                throw new RuntimeException('Você não possui permissão para editar esta proposta.');
            }
            $proposalData['share_token'] = $existing['share_token'];
            $this->proposals->update($proposalId, $proposalData, $items);
        }

        $proposal = $this->proposals->findById($proposalId);
        $items = $this->proposals->findItems($proposalId);
        $client = $this->clients->findById((int) $proposal['client_id']);

        $html = $this->renderProposalHtml($proposal, $client, $items, $user);
        $filePath = $this->storeDocument($this->config['proposal_storage'], $proposalId, 'proposta', $html);
        $this->proposals->updatePdfPath($proposalId, $filePath);

        record_audit($user['id'], 'salvou_proposta', 'proposal', $proposalId, ['status' => $proposal['status']]);

        return ['id' => $proposalId, 'share_token' => $proposal['share_token'], 'pdf_path' => $filePath];
    }

    public function acceptProposal(int $proposalId, array $user): void
    {
        $proposal = $this->proposals->findById($proposalId);
        if (!$proposal) {
            throw new RuntimeException('Proposta não encontrada.');
        }
        if ($user['perfil'] === 'vendedor' && (int) $proposal['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('Você não possui permissão para aceitar esta proposta.');
        }

        $this->proposals->updateStatus($proposalId, 'aceita');
        record_audit($user['id'], 'aceitou_proposta', 'proposal', $proposalId, []);
    }

    public function closeProposal(int $proposalId, array $user): void
    {
        $proposal = $this->proposals->findById($proposalId);
        if (!$proposal) {
            throw new RuntimeException('Proposta não encontrada.');
        }
        if ($user['perfil'] === 'vendedor' && (int) $proposal['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('Você não possui permissão para fechar esta proposta.');
        }
        $this->proposals->updateStatus($proposalId, 'fechada');
        record_audit($user['id'], 'fechou_proposta', 'proposal', $proposalId, []);
    }

    public function generateContract(int $proposalId, array $user): array
    {
        $proposal = $this->proposals->findById($proposalId);
        if (!$proposal) {
            throw new RuntimeException('Proposta não encontrada.');
        }
        if ($proposal['status'] !== 'aceita') {
            throw new RuntimeException('A proposta precisa estar aceita para gerar contrato.');
        }
        if ($user['perfil'] === 'vendedor' && (int) $proposal['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('Você não possui permissão para gerar contrato.');
        }

        $items = $this->proposals->findItems($proposalId);
        $client = $this->clients->findById((int) $proposal['client_id']);
        $html = $this->renderContractHtml($proposal, $client, $items);
        $filePath = $this->storeDocument($this->config['contract_storage'], $proposalId, 'contrato', $html);

        $contractId = $this->contracts->create([
            'proposal_id' => $proposalId,
            'pdf_path' => $filePath,
            'status' => 'gerado',
            'signed_at' => null,
        ]);

        record_audit($user['id'], 'gerou_contrato', 'contract', $contractId, []);

        return ['id' => $contractId, 'pdf_path' => $filePath];
    }

    private function extractClient(array $input): array
    {
        return [
            'pessoa_tipo' => $input['cliente_pessoa_tipo'] ?? 'PJ',
            'doc' => preg_replace('/\D+/', '', $input['cliente_doc'] ?? ''),
            'empresa_nome' => trim($input['cliente_empresa'] ?? ''),
            'contato_nome' => trim($input['cliente_contato'] ?? ''),
            'telefone' => trim($input['cliente_telefone'] ?? ''),
            'cep' => preg_replace('/\D+/', '', $input['cliente_cep'] ?? ''),
            'endereco' => trim($input['cliente_endereco'] ?? ''),
            'numero' => trim($input['cliente_numero'] ?? ''),
            'complemento' => trim($input['cliente_complemento'] ?? ''),
            'bairro' => trim($input['cliente_bairro'] ?? ''),
            'cidade' => trim($input['cliente_cidade'] ?? ''),
            'uf' => strtoupper(substr(trim($input['cliente_uf'] ?? ''), 0, 2)),
        ];
    }

    private function extractProposal(array $input, int $clientId, int $userId): array
    {
        $shareToken = $input['share_token'] ?? random_token(32);
        $primeiroVencMensal = $input['primeiro_venc_mensal'] ?? null;
        $primeiroVencImplant = $input['primeiro_venc_implant'] ?? null;

        return [
            'user_id' => $userId,
            'client_id' => $clientId,
            'plano_key' => $input['plano_key'] ?? 'kavvi_start',
            'usuarios_qtd' => (int) ($input['usuarios_qtd'] ?? 1),
            'descontos_mensal' => parse_decimal($input['descontos_mensal'] ?? 0),
            'descontos_addons' => parse_decimal($input['descontos_addons'] ?? 0),
            'pague_em_dia_percent' => parse_decimal($input['pague_em_dia_percent'] ?? 0),
            'mensalidade_base' => parse_decimal($input['mensalidade_base'] ?? 0),
            'mensalidade_pague' => parse_decimal($input['mensalidade_pague'] ?? 0),
            'primeiro_venc_mensal' => $primeiroVencMensal ?: null,
            'implantacao_tipo' => $input['implantacao_tipo'] ?? 'única',
            'implantacao_valor' => parse_decimal($input['implantacao_valor'] ?? 0),
            'implantacao_parcelas' => (int) ($input['implantacao_parcelas'] ?? 1),
            'primeiro_venc_implant' => $primeiroVencImplant ?: null,
            'addons_json' => '[]',
            'texto_proposta' => $input['texto_proposta'] ?? '',
            'status' => $input['status'] ?? 'rascunho',
            'share_token' => $shareToken,
            'pdf_path' => $input['pdf_path'] ?? '',
        ];
    }

    private function extractItems(array $input): array
    {
        $labels = $input['item_label'] ?? [];
        $perUser = $input['item_per_user'] ?? [];
        $unitPrice = $input['item_unit_price'] ?? [];
        $qty = $input['item_qty'] ?? [];
        $subtotal = $input['item_subtotal'] ?? [];

        $items = [];
        foreach ($labels as $index => $label) {
            if (trim($label) === '') {
                continue;
            }
            $items[] = [
                'item_label' => trim($label),
                'per_user' => !empty($perUser[$index]) ? 1 : 0,
                'unit_price' => parse_decimal($unitPrice[$index] ?? 0),
                'qty' => parse_decimal($qty[$index] ?? 0),
                'subtotal' => parse_decimal($subtotal[$index] ?? 0),
            ];
        }

        return $items;
    }

    private function renderProposalHtml(array $proposal, array $client, array $items, array $user): string
    {
        $planLabel = $this->config['plans'][$proposal['plano_key']]['label'] ?? strtoupper($proposal['plano_key']);
        $itemsRows = '';
        foreach ($items as $item) {
            $itemsRows .= sprintf(
                '<tr><td>%s%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                sanitize($item['item_label']),
                $item['per_user'] ? ' <small>(por usuário)</small>' : '',
                sanitize(number_format((float) $item['unit_price'], 2, ',', '.')),
                sanitize(number_format((float) $item['qty'], 2, ',', '.')),
                sanitize(number_format((float) $item['subtotal'], 2, ',', '.'))
            );
        }

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Proposta #' . $proposal['id'] . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;color:#1F2933;margin:40px;}h1{color:#0f766e;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #94a3b8;padding:8px;text-align:left;}th{background:#e2e8f0;}footer{margin-top:30px;font-size:12px;color:#475569;}</style>';
        $html .= '</head><body>';
        $html .= '<header><h1>Proposta Comercial - ' . sanitize($planLabel) . '</h1>';
        $html .= '<p><strong>Cliente:</strong> ' . sanitize($client['empresa_nome'] ?: $client['contato_nome']) . ' (' . sanitize($client['doc']) . ')</p>';
        $html .= '<p><strong>Vendedor:</strong> ' . sanitize($user['nome']) . ' (' . sanitize($user['email']) . ')</p>';
        $html .= '</header>';
        $html .= '<section><h2>Resumo Financeiro</h2>';
        $html .= '<p><strong>Mensalidade Base:</strong> ' . format_currency((float) $proposal['mensalidade_base']) . '</p>';
        $html .= '<p><strong>Mensalidade com Pague em Dia:</strong> ' . format_currency((float) $proposal['mensalidade_pague']) . '</p>';
        $html .= '<p><strong>Implantação:</strong> ' . format_currency((float) $proposal['implantacao_valor']) . ' (' . sanitize($proposal['implantacao_tipo']) . ')</p>';
        $html .= '</section>';
        if ($itemsRows) {
            $html .= '<section><h2>Periféricos e Serviços</h2><table><thead><tr><th>Item</th><th>Valor Unitário</th><th>Qtd.</th><th>Subtotal</th></tr></thead><tbody>' . $itemsRows . '</tbody></table></section>';
        }
        $html .= '<section><h2>Texto da Proposta</h2><p>' . nl2br(sanitize($proposal['texto_proposta'])) . '</p></section>';
        $html .= '<footer><p>Gerado em ' . date('d/m/Y H:i') . ' - Proposta #' . $proposal['id'] . '</p></footer>';
        $html .= '</body></html>';

        return $html;
    }

    private function renderContractHtml(array $proposal, array $client, array $items): string
    {
        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Contrato Proposta #' . $proposal['id'] . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;color:#1F2933;margin:40px;line-height:1.6;}h1{color:#0f766e;}h2{color:#0f766e;margin-top:30px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #cbd5f5;padding:8px;text-align:left;}th{background:#e2e8f0;}footer{margin-top:40px;font-size:12px;color:#475569;}</style>';
        $html .= '</head><body>';
        $html .= '<header><h1>Contrato Comercial</h1>';
        $html .= '<p><strong>Cliente:</strong> ' . sanitize($client['empresa_nome'] ?: $client['contato_nome']) . ' (' . sanitize($client['doc']) . ')</p>';
        $html .= '<p><strong>Plano:</strong> ' . sanitize($this->config['plans'][$proposal['plano_key']]['label'] ?? $proposal['plano_key']) . '</p>';
        $html .= '</header>';
        $html .= '<section><h2>Valores Contratados</h2>';
        $html .= '<p>Mensalidade: ' . format_currency((float) $proposal['mensalidade_base']) . '</p>';
        $html .= '<p>Implantação: ' . format_currency((float) $proposal['implantacao_valor']) . ' (' . sanitize($proposal['implantacao_tipo']) . ')</p>';
        if ($items) {
            $html .= '<h3>Periféricos e Serviços</h3><table><thead><tr><th>Item</th><th>Qtd.</th><th>Valor</th></tr></thead><tbody>';
            foreach ($items as $item) {
                $html .= '<tr><td>' . sanitize($item['item_label']) . '</td><td>' . sanitize((string) $item['qty']) . '</td><td>' . format_currency((float) $item['subtotal']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '</section>';
        $html .= '<section><h2>Condições Gerais</h2><p>Este contrato regulamenta a utilização do sistema KAVVI conforme proposta aceita. O cliente concorda com as condições comerciais, valores e prazos estabelecidos. Os serviços serão ativados após a confirmação do pagamento da implantação e assinatura do presente documento.</p></section>';
        $html .= '<section><h2>Assinaturas</h2><p>______________________________________________<br>Representante KAVVI</p><p>______________________________________________<br>' . sanitize($client['contato_nome']) . '</p></section>';
        $html .= '<footer><p>Gerado em ' . date('d/m/Y H:i') . ' - Contrato da Proposta #' . $proposal['id'] . '</p></footer>';
        $html .= '</body></html>';

        return $html;
    }

    private function storeDocument(string $directory, int $proposalId, string $type, string $html): string
    {
        ensure_dir($directory);
        $filename = $proposalId . '-' . $type . '-' . date('Ymd-His') . '.html';
        $path = $directory . '/' . $filename;
        file_put_contents($path, $html);
        return $path;
    }
}
