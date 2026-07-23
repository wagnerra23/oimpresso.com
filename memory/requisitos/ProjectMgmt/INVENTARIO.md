---
id: requisitos-project-mgmt-inventario
---

# CAPTERRA-INVENTÁRIO — ProjectMgmt

> **Atualizado 2026-05-08** — Fase 1 + Fase 2 entregues em 7 PRs (#207 #209 #211 #220 #222 #224 #226). Status detalhado por PMG-NNN em [SPEC.md](SPEC.md).
> Próximas atualizações via skill `comparativo-do-modulo` (executar `/comparativo ProjectMgmt`).
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (24 capacidades) + `Modules/ProjectMgmt/` + `resources/js/Pages/ProjectMgmt/`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).
> ADR mãe redesign: [0100](../../decisions/0100-projectmgmt-ui-redesign.md).

---

## Resumo

| Bucket | Quantidade | % | Δ vs 2026-05-07 |
|---|---|---|---|
| ✅ APROVADO | 13 | 54% | **+7** (Fase 1+2 fecharam drag-drop, Cmd+K, Tests, Detail, mentions, watchers, subtasks) |
| 🟡 PARCIAL | 0 | 0% | -5 (todas P0/P1 da Fase 2 viraram ✅) |
| ❌ AUSENTE | 11 | 46% | -2 |
| **Total** | 24 | 100% | — |

**Por score:**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador) | 3 | 2 | 1 | 6 |
| **P1** (mercado tem) | 3 | 2 | 6 | 11 |
| **P2** (diferenciação) | 0 | 1 | 6 | 7 |
| **P3** (opcional) | 0 | 0 | 0 | 0 |

**Diagnóstico:** módulo com **fundação sólida** (6 telas em prod + 7 controllers + 15 tabelas + 12 US-TR entregues) mas com **gap relevante de UX moderna** comparado a Linear/Jira:

- Drag-drop **incompleto** (cards draggable mas droppable column não persiste)
- Sem Cmd+K (atalho que define produtividade Linear-grade)
- Sem Detail Sheet (clique em card hoje não abre painel rico)
- Atalhos J/K/E/A documentados mas não implementados
- Sem presence Centrifugo (infra disponível desde ADR 0058 — só não integrada)
- Sem @mentions / watchers UI / sub-tasks UI / saved views backend

**Bloqueia ROI**: Wagner pediu redesign exatamente porque sente o gap. Linear é benchmark — alcançar 70% da fluidez Linear é meta realista pra Fase 1+2.

## Inventário detalhado

