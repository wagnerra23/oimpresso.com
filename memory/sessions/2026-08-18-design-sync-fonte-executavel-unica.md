---
date: "2026-08-18"
hour: "11:50 BRT"
duration: "0.6h"
topic: "Consolidação da fonte executável única do DesignSync"
authors: [W, C]
outcomes:
  - "PR #5914 aberto com uma única fonte para IDs, destinos, fases e comandos"
  - "Cache cowork/_ds removido e falso-verde do check-ignore corrigido"
prs: [5914]
us: []
related_adrs: []
---

# Sessão — DesignSync com uma fonte executável

## TL;DR

[W] pediu remover arquivos conflitantes. O fluxo executável foi consolidado no
`protocolo.config.mjs`; política ficou no `PROTOCOL.md`; documentos antigos viraram ponteiros. O
runtime `_ds` passou a ter um único destino versionado e o cache local foi removido.

## Entregas

- F3 e runbook antigos reduzidos a pontes de compatibilidade;
- skill e hook sem cópia de IDs, paths ou comandos;
- selftest anti-duplicação no painel executável;
- README do snapshot corrigido para runtime completo;
- `git check-ignore` robusto em worktree com ownership diferente;
- índice de skills regenerado pelo dono.

## Validação

As suites do applier, frescor, hook, painel, integridade de design-memory e wiring do gate passaram.
O teste de frescor foi executado depois da remoção do `_ds`, provando que o verde não depende mais
de cache preexistente. Nenhum arquivo de produto foi tocado.

## Próximo passo

Revisar o PR #5914 e, com autorização explícita de [W], mesclar. O download autenticado do bundle e
das três fontes mono permanece como etapa separada.
