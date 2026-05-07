# Sessão 2026-05-07 — Project MWART Discovery (Fase 0) ⚠️ PIVOTADA mesmo dia

> ⚠️ **CORREÇÃO 2026-05-07 (mesmo dia).** Esta sessão produziu artefatos da Fase 0 mirando `Modules/Project` Blade legacy UltimatePOS. Wagner pediu redesign de "projectMgmt" — eu interpretei como o legacy, mas o que ele queria era `Modules/ProjectMgmt` (módulo Jira-style do TIME INTERNO, em prod desde PRs #91/#92, com 6 telas Inertia/React totalizando 1935 LoC).
>
> **Status final dos 5 artefatos:** todos preservados com disclaimer no topo, viraram insumo da Fase 3.8 (delete legacy). Nenhuma US-PROJ-NNN será criada como task no MCP. ADR 0099 mudou status `aceito` → `pivotado` e título pra "Modules/Project (legacy UltimatePOS) — Discovery pré-deletion (Fase 3.8)".
>
> **Próximo passo:** Discovery NOVO mirando `Modules/ProjectMgmt` em sessão paralela. Agent Explore lançado em background pra inventariar 6 telas + 8 controllers + tabelas mcp_*. CAPTERRA-FICHA + CHARTER + SPEC novos vão em `memory/requisitos/ProjectMgmt/` quando o relatório voltar. ADR 0100 "ProjectMgmt UI Redesign" será escrita aí.
>
> **Lição meta-aprendizado:** sempre `Glob` por nome amplo (`**/Project*`) antes de assumir qual módulo. Sempre `git log --grep` por keyword domain antes de criar Charter/ADR. Faltou rigor de discovery inicial — perdi 3h na direção errada.

---

## Pedido do Wagner

> "pode crefazer a UI do Projeto (UI do Jira ou baseado no jira)"
> Confirmou opção B (módulo `Modules/Project`, não `/copiloto/admin/board`) com "projectMgmt".
> Aprovou Fase 0 completa nesta sessão com "faça".

## O que foi entregue (5 artefatos no git, ZERO código aplicação)

1. **`memory/requisitos/Project/CAPTERRA-FICHA.md`** — 24 capacidades inventariadas P0-P3 vs Jira / Linear / Asana / ClickUp / Trello / Productive.io. Critérios ✅🟡❌ + locais a inspecionar pra futura execução da skill `comparativo-do-modulo`.
2. **`memory/requisitos/Project/CHARTER-board.md`** — contrato vivo de `/project/{id}/board`. Anatomia (R1 TopBar / R2 FilterBar / R3 Kanban / R4 DetailSheet), 2 personas, 6 fluxos críticos, 8 estados de UI, regras canônicas (cores priority/status, atalhos teclado, anti-padrões). Charter > Spec ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3) — primeiro Charter formal do projeto.
3. **`memory/decisions/0099-project-mwart-migration.md`** — ADR Nygard. Decisão: 4 fases capterra-driven, gate humano entre fases, schema preservado nas Fases 1-4, deprecation Blade só após adoção MWART >70%.
4. **`memory/requisitos/Project/SPEC.md`** preenchido — 15 US priorizadas (US-PROJ-001..015) + 7 Regras Gherkin testáveis (R-PROJ-001..007). Substituiu placeholder TODO de 2026-04-22.
5. **Este session log** + batch de tasks proposto pendente aprovação Wagner.

## Estado real descoberto sobre `Modules/Project`

