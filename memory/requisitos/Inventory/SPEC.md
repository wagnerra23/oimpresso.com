---
module: Inventory
version: "1.0"
last_updated: "2026-07-02"
status: rascunho
owner: [W]
prioridade: P0-P2 (faseado)
related_modules: [Sells, Repair, OficinaAuto, ComunicacaoVisual, Vestuario, Autopecas, NfeBrasil, Marketplaces]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0129-state-machine-canonica-fsm-rbac
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
cnae_relacionados: [todos verticais]
created_at: 2026-05-12
---

# SPEC Inventory — Estoque avançado cross-vertical (Kits/BOM + Batch + Dimensional + Movements unified)

> **Status:** **PROPOSED** — discovery + proposta arquitetural. Não-iniciado em código. Aguardando aprovação Wagner pra promover seções pra `accepted` e gerar US no MCP.
>
> **Trigger ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** SPEC nasce porque 3 sinais convergem AGORA — (a) Vargas pipeline OficinaAuto (kit bomba VW Gol), (b) 6 candidatos ComVis pré-vendas (bobina lote + tinta kg/ml), (c) Modules/Vestuario já live ROTA LIVRE consumindo estoque per-variação sem audit unificado.

> **Papel no domínio Estoque (consolidação Onda 0 — 2026-07-02):** este SPEC é o **roadmap de evolução do domínio Estoque** (camada avançada), **não** um domínio paralelo. A fonte da verdade de como o saldo se move **hoje** é o [DOC-RAIZ-ESTOQUE.md](../Estoque/DOC-RAIZ-ESTOQUE.md) (integridade LIVE); este documento descreve o que ainda vamos construir **por cima** (Kits/BOM, Batch, Dimensional, Movements unified). A pasta `Inventory/` permanece a casa física deste roadmap **até a repartição do cluster Estoque aterrissar** (P6/P7 da [_TRIAGEM-IDENTIDADE-2026-06.md](../_TRIAGEM-IDENTIDADE-2026-06.md), **ADIADA** por custo/risco — decisão Wagner E1, gated por sinal [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)). A cópia duplicada `Estoque/_telas/SPEC-inventory-cross-vertical.md` foi **tombada** nesta data — ver lápide lá; este arquivo é o sobrevivente canônico (carrega as 25 âncoras `**Implementado em:**` do backfill anchor-lint — [PR #3654](https://github.com/wagnerra23/oimpresso.com/pull/3654)).

---

## §1 Visão

**Inventory avançado cross-vertical** integra 4 capacidades estado-da-arte em cima do UltimatePOS legacy preservando compatibilidade (ADR 0093 Tier 0 inviolável):

1. **Kits/BOM (Bill of Materials)** — produto pai composto por N componentes, baixa cascateada com regra fiscal explícita
2. **Batch tracking (lote/serial)** — rastreabilidade end-to-end fornecedor → cliente, validade, recall, garantia
3. **Dimensional (kg, ml, L, m², m linear)** — consumo decimal real, custo por OS preciso, alertas de cartucho/rolo
4. **Stock Movements unified (append-only)** — tabela única audit-trail toda entrada/saída/reserva/ajuste, fonte de verdade pra compliance + analytics

Evolução **opt-in per produto + per business** — nenhum cliente legacy (ROTA LIVRE biz=4) é forçado a migrar; flags `business.enable_*` ligam cada capacidade conforme demanda real.

**Integração crítica com FSM canon ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))** — Side-effects `ReservarEstoque`/`ConsumirEstoque`/`LiberarReserva` evoluem pra resolver BOM + suportar batch_id + qty decimal sem quebrar pipeline LIVE.

**Não-objetivo (V1):** MRP/PCP completo industrial (planejamento mestre, capacidade, roteiros multi-operação) — fica em Modules/Manufacturing (já scaffold existente, ver `memory/requisitos/Manufacturing/` e `memory/requisitos/IProduction.md`). Inventory entrega FUNDAÇÃO comum que Manufacturing reusa.

---

## §2 Discovery UltimatePOS legacy

UltimatePOS v6 (base do oimpresso) já tem **3 das 4 capacidades em forma rudimentar** — exploramos antes de inventar.

### §2.1 Tabelas + colunas relevantes encontradas

