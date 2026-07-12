---
page: /ads/admin/learning
component: resources/js/Pages/ads/Admin/Learning.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/learning (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/LearningController@index` (rota `ads.admin.learning.index`, middleware `auth` — V1 superadmin). Escopa `where('business_id', $businessId)` da sessão. `Inertia::defer` pra `stages` (9 COUNTs + 2 em mcp_decision_patterns), `throughput` (24 buckets GROUP BY hora) e `kpis`.

---

## Mission
Visualizar o loop de aprendizado completo do ADS (ARQ-0007): evento → classificação → roteamento → execução → review → pattern → promoção. Mostra quantas decisões passaram por cada estágio nas últimas 24h, deixa Wagner clicar num estágio pra filtrar as decisões correspondentes e explica quando/como o loop fecha (Wilson Score ≥ 0.80).

---

## Goals — Features (faz)
- 4 KPIs: eventos na janela (24h), aguardando humano, % reviewed, % pattern registrado.
- Pipeline visual de 9 estágios em cards (grid md:3 colunas): count + nome + descrição + ícone; estágio com filter_url vira link pra `/ads/admin/decisoes`.
- Gráfico de throughput por hora (barra empilhada sucessos/rejeitadas/outros) com aria-label.
- Card explicativo "Como o loop fecha": sequência de badges (Capturado→…→Promoted) + regra de promoção (Wilson LB ≥ 0.80, ≥10 amostras → task pendente Wagner + PR em PolicyEngine.php) + drift detection.
- Skeleton via `<Deferred>`; cores dos estágios colapsadas em 4 tons semânticos do DS.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO promove pattern nem edita PolicyEngine pela UI — o "loop fecha" gerando task + PR git, aprovado por Wagner.
- ❌ NÃO executa nem reprocessa decisões — é painel de observabilidade, os estágios só linkam pra filtros.
- ❌ NÃO cruza businesses — todas as contagens escopadas por business_id da sessão.
- ❌ NÃO configura a janela de análise (fixa em 24h no controller).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; pipeline empilha vertical no mobile.

---

## Automation hooks (faz)
- `Inertia::defer` dispara stages/throughput/kpis sob demanda (partial reload), fora do initial render.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh — não recarrega o pipeline sozinho.
- ❌ Sem promoção automática de padrão (sempre passa por task + PR humano).
- ❌ Sem mutação em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Validar rótulos dos 9 estágios com Wagner (nomenclatura do loop)
