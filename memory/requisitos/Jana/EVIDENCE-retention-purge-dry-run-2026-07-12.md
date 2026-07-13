# EVIDENCE — `jana:retention-purge` dry-run staging CT100 + pedido formal de flip (2026-07-12)

> **Objetivo:** fechar o prep agent-executável do item **loop-6** (`.claude/loop-fechar-o-loop.json` — LGPD purge job): provar em staging que o purge funciona, escopado, sem tocar nada — e formalizar o pedido de flip `JANA_RETENTION_ENABLED=true` pro Wagner (único passo HITL).
>
> **Âncoras de contrato:** [US-COPI-115](SPEC.md) · [AUDIT-SENIOR-2026-05-25 §6 G1](AUDIT-SENIOR-2026-05-25.md) · [`Modules/Jana/Config/retention.php`](../../../Modules/Jana/Config/retention.php) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) multi-tenant Tier 0 · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) biz=1 nunca cliente · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) testes só CT100.

---

## 1. O que foi executado (2026-07-12, staging CT100 — zero DML, zero prod)

Ambiente: container `oimpresso-staging` no CT 100 (clone anonimizado da produção, biz=1 dogfooding). Nenhum comando tocou Hostinger prod. Nenhuma flag foi alterada em `.env` algum. Todos os runs foram `--dry-run` (o Command curto-circuita a persistência) ou `SELECT` read-only.

```bash
# Run 1 — dry-run com TTLs canon, escopado biz=1
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan jana:retention-purge --business=1 --dry-run"

# Run 2 — prova do matcher (days-override=1, ainda dry-run, ainda biz=1)
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan jana:retention-purge --business=1 --days-override=1 --dry-run"

# Baseline read-only por tabela (tinker, COUNT + MIN(data))
```

A flag `--dry-run` já existe no Command e **dispensa `--force` mesmo com `enabled=false`** (checagem `! $enabled && ! $force && ! $dryRun` em [`RetentionPurgeCommand.php`](../../../Modules/Jana/Console/Commands/RetentionPurgeCommand.php)) — não foi preciso PR prévio.

## 2. Evidence pack — antes → depois

### 2.1 Baseline (antes) — staging, dados anonimizados

| Tabela | Total | biz=1 | Linha mais antiga |
|---|---:|---:|---|
| `jana_conversas` | 16 | 10 | 2026-04-26 |
| `jana_mensagens` | 118 | via parent (50) | 2026-04-26 |
| `jana_sugestoes` | 0 | 0 | — |
| `jana_cache_semantico` | 14 | 8 | 2026-04-29 |
| `jana_memoria_facts` | 31 | 19 | 2026-04-28 |
| `jana_memoria_metricas` | 197 | 53 | 2026-04-29 |
| `jana_health_narratives` | 109 | ⚠️ sem `business_id` | 2026-05-09 |

### 2.2 Run 1 — TTLs canon, `--business=1 --dry-run`

| entity | retention_days | matched | purged | status |
|---|---:|---:|---:|---|
| conversa | 730 | 0 | 0 | OK |
| mensagem | 1825 | 0 | 0 | OK |
| sugestao | 365 | 0 | 0 | OK |
| cache_semantico | 90 | 0 | 0 | OK |
| memoria_fato | 1825 | 0 | 0 | OK |
| memoria_metrica | 1095 | 0 | 0 | OK |
| health_narrative | 730 | 0 | 0 | OK |

**Total: 0 matched · 0 purged · 0 failures.** O zero é **correto, não bug**: o dado Jana mais antigo é de 2026-04-26 (~77 dias) e o menor TTL é 90d (`cache_semantico`). Primeira purga real elegível: ~**2026-07-28** (`cache_semantico`).

### 2.3 Run 2 — prova do matcher, `--days-override=1 --dry-run`

| entity | matched | cross-check vs baseline biz=1 |
|---|---:|---|
| conversa | 10 | = 10 exato ✅ |
| mensagem | 50 | 50 de 118 totais — só as filhas de conversas biz=1 (parent-join) ✅ |
| sugestao | 0 | = 0 ✅ |
| cache_semantico | 8 | = 8 exato ✅ |
| memoria_fato | 19 | = 19 exato ✅ |
| memoria_metrica | 53 | = 53 exato ✅ |
| health_narrative | 109 | ⚠️ TODAS — tabela sem `business_id` (ver §3.1) |

