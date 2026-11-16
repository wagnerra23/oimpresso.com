---
date: "2026-11-14"
hour: "22:45 BRT"
duration: "1h"
topic: "Auditoria de observabilidade pré/pós-deploy — inventário, 14 gaps priorizados e stack mínima proposta"
authors: [W, C]
outcomes:
  - "RUNBOOK-observabilidade.md (rascunho) com inventário, gaps P0-P3, stack mínima e plano de 15 passos"
  - "Veredito: observabilidade NÃO pronta para produção multi-cliente — 5 bloqueadores P0"
  - "Nenhuma config de produção alterada"
prs: []
us: []
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0162-otel-collector-prod-observability"
  - "0429-log-de-falhas-glitchtip-self-host-ct100"
---

# Session log 2026-11-14 — Auditoria de observabilidade

## TL;DR

Auditoria só-leitura de `origin/main` (`20040dde34`). Existe muita sentinela caseira rodando (25 checks do `jana:health-check`, canários WhatsApp, backup monitor), mas o destino de quase todo alerta é o `laravel.log`, o SMTP está quebrado, não há uptime externo e não há heartbeat do scheduler. Proposta: Uptime Kuma (CT 100) + 1 olho externo free + GlitchTip (já de pé) + 1 canal Telegram/Slack. Aguarda [W].

## Contexto

Pedido [W]: papel de monitoramento/observabilidade em paralelo com outras sessões, sem tocar código de aplicação, sem config em produção. DoD: RUNBOOK + lista priorizada + session log + handoff.

## Cronologia

| Quando | Evento |
|---|---|
| 21:45 | `brief-fetch`, `my-work` (vazio), `cycles-active` (nenhum ativo), `decisions-search observabilidade` |
| 21:55 | Worktree em `origin/main` (checkout local estava em `532ba51fbb`, defasado) |
| 22:00 | 2 agentes de leitura em paralelo: lado app e lado infra |
| 22:40 | Verificação manual das afirmações críticas (Handler sem Sentry, sem `/up`, sem Telescope config, zero `thenPing`) |
| 22:45 | RUNBOOK + session + handoff |

## Entregas

- `memory/requisitos/Infra/RUNBOOK-observabilidade.md` (status `rascunho`)

## Decisões cinzentas resolvidas

- **Não propor Prometheus/Grafana/Loki agora:** custo de RAM e operação para 1 pessoa; Uptime Kuma + GlitchTip cobre os P0. As regras Prometheus existentes estão obsoletas (Baileys → wuzapi/whatsmeow).
- **Não usar o WhatsApp do sistema como canal de alerta:** dependência circular.
- **G12 (LGPD Fisio) e G13 (multi-tenant runtime) fora do plano:** precisam do dono do módulo / ADR.

## Aprendizados / pegadinhas

- Checkout em `D:\oimpresso.com` estava em `532ba51fbb`, ~2.400 PRs atrás de `origin/main` — auditar sempre em worktree de `origin/main`.
- Tool MCP `system-health-audit` devolveu 500 nesta sessão.
- `observability:aggregate-daily` lê uma tabela que nada escreve; `mcp_usage_diaria` idem — "verde" falso.
- Achado fora de escopo: master key do Meilisearch em claro em `.env - Copia.example` (linha ~154). Virou chip de tarefa separada; tratar como comprometida.

## Próximos passos (não-bloqueante)

Ver §6 do RUNBOOK — passos 1 (verificação read-only) e 2 (canal de alerta) destravam o resto.

## Referências

- [RUNBOOK-observabilidade.md](../requisitos/Infra/RUNBOOK-observabilidade.md)
- [AUDITORIA-OPS-DR-2026-07.md](../requisitos/Infra/AUDITORIA-OPS-DR-2026-07.md)
