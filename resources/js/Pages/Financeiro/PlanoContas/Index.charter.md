---
page: /financeiro/plano-contas
component: resources/js/Pages/Financeiro/PlanoContas/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Financeiro
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /financeiro/plano-contas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Financeiro/Http/Controllers/PlanoContaController@index` (rota `financeiro.plano-contas.index`, grupo `web/auth/language/timezone/AdminSidebarMenu`). Lista hierárquica do plano contábil BR (Receita Federal/DCASP). Persona: Eliana [E].

---

## Mission
Visão da estrutura contábil BR (plano de contas hierárquico, ~47 entries seedadas por business) pra a operação financeira classificar lançamentos corretamente. Tabela indentada por nível (código → conta → tipo → natureza), com filtro por tipo e busca. É tela de CONSULTA de cadastro contábil — não movimenta valor.

---

## Goals — Features (faz)
- Lista contas do plano em tabela (`<table>`) ordenadas por código, indentadas por `nivel`.
- Mostra código (mono), nome, tipo (badge colorido por natureza), natureza débito/crédito, se aceita lançamento e se é protegida (cadeado).
- KPI strip `FinStatStrip`: total de contas + contagem por tipo (receita/despesa/ativo/passivo+patrim.).
- Filtro por tipo (radiogroup client-side) + busca por código/nome (`useMemo` client-side).
- Empty state com instrução de seed quando o business ainda não tem plano.
- Header canon `<PageHeader>` v3.8 + `FinanceiroSubNav` + primary "Nova conta" → `/financeiro/plano-contas/create`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera cálculo de valor, saldo ou estoque — é cadastro/estrutura contábil, sem efeito financeiro.
- ❌ NÃO cria/edita/exclui conta nesta Page (index é read-only; o Controller só tem `index`; "Nova conta" navega pra rota `create` não coberta por este charter).
- ❌ NÃO lança nem baixa títulos.
- ❌ NÃO cruza dados entre businesses — query filtra por `business_id` (session `user.business_id`), plano é seedado por tenant; nunca cross-tenant.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; densidade alta (persona Eliana).

---

## Automation hooks (faz)
- Filtro/busca 100% client-side (`useMemo`) — sem round-trip ao servidor.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO seeda o plano automaticamente — empty state instrui o seed manual via artisan/SSH.
- ❌ NÃO edita contas protegidas (cadeado é sinalização; edição não ocorre nesta tela).
- ❌ NÃO dispara job/notificação.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar existência/escopo da rota `/financeiro/plano-contas/create` (primary aponta pra ela; não achei no Controller lido)
