---
slug: inventory-avancado-kits-batch-dimensional
number: TBD (atribuído ao promover pra accepted)
title: "Inventory avançado — Kits/BOM + Batch tracking + Dimensional + Stock Movements unified"
type: adr
status: proposed
authority: canonical
lifecycle: proposal
decided_by: []
decided_at: null
module: Inventory (cross-vertical)
tags: [inventory, estoque, bom, batch-tracking, dimensional, stock-movements, multi-tenant, fsm-integration]
supersedes: []
amends: []
related: [0093, 0094, 0105, 0106, 0121, 0129, 0143]
pii: false
spec_ref: memory/requisitos/Inventory/SPEC.md
---

# ADR (proposed) — Inventory avançado: Kits/BOM + Batch tracking + Dimensional + Stock Movements unified

## Status

**PROPOSED.** Aguarda decisão Wagner sobre 8 trade-offs (D1-D8 abaixo) antes de promover pra `accepted` + atribuir número canônico.

## Contexto

UltimatePOS v6 (base do oimpresso) resolve **~50%** do problema de gestão de estoque avançada com mecanismos rudimentares (`products.type='combo'`, `purchase_lines.lot_number` string, `units.allow_decimal`, `variation_location_details`). Pesquisa de mercado (Tiny/Bling/SAP B1/NetSuite/Cin7/Odoo/TOTVS/Sankhya/Linx Microvix) mostra:

- **Tiny/Bling** (líderes BR PME): kits + lote + validade existem mas controle composição é simples (1 nível); NFe lote opt-in; sem dimensional avançado; oversell ML manual
- **SAP B1/NetSuite**: BOM multi-level + bin location + lot/serial end-to-end + multi-warehouse — referência arquitetural, preço inviável SMB BR
- **Cin7 Core**: batch/serial + auto-assembly kitting + multi-channel marketplace 700+ integrations — referência SaaS moderno
- **Odoo Inventory**: BOM com approval workflow + traceability reports + GS1 barcode + FEFO automático — open-source referência
- **TOTVS Protheus SIGAEST**: rastreabilidade lote/sublote + kits via Ordem de Produção MATA650 — referência indústria BR mas legacy desktop
- **Sankhya**: FIFO/FEFO + explosão de lote automática em apontamento produção — referência indústria BR moderna
- **Linx Microvix**: grade tamanho/cor + kits + lote — referência varejo moda BR (próximo do que ROTA LIVRE usa hoje)

**Gatilho oimpresso** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) cliente como sinal):

1. **Vargas** OficinaAuto candidato — pediu "kit bomba" multi-componente em discovery
2. **6 saudáveis OfficeImpresso** (Extreme, Gold, Zoom, Fixar, Mhundo, Produart) — todos com bobina lona lote-rastreável + tinta consumo dimensional
3. **ROTA LIVRE** (biz=4) consome variações vestuário sem audit unificado — risco compliance LGPD futuro

**Pipeline FSM canon** ([ADR 0143](../0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) introduziu side-effects `ReservarEstoque`/`ConsumirEstoque`/`LiberarReserva` LIVE em prod biz=1. Inventory avançado deve **evoluir aditivamente** (compat reverse total).

## Decisão

Implementar Inventory avançado em 5 fases (US-INV-001..025, ver [SPEC.md](../../../requisitos/Inventory/SPEC.md)) com 4 capacidades cross-vertical:

1. **Kits/BOM** via tabelas normalizadas `product_bom` + `product_kits`
2. **Batch tracking** via `product_batches` (lote/serial central decremental)
3. **Dimensional** estendendo `units.allow_decimal` + `products.base_unit_id_inv` + custo per unit + alertas %
4. **Stock Movements unified** via `stock_movements` append-only (single source of truth) + triggers MySQL imutáveis (pattern `ponto_marcacoes`)

**Princípios:**

- ✅ **Opt-in per business + per produto** (flags `business.enable_*` + `products.track_by_batch`) — ROTA LIVRE não é forçada a migrar
- ✅ **Aditivo a UPos legacy** — `variations.combo_variations` JSON mantido como fallback V1; `purchase_lines.lot_number` cache; quebra zero
- ✅ **Side-effects FSM v2** retrocompatíveis — sem flags ligadas, comportamento idêntico V1 ADR 0143
- ✅ **Tier 0 multi-tenant** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) — `business_id` global scope em todas 4 tabelas novas
- ✅ **Cross-vertical** — vive em `app/Domain/Inventory/` (irmão de `app/Domain/Fsm/`), não em `Modules/Inventory` — verticais ComVis/OficinaAuto/Autopecas USAM via service contract

