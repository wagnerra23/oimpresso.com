---
paths:
  - "Modules/**/*.php"
---

# Rule path-scoped — `Modules/**/*.php`

> Carrega quando Claude lê/edita PHP dentro de qualquer módulo nWidart. Complementa skill Tier A `preflight-modulo` + hook `modulo-preflight-warning.ps1`.

## Workflow 3 fases obrigatório (Tier 0 IRREVOGÁVEL)

Regra Primária [`memory/proibicoes.md`](../../memory/proibicoes.md) §"REGRA PRIMÁRIA — Mexeu, REGISTRA":

1. **PRE-FLIGHT** — antes de Edit/Write ler:
   - `memory/requisitos/<Modulo>/SPEC.md` (US-XXX-NNN)
   - `memory/requisitos/<Modulo>/RUNBOOK-*.md` (se MWART .tsx)
   - `memory/requisitos/<Modulo>/CAPTERRA*.md` (escopo aprovado)
   - `memory/requisitos/<Modulo>/BRIEFING.md` (estado consolidado)
   - ADRs via `decisions-search query:"<modulo lowercase>"`
2. **DURING** — commit incremental por step lógico; `git push` WIP a cada ~30min; `TodoWrite` mark completed; NUNCA `git checkout` sem `stash`/`commit`
3. **POST** — `mexeu, registra` — PR no git + CI verde + merge + docs canon

## Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

- Toda Eloquent Model que toca dados de negócio DEVE ter `business_id` global scope
- NÃO usar `withoutGlobalScopes` sem comentário `// SUPERADMIN: <razão>`
- Job assíncrono SEMPRE recebe `$businessId` no constructor (session() não funciona em fila)
- Pest test biz=1 obrigatório ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)) — nunca biz=cliente real

## Padrões UltimatePOS herdados

- Stack middlewares rotas web: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`
- NÃO modificar tabelas core (`users`, `business`, `employees`) sem bridge table
- Roles Spatie com suffix `#{biz}` quando tabela `roles.business_id` NOT NULL existir

## Skills relacionadas

`preflight-modulo` (Tier A) · `multi-tenant-patterns` (Tier A) · `commit-discipline` (Tier A) · `criar-modulo` (Tier B) · `como-integrar` (Tier B)
