---
page: /compras
component: resources/js/Pages/Compras/Index.tsx
owner: wagner
status: scaffold
last_validated: 2026-05-21
parent_module: Compras
parent_spec: memory/requisitos/Compras/SPEC.md
related_adrs: [0093, 0094, 0101, 0104, 0107, 0114]
related_us: [US-COM-001]
related_prototype: public/cowork-preview/erp-shell-v2/compras-page.jsx
tier: A
charter_version: 1
---

# Page Charter — /compras (cockpit)

> **Status:** Wave 1+2+3+4 scaffold (2026-05-21). F1 pin literal do protótipo Cowork canônico (`compras-page.jsx`).
> Persona: todo user com permission `compras.view`. Larissa @ ROTA LIVRE biz=4 (1280px, vestuário, não-técnica) é a piloto de Wave 4.5+ (GradeMatrixInput).

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
- ❌ **NÃO permite criar compra inline** — botão "+ Nova compra" disabled. Form completo Wave 8 (`/compras/create`)
- ❌ **NÃO renderiza GradeMatrixInput** — entrada matricial tam×cor vive em `/compras/create` (Wave 4.5+)
- ❌ **NÃO toca `/purchases` legacy** — deprecação só na Wave 8 via 301 + feature flag
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
- ⚠️ Aparecer **GradeMatrixInput inline** no cockpit — drift, esse componente vive em `/compras/create` (US-COM-005 Wave 4.5)
- ⚠️ Aparecer **dependência nova** (AG Grid, Handsontable, etc) — drift custo bundle, viola arte 2026-05-21 (TanStack v8 headless é o caminho)
- ⚠️ Aparecer **botão "criar compra inline"** sem Wave 8 — drift
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

- **US-COM-002 Wave 8** — Nova compra inline (`/compras/create` Inertia)
- **US-COM-003 Wave 6** — Importar XML DF-e (modal + bridge `ImportarDfeComoCompraService`)
- **US-COM-005 Wave 4.5** — GradeMatrixInput.tsx em `/compras/create` produtos variable
- **US-COM-004 Wave 8** — Deprecar `/purchases` legacy (301 + feature flag)
- **Drawer 5 tabs** — Resumo/Itens/Documentos/Pagamentos/Histórico (Wave 6+ quando `show()` existir)
- **Server-side filter persistido** — hoje filter é client-side (sobre rows defer); migrar pra query string Wave 7

---

## Refs

- [memory/requisitos/Compras/SPEC.md](../../../memory/requisitos/Compras/SPEC.md) — US-COM-001
- [memory/requisitos/Compras/RUNBOOK-compras-index.md](../../../memory/requisitos/Compras/RUNBOOK-compras-index.md)
- [memory/requisitos/Compras/BRIEFING.md](../../../memory/requisitos/Compras/BRIEFING.md)
- [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- [ADR proposta compras-modulo-greenfield-hibrido](../../../memory/decisions/proposals/compras-modulo-greenfield-hibrido.md)
- Pattern Soft wrapper precedente: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) + PR [#1297 Home](https://github.com/wagnerra23/oimpresso.com/pull/1297)
- Protótipo canon: `public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}`
- `Modules/Compras/Http/Controllers/ComprasController.php` — Controller Wave 3
- `Modules/Compras/Services/ComprasService.php` — Service Wave 3
