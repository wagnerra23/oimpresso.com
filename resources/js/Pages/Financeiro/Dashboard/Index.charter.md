---
page: /financeiro
component: resources/js/Pages/Financeiro/Dashboard/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Financeiro
related_us: [US-FIN-013]
related_adrs: [114, 101, 93, 189, 190]
tier: C
charter_version: 1
---

# Page Charter — /financeiro (Dashboard) (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> **DORMENTE (não é capacidade viva):** desde 2026-06-06 (Wagner "não vou usar o dashboard") a rota `/financeiro` faz **redirect 301 pra `/financeiro/unificado`**; `/financeiro/dashboard` também. `DashboardController@index` + esta Page ficam DORMENTES/reversíveis (não deletados), sem rota que os renderize hoje. Por isso `tier: C`.
>
> Backend: `Modules/Financeiro/Http/Controllers/DashboardController@index` (permissão `financeiro.dashboard.view`). Visão geral read-only de contas a pagar/receber (US-FIN-013).

---

## Mission
Painel de visão geral do Financeiro: 4 KPIs clicáveis (A receber / A pagar / Recebidos no mês / Pagos no mês), saldo em bancos e tabela única de títulos com filtros bookmarkable. É tela de LEITURA/consolidação — mostra a posição financeira, não movimenta dinheiro. Hoje dormente (substituída pela Visão Unificada como landing).

---

## Goals — Features (faz)
- Exibe 4 KPI cards `fin-stats` clicáveis que aplicam filtro (A receber/A pagar aberto, Recebidos/Pagos no mês) com contagem de vencidos.
- Mostra saldo em bancos (cards por `ContaBancaria` + total), com badge Virtual PJ/Corrente e "Boleto ativo".
- Tabela paginada (25/pág) de títulos: número, cliente/fornecedor, tipo, status (`StatusBadge`), vencimento + aging bucket, valor total e saldo.
- Filtros de tipo/status/busca via partial reload (`only: ['titulos','filters']`), URL-state bookmarkable.
- KPIs, títulos, contas e saldo_total são `Inertia::defer` (skeletons no primeiro paint).
- Header canon `<PageHeader>` v3.8 com primary honesto "Novo título" → `/financeiro/unificado/novo`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera cálculo, valor, saldo, baixa ou estoque — é VISÃO read-only; toda mutação (baixar título, criar) acontece em outras telas/rotas.
- ❌ NÃO cria/edita/baixa títulos aqui (KPIs e linhas só levam a filtros/navegação).
- ❌ NÃO cruza dados entre businesses — todas as queries filtram por `business_id` (session `user.business_id`); nunca cross-tenant.
- ❌ NÃO é a landing atual do módulo (está atrás de 301 → `/unificado`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; first-paint via defer (~50ms medido).

---

## Automation hooks (faz)
- `Inertia::defer` em kpis/titulos/contas/saldo_total — closures só rodam quando o partial reload as pede.
- KPI cache 5 min invalidado em TituloBaixado/Criado/Cancelado (comentário do Controller, UI-0002).
- Spans OTel (`financeiro.dashboard.*`) por payload.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO faz polling/refresh automático — dados atualizam só em navegação/filtro.
- ❌ NÃO dispara baixa, cobrança, boleto ou notificação a partir desta tela.
- ❌ NÃO muta em GET — filtros são `router.get` read-only.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Decisão explícita: manter dormente (tier C) ou reativar como live antes de virar `status: live`
- [ ] Smoke visual 1280/1440 (screenshot)
