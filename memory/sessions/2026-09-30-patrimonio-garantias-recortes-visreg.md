---
date: "2026-09-30"
topic: "Patrimônio: recortes de Bens no servidor, garantia mais recente, geometria das tabelas, vazio visível no DataTable, contrato visual de Bens e exclusão de bem sem deixar garantia órfã"
authors: ["C"]
prs: [8211, 8231, 8237, 8240, 8241, 8246, 8248, 8249, 8252, 8259, 8267, 8275, 8293]
outcomes:
  - "13 PRs mergeados, cada um com teste que morde (bite-test contra main) e medição em produção quando a tela é observável"
  - "Garantia órfã em produção diagnosticada (remover() não apagava garantias), causa consertada; a linha fica para o [W] apagar com --apply"
---

## TL;DR

Sessão aberta em `/onda patrimonio --thread 12` e estendida a pedido do [W]. O estado para retomar,
os artefatos e as lições estão no handoff
[`2026-09-30-1745-patrimonio-garantias-recortes-visreg.md`](../handoffs/2026-09-30-1745-patrimonio-garantias-recortes-visreg.md);
aqui fica só o que não cabe lá.

## Como as provas foram feitas

- **Pest sempre no CT 100**, num worktree isolado em `/tmp` com `vendor/composer` e `vendor/pestphp` copiados (com symlink puro o autoload resolvia para o checkout compartilhado e rodava o código VELHO — os 4 vermelhos da 1ª tentativa vieram disso). Scripts transportados por base64 depois que escape de barra colapsou duas vezes no `ssh … sh -c`.
- **Bite-test**: trocar o arquivo pelo de `origin/main` e ver o caso cair, sempre com o conserto já commitado e hash conferido na volta.
- **Layout**: medido no DOM de produção (`getBoundingClientRect`, `getComputedStyle`), inclusive testando a técnica do vazio visível no DOM vivo antes de escrever o código.
- **Visual**: modo update só das telas afetadas (`screens` casa pelo `source`), só os `.snap` alterados levados ao PR, imagem olhada e `snap-diff.mjs` antes do commit; PRs auxiliares do bot fechados.

## O que não foi feito

- A garantia órfã (`asset_warranties.id=1`) **não foi apagada**: exclusão definitiva fica com o [W].
- BRIEFING do AssetManagement não atualizado.
- `Governance/Dashboard` diverge da evidência visual num re-run do #8275 — fora do Patrimônio, provável efeito do #8236, não investigado.
