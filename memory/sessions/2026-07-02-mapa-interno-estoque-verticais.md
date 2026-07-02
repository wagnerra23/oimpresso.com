---
date: 2026-07-02
topic: "Mapa interno Estoque — nativo UltimatePOS × necessidade dos 3 verticais × fragmentação (insumo p/ consolidação do domínio)"
type: session
status: done
scope_modulos: [Estoque, Inventory, Produto, Purchase, Compras, StockAdjustment, StockTransfer, Vestuario, ComunicacaoVisual, OficinaAuto]
related_adrs: [0093, 0129, 0143, 0121, 0192, 0265]
owner: [W]
metodo: introspecção read-only contra origin/main (checkout local stale ~4600 commits) — código real + memory canon + 3 subagents por vertical
base: memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md (2026-06-04) — não repetido, refinado
---

# Mapa interno Estoque × verticais (2026-07-02)

> **Objetivo:** insumo pra plano de consolidação do domínio Estoque. Três frentes: (A) o que o
> UltimatePOS já faz NATIVO — não reconstruir; (B) o que cada vertical realmente precisa (do canon);
> (C) como o domínio está fragmentado hoje e pra onde cada pasta deveria ir.
>
> Tudo verificado contra `origin/main` (HEAD `dad0b11`). Parte do [DOC-RAIZ-ESTOQUE.md](../requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) (fonte da verdade do estoque HOJE) — aqui só o que ele **não** cobre: capacidades nativas de produto/variação, necessidade por vertical, e fragmentação de pastas.

---

## Frente A — Capacidades NATIVAS do UltimatePOS (não reconstruir)

Verificado nas migrations `database/migrations/*` + `app/Product.php` + `app/Utils/ProductUtil.php`.

