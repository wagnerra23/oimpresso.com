---
page: /contacts
component: resources/js/Pages/Crm/Contacts/Index.tsx
owner: wagner
status: draft
last_validated: 2026-05-14
parent_module: Crm
related_adrs: [0110, 0107, 0109, 0104, 0093, 0141]
tier: A
charter_version: 1
canary_clients: [martinho-caçambas-biz-164, rota-livre-biz-4]
---

# Page Charter — /contacts (Crm/Contacts/Index)

> **Status:** draft (US-CRM-CONT-001). Migração Blade legacy AdminLTE roxo (`contact/index.blade.php`) → Inertia React Cockpit V2.
> **Canary entrega:** Martinho Caçambas (biz=164) — Filha do Martinho (comercial) + Dani (financeiro). Champions duplos. ROTA LIVRE biz=4 valida pattern análogo.

---

## Mission

Listar contatos (clientes ou fornecedores) com busca instant, filtros por status, tabela densa Cockpit V2 e drawer detail ao clicar linha. Substitui Blade legacy DataTables + jQuery + Select2 — preservando endpoint AJAX como fallback condicional para canary gradual.

Pain-point #1 reunião Martinho 2026-05-13: "tempo pra abrir uma venda, ou prospecção". `/contacts` é onde prospecção começa — velocidade > tudo.

---

## Goals — Features (faz)

- AppShellV2 + persistent layout (Cockpit canon §1)
- Header inline: h1 "Clientes" / "Fornecedores" + subtitle + botão "Novo cliente" único
- 3 KPIs cards: Total / Ativos / Inativos (cheap COUNT queries)
- 3 filter pills `rounded-full + counter`: Todos / Ativos / Inativos
- Busca instant debounce 300ms — nome + CPF/CNPJ + telefone + email + supplier_business_name
- Tabela limpa 6 colunas: Nome (+ contact_id secundário) / CPF-CNPJ formatado / Telefone (+email) / Tipo (badge) / Status (badge) / Ações (DropdownMenu)
- Click linha abre drawer `ContactSheet` lateral direito (`<Sheet>` shadcn) — opcional V1, anchor para /contacts/{id} aceita
- Linha selecionada `bg-blue-50/60` (info active)
- Paginação canônica Cockpit V2 (per_page 10/25/50/100)
- Ordenação por colunas: nome, mobile, tax_number, created_at
- Endpoint REST canon: `GET /contacts/list-json?type=customer&q=&status=&page=&per_page=&sort=&dir=`
- Multi-tenant Tier 0: `business_id` global scope preservado via `ContactUtil::getContactQuery`
- Permissões: `customer.view`/`customer.view_own`/`supplier.view`/`supplier.view_own` (Spatie)
- Soft-delete via `DELETE /contacts/{id}` (mantém histórico)
- Toggle status active/inactive (preserva legacy `updateStatus`)
- CPF/CNPJ formatado no display via util `formatTaxNumber(raw)` (UI-only, banco mantém raw)

---

## Non-Goals — Features (NÃO faz)

- ❌ Import CSV/Excel (mantém Blade `/contacts/import` legacy — out of scope MVP)
- ❌ Ledger / extrato contábil (mantém Blade `/contacts/{id}?view=ledger`)
- ❌ Map view (mantém Blade `/contacts/map`)
- ❌ Reward points UI (preservado via flag `enable_rp`, mas não rerenderizado V1)
- ❌ Filtros avançados (has_sell_due, has_purchase_return, has_advance_balance, has_no_sell_from, customer_group) — backlog US-CRM-CONT-005
- ❌ Bulk actions (checkbox multi-select para enviar email/whatsapp bulk) — backlog US-CRM-CONT-006
- ❌ Real-time updates Centrifugo — não no MVP
- ❌ Deletar Blade legacy — F5 SUNSET acontece DEPOIS de 30 dias canary sem incidente
- ❌ Migrar `index()` controller por completo — fallback `view()` mantido enquanto canary roda

---

## UX Targets

- **p95 first-paint < 1500ms** (50 contacts + KPIs)
- **p95 busca debounce < 500ms** (digitar 3 chars → resposta)
- **p95 click "Novo cliente" → Create renderizado < 1000ms** (pain-point #1)
- **Drawer abre em < 300ms** após click
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC) — ADR 0101
- **Cabe em monitor 1280px** sem scroll horizontal (canary Filha Martinho + Dani + ROTA LIVRE Larissa)
- Tipografia canon ADR 0110: h1 22-24px font-semibold, pill 12px, badge 11px
- Cores semânticas Cockpit V2: rose/emerald/amber/blue (NUNCA cor crua)
- CPF formatado: "123.456.789-01" — CNPJ formatado: "12.345.678/0001-99"

---

## UX Anti-patterns

- ❌ Tabs `border-b-2 border-primary` em filter (canon = pills `rounded-full`)
- ❌ Modal/Dialog pra detalhe de linha (canon = Sheet lateral)
- ❌ KpiCard custom inline reusa pattern Sells V2
- ❌ Cor crua `bg-(gray|red|green)-N` (canon = rose/emerald/amber/blue semântico)
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.crm.contacts.*`)
- ❌ `format_date(now())` em campo "agora" (shift +3h legacy — ADR 0066)
- ❌ Render `dangerouslySetInnerHTML` para nome/email/mobile (XSS)
- ❌ Logging de CPF/CNPJ em console.log ou Pest fixture (LGPD)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/contacts?type=customer` (X-Inertia) | Inertia render Crm/Contacts/Index com `{type, kpis, permissions}` |
| GET | `/contacts?type=customer` (ajax) | DataTables legacy (preservado para canary) |
| GET | `/contacts/list-json?type=&q=&status=&page=&per_page=&sort=&dir=` | rows[] + meta paginação Laravel |
| DELETE | `/contacts/{id}` | `{success: true/false, msg: string}` (existente) |
| GET | `/contacts/update-status/{id}` | toggle active/inactive (existente) |

---

## Tests anti-regressão

- [tests/Feature/Crm/ContactsInertiaTest.php](../../../../tests/Feature/Crm/ContactsInertiaTest.php) — Pest cross-tenant biz=1 vs biz=99, Inertia render, permissões, paginação

---

## Refs

- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 Processo MWART canônico](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0141 Skill migracao-blade-react](../../../../memory/decisions/0141-skill-migracao-blade-react.md)
- [RUNBOOK-contacts.md](../../../../memory/requisitos/Crm/RUNBOOK-contacts.md)
- Padrão referência: [Pages/Sells/Index.tsx](../../Sells/Index.tsx)
