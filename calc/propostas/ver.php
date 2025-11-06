<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$token = $_GET['token'] ?? '';
$repo = new ProposalRepository();
$proposal = $token ? $repo->findByShareToken($token) : null;

if (!$proposal) {
    http_response_code(404);
    exit('Proposta não encontrada ou expirada.');
}

$items = $repo->findItems((int) $proposal['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Proposta #<?= (int) $proposal['id']; ?> - KAVVI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css'); ?>">
    <style>
      .public-container { max-width: 980px; margin: 24px auto; padding: 0 16px; }
      header.kavvi-head { display:flex; gap:12px; align-items:center; margin-bottom:16px; }
      header.kavvi-head img { height:38px; }
      header.kavvi-head h1 { margin:0; font-weight:600; color:#002C57; }
      .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.8rem; background:#f1f5f9; }
      .badge.ok { background:#e7f9ef; color:#065f46; }
      .items-table { width:100%; border-collapse: collapse; }
      .items-table th, .items-table td { border-bottom:1px solid #e5e7eb; padding:8px; text-align:left; }
      section.aceite { margin-top:24px; padding:16px; border:1px solid #e5e7eb; border-radius:12px; background:#fafafa; }
      section.aceite h3 { margin-top:0; }
      section.aceite form div { margin-bottom:10px; }
      section.aceite input[type="text"], section.aceite input[type="email"] { width:100%; max-width:420px; padding:10px; border:1px solid #cbd5e1; border-radius:8px; }
      section.aceite button { padding:10px 16px; border:0; border-radius:10px; background:#002C57; color:#fff; cursor:pointer; }
      footer { margin-top:24px; color:#64748b; font-size:.9rem; }
    </style>
</head>
<body class="public-proposal">
    <div class="public-container">

        <header class="kavvi-head">
            <img src="<?= asset('assets/kavvi-logo.png'); ?>" alt="KAVVI">
            <div>
              <h1>Proposta Comercial</h1>
              <small>KAVVI CRM — A fluidez que conecta vendas e atendimento.</small>
            </div>
        </header>

        <p>
          <strong>Cliente:</strong>
          <?= sanitize(($proposal['client_empresa_nome'] ?: $proposal['client_contato_nome'])); ?>
          &nbsp;•&nbsp;
          <strong>Gerada por:</strong>
          <?= sanitize($proposal['vendedor_nome']); ?> (<?= sanitize($proposal['vendedor_email']); ?>)
          &nbsp;•&nbsp;
          <strong>Status:</strong>
          <span class="badge <?= ($proposal['status']==='aceita' ? 'ok':''); ?>">
            <?= sanitize($proposal['status']); ?>
          </span>
          <?php if (!empty($proposal['accepted_at'])): ?>
            &nbsp;—&nbsp;<small>Aceita em <?= date('d/m/Y H:i', strtotime($proposal['accepted_at'])); ?></small>
          <?php endif; ?>
        </p>

        <section>
            <h2>Resumo</h2>
            <ul>
                <li><strong>Plano:</strong> <?= sanitize($appConfig['plans'][$proposal['plano_key']]['label'] ?? $proposal['plano_key']); ?></li>
                <li><strong>Usuários:</strong> <?= (int) $proposal['usuarios_qtd']; ?></li>
                <li><strong>Mensalidade Base:</strong> <?= format_currency((float) $proposal['mensalidade_base']); ?></li>
                <li><strong>Mensalidade com Pague em Dia:</strong> <?= format_currency((float) $proposal['mensalidade_pague']); ?></li>
                <li><strong>Implantação:</strong> <?= format_currency((float) $proposal['implantacao_valor']); ?> (<?= sanitize($proposal['implantacao_tipo']); ?>)</li>
            </ul>
        </section>

        <?php if ($items): ?>
            <section>
                <h2>Periféricos e Serviços</h2>
                <table class="items-table">
                    <thead>
                        <tr><th>Item</th><th>Por usuário?</th><th>Qtd.</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= sanitize($item['item_label']); ?></td>
                                <td><?= $item['per_user'] ? 'Sim' : 'Não'; ?></td>
                                <td><?= sanitize((string) $item['qty']); ?></td>
                                <td><?= format_currency((float) $item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section>
            <h2>Detalhes</h2>
            <p><?= nl2br(sanitize($proposal['texto_proposta'])); ?></p>
        </section>

        <?php
          // Bloco de aceite: só mostra se ainda não aceitou/fechou
          $status = (string)($proposal['status'] ?? '');
          $podeAceitar = !in_array($status, ['aceita','fechada'], true) && empty($proposal['accepted_at']);
          if ($podeAceitar):
        ?>
        <section class="aceite" aria-labelledby="titulo-aceite">
          <h3 id="titulo-aceite">Aceitar Proposta</h3>
          <p>Confirme seus dados para registrar o aceite eletrônico desta proposta. Ao aceitar, geraremos o contrato automaticamente.</p>

          <form id="aceiteForm" method="post" action="<?= asset('propostas/aceitar.php'); ?>" onsubmit="return enviarAceite(event)">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_token" value="<?= csrf_token(); ?>">

            <div>
              <label>Nome completo*</label>
              <input type="text" name="name" required autocomplete="name">
            </div>
            <div>
              <label>CPF/CNPJ*</label>
              <input type="text" name="doc" required inputmode="numeric" autocomplete="on" placeholder="Somente números">
            </div>
            <div>
              <label>E-mail (opcional)</label>
              <input type="email" name="email" autocomplete="email" placeholder="seunome@empresa.com.br">
            </div>

            <label style="display:flex;gap:.5rem;align-items:center;margin:.75rem 0">
              <input type="checkbox" required>
              <span>Declaro que li e concordo com os termos desta proposta e autorizo a geração do contrato eletrônico.</span>
            </label>

            <button type="submit">Aceitar e Gerar Contrato</button>
            <p id="aceiteMsg" style="margin-top:.5rem"></p>
          </form>
        </section>
        <?php endif; ?>

        <footer>
            <p>Gerado em <?= date('d/m/Y H:i', strtotime($proposal['created_at'])); ?> • Proposta #<?= (int) $proposal['id']; ?></p>
        </footer>
    </div>

    <script>
      async function enviarAceite(e){
        e.preventDefault();
        const form = e.target;
        const msg  = document.getElementById('aceiteMsg');
        msg.textContent = 'Processando...';

        try{
          const data = new FormData(form);
          const res  = await fetch(form.action, { method:'POST', body:data });
          const json = await res.json();

          if(!res.ok || !json || json.ok !== true){
            throw new Error((json && json.error) ? json.error : 'Falha ao aceitar');
          }

          msg.textContent = 'Aceite registrado! Contrato gerado.';
          if (json.contract_path){
            const a = document.createElement('a');
            a.href = json.contract_path;
            a.textContent = 'Abrir Contrato';
            a.target = '_blank';
            a.rel = 'noopener';
            msg.append(' ', a);
          }

          // Atualiza badge de status sem recarregar
          const badge = document.querySelector('.badge');
          if (badge) { badge.textContent = 'aceita'; badge.classList.add('ok'); }
        }catch(err){
          msg.textContent = 'Erro: ' + err.message;
        }
        return false;
      }
    </script>
</body>
</html>
