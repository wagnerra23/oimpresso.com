---
date: "2026-07-02"
topic: "Estado-da-arte de estoque/inventory para ERP SMB multi-vertical 2026 — refresca a proposta interna (4 capacidades, 25 US): valor migrou do transacional pro decisional, furo fiscal custo médio móvel/Bloco K, reorder dinâmico > forecasting IA (2027+)"
tipo: estado-da-arte
tema: gestão de estoque / inventory para ERP SMB multi-vertical
created_at: 2026-07-02
autor: agent estado-da-arte (Claude Opus 4.8)
baseline_interno: memory/requisitos/Estoque/_telas/SPEC-inventory-cross-vertical.md + MATRIZ-ROI.md (2026-05-12, ~7 semanas velha)
objetivo: refrescar e criticar a proposta interna (4 capacidades, 25 US) contra estado-da-arte 2026
tier0: NÃO comitar valores BRL — usar "[redacted Tier 0]"
---

# Estado-da-arte — Estoque/Inventory para ERP SMB multi-vertical (2026)

> **Escopo do refresh:** a proposta interna (SPEC PROPOSED) apostou em 4 capacidades — Kits/BOM, Batch/lote, Dimensional, Stock Movements unified. Este doc pesquisa como os melhores resolvem estoque em 2026, **procurando o que a proposta deixou passar** (não re-validando o que já está lá dentro). Veredito curto no fim.

---

## Estado REAL do código (checado, não assumido — 2026-07-02)

Antes de comparar, a honestidade sobre o que existe:

| Capacidade da proposta | Estado real | Evidência |
|---|---|---|
| **F1 Kits/BOM** | **Parcialmente construído** | `app/Domain/Inventory/Models/ProductBom.php`, `Services/BomResolver.php` (multi-level real, cycle guard MAX_DEPTH=5, fallback legacy `combo_variations`), `SideEffects/ReservarEstoque.php`, migration `product_bom`. UI Inertia (US-INV-002) e NFe kit (US-005) **não** confirmados no código. |
| **F2 Batch/lote** | **Só proposta** | `grep product_batches` em Migrations = **zero**. Só `purchase_lines.lot_number` (string legacy UPos). |
| **F3 Dimensional** | **Mecânica legacy só** | `units.allow_decimal` + `base_unit_multiplier` existem. Custo per-unit e alerta % **não** existem. |
| **F4 Stock Movements** | **Só proposta** | `grep stock_movements` em Migrations = **zero**. Audit ainda espalhado em 5 tabelas. |
| **Reorder / forecast / cycle count / ABC / ATP** | **Inexistente** | Legacy só tem `alert_quantity` (min estático per produto) + tela `purchase_requisition` Blade manual. Nenhuma matemática de ponto de pedido. |

**Conclusão factual:** a proposta é ~90% papel. Só o esqueleto de BOM foi codado. Isso é bom — significa que reordenar apostas AGORA custa quase nada (ainda não há sunk cost em F2-F5).

---

## FASE 1 — Como os melhores resolvem estoque em 2026

Cinco eixos de referência (não "empresas", porque o mercado se organiza por *camada*: o ERP faz o transacional, um otimizador plugado faz a inteligência). Isto é o achado nº1 da pesquisa e a proposta interna não o enxergou.

