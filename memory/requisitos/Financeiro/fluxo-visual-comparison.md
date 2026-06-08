---
slug: fluxo-visual-comparison
title: "Financeiro — Comparativo visual da tela Fluxo de caixa projetado"
type: visual-comparison
module: Financeiro
status: pending_wagner_decisions
date: 2026-05-14
canon_reference: prototipo-ui/prototipos/financeiro-fluxo/page.tsx (Cowork F1 aprovado [W] 2026-05-09)
blade_source: n/a (greenfield — não existe tela equivalente em legacy)
inertia_target: resources/js/Pages/Financeiro/Fluxo/Index.tsx
service_new: Modules/Financeiro/Services/FluxoCaixaService::projetar(businessId, dias=35)
controller_new: Modules/Financeiro/Http/Controllers/FluxoController::index()
stories: US-FIN-014
related_adrs: [ui/0114, 0093]
---

# Comparativo visual — Financeiro · Fluxo de caixa projetado

> **Tipo de tela:** dashboard projeção 35 dias (KPI grid + gráfico de barras + tabela próximos eventos)
> **Persona alvo:** Eliana [E] — financeiro escritório / Wagner [W] — dono. Desktop ≥1024px. Decisão de caixa em <30s.
> **Refs:**
> - Blade legacy: ❌ **n/a** — greenfield (não existe equivalente)
> - Canon Cockpit: [`prototipo-ui/prototipos/financeiro-fluxo/page.tsx`](../../../prototipo-ui/prototipos/financeiro-fluxo/page.tsx) — F1 aprovado [W] Cowork 2026-05-09
> - Charter: a criar em `resources/js/Pages/Financeiro/Fluxo/Index.charter.md` (F3)
> - ADRs: [ui/0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md), [0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Resumo executivo

Eliana hoje **não tem visão de fluxo projetado** — pra responder "vou conseguir pagar a folha dia 30?" ela abre 3 telas separadas (Contas a Receber, Contas a Pagar, Saldo bancário) e soma de cabeça. Esta tela mostra **35 dias de projeção em 1 vista**: 4 KPIs (saldo hoje, projeção 30d, pior dia, margem mínima) + gráfico barras com linha laranja de margem + tabela densa dos próximos 7 dias com eventos discriminados.

Backend usa modelos que JÁ EXISTEM ([Modules/Financeiro/Models/Titulo.php](../../../Modules/Financeiro/Models/Titulo.php), [TituloBaixa.php](../../../Modules/Financeiro/Models/TituloBaixa.php), [ContaBancaria.php](../../../Modules/Financeiro/Models/ContaBancaria.php)) — **sem migration, sem tabela nova**. Service novo `FluxoCaixaService::projetar()` orquestra leitura + agregação por dia.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Hoje (3 telas separadas) | Canon Cockpit (protótipo) | Decisão MWART |
|---|---|---|---|
| Header | n/a (não existe tela) | `PageHeader` Cockpit V2 — título "Fluxo de caixa" + sub "Projeção 35 dias · saldo, entradas e saídas dia-a-dia" | Manter como protótipo — `<PageHeader title="Fluxo de caixa" subtitle="..." />` |
| Body grid | n/a | KPI grid 4 colunas + Card gráfico h:220px + Card tabela próximos eventos | `<KpiGrid columns={4}>` + 2 Cards stack — exatamente como protótipo linha 64-128 |
| Sidebar | n/a | AppShellV2 sidebar — entrada nova "Fluxo de caixa" no submenu Financeiro | Adicionar entrada via DataController.modifyAdminMenu (mesmo padrão PR #358 do Unificado) |
| Breakpoints | n/a | desktop only ≥1024px (Persona Eliana) | desktop only F1; mobile fica US-FIN-025 |

### 2. Hierarquia visual

| Aspecto | Canon Cockpit | Decisão MWART |
|---|---|---|
| Ação primária | n/a (tela read-only) | Sem ação primária — read-only dashboard |
| Ação secundária | Hover na barra mostra tooltip data + saldo | Manter |
| KPIs | `text-2xl semibold tabular-nums` + caption + tone (emerald/rose/amber) | KpiCard component compartilhado (`@/Components/shared/KpiCard`) — protótipo já usa |
| Hierarquia tipográfica | uppercase `text-[10px]` labels + `text-[14px]` semibold subtítulos | Manter literal |

### 3. Densidade

| Aspecto | Decisão MWART |
|---|---|
| Linhas por viewport | Tabela próximos eventos: ~7-12 linhas em 1280px (denso) |
| Gap entre seções | `mt-4` entre Cards (16px) |
| Padding interno Card | `p-5` (KPI Card padrão Cockpit V2) |
| Tabular nums | obrigatório em todos valores monetários (`tabular-nums`) |

### 4. Cor e semântica

| Aspecto | Decisão MWART |
|---|---|
| Saldo positivo | `text-stone-900` (default) |
| Saldo abaixo margem | barra `bg-amber-500` no gráfico + linha tracejada `border-amber-400` na margem mínima |
| Entrada (receivable) | `↓ text-emerald-700 bg-emerald-50` |
| Saída (payable) | `↑ text-rose-700 bg-rose-50` |
| Hoje destacado | barra gráfico `bg-stone-900`; outros futuro `bg-stone-700`; passado `bg-stone-300` |
| Pior dia | KpiCard `tone="amber"` |

### 5. Interação / atalhos

| Aspecto | Decisão MWART |
|---|---|
| Hover barra | Tooltip data_label + saldo_acumulado (grupo CSS via `group-hover:`) |
| Click linha tabela | F1: nenhum; F2 (US-FIN-019): abre drawer detalhe Titulo |
| Atalhos teclado | F1: nenhum; F2: `J/K` navega linhas tabela (mesmo pattern Unificado) |
| Filtros | F1: nenhum (mostra 35d fixos); F2: dropdown 7/15/30/35/60d |

### 6. Estado vazio / loading

| Aspecto | Decisão MWART |
|---|---|
| Sem títulos no período | Card fica visível mas barras todas com altura ~0; tabela "Próximos eventos" exibe "Nenhum evento programado nos próximos 7 dias" |
| Sem ContaBancaria ativa | Banner amber no topo da tela: "Cadastre ao menos 1 conta bancária pra projetar fluxo" + CTA |
| Loading | SSR padrão Inertia — sem skeleton inicial em F1 |

### 7. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

| Aspecto | Decisão MWART |
|---|---|
| Service input | `FluxoCaixaService::projetar(int $businessId, int $dias = 35)` — `business_id` explícito como 1º arg |
| Models | `Titulo`, `TituloBaixa`, `ContaBancaria` já têm `BelongsToBusiness` global scope |
| Controller | `business_id` lido de `session('business.id')` — NUNCA aceitar via query param |
| Pest test obrigatório | cross-tenant: criar dado biz=1 + biz=99, autenticar biz=1, garantir que NÃO retorna nada de biz=99 |

### 8. Performance

| Aspecto | Decisão MWART |
|---|---|
| Query strategy | 2 queries: 1) `Titulo` WHERE vencimento BETWEEN today AND today+35d (futuros); 2) `TituloBaixa` WHERE data_baixa BETWEEN today-2d AND today-1d (histórico recente) |
| ContaBancaria.saldo_cached | Soma simples 1 query: `SELECT SUM(saldo_cached) FROM contas_bancarias WHERE business_id=? AND ativo=1` |
| Cache | F1: nenhum (dataset pequeno — ~50-200 linhas Titulo + ContaBancaria); F2: Redis 5min se latência ≥500ms |
| p95 target | <300ms (Service + render) com 1k Titulos em aberto |