| # | Capacidade | Score | Status | Evidência | Falta |
|---|---|---|---|---|---|
| **1** | Kanban board drag-drop completo | **P0** | 🟡 | `Board/Index.tsx` 441 LoC + `BoardController::updateStatus(PATCH)` + draggable cards via TaskCard | **droppable column não implementado** + atomic 409 conflict + revert em erro + teste Pest |
| 2 | Backlog priorização visual + bulk operations | P0 | ✅ | `Backlog/Index.tsx` 390 LoC + `BacklogController::bulk(POST)` + 7 filtros + multi-select | — (pode adicionar audit log mais formal) |
| 3 | My Work + Inbox unread badges | P0 | ✅ | `MyWork/Index.tsx` 461 LoC + 3 endpoints inbox + grouped by cycle | — |
| 4 | Multi-tenant + Permissions Spatie cobertas por testes Pest | P0 | 🟡 | Permission `copiloto.mcp.usage.all` checada nas rotas | **0 tests** — `Modules/ProjectMgmt/Tests/` **não existe**, não registrado em `phpunit.xml` |
| 5 | Filters URL state-driven | P0 | ✅ | localStorage + URL state implementados em Board + Backlog | — (migrar pra Saved views backend é P1) |
| **6** | Search global Cmd+K | **P0** | ❌ | `cmdk` lib em package.json, sem uso visível em ProjectMgmt | **CommandPalette component + endpoint /search + atalho global** |
| **7** | Cycle close UI | P1 | ❌ | Tool MCP `cycles-close --rollover` existe (CLI only) | **Sheet/Page com retro markdown + rollover toggle + endpoint** |
| **8** | Sprint/Cycle planning UI | P1 | ❌ | — | **Modal "Add to cycle" do Backlog + endpoint POST /cycle/{id}/add-tasks** |
| **9** | Comments com @mentions | P1 | ❌ | Tabela `mcp_inbox_notifications` existe (vazia/parcial) | **MentionInput + parser + Notification dispatch + autocomplete members** |
| **10** | Watchers UI (follow/unfollow) | P1 | ❌ | Tabela `mcp_task_watchers` existe | **Botão Follow + endpoint + UI lista watchers + Notification dispatch pra watchers além de members** |
| **11** | Centrifugo presence (quem está vendo) | P1 | ❌ | Centrifugo provisionado ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) | **Hook usePresence() + canal `project-mgmt:board:{cycle_id}` + avatar stack TopBar + teardown** |
| **12** | Atalhos keyboard completos (J/K/E/A) | P1 | ❌ | Documentado no header da Board.tsx (linhas 13-15) | **useHotkeys hook + 9 shortcuts + overlay help (?)** — NADA implementado apesar de doc |
| **13** | Subtasks (1 nível + completion bar) | P1 | 🟡 | Coluna `parent_task_id` em `mcp_tasks` | UI tree no Detail Sheet + endpoint create subtask + completion % bar |
| **14** | Saved views backend (mover de localStorage pra DB) | P1 | 🟡 | localStorage funcional + tabela `mcp_views` existe (vazia) | UI 'Save view' / 'My views' / 'Shared' + endpoint CRUD + multi-tenant |
| **15** | Triage view (tasks sem owner/priority) | P1 | 🟡 | Tool MCP `triage` existe | Page `/project-mgmt/triage` dedicada + filtros embutidos |
| 16 | Activity feed timeline | P1 | ✅ | `Activity/Index.tsx` 254 LoC | — (refinar filtros é P2) |
| 17 | Burndown chart | P1 | ✅ | `Burndown/Index.tsx` 243 LoC + Line chart ideal vs real | — (multi-cycle / scope_creep é P2) |
| 18 | Roadmap quarterly | P1 | ✅ | `Roadmap/Index.tsx` 146 LoC + quarter grouping + progress bars | — (drag horizontal é P3) |
| **19** | Dependencies graph | P2 | ❌ | Tabela `mcp_task_dependencies` existe | UI gráfica + validação no PATCH status |
| **20** | Custom fields per project | P2 | ❌ | `mcp_components` (categorização leve) | Migration `mcp_custom_fields` + UI cadastro + render dinâmico |
| **21** | Workload view | P2 | ❌ | — | Page `/project-mgmt/workload` + agregação + viz |
| **22** | Time tracking interno | P2 | ❌ | `estimate_h` em mcp_tasks | Migration nova `mcp_time_logs` + Start/Stop UI + report |
| **23** | Templates de epic/cycle (clone) | P2 | ❌ | Tabela `mcp_issue_templates` existe (uso parcial) | Flag is_template + endpoint POST /from-template + UI seletor |
| **24** | Automation rules (when X then Y) | P2 | ❌ | — | Migration `mcp_automation_rules` + engine PHP + UI cadastro |

## Tasks propostas (aguardando aprovação Wagner)

> **Ordem por prioridade** + valor estratégico (capacidades que destravam outras vão primeiro). Cada task indica `module:ProjectMgmt priority:P{N}`.
> Aprovar com `/comparativo aprovar 1,2,4` ou texto livre. **NÃO foram criadas no MCP ainda.** Skill `publication-policy`.

### Fase 1 — Fundamentos UX moderna (P0 — sprint 1, ~6-8h)

1. **[P0] PMG-001** — **Drag-drop completo** (BoardColumn droppable + atomic PATCH + 409 conflict + optimistic UI + revert + teste Pest cobrindo R-PMG-001..003) — 4-6h. **Bloqueia: nada. Desbloqueia: percepção imediata de produto.**

2. **[P0] PMG-002** — **Cmd+K Search global** (CommandPalette via cmdk + endpoint `/project-mgmt/search?q=` + multi-tenant scoped + atalho global em AppShellV2 + teste) — 3h. Independente de PMG-001.

3. **[P0] PMG-003** — **Tests Pest Modules/ProjectMgmt/Tests/Feature/PermissionsTest** + registrar em `phpunit.xml` (cross-tenant 404, 403 sem permission, multi-tenant scope) — 2h. Bloqueia merges futuros sem regressão.

### Fase 2 — Detail Sheet + interações (P1 — sprint 2, ~8-10h)

4. **[P1] PMG-004** — **Detail Sheet completo** (slide-in à direita + tabs Description/Comments/Subtasks/Activity/Watchers/Dependencies + URL state `?task=ID` + preserveState) — 4h. Foundation pra muito do resto.

5. **[P1] PMG-005** — **@mentions em comments** (MentionInput autocomplete + parser server-side + Notification dispatch via `mcp_inbox_notifications`) — 3h. Bloqueia depois de PMG-004.

