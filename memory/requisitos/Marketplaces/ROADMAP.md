---
module: Marketplaces
artefato: ROADMAP
status: feature-wish
lifecycle: aguarda-sinal-qualificado
related_spec: SPEC.md
related_proposal: ../../decisions/proposals/drafts/marketplaces-modulo-cross-vertical.md
related_matrix: MATRIZ-ROI.md
last_review: 2026-05-12
---

# ROADMAP Marketplaces (planejado — não existe) — 5 fases CONDICIONAL

> ⚠️ **NÃO IMPLEMENTAR.** Roadmap antecipatório — só vira backlog ativo quando gatilho [SPEC §11.1](SPEC.md#111-sinal-qualificado-de-mercado-adr-0105) for satisfeito (sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). Sem cliente piloto pagante, **viola ADR 0105**.
>
> Estimates IA-pair fator 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) com margem 2x sobre observação real 15-20x. Tarefas humano-limitadas (smoke real, canary 7d, contador valida CFOP) mantêm relógio do mundo real.

## Pré-fase — Gatilho (NÃO codar até satisfeito)

**Disparar quando 1+ cenário materializar:**

1. **Larissa (ROTA LIVRE biz=4) confirma** querer vender ML/Shopee — Wagner pergunta em call dedicada
2. **1+ OfficeImpresso saudável** (Vargas/Extreme/Gold/Mhundo/Produart) reporta "vendo ML, preciso integrar com NFe automática"
3. **3+ outreach inbound** em 90d sobre integração ML

**Adicional pré-fase:**

- Wagner aprova D1-D8 do [ADR proposal](../../decisions/proposals/drafts/marketplaces-modulo-cross-vertical.md)
- Wagner+contador validam mapping CFOP marketplace [SPEC §5](SPEC.md#5-cfop-fiscal-marketplace)
- ComVis ter 1ª piloto verde (Q4/26 estimado) — WIP team

**ADR de ativação** ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)): canon `Marketplaces-ativacao-cross-vertical` formaliza ativação + cria batch tasks MCP via `tasks-create`.

---

## Fase 1 — Schema fundação + OAuth2 ML + 1 anúncio manual (proof of concept)

**Objetivo:** mostrar pra Wagner/Larissa "conectei ML, vejo meu nome de vendedor, importei 1 anúncio manual oimpresso ↔ ML".

**Effort total:** **17h** IA-pair ≈ 2-3 dias úteis Felipe.

**US incluídas:**

| ID | Feature | Effort | DoD essencial |
|---|---|---:|---|
| US-MKT-001 | Schema fundação 9 tabelas + Models global scope | 4h | Migrations rodam OK + Pest isolation biz=1 vs biz=99 verde |
| US-MKT-002 | Catálogo `mkt_marketplaces` seed 3 drivers | 2h | Seeder cria ML+Shopee+Amazon BR; UI superadmin lista |
| US-MKT-003 | OAuth2 Mercado Livre + refresh job daily | 6h | Page Connect funcional + tokens encrypted + refresh job daily 5h BRT |
| US-MKT-005 | Importar 1 anúncio manual ML (POC) | 5h | Cliente cola MLB123 + sistema busca via API + lista produto oimpresso candidato match |

**Smoke prod (humano-limitado):**

- Cliente piloto cria app em developers.mercadolivre.com.br
- Wagner config `MERCADOLIVRE_APP_ID` + `MERCADOLIVRE_APP_SECRET` em .env Hostinger
- Cliente faz OAuth flow + autoriza app
- Cliente cola 1 `MLB12345` real seu → importa → vê produto sugerido oimpresso match
- **Critério sucesso:** OAuth conecta + 1 anúncio importado + zero erro em log

**Pré-requisito Fase 2:** Wagner aprova smoke verde + Larissa/cliente piloto confirma "vamos pra próxima".

---

## Fase 2 — Webhooks orders + auto-criação Transaction + FSM stages

**Objetivo:** "pedido ML cai → aparece em Sells/Index oimpresso → emite NFe automática".

**Effort total:** **32h** IA-pair ≈ 4-5 dias úteis Felipe.

**US incluídas:**

