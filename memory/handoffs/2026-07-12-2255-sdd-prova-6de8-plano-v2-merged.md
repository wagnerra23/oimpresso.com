---
date: "2026-07-12"
time: "22:55 BRT"
slug: sdd-prova-6de8-plano-v2-merged
tldr: "Prova sharded pousou 6/8 vivos (shards 2/4 mortos pelo MESMO modo novo). Causa-raiz achada e MERGEADA na noite: plano de shards não-disjunto sob pest recursivo (99/143 unidades rodavam 2× → classe 1062 + morte de coleta) — PR #4199 (plano v2 paths). Floor/transporte pousam pós-pcov (teto 02:37); cron 02:00 de hoje deve pular pelo .lock."
prs: [4199]
decided_by: [W]
related_adrs: [0279-sdd-medir-governar-floor-nightly, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Conferir pouso do floor na órfã: git fetch origin governance/nightly-floor && git show FETCH_HEAD:governance/nightly-floor.json | grep computed_at — deve ser 20260712-212018 (≠291 stale de 20260706)"
  - "Disparar read-side: gh workflow run sdd-scorecard-publish.yml (materializa floor+coverage+summary V6-B no scorecard)"
  - "1ª nightly 100% v2: 02:00 de 14/jul (a de 13/jul deve pular — .lock preso pela lane pcov da prova). Expectativa: 8/8 vivos + fim da fatia 1062 auto-infligida. NÃO declarar antes do output real (R1)"
  - "Burn-down seguinte (dado fresco, NÃO a foto de 06/jul): converter os 29 loader-blockers/quarentenados (12 TaskRegistry + 12 Fsm voltam sozinhos com o v2; InterWebhookTest é killer real a investigar) + classe assert-content (28/41 fails do shard 0 eram ExpectationFailed, não DB)"
---

# Handoff — SDD P04: prova 6/8 + shards-plan v2 mergeado (a causa da classe 1062 era o próprio harness)

Continuação do handoff [2026-07-13-0030](2026-07-13-0030-sdd-shard-survival-8de8-proof-em-voo.md). Peguei a prova em voo, fui dona do loop até o pouso (só leitura no CT100), diagnostiquei os mortos e landei o fix na mesma noite.

## Placar da prova (run `20260712-212018`)

| Shard | Estado | Nota |
|---|---|---|
| 0, 1, 3, 5, 6 | ✅ vivo | junit + summary coerentes |
| 2 | 💀 coleta | `already uses the test case` em `Modules/Jana/Tests/Feature/TaskRegistry/` — 12 quarentenas inúteis |
| 4 | 💀 coleta | MESMO modo em `tests/Feature/Domain/Fsm/` (2ª ocorrência independente) |
| 7 | ✅ vivo | killer real (`InterWebhookTest` PaymentGateway travou o processo) → `killer_from_events` quarentenou → retry fechou — **mecanismo #4188 provado em produção** |

**Noite (6 vivos, `all_shards_measured=false`):** 889 testcases falhando (495 f + 394 e) em 303 arquivos · 1.755 skipped · 29 loader-blockers. Inflado pela dupla-execução do plano v1 (abaixo).

## O que landou

**[PR #4199](https://github.com/wagnerra23/oimpresso.com/pull/4199) MERGED `39801d5850` (auto-merge no CI verde, 60 pass / 0 fail — Wagner autorizou):** o plano de shards particionava por "arquivos diretos" mas o pest varre o dir RECURSIVO → **99/143 unidades rodavam 2×+ (14 pais mistos)**. Isso matava shard por coleta (pai+filha juntos) E re-executava testes na mesma noite (pai e filha em shards distintos) → re-insert unique no MySQL persistente = **a classe SQLSTATE 1062 da "cascata de isolamento" era em parte auto-infligida pelo harness**. Fix: plano v2 (`shards[].paths` recursivo-disjuntos) + universe-gate morde `nested` + quarentena remove arquivo movido dos args. Detalhe: [session log](../sessions/2026-07-12-sdd-prova-6de8-plano-v2-disjuncao.md).

## ⚠️ EM VOO no fechamento

- **Lane pcov** da prova rodando (teto 4h → SIGKILL ~02:37 se não fechar). O passo [7/7] merge+floor+push pra órfã roda DEPOIS dela — **o floor fresco pousa hoje, mas depois deste handoff**. Verificação no próximo turno: `computed_at` do `nightly-floor.json` na órfã = `20260712-212018`.
- **Cron 02:00 de 13/jul deve PULAR** (flock preso pela prova) — não é incidente, é o comportamento anti-overlap. 1ª nightly 100% v2 = 02:00 de 14/jul.
- CT100 `/opt` self-update (*/15) + clone `$CODE` no início do run puxam o harness/plano v2 sozinhos.

## Estado MCP no momento do fechamento

- `brief-fetch`: flag 🔴 SDD composta 64,1 · alerta `full_suite_pass_rate` (esta frente). HITL pending 2 (FIN-004, runbook on-prem).
- `cycles-active`: nenhum cycle ativo em COPI (off-cycle).
- `my-work` @wagner: 10 tasks (9 review / 1 blocked) — nenhuma mapeia este trabalho (continuação de handoff + autorização direta Wagner "merge" / "assuma controle").

## Lições

1. **"Disjunto" na semântica do consumidor** — partição correta no modelo do gerador, errada no executor recursivo. O gate agora valida na semântica de execução (`nested` no universe-gate).
2. **Quarentena não cura erro de plano** — coleta duplicada com `already uses` é bug de harness; 24/29 quarentenas da noite foram giro em falso.
3. **Auto-merge + monitor** cumpriu R10 sem teatro: merge só disparou no CI 100% verde, com autorização explícita do Wagner no chat.
