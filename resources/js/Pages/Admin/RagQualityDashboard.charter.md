---
page: /admin/rag-quality
component: resources/js/Pages/Admin/RagQualityDashboard.tsx
owner: wagner
status: draft
last_validated: "2026-05-31"
parent_module: Admin
related_adrs: [160, 122, 94, 93, 58, 35]
tier: A
charter_version: 1
---

# Page Charter — /admin/rag-quality (DRAFT)

> **Status:** draft Wave 28 §G3 (2026-05-31). Wagner aprova Non-Goals + Anti-hooks ANTES de virar `status: live`.
>
> Backend: `Modules/Admin/Http/Controllers/RagQualityDashboardController.php` agrega `mcp_observability_spans` / `mcp_observability_aggregates_daily` (p99 por bucket retrieve/rerank/generate), `mcp_rag_evals` (nDCG@5 / recall@5 RAGAS — Wave 29 materialização) e contadores fallback BGE-v2-m3 (CT 100). Tokens de cor semânticos (`success`/`warning`/`destructive`/`primary`) — sem cores cruas (Constituição UI v2 · Fundações).

---

## Mission

Dashboard Wagner-only de observability do pipeline RAG (KB + Jana): latência p99 por estágio, qualidade de retrieval (nDCG@5/recall@5), queries mais lentas e saúde do reranker BGE no CT 100. Fecha o loop por métrica (princípio duro 4) sobre a stack Centrifugo/FrankenPHP + BGE ([ADR 0058](../../../../memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)).

---

## Goals — Features (faz)

- **3 sparklines p99** (retrieve Meilisearch hybrid / rerank BGE-v2-m3 / generate LLM), cada card com último p99, pico janela, contagem e nº de spans, cor semântica por threshold
- **Sparkline com tooltip nativo** (`<title>` por ponto) + marcador do último ponto (valor atual)
- **nDCG@5 trend** + **Recall@5 trend** side-by-side (fonte `mcp_rag_evals`, empty-state graceful pré-Wave 29)
- **Fallback rate BGE** com semáforo (saudável / acima da meta / drift crítico) e dica de runbook
- **Top 10 queries lentas** (cap 1000 spans) — span, query_hash, max p99, count
- **Top-bar status BGE** (ATIVO / desabilitado + endpoint)
- **Thresholds centralizados** (`P99_THRESHOLDS`, `FALLBACK_THRESHOLDS`) — não hardcoded inline
- `Inertia::defer` em todas as props caras (latency_buckets / ndcg_trend / recall_trend / top_slow_queries / fallback_rate) — D-14 pattern
- AppShellV2 layout + PageHeader canon + footer generated_at

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO recomputa evals RAGAS no GET — só lê snapshot (escrita via `kb:ragas-eval-snapshot`)
- ❌ NÃO dispara reranker/LLM no GET (apenas agrega spans já gravados)
- ❌ NÃO acessível pelo time (Maiara/Felipe/Luiz/Eliana) — middleware `is-wagner`
- ❌ NÃO acessível pela internet pública — Tailscale CIDR
- ❌ NÃO cross-business indevido — Wagner cross-tenant intencional (ver Admin charter)
- ❌ NÃO permite tunar thresholds pela UI (constantes no front; futuro `config('admin.rag.*')`)
- ❌ NÃO extrai Sparkline pra shared — componente local in-place (evitar conflito)

---

## UX targets

- Carregamento <2s via `Inertia::defer` (agregados lazy)
- Sparkline inline SVG (zero JS chart lib) — tooltip por ponto + último-ponto destacado
- Cor SÓ por token semântico (escala success/warning/destructive) — nunca emerald/amber/red/indigo cru
- Empty-state graceful por seção (tabelas vazias em dev não quebram)
- Mobile 1-col responsive — celular Tailscale

---

## Automation hooks (faz)

- `Inertia::defer` lazy props (D-14 pattern)
- `OtelHelper::span` instrumentação custo agregado
- Agregação p99 por bucket em SELECT único por janela

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO dispara LLM nem reranker no GET
- ❌ NÃO materializa `mcp_rag_evals` no GET (job/command externo)
- ❌ NÃO escreve auditoria no GET
- ❌ NÃO mostra PII (spans/evals não contêm PII; defesa em profundidade via PiiRedactor a montante)

---

## Métricas de sucesso

- ✅ Wagner abre `https://admin.oimpresso.com/rag-quality` → 3 sparklines p99 <2s
- ✅ Time tentando acessar → 403 (IsWagner)
- ✅ Fallback rate BGE verde (<5%) em operação normal; drift crítico destacado quando container down
- ✅ nDCG@5/recall@5 trend visível após primeira execução `kb:ragas-eval-snapshot`

---

## Pendências pós-Wave 28

- [x] Tokenizar cores p99/fallback/nDCG/recall (semântico, sem cru)
- [x] Tooltip + marcador último-ponto no Sparkline
- [x] Charter draft criado
- [x] Guard tsc `number | undefined` no último p99
- [ ] Wagner aprova Non-Goals + Anti-hooks → `status: live`
- [ ] Materialização `mcp_rag_evals` (Wave 29) — trend nDCG/recall sai de empty-state
- [ ] Thresholds via `config('admin.rag.*')` (atualmente const front)
- [ ] Pest matriz Inertia render IsWagner gate + props defer
