---
date: '2026-07-03'
topic: "Onda 1.1 Sells — materializar os gaps da CAPTERRA-FICHA em backlog MCP (4 US novas) + repriorizar US-SELL-040 pra P0 e remover o gate de refactor"
authors: [C]
related_adrs:
  - 0089-capterra-driven-module-evolution
  - 0101-tests-business-id-1-nunca-cliente
  - 0284-pipeline-incidente-graduado-confianca
prs: [3702, 3704]
---

# Session — Onda 1.1 Sells: backlog materializado (2026-07-03)

## TL;DR

Continuação da Onda 1.1 (a `CAPTERRA-FICHA.md` de capacidade do Sells já tinha sido gerada e mergeada em #3699, nota 60/100). Aqui, a pedido do Wagner ("faça tudo"), transformei os 6 gaps da ficha em backlog rastreável — **sem duplicar** (regra Tier 0): criei 4 US novas (offline IndexedDB, Pix QR+webhook, keyboard-first, skeleton+INP) via `tasks-create` MCP (#3702, mergeado) e comentei DB-only nas 2 que já existiam (G-01→US-SELL-040, G-03→US-SELL-041). Depois, ao abrir a US-SELL-040, achei que o teste E2E de correção de cálculo estava **p2 e travado atrás de um gate de refactor** — o mesmo gate que deixou o incidente R$×100k (2026-06-05) passar — e o corrigi pra **P0 + sem gate** (#3704, verde, aguarda merge Wagner).

## O que foi feito

1. **#3702 (MERGED)** — 4 US novas no `Sells/SPEC.md` (IDs 054-057, sequência após 053): US-SELL-054 offline-first IndexedDB (p1), 055 Pix QR + webhook auto-reconcile (p1), 056 keyboard-first coeso (p2), 057 skeleton Create + INP<200ms (p2). Todas `parent_plan: programa-ondas-onda-1-sells`. Os blocos foram escritos pelo `tasks-create` no checkout do servidor MCP (CT100) — **não** no git — então re-materializei-os num worktree fresco de `origin/main` e landei via PR pro webhook sincronizar SPEC→DB pós-merge.
2. **Comentários DB-only** (anti-dup): US-SELL-040 (G-01, argumento P0) + US-SELL-041 (G-03, falta contingência `tpEmis=9`).
3. **#3704 (verde, aguarda merge)** — US-SELL-040 p2→**p0** + remove `blocked_by` "só quando refatorar store()". Racional escrito na própria task: invariante estrutural é tautológica (strpos, proibicoes §5); canary humano biz=1 não pega inflação; o teste E2E de valor precisa existir independente do refactor.

## Pegadinhas da sessão

- `tasks-create` escreve no checkout do **servidor MCP**, não no meu worktree nem em `origin/main` — precisei landar os blocos eu mesmo via PR (senão só existiam server-side).
- Base local −4600+ de `origin/main` → todo trabalho feito em worktree fresco de `origin/main` (guard `git-base-freshness-guard`).
- Classifier bloqueou self-merge (R10) — deixei #3704 pro Wagner mergear.
- Tier 0 valor BRL: verifiquei que nenhuma edição introduziu `R$`+dígito novo.

## Refs

- Handoff: [2026-07-03-0835-onda11-capterra-sells-backlog.md](../handoffs/2026-07-03-0835-onda11-capterra-sells-backlog.md)
- Ficha: [CAPTERRA-FICHA.md](../requisitos/Sells/CAPTERRA-FICHA.md) §6 (gaps) + §8 (adversarial)
- Log da ficha: [2026-07-02-capterra-sells.md](2026-07-02-capterra-sells.md)
