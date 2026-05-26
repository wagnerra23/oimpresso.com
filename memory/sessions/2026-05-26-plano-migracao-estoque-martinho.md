---
title: "Plano migração estoque Martinho — catálogo PRODUTO + saldos PRODUTO_ESTOQUE_LOCAL → products + variation_location_details biz=164"
date: "2026-05-26"
type: session-log
status: ativo
scope_modulos: [OficinaAuto, Compras, Sells]
cliente: Martinho Caçambas LTDA (biz=164 · Tubarão SC Humaitá de Cima · CNAE 4520 mecânica pesada · sub-vertical 4)
related_adrs: [0093, 0105, 0137, 0143, 0171, 0192, 0194]
owner: [W]
sem_aprovacao_humana: nada_executado_apenas_plano
---

# Plano — migração estoque Martinho (catálogo + saldos) → biz=164

> **Pedido Wagner (2026-05-26):** "pode migrar o estoque da martinho?" → escopo refinado = **catálogo de produtos + saldos atual** · fonte Firebird WR Comercial Delphi · **plano primeiro, codar depois**.

## TL;DR (3 bullets)

- **Gap real:** `import-products.py` **NÃO existe** ainda (RUNBOOK linha 78 declara explicitamente `PRODUTO | Martinho: TODO ainda não probe`). Os 6 importers existentes (empresas → contas-bancárias → vehicles → vendas → financeiro) **pularam estoque** — vendas (44k) foram migradas mas `transactions.sell_lines` provavelmente está com `product_id=NULL` ou referenciando produto-placeholder. Precisa criar 2 importers novos: `import-products.py` + `import-stock-balances.py`.
- **Esforço estimado:** **~15-20h IA-pair Felipe + ~4h Wagner** (probe + audit + smoke) = **~2-3 dias úteis wallclock**. Não cabe na agenda Sem 22 ([levantamento-martinho-ready §6](./2026-05-26-levantamento-martinho-ready.md) já tem 50-58h de P0s travados — NFSe fix, Compras Tier 0, RB Inter PJ, cleanup tools).
- **Decisão Wagner pendente (PRÉ-EXECUÇÃO):** (a) ordem na fila vs B1-B7 do levantamento, (b) escopo `enable_stock=1` ou produtos sem saldo histórico (todos com estoque inicial = saldo atual snapshot), (c) categoria mapping CNAE 4520 (Peça Hidráulica · PTO · Kit · Serviço hora-trabalho) — semear ANTES ou criar on-demand pelo importer.

---

## §1 — Escopo refinado (após pergunta de clarificação)

Wagner respondeu (AskUserQuestion 2026-05-26 08:48):
- **Escopo:** "deve ser os dois estoque catalogo" → migrar **catálogo de produtos** + **saldos atuais** (NÃO histórico de movimentações).
- **Origem:** WR Comercial Delphi (Firebird legacy) — mesma fonte dos 91 vehicles + 44k vendas + 103k títulos já migrados.
- **Gate:** "Primeiro me devolve o plano — quero ver volume + esforço + riscos." → **NÃO codar até aprovação batch**.

Fora de escopo neste plano:
- Histórico de `PRODUTO_MOVIMENTO` (entradas/saídas) — só interessa pra auditoria contábil; saldo atual basta.
- Tabelas de preço (`PRODUTO_TABELA_PRECO`, `PRODUTO_MARKUP`) — semear default UltimatePOS (`default_sell_price` na `variations`), refinar pós-cutover.
- Composições (`PRODUTO_COMPOSICAO`) — sub-vertical 4 mecânica pesada usa cross-ref (Scania/Volvo/MB/Ford) não composição BOM. Pular.
- Centro de trabalho/PCP — Martinho não faz fabricação ([RUNBOOK §1.4](../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md)).
- Cleanup pós-import — vira US separada (alinha com US-OFICINA-005-A/B/C P0 já no levantamento §7).

---

## §2 — Fonte: schema Firebird PRODUTO

### 2.1 Tabelas envolvidas (Firebird WR Comercial)

