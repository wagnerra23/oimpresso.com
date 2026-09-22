---
date: "2026-09-21"
time: "14:31 BRT"
slug: design-pipeline-sem-falso-verde
tldr: "A cadeia de importação deixou de chamar CI de produção: 3 telas foram reclassificadas para smoked-ci e validated caiu a zero. O mapa aposentado que gerava 13 vermelhos permanentes saiu do conjunto ativo, o drift do DS ficou sem baseline e dez workflows voltaram a rodar no synchronize."
prs: []
decided_by: [W]
related_adrs:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0390-emenda-0384-smoke-em-ambiente-controlado
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
  - 0410-ratificacao-zero-baseline-no-funil-design
next_steps:
  - "Publicar a branch e acompanhar o CI"
  - "Executar smoke autenticado em produção para as três telas Fiscal quando o SHA estiver implantado"
---

# Funil de design sem falso verde

## Resultado

- Estado real: 164 pares de tela; 63 `compared`, 70 `anchored`, 28 `to-create`,
  3 `smoked-ci` e 0 `validated`.
- `validated` exige produção e cadeia de hashes completa por tela.
- 13 acusações permanentes do mapa ModuleGrades aposentado viraram zero sem anistia.
- O baseline de `ds-mirror-drift` foi apagado; drift aceitável é zero.
- Dez workflows do funil reexecutam no SHA novo do PR.
- `design-code-map-check` e `ds-mirror-drift` publicam falha real e entram no medidor de
  mordidas já existente.

## Evidência

As suítes Node específicas, o strict do mapa, o scan do medidor, as 41 catracas do
`gate-selftest`, o inventário de 606 máquinas e `git diff --check` passaram. A prova de
produção ainda não existe; esse é agora um estado explícito, não um verde herdado do CI.

## Estado MCP no momento do fechamento

As tools MCP de ciclos, tarefas, sessões e decisões (`cycles-active`, `my-work`,
`sessions-recent`, `decisions-search` e `whats-active`) não estavam disponíveis nesta sessão
do Codex. O fechamento foi conferido diretamente no Git, nos scripts de governança e nos
checks da PR; não foi fabricado snapshot MCP substituto.
