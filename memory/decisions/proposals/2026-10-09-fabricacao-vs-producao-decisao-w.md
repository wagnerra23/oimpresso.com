---
title: "Fabricação × Produção — onde se monta a composição e onde se produz (resposta à revisão do [W] no #9086)"
status: proposto
date: "2026-10-09"
owners: [W]
proposed_by: Claude Code (sessão da Fabricação, a pedido de [M])
parent_module: Manufacturing
related_adrs: [93, 121, 143, 152]
related_specs:
  - memory/requisitos/Manufacturing/SPEC.md (US-MANU-007)
  - memory/requisitos/Produto/SPEC.md (US-PROD-025)
  - memory/requisitos/ComunicacaoVisual/SPEC.md (US-COMVIS-003)
related_charters:
  - resources/js/Pages/Manufacturing/Index.charter.md
---

# Fabricação × Produção — onde fica cada coisa

> **Status: `proposto`.** Não muda código. É a resposta à revisão do [W] no
> [#9086](https://github.com/wagnerra23/oimpresso.com/pull/9086) (2026-10-09):
> *"Produção e fabricação devem ser em locais diferentes, como deveria ser na comunicação visual?
> onde monta os produtos composição e onde vai ser feito os materiais na produção — consulte os
> concorrentes."* Substitui a proposta anterior deste PR (7 decisões sobre a tela de ordem dentro
> da Fabricação), que partia da premissa de que a ordem é criada na Fabricação.
>
> **Pesquisa completa, com fontes:** [`2026-10-09-arte-composicao-vs-producao.md`](../../sessions/2026-10-09-arte-composicao-vs-producao.md).
> Tudo do oimpresso abaixo foi lido em `origin/main` de 2026-10-09.

## 0. Resposta curta

**Sim, são lugares diferentes, e o mercado faz assim.** A composição é **cadastro**: monta o
produto, aceita item em falta, não tem desperdício. A produção é **execução**: cria a ordem,
reserva e baixa material, registra o desperdício real.

Em comunicação visual há uma camada a mais: **o orçamento**. Cada peça tem medida própria, então
o preço sai de área × material × acabamento, e a perda planejada (sobra da bobina, emenda) é
cobrada ali, antes de existir produção.

| | **Fabricação** (cadastro) | **Orçamento** (CV) | **Produção** (módulo novo) |
|---|---|---|---|
| O que faz | Monta a composição do produto (ficha técnica) | Calcula o preço da peça sob medida | Cria e executa a ordem |
| Olha o estoque? | **Não.** Aceita item em falta | Não | **Sim.** Reserva, baixa e mostra o que falta |
| Perda | **Nenhuma** | Perda **planejada** (aproveitamento da bobina) entra no preço | Desperdício **real** (refugo, reimpressão) |
| Onde fica no menu | Grupo CADASTRO — como o seu protótipo já faz | Comunicação Visual | Grupo PRODUÇÃO — como o seu protótipo já faz |

O menu do seu protótipo (`prototipo-ui/cowork/Wagner/data.jsx`) **já separa os dois**: Fabricação
está em **CADASTRO** (ao lado de Clientes e Produtos), e **PRODUÇÃO** é outro grupo (Ordens de
Serviço, Entregas e instalação, Comunicação Visual, Assistência técnica). O que falta é o código
seguir o desenho. O legado Delphi também separava: composição no estoque (`PRODUTO_COMPOSICAO`) e
produção num módulo próprio (`PRODUCAO`, `PRODUCAO_PRODUTO`, que copia a composição).

## 1. O que os concorrentes fazem

| | Composição | Produção |
|---|---|---|
| **Odoo 18** | Lista de materiais no app de manufatura ou na ficha do produto. Não reserva estoque | Ordem separada. Com falta de material fica **"aguardando"**; desperdício lançado na ordem |
| **Katana** | Receita na ficha do produto | Ordem **copia** a receita (mudar a receita não muda ordem aberta). Falta aparece como "não disponível", com botão de compra |
| **Omie** | "Estrutura do produto" no cadastro | Ordem num kanban de etapas; reserva só o que existe |
| **Bling** | Composição no cadastro | Ordem de produção é módulo separado (desde 2020) |
| **Calcgraf** (gráfica) | Ficha técnica numa área própria; o orçamento de CV calcula o aproveitamento de bobina | Material reservado **depois** de emitir a ordem; compara orçado × realizado |
| **Mubisys, Zênite** (CV) | Preço no orçamento com aproveitamento de material | Produção por setor e apontamento. Onde fica a composição e o que fazem na falta: **não confirmado** (pouco publicado) |

**O ponto em comum:** ninguém impede a ordem de **existir** por falta de material. O que se
bloqueia é **começar ou concluir**. A ordem nasce, mostra o que falta e espera.

**Por que isso vale aqui (não é cópia):** na CV e na gráfica, a ordem costuma nascer da venda
antes de o material chegar; recusar a ordem esconderia a demanda que deveria gerar a compra.

## 2. O que o oimpresso tem hoje — o problema é maior que a tela

**A composição está em 3 tabelas que não se conversam:**

