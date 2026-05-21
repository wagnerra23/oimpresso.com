---
slug: cliente-index-visual-comparison
title: "Cliente — Comparativo visual /cliente (Blade legacy → Inertia React + Cockpit V2)"
type: visual-comparison
module: Cliente
status: approved
approved_by: wagner
date: 2026-05-21
canon_reference: resources/views/contact/index.blade.php (Blade legacy — DataTable jQuery + 8 colunas)
inertia_target: resources/js/Pages/Cliente/Index.tsx (charter v2)
controller: app/Http/Controllers/ContactController.php::index()
stories: [US-CRM-071]
related_adrs: [0093, 0094, 0104, 0107, 0110, 0149]
---

# Visual Comparison — Cliente/Index (`/cliente`)

> **Tipo de tela:** lista paginada com KPIs + drawer detalhe (Cockpit Pattern V2)
> **Persona:** Larissa @ ROTA LIVRE revisando carteira de clientes (OS abertas/atrasadas)
> **Estado:** Cockpit V2 canonical (ADR 0110) — 4 KPIs + tabela 7 colunas + Sheet drawer 480px

## Contexto

Index migrado de Blade DataTable jQuery → Inertia React Cockpit V2 canon. PR #1309 (KB-9.75 Slice A) adicionou ⌘K palette + cheat-sheet + J/K navigation — primeira tela do oimpresso com command palette nativo.

## Matriz consolidada

| Item | Blade legacy | React (Cockpit V2 + KB-9.75) | Peso | Score |
|---|:-:|:-:|:-:|:-:|
| KPIs no topo | ausente | 4 KPI cards `Inertia::defer` | 4 | 10/10 |
| Status semântico (late/active/idle) | ausente | rose/sky/stone badges canon | 4 | 10/10 |
| Avatar quadrado canon | ausente (sem avatar) | rounded-md monocromático stone | 3 | 10/10 |
| Drawer lateral (Cockpit V2) | ausente (link → Show full page) | `<Sheet>` 480px com histórico OS | 5 | 10/10 |
| ⌘K command palette (KB-9.75) | ausente | palette canon + J/K nav | 4 | 10/10 |
| Cheat-sheet de atalhos | ausente | modal `?` | 2 | 10/10 |
| Filtros via URL sync | sessionStorage | URL + localStorage `oimpresso.cliente.*` | 3 | 10/10 |
| DataTable jQuery | presente | substituído por tabela Inertia | 3 | n/a |
| PII mascarada | inconsistente | sempre `maskTaxNumber` backend | 4 | 10/10 |
| Dark mode | inexistente | full coverage | 1 | 10/10 |

## 8 dimensões avaliadas (canon MWART)

### 1. Layout

Cockpit V2 (ADR 0110):
- Header: h1 "Clientes" + subtitle + botão "Novo cliente"
- 4 KPI cards horizontais (Total / Com OS aberta / Com atraso / Valor total em aberto)
- Tabela 7 colunas (Avatar+Nome+Doc / Contato+Tel / Total OS / OS abertas / Valor / Status / Última OS)
- Click linha → `<Sheet>` 480px direita (Cockpit V2 master-detail canon)
- Largura cabe em 1280px sem scroll horizontal

**Decisão MWART:** Cockpit V2 canon adotado integralmente.

### 2. Hierarquia visual

- h1 22-24px `font-semibold` (proibido `font-bold` — ADR 0110)
- KPI value 28px stone-900
- KPI label 12px stone-500
- Badge status 11px (rose/sky/stone)
- Row hover `bg-stone-50/60` (canon SaleSheet pattern)
- Row selected `bg-blue-50/60`

### 3. Densidade informacional

Tabela 50 linhas/página (paginated). 7 colunas em 1280px → cada cell tem espaço respirável mas zero gordura. Larissa enxerga ~15 clientes simultâneos no fold.

### 4. Multi-tenant Tier 0

- `ContactController::index()` aplica `Contact::query()` (global scope `business_id` automático)
- KPIs agregados também respeitam scope: `Contact::late()->count()`, etc
- `/cliente/{id}/sheet-data` re-valida ownership no drawer
- ADR 0093 obrigatório

**Decisão MWART:** ✅ aprovado (Tier 0 compliant).

### 5. Permissões

- `customer.view` (todos clientes do business) OU `customer.view_own` (somente clientes atribuídos ao user)
- Botão "Novo cliente" só visível se `customer.create`
- Drawer "Histórico OS" só se `job_sheet.view` (cross-permission)

### 6. Acessibilidade & Mobile

- `aria-label` em cada KPI card
- ⌘K palette: `role="dialog"` + `aria-modal="true"`
- Cheat-sheet: `role="dialog"`
- J/K nav highlights row com `aria-current="true"`
- Mobile ≤640px: KPIs colapsam 2x2, tabela vira card list (parcial — gap < 1100px)

**Decisão MWART:** aprovado com gap mobile < 1100px (US futura).

### 7. PII / LGPD handling

- CPF/CNPJ na coluna "Nome+Doc" sempre via `maskTaxNumber`
- Telefone exibido completo (Larissa precisa pra ligar)
- Email pode ser plain
- Activity log NÃO loga "viewed Index" (privacidade)
- Drawer "Histórico OS": valores agregados (sem PII de cada OS)

**Decisão MWART:** ✅ aprovado (LGPD compliant).

### 8. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading KPIs | 4 skeleton cards | `Inertia::defer` pendente |
| Tabela loading | Skeleton 5 linhas | render inicial |
| Empty (business sem clientes) | Pill stone + CTA "Cadastrar primeiro" | `contacts.count === 0` |
| ⌘K palette open | Modal centrado, backdrop blur | tecla ⌘K |
| Drawer loading | Skeleton 3 linhas em histórico | fetch `/cliente/{id}/sheet-data` |
| Cheat-sheet open | Modal `?` | tecla ? |
| Search vazio | "Nenhum cliente encontrado" + clear button | filter retornou 0 |

## Score consolidado

| Dimensão | Score | Peso | Contribuição |
|---|:-:|:-:|:-:|
| Layout | 10 | 15% | 1.50 |
| Hierarquia | 9 | 10% | 0.90 |
| Densidade | 9 | 10% | 0.90 |
| Multi-tenant Tier 0 | 10 | 20% | 2.00 |
| Permissões | 9 | 10% | 0.90 |
| Acessibilidade | 8 | 10% | 0.80 |
| PII/LGPD | 10 | 15% | 1.50 |
| Estados | 9 | 10% | 0.90 |

### **Nota final: 94.0 / 100** (excelente)

## Gaps remanescentes (backlog)

| US futura | Gap | Prioridade |
|---|---|---|
| (futura) | Mobile < 1100px refinement | P2 |
| (futura) | Bulk actions (selecionar múltiplos pra mesclar) | P2 |
| (futura) | Filtros avançados (drawer right "Filtros") | P3 |
| (futura) | Saved views (favoritar combinação de filtros) | P3 |

## Refs

- HANDOFF: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- ADR 0107 (gate F1.5)
- ADR 0110 (Cockpit V2)
- ADR 0149 (pattern reuse)
- Charter: [`resources/js/Pages/Cliente/Index.charter.md`](../../../resources/js/Pages/Cliente/Index.charter.md) (v2)
- PR #1309 — KB-9.75 Slice A (⌘K palette + cheat-sheet + J/K nav)
