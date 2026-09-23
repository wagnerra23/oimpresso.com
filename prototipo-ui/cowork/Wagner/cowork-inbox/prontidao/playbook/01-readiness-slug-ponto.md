---
sessao: "01"
titulo: prototipo-readiness: slug do scorecard não troca `.` por `-` (kb/Index.v2) (abrir com `/onda prontidao --thread 01`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: scripts/qa/prototipo-readiness.mjs · scripts/qa/prototipo-readiness.test.mjs
nao_toca: memory/governance/scorecards/ · memory/governance/prototipo-readiness.json
---
# 01 · slug com ponto

## O problema, medido
`scorecardSlug()` faz `.replace(/[\\/]/g, '-')`: troca barra por hífen, mas **mantém o ponto**. `kb/Index.v2` vira `kb-index.v2`, e o arquivo real é `memory/governance/scorecards/screens/kb-index-v2.yaml` (4.218 B). O readiness acusa "falta scorecard" numa tela que já tem.

## O que muda
Incluir o `.` na classe: `/[\\/.]/g`. Uma linha.

## Bite-test
- **BITE:** `Pages/kb/Index.v2.tsx` → `kb-index-v2`.
- **CONTROLE:** `Pages/Essentials/Todo/Index.tsx` → `essentials-todo-index`, igual a antes.

## PARAR SE
- Algum outro scorecard hoje reconhecido deixar de casar (rodar o relatório antes e depois e comparar a contagem de prontas: **59**). Se cair, parar e reportar.
- O slug for compartilhado com `screen-grade-seed`/vital-signs (o comentário diz "mesma convenção"). Nesse caso, o conserto é na fonte única, não aqui: parar e apontar onde.

## Prova
- o regex novo presente · teste verde · `_saida-01.md` com o relatório antes/depois (kb/Index.v2 sai de 1-ciclo, prontas 59 → 60)
