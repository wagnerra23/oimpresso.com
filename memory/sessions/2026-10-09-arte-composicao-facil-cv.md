---
id: session-2026-10-09-arte-composicao-facil-cv
date: "2026-10-09"
topic: "Estado da arte — como tirar do cliente CV o peso de saber a regra de consumo e montar a composição do zero"
tipo: estado-da-arte
pii: false
---

# Estado da arte — composição fácil em Comunicação Visual (quem carrega a regra de consumo)

## TL;DR

- **O mercado não pede ao cliente que escolha a fórmula de consumo item a item.** Quem resolve isso bem (Cyrious Control, ONYX Align) põe a **dimensionalidade no cadastro do insumo** (área, comprimento, unidade). A regra de consumo vem daí por padrão, e a linha da composição só a sobrescreve quando precisa.
- **O que tira o "começar do zero" são os modelos prontos da indústria.** O shopVOX traz "Golden Products" (banner, placa rígida, envelopamento, banner retrátil...) para duplicar e ajustar, com um "Check Pricing" que testa medida e quantidade antes de salvar.
- **No dado do cliente CV, 77% das linhas usam IGUAL ou A CADA** (632 de 818 linhas com regra contada). As duas saem da unidade de medida do insumo, sem o cliente escolher nada. O que sobra para decisão humana é PERÍMETRO (3%), PERSONALIZADA (18%) e outras (1%).
- **O oimpresso já tem metade das peças, e nenhuma está ligada:** `comvis_materiais.unidade` (m² · unidade · metro linear) e `cv_acabamentos.tipo` (m_linear · unitario · m2 · fixo) existem no schema, mas o `OrcamentoCalculator` **ignora as duas** e calcula tudo como área × preço/m².
- **Recomendação:** regra de consumo **derivada da unidade do insumo**, com override na linha, mais uma **prévia com medida de exemplo**. Depois disso, um kit de modelos CV prontos. Antes de desenhar a fórmula livre, medir o que são as 146 linhas PERSONALIZADA.

> Fontes acessadas em **2026-10-09**. Código medido em `origin/main` `2540d10668`. Onde o fornecedor não publica o detalhe, está escrito **não confirmado**.
> Doc irmão do mesmo dia, outro eixo (onde mora a composição × onde mora a produção): [2026-10-09-arte-composicao-vs-producao.md](2026-10-09-arte-composicao-vs-producao.md).

## 1 · Pesquisa

**Por que a premissa dos EUA vale aqui (LC-09):** shopVOX, Cyrious e ONYX atendem *sign shop*, ou seja, peças **sob medida** (largura × altura informadas por pedido) feitas de insumo vendido em rolo ou chapa. É exatamente o produto do cliente CV. Os MIS de offset (PrintSmith, Tharstern) partem de outra premissa, papel em folha e tiragem, e por isso entram só como contraponto.

