---
date: "2026-07-04"
time: "22:30 UTC"
slug: consolidacao-conhecimento-4-maquinas
decided_by: [W]
tldr: "Guia do sistema + faxina da camada de entrada + 4 máquinas anti-drift novas (Check S/T/U/V no memory-health) + 32 links quebrados consertados. Drift invisível virou drift auditável. 10 PRs. Backlog restante é julgamento humano."
---

# Handoff 2026-07-04 22:30 — Consolidação de conhecimento + 4 máquinas

## O que aconteceu
Wagner pediu "um guia/mapa do sistema" → virou sessão de **governança de conhecimento**: criar a porta de entrada, limpar o drift acumulado, e **construir as máquinas** que impedem o drift de voltar. Fechado com comparação vs estado-da-arte 2026 (agente `estado-da-arte`).

## Entregue — 10 PRs (9 merged + #3806 auto-pendente)
| PR | O quê |
|---|---|
| #3795 | `memory/GUIA-DO-SISTEMA.md` — porta de entrada humana (produto + como operar com Claude Code) |
| #3797 | Esquece `memory/memory_backup/` (32 arquivos de backup morto) |
| #3798 | README fresco (React 18→19, MemCofre→SRS, testes→CT100, ADRs→índice gerado) |
| #3800 | Esquece `05-preferences` (única contradição ativa; 6 NN ficam banner-quarentenados) |
| #3799 | **Check S** frescor da camada de entrada |
| #3802 | **Check T** fact-anchor + **Check U** limbo + fix `Modules/Project`→`ProjectMgmt` |
| #3803 | **Check V** links internos quebrados (guard SOTA lychee) |
| #3804 | +23 links de ADR consertados (slug antigo→real) |
| #3806 | +9 links por basename único (Check V: 47→15) |
| #3805 | Session log da consolidação |

## As 4 máquinas (advisory, em `scripts/governance/memory-health.mjs`, com teste físico ADR 0258)
- **Check S** `entrada-stale` — doc de entrada sem revisão >6 meses.
- **Check T** `fato-ancora-drift` — versão/módulo em doc current-state contradizendo `package.json`/`composer.json`/árvore. Escopado só a docs current-state (evita FP de menção histórica).
- **Check U** `proposta-em-limbo`+`dir-homonimo` — pile de `proposals/` (contagem) + `dominio/`÷`dominios/`.
- **Check V** `link-quebrado` — links internos quebrados na canon (determinístico, zero-FP).

## Backlog exposto (precisa de JULGAMENTO — não bulk, lição do red-team)
1. **15 links quebrados** restantes (alvo deletado/movido pra fora do repo ou basename ambíguo tipo `SKILL.md` 72 cand) — recriar/repontar/remover caso-a-caso.
2. **90 drafts** em `decisions/proposals/` — triar (promover/arquivar/esquecer, ADR 0316).
3. **`dominio/`÷`dominios/`** — rename de desambiguação (raio 425 arquivos; qual nome ganha = decisão Wagner).
4. **6 NN banner-quarentenados** (01/02/03/04/07/09) — história honesta; esquecer só se Wagner quiser.
5. 221 scorecards / 42 sessões sem âncora / 16 planos sem Status vivo / 12 ADRs vencidas — julgamento ou CT100.

A máquina agora **conta e nomeia** tudo isso a cada PR (Check B/J/K/O/R/S/T/U/V). Consertar é triagem humana.

## Próximo passo sugerido
O automatizável-seguro acabou. Retomar pela triagem do backlog (o item mais barato: os 15 links residuais ou o rename `dominio`). O `estado-da-arte` recomendou não construir contradição-N²-geral (teatro) nem memória auto-consolidante (git+supersede já é isso).

## Lições
- Esquecimento = decisão consciente e do Wagner (ADR 0316) — red-team rejeitou bulk-delete dos 7 NN; só a contradição ativa morreu.
- Fact-anchor de prosa tem limite (claim atual vs citação histórica não é 100% determinístico) → advisory.
- Idade por git-date é mascarada pelo squash-restore #2413 → sinal honesto = contagem.
- Máquina não conserta, torna auditável.

## Estado MCP no momento do fechamento
- **cycles-active** (COPI): nenhum cycle ATIVO. Sessão off-cycle (governança de conhecimento, não task de produto).
- **my-work** (@wagner): 30 tasks ativas — backlog real do produto (8 REVIEW, 8 BLOCKED incl. NFe Gold dormentes, 14 TODO incl. P0 FSM rollout/RecurringBilling/OficinaAuto outreach/Compras Tier-0). **Nenhuma tocada nesta sessão** (governança pura).
- **decisions-search**: sessão não criou ADR nova (só checks/docs); referencia 0130/0256/0264/0270/0275/0314/0316/0317/0258 existentes.
- **Doc de comparação SOTA**: `memory/sessions/2026-07-04-arte-governanca-conhecimento-fato-vs-frescor.md`.