| Tabela / coluna | Migration | O que faz hoje | Gap pra Inventory v1 |
|---|---|---|---|
| `products.type` ENUM('single','variable','modifier','**combo**') | `2019_07_15_165136_add_fields_for_combo_product.php` | Suporta produto tipo **combo** (≈ kit fixo) | Combo guarda components em `variations.combo_variations` TEXT JSON — não-normalizado, não-indexável, sem FK; UI legacy Blade só |
| `variations.combo_variations` TEXT cast array | `app/Variation.php:25` | `[{"variation_id":N,"quantity":M,"unit_id":U}]` JSON | Não relaciona, não permite **opcional/substituição**, sem custo agregado, sem múltiplos níveis (BOM multi-level) |
| `transaction_sell_lines.parent_sell_line_id` + `children_type` ENUM('modifier','combo') | `2019_07_15_165136_add_fields_for_combo_product.php` | Linha pai (kit) + linhas filhas (componentes) na venda | Funciona pra **vendas**, mas combo desce stock dos componentes; NFe fica com 1 linha pai OU N linhas filhas (decisão D4) |
| `units` table (id, business_id, actual_name, short_name, **allow_decimal**, base_unit_id, base_unit_multiplier) | `2017_07_26_122313_create_units_table.php` + `2018_11_28_104410_modify_units_table_for_multi_unit.php` | Multi-unit com conversão base (kg→g, L→ml) | Funciona; **dimensional já suportado em parte** — falta enforcement decimal em `qty_available` (já é `decimal(22,4)`) + UX picker unit por produto + custo per unit |
| `purchase_lines.lot_number` (string) + `business.enable_lot_number` (bool) | `2018_04_17_123122_add_lot_number_to_business.php` | Lote ENTRADA capturado per linha de compra | **String solta**, sem tabela `product_batches`, sem `expires_at`, sem `qty_current`, sem FK fornecedor; só pra exibir em invoice (`add_show_expiry_and_show_lot_colums_to_invoice_layouts_table.php`) |
| `transaction_sell_lines.lot_no_line_id` (int) | `2018_07_24_160319_add_lot_no_line_id_to_transaction_sell_lines_table.php` | Aponta de qual `purchase_lines.id` (lote) saiu na venda | Frágil — quebra se purchase_lines arquivado; sem `product_batches` central |
| `products.expiry_period` + `enable_product_expiry` business | `2018_02_08_*` | Validade configurável per produto | Funciona; **falta** alerta proativo + bloqueio venda lote vencido (regra opt-in) |
| `products.sub_unit_ids` (string JSON) + `purchase_lines.sub_unit_id` + `transaction_sell_lines.sub_unit_id` | `2018_11_28_*` + `2019_08_08_*` | Multi-unit (compra em caixa, vende em UN) | Já cobre case banner kg/ml parcial — falta UI Inertia + conversion automatic no estoque |
| `product_locations` (m:n product↔business_location) | `2019_09_12_105616_create_product_locations_table.php` | Permite produto presente só em algumas filiais | Funciona; multi-location já é primeiro-classe |
| `variation_location_details` (variation_id, location_id, **qty_available** decimal 22,4) | `2017_12_25_163227_create_variation_location_details_table.php` | Saldo per variação per filial | **Não tem qty_reserved** — ADR 0143 resolveu via tabela paralela `stock_reservations`; **não tem qty per batch** — gap pra batch tracking |
| `stock_reservations` (id, business_id, transaction_id, product_id, variation_id, location_id, qty_reserved, status, expires_at) | `2026_05_11_130001_create_stock_reservations_table.php` | Reserva FSM canon (US-SELL-013, ADR 0143) | **Sem batch_id, sem unit_id, sem decimal explícito além do (22,4)**; expande pra resolver BOM + lote |
| `stock_adjustment_lines` + `transactions.type='stock_adjustment'` | `2018_02_19_121537_stock_adjustment_move_to_transaction_table.php` | Ajustes (normal/abnormal) gravam transaction tipo stock_adjustment | Audit existe MAS distribuído em N tabelas (transactions, purchase_lines, sell_lines, stock_adjustment_lines, stock_transfers) — **sem visão unificada single source of truth** |
| `stock_transfers` (transactions.type='stock_transfer') | `2018_02_27_170232_modify_transactions_table_for_stock_transfer.php` + `2020_09_07_*` | Transferência entre locations (status final) | Funciona; reusar pra TRANSFER no `stock_movements` |
| `product_racks` | `2018_04_17_160845_add_product_racks_table.php` | Posição física rack/row/position | Bin location rudimentar; NetSuite-like Advanced Bin não — gap V2 |
| `products.sub_unit_ids` (JSON) | `2018_11_28_104410_*` | Lista de sub-units permitidas | Funciona; reusar |

### §2.2 Diagnóstico

UPos legacy resolve **~50%** do que Inventory v1 precisa. Gaps reais:

