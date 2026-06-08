---
slug: marketplaces-modulo-cross-vertical
number: TBD
title: "Modules/Marketplaces — cross-vertical (ML + Shopee + Amazon BR) integração canônica"
type: adr
status: proposed
authority: canonical
lifecycle: draft
decided_by: []
decided_at: null
quarter: 2026-Q3-or-Q4
module: Marketplaces
tags: [marketplaces, mercado-livre, shopee, amazon, cross-vertical, oauth2, webhooks, nfe, fsm, fiscal, multi-tenant]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0143, 0121, 0094, 0093, 0105, 0106, 0035, 0011, 0089, 0117, 0119, 0129]
pii: false
review_triggers:
  - "Sinal qualificado §5 satisfeito (Larissa ROTA LIVRE confirma ML/Shopee OU 1+ OfficeImpresso saudável reporta dor real)"
  - "Tiny/Bling/Olist anunciam IA Jana-style → janela de diferencial fecha → repensar tese"
  - "Mercado Livre API v2 breaking change >5 endpoints simultâneos → custo manutenção driver supera valor"
---

# ADR Proposta — Modules/Marketplaces cross-vertical (ML + Shopee + Amazon BR)

> **STATUS: `proposed` (não-canônica até Wagner aprovar).** Esta proposta documenta **decisões pendentes D1-D8** que precisam ser resolvidas ANTES de scaffoldear Modules/Marketplaces. Sinal qualificado §5 é pré-requisito de ativação ([ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md)).

## Contexto

[SPEC Marketplaces](../../../requisitos/Marketplaces/SPEC.md) propõe `Modules/Marketplaces` como módulo **cross-vertical canônico** — qualquer vertical oimpresso (Vestuario / OficinaAuto / Autopecas / ComunicacaoVisual) pode vender via ML/Shopee/Amazon sem sair do oimpresso.

Pesquisa mercado externa (Tiny, Bling, Conta Azul, Olist, Shopee Open Platform, ML Developers, Amazon SP-API, CFOP SEFAZ) revela:

- **Tiny ERP** (Olist) líder marketplace BR — 40+ integrações + 100 ecosystem, R$ 49-639/m
- **Bling** 300k+ users, 250+ marketplaces, R$ 55+/m, bugs sync alto volume reportados
- **Conta Azul** financeiro forte mas marketplace raso
- **Olist** AI pricing premium grandes sellers
- **MagaluHub/eNotas/UpSeller/Anymarket** especialistas nichos diferentes

**Gap detectado:** nenhum hub atual entrega (a) multi-tenant Tier 0 [ADR 0093](../../0093-multi-tenant-isolation-tier-0.md) (b) IA conversacional Jana-style (c) profundidade vertical (vestuário/auto/comvisual) preservada.

**Risco oposto:** Tiny/Bling cobrem ~95% do que cliente SMB pede. Construir Modules/Marketplaces sem sinal qualificado é distração — viola [ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md).

## Decisões pendentes (D1-D8)

### D1 — Único módulo `Marketplaces` vs per-marketplace módulos

**Opção A (recomendada)**: 1 módulo `Modules/Marketplaces` + **driver pattern** (`MercadoLivreDriver`, `ShopeeDriver`, `AmazonBrDriver` implementando interface `MarketplaceDriver`).

**Opção B**: módulos separados `Modules/MercadoLivre`, `Modules/Shopee`, `Modules/Amazon` independentes.

**Análise:**

| Critério | Opção A (único + drivers) | Opção B (per-marketplace) |
|---|---|---|
| Schema compartilhado | ✅ tabelas `mkt_*` reusam todos drivers | ❌ duplicação `ml_orders`, `shopee_orders` |
| Code reuse OAuth/Webhook | ✅ pattern abstrato | ❌ reimplementa cada |
| FSM integration | ✅ 1 processo seed `venda_marketplace` | ❌ N processos |
| Vending separately | 🟡 add-on per-marketplace via flag | ✅ natural per módulo |
| Manutenção API breaking change | ✅ driver atualiza isolado | ✅ módulo atualiza isolado |
| Onboarding cliente | ✅ "ative Marketplaces, escolha quais" | ❌ "instale 3 módulos" |
| Pricing tier | ✅ "+R$ 49 → 1 mkt / +R$ 149 → 3 mkts" | ❌ R$ X+Y+Z somar |