## Decisões pendentes Wagner

### D1 — Kits/BOM: tabela normalizada vs estender UPos combo

**Opção A (proposta):** Tabela nova `product_bom` + `product_kits` normalizada + UI Inertia. `variations.combo_variations` JSON legacy mantém-se como fallback compat (UPos POS Blade) mas é considerada deprecada V3.

**Opção B:** Estender `variations.combo_variations` JSON schema com novos campos (is_optional, allow_substitution). Sem migration extra.

**Trade-off:** A = clean arquitetura + query indexável + multi-level + UI moderna, mas dual-write 3 meses + risco drift. B = zero migration, mas JSON não-indexável + sem FK + sem multi-level + bloqueia analytics/RAG sobre composição.

**Recomendação:** **A** — combo legacy continua funcionando, BOM nova é opt-in via `business.enable_bom=true`. Deprecação JSON em V3 com flag forçado.

### D2 — Batch tracking: opt-in per produto vs sempre obrigatório quando flag business on

**Opção A (proposta):** `products.track_by_batch` boolean opt-in per produto. Bobina Mimaki YES, café 350g NO. Permite migração gradual.

**Opção B:** Quando `business.enable_batch_tracking=true`, TODOS produtos com expires_at ou que recebem lot_number em purchase obrigatoriamente rastreados.

**Trade-off:** A = adoção realista (Vargas habilita só pneus de início), mas drift "esqueci de marcar" + relatórios incompletos. B = consistência audit perfeita, mas força UX rica antes do tempo (cada NFe entrada exige picker batch).

**Recomendação:** **A** — pragmatismo BR PME.

### D3 — Dimensional: estender `units` UPos vs criar `units_v2`

**Opção A (proposta):** Reusar `units` UPos legacy (`actual_name`, `short_name`, `allow_decimal`, `base_unit_id`, `base_unit_multiplier`). Adicionar coluna `category` (mass/volume/length/area/discrete) pra validação cruzada (não converte ml → m² absurdo).

**Opção B:** Nova tabela `units_v2` enquanto units legacy permanece read-only.

**Recomendação:** **A** — UPos units suficiente; faltam UX (picker per produto) + custo per unit no produto (`products.base_unit_cost`).

### D4 — NFe55 kit: 1 linha pai vs N linhas componentes (decisão FISCAL)

**Opção A (1 linha pai):** NFe55 mostra "Kit Bomba VW Gol G6 — 1× — R$ [redacted Tier 0]" com NCM e CFOP do kit. Simples cliente; perde rastreabilidade fiscal componentes; lote per componente impossível na NFe.

**Opção B (N linhas filhas):** NFe55 explode em 5 linhas (bomba + vedação + 4 parafusos + manual) cada com NCM/CFOP/lote próprio. Conforme Receita pra produtos rastreados; preço componentes pode somar ≠ preço-kit (regra fiscal exige consistência valor total).

**Opção C (flag per produto):** `products.kit_nfe_strategy` enum('linha_unica','linhas_componentes') decide caso a caso.

**Trade-off:** A simples mas perde rastreio CST; B compliance fiscal limpa; C complexidade UI mas flexibilidade real.

