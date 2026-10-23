---
slug: 0438-artefato-substitui-cowork-como-superficie-do-prototipo
number: 438
title: "O Artefato substitui o Cowork como lugar de ver e comentar o protótipo; o transporte do Cowork sai"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-23"
module: governance
quarter: 2026-Q4
tags: [design, cowork, artefato, prototipo, ssot, transporte]
supersedes: []
supersedes_partially:
  - 0433-fonte-do-prototipo-passa-ao-git
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0412-retorno-canon-mergeado-ao-cowork-dispensa-opt-in-emenda-0315
superseded_by: []
related:
  - 0282-protocolo-v2-colapso-ratificacao
  - 0299-figma-nao-e-fonte-de-design
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
pii: false
review_triggers:
  - "um PR mudar o protótipo publicando o Artefato sem commitar o arquivo no git (a fonte voltou a morar fora do repo)"
  - "o CI precisar ler o protótipo e não conseguir a partir do arquivo versionado"
  - "a Anthropic mudar o modelo de Artefatos (privacidade, edição pelos visitantes, limite de tamanho) de forma que publicar a partir do git deixe de funcionar"
---

# ADR 0438 — o Artefato substitui o Cowork como lugar de ver e comentar o protótipo

## Contexto

A [ADR 0433](0433-fonte-do-prototipo-passa-ao-git.md) (2026-10-15) fez do git a fonte do
protótipo do [W] e deixou o Cowork (claude.ai/design) como ferramenta opcional: desenhar lá
continuava permitido, o pacote descia pela rota ZIP e a trava `[6b]` do `receber-handoff`
impedia que ele passasse por cima do git.

O "opcional" manteve vivo todo o transporte entre o Cowork e o repo: download por `get_file`,
bundle em partes, recibo de import, ledger de frescor do espelho, hooks de opt-in do
`DesignSync`, lembrete de subir pro Cowork, envio do Design System para lá e um check
obrigatório (`espelho — mexeu depois de verificar`). Esse maquinário existe só porque a fonte
morava fora do git; com a 0433 ele passou a proteger um caminho que não é mais o principal.

Dois fatos pesam a favor de encerrar o caminho:

1. O Claude Design standalone fecha em **2026-12-14** (registrado na 0433 e no
   [PROTOCOL.md §10.8](../reference/prototipo-ui/PROTOCOL.md)). O transporte ficaria sem origem.
2. O que o [W] usava do Cowork, depois da 0433, era **ver a tela e comentar**. Um Artefato
   publicado a partir de um arquivo do repo faz isso — página privada no claude.ai, aberta no
   celular ou no computador, com comentários que o agente lê pela ferramenta `ArtifactComments`.

A diferença que importa: o Artefato **não é fonte**. O CI não tem login no claude.ai, o PR não
mostra o que mudou numa página publicada, e o time só vê o que for compartilhado. Se a fonte
passasse a ser o Artefato, voltaria o problema que as regras de âncora existem para impedir
(copiar o design no olho). Por isso a decisão separa as duas coisas.

## Decisão

[W] 2026-10-23, textual: *"Escopo A: troca o Cowork pelos Artefatos (HTML no git, o Artefato só
para ver e comentar). Remove o maquinário de transporte do Cowork e mantém âncora e contrato de
tela. Começa pela ADR."*

- **D1 — a fonte continua no git.** O protótipo é o arquivo versionado sob `prototipo-ui/`
  (D1 da 0433, intacto). Os caminhos atuais **não mudam** nesta decisão: `related_prototype` dos
  charters, os manifestos e a resolução de âncora (`scripts/design/ancora.mjs`) apontam para eles.
  Renomear a pasta `cowork/` é decisão separada, porque quebra essas referências.
- **D2 — o Artefato é a superfície de ver e comentar.** Toda tela de protótipo que o [W] for
  revisar é publicada como Artefato **a partir do arquivo do repo**. O Artefato nunca é editado
  como fonte: se a página publicada divergir do arquivo, vale o arquivo, e a página é republicada.
- **D3 — comentário no Artefato é entrada, não mudança.** O pedido feito num comentário vira
  commit no arquivo do repo, num PR; depois a página é republicada. Nada que exista só na página
  conta como design aprovado.
