# RUNBOOK — `/governance/module-grades` (Index + Show)

> **Tela canônica greenfield** Inertia React. Dashboard de notas dos 34 Modules (ADR 0153 rubrica `module-grade-v1`).
> NÃO é migração MWART Blade→Inertia (não existe Blade legacy desta tela). Override implícito por ser greenfield.

## Mission

Wagner (e Felipe/Maiara/Eliana/Luiz quando entrarem) abrem `/governance/module-grades`, veem **nota 0-100 + bucket de cor** pra cada módulo, drill-down em qualquer um pra ver **5 dimensões + top gaps**, e clicam **botão "Evoluir"** que gera batch de tasks-create sugeridas pra fechar gaps prioritários.

## Goals

1. **Visibilidade total** — abrir tela = ver maturidade do projeto inteiro em 5s
2. **Drill-down acionável** — clicar módulo = entender ONDE está o gap (qual dimensão)
3. **Ação canônica** — botão Evoluir = batch tasks-create suggested (MVP A: copy markdown; futuro: integração MCP direta)
4. **Tracking 90d** — snapshot diário em `mcp_module_grades_history` (Fase B — opcional v2)

## Non-Goals (NÃO fazer)

- ❌ Editar pesos da rubrica na UI (rubrica é ADR canônica — muda via append-only ADR 0154 v2)
- ❌ Auto-disparar agents Brain B (custo $$$ + risco regressão) — limite MVP é gerar tasks
- ❌ Comparar histórico módulo-vs-módulo (Fase B se demanda)

## UX targets

- **Index** (`/governance/module-grades`):
  - Tabela ordenada por nota descendente
  - Cor por bucket (verde/azul/amarelo/laranja/vermelho)
  - Filtro rápido por bucket (chips)
  - Busca por nome
  - KPI agregado: média projeto + distribuição buckets
  - Click row → drill-down `/governance/module-grades/{module}`

- **Show** (`/governance/module-grades/{module}`):
  - Header: nome módulo + nota + bucket
  - 5 cards dimensões (D1-D5) — score/max + breakdown sub-itens com evidência
  - Lista top gaps ordenada (perda absoluta de pontos)
  - Botão **"Evoluir"** primário — abre modal/drawer com batch tasks sugeridas + copy-as-markdown button

## Plug-points

- **Controller:** `Modules/Governance/Http/Controllers/ModuleGradeController.php` — métodos `index()` e `show($module)`
- **Service:** `Modules/Governance/Services/ModuleGradeService.php` — injetado via DI
- **Routes:** adicionadas a `Modules/Governance/Http/routes.php` no grupo `governance.*`
- **Pages:** `resources/js/Pages/governance/ModuleGrades/Index.tsx` + `Show.tsx`
- **Charter:** `Index.charter.md` + `Show.charter.md` ao lado dos `.tsx`
- **Layout:** `AppShellV2` (padrão Governance — herdado Dashboard.tsx)

## Anti-hooks

- **Inertia::defer** pra `gradeAllModules()` no Index — Service roda I/O filesystem 1-2s × 34 módulos. Sem defer trava initial render
- **Cache 5min** server-side pra `gradeAllModules()` — re-rodar não muda muito em 5min
- **Sem PII** — nomes módulos são públicos, mas evidence pode conter paths de teste → sanitizar antes de render

## Dependências canônicas

- [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md) — rubrica oficial
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — D1 base
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — D1.b validação
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — D5 nível
- ADRs Governance: 0086 Fase 5 MVP, 0094 Constituição v2

## Critério de aceite

- [ ] `php artisan module:grade --all` retorna tabela ranqueada 34 módulos
- [ ] `/governance/module-grades` carrega <2s com partial reload
- [ ] Click row → drill-down funcional
- [ ] Botão Evoluir abre modal/drawer com batch tasks
- [ ] Copy markdown copia formato pronto pra colar no Claude Code
- [ ] Pest cobre Service (5 dimensões) + Command (--all, --evolve, --json) + Controller (smoke route Tier 0)
- [ ] phpunit.xml já cobre Modules/Governance/Tests/Feature (registrado Wave B)
