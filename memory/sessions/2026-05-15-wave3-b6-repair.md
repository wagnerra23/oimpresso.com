# Wave 3 B6 — Repair MWART massiva (Agent W3-E)

> **Data:** 2026-05-15
> **Worktree:** `D:\oimpresso.com\.claude\worktrees\happy-golick-daeae6`
> **Agent:** W3-E (1 de 5 paralelos)
> **Escopo:** 7 telas Repair Blade → Inertia (MWART) — JobSheet (5) + Repair (2)
> **Refs:** [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · [ADR 0149](../decisions/0149-mwart-screen-pattern-reuse-cowork.md) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)

## Pré-flight realizado

| Pré-req | Status |
|---|---|
| `memory/requisitos/Repair/SPEC.md` | lido (stub) |
| `SPEC-FSM-WIREUP.md` | lido — DRAFT 2026-05-12 (FSM Repair em coexistência opt-in) |
| `RepairFsmActionController.php` | já existia (US-REP-FSM-004) — endpoints `/api/repair/job-sheets/{id}/fsm-*` |
| `FsmActionPanel.tsx` Sells | lido — pattern adaptado em wrapper `JobSheetFsmPanel` interno |
| `app/Domain/Fsm/ExecuteStageActionService` + `FsmAuthorizationFlag` | conhecidos via SPEC FSM |
| `JobSheet::class` trait `GuardsFsmTransitions` | confirmado (linha 14) |
| ADR 0104/0114/0149/0143/0093 | lidos |
| Cowork blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` | lido (150 linhas) |
| Wave anterior `Repair/Index.tsx` + `Repair/JobSheet/Index.tsx` | confirmados existentes — preservados |
| `resources/js/app.tsx` resolve glob `Pages/**/*.tsx` | confirmado — `.tsx` devem viver em `resources/js/Pages/`, não em `Modules/Repair/Resources/js/Pages/` |

## Decisão estrutural

**.tsx criados em `resources/js/Pages/Repair/JobSheet/*` e `resources/js/Pages/Repair/Show.tsx`** (onde Inertia carrega via `import.meta.glob`). Briefing original sugeria `Modules/Repair/Resources/js/Pages/` mas Inertia resolve global resources path. Pattern alinhado com Wave anterior (PR #100 `Repair/Index.tsx`).

## Output (24 arquivos)

### `.tsx` (5 novos)
- `resources/js/Pages/Repair/JobSheet/Show.tsx` (20.3KB) — FSM Panel adaptado wrapper + sections detalhe/parts/anexos/timeline
- `resources/js/Pages/Repair/JobSheet/Edit.tsx` (14.5KB) — form tabs Cliente/Aparelho/Defeitos/Checklist
- `resources/js/Pages/Repair/JobSheet/Create.tsx` (12.0KB) — 3 sections + submit_type save/save_and_add_parts
- `resources/js/Pages/Repair/JobSheet/AddParts.tsx` (7.6KB) — tabela editável peças
- `resources/js/Pages/Repair/Show.tsx` (10.2KB) — venda-de-reparo + sell_lines + payments + opt-in FSM Sells

### Charters (5 novos + 1 atualizado)
- 5 charters novos com YAML `mwart_pattern_reuse` (ADR 0149)
- `Repair/JobSheet/Index.charter.md` atualizado v2 (acrescenta `mwart_pattern_reuse` ao charter existente Sprint 2.5)

### RUNBOOKs (7 novos)
- 5 `RUNBOOK-jobsheet-*.md` + 2 `RUNBOOK-repair-*.md` cobrindo F1 PLAN → F2 BASELINE → F3 CODE → F4 QA → F5 CUTOVER per-tela

### Visual comparisons (2)
- `jobsheet-visual-comparison.md` — 15 dimensões pra 5 telas JobSheet
- `repair-visual-comparison.md` — dimensões pra Repair/Show

### Controllers atualizados (2)
- `JobSheetController.php` — branches MWART em `show()`, `edit()`, `create()`, `addParts()` + helpers `buildJobSheet*Payload()` (4 métodos novos)
- `RepairController.php` — branch MWART em `show()` + helpers `buildRepair*Payload()` (2 métodos novos)

### Config (1 atualizado)
- `config/mwart.php` — 6 novas flags MWART: `repair_job_sheet_show/edit/create/add_parts`, `repair_show`, `repair_show_fsm_panel`

### Pest (5 novos arquivos, 18 testes total)
- `Wave3B6JobSheetShowTest.php` (5 testes — flag OFF/ON, biz cross-tenant, FSM endpoints, FSM trait UPDATE direto)
- `Wave3B6JobSheetEditTest.php` (3)
- `Wave3B6JobSheetCreateTest.php` (3)
- `Wave3B6JobSheetAddPartsTest.php` (3)
- `Wave3B6RepairShowTest.php` (4)

## Pest local validation

```
Tests: 18 skipped (0 assertions)
Duration: 6.85s
```

**Padrão correto** — Pest UltimatePOS faz auto-skip em sqlite :memory: (100+ migrations + triggers MySQL não rodam). Mesma estratégia de `RepairIndexMwartTest.php` Sprint 2 (validado em produção biz=1 Wagner).

Em dev/Hostinger c/ DB MySQL real seeded, todos 18 testes rodam — protegem com `markTestSkipped` aninhado se schema mismatch.

## Patrões respeitados (Tier 0)

- ✅ `business_id` scope explícito em todas queries (`JobSheet::where('business_id', $bid)`)
- ✅ Trait `GuardsFsmTransitions` PRESERVADO (não tocado) — Show.tsx executa transição via POST endpoint REPAIR existente (`RepairFsmActionController::execute`)
- ✅ NUNCA UPDATE direto em `current_stage_id` — UI sempre passa por `ExecuteStageActionService` via wrapper `JobSheetFsmPanel`
- ✅ Flag MWART opt-in por business — coexistência Blade preservada (Wagner palavras: "vai mecher no modulo ler brefing e se mexer salva o progresso")
- ✅ PT-BR em UI/comentários/charters/runbooks
- ✅ Zero migrations criadas
- ✅ Zero git ops (parent consolida)
- ✅ Áreas isoladas — nada fora de `Modules/Repair/Http/Controllers/{JobSheet,Repair}Controller.php`, `resources/js/Pages/Repair/**`, `memory/requisitos/Repair/RUNBOOK-*` + `*-visual-comparison.md`, `Modules/Repair/Tests/Feature/Wave3B6*Test.php`, `config/mwart.php`

## FSM Wire-up no Show.tsx (ADR 0143)

Wrapper `JobSheetFsmPanel` (componente interno no `Show.tsx`, ~270 linhas):
- Chama `GET /api/repair/job-sheets/{id}/fsm-actions` pra listar actions
- Renderiza badge stage atual + botões `can_execute`
- Confirmação modal pra `is_critical` ou `requires_confirmation`
- POST `/repair/job-sheets/{id}/fsm-action` executa (passa por `ExecuteStageActionService` no backend → singleton `FsmAuthorizationFlag::mark()` → save autorizado)
- Empty state "Iniciar pipeline FSM" pra OS legacy (sem `current_stage_id`) — POST `/repair/job-sheets/{id}/fsm-start-pipeline`

NUNCA toca `current_stage_id` diretamente. Trait bloqueia tentativa via `UnauthorizedActionException`.

## F5 CUTOVER (parent executa)

Cada tela tem env var opt-in. Canary recomendado biz=1 (Wagner WR2):

```bash
echo "MWART_REPAIR_JOB_SHEET_SHOW=true" >> .env
echo "MWART_REPAIR_JOB_SHEET_SHOW_BIZ=1" >> .env
# repeat por tela
echo "MWART_REPAIR_JOB_SHEET_EDIT=true" >> .env
echo "MWART_REPAIR_JOB_SHEET_EDIT_BIZ=1" >> .env
echo "MWART_REPAIR_JOB_SHEET_CREATE=true" >> .env
echo "MWART_REPAIR_JOB_SHEET_CREATE_BIZ=1" >> .env
echo "MWART_REPAIR_JOB_SHEET_ADD_PARTS=true" >> .env
echo "MWART_REPAIR_JOB_SHEET_ADD_PARTS_BIZ=1" >> .env
echo "MWART_REPAIR_SHOW=true" >> .env
echo "MWART_REPAIR_SHOW_BIZ=1" >> .env
php artisan config:clear
```

ROTA LIVRE (biz=4) **NÃO** usa Repair — sem necessidade de ativar canary lá.

## Riscos catalogados

1. **R1 (MÉDIO)** — `<FsmActionPanel>` Sells assume endpoints `/sells/...`. Solução: wrapper local `JobSheetFsmPanel` no `Show.tsx` (não importa shared). Sells panel intocado.
2. **R2 (BAIXO)** — Sell-lines pesados em Repair/Show → `Inertia::defer` em `activities` + `parts`/`anexos` em JobSheet/Show.
3. **R3 (BAIXO)** — `repair_settings` per-business afeta visibilidade de campos. UI condicional via `options.repair_settings`.
4. **R4 (BAIXO)** — Variation lookup em AddParts via input numeric direto (auto-preenche name no backend). M2 backlog: select async.
5. **R5 (BAIXO)** — pattern divergência AddParts vs blueprint OS justificada (sem add-parts no Cowork — assumido fluxo POS).

## Não fiz

- ❌ git ops (parent consolida)
- ❌ Migrations
- ❌ Tocar tabelas FSM core (`sale_process_stages`, `sale_stage_actions`, etc)
- ❌ Editar `Repair/Index.tsx` (Wave anterior — RUNBOOK apenas documenta compliance)
- ❌ Tocar `JobSheet/Index.tsx` (Wave anterior — charter v2 acrescenta `mwart_pattern_reuse`)
- ❌ Mexer em `Modules/Repair/Resources/js/Pages/` (Inertia não resolve esse path)
- ❌ Screenshots de aprovação visual Wagner (gate F1.5 — pendente sessão screenshot real)

## Próximos passos

1. **Wagner** revisa charters + visual comparison
2. **Wagner** roda `npm run build` + screenshot 1280px → aprova visual
3. **Parent** consolida PR único
4. **F5 CUTOVER** canary biz=1 7d (Wagner monitor)
5. **Skill `brief-update` Tier B** auto-atualiza `memory/requisitos/Repair/BRIEFING.md` (se existir) após merge
