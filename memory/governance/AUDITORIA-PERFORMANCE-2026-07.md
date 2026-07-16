# Auditoria de Performance — 2026-07 (Onda 4 · lente 5b)

> Entregável da **Onda 4** do [PLANO-APROFUNDAMENTO-AVALIACOES](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) (PR #3820).
> Método: (a) medição real no que existe instrumentado (Jaeger CT100 + probe sintético prod), (b) análise estática de N+1 e `Inertia::defer` misses sobre `origin/main` @ f90a675507 (2026-07-05), calibrada pelo canon [RUNBOOK-inertia-defer-pattern.md](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
> Catraca pareada: [`scripts/perf-static-guard.mjs`](../../scripts/perf-static-guard.mjs) (advisory, ratchet — contadores não podem subir).
> Sem valores BRL. Executor: [CC], gate humano: Wagner.

---

## 1. Baseline p95/p99 — o que é MEDIDO hoje (e o gap honesto)

### 1.1 Gap estrutural: o app web prod NÃO exporta OTel

- **Prova 1:** `.env` de produção Hostinger não tem nenhuma var `OTEL_*` (verificado via SSH 2026-07-05).
- **Prova 2:** Jaeger CT100 (`localhost:16686/api/services`) lista **1 único serviço**: `oimpresso-mcp`. Nenhum trace do ERP web.
- **Prova 3:** Hostinger não expõe access log com tempo de resposta (`~/.logs` só tem `error_log_*` + cron/mail).

Consequência: **p95/p99 por rota autenticada do ERP é imensurável hoje**. O item #4 P0 do loop IA-OS ("ligar Langfuse + OTel collector CT100") segue pendente — o collector já tem config versionada em [`infra/ct100/otel/`](../../infra/ct100/otel/otel-collector-config.yaml); falta o app Hostinger exportar OTLP/HTTP pro CT100 :4318 (sem daemon no Hostinger — [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) preservada, export é client-side por request). **Decisão Wagner pendente** — enquanto isso, o ranking de rotas lentas da §2 é por análise estática, não por medição.

### 1.2 Medido de verdade — Jaeger CT100, serviço `oimpresso-mcp` (janela 7d, 563 traces)

| Operação | n | p50 | p95 | p99 |
|---|---|---|---|---|
| `POST /api/mcp` (tool calls) | 7 | 5.789ms | 8.308ms | 8.308ms |
| `App\System::get` | 9 | 355ms | 1.735ms | 1.735ms |
| `GET /api/mcp/health` | 542 | 2,9ms | 3,4ms | 5,2ms |

Amostra de tool-calls é pequena (n=7) mas o sinal é claro: chamada MCP real custa **5-8s** (LLM/embedder no caminho), health é sub-5ms.

### 1.3 Medido de verdade — probe sintético prod (TTFB externo, 12 amostras/rota, 2026-07-05)

| Rota | p50 | p95 | Nota |
|---|---|---|---|
| `GET /login` | 1.065ms | 1.535ms | página pública, Laravel completo |
| `GET /` | 1.061ms | 1.148ms | redirect/landing |
| rota inexistente (404 via Laravel) | 795ms | 843ms | **piso** de bootstrap framework+rede |

Leitura: o piso Hostinger (rede + LiteSpeed + bootstrap Laravel) é ~800ms; `/login` adiciona ~270ms de trabalho de app. Todo ganho de query em tela autenticada briga contra um piso alto — reforça que **defer (percepção) rende mais que micro-otimização de query** nas telas onde a lista já é enxuta.

---

## 2. Top-5 N+1 (análise estática, com fix proposto)

| # | Onde | Padrão | Custo/página | Fix proposto |
|---|---|---|---|---|
| 1 | [`Modules/Connector/Transformers/SellResource.php:43`](../../Modules/Connector/Transformers/SellResource.php) → `Util.php:754-792` (rota `GET /connector/api/sell`) | cada sell serializado chama `getInvoiceUrl()`+`getInvoicePaymentLink()`, e cada um faz `Transaction::findOrFail($id)` da row **já em memória** — e `->save()` de backfill de token **dentro de GET** | per_page 10 → ~27-47 queries; per_page 50 → 107+ | usar `$this->resource` direto (zero query); backfill de `invoice_token` em batch `whereIn` fora do request |
| 2 | [`Modules/OficinaAuto/Entities/ServiceOrder.php:265`](../../Modules/OficinaAuto/Entities/ServiceOrder.php) via Board ([`ServiceOrderController.php:395`](../../Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php)) | accessor `total_items` = `$this->items()->sum(...)` → 1 SUM **por card** (board carrega até 300) | ~305 queries no board cheio | `->withSum('items as items_sum_valor_total', 'valor_total')` na query do board + accessor lê o atributo com fallback |
| 3 | [`Modules/RecurringBilling/Http/Controllers/PlanController.php:318-327`](../../Modules/RecurringBilling/Http/Controllers/PlanController.php) | COUNT de assinaturas **dentro de foreach** de planos (KPI MRR) | N planos = N COUNTs | 1 query `groupBy('plan_id')->pluck('c','plan_id')` e consumir o mapa (o próprio arquivo já faz certo em :249-258 via `selectSub`) |
| 4 | [`Modules/Financeiro/Http/Controllers/UnificadoController.php:1247-1370`](../../Modules/Financeiro/Http/Controllers/UnificadoController.php) (`Financeiro/Unificado/Index`) | fan-out de ~19 aggregates sequenciais (kpis ×2 + aging 5 COUNTs) rodando **eager a cada filtro** | ~25+ queries/visita na tela principal do Financeiro | fundir aging em 1 `SUM(CASE WHEN)`; fundir kpis a_receber/a_pagar em 1 query; envolver em `Inertia::defer` (§3.1) |
| 5 | [`Modules/OficinaAuto/Http/Controllers/VehicleController.php:130-153`](../../Modules/OficinaAuto/Http/Controllers/VehicleController.php) | 5 COUNTs separados na mesma tabela pra KPIs | 5 queries eager/visita | 1 `groupBy('current_status')` + 1 count residual; defer. (Status `locada/disponivel` = resíduo [ADR 0265](../decisions/0265-oficina-reparo-erradica-locacao.md) — não reintroduzir no fix) |

**Menções honrosas** (lista completa na sessão executora): filtro em memória pós-paginate quebrando paginação em `CaixaUnificadaController.php:486` (bug de corretude, task separada); `Cliente/Map` carregando todos os contatos 2× (`ContactController.php:3439`); 5 COUNTs eager do `SellsCockpitAggregator`.

⚠️ Qualquer fix em #1/#4 que toque valor exibido dispara a **REGRA MESTRE** (dupla confirmação + antes→depois + OK Wagner) — [memory/proibicoes.md](../proibicoes.md).

## 3. Props caras sem `Inertia::defer` (top misses vs canon RUNBOOK)

| # | Controller | Page | Prop eager cara |
|---|---|---|---|
| 1 | `Modules/Financeiro/.../UnificadoController.php:295-307` | `Financeiro/Unificado/Index` | `kpis` (~14 agg) + `lancamentos` (500 rows × 7 relações) + `agingBreakdown` |
| 2 | `app/Http/Controllers/HomeController.php:131-136` | `Home/Index` | `totals` = 4 aggregates do ano fiscal inteiro na landing pós-login |
| 3 | `Modules/Repair/Http/Controllers/RepairController.php:434` | `Repair/Index` | paginate + totals + 3 dropdowns, tudo eager |
| 4 | `app/Http/Controllers/SellController.php:661-678` | `Sells/Index` | `sellKpis` (5 COUNTs) + dropdown com TODOS os clientes (o vizinho `coworkAggregates` já é defer) |
| 5 | `app/Http/Controllers/TransactionPaymentController.php:793-812` | `TransactionPayment/Index` | `pagamentos` paginate(50) eager |
| 6 | `app/Http/Controllers/ProdutoUnificadoController.php:52-61` | `Produto/Unificado/Index` | kpis (scan 30d) + 500 produtos × 4 relações |
| 7 | `Modules/Financeiro/.../RelatoriosController.php:44-48` | `Financeiro/Relatorios/Index` | aggregations de 3+ meses no first-paint |
| 8 | `Modules/NFSe/Http/Controllers/NfseController.php:70` | `Nfse/Index` | o próprio código anota o TODO de defer (Wave 23 D3) |

Regra de aplicação (lição PR #963/KB): defer no backend **sempre pareado** com `<Deferred>` no front, no mesmo PR — defer sem wrapper quebra a Page.

**Referências positivas já no repo** (imitar): `Cliente/Index` (`ContactController:516`), Whatsapp Inbox/Caixa Unificada (origem do pattern D-14), `Financeiro/Dashboard` (defer + OTel spans), lista do Sells (subqueries scalar + batch `whereIn` — parece N+1 e não é).

## 4. Catraca (regra 6 do plano)

[`scripts/perf-static-guard.mjs`](../../scripts/perf-static-guard.mjs) + [`perf-static-baseline.json`](../../scripts/perf-static-baseline.json) — gêmeo de `domain-dict-guard.mjs`: fotografa 3 contadores e falha (exit 1) se algum **subir**; melhorias regravam baseline com `--write-baseline`.

| Contador | O que conta | Baseline 2026-07-05 |
|---|---|---|
| `paginate_sem_eager` | `paginate(`/`simplePaginate(` sem `with(`/`withCount(`/`select(`/`pluck(` nas 40 linhas anteriores (heurística de tendência, com falsos positivos catalogados no header do script) | **28** |
| `render_paginate_sem_defer` | Controllers com `Inertia::render` + `paginate(` e zero `Inertia::defer` | **8** |
| `render_count_sem_defer` | Controllers com `Inertia::render` + `->count()` e zero `Inertia::defer` | **20** |

Advisory por ora (política [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md): required = só Tier-0); roda local/por-PR sob demanda. Promover a CI advisory é follow-up.

## 5. Próximos passos (humano-gated — nenhuma task auto-criada)

1. **[Wagner] Ligar OTel do app → CT100 :4318** (fecha o gap §1.1 e o item #4 do loop IA-OS; a próxima edição desta auditoria ganha p95/p99 por rota REAL).
2. Batch de fixes N+1 §2 (1 PR por item; #1 e #4 sob REGRA MESTRE).
3. Leva de defer §3 (1 PR por tela, backend+front pareados).
4. Task separada pro bug de paginação do `queueFilter` (corretude, não perf).

## Histórico

| Data | Alteração | Autor |
|---|---|---|
| 2026-07-05 | Criação — Onda 4 lente 5b. Baseline medido (Jaeger MCP + probe prod) + gap OTel provado + top-5 N+1 + 8 defer misses + catraca perf-static-guard. | [CC], gate Wagner |