| Referência | Quem é / como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Netstock** (add-on sobre Dynamics/SAP B1/NetSuite/Acumatica) | Camada de *otimização* que senta EM CIMA do ERP transacional. Calcula safety stock item-a-item ajustando por **volatilidade de demanda × confiabilidade do fornecedor × variabilidade de lead time × MOQ** — não um min estático. Gera reposição e classifica por importância. | Prova que "reabastecimento inteligente" virou **categoria de produto própria**, desacoplada do ERP. O ERP dono do estoque que não expõe esses sinais fica só como "livro-razão". |
| **Cin7 ForesightAI** (ex-Inventoro, adquirida início 2024) | Forecasting nativo dentro do ERP: analisa histórico + tendência + sazonalidade → projeção de demanda + reposição automática. Cin7 Core também é a referência de **matrix inventory apparel** (grade tamanho×cor visual) + batch/serial + recall + 700+ integrações multicanal. | Referência de "ERP SMB que absorveu o forecasting em vez de terceirizar". Mostra o teto de features que um concorrente global cobra ~USD 350+/mês. |
| **NetSuite / SAP Business One** | Estado-da-arte transacional "pesado": BOM multi-level com approval, lot+serial+bin numerado end-to-end, subledger de inventário, ATP contínuo (on-hand − reservas − safety + POs abertas), cycle counting nativo com ABC. | Régua de completude. Caros demais pra PME BR (USD 1k+/mês), então NÃO são concorrentes — são o *mapa* do que existe de possível. |
| **Shopify + camada de sync multicanal** (Bizowie, Deposco, TrueCommerce, Linnworks) | Referência de **ATP multicanal / anti-oversell**: estoque compartilhado entre loja física + Woo + marketplaces recalculado em quase-real-time. BigCommerce 2025: **71% das marcas multicanal têm ≥1 ruptura/semana por falha de sync cross-channel**. ATP pode ser global ou por-canal (reservar margem pro canal direto). | Define a "paridade de entrada" pra quem vende em >1 canal — que é exatamente o rumo do ComVis/Vestuário BR (ML/Shopee). |
| **Vertical specialists** — Cin7 Apparel / Linx Microvix (apparel), **Mubisys / Calcgraf** (comunicação visual BR), MAM Autocat / Epicor Prophet 21 (auto parts) | Apparel: grade tamanho×cor como *feature definidora* (visual grid). ComVis BR: Mubisys/Zênite cobrem ordem→entrega→financeiro mas **NÃO** custo-real-por-OS cruzando lona m² + tinta ml + MOD. Auto parts: cross-reference OEM↔aftermarket + fitment year/make/model é o coração (fitment errado custa ~USD 2.7bi/ano ao setor). | Confirma onde há gap-de-mercado explorável (custo-real ComVis, cross-reference auto) vs onde é só paridade (grade apparel — o oimpresso já tem via variações UPos). |

**Achado transversal da Fase 1 (o que a proposta não viu):** o valor que move caixa de PME em 2026 **migrou do transacional pro decisório**. BOM/batch/movements são *higiene* (todo mundo tem ou terá). O que diferencia e o que os líderes vendem hoje é: **(a) reabastecimento inteligente (reorder point dinâmico + forecast), (b) ATP multicanal anti-oversell, (c) analytics acionável (ABC/XYZ, dead-stock, giro/cobertura), (d) cycle counting mobile.** A proposta interna tem 0 US completas nesses quatro e enterrou o único que citou (analytics US-024) como **P3**.

---

## FASE 2 — Compara com a proposta interna

Por dimensão que emergiu da Fase 1:

| Dimensão | Estado-da-arte 2026 | oimpresso hoje (código + proposta) | Distância |
|---|---|---|---|
| **BOM multi-level** | SAP B1/NetSuite/Odoo/Cin7 têm; Tiny/Bling não | **Já codado** (BomResolver recursivo + guard). Supera Tiny/Bling | **Curta — já bate o mercado PME BR** |
| **Batch/lote + FEFO + recall** | Lot+serial end-to-end, recall cirúrgico (encolhe escopo 60-70%), FEFO por expiry | Proposta madura (F2), 0 código. `lot_number` string legacy só | **Média** |
| **Dimensional (m²/ml/kg) + custo real/OS** | Nicho — só software gráfico premium (Roland proprietário). Mubisys/Zênite **não** têm custo-real | Mecânica legacy existe; custo-real proposto (F3), 0 código | **Média — mas é o diferencial nº1 ComVis** |
| **Stock movements audit unificado** | NetSuite subledger, SAP real-time | Proposta append-only sólida (F4), 0 código. Hoje: 5 tabelas UNION | **Média** |
| **Valoração (custo médio móvel / FIFO)** | Custo médio móvel é o default global e **exigido pelo Bloco K BR** (média ponderada móvel). FIFO/FEFO por camadas | **AUSENTE da proposta.** UPos tem custo de compra por linha mas sem método de valoração formal nem camadas | **LONGA — e é paridade fiscal obrigatória BR** |
| **Reorder point dinâmico + safety stock** | Netstock/Cin7: safety = f(volatilidade, lead time, fornecedor). Fórmula Z·σ·√LT | **AUSENTE.** Só `alert_quantity` estático legacy | **LONGA — gap de categoria inteira** |
| **Demand forecasting (IA)** | Cin7 ForesightAI, Netstock, Inventoro nativos. Precisam 6-12m de histórico limpo pra ganhar de heurística | **AUSENTE da proposta** | **LONGA — mas ver "hype vs real" abaixo** |
| **ATP multicanal anti-oversell** | Recalc contínuo on-hand−reservas−safety+PO; por-canal ou global | Proposta menciona sync ML no §6 mas depende de módulo Marketplaces **que não existe**. Reserva FSM existe (bom alicerce) | **LONGA** |
| **Cycle counting / inventário rotativo mobile** | ABC-driven, scan bin/item sem parar operação; substitui inventário anual | **AUSENTE da proposta.** Só ajuste manual `stock_adjustment` | **LONGA** |
| **ABC/XYZ + dead-stock + giro/cobertura** | Analytics que move caixa; base pro cycle count e pro reorder | Proposta tem US-INV-024 dashboard mas rankeado **P3** (última) | **LONGA + mal-priorizado** |
| **Barcode / picking mobile (WMS-lite)** | Esperado em ERP PME 2026; ~25% do custo de WMS full; Android substituindo Windows CE | **AUSENTE da proposta.** UPos tem barcode em PDV mas não fluxo de conferência/picking mobile | **Média** |
| **Grade apparel tamanho×cor** | Feature definidora apparel (visual grid) | **Já resolvido** via variações UPos (ROTA LIVRE live 2+ anos) | **Curta — já bate** |
| **Auto parts cross-reference / fitment** | Coração do vertical (OEM↔aftermarket, year/make/model) | **AUSENTE** — proposta trata auto só como batch+kit, ignora cross-reference | **LONGA (para o vertical OficinaAuto)** |

