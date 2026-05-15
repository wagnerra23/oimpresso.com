---
page: /sells/{id}/edit
component: resources/js/Pages/Sells/Edit.tsx
owner: wagner
status: proposed
last_validated: 2026-05-14
parent_module: Sells
related_adrs: [0104, 0107, 0110, 0093, 0143]
tier: A
charter_version: 1
---

# Page Charter — /sells/{id}/edit

> **Status:** proposed (US-SELL-EDIT-001 inicial, canary Martinho biz=164 semana 19/maio). Espelha pattern Sells/Create.tsx mas pra UPDATE — Lara (estoque) + Dani (financeiro · DANIELLI id=297) editam vendas históricas no canary semana 19/maio.

---

## Mission

Editar venda existente (cliente / produtos / pagamentos / frete / impostos / status) numa tela única longa, com pre-fill dos valores atuais, navegação rápida via filter pills, ações sempre acessíveis no footer sticky, e capacidade de cancelar venda quando necessário — substitui `sell.edit.blade.php` legacy (898 LOC + pos.js 3178 LOC jQuery).

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb invisível (page header inline)
- Header sticky no topo: h1 "Editar venda #{invoice_no}" + subtitle com nome cliente + 5 filter pills `rounded-full` (Dados / Produtos / Pagamento / Resumo / Mais opções)
- Pills com ícones lucide (FileText / Package / CreditCard / Receipt / Settings2) + counter Produtos quando > 0
- Scroll-spy via IntersectionObserver marca pill ativa enquanto Lara/Dani rolam o form
- 4 KPIs grandes (Itens / Total venda / Pago / Status pgto) com tone semântico rose/emerald/amber/blue
- Pre-fill 100% dos campos a partir de `props.transaction` (8 sempre visíveis: Cliente, Data, Status, Nº fatura, Produtos, Pagamentos, Desconto, Notas)
- 10 campos colapsáveis em `<details>` "Mais opções" com persist localStorage `oimpresso.sells.edit.advanced.open`
- ProductSearchAutocomplete + tabela editável produtos preservando `transaction_sell_lines_id` original (UPDATE vs INSERT pra novos)
- PaymentRow split de pagamento + indicador saldo (falta/troco/exato)
- Footer sticky no bottom: Voltar + Cancelar venda (destacado rose) + Salvar e imprimir + Salvar alterações
- Atalho `Ctrl/Cmd+S` salva (Lara/Dani vêm de Word/Excel — pattern aprendido) + `Cmd+Enter` também salva (consistência Create)
- Atalho `Esc` faz blur do input ativo
- `<FieldError>` inline por campo (`role="alert"`) + auto-open `<details>` quando erro está em campo colapsado
- **Cancelar venda** via botão destacado (canon FSM `ExecuteStageActionService` action `cancelar_venda` ADR 0143) — modal de confirm com explicação clara: estoque é liberado, NFe precisa de cancelamento fiscal separado, registra auditoria
- **Read-only mode** quando `status === 'cancelled'` — todos inputs `disabled`, badge rose "Venda cancelada — somente leitura" no header
- Permissões respeitadas via `props.permissions`: `editPrice`, `editDiscount`, `canCancel`, `maxDiscount`

---

## Non-Goals — Features (NÃO faz)

- ❌ Edit POS rápido (vai pra `/pos/{id}/edit` — SellPosController@edit, outra tela)
- ❌ Edit de NFe associada (vai por fluxo `/nfe/{id}` separado — cancelamento SEFAZ é hard delete proibido ADR canon)
- ❌ Adicionar pagamento pós-fato isoladamente (rota `/sells/{id}/quick-payment` existente continua)
- ❌ Print direto (rota separada `/sells/{id}/print` Blade legacy)
- ❌ Mover/clonar venda pra outra location (não tem caso real reportado)
- ❌ Customização campos `custom_field_1..4` (legado Blade — abrir US separado quando Lara reportar)
- ❌ Module `subscription` recurring invoice (overlap com `Modules/RecurringBilling` — ignorar até user reportar)
- ❌ Module `tables` / `service_staff` (UltimatePOS restaurant — biz=164 não usa)
- ❌ Reward points redeem (`enable_rp`) — biz=164 não usa
- ❌ Multi-location move (UPDATE com location_id diferente — gera reservas estoque complexas, fora escopo Edit canon)

---

## UX Targets

- p95 first-paint < 1200ms (igual Create)
- Save click → response < 800ms
- Cabe em monitor 1280px sem scroll horizontal (Lara/Dani)
- Edit venda em ≤10s do clique edit na lista (pain #1 reunião Martinho 14/maio)
- 0 erros JS console
- Footer sticky permanece visível durante scroll do form longo
- Pill ativa muda ao rolar pra outra seção (scroll-spy IntersectionObserver)
- Tipografia canon: h1 24px, pill 12px, KPI value 36px
- Confirm modal cancelamento sem dependências externas (HTML role="dialog" + Card simples)

---

## UX Anti-patterns

- ❌ Permitir UPDATE em campo `transaction_id` ou `business_id` (Tier 0 IRREVOGÁVEL ADR 0093)
- ❌ Botão "Excluir venda" — hard delete proibido; só cancelar (status='cancelled')
- ❌ Auto-save draft em localStorage (diferente de Create — Edit não recupera de F5; usa DB como source of truth)
- ❌ Esconder botão Cancelar quando user tem permissão (deve aparecer cinza/disabled se já cancelada)
- ❌ Cor crua `bg-(gray|red|...)-N` — usar tokens semânticos
- ❌ `font-bold` em h1
- ❌ `sessionStorage` em vez de localStorage prefixed
- ❌ Object.entries direto em props UltimatePOS forDropdowns (use helper `dropdownEntries()`)
- ❌ Esquecer de preservar `transaction_sell_lines_id` em items existentes (gera duplicação na UPDATE)
- ❌ Mostrar conteúdo Blade legacy "Vendas relacionadas" sem props correspondente

---

## Tests anti-regressão

- [tests/Feature/Sells/SellsEditInertiaTest.php](../../../tests/Feature/Sells/SellsEditInertiaTest.php) — dual response + pre-fill + Tier 0 cross-tenant + cancelar venda
- [tests/Feature/Sells/SellPosControllerCreateTest.php](../../../tests/Feature/Sells/SellPosControllerCreateTest.php) — modelo de teste dual response (irmão)
- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico

---

## Refs

- [ADR 0104 — Processo MWART canônico](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0110 — Cockpit Pattern V2](../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0143 — FSM Pipeline LIVE prod biz=1](../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — `cancelar_venda` é stage action canônica
- [RUNBOOK Sells/edit](../../../memory/requisitos/Sells/RUNBOOK-edit.md)
- [Pages/Sells/Create.tsx](Create.tsx) — pattern Sells form canônico (espelhar)
- [Pages/Sells/Create.charter.md](Create.charter.md) — charter irmão
- [Pages/Crm/Contacts/Edit.tsx](../Crm/Contacts/Edit.tsx) — pattern Edit reusa Create (referência)
- [resources/views/sell/edit.blade.php](../../../resources/views/sell/edit.blade.php) — Blade legacy preservado (DUAL-MODE)
