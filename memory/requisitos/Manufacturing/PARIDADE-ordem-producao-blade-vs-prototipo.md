---
id: requisitos-manufacturing-paridade-ordem-producao
titulo: "Paridade — Ordem de produção · Blade de hoje × protótipo React"
tipo: paridade
module: Manufacturing
status: ativo
owner: wagner
gerado: 2026-10-09
fontes:
  - Modules/Manufacturing/Resources/views/production/create.blade.php (+ edit, show, production_script)
  - Modules/Manufacturing/Resources/views/recipe/ingredient_row_for_production.blade.php
  - Modules/Manufacturing/Http/Controllers/ProductionController.php (create · store · edit · update · destroy)
  - Modules/Manufacturing/Http/Controllers/RecipeController.php (getRecipeDetails)
  - Modules/Manufacturing/Http/Requests/StoreProductionRequest.php
  - prototipo-ui/cowork/Wagner/manufacturing-producao.jsx (MfgProducaoForm · MfgProducaoDrawer)
  - prototipo-ui/cowork/Wagner/manufacturing-data.jsx (consumoOP)
  - prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md §6 · §7 · §9 · §18.7
observacao: "Base da US-MANU-007. Mede o que a tela nova ganha, perde e muda em relação à Blade antes de alguém construí-la."
---

# Paridade — Ordem de produção · Blade × protótipo React

> **Pergunta que este documento responde:** se a tela de ordem de produção for construída
> igual ao protótipo, o que quem usa a Blade hoje ganha, perde e passa a ver com outro número?
>
> **Leitura feita em `origin/main` de 2026-10-09.** Fonte de design: `prototipo-ui/cowork/Wagner/`,
> fonte única desde a decisão [W] de 2026-09-25 (D-MFG-FONTE). O README do handoff do Felipe entra
> só pelas regras escritas (§6, §7, §9), que o protótipo do Wagner implementa.
>
> **O que este documento NÃO é:** ele não autoriza construir a tela. A US-MANU-007 continua
> travada pela decisão [W] sobre o caminho rascunho→finalizada (SPEC). As divergências de
> valor e estoque abaixo (marcadas ⚠️) também são decisões do [W]. Cada uma precisa de prova
> por dois caminhos e de uma tabela antes→depois (REGRA MESTRE de valor e estoque).

## 0. Resumo

| | Itens |
|---|---:|
| Iguais nos dois lados | 5 |
| O protótipo acrescenta (➕) | 6 |
| O protótipo perde (➖) — a Blade faz e a tela nova deixaria de fazer | 10 |
| ⚠️ **Mudam número de custo ou de estoque** | **5** |
| 🔒 Hipóteses de segurança no servidor (sem teste ainda) | 2 |

Legenda: **=** igual · **➕** protótipo acrescenta · **➖** protótipo perde · **⚠️** muda valor ou
estoque · **🔒** segurança.

---

## 1. Cabeçalho da ordem

| # | Item | Blade (hoje) | Protótipo | | Sugestão |
|---|---|---|---|---|---|
| 1 | Referência | campo livre; vazio ⇒ o **servidor** numera com o prefixo das Configurações (`store`, gera em `setAndGetReferenceCount` + `generateReferenceNumber`) | campo **já preenchido no navegador** com prefixo + sequência (`base.ref`) e dica do prefixo | ➖ | Manter a numeração no servidor (§9 do handoff: duas abas abertas geram a mesma referência se o navegador numerar). Campo vazio com a dica "gerada ao salvar · prefixo X". |
| 2 | Data | data **e hora** (`datetimepicker`) | só data (`DatePicker`) | ➖ | Manter a hora: `transaction_date` é data-hora e a lista ordena por ela. |
| 3 | Local | já vem escolhido quando a empresa tem um só; trocar o local recarrega os ingredientes **com o estoque daquele local** | lista fixa; o estoque mostrado não muda com o local | ➖ | Mesmo comportamento da Blade: local único já escolhido e estoque por local. |
| 4 | Produto / receita | lista de receitas da empresa (`MfgRecipe::forDropdown`) | lista de receitas | = | — |
| 5 | Quantidade e unidade | aceita **sub-unidade** do produto (ex.: caixa com 12) | só a unidade da receita | ➖ | Manter a sub-unidade quando o produto tiver. |
| 6 | Observação | não existe; o `store` não grava nada parecido | campo "Observação" (lote, equipamento, quem executou) | ➕ | Bom ganho, mas precisa de lugar para gravar no servidor: conferir a coluna antes de pôr na tela. |
| 7 | Lote e validade do produto fabricado | aparecem quando a empresa usa lote/validade (`enable_lot_number`, `enable_product_expiry`) e vão para a linha de entrada | não existem | ➖ | **Manter.** Sem eles, empresa que controla lote perde o rastro do que fabricou. |
| 8 | Anexar documento | sim (`Media::uploadMedia`) | não | ➖ | Manter (ou registrar como decisão [W] que sai). |