| Tabela legacy | Linhas Martinho (estimado) | Função | Mapeia pra |
|---|---|---|---|
| **`PRODUTO`** | TODO probe (likely 500-5000 pra oficina) | Catálogo mestre — descrição, EAN, preço, custo, marca, categoria | `products` + `variations` (single) + `product_variations` |
| **`PRODUTO_ESTOQUE`** | ~= PRODUTO | Saldo `PRINCIPAL` (DOUBLE) — 1:1 com PRODUTO | input pra `variation_location_details.qty_available` |
| **`PRODUTO_ESTOQUE_LOCAL`** | ~= PRODUTO × N locais | Saldo por local de estoque (`ATIVO S/N`) | `variation_location_details` (1 row por (product, location)) |
| **`PRODUTO_CATEGORIA`** | TODO probe (likely ≤50) | Árvore de categorias | `categories` (semear se não existir biz=164) |
| **`PRODUTO_MARCA`** | TODO probe (likely ≤30 — Scania/Volvo/MB/Ford/Tork) | Marcas | `brands` (semear se não existir biz=164) |
| **`PRODUTO_TIPO`** | TODO probe (≤10) | Tipo (peça/serviço/uso-consumo) | `products.type` enum (single/variable) + tag |
| **`UNIDADE`** (já lookup) | ≤20 | UN/PC/KG/M/H | `units` (semear se não existir biz=164) |

### 2.2 Colunas críticas PRODUTO (mapping field-by-field)

Schema-doc auto-gerada ([PRODUTO.md](../dominios/wr-comercial/modulos/estoque/tabelas/PRODUTO.md)) cobre só DELTAS (140 cols adicionadas v11→v1395). Cols baseline v1 ausentes — **probe live obrigatório** via `SELECT FIRST 1 *` pra dump real (mesma técnica usada em `import-contas-bancarias.py` `query_contas_delphi`).

Mapping preliminar (refinar pós-probe):

| Firebird PRODUTO | oimpresso products / variations | Notas |
|---|---|---|
| `CODIGO` (PK) | `products.sku` E `bridge.legacy_id` | UPSERT idempotente por `(business_id, legacy_id)` — preserva schema US-OFICINA-001 PR #556 pattern |
| `DESCRICAO` | `products.name` | Trim, normalize WIN1252 → UTF-8 |
| `DESCRICAO_NFE` | `products.product_description` | Fallback DESCRICAO se NULL |
| `CODIGOEAN` | `products.barcode_type='EAN-13'` + variation.sub_sku | Validar 8/13 dígitos · `EAN-8` se ≤8 |
| `CUSTO` | `variations.default_purchase_price` | DOUBLE PRECISION → DECIMAL(22,4) |
| `VALOR_VENDA_MINIMO` | `variations.default_sell_price` | Fallback `CALC_VVENDA_SUGERIDO` se 0 |
| `CALC_PVENDA_TOTAL` | `variations.sell_price_inc_tax` | Cálculo Delphi já tem ICMS embutido |
| `CALC_PMARGEM_CONTRIBUICAO` | `variations.profit_percent` | Coleção opcional |
| `CODPRODUTO_CATEGORIA` (FK) | `products.category_id` | Lookup `categories.legacy_id=<CODIGO>` (semear PRODUTO_CATEGORIA primeiro) |
| `CODPRODUTO_TIPO` (FK) | mapping pra `products.type` | TEM_SERVICO='S' → `services_only` UltimatePOS · TEM_PRODUTO='S' + TEM_VARIACAO='N' → `single` · TEM_VARIACAO='S' → `variable` |
| `UN_PADRAO_VENDA` | `products.unit_id` FK | Lookup `units.legacy_id` ou criar on-demand |
| `OIMPRESSO_ATIVO` / `OIMPRESSO_CODIGO` | METADADO — **não usar** pra mapping | Reservados pra futuro bi-sync; ler-só |
| `ATIVO` (assumido v1, probe confirma) | `products.is_inactive` (inverso `S`→0, `N`→1) | Semântica inversa Delphi vs UltimatePOS |
| `TEM_CONTROLE_ESTOQUE` (`S`/`N`) | `products.enable_stock` (1/0) | Default `1` se NULL (Martinho oficina = controla peça) |
| `PODE_SER_VENDIDO` (`DOM_BOOLEAN`) | filtrar — não importa se `N` | Evita poluir catálogo com itens internos |
| `PODE_SER_COMPRADO` | só info — não bloqueante | — |
| `DT_ULTIMA_COMPRA` | `products.metadata.last_purchase_at_legacy` (JSON) | Auditoria |
| `CALC_*` (35+ cols) | NÃO migrar | Cálculos Delphi proprietários — UltimatePOS recalcula próprio |
| `CERTIFICADO*` / `WEB_SERVICE_*` / `NFE_*` segredos | NÃO migrar | Mesmo princípio §EMPRESA reference legacy-delphi-firebird.md §"NUNCA migrar" |

