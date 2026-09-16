---
date: "2026-09-16"
time: "13:30 UTC"
slug: gt-g5-duas-familias-gate-e-lapide
tldr: "As duas familias que o GT-G5 isolou em 5 rodadas tiveram desfechos opostos: a 1a (removido x mudou-de-casa) virou gate por igualdade de hash de blob; a 2a (claim de historia) morreu medida com 100% de FP. O lote de 112 orfaos fechou por DECISAO [W] com bypass de required, nao por aprovacao — a curva INVERTEU nas 3 ultimas rodadas. O gate que mergeei nasceu com 4 defeitos meus, achados pela sessao irma e pelo adversario dela."
prs: [7377, 7392, 7397, 7402, 7408]
decided_by: [W]
related_adrs: [0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0344-two-strikes-cobre-processo]
next_steps:
  - "O gate `mudou_de_casa` segue ADVISORY, e agora pela razao certa: o #7399 (sessao irma) mediu que a FRACAO e a metrica errada — ela mede quanto sobreviveu, nao se mudou de casa, e da falso-negativo em 8 de 8. A separacao real e `0 x >0`. Promocao a required depende do predicado novo (>=1 blob sobrevive), nao de calibrar numero. NAO re-propor limiar de fracao."
  - "DECISAO [W] PENDENTE, nomeada na emenda do #7408: existe defesa mecanica para a 2a familia no chokepoint `auditRequisitos()` — o sujeito ali NAO vem de prosa, e o `ciclo-adversary` mediu 1 TP / 0 FP em 24a3f7f772e. Nao foi armada por populacao pequena. Armar ou nao e ato do dono; o que a lapide NAO pode dizer e que nao existe defesa."
  - "RESIDUO do #7402: o contador `mudou_de_casa_nao_resolvidos` expoe 117 pares (linha x ponteiro) que o gate NAO consegue medir. Nao sao acusacoes — sao nao-medicao contada (LC-33). Separar `sha nao tem o path` (indicio de tombstone falso) de `path mal extraido` exigiria julgar prosa; quem atacar isso precisa de criterio que nao seja sintatico."
  - "RESIDUO do #7392, ainda valido: o extrator so reconhece paths com prefixo conhecido do repo (prototipo-ui, memory, resources, ui_kits, app, Modules, scripts, governance, public, tests, database). A fixture do bite-test expos isso quando usei `proto/` e o gate ficou mudo."
  - "O eixo (b) da 2a familia — a data escrita bate com a do commit? — e IMUNE ao problema de associacao (so precisa do sha e da data, ambos no mesmo tombstone) e deu 0 em 97, 0 FP. Mas NAO foi validado contra caso conhecido, e zero pode ser cegueira: nesta investigacao TRES zeros eram. Reabrir exige caso que prove a mordida + decisao de qual data e o oraculo (author x committer, §5 2026-09-03)."
  - "Rodar o checklist MCP-first ao abrir sessao nova — ele NAO pode ser rodado aqui (tools MCP indisponiveis, ver §Estado MCP). Este handoff nao carrega snapshot de cycles/my-work/sessions-recent."
---

# GT-G5: duas familias, desfechos opostos

## Estado MCP no momento do fechamento

⚠️ **Os tools MCP estavam INDISPONIVEIS nesta sessao** — o `brief-fetch` do
SessionStart devolveu erro JSON-RPC e caiu no fallback documentado em
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback. Logo o checklist MCP-first
(`cycles-active` + `my-work` + `sessions-recent` + `decisions-search`) **nao pode
ser rodado**, e este handoff nao carrega snapshot deles. Isso e ausencia de
medicao, nao "nada a reportar" — quem abrir sessao nova deve rodar o checklist
antes de assumir estado.

O que **foi** medido, com oraculo proprio:

- `origin/main` no fechamento: `07b1a765857`, **0 vermelhos** no tip
- os 7 PRs do dia, todos **MERGED**: #7377 #7392 #7397 #7399 #7402 #7406 #7408
- branch protection restaurada e validada por `protection-drift` (veredito ok)

