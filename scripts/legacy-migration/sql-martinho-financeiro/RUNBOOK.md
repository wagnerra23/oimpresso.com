# RUNBOOK — Migração FINANCEIRO SQL-only · Martinho biz=164

> Sequência operacional pra amanhã. Cola comando por comando — não improvisa.

## Pré-condições humanas

- [ ] DBeaver conectado ao Firebird `MartinhoServidor`
- [ ] Acesso MySQL prod (Hostinger Remote MySQL whitelist OU SSH tunnel `autossh`)
- [ ] Cliente `mysql` com flag `--local-infile=1` habilitada
- [ ] Pasta filesystem acessível pelo MySQL pra CSV (Linux: `/home/admin/csv/` · Windows local: `D:/export/`)
- [ ] ~30min livres sem interrupção (pipeline aborta-friendly mas erra cansado)

## Sequência (6 passos)

### 1. Backup (shell, NÃO SQL)

```bash
mysqldump -h <prod-host> -u admin -p oimpresso fin_titulos fin_titulo_baixas \
  --where="business_id=164" \
  > backup-martinho-financeiro-$(date +%Y%m%d-%H%M%S).sql

ls -lh backup-martinho-financeiro-*.sql   # confirma >0 bytes (esperado >5MB pelos 83k+71k rows)
```

❌ Sem backup recente → ABORTAR.

### 2. Preflight (MySQL prod)

```bash
mysql -h <prod-host> -u admin -p oimpresso < 00-preflight-checks.sql
```

Lê **todas as 7 verificações**. Qualquer FAIL → corrigir + repetir.
Pontos críticos:
- PC1 nome do business = Martinho
- PC3 `local_infile = ON`
- PC5 `fin_contas_bancarias` biz=164 tem ≥1 conta
- PC6 snapshot pré-import (anotar valor de `titulos_existentes`)

### 3. Export Firebird → CSV (DBeaver)

1. Abre `01-export-financeiro-firebird.sql` no DBeaver.
2. Roda primeiro as **validações pré-export** (comentadas no fim) — confere contagem, tipo, status.
3. Roda o `SELECT` principal.
4. Right-click resultset → "Export resultset" → CSV:
   - Encoding **UTF-8**
   - Separator **`,`**
   - Quote **`"`**
   - Header **true**
   - NULL string **`(vazio)`**
5. Salva como `financeiro-martinho-YYYY-MM-DD.csv` em path acessível ao MySQL.

Tamanho esperado: ~25-40MB (83k+ rows × 24 cols).

### 4. Staging (MySQL prod)

```bash
mysql -h <prod-host> -u admin -p oimpresso < 02-create-staging-table.sql
```

Confere: `SELECT COUNT(*) FROM fin_titulos_staging_martinho;` → deve ser 0.

### 5. Load + normaliza (MySQL prod, `--local-infile=1`)

⚠️ Editar `03-load-csv-to-staging.sql` ANTES — substituir `${CSV_PATH}` pelo path absoluto do CSV.

```bash
mysql --local-infile=1 -h <prod-host> -u admin -p oimpresso < 03-load-csv-to-staging.sql
```

Confere staging via queries do fim do arquivo:
- `SELECT COUNT(*) FROM fin_titulos_staging_martinho;`
- `SELECT decision, skip_reason, COUNT(*) FROM ... GROUP BY 1,2;`
- Sample 10 rows pra eyeball — datas parseadas, valores não-NULL, tipo/status normalizados.

### 6. UPSERT (MySQL prod) — CORE

```bash
mysql -h <prod-host> -u admin -p oimpresso < 04-upsert-titulos-from-staging.sql
```

⚠️ Q0 do passo 04 **PARA** se nome do business ≠ Martinho. Confere antes de seguir.

Output esperado no fim:
```
titulos_after            ≈ 83.107 + novos
titulos_legacy_after     ≈ contagem do staging (decision='import')
baixas_after             ≈ 71.030 + novos
com_cliente_id           > 50% (Q da §4.3)
```

### 7. Validação (MySQL prod)

```bash
mysql -h <prod-host> -u admin -p oimpresso < 05-validation-queries.sql
```

8 queries. Atenção especial:
- **Q4** rows_violando_invariante = **0** (senão bug no 04)
- **Q6** baixas_orfas = **0** e titulos_quitado_sem_baixa = **0**
- **Q7** cross-tenant: só `business_id=164` (qualquer outro = LEAK → parar tudo)
- **Bonus** diff agregado Firebird ↔ MySQL: drift < 1% aceitável

### 8. Cleanup + snapshot

```sql
-- Se TUDO ok:
DROP TABLE fin_titulos_staging_martinho;
```

```bash
# Grava snapshot canônico:
$EDITOR memory/sessions/$(date +%Y-%m-%d)-migracao-financeiro-martinho-biz164.md
```

Conteúdo mínimo do snapshot:
- Contagens antes (de PC6) / depois (de Q1+Q3 do 05)
- Drift Firebird ↔ MySQL (bonus query)
- Decisões tomadas durante (filtros, conta default, write-off threshold)
- Próximas ações (write-off cleanup, lookup plano_conta_id, etc.)

## Rollback

Se algo deu errado **depois do 04** e antes de validar:

```sql
-- Remove SÓ as rows que ESTE import criou (preserva pré-existentes):
DELETE FROM fin_titulo_baixas
WHERE business_id = 164
  AND idempotency_key LIKE 'leg-164-%'
  AND created_at >= '<timestamp do início do passo 04>';

DELETE FROM fin_titulos
WHERE business_id = 164
  AND origem = 'manual'
  AND origem_id < 0
  AND updated_at >= '<timestamp do início do passo 04>';

DROP TABLE fin_titulos_staging_martinho;
```

Se tudo já foi commitado e quer voltar inteiro:

```bash
mysql -h prod -u admin -p oimpresso < backup-martinho-financeiro-YYYYMMDD-HHMMSS.sql
```

## Estimativas

| Fase | Tempo |
|---|---:|
| 1 Backup | 2-5min (rede) |
| 2 Preflight | 1min |
| 3 Export Firebird → CSV | 5-10min (volume) |
| 4 Staging | <1min |
| 5 Load + normaliza | 3-8min (LOAD DATA + 7 UPDATEs em 83k rows) |
| 6 UPSERT | 5-15min (ON DUPLICATE KEY UPDATE em 83k+71k rows) |
| 7 Validação | 2min |
| 8 Cleanup + snapshot | 5-10min (escrita) |
| **Total** | **25-50min** |

## Quando interromper

- PC1 nome ≠ Martinho → **PARAR**
- Q0 do passo 04 nome ≠ Martinho → **PARAR**
- Q7 do passo 05 cross-tenant leak (qualquer `business_id != 164`) → **PARAR**, investigar
- Diff agregado Firebird ↔ MySQL > 5% → **PAUSAR**, investigar antes de DROP staging
- Q4 invariante violado → **PAUSAR**, há bug no 04

## Refs

- [README.md](./README.md) — pattern completo + mapping
- ADR 0093 (multi-tenant Tier 0)
- ADR 0171 (piloto Martinho biz=164)
- handoff 2026-05-17 (aging bombshell R$ [redacted Tier 0]k vs R$ [redacted Tier 0]M fóssil)
- `scripts/legacy-migration/import-financeiro.py` (pipeline Python paralelo)
- `scripts/legacy-migration/sql/` (pipeline-irmão Clientes — pattern estrutural)
