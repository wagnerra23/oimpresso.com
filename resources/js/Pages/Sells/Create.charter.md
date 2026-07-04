---
page: /sells/create
component: resources/js/Pages/Sells/Create.tsx
related_us: [US-SELL-001, US-SELL-003, US-SELL-004, US-SELL-005, US-SELL-006, US-SELL-007, US-SELL-053]
owner: wagner
status: live
last_validated: "2026-06-02"
parent_module: Sells
related_adrs: [110, 107, 104, 93, 105]
tier: A
charter_version: 2
---

# Page Charter — /sells/create

> **Status:** live (US-SELL-001 a US-SELL-008 mergeados). Form pattern canon do **Cockpit Pattern V2** ADR 0110 — sticky header com filter pills + sticky footer com ações.

---

## Mission

Cadastrar venda completa (cliente + produtos + pagamento + frete + impostos) numa tela única longa, com navegação rápida via filter pills entre seções e ações sempre acessíveis no footer sticky — substitui `sell.create.blade.php` legacy.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb
- Header sticky no topo: h1 "Adicionar venda" + subtitle + 5 filter pills `rounded-full` (Dados / Produtos / Pagamento / Resumo / Mais opções)
- Pills com ícones lucide (FileText / Package / CreditCard / Receipt / Settings2) + counter Produtos quando > 0
- Click pill faz `scrollToSection(id)` smooth scroll + scroll-spy via IntersectionObserver marca pill ativa
- 4 KPIs gigantes (Itens / Total venda / Pago / Status pgto) com tone semântico rose/emerald/amber
- 8 campos sempre visíveis: Cliente / Data / Status / Local + Produtos + Pagamentos + Desconto + Notas
- 10 campos colapsáveis em `<details>` "Mais opções" com persist localStorage `oimpresso.sells.create.advanced.open`
- ProductSearchAutocomplete (debounce + min query) + tabela editável produtos (5 cols)
- PaymentRow split de pagamento + indicador saldo (falta/troco/exato)
- Footer sticky no bottom: Cancelar + Salvar venda (sempre visíveis em form longo)
- Atalho Cmd+Enter / Ctrl+Enter submete (US-SELL-007)
- Atalho Esc faz blur do input ativo — autocompletes têm Esc próprio (US-SELL-007)
- Auto-save draft localStorage `oimpresso.sells.create.draft.{biz}.{user}` debounced 500ms + recover ao montar com confirm + TTL 24h + clear no onSuccess (US-SELL-007 — Larissa atende telefone no meio)
- `<FieldError>` inline por campo (`role="alert"`) + auto-open `<details>` "Mais opções" quando erro está em campo colapsado (US-SELL-010 — gap UX detectado pelo design-arte 2026-05-13)

---

## Non-Goals — Features (NÃO faz)

- ❌ POS rápido (vai pra `/sale-pos/create`)
- ❌ Cotação (vai pra `/sells/quotation/create` — FSM stage `quote_draft`/`quote_sent` via `InitialStageResolver`)
- ❌ NFC-e auto (vai por flag `NFEBRASIL_AUTO_EMISSION_NFCE` — backend handler `EmitirNfceAoFinalizarVenda` listener)
- ❌ Print direto (rota separada Blade `/sells/{id}/print`)
- ❌ Tabs com troca de conteúdo (canon = pills + scroll-spy)

---

## UX Targets

- p95 first-paint < 1200ms
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (ROTA LIVRE)
- Save click → response < 800ms
- Footer sticky permanece visível durante scroll do form longo
- Pill ativa muda ao rolar pra outra seção (scroll-spy IntersectionObserver)
- Tipografia canon: h1 24px, pill 12px, KPI value 36px

---

## UX Anti-patterns

- ❌ Tabs `border-b-2` em vez de pills (testado anti-regressão)
- ❌ Botões Cancelar/Salvar duplicados (canon = 1x no footer)
- ❌ KPIs custom inline (canon = pattern V2 grandes 4-col)
- ❌ Cor crua `bg-(gray|red|...)-N`
- ❌ `font-bold` em h1
- ❌ `sessionStorage` em vez de localStorage prefixed
- ❌ `Object.entries` direto em props UltimatePOS forDropdowns (use helper `dropdownEntries()` — auto-mem GOTCHAS)

---

## Tests anti-regressão

**Casos de uso (spec executável):** [`memory/requisitos/Sells/CASOS-USO-CREATE-VENDA.md`](../../../../memory/requisitos/Sells/CASOS-USO-CREATE-VENDA.md) — 15 CU em Given/When/Then + paridade Blade↔V2 + mapa CU→Pest. **Regra de cutover:** religar a flag `useV2SellsCreate` só quando todo CU `must` estiver 🟢 + smoke biz=4 (PRE-MERGE-UI Camada 4).

- [tests/Feature/Sells/SellsCreatePageTest.php](../../../../tests/Feature/Sells/SellsCreatePageTest.php) — ~49 testes estruturais
- [tests/Feature/Sells/SellPosControllerCreateTest.php](../../../../tests/Feature/Sells/SellPosControllerCreateTest.php) — backend dual response
- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico
- + suites: CustomerAutoApplyOnSelect · QuickAddCustomerSheet · ProductSearchAutocomplete(Race/ConfigurableFields) · ProductLineCardComponent · CommissionSplitEditor · CriarOsPorVenda · MultiTenantSqlGuard

---

## Refs

- [DESIGN.md §16 Cockpit V2](../../../../DESIGN.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- PR #257, #258, #259, #261 — sequência migração visual

## UCs cobertos (PRECISA TER · rastreável · §10.4 [CC])

> Cada item vem de um Caso de Uso ("A tela precisa:") amarrado a um GUARD Pest `uc-<id>`
> (`tests/Feature/Guards/UcGuardsTest.php`) via [`prototipo-ui/audit/uc-registry.json`](../../../../prototipo-ui/audit/uc-registry.json).
> ✅ = elemento presente + travado (some o elemento = build vermelho). 🟡 = gap conhecido (acende no `protocol_freshness`, advisory).

- ✅ **UC-V01** (`uc-v01`) — busca rápida de cliente/produto, tabela de preços automática, condições (prazo/pagamento/endereço), rascunho × pedido confirmado.
- ✅ **UC-V02** (`uc-v02`) — busca de produto com estoque/prazo visível, desconto com limite, observação por item.
- ✅ **UC-V03** (`uc-v03`) — seletor de tabela de preços, desconto com limite visível, aprovação de desconto.
- 🟡 **UC-V04** — botão de envio com registro de canal+data, estado "Aguardando aprovação", registro da aprovação, histórico de versões. _(sem cobertura)_
- 🟡 **UC-R01** — busca de kits distinta, expansão dos filhos, subtotal do kit, substituir filho, reserva de estoque dos filhos. _(sem cobertura)_
- 🟡 **UC-C01** — formulário de item personalizado (dimensão/material/acabamento/arte), preço em tempo real, observações de produção. _(sem cobertura)_