---

## §F1.5 Critique — score esperado

**Score: 88 / 100** (estimado pelo protótipo aprovado [W] 2026-05-09 + ausência de gaps visuais explícitos).

Pontos perdidos:
- **−5** ausência de filtro período (7/15/30/35/60d) — backlog F2
- **−4** sem skeleton loading — backlog F2 (impacto baixo com SSR)
- **−3** sem export CSV/XLSX — backlog F2 (Eliana pede)

**Aprovado pra F3 com gate ≥80.** Próximo passo: backend baseline (Service + Controller + Pest).

---

## §Decisões abertas pro Wagner (BLOQUEIA F3)

> 4 questões listadas no `prototipos/financeiro-fluxo/README.md`. Cada uma tem **recomendação minha**. Wagner aprova/contesta cada uma.

### Q1 — Saldo hoje = soma de ContaBancaria.saldo_cached?

**Recomendação:** ✅ SIM. Soma `ContaBancaria.saldo_cached` WHERE `business_id = ?` AND `ativo = true`. Simples, direto, já reflete a realidade reconciliada.

**Risco baixo:** `saldo_cached` é atualizado por trigger/job a cada baixa — pode estar drifting ±1d em casos extremos. Aceitável pra projeção.

**Alternativa rejeitada:** calcular em runtime via `Sum(creditos) - Sum(debitos)` — caro, traz N+1 risk.

