-- ============================================================================
-- 03 — LOAD CSV → staging (rodar no MySQL prod após criar staging)
-- ============================================================================
--
-- ⚠️ Substitua ${CSV_PATH} pelo caminho ABSOLUTO do CSV exportado no passo 1.
--    Ex: /home/admin/csv/clientes-martinho-2026-05-20.csv
--        D:\export\clientes-larissa-2026-05-20.csv  (Windows: use forward slash ou escape \\)
--
-- Pré-requisito MySQL: local_infile=ON.
--   Verifica: SHOW VARIABLES LIKE 'local_infile';
--   Liga (volátil): SET GLOBAL local_infile = 1;
--   Liga persistente: edita my.cnf [mysqld] local_infile=1 + restart
--
-- Cliente MySQL precisa flag --local-infile=1:
--   mysql --local-infile=1 -h prod -u admin -p oimpresso
-- ============================================================================

LOAD DATA LOCAL INFILE '${CSV_PATH}'
INTO TABLE contacts_staging_pessoas
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES  -- pula header
(
    legacy_codigo,
    razao_social,
    cpf_cnpj,
    telefone_principal,
    telefone_alternativo,
    email,
    cidade,
    bloqueado_sn,
    @data_cadastro_raw
)
SET data_cadastro = NULLIF(@data_cadastro_raw, '');

-- Normaliza após load (Firebird traz com pontuação; contacts.tax_number só dígitos)
UPDATE contacts_staging_pessoas
SET cpf_cnpj = REGEXP_REPLACE(cpf_cnpj, '[^0-9]', '')
WHERE cpf_cnpj IS NOT NULL;

UPDATE contacts_staging_pessoas
SET telefone_principal = REGEXP_REPLACE(telefone_principal, '[^0-9]', '')
WHERE telefone_principal IS NOT NULL;

UPDATE contacts_staging_pessoas
SET telefone_alternativo = REGEXP_REPLACE(telefone_alternativo, '[^0-9]', '')
WHERE telefone_alternativo IS NOT NULL;

UPDATE contacts_staging_pessoas
SET email = LOWER(TRIM(email))
WHERE email IS NOT NULL;

-- ============================================================================
-- Validações pós-load:
-- ============================================================================
--
-- Total carregado:
--   SELECT COUNT(*) FROM contacts_staging_pessoas;
--
-- Sem CNPJ/CPF:
--   SELECT COUNT(*) FROM contacts_staging_pessoas WHERE COALESCE(cpf_cnpj,'') = '';
--
-- Bloqueados:
--   SELECT COUNT(*) FROM contacts_staging_pessoas WHERE bloqueado_sn = 'S';
--
-- Duplicatas legacy_codigo (não deveria ter se PK Firebird funcionou):
--   SELECT legacy_codigo, COUNT(*) FROM contacts_staging_pessoas
--   GROUP BY legacy_codigo HAVING COUNT(*) > 1;
--
-- Sample 10 linhas pra eyeball:
--   SELECT * FROM contacts_staging_pessoas LIMIT 10;
-- ============================================================================
