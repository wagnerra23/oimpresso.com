---
date: "2026-08-03"
time: "15:52 BRT"
slug: "tutorial-sdd-guiado"
tldr: "O SDD deixou de ser só referência: /documentacao ganhou tutorial ponta a ponta e a própria máquina passou a ensinar o fluxo por feature:tutorial/--help."
decided_by: [W]
cycle: null
us: []
next_steps: ["Commitar e integrar após autorização humana."]
hour: "15:52 BRT"
topic: "Tutorial guiado para o trio SDD"
authors: [C]
prs: []
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Handoff — tutorial SDD guiado

## Entrega

O §B7.1 do `GUIA-DO-SISTEMA.md`, renderizado em `/documentacao`, passou a acompanhar uma feature da decisão de usar o trio até a âncora final. A máquina ganhou `--help` e o alias `feature:tutorial`; os três templates agora dizem explicitamente “gerado pela máquina, nunca copiar”.

## Prova e estado

`feature:tutorial` e `feature:init -- --help` exibiram a receita; self-test passou 35/35; dry-run real listou somente o trio e não escreveu; lint full-tree terminou com 2 features, 0 erros e 0 avisos; diff-check passou. Nenhum PHP/runtime/ADR foi tocado. MCP não estava disponível nesta sessão; fallback por canon git. Sem commit, push ou PR.