| Capacidade | UPos legacy | Gap |
|---|---|---|
| Kits/BOM | Combo via `variations.combo_variations` JSON | Falta tabela normalizada `product_bom` com FK + suporte opcional/substituição + multi-level + pricing strategy + UI Inertia |
| Batch tracking | `purchase_lines.lot_number` string + `transaction_sell_lines.lot_no_line_id` | Falta tabela central `product_batches` com qty_current decremental + status + expires_at + fornecedor FK + FIFO/FEFO consumer + UI |
| Dimensional | `units.allow_decimal` + `base_unit_multiplier` + sub_unit | Funciona mecânica básica; falta UX picker + custo per unit (custo R$/ml tinta, R$/m² lona) + cartucho/rolo % restante |
| Stock Movements unified | Espalhado em 5 tabelas (purchase_lines, transaction_sell_lines, stock_adjustment_lines, stock_transfers, variation_location_details) | Falta `stock_movements` append-only single source — view consolidada hoje exige UNION 5x; audit/analytics/compliance frágil |
| Negative inventory | `qty_available` clamp em 0 (`ConsumirEstoque.php:48`) | Sem flag `allow_negative` — ML Full pode oversell legitimamente |
| FEFO consumo | Não-implementado (combo desce sem priorizar validade) | Adicionar policy quando batch ativo |

### §2.3 Princípio guia

**Não substituir UPos legacy** — Reservation pattern ADR 0143 provou: tabela paralela `stock_reservations` deixou `variation_location_details.qty_available` inviolada. Mesma estratégia pra batch (`product_batches` paralelo), BOM (`product_bom` paralelo ao combo), movements (`stock_movements` espelha mas não substitui). **Opt-in per business + per produto** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

---

## §3 Cenários de uso peculiares

### §3.1 Cenário A — Oficina Kit Bomba VW Gol (composição produto)

**Cliente piloto:** Vargas (OficinaAuto candidato).

**Situação:** Mecânico vende "Kit Bomba D'água VW Gol G6 2012-2018" pra cliente.

Composição BOM (1 nível):
- 1× Bomba d'água Mahle BB-318 (`product_id=1001`)
- 1× Vedação carcaça Sabó V-9912 (`product_id=1002`)
- 4× Parafuso M8×40 inox (`product_id=1003`)
- 1× Manual técnico Vargas (`product_id=1004` — não-estocável)

**Fluxo esperado:**
1. PDV scanner SKU "KIT-BOMBA-GOL-G6" → `products.type='combo'` + `product_bom` resolvido
2. Sistema verifica disponibilidade dos 4 componentes (qty_available ≥ requerido)
3. POST `/sells/store` cria 1 linha pai `KIT-BOMBA` + 4 linhas filhas via `parent_sell_line_id` + `children_type='combo'`
4. FSM `cliente_aprovou` → `ReservarEstoque` **resolve BOM** e cria 4 `stock_reservations` (não 1)
5. FSM `concluir_producao` → `ConsumirEstoque` baixa qty_available das 4 variações + cria 4 linhas em `stock_movements` type `PRODUCTION_CONSUME`
6. NFe55: **D4 decisão** — 1 linha "Kit Bomba" (preço fechado) OU 5 linhas componentes (CFOP/NCM individual)?

**Edge cases:**
- 1 dos 4 parafusos esgotado → `is_optional=false` BLOQUEIA venda (recommend substituição via `allow_substitution=true` apontando `product_id_substituto`)
- Mecânico comprou kit fechado do fornecedor (caixa lacrada Mahle "KIT-MAHLE-2024") → ENTRADA com **flag `keep_as_kit=true`** mantém 1 SKU pai E 4 SKUs filhos com qty_current vinculados via `product_batches.parent_batch_id`; **D1 decisão**: estoque desce 1 do pai OU 4 dos filhos?

### §3.2 Cenário B — ComVis Bobina Lona Mimaki (batch tracking)

**Candidato piloto:** Vargas (placa SP, gráfica) ou Extreme (6 saudáveis OfficeImpresso).

**Situação:** Cliente Largados S.A. encomenda "5 banners 3m × 1m blackout 510g" pra evento.