**Recomendação D1**: ✅ **Opção A (único módulo + driver pattern)** — alinha com nWidart pattern + ADR 0121 modular sancionado + permite "escolher quais marketplaces ativar" via UI por business.

**Pendente Wagner:** aprovar.

### D2 — Sync bi-direcional (oimpresso ↔ ML) vs one-way pull

**Opção A**: bi-direcional completo — anúncio criado oimpresso → push ML; ajuste estoque oimpresso → push ML; pedido ML → pull oimpresso; estoque Full ML → pull oimpresso.

**Opção B**: one-way pull apenas — só recebe pedidos ML → cria transactions oimpresso; anúncios criados manual via ML Painel; estoque manual.

**Análise:**

| Critério | Bi-direcional | One-way pull |
|---|---|---|
| Esforço build | Alto (US-MKT-011, 012, 013) | Médio (só webhook + orders) |
| Risco sync loop infinito | 🔴 alto (precisa debounce) | ✅ zero |
| Match Tiny/Bling | ✅ paridade | ❌ piora atrás |
| Diferencial vs concorrente | 🟡 igual | ❌ pior |
| Manutenção rate limit | 🔴 complexa | ✅ trivial |
| ROI 1º cliente | 🟡 só vale com volume alto | ✅ entrega valor já |

**Recomendação D2**: **Híbrido faseado** — Fase 1-3 one-way pull (orders + NFe + tracking); Fase 4+ bi-direcional opt-in via feature flag `MARKETPLACES_BIDIR_SYNC=true` per business. Riscos sync loop endereçados antes ativar.

**Pendente Wagner:** aprovar fasing.

### D3 — Estoque Full ML (consignação) vs estoque próprio

**Opção A**: suportar ambos modos — `me1`/`me2`/`me2_full` selecionável per listing.

**Opção B**: só estoque próprio inicialmente (`me1`/`me2` Coletas) — Full vira P3 feature-wish.

**Análise:**

ML Full = vendedor envia estoque pro CD ML, ML faz fulfillment inteiro (picking, packing, shipping). Mudanças importantes:

- Estoque vira **consignação** (CFOP 5910/6910 remessa)
- Devolução não-vendida volta via NFe retorno (CFOP 1411/2411)
- ML deduz armazenagem se produto fica >90 dias sem venda
- Cliente recebe mais rápido + frete grátis garantido → reputação ML ⬆
- Margem ⬇ pela armazenagem + fees Full

**Recomendação D3**: **Opção A com ambos suportados desde fase 1** — schema `mkt_orders.shipping_type` já modela. Implementação CFOP 5910 (remessa Full) e CFOP 1411 (retorno) em fase 2 (US-MKT-013 ajuste). Cliente ativa per listing.

**Pendente Wagner:** aprovar Full suport desde fase 1.

### D4 — Auto-anunciar produtos oimpresso OU manual cadastro per anúncio

**Opção A**: auto — cliente marca `produtos.publicar_marketplaces = ['ml', 'shopee']` no produto UltimatePOS, job daily cria/atualiza anúncio.

**Opção B**: manual — UI explícita "Criar anúncio ML" com escolha categoria + atributos + listing_type per produto.

**Análise:**

| Critério | Auto | Manual |
|---|---|---|
| UX onboarding | ✅ "1 click ativar" | ❌ múltiplos passos |
| Risco anúncio errado | 🔴 alto (categoria errada, atributo faltando) | ✅ controle humano |
| Aprovação ML | 🔴 muitos rejeitados sem atributos | ✅ atributos preenchidos |
| Match Tiny/Bling | ❌ ambos têm manual | ✅ igual |
| Bulk operations | ✅ 1000 produtos em 1 cron | 🟡 UI bulk select OK |

