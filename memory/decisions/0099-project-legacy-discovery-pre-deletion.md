---
slug: 0099-project-legacy-discovery-pre-deletion
number: 99
title: "Modules/Project (legacy UltimatePOS) — Discovery pré-deletion (Fase 3.8)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: governance
quarter: 2026-Q2
tags: [mwart, legacy, project, discovery, pre-deletion, queue-for-delete]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0011-alinhamento-padrao-jana", "0070-jira-style-task-management-current-md-removed", "0079-constituicao-oimpresso-7-camadas-governanca", "0080-trust-tiers-operacional-audit-findings", "0086-fase-5-mvp-governance-actiongate-warn", "0087-drift-resolution-sem-mover-url", "0088-module-rename-php-only", "0089-capterra-driven-module-evolution", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
review_triggers: ["Quando Fase 3.8 começar — usar este discovery pra decidir o que extrair antes de git rm", "Se ProjectMgmt absorver alguma capacidade do legacy (ex: TimeLogs, Invoice from work)"]
---

# ADR 0099 — Modules/Project (legacy UltimatePOS) — Discovery pré-deletion (Fase 3.8)

## ⚠️ Pivot 2026-05-07 (mesmo dia)

Esta ADR foi originalmente escrita como "Project MWART Migration: Blade legacy → Inertia/React em 4 fases capterra-driven" mirando uma migração completa de `Modules/Project/` (Blade UltimatePOS, gestão de projetos de cliente) para Inertia/React.