## 2. Ingredientes

| # | Item | Blade (hoje) | Protótipo | | Sugestão |
|---|---|---|---|---|---|
| 9 | Consumo proporcional à quantidade | sim: linha = quantidade da receita × quantidade da ordem ÷ quantidade da receita | sim (`fator = qtd ÷ r.qtd`) | = | — |
| 10 | Editar o consumo de uma linha | sim, salvo `disable_editing_ingredient_qty` (vira somente leitura) | sim, salvo `travarQtd` (vira texto) | = | — |
| 11 | Trocar receita ou quantidade zera os ajustes | trocar a quantidade recalcula todas as linhas; trocar a receita recarrega a tabela | regra 1: `consumo: null` | = | — |
| 12 | Desperdício **por ingrediente** | coluna "% desperdício" editável; o consumo **já inclui** o desperdício (`getRecipeDetails` soma o % à quantidade) e mostra a "quantidade final" | **não existe** — o protótipo não modela desperdício por ingrediente (`consumoOP` usa `i.q × fator`) | ⚠️ | Decisão [W]. Se a empresa cadastrou desperdício no ingrediente, a tela nova mostra **consumo e custo menores** que a Blade para a mesma ordem. Medir quantas receitas têm `waste_percent > 0` nos ingredientes antes de decidir. |
| 13 | Sub-unidade por ingrediente | escolhível na linha (troca o multiplicador junto) | fixa | ➖ | Manter a escolha (o editor de ingredientes React já faz isso — regra 4). |
| 14 | Custo unitário e estoque na linha | não aparecem; o estoque só surge como mensagem de erro | colunas "Custo unit." e "Estoque", linha tingida quando falta | ➕ | Bom ganho. |
| 15 | Estoque insuficiente | **bloqueia o envio no navegador** quando a empresa não permite vender sem estoque (`allow_overselling` desligado — `data-rule-max-value` na linha). O servidor não confere: `decreaseProductQuantity` só decrementa | **avisa e não bloqueia, sempre** (regra 2 `[FECHADA]`), com o botão "Abrir Compras" | ⚠️ | Decisão [W]. A regra 2 ignora uma configuração que a empresa já escolheu. Sugestão: seguir `allow_overselling` — e conferir também no servidor, que hoje deixa passar qualquer POST. |
| 16 | Lote dos ingredientes consumidos | o detalhe da ordem mostra lote e validade de cada insumo baixado | o painel da ordem não mostra | ➖ | Manter no detalhe. |

## 3. Custo

| # | Item | Blade (hoje) | Protótipo | | Sugestão |
|---|---|---|---|---|---|
| 17 | Quem calcula o custo gravado | o **navegador**: `final_total` vem de um campo escondido e é gravado como está, em rascunho e em finalizada; o custo unitário da entrada é `final_total ÷ quantidade` | §9: o **servidor** calcula; o congelado (`custoSnap`) só nasce ao finalizar | ⚠️ | Servidor recalcula, como o #9051 fez para salvar receita. Um POST forjado hoje grava qualquer custo numa `transaction` de produção. |
| 18 | Custo extra da ordem | **editável na ordem**, com o tipo (fixo / % / por unidade). O **fixo entra inteiro**, qualquer que seja a quantidade | só leitura ("custo extra da receita"); o **fixo é proporcional** à quantidade (`extra × fator`) | ⚠️ | Decisão [W]. Exemplo: receita de 10 un com custo extra fixo; ordem de 20 un — a Blade soma o fixo uma vez, o protótipo soma duas. |
| 19 | Desperdício da ordem | campo "unidades desperdiçadas" (vem do % da receita). Entra no estoque **quantidade − desperdício**, e o custo unitário da entrada é `total ÷ (quantidade − desperdício)` | não existe; "custo por unidade" = `total ÷ quantidade` e a entrada no estoque não aparece | ⚠️ | Decisão [W]. Muda **quanto produto entra no estoque** e **o custo unitário gravado**. |
| 20 | Dois números da ordem finalizada | o detalhe mostra só o custo gravado | "Custo congelado na produção" × "Mesma receita hoje", com a variação % | ➕ | Bom ganho (regra 4). |
| 21 | Atualizar o custo do produto ao finalizar | faz em silêncio quando `enable_updating_product_price` está ligado | avisa **antes** de salvar, com o valor | ➕ | Bom ganho (regra 5). |

