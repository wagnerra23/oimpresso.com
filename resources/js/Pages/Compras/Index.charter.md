---
page: /compras
component: resources/js/Pages/Compras/Index.tsx
owner: wagner
status: draft
status_detail: scaffold
last_validated: "2026-05-25"
parent_module: Compras
states: [default, loading]  # gate L2 — dark/empty podados: baselines flaky (não-reproduzíveis run-a-run no CI) · sync com tests/Browser/visreg-states.json
parent_spec: memory/requisitos/Compras/SPEC.md
related_adrs: [93, 94, 101, 104, 107, 114, 141, 149]
related_us: [US-COM-001]
related_prototype: prototipo-ui/cowork/compras-page.jsx
tier: A
charter_version: 2
related_proposals:
  - "compras-purchase-convergencia-c1"
---

# Page Charter — /compras (cockpit · v2 C1 convergência)

> **Status v2 (2026-05-25):** mantém Wave 1+2+3+4 visual scaffold mas pivota Non-Goals + Anti-hooks via convergência C1 — cockpit `/compras` delega CRUD pra trilho A `Pages/Purchase/*` Inertia (Wave 2 B5 piloto MWART · ADR 0141). Botão "+ Nova compra" deixa de prometer Wave 8 com `/compras/create` e passa a `router.visit('/purchases/create')`. Ver ADR proposta [`compras-purchase-convergencia-c1`](../../../../memory/decisions/proposals/compras-purchase-convergencia-c1.md).
>
> **Status v1 original (2026-05-21):** F1 pin literal do protótipo Cowork canônico (`compras-page.jsx`).
>
> Persona: todo user com permission `compras.view` + `purchase.create/update` pros botões (C1 — sem alias `compras.create`). Larissa @ ROTA LIVRE biz=4 (1280px, vestuário, não-técnica) é a piloto de Wave 4.5+ (GradeMatrixInput).

---

## Mission (1 frase)

Servir como **cockpit operacional de Compras** entregando 4 KPIs (a pagar / em trânsito / volume mês / fornecedores) + lista paginada filtrável por estágio FSM + drawer detalhe, em ≤800ms numa shell Inertia React preservando 100% do visual Cowork canônico.

---

## Goals — Features (faz)

- AppShellV2 layout com breadcrumb único `Compras`
- 4 KPI cards (a pagar / em trânsito / volume do mês / fornecedores ativos) — agregados server-side scoped `business_id` via `ComprasService::calcularKpis`
- Tabela paginada (25 linhas/página) com colunas Compra / Fornecedor / Data / Estágio (pill colorida) / Itens / Total / A pagar / NF-e
- Filtros locais: all / abertas / rascunhos / em trânsito (state client-side; quando server filter for adicionado, vira query string)
- Search input header (placeholder Wave 5+ — sem ação MVP)
- Drawer simples ao clicar linha — mostra ref, fornecedor, data, status, total, due (sem 5 tabs detalhadas — Wave 6+)
- Botão "Nova compra" disabled (Wave 8 abre `/compras/create`)
- Botão "↓ Importar XML" disabled (Wave 6 abre modal DFe pendentes)
- Inertia::defer obrigatório em `kpis` e `rows` (pattern canônico — props caras default defer)
- CSS bundle `cowork-compras-bundle.css` aplicado INTEIRO (1ª aplicação Tier 0 — proibicoes.md)
- Permission gate `compras.view` — sem permission, retorna 403
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `session('user.business_id')` em todas queries

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test futuro.

- ❌ **NÃO renderiza DrawerView 5 tabs** (Resumo/Itens/Documentos/Pagamentos/Histórico) — backend `show()` ainda não existe. Wave 6+ habilitam
- ❌ **NÃO importa XML DF-e** — botão disabled. Bridge `ImportarDfeComoCompraService` vem na Wave 6
- ❌ **NÃO replica form Create no estilo Cowork** — botão "+ Nova compra" delega `/purchases/create` Inertia via `router.visit` (C1 · ADR `compras-purchase-convergencia-c1`). Criar `Pages/Compras/Create.tsx` só se review trigger ativar (vertical-específico vestuário Larissa)
- ❌ **NÃO renderiza GradeMatrixInput inline** — entrada matricial tam×cor entra em `Pages/Purchase/Create.tsx` ou no futuro `Pages/Compras/Create.tsx` vertical-específico (Wave 4.5+)
- ❌ **NÃO substitui `/purchases` legacy via 301** — C1 inverte direção: `/purchases` é canônico Inertia React (Wave 2 B5 piloto MWART), `/compras` é cockpit complementar. Coexistem
- ❌ **NÃO usa session storage** pra filtros — query string (`?stage=...&q=...`) anti-hook charter
- ❌ **NÃO mock data** — se backend retornar `null/[]`, UI mostra empty state real (não inventa números)

---

## UX targets (mensuráveis)

- **First-paint ≤ 800ms** (Inertia::defer skeleton inicial em ≤100ms, props completam em ≤800ms)
- **0 erros JS console** (Pest GUARD valida pós-merge via Chrome MCP `read_console_messages`)
- **Larissa entende KPIs em ≤ 5s** — cards com label PT-BR + tom semântico (warn=a pagar, ok=fornecedores)
- **Tabela 25 linhas paginada** sem scroll horizontal em 1280px (Larissa monitor)
- **Drawer abre ≤ 200ms** após click linha (state local)

