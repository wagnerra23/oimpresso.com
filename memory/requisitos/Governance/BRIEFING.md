---
distilled_at: "2026-07-01"
distilled_by: jana:distill-module-truth
module: Governance
---

# BRIEFING — Governance (verdade destilada)

## Estado atual
O módulo **Governance** atua como enforcer da **Constituição v2**, habilitando a execução de princípios em um ambiente multi-tenant através de checks executáveis. Está **ativo**; na medição de 2026-05-16 a grade era 49/100 com meta 84 (Wave G) — desde então a rubrica evoluiu pra v3/v4 e o módulo seguiu recebendo PRs em jun/2026 (ex.: US-GOV-043).

## Capacidades
- **Dashboard `/governance`**: Visualização centralizada das pendências, alertas de drift, e grades de módulos.
- **Policies**: listagem por categoria + toggle (com campo versão); CRUD completo e audit trail (`mcp_governance_rule_history`) planejados — Fase 5+1.
- **Audit log**: Registro imutável de decisões e mudanças.
- **Drift alerts**: Detecção automática de modificações não registradas.
- **ActionGate middleware**: Controle de ações críticas em diferentes modos.
- **Module Grades**: Avaliação programática de módulos, com rubrica de notas.
- **CLI `php artisan module:grade`**: Execução de grades diretamente pelo terminal.

## Gaps
- **Cobertura SPEC formal**: era **20%** na medição 2026-05-16; o SPEC.md canônico existe (v1.0, atualizado 2026-06-21, 68 refs US-GOV) — re-medir cobertura de US.
- **Documentação**: **40%** pronta na medição 2026-05-16; faltavam detalhes em SPEC e CAPTERRA.
- **Cobertura Pest cross-tenant**: **40%** na medição 2026-05-16.

## Última mudança
Em 2026-05-16 o Module Grades landou (PR #948) com a grade do módulo em 49/100; em jun/2026 o módulo recebeu novos PRs (ex.: US-GOV-043 em #3195) e a rubrica evoluiu pra v3/v4 (`module:grade-v4`).

## Proveniência (destilado de)

- session `sessions/2026-06-29-arte-constituicao-adr-resumo-indexacao.md` (2026-06-29) — 2026-06-29-arte-constituicao-adr-resumo-indexacao.md
- session `sessions/2026-06-21-verificacao-rede-onda0-estado-real.md` (2026-06-21) — 2026-06-21-verificacao-rede-onda0-estado-real.md
- handoff `handoffs/2026-06-21-1258-onda1-gaps-cruzados.md` (2026-06-21) — 2026-06-21-1258-onda1-gaps-cruzados.md
- session `sessions/2026-06-20-sdd-avaliacao-30threads.md` (2026-06-20) — 2026-06-20-sdd-avaliacao-30threads.md
- handoff `handoffs/2026-06-20-2302-spec-schema-gate-e-aposentar-governance.md` (2026-06-20) — 2026-06-20-2302-spec-schema-gate-e-aposentar-governance.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- session `sessions/2026-06-13-auditoria-senior-maturidade-ds-oimpresso.md` (2026-06-13) — 2026-06-13-auditoria-senior-maturidade-ds-oimpresso.md
- session `sessions/2026-06-13-ds-harmonizado-tela-linda-cliente.md` (2026-06-13) — 2026-06-13-ds-harmonizado-tela-linda-cliente.md
- session `sessions/2026-06-13-prompts-burndown-f2b-pos-triage.md` (2026-06-13) — 2026-06-13-prompts-burndown-f2b-pos-triage.md
- session `sessions/2026-06-13-reconciliar-ds-critic-s1-s5-cx.md` (2026-06-13) — 2026-06-13-reconciliar-ds-critic-s1-s5-cx.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