## 4. Salvar, finalizar, editar, excluir

| # | Item | Blade (hoje) | Protótipo | | Sugestão |
|---|---|---|---|---|---|
| 22 | Rascunho não movimenta estoque | sim: estoque só se move com `status = received` (entrada) e `final` (baixa) | regra 3 | = | — |
| 23 | Botão de salvar | caixa "Finalizar" + botão "Enviar" | caixa "Finalizar — dá entrada no produto e baixa os ingredientes" + botão que diz o que acontece: "Salvar rascunho" / "Salvar e finalizar" | ➕ | Bom ganho. |
| 24 | Quantidade zero ou negativa | o navegador barra (quantidade > 0 e > desperdício); o **servidor aceita** (`quantity` só `string`) | barra quantidade ≤ 0 e consumo negativo | ➕ | Recusar também no servidor (padrão do #9051). |
| 25 | Editar ordem finalizada | **proibido** (`edit` e `update` devolvem para a lista) | o painel oferece "Editar ordem" sempre | ➖ | Manter proibido: a ordem finalizada já moveu estoque e congelou custo. |
| 26 | Excluir | só rascunho (`destroy` filtra `mfg_is_final = 0`) | não tem | ➖ | Manter "excluir rascunho". |

> Contagem do §0, conferível linha a linha: **=** itens 4, 9, 10, 11, 22 · **➕** 6, 14, 20, 21, 23, 24 ·
> **➖** 1, 2, 3, 5, 7, 8, 13, 16, 25, 26 · **⚠️** 12, 15, 17, 18, 19 — 26 itens.

## 5. 🔒 Hipóteses de segurança no servidor — **não provadas**

Lidas no código; nenhuma tem teste vermelho ainda. São **hipóteses**, não achados, até o teste
rodar na lane `Manufacturing · Pest (MySQL)`.

| # | Onde | O que o código faz | Hipótese | Teste que prova |
|---|---|---|---|---|
| H1 | `ProductionController::store` + `StoreProductionRequest` | `variation_id` valida `exists:variations,id` **sem empresa**; a receita é buscada por `MfgRecipe::where('variation_id', …)->first()` **sem a cadeia de empresa**; `location_id` só é `required`. O escopo `scopeForBusinessViaProductChain` existe no módulo, mas não é usado aqui | um POST com `variation_id` de outra empresa cria ordem que consome os ingredientes da receita dela | empresa 98 posta a variação da receita de outra empresa ⇒ espera recusa e nenhuma `transaction` criada |
| H2 | `RecipeController::getRecipeDetails` (`/manufacturing/get-recipe-details`) | mesma busca sem a cadeia de empresa | quem tem `access_recipe` lê ingredientes e custo de receita de outra empresa | GET com a variação de outra empresa ⇒ espera 404/403 |

O padrão de conserto já existe no módulo: o #9071 fez o mesmo para "excluir receita".

## 6. Melhorias sugeridas, em ordem

1. **🔒 Provar H1 e H2 e consertar** (PR próprio, pequeno, padrão #9071). É independente da
   decisão de FSM e protege a Blade de hoje.
2. **Servidor calcula o custo da ordem** (item 17) e recusa quantidade ≤ 0 (item 24) — padrão
   #9051. Também vale para a Blade de hoje.
3. **[W] decide as três contas** (itens 12, 18 e 19) **e o bloqueio por estoque** (item 15)
   **antes** de alguém construir a tela. Construir igual ao protótipo muda número de custo e
   de estoque sem ninguém ter escolhido isso.
4. **Não perder** hora, sub-unidade, lote/validade, anexo, desperdício por ingrediente e o
   bloqueio de editar finalizada (itens 2, 5, 7, 8, 12, 13, 25). O protótipo não mostra, mas
   quem usa a Blade usa.
5. **Trazer os ganhos do protótipo**: custo unitário e estoque na linha (14), dois números da
   finalizada (20), aviso do preço de custo (21), botão que diz o que acontece (23), ponte para
   Compras (15) e observação (6, depois de ter onde gravar).
6. **Referência numerada no servidor** (item 1), com a dica do prefixo no campo vazio.

---
**Gerado:** 2026-10-09 — leitura de `origin/main`; nenhuma afirmação de comportamento foi
rodada em navegador ou em teste. [M+C]
