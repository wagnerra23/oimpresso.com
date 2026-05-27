# Migração SQL-only — Financeiro Martinho Caçambas (biz=164)

> **Caminho SQL puro** pra migrar FINANCEIRO Firebird → `fin_titulos` + `fin_titulo_baixas` MySQL prod.
> Alternativa ao pipeline Python `scripts/legacy-migration/import-financeiro.py`.
> **biz_id = 164 hard-coded** (Martinho Caçambas) em todos os arquivos.

## Estado atual em prod (handoff 2026-05-17)

Martinho **JÁ tem 83.107 fin_titulos + 71.030 baixas em prod biz=164**.

Esses SQLs são **idempotentes** — rerun não duplica. Use pra:
- Auditar contagens vs Firebird origem (queries em `05-validation`)
- Importar lançamentos NOVOS desde a última importação
- Atualizar campos mudados (DATAPAGTO virou pago, VALOR ajustado etc)

## Pré-requisitos

1. **DBeaver** conectado ao Firebird `MartinhoServidor` (alias da conexão Wagner)
2. **MySQL prod** acessível (Hostinger Remote MySQL whitelist OU SSH tunnel autossh)
3. Tabelas destino existem: `fin_titulos`, `fin_titulo_baixas`, `fin_contas_bancarias` (com pelo menos 1 conta default biz=164), `contacts` (clientes Martinho já migrados, 9.988 rows)
4. **Backup obrigatório:**

```bash
mysqldump -h prod -u admin -p oimpresso fin_titulos fin_titulo_baixas \
  --where="business_id=164" \
  > backup-martinho-financeiro-$(date +%Y%m%d-%H%M%S).sql
```

## Pipeline em 5 passos

```
┌──────────────┐   1.SQL    ┌──────────┐   2.CSV    ┌──────────┐   3.LOAD     ┌──────────────┐
│   Firebird   │ ────────▶  │ DBeaver  │ ────────▶  │ Disk     │ ────────▶   │ MySQL        │
│  FINANCEIRO  │   SELECT   │ export   │   csv      │ (.csv)   │   DATA       │ staging      │
└──────────────┘            └──────────┘            └──────────┘   INFILE     └──────────────┘
                                                                                     │
                                                                                     │ 4.SQL UPSERT
                                                                                     ▼
                                                                          ┌──────────────────┐
                                                                          │ fin_titulos      │
                                                                          │ + fin_titulo_    │
                                                                          │   baixas (biz=164)│
                                                                          └──────────────────┘
                                                                                     │
                                                                                     │ 5.SQL validação
                                                                                     ▼
                                                                          ┌──────────────────┐
                                                                          │ Diff Firebird vs │
                                                                          │ MySQL counts     │
                                                                          └──────────────────┘
```

| # | Arquivo | Roda em | Propósito |
|---|---|---|---|
| 00 | `00-preflight-checks.sql` | MySQL prod | 7 sanity checks (business, tabelas, local_infile, contacts, conta default, snapshot, backup) |
| 01 | `01-export-financeiro-firebird.sql` | DBeaver Firebird | SELECT FINANCEIRO filtrado (EMISSAO≥2020, ATIVO≠N, STATUS ok) → export CSV |
| 02 | `02-create-staging-table.sql` | MySQL prod (1x) | Cria `fin_titulos_staging_martinho` temporária |
| 03 | `03-load-csv-to-staging.sql` | MySQL prod | LOAD DATA LOCAL INFILE + 7 UPDATEs normalização |
| 04 | `04-upsert-titulos-from-staging.sql` | MySQL prod | INSERT/UPDATE fin_titulos + fin_titulo_baixas + lookup cliente_id |
| 05 | `05-validation-queries.sql` | MySQL prod | 8 queries diff Firebird vs MySQL + cross-tenant guard |
| —  | `RUNBOOK.md` | — | Sequência operacional passo-a-passo amanhã |

## Mapping campo-a-campo (Firebird FINANCEIRO → MySQL fin_titulos)

Pattern documentado em `memory/reference/migracao-officeimpresso-pattern.md §Fase 5` (pareado com `import-financeiro.py`).

| Firebird FINANCEIRO | MySQL fin_titulos | Transform |
|---|---|---|
| `CODIGO` (PK) | `numero = 'LEG-{CODIGO}'` + `metadata.legacy_id = CODIGO` | string 20 char max |
| `TIPO` | `tipo` | `'A RECEBER'`/`'RECEBIDA'` → `'receber'` ; `'A PAGAR'`/`'PAGA'` → `'pagar'` |
| `DATAPAGTO IS NULL` | `status='aberto'` | senão `status='quitado'` + cria row em fin_titulo_baixas |
| `STATUS = 'ATIVO'` | (filtra IN) | `ATIVO*` (saldo virtual) e `INATIVO AGRUPADO` (filha) → SKIP |
| `RAZAOSOCIAL` | `cliente_descricao` (fallback) | TRIM. PII redacted em audit. Lookup `contacts.legacy_id` resolve `cliente_id`. |
| `VALOR` | `valor_total` | DECIMAL(22,4) — Delphi tem float, MySQL tem precisão exata |
| `EMISSAO` | `emissao` | DATE |
| `VENCTO` | `vencimento` | DATE |
| `DT_COMPETENCIA` | `competencia_mes` | `YYYY-MM` (DATE_FORMAT). Fallback `EMISSAO` se NULL |
| `HISTORICO` | `observacoes` | TEXT |
| — | `origem` | `'manual'` fixo (Firebird = mundo externo) |
| — | `origem_id` | NULL (não há transaction equivalente) |
| — | `parcela_numero` | `PARCELA` se preenchido, senão `1` |
| `CODPLANOCONTAS` | `plano_conta_id` | Lookup `fin_planos_conta` se mapeado, senão NULL |
| `CODCONTA` | (para fin_titulo_baixas) | `conta_bancaria_id` via lookup em `fin_contas_bancarias.legacy_id` |
| **fixos:** | `business_id=164` · `moeda='BRL'` · `created_by=1` · `valor_aberto = valor_total - sum(baixas)` |