### 2.3 Mapping saldos

```
PRODUTO_ESTOQUE.PRINCIPAL  ─┐
                            ├─→ variation_location_details.qty_available
PRODUTO_ESTOQUE_LOCAL.[CODIGO×LOCAL] ─┘   (1 row por (product_id, location_id))
```

`location_id`: usar **BL0001** validado em Martinho biz=164 ([levantamento-martinho-ready §inventário](./2026-05-26-levantamento-martinho-ready.md) cita "Location BL0001"). Se `PRODUTO_ESTOQUE_LOCAL` tiver multi-local (provável **NÃO** pra Martinho 1 oficina), agregar SUM → BL0001.

Saldo negativo (`PRINCIPAL < 0`) → importar com warning audit JSON (não pular — sinal de saldo legacy errado, operador corrige depois via cleanup §3).

---

## §3 — Alvo: schema oimpresso

### 3.1 Estrutura products (UltimatePOS canônico)

`products` (multi-tenant via `business_id` FK) — [migration baseline 2017-08-08](../../database/migrations/2017_08_08_115903_create_products_table.php):
- PK `id` auto-inc + `business_id` (Tier 0 scope)
- `name`, `sku` (unique within business — validar), `type` enum `single|variable`
- `unit_id` FK (units), `category_id` FK (categories), `brand_id` FK (brands) — TODOS biz-scoped
- `enable_stock` boolean default 0
- `is_inactive` boolean (migration 2019)
- `tax` FK (tax_rates) · `tax_type` enum `inclusive|exclusive`
- `barcode_type` enum `C39|C128|EAN-13|EAN-8|UPC-A|UPC-E|ITF-14`

`variations` (1 row pra products `type=single` ou N pra `variable`):
- `product_id` FK, `name` (default 'DUMMY' single), `sub_sku`
- `default_purchase_price` (CUSTO), `default_sell_price` (VALOR_VENDA_MINIMO ou CALC_PVENDA_TOTAL)
- `dpp_inc_tax`, `profit_percent`, `sell_price_inc_tax`

`variation_location_details` ([migration 2017-12-25](../../database/migrations/2017_12_25_163227_create_variation_location_details_table.php)):
- `product_id` + `variation_id` + `location_id` + `qty_available` (DECIMAL 22,4 default 0)

### 3.2 Bridge `products_legacy_map` (a criar — espelho do `accounts_legacy_map` PR #593)

```sql
CREATE TABLE products_legacy_map (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  legacy_source VARCHAR(50) NOT NULL DEFAULT 'wr-comercial-delphi',
  legacy_id VARCHAR(15) NOT NULL,         -- PRODUTO.CODIGO Firebird
  product_id INT UNSIGNED NOT NULL,
  variation_id INT UNSIGNED NOT NULL,     -- pra single, é a única variation
  created_at TIMESTAMP, updated_at TIMESTAMP,
  UNIQUE KEY uq_legacy (business_id, legacy_source, legacy_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_business_legacy (business_id, legacy_id)
);
```

Permite idempotência + audit "qual produto Delphi virou qual produto oimpresso?". Mesmo pattern já validado biz=1 contas (19 rows) + empresas (4 rows).

