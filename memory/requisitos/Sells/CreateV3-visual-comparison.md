---
id: requisitos-sells-createv3-visual-comparison
title: "Comparacao design x producao — Sells/CreateV3"
module: Sells
tela: Sells/CreateV3
owner: W
status: rascunho
last_validated: "2026-11-24"
---

# Comparacao design x producao — `Sells/CreateV3`

> **Ancora computada, nao escolhida no olho** — `related_prototype` do
> [`CreateV3.charter.md`](../../../resources/js/Pages/Sells/CreateV3.charter.md) declara
> `prototipo-ui/cowork/Wagner/venda-v3.jsx` (seção `sells-create.jsx`) _(repontado 2026-10-07 por decisão [W] para a cópia do [W]: ela recebeu o conteúdo da do Felipe — busca de cliente sem acento e por dígito, CST, parcelas — e mantém a placa do total escura no tema escuro; a referência anterior era `prototipo-ui/cowork/Felipe/venda-v3.jsx`)_, e o drawer de item mora na seção
> `sells-item-detail.jsx` do mesmo arquivo. _(repontado 2026-09-21 — o espelho do Felipe passou a ser o pacote "Produto Unificado V2", onde a Venda V3 veio num arquivo só: `venda-v3.jsx`, seção `sells-create.jsx`, exporta `window.VendaV3Create`. A âncora anterior `venda-v3/sells-create.jsx` segue no histórico em [bdf6d07e3](https://github.com/wagnerra23/oimpresso.com/blob/bdf6d07e3/prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx); dono: `resources/js/Pages/Sells/CreateV3.charter.md`)_
>
> Primeiro registro desta tela. Medido em **2026-08-27**, depois de o [W] comparar a tela
> com o protótipo e apontar as diferenças aba a aba.

## O que este documento NAO e

Nao e veredito de pixel. A dimensao **D6 (CSS computado / render pareado)** do
[PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md) **nao foi
medida**, e a razao e concreta e verificavel: **o prototipo nao renderiza**. A camada 0 do
Design System nao veio no handoff (`_ds/colors_and_type.css`, `styles.css`, `_ds_bundle.js`
ausentes — o charter ja registra isso na errata de 2026-08-10), e servido em `127.0.0.1` o
entry abre em **tela branca** (`#root` vazio, `window.I` undefined). Sem render do lado do
prototipo nao ha onde injetar a sonda `design-diff.mjs --probe`, e medir **um lado so nao e
comparar**.

Ate destravar isso, o que segue e **comparacao ESTRUTURAL**, campo a campo, contada dos dois
codigos-fonte. Dizer isso importa porque muda o que a prova vale.

## O que ja FECHOU (com PR e recibo)

| Item | Estado | PR |
|---|---|---|
| Colunas configuraveis chegam ao grid | ✅ em producao | [#6313](https://github.com/wagnerra23/oimpresso.com/pull/6313) |
| Fechamento MOSTRA as parcelas geradas | ✅ em producao | [#6349](https://github.com/wagnerra23/oimpresso.com/pull/6349) |
| Aba **Tributacao** igual a ancora | ✅ em producao | [#6351](https://github.com/wagnerra23/oimpresso.com/pull/6351) |

Dois achados colhidos no caminho, os dois **ja em producao antes de alguem ver**:

- **Vencimento exibido um dia antes** (`#6349`). `diaZero` fazia `new Date('2026-08-27')`, e a
  ES manda parsear data-only como **UTC** — em `America/Sao_Paulo` vira `2026-08-26 21:00` e o
  `setHours(0,0,0,0)` grampeia no dia 26. Controle por mutacao: `2026-01-01` exibia
  **31/12/2025** (mes E ano fiscal errados). Tres consumidores reais, todos no `ParcelasDrawer`.
  O CI roda em **UTC** e nunca veria.
- **A aba Tributacao afirmava** *"os valores por imposto sao calculados no servidor na emissao"*
  enquanto a ancora **mostra** os valores. A frase saiu junto com o calculo (`#6351`).

## O que FALTA, por aba

> Excluidas desta lista: **Tributacao** (fechada no `#6351`) e **Geral** (adiantada em relacao
> a ancora — decisao [W] 2026-08-27).

### 1. Observacao — **o mais grave**

| Ancora | Producao |
|---|---|
| **duas** textareas: *"Observacao geral do produto (sai no documento do cliente)"* e *"Observacao interna (nao sai no documento)"* | **uma** so, rotulada *"sai na OS e no documento"* |

A ancora separa as duas **de proposito** e diz por que: unificar foi o que gerou a reclamacao
de **vazamento de nota interna no documento do cliente (CU-SELL-12)**. Producao tem hoje
exatamente o campo unico que causou o problema — quem escrever *"cliente reclamou da cor na
ultima compra"* manda isso pro PDF/NF-e.

**E defeito com causa documentada, nao lacuna de porte.**

### 2. Preco — falta o controle de alcada inteiro

| Ancora | Producao |
|---|---|
| Preco de tabela (ro) · **Menor preco permitido** (ro) · Preco nesta venda (editavel) | Valor unitario · Desconto % · Acrescimo % · Base — todos **read-only** |
| **faixa de alcada**: pill `preco liberado` / `precisa liberacao` + quanto falta/sobra em R$ | — |
| **Desconto sobre a tabela** em % | — |
| nota: custo/markup/margem **nao aparecem pro vendedor** | — |

A aba nao sabe dizer se o preco esta dentro da alcada. E o mecanismo que impede fechar abaixo
do minimo sem supervisor. ⚠️ **Territorio Tier 0 — VALOR** (a REGRA MESTRE nomeia preco e
desconto): exige prova por dois caminhos + tabela antes→depois antes de mergear.

### 3. Producao — 10 campos viraram 3

| Ancora | Producao |
|---|---|
| Em producao · Tipo de impressao · **Acabamento (select, 5 opcoes)** · Local de aplicacao · **Equipamento/setor** · **Prioridade** · **Requisitar do estoque** · **Prazo da equipe** · **Prazo da etapa** | Local · Tipo de impressao · Acabamento **como texto livre** |
| textarea **"Observacao de producao (vai na OP, nao sai no documento do cliente)"** | — |
| secao **"Arquivo de arte"** (caminho na rede + botao Anexar) | — |

⚠️ O "Acabamento" de producao e `<Texto>` com `onChange={() => {}}` — **nao guarda o que se
digita**. Campo que aceita entrada e descarta e pior que campo ausente: parece funcionar.

### 4. Fluxo — read-only

| Ancora | Producao |
|---|---|
| tabela + **remover etapa** · **Adicionar etapa** (modal: etapa/setor/responsavel/previsao) · **Aplicar fluxo padrao do produto** | tabela **estatica** de 4 etapas fixas no codigo (`ETAPAS_PADRAO`) |
| **EmptyState** quando nao ha etapas | — |

### 5. Anexos

| Ancora | Producao |
|---|---|
| dropzone + **Escolher arquivo** + **tabela dos anexos** (arquivo · tipo · enviado · Baixar) | caixa vazia declarando que upload nao faz parte do porte |

## Ordem proposta

Do que doi mais pro que doi menos: **Observacao** (defeito com causa documentada) →
**Preco** (controle de alcada · Tier 0) → **Producao** (campos + arquivo de arte) →
**Fluxo** (edicao) → **Anexos**.

Observacao e Preco sao pequenos e independentes — cabem em dois PRs curtos. Producao e Fluxo
sao maiores.

## Residuo honesto

- **D6 segue nao medida** e vai seguir enquanto a camada 0 do DS nao existir no repo. Isso e
  conflito conhecido e ja registrado no charter; nao se resolve nesta tela.
- A comparacao acima e de **campo**, nao de layout: espacamento, tipografia e cor dos blocos
  novos nao foram comparados com a ancora, porque nao ha render dos dois lados.
- A tela **nao grava** (UC-V302). Nada do que falta aqui chega ao banco hoje — o que muda o
  risco de cada item, nao a sua existencia.

## Aplicado — 2026-08-27

Quatro das cinco divergencias foram fechadas em `_components/v3/ItemDetalhe.tsx` (+199 linhas):

| Aba | O que entrou |
|---|---|
| **Observacao** | as **duas** areas da ancora — geral (sai no documento) e interna (nao sai). Antes era uma so, e o rotulo dizia que ela saia no documento: a que sumia era a interna |
| **Producao** | as 2 secoes (`Instrucoes de producao` · `Arquivo de arte`) e os 7 campos que faltavam — em producao · acabamento · equipamento/setor · prioridade · requisitar do estoque · prazo da equipe · prazo da etapa — mais a observacao de producao |
| **Fluxo** | titulo de secao, **6a coluna** (remover etapa), `EmptyState` e `Aplicar fluxo padrao do produto` |
| **Anexos** | a tabela da ancora (arquivo · tipo · enviado · acao), sob o aviso que ja existia |

Mais os **contadores** no rotulo das abas (`Fluxo` = etapas, `Anexos` = 2), que a ancora tem e o
porte nao trouxe.

### O que ficou de fora, e por que

- **Preco** — nao entra nesta leva. E o controle de alcada (`menor preco permitido`), e mexer
  nele cai na REGRA MESTRE de valor: dois caminhos de prova e o antes→depois apresentado antes
  do merge. Decisao de [W]/[L], nao consequencia deste PR.
- **`Adicionar etapa`** (Fluxo) e **`Anexar arquivo`** (Producao) — a ancora tem os dois; o
  primeiro abre um modal de 4 campos, o segundo grava arquivo. Nenhum dos dois entra como botao
  inerte: botao que promete e nao entrega e pior que botao ausente (charter, Goals).
- **D6 / layout** — segue nao medida, pela mesma razao ja registrada acima: nao ha render dos
  dois lados. O que este PR fecha e **campo e secao**, nao espacamento nem cor.

## Medido em 2026-11-24 — gavetas, design e funções

Pedido do time em 2026-11-24: conferir todas as gavetas, o design e as funções da Nova venda
contra o protótipo, para a tela funcionar até 27/11.

**Como foi medido.** O protótipo agora renderiza. Servindo a raiz do repositório,
`prototipo-ui/cowork/Wagner/oimpresso.com.html` resolve as 335 referências locais e o Design
System por `../../design-system/`. A ressalva de 2026-08-27 acima ("o protótipo não renderiza")
era verdade naquele dia e caducou. Os dois lados foram lidos pelo DOM, com texto, rótulos e
botões de cada bloco e de cada gaveta: a produção em `oimpresso.com` (empresa 1, gravação ligada)
e o protótipo em `VendaV3Create`, os dois no tema escuro.

**D6 (CSS computado) segue não medida.** A sonda `design-diff.mjs --probe` precisa do mapa de
papéis desta tela (`__DD_ROLES`), que ainda não existe. O que segue é estrutura e texto, não
espaçamento nem cor.

### O que a gravação recusa hoje

Com a gravação ligada, a tela oferece estes quatro e o `gravacao-dominio.ts` recusa no "Gravar",
com motivo (UC-V381). É o que mais pesa para a tela funcionar:

| Oferecido na tela | O que a gravação responde |
|---|---|
| Parcelar… | "O plano de parcelas ainda não é gravado" |
| + PIX, + Boleto | "PIX ainda não é gravado — use Dinheiro ou Cartão" |
| Acréscimo | "Acréscimo ainda não é gravado" |
| Beneficiário na gaveta de comissão | "A comissão por beneficiário ainda não é gravada" |

Os quatro mexem em valor (REGRA MESTRE): dupla prova, antes→depois e aprovação [W] antes do merge.

Gravando, a comissão também tem duas portas: o seletor "Comissionado" do rodapé, que grava
(UC-V395), e a gaveta de comissão, que é recusada. Unificar as duas é decisão [W].

### Por bloco

| Bloco | O que o protótipo tem e a produção não | Veredito |
|---|---|---|
| Topo | botão "Voltar para Vendas · esc" | DÍVIDA A FECHAR |
| Numeração das seções | protótipo: 1 Cliente · 2 Itens · 3 Fechamento; produção: 1 Cliente · 2 Itens · 3 Entrega e frete · 4 Observações e produção, e o Fechamento sem número | DÍVIDA A FECHAR |
| Cliente | local da venda, data da venda, pedido de venda vinculado, campos personalizados, venda de reparo | BACKLOG (protótipo em #10207, #10215) |
| Itens | "Consultar produto… F3": consulta em tabela com SKU, EAN, código de fábrica, categoria, tipo, unidade, tabela e estoque | DÍVIDA A FECHAR |
| Entrega e frete | transportadora, veículo, volumes, remessa, entregador, previsão, rastreio e endereço de entrega | gravando, a tela avisa que não grava (UC-V405); gravar é BACKLOG (protótipo em #10209) |
| Fiscal do pedido | natureza da operação, esquema de numeração, nº da fatura, imposto do pedido, informações complementares da NF-e, venda para exportação | BACKLOG (protótipo em #10207 e "Permissões e configurações") |
| Observações e produção | "Abrir OS de produção" e "Documentos da venda · Anexar arquivo" | BACKLOG (protótipo em #10209) |
| Fechamento | cupom, resgatar pontos, despesas da venda, sinal %, + Maquininha, adiantamento | BACKLOG (protótipo em #10209, #10213, #10214; pontos em "Configurações do business") |
| Pagamento | a nota sobre `payment_status=due` (no protótipo, só no modo de anotação) | FECHADO no #11402 |
| Comissão · resumo | "Comissão da venda" com total e percentual, um selo por beneficiário, o gatilho e "Configurar comissão" | FECHADO no #11402 |
| Comissão · gaveta | título "Comissão desta venda"; bruto, líquido e margem no topo; beneficiário por tipo em 4 botões; liberação por parcela recebida; ciclo de apuração (provisionada · aprovada · paga); nota de estorno; rodapé só com "Fechar" (a produção tem "Confirmar comissão") | DÍVIDA A FECHAR na forma; gravar o beneficiário é BACKLOG |
| Parcelas | condição com código e nome ("18 — PIX") | FECHADO no #11402 |
| Parcelas | "Tipo de pagamento" no gerador; valor e tipo editáveis na própria linha; "Soma das parcelas" com "Jogar o resto na última" | DÍVIDA A FECHAR |
| Detalhe do item | 7 abas: Geral, Produção, Fluxo, Tributação, Preço, Anexos e Observação | gravando, não abre (UC-V407): o que as abas mostram ainda não é gravado |
| Situação | gravando, a produção diz só "Não gravada"; o protótipo lista as 8 etapas do fluxo | DÍVIDA A FECHAR: mostrar as etapas do modelo de fluxo da empresa |
| Rodapé | "Descartar", "Salvar rascunho", "Tipo de documento" (Venda · Cotação · Proforma), "Bonificação", "Finalizar venda", "A venda nasce em …" e "Rascunho salvo às …"; a produção tem "Salvar como orçamento", "Comissionado" e "Gravar venda" | rótulos: DÍVIDA A FECHAR; rascunho, proforma e bonificação: BACKLOG (protótipo em #10206, #10213) |
| Colunas do grid | o catálogo do protótipo tem 31 colunas (Seq., Cód. produto, Tipo, Qtd. peças, Comprimento, Fórmula, R$ tabela, R$ desconto, R$ outros, CEST, Em produção, Obs. da produção, Local de estoque…); a produção tem 30, com nomes e grupos diferentes, reordena por ‹ › e confirma por "Aplicar" | DÍVIDA A FECHAR |
| Consulta de cliente | mesma estrutura; a produção pede 2 letras antes de listar | IGUAL na forma (a lista vem do cadastro) |

### O que este registro não cobre

- **D6** (espaçamento, tipografia e cor), pela razão acima.
- **D1** (rede): nenhuma ação desta tela foi medida por `read_network_requests`, além da busca de
  produto (`/products/list`, 200).
- Gravar de verdade em produção: nada foi gravado nesta medição.
