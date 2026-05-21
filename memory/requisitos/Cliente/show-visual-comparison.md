---
slug: cliente-show-visual-comparison
title: "Cliente — Comparativo visual /cliente/{id} React vs /contacts/{id} Blade (Wave 5 paralela + Slice 3 bloco BR)"
type: visual-comparison
module: Cliente
status: approved
approved_by: wagner
date: 2026-05-21
canon_reference: resources/views/contact/show.blade.php (Blade legacy — 10 tabs + dropdown + Add Discount)
inertia_target: resources/js/Pages/Cliente/Show.tsx (charter v2 status live)
controller: app/Http/Controllers/ContactController.php::show($id)
stories: [US-CRM-063, US-CRM-064, US-CRM-065, US-CRM-066, US-CRM-067, US-CRM-073]
related_adrs: [0093, 0094, 0104, 0107, 0110, 0149]
---

# Visual Comparison — Cliente/Show (`/cliente/{id}` vs `/contacts/{id}` Blade legacy)

> **Tipo de tela:** detalhe de cliente (header + 4 stats + 4 tabs + dropdown + sidebar)
> **Persona:** Larissa @ ROTA LIVRE consultando histórico antes de aprovar venda a prazo
> **Estado pós-Wave:** ~85% paridade funcional (era ~40% pré-Wave US-CRM-063..067)

## Contexto