---

## §4 — Padrão de execução (canônico — espelho `import-vehicles.py`)

Workflow validado em 5 importers + ratificado [RUNBOOK §2-§4](../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md):

```
probe Firebird ─→ dry-run gera SQL+audit.json ─→ Wagner aprova ─→ --target local (Herd) ─→ smoke local ─→ --target prod --confirm ─→ smoke prod
```

### 4.1 Decisão: pattern A (Python direto MySQL) vs pattern B (JSON intermediate)

**Recomendado: Pattern A** (igual `import-vehicles.py`, `import-contas-bancarias.py`, `import-empresas.py`).
- ✅ Idempotente via legacy_id bridge
- ✅ Audit JSON Wagner inspeciona ANTES de prod
- ✅ Mesmo SSH tunnel `migrar-tudo.py` (127.0.0.1:33069)
- ✅ `--target dry-run|local|prod` + `--confirm` gate
- ❌ Wagner precisa Python local + Firebird driver (já tem)

Pattern B (JSON intermediate como `ImportFirebirdMartinhoCommand.php` no Modules/OficinaAuto) — **NÃO adequado** aqui. Pattern B existe pro caso de US-OFICINA-002 que prevê JSON dumpado por terceiro (futuro). Pra ESTE batch Wagner local + Firebird live, pattern A é direto.

### 4.2 Scripts a criar

**`scripts/legacy-migration/import-products.py`** (~250-400 LOC, espelho de `import-contas-bancarias.py`):

```bash
# Dry-run (gera SQL preview, NÃO toca DB)
python import-products.py --alias MartinhoServidor --target-business 164

# Local Herd
python import-products.py --alias MartinhoServidor --target-business 164 --target local

# Prod (exige --confirm)
python import-products.py --alias MartinhoServidor --target-business 164 --target prod --confirm

# Opcionais
--only-ativo       # filtra ATIVO='S' Firebird
--only-vendavel    # filtra PODE_SER_VENDIDO='S'
--chunk-size 500   # batch insert
--seed-categories  # cria categories antes (default: cria on-demand)
--seed-brands      # idem brands
--seed-units       # idem units (pré-req)
```

**`scripts/legacy-migration/import-stock-balances.py`** (~150-200 LOC, depende de products):

```bash
python import-stock-balances.py --alias MartinhoServidor --target-business 164 \
    --target prod --confirm --default-location-id <BL0001-id>
```

Lê `PRODUTO_ESTOQUE` (saldo principal) + agrega `PRODUTO_ESTOQUE_LOCAL` se multi-local.

### 4.3 Lib reuso

Já existe ([scripts/legacy-migration/lib/](../../scripts/legacy-migration/lib/)):
- `firebird_reader.py` — connection + query helpers + WIN1252 normalize
- `mysql_writer.py` — UPSERT helpers + audit
- `ddl_parser.py` — schema reflection
- `fk_resolver.py` — CODxxx → tabela alvo

NÃO precisa criar nada novo de infra — só ~2 scripts importer-específicos.

---

## §5 — Volume + esforço estimado

### 5.1 Volume (probe pendente — chute baseado em oficina típica)

| Métrica | Estimativa | Confiança |
|---|---|---|
| Linhas PRODUTO | 1.500-5.000 | média (oficina pesada com cross-ref Scania/Volvo/MB/Ford tem catálogo amplo) |
| Linhas PRODUTO_ESTOQUE | ~= PRODUTO | alta |
| Linhas PRODUTO_ESTOQUE_LOCAL | ~= PRODUTO × 1 local | alta (Martinho 1 oficina) |
| Categorias PRODUTO_CATEGORIA | 20-50 | média |
| Marcas PRODUTO_MARCA | 15-30 | alta (Scania, Volvo, MB, Ford, Iveco, Tork, Parker, Bosch, Rexroth, etc) |
| Unidades únicas | 5-10 | alta (UN, PC, KG, M, M², L, H) |
| Inativos `ATIVO='N'` | 30-50% | média (legacy 5-10 anos de uso) |
| Sem EAN | >50% | alta (peça hidráulica raramente tem EAN) |
| Saldo negativo | 5-10% | média (drift legacy clássico) |

