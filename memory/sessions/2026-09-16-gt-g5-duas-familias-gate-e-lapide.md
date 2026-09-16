---
date: "2026-09-16"
topic: "As duas familias que reincidiram nas 5 rodadas do GT-G5: uma virou maquina e foi consertada 4x por erro meu; a outra morreu medida (100% FP) e a lapide precisou de emenda no mesmo dia porque afirmava mais do que a medicao sustentava"
authors: [C]
prs: [7377, 7392, 7397, 7402, 7408]
outcomes:
  - "112 ponteiros orfaos MUDOS pagos em memory/requisitos (#7377) — mergeado SEM entry no ledger, bypass deliberado de required autorizado por [W], com enforce_admins desligado e religado (protection-drift veredito ok). O corpo do merge registra a trajetoria das 5 rodadas: 45,67% -> 23,62% -> 10,74% -> 2,50% -> 2,79% -> 3,23%, que INVERTEU nas tres ultimas"
  - "A 1a familia (removido x mudou-de-casa) virou gate (#7392): predicado DETERMINISTICO por igualdade de hash de blob, nao heuristica — por isso escapa da familia de guard sintatico que o §5 ja enterrou 8x. FP medido ANTES de armar: 0 em 91 tombstones, e pega os 4 casos que o refutador levou uma rodada inteira pra achar a mao"
  - "O gate nasceu com 4 defeitos MEUS, consertados no #7402: enxergava 35% menos do corpus (extrator nao cortava no espaco), dispensava por VOCABULARIO em vez de por destino (acusaria linha correta), o proprio conserto criou um 3o FP (destino citado em nivel mais alto que o blob), e descartava 117 nao-medicoes em SILENCIO (LC-33) enquanto reportava 0 acusacoes"
  - "A 2a familia (claim de historia sem git log) MORREU MEDIDA (#7397): 100% de FP em 2 dos 3 eixos. Causa estrutural — o sujeito do verbo frequentemente NEM E PATH (coluna de DB, id de US, agendamento), entao nao ha o que extrair"
  - "A lapide precisou de EMENDA no mesmo dia (#7408): a frase resiste-a-maquina era forte demais e em canon append-only vira instrucao de desistencia (§5 2026-09-01). Existe defesa no chokepoint auditRequisitos(), 1 TP / 0 FP medido — nao armada por populacao pequena, decisao [W]"
  - "As 4 medicoes da investigacao da 2a familia vieram ERRADAS por cegueira da sonda, nunca por estado do corpus — e todas com cara de resultado. O que segurou as 4 foi controle positivo contra caso conhecido (§5 2026-08-01); nenhum gate teria pego"
---

# Duas familias, um gate, uma lapide — e o gate precisou de quatro consertos

## TL;DR

As duas familias que o refutador GT-G5 isolou em 5 rodadas tiveram desfechos
**opostos**, e a diferenca esta no PREDICADO, nao no tema:

| familia | predicado | resultado |
|---|---|---|
| removido x **mudou de casa** | resolve por LOOKUP (igualdade de hash de blob) | **0 FP em 91** -> virou gate (#7392) |
| **claim de historia** sem `git log` | precisa ler INTENCAO (a qual alvo a frase se refere) | **100% FP** -> virou lapide (#7397) |

O lote de 112 orfaos mudos (#7377) fechou por **decisao [W] com bypass de
required**, nao por aprovacao: a curva de erro INVERTEU nas tres ultimas rodadas
(`2,50 -> 2,79 -> 3,23%`) porque o conserto introduz erro a uma taxa comparavel a
que remove. O gate que eu mergeei nasceu com **4 defeitos meus** (35% do corpus
invisivel, dispensa por vocabulario, um 3o FP criado pelo proprio conserto, e 117
nao-medicoes descartadas em silencio), achados pela sessao irma e pelo
`ciclo-adversary` dela. E a lapide precisou de **emenda no mesmo dia**, porque
afirmava mais do que a medicao sustentava.

A sessao comecou pagando os **112 ponteiros orfaos mudos** que a 2a raiz do
`charter-blueprint-pointers` passou a enxergar, e terminou com as duas familias
de erro que o refutador GT-G5 isolou em 5 rodadas tendo desfechos **opostos**:
uma virou maquina, a outra virou registro de impossibilidade.

## O lote (#7377) e por que ele fechou por decisao, nao por aprovacao

Cinco rodadas do GT-G5, cada uma a ~350-400k tokens. A curva caiu 14x nas quatro
primeiras e **inverteu** nas tres ultimas: `2,50% -> 2,79% -> 3,23%`. A causa nao
e "falta pouco" — e que **o conserto introduz erro a uma taxa comparavel a que
remove**: 2 dos 6 refutados da ultima rodada nasceram do conserto da anterior.

[W] optou por mergear com bypass. O `ledger-check --enforce` estava vermelho
porque nenhuma rodada aprovou; `enforce_admins` foi desligado e religado, e o
`protection-drift` confirmou a restauracao string-exata — contar "46 contexts"
nao prova nada, foi assim que o mojibake de 2026-07-02 deadlockou a main. O corpo
do merge registra tudo isso para que ninguem leia o merge como aprovacao.

## A 1a familia virou maquina — e o predicado e o que a salva

Dizer `_(removido em <sha>)_` quando o arquivo so mudou de endereco faz dois
estragos: engana quem le e **blinda a linha** contra cobranca futura, porque
`declaraMorte()` a considera resolvida.

O predicado e **igualdade de hash de blob**, nao heuristica de similaridade. O
git ja faz deteccao de rename por similaridade (`diff -M`, prior art), mas o caso
aqui e mais estreito: o blob sobrevive IDENTICO, entao basta lookup. Por ser
lookup e nao julgamento de prosa, escapa da familia de guard sintatico.

FP medido **antes** de armar: 0 em 91 tombstones. E o teste que importa: os 4
gaps Essentials que o refutador da r5 levou uma rodada inteira pra achar a mao
aparecem na varredura.

## O gate nasceu torto, e quem achou foram outros

Quatro defeitos, todos meus, achados pela sessao irma e pelo `ciclo-adversary`
que ela despachou:

| defeito | efeito medido |
|---|---|
| extrator nao cortava no espaco | **35%** do corpus invisivel |
| dispensa por VOCABULARIO | acusaria linha correta ("foi movido para") |
| destino citado em nivel mais alto | 3o FP, criado pelo proprio conserto |
| `if (!obj) continue` mudo | **117** nao-medicoes descartadas (LC-33) |

O conserto do vocabulario **nao** foi ampliar a lista de verbos — eu mesmo tinha
escrito que excecao que cresce por vocabulario e como um gate morre. Virou
`jaDeclaraDestino(linha, destinos)`, que compara com o **path vivo do blob**.

O ultimo e o pior: `<sha>^:<path>` nao resolver **e indicio de tombstone falso**,
que e exatamente o que o gate existe pra achar — e o codigo jogava fora calado,
com `sh()` engolindo stderr, tornando "nao resolve" indistinguivel de "git
falhou". O gate reportava `0` acusacoes ao lado de 117 nao-medicoes.

## A 2a familia morreu medida

Recorte: *"esta afirmacao foi medida?"* e semantico (ADR 0224), mas *"esta
afirmacao e VERDADE?"* parecia decidivel contra o git. Tres eixos, cada um
nascido de um erro meu documentado nas rodadas.

**100% de FP em dois deles.** A causa e estrutural: a linha cita mais de um path,
ou mais de um tombstone no mesmo parenteses, e — o que a sessao irma mediu, e e
mais fundo que o meu enquadramento — **o sujeito do verbo frequentemente nem e
path**: coluna de banco, id de US, agendamento. A minha causa sugeria que um
extrator melhor resolveria; a dela mostra que nao ha o que extrair.

O eixo (b) (a data bate com a do commit?) e imune a associacao e deu 0 em 97 —
mas **nao foi validado contra caso conhecido**, e zero pode ser cegueira: nesta
investigacao tres zeros eram. Pre-requisito escrito na lapide.

## A emenda, e por que ela importava mais que o gate

A lapide entrou em main dizendo que a familia *"resiste a maquina em 2 dos 3
eixos"*. O `ciclo-adversary` derrubou: existe defesa no chokepoint
`auditRequisitos()`, onde o sujeito **nao vem de prosa** — e mediu **1 TP / 0 FP**.
O TP e uma linha que dizia `audit/reports/` "nunca versionado" tendo 2 commits —
**erro meu**, consertado nesta mesma sessao, e cuja linha o `declaraMorte()`
ISENTAVA por conter a frase.

Em canon append-only, "nao ha defesa" vira instrucao de desistencia. A diferenca
entre isso e "existe, nao armada por populacao pequena — decisao [W]" e a
diferenca entre uma sessao futura desistir e uma sessao futura perguntar.

## A licao de metodo, que vale mais que os dois gates

As **quatro** medicoes da investigacao vieram erradas **por cegueira da sonda**:
regex `/g` no escopo do modulo vazando `lastIndex` entre arquivos; regra que
exigia o path existir em `sha^`, falsa justamente no caso que deveria pegar;
extrator levando prosa junto com o path; e um patch que **nao aplicou** (LC-26 no
transporte) cujo `AssertionError` saiu logo acima de numeros identicos aos
anteriores. Some-se o `node --check` aprovando duas classes de caractere
semanticamente erradas.

Nenhuma foi pega por revisao. O que pegou as quatro foi **controle positivo
contra um caso que eu sabia que casava**. Corolario que ficou no §5: gate
derivado de sonda precisa de **controle positivo embutido**, nao so bite-test do
caminho feliz.

## Trabalho em par

Duas sessoes no mesmo arquivo, coordenadas por mensagem: eu no eixo do gate, ela
no limiar. Ela achou 2 dos meus 4 defeitos; o adversario dela achou o 3o; e a
causa dela para a lapide substituiu a minha. Em troca, a objecao que levantei a
calibracao dela a fez re-medir e descobrir que o proprio vao `(0.455, 0.705)` era
**artefato tautologico** — ela rotulara as amostras negativas *porque sao o que o
gate nao acusa*, que e a §5 2026-07-17 (drift-sentinel). O limiar caiu no #7399.