**Estoque atual:** 3 rolos lona Mimaki Eco-Sol-MAX 1.6m × 50m blackout 510g azul:
- Rolo A — lote `MIM-2024-Q3-08891` — qty_current 47.2m de 50m (cor #0033CC validado)
- Rolo B — lote `MIM-2024-Q4-12345` — qty_current 50.0m (cor #0033CC validado)
- Rolo C — lote `MIM-2024-Q4-12347` — qty_current 50.0m (cor #0033D5 — **levemente diferente**)

**Fluxo esperado:**
1. Orçamentista cria OS pelos 5 banners → cálculo m² (US-COMVIS-001) demanda 15m² = ~9.4m linear bobina
2. Sistema oferece batch picker: "Pode usar 1 rolo todo OU misturar?"
3. Cliente exige **cor uniforme** → escolhe APENAS rolos do mesmo lote (rolos A+B compatíveis cor `#0033CC`)
4. FSM `cliente_aprovou` → `ReservarEstoque(batch_id=A, qty=9.4)` reserva 9.4m do rolo A
5. Operador máquina imprime → FSM `concluir_producao` → `ConsumirEstoque` decrementa `product_batches.qty_current` rolo A → 47.2 − 9.4 = 37.8m, registra `stock_movements` type `PRODUCTION_CONSUME` com batch_id
6. 8 meses depois cliente reclama "lona descascou" → consulta `stock_movements` retro → identifica lote `MIM-2024-Q3-08891` → reclamação fabricante Mimaki com prova rastreável

**Edge cases:**
- Rolo Mimaki **vencendo em 60d** (`product_batches.expires_at`) → política FEFO oferece esse rolo PRIMEIRO se cor compatível (D5 decisão)
- Defeito fabricante em lote inteiro → status `defeito` bloqueia consumo + sugere RMA fornecedor
- Sobrou 0.6m de rolo após impressão (sucata) → registra como `LOSS` em `stock_movements`

### §3.3 Cenário C — ComVis Tinta cartucho (dimensional kg/ml)

**Situação:** Plotter Roland VS-540 com 4 cartuchos EcoSol-MAX 220ml CMYK.

**Estoque cartucho atual** (rastreado per **product_batches** tipo cartucho, qty_current em ml):
- C-cyan — batch `ROLAND-2024-12345` — 165ml restantes (75% original)
- M-magenta — batch `ROLAND-2024-12346` — 12ml (5% — **alerta crítico**)
- Y-yellow — batch `ROLAND-2024-12347` — 198ml
- K-black — batch `ROLAND-2024-12348` — 88ml (40%)

**Fluxo esperado:**
1. Apontamento máquina US-COMVIS-004 grava `consumo_tinta_ml{c:8,m:5,y:12,k:25}` em job-print
2. Hook decrementa `product_batches.qty_current` per cartucho ativo
3. Cartucho M abaixo de threshold (10ml = 5%) → alerta "Troca M-magenta" em `notifications` + dashboard plotter
4. Operador troca cartucho → registra ENTRADA novo batch + status batch antigo = `esgotado`
5. Custo banner = soma(consumo_ml × custo_R$/ml) cruzando batch.unit_cost

**Implicação dimensional:** Tinta vende-se em UN (cartucho) mas CONSOME-SE em ml. UPos legacy já tem multi-unit (caixa 6un → UN) — estender pra dimensional consumo per OS.

### §3.4 Cenário D — Autopecas estoque Mahle + garantia fornecedor (batch tracking)

**Cliente:** Vargas (hipótese) — autopeças balcão.

**Situação:** Vargas compra 100 filtros óleo Mahle OE-682 lote `MH-2024-12345` em 1 NFe entrada. Vende 5 ao longo do mês. 1 cliente volta D+45 reclamando: "filtro vazando óleo desde dia 3".

**Fluxo:**
1. NFe entrada cria `product_batches` row qty_initial=100, qty_current=100, lot_number `MH-2024-12345`, supplier_id=Mahle, purchase_line_id=NN
2. Cada venda registra `transaction_sell_lines.batch_id` (ou stock_movements row tipo OUT com batch_id) — FEFO ou manual
3. Cliente volta → busca `stock_movements WHERE customer_id=X AND batch_id=...` → identifica lote 100% Mahle
4. Loja registra `defeito_relatado` em batch → flag `aviso_rma_fabricante` automático
5. Se 3+ defeitos no mesmo lote → status batch = `defeito_lote_inteiro` → bloqueia venda restante + cria RMA fornecedor (US-AP-006 garantia)

**Edge case:** Vargas compra 50 filtros adicionais MESMO modelo OE-682 lote `MH-2024-12999` (3 meses depois). Estoque agora tem 2 batches mesmo SKU. Venda futura escolhe automaticamente FEFO ou manual. **D5 decisão** + UI picker.

### §3.5 Cenário E — Vestuario ROTA LIVRE (já live — manter)

**Cliente live:** Larissa biz=4.

**Situação:** Camiseta básica branca SKU "CAM-BRANCA-001" com variações tamanho (P/M/G/GG).

**Estoque atual** (UPos legacy funciona):
- variation_id 100 (CAM-BRANCA-P) qty_available 12 em location_id 1
- variation_id 101 (CAM-BRANCA-M) qty_available 8
- variation_id 102 (CAM-BRANCA-G) qty_available 3 (**baixo!**)
- variation_id 103 (CAM-BRANCA-GG) qty_available 0

**Não muda nada V1.** Variação per tamanho/cor já é primeiro-classe UPos. Manter.

Gap futuro V3+: alertar estoque mínimo automático per variação (US-INV-021).

### §3.6 Cenário F — Mix vende E compra (devolução pneu)

**Situação:** Oficina compra 4 pneus Pirelli novos R$ [redacted Tier 0] vende 4 pneus novos pra cliente, cliente entrega 4 pneus velhos (não-vendáveis novos, mas sucateáveis).

**Fluxo:**
1. ENTRADA NFe purchase → 4 pneus novos em estoque (`stock_movements` type IN)
2. VENDA 4 pneus → consumo estoque (type OUT)
3. **DEVOLUÇÃO sucata** → registro novo SKU "Pneu velho sucata" com batch_id próprio + entrada baixo valor (R$ [redacted Tier 0]/un) — type RETURN com sub-tipo `sucata`
4. Posterior venda pneu velho pra ferro-velho → OUT comum

Inventory v1 cobre via stock_movements tipo `RETURN` + sub-tipo (sucata/revenda/baixa).

---

## §4 Schema proposto

### §4.1 Tabelas novas (opt-in via business flags)

#### `product_bom` (Bill of Materials)

Substitui `variations.combo_variations` JSON (mantido como fallback legacy, não-deprecado V1).

```
id                       bigIncrements
business_id              unsignedInteger (Tier 0)
parent_product_id        unsignedInteger FK products.id (type=combo ou single)
parent_variation_id      unsignedInteger nullable (se variável)
component_product_id     unsignedInteger FK products.id
component_variation_id   unsignedInteger FK variations.id
qty_required             decimal(22,4)
unit_id                  unsignedInteger FK units.id
is_optional              boolean default false
allow_substitution       boolean default false
substitute_product_id    unsignedInteger nullable FK products.id
sequence                 unsignedSmallInteger (ordem na composição)
notes                    string nullable
created_at/updated_at
softDeletes

UNIQUE (business_id, parent_product_id, parent_variation_id, component_product_id, component_variation_id)
INDEX (business_id, parent_product_id)
```

Suporta **multi-level** via recursão: component_product pode ele mesmo ser combo (kit-de-kits raros mas válidos — Mubisys/Calcgraf não suportam).

#### `product_kits` (catálogo + metadata)

```
id                       bigIncrements
business_id              unsignedInteger
parent_product_id        unsignedInteger FK products.id
name                     string
description              text nullable
pricing_strategy         enum('sum_components','fixed_kit_price','markup_over_sum')
markup_percent           decimal(8,4) nullable (se markup_over_sum)
is_active                boolean default true
allow_partial_sale       boolean default false (vende kit incompleto?)
created_at/updated_at
softDeletes
```

#### `product_batches` (lote/serial central)

Tabela canônica pra batch tracking. Substitui `purchase_lines.lot_number` string (mantido como cache backref V1, deprecação V3).

```
id                       bigIncrements
business_id              unsignedInteger (Tier 0)
product_id               unsignedInteger FK products.id
variation_id             unsignedInteger FK variations.id nullable
location_id              unsignedInteger FK business_locations.id
lot_number               string (fornecedor — "MIM-2024-Q4-12345")
internal_serial          string nullable (interno único se serial individual)
manufactured_at          date nullable
expires_at               date nullable
qty_initial              decimal(22,4)
qty_current              decimal(22,4)
unit_id                  unsignedInteger FK units.id
unit_cost                decimal(22,4) nullable (custo entrada R$/unit)
supplier_id              unsignedInteger FK contacts.id (fornecedor) nullable
purchase_line_id         unsignedInteger FK purchase_lines.id nullable
parent_batch_id          unsignedBigInteger FK product_batches.id nullable (kit-de-kit)
status                   enum('active','depleted','expired','defeito','quarentena','sucata')
attributes               json nullable (cor pantone, lote analítico, certificado fornecedor URL)
created_at/updated_at
softDeletes

INDEX (business_id, product_id, status)
INDEX (business_id, expires_at)  -- FEFO query
INDEX (business_id, supplier_id, lot_number)  -- garantia fornecedor lookup
```

#### `stock_movements` (audit append-only)

**Single source of truth** — toda mudança quantidade passa aqui.

```
id                       bigIncrements
business_id              unsignedInteger (Tier 0)
location_id              unsignedInteger FK business_locations.id
product_id               unsignedInteger FK products.id
variation_id             unsignedInteger FK variations.id
batch_id                 unsignedBigInteger FK product_batches.id nullable
type                     enum('IN','OUT','RESERVE','RELEASE','ADJUST','TRANSFER','PRODUCTION_CONSUME','LOSS','RETURN')
sub_type                 string nullable (ex: 'sucata','revenda','baixa','recall')
qty                      decimal(22,4) (sempre positivo; signal via type)
unit_id                  unsignedInteger FK units.id
unit_cost                decimal(22,4) nullable
reference_type           string ('transaction','job_sheet','purchase','stock_adjustment','stock_transfer','manual')
reference_id             unsignedBigInteger nullable
user_id                  unsignedInteger FK users.id nullable
reason                   text nullable
executed_at              timestamp (NOT NULL DEFAULT NOW)
created_at  (immutable após insert — trigger MySQL impede UPDATE/DELETE — ADR 0093 + Portaria 671 pattern)

INDEX (business_id, product_id, executed_at)
INDEX (business_id, location_id, executed_at)
INDEX (business_id, batch_id, executed_at)
INDEX (business_id, reference_type, reference_id)
INDEX (business_id, type, executed_at)  -- relatório IN/OUT period
```

**Imutabilidade SQL:** triggers `BEFORE UPDATE`/`BEFORE DELETE` raise erro (pattern já validado em `ponto_marcacoes` per CLAUDE.md proibições). D8 decisão: retenção forever (5 GB/ano estimado biz médio) vs partition 5 anos.

### §4.2 Tabelas existentes — alterações compatíveis (sem breaking)

| Tabela | Coluna nova | Tipo | Default | Justificativa |
|---|---|---|---|---|
| `business` | `enable_bom` | boolean | false | Opt-in BOM per business |
| `business` | `enable_batch_tracking` | boolean | false | Opt-in batch (UPos já tem `enable_lot_number` mas usado de outro jeito) |
| `business` | `enable_stock_movements_audit` | boolean | false | Opt-in append-only (default off → backfill incremental) |
| `business` | `allow_negative_inventory` | boolean | false | Habilita oversell (ML Full) |
| `business` | `consumption_policy` | enum('manual','fifo','fefo') | 'manual' | Política padrão consumo batch |
| `products` | `track_by_batch` | boolean | false | Opt-in per produto (Mimaki bobina YES, café 350g NO) |
| `products` | `batch_strategy` | enum('per_lot','per_serial_unit') | 'per_lot' | Bobina = per_lot; cartucho serializado raro = per_serial_unit |
| `products` | `base_unit_id_inv` | int unsigned | nullable | Override unit pra cálculo consumo (cartucho 220ml = unit ml) — distinto do `unit_id` venda |
| `products` | `alert_qty_min_warning` | decimal(22,4) | nullable | Reuso `alert_quantity` legacy + novo |
| `stock_reservations` (existe) | `batch_id` | unsignedBigInt FK | nullable | Reserva pode amarrar lote |
| `stock_reservations` (existe) | `unit_id` | int unsigned | nullable | Explicita unit reserva |
| `transaction_sell_lines` (existe) | `batch_id` | unsignedBigInt FK | nullable | Venda registra lote consumido (substitui `lot_no_line_id` em V2) |

---

## §5 Integração com Side-effects FSM canon (ADR 0143)

**Premissa:** Pipeline FSM LIVE em prod biz=1 ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) NÃO pode quebrar. Side-effects evoluem **aditivos**.

### §5.1 `ReservarEstoque` v2

```
input: subject (Transaction|JobSheet), payload = {
    items: [
        {product_id, variation_id, location_id, qty, unit_id?, batch_id?}
    ],
    expires_in_days: 30
}

algoritmo:
1. Pra cada item:
   1.1. Se product.type='combo' E business.enable_bom=true:
        - Resolver product_bom recursivamente (multi-level guard recursão ≤ 5)
        - Pra cada componente: criar stock_reservations row (qty × multiplier do nível)
        - Linkar children → parent via stock_reservations.parent_reservation_id (coluna nova)
   1.2. Senão: criar 1 stock_reservation simples
2. Se item.batch_id presente: amarra reserva ao batch (decrementa product_batches.qty_reserved virtualmente — coluna stock-only)
3. Se item.unit_id ≠ product.base_unit_id: converter via units.base_unit_multiplier antes de salvar
4. Validação: qty_disponível (qty_available − reservas_active) ≥ qty pedida — exceto se business.allow_negative_inventory=true
5. Audit: cria stock_movements type=RESERVE per item filho
```

Compatibilidade legacy: se `business.enable_bom=false`, side-effect ignora resolução BOM → comportamento idêntico V1.

### §5.2 `ConsumirEstoque` v2

```
input: subject, payload (idem)

algoritmo:
1. Buscar stock_reservations active da transaction
2. Pra cada reserva:
   2.1. Marca status=consumed
   2.2. Se reserva.batch_id presente: decrementa product_batches.qty_current
   2.3. Senão E business.consumption_policy='fefo': escolher batch ativo com expires_at mais próximo
   2.4. Decrementa variation_location_details.qty_available
   2.5. Audit: cria stock_movements type=PRODUCTION_CONSUME com batch_id resolvido
3. Se product_batches.qty_current = 0: status=depleted
4. Se algum batch expires_at < today: status=expired (job daily independente)
```

### §5.3 `LiberarReserva` v2

```
input: subject

algoritmo:
1. Buscar stock_reservations active da transaction
2. Pra cada reserva: status=released
3. Audit: cria stock_movements type=RELEASE
4. Componentes BOM são liberados em cascade (parent_reservation_id)
```

### §5.4 Side-effects NOVOS

| Side-effect | Quando | Faz |
|---|---|---|
| `RegistrarEntradaBatch` | FSM purchase `recebido` | Cria `product_batches` row a partir de `purchase_lines.lot_number` + qty + fornecedor |
| `BloquearBatchDefeito` | Action manual gestor estoque | Status batch → `defeito` + bloqueia consumo |
| `RecolherBatch` (recall) | Recall fornecedor | Status batch → `quarentena` + notifica clientes que compraram via stock_movements lookup |

---

## §6 Concorrência marketplace (sync cross-agent)

Cruza com agente Marketplaces (sync ML/Shopee/Magalu).

**Premissas:**
- Cada vertical pode habilitar marketplace listing (sub-feature, não obrigatório)
- ML Full = ML controla estoque na ponta deles, oimpresso só sincroniza
- ML próprio = oimpresso é fonte da verdade, decrementa via webhook ML

**Fluxo proposto:**
1. `stock_movements` insertion → event `StockMovedEvent` (já pattern UPos)
2. Listener `SyncToMarketplacesListener` (futuro módulo Marketplaces — *planejado, não existe*) consome event
3. Calcula novo qty_available_consolidated = sum(variation_location_details.qty_available) − sum(stock_reservations.active.qty)
4. POST API ML/Shopee atualiza listing
5. Webhook ML PEDIDO criado → cria sell + reserva imediata (idempotente per ext_order_id)

**Negative inventory (D6):** se `business.allow_negative_inventory=true`, oversell ML permitido. Stock_movements pode resultar `qty_available < 0` (legítimo — backorder). UI sinaliza vermelho mas NÃO bloqueia.

---

## 7. User Stories

### Fase 1 — Kits/BOM fundação

#### US-INV-001 · Schema `product_bom` + `product_kits` + flags business — **P0**
> **Owner:** [F] · **Priority:** P0 · **Estimate:** 6h codáveis IA-pair ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))
> **Type:** chore · **blocked_by:** none
> **Aceitação:**
- [ ] Migration `product_bom` + `product_kits` + alteração `business` flags
- [ ] Models Eloquent `ProductBom`, `ProductKit` com `business_id` global scope (Tier 0)
- [ ] Pest cross-tenant biz=1 vs biz=99 ([feedback_test_biz_99_cross_tenant_convention](../../../memory/feedback_test_biz_99_cross_tenant_convention.md))
- [ ] Seeder bootstrap zero data se flag off
- [ ] Smoke local: enable flag em biz=1 + criar 1 BOM via tinker + listar

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-002 · UI Inertia cadastro BOM (drag-drop componentes) — **P0**
> **Estimate:** 12h · **blocked_by:** US-INV-001
> Page `Inventory/Bom/Edit.tsx` permite definir composição produto pai com qty/unit/optional/substitute. Pattern Repair Kanban dnd-kit reuso.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-003 · Resolver BOM em `ReservarEstoque` (recursivo multi-level) — **P0**
> **Estimate:** 8h · **blocked_by:** US-INV-001
> Side-effect v2 + Pest 8+ casos (simples, multi-level, opcional, substituição, batch_id presente, decimal qty, FK validation, recursion guard ≤5).

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-004 · Resolver BOM em `ConsumirEstoque` + `LiberarReserva` (v2) — **P0**
> **Estimate:** 6h · **blocked_by:** US-INV-003

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-005 · NFe55 composição (1 linha pai vs N filhas — D4 dependente) — **P0**
> **Estimate:** 8h pós-D4 · **blocked_by:** US-INV-004 + D4 aprovada
> Integração Modules/NfeBrasil — flag `kit_nfe_strategy` per produto.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

