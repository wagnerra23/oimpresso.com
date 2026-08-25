---
date: "2026-08-21"
time: "00:30"
slug: "visreg-narrativa-do-comentario-medida"
tldr: "PR #6036 mergeado por [W] (83ad1315f3, CI 106 pass): o comentário de falha do visual-regression passa a NOMEAR o step que reprovou e escolhe a narrativa pelo mesmo predicado de scope do canário, em vez de dizer 'tela sem contrato' em toda falha. Quando não dá pra saber, diz que não sabe. Tema FECHADO, sem pendência. Dois erros meus de LC-08 registrados (contador 104 -> 106) + lápide §5 nova."
decided_by: [W]
prs: [6036]
related_adrs:
  - 0108-regressao-visual-pest-browser-tier-2
  - 0344-two-strikes-cobre-processo
---

# Handoff — narrativa do comentário do gate visual

## Estado: FECHADO

[PR #6036](https://github.com/wagnerra23/oimpresso.com/pull/6036) **mergeado por [W]** em
2026-08-20 19:39Z, commit `83ad1315f3`. CI fechou **106 pass · 0 fail**. Verificado no `main`
depois do merge: os 3 arquivos intactos (164/51), `--selftest` verde rodando a partir do `main`,
e os dois blocos de selftest (o meu e o do #6033) coexistindo.

Não há trabalho pendente deste tema.

## O que mudou

O comentário de falha do `visual-regression` escolhia a narrativa por `uncovered_screens` não
vazio, **ignorando o `scope`** — e em `global` essa lista quase nunca é vazia, então toda falha
virava *"Tela sem contrato visual"*. Agora a narrativa é escolhida pelo `ui-impact.mjs`
(`--explain-failure`, mesmo predicado do `validateExecution()`) e **nomeia o step que reprovou**.

Quatro modos: `sem-contrato` (só em targeted) · `zona-cinza` (tabela tela × ratio) ·
`step-nomeado` (lista causas, não escolhe) · `indeterminado` (**diz que não sabe** + link do run).

O `PixelBaselineTest` passou a publicar `gray=` no `GITHUB_OUTPUT` para alimentar o `zona-cinza`.

Detalhe, provas e recibos: [session log](../sessions/2026-08-20-visreg-narrativa-do-comentario.md).

## O que o próximo deve saber

1. **O `--explain-failure` tem 4 modos e o CI só exercita o que falha.** Ao mexer nele, rode o
   `--selftest` (a reprodução do #5976 mora lá) e lembre que os modos `sem-contrato` e
   `indeterminado` não aparecem em run verde nenhum — só o bite-test os cobre.
2. **`gray=` é output novo do pixel-diff.** Escrito no `afterAll`, **antes** do
   `writeGrayZoneSummary()` (que lança quando a zona cinza bloqueia). Se mover a ordem, o
   `zona-cinza` para de ter dados e o comentário cai para `step-nomeado` — silenciosamente.
3. **Não restatear enforcement no comentário.** Dois blocos em tempo presente (*"Esse check é
   REQUIRED"*) saíram por LC-10. O dono é `governance/required-checks-baseline.json`.

## Dois erros meus, registrados (LC-08 → 106)

- **Sondei o git com a mudança não commitada** e afirmei no PR que o gate faria skip-as-pass. O
  classificador diffa COMMITS — mediu uma árvore sem as mudanças. Pego pelo CI.
- **Previ "mergeiam em qualquer ordem"** vs o #6033: trabalho disjunto (verdade, medido), mas
  mesmo ponto de inserção do selftest — conflito textual garantido. Pego pelo `git merge`.

Lápide nova em `memory/licoes-rejeitadas.md` (*"Sondar o git com a mudança AINDA NÃO
COMMITADA"*), §5 derivado regenerado. Nenhuma chegou a prod ⇒ nenhuma virou máquina (ADR 0344);
o par candidato, se reincidir, está descrito na própria lápide.

## Estado MCP no momento do fechamento

⚠️ **Não consultado — o servidor MCP do oimpresso não estava conectado nesta sessão.** Nenhuma
tool `oimpresso` disponível (só o hook de `brief-fetch` rodou no SessionStart). Portanto
`cycles-active`, `my-work`, `sessions-recent`, `decisions-search` e `whats-active` **não foram
executados**. Isto é ausência de medição, não estado verde — quem retomar deve rodá-los.

Substituto parcial usado para paralelismo: varredura de todos os PRs abertos via `gh` procurando
quem tocava `.github/workflows/visual-regression.yml` (nenhum, no momento da consulta). Isso
**não** cobre sessão viva sem PR aberto — e a limitação se materializou: o #6033 mergeou no
intervalo e gerou o conflito textual descrito acima.
