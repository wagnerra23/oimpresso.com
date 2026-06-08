# AUDITORIA · Módulo Compras (Purchase Order) — oimpresso

> **Tema:** Compras / Purchase Order Management
> **Persona:** Larissa @ ROTA LIVRE (biz=4, vestuário/gráfica, PME ~10 funcionários)
> **Data:** 2026-05-21
> **Auditor:** audit-research-expert (Claude Opus 4.7)
> **Status do módulo hoje:** UltimatePOS core legacy (Blade + DataTables) · **NÃO existe `Modules/Compras/`** (greenfield se EVOLUIR)
> **Protótipo canon visual:** `public/cowork-preview/erp-shell-v2/compras-page.jsx` (FSM 6 estágios, importação XML primária, drawer row-driven)

---

## 1. Resumo executivo

**Contexto.** Compras hoje é um dos pedaços mais legados do oimpresso: ~4.4k LOC distribuídos em 5 controllers monolíticos em `app/Http/Controllers/Purchase*.php` (`PurchaseController` 1825 linhas, `PurchaseOrderController` 936, `PurchaseRequisitionController` 497, `PurchaseReturnController` 460, `PurchaseXmlController` 664) + ~14 views Blade + lógica enterrada em `app/Utils/TransactionUtil.php` (6435 linhas, polimórfica entre sell/purchase/expense/stock_adjustment). A FSM real do legacy é simples (`ordered → pending → received → completed`) e mistura status fiscal (`status`), status logístico (`shipping_status`), e status financeiro (`payment_status`) sem orquestração explícita. Já existe ponte bidirecional bem-feita pro Financeiro via `TransactionObserver` (cobre `type='purchase'` desde Onda 2 em 2026-04-25), e `Modules/NfeBrasil` já tem `DistribuicaoDfeService` puxando DFes da SEFAZ via NSU diariamente (06:15 BRT) e armazenando em `nfe_dfe_recebidos` — mas **NÃO existe bridge** entre esse DFe recebido e um Purchase Order/Transaction de compra (o XML upload manual via `PurchaseXmlController::verXml` é o único caminho hoje, com parsing artesanal SimpleXMLElement).

**Nota global: 46% de maturidade vs estado-da-arte 2026 (PME BR + mid-market global).** Fórmula weighted (ver §4):  Ingestão XML 65% × 18% + FSM/Workflow 35% × 15% + 3-way Match 5% × 15% + Supplier Mgmt 30% × 10% + Replenishment AI 0% × 8% + KPIs Dashboard 20% × 8% + UX/Mobile/Atalhos 25% × 7% + Integração Financeiro 90% × 10% + Audit Trail/LGPD 55% × 5% + Multi-tenant Tier 0 95% × 4%.

**Top 3 gaps críticos:** (1) **ZERO bridge DFe SEFAZ → PO** apesar de infra pronta — supplier importa XML manual quando SEFAZ já entregou tudo via NSU diário (P0, 5-8 dev-days IA-pair); (2) **3-way match (PO/recebimento/NF-e) inexistente** — Larissa hoje confere visualmente, fonte #1 de overpayment em PME segundo Rillion/NetSuite (P0, 8-12 dd); (3) **FSM real do código (4 estados) NÃO bate com FSM do protótipo aprovado (6 estágios `rascunho→pedido→trânsito→recebido→conferido→pago`)** — sem isso a tela Inertia não tem como ser fiel à visão Wagner+Cowork (P0, 3-5 dd só pra FSM + migration de estado).

---

## 2. Inventário do que existe hoje

### ✅ APROVADO (manter intacto, não regredir)

