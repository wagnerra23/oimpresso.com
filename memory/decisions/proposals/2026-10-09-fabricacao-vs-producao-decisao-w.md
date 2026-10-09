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

## 3. Decisões para você

| # | Decisão | Recomendo |
|---|---|---|
| **D1** | Separar **Fabricação** (cadastro da composição) de **Produção** (módulo novo de execução), como o seu menu do protótipo já faz | **Sim.** A aba "Ordens de produção" da Fabricação vira só consulta, e a US-MANU-007 (formulário de ordem dentro da Fabricação) é **cancelada**: o formulário nasce no módulo de Produção |
| **D2** | Qual tabela vira a **fonte única** da composição | **Medir antes de escolher** (quantas composições existem em cada uma, por empresa — leitura no banco, cerca de 1h). As opções: `mfg_recipes` (tem editor pronto, custo e sub-unidades) ou `product_bom` (já é a que o estoque consome nas vendas). Escolhida a fonte, a outra passa a ler dela |
| **D3** | O que quer dizer "não produz sem material" | **Deixar criar a ordem e bloquear iniciar/concluir sem material**, com estado "aguardando material" e atalho para Compras — o padrão de todos os concorrentes com fonte. A alternativa é recusar criar a ordem |
| **D4** | O que fazer com o "% de desperdício" da receita | **Tirar da composição.** O desperdício real vai para a ordem de produção. Na CV, a perda planejada fica no cálculo do orçamento. O campo hoje existe no banco: tirar da tela é simples, apagar o dado é outra decisão |
| **D5** | Onde a CV entra | **A OS da CV gera ordem de produção por item** e usa a mesma composição da D2. As duas tabelas de OS da CV precisam virar uma (fora deste PR) |

Responder no PR basta, por exemplo: *"D1 sim · D2 medir · D3 recomendado · D4 recomendado · D5 recomendado"*.

## 4. O que acontece depois das respostas

1. **Medição da D2** (leitura no banco, sem escrita) e uma **ADR** com o desenho aprovado — o
   código vem depois da ADR, como você pediu para a entrada por XML.
2. Na **Fabricação**, sem mexer em estoque: a aba de ordens fica só consulta e, se D4 for aceita,
   o campo de desperdício sai da tela do editor.
3. O **módulo de Produção** nasce com a ordem: reserva, "aguardando material", conclusão com baixa
   e desperdício real. Tudo que mexe em estoque passa pela REGRA MESTRE (prova por dois caminhos,
   tabela antes→depois em dados de teste e sua aprovação).

**O que muda neste PR:** a grade de paridade da ordem continua, como inventário do que a tela
Blade faz hoje — vira insumo do módulo de Produção. A proposta anterior das 7 decisões sai.

## 5. O que este documento não decide

- Nada de preço de venda ou markup.
- Não consultei dados de produção: não sei quantas empresas usam receita, `product_bom` ou combo.
  É exatamente a medição da D2.
- Duas hipóteses de segurança no servidor da ordem (H1 e H2, na grade) seguem com tarefa própria.

---
**Trilha:** 2026-10-09 — escrito a pedido de [M], respondendo à revisão do [W] no #9086. [M+C]