**Total: 249 matched · 0 purged · 0 failures.** Dupla confirmação (Regra Mestre, dois caminhos independentes): (a) dry-run do Command; (b) baseline `COUNT` read-only por tabela — os números batem exatamente pra toda entidade com `business_id` direto ou via parent-join. **Isolamento Tier 0 comprovado**: nada fora do biz=1 casou nas entidades tenant-scoped. Zero linhas alteradas em qualquer run (`purged=0` + `[DRY RUN] nada foi persistido`).

Não redigimos PII porque **nenhum conteúdo de linha foi lido/exibido** — só contagens e datas.

## 3. Caveats honestos (importam pro flip)

### 3.1 ⚠️ BLOCKER pro flip global como está: o cron itera TODOS os businesses (inclui biz=4)

O schedule ([`app/Console/Kernel.php:770`](../../../app/Console/Kernel.php)) roda `jana:retention-purge` **sem `--business`** → `resolveBusinesses()` itera **todos**, incluindo **biz=4 ROTA LIVRE (Larissa)**. O loop-6 exige "NUNCA roda automated em biz=4". Logo, **flipar `JANA_RETENTION_ENABLED=true` hoje violaria a própria regra do item** — o pedido em §4 propõe canary que NÃO depende da flag, e uma condição estrutural pro flip global.

### 3.2 `jana_health_narratives` é plataforma-wide

Sem coluna `business_id` — mesmo com `--business=1`, todas as 109 linhas casam. A config declara "sem PII direta (fatos da plataforma)", mas o canary deve tratar essa entidade como plataforma, não como biz=1.

### 3.3 `anonymize` é irreversível + entidades sem `pii_columns` nunca purgam

Estratégia default `anonymize` substitui PII via `PiiRedactor` — **não tem rollback do conteúdo** (por design LGPD). `sugestao` e `memoria_metrica` têm `pii_columns=[]` → `purged=0` sempre (linha preservada, comportamento intencional). O rollback do §4 é *operacional* (parar de purgar), não *restaurativo*.

### 3.4 Staging não exercita o cron

`->environments(['live'])` — o schedule só arma em prod. O dry-run valida o Command/Service; o gatilho diário só será observável no canary em prod.

## 4. 📋 Pedido formal de flip — decisão Wagner (HITL)

**O que ativa:** `JANA_RETENTION_ENABLED=true` no `.env` prod (Hostinger) arma o cron daily 03:00 BRT (`jana:retention-purge`, estratégia `anonymize`, log em `storage/logs/jana-retention.log`, falha alerta no canal `copiloto-ai`).

**Plano proposto (canary 7d biz=1 SEM flipar a flag):**

1. **Dias 1–7:** execução manual diária em prod, explícita e escopada — `php artisan jana:retention-purge --business=1 --force` (o `--force` dispensa a flag; biz=4 nunca entra). Antes de cada run real, um `--business=1 --dry-run` registra o antes. Evidência diária (tabela matched/purged) appendada neste doc.
2. **Começo de baixo risco:** primeira entidade elegível é `cache_semantico` (~28/jul) — cache derivado, 100% regenerável. Se preferir ainda mais conservador: `--entity=cache_semantico` na primeira semana.
3. **Condição estrutural pro flip global (PR pequeno, antes do flip):** allowlist por business em `retention.php` (ex.: `business_allowlist => [1]`) consumida pelo Command quando rodar sem `--business` — só então `JANA_RETENTION_ENABLED=true` fica seguro contra o §3.1. Alternativa mais simples: schedule passa `--business=1` hardcoded até segunda ordem.
4. **Rollback:** desarmar = `JANA_RETENTION_ENABLED=false` (efeito imediato — o cron é gated por `->when(config(...))`; nenhum deploy). Purgas já feitas em `anonymize` não são restauráveis (§3.3) — por isso canary começa por entidade regenerável.
5. **O que NUNCA toca:** biz=4 Larissa — nem no canary (comando explícito `--business=1`), nem no flip global (allowlist é condição). `activity_log` nunca é purgada (audit append-only). `meta*`/`memoria_gabarito` têm TTL `null` = nunca purgam.

**Sign-off pedido:** ☐ Wagner aprova canary 7d biz=1 (passo 1) · ☐ Wagner aprova PR de allowlist (passo 3) · ☐ Wagner aprova flip `JANA_RETENTION_ENABLED=true` pós-canary.

---

*Prep loop-6 concluído por [CC] em 2026-07-12 — código pronto, dry-run provado em staging, flip aguardando Wagner (R10). Nenhuma flag alterada, nenhum DML em prod, biz=4 intocado.*
