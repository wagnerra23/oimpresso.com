---
page: /ia/admin/custos
component: resources/js/Pages/Jana/Admin/Custos/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Jana
related_us: [US-COPI-070]
related_adrs: [114, 101, 93, 29]
tier: B
charter_version: 1
---

# Page Charter — /ia/admin/custos (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Jana/Http/Controllers/Admin/CustosController@index` (rota `jana.admin.custos.index`, permissão `copiloto.admin.custos.view`). Dashboard admin de custo de IA do Copiloto por período, scopado ao `business_id` da sessão.

---

## Mission
Dar ao admin do business uma visão consolidada de quanto a IA (Copiloto) custou no período escolhido — mês atual, mês anterior, últimos 90 dias ou range customizado. Responde "quanto gastei de IA e quem consumiu" com KPIs de custo em R$, tokens, mensagens e usuários ativos, um gráfico de gasto diário e um breakdown por usuário. Fundamenta a decisão de ROI (Onda 1) sem depender do superadmin.

---

## Goals — Features (faz)
- Exibe 4 KPIs do período (`KpiGrid`/`KpiCard`): custo em R$, mensagens, tokens consumidos, usuários ativos.
- Filtro de período por preset (`mes_atual`, `mes_anterior`, `90d`, `custom`) e, no modo `custom`, range de datas De/Até via form.
- Gráfico de área SVG inline (sem dep externa) do gasto diário no período, com total agregado.
- Tabela "Por usuário" com conversas, mensagens, tokens e R$ aproximado por usuário, mais linha de total.
- Mostra contexto de pricing (modelo base e câmbio BRL/USD) lido de `config('copiloto.ai.*')`.
- Partial reload (`router.get` com `only: [...]`) ao trocar filtro — não retrafega `pricing` (config estática).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não expõe custo cross-business — cada admin vê só o `business_id` da própria sessão (inferência pendente de Wagner).
- ❌ Não edita/ajusta preços de modelo nem câmbio pela tela (config, não formulário).
- ❌ Não exporta CSV/PDF do relatório (inferência pendente de Wagner).
- ❌ Não faz forecast/projeção de custo futuro — só mostra o realizado do período.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (`AppShellV2` layout + `JanaAreaHeader active="custos"`).

---

## Automation hooks (faz)
- Consumo de IA popula o painel automaticamente conforme o Copiloto é usado (agregação lida no `CustosService::painel`).
- Troca de filtro dispara partial reload server-driven do payload (`kpis`, `por_usuario`, `serie_diaria`, `periodo`, `filters`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh — só re-busca quando o usuário troca filtro/aplica range.
- ❌ Não muta dados em GET — a rota é read-only (dashboard).
- ❌ Não dispara alerta/notificação de estouro de custo a partir desta tela.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar com Wagner se export CSV/PDF e alerta de custo entram no escopo ou ficam como Non-Goal firme
