---
date: "2026-07-30"
time: "08:04 BRT"
slug: "documentation-loop-primeiro-recibo-real"
tldr: "O primeiro uso real do ciclo documental fechou o ID memory-health:link-quebrado:131e73bd28b3 e reduziu os links quebrados 28→26. A rodada também corrigiu o falso vazio do mapa pré-commit: worktree rastreado/não rastreado passa a entrar no impacto e --require-clean cobra o recibo final no commit."
decided_by: [W]
cycle: null
prs: []
us: []
next_steps:
  - "Abrir o PR com trailer Documentation-Receipt: memory-health:link-quebrado:131e73bd28b3 e aguardar todo o CI."
  - "Na primeira run pós-merge, confirmar no snapshot da main que o mesmo ID segue ausente."
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
---

# Handoff 2026-07-30 08:04 BRT — primeiro recibo documental real

## TL;DR

O ciclo documental foi executado sobre a `main` real. O alvo histórico existia apenas no commit
`586a3bca09`, então as duas ocorrências do link morto no índice vivo foram re-apontadas para o blob
imutável. O mesmo ID desapareceu e o detector dono caiu de 28 para 26 links quebrados.

A execução também achou uma falha no próprio instrumento: antes do commit, `--impact-ref
origin/main --head-ref HEAD` ignorava o worktree e devolvia `changed_files: []`. O dono foi estendido
para incluir alterações rastreadas e não rastreadas e para reprovar o recibo final sujo com
`--require-clean`.

## Provas

- `documentation-loop --selftest`: **10/10**.
- Bite pré-commit: `--require-clean` saiu **1** com os arquivos pendentes.
- Recibo semântico: `ok: true`, `missing_expected: []`, ID em `resolved`.
- `deadlink-gate`: **1096/1098**, um arquivo melhorou, nenhuma piora.
- `module-surface --all --check`: 39 módulos + `_Geral` sem drift.

## Limite honesto

O campo `introduced` revelou dois links históricos que já estavam fora da janela de amostra do
`memory-health`; não há evidência de que nasceram neste diff. Não foram tratados como regressão nem
quitados em massa.

## Estado MCP no momento do fechamento

- `brief-fetch`: indisponível nesta sessão; a busca de tools não expôs o MCP oimpresso.
- `cycles-active`, `my-work`, `sessions-recent`, `decisions-search`, `whats-active`: não medidos pelo
  mesmo motivo. Foi usado o fallback documental (`CLAUDE.md`, `memory/08-handoff.md`, sessão mais
  recente e `memory/proibicoes.md`).
- Checkout principal não foi tocado: estava 14 commits atrás e continha `.worktrees/` e
  `grep.exe.stackdump` não rastreados.

## Referências

- Session log: [2026-07-30-documentation-loop-primeiro-recibo-real.md](../sessions/2026-07-30-documentation-loop-primeiro-recibo-real.md)
- Handoff anterior: [2026-07-29-documentation-impact-loop.md](2026-07-29-documentation-impact-loop.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
