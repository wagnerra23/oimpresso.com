---
date: "2026-09-15"
topic: "As três portas pelas quais o validate-memory-schema.sh afirmava sobre o que não mediu — e as seis vezes que cometi a mesma classe consertando-as"
authors: [C]
prs: [7345, 7358, 7361, 7370]
outcomes:
  - "3 portas fechadas no mesmo script: FP de encoding (campo presente acusado de ausente), rodapé contando arquivo inexistente como validado, e tipo inválido saindo exit 0"
  - "LC-33 aberta (vermelho-por-nao-medicao) — espelho da LC-13, com Gate: none alarmando de propósito"
  - "6 recibos de erros meus na própria sessão, mais a errata de uma frase que o merge tornou falsa 2h depois"
---

## TL;DR

O pedido era um falso-positivo. O que apareceu foi **um vício em três portas do mesmo
script** — o `validate-memory-schema.sh` afirmando desfechos sobre o que não tinha
conseguido medir. Fechei as três (#7345, #7361), registrei o ciclo (#7358) e, duas
horas depois, a errata de uma frase minha que o próprio merge tornou falsa (#7370).
Os quatro mergeados. **Seis recibos no ledger, todos de erros meus** — porque cometi
a classe três vezes enquanto a consertava.

## As três portas

**1 — o campo presente acusado de ausente.** `extract_frontmatter_field` imprimia
com `print()`. No Windows o stdout é cp1252; um `→` no `tldr` estourava
`UnicodeEncodeError`, o `2>/dev/null` comia o traceback, o `|| true` zerava o rc, a
função devolvia vazio e o chamador dizia `campo obrigatório ausente`. Alcance medido
com o predicado do script, por tipo: **167 de 456** handoffs (36,6%), **79 de 549**
sessions (14,4%), 0 de 61 SPEC.

Não era a 1ª vez. Em **2026-07-29** o mesmo defeito foi medido, diagnosticado
corretamente e adiado por two-strikes. Pior: em **2026-09-10** nasceu o bite-test que
o pegaria — já carregando `PYTHONIOENCODING: 'utf-8'` no env, com um comentário que
descrevia o bug com precisão. **A muleta no chamador cegou o único mecanismo capaz de
acusá-lo**, e ficou indistinguível de conserto no log do CI. Esse é o incremento da
lápide.

**2 — o rodapé.** `[[ ! -f ]] → continue` sem incrementar `SKIPPED`, e o rodapé faz
`TOTAL - SKIPPED`. Reproduzido com o padrão exato do meu próprio erro do dia (lista de
paths escrita com CRLF): `50× [SKIP]` e `Arquivos validados: 50 (skipados: 0) — erros: 0`
com **rc=0**. Virou `0 de 50 (pulados: 0 · inexistentes: 50) — erros: 50` com rc=1, e
os três baldes somam o total.

**3 — o tipo inválido.** O `TYPE` era validado só dentro do laço, então tipo torto
**sem arquivos** nunca chegava lá: `--selftest` devolvia `exit 0` com `[OK] nada a
validar`. Agora reprova na entrada e a mensagem **aponta o bite-test real** em vez de
fingir ter um próprio — implementar um `--selftest` ali seria LC-19.

## O que eu errei, medido

| classe | o que foi | como apareceu |
|---|---|---|
| LC-08 | extraí número com `tail -1` e peguei o `erros: 0` da **NOTA do próprio script** | publiquei "CORRIGIDO erros: 0" quando o real era 8 e 18 |
| LC-08 | medi um site forçando `PYTHONIOENCODING=cp1252` (estrito) | conclui "aborta sob `set -e`" e **escrevi num comentário de código**; o padrão é `surrogateescape`, que corrompe calado |
| LC-08 | `git rev-parse "<ref>:<path>"` mangleado pelo MSYS com `2>/dev/null` | publiquei "DIVERGE — um commit do main tocou o script"; eram iguais |
| LC-13 | lista de paths com CRLF | comparei duas versões sobre 50 arquivos que **nenhuma** abriu e publiquei `erros: 0` dos dois lados |
| LC-26 | par de barras num heredoc Python | `SyntaxError: unterminated string literal` |
| LC-10 | escrevi "**NÃO consertado**" em presente sobre um gap que eu ia fechar | apodreceu 2h depois, no merge |

Três deles viraram **claim publicada** antes de eu corrigir. O que me salvou nos três
foi descer um nível e medir de novo — nunca revisão de leitura.

## O adversário

Rodei o `ciclo-adversary` antes de escrever no ledger, como o canon manda. **REJECT**
com 5 itens. Verifiquei os cinco por medição própria (§5 2026-07-26: correção de peer
é hipótese, não patch) e **os cinco conferiram**. Quatro apliquei tal qual; o quinto —
o denominador — apliquei com número **meu**: 549, não os 552 dele, porque ele não
aplicava o guard de YAML e o critério é *o mesmo predicado no numerador e no
denominador*.

O achado que mais doeu foi o #1: eu ia arquivar na LC-13 alegando que *"a lápide-mãe
declara LC-13"*. **Falso** — ela cita por analogia, dentro do bullet "O limite", e não
tem linha `Ocorrência da LC-NN`. Derivei a classe de um hit de `grep`, que é
literalmente a LC-08 cometida ao registrar a LC-08.

## A classe nova

**LC-33 `vermelho-por-nao-medicao`.** A LC-13 colapsa "não consegui medir" em **verde**
e esconde defeito; esta colapsa em **vermelho** e fabrica defeito, mandando consertar o
alvo errado. Três razões medidas contra enfiar na LC-13: a **LC-14** proíbe
nominalmente somar as direções; o próprio §5 já usa a direção como discriminador
(*"sentido PERMISSIVO — logo é LC-13, não LC-08"*); e a emenda **anterior** da mesma
lápide-mãe virou classe nova (**LC-24**). E o custo prático decide: o `Gate:` da LC-13
está **armado**, então arquivar ali mandaria uma 2ª ocorrência sem defesa de classe
para um balde **mudo**.

Nasceu com `Gate: none` e **alarmando** — é o sinal honesto, não um esquecimento.

## Método que funcionou (e o que não)

- **Mutação, sempre.** Cada perna nova do bite-test foi revertida contra o blob
  anterior: PERNA 4 → 2 de 3 caem; PERNAS 5+6 → 7 de 7. E o **controle negativo**
  ficou verde nos dois lados, provando que não implementei o conserto quebrando o
  caminho legítimo.
- **FP medido antes de armar.** Antes de tornar "arquivo inexistente" um erro: os 3
  jobs usam `--diff-filter=AM`/`=A` e **zero** arquivo sob `memory/` tem espaço no
  nome (controle positivo: 3951 com hífen).
- **O que NÃO funcionou:** ler. Os seis erros passaram por revisão minha e só caíram
  quando algo foi executado.

## Hooks que morderam

`block-destructive` barrou um `rm -rf` e um `git push --force` — os dois corretamente,
e o segundo me fez refazer sem reescrever histórico remoto. `memory-schema-preflight`
barrou o frontmatter do handoff desta própria sessão (slug de ADR com ponto). Três
mordidas úteis num dia.
