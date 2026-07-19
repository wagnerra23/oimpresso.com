---
module: Brief
status: producao
updated_at: "2026-07-18"
na_justified:
  D3.b: "Brief não tem capacidades múltiplas pra um BRIEFING.md narrativo justificar. ADR 0091 já cumpre o papel de documento canônico do módulo."
related_adrs: ["0091-daily-brief", "0153-module-grade-rubrica-v1", "0154-module-grade-v2-na-justificado", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0278-arquitetura-rede-ia-duravel-anti-vazamento", "0294-metodo-dual-track-shapeup-catraca", "0317-maquina-revisao-adr-quando-rever-gatilhos"]
---

# BRIEFING — Modules/Brief

> **N/A justificado D3.b** — Brief é tool MCP atômica de infra. Documento canônico é [ADR 0091](../../decisions/0091-daily-brief.md). Este BRIEFING existe apenas como ponteiro pra cumprir governança Tier 0.

## O que é

Tool MCP **`brief-fetch`** — entrega snapshot consolidado do estado do projeto (~3k tokens) no início de cada sessão Claude Code, substituindo 5-8 chamadas exploratórias (cycles-active, sessions-recent, tasks-active, decisions-search).

## Por que existe

Economia ~27k tokens por sessão típica. Cache 5min trivial. Skill `brief-first` (Tier A always-on) força como primeira tool MCP.

## Como é gerado (2 estágios)

1. **Corpo via Brain B** — `BriefGeneratorService` roda `refresh_brief_inputs_cache()` (stored proc), lê a linha singleton de `mcp_brief_inputs_cache` e manda pro **OpenAI gpt-4o-mini** com prompt fixo de **7 seções ordenadas** (`ESTADO MACRO` · `EM VOO AGORA` · `DECISÕES RECENTES` · `SKILLS USO 7d` · `CHARTERS APODRECENDO` · `FLAGS` · `METADATA`), teto **≤8k tokens** ([ADR 0226](../../decisions/0226-brief-v2-1m-aware-rico.md), 1M-aware). `BriefValidator` exige exatamente os 7 headers.
2. **Injeções determinísticas pós-LLM** — `GenerateBriefCommand` costura linhas de sinal que o modelo nunca poderia inventar (estado de runtime), sob headers existentes (não cria header novo — quebraria o validador). Todas **best-effort**: `node`/`gh` ausente, tabela não-deployada ou qualquer erro → brief intacto, nunca quebra.

Cron **6×/dia** — `0 7,11,14,17,20,23 * * * America/Sao_Paulo` (o "daily 06:00 BRT" das versões antigas estava stale).

## Linhas de sinal injetadas no Daily Brief (pós-LLM)

O Daily Brief **ganhou várias linhas de sinal** desde 2026-06. Cada uma vem de um `*BriefLineService`/`*BriefSectionService` orquestrado em [`GenerateBriefCommand`](../../../Modules/Brief/Console/Commands/GenerateBriefCommand.php) (6 dos 7 injetores vivem em `Modules/Governance`; só o de leases mora em `Modules/Brief`):

| Sinal | Onde entra | Serviço · fonte | Origem |
|---|---|---|---|
| **Leases ativos** + nudge "claim antes de pegar" | sob `## EM VOO AGORA` | `LeaseBriefSectionService` → `WorkLeaseService::activeLeases()` · kill-switch `brief.lease_section` (default ON) | #2800 · [ADR 0278](../../decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md) |
| **Linha SDD** (composta do scorecard só quando mudou vs último snapshot OU alerta) | `## FLAGS` | `SddBriefLineService` (GT-G8) · kill-switch `governance.sdd_brief_line` = env `GOVERNANCE_SDD_BRIEF_LINE` (default ON) | #2630 · [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) |
| **Saúde dos planos** ("Planos: N vivos · X órfãos · Y a revisar") | `## FLAGS` | `PlanHealthBriefLineService` → shell-out `scripts/governance/plan-health.mjs --json`; catraca-irmã do gate CI advisory `plan-health-gate.yml` | #3103 · [ADR 0294](../../decisions/0294-metodo-dual-track-shapeup-catraca.md) |
| **Saúde do shipped-log** (porta de saída do loop) | `## FLAGS` | `ShippedLogBriefLineService` → shell-out `shipped-log-generate.mjs --json`; catraca-irmã de `shipped-log-gate.yml` | #3196 · ADR 0294 ext |
| **Revisão de ADR** (filas Check O morta-mas-canon + R vencida, top-3) | `## FLAGS` | `AdrReviewBriefLineService` → `memory-health.mjs --json`; cauda no flush trimestral `governance:adr-review-flush` | #3606 · [ADR 0317](../../decisions/0317-maquina-revisao-adr-quando-rever-gatilhos.md) |
| **Flag ADR pendente** (`🟠 ADR pendente: A:N B:N C:N`, só quando N>0) | `## FLAGS` | `AdrPendenteBriefLineService` → `adr-proposto-parado.mjs --json` (checks A/B/C do ciclo de ratificação) | #4053 · US-GOV-052 |
| **Outcome do agente (7d)** (DORA dos PRs do agente — aceitação, change-failure, time-to-merge + tendência vs 30d) | seção própria antes de `## FLAGS` | `AgentOutcomeBriefSectionService` → `agent-pr-outcomes.mjs --json` | #4053 · US-GOV-052 |

## Diferencial

Não tem concorrente — é infra interna. ADR 0091 cataloga decisão de design (3 ângulos faturamento, cron, persistência `mcp_briefs`).

## Estado

LIVE em produção (CT 100 MCP server `mcp.oimpresso.com`). Corpo LLM (gpt-4o-mini) + 7 linhas de sinal determinísticas pós-LLM. Cobertura Pest no módulo: `BriefValidatorTest`, `BriefFallbackTest`, `BriefMultiTenantTest`, `BriefLgpdComplianceTest`, `LeaseBriefSectionServiceTest`, `CycleDriftAlertTest`, `Wave28PolishTest`, `SmokeRoutesTest` (os testes das injeções de Governance vivem em `Modules/Governance/Tests/`).

## Documento canônico

Toda a especificação técnica e arquitetural está em **[ADR 0091 — Daily Brief](../../decisions/0091-daily-brief.md)**. Este BRIEFING não duplica conteúdo (Tier 0: "não duplicar info entre sistemas").

---
**Atualizado:** 2026-07-18 — refresh de frescor briefing↔código [CC]. Novas linhas de sinal do Daily Brief documentadas: **leases ativos + nudge claim** (#2800), **linha SDD** (#2630), **saúde dos planos** (#3103), **saúde do shipped-log** (#3196), **revisão de ADR** (#3606), **flag ADR pendente** + **outcome do agente 7d** (#4053). Corrigido o cron stale (6×/dia, não "daily 06:00 BRT") e frontmatter completado (`status`/`updated_at` + `related_adrs` em forma de slug).