| Capacidade | Nativo? | Onde no código (origin/main) |
|---|---|---|
| **Tipos de produto** `single` / `variable` / `modifier` / `combo` | ✅ Sim | `products.type` ENUM — base `2017_08_08_...create_products_table` (single/variable) → `2018_06_05_..._modifiers` (+modifier) → `2019_07_15_165136_add_fields_for_combo_product` (+combo) |
| **Grade tamanho×cor (variações)** | ✅ Sim | `product_variations` (atributo, ex "Tamanho", `is_dummy`) → `variations` (célula, ex "M-Azul": `sub_sku`, `default_purchase_price`, `dpp_inc_tax`, `default_sell_price`, `profit_percent`). Templates: `variation_templates`/`variation_value_templates` |
| **Saldo por (variação × local)** | ✅ Sim | `variation_location_details.qty_available` decimal(22,4) — `2017_12_25_163227_...`. **Sem `business_id`** (isolamento transitivo, DOC-RAIZ §6/R4) |
| **Custo** | 🟡 Parcial — **último custo**, não média ponderada | `variations.default_purchase_price`/`dpp_inc_tax` + subquery `last_purchased_price` (`ProductUtil.php:542`). **NÃO há weighted-average cost** |
| **Alerta estoque mínimo** | ✅ Sim | `products.alert_quantity` decimal(22,4) — base products table |
| **Lote (lot number)** | 🟡 Parcial — string solta na entrada | `purchase_lines.lot_number` (string) + `business.enable_lot_number` (`2018_04_17_123122`) + `transaction_sell_lines.lot_no_line_id` (aponta qual purchase_line saiu). **Sem tabela `product_batches`, sem `qty_current`, sem FEFO** |
| **Validade** | 🟡 Parcial | `purchase_lines.mfg_date`/`exp_date` (`2018_02_08_131118`) + `products.expiry_period`/`enable_product_expiry`. Existe; **sem alerta proativo/bloqueio venda vencido** |
| **Sub-unidades / multi-unit (conversão)** | ✅ Sim | `units.allow_decimal` + `base_unit_id` + `base_unit_multiplier` (`2018_11_28_104410`) + `products.sub_unit_ids` JSON + `enable_sub_units` (`2019_08_08_162302`) + `purchase_lines/sell_lines.sub_unit_id`. **Secondary unit:** `secondary_unit_id`/`secondary_unit_quantity` (`2022_06_28_133342`) |
| **Multi-local (multi-armazém)** | ✅ Sim | `business_locations` (`2017_12_25_122822`) + `product_locations` m:n (produto só em algumas filiais, `2019_09_12_105616`). Saldo já é per-location no VLD |
| **Bin / posição física** | 🟡 Rudimentar | `product_racks` (rack/row/position) + `products.enable_racks` (`2018_04_17_160845`). Não é advanced bin NetSuite-like |
| **Barcode / SKU** | ✅ Sim | `products.sku` + `barcode_type` ENUM (C39/C128/EAN-13/EAN-8/UPC-A/UPC-E/ITF-14); `variations.sub_sku` por célula |
| **Opening stock** | ✅ Sim | via `purchase_lines` (`opening_stock` type) — `OpeningStockController@save` + `ProductUtil::addSingleProductOpeningStock`; caller já em `DB::transaction` (DOC-RAIZ R3 = falso alarme) |
| **Stock adjustment** | ✅ Sim | `transactions.type='stock_adjustment'` + `stock_adjustment_lines` (`2018_02_19_121537`). UI Inertia `?v=2` + Blade fallback |
| **Stock transfer** | ✅ Sim | `transactions.type` `sell_transfer`+`purchase_transfer` (`2018_02_27_170232`). Baixa origem + entra destino |
| **Kits / BOM** | 🟡 **Parcial — em construção (Fase 1 shipada)** | Legacy: `products.type=combo` + `variations.combo_variations` JSON (não-normalizado). **Canon V2 JÁ EXISTE:** tabela `product_bom` (`2026_05_12_080001`, multi-tenant, multi-level, `is_optional`/`allow_substitution`) + `app/Domain/Inventory/Models/ProductBom.php` (HasBusinessScope) + `app/Domain/Inventory/Services/BomResolver.php` (recursivo MAX_DEPTH=5, anti-ciclo, fallback combo legacy) + `ProductBomController` (API index/store/destroy, `routes/web.php:427-435`). `ReservarEstoque`/`ConsumirEstoque` v2 **já resolvem BOM**. **Gap:** sem UI Inertia de cadastro drag-drop (US-INV-002) |
| **Reserva (hold) FSM** | ✅ Sim | `stock_reservations` (com `business_id`+HasBusinessScope) + `app/Domain/Fsm/SideEffects/{Reservar,Consumir,Liberar}Estoque` (ADR 0129/0143). Reserva ≠ baixa (INV-4) |
| **Movimentação unificada (ledger append-only)** | ❌ **Não** — espalhado em 5 tabelas | Não existe `stock_movements`. Audit hoje é UNION de purchase_lines + transaction_sell_lines + stock_adjustment_lines + stock_transfers + VLD. US-INV-016 proposto |
| **Batch tracking central** | ❌ **Não** | `product_batches` não existe (só `lot_number` string). US-INV-006 proposto |
| **Estoque negativo (opt-in)** | ❌ **Não** (clamp em 0) | `ConsumirEstoque` clampa; sem flag `allow_negative`. US-INV-021 proposto |
| **Dimensional (custo R$/ml/m²/kg)** | 🟡 Mecânica sim, custo por-unidade não | `qty_available` decimal + `allow_decimal` + conversão existem; **custo R$/ml tinta, R$/m² lona não é calculado nativo**. US-INV-011/012 proposto |

**Resumo A:** o nativo cobre bem produto/variação/grade/multi-local/multi-unit/adjustment/transfer/opening/reserva. O **BOM canônico Fase 1 já está shipado no core** (`app/Domain/Inventory/` — não é "não existe"). Faltam de verdade: **batch central, ledger unificado, custo dimensional por-unidade, average cost, estoque negativo opt-in**.

---

## Frente B — Necessidade REAL por vertical (do canon)

Formato: **precisa X · nativo cobre? · gap · evidência**.