- 100% Blade legacy, migrations 2019-2020, 5 controllers (Project / Task / TaskComment / TimeLog / Invoice / Report / Activity)
- 10 entidades com relacionamentos não-triviais (members + comments + time_logs + invoice_lines + categorizable morphMany)
- `Tests/Feature/` **vazio** — 0% cobertura (proibição CLAUDE.md: senão CI nunca roda)
- `SPEC.md` original quase vazio (só 4 regras Spatie multi-tenant); CHANGELOG só docs scaffold de abr/22
- 3 Notification classes existem (`NewProjectAssigned`, `NewTaskAssigned`, `NewCommentOnTask`) — preservar
- DataController + InstallController OK (UltimatePOS hooks plugados); preservar
- Schema `pjt_*` já cobre Fase 1-4 sem migration nova; Fase 5 (opcional) traz schema novo
- Adoção em ROTA LIVRE (biz=4) **provavelmente zero** — uma das justificativas pra fase 0 antes de gastar 30-40h dev

## Fases definidas na ADR 0099

| Fase | Entrega | Esforço | Aceitação |
|---|---|---|---|
| **0** | 5 artefatos discovery (esta sessão) | 3h | ✅ DONE 2026-05-07 |
| **1** | MVP Kanban: `/project` Index + `/project/{id}/board` + tests R-PROJ-001..004 | 6-8h | ROTA LIVRE testa em prod por 7d, drag-drop funcional |
| **2** | DetailSheet + TimeLogs + Backlog + Notifications validation | 8-10h | Larissa registra ≥10 TimeLogs reais; ≥3 comentários |
| **3** | Quick add + Bulk + @mentions + Watchers + Filters salvos + Cmd+K + ActivityLog UI | 6-8h | Cmd+K funcional cross-project; bulk c/ permission test |
| **4** | Invoice from TimeLogs + integração Modules/Financeiro (Receivable) | 8h | 1 invoice gerada de timelogs reais em prod |
| **5** | Opcional (P2): Subtasks / Templates / Time estimates / Roadmap-Gantt / Custom fields | — | Só se Fase 1-4 mostrarem ROI |

## Batch de tasks MCP propostas (PENDENTE APROVAÇÃO)

> ⚠️ **NÃO foram criadas no MCP ainda** ([publication-policy](.claude/skills/publication-policy/SKILL.md)).
> Aprovar via `/comparativo aprovar 1,2,3,4,5` ou texto livre. Wagner: confirmar quais entram.

### P0 — Fase 1 e 2 (próximos 1-2 cycles)

1. **[P0] PROJECT-2** — Fase 1 MVP Kanban (`Index.tsx` + `Board.tsx` + drag-drop + 5 testes Pest R-PROJ-001..005). Cobre US-PROJ-001, 002, 005. _Estimativa: 6-8h. Bloqueador: nenhum._
2. **[P0] PROJECT-3** — Fase 2 DetailSheet (`Detail.tsx` modal slide-in com tabs Description/Comments/TimeLogs/Activity). Cobre US-PROJ-003. _Estimativa: 4-6h. Bloqueador: PROJECT-2._
3. **[P0] PROJECT-4** — Fase 2 Time Tracking (start/stop UI + manual entry + report agregado). Cobre US-PROJ-004 + R-PROJ-006. _Estimativa: 4h. Bloqueador: PROJECT-3._
4. **[P0] PROJECT-5** — Fase 2 Backlog separado + Notifications validation prod. Cobre US-PROJ-006 + 007. _Estimativa: 3-4h. Bloqueador: PROJECT-3._

### P1 — Fase 3 (cycle subsequente)

5. **[P1] PROJECT-6** — Quick add inline + Bulk edit (multi-select). Cobre US-PROJ-008 + 009. _Estimativa: 4h._
6. **[P1] PROJECT-7** — @mentions em comments + Watchers (migration `pjt_project_task_watchers` + Notification listener). Cobre US-PROJ-010 + 011. _Estimativa: 4h._
7. **[P1] PROJECT-8** — Filters salvos / quick-views + Cmd+K Search global. Cobre US-PROJ-012 + 013. _Estimativa: 4h._
8. **[P1] PROJECT-9** — Activity log UI no DetailSheet. Cobre US-PROJ-014. _Estimativa: 2h._

