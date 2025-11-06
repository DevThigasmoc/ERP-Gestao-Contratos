<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_auth();

$user = current_user();
$service = new ProposalService($appConfig);
$proposalRepo = new ProposalRepository();
$message = null;
$error = null;
$currentProposal = null;
$proposalItems = [];
$shareUrl = null;

/** BASE PATH (/calc) para gerar links e assets corretamente */
$BASE_PATH = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($BASE_PATH === '/' || $BASE_PATH === '\\') { $BASE_PATH = ''; }

function build_share_url(string $token): string
{
    global $BASE_PATH;
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = $BASE_PATH . '/propostas/ver.php?token=' . urlencode($token);
    return $host ? ($scheme . '://' . $host . $path) : $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['_token'] ?? '');
    $action = $_POST['action'] ?? 'save_proposal';
    try {
        if ($action === 'save_proposal') {
            $result = $service->saveProposal($_POST, $user);
            $message = 'Proposta salva com sucesso!';
            $currentProposal = $proposalRepo->findById($result['id']);
            $proposalItems = $proposalRepo->findItems($result['id']);
        } elseif ($action === 'accept_proposal') {
            $service->acceptProposal((int) $_POST['proposal_id'], $user);
            $message = 'Proposta aceita. Você já pode gerar o contrato.';
            $currentProposal = $proposalRepo->findById((int) $_POST['proposal_id']);
            $proposalItems = $proposalRepo->findItems((int) $_POST['proposal_id']);
        } elseif ($action === 'generate_contract') {
            $service->generateContract((int) $_POST['proposal_id'], $user);
            $message = 'Contrato gerado com sucesso!';
            $currentProposal = $proposalRepo->findById((int) $_POST['proposal_id']);
            $proposalItems = $proposalRepo->findItems((int) $_POST['proposal_id']);
        }
        if (!empty($currentProposal['share_token'])) {
            $shareUrl = build_share_url($currentProposal['share_token']);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif (isset($_GET['proposal'])) {
    $proposalId = (int) $_GET['proposal'];
    $proposal = $proposalRepo->findById($proposalId);
    if ($proposal && ($user['perfil'] !== 'vendedor' || (int) $proposal['user_id'] === (int) $user['id'])) {
        $currentProposal = $proposal;
        $proposalItems = $proposalRepo->findItems($proposalId);
        if (!empty($proposal['share_token'])) {
            $shareUrl = build_share_url($proposal['share_token']);
        }
    }
}

$csrfToken = csrf_token();
$plans = $appConfig['plans'];
$addons = $appConfig['addons'];
$recentProposals = $proposalRepo->list([], $user);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>KAVVI Calculadora Comercial</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- forçando assets sob /calc -->
  <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/style.css">
  <link rel="icon" href="<?= $BASE_PATH ?>/assets/favicon.ico">
  <style>
    .app-header .logo-wrap { display:flex; align-items:center; gap:10px; font-weight:600; color:#002C57; }
    .totais { display:flex; gap:24px; align-items:center; margin-top:10px; }
    .totais strong { color:#002C57; }
    tr[data-item="principal"] td { background:#f8fafc; font-weight:600; }
  </style>
</head>
<body>
<header class="app-header">
  <div class="logo-wrap">
    <img src="<?= $BASE_PATH ?>/assets/kavvi-logo.png" alt="KAVVI" style="height:28px">
    <span>KAVVI Calculadora</span>
  </div>
  <nav>
    <a href="<?= $BASE_PATH ?>/admin/index.php">Painel</a>
    <a href="<?= $BASE_PATH ?>/auth/logout.php">Sair</a>
  </nav>
</header>

<main class="layout">
  <section class="calculator">
    <h1>Gerador de Propostas</h1>
    <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error); ?></div><?php endif; ?>

    <form method="post" id="proposal-form">
      <input type="hidden" name="_token" value="<?= sanitize($csrfToken); ?>">
      <input type="hidden" name="proposal_id" value="<?= sanitize((string) ($currentProposal['id'] ?? '')); ?>">
      <input type="hidden" name="share_token" value="<?= sanitize((string) ($currentProposal['share_token'] ?? '')); ?>">
      <input type="hidden" name="status" value="<?= sanitize((string) ($currentProposal['status'] ?? 'rascunho')); ?>">

      <section class="card">
        <h2>Dados do Cliente</h2>
        <div class="grid">
          <label>Tipo de Pessoa
            <select name="cliente_pessoa_tipo">
              <option value="PJ" <?= (($currentProposal['client_pessoa_tipo'] ?? 'PJ') === 'PJ') ? 'selected' : ''; ?>>Pessoa Jurídica</option>
              <option value="PF" <?= (($currentProposal['client_pessoa_tipo'] ?? '') === 'PF') ? 'selected' : ''; ?>>Pessoa Física</option>
            </select>
          </label>
          <label>CPF/CNPJ
            <input type="text" id="cliente_cnpj" name="cliente_doc" value="<?= sanitize((string) ($currentProposal['client_doc'] ?? '')); ?>" required>
          </label>
          <label>Razão/Nome Fantasia
            <input type="text" id="cliente_empresa" name="cliente_empresa" value="<?= sanitize((string) ($currentProposal['client_empresa_nome'] ?? '')); ?>">
          </label>
          <label>Contato Responsável
            <input type="text" name="cliente_contato" value="<?= sanitize((string) ($currentProposal['client_contato_nome'] ?? '')); ?>" required>
          </label>
          <label>Telefone
            <input type="text" name="cliente_telefone" value="<?= sanitize((string) ($currentProposal['client_telefone'] ?? '')); ?>">
          </label>
          <label>CEP
            <input type="text" id="cliente_cep" name="cliente_cep" value="<?= sanitize((string) ($currentProposal['client_cep'] ?? '')); ?>">
          </label>
          <label>Endereço
            <input type="text" id="cliente_endereco" name="cliente_endereco" value="<?= sanitize((string) ($currentProposal['client_endereco'] ?? '')); ?>">
          </label>
          <label>Número
            <input type="text" id="cliente_numero" name="cliente_numero" value="<?= sanitize((string) ($currentProposal['client_numero'] ?? '')); ?>">
          </label>
          <label>Complemento
            <input type="text" id="cliente_complemento" name="cliente_complemento" value="<?= sanitize((string) ($currentProposal['client_complemento'] ?? '')); ?>">
          </label>
          <label>Bairro
            <input type="text" id="cliente_bairro" name="cliente_bairro" value="<?= sanitize((string) ($currentProposal['client_bairro'] ?? '')); ?>">
          </label>
          <label>Cidade
            <input type="text" id="cliente_cidade" name="cliente_cidade" value="<?= sanitize((string) ($currentProposal['client_cidade'] ?? '')); ?>">
          </label>
          <label>UF
            <input type="text" id="cliente_uf" name="cliente_uf" value="<?= sanitize((string) ($currentProposal['client_uf'] ?? '')); ?>" maxlength="2">
          </label>
        </div>
      </section>

      <section class="card">
        <h2>Plano e Mensalidade</h2>
        <div class="grid">
          <label>Plano
            <select name="plano_key" id="plano_key">
              <?php foreach ($plans as $key => $plan): ?>
                <option
                  value="<?= sanitize($key); ?>"
                  data-price="<?= sanitize((string)$plan['base_price']); ?>"
                  data-label="<?= sanitize($plan['label']); ?>"
                  <?= (($currentProposal['plano_key'] ?? 'kavvi_start') === $key) ? 'selected' : ''; ?>
                ><?= sanitize($plan['label']); ?> - <?= format_currency((float) $plan['base_price']); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Qtde. Usuários
            <input type="number" name="usuarios_qtd" id="usuarios_qtd" min="1" value="<?= sanitize((string) ($currentProposal['usuarios_qtd'] ?? 1)); ?>">
          </label>

          <label style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" id="pague_em_dia_enable"><span>Aplicar “Pague em Dia”</span>
          </label>

          <label>% Pague em Dia
            <input type="number" step="0.01" name="pague_em_dia_percent" id="pague_em_dia_percent" value="<?= sanitize((string) ($currentProposal['pague_em_dia_percent'] ?? 0)); ?>">
          </label>

          <label>Descontos Mensalidade (%)
            <input type="number" step="0.01" name="descontos_mensal" id="desconto_mensal" value="<?= sanitize((string) ($currentProposal['descontos_mensal'] ?? 0)); ?>">
          </label>
          <label>Descontos Periféricos (%)
            <input type="number" step="0.01" name="descontos_addons" id="desconto_addons" value="<?= sanitize((string) ($currentProposal['descontos_addons'] ?? 0)); ?>">
          </label>

          <label>Mensalidade Base
            <input type="number" step="0.01" name="mensalidade_base" id="mensalidade_base" value="<?= sanitize((string) ($currentProposal['mensalidade_base' ] ?? 0)); ?>" readonly>
          </label>
          <label>Mensalidade com Pague em Dia
            <input type="number" step="0.01" name="mensalidade_pague" id="mensalidade_pague" value="<?= sanitize((string) ($currentProposal['mensalidade_pague'] ?? 0)); ?>" readonly>
          </label>
          <label>1º Vencimento Mensalidade
            <input type="date" name="primeiro_venc_mensal" value="<?= sanitize((string) ($currentProposal['primeiro_venc_mensal'] ?? '')); ?>">
          </label>
        </div>

        <div class="totais">
          <div><strong>Total Mensal:</strong> <span id="total_mensal"><?= isset($currentProposal['mensalidade_pague']) ? format_currency((float)$currentProposal['mensalidade_pague']) : 'R$ 0,00'; ?></span></div>
          <div><strong>Total Implantação:</strong> <span id="total_implantacao"><?= isset($currentProposal['implantacao_valor']) ? format_currency((float)$currentProposal['implantacao_valor']) : 'R$ 0,00'; ?></span></div>
        </div>
      </section>

      <section class="card">
        <h2>Implantação</h2>
        <div class="grid">
          <label>Tipo
            <select name="implantacao_tipo" id="implantacao_tipo">
              <option value="única" <?= (($currentProposal['implantacao_tipo'] ?? 'única') === 'única') ? 'selected' : ''; ?>>Pagamento Único</option>
              <option value="parcelada" <?= (($currentProposal['implantacao_tipo'] ?? '') === 'parcelada') ? 'selected' : ''; ?>>Parcelada</option>
            </select>
          </label>
          <label>Valor Implantação
            <input type="number" step="0.01" name="implantacao_valor" id="implantacao_valor" value="<?= sanitize((string) ($currentProposal['implantacao_valor'] ?? 0)); ?>">
          </label>
          <label>Qtde. Parcelas
            <input type="number" name="implantacao_parcelas" id="implantacao_parcelas" value="<?= sanitize((string) ($currentProposal['implantacao_parcelas'] ?? 1)); ?>" min="1">
          </label>
          <label>1º Vencimento Implantação
            <input type="date" name="primeiro_venc_implant" id="primeiro_venc_implant" value="<?= sanitize((string) ($currentProposal['primeiro_venc_implant'] ?? '')); ?>">
          </label>
        </div>
      </section>

      <section class="card">
        <h2>Periféricos e Serviços</h2>
        <div id="items-container">
          <table class="items-table">
            <thead>
              <tr><th>Item</th><th>Por usuário?</th><th>Valor Unitário</th><th>Qtd.</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="pacote_itens">
              <?php if ($proposalItems): ?>
                <?php foreach ($proposalItems as $index => $item): ?>
                  <tr>
                    <td><input type="text" name="item_label[]" value="<?= sanitize($item['item_label']); ?>" required></td>
                    <td class="center"><input type="checkbox" name="item_per_user[<?= $index; ?>]" value="1" <?= $item['per_user'] ? 'checked' : ''; ?>></td>
                    <td><input type="number" step="0.01" name="item_unit_price[]" value="<?= sanitize((string) $item['unit_price']); ?>" required></td>
                    <td><input type="number" step="0.01" name="item_qty[]" value="<?= sanitize((string) $item['qty']); ?>" required></td>
                    <td><input type="number" step="0.01" name="item_subtotal[]" value="<?= sanitize((string) $item['subtotal']); ?>" required></td>
                    <td><button type="button" class="btn-link remove-item">Remover</button></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr class="empty"><td colspan="6">Nenhum item adicionado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <button type="button" class="btn-secondary" id="add-item">Adicionar Item</button>
        </div>
      </section>

      <section class="card">
        <h2>Texto da Proposta</h2>
        <textarea name="texto_proposta" rows="6" placeholder="Detalhes, condições e observações da proposta."><?= sanitize((string) ($currentProposal['texto_proposta'] ?? '')); ?></textarea>
      </section>

      <div class="actions">
        <button type="submit" name="action" value="save_proposal" class="btn-primary">Salvar Proposta</button>
        <?php if ($currentProposal && in_array($currentProposal['status'], ['rascunho', 'enviada', 'aceita'], true)): ?>
          <button type="submit" name="action" value="accept_proposal" class="btn-secondary">Aceitar Proposta</button>
        <?php endif; ?>
        <?php if ($currentProposal && $currentProposal['status'] === 'aceita'): ?>
          <button type="submit" name="action" value="generate_contract" class="btn-secondary">Gerar Contrato</button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($currentProposal): ?>
      <div class="card info">
        <h2>Compartilhar</h2>
        <p>Status atual: <strong><?= sanitize($currentProposal['status']); ?></strong></p>
        <?php if ($shareUrl): ?><p>Link público: <input type="text" readonly value="<?= sanitize($shareUrl); ?>" class="share-link"></p><?php endif; ?>
        <?php if (!empty($currentProposal['pdf_path'])): ?><p>Último HTML gerado: <code><?= sanitize(basename($currentProposal['pdf_path'])); ?></code></p><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <aside class="sidebar">
    <div class="card">
      <h2>Minhas Propostas</h2>
      <ul class="proposal-list">
        <?php foreach ($recentProposals as $proposal): ?>
          <li>
            <a href="?proposal=<?= (int) $proposal['id']; ?>">#<?= (int) $proposal['id']; ?> - <?= sanitize($proposal['empresa_nome']); ?></a>
            <span class="badge badge-status-<?= sanitize($proposal['status']); ?>"><?= sanitize($proposal['status']); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h2>Adicionar periférico rápido</h2>
      <ul class="addon-list">
        <?php foreach ($addons as $addon): ?>
          <li data-label="<?= sanitize($addon['label']); ?>" data-price="<?= sanitize((string) $addon['unit_price']); ?>">
            <span><?= sanitize($addon['label']); ?></span>
            <span><?= format_currency((float) $addon['unit_price']); ?></span>
            <button type="button" class="btn-link add-addon">Adicionar</button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>
</main>

<script>
/* ===== helpers ===== */
function toBRL(n){ return n.toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); }
function toNum(v){ return parseFloat((v||'').toString().replace(',', '.')) || 0; }
function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }

/* ===== refs ===== */
const planoEl = document.getElementById('plano_key');
const usuariosEl = document.getElementById('usuarios_qtd');
const descMensalEl = document.getElementById('desconto_mensal');
const descAddonsEl = document.getElementById('desconto_addons');
const pagueEnableEl = document.getElementById('pague_em_dia_enable');
const paguePctEl = document.getElementById('pague_em_dia_percent');

const mensalBaseEl = document.getElementById('mensalidade_base');
const mensalPagueEl = document.getElementById('mensalidade_pague');
const totalMensalEl = document.getElementById('total_mensal');
const totalImplEl = document.getElementById('total_implantacao');

const itensTbody = document.getElementById('pacote_itens');

/* limites (espelham back) */
window.KAVVI = window.KAVVI || {};
if(typeof window.KAVVI.MAX_DESC_MENSALIDADE !== 'number') window.KAVVI.MAX_DESC_MENSALIDADE = 15;
if(typeof window.KAVVI.MAX_DESC_PERIFERICOS !== 'number') window.KAVVI.MAX_DESC_PERIFERICOS = 5;
if(typeof window.KAVVI.PAGUE_EM_DIA_PERCENT !== 'number') window.KAVVI.PAGUE_EM_DIA_PERCENT = <?= defined('PAGUE_EM_DIA_PERCENT') ? (int)PAGUE_EM_DIA_PERCENT : 10 ?>;

function getPlanoPrice(){ return toNum(planoEl.selectedOptions[0].dataset.price); }
function getPlanoLabel(){ return planoEl.selectedOptions[0].dataset.label || planoEl.selectedOptions[0].textContent.trim(); }

/* ============= produto principal automático ============= */
function upsertProdutoPrincipal(){
  const users = Math.max(1, parseInt(usuariosEl.value || '1', 10));
  const base = getPlanoPrice();
  let desc = toNum(descMensalEl.value);
  desc = clamp(desc, 0, window.KAVVI.MAX_DESC_MENSALIDADE);

  const unitAfterDesc = base * (1 - desc/100);
  const subtotal = unitAfterDesc * users;

  let tr = itensTbody.querySelector('tr[data-item="principal"]');
  if(!tr){
    tr = document.createElement('tr');
    tr.setAttribute('data-item', 'principal');
    tr.innerHTML = `
      <td class="item-label"></td>
      <td class="center">Sim</td>
      <td class="item-valor"></td>
      <td class="item-qtd"></td>
      <td class="item-subtotal"></td>
      <td></td>`;
    const empty = itensTbody.querySelector('.empty'); if (empty) empty.remove();
    itensTbody.prepend(tr);
  }
  tr.querySelector('.item-label').textContent = 'Plano ' + getPlanoLabel();
  tr.querySelector('.item-valor').textContent = toBRL(unitAfterDesc);
  tr.querySelector('.item-qtd').textContent = users.toString();
  tr.querySelector('.item-subtotal').textContent = toBRL(subtotal);

  const enabled = pagueEnableEl.checked;
  const pagPct = enabled ? (toNum(paguePctEl.value) || window.KAVVI.PAGUE_EM_DIA_PERCENT) : 0;
  const comPague = subtotal * (1 - pagPct/100);
  mensalBaseEl.value = (unitAfterDesc * users).toFixed(2);
  mensalPagueEl.value = comPague.toFixed(2);
}

function recalcAddons(){
  const users = Math.max(1, parseInt(usuariosEl.value || '1', 10));
  let d = toNum(descAddonsEl.value);
  d = clamp(d, 0, window.KAVVI.MAX_DESC_PERIFERICOS);

  itensTbody.querySelectorAll('tr').forEach(tr=>{
    if(tr.dataset.item === 'principal') return;
    const unitI = tr.querySelector('input[name="item_unit_price[]"]');
    const qtyI  = tr.querySelector('input[name="item_qty[]"]');
    const subI  = tr.querySelector('input[name="item_subtotal[]"]');
    if(!unitI || !qtyI || !subI) return;

    const perChk = tr.querySelector('input[type="checkbox"]');
    const perUser = perChk ? perChk.checked : false;

    const unit = toNum(unitI.value);
    const qty  = toNum(qtyI.value) || 1;
    let sub = unit * qty;
    if (perUser) sub *= users;
    sub = sub * (1 - d/100);
    subI.value = sub.toFixed(2);
  });
}

function recalcTotais(){
  let totalMensal = 0;
  const principalSubtotalEl = itensTbody.querySelector('tr[data-item="principal"] .item-subtotal');
  if(principalSubtotalEl){ totalMensal += toNum(mensalPagueEl.value); }

  itensTbody.querySelectorAll('tr').forEach(tr=>{
    if(tr.dataset.item === 'principal') return;
    const subI = tr.querySelector('input[name="item_subtotal[]"]');
    if (subI) totalMensal += toNum(subI.value);
  });

  totalMensalEl.textContent = toBRL(totalMensal);
  const imp = toNum(document.getElementById('implantacao_valor').value);
  document.getElementById('total_implantacao').textContent = toBRL(imp);
}

/* Recalcular nas mudanças principais */
['change','input'].forEach(ev=>{
  planoEl.addEventListener(ev, onChangeCore);
  usuariosEl.addEventListener(ev, onChangeCore);
  descMensalEl.addEventListener(ev, onChangeCore);
  descAddonsEl.addEventListener(ev, onChangeCore);
  pagueEnableEl.addEventListener(ev, onChangeCore);
  paguePctEl.addEventListener(ev, onChangeCore);
});
document.getElementById('implantacao_valor').addEventListener('input', recalcTotais);

function onChangeCore(){ upsertProdutoPrincipal(); recalcAddons(); recalcTotais(); }

/* === Adicionar Item (permanece igual) === */
document.getElementById('add-item').addEventListener('click', function () {
  addItemRow('', false, '', '', '');
});
function addItemRow(label, perUser, unitPrice, qty, subtotal) {
  const empty = itensTbody.querySelector('.empty'); if (empty) empty.remove();
  const index = itensTbody.querySelectorAll('input[name="item_label[]"]').length;

  const tr = document.createElement('tr');
  tr.innerHTML =
    '<td><input type="text" name="item_label[]" required></td>' +
    '<td class="center"><input type="checkbox" name="item_per_user[' + index + ']" value="1"></td>' +
    '<td><input type="number" step="0.01" name="item_unit_price[]" required></td>' +
    '<td><input type="number" step="0.01" name="item_qty[]" required></td>' +
    '<td><input type="number" step="0.01" name="item_subtotal[]" required></td>' +
    '<td><button type="button" class="btn-link remove-item">Remover</button></td>';
  itensTbody.appendChild(tr);

  const inputs = tr.querySelectorAll('input');
  inputs[0].value = label || '';
  if (perUser) inputs[1].checked = true;
  inputs[2].value = unitPrice || '';
  inputs[3].value = qty || '';
  inputs[4].value = subtotal || '';

  // ligar recalculo nesses inputs
  [inputs[1],inputs[2],inputs[3],inputs[4]].forEach(el=>{
    if (el) el.addEventListener('input', ()=>{ recalcAddons(); recalcTotais(); });
  });
}

/* === NOVO: delegação para remover QUALQUER linha existente/novas === */
itensTbody.addEventListener('click', function(e){
  const btn = e.target.closest('.remove-item');
  if(!btn) return;
  const tr = btn.closest('tr');
  if(!tr || tr.dataset.item === 'principal') return; // não remove o principal
  tr.remove();
  if (!itensTbody.querySelector('tr:not([data-item="principal"])')) {
    const emptyRow = document.createElement('tr');
    emptyRow.classList.add('empty');
    emptyRow.innerHTML = '<td colspan="6">Nenhum item adicionado.</td>';
    itensTbody.appendChild(emptyRow);
  }
  recalcTotais();
});

/* Bridge: enviar principal como inputs hidden no submit */
document.getElementById('proposal-form').addEventListener('submit', function(){
  const principal = document.querySelector('#pacote_itens tr[data-item="principal"]');
  if (!principal) return;
  const money = (txt)=> parseFloat((txt||'').replace(/[R$\s\.]/g,'').replace(',','.')) || 0;

  const label = principal.querySelector('.item-label')?.textContent?.trim() || '';
  const qtd   = parseFloat((principal.querySelector('.item-qtd')?.textContent || '1').replace(',','.')) || 1;
  const unit  = money(principal.querySelector('.item-valor')?.textContent || '');
  const sub   = money(principal.querySelector('.item-subtotal')?.textContent || (unit*qtd).toString());
  if(!label || !qtd || !unit) return;

  const idx = document.querySelectorAll('input[name="item_label[]"]').length;

  const holder = document.createElement('div');
  holder.style.display = 'none';
  const hidden = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; return i; };
  holder.appendChild(hidden('item_label[]', label));
  holder.appendChild(hidden('item_unit_price[]', unit.toFixed(2)));
  holder.appendChild(hidden('item_qty[]', qtd.toFixed(2)));
  holder.appendChild(hidden('item_subtotal[]', sub.toFixed(2)));
  const per = hidden('item_per_user['+idx+']', '1');
  holder.appendChild(per);
  this.appendChild(holder);
});

/* init */
(function init(){
  if (toNum(paguePctEl.value) > 0) pagueEnableEl.checked = true;
  onChangeCore();

  // ligar recálculo nos inputs das linhas já renderizadas pelo PHP
  itensTbody.querySelectorAll('tr').forEach(tr=>{
    const unitI = tr.querySelector('input[name="item_unit_price[]"]');
    const qtyI  = tr.querySelector('input[name="item_qty[]"]');
    const perI  = tr.querySelector('input[type="checkbox"]');
    [unitI, qtyI, perI].forEach(el=>{ if(el) el.addEventListener('input', ()=>{ recalcAddons(); recalcTotais(); }); });
  });
})();
</script>

<!-- CEP/CNPJ e demais melhorias (SOMENTE isso; ver arquivo abaixo sem “produto principal”) -->
<script src="<?= $BASE_PATH ?>/assets/js/kavvi-enhancements.js"></script>
</body>
</html>
