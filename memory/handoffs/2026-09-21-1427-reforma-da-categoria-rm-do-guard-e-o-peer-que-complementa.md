---
date: "2026-09-21"
time: "14:27 BRT"
slug: "reforma-da-categoria-rm-do-guard-e-o-peer-que-complementa"
tldr: "A categoria de remoção do block-destructive foi reformada em 3 PRs mergeados: a isenção passa a valer pelo CONJUNTO de flags e por ALVO em qualquer posição, e o detector passa a cobrir o comando SEM FLAG. Custo medido antes: 557/559 passam a bloquear (90,5% remoção real), 32 afrouxados todos benignos. O /i do detector, que os dois lados liam como herança decorativa, protege caso REAL: no Git Bash/NTFS o nome em maiúsculo resolve e executa."
prs: [7604, 7613, 7619]
decided_by: [W]
next_steps:
  - "Nenhuma ação pendente de código — os 3 PRs mergeados (#7604 12:43Z, #7613 13:57Z, #7619 14:25Z)."
  - "Sinal a vigiar, e é do [W]: 559 é o custo medido NO PASSADO. O padrão sonda-e-remove é recorrente; o atrito vai aparecer como pedido de aprovação repetido, nunca como vermelho de CI."
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **sem tasks ativas** pra `wr23`
- `decisions-search` → nenhuma ADR nova nesta sessão (o trabalho é hook + ledger, não decisão arquitetural)
- Handoffs irmãos de 2026-09-21 já em main: **5** (`0800`, `0844`, `0850`, `1139`, `1242`) — índice tocado várias vezes hoje, passo 3.1 aplicado
- Os 3 PRs desta sessão: **todos MERGED** (`e60b5fcf78c`, `f2e061f4e59`, `ce8f5915ae0`)

## O que aconteceu

Um chip pedia alinhar o regex da isenção de `alvosRmRf` ao do detector, **medindo o FP antes**. O passo 4 dele previa a saída possível: *"pode haver razão registrada, e aí o conserto é de redação, não de regex"*. Havia — em **três** camadas (comentário §MULTI-ARG, assert nomeado `DECISÃO`, decisão [W] de 2026-09-16). Os dois primeiros commits fizeram só a redação e os asserts.

O [W] então decidiu fechar os dois lados, em sequência: primeiro o "vale" da isenção, depois o comando **sem flag**.

**O que estava errado na isenção:** ela casava o par **ordenado** `(r|R)(f|F)`. As três formas que o SO trata como o mesmo comando saíam do hook com vereditos diferentes, e o menos destrutivo bloqueava enquanto o mais destrutivo passava. A justificativa registrada media **quantidade** (o "43" de 2026-09-16), não **risco**.

**O que mudou o resultado por uma escolha de técnica:** ao abrir o detector para o comando sem flag, o regex ingênuo acusaria **183** ocorrências de `git rm` — 31,8% de FP. Excluir `<tool> rm` por um helper (`ehToolRm`) derrubou para 10,1%. Medi as duas versões.

**O achado que ninguém tinha:** o `/i` do detector, que **os dois lados** liam como herança decorativa do porte `.ps1`, protege caso **real**. No Git Bash sobre NTFS (case-insensitive), o nome em maiúsculo resolve para `/usr/bin/…` e **executa** (`coreutils 8.32`). Uma sessão irmã propôs removê-lo alegando *"POSIX é case-sensitive"* — premissa verdadeira em Linux/macOS, falsa na plataforma onde o hook roda.

## Artefatos gerados

| artefato | onde | estado |
|---|---|---|
| isenção por conjunto de flags | `.claude/hooks/block-destructive.mjs` | [#7604](https://github.com/wagnerra23/oimpresso.com/pull/7604) **MERGED** 12:43Z |
| detector sem flag + `ehToolRm` + isenção por posição | idem | [#7613](https://github.com/wagnerra23/oimpresso.com/pull/7613) **MERGED** 13:57Z |
| recibo na LC-08 (sem lápide) | `memory/LICOES_CODE.md` | [#7619](https://github.com/wagnerra23/oimpresso.com/pull/7619) **MERGED** 14:25Z · CI 112 pass |
| asserts | `.claude/hooks/block-destructive.test.mjs` | 171 → **190** |

## Persistência

- **git canônico:** os 3 PRs em `main`; smoke real do hook rodado **contra a versão de main** (10/10, com controle positivo do harness)
- **MCP:** propaga por webhook (~2min) — sem task a fechar, não havia cycle ativo
- **BRIEFING:** não se aplica — o trabalho é hook de sessão, não capacidade de módulo

## Próximos passos pra retomar

Nada pendente de código. Para retomar o contexto: ler o corpo do [#7613](https://github.com/wagnerra23/oimpresso.com/pull/7613), que carrega a medição completa e a tabela de classes.

## Lições catalogadas

**Registrada no ledger (LC-08, [#7619](https://github.com/wagnerra23/oimpresso.com/pull/7619)), e o desfecho é o interessante:** eu ia abrir lápide alegando incremento — *"peer que COMPLEMENTA é diferente de peer que CONTRADIZ"*. O `ciclo-adversary`, rodado **antes** de virar canon e com instrução explícita de me dizer que a lápide não deveria existir, **rejeitou**: a §5 2026-08-31 já generaliza sem condicionante, e a §5 2026-09-21 *grep -iF aborta* — **do mesmo dia, mesmo bloco** — já instancia o caso complementar. Ficou só o recibo.

**E ao verificar o adversário, cometi a mesma classe:** busquei a citação dele por `grep` da string **sem os asteriscos de markdown** que o texto real tem no meio, obtive 0, e quase publiquei que ele havia inventado a citação. O controle positivo que rodei junto não salvou — provava que o arquivo abria, não que o padrão estava certo.

**Três sondas minhas erraram e foram contidas no terminal:** mutante que casou 2 linhas (isenção **e** detector) produzindo 7 FAIL que mediam outra coisa; balde que classificava `key→outra-key` como afrouxamento e fabricou um `AFROUXOU=1` inexistente; e `node -e` que comeu o `-f` como opção **do node**, devolvendo `exit=0` que quase li como *passa*.

**O que nenhum gate pegou:** nenhum dos erros desta sessão — meus ou da sessão irmã — foi detectado por máquina. Foram pegos por *"a soma não fecha"*, por *"rode o comando na plataforma certa"* e por medir o número do peer antes de usá-lo. Por isso o que ficou no código foram os **comandos reprodutores**, não as conclusões.

## Pointers detalhados

- Medição completa, tabela de classes e as bordas: corpo do [#7613](https://github.com/wagnerra23/oimpresso.com/pull/7613)
- Escala de 13 pontos e por que o `/i` fica: §ESCOPO / §FLAG-SET / §POSIÇÃO em `.claude/hooks/block-destructive.mjs`
- Recibo da lição: `memory/LICOES_CODE.md`, bloco `## LC-08`, último `- **rec**`
- Lápide irmã que já cobria a classe: §5 2026-09-21, *`grep -iF` aborta*
