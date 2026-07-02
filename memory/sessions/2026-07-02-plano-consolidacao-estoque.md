---
date: "2026-07-02"
topic: "Plano de consolidação e evolução do domínio Estoque — unifica 2 SPECs sobrepostos, reordena roadmap por valor transversal (decisional > transacional), fecha furo fiscal (custo médio móvel/Bloco K); Onda 0 + 4 ondas com gates de sinal (ADR 0105)"
authors: [C]
type: plano-consolidacao
metodo: "2 pesquisas profundas paralelas (estado-da-arte externo 2026 + mapa interno nativo×verticais×fragmentação), verificadas contra origin/main; síntese"
related_adrs: [0093, 0105, 0106, 0121, 0129, 0143, 0192, 0265]
insumos:
  - 2026-07-02-arte-estoque-inventory-smb-2026.md
  - 2026-07-02-mapa-interno-estoque-verticais.md
related_adrs_slugs:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0129-state-machine-canonica-fsm-rbac
---

# Plano do Estoque — consolidação + evolução (2026-07-02)

> **Natureza deste doc:** plano PROPOSTO. Não cria roadmap paralelo — a execução vira **edição do Estoque
> canon** (`memory/requisitos/Estoque/`) + US no MCP após aprovação Wagner. Fonte de verdade do estado
> atual segue o [DOC-RAIZ-ESTOQUE.md](../requisitos/Estoque/DOC-RAIZ-ESTOQUE.md). Insumos: os 2 estudos
> acima (externo + interno), ambos verificados contra `origin/main`.

## 1. Diagnóstico — por que "não se acha nada no estoque"

O domínio Estoque hoje é **duas camadas do mesmo assunto tratadas como coisas separadas**, espalhadas em 6 pastas:

- **Camada integridade** (`Estoque/` SPEC v1.0 **ATIVO** + DOC-RAIZ) — como o saldo se move HOJE, invariantes INV-1..6, riscos R1-R6. É realidade LIVE.
- **Camada avançada** (`Inventory/` SPEC **PROPOSED/ADIADO**) — o que queremos construir: Kits/BOM, Batch, Dimensional, Movements. 25 US + roadmap 5 fases + MATRIZ-ROI.

**Três problemas concretos:**
1. **SPEC Inventory está DUPLICADO byte-a-byte** — `Inventory/SPEC.md` (570 linhas) ≈ `Estoque/_telas/SPEC-inventory-cross-vertical.md` (568 linhas), corpo idêntico, diferem só no frontmatter (v0.1.0 × v1.0). Dois arquivos, um plano.
2. **Dois drifts de canon** (o mapa interno pegou):
   - **DOC-RAIZ diz que a baixa de peça na OS (R2) foi "revertida, não está no código" — FALSO/STALE.** `ServiceOrderItemService::baixarEstoqueConclusao()` está **LIVE** em origin/main (`ServiceOrderObserver` bloco P0-2).
   - **Ambos os SPECs Inventory dizem "PROPOSED, não-iniciado em código" — STALE.** O **BOM canônico Fase 1 já shipou** (`app/Domain/Inventory/` — `product_bom` + `BomResolver` multi-level + CRUD API + resolução nos side-effects FSM).
3. **Roadmap ordenado pelo eixo errado** — priorizou por vertical-piloto (kit do Vargas) em vez de valor transversal. O que move caixa de PME (analytics + reposição) ficou enterrado em P3.

## 2. O que já existe (não reconstruir)

**Nativo UltimatePOS cobre bem:** tipos de produto (single/variable/modifier/combo), **grade tamanho×cor** (provada em prod pelo Vestuario há 2+ anos), multi-local, multi-unit/dimensional mecânico (`allow_decimal` + `base_unit_multiplier` + `sub_units` — cobre conversão bobina→m² e ml de tinta), stock adjustment/transfer, opening stock, `alert_quantity` estático, barcode/SKU.

**Já construído pelo oimpresso (não é greenfield):**
- **BOM Fase 1** — `product_bom` (multi-tenant, multi-level, `is_optional`/`allow_substitution`) + `BomResolver` recursivo (MAX_DEPTH=5, anti-ciclo) + `ProductBomController` API + `Reservar/ConsumirEstoque` v2 resolvem BOM. **Falta só a UI drag-drop (US-INV-002) e plugar na OficinaAuto.** Isso **bate Tiny/Bling** (não têm multi-level) — é diferencial real já pago.
- **Baixa de peça na OS (R2)** — LIVE. Falta refinar: plugar `BomResolver` (se item for kit, baixar componentes) + trocar "maior saldo" por location default via `ProductUtil` (auditável).
- **Reserva FSM** (ADR 0129/0143) — reserva ≠ baixa, desacoplada. É alicerce mais limpo que o de muitos concorrentes SMB e a base natural do ATP correto.

