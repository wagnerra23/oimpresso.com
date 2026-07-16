---
page: /ads/admin/decisoes
component: resources/js/Pages/ads/Admin/Decisoes.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/decisoes (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/DecisoesController@index` + ações `approve`/`reject`/`dismiss` (rotas `ads.admin.decisoes.*`, middleware `auth` — V1 superadmin; permissão `ads.decisoes.review` planejada V2). Escopa `where('business_id', $businessId)` da sessão. `Inertia::defer` pra `decisions` (50 rows + DecisionPresenter) e `kpis` (5 COUNTs).
>
> PT-04 declarado porque a tela é liderada por um `KpiGrid` de 5 `KpiCard` que funcionam como filtros/abas (onClick→setTab, selected). A lista de decisões abaixo é `<ul>/<li>` (não `<table>`), então a assinatura de PT-01 não bate — o dominante-declarável presente é o grid de KPIs.

---

## Mission
Inbox operacional onde Wagner triagem decisões automatizadas detectadas e roteadas pelo Adaptive Decision System (ARQ-0008 HiTL). É a fila de trabalho humano do loop: aprovar, rejeitar (com aprendizado do ConfidenceEngine) ou dispensar itens não-acionáveis, com KPIs que servem de filtro por estado.

---

## Goals — Features (faz)
- 5 KPI-filtros clicáveis (Aguardando você / Brain B processando / Subtarefas / Concluídas 7d / Rejeitadas 7d) que trocam a aba via partial reload.
- 4 abas: pendentes, em_andamento, subtarefas, historico (query escopada por tab no controller).
- Lista de decisões (linha estilo "Ordem"): id, título one_line (DecisionPresenter), badge de destino/risco/policy, hint de ação, resumo da instrução do Brain B, meta (timestamp · origem · módulo), link pra detalhe e pra decisão pai (subtarefa).
- Ações por linha na aba pendentes: Aprovar/Rejeitar (se actionable) ou Dispensar (se não).
- Auto-refresh toggle "Live (10s)" nas abas operacionais; EmptyState por aba.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO cria decisões manualmente — elas nascem do Brain A daemon / eventos, não desta UI.
- ❌ NÃO edita a instrução do Brain B na listagem (só aprova/rejeita/dispensa; edição fina é no detalhe).
- ❌ NÃO mostra decisões de outro business — tudo escopado por business_id da sessão.
- ❌ NÃO pagina além de 50 rows por aba (limite fixo no controller) nem exporta.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; troca de aba via partial reload `only:['tab','decisions']` (kpis não re-buscam).

---

## Automation hooks (faz)
- Auto-refresh: `setInterval` 10s dispara `router.reload({ only:['decisions','kpis'] })` nas abas operacionais (não no histórico).
- `Inertia::defer` + partial reload — troca de aba re-busca só tab+decisions; approve/reject/dismiss dão reload preserveScroll.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Polling pausa quando: aba = histórico, toggle desligado, ou usuário com foco em `<input>` (não interrompe digitação).
- ❌ Nenhuma decisão é aprovada/rejeitada sozinha — toda mutação é clique humano.
- ❌ Rejeição sempre pede motivo (prompt) antes de postar; sem mutação em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — cobrir as 4 abas + estado live/pausado
- [ ] Confirmar criação da permissão `ads.decisoes.review` (V2) antes de abrir pra time
