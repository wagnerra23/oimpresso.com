---
page: /financeiro/relatorios
component: resources/js/Pages/Financeiro/Relatorios/Index.tsx
related_prototype: n/a (tela de relatório com abas + KPI strip bespoke — não casa a assinatura de um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Financeiro
related_us: [US-FIN-014]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /financeiro/relatorios (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> **Silêncio de PT (honesto):** relatório com abas (Fluxo/Resumo) + KPI strip `fin-stats` bespoke + tabelas — os tokens de PT-04 (`KpiCard/KpiGrid/KpiFilterCard`) não existem no `.tsx`, e "Lista" não descreve a tela; então não declara PT.
>
> Backend: `Modules/Financeiro/Http/Controllers/RelatoriosController@index` (permissão `financeiro.relatorios.view`). Relatórios gerenciais Fluxo de Caixa + Resumo (US-FIN-014).

---

## Mission
Relatórios gerenciais read-only do Financeiro: Fluxo de Caixa projetado vs realizado (por semana) e Resumo do período (KPIs + alerta de vencidos), filtrados por intervalo de datas. Ajuda a operação a ler a posição de caixa; não movimenta nem recalcula nada. DRE saiu daqui em 2026-05-20 (tela dedicada `/financeiro/dre`, banner avisa).

---

## Goals — Features (faz)
- Aba Fluxo: KPIs (projetado/realizado receber/pagar, saldos) + tabela semanal com barras de visualização proj/real.
- Aba Resumo: KPIs a receber/pagar aberto + recebido/pago no período + saldos, e card de atenção a vencidos com links pro dashboard.
- Filtro de período (de/até) + atalho "Últimos 4 meses", via partial reload (`only: ['filters','fluxo','resumo']`).
- Export CSV do período/aba via `FinanceiroSubNav` overflow (`/financeiro/relatorios/export-csv`, abre em nova aba).
- Banner que redireciona pra a DRE dedicada (`/financeiro/dre`).
- Header canon `<PageHeader>` v3.8 + `FinanceiroSubNav`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera cálculo, valor, saldo, baixa ou estoque — relatório é 100% VISÃO/leitura; agrega dados existentes sem escrever.
- ❌ NÃO contém mais a DRE (extraída pra `/financeiro/dre`).
- ❌ NÃO lança nem baixa títulos.
- ❌ NÃO cruza dados entre businesses — `Titulo`/`TituloBaixa` usam `BusinessScope` (session `user.business_id`); relatório é sempre tenant-isolado, nunca cross-tenant.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Export CSV com BOM UTF-8 (Excel BR) via `streamDownload` no Controller.
- Partial reload no filtro de período — só re-busca filters/fluxo/resumo.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO envia relatório por e-mail/agendamento — export é sob demanda (clique).
- ❌ NÃO faz polling nem recalcula em background.
- ❌ NÃO muta em GET — export e filtros são read-only.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar que o banner/redirect pra `/financeiro/dre` é o comportamento final desejado