| ID | Feature | Effort | DoD essencial |
|---|---|---:|---|
| US-MKT-006 | Webhook receiver ML + HMAC + idempotency | 5h | Endpoint público `/webhooks/marketplace/mercado_livre` valida HMAC + persiste `mkt_webhook_log` append-only |
| US-MKT-007 | Pedido ML → Transaction UltimatePOS + FSM stage initial | 8h | Job `ProcessMercadoLivreOrderJob` cria tx + linha + contact buyer (PII hash) + stage `pedido_ml_recebido` |
| US-MKT-008 | UI Page Orders Cockpit V2 + drawer FSM | 6h | Lista + filtros + drawer timeline + bulk actions |
| US-MKT-009 | NFe automática CFOP resolver + dispatch SEFAZ | 7h | Listener emite NFe via Modules/NfeBrasil + CFOP correto + ML recebe XML via POST invoices |
| US-MKT-010 | Sync rastreio Correios + auto-fechamento | 6h | Cron `marketplaces:sync-shipments` + status_correios sync + trigger FSM `confirmar_entrega_ml` |

**Setup FSM canon** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)):

- Seeder novo `FsmProcessoVendaMarketplaceSeeder` per business — 6 stages × 8 actions × 5 roles
- Coexiste com `venda_com_producao` e `venda_simples` (multi-process per business via `transactions.sale_process_id`)
- Migration `transactions.sale_process_id` já existe (ADR 0143) — só seeder novo + Pest

**Smoke prod (humano-limitado):**

- Cliente cria 1 pedido real ML (R$ baixo valor) via conta sandbox ML
- Webhook chega oimpresso (verificar `mkt_webhook_log`)
- Sells/Index mostra pedido com stage `pedido_ml_recebido`
- Cliente clica "Marcar enviado" → NFe emitida → cstat 100 SEFAZ → ML recebe XML
- **Critério sucesso:** 5 pedidos sucessivos end-to-end sem erro + NFe cstat 100 em todos

**Pré-requisito Fase 3:** Wagner aprova smoke + cliente piloto reporta "uso diário OK".

---

## Fase 3 — Sync rastreamento avançado + Reconciliação financeira + Reputação

**Objetivo:** "vejo previsão fluxo caixa marketplace + reputação ML em dashboard + estoque sincronizado automaticamente".

**Effort total:** **31h** IA-pair ≈ 4-5 dias úteis Felipe.

**US incluídas:**

| ID | Feature | Effort | DoD essencial |
|---|---|---:|---|
| US-MKT-011 | Sync estoque oimpresso → ML (push debounced) | 8h | Event StockAdjusted → SyncListingStockJob + debounce 60s + lock per item_id |
| US-MKT-012 | Sync estoque ML → oimpresso (pull webhook) | 4h | Webhook `items` topic dispara SyncListingFromMarketplaceJob (uso Full ML) |
| US-MKT-015 | Reconciliação Mercado Pago split D+X (AR 2 buckets) | 8h | AR pendente + AR confirmado segregados + cron `marketplaces:reconcile-payouts` daily + dashboard previsão |
| US-MKT-016 | Reputação ML monitoring + alerta SLA | 5h | Cron daily snapshot + alerta WhatsApp/email se queda + UI dashboard 90d |
| US-MKT-017 | Pricing rules per marketplace (markup) | 6h | Schema `mkt_pricing_rules` + service `MarketplacePricingService` aplica markup_pct |

**Decisões D2/D3/D6 do ADR proposal aplicadas:**

- D2: one-way pull ainda dominante (US-MKT-011 push opt-in via flag `MARKETPLACES_BIDIR_SYNC` per business)
- D3: estoque Full ML suportado (US-MKT-012 + ajuste CFOP 5910/6910)
- D6: AR 2 buckets implementado (US-MKT-015)

**Smoke prod (humano-limitado):**

- Cliente vende 10 pedidos ML em 1 semana
- Mercado Pago paga 7 deles (3 ainda pending) → dashboard mostra "R$ X confirmado / R$ Y pendente D+12"
- Cliente ajusta estoque produto oimpresso → vê ML atualizar em <2min
- Cliente recebe alerta "reputação caiu pra 4/5" via WhatsApp Jana
- **Critério sucesso:** 0 discrepância recon split + 0 sync loop infinito + alerta reputação trigger 1 vez

**Pré-requisito Fase 4:** 1+ cliente piloto pagando R$ [redacted Tier 0]/m add-on Marketplace Start (revenue real).

---

## Fase 4 — Shopee + Amazon BR (driver pattern em ação)

**Objetivo:** "cliente conecta Shopee OU Amazon BR e tudo funciona idêntico ML".

**Effort total:** **28h** IA-pair ≈ 4 dias úteis Felipe.

**US incluídas:**

