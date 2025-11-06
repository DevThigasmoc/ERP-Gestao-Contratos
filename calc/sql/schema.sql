CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin','gestor','vendedor') NOT NULL DEFAULT 'vendedor',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_tipo ENUM('PJ','PF') NOT NULL DEFAULT 'PJ',
    doc VARCHAR(20) NOT NULL,
    empresa_nome VARCHAR(255) NULL,
    contato_nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,
    cep VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(120) NULL,
    cidade VARCHAR(120) NULL,
    uf VARCHAR(2) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_doc (doc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    plano_key VARCHAR(80) NOT NULL,
    usuarios_qtd INT NOT NULL DEFAULT 1,
    descontos_mensal DECIMAL(12,2) NOT NULL DEFAULT 0,
    descontos_addons DECIMAL(12,2) NOT NULL DEFAULT 0,
    pague_em_dia_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    mensalidade_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    mensalidade_pague DECIMAL(12,2) NOT NULL DEFAULT 0,
    primeiro_venc_mensal DATE NULL,
    implantacao_tipo VARCHAR(30) NOT NULL DEFAULT 'única',
    implantacao_valor DECIMAL(12,2) NOT NULL DEFAULT 0,
    implantacao_parcelas INT NOT NULL DEFAULT 1,
    primeiro_venc_implant DATE NULL,
    addons_json JSON NOT NULL,
    texto_proposta MEDIUMTEXT NOT NULL,
    status ENUM('rascunho','enviada','aceita','fechada') NOT NULL DEFAULT 'rascunho',
    share_token VARCHAR(120) NOT NULL UNIQUE,
    pdf_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposal_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT UNSIGNED NOT NULL,
    item_label VARCHAR(255) NOT NULL,
    per_user TINYINT(1) NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT UNSIGNED NOT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    status ENUM('gerado','assinado') NOT NULL DEFAULT 'gerado',
    signed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(120) NOT NULL,
    entity VARCHAR(120) NOT NULL,
    entity_id INT UNSIGNED NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
