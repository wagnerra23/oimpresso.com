---
title: "Ordem de produção em React (US-MANU-007) — o que recomendo e o que o [W] precisa decidir"
status: proposto
date: "2026-10-09"
owners: [W]
proposed_by: Claude Code (sessão da Fabricação, a pedido de [M])
parent_module: Manufacturing
related_adrs: [93, 104, 143, 358]
related_specs:
  - memory/requisitos/Manufacturing/SPEC.md (US-MANU-007)
  - memory/requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md
related_charters:
  - resources/js/Pages/Manufacturing/Index.charter.md
---

# Ordem de produção em React — recomendações para decisão [W]

> **Status: `proposto`.** Não muda código. A US-MANU-007 está travada esperando a decisão D1 (SPEC).
> As decisões D2 a D4 mexem em **custo e estoque**: depois de escolhidas, a implementação segue a
> REGRA MESTRE de valor e estoque (prova por dois caminhos + tabela antes→depois em dados de teste
> + aprovação antes de ir para produção).
>
> **Base:** a grade de paridade [`PARIDADE-ordem-producao-blade-vs-prototipo.md`](../../requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md)
> (26 itens, Blade de hoje × protótipo `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx`),
> lida em `origin/main` de 2026-10-09.

## 0. Resumo — o que preciso que você responda

| # | Decisão | Recomendo | Se escolher o contrário |
|---|---|---|---|
| **D1** | Como a ordem passa de rascunho para finalizada | **A** — uma ação "Finalizar" num serviço único do módulo, só de ida, numa transação | B: pipeline FSM completo (mais pesado); C: manter o `update()` de hoje (grava o custo vindo do navegador) |
| **D2** | Custo extra **fixo** numa ordem maior ou menor que o lote da receita | **Proporcional ao tamanho da ordem como valor inicial, editável na ordem** | Blade: soma o fixo uma vez por ordem, qualquer que seja o tamanho |
| **D3** | Desperdício da ordem (unidades perdidas) | **Manter a Blade**: entra no estoque só o que sobra, e o custo por unidade é sobre as unidades boas | Protótipo: não tem o campo; a entrada e o custo por unidade ficam sem desperdício |
| **D4** | Ingrediente sem estoque suficiente | **Seguir a configuração da empresa** (`allow_overselling`): bloqueia quando ela não permite vender sem estoque, e conferir também no servidor | Protótipo (regra 2): sempre avisa e deixa finalizar com saldo negativo |
| **D5** | Ordem finalizada pode ser editada? | **Não** (como a Blade). Erro em ordem finalizada se corrige com estorno, que é outra US | Protótipo: o painel oferece "Editar ordem" sempre |
| **D6** | O que a tela nova pode deixar de ter | **Nada.** Manter hora, sub-unidade do produto e dos ingredientes, lote e validade, anexo, a coluna de desperdício por ingrediente, o lote dos insumos no detalhe e "excluir rascunho" | Cada item removido é uma perda para quem usa a Blade hoje — diga quais |
| **D7** | O que adotar do protótipo | **Tudo**: custo e estoque na linha (por local), botão que diz o que acontece, aviso antes de atualizar o custo do produto, os dois números da finalizada, ponte para Compras e campo de observação | — |

Responder no PR basta, por exemplo: *"D1 A · D2 recomendado · D3 recomendado · D4 protótipo · D5–D7 recomendado"*.

## 1. Um exemplo com números (fictício)

**Receita "Banner":** rende 10 un. Lona 10 m² a 8,00 · Tinta 2 L a 50,00 · custo extra **fixo** de
30,00 · desperdício da receita 5% · a lona tem 10% de desperdício cadastrado no ingrediente.
**Ordem:** 20 un (2 lotes). **Estoque de lona no local:** 15 m².