| Player | (a) Onde mora a regra | (b) Fórmula, lista ou inferência | (c) Modelos prontos | (d) Submontagem | (e) Como o usuário confere |
|---|---|---|---|---|---|
| **Cyrious Control** (sign; hoje da Corebridge) | **No insumo:** cada *Part* tem *Unit Type*, a "dimensionalidade" (área, comprimento, volume, unidade), mais % de refugo e arredondamento. **Na ligação produto↔insumo** fica a fórmula de consumo; em lista de materiais, a fórmula fica **na própria entrada da lista**, com um default no modificador quando ela está em branco [1][2] | Fórmula em variáveis (`Area = Height * Width`), sobrescrevível por produto [3]. A inferência automática da fórmula a partir do *Unit Type* **não confirmada** [1] | **Não confirmado.** Só "Formula Templates", fórmulas salvas e reusáveis [2] | **Sim:** produto filho ("child"), com o preço atribuído ao pai; o mesmo insumo pode entrar várias vezes [2] | Fórmulas de **Warning/Error** por produto (o erro impede salvar); *Variation Grid* compara configurações no orçamento [2] |
| **shopVOX Pro** (sign) | No **template do produto**: drop-downs de material/mão de obra/máquina e *default items*. O material guarda unidade de venda (m² ou chapa) e a razão compra/venda [4][5] | Fórmulas de sistema não editáveis (`Area`, `Perimeter = 2*(Width+Height)`, `Volume`) mais fórmulas próprias; ex.: ilhós = `Math.ceil(((Width+Height)*2)/Grommets_Spacing)` [6] | **Sim, "Golden Products":** Banner, Yard Sign, Retractable Banner Stand, Magnetic Sign, Rigid Sign, Vehicle Wrap... para duplicar e ajustar [7] | **Não confirmado** [5] | **"Check Pricing":** escolhe tamanho e quantidade e vê o preço antes de usar [8] |
| **ONYX Align** (sign/print) | **No item (modificador):** *cost type* **Linear** (comprimento do job), **Each** (unidade) ou **SQFT** (área do job) [9] | **Inferência pela unidade:** escolhe o tipo, o sistema aplica à medida do job. Sem fórmula | Não confirmado | Não confirmado | Não confirmado |
| **Avanti Slingshot** (MIS, grande formato) | **No motor de orçamento:** o módulo de grande formato considera imposição, material, selagem de borda, **posição de ilhós** e cobertura de tinta [10] | Regra embutida no motor, não escrita pelo usuário (detalhe **não confirmado**) | "Standard products and product kits" no orçamento [11] | "Product kits" [11]; recursão não confirmada | Não confirmado |
| **Corebridge** (sign) | **Não confirmado.** Só material de marketing: "Templates for common jobs" e custo de material travado no orçamento [12]. Um usuário relata que montar a precificação é trabalhoso no começo [13] | Não confirmado | "Templates for common jobs" [12] (conteúdo não confirmado) | Não confirmado | Não confirmado |
| **Mubisys** (CV BR) | **Não confirmado.** Anuncia "precificação automática", "diversos tipos de produtos e com cálculos complexos" (fachadas, letras caixa, banners) e "algoritmos de aproveitamento de materiais" [14] | Não confirmado | Não confirmado | Não confirmado | Não confirmado |
| **Calcgraf / NetCalc** (gráfica BR) | **Não confirmado.** Em CV anuncia "automatização do cálculo de emendas para orçamento de grandes formatos" [15]. A ficha técnica nasce **na aprovação do pedido** [16] | Não confirmado | Reaproveita características de produções anteriores ao re-orçar [16] | Não confirmado | Não confirmado |
| **Zênite (ZSL), Calcme, Visua, Simplifica, Alfa** | **Não confirmado:** nada público sobre composição ou regra de consumo. A Calcme anuncia só "cálculo automático" com insumos e máquinas [17] | — | — | — | — |
| **PrintSmith / Tharstern** (MIS offset, contraponto) | PrintSmith: o grande formato usa as mesmas matrizes do offset, e usuários de sign reclamam [18]. Tharstern: "presets" por equipamento em listas suspensas; o Estimate Pro **não é por template**, reusa orçamentos anteriores [19] | Lista de presets | Biblioteca de orçamentos anteriores [19] | Não confirmado | Não confirmado |

### O padrão que aparece

1. **A dimensionalidade é do insumo, não da linha.** Cyrious põe *Unit Type* na *Part*; ONYX põe *Linear/Each/SQFT* no item; o material do shopVOX tem unidade de venda. Em nenhum deles o usuário escolhe "IGUAL" em cada linha de cada produto.
2. **Fórmula livre é a válvula de escape, não o caminho principal.** Existe (Cyrious, shopVOX), mas os modelos prontos já trazem as fórmulas escritas por quem conhece o ramo.
3. **Começar de um modelo, não do zero.** Golden Products é o mecanismo documentado mais explícito, e o Avanti fala em "standard products and product kits".
4. **Conferir antes de salvar.** "Check Pricing" (medida + quantidade → preço) e fórmulas de erro que impedem salvar.

O BR público é raso: nenhum dos 7 publica como modela a regra. Não dá para afirmar que o BR resolve ou não resolve. **Não confirmado.**

## 2 · Comparação com o oimpresso

**O dado do cliente** (não o modelo): 219 produtos compostos, 1.041 linhas. As regras somadas dão **818**; as 223 restantes não vieram classificadas no relato. Sobre as 818:

| Regra legado | Linhas | % | Sai da unidade do insumo? |
|---|---|---|---|
| IGUAL (pela área da peça) | 456 | 55,7% | sim: insumo em m² |
| A CADA (por unidade) | 176 | 21,5% | sim: insumo em unidade |
| PERSONALIZADA | 146 | 17,8% | **não**: precisa de fórmula; conteúdo **não medido** |
| PERÍMETRO (borda) | 28 | 3,4% | sim, se o insumo for metro linear de borda; ilhós precisa de espaçamento |
| outras (proporcional, ilhós, folhas) | 12 | 1,5% | folha/chapa precisa de aproveitamento |

