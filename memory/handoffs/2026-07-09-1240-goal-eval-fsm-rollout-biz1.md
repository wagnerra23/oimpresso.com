---
date: "2026-07-09"
time: "12:40 BRT"
slug: "goal-eval-fsm-rollout-biz1"
tldr: "US-SELL-036 executada em prod (goal-eval rodada 1): 5→0 vendas legadas biz=1 sem stage (170→175 com FSM, history 200→205), canary scan-drift iniciado (fecha 2026-07-16). Scorecard do IA OS em sessions/2026-07-09-goal-eval-us-sell-036-fsm-rollout.md."
us: [US-SELL-036]
next_steps:
  - "2026-07-16: fechar canary 7d (fsm:scan-drift daily 03:00 BRT sem drift) → US-SELL-036 review→done"
  - "Goal-eval rodada 2: US-RECURRINGBILLING-002/003 — precisa janela síncrona Wagner (REGRA MESTRE valor: dupla confirmação + antes→depois apresentado)"
  - "Fricções anotadas no scorecard (brief EM VOO null · rotina IA-OS SessionStart · número stale em task): decidir AJUSTAR/CORTAR só após 3-4 rodadas (ranking real)"
related_adrs: [0143-fsm-pipeline-live-prod-marco-2026-05-12]
---

# Goal-eval rodada 1 — FSM rollout biz=1 completo (5→0)

## O que foi feito

- **Outcome no mundo:** `php artisan fsm:bulk-start-pipeline 1 --limit=14` em prod Hostinger migrou as **5** vendas biz=1 restantes com `current_stage_id IS NULL` (OS00130–OS00134) pro pipeline FSM canon ADR 0143 — mapping `paid:3, invoiced:2`, 0 skip. biz=1 agora 100% no FSM (175/175 type=sell).
- A task falava "14 de 162" (números de 16/mai) — o mundo tinha 5 restantes (dry-run corrigiu antes de qualquer write). Detalhe + log de técnicas completo no session log.
- **Canary iniciado:** `fsm:scan-drift transactions` day-0 limpo + cron daily 03:00 BRT confirmado no `schedule:list`.
- **Smoke R1:** `/login` 200 · `/` 200 · `/sells` 302 (HTTP literal via `curl -sv`).
- **Registro:** US-SELL-036 `todo→doing→review` com acceptance_ref + comentário com evidência completa. Fica em review até canary fechar.
- **Sem governança nova** (regra 2 do teste): fricções só anotadas.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** em COPI (off-cycle).
- `my-work`: 30 tasks ativas @wagner — 8 review / 8 blocked / 14 todo; US-SELL-036 saiu de todo→**review** nesta sessão. Demais P0 todo: US-RECURRINGBILLING-002/003, US-OFICINA-026, US-COM-011, US-PROD-020/021, US-FISCAL-018, US-SELL-009, US-COM-007/008, FORJA-142.
- `sessions-recent`: **tool não exposta no registry MCP desta sessão** (fricção F3) — fallback `git ls-tree origin/main`: última session `2026-07-09-ds-loop-sync-git-espelho.md`, último handoff `2026-07-09-1118-ds-loop-sync-concluido.md`.
- `decisions-search "fsm bulk start pipeline canary"`: 0143 (FSM live prod) · 0284 (pipeline incidente graduado) · 0298 (teto governança) — nenhuma ADR nova aceita no intervalo desde o handoff 11:18 relevante ao escopo.
