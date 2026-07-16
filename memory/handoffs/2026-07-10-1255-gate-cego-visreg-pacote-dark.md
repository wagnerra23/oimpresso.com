---
date: "2026-07-10"
time: "12:55 BRT"
slug: gate-cego-visreg-pacote-dark
tldr: "Avaliação adversarial do protocolo design→code virou caça real: prova contrafactual achou o visual-regression CEGO em todo PR desde #3933 (07/jul, auto-referência no mode step) — fix merged #4088. Pacote dark entregue (Caixa no L2, baselines frescas, maps hotspots 13/13+5/6, Sells re-destilada). Flip L2→enforcing ABORTADO com evidência (assert binário flaky run-a-run) → US-036 p1 (double-threshold v2)."
prs: [4079, 4081, 4082, 4083, 4086, 4087, 4088, 4089]
related_adrs: [0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0291-distiller-modulo-verdade-contrato-emenda-0270-f3, 0299-figma-nao-e-fonte-de-design, 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma]
next_steps:
  - "US-_DESIGNSYSTEM-036 (p1): L2-v2 double-threshold → flip enforcing + contrafactual round 3 (quebra de superfície real, ex. filter invert)"
  - "Chip: PAT no modo update do visreg (PR de GITHUB_TOKEN não dispara CI; workaround close/reopen documentado)"
  - "US-COM-021: flakiness Compras dark/empty (mesma raiz do abort do flip)"
  - "US-_DESIGNSYSTEM-035: Showcase DS no VRT (rota superadmin + charter ausente mapeados)"
  - "Acompanhar task_52981b56 + task_f707fee6 (fidelidade, sessão 08/jul)"
---

# Handoff — Gate cego no visreg (3 dias) + pacote dark + flip L2 abortado

> Narrativa completa e evidências: [session log](../sessions/2026-07-10-avaliacao-protocolo-design-code-gate-cego.md). Este handoff é o estado pro próximo.

## O que o próximo agente PRECISA saber

1. **O `visual-regression` voltou a rodar de verdade em PR** (fix #4088). Entre 07/jul 17:38 e 10/jul 15:43, TODO pull_request pulou pixel L1/L2/Tier0/axe/smoke (auto-referência `steps.mode` no mode step; deveria ser `steps.changes`). PRs de UI mergeados nessa janela passaram sem verificação visual — se aparecer regressão visual "misteriosa" de 08-10/jul, a janela cega é a explicação.
2. **L2 estados isolados SEGUE ADVISORY** — flip abortado com evidência (mesmo SHA, conjuntos de falha diferentes entre attempts: oficina-os/compras). NÃO re-tentar flip sem a US-036 (double-threshold v2 + 3 runs estáveis). O comment do step no workflow carrega o forense.
3. **PR criado pelo runner (modo update) NÃO recebe CI** — destravar com close/reopen (provado 3× hoje: #4081/#4086). Órfãos antigos #3994/#3987 fechados.
4. **Baselines L2 frescas** (incl. `caixa-unificada` default+dark) e manifesto com 6 telas. Compras dark segue podado (flakiness, US-COM-021).
5. **Maps por região**: `caixa-unificada.map.json` (13/13) + `vendas.map.json` (5/6 + 1 TODO honesto) — âncoras por grep real, `design-code-map-check` [OK]. Gaps corrigidos (`prototipo:` apontava pra pastas mortas).
6. **Sells/BRIEFING.md re-destilada pelo distiller REAL** (CT100, carimbo 2026-07-10) — precedente #4061 seguido; staging deixado limpo. Título duplicado no output do distiller = bug cosmético conhecido.
7. **Figma MCP desconectado por [W]** (atrator do ADR 0299 morto na origem).

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** em COPI.
- `my-work` (@wagner): 10 ativas — 9 REVIEW (US-SELL-036 p0 FSM rollout; US-TR-305/306/307/309/310/311; US-PG-008; US-FIN-023) + 1 BLOCKED (FIN-4 cobrança ROTA LIVRE).
- Handoff irmão anterior: [2026-07-08 17:05 teste-protocolo-fidelidade-multieixo](2026-07-08-1705-teste-protocolo-fidelidade-multieixo.md).
- US criadas nesta sessão: US-COM-021 · US-_DESIGNSYSTEM-035 · US-_DESIGNSYSTEM-036 (blocos no SPEC via #4083 e este PR).

## Métricas que fundamentaram o dia (reusáveis)

- Re-trabalho visual 12/jun–10/jul: ~0,5 fix visual por entrega; 70% em 2 telas (Financeiro/Unificada + Caixa Unificada); dark = vetor nº 1.
- Doc-set do protocolo ≈ 67k tokens (14 docs) — motivou o padrão "não cacheia, aponta" (#4079).
- Lentes reais em PR: 1 → 8 (a diferença entre nominal e real ERA o gate cego).