| ID | Feature | Effort | DoD essencial |
|---|---|---:|---|
| US-MKT-004 | OAuth2 Shopee + Amazon BR drivers | 6h | Drivers `ShopeeDriver` + `AmazonBrDriver` herdam `MarketplaceDriver` abstract |
| US-MKT-021 | Shopee orders webhook + NFe completo | 10h | Idem ML pattern — webhook + transaction + NFe + tracking |
| US-MKT-022 | Amazon BR orders webhook + NFe (SP-API) | 12h | LWA + IAM role + AWS Sig V4 + getInvoice API |

**Validação driver pattern:**

- Driver `MarketplaceDriver` abstract com métodos `oauth_url()`, `exchange_code()`, `refresh_token()`, `webhook_validate()`, `fetch_order()`, `push_invoice()`, `fetch_shipment()`, `fetch_reputation()`
- Implementação Shopee + Amazon = adicionar driver + seed em `mkt_marketplaces` + UI Connect aparece automático
- **Sem refactor core** — validação arquitetura

**Smoke prod (humano-limitado):**

- Cliente conecta Shopee (OAuth) + faz 1 pedido teste
- Cliente conecta Amazon BR (LWA flow mais chato) + faz 1 pedido teste
- Ambos fluxos chegam oimpresso + NFe emitida + tracking sync
- **Critério sucesso:** ambos pipelines verde + zero divergência arquitetura

**Pré-requisito Fase 5:** 3+ clientes pagando add-on + revenue R$ [redacted Tier 0]-1.500/m comprovado.

---

## Fase 5 — Reputação avançada + Analytics + Jana diferenciais + Magalu/Americanas

**Objetivo:** "Jana responde 'quantos pedidos ML hoje?' + analytics margem real per SKU + Magalu integrado".

**Effort total:** **80h** IA-pair ≈ 10-12 dias úteis Felipe.

**US incluídas:**

