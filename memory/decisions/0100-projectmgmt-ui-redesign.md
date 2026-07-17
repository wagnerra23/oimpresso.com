---
slug: 0100-projectmgmt-ui-redesign
number: 100
title: "ProjectMgmt UI Redesign — Linear-tier UX em 4 fases capterra-driven"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: governance
quarter: 2026-Q2
tags: [projectmgmt, ui, redesign, capterra-driven, jira-like, linear-style]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0058-reverb-substituido-por-centrifugo-frankenphp, 0070-jira-style-task-management-current-md-removed, 0079-constituicao-oimpresso-7-camadas-governanca, 0080-trust-tiers-operacional-audit-findings, 0086-fase-5-mvp-governance-actiongate-warn, 0089-capterra-driven-module-evolution, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0099-project-legacy-discovery-pre-deletion]
pii: false
review_triggers: ["Após Fase 1 mergeada (drag-drop + Cmd+K) reavaliar Fase 2 com base em uso real do time", "Se time não adotar Cmd+K em 14d, reconsiderar prioridade Fase 1"]
---

# ADR 0100 — ProjectMgmt UI Redesign: Linear-tier UX em 4 fases capterra-driven

## Contexto

Wagner pediu em 2026-05-07: "redesenhar UI do ProjectMgmt baseado no Jira/Linear" (sessão `2026-05-07-project-mwart-discovery-fase0` — pivotada).

Estado atual do `Modules/ProjectMgmt`:

- **Em prod desde 2026-05-04** ([PR #91](https://github.com/wagnerra23/oimpresso.com/pull/91), [PR #92](https://github.com/wagnerra23/oimpresso.com/pull/92), [ADR 0070](0070-jira-style-task-management-current-md-removed.md))
- **6 telas Inertia/React** em `resources/js/Pages/ProjectMgmt/`: Board (441 LoC) / Backlog (390) / MyWork (461) / Activity (254) / Burndown (243) / Roadmap (146) — total 1935 LoC
- **7 controllers**: Board / Backlog / Roadmap / MyWork / Burndown / Activity + Admin/Projects + DataController + InstallController
- **15 tabelas `mcp_*`** owned (mcp_jira_projects / epics / cycles / tasks / task_attachments / comments / dependencies / events / memory_links / watchers / components / views / inbox_notifications / issue_templates / cycle_goals)
- **0 tests** — `Modules/ProjectMgmt/Tests/` não existe nem registrado em `phpunit.xml`
- **Permission single**: `copiloto.mcp.usage.all` (pattern UltimatePOS)
- **SCOPE.md**: rename pra `Modules/Project` previsto em **Fase 3.9** ([ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md)), **após Fase 3.8** (delete `Modules/Project` legacy — em PR #202).

Diagnóstico via [INVENTARIO.md](../requisitos/ProjectMgmt/INVENTARIO.md) (24 capacidades inventariadas):

| Score | ✅ | 🟡 | ❌ | Gap |
|---|---|---|---|---|
| **P0** (bloqueador) | 3 | 2 | 1 | **3 a fechar** (drag-drop completo, Cmd+K, tests Pest) |
| **P1** (mercado tem) | 3 | 2 | 6 | **8 a fechar** |
| **P2** (diferenciação) | 0 | 1 | 6 | postergar até pedido explícito |
| **P3** | 0 | 0 | 0 | NA |

**Gap percebido**: Wagner sente dor concreta ao usar — drag-drop não persiste, sem Cmd+K, sem Detail Sheet, atalhos documentados (Board.tsx:13-15) **não implementados**. Linear é benchmark de fluidez (~100ms ações) que o time admira.

Restrições:

- [ADR 0089](0089-capterra-driven-module-evolution.md) — toda evolução de módulo passa pelo trio CAPTERRA-FICHA + INVENTARIO + SPEC + ADR
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL: business_id global scope, jobs com `$businessId` no constructor
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 Charter > Spec, §5 SoC brutal
- [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo+FrankenPHP é o real-time canon (substituindo Reverb)
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — Jira-style task management (escopo do módulo)
- [ADR 0099](0099-project-legacy-discovery-pre-deletion.md) — discovery do legacy `Modules/Project` queue-for-delete (insumo histórico)
- Princípios CLAUDE.md: 1 PR ≤300 linhas, 1 intent por PR, conventional commits, refs cycle/sprint
- Skill `mwart-quality` Tier B auto-trigger ao Edit `.tsx` em Modules/

Alternativas avaliadas:

| Alternativa | Por que não |
|---|---|
| **Big-bang rewrite** (todas 6 telas + features novas em 1 sprint) | 30-40h sem feedback loop; quebra prod; viola "1 PR = 1 intent"; alto risco em CYCLE-02 com NfeBrasil P0 ativo |
| **Adicionar lib `@hello-pangea/dnd`** desde já | Pacote pesado (`+~80KB`); HTML5 native suficiente pra drag entre colunas; reorder dentro de coluna é P2 |
| **Trocar Centrifugo por Pusher SaaS** | Quebra [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md); custo recorrente; perde control |
| **Incluir custom fields agora** | Migration nova mcp_custom_fields não justificada hoje (Wagner não pediu); P2 do INVENTARIO |
| **Fork pra desenvolver em paralelo (não tocar prod)** | Coexistência viola SoC; produção continua com gap; perde momentum |

## Decisão

**Redesign incremental do `Modules/ProjectMgmt` em 4 fases capterra-driven, com gate humano entre cada fase, mirando atingir 70% da fluidez Linear ao fim da Fase 4.**

### Fase 0 — Discovery (esta sessão, 2026-05-07)

Sai com 4 artefatos no git em `memory/requisitos/ProjectMgmt/`:

- [`CAPTERRA-FICHA.md`](../requisitos/ProjectMgmt/CAPTERRA-FICHA.md) — 24 capacidades P0-P3 vs Linear / Jira / Asana / ClickUp / Plane / Productive
- [`CHARTER-board.md`](../requisitos/ProjectMgmt/CHARTER-board.md) — contrato vivo da Page existente (anatomia / personas / fluxos / estados / regras / anti-padrões / métricas)
- [`INVENTARIO.md`](../requisitos/ProjectMgmt/INVENTARIO.md) — gap analysis ✅🟡❌ + 25 PMG-NNN tasks propostas P0-P3
- Esta ADR 0100

**Fase 0 NÃO escreve código de aplicação.** Sai com plano travado.

### Fase 1 — Fundamentos UX moderna (1 cycle, 6-8h)

Cobre PMG-001, PMG-002, PMG-003 ([INVENTARIO § Fase 1](../requisitos/ProjectMgmt/INVENTARIO.md)):

- **PMG-001 — Drag-drop completo** (BoardColumn droppable + atomic PATCH com optimistic-lock + 409 conflict + revert + teste Pest)
- **PMG-002 — Cmd+K Search Global** (CommandPalette via `cmdk` lib já instalada + endpoint `/project-mgmt/search` + multi-tenant + atalho global em AppShellV2)
- **PMG-003 — Tests Pest Modules/ProjectMgmt/Tests/Feature/** + registrar em `phpunit.xml` (cross-tenant 404, 403 sem permission, R-PMG-001..003)

**Aceitação Fase 1**: Wagner usa drag-drop persistente + Cmd+K diariamente em prod por 7 dias sem regressão. ≥4 tests Pest verdes.

### Fase 2 — Detail Sheet + interações (1 cycle, 8-10h)

Cobre PMG-004 a PMG-007:

- **PMG-004 — Detail Sheet completo** (slide-in à direita + tabs Description / Comments / Subtasks / Activity / Watchers / Dependencies + URL state `?task=ID` + preserveState)
- **PMG-005 — @mentions em comments** (MentionInput autocomplete + parser + `mcp_inbox_notifications` dispatch)
- **PMG-006 — Watchers UI** (botão Follow/Unfollow + lista no Detail + Notification dispatch)
- **PMG-007 — Subtasks UI** (tree no Detail + completion % bar + endpoint create subtask)

**Aceitação Fase 2**: ≥10 comentários trocados via UI em prod; ≥5 tasks com sub-tasks reais; @mentions disparando notification em ≥3 casos reais.

### Fase 3 — Workflow + atalhos (1 cycle, 6-8h)

Cobre PMG-008 a PMG-010:

- **PMG-008 — Atalhos keyboard** (J/K/E/A/C/?/Esc/`/`/Cmd+K) com overlay help + preventDefault navegação browser
- **PMG-009 — Cycle close UI** (Sheet com tabs Incompletas / Retro / Confirm + endpoint POST + rollover toggle)
- **PMG-010 — Sprint planning Modal** ("Add to cycle" do Backlog + endpoint POST /cycle/{id}/add-tasks)

**Aceitação Fase 3**: ≥80% das sessões de Wagner usam atalho J ou K; cycles fechados em prod com retro markdown. Closes US-TR-302 indirectly.

### Fase 4 — Real-time + persistence (1 cycle, ~6h)

Cobre PMG-011 + PMG-012:

- **PMG-011 — Centrifugo presence** (hook `usePresence` + canal `project-mgmt:board:{cycle_id}` + avatar stack TopBar + teardown em unmount). Foundation pra futuros real-time (notifications, comments live, etc).
- **PMG-012 — Saved views backend** (mover localStorage → `mcp_views` table + UI Save/My/Shared + endpoints CRUD + sharing)

**Aceitação Fase 4**: Wagner vê outros members conectados em tempo real; ≥3 saved views compartilhadas pelo time.

### Fase 5 — Opcional (P2/P3, não-comprometida)

Triage / Activity refino / Burndown multi-cycle / Dependencies graph / Custom fields / Workload / Time tracking interno / Templates / Automation rules / Mobile / Dark mode / Roadmap drag / Public share. Só entra se Fase 1-4 mostrarem ROI (Wagner adotando).

### Convenções de execução

- **1 PR por fase** subdividido em commits ≤300 linhas (skill `commit-discipline`)
- **Branch convention**: `feat/projectmgmt-fase-{N}-{slug}` (ex: `feat/projectmgmt-fase-1-drag-drop-cmdk`)
- **Routes**: extender `Modules/ProjectMgmt/Http/routes.php` (já tem `/project-mgmt` prefix)
- **Controllers**: editar existentes em `Modules/ProjectMgmt/Http/Controllers/`
- **Pages**: editar `resources/js/Pages/ProjectMgmt/{Board,Backlog,MyWork,...}/Index.tsx` existentes; criar `Detail/` e `Search/` quando necessário
- **Componentes**: extender `resources/js/Components/board/` quando reutilizável; inline quando page-specific
- **Skill obrigatória**: `mwart-quality` (Tier B auto-trigger) — 9 pré-flight checks por Edit `.tsx`
- **Tests**: criar `Modules/ProjectMgmt/Tests/Feature/{Permissions,Board,Search,DetailSheet,Cycle}Test.php` + adicionar diretório a `phpunit.xml`
- **Schema**: Fase 1-4 NÃO mexe em schema (15 tabelas existentes cobrem tudo). Fase 5 (opcional) traz schemas novos
- **Centrifugo**: usar canais `project-mgmt:*` (não inventar; consistente com convenção módulo)
- **Não confundir** com `Modules/Project` legacy (será deletado em [PR #202 Fase 3.8](https://github.com/wagnerra23/oimpresso.com/pull/202))

## Justificativa

**Por que capterra-driven em 4 fases:**

- ROI gradual com gate humano após cada fase reduz risco ([ADR 0089](0089-capterra-driven-module-evolution.md) pattern)
- Fase 1 (drag-drop + Cmd+K + tests) é o **maior salto perceptual** com **menor esforço relativo** — ROI alto
- Cada fase é 1 cycle (~1-2 semanas) — encaixa em [ADR 0070](0070-jira-style-task-management-current-md-removed.md) workflow
- Permite coexistir com NfeBrasil P0 e Constituição V2 health-check no mesmo quarter (CYCLE-02)

**Por que NÃO greenfield (rewrite from scratch):**

- 1935 LoC já em prod e funcionando — descartar é desperdício
- 12 US-TR-NNN entregues + DB schema estável + permission pattern OK
- Greenfield criaria gap de feature parity onde não há gap real

**Por que Linear como benchmark (não Jira):**

- Linear estabeleceu o **bar de fluidez** moderno (Cmd+K, atalhos, presence)
- Wagner mencionou Linear explicitamente
- Jira é benchmark de **completude funcional** (cobre escopo P0-P1)
- Plane.so como referência arquitetural (open-source, similar self-host story)

**Por que NÃO adicionar lib drag-drop (`@hello-pangea/dnd`, `react-beautiful-dnd`):**

- Pacote pesado (~80KB gzipped); package.json hoje sem essa dep
- HTML5 Drag-Drop API nativo é **suficiente pra drag entre colunas** (use case P0)
- **Reorder dentro de coluna** (use case que justifica lib) é P2 — não no escopo Fase 1-4
- Skill `mwart-quality` audita dependências adicionadas — adicionar exigiria justificativa ADR própria

**Por que tests Pest na Fase 1 (não no fim):**

- 0% cobertura é débito — bloqueia confiança em mudanças subsequentes
- Pattern Repair (`RepairIndexMwartTest`) é proven; replicar economiza tempo
- Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) **exige** testes cobrindo cross-tenant

**Por que Centrifugo presence (Fase 4) e não cedo:**

- Visibilidade alta (avatar stack é "wow") mas dep mais complexa (canal naming + teardown + reconnect)
- Foundation já existe ([ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md)); só falta integrar
- Postergar reduz risco em Fase 1 que precisa ser sólido

**Quando reabrir esta ADR:**

- Time não adota Cmd+K em 14d após Fase 1 (PMG-002 fail) — repensar UX shortcuts
- Wagner decide priorizar outro módulo no quarter — pausar Fases 2-5 sem perder Fase 1 já mergeada
- Mercado/concorrente lança feature game-changer não inventariada (revisar CAPTERRA-FICHA)
- Fase 3.9 rename `ProjectMgmt → Project` acontecer — revisar paths/imports/URLs

## Consequências

**Positivas:**

- ProjectMgmt vira **referência interna de UX moderna** (Linear-tier) — set pattern reaproveitável em outros módulos (Repair, Whatsapp cockpit, etc)
- Time interno usa o sistema **diariamente** com fluidez — captura tasks de chat/email sem fricção; reduz fluxo paralelo em planilha/Notion
- Closes parcialmente **PROJECT-1** (US-TR-201..206 já entregues; PMG-* fecha gap restante de polish)
- ≥4 tests Pest novos cobrem multi-tenant + permission — fim do gap "0% cobertura ProjectMgmt"
- CAPTERRA-FICHA / Charter / INVENTARIO atualizados — qualquer dev futuro entra sem ramp-up cego
- Centrifugo presence (Fase 4) abre caminho pra notifications real-time, comments live, status sync entre tabs (futuro)

**Negativas / Trade-offs:**

- Investimento total ~26-32h dev (4 fases × 6-10h cada) — alto; precisa caber em quarter sem comprometer NfeBrasil P0
- Coexistência com Modules/Project legacy até [PR #202 mergear](https://github.com/wagnerra23/oimpresso.com/pull/202) (Fase 3.8) — gap pequeno, baixo risco
- Tests Pest exigem env de DB pra rodar full UltimatePOS schema — pattern Repair `markTestSkipped` mitiga em CI
- @mentions e watchers (Fase 2) precisam Notification template + delivery testado em prod — depende de ambiente mail OK

**Riscos mitigados:**

- Big-bang rewrite descartado → fases pequenas com gate
- Charter previne feature creep ([ADR 0094 §3](0094-constituicao-v2-7-camadas-8-principios.md))
- CAPTERRA-FICHA é fonte de verdade pra "isso é P0 ou P3?" sem debate ad-hoc
- Skills `mwart-quality` + `commit-discipline` Tier A enforçam padrão de cada PR
- Multi-tenant Tier 0 testado per-feature ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- Lib drag-drop adicional adiada — bundle size sob controle

## Plano de execução (próximas sessões)

**Não criar tasks PMG-* no MCP nesta sessão sem OK do Wagner** ([publication-policy](../../.claude/skills/publication-policy/SKILL.md)). Batch proposto vai pro [INVENTARIO § Tasks propostas](../requisitos/ProjectMgmt/INVENTARIO.md); Wagner aprova em lote.

Ordem sugerida (mais valor visível por hora):

1. **PMG-001** P0 — Drag-drop completo (Fase 1)
2. **PMG-002** P0 — Cmd+K Search Global (Fase 1)
3. **PMG-003** P0 — Tests Pest base (Fase 1)
4. **PMG-004** P1 — Detail Sheet (Fase 2; foundation pra 5/6/7)
5. **PMG-005..007** P1 — @mentions / Watchers / Subtasks (Fase 2)
6. **PMG-008..010** P1 — Atalhos / Cycle close / Sprint planning (Fase 3)
7. **PMG-011..012** P1 — Presence / Saved views backend (Fase 4)
8. **PMG-013..025** — entries P2/P3 conforme adoção real

## Validação

- [ ] Fase 1 (PMG-001..003) mergeada e em prod por 7 dias sem regressão
- [ ] Wagner usa drag-drop ≥10×/dia (telemetria `board.task.moved`)
- [ ] Cmd+K aberto ≥5×/dia per user ativo (telemetria `palette.opened`)
- [ ] ≥4 tests Pest verdes em CI cobrindo multi-tenant + permission + R-PMG-001..003
- [ ] Pattern reaproveitado em Repair (Cockpit) ou Whatsapp na sessão seguinte
- [ ] Após Fase 4, score INVENTARIO sobe de 6/24 ✅ → ≥17/24 ✅ + 🟡

## Referências

- [ADR 0058 — Centrifugo+FrankenPHP](0058-reverb-substituido-por-centrifugo-frankenphp.md)
- [ADR 0070 — Jira-style task management](0070-jira-style-task-management-current-md-removed.md)
- [ADR 0079 — Constituição 7 camadas](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0080 — Trust tiers](0080-trust-tiers-operacional-audit-findings.md)
- [ADR 0086 — Fase 5 governance](0086-fase-5-mvp-governance-actiongate-warn.md)
- [ADR 0089 — Capterra-driven module evolution](0089-capterra-driven-module-evolution.md)
- [ADR 0093 — Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2 (Charter > Spec, SoC brutal)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0099 — Modules/Project legacy discovery pre-deletion](0099-project-legacy-discovery-pre-deletion.md)
- [memory/requisitos/ProjectMgmt/CAPTERRA-FICHA.md](../requisitos/ProjectMgmt/CAPTERRA-FICHA.md)
- [memory/requisitos/ProjectMgmt/CHARTER-board.md](../requisitos/ProjectMgmt/CHARTER-board.md)
- [memory/requisitos/ProjectMgmt/INVENTARIO.md](../requisitos/ProjectMgmt/INVENTARIO.md)
- [memory/requisitos/TaskRegistry/SPEC.md](../requisitos/TaskRegistry/SPEC.md) — SPEC funcional histórico (US-TR-NNN)
- [Modules/ProjectMgmt/SCOPE.md](../../Modules/ProjectMgmt/SCOPE.md)
- [PR #197](https://github.com/wagnerra23/oimpresso.com/pull/197) — Discovery legacy `Modules/Project` (pivot)
- [PR #202](https://github.com/wagnerra23/oimpresso.com/pull/202) — Fase 3.8 delete legacy
- Skill `mwart-quality` — pré-flight checks pra `.tsx`
- Skill `cockpit-runbook` — gerar RUNBOOK pós-Fase 1
- Linear method: https://linear.app/method
- Atlassian Design System: https://atlassian.design/
- Plane.so: https://plane.so