6. **[P1] PMG-006** — **Watchers UI** (botão Follow/Unfollow + lista no Detail + Notification dispatch pra watchers além de members) — 2h. Bloqueia depois de PMG-004.

7. **[P1] PMG-007** — **Subtasks UI** (tree no Detail + completion % bar + endpoint create subtask) — 3h. Bloqueia depois de PMG-004.

### Fase 3 — Workflow + atalhos (P1 — sprint 3, ~6-8h)

8. **[P1] PMG-008** — **Atalhos keyboard J/K/E/A/C/?** (useHotkeys hook + overlay help + preventDefault navegação browser) — 3h. Independente.

9. **[P1] PMG-009** — **Cycle close UI** (Sheet com Incompletas/Retro/Confirm + endpoint POST + rollover toggle + teste) — 3h. Bloqueia depois de PMG-004.

10. **[P1] PMG-010** — **Sprint planning Modal** ("Add to cycle" do Backlog + endpoint POST /cycle/{id}/add-tasks + multi-select integrado) — 2h. Independente.

### Fase 4 — Real-time + persistence (P1 — sprint 4, ~6h)

11. **[P1] PMG-011** — **Centrifugo presence** (hook usePresence + canal por cycle + avatar stack TopBar + teardown) — 3h. Foundation pra futuros real-time.

12. **[P1] PMG-012** — **Saved views backend** (mover de localStorage pra mcp_views + UI Save/My/Shared + endpoints CRUD) — 3h. Bloqueia: nada.

### Fase 5 — Diferenciação (P2 — backlog não-comprometido)

13. **[P2] PMG-013** — Triage view dedicada
14. **[P2] PMG-014** — Activity feed filtros + permalinks + lazy load
15. **[P2] PMG-015** — Burndown multi-cycle + scope_creep + projection
16. **[P2] PMG-016** — Dependencies graph
17. **[P2] PMG-017** — Time tracking interno + report
18. **[P2] PMG-018** — Workload view
19. **[P2] PMG-019** — Custom fields per project
20. **[P2] PMG-020** — Templates de epic/cycle
21. **[P2] PMG-021** — Automation rules

### P3 — Diferenciação opcional

22. **[P3] PMG-022** — Mobile responsive otimizado
23. **[P3] PMG-023** — Dark mode toggle
24. **[P3] PMG-024** — Roadmap timeline drag horizontal
25. **[P3] PMG-025** — Public share link read-only

## Próxima reauditoria sugerida

**2026-08-07** (trimestral) ou após mergear ≥3 das tasks P0/P1.

## Observações desta auditoria

- Módulo nasceu com **infra sólida** (6 telas + 7 controllers + 15 tabelas + 12 US-TR entregues) mas **UX moderna parcial** comparado a Linear/Jira.
- **Drag-drop incompleto** (PMG-001) é a single biggest UX win do redesign — alta visibility + esforço médio + valida resto da arquitetura.
- **Cmd+K** (PMG-002) é o segundo maior — produtividade keyboard-first é assinatura Linear.
- **Detail Sheet** (PMG-004) é foundation pra 4+ tasks subsequentes — vale priorizar Fase 2.
- **0% cobertura de testes** ([Tests/ não existe](../../../Modules/ProjectMgmt/)) — bloqueador soft pra confiança em mudanças. PMG-003 resolve.
- **Atalhos documentados sem implementação** (header Board.tsx:13-15) é débito visível — PMG-008 fecha gap perceptual.
- **Centrifugo presence** (PMG-011) é diferencial visível mas dep mais complexa — depois das fundações.
- **Custom fields** (PMG-019) é P2 mas exige migration nova — postergar até Wagner pedir explicitamente.
- **Time tracking interno** (PMG-022) **não confunde** com `pjt_project_time_logs` legacy (DELETADO Fase 3.8). Schema novo `mcp_time_logs` necessário se entrar.

## Insumos preservados de PR #197

O PR #197 mirou no `Modules/Project` legacy (queue-for-delete Fase 3.8) e foi mergeado com disclaimer pivot. Os critérios UX do `CHARTER-board.md` legacy são **reaproveitáveis** pro Charter atual:

- Anatomia 4 regiões (TopBar / FilterBar / Kanban / DetailSheet) — ✅ portado
- 6 fluxos críticos (drag-drop / detail / quick add / etc) — ✅ portado
- 8 estados de UI (loading / empty / error / 409 / etc) — ✅ portado
- Anti-padrões (modal full-screen / window.location.reload / etc) — ✅ portado

ADR 0099 (status `aceito`, conteúdo redirecionado pra Fase 3.8 deletion) é o doc de transição entre os 2 esforços.
