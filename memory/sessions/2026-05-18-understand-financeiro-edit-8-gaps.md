---
slug: 2026-05-18-understand-financeiro-edit-8-gaps
title: "wagner-understand — implementar Edit do Financeiro (drawer Sheet) + 8 gaps Cowork"
type: understand-decode
date: 2026-05-18
session: youthful-matsumoto-21811c
spawned_by: claude-pai
status: ready-for-execution
related_module: Financeiro
related_adrs: [0093, 0094, 0104, 0107, 0114, 0143, 'fin-ui/0002', 'fin-ui/0003', 'fin-tech/0001', 'fin-tech/0002']
related_us: [US-FIN-013, US-FIN-020, US-FIN-021]
referenced_prototype: prototipo-ui/prototipos/financeiro-unificado/visual-source.html
---

# Decodificação

## Pedido cru de Wagner (texto exato)

> "acesso a rquivos se precisar
> Fetch this design file, read its readme, and implement the relevant aspects of the design. https://api.anthropic.com/v1/design/h/NBcYUr1FsA-CvD_xJ82Jaw?open_file=Oimpresso+ERP+-+Chat.html
> Implement: Oimpresso ERP - Chat.html
> teria que implemnetar o edit do financeiro"

> Antes desse texto, Wagner colou doc inline `GAPS_FINANCEIRO_PRA_CODE.md` listando **8 gaps** entre Financeiro prod e padrão Cowork:
> 1. Filtros multi-select com lifecycle states (a vencer / em atraso / pago / sem categoria)
> 2. Categorias reais cadastradas + `desc` com #V-NNNN / #OS-NNNN / #PC-NNNN
> 3. Cross-links #V-/#OS-/#PC- (já existe `FinCrossLinkify`)
> 4. `FinPillFrescor` 6 estados (já existe)
> 5. Classes `.fin-btn-ai`/`.fin-btn-trilha`/`.fin-btn-present` (já existem)
> 6. **"Conferido"** (curadoria Eliana) vs **"Paguei"** (estado FSM) como conceitos distintos
> 7. **Drawer detalhe completo** (anomalia + party history + audit + comentários — já existe)
> 8. Migration `conferido_by` + `conferido_at` na tabela `fin_titulos` (curadoria backend, não localStorage)

## Decodificação refinada