**Onde o oimpresso já bate ou supera o mercado PME BR (dizendo honestamente):**
- **BOM multi-level** — Tiny/Bling não têm; oimpresso **já codou**. Diferencial real.
- **Grade tamanho×cor** — paridade plena, live em produção.
- **Reserva ≠ baixa (FSM canon)** — arquitetura de reserva desacoplada (ADR 0143) é **mais limpa** que a de muitos concorrentes SMB e é o alicerce natural pra ATP correto. Isso é uma vantagem que a proposta não capitalizou.

---

## FASE 3 — O que está faltando (rankeado por impacto × esforço)

Estimativas em h IA-pair (ADR 0106, fator 10x). Foco: **gaps que a proposta de 2026-05-12 deixou passar**, não re-listar as 25 US existentes.

| # | Gap (novo, não está nas 25 US) | Impacto | Esforço IA-pair | Pré-req bloqueante? | Diferencial BR PME vs paridade |
|---|---|---|---|---|---|
| **G1** | **Custo médio ponderado móvel + valoração** (camada de custo por movimento) | **ALTO** | ~10-14h | Precede F4 movements (o movimento é o lugar natural do custo) | **Paridade OBRIGATÓRIA** — Bloco K BR exige média ponderada móvel; Tiny/Bling entregam. Sem isso, oimpresso fica *atrás* deles no fiscal |
| **G2** | **ABC/XYZ + dead-stock + giro/cobertura** (analytics de estoque) | **ALTO** | ~10h | Depende de F4 movements (fonte de dados) | **Diferencial** vs Tiny/Bling (audit fraco); habilita G3/G4. Hoje é US-024 **P3 — reordenar pra cedo** |
| **G3** | **Reorder point dinâmico + safety stock** (Z·σ·√LT, lead time do fornecedor) | **ALTO** | ~8-12h | Depende de G1 (custo) + histórico movements | **Paridade↑** — Netstock/Cin7 vendem isso caro; substitui `alert_quantity` estático. Move caixa de verdade |
| **G4** | **ATP multicanal anti-oversell** (on-hand − reservas − safety, por canal) | **ALTO** | ~12h | **BLOQUEADO** por módulo Marketplaces (não existe). Reserva FSM ajuda | **Paridade** obrigatória quando ComVis/Vestuário forem multicanal. 71% das marcas multicanal têm ruptura/semana por sync ruim |
| **G5** | **Cycle counting / inventário rotativo** (ABC-driven, sem parar operação) | **MÉDIO-ALTO** | ~8h | Depende de G2 (ABC) + F4 (ajuste vira movement) | **Diferencial** — Tiny/Bling não têm rotativo estruturado. Barato e alto valor operacional |
| **G6** | **Auto parts cross-reference / fitment** (OEM↔aftermarket, year/make/model) | **ALTO (só OficinaAuto)** | ~14h | Sinal qualificado (Vargas/Martinho assinar) | **Diferencial vertical** — é o *coração* do vertical, não batch/kit. Proposta ignorou |
| **G7** | **Barcode conferência/picking mobile (WMS-lite)** | **MÉDIO** | ~14h | Nenhum | **Paridade** emergente 2026. Baixa prioridade até ter cliente com galpão real |
| **G8** | **Demand forecasting IA** (sazonalidade + tendência) | **MÉDIO (hype-adjusted)** | ~16h+ | **Depende de 6-12m de histórico limpo** (movements) + G3 | **Diferencial** SE bem-feito. Mas ver nota "hype vs real" — não é o primeiro passo |
| **G9** | **FEFO real + validade proativa** (bloqueio venda vencido, alerta) | **MÉDIO** | ~6h | Depende de F2 batches | **Diferencial** — Tiny FIFO-only. Já está na proposta (US-022) mas como P2/F5 tardio |
| **G10** | **Lead time do fornecedor rastreado** (data pedido → recebimento) | **MÉDIO** | ~4h | Nenhum (usa purchases existentes) | Insumo barato e essencial pro G3. Proposta não tem |