**Idempotência:** UNIQUE composto `(business_id, origem, origem_id, parcela_numero)` no fin_titulos. Aqui usamos `origem='manual'` + `origem_id = CODIGO Firebird (negativo)` pra evitar colisão com origem real Laravel.

Hack pra preservar legacy_id sem coluna dedicada: `origem_id = -CODIGO` (negativo, escapeia conflito com transactions.id positivo) + `metadata.legacy_id = CODIGO`.

## Para FINANCEIRO com DATAPAGTO (baixa)

Quando `DATAPAGTO IS NOT NULL`, além de upsert no `fin_titulos` com `status='quitado'`, inserir 1 row em `fin_titulo_baixas`:

| Firebird | MySQL fin_titulo_baixas | Transform |
|---|---|---|
| `CODIGO` | `idempotency_key = 'leg-164-{CODIGO}'` (max 36 char) | UUID-like |
| `VALOR - JUROS - MULTA + DESCONTO` | `valor_baixa` | resolve líquido |
| `JUROS` | `juros` | DECIMAL(22,4) |
| `DESCONTO` | `desconto` | DECIMAL(22,4) |
| (lookup) | `titulo_id` | `SELECT id FROM fin_titulos WHERE business_id=164 AND numero='LEG-{CODIGO}'` |
| `TIPOPAGTO` | `meio_pagamento` | mapeamento enum: `'BOLETO'`→`'boleto'`, `'PIX'`→`'pix'`, `'DINHEIRO'`→`'dinheiro'`, etc. Default `'outro'` |
| `DATAPAGTO` | `data_baixa` | DATE |
| `CODCONTA` | `conta_bancaria_id` | lookup `fin_contas_bancarias` biz=164 (fallback: primeira conta ativa) |

## Filtros importantes (write-off heuristics Wagner)

Pattern do `import-financeiro.py`:

```sql
-- Write-off candidates (não importa real, mas flag pra UI):
WHERE TIPO = 'A RECEBER'
  AND VENCTO < (CURRENT_DATE - 365)
  AND DATAPAGTO IS NULL
  AND COALESCE(BOLETO_NOSSO_NR, '') = ''
  AND COALESCE(JUROS, 0) = 0
  AND COALESCE(DESCONTO, 0) = 0;
-- → metadata.is_write_off_candidate = true (UI filtra)
```

Handoff 2026-05-17 disse: "aging bombshell R$ [redacted Tier 0]M aberto → só R$ [redacted Tier 0]k real + R$ [redacted Tier 0]M fóssil pré-2020". Cuidado pra não trazer R$ [redacted Tier 0]M de lixo histórico.

**Recomendação:** filtra `EMISSAO >= '2020-01-01'` no passo 01 pra excluir fóssil.

## Rollback

```sql
-- Remove só linhas importadas nesta sessão (preserva fin_titulos pré-existentes)
DELETE FROM fin_titulo_baixas
WHERE business_id = 164
  AND idempotency_key LIKE 'leg-164-%';

DELETE FROM fin_titulos
WHERE business_id = 164
  AND numero LIKE 'LEG-%'
  AND created_at >= '<timestamp do início>';

-- Cleanup staging
DROP TABLE fin_titulos_staging_martinho;
```

## Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

🚨 `biz_id=164` é HARD-CODED. Diferente do pipeline Cliente (que usa `${BIZ_ID}` placeholder), aqui é específico do Martinho.

Pra outro cliente: criar nova pasta `sql-{cliente}-financeiro/` por cópia + ajustar `164` → novo `biz_id`.

Q6 do `05-validation` valida cross-tenant (qualquer `business_id != 164` aborta).

## Refs

- `scripts/legacy-migration/import-financeiro.py` (pattern Python paralelo)
- `memory/reference/migracao-officeimpresso-pattern.md §Fase 5`
- `memory/research/relatorios-jana/01-inadimplencia.md` (write-off heuristics)
- handoff 2026-05-17 17:22 (Martinho biz=164 perfil canon: 83k fin_titulos + 71k baixas + R$ [redacted Tier 0]M nominal · R$ [redacted Tier 0]k real + R$ [redacted Tier 0]M fóssil)
- ADR 0093 multi-tenant Tier 0
- ADR 0113 Delphi↔Laravel 3 caminhos
- handoff 2026-05-20 (skill `migration-status` Tier B PR #1202)
- Pipeline Cliente equivalente: `scripts/legacy-migration/sql/` (PR #1204)
