---
date: "2026-08-20"
topic: "Gate visual — o comentário de falha passa a nomear o step que reprovou, em vez de escolher a narrativa plausível"
authors: [W, C]
outcomes:
  - "PR #6036 mergeado: a narrativa do comentário vira MEDIDA (4 modos), com o step que reprovou nomeado"
  - "O predicado de scope deixa de ter 2 donos — o comentário passa a usar o MESMO de validateExecution()"
  - "Causa não determinável virou estado explícito: o gate diz que não sabe, sem calar nem inventar"
  - "Dois erros meus de LC-08 registrados com errata visível; nenhum chegou a prod"
prs: [6036]
related_adrs:
  - 0108-regressao-visual-pest-browser-tier-2
  - 0344-two-strikes-cobre-processo
---

# Gate visual — a narrativa do comentário de falha

## O defeito

O step `Comment on PR if regression detected` escolhia o corpo por UM sinal — `uncovered_screens`
não vazio — ignorando o `scope`. Em `scope: global` essa lista quase nunca é vazia (o classificador
escala ~todos os consumidores do componente compartilhado), então **toda** falha do job virava
*"Tela sem contrato visual"*. O `if:` é `failure()` genérico: dispara quando qualquer coisa falhou.

Caso medido — [#5976](https://github.com/wagnerra23/oimpresso.com/pull/5976) · job 96505067528:

| o comentário disse | o job mediu |
|---|---|
| "Tela sem contrato visual", 8 telas | único step não-success: **#24 Pixel-diff** |
| — | motivo: **ZONA CINZA** (0,8261% · 0,7743% · 0,1030%; limiares 0,1%..2%) |
| — | `PixelBaselineTest`: **21 passed, 219 assertions** |

Custo real: levou a planejar 8 baselines, **duas impossíveis** — `Financeiro/Dashboard` tem rota
301 (Pages dormentes, `Modules/Financeiro/Routes/web.php:66` e `:215`) e `Cliente/Show` só
renderiza atrás da flag `cliente_show` (rollback de canary,
[ADR 0179](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)).

## O conserto — estender o dono, não abrir régua

A regra já era canon desde 2026-07-16, escrita no env do `Canário anti-verde-vazio` e implementada
em `validateExecution()`: *targeted ⇒ a lista É a cobrança; global ⇒ o pixel roda o núcleo-6 e a
lista é raio informativo*. O comentário era o **único consumidor que não a respeitava** — por isso o
modo novo `--explain-failure` nasceu no mesmo arquivo, com o mesmo predicado, e não como script novo.

| modo | quando | o que diz |
|---|---|---|
| `sem-contrato` | targeted **e** uncovered não-vazio | a narrativa original — a mordida não sumiu |
| `zona-cinza` | pixel-diff falhou e publicou a faixa do meio | tabela **tela × ratio medido** |
| `step-nomeado` | há step em failure, sem zona cinza | lista as causas possíveis, **não escolhe uma** |
| `indeterminado` | nenhum step instrumentado em failure | **diz que não sabe** + link do run |

Para o `zona-cinza` existir, o `PixelBaselineTest` passou a publicar `gray=` no `GITHUB_OUTPUT` — no
mesmo `afterAll` que já publicava `expected/executed/compared`, **antes** do `writeGrayZoneSummary()`,
que lança quando a zona cinza bloqueia.

## Provas

- **Bite-test**: a reprodução vive no `--selftest` com os valores do run real. Predicado antigo →
  `AssertionError ... actual: 'sem-contrato'`, exit 1; com o fix, exit 0.
- **Dry-run end-to-end**: env + `run` montados a partir do YAML parseado, com os outcomes reais do
  job 96505067528 → reproduz `0.8261% / 0.7743% / 0.1030%` e não cobra as 8 telas.
- **Todos os modos que o CI invoca** foram rodados, não só o novo: `--selftest` · `--assert-execution`
  · `--base/--head/--github-output` · `--explain-failure`.
- **Prova viva**: o próprio PR caiu no mesmo modo de falha e o comentário saiu correto. No run verde
  seguinte, os 2 steps novos ficaram `skipped` — controle negativo (sem falha, não comentam).

## Efeito colateral saudável

Saíram do comentário dois blocos que afirmavam enforcement **em tempo presente** (*"Esse check é
REQUIRED"*, *"Segue advisory: L5 perf"*) — a forma banida por LC-10. O dono de "o que é required" é
`governance/required-checks-baseline.json`. Também morreu um número podre: o texto dizia *"regenera
os ~68"* e são **21** (manifesto = 21 = snaps do `PixelBaselineTest`; 74 é o total de snaps Browser).

## Os dois erros meus (LC-08 → 106)

1. **Sondei o git com a mudança não commitada.** Rodei o classificador com os 3 arquivos ainda no
   working tree e escrevi no PR que o gate concluiria por skip-as-pass. Ele diffa COMMITS: mediu uma
   árvore sem elas. O real era `visual_required=true` · `scope=global`. Pego pelo CI.
2. **Previ "mergeiam em qualquer ordem"** para este PR e o #6033. O trabalho era disjunto (medido e
   confirmado), mas os dois anexavam selftest no MESMO ponto de inserção — conflito textual garantido.
   Pego pelo `git merge`. Resolvido de forma aditiva, com bite-test dos dois lados.

Lápide em [licoes-rejeitadas.md](../licoes-rejeitadas.md) (2026-08-20, *"Sondar o git com a mudança
AINDA NÃO COMMITADA"*); §5 derivado regenerado (135 limites, 0 perdidos). Nenhuma das duas chegou a
prod, então nenhuma virou máquina — [ADR 0344](../decisions/0344-two-strikes-cobre-processo.md).

## Nota de processo

O MCP do oimpresso **não estava conectado** nesta sessão, então o `whats-active` não pôde ser rodado.
Substituí pelo equivalente no GitHub: varredura de todos os PRs abertos por quem tocava
`visual-regression.yml` (nenhum). Isso **não** cobre sessão viva sem PR aberto — limitação declarada,
não suprimida. O conflito com o #6033 apareceu justamente porque ele mergeou no intervalo.