## O que entrou

| PR | o que |
|---|---|
| #7377 | 112 ponteiros orfaos mudos pagos + gate estendido (placeholder, reticencias, "renomeada pra") |
| #7392 | gate distingue **removido** x **mudou de casa** |
| #7402 | 4 consertos desse gate — todos defeitos meus |
| #7397 | lapide: a 2a familia **nao vira gate** (100% FP) |
| #7408 | emenda dessa lapide |
| #7406 | flip da ADR 0401 `proposto -> aceito` (destravou 6 PRs) — da sessao irma |
| #7399 | o limiar de fracao e a metrica errada — da sessao irma |

## Por que o #7377 fechou por decisao, e nao por aprovacao

O `ledger-check --enforce` estava **vermelho** no merge: nenhuma das 5 rodadas do
GT-G5 aprovou. [W] autorizou o bypass; `enforce_admins` foi desligado e religado
na sequencia, com `protection-drift` confirmando restauracao string-exata.

A trajetoria esta no corpo do merge `d233e401098` e e o que importa para quem ler
depois: `45,67% -> 23,62% -> 10,74% -> 2,50% -> 2,79% -> 3,23%`. Caiu 14x nas
quatro primeiras e **inverteu** nas tres ultimas. Nao e "falta pouco" — e que o
conserto introduz erro a uma taxa comparavel a que remove. **Nao leia aquele
merge como aprovacao do protocolo.**

## O que separa a familia que virou maquina da que nao virou

Nas duas o alvo e o mesmo tipo de frase. A diferenca esta no **predicado**:

- **1a familia**: *o conteudo desse path sobrevive em outro lugar?* -> igualdade
  de **hash de blob**. Nao precisa associar frase a path. **0 FP em 91.**
- **2a familia**: *essa afirmacao de historia e verdade?* -> precisa saber **a
  qual alvo a frase se refere**, e o sujeito do verbo frequentemente **nem e
  path** (coluna de DB, id de US, agendamento). **100% FP.**

Esse par e a regra reutilizavel: predicado que resolve por lookup sobrevive;
predicado que precisa ler intencao cai na familia de guard sintatico que o §5 ja
enterrou 8x.

## O gate nasceu torto — e quem achou foram outros

Quatro defeitos meus no #7392, corrigidos no #7402. Dois foram achados pela
sessao irma, um pelo `ciclo-adversary` que ela despachou:

| defeito | efeito medido |
|---|---|
| extrator nao cortava no espaco | **35%** do corpus invisivel |
| dispensa por VOCABULARIO | acusaria linha correta ("foi movido para") |
| destino citado em nivel mais alto que o blob | 3o FP, criado pelo proprio conserto |
| `if (!obj) continue` mudo | **117** nao-medicoes descartadas (LC-33) |

O quarto e o mais grave: `<sha>^:<path>` nao resolver **e indicio de tombstone
falso** — exatamente o que o gate existe pra achar — e o codigo descartava calado,
com `sh()` engolindo stderr. O gate reportava `0` acusacoes ao lado de 117
nao-medicoes.

## A licao de metodo

As **quatro** medicoes da investigacao da 2a familia vieram erradas por **cegueira
da sonda**, nunca por estado do corpus, e todas com cara de resultado. O que
segurou as quatro foi **controle positivo contra um caso conhecido**. Corolario
no §5: gate derivado de sonda precisa de controle positivo **embutido**.

## Trabalho em par (e o que ele custou e rendeu)

Duas sessoes no mesmo arquivo, coordenadas por mensagem. A colisao foi detectada
e **coordenada antes de produzir artefato** (§5 2026-09-05): eu assumi o eixo do
gate, ela o do limiar. Rendeu: ela achou 2 dos meus 4 defeitos, o adversario dela
achou o 3o, e a causa dela para a lapide substituiu a minha. Em troca, minha
objecao a calibracao dela a fez re-medir e descobrir que o proprio vao
`(0.455, 0.705)` era **artefato tautologico** (§5 2026-07-17, drift-sentinel).