| Fonte | Onde | Quem usa |
|---|---|---|
| `mfg_recipes` (receitas) | Fabricação (Manufacturing) | O editor de ingredientes e a ordem de produção do Manufacturing |
| `product_bom` | Núcleo (Inventory) — a migration se declara a fonte "canônica" | O FSM das vendas com produção e da CV, para **reservar e baixar estoque** (`ReservarEstoque`, `ConsumirEstoque` via `BomResolver`). **Não tem tela** (US-PROD-025 pendente) |
| `combo_variations` | Combo herdado do UltimatePOS | `BomResolver`, como reserva |

Medido: `product_bom` aparece em **0** arquivos do Manufacturing, e `mfg_`/`MfgRecipe` em **0**
arquivos de `app/Domain/Inventory` e `app/Domain/Fsm` (controles: o mesmo padrão `mfg_` aparece em
83 arquivos do Manufacturing, e `product_bom` em 3 do `app`). Ou seja: **a receita que se monta na
Fabricação não é a que a venda com produção consome.** Quem cadastrar a composição na Fabricação
hoje não muda o que é baixado do estoque quando a venda vai para produção.

**A produção está espalhada** e não há módulo de Produção: a ordem do Manufacturing (Blade), o FSM
"venda com produção" (`iniciar_producao`/`concluir_producao`), o FSM da CV (baixa no
`concluir_impressao`), o kanban `producao-oficina` do Repair e a API de produção do app. A CV tem
ainda duas tabelas de OS (`comvis_os` e `cv_ordens_producao`) e um catálogo de material próprio
(`comvis_materiais`). O PCP (`Modules/Pcp`) está parado por decisão (ADR 0152) até ter cliente-piloto.

**O desperdício está nos dois lados:** a receita guarda `% de desperdício` (na receita e em cada
ingrediente — o editor React mostra os dois campos) e a ordem guarda as unidades perdidas.

## 2b. O que os clientes realmente montam — e onde eles se perdiam

> O legado **não é parâmetro de como fazer**. Ele entra aqui só como dado do que o cliente
> precisa montar. Medido em 2026-10-09, só leitura, em cópias locais de dois bancos: a Martinho
> (caçambas) e um cliente de comunicação visual. Só contagens; sem nomes de produto nem valores.

| | Martinho | Cliente de CV |
|---|---:|---:|
| Produtos com composição | 169 | 219 |
| Linhas de composição / componentes diferentes | 2.198 / 239 | 1.041 / 347 |
| Linhas dentro de submontagem (composição dentro de composição) | 429 | 436 |
| Linhas com **desperdício** preenchido | **0** | **0** |
| Ainda em uso | sim (135 linhas alteradas em 2026) | sim (210 linhas alteradas em 2026) |
| Ordens de produção no legado | 257 | 600 |

**O problema que vocês viveram:** o cliente nunca sabia qual fórmula aplicar a cada matéria-prima
nem qual era a composição base do produto. No cliente de CV, cada linha pedia uma regra escolhida
à mão entre 8 (IGUAL, A CADA, PERSONALIZADA, PERÍMETRO, PROPORCIONAL, ILHÓS, FOLHAS/CHAPA, SEM
FÓRMULA), e os dados mostram o resultado:

- **223 das 1.041 linhas ficaram sem regra nenhuma.**
- A regra contradiz a unidade do material em vários casos: "A CADA" aplicado a material em m² (8),
  "IGUAL" a material em unidade (11).
- As **146 linhas PERSONALIZADA quase nunca são fórmula de verdade**: 131 são tinta ou solvente
  em mililitro, e 58 estão escritas como fórmula de planilha que pega o valor de uma célula de
  outra linha e multiplica por 2,5 — pelo padrão, *"2,5 ml de tinta por m² impresso"* (qual
  coluna a célula aponta não foi conferido). Só **4** são fórmula de
  fato: 1 de perímetro escrito à mão, 2 de espaçamento ("um a cada 10") e 1 que copia outra linha.
- Para a migração: no legado **"ml" (mililitro) e "ML" (metro linear) só se diferenciam por
  maiúscula**. Isso precisa de tratamento explícito.

**O que os concorrentes fazem** (pesquisa com fontes em
[`2026-10-09-arte-composicao-facil-cv.md`](../../sessions/2026-10-09-arte-composicao-facil-cv.md)):
os sistemas feitos para *sign shop* — peça sob medida de rolo e chapa, o mesmo produto do cliente
de CV — **não pedem ao usuário que escolha a fórmula em cada linha**:

- **Cyrious Control e ONYX:** o cadastro do **insumo** diz se ele se mede por área, por comprimento
  ou por unidade. A composição herda isso; a linha só troca quando precisa.
- **shopVOX:** traz **produtos-modelo prontos** (banner, placa rígida, envelopamento…) para
  duplicar e ajustar, e um **"conferir preço"** que mostra o resultado para uma medida e quantidade
  antes de usar.
- Mubisys, Calcgraf, Zênite e Calcme não publicam como fazem: **não confirmado**.