| Vertical | Necessidade | Nativo cobre? | Gap / estado |
|---|---|---|---|
| **Vestuario** (ROTA LIVRE biz=4, live 2+ anos) | Grade tamanho×cor + baixa na venda | ✅ Sim — 100% nativo, zero custom | Funciona há 2 anos. `SellPosController:641`→`ProductUtil::decreaseProductQuantity`; grade = `Product`→`ProductVariation`→`Variation`→VLD |
| Vestuario | Cadastro/reposição por **grade-curva** (matriz PP-GG, proporção de compra) | 🟡 Parcial (variations existem, cadastro 1-a-1) | `GradeCurvaService` (`Modules/Vestuario/Services`) é **esqueleto órfão** — sem controller/route/persistência; Larissa repõe "no olho" (`SPEC.md` US-VEST-005) |
| Vestuario | **Devolução/troca reintegra estoque** à location | ❌ Não (custom não fecha) | `DevolucaoService` grava crédito em `vestuario_devolucoes` mas **NÃO** chama `updateProductQuantity`; AC "estoque retorna" (`SPEC.md:211`) **não implementado**; sem route |
| Vestuario | Etiqueta térmica tam-cor + barcode | ✅ Resolvido | `EtiquetaTagController` (ZPL+PDF+EAN-13, US-VEST-020) — única peça vertical roteada |
| Vestuario | "Estação/coleção" first-class (giro/liquidação) | ❌ Não | Hoje prefixo no nome ("Verão24-"); `products.estacao_id`+`vest_estacoes` (US-VEST-029) não codado |
| **ComunicacaoVisual** (em construção; candidatos 6 OfficeImpresso) | Catálogo matéria-prima por **m²** (lona/vinil/ACM, gramatura g/m²) | 🟡 Parcial | Existe catálogo custom **duplicado** (`comvis_materiais` legacy × `cv_substratos` canon SPEC §12.1) mas só preço/custo — **sem saldo em mãos** |
| ComVis | **Saldo real de substrato em m²** decrescível (baixar m² lona ao concluir impressão) | 🟡 Mecânica nativa serve (qty_available decimal + conversão bobina→m²) | Tabelas de material são **catálogo puro** (sem coluna de saldo, sem ledger); side-effect `ConsumirEstoque` de substrato = **DoD não implementado** (US-COMVIS-NEW-007 `todo`) |
| ComVis | Consumo **tinta ml/CMYK** + cartucho ml restante | 🟡 `sub_unit_ids`+`allow_decimal` modelariam | `comvis_apontamentos` real **não tem coluna de tinta** (só `m2_produzido`/`m2_orcado`/`drift_percent`); `consumo_tinta_json`/`cv_maquinas` são schema proposto (US-COMVIS-004/015) |
| ComVis | **Custo real por OS** (m² + tinta + mão-de-obra vs orçado) | ❌ Não (lógica vertical) | `PosCalculoController/Service` não construídos; diferencial competitivo P1 (US-COMVIS-005 "pendente") |
| ComVis | Apontamento de máquina (drift orçado×realizado) | ❌ Não (nativo) — **único pedaço vivo** | `ApontamentoTracker` + `ApontamentoController` funcionam (drift%); falta form mobile + parte tinta/mídia |
| ComVis | Waste/desperdício/sobra de bobina | ❌ Não — **nem é requisito documentado** | grep `waste/desperd/sobra/bobina` no SPEC = 0 hits; proxy indireto = `drift_percent` |
| **OficinaAuto** (aguarda sinal; Martinho biz=164; só reparo — ADR 0265) | Item da OS vinculado ao **catálogo** (product_id) | 🟡 Parcial | `oficina_service_order_items.product_id` nullable **existe** (fillable+request+tipo TS `number\|null`) mas nasce vazio — **sem product-picker UI**; item é `descricao` texto livre |
| OficinaAuto | **Baixa de peça ao concluir OS ("R2")** | ✅ **LIVE em origin/main** (⚠️ DOC-RAIZ está STALE) | `ServiceOrderItemService::baixarEstoqueConclusao()` chamado por `ServiceOrderObserver` bloco P0-2 (linha 134-142) no `status→concluida`. **DOC-RAIZ §92/145 diz "revertido, não está no código" — FALSO, corrigir** |
| OficinaAuto | Location correta na baixa (design ainda aberto) | 🟡 Vivo mas fora do canon | Hoje resolve VLD por `orderByDesc('qty_available')` (maior saldo) + `VariationLocationDetails::save()` direto. Canon (R2 P1) pede **location default do business via ProductUtil** (auditável) |
| OficinaAuto | **Kit/BOM na OS** (kit bomba VW do Vargas) | 🟡 Infra pronta, não plugada | `product_bom`+`BomResolver` existem, mas `baixarEstoqueConclusao` **não** chama `BomResolver` → se item for kit, baixa o kit direto, não os componentes |
| OficinaAuto | **Cross-reference de peça** (OEM / equivalente entre fabricantes) | ❌ Não (nem especificado no schema) | Só `sku`/`sub_sku` genéricos; sem `oem_code` nem pivô N:N. US-AUTO-008 P1 "pendente" — feature nova, diferencial comercial, não bloqueia baixa |