### Fase 2 — Batch tracking

#### US-INV-006 · Schema `product_batches` + flags + relação `purchase_lines` — **P0**
> **Estimate:** 6h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-007 · `RegistrarEntradaBatch` side-effect (FSM purchase) — **P0**
> **Estimate:** 6h · **blocked_by:** US-INV-006

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-008 · UI Inventory/Batches listagem + busca + filtro status/expiração — **P0**
> **Estimate:** 10h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-009 · Batch picker em PDV/OS (vincular lote consumido) — **P0**
> **Estimate:** 12h · **blocked_by:** US-INV-007
> ComVis: pode misturar lotes? Picker UI; manual ou FEFO automatic.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-010 · Lookup garantia fornecedor (batch → clientes que receberam) — **P1**
> **Estimate:** 4h · **blocked_by:** US-INV-016 (stock_movements)

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

### Fase 3 — Dimensional

#### US-INV-011 · `products.base_unit_id_inv` separado de venda unit — **P1**
> **Estimate:** 4h
> Cartucho VENDE em UN, CONSOME em ml.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-012 · UI custo per ml/kg/m² + cálculo custo real OS — **P1**
> **Estimate:** 10h · **blocked_by:** US-INV-011

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-013 · Alerta cartucho/rolo baixo (% restante) — **P1**
> **Estimate:** 4h
> Cruzar `product_batches.qty_current / qty_initial < threshold`. Notification + dashboard.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-014 · Apontamento máquina decrementa batch ml automatic (hook US-COMVIS-004) — **P1**
> **Estimate:** 6h · **blocked_by:** US-INV-013

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-015 · Conversão unit automática em side-effects (kg → g, L → ml) — **P2**
> **Estimate:** 4h
> Reuso `units.base_unit_multiplier` já existente.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