**Recomendação D4**: **Manual default** + **Bulk import via Page** (US-MKT-013 fase 2). Auto-create requires preset `product.marketplace_template_id` apontando pra `mkt_listing_templates` (extensão futura US-MKT-024).

**Pendente Wagner:** aprovar manual-first.

### D5 — NFe CFOP marketplace auto vs manual escolha

**Opção A**: auto via `MarketplaceCFOPResolver` mapping §5 SPEC; override manual via UI.

**Opção B**: manual em todo pedido — atendente escolhe CFOP per pedido.

**Análise:**

CFOP marketplace é confuso (Portaria CAT SP Nº 59 06/07/2018 + casos edge per estado). Contador cliente geralmente prefere mapping pré-validado. Mas mapping errado = multa SEFAZ.

**Recomendação D5**: **Auto com override Wagner-aprovado**. Mapping inicial sugerido SPEC §5; cliente revê com contador antes ativar; UI mostra CFOP escolhido + permite override per pedido se atendente identificar caso especial. Audit log toda override.

**Pendente Wagner:** aprovar + Wagner+contador valida mapping inicial antes 1º cliente.

### D6 — Mercado Pago split (12-60 dias) — quando contabilizar AR?

**Opção A**: AR no momento pedido `paid` ML — confiável Mercado Pago vai pagar D+X.

**Opção B**: AR só quando `money_release_date` chega (D+12 a D+60) — caixa-base contábil.

**Opção C**: 2 buckets — `AR pendente futuro` (D+X) + `AR confirmado` (atual).

**Análise:**

Larissa (ROTA LIVRE) ou OfficeImpresso típico SMB faz regime caixa, não competência. Inflar AR com R$ 50k "Mercado Pago vai pagar em 60d" distorce DRE + cash flow.

**Recomendação D6**: **Opção C — 2 buckets distintos** com label clara:
- `AR Pendente Marketplace` (expected_date = money_release_date) — não conta em "vencidos hoje" nem inflate AR atual
- `AR Confirmado` — quando Mercado Pago confirma payout via API → vira AR normal
- Dashboard Financeiro segrega "Recebíveis confirmados / Recebíveis previsão D+1, D+7, D+14, D+30"

**Pendente Wagner+contador:** validar tratamento contábil.

### D7 — Reputação ML monitoring + alerta SLA

**Opção A**: snapshot diary + alerta drift via WhatsApp/email; nada mais.

**Opção B**: ativo — Jana sugere ações ("você atrasou 2 pedidos esta semana, prioriza envio") + workflows preventivos.

**Análise:**

Reputação ML é critical revenue driver — verde escuro libera frete grátis ML banca + destaque busca; vermelho = perda venda. Cliente típico SMB perde reputação por desatenção (deixa pedido sem enviar 3+ dias). Alerta proativo = valor real.

**Recomendação D7**: **Opção A em fase 1-3**, **Opção B fase 5** (Jana action sugerida via ContextSnapshotService + PolicyEngine `REQUIRE_HUMAN_REVIEW`). Não automatizar ação sem humano.

**Pendente Wagner:** aprovar fasing.

### D8 — Pricing dinâmico (preço ML diferente de loja física)

**Opção A**: 1 preço único produto (oimpresso products.sell_price) + markup_pct per marketplace adicional.

**Opção B**: preços independentes per canal (loja, ML, Shopee, Amazon) — campo separado per listing.

**Análise:**

Mercado típico:
- Cliente cobra mais em ML porque taxa ML embute (preço produto + ~17% pra cobrir taxa Premium + custo fixo)
- Cliente cobra menos em Amazon BR pra ganhar Buy Box
- Shopee tem frete subsidiado então cliente cobra mais embutido pra compensar margem

**Recomendação D8**: **Opção B com helper Opção A** — schema `mkt_listings.price_marketplace` é fonte verdade per anúncio; `mkt_pricing_rules` (SPEC §3.8) automatiza cálculo via markup_pct. Cliente decide override manual ou regra automática.