**Resumo B:** Vestuario prova que o **nativo sozinho roda um vertical em produção** (grade + venda). ComVis e OficinaAuto precisam de **camada por cima do nativo** (dimensional/custo-OS/tinta pra ComVis; kit-na-OS + location default + OEM cross-ref pra OficinaAuto) — a maioria já tem infra parcial (`BomResolver` pronto, apontamento vivo, R2 vivo), faltando plugar/UI, não reconstruir.

---

## Frente C — Fragmentação do domínio → destino

6 pastas do cluster + 2 SPECs Inventory sobrepostas.

| Pasta (origin/main) | Conteúdo real | Sobreposição / duplicata | Destino recomendado |
|---|---|---|---|
| **`Estoque/`** | SPEC v1.0 **ativo** (2026-06-04) + **DOC-RAIZ** (fonte da verdade) + BRIEFING (porta cross-cutting) + `_telas/` com RUNBOOKs de stock-adjustment/transfer (movidos p/ cá) + `SPEC-inventory-cross-vertical.md` | Contém a cópia do Inventory SPEC (ver abaixo) | **É o dono canônico do domínio.** Absorve StockAdjustment/StockTransfer (já feito) + repartição Inventory (P7, ADIADO) |
| **`Inventory/`** | 2 docs: `SPEC.md` (v0.1.0, 2026-05-12, "Estoque avançado cross-vertical" — Kits/BOM+Batch+Dimensional+Movements) + BRIEFING | **DUPLICATA quase literal:** `Inventory/SPEC.md` (570 linhas) ≈ `Estoque/_telas/SPEC-inventory-cross-vertical.md` (568 linhas). Blobs diferentes só no frontmatter (v0.1.0 rascunho × v1.0 2026-06-13) + 2 linhas de wording. **Corpo idêntico.** Ambos dizem "PROPOSED, não-iniciado em código" — **stale**, pois BOM Fase 1 shipou | **FUNDIR em Estoque** (P6/P7). Manter 1 SPEC Inventory (o de `_telas/` v1.0 é o mais novo), tombar o outro. Atualizar status: BOM Fase 1 = shipada, não "proposed" |
| **`Produto/`** | 8 RUNBOOKs das telas core de produto (index/create/edit/show/selling-prices/bulk-edit/stock-history) + UI-CATALOG + BRIEFING + ADR ARQ-0001 (selling-price multiplier) + `produtos-gap.md` | Recebe (P6) os RUNBOOKs de produto que ainda moram em Inventory (ADIADO) | **Virar porta `Produto`** (telas core `ProductController`, ≠ `Modules/ProductCatalogue`). Não fundir em Estoque — cadastro ≠ saldo |
| **`Purchase/`** | BRIEFING **tombstone** ("REPARTIDO KL-E2") + **ainda tem** RUNBOOK-create/index + create-visual-comparison | Redirect parcial: aponta p/ `Compras/_telas/` mas 2 RUNBOOKs (create/index) **permanecem** aqui (blobs ≠ dos de Compras/_telas — não migrados, versões antigas) | **FUNDIR em Compras** (P5). Terminar a migração: mover/tombar os 2 RUNBOOKs residuais |
| **`Compras/`** | Módulo PT completo: SPEC + BRIEFING + AUDIT-SENIOR + DISCOVERY-LARISSA + CAPTERRA + `_telas/` (RUNBOOK-purchase-create/edit + visual-comparisons) | Recebe Purchase (P5) | **Dono canônico de compras.** Já tem `_telas/` com RUNBOOKs de purchase |
| **`StockAdjustment/`** | Só BRIEFING **tombstone** ("REPARTIDO KL-E2") | RUNBOOKs já movidos p/ `Estoque/_telas/` | **Já fundido em Estoque** — pasta é redirect stub. Limpável quando conveniente |
| **`StockTransfer/`** | Só BRIEFING **tombstone** ("REPARTIDO KL-E2") | RUNBOOKs já movidos p/ `Estoque/_telas/` | **Já fundido em Estoque** — pasta é redirect stub |

