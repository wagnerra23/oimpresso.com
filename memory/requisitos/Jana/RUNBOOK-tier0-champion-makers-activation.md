# RUNBOOK — Ativação dos Tier 0 das champion-makers IA

> **Status:** prep pronta · **flips são do [W]** (dinheiro/infra/prod). Nada aqui ativa sozinho.
> **Origem:** handoff IA champion-makers (2026-06-01). Aditivos já em prod (PRs #2070/#2071);
> os 3 Tier 0 abaixo o handoff manda "abre PR/documenta, NÃO ativa, espera [W]".
> **Como usar:** quando quiser ligar cada um, segue o passo-a-passo. Tudo é reversível.

---

## TAREFA 1.a — Ligar o purge LGPD em prod (`JANA_RETENTION_ENABLED`)

**Estado:** comando `jana:retention-purge` + `RetentionPurgeService` + Pest já em `main`. Schedule diário 03:00 BRT já registrado (`app/Console/Kernel.php` → `jana-retention-purge-daily`), atrás do flag `JANA_RETENTION_ENABLED` (default **false** em `Modules/Jana/Config/retention.php`).

**Pré-flip (canary 7d biz=1) — sem ativar:**
```bash
# 1. Dry-run global (não persiste nada) — confere o que SERIA purgado
php artisan jana:retention-purge --dry-run
# 2. Dry-run só biz=1 (canary)
php artisan jana:retention-purge --business=1 --dry-run
```
Revisar a tabela de output (business_id / entity / retention_days / matched). Se os números fazem sentido (nada purgando demais), seguir.

**Flip (prod, pós-canary 7d):**
```bash
# .env do Hostinger (prod app)
JANA_RETENTION_ENABLED=true
# limpar config cache pra valer
php artisan config:clear
```
O schedule diário passa a purgar de verdade. **Reverter:** `JANA_RETENTION_ENABLED=false` + `config:clear`.

> ⚠️ LGPD: o purge é append-only-safe e idempotente, mas é **destrutivo** por design (apaga dado vencido). Rodar `--dry-run` 7d seguidos antes do flip, conferindo o delta.

---

## TAREFA 1.b — Ligar o OTel collector no CT 100 (`OTEL_ENABLED`)

**Estado:** `OtelHelper::span` instrumenta 46 services, mas é **no-op** enquanto `OTEL_ENABLED=false` (`config/otel.php`). O app já está configurado pra exportar OTLP HTTP pra `mcp.oimpresso.com:4318` (`OTEL_EXPORTER_OTLP_TRACES_ENDPOINT`). Falta só o backend rodando + o flag.

**Flip (CT 100):**
```bash
# 1. (uma vez) instalar os pacotes SDK no app do CT 100
composer require open-telemetry/sdk open-telemetry/exporter-otlp open-telemetry/opentelemetry-auto-laravel
#    ⚠️ SÓ no CT 100 — NUNCA no Hostinger (ADR 0062 separação de runtime).

# 2. subir o backend OTLP (Jaeger all-in-one, já pronto neste repo)
cd docker/otel && docker compose up -d
#    Recebe OTLP em :4318, UI em :16686 (proteger atrás de Tailscale).

# 3. ligar o flag no .env do CT 100
OTEL_ENABLED=true
OTEL_SAMPLE_RATE=0.05        # 5% (default) — subir se precisar mais trace
php artisan config:clear
```
**Custo:** Jaeger badger = disco local (~zero). Pra escala/retention longa → trocar `SPAN_STORAGE_TYPE` pra Elasticsearch (aí tem custo). **Reverter:** `OTEL_ENABLED=false` (volta a no-op < 1µs).

---

## TAREFA 2 — Apertar o gate RAGAS (cadência real + thresholds)

**Estado:** `.github/workflows/jana-ragas-gate.yml` roda **mock default** (zero custo CI), real só via secret. Cron weekly (`0 8 * * 1`). Thresholds: faithfulness ≥ 0.80, relevancy ≥ 0.75. Golden expandido pra 115 casos (#2071).

**Flip — passo 1: ligar real mode (custo ~R$ [redacted Tier 0]/mês per-PR Jana ou ~R$ [redacted Tier 0]/mês weekly):**
```bash
# GitHub repo → Settings → Secrets and variables → Actions:
gh secret set RAGAS_MODE --body "real"
gh secret set OPENAI_API_KEY --body "<sk-...>"   # judge LLM
```

**Flip — passo 2: cadência diária (opcional, mais custo):**
```yaml
# .github/workflows/jana-ragas-gate.yml — trocar:
#   - cron: '0 8 * * 1'   # weekly
# por:
    - cron: '0 8 * * *'   # daily
```

**Flip — passo 3: subir thresholds (SÓ depois de provar a baseline):**
```bash
# 1. rodar o eval REAL antes pra provar que a baseline atual não fica vermelha:
RAGAS_MODE=real OPENAI_API_KEY=sk-... php artisan jana:ragas-eval --detail
# 2. se faithfulness/relevancy reais > alvo novo com folga, subir no workflow
#    (ex.: 0.80 → 0.83, 0.75 → 0.78). Senão, NÃO subir (ratchet só pra cima e seguro).
```
> Regra do handoff: **subir threshold SÓ se a baseline atual passar** — rodar o eval real antes, nunca às cegas.

---

## TAREFA 3 — Resiliência Meilisearch (HA do ponto único de falha)

**Estado:** com #2070, Meilisearch down já **não estoura** (chat degrada sem recall + `jana:health-check` alerta). Então HA é **opcional** — só elimina a perda temporária de recall, não evita 500.

**Opções (decisão [W], todas com custo):**

| Opção | Como | Custo | Recomendação |
|---|---|---|---|
| **A. Manter degradação graciosa** | nada — #2070 já cobre | R$ [redacted Tier 0] | ✅ **default por ora** — o risco (perder recall por minutos) é baixo e já tratado |
| **B. Réplica self-managed** | 2º Meilisearch no CT 100 + script de re-index + LB/failover (nginx upstream) | disco + RAM 2ª instância | só se recall virar crítico (SLA) |
| **C. Meilisearch Cloud** | managed HA | ~USD 30+/mês | só se sair do CT 100 |

> **Recomendação:** ficar na **A** até ter sinal real (Larissa reclamar de "Jana esqueceu" recorrente OU `memoria_recall_backend` alertar com frequência). Aí reavaliar B/C com ADR.

---

## Resumo dos flips (checklist [W])

- [ ] **T1.a** `JANA_RETENTION_ENABLED=true` no Hostinger (pós 7d de `--dry-run`)
- [ ] **T1.b** `composer require` SDK OTel + `docker compose up` no CT 100 + `OTEL_ENABLED=true`
- [ ] **T2** secret `RAGAS_MODE=real` + `OPENAI_API_KEY` (+ cron daily opcional + threshold pós-baseline)
- [ ] **T3** nenhum flip — opção A (degradação graciosa) é o default recomendado

**Refs:** ADR 0140 · 0093 · 0062 · `config/otel.php` · `Modules/Jana/Config/retention.php` · `.github/workflows/jana-ragas-gate.yml` · handoff `memory/handoffs/2026-06-01-1100-*.md`
