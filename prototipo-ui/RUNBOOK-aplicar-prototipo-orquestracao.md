# RUNBOOK — Aplicar protótipo → tela

> **Ponte de compatibilidade; não é fonte operacional.** Este arquivo continua no path antigo para
> não quebrar links históricos.

O runbook anterior repetia fases, IDs, paths e comandos que também viviam na skill, no hook e no
protocolo principal. Essas cópias divergiram com o tempo — inclusive mantendo ZIP, staging e gates
antigos ao lado do fluxo DesignSync atual.

A fonte única executável é [`protocolo.config.mjs`](protocolo.config.mjs). Ela contém os destinos,
o mapa de fases e os comandos vigentes, e seu `--selftest` impede que essas instruções voltem a ser
copiadas para esta ponte.

Política e autoridade do loop permanecem em [`PROTOCOL.md`](PROTOCOL.md). Para a mecânica de uma
tela, siga o RUNBOOK por tela apontado pelo painel executável.

## Evolução

- 2026-08-18 — runbook duplicado reduzido a ponte; execução consolidada em
  `protocolo.config.mjs`.