| | Blade hoje | Protótipo | **Recomendado (D2 + D3)** |
|---|---:|---:|---:|
| Consumo de lona / tinta | 20 m² / 4 L | 20 m² / 4 L | 20 m² / 4 L |
| Ingredientes | 360,00 | 360,00 | 360,00 |
| Custo extra fixo | 30,00 | 60,00 | 60,00 (editável) |
| **Total da ordem** | **390,00** | **420,00** | **420,00** |
| Entra no estoque | 19 un | não diz | 19 un |
| **Custo por unidade gravado** | **20,53** (390 ÷ 19) | 21,00 (420 ÷ 20) | **22,11** (420 ÷ 19) |
| Lona sem estoque (D4) | bloqueia se a empresa não permite vender sem estoque | avisa e finaliza com −5 m² | igual à Blade + conferido no servidor |

Para comparar: a lista de Receitas mostra **21,00 por unidade** para essa receita
(`(180 + 30) ÷ 10`).

- **Por que a recomendada dá 22,11 e não 21,00:** a lista de Receitas divide o custo pela
  quantidade cadastrada, sem tirar o desperdício (handoff §7, ponto 1). A ordem divide pelas
  unidades que de fato entram no estoque. Os 1,11 de diferença são o custo das 5% perdidas, pago
  pelas unidades boas. A tela nova deve dizer isso numa linha, para os dois números não parecerem
  contradição.
- **O desperdício de 10% na lona não muda nenhum número** — nem na Blade, nem no protótipo. Na
  Blade ele é só informação ("quantidade final" = 20 − 10% = 18 m²). A primeira versão da grade
  dizia o contrário; a correção está registrada no topo dela.
- **As contas desta tabela foram feitas à mão a partir das fórmulas lidas no código.** A prova por
  dois caminhos (teste no servidor com estes números + conferência da tela contra o servidor) vem
  com a implementação, antes do merge.

## 2. As decisões, uma a uma

### D1 · Rascunho → finalizada

Hoje a Blade decide tudo no `ProductionController@store/@update` pelo campo `finalize`: cria a
entrada do produto (`production_purchase`) e a baixa dos ingredientes (`production_sell`), numa
transação, e o estoque só se move com status `received`/`final`. O charter do Index proíbe
`UPDATE` direto em `transactions` porque o FSM de Sells/Repair (ADR 0143) não cobre a Fabricação.

| Opção | Como fica | Prós | Contras |
|---|---|---|---|
| **A (recomendo)** | Ação "Finalizar" num serviço do módulo: trava a ordem, recalcula o custo no servidor, cria a entrada e a baixa, congela o custo, tudo numa transação e só de ida. Repetir não duplica | pouco código novo; reaproveita o que o legado já faz (`ProductUtil`, `mapPurchaseSell`); fácil de testar com números | não usa as tabelas do FSM; o anti-hook do charter precisa ser relido como "toda escrita passa por este serviço" |
| B | Pipeline FSM canônico (ADR 0143) com 2 estágios | segue o padrão de Sells e Repair; histórico de transição pronto | pesado para 2 estados (rascunho e finalizada) e uma transição só de ida |
| C | Manter o `update()` de hoje, só trocar a tela | menor esforço | grava o custo que o navegador mandar (§9); não resolve o anti-hook |

### D2 · Custo extra fixo

Na receita, o custo extra fixo vale para o **lote** inteiro (a lista de Receitas o divide pela
quantidade do lote). Na ordem da Blade ele vem preenchido com o valor da receita e entra **uma vez**,
mesmo que a ordem produza 2 lotes — o custo por unidade da ordem fica abaixo do da receita. O
protótipo multiplica pelo número de lotes.

