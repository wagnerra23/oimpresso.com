---
module: Brief
na_justified:
  D3.a: "Brief é MCP tool por design (Daily Brief brief-fetch). Não tem US-XXX-NNN tradicional — funcionalidade é UMA tool: gerar snapshot ~3k tokens. Documentação canônica é ADR 0091."
  D3.b: "Brief não tem capacidades múltiplas pra um BRIEFING.md narrativo justificar. ADR 0091 já cumpre o papel de documento canônico do módulo."
  D9.b: "Brief é geração síncrona via tool MCP + cron daily 06:00 BRT (artisan command direto, não dispatch). Não usa fila Horizon/failed_jobs — execução é determinística single-shot. ADR 0091 §arquitetura."
related_adrs: [0091, 0153, 0154]
---

# SPEC — Modules/Brief

> **N/A justificado D3.a + D3.b** — Brief é tool MCP atômica (não módulo de feature com US). Spec canônica vive em [ADR 0091](../../decisions/0091-daily-brief.md).

## Propósito (1 linha)

Gerar **snapshot consolidado do estado do projeto (~3k tokens)** entregue como tool MCP `brief-fetch`, substituindo 5-8 chamadas exploratórias no início de cada sessão Claude Code.

## Arquitetura (3 peças)

| Camada | Arquivo | Função |
|---|---|---|
| **Tool MCP** | `Modules/Brief/Mcp/Tools/BriefFetchTool.php` | Registra `brief-fetch` no MCP server, lê cache `mcp_briefs` (TTL 5min), serializa markdown |
| **Service** | `Modules/Brief/Services/BriefDiarioService.php` | Compõe brief: cycles-active + tasks-active + sessions-recent + decisions-recent + health-check |
| **Controller (cron)** | `Modules/Brief/Console/Commands/GerarBriefDiarioCommand.php` | Daily 06:00 BRT regenera + persiste em `mcp_briefs` |

## Por que NÃO tem SPEC tradicional (D3.a)

SPEC.md no padrão oimpresso lista **US-XXX-NNN** (user stories priorizadas P0-P3). Brief NÃO tem stories de usuário no sentido tradicional — é **uma única função técnica**: "dado o estado consolidado do projeto, retorne markdown ~3k tokens". Não há fluxos múltiplos, telas, RBAC, multi-tenant scope variável, nem capacidades incrementais.

A "spec" do que o brief deve conter (ângulos faturamento, cycles, decisões recentes, health) já está formalizada em [ADR 0091](../../decisions/0091-daily-brief.md) — esse ADR É o spec canônico.

## Por que NÃO tem BRIEFING.md narrativo (D3.b)

BRIEFING.md no padrão oimpresso ([template](../_DesignSystem/BRIEFING-TEMPLATE.md)) descreve **capacidades, diferenciais, score Capterra, UX visível, gaps**. Brief não tem UX visível (é tool MCP — output markdown), não tem concorrentes Capterra (é infra interna), não tem gaps de mercado a fechar.

BRIEFING.md mínimo existe como ponteiro pra ADR 0091 (cumpre regra Tier 0 "todo módulo tem BRIEFING").

## Skill canônica

`brief-first` (Tier A always-on) — força `brief-fetch` como primeira tool MCP em toda sessão. Definida em [`.claude/skills/brief-first/SKILL.md`](../../../.claude/skills/brief-first/SKILL.md).

## ADRs relacionadas

- **[ADR 0091](../../decisions/0091-daily-brief.md)** — Daily Brief (spec canônica, irreplaceable)
- **[ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md)** — Rubrica module-grade (origem do N/A justified)
- **[ADR 0154](../../decisions/0154-na-justified-modules-infra.md)** — N/A justified em módulos de infra (limite 3 por módulo)