| Campo | Inferência |
|---|---|
| **Objetivo principal cirúrgico** | **Implementar o "edit do financeiro"** = drawer Sheet com botão "Editar" funcional. Hoje o botão `<Button variant="outline">Editar</Button>` em `Index.tsx:706` é stub sem onClick. |
| **Objetivo secundário** | Promover **`conferido`** de localStorage → tabela (gap 6 + gap 8) — é a única dívida estrutural genuína. Resto dos 8 gaps Wagner já está implementado parcialmente ou totalmente. |
| **Gap fantasma** | Wagner listou 8 gaps mas **6 deles já estão implementados na branch atual** (FinCrossLinkify, FinPillFrescor, FinAnomalyDetector, FinPartyHistory, FinAuditTrail, FinCommentsThread, classes `.fin-btn-*`). O documento `GAPS_FINANCEIRO_PRA_CODE.md` que Wagner colou parece desatualizado (pré-Ondas 5/6/7/7b/8/8b mergeadas em PRs #1064-#1085). |
| **Critério de pronto cirúrgico** | (a) clicar "Editar" no drawer abre form preenchido com `Titulo` real; (b) salvar atualiza `cliente_descricao`/`categoria_id`/`vencimento`/`observacoes` (campos editáveis seguros pré-baixa); (c) Pest cross-tenant biz=1 vs biz=99 + idempotência; (d) smoke Brave em prod logado biz=1 mostra drawer abre e save funciona. |
| **Critério de pronto expandido (8 gaps)** | drawer edit + migration `conferido_by`/`conferido_at` + POST `/unificado/{id}/conferir` (com auth + business_id scope) + FinConferidoToggle reescrito pra Inertia POST em vez de localStorage. |
| **Persona alvo** | **Eliana [E]** (advogada+financeiro, escritório, densidade alta, monitor desktop — não mobile). Charter confirma. |
| **Implícitos** | (1) Edit NÃO substitui rotas legacy `/contas-receber/{id}` e `/contas-pagar/{id}` — coexistem; (2) Edit NÃO é cancelamento/estorno (não-goal explícito do charter); (3) campos imutáveis pós-baixa (`valor_total`, `tipo`, `origem`) ficam read-only no drawer; (4) idempotência (ADR fin-tech/0001) — `updated_by` + auditoria. |
| **Ambiguidades a confirmar** | (a) Wagner quer **modal/sheet inline** ou usar **rotas legacy** `/contas-receber/{id}/edit`? Charter Non-Goal #2 diz "drawer leva pra rotas de edição existentes" — Wagner quer mudar essa decisão? (b) `conferido` é per-user (Eliana ≠ Wagner ≠ Bruna) ou per-business único? (c) URL design API: `https://api.anthropic.com/v1/design/h/NBcYUr1FsA-CvD_xJ82Jaw` — Wagner colou link de "Oimpresso ERP - Chat.html" que sugere uma tela "Chat" — relevância pro Financeiro/Edit? Confirmar antes de codar. |

---

# Regras protocolo aplicáveis (R1-R11)

| Regra | Aplica? | O que exige neste pedido |
|---|---|---|
| **R1 Smoke real** | ✅ | Pós-merge edit drawer: abrir Brave/Chrome MCP em `oimpresso.com/financeiro/unificado` logado biz=1, clicar linha, clicar Editar, salvar, screenshot + console clean. Sem isso, não é "feito". |
| **R2 Cópia literal design** | 🟡 parcial | Se Wagner aprovou screenshot de "Oimpresso ERP - Chat.html" mostrando drawer edit específico, cópia integral. Se não, drawer edit segue padrão `CategoriaSheet.tsx` (existente no projeto, mesmo módulo). Confirmar com Wagner ANTES de inflar. |
| **R3 Workflow 3 fases** | ✅ obrigatório | PRE-FLIGHT: ler `memory/requisitos/Financeiro/SPEC.md` + `RUNBOOK-transaction-payment.md` + `ADR fin-ui/0002` + `ADR fin-ui/0003` (amendment unificado) + `ADR fin-tech/0001` (idempotência) + `ADR fin-tech/0002` (soft-delete imutabilidade). DURING: commits incrementais. POST: PR + CI verde + smoke + brief-update Tier B. |
| **R4 Multi-tenant Tier 0** | ✅ obrigatório | (a) Migration `conferido_by`/`conferido_at` em `fin_titulos` — não precisa adicionar `business_id` (tabela já tem); (b) UPDATE no controller `Titulo::where('business_id', $businessId)->findOrFail($id)`; (c) Pest cross-tenant biz=1 vs biz=99 atualiza só do próprio tenant. |
| **R5 PT-BR + economia** | ✅ | Form labels PT-BR ("Descrição", "Categoria", "Vencimento", "Salvar", "Cancelar"). **Confirmar escopo ANTES de codar** (8 gaps ≈ 600+ LOC; só drawer edit ≈ 200 LOC + migration). Wagner ambiguidades 3 itens acima precisam responder. |
| **R6 biz=1 não biz=4** | ✅ | Pest fixtures biz=1. Smoke Brave logado Wagner (biz=1 ou biz=164), nunca Larissa (biz=4 ROTA LIVRE prod). |
| **R7 Charter + visual-comparison** | ✅ obrigatório | Charter `Index.charter.md` v4 existe — **PRECISA EVOLUIR PRA v5** adicionando US-FIN-021 (Form Edit) saindo do Backlog e indo pra Goals; Non-Goal #2 ("drawer leva pra rotas de edição existentes") será removido. Visual-comparison.md já existe (`financeiro-unificado-visual-comparison.md`) — atualizar com diff drawer edit. |
| **R8 Branch + worktree** | ✅ | Trabalho em worktree `D:\oimpresso.com\.claude\worktrees\youthful-matsumoto-21811c\` — paths absolutos do worktree em todo Edit. |
| **R9 Zero auto-mem** | ✅ | Este doc (`memory/sessions/`) vai pro git canon, não pra `~/.claude/projects/`. |
| **R10 Aprovação humana commit/push/merge** | ✅ | Wagner aprovar caminho ANTES de Claude pai começar implementação massiva. As 3 ambiguidades acima precisam resposta primeiro. |
| **R11 Continuar autonomamente até desfecho** | ✅ | Quando Wagner aprovar caminho, Claude pai executa até smoke Brave pós-merge + brief-update sem pausa. |

---

# Inventário no projeto (estado real 2026-05-18)

## O que JÁ EXISTE (não duplicar)

| Item | Path | Status |
|---|---|---|
| Controller principal | `Modules/Financeiro/Http/Controllers/UnificadoController.php` | ✅ multi-tenant ok, biz_id session, can:permission middleware, `shapeTitulo()` helper canon |
| Tabela `fin_titulos` | `Modules/Financeiro/Database/Migrations/2026_04_24_140004_create_fin_titulos_table.php` | ✅ tem business_id FK, soft-delete, idempotency UNIQUE constraint, `metadata` JSON |
| Page Unificado | `resources/js/Pages/Financeiro/Unificado/Index.tsx` (777 linhas) | ✅ KPIs hero + sparkline, filter chips coloridos, drawer detalhe com Sheet, CmdK palette, atalhos, FinPillFrescor inline |
| **Botão "Editar" drawer** | `Index.tsx:706` `<Button variant="outline">Editar</Button>` | 🔴 **STUB sem onClick** — este é o gap real foco do pedido |
| Charter | `resources/js/Pages/Financeiro/Unificado/Index.charter.md` v4 | ✅ canônico, `status: live`, US-FIN-021 listada no Backlog futuro |
| FinCrossLinkify (#V- #OS- #PC-) | `_components/FinCrossLinkify.tsx` | ✅ regex parser completo, 6 patterns, Inertia router.visit, renderiza pills coloridas |
| FinPillFrescor 6 estados | `_components/FinPillFrescor.tsx` | ✅ paid/overdue/today/warning/soon/fresh derivado de vencimento+liquidacao |
| FinConferidoToggle | `_components/FinConferidoToggle.tsx` | 🟡 funciona via localStorage `oimpresso.financeiro.conferido`. Wagner gap 8 quer promover pra tabela. Já há TODO inline: "Onda futura plugará em backend `fin_review_log` table" |
| FinCommentsThread / Badge | `_components/FinCommentsThread.tsx` | ✅ localStorage Eliana↔Wagner↔Bruna |
| FinAuditTrail | `_components/FinAuditTrail.tsx` | ✅ 5 kinds derivado (create/categorize/edit/concil/alert) |
| FinAnomalyDetector | `_components/FinAnomalyDetector.tsx` | ✅ outlier vs média histórica contraparte (Onda 6 R2) |
| FinPartyHistory | `_components/FinPartyHistory.tsx` | ✅ stats contraparte (count, total, média, on-time%, isNew, isRecurrent) |
| FinMonthDigest | `_components/FinMonthDigest.tsx` | ✅ digest mensal Eliana 5min sexta |
| FinChecklistFechamento | `_components/FinChecklistFechamento.tsx` | ✅ 12 passos fechamento mensal + localStorage |
| FinTroubleshooter / PresentationMode | `_components/FinTroubleshooter.tsx` / `FinPresentationMode.tsx` | ✅ Onda 7b |
| Classes `.fin-btn-ai`/`.fin-btn-trilha`/`.fin-btn-present` | CSS canon `resources/css/cockpit.css` ou `fin-*.css` | ✅ Onda 8b filter chips coloridos polish |
| Categorias seeder + tabela | `Modules/Financeiro/Database/Seeders/PlanoContasBrSeeder.php` + `fin_categorias` table | ✅ tabela existe, seeder existe |
| Auto-criação Titulo a partir de Venda | `Modules/Financeiro/Services/TituloAutoService.php` + `Observers/TransactionObserver.php` | ✅ canônico — `origem='venda'`, `origem_id=transaction.id`, idempotente, ADR fin-tech/0001 |
| Pattern Sheet sibling (referência canon) | `resources/js/Pages/Financeiro/Categorias/_components/CategoriaSheet.tsx` | ✅ copiar pattern (form + validation + Inertia POST) |
| Rotas Financeiro | `Modules/Financeiro/Routes/web.php` | ✅ stack canônico `['web','auth','language','timezone','AdminSidebarMenu']` |

## O que NÃO EXISTE (gap real)

| Item | Path destino | Esforço |
|---|---|---|
| **Edit drawer/modal funcional do Titulo** | criar `_components/TituloEditSheet.tsx` + handler `Index.tsx:706` | ~200 LOC TSX |
| **Method `UnificadoController::update(int $id, UpdateTituloRequest)`** | adicionar em `UnificadoController.php` | ~40 LOC PHP |
| **FormRequest `UpdateTituloRequest`** | criar `Modules/Financeiro/Http/Requests/UpdateTituloRequest.php` | ~30 LOC PHP |
| **Rota PUT `/financeiro/unificado/{id}`** | adicionar em `Routes/web.php` | 3 linhas |
| **Migration `add_conferido_to_fin_titulos`** | criar `2026_05_18_HHMMSS_add_conferido_to_fin_titulos_table.php` | ~30 LOC PHP — 2 columns nullable: `conferido_by` (FK users), `conferido_at` (timestamp) |
| **Method `UnificadoController::conferir(int $id)`** | adicionar em `UnificadoController.php` | ~25 LOC PHP |
| **Rota POST `/financeiro/unificado/{id}/conferir`** | adicionar em `Routes/web.php` | 3 linhas |
| **FinConferidoToggle reescrito Inertia POST** | atualizar `_components/FinConferidoToggle.tsx` substituindo localStorage por router.post + Eloquent persist | ~30 LOC diff |
| **Pest test `UnificadoEditTest`** | criar `Modules/Financeiro/Tests/Feature/UnificadoEditTest.php` | ~120 LOC PHP — cobre auth, biz scope, idempotência, soft-delete imutabilidade |
| **Charter `Index.charter.md` v5** | atualizar v4→v5 movendo US-FIN-021 de Backlog→Goals; remover Non-Goal #2 | ~10 linhas diff |
| **Visual-comparison.md update** | append section "Drawer edit" em `memory/requisitos/Financeiro/financeiro-unificado-visual-comparison.md` | ~30 linhas |

## ADRs canon relacionadas (leitura obrigatória PRE-FLIGHT)

- [ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2
- [ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART canônico
- [ADR 0107](memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) Visual-comparison gate F3
- [ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop formalizado
- [ADR fin-arq/0005](memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) Módulo Financeiro paralelo
- [ADR fin-ui/0002](memory/requisitos/Financeiro/adr/ui/0002-dashboard-unificado-4-estados.md) Dashboard unificado
- [ADR fin-ui/0003](memory/requisitos/Financeiro/adr/ui/0003-amendment-0002-visao-unificada-cockpit-v2.md) Amendment Cockpit V2
- [ADR fin-tech/0001](memory/requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md) Idempotência mutação financeira
- [ADR fin-tech/0002](memory/requisitos/Financeiro/adr/tech/0002-soft-delete-com-trava-historico.md) Soft-delete + trava histórico

---

# Pegadinhas conhecidas (alertas Tier 0)

- **[Tier 0]** `business_id` global scope obrigatório em `UnificadoController::update()` — `Titulo::where('business_id', $businessId)->findOrFail($id)`, nunca `findOrFail($id)` direto (vaza cross-tenant)
- **[Tier 0 fin-tech/0002]** Pós-baixa `status='quitado'`: campos `valor_total`, `tipo`, `origem`, `origem_id` ficam **imutáveis** (trava histórico). Drawer edit deve renderizar read-only nesses campos quando `status === 'quitado'`. Não é Non-Goal — é regra de imutabilidade legal/contábil
- **[Tier 0 fin-tech/0001]** Mutação financeira tem `idempotency_key UUID`. Pra UPDATE de título (não baixa), basta `updated_by` + `updated_at` (Laravel auto). Não inventar coluna nova
- **[LICOES_F3]** M-AP-1 Auto-aprendizado ignorado sob pressão delivery — Wagner já queimou bateria com batch F3 Financeiro rejeitado 2026-05-09 (5 controllers stub). **Antes de criar `_components/TituloEditSheet.tsx`, ler `Modules/Financeiro/Resources/js/Pages/Financeiro/Categorias/_components/CategoriaSheet.tsx` (pattern Sheet sibling do mesmo módulo)** e copiar literal
- **[LICOES_F3 T-AP-1]** Models inventados — usar `Titulo` (NÃO `FinancialEntry`), `Categoria` (NÃO `Category`), `ContaBancaria` (NÃO `BankAccount`)
- **[LICOES_F3 T-AP-2]** Tenant scope ausente — `session('user.business_id')`, NÃO `auth()->user()->business_id`
- **[LICOES_F3 T-AP-8]** `auth()->user()->business_id` quebra em jobs/CLI. UPOS canon = `session('user.business_id')`
- **[LICOES_F3 T-AP-13]** Mutação NO-OP — endpoint update DEVE implementar ou `abort(501)`, nunca `return back()` silencioso
- **[Pegadinha Windows]** PowerShell 5.1 `Set-Content -Encoding utf8` grava BOM → quebra PHP. Migration nova via `[System.IO.File]::WriteAllText(...UTF8Encoding $false)` OU Python `python -c`
- **[Worktree]** Trabalhando em `D:\oimpresso.com\.claude\worktrees\youthful-matsumoto-21811c\` — todo Edit path absoluto do worktree
- **[charter-first]** Editar Page Index.tsx exige ler `Index.charter.md` ANTES (já feito neste decode)
- **[Inertia::defer]** Index controller já é rápido (~50ms com `limit(500)` + eager-load + KPI aggregations pequenas). NÃO precisa deferir o payload base. Mas se adicionar prop nova com query cara (ex: stats histórico contraparte server-side futuro), usar `Inertia::defer()`
- **[Sheet shadcn componente]** Já importado em `Index.tsx:20` — reusar. Pattern `<Sheet open={editOpen}><SheetContent side="right">...</SheetContent></Sheet>` segue padrão drawer detalhe atual
- **[FinConferidoToggle migração localStorage → DB]** Backward compat: ler localStorage existente no mount, fazer **migration one-shot** via POST batch pro backend, depois operar só backend. Detalhe técnico — propor abordagem ao Wagner antes

---

# Plug-points exatos (caminhos arquivo:linha)

## Foco principal — Edit drawer

| Camada | Arquivo:linha | Mudança |
|---|---|---|
| **Frontend handler** | `resources/js/Pages/Financeiro/Unificado/Index.tsx:706` | substituir stub `<Button variant="outline">Editar</Button>` por `<Button variant="outline" onClick={() => setEditOpen(true)}>Editar</Button>` |
| **Frontend state** | `Index.tsx:381-385` (zona useState) | adicionar `const [editOpen, setEditOpen] = useState(false);` |
| **Frontend novo Sheet** | `Index.tsx:765` (depois do PresentationMode) | adicionar `<TituloEditSheet open={editOpen} onClose={() => setEditOpen(false)} titulo={selected} categorias={categorias} contas={contas} />` |
| **Frontend novo componente** | NEW `_components/TituloEditSheet.tsx` | Sheet com form `descricao` / `categoria_id` / `vencimento` / `observacoes` editáveis + read-only `valor_total` / `tipo` / `numero` quando settled. Pattern: copiar `Categorias/_components/CategoriaSheet.tsx` |
| **Frontend Index.tsx imports** | `Index.tsx:37` (zona _components imports) | adicionar `import { TituloEditSheet } from './_components/TituloEditSheet';` |
| **Backend Controller** | `Modules/Financeiro/Http/Controllers/UnificadoController.php:190` (depois de `baixar()`) | adicionar method `update(UpdateTituloRequest $request, int $id): RedirectResponse` |
| **Backend FormRequest** | NEW `Modules/Financeiro/Http/Requests/UpdateTituloRequest.php` | rules: `descricao` string max 255 nullable, `categoria_id` exists fin_categorias filtered biz, `vencimento` date pós ou igual `emissao`, `observacoes` string nullable |
| **Backend Route** | `Modules/Financeiro/Routes/web.php:60` (depois da rota baixar) | adicionar `Route::put('/unificado/{id}', [UnificadoController::class, 'update'])->whereNumber('id')->name('unificado.update');` |
| **Pest** | NEW `Modules/Financeiro/Tests/Feature/UnificadoEditTest.php` | 8 testes: auth required, biz scope (biz=99 não edita biz=1), categoria_id cross-tenant rejeitada, vencimento date validation, imutabilidade pós-baixa (valor_total/tipo readonly), observacoes update ok, audit log (updated_by set), Sheet smoke estrutural |
| **Charter** | `Index.charter.md` v4→v5 | mover US-FIN-021 de §Backlog futuro pra §Goals; remover Non-Goal #2 ("Edição inline de título — drawer leva pra rotas de edição existentes") |
| **Visual-comparison** | `memory/requisitos/Financeiro/financeiro-unificado-visual-comparison.md` | append section "Drawer Edit (US-FIN-021)" com screenshot Cowork vs prod |

## Foco secundário — Conferido DB-backed (gap 8 do doc Wagner)

| Camada | Arquivo:linha | Mudança |
|---|---|---|
| **Migration** | NEW `Modules/Financeiro/Database/Migrations/2026_05_18_HHMMSS_add_conferido_to_fin_titulos_table.php` | `ALTER TABLE fin_titulos ADD conferido_by INT UNSIGNED NULL FK users, ADD conferido_at TIMESTAMP NULL, ADD INDEX (business_id, conferido_at)` |
| **Model** | `Modules/Financeiro/Models/Titulo.php` | adicionar `conferido_by` + `conferido_at` em `$fillable` + casts `'conferido_at' => 'datetime'` |
| **Backend Controller** | `UnificadoController.php` | adicionar method `conferir(int $id): RedirectResponse` (toggle on/off, set conferido_by=auth()->id() / conferido_at=now() ou NULL) |
| **Backend Route** | `Routes/web.php` | `Route::post('/unificado/{id}/conferir', [UnificadoController::class, 'conferir'])->whereNumber('id')->name('unificado.conferir');` |
| **Backend shape** | `UnificadoController::shapeTitulo()` | adicionar campos `conferido: bool`, `conferido_by_name: string\|null`, `conferido_at_label: string\|null` no payload |
| **Frontend FinConferidoToggle** | `_components/FinConferidoToggle.tsx` | substituir localStorage `useFinConferido()` por prop `conferido` do row + `router.post('/financeiro/unificado/{id}/conferir')` |
| **Pest** | `UnificadoEditTest.php` (mesmo arquivo) | +4 testes: conferir toggle on, toggle off (UPDATE conferido_by=NULL), biz scope, conferido_at set |

---

# Tasks atômicas + estimate (fator 10x ADR 0106)

## Caminho A — Cirúrgico (recomendado se Wagner confirmar ambiguidades)

| # | Task | LOC est | Tempo IA-pair | Bloqueia? |
|---|---|---|---|---|
| **A1** | Migration `add_conferido_to_fin_titulos` + Model fillable + Pest migration up/down idempotente | 60 LOC | ~20min | base de A3, A5 |
| **A2** | FormRequest `UpdateTituloRequest` + Pest validation rules | 80 LOC | ~25min | base de A3 |
| **A3** | Method `UnificadoController::update()` + `conferir()` + shape ajustado + Route PUT/POST | 100 LOC | ~30min | depende A1, A2 |
| **A4** | Frontend `TituloEditSheet.tsx` novo componente | 200 LOC | ~45min | depende A3 |
| **A5** | Frontend `FinConferidoToggle.tsx` rewrite localStorage→Inertia POST | 80 LOC | ~25min | depende A1, A3 |
| **A6** | Index.tsx wire-up edit button + state + import + render TituloEditSheet | 30 LOC | ~10min | depende A4 |
| **A7** | Pest `UnificadoEditTest.php` (12 testes: edit + conferir + cross-tenant + imutabilidade) | 250 LOC | ~50min | depende A1-A6 |
| **A8** | TypeScript check + ESLint + Pest local verde | — | ~15min | depende A1-A7 |
| **A9** | Charter v4→v5 + visual-comparison.md update | 50 LOC | ~15min | paralelo A1-A8 |
| **A10** | PR open + CI watch verde + Wagner aprovação merge (R10) | — | ~20min wait | depende A1-A9 |
| **A11** | Merge + deploy SSH Hostinger + curl smoke real (R1) | — | ~15min | depende A10 |
| **A12** | Brave/Chrome MCP smoke pos-merge prod biz=1 — clicar Editar, salvar, screenshot + console clean (R1) | — | ~15min | depende A11 |
| **A13** | brief-update Tier B atualiza `memory/requisitos/Financeiro/BRIEFING.md` | 20 LOC | ~5min | depende A12 |
| **TOTAL** | | ~870 LOC | **~4h30 IA-pair** | 1 PR único (override design-literal-copy se Wagner aprovou Sheet do Cowork) OU 2 PRs separados (edit / conferido) |

**Decomposição em PRs ≤300 LOC** (skill commit-discipline):

- **PR 1 — Backend Edit** (A1+A2+A3+A7-parte-edit): ~430 LOC PHP → tirar pra ≤300 separando migration em PR independente
  - **PR 1a:** Migration `add_conferido` (60 LOC) — sem dependência, deploy primeiro
  - **PR 1b:** Backend update endpoint (220 LOC) — depois de 1a
- **PR 2 — Frontend Edit Sheet** (A4+A6+A9): ~280 LOC TSX+MD → ok ≤300
- **PR 3 — Conferido DB-backed** (A5+A7-parte-conferir): ~130 LOC → ok ≤300

**Ordem sequencial estrita:** 1a → 1b → 2 → 3 (devido a dependências de migration deployada + endpoint disponível).

## Caminho B — 8 gaps inteiros (caso Wagner confirme escopo expandido)

| Gap | Status real | Trabalho |
|---|---|---|
| Gap 1 Filtros multi-select lifecycle | parcial (filter chips existem mas single-select tab) | ~3h — refator chips→multi-select + KPI cards drill com OR-state |
| Gap 2 Categorias reais + `desc` #V-/#OS- | seeder existe mas só núcleo. Auto-criação Titulo a partir de venda já adiciona `cliente_descricao` baseado em customer name | ~30min — adicionar prefix `#V-{tx.id}` em `cliente_descricao` no TituloAutoService linha ~118 |
| Gap 3 Cross-links #V-/#OS-/#PC- | ✅ implementado (FinCrossLinkify) | 0 |
| Gap 4 FinPillFrescor 6 estados | ✅ implementado | 0 |
| Gap 5 Classes fin-btn-ai/trilha/present | ✅ implementado Onda 8b | 0 |
| Gap 6 Conferido vs Paguei conceitos | parcial (Conferido localStorage, Paguei FSM ok) | mesmo trabalho A1+A5 (~1h30) |
| Gap 7 Drawer detalhe completo | ✅ implementado (anomaly+history+audit+comments) | 0 |
| Gap 8 Migration conferido_by/conferido_at | falta | mesmo trabalho A1 (~20min) |
| **TOTAL B** | | **~4h30 cirúrgico + ~3h gaps adicionais reais = ~7h30** |

---

# Recomendação pro Claude pai

## Caminho recomendado

**Caminho A cirúrgico** (4h30 IA-pair) + decomposição em **3 PRs sequenciais** (≤300 LOC cada).

**Justificativa:**
1. **6 dos 8 gaps já estão implementados** nas Ondas 5-8b mergeadas (PRs #1064-#1085). O doc `GAPS_FINANCEIRO_PRA_CODE.md` que Wagner colou está desatualizado.
2. Gap 6+8 (Conferido DB-backed) + "edit do financeiro" são o **trabalho real único**.
3. Wagner palavras textuais: *"teria que implemnetar o edit do financeiro"* — pedido cirúrgico, não scope expandido.
4. Caminho B (8 gaps inteiros) adiciona Gap 1 (filtros multi-select lifecycle) e Gap 2 (prefix #V- em desc) que valem mas **não foram pedidos explicitamente** — propor como follow-up.

## O que confirmar com Wagner ANTES de codar

| # | Pergunta | Razão |
|---|---|---|
| **Q1** | Edit é **drawer Sheet inline** (recomendado pela URL "Oimpresso ERP - Chat.html" sugerir drawer) ou redirecionamento pras rotas legacy `/contas-receber/{id}/edit`? | Charter Non-Goal #2 atual diz "drawer leva pra rotas existentes" — mudança de Non-Goal exige confirmação |
| **Q2** | `conferido_by` é **per-user** (Eliana ≠ Wagner ≠ Bruna conferem separadamente) ou **per-business único** (qualquer user do business confere e fica visível pra todos)? | Default proposto: per-business único (FK users, qualquer user que conferir conta). Migração de localStorage só faz sentido assim |
| **Q3** | URL design `api.anthropic.com/v1/design/h/NBcYUr1FsA-CvD_xJ82Jaw?open_file=Oimpresso+ERP+-+Chat.html` — Wagner aprovou esse "Chat.html" como referência visual do drawer Edit, ou é doc de outra feature (módulo Chat?) que ele colou por engano junto? | Se for design canon de Edit Financeiro, R2 cópia literal aplica (1 PR override design-literal-copy). Se for outra feature, edit segue pattern `CategoriaSheet.tsx` |
| **Q4** | OK incluir gap secundário **#V-/#OS- prefix em `cliente_descricao`** (Gap 2 do doc) como PR 4 follow-up? É ~30min, melhora cross-link já existente | Pequeno mas valioso pra fechar loop Financeiro↔Vendas |
| **Q5** | Backward compat de `FinConferidoToggle` localStorage existente — descartar dados antigos ou fazer migration one-shot? Eliana já marcou N títulos como conferidos no dispositivo dela | Decisão de UX |

## Skills que DEVEM ativar (auto-trigger pelo path)

- **brief-first** Tier A always-on (já carregado SessionStart)
- **multi-tenant-patterns** Tier A always-on — Eloquent Model + Controller + Migration novos
- **commit-discipline** Tier A always-on — 1 PR = 1 intent, ≤300 LOC, conventional commits
- **mwart-process** Tier A always-on — Edit em `Modules/<X>/` (toca módulo)
- **charter-first** Tier A always-on — Edit em Page `.tsx` com `.charter.md` ao lado
- **inertia-defer-default** Tier B auto-trigger — Index.tsx render com props caras (já validado no atual; novo update endpoint não adiciona props caras, mas se vier prop de stats contraparte server-side, deferir)
- **preflight-modulo** Tier B auto-trigger — Edit em `Modules/Financeiro/Http/Controllers/`
- **smoke-prod-evidence** Tier B auto-trigger — pré-merge + pós-merge curl/screenshot evidência
- **brief-update** Tier B auto-trigger — pós-merge atualiza `memory/requisitos/Financeiro/BRIEFING.md`
- **tela-smoke-pos-merge** Tier B auto-trigger — Brave MCP screenshot pos-deploy

## Hook bloqueadores ativos a considerar

- `block-mwart-violation.ps1` — exige `RUNBOOK-<tela-kebab>.md` antes de Edit `Pages/<Mod>/<Tela>.tsx`. **CHECAR:** existe `memory/requisitos/Financeiro/RUNBOOK-unificado.md` ou só `RUNBOOK-transaction-payment.md`? Provavelmente precisa criar RUNBOOK ou override `/mwart-override <razão>` em PR body
- `block-automem.ps1` — não aplica (sem Write em ~/.claude/projects/*/memory/)
- `post-merge-ui-smoke-required.ps1` — pós-merge bloqueia próximas declarações "pronto/funcionando" até screenshot MCP. Garantir smoke Brave automático
- `block-claim-without-evidence.ps1` — `gh pr create` em branch que toca infra crítica exige Infra Contract — **NÃO se aplica** (Routes web.php mexe, mas nova rota não muda runtime — só add endpoint)

---

# Sumário compacto pro Claude pai (~30 linhas)

**Pedido decodificado:** Wagner quer drawer Edit funcional em `/financeiro/unificado` + promover Conferido de localStorage→DB. Os outros 6 dos 8 gaps do doc colado já estão implementados nas Ondas 5-8b mergeadas (Wagner doc está desatualizado).

**Estado atual:**
- `Index.tsx:706` tem `<Button variant="outline">Editar</Button>` STUB sem onClick — gap real
- `FinConferidoToggle` funciona via localStorage com TODO inline "Onda futura plugará em backend `fin_review_log`"
- Tabela `fin_titulos` migration existe, multi-tenant ok, soft-delete + idempotency UNIQUE
- Charter `Index.charter.md` v4 lista US-FIN-021 (Form Edit) no Backlog — precisa promover pra Goals v5
- Pattern Sheet sibling canônico: `Categorias/_components/CategoriaSheet.tsx` — copiar

**Plug-points cirúrgicos:**
- Frontend: NEW `_components/TituloEditSheet.tsx` + wire em `Index.tsx:706` + state
- Backend: `UnificadoController::update()` + `conferir()` + `UpdateTituloRequest` + 2 Routes
- Migration: `add_conferido_to_fin_titulos` (conferido_by FK users + conferido_at + index)
- Pest: `UnificadoEditTest.php` ~12 testes (cross-tenant, imutabilidade pós-baixa, validation, conferido toggle)
- Charter v4→v5 + visual-comparison.md update

**Pegadinhas Tier 0:**
- LICOES_F3_FINANCEIRO_REJEITADO catalogou 15 anti-padrões — usar `Titulo`/`Categoria`/`ContaBancaria` (PT-BR), `session('user.business_id')`, stack middleware canônica, sem mutação NO-OP
- Pós-baixa `status='quitado'`: campos `valor_total`/`tipo`/`origem`/`origem_id` imutáveis (fin-tech/0002)
- PowerShell BOM mata PHP — usar UTF8Encoding $false ou Python pra Write
- Worktree: paths absolutos `D:\oimpresso.com\.claude\worktrees\youthful-matsumoto-21811c\...`

**Tasks atômicas:** 13 (~870 LOC, ~4h30 IA-pair) → 3 PRs sequenciais ≤300 LOC:
- PR 1a Migration conferido (60 LOC) → deploy
- PR 1b Backend update endpoint (220 LOC) → depende 1a
- PR 2 Frontend TituloEditSheet (280 LOC) → depende 1b
- PR 3 Conferido DB-backed FinConferidoToggle rewrite (130 LOC) → depende 1a+1b

**ANTES de codar — confirmar com Wagner 5 perguntas:**
1. Drawer Sheet inline OU redirecionar pra rotas legacy `/contas-receber/{id}/edit`? (Charter Non-Goal #2)
2. `conferido_by` per-user OU per-business único?
3. URL design "Oimpresso ERP - Chat.html" — esse é canon do Edit drawer ou doc de outra feature colado por engano?
4. OK incluir PR 4 follow-up Gap 2 (#V- prefix em cliente_descricao)? +30min
5. Backward compat localStorage→DB Conferido — descartar antigos ou migration one-shot?

**R10/R11:** Wagner aprovar caminho UMA vez ("sim faz caminho A com Q1=Sheet inline / Q2=per-business / Q3=ignora link / Q4=sim PR 4 / Q5=descartar antigos") → Claude pai executa 3 PRs sequenciais até desfecho final (smoke Brave pós-merge + brief-update) sem pausa interna.

**ADRs canon leitura obrigatória PRE-FLIGHT:** ADR 0093 + 0094 + 0104 + 0107 + 0114 + fin-arq/0005 + fin-ui/0002 + fin-ui/0003 + fin-tech/0001 + fin-tech/0002 + LICOES_F3_FINANCEIRO_REJEITADO.md + Index.charter.md v4 + RUNBOOK-transaction-payment.md.