| ID | Feature | Effort | DoD essencial |
|---|---|---:|---|
| US-MKT-013 | Anúncio em lote (bulk create) | 10h | Page Bulk seleciona N produtos + categoria + listing_type + chunks 50 itens |
| US-MKT-014 | Disputa workflow completo | 12h | Webhook claims + UI evidência + FSM disputas + RefundCobrancaJob |
| US-MKT-018 | **Jana tool relatórios IA marketplaces** (DIFERENCIAL #1) | 4h | Tool `marketplaces.consulta` responde via Modules/Jana ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) |
| US-MKT-019 | Etiquetas envio ML Coletas + bulk print | 6h | GET shipments label PDF + bulk 20 etiquetas/página |
| US-MKT-020 | **Pipeline produção sob demanda ML (ComVis)** (DIFERENCIAL #2) | 8h | Pedido ComVis vendido ML → FSM `venda_com_producao` stage inicial — caso cliente ComVis vende impressão sob demanda |
| US-MKT-023 | Pricing dinâmico copilot competitor watch | 14h | Cron monitora preço concorrentes + Jana sugere ajuste + PolicyEngine REQUIRE_HUMAN_REVIEW |
| US-MKT-024 | Analytics margem real (CMV + frete + taxa + tributos) | 10h | Dashboard margem per SKU/categoria/marketplace + DRE agregado |
| US-MKT-025 | Magalu Hub + Americanas drivers | 16h | 2 drivers novos via mesmo pattern |

**Diferenciais únicos consolidados:**

- **US-MKT-018 (Jana IA)** — wedge primário, ROI=62.5 ([MATRIZ-ROI §Top 5](MATRIZ-ROI.md))
- **US-MKT-020 (ComVis pipeline)** — único módulo cross-vertical que conecta ML → produção sob demanda. Caso uso: Larissa (Vestuario) ou cliente ComVis vende produto personalizado em ML, oimpresso recebe pedido, dispara fluxo produção, envia ao buyer. Nenhum hub atual entende esse fluxo.

**Smoke prod (humano-limitado):**

- Wagner pergunta no WhatsApp Jana: "quantos pedidos ML hoje da Larissa?" → resposta instant
- Cliente ComVis vende banner personalizado ML → pedido cai → FSM produção dispara → produzido → enviado → fechado
- Cliente ativa Magalu Hub → vende 5 pedidos teste → tudo funciona
- **Critério sucesso:** Jana acerta 95%+ perguntas relatório; pipeline ComVis ML end-to-end OK; Magalu sem regressão

**Pós-Fase 5:**

- 8-15 clientes pagantes Marketplace add-on
- ARR add-on R$ [redacted Tier 0]-80k contributing pra [ADR 0022 meta R$ [redacted Tier 0]M/ano](../../decisions/0022-meta-5mi-ano-financeira.md)
- Marketing pode mostrar diferencial real "ERP modular + IA conversacional + marketplace nativo" — feature pricing vs Tiny/Bling

---

## Total roadmap CONDICIONAL

| Fase | Effort (h) | Wallclock | Cumulative |
|---|---:|---|---:|
| Fase 1 — Fundação + OAuth | 17h | 2-3d | 17h |
| Fase 2 — Webhooks + NFe + Tracking | 32h | 4-5d | 49h (MVP entregável) |
| Fase 3 — Sync + Recon + Reputação | 31h | 4-5d | 80h |
| Fase 4 — Shopee + Amazon | 28h | 4d | 108h |
| Fase 5 — Diferenciais + Magalu | 80h | 10-12d | **188h** |

**Total módulo:** ~188h IA-pair ≈ 23-24 dias úteis Felipe (vs ~190 dias humano sem IA-pair).

**MVP entregável (Fase 1+2):** **49h ≈ 6-8 dias** — pipeline ML funcional mostrável Larissa/Vargas no piloto.

**Humano-limitado (NÃO inclusos):**

- Setup contrato com cliente piloto + suporte presencial inicial
- Validação contador CFOP marketplace antes 1º cliente
- Canary 7d em produção biz=4 ou cliente OfficeImpresso (ADR 0143 estilo)
- Monitor 30d métricas (ROI, churn, NFe-cstat-100-rate, sync-loop-incidents)
- Smoke real cliente concreto fazer 50+ pedidos cada fase

## Métricas de gate fase-a-fase

| Fase | Métrica gate antes próxima |
|---|---|
| Fase 1 → 2 | Wagner aprova smoke; OAuth conecta; 1 anúncio importado sem erro |
| Fase 2 → 3 | 5 pedidos end-to-end sucessivos sem erro; NFe cstat 100 em todos; cliente piloto valida |
| Fase 3 → 4 | 1+ cliente pagando R$ [redacted Tier 0]/m add-on Marketplace Start; recon split 0 discrepância; sync 0 loop infinito |
| Fase 4 → 5 | 3+ clientes pagando add-on; revenue R$ [redacted Tier 0]-1.500/m; Shopee+Amazon paridade arquitetural ML |
| Pós-Fase 5 | 8-15 clientes; ARR R$ [redacted Tier 0]-80k; Jana 95%+ relatórios; pipeline ComVis ML funcional |

## Anti-padrões de execução

1. ❌ **Pular Fase 1 e ir direto pra Fase 4** — schema mal calibrado custa refactor caro
2. ❌ **Suportar 3 marketplaces simultâneos antes ML funcionar** — driver pattern só prova valor na fase 4
3. ❌ **Pricing tier definitivo antes Fase 3 com 1+ cliente real** — calibração mercado precisa sinal pagante
4. ❌ **Skip smoke prod humano** entre fases — bug que escapa Pest aparece em load real cliente
5. ❌ **Construir Fase 5 (Jana IA) antes Fase 1-3** — sem base, IA grounding é teatro

## Riscos roadmap

1. **Sinal qualificado não materializa em 12m** → arquivar SPEC `historical` ([ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md))
2. **API ML breaking change durante fase 3** → custo refactor driver isolado (mitigado driver pattern)
3. **Cliente piloto perde reputação ML por bug oimpresso** → racha confiança; mitigar com US-MKT-016 fase 3 alerta proativo + canary 7d
4. **Mercado Pago split conta cliente errado** → causa contábil cliente; mitigar US-MKT-015 AR 2 buckets + contador valida antes ativar
5. **CFOP errado SEFAZ multa cliente** → Wagner+contador valida mapping antes 1º cliente (pré-fase obrigatória)

## Sucesso roadmap = 12 meses pós-ativação

- **3+ clientes pagantes Marketplace add-on** (R$ [redacted Tier 0]-399/m)
- **ARR add-on R$ [redacted Tier 0]-80k** (1-1.6% da meta R$ [redacted Tier 0]M/ano [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md))
- **Reputação ML média clientes ≥4/5 verde**
- **Bug crítico produção <1/trimestre** (Pest gate ADR 0094)
- **Cliente vertical (ComVis/Vestuario) NÃO migra pra Tiny/Bling** — retention proxy módulo entregando valor

---

**Última atualização:** 2026-05-12 — roadmap inicial CONDICIONAL. Aguardando sinal qualificado [SPEC §11.1](SPEC.md#111-sinal-qualificado-de-mercado-adr-0105) materializar. Revisar trimestralmente. Se 12m sem ativação, arquivar `historical`.