---

## Anti-hooks (sinais de drift)

> Quando esta tela "ganhar" funcionalidade, suspeite — fica fácil escorregar pra F6 Hard sem ADR.

- ⚠️ Aparecer **modal "Importar XML" funcional** sem ADR Wave 6 promovida — drift, exige ImportarDfeComoCompraService completo
- ⚠️ Aparecer **GradeMatrixInput inline** no cockpit — drift, esse componente vive em `Pages/Purchase/Create.tsx` (C1) ou futuro `/compras/create` vertical-específico (US-COM-005 Wave 4.5)
- ⚠️ Aparecer **dependência nova** (AG Grid, Handsontable, etc) — drift custo bundle, viola arte 2026-05-21 (TanStack v8 headless é o caminho)
- ⚠️ Aparecer **botão "criar compra inline" que NÃO seja `router.visit('/purchases/create')`** — drift C1, exige novo ADR pra reverter convergência
- ⚠️ Aparecer **`Pages/Compras/Create.tsx` ou `Pages/Compras/Edit.tsx`** — drift C1 (review trigger #1 da ADR ativa antes)
- ⚠️ Aparecer **`<a href="/purchases/*">` ou `window.location.href='/purchases/*'`** em qualquer componente Compras — cai no Blade legacy sem header `X-Inertia`. Usar `router.visit(...)` Inertia
- ⚠️ Aparecer **session storage** para filtros — preferir query string (`?stage=...`)
- ⚠️ Aparecer **mock data** (`rand()`, fixtures hardcoded) em controller ou Page — banido por LICOES_F3_FINANCEIRO_REJEITADO M-AP-2

---

## Test plan (Pest GUARD)

Cobertos em `Modules/Compras/Tests/Feature/ComprasIndexTest.php`:

1. ✅ `rota /compras responde 200 com permission compras.view`
2. ✅ `index renderiza Inertia component Compras/Index`
3. ✅ `sem permission compras.view → 403`

Wave 7 adicionará:

4. `Multi-tenant — não vaza compras de outro business` (invariante ADR 0093)
5. `KPIs calculam correto — aberto/transito/mes/fornec batem queries diretas`
6. `Filtro stage=transito só retorna transactions.status='ordered'+'pending'`
7. `Inertia::defer renderiza skeleton + completa em <800ms` (smoke timing)

---

## Backlog (não no escopo Waves 1-5)

- ~~**US-COM-002 Wave 8** — Nova compra inline (`/compras/create` Inertia)~~ — **cancelada via C1** (delega `/purchases/create`)
- ~~**US-COM-004 Wave 8** — Deprecar `/purchases` legacy (301 + feature flag)~~ — **invertida via C1** (`/purchases` canon, `/compras` cockpit complementar)
- **US-COM-003 Wave 6** — Importar XML DF-e (modal + bridge `ImportarDfeComoCompraService`) — **mantida** (GAP NOVO único)
- **US-COM-005 Wave 4.5** — GradeMatrixInput.tsx vestuário Larissa biz=4 — vai pra `Pages/Purchase/Create.tsx` (C1) ou futuro `Pages/Compras/Create.tsx` SE review trigger ADR C1 ativar
- **Drawer 5 tabs** — Resumo/Itens/Documentos/Pagamentos/Histórico (Wave 6+ quando `show()` existir) — **mantido** (diferencial UX cockpit)
- **Link "Ver tela cheia" no drawer** apontando `/purchases/{id}?v=2` — V2 polimento C1
- **Server-side filter persistido** — hoje filter é client-side (sobre rows defer); migrar pra query string Wave 7

---

## Refs

- [memory/requisitos/Compras/SPEC.md](../../../../memory/requisitos/Compras/SPEC.md) — US-COM-001
- [memory/requisitos/Compras/RUNBOOK-compras-index.md](../../../../memory/requisitos/Compras/RUNBOOK-compras-index.md)
- [memory/requisitos/Compras/BRIEFING.md](../../../../memory/requisitos/Compras/BRIEFING.md)
- [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- [ADR proposta compras-modulo-greenfield-hibrido](../../../../memory/decisions/proposals/compras-modulo-greenfield-hibrido.md) — supersedes_partially por C1 (Wave 8 cancelled)
- [ADR proposta compras-purchase-convergencia-c1](../../../../memory/decisions/proposals/compras-purchase-convergencia-c1.md) — **C1 vigente nesta charter v2**
- [ADR 0141 skill migracao-blade-react](../../../../memory/decisions/0141-skill-migracao-blade-react.md) — piloto Wave 2 B5 do trilho A Purchase
- [Pages/Purchase/Create.charter.md](../Purchase/Create.charter.md) — Tier A ★ intocado por C1
- Pattern Soft wrapper precedente: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) + PR [#1297 Home](https://github.com/wagnerra23/oimpresso.com/pull/1297)
- Protótipo canon: `prototipo-ui/cowork/compras-page.{jsx,css}`
- `Modules/Compras/Http/Controllers/ComprasController.php` — Controller Wave 3
- `Modules/Compras/Services/ComprasService.php` — Service Wave 3