**Erro de direção descoberto na mesma sessão:** Wagner pediu redesign de "ProjectMgmt", referindo-se a [`Modules/ProjectMgmt/`](../../Modules/ProjectMgmt/) (módulo Jira-style do TIME interno, em prod desde 2026-05-04 PRs #91/#92). Essa Fase 0 mirou no módulo errado.

**Decisão pós-pivot:** manter os 5 artefatos (CAPTERRA-FICHA, CHARTER-board, SPEC.md preenchido, session log, esta ADR) **mas re-enquadrados** como "discovery do legacy `Modules/Project` queue-for-delete". Razão:

- `Modules/Project` será **deletado em Fase 3.8** ([SCOPE.md ProjectMgmt](../../Modules/ProjectMgmt/SCOPE.md) explicita: "Fase 3.8 — DELETE Project legado UltimatePOS")
- Este discovery (24 capacidades inventariadas + 15 US documentadas + Charter da tela board) **vira insumo pra Fase 3.8**: ajuda decidir o que extrair antes do `git rm -rf Modules/Project/` (Invoice from TimeLogs, ClientProjects, timesheet) e onde recolocar (Modules/Financeiro? virar feature do ProjectMgmt?).
- **NÃO se executa nenhuma das "4 Fases" originalmente propostas.** Migração Blade→MWART do legacy foi cancelada.
- **Substituído por:** ADR 0100 (a criar) — "ProjectMgmt UI Redesign — Capterra-driven Jira-like" mirando o módulo certo.

A próxima sessão cria o discovery NOVO em `memory/requisitos/ProjectMgmt/{CAPTERRA-FICHA,CHARTER-board,SPEC,...}.md` espelhando o pattern, mas sobre as 6 telas que **já existem** em `resources/js/Pages/ProjectMgmt/`.

---

## Contexto (original — preserva pra Fase 3.8 deletion)

O módulo `Modules/Project` é **100% Blade legacy** (migrations 2019-2020, último alinhamento UI em 2020). Wagner solicitou em 2026-05-07: "refazer a UI do Project baseado no Jira" (ver session log da data).

Estado atual:

- **5 controllers Blade**: Project, Task, TaskComment, ProjectTimeLog, Invoice, Report, Activity (~25 ações REST + 4 actions custom)
- **10 entidades** com relacionamentos não-triviais (members, comments, time_logs, invoice_lines, categorizable morphMany)
- **SPEC.md praticamente vazio** (só multi-tenant + 4 permissões Spatie, zero US de UI funcional)
- **Tests/Feature/ vazio** — 0% cobertura
- **Adoção em prod desconhecida** — ROTA LIVRE provavelmente não usa
- **Documentação rica em scaffolding** (ARCHITECTURE/SPEC/CHANGELOG/RUNBOOK existem em `memory/requisitos/Project/`) mas com placeholders TODO

Restrições:

- [ADR 0011](0011-alinhamento-padrao-jana.md) — antes de criar tela nova, imitar `Modules/Jana/Repair/Project` referência. Repair foi recém-migrado MWART; usar como template canônico.
- [ADR 0089](0089-capterra-driven-module-evolution.md) — toda evolução de módulo passa por `CAPTERRA-FICHA + INVENTARIO + SPEC + adr/`.
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL: `business_id` global scope, jobs com `$businessId` no constructor, zero `withoutGlobalScopes`.
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 Charter > Spec, §5 SoC brutal — telas têm Charter contratual, sem feature creep.
- Princípios CLAUDE.md: 1 PR ≤300 linhas, 1 intent por PR, conventional commits, refs sprint/cycle.

Alternativas avaliadas:

| Alternativa | Por que não |
|---|---|
| **Big-bang rewrite** (todas telas em 1 sprint) | Projeto de 40-60h sem feedback loop; quebra UltimatePOS hooks; inviável em modelo cycle 2-semanal |
| **Manter Blade + adicionar Kanban como tela nova só** | Diverge dos princípios MWART; cria fork de UI que vira débito |
| **Reescrever em Vue** (UltimatePOS é Vue/jQuery) | Stack canônica é Inertia/React 19 ([what-oimpresso.md](../what-oimpresso.md)); divergir custaria mais tarde |
| **Comprar Jira Service** (não construir) | Multi-tenant por business_id seria complexo via SSO; preço mata margem; perde diferencial vertical (invoice from timelogs) |

## Decisão

**Migrar `Modules/Project` para MWART (Inertia/React) progressivamente em 4 fases, capterra-driven, com gates de validação humana entre cada fase.**

### Fase 0 — Discovery (esta sessão, 2026-05-07)

Sai com 5 artefatos no git:

- ✅ `memory/requisitos/Project/CAPTERRA-FICHA.md` — 24 capacidades inventariadas P0-P3 vs Jira/Linear/Asana/ClickUp/Trello/Productive
- ✅ `memory/requisitos/Project/CHARTER-board.md` — contrato vivo de `/project/{id}/board`
- ✅ Esta ADR 0099
- ✅ `memory/requisitos/Project/SPEC.md` preenchido com US-PROJ-* priorizadas
- ✅ Session log em `memory/sessions/2026-05-07-project-mwart-discovery.md`

**Fase 0 NÃO escreve código de aplicação.** Sai com plano travado pra Wagner aprovar criação das tasks MCP.

### Fase 1 — MVP Kanban (1 cycle, 6-8h dev)

Entrega tela `/project/{id}/board` cobrindo capacidades P0 obrigatórias:

- #1 Lista projetos com filtros (refazer `Project/Index.tsx` substituindo `index.blade.php`)
- #2 Kanban board drag-drop (tela nova `Board.tsx`)
- #5 Multi-tenant + Permissions Spatie (testes Pest cobrindo R-PROJ-001..004)
- #7 Multi-assignee + Lead (avatar stack na UI)

Reaproveita 100% do schema atual (`pjt_*` tables) e Routes/web.php (PATCH `/project-task/{id}/post-status` já existe).

**Aceitação Fase 1**: ROTA LIVRE testa em prod por ≥7 dias, drag-drop funcional sem regressão, ≥5 testes Pest verdes.

### Fase 2 — Issue Detail + Comments + TimeLogs (1 cycle, 8-10h dev)

- #3 Issue detail Jira-style (DetailSheet à direita)
- #4 Time tracking (manual + start/stop UI)
- Notifications #6 validar template + delivery
- Backlog separado (#8) — drawer no Board

**Aceitação Fase 2**: Larissa registra ≥10 TimeLogs reais; ≥3 comentários trocados em prod.

### Fase 3 — Filtros, Bulk, @mentions (1 cycle, 6-8h dev)

- #9 Quick add inline
- #10 Bulk edit
- #11 @mentions
- #12 Watchers
- #14 Filters salvos
- #15 Search global Cmd+K
- #16 Activity log visível

**Aceitação Fase 3**: Cmd+K busca funcional cross-projeto; filters URL-state-driven; bulk edit com permission check testado.

### Fase 4 — Diferencial vertical: Invoice from TimeLogs (1 cycle, 8h dev)

Capacidade #24 — gerar Invoice parcial a partir de TimeLogs selecionados, integrado ao Modules/Financeiro.

**Aceitação Fase 4**: 1 invoice gerada de timelogs reais em prod; Receivable criado no Financeiro; teste E2E verde.

### Fase 5 — Opcional (P2, não-comprometida)

Templates / Time estimates / Subtasks / Roadmap-Gantt / Custom fields. Só entra se Fase 1-4 mostrarem ROI (Larissa adotando).

### Convenções de execução

- **1 PR por fase** subdividido em commits ≤300 linhas (skill `commit-discipline`).
- **Branch convention**: `feat/project-mwart-fase-{N}-{slug}` (ex: `feat/project-mwart-fase-1-kanban`).
- **Routes**: novas Inertia em `Modules/Project/Routes/web.php` com prefixo `/project/{id}/board` (sem prefixo `/v2`); rotas Blade legacy continuam vivas até deprecation final pós-Fase 4.
- **Controllers**: criar em `Modules/Project/Http/Controllers/Inertia/{Board,TaskDetail,...}Controller.php` separados dos Blade legacy. Não tocar nos Blade controllers até deprecation.
- **Pages**: `resources/js/Pages/Project/{Board,Detail,Backlog,Index}.tsx`. Layout `AppShellV2` via `Page.layout` pattern.
- **Skill obrigatória**: `mwart-quality` (Tier B auto-trigger) ativará a cada Edit `.tsx` em Modules/Project — 9 pré-flight checks.
- **Tests**: criar `Modules/Project/Tests/Feature/{Permissions,Board,TaskDetail,TimeTracking}Test.php`. Adicionar à `phpunit.xml`.
- **Schema**: Fase 1-4 NÃO mexe em schema. Fase 5 (opcional) traz migrations novas.

## Justificativa

**Por que capterra-driven em 4 fases:**

- ROI gradual com gate humano após cada fase reduz risco (Wagner aprovou padrão em [ADR 0089](0089-capterra-driven-module-evolution.md))
- Larissa testando MVP em ≤2 semanas dá feedback precoce; podemos pivotar antes de gastar 30h em features que ela não usaria
- Cada fase é 1 cycle (`mcp_cycles`) — encaixa no [ADR 0070](0070-jira-style-task-management-current-md-removed.md) workflow

**Por que não migrar Repair-style direto sem fase 0:**

- SPEC.md vazio = qualquer design seria chute
- Sem CAPTERRA-FICHA, decisões de escopo (sprints? roadmap? customer view?) sairiam por intuição, não evidência
- Charter como contrato impede feature creep em pull requests subsequentes ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3)

**Por que preservar Blade legacy durante migração:**

- Risco zero de quebrar adoção atual (mesmo que baixa)
- Permite A/B test passive (analytics: hits Blade vs MWART)
- Deprecation final só após métricas de adoção MWART >70% (ver Charter §9)

**Por que não tocar schema nas Fases 1-4:**

- Schemas existentes (`pjt_*`) são suficientes pra todas P0/P1 (validado nas capacidades #1-#16 do CAPTERRA-FICHA)
- Migration nova = risco de prod migration falhar; isolar pra Fase 5 quando necessária
- `parent_task_id` (subtask), `is_template`, `estimated_hours`, dependencies — tudo P1/P2 que entra incrementalmente com migration própria

**Quando reabrir esta ADR:**

- Larissa não adota o /board após 30 dias da Fase 1 — repensar se MWART migration vale ou se módulo deve ser deprecated
- Mercado/concorrente lança feature game-changer não inventariada (revisar CAPTERRA-FICHA)
- Wagner decide priorizar outro módulo no quarter — pausar Fases 2-5 sem perder Fase 1 já mergeada

## Consequências

**Positivas:**

- Tela `/project/.../board` competitiva visualmente (drag-drop + presence + atalhos) com Jira/Linear-tier UX
- Diferencial vertical (Invoice from TimeLogs Fase 4) que nenhum dev-tool tem nativamente
- 4 testes Pest novos cobrindo R-PROJ-001..004 (fim do gap "0% cobertura")
- SPEC.md, Charter, INVENTARIO atualizados — qualquer dev futuro entra sem ramp-up cego
- Reduz superfície de Blade legacy no projeto (uma das missões CYCLE-02 conforme brief diário)
- Sets pattern reutilizável: outros módulos legacy podem migrar via "fase 0 capterra-driven" depois

**Negativas / Trade-offs:**

- Investimento total ~30-40h dev (4 fases × 6-10h cada) — alto; precisa caber em quarter sem comprometer NfeBrasil P0 e Constituição V2 health-check
- Co-existência Blade/MWART por 4-8 semanas — risco de drift se Blade legacy receber bugfix sem espelhar no MWART
- Larissa pode rejeitar a UX Jira-like (gráficas operam diferente de devs) — fase 1 valida; se rejeitar, repensar
- @mentions e watchers na Fase 3 exigem schema novo (`pjt_project_task_watchers`, `mention_user_id`) — primeira migration desta ADR

**Riscos mitigados:**

- Big-bang rewrite descartado → fases pequenas com gate
- Charter previne feature creep ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3)
- CAPTERRA-FICHA é fonte de verdade pra "isso é P0 ou P3?" sem debate ad-hoc
- Skills `mwart-quality` + `commit-discipline` Tier A enforçam padrão de cada PR
- Multi-tenant Tier 0 testado per-feature ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

## Plano de execução (próximas sessões)

**Não criar tasks MCP nesta sessão sem OK do Wagner** ([publication-policy](../../.claude/skills/publication-policy/SKILL.md)). Batch proposto vai pro fim do session log; Wagner aprova `tasks-create` lote.

Ordem sugerida:

1. **PROJECT-2** P0 — Fase 1 MVP Kanban (1 cycle)
2. **PROJECT-3** P0 — Fase 2 Detail + TimeLogs (1 cycle)
3. **PROJECT-4** P1 — Fase 3 Filtros/Bulk/@mentions/Watchers (1 cycle)
4. **PROJECT-5** P1 — Fase 4 Invoice from TimeLogs (1 cycle)
5. **PROJECT-6..N** — entries P2/P3 conforme adoção real

## Referências

- [ADR 0011](0011-alinhamento-padrao-jana.md) — alinhamento padrãa Jana/Repair como template
- [ADR 0089](0089-capterra-driven-module-evolution.md) — capterra-driven module evolution (3 artefatos)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 irrevogável
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Charter > Spec, SoC brutal)
- [memory/requisitos/Project/CAPTERRA-FICHA.md](../requisitos/Project/CAPTERRA-FICHA.md)
- [memory/requisitos/Project/CHARTER-board.md](../requisitos/Project/CHARTER-board.md)
- [memory/requisitos/Project/SPEC.md](../requisitos/Project/SPEC.md)
- Session log 2026-05-07 fase 0 discovery — `memory/sessions/2026-05-07-project-mwart-discovery.md`
- Skill `mwart-quality` — pré-flight checks pra `.tsx` em `Modules/<X>/`
- `Modules/Repair/` — referência de migração MWART recente