**Recomendação:** **C** — flag per produto default `linhas_componentes`; oficinaauto/autopecas (componentes regulamentados) usam B; ComVis kit-promoção pode usar A. **CONFIRMAR com contadora Eliana[E]** antes de fixar default.

### D5 — Política consumo batch: FIFO, FEFO, manual (per business default)

**Opção A:** `business.consumption_policy` enum('manual','fifo','fefo'):
- **manual:** UI picker exige escolha em cada consumo (default V1 — UX rica mas trabalhosa)
- **fifo:** sistema escolhe batch mais antigo primeiro (food/cosméticos não-vencíveis)
- **fefo:** sistema escolhe batch com expires_at mais próximo (food vencível, tinta PVC, lonas)

**Opção B:** Per produto (override business).

**Recomendação:** **A com B opcional** — business policy default + override per produto (`products.consumption_policy_override`).

### D6 — Negative inventory permitido (oversell ML) vs strict block

**Opção A (proposta):** `business.allow_negative_inventory` boolean default `false` (block). Quando `true`, qty_available pode ficar < 0; UI sinaliza vermelho + permite venda backorder.

**Opção B:** Sempre block (UPos legacy comportamento).

**Trade-off:** ML Full **realisticamente vende mesmo se ML.com estoque off** — bloquear força sync agressivo + perdas vendas. Mas permitir abre porta inadimplência operacional ("vendeu o que não tinha").

**Recomendação:** **A opt-in** — ROTA LIVRE biz=4 fica em false; gráficas com ML Full ligam true.

### D7 — Multi-location transfer com batch preservation

**Opção A (proposta):** Stock_transfer (UPos legacy, já existe) GANHA suporte batch_id — transfer preserva lote origem→destino. `stock_movements` registra dois rows (OUT origem, IN destino) ambos com mesmo batch_id.

**Opção B:** Transfer cria NOVO batch destino (split). Mais complexo mas permite "lote A reembala em lote A.1 no destino".

**Recomendação:** **A** — preservação simples cobre 95% dos casos PME BR; B fica em V3 se aparecer demanda.

### D8 — Stock_movements retention: forever vs partition 5 anos

**Opção A (proposta):** Forever. Storage estimado biz médio = ~50k rows/mês = 600k/ano = 100MB/ano (linha ~150 bytes). Em 5 anos = 500MB — Hostinger PRO suporta.

**Opção B:** Partition por ano + arquivar > 5 anos pra cold storage.

**Trade-off:** A simples mas crescimento linear; B otimização prematura (PME BR raramente passa 5 anos de operação intensa).

**Recomendação:** **A** — review-trigger anual cron compara `SELECT COUNT(*) FROM stock_movements`; se ultrapassar 10M rows, migrate B.

## Consequências

### Positivas

- ✅ **Unblocking** Vargas OficinaAuto (kit bomba) + 6 saudáveis ComVis (bobina lote + tinta consumo)
- ✅ Stack moderna competitiva com Cin7/Odoo (multi-level BOM + FEFO + batch + dimensional + audit)
- ✅ Compliance fiscal/LGPD pavimentado (stock_movements append-only = audit trail Receita Federal + recall garantia fornecedor)
- ✅ FSM canon ADR 0143 evolui aditivo — risco regressão biz=1 mínimo
- ✅ Backfill incremental (US-INV-017) permite migrar histórico sem downtime
- ✅ Cross-vertical reuso — todos módulos Modules/<Vertical> herdam Inventory sem duplicar

### Negativas / riscos

- ❌ 25 US (~174h IA-pair, 348h margem 2×) — escopo grande, requer commitment 9 semanas full-focus
- ❌ **Dual-write transition** combo legacy ↔ product_bom por 3 meses (D1 opção A) — risco drift se não houver reconciliation job
- ❌ Stock_movements triggers MySQL bloqueiam UPDATE/DELETE — devs precisam aprender pattern (já existe em `ponto_marcacoes`)
- ❌ **Backfill 5+ anos UPos legacy biz com >1M transactions** demora 4-8h — agendamento maintenance window
- ❌ Side-effects FSM v2 exige Pest cross-tenant + Pest BOM multi-level + Pest batch FIFO/FEFO ≥ 30 testes — gap testing crítico
- ❌ **D4 NFe kit** pode exigir consulta contador Eliana[E] + revisão regra fiscal por CNAE antes de release