### 5.2 Esforço h-humano (NÃO recalibrado — Wagner pediu horas reais)

| Fase | Tarefa | Esforço | Owner |
|---|---|---|---|
| F0 | Probe Firebird Martinho (counts + sample + categories enumeradas) | 2h | Wagner local |
| F1 | Decisões pré-mapping (ver §7 abaixo) | 1h | Wagner aprovar |
| F2 | `import-products.py` scaffold (copy `import-vehicles.py`) + adapter cols | 4h | Felipe IA-pair |
| F2.5 | Lookup tables — seed categories/brands/units biz=164 (pode ser parte de F2 ou flag separada) | 2h | Felipe IA-pair |
| F3 | `import-stock-balances.py` scaffold | 2h | Felipe IA-pair |
| F4 | Bridge migration `products_legacy_map` table | 1h | Felipe IA-pair |
| F5 | Dry-run + audit JSON review Wagner | 1h | Felipe gera + Wagner inspeciona |
| F6 | `--target local` Herd + smoke (UI Sells/Index autocomplete pega produto) | 2h | Felipe + Wagner browser MCP |
| F7 | `--target prod --confirm` biz=164 + smoke browser real | 1h | Wagner |
| F8 | Conciliação pós-import — vendas órfãs `sell_lines.product_id IS NULL` (44k vendas migradas referenciam o quê?) | 2h | Wagner+Felipe |
| F9 | Pest cross-tenant guard `ProductsImporterTest` (biz=1 não vê biz=164 products) | 1h | Felipe |
| F10 | Session log debrief + atualizar RUNBOOK-migracao-cliente-legacy.md §10 com gotchas Martinho products | 30min | Wagner |

**Total: ~18.5h** (~3 dias úteis IA-pair real wallclock incluindo paralelismo).

### 5.3 Comparativo com batches anteriores Martinho

| Batch | Linhas | Esforço real | Tempo wallclock |
|---|---|---|---|
| `import-empresas.py` biz=164 (2026-05-13) | 1 | ~3h | 1d |
| `import-vehicles.py` biz=164 | 91 | ~6h | 1d (sessão maratona) |
| `import-vendas.py` biz=164 | 44.709 | ~12h | 2d |
| `import-financeiro.py` biz=164 | 103.000 | ~16h | 2-3d |
| **`import-products.py` + `import-stock-balances.py` biz=164 (este plano)** | **~1.500-5.000 + saldos** | **~18.5h** | **~2-3d** |

Custo similar ao import-financeiro (volume menor mas 2 importers + bridge table + pest novos).

---

## §6 — Riscos Tier 0 + dependências

### 6.1 Tier 0 multi-tenant (IRREVOGÁVEL · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))

| Risco | Mitigação |
|---|---|
| Vazar product de Martinho biz=164 pra outro biz | `--business=164` obrigatório (igual padrão `--target-business`); todos INSERT incluem `business_id=164`; Pest cross-tenant biz=1 vs biz=164 (F9 acima) |
| SKU duplicado entre tenants (products.sku UNIQUE business-scoped?) | Confirmar schema — `products` baseline 2017 tem `string('sku')` sem unique. Mas conferir migrations adicionais. Se não-unique global, OK. Se unique global, prefixar com `M164-` |
| Wagner rodando local com `--target prod` por engano | `--confirm` gate explícito (já no pattern) + tunnel SSH só Wagner tem chave + `migrar-tudo.py` reusa cred Hostinger |
| Cred Firebird (SYSDBA/masterkey) leak | Já hardcoded no Delphi público + documentado em [legacy-delphi-firebird.md](../reference/legacy-delphi-firebird.md) — não é segredo |
| PII em products.name (raríssimo mas possível) | Audit JSON Wagner inspeciona antes prod (mesmo gate dos 5 importers anteriores) |

### 6.2 Dependências de ordem