### Q2 — Período 35d fixo ou parametrizável?

**Recomendação:** **35d fixo em F1**. Parametrizável (7/15/30/35/60d) entra em F2 como dropdown — leva ~30min adicionar quando precisar. F1 entrega valor imediato sem decisão UX adicional.

**Razão:** "vou conseguir pagar a folha dia 30?" cabe em 35d. Eliana raramente projeta 60d (vai pro DRE).

### Q3 — Margem mínima R$ [redacted Tier 0]k → config.tenant ou hardcode?

**Recomendação:** **Hardcode R$ [redacted Tier 0] em F1**, configurável em F2 via `business_settings.margem_minima_caixa`. Migration trivial (`ALTER TABLE business_settings ADD COLUMN margem_minima_caixa DECIMAL(15,2) DEFAULT 5000`) entra como US-FIN-021.

**Razão:** ROTA LIVRE não tem opinião agora — 5k é número conservador. Quando Eliana pedir "muda pra 8k" → migration + Inertia setting (~1h trabalho).

### Q4 — Histórico = -2 dias arbitrário ou "últimas baixas relevantes"?

**Recomendação:** **-2 dias fixo em F1**, com nota visual ("histórico recente · últimas 48h"). Mostra contexto sem inflar query.

**Razão:** "Últimas baixas relevantes" exige definição de "relevante" (valor ≥X? top N?) — vira decisão UX que adia F3. Aceitar -2d agora; Eliana pode questionar depois e a gente troca por regra clara.

---

## §Próxima ação após Wagner aprovar Q1-Q4

[CL] executa F3 em sequência:

1. `Modules/Financeiro/Services/FluxoCaixaService.php` — `projetar(int $businessId, int $dias = 35): array` retornando Props do page.tsx
2. `Modules/Financeiro/Http/Controllers/FluxoController.php` — `index(Request $r)` lê `business.id` da session, chama Service, `Inertia::render('Financeiro/Fluxo/Index', $props)`
3. `Modules/Financeiro/Tests/Feature/FluxoControllerTest.php` Pest — cross-tenant biz=1 vs biz=99 + smoke 200 + props shape
4. `resources/js/Pages/Financeiro/Fluxo/Index.tsx` — copy do protótipo `page.tsx` (sem mudanças, só path)
5. `resources/js/Pages/Financeiro/Fluxo/Index.charter.md` — charter Cockpit V2 padrão
6. `Modules/Financeiro/Routes/web.php` — `Route::get('/financeiro/fluxo', [FluxoController::class, 'index'])->name('financeiro.fluxo')`
7. `Modules/Financeiro/Http/Controllers/DataController.php::modifyAdminMenu()` — entrada "Fluxo de caixa" no submenu Financeiro

Esforço total estimado (10x IA-pair ADR 0106): **~1.5-2h** trabalho [CL] após Wagner desbloquear Q1-Q4.