Wave 5 paralela 2026-05-21 (PR #1298) fechou paridade Show de 40%→85% via 5 sub-components em `_show/` (PaymentsTab + LedgerTab + SalesTab + DocumentsTab + ActionsMenu + AddDiscountModal). Slice 3 (PR #1316) adicionou bloco fiscal BR completo na sidebar.

## Matriz Header / topo

| Item | Blade | React (pós-Wave) | Peso | Nota |
|---|:-:|:-:|:-:|:-:|
| Selector contact picker (trocar sem voltar) | presente | ausente | 3 | 0/10 |
| Tipo (badge) | presente | presente | 1 | 10/10 |
| Endereço completo | presente | sidebar | 2 | 9/10 |
| Mobile/Celular | presente | sidebar | 2 | 10/10 |
| Add Discount (W-E US-067) | presente | modal | 4 | 10/10 |
| Botão Editar | presente | topo | 2 | 10/10 |
| Menu ações 8 itens (W-E US-067) | presente | dropdown | 5 | 9/10 |
| 4 StatCards | ausente | presente | 3 | 10/10 |
| Loading skeletons | ausente | Inertia::defer | 2 | 10/10 |
| Badge "Inativo" | ausente | presente | 1 | 10/10 |
| **Bloco Fiscal BR sidebar** (Slice 3) | ausente | CPF/CNPJ+IE+IM+Regime+CF+CI | 4 | 10/10 |

## Matriz Tabs

| Tab | Blade | React (pós-Wave) | Peso | Nota |
|---|:-:|:-:|:-:|:-:|
| Ledger (W-B US-064) | presente | range+formato+export | 5 | 9/10 |
| Vendas (W-C US-065) | DataTable | Inertia partial reload+filtros | 5 | 9/10 |
| Pagamentos (W-A US-063) | presente | self-fetch | 5 | 9/10 |
| Documents & Note (W-D US-066) | presente | upload+autosave | 4 | 9/10 |
| Atividades | presente | escopo futuro | 3 | 0/10 |
| Pessoas de contato | presente | escopo futuro | 3 | 0/10 |
| Assinaturas | presente | escopo futuro | 2 | 0/10 |
| Reward Points | presente | escopo futuro | 1 | 0/10 |

## 8 dimensões avaliadas (canon MWART)

### 1. Layout

Header 56px avatar + nome + doc mascarado + badge tipo. 4 StatCards horizontais (`Inertia::defer`). Content area com tabs `role="tab"` + tab panels. Sidebar direita 320px com Contato + **Bloco Fiscal BR** (Slice 3).

**Decisão MWART:** Layout Inertia adotado. Cockpit V2 canon (ADR 0110).

### 2. Hierarquia visual

- h1 nome cliente 22-24px `font-semibold`
- Badge tipo 11px (canon ADR 0110)
- StatCard value 28px (canon)
- Tab label 14px
- Sidebar label 12px stone-500, value 14px stone-900

### 3. Densidade informacional

Larissa monitor 1280×1024 — header + 4 stats + tab + sidebar tudo visível sem scroll horizontal. Tab content scrolla vertical isolado.

### 4. Multi-tenant Tier 0

- `Contact::where('business_id', session('business.id'))->findOrFail($id)` no `show()`
- Helper `buildClienteSalesPaginator($id, $filters)` re-aplica scope
- `/contacts/payments/{id}` JSON valida ownership backend
- Cross-tenant retorna 404 (anti-enumeração)
- ADR 0093 obrigatório

**Decisão MWART:** ✅ aprovado (Tier 0 compliant em todos os endpoints).

### 5. Permissões

- `customer.view` OU `customer.view_own` necessária
- Dropdown Ações filtra opções por permission (ex: "Excluir" só se `customer.delete`)
- "Add Discount" só se `customer.add_discount` (grant separado)

### 6. Acessibilidade & Mobile

- `role="tablist"` + `role="tab"` + `role="tabpanel"` corretos
- `aria-selected` sincroniza com state
- Keyboard nav: Tab / Shift+Tab entre tabs, Enter ativa
- Dropdown `role="menu"` + `aria-haspopup`
- Mobile ≤640px: stats colapsam pra 2x2, sidebar vira accordion (parcial — gap)

**Decisão MWART:** aprovado com gap mobile fine-tuning.

### 7. PII / LGPD handling

- `cpf_cnpj` e `ie_rg` **mascarados** via `maskTaxNumber` ANTES do Inertia props
- Sidebar exibe formato `123.***.***-95` (CPF) ou `12.345.***/***-95` (CNPJ)
- Backend tem método dedicado pra "revelar" se permission `customer.view_pii_full` (grant raro)
- Activity log NÃO loga "viewed" (privacidade — Charter Anti-hook)
- Export ledger gera PDF com PII completa (Larissa autorizada via permission)
- `bank_account_number` mascarado (últimos 4 dígitos)

**Decisão MWART:** ✅ aprovado (LGPD compliant).

### 8. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Header loading | Skeleton avatar+texto | Inertia render |
| Stats loading | 4 skeleton cards | `Inertia::defer` pendente |
| Tab Vendas empty | Pill stone "Nenhuma venda" | sales.data vazio |
| Tab Pagamentos empty | Pill "Sem pagamentos" | array vazio |
| Documents upload | Progress bar 0-100% | XHR multipart |
| Notes autosave | Pill amber "Salvando" → emerald "Salvo" | debounce 1500ms |
| Cross-tenant | 404 page | business_id mismatch |
| Erro stats defer | Toast rose + retry button | defer falhou |

## Score consolidado pós-Wave + Slice 3

| Dimensão | Score | Peso | Contribuição |
|---|:-:|:-:|:-:|
| Layout | 9 | 12% | 1.08 |
| Hierarquia | 9 | 10% | 0.90 |
| Densidade | 9 | 10% | 0.90 |
| Multi-tenant Tier 0 | 10 | 20% | 2.00 |
| Permissões | 9 | 10% | 0.90 |
| Acessibilidade | 8 | 8% | 0.64 |
| PII/LGPD | 10 | 15% | 1.50 |
| Estados | 9 | 8% | 0.72 |
| Tabs paridade | 6.2 | 7% | 0.43 |

### **Nota final pós-Wave + Slice 3: 90.7 / 100** (era 76.2 pré-Slice 3, era 38.7 pré-Wave)

## Gaps remanescentes (~10% restante)

| US futura | Gap | Prioridade |
|---|---|---|
| (futura) | Tab Atividades (activity log inline) | P1 |
| (futura) | Tab Pessoas de contato (sub-contatos) | P2 |
| (futura) | Tab Assinaturas (recorrência) | P3 |
| (futura) | Tab Reward Points (condicional rp_enabled) | P3 |
| (futura) | Contact picker header (trocar sem voltar) | P2 |
| (futura) | Ledger inline 100% (sem abrir Blade legacy ao filtrar) | P2 |
| (futura) | Mobile ≤640px refinement total | P2 |

## Refs

- HANDOFF: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- ADR 0107 (gate F1.5 visual-comparison)
- ADR 0110 (Cockpit V2)
- ADR 0149 (pattern reuse Crm)
- Charter: [`resources/js/Pages/Cliente/Show.charter.md`](../../../resources/js/Pages/Cliente/Show.charter.md) (v2 status live)
- PR #1298 — Wave 5 paralela US-CRM-063..067
- PR #1316 — Slice 3 bloco fiscal BR sidebar
- Coord: [`memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md`](../../sessions/2026-05-21-coord-cliente-show-paridade-5waves.md)
