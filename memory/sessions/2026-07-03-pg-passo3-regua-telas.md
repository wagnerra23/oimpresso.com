# Session — PaymentGateway Passo 3 (régua por tela) 2026-07-03

> **Continuação** de [2026-07-03-capterra-paymentgateway.md](2026-07-03-capterra-paymentgateway.md) (Passos 1+2, PR #3736 mergeado). Wagner: "vai".
> Passo 3 do [template-onda-modulo](../requisitos/_Governanca/programa-ondas/template-onda-modulo.md): régua por tela = `screen-grade` (UX) + `casos_coverage` (UC) + dente D1 se toca valor. Base `origin/main`@`85e10d3c`. Read-only sobre código.

## 2 telas do módulo

| Tela | screen-grade | casos_coverage | D1 valor |
|---|---|---|---|
| **Settings/PaymentGateways/Index** | **80 Advanced** (mantido) | 🧪 8 casos com teste Feature, 0 UC-id | não (config; toggle libera emissão) |
| **Settings/PaymentGateways/CnabRetorno** | **76 Advanced** (⬆️ era 58) | 🧪 8 casos `CnabRetornoProcessorTest`, 0 UC-id | 🔴 **sim** — reconcilia cobranças (quita título) |

## Achados

1. **Scorecards estavam stale (30/mai).** O CnabRetorno marcava **58 (Developing)** com 3 gaps: "sem charter / sem dropzone / sem EmptyState DS-v4" — **todos já resolvidos** no código atual (migração DS v4 feita). Re-grade honesto **58→76** (ratchet permite subir). Index seguia 80 (bate com o julgamento atual — mantido).
2. **Débito real não é ausência de teste — é rastreabilidade (G-2).** As 2 telas têm baseline Feature forte (Index: toggle/Tier-0 scope/cross-tenant 404/read-only GET/store; CnabRetorno: dispatch Paga/Cancelada/Vencida + idempotência + scope). Nenhum teste cita `UC-PG-NN` → 0 UC no manifesto. Promoção = 1 linha por caso (citar id no teste existente).
3. **Exposição Tier-0 nº1 (CnabRetorno):** o upload MOVE VALOR (quita títulos via `CnabRetornoProcessor`) **sem preview antes→depois** — vai direto pro background. Candidato P0 (REGRA MESTRE valor). Registrado no casos.md como backlog.
4. **Charter CnabRetorno ainda `draft`** com Non-Goals abertos (reprocessamento/download/lote/AuditLog) — resolver com Wagner antes de fechar catraca.

## Entregáveis

- `resources/js/Pages/Settings/PaymentGateways/Index.casos.md` (novo) — 9 casos backlog (8 com teste Feature, 1 sem)
- `resources/js/Pages/Settings/PaymentGateways/CnabRetorno.casos.md` (novo) — 10 casos backlog (8 com teste, 2 sem, incl. preview D1)
- `memory/governance/scorecards/screens/settings-paymentgateways-cnabretorno.yaml` — re-grade 58→76 + resumo/gaps atuais

## Gates (rodados localmente — pré-PR)

- `screen-grades-ratchet.mjs`: ✅ 222 telas, 0 regrediram (CnabRetorno subiu)
- `screen-coverage-map.mjs --check`: ✅ sem regressão
- `casos-coverage-guard.mjs`: ✅ sem violação nova, **débito −6** vs baseline

## Próximo (Passo 4 — catraca + sentinela)

Ao promover cada UC-PG-NN (com teste citando o id), a `casos-gate` passa a defendê-lo; `exposicao-tier0` vigia o CnabRetorno (dinheiro). Preview antes→depois (D1) é o P0 de valor da tela.