```
[já feito] business biz=164 row em prod
                ↓
[já feito] import-empresas → contacts entidade Martinho
                ↓
[ESTE PLANO] seed units / brands / categories biz=164 (lookup tables)
                ↓
[ESTE PLANO] import-products → products + variations + product_variations
                ↓
[ESTE PLANO] import-stock-balances → variation_location_details
                ↓
[bloqueado] Compras Tier 0 hotfix (US-COM-006/007/008/009) — sem isso cadastrar fornecedor peça vaza cross-tenant
                ↓
[futuro] reconciliar 44k vendas existentes — popular sell_lines.product_id retroativamente (cleanup US-OFICINA-005-D futuro)
```

### 6.3 Riscos específicos descobertos

1. **products schema tem 18 migrations addons** — alguns campos podem ter NOT NULL constraint sem default (ex: `tax_type`, `barcode_type`, `created_by`). Importer precisa setar defaults sensatos (ex: `tax_type='exclusive'`, `barcode_type='C128'`, `created_by=<user_id_admin biz=164>`).
2. **`CALC_*` cols Firebird** — 35+ cálculos proprietários (margem, lucro desejado, preço atacado). UltimatePOS recalcula próprio — NÃO migrar pra evitar drift de fórmula. Só mapear `default_purchase_price` (custo) + `default_sell_price` (venda mínimo).
3. **Saldo histórico NÃO importado** — só snapshot atual. Operador Martinho NÃO conseguirá ver "vendi 3 do item X em 2024-08" no oimpresso. Decisão consciente do escopo (linha 25 §1).
4. **`OIMPRESSO_CODIGO` / `OIMPRESSO_ATIVO` cols no Firebird** — sinais de tentativa prévia de bi-sync. **Não usar** pra mapping (pode estar drifted). UPSERT por `legacy_id=CODIGO` direto.
5. **Produtos serviço (`TEM_SERVICO='S'`)** — UltimatePOS distingue produto físico vs serviço; `products.type` enum só tem `single|variable`. Servicos vão como `single` + `enable_stock=0` + categoria "Serviços". Cuidado: Modules/OficinaAuto pode esperar tabela própria de serviços/horas — confirmar pós-probe se há `oa_servicos_executados` que conflita.
6. **44k vendas sem product_id** — vendas já importadas (`transactions.type=sell`) provavelmente têm `sell_lines.product_id=NULL` ou apontam pra produto-fake `placeholder`. Pós-products importer, reconciliação é US separada (NÃO incluída neste plano — vira sub-feature US-OFICINA-005-D).
7. **Produtos de uso/consumo (`TEM_USOECONSUMO='S'`)** — categoria interna oficina (limpeza, EPI, óleo). Importar com flag `is_consumable` ou tag — Modules/Sells não vende, Modules/Compras compra.
8. **Variações** — `TEM_VARIACAO='S'` muito raro em oficina pesada (peça hidráulica não tem cor/tamanho). Provável Martinho = 100% `type=single`. Probe confirma.

---

## §7 — Decisões pré-execução (Wagner aprova antes F2)

Estas decisões DEVEM ser explícitas antes Felipe começar a codar — evita retrabalho mid-scaffold:

| # | Decisão | Opções | Recomendação |
|---|---|---|---|
| D1 | Importar produtos `ATIVO='N'` (inativos)? | (a) só ativos · (b) todos com `is_inactive=1` no oimpresso | (b) — preserva histórico, operador filtra UI |
| D2 | `enable_stock=1` global ou só onde `TEM_CONTROLE_ESTOQUE='S'`? | (a) global=1 · (b) respeitar flag Firebird | (b) — alguns serviços/uso-consumo não controlam estoque |
| D3 | Saldo negativo importar como está ou zerar? | (a) negativo · (b) clip em 0 + warning audit | (a) — sinal de problema legacy, operador resolve em cleanup |
| D4 | Categoria root pra produtos sem `CODPRODUTO_CATEGORIA`? | (a) "Sem categoria" · (b) "Importado Firebird" | (a) — neutro |
| D5 | EAN inválido (≤7 dígitos OU >13 OU não-numérico)? | (a) descartar · (b) gravar como `internal_barcode` separado · (c) C128 catch-all | (c) — `barcode_type='C128'` aceita qualquer string, não perde info |
| D6 | Como tratar produtos serviço (`TEM_SERVICO='S'`)? | (a) `products` tipo `single` + categoria "Serviços" · (b) tabela separada `oa_servicos` futuro | (a) — V0 pragmático; refatorar se Modules/OficinaAuto pedir |
| D7 | `default_sell_price` quando `VALOR_VENDA_MINIMO=0`? | (a) usar `CALC_PVENDA_TOTAL` · (b) usar `CUSTO * 1.5` markup default · (c) deixar NULL | (a) — Delphi já calculou; (c) fallback se ambos NULL |
| D8 | Brands/categories/units — semear todos pré-import OU criar on-demand? | (a) seed completo via flag `--seed-*` · (b) on-demand dentro do importer | (a) com flag — mais visível Wagner, audit JSON separado |
| D9 | Conciliar vendas existentes (popular `sell_lines.product_id` por `legacy_id` match) neste plano OU separar US? | (a) incluir aqui +6h · (b) US separada US-OFICINA-005-D | (b) — escopo "estoque" fechado · cleanup vai com cleanup |
| D10 | Pest cross-tenant biz=1 vs biz=164 obrigatório antes prod? | (a) sim, F9 acima · (b) só após smoke local · (c) pular | (a) — Tier 0 ADR 0093 sem exceção |

---

## §8 — Plano de execução proposto (após Wagner aprovar D1-D10)

### Fase A — Probe (1 sessão Wagner local · 2-3h)

1. Conectar Firebird: `python scripts/legacy-migration/poc2-firebird-connect.py --alias MartinhoServidor`
2. Contagens reais:
   ```sql
   SELECT COUNT(*) FROM PRODUTO;
   SELECT COUNT(*) FROM PRODUTO WHERE ATIVO='S';
   SELECT COUNT(*) FROM PRODUTO_ESTOQUE WHERE PRINCIPAL <> 0;
   SELECT COUNT(*) FROM PRODUTO_ESTOQUE_LOCAL;
   SELECT COUNT(DISTINCT CODPRODUTO_CATEGORIA) FROM PRODUTO;
   SELECT FIRST 1 * FROM PRODUTO;   -- inspeciona TODAS cols reais (140+ baseline v1)
   SELECT FIRST 5 * FROM PRODUTO ORDER BY DT_ULTIMA_COMPRA DESC;
   ```
3. Anotar resultados em `memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md` (extends).
4. Wagner decide D1-D10 com numbers reais na mão.

### Fase B — Scaffold (Felipe IA-pair · ~9h em paralelo Sem 23 ou Sem 24)

5. Worktree filho: `git worktree add ../../worktrees/martinho-products-migration claude/martinho-products-2026-MM-DD`
6. Bridge migration `products_legacy_map` (F4 · 1h)
7. `import-products.py` scaffold + adapter cols + seed lookup tables (F2+F2.5 · 6h)
8. `import-stock-balances.py` scaffold (F3 · 2h)
9. Pest `ProductsImporterMultiTenantTest.php` (F9 · 1h)

### Fase C — Apply (Wagner + Felipe · ~6h)

10. Dry-run `--target dry-run` · audit JSON Wagner aprova (F5 · 1h)
11. Local Herd `--target local` · smoke `/products` lista + `/sells/create` autocomplete pega produto (F6 · 2h)
12. Prod `--target prod --confirm` · backup MySQL antes · smoke browser MCP biz=164 (F7 · 1h)
13. Conciliação spot-check pós-import (F8 · 2h):
    ```sql
    SELECT COUNT(*) FROM products WHERE business_id=164;                    -- == probe
    SELECT COUNT(*) FROM variation_location_details vld
      JOIN products p ON p.id=vld.product_id
     WHERE p.business_id=164;                                                -- ≈ probe
    SELECT SUM(qty_available) FROM variation_location_details vld
      JOIN products p ON p.id=vld.product_id
     WHERE p.business_id=164;                                                -- bate com SUM(PRINCIPAL) Firebird?
    ```