## 3. O furo que ninguém viu (achado nº1 externo)

**O valor migrou do transacional pro decisional.** BOM/Batch/Movements são *higiene* — todo ERP terá. O que os líderes vendem caro (Netstock, Cin7 ForesightAI) e o que Tiny/Bling **não** têm com profundidade é a **camada de decisão**: ABC/dead-stock, ponto-de-pedido dinâmico (safety stock), giro/cobertura. A proposta tinha 0 US completas nesses e enterrou o único que citou (US-024 analytics) em **P3**.

**E um furo fiscal:** falta **custo médio ponderado móvel** — exigido pelo **Bloco K SPED BR**. Não está em nenhuma das 25 US. É o **único ponto onde o oimpresso hoje fica ATRÁS de Tiny/Bling**. Paridade obrigatória, não diferencial.

**Forecasting IA = hype por enquanto.** ML precisa de 6-12m de histórico limpo que ainda não existe (o `stock_movements` nem foi criado). Reorder point dinâmico (fórmula fechada Z·σ·√LT) entrega ~80% do valor sem ML. Forecasting é 2027+.

## 4. Plano — Onda 0 (higiene, barato, AGORA) + 4 ondas de evolução

### Onda 0 — Consolidação documental + fix de drift (não precisa de sinal de cliente)
Higiene que resolve o P5/P6/P7 e a dor "não acho nada". Só docs/canon, zero código de produto.

