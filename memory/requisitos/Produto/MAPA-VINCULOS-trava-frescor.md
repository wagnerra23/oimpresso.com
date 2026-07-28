---
id: requisitos-produto-mapa-vinculos-trava-frescor
---

# Mapa de vínculos "arquivo → doc obrigatório" — trava de frescor (piloto: módulo Produto)

> **O que é isto.** A matéria-prima da **trava de frescor de documentação** desenhada em
> [2026-07-20-trava-frescor-docs-produto.md](../../sessions/2026-07-20-trava-frescor-docs-produto.md).
> A trava (gate automático) vai, ao detectar mudança num arquivo de código/tela, **exigir** que os documentos
> frágeis acompanhem a mudança no mesmo PR. Este mapa responde a pergunta em aberto:
> **"mexeu no arquivo X → qual doc Y fica obrigatório?"**
>
> **Regra de âncoras (zero-órfãos).** Todo arquivo com vínculo a Produto aparece aqui como **link clicável**,
> não em `code span`. A [seção 7](#7-âncoras-completas-zero-órfãos) garante 100% dos arquivos linkados.
>
> **Status:** DRAFT de revisão. Felipe [F] + Wagner [W] conferem **antes** de codar a trava. Nada de enforcement
> implementado. Branch atual é stale — a trava (código) entra por **PR pra `main`**.
>
> **Escopo da trava** (decisão da sessão): apenas os **3 docs frágeis** — **SDD**, **BRIEFING**, **casos**.
> Os outros 3 (charter, teste, catálogo) já têm mecânica viva (gate/auto-geração) e entram como contexto `[já-coberto]`.
>
> **Links relativos a esta pasta** (`memory/requisitos/Produto/`): docs-irmãos são diretos (`BRIEFING.md`);
> código na raiz é `../../../` (`../../../app/Product.php`).

---

## 0. Atualização 2026-07-21 — decisões [F]/[W] (reenquadra este mapa)

> As pendências que estavam em aberto (§3, §5) foram decididas. O mapa deixa de ser "matéria-prima de uma
> **trava**" e passa a ser **mapeamento puro** (arquivo → doc) + roteiro de **arquivos** a existir. O enforcement
> (gate/hook/CI) **não é mais deste trabalho** — o Wagner está construindo outro mecanismo. Onde o texto abaixo
> ainda diz "trava"/"[trava]"/"escape", leia como **vínculo declarado**; o *como forçar* é do mecanismo do [W].

| # | Pendência (era) | Decisão | Efeito no mapa |
|---|---|---|---|
| 1 | Palavra de escape (texto exato) | **Não haverá escape** ([W]) | Cai o `escape_word` do YAML (§4) e toda menção a escape. |
| 2 | Q3 — Blade legacy entra na v1? | **Sim, entra na v1** ([F]) | Classe E deixa de ser `[candidato]` → vínculo firme. Zero-apodrecimento vence "não inflar escopo". |
| 3 | Q-noise — BRIEFING super-dispara | **Não é problema deste mapa** | O "super-disparo" era risco do *gate* (toque cerimonial). Enforcement é do [W]; aqui só se declara o vínculo tela ↔ BRIEFING. |
| 4 | Onde a trava mora / codar o gate | **Segurar. [W] faz outro mecanismo** | Entrego só **mapeamento + arquivos**. Sem gate/hook/CI neste escopo. |
| 5 | Q2 — `casos.md` `update-if-exists` | **Criar quando faltar** ([F]) | Reverte a recomendação Q2 — ver reprecificação abaixo. |

**Reprecificação do item 5 (criar `casos.md` faltantes) — o custo real.** Um `casos.md` neste projeto **não é texto
livre**: é governado por [`casos-coverage-guard.mjs`](../../../scripts/casos-coverage-guard.mjs) (ADR 0264). **G-2**
exige que **todo UC declarado tenha teste Pest citando o id** (UC órfão = quebra o CI); os UCs ancoram num contrato
`CU-PROD-NN` do [SDD §6.1](SDD-tela-cadastro-produto-v1.0.md) (nunca na tela — `proibicoes §5`); a prova roda no
**CT 100 (biz=1)**. Logo "criar `casos.md`" = **contrato + teste + prova-verde**, não um arquivo de texto. Mapeando as
6 telas do Produto sem `casos.md` contra o SDD:

| Tela sem `casos.md` | Contrato no SDD | Fechável já? |
|---|---|:-:|
| [BulkEdit](../../../resources/js/Pages/Produto/BulkEdit.tsx) | CU-PROD-06 ✅ | Sim |
| [StockHistory](../../../resources/js/Pages/Produto/StockHistory.tsx) | CU-PROD-11 🟡 fachada (G-01) | Sim — bug real p/ o teste defender |
| [Unificado/Index](../../../resources/js/Pages/Produto/Unificado/Index.tsx) | CU-PROD-12 🟡 valor ausente (G-03) | Sim |
| [Index](../../../resources/js/Pages/Produto/Index.tsx) | — sem CU dedicado | Não — CU novo no SDD (decisão [W]) |
| [Edit](../../../resources/js/Pages/Produto/Edit.tsx) | deriva do CU-PROD-01 | Não — contrato próprio a definir |
| [Show](../../../resources/js/Pages/Produto/Show.tsx) | — sem CU | Não — CU novo no SDD (decisão [W]) |

**Consequência:** só **3 de 6** têm contrato pronto; os outros **3 exigem estender o SDD antes** (decisão de contrato,
tipicamente [W]). Ordem recomendada dos `casos.md`: **StockHistory** primeiro (a US-PROD-020 já o pediu, tem
CU-PROD-11 e um bug conhecido que o teste defende) como trio-padrão validável → BulkEdit → Unificado → depois os 3 que
precisam de CU novo. Cada um é **código + teste no CT 100**, não documentação.

---

## 1. Inventário do piloto (fonte: `origin/main`, não o checkout stale)

**8 telas** em `resources/js/Pages/Produto/` — cada ✅ é âncora pro arquivo:

| Tela | `.tsx` | charter | casos | RUNBOOK | visual-comparison | testes baseline / inertia |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Index | [✅](../../../resources/js/Pages/Produto/Index.tsx) | [✅](../../../resources/js/Pages/Produto/Index.charter.md) | ❌ | [✅](_telas/RUNBOOK-produto-index.md) | [✅](_telas/produto-index-visual-comparison.md) + [matrix](_telas/produto-index-setor-matrix.md) | [B](../../../tests/Feature/Produto/Wave2IndexBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2IndexInertiaTest.php) |
| Create | [✅](../../../resources/js/Pages/Produto/Create.tsx) | [✅](../../../resources/js/Pages/Produto/Create.charter.md) | [✅](../../../resources/js/Pages/Produto/Create.casos.md) | [✅](_telas/RUNBOOK-produto-create.md) | [✅](_telas/produto-create-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2CreateBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2CreateInertiaTest.php) |
| Edit | [✅](../../../resources/js/Pages/Produto/Edit.tsx) | [✅](../../../resources/js/Pages/Produto/Edit.charter.md) | ❌ | [✅](_telas/RUNBOOK-produto-edit.md) | [✅](_telas/produto-edit-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2EditBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2EditInertiaTest.php) |
| Show | [✅](../../../resources/js/Pages/Produto/Show.tsx) | [✅](../../../resources/js/Pages/Produto/Show.charter.md) | ❌ | [✅](_telas/RUNBOOK-produto-show.md) | [✅](_telas/produto-show-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2ShowBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2ShowInertiaTest.php) |
| BulkEdit | [✅](../../../resources/js/Pages/Produto/BulkEdit.tsx) | [✅](../../../resources/js/Pages/Produto/BulkEdit.charter.md) | ❌ | [✅](_telas/RUNBOOK-produto-bulk-edit.md) | [✅](_telas/produto-bulk-edit-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2BulkEditBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2BulkEditInertiaTest.php) |
| SellingPrices | [✅](../../../resources/js/Pages/Produto/SellingPrices.tsx) | [✅](../../../resources/js/Pages/Produto/SellingPrices.charter.md) | [✅](../../../resources/js/Pages/Produto/SellingPrices.casos.md) | [✅](_telas/RUNBOOK-produto-selling-prices.md) | [✅](_telas/produto-selling-prices-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2SellingPricesBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2SellingPricesInertiaTest.php) |
| StockHistory | [✅](../../../resources/js/Pages/Produto/StockHistory.tsx) | [✅](../../../resources/js/Pages/Produto/StockHistory.charter.md) | ❌ | [✅](_telas/RUNBOOK-produto-stock-history.md) | [✅](_telas/produto-stock-history-visual-comparison.md) | [B](../../../tests/Feature/Produto/Wave2StockHistoryBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2StockHistoryInertiaTest.php) |
| Unificado/Index | [✅](../../../resources/js/Pages/Produto/Unificado/Index.tsx) | [✅](../../../resources/js/Pages/Produto/Unificado/Index.charter.md) | ❌ | ❌ | ❌ | ❌ |

**Backend** (todos âncoras na [seção 7](#backend)):
- Controllers: [ProductController.php](../../../app/Http/Controllers/ProductController.php) (~2.729 LOC, serve **7 telas** — **1:N**),
  [ProdutoUnificadoController.php](../../../app/Http/Controllers/ProdutoUnificadoController.php) (**1:1** com Unificado),
  [OpeningStockController.php](../../../app/Http/Controllers/OpeningStockController.php),
  [ImportProductsController.php](../../../app/Http/Controllers/ImportProductsController.php),
  [Inventory/ProductBomController.php](../../../app/Http/Controllers/Inventory/ProductBomController.php).
- Models (raiz `app/`, estilo UltimatePOS): [Product](../../../app/Product.php) ·
  [Variation](../../../app/Variation.php) · [ProductVariation](../../../app/ProductVariation.php) ·
  [SellingPriceGroup](../../../app/SellingPriceGroup.php) · [VariationTemplate](../../../app/VariationTemplate.php) ·
  [VariationValueTemplate](../../../app/VariationValueTemplate.php) · [Category](../../../app/Category.php) ·
  [Brands](../../../app/Brands.php) · [Unit](../../../app/Unit.php).
- Motor: [app/Utils/ProductUtil.php](../../../app/Utils/ProductUtil.php) (preço/estoque; citado pelo SDD).
- Rotas: [routes/web.php](../../../routes/web.php) (`/products/*`, `/products/unificado`) — global demais pra ser gatilho (Classe F).

**Blade legacy (fonte da migração MWART)** — [`resources/views/product/`](../../../resources/views/product/): 10 views +
26 partials = 36 arquivos, todos âncoras na [seção 7](#blade-legacy). Vigiado por
[PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md) e pelos dois `ANTI-REGRESSAO-*`.

**Docs por-módulo** (27 arquivos em `memory/requisitos/Produto/`): [SPEC.md](SPEC.md) · [BRIEFING.md](BRIEFING.md) ·
[SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md) · [UI-CATALOG.md](UI-CATALOG.md) ·
[CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) ·
[PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md) ·
[ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) ·
[ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md) ·
[produtos-gap.md](produtos-gap.md) · [PROTOTIPO-preco-especial.md](PROTOTIPO-preco-especial.md) ·
[adr/arq/0001-selling-price-multiplier.md](adr/arq/0001-selling-price-multiplier.md). (RUNBOOKs e visual-comparison na [seção 7](#docs-por-tela--módulo).)

---

## 2. O mapa (arquivo mexido → doc obrigatório)

`[trava]` = a nova trava cobre; `[já-coberto]` = mecânica existente já força; `[candidato]` = fora dos 3 frágeis, decisão [W].

### Classe A — Frontend por-tela: `resources/js/Pages/Produto/<Tela>.tsx` (1:1)
| Doc exigido | Regra | Mecanismo |
|---|---|---|
| `<Tela>.charter.md` | ler antes de editar | `[já-coberto]` skill `charter-first` + [block-mwart-violation.mjs](../../../.claude/hooks/block-mwart-violation.mjs) |
| `Wave2<Tela>{Baseline,Inertia}Test.php` | teste acompanha | `[já-coberto]` MWART F2/F4 + `mwart-gate.yml` |
| `<Tela>.casos.md` **se existir** | atualizar no mesmo PR | **`[trava]`** — só Create e SellingPrices hoje (Q2) |
| [BRIEFING.md](BRIEFING.md) | tocar no mesmo PR | **`[trava]`** — capacidade da tela pode ter mudado (Q-noise) |

### Classe B — Charter por-tela: `resources/js/Pages/Produto/<Tela>.charter.md`
| Doc exigido | Regra | Mecanismo |
|---|---|---|
| `<Tela>.casos.md` **se existir** | atualizar no mesmo PR | **`[trava]`** |
| [BRIEFING.md](BRIEFING.md) | tocar no mesmo PR | **`[trava]`** |

### Classe C — Backend compartilhado (1:N): controllers, Models, motor
Gatilhos: [ProductController.php](../../../app/Http/Controllers/ProductController.php),
[OpeningStockController.php](../../../app/Http/Controllers/OpeningStockController.php),
[ImportProductsController.php](../../../app/Http/Controllers/ImportProductsController.php),
[ProductBomController.php](../../../app/Http/Controllers/Inventory/ProductBomController.php),
[ProductUtil.php](../../../app/Utils/ProductUtil.php) e os 9 Models (ver seção 1).
**Não apontam pra uma tela** — raiz do 1:N. Resolução (Q1): sobe pro nível **módulo**.

| Doc exigido | Regra | Mecanismo |
|---|---|---|
| [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md) | tocar no mesmo PR | **`[trava]`** — mudança de arquitetura/dados |
| [BRIEFING.md](BRIEFING.md) | tocar no mesmo PR | **`[trava]`** |
| _(nenhum doc por-tela)_ | — | por design: não dá pra saber qual tela |

### Classe D — Backend dedicado (1:1): [ProdutoUnificadoController.php](../../../app/Http/Controllers/ProdutoUnificadoController.php)
| Doc exigido | Regra | Mecanismo |
|---|---|---|
| [SDD](SDD-tela-cadastro-produto-v1.0.md) + [BRIEFING](BRIEFING.md) | como Classe C | **`[trava]`** |
| `Unificado/Index.casos.md` **se existir** | update-if-exists (hoje não existe → noop) | **`[trava]`** |

### Classe E — Blade legacy: [`resources/views/product/*.blade.php`](../../../resources/views/product/) (+ partials)
Mexer nele = o legado mudou → docs que comparam charter/tela contra o legado podem ter apodrecido.

| Doc exigido | Regra | Mecanismo |
|---|---|---|
| [PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md) | atualizar no mesmo PR | **`[candidato]`** (Q3) |
| [ANTI-REGRESSAO-*](ANTI-REGRESSAO-cadastro-produto-legacy.md) | se tocar cadastro/variação | **`[candidato]`** (Q3) |
| [FormacaoPrecoParidadeLegadoTest](../../../tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php) / `*ContratoTest` | teste de paridade acompanha | `[já-coberto]` |

### Classe F — Rotas: [routes/web.php](../../../routes/web.php)
Global (tocado por qualquer feature). Usá-lo de gatilho = super-ruído. **Recomendo exemptar** — rota nova de
produto já arrasta o `.tsx`/controller (Classes A/C). FQCN já é coberto por
[.claude/rules/routes.md](../../../.claude/rules/routes.md).

### Fora de gatilho
- Editar um **doc** não é mudança de código — não dispara nada. Elo doc↔doc é a política **BRIEFING zero-órfãos**.
- `_components/`, `_telas/` helpers, `Pages/_Showcase/*` — exemptos (mesma lista do `block-mwart-violation.mjs`).
- [UI-CATALOG.md](UI-CATALOG.md) é **auto-regenerável** — nunca é "doc exigido", é projeção da fonte.

---

## 3. Resolução das questões de design

### Q1 — Vínculo tela ↔ controller é 1:N. **Recomendo: separar por granularidade.**
Backend compartilhado **não exige doc por-tela** — sobe pro nível módulo ([SDD](SDD-tela-cadastro-produto-v1.0.md) +
[BRIEFING](BRIEFING.md)). Docs por-tela só saem do gatilho **1:1** que é o próprio `.tsx`. Dissolve o 1:N sem
declaração manual.

### Q2 — [casos.md](../../../resources/js/Pages/Produto/Create.casos.md) só existe em 2/8. **Recomendo: `update-if-exists`, não `force-create`.**
Exige atualizar onde já existe ([Create](../../../resources/js/Pages/Produto/Create.casos.md),
[SellingPrices](../../../resources/js/Pages/Produto/SellingPrices.casos.md)). **Não** força criar os 6 ausentes —
isso é backlog de conteúdo, não frescor. Preencher os 6 vira **task separada**.

### Q3 — Blade legacy → PARIDADE/ANTI-REGRESSAO entram na trava? (achado na varredura)
Esses docs também apodrecem, mas estão **fora** dos 3 frágeis originais. Marquei `[candidato]`. **Decisão [W]:**
incluir a Classe E na v1 ou deixar pra v2. Não incluí por padrão pra não inflar o escopo acordado.

### Q-noise — [BRIEFING](BRIEFING.md) dispara em *todo* `.tsx`?
Vai **super-disparar** (um tweak de CSS forçaria tocar o BRIEFING). É intencional — BRIEFING é o que mais
apodrece — e os falsos-positivos são o caso da **palavra de escape**. **Decisão [W]:** aceitar ruído + escape,
ou afrouxar pra "só quando muda contrato" (que um gate mecânico não detecta de diff sozinho).

---

## 4. Semente legível-por-máquina (config que a trava vai consumir)

Globs ficam em `code` (são padrões, não âncoras). Um bloco por regra:

```yaml
# trava-frescor.produto.yml  (DRAFT — vira config real na PR da trava)
modulo: Produto
escape_word: "<PENDENTE-WAGNER>"       # texto exato = decisão [W]
regras:
  - id: tela-casos
    gatilho: "resources/js/Pages/Produto/**/{*.tsx,*.charter.md}"
    exige: ["${dir}/${tela}.casos.md"]  # só se o alvo já existir
    modo: update-if-exists
  - id: tela-briefing
    gatilho: "resources/js/Pages/Produto/**/*.tsx"
    exige: ["memory/requisitos/Produto/BRIEFING.md"]
    modo: touch-required
  - id: backend-modulo
    gatilho:
      - "app/Http/Controllers/ProductController.php"
      - "app/Http/Controllers/ProdutoUnificadoController.php"
      - "app/Http/Controllers/OpeningStockController.php"
      - "app/Http/Controllers/ImportProductsController.php"
      - "app/Http/Controllers/Inventory/ProductBomController.php"
      - "app/Utils/ProductUtil.php"
      - "app/{Product,Variation,ProductVariation,SellingPriceGroup,VariationTemplate,VariationValueTemplate,Category,Brands,Unit}.php"
    exige:
      - "memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md"
      - "memory/requisitos/Produto/BRIEFING.md"
    modo: touch-required
  - id: legado-paridade          # CANDIDATO — decisão [W] (Q3)
    gatilho: "resources/views/product/**/*.blade.php"
    exige:
      - "memory/requisitos/Produto/PARIDADE-charter-vs-legado.md"
      - "memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md"
    modo: touch-required
    status: candidato
  # EXEMPTO de propósito: routes/web.php (global, super-ruído — Classe F)
```

`modo`: `touch-required` = alvo tem que aparecer no diff do PR; `update-if-exists` = idem, só se o alvo já existe.
`exige` compara contra `git diff --name-only origin/main...HEAD` (mesma base dos gates atuais).

---

## 5. Pendências pra fechar antes de codar a trava

- **[W]** Texto exato da **palavra de escape** (conceito já aprovado).
- **[W]** Q3 (Classe E na v1?) e Q-noise (super-disparo de BRIEFING).
- **[F/W]** Confirmar Q1 (backend → módulo) e Q2 (`update-if-exists`).
- **[F]** "Mora em todos os pontos de entrada" → `settings.json` versionado (local) + workflow CI (time).
- Task separada (fora da trava): preencher os **6 [casos.md](../../../resources/js/Pages/Produto/) ausentes**
  (Index, Edit, Show, BulkEdit, StockHistory, Unificado).
- **[F/W]** Concern à parte (ver §6): **raio de quebra cross-módulo** quando o contrato do Produto muda — é
  análise de impacto, não frescor de doc. Decidir se vira parte da trava ou mecanismo separado.

---

## 6. Varredura de completude (onde olhei — pra auditar)

Enumeração exaustiva sobre `origin/main`. ✅ = varrido; achado incorporado acima.

| Pasta / padrão | Resultado |
|---|---|
| `resources/js/Pages/Produto/**` (recursivo) | ✅ 18 arquivos (8 tela + 8 charter + 2 casos). Sem `_components`. |
| `resources/js/{Components,Layouts}/**` produto | ✅ nenhum componente React compartilhado Produto-específico |
| `resources/views/product/**` (Blade + partials) | ✅ 36 arquivos → Classe E |
| `app/Http/Controllers/**` produto | ✅ 5 controllers |
| `app/*.php` (Models raiz UltimatePOS) | ✅ 9 Models → Classe C |
| `app/Utils/ProductUtil.php` | ✅ Classe C |
| `Http/Requests/**` produto | ✅ nenhum (valida inline no controller) |
| `routes/web.php` | ✅ Classe F (exempto) |
| `tests/**` produto | ✅ Wave2 (14) + Contrato/Paridade (5) — todos `[já-coberto]` |
| `memory/requisitos/Produto/**` | ✅ 27 docs |
| charter/casos `Produto` fora de `Pages/Produto` | ✅ nenhum |

**Fora deste mapa — não porque não têm vínculo, mas porque o vínculo pertence a OUTRO mapa.**
Módulos que *consomem* produto ([ProductCatalogue](../../../Modules/ProductCatalogue/) 2 docs, Manufacturing 12,
Connector 3, OficinaAuto 30, Repair produção 28) referenciam o model [Product](../../../app/Product.php) de verdade
(ex.: `ProductCatalogueRepository`). Mas a trava é **por-módulo** e sua regra é "arquivo → doc **do módulo dono do arquivo**":

- Um arquivo do Manufacturing mantém fresco o doc **do Manufacturing**, não o do Produto. Ele entra no **mapa do
  Manufacturing** quando a trava generalizar — cada módulo tem sua pasta `memory/requisitos/<Mod>/` própria.
- Pôr esses arquivos aqui estaria **errado no sentido**: forçaria o [BRIEFING](BRIEFING.md)/[SDD](SDD-tela-cadastro-produto-v1.0.md)
  do Produto a mudar quando alguém edita o Manufacturing. E **explodiria o escopo**: Produto é o "registro-mãe"
  consumido por Vendas/Compras/Fiscal/Produção — "consome produto" ≈ o ERP inteiro.

> ⚠️ **Buraco real que este mapa NÃO cobre (item aberto).** Quando o **contrato/schema** do Produto muda
> (campo novo em [Product](../../../app/Product.php), regra de preço em [ProductUtil](../../../app/Utils/ProductUtil.php)),
> os consumidores *podem quebrar*. Isso é vínculo legítimo, mas é **outro mecanismo** — *análise de impacto /
> raio de quebra*, não frescor de doc. Não se resolve empilhando arquivos de consumidor aqui. Registrar como
> concern separado (decisão [F/W] se vira parte da trava ou item à parte).

---

## 7. Âncoras completas (zero órfãos)

Todo arquivo com vínculo a Produto, linkado. Se um dia entrar/sair arquivo, esta lista é a fonte da trava.

### Frontend (telas)
Index [tsx](../../../resources/js/Pages/Produto/Index.tsx) · [charter](../../../resources/js/Pages/Produto/Index.charter.md) —
Create [tsx](../../../resources/js/Pages/Produto/Create.tsx) · [charter](../../../resources/js/Pages/Produto/Create.charter.md) · [casos](../../../resources/js/Pages/Produto/Create.casos.md) —
Edit [tsx](../../../resources/js/Pages/Produto/Edit.tsx) · [charter](../../../resources/js/Pages/Produto/Edit.charter.md) —
Show [tsx](../../../resources/js/Pages/Produto/Show.tsx) · [charter](../../../resources/js/Pages/Produto/Show.charter.md) —
BulkEdit [tsx](../../../resources/js/Pages/Produto/BulkEdit.tsx) · [charter](../../../resources/js/Pages/Produto/BulkEdit.charter.md) —
SellingPrices [tsx](../../../resources/js/Pages/Produto/SellingPrices.tsx) · [charter](../../../resources/js/Pages/Produto/SellingPrices.charter.md) · [casos](../../../resources/js/Pages/Produto/SellingPrices.casos.md) —
StockHistory [tsx](../../../resources/js/Pages/Produto/StockHistory.tsx) · [charter](../../../resources/js/Pages/Produto/StockHistory.charter.md) —
Unificado [tsx](../../../resources/js/Pages/Produto/Unificado/Index.tsx) · [charter](../../../resources/js/Pages/Produto/Unificado/Index.charter.md)

### Backend
Controllers: [ProductController](../../../app/Http/Controllers/ProductController.php) ·
[ProdutoUnificadoController](../../../app/Http/Controllers/ProdutoUnificadoController.php) ·
[OpeningStockController](../../../app/Http/Controllers/OpeningStockController.php) ·
[ImportProductsController](../../../app/Http/Controllers/ImportProductsController.php) ·
[ProductBomController](../../../app/Http/Controllers/Inventory/ProductBomController.php) —
Motor: [ProductUtil](../../../app/Utils/ProductUtil.php) —
Models: [Product](../../../app/Product.php) · [Variation](../../../app/Variation.php) ·
[ProductVariation](../../../app/ProductVariation.php) · [SellingPriceGroup](../../../app/SellingPriceGroup.php) ·
[VariationTemplate](../../../app/VariationTemplate.php) · [VariationValueTemplate](../../../app/VariationValueTemplate.php) ·
[Category](../../../app/Category.php) · [Brands](../../../app/Brands.php) · [Unit](../../../app/Unit.php) —
Rotas: [routes/web.php](../../../routes/web.php)

### Blade legacy
Views: [index](../../../resources/views/product/index.blade.php) · [create](../../../resources/views/product/create.blade.php) ·
[edit](../../../resources/views/product/edit.blade.php) · [show](../../../resources/views/product/show.blade.php) ·
[bulk-edit](../../../resources/views/product/bulk-edit.blade.php) ·
[add-selling-prices](../../../resources/views/product/add-selling-prices.blade.php) ·
[stock_history](../../../resources/views/product/stock_history.blade.php) ·
[stock_history_details](../../../resources/views/product/stock_history_details.blade.php) ·
[view-modal](../../../resources/views/product/view-modal.blade.php) ·
[view-product-group-prices](../../../resources/views/product/view-product-group-prices.blade.php)

Partials: [bulk_edit_product_row](../../../resources/views/product/partials/bulk_edit_product_row.blade.php) ·
[bulk_edit_variation_row](../../../resources/views/product/partials/bulk_edit_variation_row.blade.php) ·
[combo_product_details](../../../resources/views/product/partials/combo_product_details.blade.php) ·
[combo_product_entry_row](../../../resources/views/product/partials/combo_product_entry_row.blade.php) ·
[combo_product_form_part](../../../resources/views/product/partials/combo_product_form_part.blade.php) ·
[edit_product_location_modal](../../../resources/views/product/partials/edit_product_location_modal.blade.php) ·
[edit_product_variation_row](../../../resources/views/product/partials/edit_product_variation_row.blade.php) ·
[edit_single_product_form_part](../../../resources/views/product/partials/edit_single_product_form_part.blade.php) ·
[edit_variable_product_form_part](../../../resources/views/product/partials/edit_variable_product_form_part.blade.php) ·
[product_list](../../../resources/views/product/partials/product_list.blade.php) ·
[product_stock_details](../../../resources/views/product/partials/product_stock_details.blade.php) ·
[product_variation_row](../../../resources/views/product/partials/product_variation_row.blade.php) ·
[product_variation_template](../../../resources/views/product/partials/product_variation_template.blade.php) ·
[quick_add_product](../../../resources/views/product/partials/quick_add_product.blade.php) ·
[quick_product_opening_stock](../../../resources/views/product/partials/quick_product_opening_stock.blade.php) ·
[single_product_details](../../../resources/views/product/partials/single_product_details.blade.php) ·
[single_product_form_part](../../../resources/views/product/partials/single_product_form_part.blade.php) ·
[toggle_woocommerce_sync_modal](../../../resources/views/product/partials/toggle_woocommerce_sync_modal.blade.php) ·
[variable_product_details](../../../resources/views/product/partials/variable_product_details.blade.php) ·
[variable_product_form_part](../../../resources/views/product/partials/variable_product_form_part.blade.php) ·
[variation_value_row](../../../resources/views/product/partials/variation_value_row.blade.php)

### Testes
[Wave2Index B](../../../tests/Feature/Produto/Wave2IndexBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2IndexInertiaTest.php) ·
[Wave2Create B](../../../tests/Feature/Produto/Wave2CreateBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2CreateInertiaTest.php) ·
[Wave2Edit B](../../../tests/Feature/Produto/Wave2EditBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2EditInertiaTest.php) ·
[Wave2Show B](../../../tests/Feature/Produto/Wave2ShowBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2ShowInertiaTest.php) ·
[Wave2BulkEdit B](../../../tests/Feature/Produto/Wave2BulkEditBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2BulkEditInertiaTest.php) ·
[Wave2SellingPrices B](../../../tests/Feature/Produto/Wave2SellingPricesBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2SellingPricesInertiaTest.php) ·
[Wave2StockHistory B](../../../tests/Feature/Produto/Wave2StockHistoryBaselineTest.php) / [I](../../../tests/Feature/Produto/Wave2StockHistoryInertiaTest.php) —
Contrato/paridade: [CadastroProdutoContratoTest](../../../tests/Feature/Produto/CadastroProdutoContratoTest.php) ·
[FormacaoPrecoParidadeLegadoTest](../../../tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php) ·
[TabelaPrecoContratoTest](../../../tests/Feature/Produto/TabelaPrecoContratoTest.php) ·
[ProdutoEditAutosaveContractTest](../../../tests/Feature/Contract/ProdutoEditAutosaveContractTest.php) ·
[CalculoValorProdutoTest](../../../tests/Feature/Calculo/CalculoValorProdutoTest.php) ·
[ProductStockLogsActivityTest](../../../tests/Feature/Auditoria/ProductStockLogsActivityTest.php) —
Fixture: [produto_edit.php](../../../tests/Contract/Fixtures/produto_edit.php)

### Docs (por-tela + módulo)
RUNBOOKs: [index](_telas/RUNBOOK-produto-index.md) · [create](_telas/RUNBOOK-produto-create.md) ·
[edit](_telas/RUNBOOK-produto-edit.md) · [show](_telas/RUNBOOK-produto-show.md) ·
[bulk-edit](_telas/RUNBOOK-produto-bulk-edit.md) · [selling-prices](_telas/RUNBOOK-produto-selling-prices.md) ·
[stock-history](_telas/RUNBOOK-produto-stock-history.md) —
Visual-comparison: [index](_telas/produto-index-visual-comparison.md) · [create](_telas/produto-create-visual-comparison.md) ·
[edit](_telas/produto-edit-visual-comparison.md) · [show](_telas/produto-show-visual-comparison.md) ·
[bulk-edit](_telas/produto-bulk-edit-visual-comparison.md) · [selling-prices](_telas/produto-selling-prices-visual-comparison.md) ·
[stock-history](_telas/produto-stock-history-visual-comparison.md) · [setor-matrix](_telas/produto-index-setor-matrix.md) —
Módulo: [SPEC](SPEC.md) · [BRIEFING](BRIEFING.md) · [SDD](SDD-tela-cadastro-produto-v1.0.md) · [UI-CATALOG](UI-CATALOG.md) ·
[CAPTERRA-FICHA](CAPTERRA-FICHA.md) · [CAPTERRA-INVENTARIO](CAPTERRA-INVENTARIO.md) · [PARIDADE](PARIDADE-charter-vs-legado.md) ·
[ANTI-REGRESSAO cadastro](ANTI-REGRESSAO-cadastro-produto-legacy.md) · [ANTI-REGRESSAO variação](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md) ·
[produtos-gap](produtos-gap.md) · [PROTOTIPO-preco-especial](PROTOTIPO-preco-especial.md) ·
[ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)

---

_DRAFT de revisão append-only. Autor: Claude (Opus 4.8) a pedido de Felipe [F]. Piloto: módulo Produto.
Fonte do inventário: `origin/main`. Sem PII, sem mudança de código. Segue pra conferência [F]+[W] antes da PR da trava._
