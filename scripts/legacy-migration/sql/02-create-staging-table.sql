-- ============================================================================
-- 02 — CREATE STAGING TABLE (rodar 1x no MySQL prod antes de cada migração)
-- ============================================================================
--
-- Tabela temporária que recebe o CSV exportado. Sem business_id (ainda).
-- Após o UPSERT no contacts, DROP TABLE pra evitar lixo acumulado.
--
-- Idempotente: DROP IF EXISTS limpa staging anterior.
-- ============================================================================

DROP TABLE IF EXISTS contacts_staging_pessoas;

CREATE TABLE contacts_staging_pessoas (
    legacy_codigo         VARCHAR(32)     NOT NULL COMMENT 'CODIGO Firebird (PK origem)',
    razao_social          VARCHAR(255)    NULL,
    cpf_cnpj              VARCHAR(20)     NULL COMMENT 'só dígitos após normalização',
    telefone_principal    VARCHAR(20)     NULL,
    telefone_alternativo  VARCHAR(20)     NULL,
    email                 VARCHAR(255)    NULL,
    cidade                VARCHAR(100)    NULL,
    bloqueado_sn          CHAR(1)         NULL COMMENT 'Firebird S/N → contact_status active/inactive',
    data_cadastro         TIMESTAMP       NULL,

    -- Auxiliares pra debug
    raw_csv_line          INT             AUTO_INCREMENT,
    imported_at           TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (raw_csv_line),
    INDEX idx_staging_legacy (legacy_codigo),
    INDEX idx_staging_doc    (cpf_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Staging temporário PESSOAS Firebird — DROP após UPSERT';

-- ============================================================================
-- Verifica criação:
--   SHOW CREATE TABLE contacts_staging_pessoas\G
--   SELECT COUNT(*) FROM contacts_staging_pessoas;  -- deve ser 0
-- ============================================================================