**Pendente Wagner:** aprovar pricing rules.

## Pré-requisitos pra aprovação canônica

### Sinal qualificado de mercado ([ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md))

**Pelo menos 1 cenário:**

1. **Larissa (biz=4) confirma** querer vender ML/Shopee — Wagner pergunta em call dedicada (Q3/26)
2. **1+ OfficeImpresso saudável** (Vargas/Extreme/Gold/Mhundo/Produart) reporta "vendo em ML, preciso integrar com NFe automática"
3. **3+ outreach inbound** em 90d sobre integração ML

### Capacidade time

WIP atual: ComVis em construção + OficinaAuto V0 + MWART Financeiro + Vestuario live (manutenção). Marketplaces ativa **após** ComVis 1ª piloto verde (Q4/26 estimado).

### Aprovação Wagner

- Resolver D1-D8 acima
- Validar mapping CFOP §5 SPEC com contador
- Aprovar pricing tier add-on (R$ 49 / R$ 149 / R$ 399)
- Status `proposed` → `accepted` quando todos acima OK

## Alternativas avaliadas

### A — Não construir Modules/Marketplaces; recomendar Tiny/Bling para clientes

- ✅ Zero esforço; foco em vertical
- ✅ Tiny/Bling maduros
- ❌ Perde cliente oimpresso vertical que vende ML → sai pra Tiny
- ❌ Sem multi-tenant Tier 0 cliente fica vulnerável
- ❌ Sem IA conversacional Jana
- 🟡 Recomendável se sinal qualificado §5 não materializar em 12m

### B — Construir só Mercado Livre (sem Shopee/Amazon)

- ✅ 60-70% volume marketplace BR
- ❌ Concorrentes Tiny/Bling vendem todos juntos
- ❌ Cliente que migra Tiny → oimpresso perde 30-40% canais

### C — Modules/Marketplaces único + drivers ✅ ESCOLHIDO

- Resolução pragmática — schema compartilhado + driver pattern
- Permite "ativar Shopee depois" sem refactor
- Match esperado mercado

### D — Per-marketplace módulos (Modules/MercadoLivre, Modules/Shopee, Modules/Amazon)

- ❌ Duplicação massiva schema
- ❌ Onboarding pior cliente

## Consequências

### Positivas (se aprovado)

1. Cliente oimpresso vertical (Vestuario/Auto/ComVis) **mantém oimpresso como ERP único** mesmo vendendo marketplace — não migra pra Tiny
2. Multi-tenant Tier 0 preservado (cada business credenciais isoladas) — diferencial vs Tiny/Bling (que são multi-empresa SQL com risco vazamento)
3. Jana IA responde "quantos pedidos ML hoje?" — feature **nenhum concorrente entrega**
4. FSM canon ADR 0143 absorve marketplace path sem big-bang — leve adição processo seed
5. CFOP marketplace pré-validado contador = cliente sem dor SEFAZ
6. Add-on cross-vertical = preço modular ([ADR 0121](../../0121-oimpresso-modular-especializado-por-vertical.md) Princípio P5)

### Negativas

1. **Custo manutenção recorrente** — APIs ML/Shopee/Amazon mudam 5-15x/ano; ~20h/mês engenheiro manter drivers atualizados
2. **Rate limit 1500/min** ML obriga queue + debounce — complexidade engenharia
3. **CFOP errado risco multa** SEFAZ — exige validação contador antes ativar
4. **Mercado Pago split D+0..D+60** complexifica AR contábil — exige Opção C D6 (2 buckets) bem implementada
5. **Refresh token 90d** exige rotação proativa — cliente vê reauthorize pop-up = atrito UX
6. **Distração de Modules vertical depth** — risco oimpresso virar "mais um hub raso" se foco escorrega

### Mitigações