### Nota honesta — estado-da-arte REAL vs hype

- **Forecasting IA (G8) é o mais hype-vulnerável.** A própria pesquisa admite: ML precisa de **6-12 meses de histórico limpo** pra ganhar de uma heurística simples (reorder point + média móvel). O oimpresso **não tem esse histórico limpo** enquanto `stock_movements` (F4) não existir e rodar por um ano. Vender "IA de previsão" agora seria teatro. **Reorder point dinâmico (G3) entrega 80% do valor com fórmula fechada, sem ML.** Forecasting IA é fase 2027+, e mesmo assim provavelmente "IA" = statistical forecasting (Holt-Winters/Croston), não LLM.
- **WMS-lite (G7) é real mas prematuro.** Só compensa quando há galpão com bin locations e volume de picking — nenhum dos 3 verticais atuais está aí ainda. Manter no radar, não construir.
- **ATP multicanal (G4) é real e obrigatório, mas bloqueado.** Depende do módulo Marketplaces inexistente. Não dá pra "começar por ele".
- **O que NÃO é hype e move caixa hoje:** custo médio móvel (G1, fiscal-obrigatório), ABC/dead-stock (G2, acha capital parado), reorder dinâmico (G3, evita ruptura e excesso), cycle counting (G5, acurácia sem parar loja).

### Coluna "diferencial BR PME" vs "paridade obrigatória" (síntese)

| Ganha de Tiny/Bling (diferencial) | Mesa de entrada (paridade obrigatória) |
|---|---|
| BOM multi-level (**já codado**) | Custo médio móvel / Bloco K (G1) — **hoje o oimpresso está ATRÁS** |
| Custo-real por OS ComVis (m²+ml+MOD) — Mubisys não tem | Lote+validade (F2) — Tiny/Bling já têm |
| Cor-Pantone-per-lote (US-009) | ATP anti-oversell multicanal (G4) quando for multicanal |
| Cycle counting rotativo (G5) | Grade tamanho×cor (**já tem**) |
| ABC/dead-stock acionável (G2) | Multi-depósito (**já tem** via product_locations) |
| Auto cross-reference/fitment (G6, vertical) | Reorder/min-max (G3) — versão estática já existe, dinâmica é upgrade |
| Stock movements audit append-only (F4) | NFe de kit (US-005) |

---

## Veredito sobre a proposta de 2026-05-12

**As 4 capacidades continuam boas apostas** — nenhuma é morta. Mas a proposta tem **3 problemas estruturais** que 7 semanas não corrigiram:

1. **Confundiu higiene com diferencial.** BOM/batch/movements são *fundação* (todo ERP terá). A proposta os trata como o produto. O diferencial que move caixa (analytics + reorder) está na F5/P3 — **enterrado no fim**.
2. **Esqueceu a paridade fiscal (custo médio móvel / Bloco K).** Este é o único gap onde o oimpresso hoje fica *atrás* de Tiny/Bling. Não pode faltar. **Não está em nenhuma das 25 US.**
3. **Ordenou por vertical-piloto (Vargas kit), não por valor-transversal.** Kits/BOM desbloqueia 1 cliente; ABC/dead-stock/reorder desbloqueia *todos*. E o custo médio móvel é pré-requisito silencioso de F4 (o movimento carrega o custo) — melhor fazer junto.

**Reordenação recomendada:**
- **Manter F1 (BOM)** — já codado, é diferencial. Terminar UI + NFe.
- **Fundir F4 (movements) com G1 (custo médio móvel)** e **subir pra logo depois de F1** — o `stock_movements` deve nascer já carregando `unit_cost` + custo médio recalculado; refazer depois é caro. Isso resolve a paridade fiscal E cria a base de dados de analytics.
- **Subir G2 (ABC/dead-stock) e G3 (reorder dinâmico) de P3→P1** — são o que os líderes vendem e o que Tiny/Bling não têm com profundidade.
- **Manter F2 (batch) e F3 (dimensional) como estão** — são diferenciais verticais reais (ComVis cor-Pantone, custo-real), ativar por sinal (ADR 0105).
- **Adiar G7 (WMS-lite) e G8 (forecast IA)** — prematuros/hype-vulneráveis.

