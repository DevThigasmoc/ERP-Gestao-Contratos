# Deploy da KAVVI Calculadora (Hostinger)

## 1. Preparar arquivos
1. Faça o upload de toda a pasta do projeto para `public_html/calculadora/` via FTP ou Gerenciador de Arquivos.
2. Certifique-se de manter a estrutura de diretórios:
   - `admin/`, `auth/`, `app/`, `assets/`, `propostas/`, `sql/`, `storage/` etc.
3. Garanta permissão de escrita (755 ou 775) nas pastas `storage/`, `storage/propostas_pdf/`, `storage/contratos_pdf/` e `storage/runtime/`.

## 2. Banco de dados
1. No painel da Hostinger, crie um banco MariaDB/MySQL ou use um existente.
2. Crie um usuário e dê permissão total a esse banco.
3. Acesse o phpMyAdmin e importe `sql/schema.sql` para criar as tabelas.
4. Importe `sql/seeds.sql` para cadastrar o usuário administrador inicial (`admin@kavvi.com / Admin@123`).

## 3. Configuração de credenciais
1. Copie `app/config.php` para `app/config.local.php` **no servidor**.
2. Edite `app/config.local.php` retornando um array com as credenciais:
   ```php
   <?php
   return [
       'db' => [
           'host' => 'HOST_DO_BANCO',
           'name' => 'NOME_DO_BANCO',
           'user' => 'USUARIO',
           'pass' => 'SENHA',
       ],
   ];
   ```
3. Opcional: defina as credenciais via variáveis de ambiente (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

## 4. Regras de segurança (.htaccess)
- O arquivo `.htaccess` na raiz bloqueia acesso direto a `storage/` e `sql/` e remove listagem de diretórios.
- Dentro de `storage/` e `sql/` há `.htaccess` adicionais reforçando o bloqueio e desabilitando execução de PHP.

## 5. Testes após deploy
1. Acesse `https://seu-dominio/calculadora/auth/login.php` e faça login com `admin@kavvi.com` e `Admin@123`. Altere a senha em seguida.
2. Crie um usuário vendedor e faça login com ele.
3. Na calculadora (`index.php`):
   - Preencha dados de cliente, plano e periféricos.
   - Clique em **Salvar Proposta** e confirme se o arquivo HTML é criado em `storage/propostas_pdf/`.
   - Copie o link público exibido e abra em uma janela anônima (modo somente leitura).
   - Clique em **Aceitar Proposta** e depois em **Gerar Contrato**. Verifique se o arquivo aparece em `storage/contratos_pdf/`.
4. No painel admin (`admin/index.php`):
   - Veja a listagem de propostas e filtros em `admin/proposals.php`.
   - Baixe o contrato gerado via `admin/contracts.php`.
   - Teste o CRUD de usuários em `admin/users.php` (apenas admin).

## 6. Checklist de segurança
- Habilite HTTPS no domínio (Hostinger fornece SSL gratuito).
- Confirme que os arquivos em `storage/` não são acessíveis diretamente.
- Utilize senhas fortes e altere a senha padrão do administrador.
- Faça backups periódicos do banco e da pasta `storage/`.
