# Migração SQL-only — Cliente (PESSOAS/CLIENTES Firebird → `contacts` MySQL)

> **Caminho SQL puro** alternativo ao pipeline Python (`scripts/legacy-migration/import-*.py`).
> Pra quem quer rodar via **DBeaver** (Firebird) + **MySQL Workbench**/**Sequel Pro** (MySQL) sem PHP/Python no meio.

## Quando usar este caminho

✅ **Use** quando:
- Você tem DBeaver conectado ao Firebird local do cliente + MySQL Workbench/CLI prod
- Quer **inspeção visual** dos dados antes de inserir (diff manual)
- Vai migrar **1 cliente specific** ad-hoc, não pipeline recorrente
- Não tem Python + `firebird-driver` instalado nessa máquina

❌ **NÃO use** quando:
- Vai migrar todos clientes em batch → use pipeline Python `import-contacts-from-venda.py`
- Quer idempotência automática + retry → use `Modules/Officeimpresso/Services/FirebirdImporter/OfficeimpressoImporterService::importClientes()`
- Tem >100k registros → CSV+LOAD DATA tem overhead manual; pipeline Python streams

## Pré-requisitos

1. **Acesso Firebird do cliente** (DBeaver com `fbclient.dll` instalado)
2. **Acesso MySQL prod** (Hostinger Remote MySQL whitelist OU SSH tunnel autossh)
3. **`business_id` destino conhecido** (ex: Wagner=1, Larissa=4, Martinho=164)
4. **`contacts.legacy_id` column existe** (migration `2026_05_13_170001_add_legacy_id_to_contacts.php` aplicada — confere com `SHOW COLUMNS FROM contacts LIKE 'legacy_id'`)

## Pipeline em 5 passos

```
┌──────────────┐   1.SQL    ┌──────────┐   2.CSV    ┌──────────┐   3.LOAD     ┌──────────┐
│   Firebird   │ ────────▶  │ DBeaver  │ ────────▶  │ Disk     │ ────────▶   │ MySQL    │
│  CLIENTES    │   SELECT   │ export   │   csv      │ (.csv)   │   DATA       │ staging  │
└──────────────┘            └──────────┘            └──────────┘   INFILE     └──────────┘
                                                                                    │
                                                                                    │ 4.SQL UPSERT
                                                                                    ▼
                                                                              ┌──────────┐
                                                                              │ contacts │
                                                                              │ (prod)   │
                                                                              └──────────┘
                                                                                    │
                                                                                    │ 5.SQL validação
                                                                                    ▼
                                                                              ┌──────────┐
                                                                              │ Diff     │
                                                                              │ counts   │
                                                                              └──────────┘
```

### Passo 1 — Export Firebird (DBeaver)

Abre `01-export-clientes-firebird.sql` no DBeaver conectado ao Firebird do cliente.
Roda → exporta resultado como CSV (DBeaver → "Export resultset" → CSV).

Salva como `clientes-<cliente>-<YYYY-MM-DD>.csv` (ex: `clientes-martinho-2026-05-20.csv`).

### Passo 2 — Criar staging table MySQL

Roda `02-create-staging-table.sql` UMA VEZ no MySQL prod. Cria tabela temporária `contacts_staging_pessoas`.

### Passo 3 — Load CSV → staging

Substitui `${CSV_PATH}` em `03-load-csv-to-staging.sql` pelo path absoluto do CSV.
Roda no MySQL.

```sql
LOAD DATA LOCAL INFILE '/path/to/clientes-martinho-2026-05-20.csv'
INTO TABLE contacts_staging_pessoas
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES;
```

⚠️ MySQL prod precisa de `local_infile=ON`. Confere com `SHOW VARIABLES LIKE 'local_infile'`.
Se OFF, Wagner ativa via SSH: `SET GLOBAL local_infile = 1;` (volátil — restart MySQL desliga).

### Passo 4 — UPSERT staging → contacts (com biz_id)

⚠️ **OBRIGATÓRIO:** abra `04-upsert-contacts-from-staging.sql` e **substitua `${BIZ_ID}`** pelo `business_id` real (NÃO commitar com placeholder).

```sql
-- Exemplo: cliente Martinho biz=164
sed -i 's/${BIZ_ID}/164/g' 04-upsert-contacts-from-staging.sql
mysql -h prod -u admin -p oimpresso < 04-upsert-contacts-from-staging.sql
```

Estratégia: `INSERT ... ON DUPLICATE KEY UPDATE` via índice composto `(business_id, legacy_id)`.
Idempotente — rerun não duplica.

### Passo 5 — Validação

Roda `05-validation-queries.sql` substituindo `${BIZ_ID}`. Confere:

- Total clientes Firebird vs total contacts MySQL com `legacy_id NOT NULL`
- Bloqueados (`contact_status='inactive'`) bate com `BLOQUEADO='S'` Firebird
- CNPJ/CPF únicos no business (sem duplicação)
- 0 rows com `tax_number IS NULL` (todo cliente Delphi tinha doc)

## Mapeamento de campos (anticorruption layer)

| Firebird CLIENTES | MySQL contacts | Transform |
|---|---|---|
| `CODIGO` (PK) | `legacy_id` (string 32) | `CAST AS VARCHAR(32)` |
| `RAZAO_SOCIAL` | `name` | TRIM |
| `RAZAO_SOCIAL` | `supplier_business_name` | espelho (preserva pra retro-compat) |
| `CNPJ` ou `CPF` | `tax_number` | `regexp_replace(., '[^0-9]', '')` (só dígitos) |
| `FONE1` | `mobile` | só dígitos, +55 implícito |
| `FONE2` | `alternate_number` | só dígitos |
| `EMAIL` | `email` | LOWER + TRIM |
| `CIDADE` | `city` | TRIM |
| `BLOQUEADO='S'` | `contact_status='inactive'` | Else `'active'` |
| `DATACADASTRO` | `created_at` | Cast TIMESTAMP |
| — | `business_id` | `${BIZ_ID}` parâmetro |
| — | `type` | `'customer'` fixo |
| — | `created_by` | `1` (system/admin) |

> ⚠️ **329 cols PESSOAS legacy → 30 canônicas contacts.** Migração intencionalmente perde campos vazados (PLACA/MARCAMODELO/ANO etc) que não pertencem a `contacts`. Esses campos vão pra `oficina_vehicles` se cliente é OficinaAuto (ver `import-vehicles.py`).

## Rollback

Se o passo 4 der problema:

```sql
-- Rollback: remove só os contacts importados nesta sessão (preserva contacts pré-existentes)
DELETE FROM contacts
WHERE business_id = ${BIZ_ID}
  AND legacy_id IS NOT NULL
  AND created_at >= '<timestamp do início da migração>';

-- Limpa staging
DROP TABLE contacts_staging_pessoas;
```

Staging table tem `auto_drop`-style — recomendamos `DROP TABLE` ao final pra evitar lixo acumulado entre migrações.

## Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

🚨 **Cuidado supremo:** `${BIZ_ID}` errado vaza clientes de um tenant pra outro.

Antes de rodar passo 4:
1. Confere `SELECT id, name FROM business WHERE id = ${BIZ_ID}` → name bate com cliente esperado?
2. Confere `SELECT COUNT(*) FROM contacts WHERE business_id = ${BIZ_ID}` → quantos já existem? (não deve apagar)
3. Backup do `contacts` antes via `mysqldump -h prod -u admin -p oimpresso contacts > contacts-pre-import-${date}.sql`

## Refs

- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0113](../../../memory/decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) Integração Delphi↔Laravel 3 caminhos
- [SCHEMA-FIREBIRD.md §PESSOAS](../../../memory/legacy-delphi/SCHEMA-FIREBIRD.md)
- [MAPEAMENTO-DELPHI-LARAVEL.md §Tabelas](../../../memory/legacy-delphi/MAPEAMENTO-DELPHI-LARAVEL.md)
- Pipeline Python alternativo: `scripts/legacy-migration/import-contacts-from-venda.py`
- Migration bridge: `database/migrations/2026_05_13_170001_add_legacy_id_to_contacts.php`
