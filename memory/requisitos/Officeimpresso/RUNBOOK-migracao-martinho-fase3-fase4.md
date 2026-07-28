---
id: requisitos-officeimpresso-runbook-migracao-martinho-fase3-fase4
title: "RUNBOOK migração Martinho biz=164 — Fase 3 (Vendas) + Fase 4 (Financeiro) · HISTORICAL — execução real divergiu do plano"
module: Officeimpresso
owner: F
status: historical
last_validated: "2026-05-27"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0137-modules-oficinaauto-qualificada
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
preconditions:
  - "Martinho biz=164 já existe em prod (validado 2026-05-13 sessão Fase 2)"
  - "Banco Firebird Martinho acessível via alias HKCU 'MartinhoCacamba' ou path 192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"
  - "Fase 1 (EMPRESA→contacts entidade própria) + Fase 2 (EQUIPAMENTO_VEICULO→vehicles 91 rows) confirmadas em prod (audit JSON salvo)"
  - "PR ADR 0197 (Bucket A+B contacts extend) mergeado em main"
  - "PR ADR 0198 Fase 1 (partitioning transactions/transaction_sell_lines/fin_titulos por YEAR) mergeado em main"
  - "PR ADR 0198 Fase 2 (Object Storage disk Hostinger S3-compat configurado) mergeado em main"
  - "PR ADR 0198 Fase 4 (ImportTransactionsBatchJob + queue migrations-legacy + Horizon) mergeado em main"
  - "Diagnóstico Fase 0 ADR 0198 executado — tamanho DB Hostinger atual conhecido + plano confirmado aguenta +5 GB previsto"
  - "Wagner aprovou execução piloto Martinho (já existe ADR 0171 aprovação base; este RUNBOOK roda nas Fases 3-4 do plano)"
steps:
  - "Pre-flight: contar transactions + fin_titulos prod biz=164 (detect import anterior); ler audit JSON existente"
  - "Dry-run Fase 3 (Vendas) — rodar import-vendas-from-firebird.py --biz 164 --dry-run; revisar audit JSON; Wagner aprova"
  - "Execute Fase 3 live — dispatch Bus::batch ImportTransactionsBatchJob por ano (2010-2026); monitor Horizon /horizon"
  - "Pest smoke biz=164 — vendas integridade FK contacts/vehicles; partitioning pruning funciona"
  - "Dry-run Fase 4 (Financeiro) — rodar import-financeiro-from-firebird.py --biz 164 --dry-run --cleanup-first; revisar audit write-off list; Wagner aprova"
  - "Execute Fase 4 live — dispatch ImportFinanceiroBatchJob; verificar fin_titulos populated; write-off candidates em audit-writeoff-biz164-{ts}.json"
  - "Cutover XML/DANFE legacy → Object Storage (job paralelo MoveLegacyAttachmentsToObjStorJob biz=164)"
  - "Smoke prod 24h canary — alertas Sentry/Grafana; queries críticas (Sells/Index, Financeiro/Inadimplencia) sob SLA"
  - "Cleanup: arquivar audits + atualizar perfil Martinho (05-martinho-cacambas/01-perfil.md) com data Fase 3+4 ✅"
---

# RUNBOOK — Migração Martinho biz=164 · Fases 3 (Vendas) + 4 (Financeiro)