**Leitura:** a dor relatada ("o cliente nunca sabia qual fórmula aplicar") nasce de o legado pedir a escolha **na linha**, entre 7 opções, sem default. Em 3 de cada 4 linhas a resposta já estava escrita na unidade do insumo.

| Dimensão | Estado-da-arte | oimpresso hoje (medido em `origin/main`) | Distância |
|---|---|---|---|
| Regra de consumo por medida | Dimensionalidade no insumo + fórmula na ligação (Cyrious) | `mfg_recipe_ingredients` tem só `quantity` fixa + `sub_unit_id` + grupo + % de perda. **Nenhuma regra por medida** | **longa** |
| Unidade do insumo CV | Material sabe se é área, comprimento ou unidade | **Existe e está morta:** `comvis_materiais.unidade` aceita `m2 · unidade · metro_linear`, mas `OrcamentoCalculator` faz sempre `largura × altura × qtd × preco_venda_m2`, sem ler `unidade`. Um material cadastrado como metro linear seria cobrado por área. **Latente:** os 5 materiais do seeder são `m2`; dado de prod **não medido** | **curta** (falta ligar) |
| Acabamento com regra própria | ONYX Linear/Each/SQFT; shopVOX ilhós por perímetro/espaçamento | **Existe e está morto:** `cv_acabamentos.tipo` (`m_linear · unitario · m2 · fixo`) só é usado em testes de isolamento. O orçamento soma "extras" digitados à mão | **curta** (falta ligar) |
| Catálogo de insumo CV | Um catálogo | **Dois:** `comvis_materiais` (legacy, usado pelo calculator) e `cv_substratos` (canon SPEC §12.1, usado só pela `OrdemProducao`). O comentário da migration adia a unificação | média |
| Modelos prontos | Golden Products (shopVOX) | Seeder com 5 **materiais** (lona, vinil, ACM, plotter). **Zero modelos de produto** | longa |
| Submontagem | Produto filho (Cyrious) | `product_bom` + `BomResolver` recursivo (MAX_DEPTH=5, detecta ciclo). `mfg_recipes`: resolução recursiva **não encontrada** (o único "recursiv" no módulo é conversão de moeda) | média (a peça existe em outra tabela) |
| Prévia com medida | Check Pricing | O editor de receita mostra custo por unidade-base; **nenhum campo de largura/altura** (`git grep` em `IngredientesEditor.tsx`: 0) | longa |
| Validação | Warning/Error que impede salvar | Não encontrado para composição | média |

**Onde o oimpresso já está à frente dos BR com fonte:** recursão de BOM com detecção de ciclo e custo da receita com preço simulado. Nenhum concorrente BR publica isso. Os concorrentes dos EUA não mostram isso em doc pública.