**Recomendo** começar proporcional (fecha com a receita, que é a regra 4 do protótipo: "dois números
do mesmo fato precisam fechar") e **deixar editável na ordem**, como a Blade deixa, para quem tem um
custo que é de fato por ordem (ex.: preparar a máquina uma vez).

### D3 · Desperdício da ordem

A Blade preenche "unidades desperdiçadas" com o % da receita, deixa editar, dá entrada só do que
sobra e grava o custo por unidade boa. É o número que o estoque e a venda vão usar depois.
**Recomendo manter.** O protótipo simplesmente não trata o tema.

### D4 · Estoque insuficiente

A empresa já escolheu, em `allow_overselling`, se permite vender sem estoque. A Blade respeita isso
na tela (o servidor não confere: `decreaseProductQuantity` só desconta). A regra 2 do protótipo
("avisa e não bloqueia") ignora essa escolha. **Recomendo seguir a configuração e conferir também no
servidor**, mantendo do protótipo o aviso com a contagem e o botão "Abrir Compras".

### D5 · Editar ordem finalizada

A finalizada já moveu estoque e congelou o custo; a Blade proíbe editar (`edit` e `update` devolvem
para a lista). **Recomendo manter proibido.** Corrigir uma ordem errada é um estorno (devolver o
estoque e anular o custo), que deve virar uma US própria se houver pedido.

### D6 · O que a tela nova não pode perder

Hora da produção · sub-unidade do produto · sub-unidade por ingrediente · lote e validade do produto
fabricado (quando a empresa usa) · anexo · coluna de desperdício por ingrediente (informativa) ·
lote dos insumos consumidos no detalhe · "excluir rascunho". **Recomendo manter todos.** Se algum
puder sair, diga qual.

### D7 · O que vem do protótipo

Custo unitário e estoque **do local escolhido** em cada linha · botão "Salvar rascunho" / "Salvar e
finalizar" · aviso antes de salvar quando `enable_updating_product_price` está ligado, com o valor ·
na ordem finalizada, "Custo congelado na produção" × "Mesma receita hoje", calculados com a mesma
conta da ordem (D2 e D3), para os dois fecharem · "Abrir Compras" quando falta estoque · campo
"Observação", gravado em `transactions.additional_notes` (a coluna existe e o `store` de hoje não a
usa).

## 3. Sem decisão — vai junto, porque é regra

- **O servidor calcula o custo gravado** (handoff §9); hoje o `final_total` vem do navegador. Vale
  também para a Blade de hoje.
- **O servidor recusa quantidade ≤ 0** (hoje só o navegador recusa).
- **A referência é numerada pelo servidor** com o prefixo das Configurações (já é assim na Blade).
- **Isolamento entre empresas:** duas hipóteses ainda sem teste (H1 no `store`, H2 no
  `get-recipe-details`) — o servidor busca a receita sem conferir a empresa. Há uma tarefa separada
  para provar com teste e consertar. Independe de D1–D7.

## 4. Como eu entregaria (depois das respostas)

| Etapa | O quê | Move estoque? |
|---|---|---|
| 0 | Servidor: H1/H2 provadas e consertadas · custo calculado no servidor · recusa de quantidade ≤ 0 · local conferido contra a empresa. Vale para a Blade de hoje | não muda |
| 1 | Formulário React salvando **só rascunho**, atrás de `?tela=nova` (a Blade continua a padrão, como foi com o editor de ingredientes) | não |
| 2 | "Finalizar" pela opção escolhida em D1, com os números de D2/D3 e o bloqueio de D4. Prova por dois caminhos + tabela antes→depois em ordens de teste (tenant 98) + sua aprovação | **sim** |
| 3 | Virada: o React passa a ser a tela padrão e a Blade fica em `?legacy=1` | — |

O detalhe da ordem já existe no servidor (`ProductionService::detalheOrdem`); a etapa 2 só
acrescenta os dois números da finalizada.

## 5. O que este documento não decide

- **"Atualizar preço de venda" em massa** — continua fora, como já está no SPEC (a regra de markup
  não foi decidida).
- **Se a lista de Receitas deveria mostrar o custo por unidade boa** (como a ordem recomendada em
  D3). Hoje ela segue o handoff §7 ponto 1. Se você quiser mudar, é outra decisão.
- **Dados reais de produção** não foram consultados: não sei quantas empresas usam custo extra fixo,
  desperdício da ordem ou `allow_overselling`. Se ajudar a decidir D2–D4, posso medir antes.

---
**Trilha:** 2026-10-09 — escrito a pedido de [M] para levar ao [W]. [M+C]
