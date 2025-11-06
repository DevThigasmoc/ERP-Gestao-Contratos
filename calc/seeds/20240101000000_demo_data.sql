INSERT INTO companies (name, document, created_at, updated_at)
VALUES ('KAVVI Tecnologia', '00.000.000/0001-00', NOW(), NOW());

SET @companyId = LAST_INSERT_ID();

INSERT INTO users (company_id, nome, email, password_hash, perfil, ativo, created_at, updated_at) VALUES
(@companyId, 'Administrador KAVVI', 'admin@kavvi.com', '$2y$12$k27ve6dWnTgIxvyRqpnJ.uSbWfb20YyUuhdRDWy41JQLtdLAE7edy', 'admin', 1, NOW(), NOW()),
(@companyId, 'Gestor Financeiro', 'gestor@kavvi.com', '$2y$12$k27ve6dWnTgIxvyRqpnJ.uSbWfb20YyUuhdRDWy41JQLtdLAE7edy', 'gestor', 1, NOW(), NOW()),
(@companyId, 'Vendedor 1', 'vendedor1@kavvi.com', '$2y$12$k27ve6dWnTgIxvyRqpnJ.uSbWfb20YyUuhdRDWy41JQLtdLAE7edy', 'vendedor', 1, NOW(), NOW()),
(@companyId, 'Vendedor 2', 'vendedor2@kavvi.com', '$2y$12$k27ve6dWnTgIxvyRqpnJ.uSbWfb20YyUuhdRDWy41JQLtdLAE7edy', 'vendedor', 1, NOW(), NOW());

INSERT INTO products (company_id, type, key_slug, label, unit_price, per_user, recurring, billing_cycle, max_discount_percent, active, sort_order, created_at, updated_at) VALUES
(@companyId, 'plan', 'kavvi_start', 'KAVVI Start', 149.90, 1, 1, 'monthly', 15.00, 1, 1, NOW(), NOW()),
(@companyId, 'plan', 'kavvi_plus', 'KAVVI Plus', 229.90, 1, 1, 'monthly', 15.00, 1, 2, NOW(), NOW()),
(@companyId, 'addon', 'impressora_termica', 'Impressora Térmica', 850.00, 0, 0, 'one_time', 5.00, 1, 10, NOW(), NOW()),
(@companyId, 'service', 'suporte_premium', 'Suporte Premium', 99.90, 1, 1, 'monthly', 5.00, 1, 20, NOW(), NOW()),
(@companyId, 'setup', 'implantacao', 'Implantação Completa', 1200.00, 0, 0, 'one_time', 0.00, 1, 30, NOW(), NOW());

INSERT INTO proposals (company_id, cliente_id, user_id, status, total_valor, created_at, updated_at, share_token)
VALUES (@companyId, NULL, (SELECT id FROM users WHERE email = 'vendedor1@kavvi.com'), 'aceita', 149.90, NOW(), NOW(), UUID());

SET @proposalId = LAST_INSERT_ID();

INSERT INTO subscriptions (company_id, proposal_id, customer_doc, customer_name, vendor_user_id, plan_key, users_qtd, base_price, pague_em_dia_percent, status, start_date, end_date, created_at, updated_at)
VALUES (@companyId, @proposalId, '12345678901', 'Cliente Demo', (SELECT id FROM users WHERE email = 'vendedor1@kavvi.com'), 'kavvi_start', 5, 149.90, 5.00, 'active', CURDATE(), NULL, NOW(), NOW());