> ## ⚠️ HISTÓRICO — leia §7 PRIMEIRO
>
> **Status: `historical`** (frontmatter). Este RUNBOOK foi escrito 2026-05-27 13:23 BRT assumindo que Fase 3 (VENDA→transactions) + Fase 4 (FINANCEIRO→fin_titulos) estavam pendentes em prod. **Não estavam.**
>
> Diagnóstico Hostinger às 13:30 BRT revelou que Martinho biz=164 já tinha **43.974 vendas + 83.045 fin_titulos** em prod (14 anos de dados, 2012-03 → 2026-05). Execução paralela não-documentada migrou antes do RUNBOOK ser escrito.
>
> **§7 Retrospectiva** (apendada às 13:36 BRT) documenta estado real + gap crítico descoberto (92.5% das vendas sem `transaction_sell_lines`).
>
> **§0-§6 abaixo = plano original não-executado.** Útil como referência de pattern (idempotência, audit JSON, pre-flight), mas NÃO rodar `php artisan officeimpresso:migrate-batch --biz=164` — vai duplicar dados em prod.
>
> 👉 **Pular pra [§7 Retrospectiva 2026-05-27 — Estado REAL Hostinger pos-merge (descoberta diagnóstico)](#7-retrospectiva-2026-05-27--estado-real-hostinger-pos-merge-descoberta-diagnóstico)**
>
> **2026-05-27 atualização (15:00 BRT) — Consolidação canônica:** Pattern oficial pra Vargas/Gold/Extreme + 33 clientes remanescentes vive em **2 ADRs**: [ADR 0203 canon](../../decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) (Felipe Wave 29-1 — pipeline end-to-end · resolve gap 92.5% sub-linhas · WireCrypt fix · PHP service amplo) + [ADR 0332](../../decisions/0332-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon.md) (Wave 2 importers complementares: compras/estoque/contacts-NFe/daemon-sync). Pattern canônico atualizado com 13 fases combinadas em [migracao-officeimpresso-pattern.md §2-bis](../../reference/migracao-officeimpresso-pattern.md). Detalhes consolidação + arqueologia 5 branches órfãs em [memory/sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md](../../sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md).

---

> **Cliente piloto:** Martinho Caçambas LTDA · biz=164 prod oimpresso · vertical mecânica pesada caminhão basculante ([ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)).
>
> **Fases já feitas (não rodar de novo):** Fase 1 (EMPRESA→contacts) 2026-05-13 · Fase 2 (EQUIPAMENTO_VEICULO→vehicles 91 rows) 2026-05-13 13:31 BRT · **Fase 3 VENDA (43.974 em prod) + Fase 4 FINANCEIRO (83.045 títulos + 71.675 baixas em prod)** — descobertas em prod 2026-05-27 ver §7. ⚠️ Gap crítico Fase 3 sub-linhas (92.5% órfãs em `transaction_sell_lines`) — US-OFICINA-XXX pendente Felipe investigar.
>
> **Fases cobertas no PLANO ORIGINAL §0-§6 abaixo (NÃO REEXECUTAR — ver §7 status real):** Fase 3 VENDA (44.709 esperado) · Fase 4 FINANCEIRO (cleanup-first per [ADR 0198 §Mitigação 4](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md)).
>
> **Owner:** Felipe (F). Wagner (W) aprova gates dry-run.
>
> **Pattern canônico geral:** [migracao-officeimpresso-pattern.md](../../reference/migracao-officeimpresso-pattern.md) — esta RUNBOOK estende com especificidades Martinho Fase 3+4.

## 0. Pré-condições (validar TODAS antes de iniciar)

- [ ] Worktree dedicada criada (não usar main worktree primário): `git worktree add .claude/worktrees/martinho-migracao-fase3 -b chore/migracao-martinho-fase3-vendas origin/main`
- [ ] `.venv` Python ativo em `scripts/legacy-migration/` (firebird-driver 1.x + pymysql + python-dotenv instalados)
- [ ] `.env` (gitignored) tem:
  - `FIREBIRD_USER=SYSDBA`
  - `FIREBIRD_PASSWORD=masterkey` (default WR Comercial — registry tem placeholder de 1 char, senha real é fixed)
  - `HOSTINGER_DB_HOST=...` (SSH tunnel ou direct conn — Wagner setup)
  - `HOSTINGER_DB_USER=...`
  - `HOSTINGER_DB_PASS=...` (de Vaultwarden item "Hostinger MySQL prod")
- [ ] Firebird Martinho acessível: `python scripts/legacy-migration/poc2-firebird-connect.py --alias MartinhoCacamba` retorna OK (testa conn)
- [ ] **Backup MySQL Hostinger ANTES de tudo:** snapshot VPS via painel Hostinger OU `mysqldump biz_164_only > backup-biz164-pre-fase3-{ts}.sql.gz` (idealmente ambos)

## 1. Pre-flight — Detect import anterior + ler audits

### 1.1 Count tabelas alvo em prod (esperado zero antes Fase 3)

Via phpMyAdmin Hostinger prod (ou SSH se setup) — substituir `oimpresso_prod` pelo schema real:

```sql
USE oimpresso_prod;
SELECT
  (SELECT COUNT(*) FROM transactions WHERE business_id = 164) AS vendas_biz164,
  (SELECT COUNT(*) FROM transaction_sell_lines tsl
   JOIN transactions t ON t.id = tsl.transaction_id WHERE t.business_id = 164) AS itens_biz164,
  (SELECT COUNT(*) FROM fin_titulos WHERE business_id = 164) AS titulos_biz164,
  (SELECT COUNT(*) FROM vehicles WHERE business_id = 164) AS veiculos_biz164,
  (SELECT COUNT(*) FROM contacts WHERE business_id = 164) AS contacts_biz164;
```

**Resultados esperados (antes Fase 3):**

| Tabela | Esperado | Se diferente |
|---|---|---|
| `vendas_biz164` | 0 | PARAR — alguém importou antes; investigar `ls scripts/legacy-migration/output/audit-vendas-biz164-*.json` + `git log --grep "biz.*164"` |
| `itens_biz164` | 0 | PARAR — mesma investigação |
| `titulos_biz164` | 0 | PARAR — mesma investigação |
| `veiculos_biz164` | **91** | Se ≠ 91 → drift Fase 2; auditar antes Fase 3 |
| `contacts_biz164` | **>= 4** (EMPRESA Fase 1) | Se < 4 → re-rodar Fase 1; se >> 4 → Fase 2 desta ADR já rodou (PESSOAS cadastros) — OK |

### 1.2 Conferir audits existentes

```bash
ls -lh scripts/legacy-migration/output/audit-*biz164*.json
# Esperado: audit-empresas-biz164-{ts}.json + audit-vehicles-biz164-20260513-1331.json
```

Se faltar: parar + buscar logs `memory/sessions/2026-05-13-*martinho*.md` antes de avançar.

## 2. Fase 3 — Migração VENDA → transactions (44.709 esperado)

### 2.1 Garantir importer commitado

Verificar existência:

```bash
ls -lh scripts/legacy-migration/import-vendas-from-firebird.py
```

Se NÃO existe: **bloqueado** — abrir PR antes seguindo pattern de `import-empresas.py` + `import-contas-bancarias.py`. Specs do importer:

- Source: `SELECT * FROM VENDA WHERE 1=1 ORDER BY DT_EMISSAO` (chunked LIMIT/OFFSET 5000)
- Target: `transactions` (UltimatePOS core, multi-tenant `business_id`)
- Mapping crítico: [TELA-LISTA-VENDAS.md §9.1-9.5](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md)
- **FK resolution 2-pass:**
  - Pass 1: INSERT `transactions` (sem `vehicle_id`, sem `contact_id`)
  - Pass 2: UPDATE `transactions` SET `vehicle_id` via JOIN `vehicles.legacy_id`, `contact_id` via JOIN `contacts.legacy_id`
- **Sub-linhas:** `import-venda-itens-from-firebird.py` separado, roda APÓS Fase 3.1 completa
- Idempotência: SELECT-then-UPDATE/INSERT em `(business_id, legacy_id)` — NUNCA `ON DUPLICATE KEY UPDATE`
- Audit JSON: `scripts/legacy-migration/output/audit-vendas-biz164-{ts}.json` com `raw_delphi` per record (PII redacted antes de json.dumps)

### 2.2 Dry-run

```bash
cd scripts/legacy-migration
python import-vendas-from-firebird.py \
  --alias MartinhoCacamba \
  --biz 164 \
  --dry-run \
  --year-from 2010 --year-to 2026 \
  --chunk-size 5000 \
  --output-audit audit-vendas-biz164-dryrun-$(date +%Y%m%d-%H%M%S).json
```

**Saída esperada:**

```
[OK] Conectado Firebird MartinhoCacamba (44.709 VENDA rows detectadas)
[INFO] Dry-run mode: NÃO escreverá MySQL
[1/9] Processando 2010 (1.234 rows)...
[2/9] Processando 2011 (2.345 rows)...
...
[9/9] Processando 2026 YTD (456 rows)...
[STATS] Total processado: 44.709 · would_insert: 44.709 · would_update: 0 · errors: 0 · FK_unresolved: 12 (veículo) + 3 (contato)
[STATS] Audit JSON: scripts/legacy-migration/output/audit-vendas-biz164-dryrun-{ts}.json (12 MB)
[OK] Dry-run completo. Revisar audit + aprovar antes de --apply
```

**Wagner revisa audit JSON** (PII já redacted) → se FK_unresolved > 5% → debugar antes do live. Se < 5% → autoriza Fase 3.3.

### 2.3 Execute live via Horizon batched job

NÃO rodar `--apply` inline (lock contention prod). Dispatch:

```bash
cd /var/www/oimpresso  # via SSH Hostinger
php artisan officeimpresso:migrate-batch \
  --biz=164 \
  --tabela=transactions \
  --alias=MartinhoCacamba \
  --queue=migrations-legacy
```

Esse command dispara `Bus::batch(...)` com 1 job por ano (2010-2026 = 17 jobs paralelos no queue). Horizon dashboard `/horizon/jobs/migrations-legacy` mostra progresso.

**SLA esperado:** 44.709 vendas / 5k chunk / 50ms per row ≈ 7-10 min total (queue concurrency 4).

### 2.4 Validate pós-execute

```sql
USE oimpresso_prod;
SELECT
  COUNT(*) AS total,
  MIN(transaction_date) AS primeira,
  MAX(transaction_date) AS ultima,
  COUNT(DISTINCT YEAR(transaction_date)) AS anos_distintos,
  SUM(CASE WHEN vehicle_id IS NULL THEN 1 ELSE 0 END) AS sem_veiculo,
  SUM(CASE WHEN contact_id IS NULL THEN 1 ELSE 0 END) AS sem_contato
FROM transactions
WHERE business_id = 164;
```

**Esperado:** total ≈ 44.709 (±1% drift aceitável por VENDAs canceladas/excluídas no Delphi); sem_veiculo < 5%; sem_contato < 1%.

### 2.5 Pest smoke

```bash
php artisan test --filter=MartinhoBiz164Phase3Smoke
```

Suite mínima esperada:

- `it loads /sells dashboard biz=164 in <500ms with 44k transactions`
- `it filters Sells/Index by year 2024 using partition pruning (EXPLAIN shows p_2024 only)`
- `it preserves multi-tenant scope: query biz=4 (Larissa) NÃO retorna nada de biz=164`
- `it preserves FK integrity: every transactions.vehicle_id resolves to vehicles row OR is NULL`

Felipe abre PR pra suite criando se ainda não existir.

## 3. Fase 4 — Migração FINANCEIRO → fin_titulos (cleanup-first)

### 3.1 Garantir importer commitado

Verificar `scripts/legacy-migration/import-financeiro-from-firebird.py`. Specs:

- Source: `SELECT * FROM FINANCEIRO WHERE 1=1 ORDER BY DT_VENCTO`
- Target: `fin_titulos` (Modules/Financeiro) + `fin_titulo_baixas` (FK linkagem)
- **Cleanup-first obrigatório** ([ADR 0198 §Mitigação 4](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md)):

```python
WRITE_OFF_CANDIDATE = (
  row.DT_VENCTO < (NOW - timedelta(days=365)) and
  not row.BOLETO_ASAAS_ID and
  not row.has_movimentacao_recent_via_baixas
)
if WRITE_OFF_CANDIDATE:
  write_off_audit.append({'legacy_id': row.CODIGO, 'reason': '...', 'amount': row.VALOR})
  continue  # NÃO importa
else:
  insert_fin_titulo(row)
```

### 3.2 Dry-run

```bash
python import-financeiro-from-firebird.py \
  --alias MartinhoCacamba \
  --biz 164 \
  --dry-run \
  --cleanup-first \
  --output-audit audit-financeiro-biz164-dryrun-$(date +%Y%m%d-%H%M%S).json \
  --output-writeoff audit-writeoff-biz164-dryrun-$(date +%Y%m%d-%H%M%S).json
```

**Saída esperada:**

```
[OK] Conectado Firebird MartinhoCacamba (X FINANCEIRO rows detectadas)
[INFO] Cleanup-first ON: write-off candidates separados em audit-writeoff
[STATS] would_insert: Y · write_off_candidates: Z (76.7% típico Martinho) · errors: 0
```

Wagner revisa **ambos** audits (insert + writeoff) → autoriza.

### 3.3 Execute live

```bash
php artisan officeimpresso:migrate-batch \
  --biz=164 \
  --tabela=fin_titulos \
  --alias=MartinhoCacamba \
  --cleanup-first \
  --queue=migrations-legacy
```

### 3.4 Validate

```sql
SELECT
  COUNT(*) AS total_titulos,
  SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END) AS abertos,
  SUM(CASE WHEN status='PAID' THEN 1 ELSE 0 END) AS pagos,
  MIN(due_date) AS primeira_due,
  MAX(due_date) AS ultima_due
FROM fin_titulos
WHERE business_id = 164;
```

## 4. Anexos XML/DANFE legacy → Object Storage

Per [ADR 0198 §Mitigação 1](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md). Job paralelo:

```bash
php artisan officeimpresso:move-attachments-objstor --biz=164 --queue=migrations-legacy
```

Esse job lê BLOBs do Firebird (não MySQL — Martinho nunca teve XMLs no MySQL pois Fase 3 foi a primeira), faz upload Object Storage, persiste `nfe_xmls.storage_path`. Não bloqueia Fase 3+4 — pode rodar depois.

## 5. Canary smoke prod 24h

Após Fase 3 + Fase 4 + Object Storage moves completarem:

- [ ] Sentry/Grafana alertas zero por 24h
- [ ] `php artisan jana:health-check` retorna OK em todas 5 checks (multi_tenant_isolation crítica)
- [ ] Wagner valida visualmente: `/sells/index?biz=164` carrega <2s · `/financeiro/inadimplencia?biz=164` carrega <3s · `/clientes/{contact_id_martinho}` mostra "cliente desde 200X"
- [ ] Felipe roda `EXPLAIN SELECT * FROM transactions WHERE business_id=164 AND YEAR(transaction_date)=2024` → confirma partition pruning ativo

## 6. Cleanup + handoff

- [ ] Mover audits JSON pra archive: `mv scripts/legacy-migration/output/audit-*biz164*.json scripts/legacy-migration/output/archive/2026-05-martinho-fase3-4/`
- [ ] Atualizar [`memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md`](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) §7 Plano de migração:
  - V1 (Fase 1+2) ✅
  - **V2 (Fase 3+4) ✅ <DATA>** ← apender
- [ ] Atualizar [`memory/reference/migracao-officeimpresso-pattern.md`](../../reference/migracao-officeimpresso-pattern.md) §2.3 + §2.4 status pra `✅ validado Martinho biz=164 (44.709 vendas + Y títulos · <DATA>)`
- [ ] Session log: `memory/sessions/<DATA>-martinho-fase3-fase4-completa.md` (handoff Felipe → Wagner com números reais)
- [ ] PR Felipe: 1 commit dos updates de docs + 1 commit dos audit archives = 1 PR isolado

## Cláusulas de proibição (Tier 0)

- ⛔ **Não rodar `--apply` SEM dry-run aprovado por Wagner.** Mesmo se "parecer óbvio".
- ⛔ **Não esquecer redação PII** no `audit JSON` — `PiiRedactor::redact()` em CNPJ/CPF/EMAIL/FONE antes de `json.dumps()`.
- ⛔ **Não usar `INSERT ... ON DUPLICATE KEY UPDATE`** — schema usa `index` (não `unique`) em `(business_id, legacy_id)`. SELECT-then-UPDATE/INSERT obrigatório.
- ⛔ **Não pular Pest smoke biz=164** — sem teste, drift multi-tenant pode passar silencioso ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- ⛔ **Não migrar `CERTIFICADO`/`SENHA_*` se aparecer em VENDA/FINANCEIRO** (raro, mas auditar) — Vaultwarden.
- ⛔ **Não modificar `transactions.id` ou `fin_titulos.id`** dos rows já existentes em outras biz (Larissa biz=4, etc) durante a migração — afeta FKs cross-tenant.

## Rollback (se Fase 3 falhar)

Cenário: smoke quebra, queries lentas, drift multi-tenant detectado.

1. **Stop queue migrations-legacy** imediatamente: `php artisan horizon:terminate` + `php artisan queue:flush migrations-legacy`
2. **Restore MySQL** via snapshot pré-Fase 3 (do passo 0 — backup obrigatório)
3. **Investigar** audit JSON pra identificar row problemática
4. **Não tentar fix-forward** se DB já rollbackado — re-rodar Fase 3 completa após fix do importer

## Refs

- [ADR 0197 — Bucket A+B schema PESSOAS→contacts](../../decisions/0197-extend-contacts-absorcao-pessoas-legacy.md)
- [ADR 0198 — Hot/cold tiering escala transacional](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md)
- [migracao-officeimpresso-pattern.md (pattern canônico)](../../reference/migracao-officeimpresso-pattern.md)
- [TELA-LISTA-VENDAS.md (mapping VENDA→transactions)](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md)
- [TELA-FINANCEIRO.md (mapping FINANCEIRO→fin_titulos)](../../research/clientes-legacy-officeimpresso/_MAPPING/TELA-FINANCEIRO.md)
- [Perfil Martinho biz=164](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [ADR 0171 — Ativação piloto Martinho faseada](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)

---

## 7. Retrospectiva 2026-05-27 — Estado REAL Hostinger pos-merge (descoberta diagnóstico)

> **Status muda pra `historical`.** Wagner confirmou acesso Hostinger via SSH em 2026-05-27 e Claude rodou as 3 queries diagnóstico [ADR 0198 §Fase 0](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md#fase-0--diagnóstico-hostinger-atual-bloqueador). **Descoberta:** Fases 3+4 NÃO estavam pendentes — alguma execução pré-RUNBOOK (provavelmente Felipe em sessão paralela não-documentada) já migrou massivamente. RUNBOOK escrito em 2026-05-27 manhã (PR #1717) virou plano-com-realidade-divergente.

### 7.1 Hostinger DB total atual

| Métrica | Valor (2026-05-27 13:30 BRT) |
|---|---|
| **DB size** | **594 MB** / 368 tabelas |
| **Disco servidor** | 21 TB total · 6.1 TB livre (29%) |
| **MariaDB** | 11.8.6 (partitioning suportado ✅) |
| **Storage app** | 273 MB · vendor 465 MB · .git 1.4 GB (limpar) |

**Implicação:** gargalo escala temido por Wagner em [§Contexto ADR 0198](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md#contexto) **não é problema atual** — DB inteiro mais leve que um Firebird legacy individual. Mitigações ADR 0198 viram **prospectivas** (aplicar antes do 2º cliente Gold/Vargas/Extreme, não pro Martinho).

### 7.2 Top 4 tabelas pesadas Hostinger

| Tabela | Tamanho | Linhas |
|---|---:|---:|
| `fin_titulos` | 105.3 MB | 96.313 |
| `transactions` | 103.4 MB | 66.776 |
| `mcp_dual_brain_decisions` | 72.9 MB | 22.493 |
| `messages` (WhatsApp) | 46.2 MB | 44.068 |

### 7.3 Inventory Martinho biz=164 — REAL

| Tabela | Volume real | Status |
|---|---:|---|
| `contacts` | **9.938** | ✅ massivo — PESSOAS migradas (não só EMPRESA Fase 1) |
| `products` | **3.809** | ✅ catálogo migrado |
| `vehicles` | 91 | ✅ Fase 2 confirmada |
| `service_orders` | 91 | ✅ 1 OS/veículo (Fase 2 satélite) |
| `transactions` | **43.974** | ✅ Fase 3 cabeçalho ~98% (esperado 44.709) |
| `transaction_sell_lines` | **5.758** | ⚠️ **GAP CRÍTICO** — 92.5% das vendas sem sublinhas |
| `transaction_payments` | 0 | ℹ️ esperado (Modules/Financeiro canônico ≠ UPOS payment legacy) |
| `fin_titulos` | **83.045** | ✅ Fase 4 massiva (provavelmente SEM cleanup-first) |
| `fin_titulo_baixas` | 71.675 | ✅ baixas históricas |
| `users` | 12 | ✅ operadores Martinho cadastrados |

### 7.4 Receita Martinho 14 anos (cross-validation com perfil)

| Ano | Vendas | Receita |
|---:|---:|---:|
| 2026 (parcial) | 1.923 | R$ [redacted Tier 0] |
| 2025 | 4.868 | **R$ [redacted Tier 0]** |
| 2024 | 5.211 | R$ [redacted Tier 0] |
| 2023 | 5.192 | R$ [redacted Tier 0] |
| 2022 | 5.221 | R$ [redacted Tier 0] |
| 2021 | 5.161 | R$ [redacted Tier 0] |
| 2020 | 3.578 | R$ [redacted Tier 0] |
| 2019 | 2.534 | R$ [redacted Tier 0] |
| 2018 | 3.176 | R$ [redacted Tier 0] |
| 2012-2017 | 7.110 | R$ [redacted Tier 0] (acumulado) |

Cross-validation [perfil §5](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md#5-saúde-financeira): "R$ [redacted Tier 0]M 12m + R$ [redacted Tier 0]M+/mês Wagner" bate com 2024-2025 (≈R$ [redacted Tier 0]M/ano = R$ [redacted Tier 0]M/mês). Perfil estava conservador — receita real é dobro do snapshot anterior.

### 7.5 Gap crítico — sub-linhas de venda

**92.5% das vendas Martinho NÃO têm linhas em `transaction_sell_lines`:**

- 43.951 vendas tipo `sell`
- Apenas 3.307 têm sub-linhas (7.5%)
- Média 0.13 item/venda
- 40.644 vendas órfãs (sem `transaction_sell_lines` correspondente)

**Hipóteses (a investigar):**

1. **Importer Fase 3 parou no cabeçalho** — `transactions.final_total` migrado agregado, mas loop de sub-linhas (VENDA_ITEM Delphi) não rodou ou abortou cedo
2. **FK pra products faltando** — sub-linhas precisam de `products.id`, e dos 3.809 produtos migrados pode não haver match pra todos os SKUs antigos. Mas isso explicaria parcial, não 92%
3. **Importer parcial intencional** — Felipe pode ter migrado só vendas recentes (2023+) e deixado histórico só com cabeçalho. Mas 3.307 < 5.211 vendas só de 2024 — nem isso

**Recomendação Felipe (US-OFICINA-XXX a abrir):**

```bash
# Investigar — query Firebird vs MySQL pra comparar VENDA_ITEM cardinalidade
python scripts/legacy-migration/audit-venda-itens-gap.py --biz 164 --alias MartinhoCacamba
# Output esperado: por ano, quantas VENDA têm VENDA_ITEM no Firebird vs transaction_sell_lines no MySQL
```

Decisão arquitetural pendente:
- **Opção A** — completar import sub-linhas retroativamente (importer novo, 2-pass FK products via legacy_id)
- **Opção B** — aceitar perda histórica de granularidade · usar só `transactions.final_total` agregado pra reports
- **Opção C** — `Modules/Financeiro` já reflete a realidade comercial (83k títulos + 71k baixas) — sub-linhas só importam pra produção/Compras retroativa

Wagner decide quando US for priorizada. **Não bloqueia operação atual** — Sells trabalha normal com vendas novas (Martinho ativo 2026-05-26).

### 7.6 Reclassificação ADRs 0197 + 0198

| ADR | Status original (2026-05-27 manhã) | Reclassificação pos-diagnóstico |
|---|---|---|
| **0197** (Bucket A+B contacts) | "schema necessário pra absorver PESSOAS legacy" | **Confirmado** — `contacts` biz=164 tem 9.938 rows que vão se beneficiar dos campos Bucket A (`bloqueado`, `prioridade_producao`, `parent_contact_id`, etc) quando ContactProfileLegacy backfill rodar via importer dedicado. Migration Bucket A já mergeada (PR #1723 ✅) |
| **0198** (hot/cold tiering) | "preocupação Wagner sobre gargalo" | **Prospectivo** — Hostinger atual em 594 MB com Martinho ✅; mitigações (partitioning, archive, Object Storage XMLs) aplicar ANTES do 2º cliente (Gold biz=? próximo). Wagner pode adiar implementação até real necessidade aparecer |

### 7.7 Próximos passos atualizados

1. ✅ **PR #1717** ADR 0197+0198 + RUNBOOK — mergeado 2026-05-27 13:23 BRT
2. ✅ **PR #1723** Migration Bucket A contacts (13 cols + Pest) — mergeado 2026-05-27 13:24 BRT
3. 🟡 **PR Bucket B** (próximo) — `contact_profile_legacy` 1:1 + Model novo + Eloquent relation + Pest. Permite importer dedicado backfillar 9.938 contacts biz=164 com `legacy_data_cadastro` etc
4. 🟡 **US-OFICINA-XXX gap sub-linhas** (Felipe owner) — investigar 92.5% gap `transaction_sell_lines` Martinho; decidir backfill vs accept-loss
5. 🟢 **Plano Hostinger upgrade** ([ADR 0198 §Mitigação 5](../../decisions/0198-hot-cold-tiering-migracao-transacional-legacy.md)) — **NÃO urgente** dado 594 MB atual. Reavaliar quando Gold biz=? entrar
6. 🟢 **Partitioning + archive + Object Storage** — aplicar ANTES de Gold piloto, não pro Martinho que já está saudável

---

**Status:** `historical` desde 2026-05-27 — execução real pre-RUNBOOK divergiu do plano original. Documentado em [memory/sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md](../../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md).