### P1 — Fase 4 (diferencial vertical)

9. **[P1] PROJECT-10** — Invoice from TimeLogs + integração Receivable Financeiro (regra R-PROJ-007 timelog faturado imutável). Cobre US-PROJ-015. _Estimativa: 8h._

### P2 — Backlog (não comprometido)

10. **[P2] PROJECT-11..16** — Subtasks / Time estimates / Templates / Roadmap-Gantt / Custom fields / Dependencies. _Só se Fase 1-4 mostrarem adoção real._

### P3 — Diferenciação opcional

11. **[P3] PROJECT-17..19** — Customer view / Burndown charts / WIP limits.

## Decisões importantes pra próximas sessões

- Schema `pjt_*` **NÃO MUDA** nas Fases 1-4. Migration nova só Fase 5.
- Rotas Inertia em `Modules/Project/Routes/web.php` mantém prefixo atual `/project` (sem `/v2`); coexiste com Blade até deprecation pós-Fase 4.
- Controllers MWART em subfolder `Modules/Project/Http/Controllers/Inertia/{Board,TaskDetail,...}Controller.php` — **separados dos Blade legacy**, não tocar nos Blade até deprecation.
- Pages em `resources/js/Pages/Project/{Index,Board,Detail,Backlog,Roadmap}.tsx` com `Page.layout = AppShellV2` (preferência preservada do CLAUDE).
- Skill `mwart-quality` (Tier B) auto-trigger ao Edit `.tsx` em `Modules/Project/` — 9 pré-flight checks aplicados.
- Skill `commit-discipline` (Tier A) — 1 PR ≤300 linhas, conventional commits, refs CYCLE-NN.
- Pest Tests: criar `Modules/Project/Tests/Feature/{Permissions,Board,TaskDetail,TimeTracking,InvoiceFromTimeLogs}Test.php` + adicionar ao `phpunit.xml` (proibição CLAUDE.md).
- ADR 0099 status `proposto` — passa pra `aceito` após Wagner aprovar batch.

## Anti-escopo declarado (para sobreviver feature creep)

Fora do MVP completo (Fase 1-4):
- ❌ Sprints/Cycles internos (gráfica não é dev iterativo)
- ❌ Roadmap/Gantt timeline (P2, tela separada)
- ❌ Mobile responsive otimizado (P2 — desktop-first 1280px Larissa)
- ❌ Customer view pública (P3)
- ❌ Burndown / Velocity charts (P3)
- ❌ WIP limits por coluna (P3)

## Artefatos criados (lista para git add)

```
memory/requisitos/Project/CAPTERRA-FICHA.md         (novo, ~290 linhas)
memory/requisitos/Project/CHARTER-board.md          (novo, ~280 linhas)
memory/requisitos/Project/SPEC.md                   (atualizado de 69 → ~330 linhas)
memory/decisions/0099-project-mwart-migration.md    (novo, ~165 linhas)
memory/sessions/2026-05-07-project-mwart-discovery-fase0.md  (este)
```

## Próximo passo concreto

1. **Wagner**: revisar artefatos (priorizar Charter §3 anatomia + §8 anti-escopo + ADR 0099 §Decisão)
2. **Wagner**: aprovar quais tasks do batch criam no MCP (PROJECT-2..10)
3. **Sessão seguinte**: começar PROJECT-2 (Fase 1 MVP Kanban) — branch `feat/project-mwart-fase-1-kanban`, criar `BoardController` + `Board.tsx` seguindo Charter, testes R-PROJ-001..005

## Métricas da sessão

- 5 artefatos criados, ~1100 linhas markdown total
- 0 linhas de código aplicação tocadas
- Brief diário consultado 1× (cache hit)
- Skills triggered: brief-first (Tier A SessionStart hook), implícito mcp-first
- Tempo estimado: 3h (vs 30-40h se tivesse pulado discovery e codado direto)