1. **Driver versionado** + Pest contract tests + alerta CI quando API ML responde 4xx em endpoint usado
2. **Queue Redis com lock per item_id** + debounce 60s
3. **CFOP resolver per-business com override** + audit log mudanças + contador valida mapping antes 1º cliente
4. **AR 2 buckets** + reconciliação cron daily + dashboard previsão fluxo
5. **Job daily refresh_token** quando expira <14d + alerta admin se job falha
6. **Module shared + driver per marketplace** preserva foco vertical — Modules/Marketplaces é **horizontal** que serve todos verticais

## Multi-tenant Tier 0 amarração ([ADR 0093](../../0093-multi-tenant-isolation-tier-0.md))

Toda tabela `mkt_*` (SPEC §3):
- ✅ `business_id` indexado + FK obrigatório
- ✅ Model com `HasBusinessScope` global scope
- ✅ Credenciais OAuth encrypted + per-business (`mkt_account_credentials.business_id`)
- ✅ Jobs async sempre recebem `$businessId` constructor (nunca `session()`)
- ✅ Webhooks identificam business via `mkt_account_credentials.seller_id_external` → resolve business_id
- ⛔ `withoutGlobalScopes` permitido APENAS com comentário `// SUPERADMIN: <razão>`

## FSM canon amarração ([ADR 0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

- Novo processo seed `venda_marketplace` (D2 Opção A) — 6 stages + 8 actions
- Reusa `ExecuteStageActionService` + `StageActionPolicy` + `sale_stage_history` (audit append-only)
- Actions críticas (`is_critical: true`): `marcar_enviado_ml`, `confirmar_entrega_ml`, `aceitar_disputa`, `defender_disputa`, `cancelar_pedido_ml`
- Side-effects: `CancelarVendaCascade` (já existe) + novo `EstornarMercadoPagoSplit` (P2)
- Pipeline coexiste com `venda_com_producao` (vertical ComVis pode vender produzido em ML — fase 4+)

## Métricas de sucesso (12m pós-ativação)

| Métrica | M0 ativação | M6 | M12 | Crítica |
|---|---|---|---|---|
| Clientes pagantes Marketplaces add-on | 1 piloto | 3-5 | **8-15** | <5 = re-avaliar tese |
| ARR módulo (add-on R$/ano) | R$ 1.8-4.8k | R$ 10-25k | **R$ 40-80k** | <R$ 20k = pivotar |
| Pedidos ML/mês ingest agregado | 100-500 | 1k-3k | **5k-10k** | (escala viabilidade) |
| Bug crítico produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Reputação ML clientes piloto | n/a | ≥4/5 green | **≥4.5/5** | (proxy qualidade integração) |
| API call ratio (calls vs limit) | <30% | <50% | <70% | (escalabilidade) |

## Lifecycle

- **Hoje (2026-05-12)**: `proposed` em `proposals/drafts/`
- **Q3/26**: Wagner valida sinal qualificado §5 (call Larissa + outreach OfficeImpresso)
- **Q4/26**: se sinal positivo, D1-D8 resolvidas → status `accepted` + ADR canon número final + scaffold US-MKT-001
- **12m sem sinal**: arquivar `historical` ([ADR 0095](../../0095-skills-tiers-convencao-interna.md) lifecycle)

## Refs

- SPEC: [memory/requisitos/Marketplaces/SPEC.md](../../../requisitos/Marketplaces/SPEC.md)
- MATRIZ-ROI: [memory/requisitos/Marketplaces/MATRIZ-ROI.md](../../../requisitos/Marketplaces/MATRIZ-ROI.md)
- ROADMAP: [memory/requisitos/Marketplaces/ROADMAP.md](../../../requisitos/Marketplaces/ROADMAP.md)
- ADR 0143, 0121, 0117, 0105, 0094, 0093, 0035, 0011, 0089, 0119, 0129
- [Mercado Livre Developers](https://developers.mercadolivre.com.br)
- [Shopee Open Platform](https://open.shopee.com)
- [Amazon SP-API](https://developer-docs.amazon.com/sp-api)

---

**Última atualização:** 2026-05-12 — proposta inicial em status `proposed`. Aguardando Wagner validar sinal qualificado §5 + decidir D1-D8 antes promoção pra ADR canon.
