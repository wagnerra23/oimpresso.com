---
date: "2026-09-07"
time: "0810 BRT"
slug: "descida-inline-0389-jana-cowork-sem-disco"
tldr: "O Cowork respondeu que NÃO regenera o pacote (sem disco/node do lado dele) e sugeriu empacotar o espelho — recusado (recibo falso). [W] liberou a rota inline da ADR 0389: 4 arquivos do ciclo Jana 04/09 desceram (2 com sha256 batendo o que o Cowork mediu no vivo, 2 por tamanho), consumidor re-medido (alvo 1022 nós, 3 runs idênticos), gate required 'espelho — mexeu depois' verde com origem DECLARADA no ledger. PR #6933 aberto; #6918 já mergeado. Achado pro painel: a rotina 'Cowork regenera ao fim do ciclo' não tem executor possível."
decided_by: ["W"]
cycle: null
prs: [6933, 6918]
us: []
next_steps:
  - "[W] mergear #6933 (CI em execução no fechamento)"
  - "Painel/PROTOCOL: registrar que a rotina de regeneração do pacote NÃO é executável pelo agente de design (sem disco/node) — quem tem os arquivos em disco e node é o Code via get_file, e o piso inline continua; decisão [W] sobre dono da emissão"
  - "Pedir ao Cowork sha256 de oimpresso.com.html e modulo-padrao.jsx (só o tamanho foi conferido) — evidência independente que a 0389 não dá"
  - "Ratificar ADR 0389 (status proposto → aceito) em PR próprio com label adr-metadata-normalization, se [W] quiser tornar a liberação regra"
  - "Espelho: 254 de 258 seguem sem veredito de frescor (--compare INCONCLUSIVO) — só o pacote fecha"
related_adrs: ["0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0384-design-sync-recibos-executaveis-por-tela", "0387-github-md-diario-cowork-aceito-e-tratado"]
---

# Handoff — a descida inline da Jana, e o Cowork sem disco

> Continuação da sessão de 06/09 ([sessions/2026-09-06-alvo-jana-painel-exportado-por-maquina.md](../sessions/2026-09-06-alvo-jana-painel-exportado-por-maquina.md), seção "2026-09-07"). Este handoff é o **estado pro próximo**.

## Estado no fechamento

| item | estado |
|---|---|
| [#6918](https://github.com/wagnerra23/oimpresso.com/pull/6918) | **MERGEADO** 06/09 12:41Z (squash `4a31261b66`) |
| [#6933](https://github.com/wagnerra23/oimpresso.com/pull/6933) | aberto · CI em execução · merge = [W] |
| espelho | `cli-tabs.jsx` 11.231 B `fb24ce60…` == vivo · `chat-jana.jsx` 33.120 B `d2efd2c4…` == vivo · `oimpresso.com.html` 34.739 B (tamanho) · `modulo-padrao.jsx` 8.566 B (tamanho) |
| alvo Jana | re-medido: 1022 nós · 9/9 · 3 runs idênticos (sha `5c27be80cf996bbb`) · header agora `div > header` (CliPageHead) · nav com `role=tablist` |
| gate `espelho — mexeu depois de verificar` (required) | 0 mexido-depois · ledger com `origemDeclarada: 'agente'` |
| pacote do Cowork | congelado em 24/08; **o Cowork declarou que não consegue regenerar** |

## O que a resposta do Cowork ensinou (e o que NÃO fazer)

- **Certo:** ele não fingiu o recibo, e mediu no vivo o delta vs o manifesto — foi esse delta que, cruzado com o espelho, reduziu 8 candidatos a 4.
- **Errado, e não foi rodado:** `gerar-payload-partes --root prototipo-ui/cowork` empacotaria o **espelho**. Carimbo de hoje em bytes velhos = §5 2026-08-25 (frescor medido contra artefato derivado do próprio espelho).
- **Estrutural:** a rotina "regenera ao fim de todo ciclo" (decisão de 06/09) **não tem executor**: o agente de design não tem disco nem node; o Code só chega ao vivo por `get_file`, que devolve arquivo pequeno inline. Isso tem que entrar no painel como fato.

## Como a rota inline ficou honesta na máquina

`--export-from --origem agente` já existia, mas a declaração só era impressa. Agora ela viaja pro snapshot (`_origemDeclarada`) e pro ledger (`origemDeclarada`) — campo aditivo, nenhum consumidor mudou. A rodada não se passa por export de saída persistida. A 0389 continua dizendo que **não há verificação independente** para inline; a que existe aqui veio de fora (sha256 do Cowork em 2 dos 4).

## Armadilhas desta rodada

- **Cherry-pick com `-q`** não existe: a branch subiu vazia e o `gh pr create` disse "No commits between". Conferir `git log --oneline -1` antes de `push`.
- **PR empilhado numa base já mergeada** falha com "Base ref must be a branch" — checar `gh pr view <n> --json state` antes de escolher a base.
- **`print()` com `→` em console cp1252** derruba o script Python DEPOIS da escrita — o arquivo fica certo e o rc mente. Escrever primeiro, imprimir ASCII.
- **Heredoc + `\n` literal em replace** colapsa (LC-26, 2ª vez nesta sessão): patch por trecho sem barra, ou Write tool.

## Estado MCP no momento do fechamento

Sem tools MCP expostas neste worktree filho; nenhuma task MCP criada — trabalho derivado do "libere" de [W] em chat e registrado nos PRs.