### Fase 4 — Stock Movements unified

#### US-INV-016 · Schema `stock_movements` + triggers append-only — **P0**
> **Estimate:** 6h
> Trigger MySQL BEFORE UPDATE/DELETE raise (pattern `ponto_marcacoes`).

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-017 · Backfill incremental stock_movements a partir UPos legacy — **P0**
> **Estimate:** 12h · **blocked_by:** US-INV-016
> Script script `scripts/inventory/backfill-stock-movements.py` (pattern legacy importer per [feedback_legacy_migration_python_importer](../../../memory/feedback_legacy_migration_python_importer.md)). Idempotent.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-018 · Hook automático em todos side-effects existentes (Reservar/Consumir/Liberar) — **P0**
> **Estimate:** 4h · **blocked_by:** US-INV-016

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-019 · UI relatório movements per produto/batch/period — **P1**
> **Estimate:** 8h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-020 · Comando `inventory:reconcile` daily 04:00 BRT (drift vs `variation_location_details`) — **P0**
> **Estimate:** 4h
> Alerta `jana:health-check` se delta detectado.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

### Fase 5 — Negative + FEFO + analytics

#### US-INV-021 · Negative inventory opt-in + UI sinalização — **P2**
> **Estimate:** 6h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-022 · FEFO consumo policy (business.consumption_policy='fefo') — **P2**
> **Estimate:** 4h · **blocked_by:** US-INV-006

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-023 · Job daily `inventory:expire-batches` (batch.expires_at < today → status=expired) — **P2**
> **Estimate:** 2h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-024 · Dashboard analytics estoque (giro, lead-time, ruptura, custo real per categoria) — **P3**
> **Estimate:** 14h

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

