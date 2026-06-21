---
page: /team-mcp/scorecard
component: resources/js/Pages/team-mcp/Scorecard/Index.tsx
owner: wagner
status: draft
last_validated: "2026-06-16"
parent_module: TeamMcp
related_adrs:
  - "0091-daily-brief"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0114-prototipo-ui-cowork-loop-formalizado"
related_ficha: memory/requisitos/TeamMcp/scorecard-visual-comparison.md
tier: A
charter_version: 1
---

# Page Charter — `/team-mcp/scorecard` (DRAFT)

> Criado no PR **Forja PR-3** (2026-06-16). **A Page não existia** — `ScorecardController@index` renderizava `team-mcp/Scorecard/Index` sem componente correspondente (rota quebrada). Este PR cria a Page em DS v6. Persona: Wagner [W] (superadmin, `copiloto.mcp.usage.all`). Backend: `ScorecardController` + `ScorecardBuilderService` (Inertia::defer facts/checks). Ref: [scorecard-visual-comparison.md](../../../../memory/requisitos/TeamMcp/scorecard-visual-comparison.md).

## Mission

Painel **read-only** de saúde do MCP no padrão **Facts + Checks** (ADR 0091): separa número (sem juízo) de juízo (semáforo ok/fail). Operação primária: Wagner bate o olho em "tá tudo verde?" e, se não, entra nos números. **Sem dado fantasma** — projeta só o que `ScorecardBuilderService` retorna.

## Goals — Features (faz)

- **Semáforo geral**: banner verde (tudo OK) / amarelo (N falhando).
- **Facts** (KpiCards): tokens ativos · calls 7d · custo 7d (BRL) · devs ativos 7d + Top tools 7d + aviso de tabela ausente.
- **Checks** (lista semáforo): cada dimensão com ícone ok/fail + nome + detail + pill.
- Atalho `R` + botão Atualizar (reload defer facts/checks).
- Loading skeleton (defer) + meta (gerado em / pattern / fonte).

## Non-Goals — Features (NÃO faz)

- ❌ **Sparkline** — não há série temporal real no builder; render de série exigiria nova métrica (fora de "só Facts+Checks atuais", §3). Deferido até existir um trends builder real.
- ❌ Drill que abre outra tela (detail já vem inline no check).
- ❌ Mutação (tela 100% read-only).
- ❌ Filtro business_id — scorecard é repo-wide intencional (ADR 0093).

## UX targets

- DS v6: semáforo via tokens semânticos (success/warning/destructive), **sem cor crua**, `tabular-nums` nos números, ramp `--fs`.
- KpiCard shared pros Facts; lista de checks com ícones lucide. Locators `data-testid`.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO escreve nada. ❌ NÃO inventa métrica/série. ❌ NÃO expõe valor de cap de custo (Tier 0 — o backend já redige o label).

## Restrições Tier 0

- Permissão `copiloto.mcp.usage.all` no construtor.
- Repo-wide cross-business INTENCIONAL (ADR 0093) — governança da plataforma.

## Métricas de sucesso (validação Wagner)

- ✅ Rota `/team-mcp/scorecard` deixa de quebrar (Page existe).
- ✅ Semáforo reflete N/M checks; verde quando todos OK.
- ✅ Facts mostram números reais do builder; Top tools listado.
- ✅ Sem cor crua (conformance/foundation/eslint-baseline verdes).