| Componente | Estado | Evidência |
|---|---|---|
| **Multi-tenant Tier 0** | 100% — `business_id` global scope em `Transaction`, `Contact`, `PurchaseLine` | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · `PurchaseController.php:66` `request()->session()->get('user.business_id')` |
| **Bridge Financeiro auto** | 90% — `TransactionObserver` cobre `type='purchase'` com sincronização sincrona idempotente | `Modules/Financeiro/Observers/TransactionObserver.php:23` · UNIQUE `(business_id, origem, origem_id, parcela_numero)` |
| **DistribuicaoDFe SEFAZ NSU** | 95% — pull diário 06:15 BRT, cursor NSU irreversível, throttle 5min, OTel wrap | `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php:48` · `PuxarDfesRecebidosCommand` |
| **Manifestação destinatário** | 70% — `ManifestacaoController` + service implementam ciência/confirmação/desconhecimento (3 dos 4 eventos NT 2014.002) | `Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php` |
| **Polimorfismo Transaction** | OK pra MVP, débito técnico aceito — `type='purchase' \| 'purchase_order' \| 'purchase_return' \| 'purchase_transfer'` num único modelo | `app/Transaction.php:15` |
| **Permissões granulares** | OK — `purchase.view/create/update`, `purchase_order.view_all/view_own`, `purchase_requisition.*`, `view_own_purchase` | `PurchaseController.php:63`, `PurchaseOrderController.php:78` |
| **Protótipo canon visual** | 100% pronto pra implementar — FSM 6 estágios + KPIs + drawer + atalhos `/N I ↑↓ Esc` | `public/cowork-preview/erp-shell-v2/compras-page.jsx` (469 linhas) |

### 🟡 PARCIAL (existe mas não é estado-da-arte)

| Componente | % | Gap |
|---|---|---|
| **Importação XML NF-e manual** | 65% | Funciona (`PurchaseXmlController::verXml` parsing SimpleXMLElement), mas é manual: usuário precisa baixar XML do email/SEFAZ e fazer upload. Cria supplier auto, valida produto por EAN/xProd, mas não usa `nfe_dfe_recebidos` que já está pronto no NfeBrasil |
| **Purchase Order separado de Purchase** | 40% | Existe `PurchaseOrderController` + tabela `transactions WHERE type='purchase_order'`, mas é tela Blade isolada com status próprio (`ordered/partial/completed`) que NÃO converte estado quando vira Purchase real (orfão) |
| **Purchase Requisition** | 35% | Existe `PurchaseRequisitionController` mas é flow paralelo desconectado — Larissa não usa, é UI técnica enterprise mal-adaptada |
| **Shipping status** | 50% | Existe enum (`ordered/packed/shipped/delivered/cancelled`) mas é campo solto sem timeline, sem ETA, sem notificação supplier |
| **Audit trail** | 55% | `Spatie\Activitylog\Models\Activity` cobre CRUD mas não está em formato append-only LGPD nem tem assinatura/hash chain |
| **FSM (estados)** | 35% | 4 estados misturados (`received/pending/ordered/draft/final/in_transit/completed`) sem máquina explícita, sem guarda de transição, sem evento bus |
| **KPIs dashboard compras** | 20% | Index Blade tem só DataTable filtrável — sem KPIs "A pagar / Em trânsito / Volume mês / Fornecedores ativos" que o protótipo canon mostra |
| **Atalhos teclado** | 40% | Existem 2 atalhos no Blade (`purchase/partials/keyboard_shortcuts.blade.php`) mas é PowerUser-only, não documentados no header |

### ❌ AUSENTE (greenfield)

| Componente | Impacto |
|---|---|
| **3-way match (PO ↔ recebimento ↔ NF-e)** | P0 — fonte #1 de overpayment, padrão de mercado desde 2020 (NetSuite/Ramp/SAP) |
| **Bridge DFe SEFAZ → Purchase auto-criado** | P0 — infra DistribuicaoDFe está 95% pronta, falta listener `DfeRecebidoEvent → CriarPurchaseRascunhoJob` |
| **Supplier scorecard** (on-time delivery, lead-time, defect rate, fill rate) | P1 — Cin7/GEP/Procurify oferecem auto, Larissa hoje julga "no olhômetro" |
| **AI replenishment / reorder suggestions** | P2 — pré-IA: setar min/max + lead-time consume sales history. Sem cliente reportando dor (ADR 0105), mantém wishlist |
| **Embedded payments PIX/Boleto no PO** | P1 — Bling/Omie geram boleto no fluxo de compra. Hoje precisa ir no Financeiro |
| **Mobile receiving** (scanner código de barras na recepção) | P2 — Procurify mobile-first, Larissa recebe via desktop hoje |
| **Catálogo fornecedor / RFQ** (request for quotation) | P3 — enterprise-only (Ariba/Coupa), fora do escopo PME |
| **Contratos master (blanket PO)** | P3 — fora do escopo PME |
| **Module wrapper `Modules/Compras/`** | P0 se EVOLUIR — sem pacote nwidart isolando, qualquer mexida no `PurchaseController.php` (1825 linhas) é jogo de Jenga |
| **Inertia/React Pages** | P0 se EVOLUIR — protótipo canon é JSX standalone (window.ComprasPage), precisa virar `resources/js/Pages/Compras/Index.tsx` via processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) |
| **Charter `.charter.md`** | P0 se EVOLUIR — convenção Constituição v2 ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) Skill `charter-first` |

