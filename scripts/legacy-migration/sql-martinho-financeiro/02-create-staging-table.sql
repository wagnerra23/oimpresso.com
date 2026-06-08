-- ============================================================================
-- 02 — CREATE STAGING TABLE (rodar 1x no MySQL prod antes de cada migração)
-- ============================================================================
--
-- Tabela temporária que recebe o CSV exportado no passo 01.
-- Após o UPSERT no fin_titulos + fin_titulo_baixas, DROP TABLE pra evitar lixo.
--
-- Idempotente: DROP IF EXISTS limpa staging anterior.
--
-- Schema espelha o SELECT do passo 01 (24 cols + raw_csv_line auto).
-- ============================================================================

DROP TABLE IF EXISTS fin_titulos_staging_martinho;

CREATE TABLE fin_titulos_staging_martinho (
    -- Identidade
    legacy_codigo            VARCHAR(20)   NOT NULL  COMMENT 'CODIGO Firebird (PK origem)',
    cod_pedido               VARCHAR(32)   NULL      COMMENT 'lookup transactions.ref_no',
    cod_empresa              VARCHAR(10)   NULL      COMMENT 'audit: deve ser 1 (single-tenant FB Martinho)',
    pessoa_responsavel       VARCHAR(32)   NULL      COMMENT 'TODO: futuro lookup contacts.legacy_id',

    -- PII (vai pra metadata redacted no upsert)
    razao_social             VARCHAR(255)  NULL,
    documento                VARCHAR(20)   NULL,
    nota_fiscal              VARCHAR(20)   NULL,
    boleto_nosso_nr          VARCHAR(20)   NULL,

    -- Histórico
    historico                VARCHAR(500)  NULL,

    -- Datas (carregadas como string, parseadas em UPDATE pós-load)
    emissao                  VARCHAR(30)   NULL,
    vencto                   VARCHAR(30)   NULL,
    data_pagto               VARCHAR(30)   NULL,
    dt_competencia           VARCHAR(30)   NULL,

    -- Valores (string → DECIMAL no parse)
    valor                    DECIMAL(22,4) NULL,
    juros                    DECIMAL(22,4) NULL,
    desconto                 DECIMAL(22,4) NULL,

    -- Plano contábil / categoria
    cod_plano_contas         VARCHAR(32)   NULL,
    cod_conta                VARCHAR(32)   NULL,

    -- Pagamento
    tipo_pagto               VARCHAR(50)   NULL,
    condicao_pagto           VARCHAR(50)   NULL,
    parcela                  VARCHAR(10)   NULL      COMMENT '1/3 → parsing pós-load',

    -- Classificação Delphi
    tipo                     VARCHAR(20)   NULL,
    status_delphi            VARCHAR(30)   NULL,
    ativo_sn                 CHAR(1)       NULL,

    -- Computados pós-load (preenchidos em passo 03)
    emissao_parsed           DATE          NULL,
    vencto_parsed            DATE          NULL,
    data_pagto_parsed        DATE          NULL,
    competencia_mes          CHAR(7)       NULL      COMMENT 'YYYY-MM',
    tipo_normalized          ENUM('receber','pagar')                           NULL,
    status_normalized        ENUM('aberto','parcial','quitado','cancelado')    NULL,
    parcela_numero           TINYINT       UNSIGNED  NULL,
    parcela_total            TINYINT       UNSIGNED  NULL,
    is_write_off_candidate   TINYINT(1)    NOT NULL  DEFAULT 0,
    decision                 ENUM('import','skip')   NOT NULL DEFAULT 'import',
    skip_reason              VARCHAR(50)   NULL,

    -- Auxiliares debug
    raw_csv_line             INT           UNSIGNED  AUTO_INCREMENT,
    imported_at              TIMESTAMP     NOT NULL  DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (raw_csv_line),
    UNIQUE KEY uk_staging_legacy (legacy_codigo),
    INDEX idx_staging_tipo       (tipo_normalized),
    INDEX idx_staging_status     (status_normalized),
    INDEX idx_staging_decision   (decision),
    INDEX idx_staging_cod_pedido (cod_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Staging temporário FINANCEIRO Martinho — DROP após UPSERT';

-- ============================================================================
-- Verifica criação:
--   SHOW CREATE TABLE fin_titulos_staging_martinho\G
--   SELECT COUNT(*) FROM fin_titulos_staging_martinho;  -- deve ser 0
-- ============================================================================