**Confirmação da triagem** ([_TRIAGEM-IDENTIDADE-2026-06.md](../requisitos/_TRIAGEM-IDENTIDADE-2026-06.md) P5/P6/P7): as decisões batem — P5 Purchase→Compras, P6 Produto=porta+Inventory reparte, P7 Estoque absorve Inventory/StockAdjustment/StockTransfer. Status "ADIADO (cluster Estoque)". **Refino desta sessão:** a consolidação está **parcialmente executada** — StockAdjustment/StockTransfer já são stubs, Purchase é redirect com 2 RUNBOOKs residuais, e a duplicata Inventory SPEC × Estoque/_telas/SPEC-inventory ainda vive dupla. O trabalho restante é: (1) unificar 1 SPEC Inventory + atualizar status pós-BOM-Fase-1, (2) terminar migração dos 2 RUNBOOKs de Purchase, (3) repartir os RUNBOOKs de produto Inventory→Produto (se ainda houver), (4) limpar stubs.

---

## Conclusão — reusar nativo × construção real

O que **dá pra reusar do nativo** (não reconstruir): tipos de produto (single/variable/modifier/combo), **grade tamanho×cor** (product_variations→variations→VLD, provado em prod pelo Vestuario), **multi-local** (business_locations + product_locations), **multi-unit/dimensional mecânico** (allow_decimal + base_unit_multiplier + sub_units — cobre a conversão bobina→m² e ml de tinta), stock adjustment/transfer, opening stock, alert_quantity, barcode/SKU, e a **reserva FSM** (ADR 0129/0143). Além disso, o **BOM canônico Fase 1 já está construído no core** (`app/Domain/Inventory/` — `product_bom`+`BomResolver` multi-level + CRUD API + resolução nos side-effects) — logo BOM não é greenfield, é **plugar nos verticais** (OficinaAuto não consome; UI drag-drop US-INV-002 falta).

A **construção real** que sobra concentra-se em 3 blocos: (1) **camada Inventory avançado ainda proposta** — `product_batches` (lote central/FEFO), `stock_movements` (ledger unificado append-only), custo dimensional por-unidade (R$/ml, R$/m²), estoque negativo opt-in — nada disso existe além de rudimentos; (2) **integrações vertical-específicas** já com infra parcial — grade-curva + devolução-que-reintegra (Vestuario, serviços órfãos), custo-por-OS + tinta CMYK (ComVis), kit-na-OS + location-default + OEM cross-reference (OficinaAuto); (3) **higiene de consolidação** das 6 pastas (fundir/tombar). Dois achados de drift a corrigir no canon: **DOC-RAIZ diz que a baixa de peça na OS foi revertida — está STALE, o `baixarEstoqueConclusao` está LIVE em origin/main**; e a **dupla SPEC Inventory** com status "proposed/não-iniciado" ignora que BOM Fase 1 shipou.
