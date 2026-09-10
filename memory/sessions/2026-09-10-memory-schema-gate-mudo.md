---
date: "2026-09-10"
hour: "09:30 BRT"
topic: "O memory-schema-gate detectava 0 arquivos desde que nasceu — pathspec cru do git em pasta plana, e o || true que engolia o rc do diff"
authors: ["C"]
outcomes:
  - "Os 3 jobs validate-*-schema voltaram a enxergar: pathspec :(glob) em memory/handoffs e memory/sessions"
  - "O rc do git diff deixou de ser engolido nos mesmos 3 jobs, sem quebrar o caso de 0 arquivos"
  - "Bite-test de 3 pernas wirado no job selftest, com controle negativo provado em cada uma"
prs: [7182, 7189]
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
---

# O gate que nunca mordeu, e as duas portas pelas quais ele saía verde

## TL;DR

Os jobs `validate-handoff-schema` e `validate-session-schema` do `memory-schema-gate.yml`
detectavam **0 arquivos sempre**, desde que nasceram. Usavam o pathspec **cru** do git
(`-- 'memory/handoffs/**/*.md'`), e ali o `*` casa `/`, logo `**/` exige um componente de
diretório **intermediário** — que `memory/handoffs/` e `memory/sessions/` não têm, por serem
planas. Dois PRs fecharam as duas portas de mudez: o [#7182](https://github.com/wagnerra23/oimpresso.com/pull/7182)
(pathspec `:(glob)`) e o [#7189](https://github.com/wagnerra23/oimpresso.com/pull/7189)
(o `|| true` que engolia o rc do `git diff`).

## Contexto

O achado veio de fora desta sessão: o [#7178](https://github.com/wagnerra23/oimpresso.com/pull/7178)
documentou que dois handoffs mergearam **verdes** estando fora do regex de nome e sem a seção
`## Estado MCP no momento do fechamento` — as duas regras duras do ADR 0130. Quem pegou foi o
`doc-id-index --check-collisions`, **por acidente**: os handoffs colidiam de id com session logs.

## O recibo do defeito

Range `B=3bc92f1264` → `H=3ea0000c8c` (400 commits):

| pathspec | handoffs | sessions |
|---|---:|---:|
| cru `memory/<p>/**/*.md` | **0** | **0** |
| `:(glob)memory/<p>/**/*.md` | 19 | 42 |
| pasta simples `memory/<p>/` | 19 | 42 |

O de SPEC escapava **por acidente**: `memory/requisitos/<Mod>/SPEC.md` tem o `<Mod>` no meio.
Medido cru 6 = glob 6 — troca no-op, feita junto só pra não deixar a forma frágil de pé.

Varredura do repo (`rg --hidden`, obrigatório): só este arquivo tinha a forma cega. Os
`prototipo-ui/**` e `resources/js/Pages/**` do `design-memory-gate` medem **igual** nas duas
formas (636=636, 249=249) porque terminam em `**`, não em `**/<arquivo>`. E
`resources/js/Pages/**/*.tsx` dá 83=83 porque ali **sempre** há um `<Mod>/` no meio: **o defeito
só se manifesta em pasta plana.**

## O FP medido antes de armar

| população | reprova | taxa |
|---|---:|---:|
| handoffs novos (`--diff-filter=A`) | 0 / 19 | 0% |
| sessions novas/modificadas (`AM`) | 36 / 43 | 83,7% |

Os 36 são reprovação **legítima**, não falso-positivo: o corpus usa `## O pedido` / `## A
medição`, e o schema exige `## TL;DR` / `## Resumo` / `## Contexto` — que é o que o próprio
`memory/sessions/_TEMPLATE.md` prescreve. A prática drifou porque o gate estava cego. **24 dos
36 vêm de um único gerador** (`refutacao-gt-g5-*`), com chip aberto.

Nenhum dos dois jobs é required (união `classic_protection` + `rulesets` = 46 contexts), então o
conserto **já nasce advisory por construção** — sem precisar de `continue-on-error`, que a §5
2026-07-09 proíbe justamente pra advisory poder ficar vermelho.

## A medição que mudou o desenho

No segundo PR eu ia remover o `|| true`, que era o conserto óbvio. Medido em sandbox com
`bash -e`, o `|| true` cobria **duas** coisas:

| caso | com `\|\| true` | sem (cru) | conserto |
|---|:--:|:--:|:--:|
| diff ok, com arquivos | rc=0 | rc=0 | rc=0 |
| diff ok, **0 arquivos** | rc=0 | **rc=1** | rc=0 |
| **diff falha** | **rc=0** | rc=1 | **rc=128** |

Remover deixaria **vermelho o caso mais comum** — o `grep` sai 1 quando não casa nada, e a
maioria dos PRs não toca `memory/sessions`. Teria trocado um gate mudo por um gate que reclama
em todo PR. O conserto separa: `git diff` roda sozinho (falha ⇒ `bash -e` mata o step) e o
`|| true` fica só no pipeline de `grep`, onde vazio é legítimo.

## O bite-test, e por que 3 pernas

`scripts/tests/memory-schema-detect.test.mjs`, wirado no job `selftest` que já existia
(estender o dono, não abrir paralelo):

1. **Detecção** — lê o pathspec **do YAML** e o **executa** contra um repo sintético de pasta
   plana. Conferir a presença da string `:(glob)` seria presence-gate (LC-11).
2. **Mordida** — o validador reprova o ruim e libera o bom.
3. **rc do diff** — extrai o **pipeline** do YAML e o executa com BASE/HEAD reais, pinando os
   três casos da tabela acima.

Sem a perna 1, reverter o pathspec deixaria o gate mudo com o teste verde (LC-15). Sem a 3, o
`|| true` voltaria. Cada perna teve controle negativo rodado: revertido o `:(glob)` →
`acha 0 de 3`; reintroduzido o `|| true` → `diff FALHA -> rc=0`. 17/17 com os consertos.

## Aprendizados / pegadinhas

- **A primeira medição de FP estava errada e quase virou conclusão.** Deu 5/19 handoffs
  reprovando "sem `tldr`" — os arquivos *tinham* `tldr`. Era `UnicodeEncodeError` cp1252 do
  Windows no `print` do validador. Com `PYTHONIOENCODING=utf-8` (o default do runner): 0/19.
  O que denunciou foi cruzar com o job AJV irmão, que exige `tldr` e **enxerga** — se ele
  passasse, a acusação do outro não podia ser verdadeira. (§5 2026-08-07.)
- **LC-26 duas vezes**, com naturezas opostas: o colapso do par de barra invertida foi ruidoso
  (`SyntaxError` na hora); o erro seguinte, já com `chr(92)`, foi **silencioso** — aspas
  desbalanceadas com o script imprimindo "3 sites consertados". Recibo no ledger.
- **`bash -n` com controle positivo**: quatro `rc=0` não provariam nada sem a fixture quebrada
  devolvendo `rc=2`.
- **`gh pr merge --delete-branch` falhou no cleanup local** (`'main' is already used by worktree`)
  **depois** de o merge já ter ocorrido pela API. A mensagem de erro não é o veredito.
- **Falha transitória de CI é do runner, não do conserto**: o push em `main` do #7182 deu
  `failure` por `ETXTBSY` no `esbuild` durante `npm install`, num job que eu não toquei e que
  morreu antes de qualquer lógica. Re-rodado: success.

## Próximos passos (não-bloqueante)

- O gerador `refutacao-gt-g5-*` (24 dos 36 vermelhos previstos) — chip iniciado em sessão
  separada.
- Os 12 session logs escritos à mão que reprovam não têm dívida acionável: cada autor ajusta ao
  criar o próximo. Este arquivo já nasce conforme.