- **D4 — o Cowork deixa de ser rota de entrada e de saída.** Supersede parcialmente:
  - a **0433 D2** (desenhar no Cowork e trazer pela rota ZIP) e a **0433 D5** (retorno ao Cowork);
  - a **0412** inteira no eixo do protótipo (o canal `cowork-inbox/` deixa de subir);
  - a **0315** no que trata o `DesignSync` como transporte de leitura e escrita do protótipo.

  Leitura pontual do Cowork para recuperar um arquivo antigo continua possível até o fechamento,
  como arqueologia, sem precedência sobre o git.
- **D5 — o maquinário de transporte sai, em ondas.** Sai o que só existe para levar ou trazer
  conteúdo entre o Cowork e o repo, ou para provar que o espelho está igual ao Cowork:
  importação e aplicação de bundle/payload, recibos de import, frescor do espelho contra o vivo,
  hooks de opt-in do `DesignSync`, lembrete de subida, envio do Design System ao Cowork, e as
  partes das skills e do `CLAUDE.md` que descrevem esse caminho. Cada onda é um PR com a lista
  medida (consumidores contados no repo inteiro) do que remove; esta ADR não fixa a lista, para
  não restatear o que a medição de cada onda sabe melhor.
- **D6 — o que fica.** Não mudam: a **Âncora de design** e os **Contratos de tela** (os dois
  checks obrigatórios), o charter, a Constituição UI ([UI-0013](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)),
  a comparação protótipo × produção medida por `design-diff`, o `visual-regression` (advisory) e
  a [0299](0299-figma-nao-e-fonte-de-design.md) (Figma não é fonte). O Design System continua
  vivendo no git; só o envio dele ao Cowork sai.
- **D7 — o check obrigatório do espelho sai por flip do [W].** `espelho — mexeu depois de
  verificar` mede a prova de fidelidade contra o Cowork, que esta decisão encerra. Tirá-lo da
  proteção do `main` é ato do [W] (R10), feito na onda que remover o script dele, com os PRs
  abertos atualizados no mesmo passo (§5 2026-08-08). Até lá ele segue rodando.
- **D8 — os playbooks de thread passam a ser escritos no git.** [W] 2026-10-23, textual: *"Os
  playbooks passam a ser escritos aqui no git, como qualquer protótipo: eu peço, você escreve o
  00-INDICE e as threads, PR e merge."* O `00-INDICE.md` e os `NN-*.md` sob
  `prototipo-ui/cowork/<dono>/cowork-inbox/<mod>/playbook/` deixam de ser autoria do Cowork: o
  [W] pede, o agente escreve, e o merge do PR é a aprovação. A regra antiga do `/onda` ("o índice
  e o resto do playbook são do Cowork — não edite no espelho") cai com esta decisão; o texto dela
  é trocado na onda 3. O `_saida-NN.md` segue como recibo da thread, sem mudança.

## Consequências

- O [W] pede a mudança no chat ou num comentário do Artefato; o agente edita o arquivo no repo,
  abre PR e republica a página. A revisão visual é no Artefato, o histórico é no git.
- A fidelidade que importa continua sendo protótipo × produção. A fidelidade espelho × Cowork
  deixa de existir como requisito.
- Os 2.292 arquivos de `prototipo-ui/` (medido em 2026-10-23, `git ls-tree -r origin/main
  prototipo-ui/ | wc -l`) ficam onde estão. Arquivar o que não é mais âncora de nenhuma tela é
  trabalho de uma onda própria, com a âncora de cada tela conferida antes.
- Risco declarado: Artefato publicado a partir de arquivo **não commitado** cria uma versão sem
  histórico. A defesa, por ora, é a D2/D3 e a revisão no PR; se acontecer, é o primeiro gatilho de
  revisão desta ADR.
- Ordem das ondas seguintes: (1) remover hooks e skills de transporte; (2) remover scripts e
  workflows de transporte e o check do espelho (D7); (3) ajustar `CLAUDE.md`, `PROTOCOL.md` e o
  hook `design-agente-ativa` para descrever o caminho Artefato, e trocar no `/onda` a regra de
  "não editar o playbook no espelho" pela autoria no git (D8); (4) arquivar o que não é âncora.
