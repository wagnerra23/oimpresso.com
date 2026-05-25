---
slug: compras-purchase-convergencia-c1
title: "Compras × Purchase convergência C1 — cockpit /compras delega CRUD pra /purchases/* Inertia"
type: adr
status: proposed
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-25"
proposed_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-25 via AskUserQuestion 3-opções pós-comparativo Compras × Sells — opção C1 (cockpit reusa Pages/Purchase/*) escolhida sobre C2 (reescrever Create no estilo Cowork) e C3 (coexistir)"
module: Compras
quarter: 2026-Q2
tags: [compras, purchase, mwart, convergence, cockpit, multi-tenant, tier-0, append-only, charter-amendment]
supersedes: []
supersedes_partially: [compras-modulo-greenfield-hibrido]
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0107-emendation-0104-visual-comparison-gate-f3"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0141-skill-migracao-blade-react"
  - "0149-pattern-reuse-mwart-create-edit"
charter_impact:
  - "Pages/Compras/Index.charter.md v1 → v2 (Non-Goals + Anti-hooks reescritos: 'NÃO permite criar inline' vira 'NÃO replica form Create — delega /purchases/create via router.visit')"
spec_impact:
  - "memory/requisitos/Compras/SPEC.md §3 — Caminho C1 substitui Caminho B Wave 8 prometida"
  - "US-COM-002 (criar compra manual) — delega a Purchase MWART Wave 2 B5"
  - "US-COM-004 (deprecar /purchases) — INVERTE direção: /purchases canônico, /compras é cockpit complementar"
pii: false
review_triggers:
  - "Wagner decidir criar Pages/Compras/Create.tsx mesmo assim (vertical-específico vestuário Larissa) → revisar C1 vs C2"
  - "Time MCP (Felipe/Maiara) reportar 'qual link uso /compras ou /purchases' >3 vezes em 7d → criar topnav Modules/Compras/Resources/menus/topnav.php apontando /purchases/create"
  - "GradeMatrixInput Wave 4.5 ROTA LIVRE Larissa biz=4 exigir entry-point cockpit /compras → criar Pages/Compras/Create.tsx APENAS pra vertical vestuário (não default)"
  - "Permission gap user com compras.view mas SEM purchase.create reportado >2 vezes → criar alias PermissionsSeeder compras.create ↔ purchase.create"
---

# ADR · Compras × Purchase convergência C1

## Contexto

Wagner pediu comparativo Compras × Sells na sessão `frosty-greider-83ab2f` 2026-05-25 após Ondas 3+4+5+6 do ADR 0192 (Integração Vendas × Oficina) terem subido a nota Vendas de 9,0 → 9,3.

Comparativo descobriu que existem **2 trilhos paralelos de Compras** vivos hoje:

| Trilho | Rota | Onde | LOC | Status |
|---|---|---|---:|---|
| **A — Purchase (UltimatePOS core)** | `/purchases` `/purchases/create` `/purchases/{id}/edit` `/purchases/{id}` | `Pages/Purchase/*` + `app/Http/Controllers/PurchaseController.php` | 1.729 (Index+Create+Edit+Show) | **F3 implementado · aguarda smoke Wagner** (piloto skill `migracao-blade-react` v0.1.0 · ADR 0141) |
| **B — Compras (Modules greenfield)** | `/compras` | `Pages/Compras/Index.tsx` + `Modules/Compras/Http/Controllers/ComprasController.php` | 828 (Index só · drawer 2 tabs) | scaffold Wave 1+2+3+4 F1 · SPEC `proposed` |

A SPEC Compras §3 + US-COM-002 + US-COM-004 (referenciando proposta [`compras-modulo-greenfield-hibrido`](compras-modulo-greenfield-hibrido.md) ainda `proposed`) prometia:

- Wave 8: criar `Pages/Compras/Create.tsx` form completo Cowork
- US-COM-004: `/purchases` Blade legacy deprecada via 301 → `/compras` quando flag ON

**Erro de premissa descoberto agora:** SPEC trata `/purchases` como Blade legacy. Mas o trilho A **JÁ É React/Inertia** desde a Wave 2 B5 do piloto `migracao-blade-react` (ADR 0141). `/purchases/create`, `/purchases/{id}/edit`, `/purchases/{id}` retornam Inertia React via dual-response (`X-Inertia` header ou `?v=2` query — `PurchaseController:400, 928`). `Purchase/Create.tsx` (520 LOC) e `Purchase/Edit.tsx` (502 LOC) têm charters Tier A ★ com ADRs canon 0104/0093/0114/0149 já validados.

Manter os 2 trilhos e seguir SPEC original cria:

1. **3 telas pra mesma operação** — `/purchases/create` (Purchase MWART), `/compras/create` (Compras Wave 8 futuro), Blade legacy ainda viva pra rollback
2. **1.729 LOC duplicados** + 4 testes Pest Wave2 duplicados + charters Tier A duplicados
3. **Confusão pra Felipe/Maiara** ("qual link uso?") — sidebar com 2 entry-points pra mesma intent
4. **Débito de manutenção** — bug em Purchase/Create exige fix em 2 lugares depois

**3 caminhos avaliados** (Wagner via AskUserQuestion):

| Caminho | Trade-off |
|---|---|
| **C1 · Cockpit Compras vira shell e reusa `/purchases/*`** | Reusa 1.729 LOC já em smoke. Mata duplicação. ~30 LOC efetivas. Charter Tier A intacto. Perde verbatim Cowork no Create. |
| **C2 · Wave 8 reescreve Create no estilo Cowork (`Pages/Compras/Create.tsx`)** e deprecia Purchase/Create | Visual Cowork end-to-end. 520 LOC charter Tier A vira débito morto. Quebra ADR 0149 pattern reuse. |
| **C3 · Coexistir** (status quo) | Confusão sidebar. PR Wave 8 cria duplicação confirmada. |

## Decisão

**Caminho C1 — cockpit `/compras` delega CRUD pra `/purchases/*` Inertia.**

### Contrato

1. **`/compras` permanece como cockpit de listagem + KPIs + drawer denso Cowork** — preserva valor visual da Wave 1-4 (CSS bundle `cowork-compras-bundle.css` aplicado Tier 0)
2. **Botão "+ Nova compra"** (`Pages/Compras/Index.tsx:240-242`) deixa de ser `disabled` → `onClick={() => router.visit('/purchases/create')}`. `router.visit` injeta `X-Inertia` header automaticamente, dispara dual-path em `PurchaseController:400`, retorna `Purchase/Create.tsx` Inertia React. **NÃO** cai no Blade
3. **AcoesDropdown "Editar / Atualizar status / Notif. pendente"** (linhas 115, 152, 162) — trocar `navigateBlade(url) → window.location.href` (cai no Blade legacy sem header X-Inertia) por `router.visit(url)` (Inertia → Purchase/Edit.tsx React)
4. **Sidebar primary action** (`DataController:114`) — `'href' => '/compras/create'` (404 hoje) vira `'href' => '/purchases/create'`
5. **Permission gate** — botão "+ Nova compra" lê `props.permissions.create` que vem de `auth()->user()->can('purchase.create')` (não `compras.create` — alias V2 se Wagner mantiver módulo como conceito separado)
6. **Drawer cockpit `/compras` 5 tabs in-page mantido** — Cowork denso é diferencial UX da listagem. Adicionar link secundário "Ver tela cheia" no rodapé do drawer apontando `/purchases/{id}?v=2` é opção V2
7. **Botão "↓ Importar XML"** — segue `disabled` aguardando Wave 6 (bridge `ImportarDfeComoCompraService` US-COM-003 GAP NOVO) — **fora do escopo C1**

### Multi-tenant Tier 0 ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md))

**Boa surpresa do audit:** brief Wagner suspeitou de divergência (`auth()->user()->business_id` em Purchase vs `session('user.business_id')` em Compras). **Não há divergência.** `PurchaseController` linhas 66, 354, 482, 634, 834, 1057, 1186, 1284, 1342, 1432 → **todas** usam `request()->session()->get('user.business_id')`. Igual ao `ComprasController:43`.

C1 não toca scope — só roteamento. Tier 0 fica intacto.

### Charter impact

- `Pages/Compras/Index.charter.md` v1 → v2:
  - Frontmatter `charter_version: 1` → `2`, `last_validated: 2026-05-25`
  - Non-Goal "NÃO permite criar compra inline — botão '+ Nova compra' disabled. Form completo Wave 8 (`/compras/create`)" → "NÃO replica form Create — delega `/purchases/create` Inertia via `router.visit` (C1 · ADR `compras-purchase-convergencia-c1`)"
  - Non-Goal "NÃO toca `/purchases` legacy — deprecação só na Wave 8 via 301 + feature flag" → "Cockpit reusa `/purchases/*` Inertia (Trilho A piloto MWART Wave 2 B5). `/compras` e `/purchases/*` coexistem como cockpit + CRUD"
  - Anti-hook "Aparecer botão 'criar compra inline' sem Wave 8 — drift" → "Aparecer botão 'criar compra inline' que NÃO seja `router.visit('/purchases/create')` — drift"
- `Pages/Purchase/Create.charter.md` e `Pages/Purchase/Edit.charter.md` **intocados** (Tier A ★ piloto MWART)

### SPEC impact

- `memory/requisitos/Compras/SPEC.md` §3 amendment 2026-05-25 — Caminho C1 substitui Caminho B Wave 8 prometida
- US-COM-002 (criar compra manual) — vira "delegada a Purchase MWART Wave 2 B5 via router.visit; nasce wrapper `ComprasService::criar()` SE/QUANDO sinal real exigir vertical-específico vestuário"
- US-COM-004 (deprecar `/purchases`) — INVERTE direção: `/purchases` canon, `/compras` cockpit complementar; R-COM-302 anula
- Status SPEC `proposed` → `accepted` via esta ADR (substitui dependência da proposta `compras-modulo-greenfield-hibrido` ainda não promovida)

### Permissions

- Botão "+ Nova compra" usa `purchase.create` (não `compras.create`)
- AcoesDropdown "Editar" usa `purchase.update`
- AcoesDropdown "Excluir" mantém `purchase.delete` (já no Purchase/Index.tsx)
- Cockpit `/compras` continua gateado por `compras.view` (catálogo permissions Modules/Compras intacto)
- **Sem alias** `compras.create ↔ purchase.create` agora — review trigger ativa se gap reportado

### Tests

- `Modules/Compras/Tests/Feature/ComprasIndexTest.php` — adicionar teste 4 "user com `purchase.create` vê prop `permissions.create=true`" e teste 5 "Inertia component `Purchase/Create` é retornado quando user clica botão (smoke `withHeaders(X-Inertia)->get('/purchases/create')`)"
- `tests/Feature/Purchase/Wave2CreateInertiaTest.php` — **intocado** (smoke convergência cross-modulo opcional fora do escopo C1 MVP)

## Status

**proposed** — aguarda Wagner aprovar via merge do PR `feat/compras-purchase-convergencia-c1`. Implementação ~30 LOC efetivas + amendments charter v2 + SPEC + esta ADR.

## Consequências

### Positivas

- **Mata duplicação preventiva** — Wave 8 prometia criar `Pages/Compras/Create.tsx` (520 LOC + charter Tier A + testes). C1 cancela essa wave.
- **Reuso integral Wave 2 B5 MWART** — 1.729 LOC + 4 testes Pest + charters Tier A já validados (F3 implementado aguardando smoke Wagner).
- **Sidebar primary action conserta link quebrado** — `/compras/create` hoje retorna 404; C1 aponta `/purchases/create` que existe.
- **Princípio "Charter > Spec" da Constituição v2** preservado — charter Purchase/Create Tier A ★ continua sendo fonte da verdade.
- **Princípio "Loop fechado por métrica"** — se review trigger ativar (Larissa exigir vertical-específico vestuário, ou gap de permission reportado), criar Pages/Compras/Create.tsx aí.
- **ADR 0149 (pattern reuse)** — C1 é exemplo canônico: cockpit greenfield reusa trilho MWART em vez de duplicar.

### Negativas

- **Charter Compras/Index v1 Non-Goals + Anti-hooks ficam violados** entre merge desta ADR e amendment charter — mitigação: ambos vão no mesmo PR atômico (decisão Wagner opção C "tudo num PR só").
- **Visual `/purchases/create` ainda é Tailwind shadcn (não Cowork bundle)** — divergência estética entre cockpit `/compras` (Cowork denso) e form `/purchases/create` (shadcn utility). Aceitável até Larissa ou outro cliente reportar dor; review trigger #1 ativa.
- **SPEC US-COM-002 e US-COM-004 ficam vazios** — manutenção exige amendment ou ADR explicit superseded.
- **Permission split `compras.view` × `purchase.create`** — user com 1 sem o outro vê UI inconsistente. Aceitável V1; review trigger #4 ativa.
- **`/compras/create` rota fica 404 definitivamente** — se algum link interno hardcoded apontar pra lá (busca grep cobre), redirect 301 → `/purchases/create` opcional V2.
- **Proposta `compras-modulo-greenfield-hibrido`** fica **parcialmente superseded** — apenas a parte que prometia Wave 8 com `/compras/create` é anulada. Backend híbrido (transactions polimórfica + TransactionUtil + Observer Financeiro) **continua canon**.

### Pendências (não bloqueiam aceite)

- ADR amendment `compras-modulo-greenfield-hibrido` ou nota explícita marking Wave 8 da proposta como cancelled
- Topnav `Modules/Compras/Resources/menus/topnav.php` adicionando `/purchases/create` (Cowork visual sidebar) — V2 polimento
- Pest test smoke convergência cross-modulo `tests/Feature/Purchase/Wave2CreateInertiaTest.php` (user vem de `/compras`) — V2
- Link "Ver tela cheia" no rodapé do drawer cockpit `/compras` apontando `/purchases/{id}?v=2` — V2

## Refs

- [Comparativo Compras × Sells](../../sessions/2026-05-25-como-integrar-c1-compras-converge-purchase.md) — audit introspectivo via skill `como-integrar`
- [Proposta `compras-modulo-greenfield-hibrido`](compras-modulo-greenfield-hibrido.md) — supersedes_partially (Wave 8 cancelled)
- [`memory/requisitos/Compras/SPEC.md`](../../requisitos/Compras/SPEC.md) — amendment §3 + US-COM-002 + US-COM-004 neste PR
- [`Pages/Compras/Index.charter.md`](../../../resources/js/Pages/Compras/Index.charter.md) — v1 → v2 amendment neste PR
- [`Pages/Purchase/Create.charter.md`](../../../resources/js/Pages/Purchase/Create.charter.md) — Tier A ★ intocado
- [`Pages/Purchase/Edit.charter.md`](../../../resources/js/Pages/Purchase/Edit.charter.md) — Tier A intocado
- `PurchaseController:400, 928` — dual-response `X-Inertia` header ou `?v=2` query
- [ADR 0141 skill `migracao-blade-react`](../0141-skill-migracao-blade-react.md) — piloto Wave 2 B5
- [ADR 0149 pattern reuse MWART](../0149-pattern-reuse-mwart-create-edit.md) — Edit reusa ~80% UI do Create
- [ADR 0093 multi-tenant Tier 0](../0093-multi-tenant-isolation-tier-0.md) — preservado intocado
- [ADR 0104 MWART canônico](../0104-processo-mwart-canonico-unico-caminho.md) — Purchase trilho A é caso canon
- [ADR 0107 visual gate F1.5](../0107-emendation-0104-visual-comparison-gate-f3.md) — Purchase/Create gate já passou; C1 não exige novo gate (entry-point externo só)
- [ADR 0114 prototipo-ui Cowork loop](../0114-prototipo-ui-cowork-loop-formalizado.md) — `/compras` cockpit é caso canon Cowork
