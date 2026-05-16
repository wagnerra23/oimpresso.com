<!-- Template canônico oimpresso (ADR 0094 Constituição v2 + ADR 0155 module-grades-gate). PT-BR. -->

## Resumo

<!-- 1-2 linhas: o que muda + por quê. Foco no "porquê"; o "o quê" o diff mostra. -->

## Intent (commit-discipline)

<!-- 1 PR = 1 intent (skill `commit-discipline` Tier A — ADR 0094 princípio #5 SoC). -->

- Tipo conventional commit: <!-- feat | fix | refactor | docs | test | chore | perf | build | revert -->
- Escopo: <!-- jana | repair | nfe-brasil | recurring-billing | governance | ct100 | mcp | claude-md | ... -->
- Posso descrever a mudança em 1 frase sem "e"? Se não → quebrar em 2 PRs.

## Refs

<!-- Sprint/Cycle + Task MCP. Regex GitTaskLinkerService aceita: `Refs: <KEY>-NNN`, `Closes: <KEY>-NNN`, `Fixes: <KEY>-NNN`. -->

- Refs: <!-- CYCLE-06 PASSO 4 | COPI-NN | US-RB-NNN | etc -->

## Tier 0 IRREVOGÁVEL — Multi-tenant scope (ADR 0093)

- [ ] `business_id` global scope preservado em Models novos/alterados (sem `withoutGlobalScopes` sem comentário `// SUPERADMIN: <razão>`)
- [ ] Jobs assíncronos passam `$businessId` no constructor (`session()` não funciona em fila)
- [ ] Tabelas novas têm `business_id` indexado + FK
- [ ] Cross-tenant Pest test biz=1 vs biz=99 se Model novo
- [ ] Sem PII real (CPF/CNPJ cliente) em código, commit message, log — usa `[REDACTED]` ou `PiiRedactor`

## Pest (ADR 0101)

- [ ] Tests novos cobrem caminhos críticos (regras de negócio, edge cases)
- [ ] Tests registrados em `phpunit.xml` (Modules/`Tests/` não auto-carrega — proibicoes.md)
- [ ] biz=4 (ROTA LIVRE cliente prod) **NUNCA** usado em fixture — usa biz=1 (ADR 0101)
- [ ] `php artisan test` local passou antes de abrir PR

## Module Grades Gate (ADR 0155)

Workflow `Module Grades Gate (anti-regressão)` roda automaticamente. Se a nota de qualquer módulo cair vs `governance/module-grades-baseline.json`, opções:

1. **Corrigir código** — fazer a nota subir/manter (preferível)
2. **Justificar regressão temporária** — aplicar label `module-grades-allowed-regression` no PR + comentário com razão + ADR/Issue linkada (Wagner ciente)
3. **Módulo novo entrando no baseline** — aplicar label `module-grades-new-module-allowed` + PR separado aprovado por Wagner atualizando `governance/module-grades-baseline.json`

> Baseline é atualizado **manualmente via PR aprovado por Wagner** — nunca auto. Override deixa rastro em comentário automático no PR linkando ADR justificativa.

## MWART (ADR 0104) — preencher se PR toca `resources/js/Pages/<Mod>/<Tela>.tsx`

- [ ] RUNBOOK `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` existe + foi consultado
- [ ] `.charter.md` ao lado do `.tsx` existe + foi lido (skill `charter-first`)
- [ ] F2 BACKEND BASELINE — Pest 5+ fixtures do `store()` rodaram verdes antes do Edit
- [ ] F4 QA com smoke biz=1 (ADR 0101) — biz=4 só em F5 cutover canary 7d
- [ ] Sem violação dos 6 meta-anti-padrões / 15 técnicos catalogados em `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`

## Performance (D6 do module-grade v3 — ADR 0155)

<!-- Preencher se PR altera Controller / Service / página Inertia. -->

- [ ] `Inertia::defer` aplicado em props caras (paginate / count / with eager-load / aggregated / Service-DB / HTTP externo) — skill `inertia-defer-default` Tier B + proibicoes.md §"Sempre fazer"
- [ ] Sem N+1 introduzido (Controller com `paginate(` tem `->with(` explícito ou justificativa)
- [ ] OTel span adicionado se Service novo que toca DB/HTTP/LLM (D9.a — ADR 0155)

## Briefing (regra "mexeu, registra" — proibicoes.md)

<!-- Preencher se PR toca `Modules/<X>/` + `resources/js/Pages/<X>/` alterando capacidade/diferencial/UX. -->

- [ ] `memory/requisitos/<X>/BRIEFING.md` atualizado se capacidade mudou (skill `brief-update` Tier B auto-trigger)
- [ ] `memory/requisitos/<X>/SPEC.md` US-XXX-NNN atualizada com novo status se aplicável

## Como testar

<!-- Passos manuais smoke (URL local, credencial dev, payload exemplo se API). Sem PII real. -->

1.
2.
3.

URL local: `http://oimpresso.test/...`

## Rollback

<!-- Como reverter se quebrar prod (Hostinger ou CT 100). -->

- Revert PR: `gh pr revert <NN>` → novo PR de revert → merge
- Restore DB (se migration destrutiva): seeder de baseline em `database/seeders/...`
- Comunicar Wagner antes se ROTA LIVRE (biz=4) afetada — 99% do volume de vendas (regras-time.md)

## Pré-flight Wagner regra "mexeu, registra" (proibicoes.md §"REGRA PRIMÁRIA")

<!-- 3 fases obrigatórias antes/durante/depois — proibicoes.md evolução 2026-05-15. -->

- [ ] **PRÉ-FLIGHT** — leu SPEC + RUNBOOK + CAPTERRA + ADRs do módulo antes de qualquer Edit/Write
- [ ] **DURING** — commits incrementais por step lógico, `git push` WIP a cada ~30min, `TodoWrite` mark completed
- [ ] **POST** — este PR (regra "mexeu, registra")

---

_Refs: [ADR 0094 Constituição v2](/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) · [ADR 0155 module-grade v3 + gate CI](/memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) · [ADR 0093 Multi-tenant Tier 0](/memory/decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0104 MWART](/memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0101 Tests biz=1](/memory/decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0070 Tasks MCP](/memory/decisions/0070-jira-style-task-management-current-md-removed.md) · skill [commit-discipline](/.claude/skills/commit-discipline/SKILL.md)_
