---
slug: 0134-tasks-create-respeita-spec-placeholders
number: 134
title: "tasks-create respeita placeholders em SPEC.md (regex headers + bullets)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: copiloto
tags: [governance, mcp, tasks, spec, drift, prevention]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0053-mcp-server-governanca-como-produto, 0070-jira-style-task-management-current-md-removed, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios]
pii: false
review_triggers:
  - "Detectado ≥1 drift duro em jana:health-check spec_id_drift por ≥3 dias consecutivos → reavaliar política (auto-renumerar? bloquear push?)"
  - "Regex `(?:^###|^-)\\s+...` der falso positivo em formato de SPEC novo → atualizar regex + test Pest"
  - "Tool MCP `spec-id-drift-list` ser criada (US follow-up) → atualizar este ADR mencionando-a"
---

# ADR 0134 — tasks-create respeita placeholders em SPEC.md

## Contexto

`tasks-create` no MCP server gera próximo ID livre via `TaskCrudService::gerarProximoIdCanonical()`. A lógica fazia `max(DB, SPEC) + 1`, mas o regex de leitura do SPEC pegava só **section headers** (`### US-XX-NNN ·`), ignorando **bullets** (`- US-XX-NNN —`).

Bullets são o formato canônico de **placeholders** em seções "Out of scope" e "Backlog futuro" do SPEC.md — IDs reservados pra detalhamento futuro mas que ainda não viraram story completa.

### Reincidência (2 dias seguidos)

- **2026-05-10:** placeholders 041-044 colidiram com IDs gerados pelo `/comparativo`; renumerados pra 053-056 (nota linha 577 `memory/requisitos/Whatsapp/SPEC.md`)
- **2026-05-11:** `tasks-create` pra fix UX (PR #527) gerou US-WA-053 colidindo com placeholder bullet `- US-WA-053 — "Mover conversa pra outro número"`; renumerados pra 057-060

Cada drift custou ~10min de renumeração + risco de quebrar refs em handoffs/ADRs/PRs.

## Decisão

**Defesa em 2 camadas:**

### Camada 1 — Prevenção

`TaskCrudService::gerarProximoIdCanonical()` (`Modules/Jana/Services/TaskRegistry/TaskCrudService.php:416`) usa regex que captura ambos formatos:

```regex
(?:^###|^-)\s+(?:\S+\s+)?US-{PREFIX}-(\d+)
```

- `^###|^-` = section header OU bullet
- `\s+(?:\S+\s+)?` = whitespace + opcional não-space-token (cobre emoji ✅/❌, status markers)
- Captura SÓ declarações no início de linha (menções inline em prose ignoradas)

Bound by Pest tests em `Modules/Jana/Tests/Feature/TaskRegistry/TaskCrudServiceCanonicalIdTest.php`:
- "considera placeholders em bullets (não só headers) — regressão US-WA-053"
- "regex bullet ignora menções inline em prose"

### Camada 2 — Detecção (defesa em profundidade)

Novo check `spec_id_drift` em `php artisan jana:health-check` (7º check, cron diário 06:00 BRT). Para cada `memory/requisitos/*/SPEC.md`:

1. Extrai entries detalhadas (regex section headers + `·` + title)
2. Compara `mcp_tasks.title` no DB
3. **ALERT** se mesmo `task_id` tem title divergente DB↔SPEC

Reporta ≤5 primeiros drifts inline; resto via `(+N mais)`.

## Não-objetivos

- **Auto-renumerar placeholders existentes.** Renumeração afeta refs externas (handoffs, ADRs, PRs) — sempre humano-no-loop.
- **Bloquear `git push` com drift.** Health-check apenas alerta. Bloqueio formal pode entrar via hook futuro (`block-spec-drift.ps1`) se reincidência continuar.
- **Tool MCP `spec-id-drift-list`** dedicada. Por enquanto, `jana:health-check --json` cumpre o papel (machine-readable). Tool MCP separada vira US follow-up se houver demanda real (≥3 buscas/semana).
- **Reformatar SPEC.md pra abolir bullets em "Out of scope".** Placeholders são legítimos enquanto não detalhados; é só o tooling que precisa entender ambos formatos.

## Consequências

### Positivas

- Eliminado drift recorrente que custou 20+ min de rework em 2 dias
- Health-check passivo detecta caso edge (alguém edita SPEC ao mesmo tempo que `tasks-create`)
- Pattern reutilizável: se outro tool ler IDs do SPEC, pode usar mesma regex

### Negativas / riscos

- Regex `(?:\S+\s+)?` é permissivo. Se SPEC introduzir formato novo (ex: `### [P0] US-XX-NNN`), pode pegar `[P0]` como prefix e continuar funcionando — mas falhar em formatos exóticos (`### US-XX-NNN-suffix`). Mitigado por test que ignora menções inline.
- Health-check adiciona ~50ms ao cron diário (glob + regex em ~15 SPEC.md). Aceitável.

## Implementação

- `Modules/Jana/Services/TaskRegistry/TaskCrudService.php` linha ~432 — regex estendido
- `Modules/Jana/Tests/Feature/TaskRegistry/TaskCrudServiceCanonicalIdTest.php` — 2 testes novos
- `Modules/Jana/Console/Commands/HealthCheckCommand.php` — `checkSpecIdDrift()` + register no array

## Alternativas consideradas

1. **Hook bloqueador `block-spec-drift.ps1`** — descartado: prevenção em runtime já elimina causa raiz. Hook bloqueador entra só se reincidência continuar pós-fix.
2. **Renumeração automática de placeholders** — descartado: renumeração afeta refs em handoffs/PRs/ADRs externos (efeito borboleta). Humano decide.
3. **Mover placeholders pra arquivo separado `PLACEHOLDERS.md`** — descartado: bullets em SPEC.md são contexto pro leitor humano; separar reduziria legibilidade.

## Skill / hook follow-up

Nenhum mandatório. Skill `module-completeness-audit` pode incluir `spec_id_drift` no checklist de governança em versão futura.

## Referências

- PR #527 — fix UX `/whatsapp/conversations` que disparou descoberta do bug
- US-COPI-105 — esta implementação
- US-WA-053 — caso concreto do drift
- `memory/requisitos/Whatsapp/SPEC.md` linha 577 — nota da renumeração 041-044 → 053-056 (2026-05-10) + 053-056 → 057-060 (2026-05-11)