---

## Recomendação concreta

**Comece por G1+F4 fundidos: `stock_movements` append-only nascendo com custo médio ponderado móvel embutido.** Alto-impacto (paridade fiscal Bloco K obrigatória — hoje o oimpresso está atrás de Tiny/Bling nisso — **+** vira a fonte única de dados que habilita ABC/dead-stock/reorder/forecast depois), baixo-esforço relativo (~16-20h IA-pair fundido vs ~34h da F4 sozinha + retrabalho de custo depois), e **sem pré-req bloqueante** (não depende de Marketplaces nem de histórico prévio — ele *cria* o histórico).

Contra-argumento honesto: a F1 (BOM) já está no meio do caminho e terminá-la fecha o sinal Vargas. Se o critério for "fechar cliente assinante já" (ADR 0105), termina-se F1 primeiro. Se o critério for "construir a fundação certa antes de empilhar", G1+F4 vem antes. **Minha aposta: G1+F4**, porque errar a fundação do custo/movimento é o retrabalho mais caro da lista, e o BOM já está estável o suficiente pra esperar 2 semanas.

**Próxima ação hoje:** decidir com o Wagner o método de valoração default (custo médio móvel por business, com override) e adicionar ao SPEC a coluna `unit_cost` + recompute de custo médio na spec de `stock_movements` — antes de escrever a migration. É uma edição de SPEC + 1 decisão (D-nova), não código.

---

## Fontes (Fase 1)

- [AI-Powered Inventory Forecasting Tools Compared 2026 — Prediko](https://www.prediko.io/forecasting-demand-planning/ai-powered-inventory-forecasting-tools)
- [Cin7 ForesightAI (ex-Inventoro)](https://www.cin7.com/features/inventory/forecasting/)
- [Netstock — AI safety stock optimization](https://www.netstock.com/blog/utilizing-ai-for-efficient-inventory-management-systems/)
- [Netstock (Acumatica marketplace)](https://www.acumatica.com/acumatica-marketplace/netstock-inventory-optimization-solution/)
- [Inventory Costing Methods — Descartes Finale](https://www.finaleinventory.com/accounting-and-inventory-software/inventory-costing-methods)
- [FIFO vs Moving Weighted Average — Fulfil](https://www.fulfil.io/blog/inventory-valuation-methods-fifo-moving-weighted-average/)
- [Manual Bloco K SPED Fiscal 2025 — OSP](https://ospcontabilidade.com.br/blog/manual-completo-bloco-k-sped-fiscal/)
- [Bloco K SPED 2026 estoque industrial — Ledware](https://www.ledware.com.br/2026/05/23/bloco-k-sped-fiscal-2026-estoque-industrial/)
- [Cycle Counting Best Practices — NetSuite](https://www.netsuite.com/portal/resource/articles/inventory-management/using-inventory-control-software-for-cycle-counting.shtml)
- [Inventory Cycle Count ABC Method — rfSMART](https://www.rfsmart.com/resources/inventory-cycle-counting-guide)
- [Available-to-Promise Formula 2025 — Shopify](https://www.shopify.com/uk/blog/available-to-promise)
- [Multichannel Inventory Management Problems & Solutions 2025 — Shopify](https://www.shopify.com/enterprise/blog/multi-channel-inventory-management)
- [Real-Time Inventory breaks down in high-volume ecommerce (ATP/ERP) — Bizowie](https://bizowie.com/why-real-time-inventory-breaks-down-in-high-volume-ecommerce-and-how-erp-fixes-it)
- [Apparel Inventory Control multi-channel — Cin7](https://www.cin7.com/blog/apparel-inventory-control/)
- [Fashion Variant Matrix Management (size/color) — Odoo/Braincuber](https://www.braincuber.com/blog/fashion-managing-size-color-variants-matrix-without-headaches)
- [Lot Tracking & Traceability guide 2026 — Datacor](https://www.datacor.com/resources/lot-tracking-traceability-guide)
- [Serial Number Tracking / recall scope 60-70% — The Retail Exec](https://theretailexec.com/logistics/serial-number-tracking/)
- [ERP for Automotive Parts: Fitment & Cross-Reference — Ecosire](https://ecosire.com/blog/erp-for-automotive-parts-distribution)
- [Mubisys — gestão comunicação visual BR](https://mubisys.com/)
- [Barcode scanning WMS-lite vs full WMS — ERP Software Blog](https://erpsoftwareblog.com/2025/09/benefits-of-barcode-scanning/)
- [Bling — controle de estoque](https://www.bling.com.br/funcionalidades/controle-de-estoque)
