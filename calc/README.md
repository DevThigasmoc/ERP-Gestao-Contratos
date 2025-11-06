# KAVVI Calculadora - Gestão de Contratos e Financeiro

## Instalação

1. Copie o conteúdo desta pasta para `public_html/calc/` no seu servidor.
2. Crie um banco MySQL e configure as credenciais no arquivo `.env` (use `.env.example` como base).
3. Execute as migrações SQL localizadas em `migrations/` na ordem sugerida pelo prefixo do arquivo.
4. Execute os seeds em `seeds/` para criar a empresa e usuários de demonstração.
5. Certifique-se de que as pastas `storage/` e `storage/company_settings/` são graváveis pelo PHP.

## Usuários iniciais

Após rodar os seeds, acesse com:
- E-mail: `admin@kavvi.com`
- Senha: `kavvi123`

## Estrutura principal

- `catalogo/`: cadastro, edição e importação de produtos e serviços por empresa.
- `financeiro/`: dashboards, contas a receber/pagar, assinaturas e configurações financeiras.
- `webhooks/efi.php`: endpoint para conciliação automática das cobranças EFI.
- `migrations/` e `seeds/`: scripts SQL para estruturar o banco multiempresa.

## Webhook EFI

Configure o endpoint `https://seu-dominio.com/calc/webhooks/efi.php?secret=SEU_SEGREDO` na EFI.

## Testes recomendados

1. Aceitar uma proposta pública em `/calc/propostas/ver.php?token=...`.
2. Verificar a criação automática da assinatura em `financeiro/assinaturas.php`.
3. Gerar o faturamento do mês em `financeiro/receber.php` e conferir as cobranças.
4. Simular o webhook de pagamento e confirmar a criação das parcelas de comissão.
5. Marcar parcelas como pagas em `financeiro/pagar.php` e revisar os KPIs em `financeiro/dashboard.php`.