> **ATUALIZADO 2026-07-02 pós-sessões paralelas:** o chip "Inventory→_pendente_" ([PR #3654](https://github.com/wagnerra23/oimpresso.com/pull/3654)) ancorou **25 US** (não 43 — 43 era contagem inflada de grep; canônico anchor-lint = 25) como `_pendente_` **só em `Inventory/SPEC.md`**. Resultado: as duas cópias **não são mais byte-idênticas** — `Inventory/SPEC.md` tem 25 âncoras `_pendente_`, a `Estoque/_telas/SPEC-inventory-cross-vertical.md` tem 0. Isso **inverte a instrução de dedup** abaixo. O FUNDIR/MATAR ([PR #3653](https://github.com/wagnerra23/oimpresso.com/pull/3653)) respeitou o limite ADIADO — não tocou o cluster Estoque.

1. **Deduplicar o SPEC Inventory (reconciliando a divergência):** o sobrevivente **precisa carregar as 25 âncoras `_pendente_`** que hoje vivem em `Inventory/SPEC.md`. Duas formas equivalentes: (a) manter `Inventory/SPEC.md` como base + trazer o frontmatter v1.0 e tombar a cópia `_telas/`; ou (b) portar as 25 âncoras pra `_telas/` e tombar `Inventory/`. **Não** tombar `Inventory/SPEC.md` cegamente (perderia o backfill fresco). Fundir como **roadmap de evolução do Estoque**, não SPEC paralelo.
2. **Corrigir os 3 drifts:** (i) DOC-RAIZ R2 (baixa OS está LIVE via `baixarEstoqueConclusao`, não "revertida") — hoje o DOC-RAIZ ainda diz "**PROPOSTO**... revertido... não está no código"; (ii) status do SPEC (BOM Fase 1 = shipada, não "PROPOSED não-iniciado"); (iii) micro-inconsistência nova: `Inventory/SPEC.md` agora tem 25 âncoras `_pendente_` mas o cabeçalho ainda diz "não-iniciado em código".
3. **Terminar a repartição:** 2 RUNBOOKs residuais de `Purchase/` → `Compras/` (P5); RUNBOOKs de produto Inventory→`Produto/` se sobrar (P6); limpar stubs StockAdjustment/StockTransfer (P7 — já são redirect).
4. **Produto = porta própria** (telas core `ProductController`), separada de Estoque (cadastro ≠ saldo). Não fundir.

### Onda 1 — Fundação de dados + paridade fiscal (transversal, alto impacto, sem pré-req bloqueante)
**`stock_movements` append-only nascendo já com `unit_cost` + custo médio ponderado móvel embutido** (G1+F4 fundidos — recomendação nº1 do estudo externo). Por quê junto: o movimento é o lugar natural do custo; criar movements sem custo e refazer depois é o retrabalho mais caro da lista. Resolve a **paridade fiscal (Bloco K)** E cria a **fonte única de dados** que habilita todo o decisional. ~16-20h IA-pair fundido (vs ~34h de F4 sozinha + retrabalho).
- Hook automático nos side-effects (Reservar/Consumir/Liberar + compra/venda/ajuste/transfer) registra o movimento — dev não esquece.
- Comando `inventory:reconcile` diário (drift saldo calculado × real) plugado no `jana:health-check`.

### Onda 2 — Camada decisional (transversal, move caixa de TODOS os verticais)
O que os líderes vendem e Tiny/Bling não têm — subir de P3→P1:
- **ABC/XYZ + dead-stock + giro/cobertura** (G2) — acha capital parado; base do reorder e do cycle count. ~10h.
- **Reorder point dinâmico + safety stock** (G3, Z·σ·√LT) — substitui o `alert_quantity` estático; evita ruptura E excesso. ~8-12h. Depende de G1 (custo) + histórico de movements.
- **Lead time do fornecedor rastreado** (G10) — insumo barato e essencial do G3. ~4h.
- **Cycle counting / inventário rotativo** (G5, ABC-driven) — acurácia sem parar a loja. ~8h. Diferencial: Tiny/Bling não têm rotativo estruturado.

### Onda 3 — Verticais (ativar POR SINAL qualificado — ADR 0105)
Infra já parcial; é plugar/UI, não reconstruir:
- **OficinaAuto** (gate: Vargas/Martinho assina pioneer): UI drag-drop BOM (US-INV-002) + plugar `BomResolver` na baixa da OS + location default + **cross-reference OEM↔aftermarket** (G6 — é o *coração* do vertical, a proposta ignorou) + product-picker na OS.
- **ComVis** (gate: ≥2 candidatos saudáveis assinam): saldo de substrato em m² decrescível + consumo de tinta ml/CMYK + **custo real por OS** (m²+tinta+MOD vs orçado) — diferencial nº1, Mubisys não tem.
- **Vestuario** (cliente já existe — dívida de correção): `DevolucaoService` que **reintegra estoque** (AC hoje não implementado) + grade-curva de reposição.

### Onda 4 — Batch/lote + validade (diferencial vertical, por sinal)
- **`product_batches` central + FEFO** (F2) + **cor-Pantone-per-lote** (US-009, ComVis — ninguém BR PME tem) + **validade proativa** (bloqueio venda vencido, alerta — G9).

### Radar (NÃO construir agora — registrado pra não re-propor)
- **ATP multicanal anti-oversell** (G4) — real e obrigatório quando for multicanal, mas **BLOQUEADO** pelo módulo Marketplaces que não existe. A reserva FSM já é o alicerce.
- **WMS-lite / picking mobile** (G7) — real mas prematuro; só compensa com galpão/bin/volume que nenhum dos 3 verticais tem.
- **Forecasting IA** (G8) — 2027+; precisa de 12m de `stock_movements` limpo. Vender antes = teatro.

## 5. Sequência e gates

```
Onda 0 (higiene docs) ──► Onda 1 (movements+custo médio) ──┬─► Onda 2 (ABC/reorder/cycle) [transversal]
   [agora, sem gate]         [sem gate — cria o histórico]  │
                                                            └─► Onda 3 (verticais) [gate ADR 0105 por vertical]
                                                                    └─► Onda 4 (batch/FEFO) [gate sinal]
Radar: ATP (bloq. Marketplaces) · WMS-lite (prematuro) · Forecast IA (2027+, precisa 12m histórico)
```

- **Onda 1 é a chave de abóbada:** sem `stock_movements`+custo, a Onda 2 inteira (decisional) e o forecasting futuro não têm fonte de dados. É o primeiro passo certo.
- **Onda 3/4 são gated por sinal** (ADR 0105): backlog só vira código quando cliente paga + reporta. Vestuario é exceção (cliente vivo, é dívida de correção).

## 6. A única decisão que é do Wagner

**Método de valoração default = custo médio ponderado móvel por business, com override.** Recomendo (é o exigido pelo Bloco K e o default global). Wagner valida — não calcula. Isso entra no SPEC como coluna `unit_cost` + regra de recompute do custo médio ANTES de escrever a migration de `stock_movements`.

**Split execução vs doc:** consolidar a **documentação já** (Onda 0 — barato, resolve a dor de fragmentação); construir **código** por onda, gated por sinal (Onda 1 pode começar sem gate por ser fundação fiscal+dados).

## 7. Próximas ações propostas (chips)
- **Chip A (Onda 0):** deduplicar SPEC Inventory + corrigir os 2 drifts (DOC-RAIZ R2 stale, status BOM) + terminar repartição Purchase→Compras + limpar stubs. Docs-only.
- **Chip B (Onda 1, só quando Wagner OK no método de valoração):** SPEC de `stock_movements`+custo médio móvel (edição de SPEC + ADR de decisão de valoração) — antes da migration.
- Ondas 2-4: backlog no MCP (`US-ESTOQUE-*`), disparadas por gate.

## Estado MCP no fechamento
- Brief #298; HITL Wagner 2 pendentes. 3 chips de anchors/FUNDIR/Inventory→pendente rodando em sessões paralelas (não colidem com este plano — Estoque estava explicitamente fora deles).
- Insumos: run estado-da-arte (114.9k tok) + run mapa-interno (172.3k tok).