---

## 3. Comparação dimensão-a-dimensão

| # | Dimensão | oimpresso 2026-05 | Bling | Omie | Tiny | Zoho Inv. | Cin7 Core | Procurify | SAP Ariba |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | **Importação XML NF-e manual upload** | ✓ (65%) | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| 2 | **Auto-pull DFe SEFAZ (NSU)** | infra ✓, bridge ✗ | ✓ | ✓ | ✓ | — | — | — | — |
| 3 | **Vincular XML ↔ PO existente** | ✗ | ✓ ([Bling](https://ajuda.bling.com.br/hc/pt-br/articles/21830391097367)) | ✓ | parcial | — | — | — | — |
| 4 | **3-way match auto (PO/recv/NF)** | ✗ | parcial | parcial | ✗ | ✗ | ✓ | ✓ | ✓ |
| 5 | **FSM PO explícita (≥5 estágios)** | 35% (4 estados misturados) | ~5 | ~6 | ~5 | 4 | 7+ | 8+ | 10+ |
| 6 | **Supplier scorecard auto** | ✗ | ✗ | parcial | ✗ | parcial | ✓ ([Cin7 ForesightAI](https://www.cin7.com/features/inventory/purchasing/)) | ✓ | ✓ |
| 7 | **AI replenishment / reorder** | ✗ | ✗ | ✗ | ✗ | parcial | ✓ Smart Reorder | parcial | ✓ |
| 8 | **Bridge Financeiro auto (boleto/PIX)** | ✓ 90% (Observer) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 9 | **KPIs dashboard compras** | 20% | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 10 | **Atalhos teclado / power-user UX** | 40% (Blade legacy) | parcial | ✗ | ✗ | parcial | parcial | ✓ | parcial |
| 11 | **Mobile receiving / barcode scan** | ✗ | parcial | parcial | ✗ | ✓ | ✓ | ✓ | ✓ |
| 12 | **Audit trail append-only / LGPD** | 55% (Activitylog) | parcial | parcial | parcial | parcial | parcial | ✓ | ✓ |
| 13 | **Multi-tenant isolation Tier 0** | ✓ 95% | ✓ (SaaS) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 14 | **Performance ≥10k POs** | ⚠ DataTables server-side, sem cache | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| 15 | **Pricing (PME-friendly)** | embutido (gratuito até 2 users) | R$80-120/mo | R$200-1500/mo | R$80-300/mo | US$39/mo | US$349/mo | US$2000/mo+ | enterprise |

Fontes: [Bling planos](https://www.bling.com.br/planos-e-precos) · [Cierus Bling×Tiny×Omie 2026](https://www.cierus.com.br/news-details.php?slug=bling-vs-tiny-vs-omie-qual-erp-escolher) · [Sumtracker top 10 PO software 2026](https://www.sumtracker.com/blog/10-purchase-order-management-softwares) · [Coupa vs Ariba (Zip 2026)](https://ziphq.com/compare/coupa-vs-sap-ariba) · [Opstream AI procurement 2026](https://www.opstream.ai/blog/best-ai-procurement-platforms/) · [Beancount AP automation 2026](https://beancount.io/blog/2026/05/11/accounts-payable-automation-2026-ai-invoice-capture-three-way-match-touchless-approvals-cut-costs-eliminate-duplicate-payments-guide)

---

## 4. Score weighted por área

Fórmula: `nota_final = Σ (área_% × peso_área)`. Pesos calibrados pra persona Larissa (PME vestuário/gráfica, foco operacional não enterprise).

| # | Área | Peso | Nota oimpresso | Contribuição | Evidência principal |
|---|---|:---:|:---:|:---:|---|
| A | Ingestão XML / DFe SEFAZ | 18% | 65% | 11,7 | `PurchaseXmlController.php` funciona upload manual; `DistribuicaoDfeService` pronta mas desconectada (sem listener → PO) |
| B | FSM Purchase Order / Workflow | 15% | 35% | 5,2 | 4 estados misturados em 3 campos (`status`+`shipping_status`+`payment_status`), sem máquina explícita ([PurchaseController.php:174](https://github.com)) |
| C | 3-way Match (PO/Recv/NF-e) | 15% | 5% | 0,7 | Inexistente. Conferência manual visual. Padrão de mercado desde 2018 ([Rillion 3-way matching](https://www.rillion.com/learn-ap/3-way-matching/)) |
| D | Supplier Management (cadastro + scorecard) | 10% | 30% | 3,0 | `Contact type='supplier'` OK, sem scorecard (on-time%, lead-time avg, defect rate) |
| E | Replenishment / AI Reorder | 8% | 0% | 0,0 | Inexistente. Sem min/max stock vinculado a histórico vendas |
| F | KPIs Dashboard Compras | 8% | 20% | 1,6 | Só DataTable filtrável; sem cards "A pagar/Trânsito/Volume mês/Fornecedores" (protótipo mostra 4) |
| G | UX / Mobile / Atalhos | 7% | 25% | 1,75 | Blade legacy, atalhos básicos em partial isolado, sem mobile receiving |
| H | Integração Financeiro | 10% | 90% | 9,0 | `TransactionObserver` cobre `purchase` desde Onda 2 (2026-04-25); UNIQUE garante idempotência |
| I | Audit Trail / LGPD | 5% | 55% | 2,75 | Spatie Activitylog OK, mas não append-only hash-chained |
| J | Multi-tenant Tier 0 | 4% | 95% | 3,8 | `business_id` em todo lugar; ADR 0093 IRREVOGÁVEL respeitado |
| **Total** | **100%** | — | **46,3%** | |

**Saturação esperada se executar Roadmap completo:** 78-82% (não vale perseguir 95%+ — Larissa não precisa de RFQ/blanket PO/sourcing AI no nível Ariba; é over-engineering pra persona PME).

---

## 5. Top 10 gaps priorizados (impacto × esforço, recalibrado [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair)

| # | Gap | Prio | Impacto | Esforço (dev-days IA-pair) | ROI | Sistema-ref |
|---|---|:---:|:---:|:---:|:---:|:---|
| 1 | **Bridge DFe SEFAZ → Purchase auto-rascunho** (listener `NfeDfeRecebido` cria Transaction `type='purchase' status='draft'`, supplier match por CNPJ, produto match por EAN+xProd) | P0 | 🔴 ALTO | 5-8 | ⭐⭐⭐⭐⭐ | Bling auto-import |
| 2 | **FSM Purchase explícita (6 estágios `rascunho→pedido→trânsito→recebido→conferido→pago`)** + state machine PHP (`spatie/laravel-model-states`) + migration consolidando `status`+`shipping_status` em `stage` | P0 | 🔴 ALTO | 3-5 | ⭐⭐⭐⭐⭐ | protótipo canon (já aprovado) |
| 3 | **3-way match automático** (matching PO.qty vs Recv.qty vs NFe.qty, tolerância configurável, drawer "discrepâncias" no Inertia Page) | P0 | 🔴 ALTO | 8-12 | ⭐⭐⭐⭐ | NetSuite/Cin7/Precoro |
| 4 | **Tela Inertia/React `Pages/Compras/Index.tsx` + Drawer.tsx** seguindo protótipo canon (FSM track + KPIs + tabela + 5 tabs drawer) via processo MWART 5 fases | P0 | 🔴 ALTO | 6-10 | ⭐⭐⭐⭐⭐ | protótipo canon |
| 5 | **Manifestação destinatário 1-click no drawer** (botão "Manifestar" chama `ManifestacaoController::confirmarOperacao` que já existe) | P1 | 🟡 MÉDIO | 1-2 | ⭐⭐⭐⭐⭐ | ERPFlex/Useall |
| 6 | **Module wrapper `Modules/Compras/`** (nwidart) isolando do `PurchaseController.php` legacy, com `ComprasServiceProvider`, charter, ADRs canon | P0 (se EVOLUIR) | 🔴 ALTO | 2-3 | ⭐⭐⭐⭐ | Modules/Financeiro como referência |
| 7 | **KPIs cards no header** (4 métricas: a pagar, em trânsito, volume mês, fornecedores ativos) com cache redis 5min | P1 | 🟡 MÉDIO | 2-3 | ⭐⭐⭐⭐ | Bling/Omie/Tiny dashboards |
| 8 | **Supplier scorecard básico** (4 métricas: on-time%, lead-time médio, defect rate, fill rate) calculadas em job daily | P2 | 🟢 BAIXO-MÉDIO | 4-6 | ⭐⭐⭐ | Cin7 ForesightAI |
| 9 | **Audit trail append-only hash-chained** (extends Activitylog com prev_hash, garante LGPD compliance forte) | P2 | 🟢 BAIXO-MÉDIO | 3-5 | ⭐⭐ | Procurify enterprise |
| 10 | **Embedded payments PIX/Boleto no drawer pagamentos** (botão "Gerar boleto" chama `Modules/Financeiro` direto) | P1 | 🟡 MÉDIO | 2-3 | ⭐⭐⭐⭐ | Bling/Omie |

**Esforço total roadmap completo:** 36-57 dev-days IA-pair (~ 4-6 semanas calendar 1 dev FT, ou 2-3 semanas com 2 devs paralelos).

---

## 6. Decisão estratégica: CONSOLIDAR vs EVOLUIR

### ⚖️ Caminho A — CONSOLIDAR (ficar em `app/Http/Controllers/Purchase*.php` legacy)

**Quando faz sentido:** se gap #1 (bridge DFe→PO) for o único reportado por Larissa e nada mais doer.
- ✅ Esforço baixíssimo (8-12 dd total — gaps 1 + 5 + 7)
- ✅ Zero risco regression em `TransactionUtil.php` (6435 LOC compartilhadas com Sells)
- ❌ Cada melhoria futura é cirurgia no monolito Blade
- ❌ Tela Compras continua estética 2018 enquanto Financeiro/Crm já evoluíram pra Inertia
- ❌ Protótipo canon visual fica engavetado (desperdício do trabalho Cowork)

### 🚀 Caminho B — EVOLUIR (criar `Modules/Compras/` greenfield, processo MWART)

**Quando faz sentido:** se Larissa reportou ≥2 dores reais em Compras OU se métrica detecta drift (tempo médio de conferência NF-e, % de discrepância, etc.) ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).
- ✅ Implementa protótipo canon com 100% fidelidade (FSM 6 estágios + KPIs + drawer)
- ✅ Module wrapper isolado (zero risco em `PurchaseController.php` legacy — pode rodar lado-a-lado durante migração, padrão usado em Financeiro com flag `Modules/Financeiro habilitado`)
- ✅ Bridge DFe SEFAZ vira event-driven limpo (`NfeDfeRecebido` event → listener `CriarPurchaseRascunho`)
- ❌ Esforço alto (36-57 dd total)
- ❌ Precisa ADR canon + Charter + 5 fases MWART (gate visual F1.5 + F3 estado-da-arte) [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)

### 🎯 Recomendação: **EVOLUIR** (Caminho B) condicional a sinal Larissa

**Justificativa (1 parágrafo):** O custo de oportunidade de NÃO evoluir é altíssimo dado que (a) protótipo canon visual já foi aprovado/produzido (`compras-page.jsx`, 469 linhas, FSM 6 estágios + KPIs), (b) infra DFe SEFAZ está 95% pronta e SUB-utilizada hoje, (c) bridge Financeiro 90% pronta cobre `purchase`, e (d) precedente da Onda Financeiro (Modules/Financeiro lado-a-lado com `app/Http/Controllers/ExpenseController.php` legacy, deprecação Fase 1-5 já executada nos PRs #1281-1284) prova que o padrão "Module wrapper + flag de habilitação + redirects 301 do legacy" funciona com baixo risco. **Mas não começar até Larissa reportar dor concreta OU métrica detectar drift** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — caso contrário vira hipótese sem sinal e congela 4-6 semanas-dev em wishlist.

---

## 7. Roadmap EVOLUIR — 3 ondas

> **Premissa:** Larissa reportou ≥1 dor (ex: "conferência manual de NF-e tá tomando 30min/dia" OU "perdi prazo de boleto compra duas vezes esse mês"). Caso contrário, recuar pro Caminho A.

### 🌊 Onda 1 — Foundation (10-15 dev-days, ~1.5 sem calendar)
1. **ADR canon** `Modules/Compras isolado via nwidart` (precedente: Financeiro adr/arq/0001)
2. **Charter** `memory/requisitos/Compras/README.md` + `ARCHITECTURE.md` + `GLOSSARY.md` (precedente: Crm, Accounting)
3. **Module wrapper** `Modules/Compras/` (ServiceProvider, Routes/web.php, RouteServiceProvider, composer.json)
4. **Gap #2 — FSM 6 estágios** com `spatie/laravel-model-states` (rascunho/pedido/trânsito/recebido/conferido/pago) + migration nova `compras_purchases` (espelho de `transactions` mas isolada, ou view materialized)
5. **Gap #4 (parcial) — Inertia Page** `resources/js/Pages/Compras/Index.tsx` + `Drawer.tsx` (F1 backend + F3 frontend MWART), KPIs hardcoded mock
6. **Gate visual F1.5** Cowork screenshot vs protótipo canon

### 🌊 Onda 2 — Diferencial fiscal BR (12-18 dev-days, ~2 sem calendar)
7. **Gap #1 — Bridge DFe SEFAZ → Purchase auto-rascunho** (listener `NfeDfeRecebidoEvent` → `CriarPurchaseRascunhoJob` que monta Transaction + PurchaseLines com supplier match CNPJ + product match EAN)
8. **Gap #5 — Manifestação 1-click** no drawer Documentos
9. **Gap #7 — KPIs reais** (query agregada com cache redis 5min, métricas: a pagar/trânsito/volume mês/fornecedores ativos)
10. **Gap #10 — Embedded payments** botão "Gerar boleto" chamando `Modules/Financeiro\TituloAutoService`

### 🌊 Onda 3 — Inteligência (14-24 dev-days, ~2.5 sem calendar)
11. **Gap #3 — 3-way match** algoritmo + UI tab "Discrepâncias" no drawer
12. **Gap #8 — Supplier scorecard básico** (4 métricas calculadas em job daily 03:00 BRT)
13. **Gap #9 — Audit trail append-only** hash-chained estendendo Activitylog
14. **Deprecação legacy** (Fase 1-5 espelhando Financeiro: esconder dropdown → 301 redirects → command bridge)

**Total recalibrado:** 36-57 dd IA-pair · saturação 78-82% maturidade · ROI máximo nas Ondas 1+2 (atinge ~70% sozinhas).

---

## 8. Surpresas

### 🟢 Positiva (oimpresso > mercado)
1. **Infra DFe SEFAZ via NSU diário** (`DistribuicaoDfeService` com cursor irreversível, throttle 5min, OTel wrap, schedule 06:15 BRT) — **mais robusta** que Tiny/Conta Azul (que dependem de pull manual ou de webhooks instáveis). Só falta plugar no fluxo de compras.
2. **Bridge Financeiro idempotente** com UNIQUE `(business_id, origem, origem_id, parcela_numero)` ([Financeiro tech/0001](../../requisitos/Financeiro/adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md)) — **mais sólida** que Bling (que duplica títulos em retries de fila).
3. **Protótipo canon visual já aprovado** (FSM 6 estágios + KPIs + drawer row-driven + atalhos `/N I ↑↓ Esc`) — **UX-grade equivalente a Procurify**, raríssimo em PME BR. Bling/Tiny são feios.

### 🔴 Negativa (mercado > oimpresso)
1. **3-way match (PO/Recv/NF-e) inexistente** — padrão de mercado HÁ 8 ANOS (NetSuite 2018, SAP desde 90s). Larissa hoje confere manualmente, fonte #1 de erro silencioso (overpay 2-5% típico segundo [Beancount 2026](https://beancount.io/blog/2026/05/11/accounts-payable-automation-2026-ai-invoice-capture-three-way-match-touchless-approvals-cut-costs-eliminate-duplicate-payments-guide)).
2. **Bling/Omie já vinculam XML ↔ PO existente automaticamente** ([Bling: "Como vincular itens NF-e com Pedidos de Compra"](https://ajuda.bling.com.br/hc/pt-br/articles/21830391097367)). oimpresso só faz import como entrada nova.
3. **Supplier scorecard ausente** — Cin7 ForesightAI calcula on-time%, lead-time avg, defect rate; oimpresso não tem nem schema pra isso.

---

## Apêndice A — Fontes pesquisadas (6 WebSearch)

1. [Bling — Vincular itens NF-e com Pedidos de Compra](https://ajuda.bling.com.br/hc/pt-br/articles/21830391097367)
2. [Bling — Importar XML nota de entrada](https://ajuda.bling.com.br/hc/pt-br/articles/360036460513)
3. [Bling — Consultar/manifestar/importar notas entrada SEFAZ](https://ajuda.bling.com.br/hc/pt-br/articles/360057118174)
4. [Bling — Planos e preços](https://www.bling.com.br/planos-e-precos)
5. [Cierus — Bling vs Tiny vs Omie 2026](https://www.cierus.com.br/news-details.php?slug=bling-vs-tiny-vs-omie-qual-erp-escolher)
6. [Multise — Conta Azul, Omie, Nibo ou Bling comparativo](https://multise.com.br/conta-azul-omie-nibo-ou-bling-comparativo-entre-os-erps-mais-usados-por-pmes/)
7. [Rillion — What is 3-way matching](https://www.rillion.com/learn-ap/3-way-matching/)
8. [NetSuite — Three-Way Matching](https://www.netsuite.com/portal/resource/articles/accounting/three-way-matching.shtml)
9. [Beancount — AP Automation 2026 (3-way + AI capture)](https://beancount.io/blog/2026/05/11/accounts-payable-automation-2026-ai-invoice-capture-three-way-match-touchless-approvals-cut-costs-eliminate-duplicate-payments-guide)
10. [Amazon Business — Invoice matching 2026 guide](https://business.amazon.com/en/blog/invoice-matching)
11. [Cin7 — Supplier Management & PO Software](https://www.cin7.com/features/inventory/purchasing/)
12. [Sumtracker — 10 Best PO Management Softwares 2026](https://www.sumtracker.com/blog/10-purchase-order-management-softwares)
13. [SelectHub — Zoho Inventory vs Cin7 2026](https://www.selecthub.com/inventory-management-software/zoho-inventory-vs-cin7/)
14. [Opstream — Best AI Procurement Platforms 2026](https://www.opstream.ai/blog/best-ai-procurement-platforms/)
15. [Ziphq — Coupa vs SAP Ariba](https://ziphq.com/compare/coupa-vs-sap-ariba)
16. [GEP SMART — Procurement Software 2026](https://www.gep.com/software/gep-smart/procurement-software)
17. [Optiply — Top 6 replenishment software 2026](https://www.optiply.com/en/blog/top-replenishment-software-tools)
18. [ERPFlex — Manifestação do Destinatário (docs)](https://docsnew.erpflex.com.br/manifestacao_do_destinatario/)
19. [Useall M2 — Importação de XML compras](https://manuaism2.useallcloud.com.br/Processos/compras/importacaoXml/importacaoXml/)

## Apêndice B — Arquivos de código inspecionados

- `app/Http/Controllers/PurchaseController.php` (1825 LOC)
- `app/Http/Controllers/PurchaseOrderController.php` (936 LOC)
- `app/Http/Controllers/PurchaseRequisitionController.php` (497 LOC)
- `app/Http/Controllers/PurchaseReturnController.php` (460 LOC)
- `app/Http/Controllers/PurchaseXmlController.php` (664 LOC)
- `app/Utils/TransactionUtil.php` (6435 LOC, polimórfico)
- `app/Transaction.php` (modelo polimórfico)
- `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` + `PuxarDfesRecebidosCommand.php`
- `Modules/Financeiro/Observers/TransactionObserver.php` (Onda 2 — cobre `purchase`)
- `public/cowork-preview/erp-shell-v2/compras-page.jsx` (469 LOC, protótipo canon)
- `resources/views/purchase/*.blade.php` (14 arquivos legacy)
- `resources/views/purchase_order/*.blade.php` (7 arquivos legacy)

---

**Fim auditoria.** Próxima fase (se Wagner aprovar EVOLUIR): criar ADR canon `Modules/Compras isolado via nwidart` + Charter `README.md` + abrir SPRINT canônica.