### Fase D — Debrief (Wagner · 30min)

14. Session log debrief `2026-MM-DD-import-products-martinho-debrief.md`
15. Apendar gotchas em `RUNBOOK-migracao-cliente-legacy.md §10 Martinho`
16. Atualizar `legacy-delphi-firebird.md §"Tabelas-fonte"` — marcar PRODUTO/PRODUTO_ESTOQUE de "pendente" → "migrado biz=164" (+ futuros)

---

## §9 — Riscos NÃO-MIGRAÇÃO (se decidirmos não fazer agora)

Caso Wagner decida postergar pós-Sem 23 (atende B1-B7 levantamento primeiro):

- **Sem catálogo:** OS de manutenção não pode listar peça aplicada com preço — `final_total=0` continua (mesmo bug B2 do levantamento). Wagner edita manual a venda derivada.
- **Sem catálogo:** Cadastrar nova venda via Sells/Create exige criar produto on-the-fly toda venda — operador Martinho não vai usar Sells, vai voltar pro Delphi. Risco de rejeição operacional.
- **Sem saldo:** Relatório de inventário oimpresso mostra 0 produtos — descrédito.
- **Compras:** Modules/Compras quando hotfix (US-COM-006/007/008/009) sair, cadastrar entrada de peça hidráulica nova exige criar produto. Sem catálogo, ou cria duplicado ou desiste.

**Conclusão:** estoque é pré-req pra Fase 2 ROADMAP OficinaAuto ATIVAR de verdade ([ADR 0171](../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)). Não é P0 imediato (não quebra B1-B7 do levantamento) mas é **P0-implícito** pra Martinho "operar plenamente" como pacote R$ 850 prometeu.

---

## §10 — Recomendação Wagner

**Faseado em 2 cycles:**

- **Cycle atual (Sem 22 · 2026-05-26→06-01):** Wagner roda **Fase A — Probe** isolada (2-3h local), entrega counts reais + decisões D1-D10. Custo baixo, valor alto (descobre se é 1.5k ou 5k produtos, decide se vai mesmo).
- **Cycle Sem 24 (~2026-06-10):** após B1-B7 levantamento destravado, agendar Fase B-C-D (~15h). Inclui paralelismo com Compras Tier 0 hotfix (US-COM-006/007/008/009) que sai antes.

Alternativa **TUDO AGORA Sem 22-23:** se Wagner achar que sem estoque o piloto está incompleto, viabilizar em vez de B6 (WhatsApp anti-cross 3h) + B7 (RB Inter PJ 9h) trocaria pra B1+B4+products (50h+) — atrasa cobrança automática Martinho ~1 semana.

---

## §11 — Refs

- [Levantamento Martinho-ready 2026-05-26](./2026-05-26-levantamento-martinho-ready.md) — contexto B1-B7
- [RUNBOOK-migracao-cliente-legacy](../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md) — receita 8 fases
- [OFFICEIMPRESSO-FIREBIRD-SCHEMA](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) — schema 9 tabelas críticas
- [legacy-delphi-firebird](../reference/legacy-delphi-firebird.md) — 50 bancos · `MartinhoServidor` @ `D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB`
- [PRODUTO schema delta](../dominios/wr-comercial/modulos/estoque/tabelas/PRODUTO.md) — 140 cols delta v11→v1395
- [import-vehicles.py](../../scripts/legacy-migration/import-vehicles.py) · [import-contas-bancarias.py](../../scripts/legacy-migration/import-contas-bancarias.py) — patterns canônicos
- [products migration baseline](../../database/migrations/2017_08_08_115903_create_products_table.php)
- [variation_location_details migration](../../database/migrations/2017_12_25_163227_create_variation_location_details_table.php)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · [ADR 0137](../decisions/0137-modules-oficinaauto-qualificada.md) · [ADR 0171](../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md) · [ADR 0192](../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) · [ADR 0194](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