### Riscos top 3

1. **R-ADR-1 — Stock_movements append-only quebra optimize:clear / cache?**
   Mitigação: triggers BEFORE UPDATE/BEFORE DELETE raise erro com mensagem clara; testar `php artisan optimize:clear` em staging com 100k rows antes prod.

2. **R-ADR-2 — Combo legacy UPos POS Blade quebra após product_bom existir?**
   Mitigação: Pest dual-mode (combo JSON vs product_bom) durante transition + reconciliation job daily; smoke ROTA LIVRE biz=4 (que NÃO habilita enable_bom) garante zero impacto.

3. **R-ADR-3 — D4 NFe kit linhas componentes desalinha NCM/CFOP/CST per componente?**
   Mitigação: D4 default conservador (`linhas_componentes`) força configuração correta de cada produto componente; bloqueio emissão NFe se algum componente sem NCM válido; revisar com Eliana[E] + contador externo.

## Alternativas consideradas

### Alt-1 — Comprar/integrar com Tiny/Bling como inventory backend

**Custo:** R$ [redacted Tier 0]–199/mês per business + custo API integration + lock-in vendor.

**Rejeitada:** ADR 0121 explicitamente posiciona oimpresso como **núcleo modular especializado**. Terceirizar inventory = comoditiza diferenciação Inv + bloqueia features verticais (kit bomba oficina, bobina lote ComVis, custo real per OS gráfica). Tiny/Bling não suportam custom logic per CNAE.

### Alt-2 — Forkar Odoo Inventory module

**Rejeitada:** Stack Python/PostgreSQL ≠ stack oimpresso (Laravel/MySQL). Custo manutenção dual ≥ custo implementação nativa.

### Alt-3 — Implementar tudo dentro de Modules/Manufacturing (existe scaffold)

**Rejeitada:** Manufacturing escopo MRP/PCP indústria (>50 funcionários). Inventory v1 é PME 1-20 funcionários cross-vertical. Misturar = bloat Manufacturing + adiar entrega Vargas/ComVis.

## Compliance

- ✅ ADR 0093 Tier 0 multi-tenant — `business_id` global scope em TODAS 4 tabelas novas
- ✅ ADR 0143 FSM canon — side-effects v2 retrocompat
- ✅ ADR 0104 MWART canônico — telas Inventory/* seguem 5 fases (PLAN → BACKEND → FRONTEND → QA → CUTOVER)
- ✅ ADR 0105 cliente como sinal — ativa AGORA porque Vargas + 6 candidatos saudáveis (sinal qualificado)
- ✅ ADR 0106 recalibração IA-pair — estimates 174h ≤ janela 2 sprints + margem 2×
- ✅ Constituição v2 (ADR 0094) — SoC brutal (Inventory ≠ Manufacturing ≠ Sells), append-only stock_movements, transparência custo real

## Promoção

Quando aceito:
1. Atribuir número ADR canônico (próximo `decisions-search status:accepted`)
2. Mover de `proposals/drafts/` → `decisions/NNNN-inventory-avancado-kits-batch-dimensional.md`
3. Atualizar SPEC.md status `proposed` → `accepted`
4. Criar US-INV-001..005 no MCP (`tasks-create module:Inventory cycle:current`)
5. Webhook GitHub → MCP server sincroniza
6. Commit conventional: `feat(adr): inventory avançado kits + batch + dimensional + movements [W]`

---

> Draft criado 2026-05-12 [W]. Status: proposed. Próximo passo: Wagner revisa D1-D8 + responde decisões + promove pra accepted OU pede ajustes.