## 3 · O que falta, por impacto × esforço

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req |
|---|---|---|---|---|
| 1 | **Regra de consumo derivada da unidade do insumo, com override na linha.** m² → consome a área; unidade → N por peça; metro linear → perímetro; folha/chapa → peças por folha. Ligar `comvis_materiais.unidade` e `cv_acabamentos.tipo` ao `OrcamentoCalculator` | **alto** (cobre 77% das linhas sem escolha) | 6–8h | Mexe em VALOR → **Regra Mestre** (dupla prova + antes→depois + [W]) |
| 2 | **Prévia com medida de exemplo** na tela da composição: "para 1,00 × 2,00 m consome 2,00 m² de lona, 6,00 m de bainha, 12 ilhós", com custo | **alto** (o cliente vê se está certo) | 3–4h | #1 |
| 3 | **Medir as 146 linhas PERSONALIZADA** do cliente: o que são, quantas viram uma das regras padrão, quantas precisam mesmo de fórmula | alto (decide se existe editor de fórmula) | ~1h de análise | dado do cliente legado |
| 4 | **Kit de 6–8 modelos CV prontos** (banner com ilhós, adesivo, placa ACM, lona backlight, fachada, totem) semeado por business, para duplicar e ajustar | médio-alto | 4–6h | #1 + a decisão do irmão sobre **qual tabela é a composição** |
| 5 | **Submontagem** ("impressão por m²" = tinta + solvente; "kit de acabamento") consumindo pela medida da peça-mãe | médio | reusar `BomResolver`; 3–5h | decisão do doc irmão (#2 lá: uma fonte só de composição) |
| 6 | Unificar `comvis_materiais` × `cv_substratos` | médio | ~1 dia + migração | decisão [W] |
| 7 | Fórmula livre com variáveis (Largura, Altura, Área, Perímetro, espaçamento), como válvula de escape | médio-baixo, até o #3 dizer o contrário | 1–2 dias | #3 |

**O desenho, em uma frase:** a regra mora **no insumo** (herdada pela unidade de medida, como Cyrious e ONYX); a linha da composição só **sobrescreve**; o produto **nasce de um modelo** (como os Golden Products); e o cliente **vê o consumo numa medida de exemplo** antes de salvar (como o Check Pricing). As três premissas valem porque o cliente CV, como o *sign shop*, vende peça sob medida feita de rolo e chapa.

**Ressalva de escopo:** o #1 cabe no `OrcamentoCalculator` sem esperar a unificação da composição. Mas, se a composição ficar em `mfg_recipes`, a mesma regra tem de valer lá, senão o orçamento cobra uma coisa e a produção baixa outra. A ordem certa depende da decisão do doc irmão.

## Recomendação

**Comece pelo #3 (medir as PERSONALIZADA), em paralelo com o #1.** O #3 custa ~1h, não tem pré-requisito técnico e decide se o oimpresso precisa de editor de fórmula ou só de regras padrão com override. Construir o editor antes de saber seria importar a solução do shopVOX sem checar a premissa (LC-09).

**Próxima ação hoje:** puxar do legado do cliente CV as 146 linhas PERSONALIZADA (produto, insumo, fórmula escrita) e classificar cada uma: "é IGUAL/A CADA/PERÍMETRO disfarçada" · "precisa de espaçamento (ilhós)" · "precisa de folha/aproveitamento" · "fórmula de verdade". Sem PII e sem valores no doc.

## Fontes (acessadas em 2026-10-09)

1. [Cyrious Control — Parts (pricing ch. 4)](https://control.cyriouswiki.com/pricing_ch_04-parts)
2. [Cyrious Control — Products (pricing ch. 3)](https://control.cyriouswiki.com/pricing_ch_03-products)
3. [Cyrious — how to make variable formulas](https://control.cyriouswiki.com/how_to_make_variable_formulas) · [variable override por produto](https://control.cyriouswiki.com/how_to_have_different_formulas_for_a_variable_depending_on_the_product_selected)
4. [shopVOX — Materials management guide](https://shopvox-pro.helpdocs.io/article/gsbm5w5dyz-materials-management-in-shop-vox-a-comprehensive-guide)
5. [shopVOX — Create functionality with product templates](https://shopvox-pro.helpdocs.io/article/y968jbs2ds-create-functionality-with-product-templates)
6. [shopVOX — Product templates: custom formula logic](https://shopvox-pro.helpdocs.io/article/i8rqtmn2jm-product-templates-custom-formula-logic)
7. [shopVOX — Golden Products](https://shopvox-pro.helpdocs.io/article/6ikvro5jm3-products-shop-vox-golden-products)
8. [shopVOX — Yard Sign Golden Product (Check Pricing)](https://shopvox-pro.helpdocs.io/article/lg1dnrwqz0-yard-sign-golden-product)
9. [ONYX Align — How to create a modifier](https://help.onyxgfx.com/onyxalign/Content/Align/ONYXAlign/How-to-Create-a-Modifier_45225875.html)
10. [Printing Impressions — Avanti Slingshot Grand Format Estimating (2015-08-17)](https://www.piworld.com/article/newest-avanti-slingshot-modules-garner-two-must-see-em-awards/)
11. [Avanti Slingshot — Core modules](https://avantisystems.com/core-modules/)
12. [Corebridge — site oficial](https://www.corebridge.net/)
13. [Signs101 — Corebridge POS software (fórum)](https://www.signs101.com/threads/corebridge-pos-software.138577)
14. [Mubisys — Comercial](https://mubisys.com/comercial)
15. [Calcgraf — segmento Comunicação Visual](https://www.calcgraf.com.br/segmento/comunicacao-visual/)
16. [Calcgraf — Ficha técnica](https://www.calcgraf.com.br/solucao/ficha-tecnica/)
17. [Startupi — Calcme](https://startupi.com.br/tags/calcme/)
18. [Signs101 — PrintSmith para grande formato](https://www.signs101.com/threads/anyone-using-printsmith.169979) · [GetApp — PrintSmith Vision](https://www.getapp.co.uk/software/2045647/eps-printsmith-vision)
19. Tharstern Estimate Pro: o trecho veio de um resultado de busca que aponta para *digitalprintermag.co.uk* (`?p=26584` ou `?p=31727`). A página devolveu **403** no fetch, então **qual URL e a data exata não estão confirmadas**. Trate a linha do Tharstern como fraca
