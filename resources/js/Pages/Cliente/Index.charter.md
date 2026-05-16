---
page: /cliente
component: resources/js/Pages/Cliente/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-09"
parent_module: Cliente
related_adrs: ["0110-cockpit-pattern-v2-canon-list-detail", "0107-emendation-0104-visual-comparison-gate-f3", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
tier: A
charter_version: 1
---

# Page Charter — /cliente (DRAFT)

> **Status:** draft criado em batch 2026-05-09 a partir de [`clientes-page.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/clientes-page.jsx) ([UI Kit cowork-2026-05-09](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/README.md), [ADR ui/0012](../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)). Wagner aprova **Non-Goals + Automation Anti-hooks** (anti-alucinação) ANTES de virar `status: live`.
>
> Backend canon: `app/Http/Controllers/ContactController.php` (UPOS herdado). Cliente = `App\Contact` com `type: customer` (também serve `supplier`).

---

## Mission

Listar clientes do business com KPIs de relacionamento (OS abertas/atrasadas/valor total) e drawer lateral pra detalhe + histórico de OS — substitui a tela Blade legacy `customer.index.blade.php` preservando Cockpit V2 pattern.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb (Cockpit canon)
- `<PageHeader>` shared: h1 "Clientes" + subtitle + botão "Novo cliente" (rota Blade legacy `/contacts/create`)
- 4 KPI cards: Total clientes / Com OS aberta / Com atraso / Valor total em aberto
- Tabela 7 colunas: Avatar+Nome+CNPJ/CPF / Contato+Telefone / Total OS / OS abertas / Valor total / Status / Última OS
- Avatar quadrado `rounded-md` com letra/glyph monocromático (NÃO emoji-style)
- Status semântico (calculado server-side):
  - `late` (rose) — tem OS atrasada
  - `active` (blue/sky) — tem OS aberta sem atraso
  - `idle` (stone) — sem OS aberta
- Click linha abre `<Sheet>` lateral direito 480px com:
  - 4 KPIs do cliente: total OS / em aberto / atrasadas / valor
  - Section "Contato" — nome, telefone, CNPJ/CPF, última OS
  - Section "Histórico de OS" — lista cronológica (mais recente primeiro) com estágio/prazo/valor
- Filtros opcionais: busca por nome/CNPJ/telefone (URL sync)
- Persistência filtros + drawer aberto em localStorage prefix `oimpresso.cliente.*`
- Multi-tenant: `App\Contact` filtrado por `business_id` global scope ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Permission gate: `customer.view`, `customer.view_own` (Spatie UPOS canon)

---

## Non-Goals — Features (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova esta lista. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ CRUD inline (criar/editar via rotas dedicadas Blade `/contacts/create`, `/contacts/{id}/edit`)
- ❌ Bulk actions (selecionar múltiplos pra deletar/mesclar) — fica em backlog
- ❌ Mesclar duplicados (rota Blade dedicada `/contacts/duplicates`)
- ❌ Histórico financeiro detalhado nesta tela (vai pra `/financeiro/extrato/cliente/{id}` — feature futura)
- ❌ Enviar WhatsApp/email do drawer (vai pra Modules/Whatsapp dedicado)
- ❌ Auto-cadastro via NFC-e (vem do fluxo Sells, não desta tela)
- ❌ Importar CSV/Excel direto (rota Blade legacy `/contacts/import`)
- ❌ Categorização customizada (UPOS tem `customer_group` — não duplicar)
- ❌ Mostrar saldo a receber em tempo real do drawer (custo agregação alta — usar valor cached)
- ❌ Edição inline de telefone/email (drawer é read-only)

---

## UX Targets

- p95 first-paint < 1200ms (KPIs + 50 linhas)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- Drawer abre em < 300ms após click (1 fetch JSON pra `histórico de OS`)
- Tipografia canon ADR 0110: h1 22-24px, badge 11px, KPI value 28px
- Cores semânticas Cockpit V2: rose/emerald/amber/blue/stone (NÃO cor crua `bg-(red|green)-N`)
- Avatar quadrado 32px `rounded-md` com gradient stone neutro (não policromático)
- Tabela: linha selecionada `bg-blue-50/60` (igual SaleSheet pattern)

---

## UX Anti-patterns

- ❌ Tabs `border-b-2` em filter (canon = pills `rounded-full` quando tiver filtros futuros)
- ❌ Modal pra detalhe (canon = `<Sheet>` lateral)
- ❌ Cor crua `bg-(red|green|blue)-N` (canon = semântico rose/emerald/sky)
- ❌ Avatar circular emoji-style (canon = quadrado `rounded-md` com letra monocromática)
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` prefixed `oimpresso.cliente.*`)
- ❌ Mostrar PII completa (CPF/CNPJ formatado por máscara, não plain text em log)

---

## Automation Hooks

- Endpoint `GET /cliente` — `ContactController::index()` retorna lista paginada (limit 50) + KPIs agregados
- Endpoint `GET /cliente/{id}/sheet-data` — drawer detail com `App\Contact` + agregação de OS via join `App\Repair\JobSheet` (ou equivalente)
- Multi-tenant: `App\Contact` usa `business_id` global scope (UPOS canon)
- Permission middleware: `can:customer.view` no `__construct`

---

## Automation Anti-hooks

> ⚠️ O que essa tela NUNCA dispara. Wagner aprova esta lista. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara SMS/WhatsApp
- ❌ Não escreve no banco no render (read-only puro)
- ❌ Não roda jobs em fila ao abrir
- ❌ Não chama Brain B/Sonnet (sem IA nesta tela)
- ❌ Não acessa Contact de outro `business_id` (multi-tenant Tier 0)
- ❌ Não logga PII em plain text (sanitizer obrigatório se houver audit log)
- ❌ Não dispara verificação Receita Federal CNPJ ao abrir (cron separado)
- ❌ Não persiste credencial de gateway no client (token vive backend)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// tests/Feature/Cliente/ClienteIndexCharterTest.php

it('renders under 1200ms p95 with 50 customers')
it('does not emit emails on render')
it('does not dispatch jobs on render')
it('does not mutate state on GET')
it('isolates customers by business_id')
it('returns 404 for cross-tenant customer access via sheet-data')
it('renders at 1280px without horizontal scroll')
it('formats CPF/CNPJ with mask, not plain digits')
it('uses localStorage prefix oimpresso.cliente.* (never sessionStorage)')
it('classifies status correctly: late > active > idle')
it('shows empty state when no customers in business')
```

---

## Comparáveis canônicos (`mwart-comparative` V4)

- **Attio** (CRM enxuto, densidade media) — referência principal
- **Linear** (lista densa Larissa) — referência drawer + atalhos
- **Excluir:** Salesforce/HubSpot (overhead enterprise), Pipedrive (sales-first vs nosso CRM-light), Notion (densidade insuficiente)

---

## Refs

- Material visual: [`ui_kits/cowork-2026-05-09/clientes-page.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/clientes-page.jsx) (10 KB) + [`data-clientes.jsx`](../../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/data-clientes.jsx)
- Canon visual: [ADR ui/0012](../../../memory/requisitos/_DesignSystem/adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md)
- [ADR 0110 — Cockpit Pattern V2](../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0107 — Visual gate F1.5](../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição V2](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §6 multi-tenant
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight obrigatório antes de F3
- Backend: `app/Http/Controllers/ContactController.php` (UPOS canon — Page nova consome controller existente)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [CL] | Charter draft criado em batch (sessão F0 batch — 4 telas com material no canon 2026-05-09). Convenção PT-BR `Pages/Cliente/Index.tsx` (futura) seguindo padrão Pages/Financeiro/, Pages/Repair/. Wagner pode override pra `Pages/Contact/` se preferir UPOS-aligned EN. **Pendente:** aprovação Wagner em Non-Goals + Anti-hooks pra `status: live`. |
