---
date: "2026-06-24"
time: "00:36 BRT"
slug: ancora-improvada-spec-vivo-gate-de-entrada
tldr: "Programa spec-vivo de endurecer a âncora spec↔código: G1a covers + G1b gate de entrada (advisory) + reconciliação NfeBrasil + lane MySQL fiscal (12 fails reais que o sqlite mascarava) + fato dos ~150 clientes Delphi. 16 PRs mergeados. 3 chips spawnados pra continuar."
topic: "Programa âncora-improvada (spec-vivo): gate de entrada que exige aceite+teste, sob a escala real de ~150 clientes Delphi"
cycle: CYCLE-08
duration: "épica (16 PRs)"
authors: [C]
---

# Handoff — âncora improvada / gate de entrada (spec-vivo)

## Estado MCP no momento
- Cycle ativo: **CYCLE-08** (Receita — Onda A, monetizar a carteira legacy).
- **16 PRs mergeados** nesta sessão + 1 em voo (#3316, lane MySQL fiscal — descoberta rodando).
- 3 tarefas spawnadas como chips (forward-handoff) + 1 ADR-proposta aguardando ratificação Wagner.

## O que aconteceu
Começou como *"testar importar o protótipo"* (bundle Cowork ComVis, 1186 arquivos) → o import revelou que `vendas` só tinha charter, e a branch `feat/vendas-link-caixa-do-dia` estava **161 commits atrás do main** (worktree órfã `frosty-greider` compartilhada por ~24 sessões). Daí virou um **programa de endurecer a âncora spec↔código** (Wagner: *"boas âncoras, bom sistema"* + *"o código não pode ser feito e refeito por cada pessoa"*). Pesquisa (workflows de 14 e 9 agentes) → implementação faseada → reabertura sob a premissa corrigida (~150 clientes, não 1).

## Artefatos / PRs (todos mergeados salvo #3316)
- **Salvamento da branch stale:** skill+RUNBOOK `aplicar-prototipo` (#3301) · cmd Jana `mcp:tasks:unassigned` (#3302) · 9 docs de pesquisa (#3303).
- **Pesquisa da âncora:** estado-da-arte (#3306) · benchmark-com-nota + teste-de-fogo nota fiscal (#3307) · errata 0273/0303 (#3308) · design final (#3309) · re-eval robustez (#3314).
- **Fato canônico:** ~150 clientes Delphi migrando pro online (#3313) — corrige a premissa "cliente único".
- **Implementação:** G1a covers-check `--check-covers` (#3310) · emenda ADR 0303 SA-A2-ter (proposta #3311) · reconciliação NfeBrasil 13 dead_tests→0 (#3312) · **G1b gate de entrada** `--check-entry` (req_sem_aceite + req_sem_covering_test) (#3315) · lane MySQL fiscal advisory (#3316, em voo).

## Persistência
- **git:** tudo no main (webhook → MCP ~2min). `scripts/governance/anchor-lint.mjs` ganhou `--check-covers` + `--check-entry`; fixtures + catracas no `gate-selftest.mjs` (26/26).
- **Docs canon:** `memory/sessions/2026-06-23-{ancora-improvada-design-final, robustez-anchor-reavaliada-150-clientes, arte-ancora-*}.md` · `memory/reference/escala-migracao-carteira-delphi.md` · `memory/decisions/proposals/2026-06-23-anchor-covers-check-sa-a2-ter.md`.

## Próximos passos pra retomar
3 chips spawnados (1 sessão por tarefa, pedido do Wagner): **(1)** verde@ Phase B (`anchor-lint --junit` lê o JUnit do lane fiscal) · **(2)** NfeBrasil lane: descoberta (#3316) + ratchet do allowlist nos verdes · **(3)** armar os gates advisory→required (baseline grandfather + adoção `@covers-us` + clique do Wagner, ADR 0275). + A emenda ADR 0303 (#3311) aguarda `aceito` textual do Wagner.

## Lições catalogadas
- **Anti-stale reincidente (a lição-mãe da sessão):** `D:\oimpresso.com` estava 161 commits atrás; `git cat-file/show <rev>:path` mente por cwd-scope nessa estrutura. SEMPRE checkout limpo off `origin/main`, cite file:line. Vários agentes/eu caímos nisso e o adversário corrigiu.
- **Premissa errada vira design errado:** "cliente único (Larissa)" enviesou o design pra "não vale robustez"; são ~150 clientes fiscais — o cost/benefit inverte em 1,5 gaps.
- **Anchor-rot recursivo:** o avaliador do workflow ancorou num `_pendente_` stale (US-NFE-002:52) — a própria mentira que o sistema combate; só o adversário pegou.
- **Auto-merge + edição pós-merge orfana o commit** → cherry-pick numa branch nova off main (incidente errata, recuperado).

## Pointers detalhados (on-demand)
- Design completo: `memory/sessions/2026-06-23-ancora-improvada-design-final.md`
- Re-eval sob 150 clientes: `memory/sessions/2026-06-23-robustez-anchor-reavaliada-150-clientes.md`
- Gate: `scripts/governance/anchor-lint.mjs` (`--check-covers` G1a · `--check-entry` G1b)