#### US-INV-025 · Multi-location transfer com batch preservation — **P3**
> **Estimate:** 8h
> Stock_transfer atual perde batch_id; corrigir.

**Implementado em:** _pendente_ — bloqueado por P7 (cluster Estoque ADIADO); não ancorar até a repartição (docs→Produto/Compras/Estoque) aterrissar; ver `_ANCHOR-REVIEW-QUEUE.md`

---

## §8 Estimate total

| Fase | US | Estimate (h IA-pair, ADR 0106) | Margem 2× |
|---|---|---|---|
| 1 — Kits/BOM | 5 | 40h | 80h ≈ 2 semanas Wagner+IA |
| 2 — Batch tracking | 5 | 38h | 76h |
| 3 — Dimensional | 5 | 28h | 56h |
| 4 — Movements unified | 5 | 34h | 68h |
| 5 — Negative/FEFO/analytics | 5 | 34h | 68h |
| **Total** | **25 US** | **174h** | **348h ≈ 9 semanas full-focus** |

---

## §9 Restrições/proibições

- 🚫 Não modificar `variation_location_details.qty_available` fora de side-effects FSM ou comando `inventory:reconcile`
- 🚫 Não removar `variations.combo_variations` JSON em V1 (compat legacy UPos POS Blade)
- 🚫 Não permitir UPDATE/DELETE em `stock_movements` — triggers MySQL bloqueiam
- 🚫 Não habilitar flags `enable_bom`/`enable_batch_tracking` em biz=4 (ROTA LIVRE) sem aviso prévio + canary 7d ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) cutover discipline)
- 🚫 Não criar um módulo `Inventory` separado em `Modules/` (*não existe — proibido por design*) — Inventory é **camada cross-vertical** que vive em `app/Domain/Inventory/` (perto de `app/Domain/Fsm/`); módulos verticais USAM via service/contract

---

## §10 Riscos

1. **R1 — Stock_movements append-only quebra `optimize:clear`?** — patterns Portaria 671 já provados em `ponto_marcacoes`; mas volume biz médio = ~50k rows/mês → testar storage prod Hostinger antes
2. **R2 — Combo legacy UPos (variations.combo_variations) drift vs product_bom** — definir source of truth (D1 decisão) ou dual-write transition período 3 meses
3. **R3 — Backfill stock_movements de 5+ anos UPos legacy** — pode levar 4-8h em biz grande (>1M transactions); rodar em maintenance window

---

## §11 ADRs relacionadas / dependências

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 multi-tenant — todas tabelas novas obrigatório `business_id` + global scope
- [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2
- [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal — Inventory v1 ativa SE Vargas/Extreme assinarem
- [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Recalibração estimates IA-pair
- [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) Modular especializado — Inventory é cross-vertical infraestrutura
- [0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) FSM canon
- [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) FSM LIVE — side-effects v2 (CRÍTICO compat)

---

> SPEC criado 2026-05-12 [W]. Próximo passo: Wagner revisa §3 cenários + §4 schema + decisões D1-D8 → promover pra `accepted` → criar US-INV-001..005 no MCP via `tasks-create`.