**O oimpresso já tem metade da peça e não usa:** o catálogo de materiais da CV guarda a unidade de
cada material (m², unidade, metro linear), mas o cálculo do orçamento (`OrcamentoCalculator`)
ignora esse campo e trata tudo como área × preço/m². Hoje isso está latente (os materiais de
exemplo são todos m²); um material cadastrado em metro linear seria cobrado por área. É valor,
então o conserto passa pela REGRA MESTRE.

## 3. Decisões para você

| # | Decisão | Recomendo |
|---|---|---|
| **D1** | Separar **Fabricação** (cadastro da composição) de **Produção** (módulo novo de execução), como o seu menu do protótipo já faz | **Sim.** A aba "Ordens de produção" da Fabricação vira só consulta, e a US-MANU-007 (formulário de ordem dentro da Fabricação) é **cancelada**: o formulário nasce no módulo de Produção |
| **D2** | Qual tabela vira a **fonte única** da composição | **Escolher pelo que ela precisa suportar**, que agora está medido (§2b): **submontagem** (os dois clientes têm ~430 linhas assim), **regra de consumo por linha** (D6) e receber as composições do legado na migração. Hoje: `mfg_recipes` tem editor pronto, custo e sub-unidades, mas **não** resolve submontagem nem tem regra de consumo; `product_bom` já resolve submontagem (até 5 níveis) e é a que o estoque consome nas vendas, mas **não tem tela** nem regra de consumo. Nenhuma das duas serve sem acréscimo. Escolhida a fonte, a outra passa a ler dela |
| **D3** | O que quer dizer "não produz sem material" | **Deixar criar a ordem e bloquear iniciar/concluir sem material**, com estado "aguardando material" e atalho para Compras — o padrão de todos os concorrentes com fonte. A alternativa é recusar criar a ordem |
| **D4** | O que fazer com o "% de desperdício" da receita | **Tirar da composição.** O desperdício real vai para a ordem de produção. Na CV, a perda planejada fica no cálculo do orçamento. O campo hoje existe no banco: tirar da tela é simples, apagar o dado é outra decisão |
| **D5** | Onde a CV entra | **A OS da CV gera ordem de produção por item** e usa a mesma composição da D2. As duas tabelas de OS da CV precisam virar uma (fora deste PR) |
| **D6** | Como o cliente monta a composição **sem precisar saber a fórmula** | **A regra de consumo mora no insumo e vem da unidade dele**, trocável na linha: material em **m²** consome pela área da peça · **metro linear** pelo perímetro · **unidade** N por peça · **folha/chapa** pelo aproveitamento. Mais **duas regras com taxa**, que cobrem quase todo o "PERSONALIZADA": **"por área × taxa"** (2,5 ml de tinta por m²) e **"a cada N cm de perímetro"** (ilhós). **Sem editor de fórmula** — os dados mostram 4 fórmulas de verdade em 1.041 linhas. Junto: **prévia com uma medida de exemplo** ("1,00 × 2,00 m consome 2 m² de lona, 6 m de bainha, 12 ilhós") e **modelos prontos de produto de CV** para copiar e ajustar |

Responder no PR basta, por exemplo: *"D1 sim · D2 product_bom · D3 a D6 recomendado"*.

## 4. O que acontece depois das respostas

1. Uma **ADR** com o desenho aprovado — o código vem depois da ADR, como você pediu para a
   entrada por XML.
2. Na **Fabricação**, sem mexer em estoque: a aba de ordens fica só consulta e, se D4 for aceita,
   o campo de desperdício sai da tela do editor.
3. O **módulo de Produção** nasce com a ordem: reserva, "aguardando material", conclusão com baixa
   e desperdício real. Tudo que mexe em estoque passa pela REGRA MESTRE (prova por dois caminhos,
   tabela antes→depois em dados de teste e sua aprovação).
4. **Composição fácil (D6):** a regra pela unidade do insumo e a prévia com medida de exemplo vêm
   primeiro; os modelos prontos de produto de CV, depois. O conserto do orçamento que ignora a
   unidade do material entra junto da regra, porque é a mesma conta.

**O que muda neste PR:** a grade de paridade da ordem continua, como inventário do que a tela
Blade faz hoje — vira insumo do módulo de Produção. A proposta anterior das 7 decisões sai.

## 5. O que este documento não decide

- Nada de preço de venda ou markup.
- Não consultei os dados de produção do oimpresso: não sei quantas empresas já usam receita,
  `product_bom` ou combo. Os números da §2b são de dois bancos do legado, em cópia local.
- Quais modelos prontos de produto de CV entram primeiro (banner, adesivo, placa, fachada…) —
  sai de conversa com cliente, não deste documento.
- Duas hipóteses de segurança no servidor da ordem (H1 e H2, na grade) seguem com tarefa própria.

---
**Trilha:** 2026-10-09 — escrito a pedido de [M], respondendo à revisão do [W] no #9086. Mesmo
dia: acrescentados os dados medidos em dois bancos do legado (§2b), a D2 reescrita com eles e a D6
(composição sem fórmula), a pedido de [M]. [M+C]
