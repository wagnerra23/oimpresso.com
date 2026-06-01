---
page: /sells/create
component: resources/js/Pages/Sells/Create.tsx
owner: wagner
status: live
last_validated: 2026-06-01
parent_module: Sells
related_adrs: [0110, 0107, 0104, 0093]
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
- Header sticky no topo: h1 "Adicionar venda" + subtitle + 6 filter pills `rounded-full` (Dados / Produtos / Entrega / Pagamento / Resumo / Mais opções)
- Pills com ícones lucide (FileText / Package / Truck / CreditCard / Receipt / Settings2) + counter Produtos quando > 0
- Click pill faz `scrollToSection(id)` smooth scroll + scroll-spy via IntersectionObserver marca pill ativa
- 4 KPIs gigantes (Itens / Total venda / Pago / Status pgto) com tone semântico rose/emerald/amber
- 8 campos sempre visíveis: Cliente / Data / Status / Local + Produtos + Pagamentos + Desconto + Notas
- Campos avançados colapsáveis em `<details>` "Mais opções" (fatura / nº fatura / imposto / prazo / comissão / grupo de preço) com persist localStorage `oimpresso.sells.create.advanced.open`
- **Seção Entrega de 1ª classe** (`sec-entrega`, US-CRM-078 PR2 · ADR 0093): toggle Retirada/Entrega; seletor lê o catálogo `contact.addresses[]` do cliente — **Destinatário** (cadastro `is_default`) ↔ **Local de entrega** (`is_shipping`) — + opção "Outro endereço" avulso estruturado (CEP/logradouro/nº/compl/bairro/cidade/UF); hint de **MDF-e** quando a cidade de entrega ≠ cidade da loja. Persiste one-line em `shipping_address` (compat). Gatilho fiscal real (`<entrega>`/cMun via `city_code`) fica no `NfeService` — **PR3** follow-up.
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

- [tests/Feature/Sells/SellsCreatePageTest.php](../../tests/Feature/Sells/SellsCreatePageTest.php) — 39+ testes estruturais
- [tests/Feature/Sells/SellPosControllerCreateTest.php](../../tests/Feature/Sells/SellPosControllerCreateTest.php) — backend dual response
- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- PR #257, #258, #259, #261 — sequência migração visual
