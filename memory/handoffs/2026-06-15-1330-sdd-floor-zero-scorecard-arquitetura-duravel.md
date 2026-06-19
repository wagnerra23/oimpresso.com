---
date: "2026-06-15"
hour: "13:30 BRT"
topic: "SDD floor: corruptores era-sqlite 67→0 + linter honesto (dual-mode) + scorecard adversarial 61.9 + 2 proposals (medir→governar, arquitetura durável multi-IA) + ledger firme de conclusão"
duration: "~6h (épico, off-cycle)"
authors: [W, C]
---

# Handoff — SDD floor zerado (corruptores) + scorecard adversarial + arquitetura durável

> **TL;DR:** [W] "do plano sdd: cauda longa ~1078, triagem real por arquivo" → virou um dia épico ultracode. **Corruptores era-sqlite 67→0** (guard sqlite-only, NÃO RefreshDatabase — a lane per-PR é sqlite + migrations MySQL-only) · **corruptor-linter v1→v3** (admitiu ~48% falso-positivo, reescrito por comportamento-no-MySQL + dual-mode, meta-teste 2 lados) · **scorecard SDD adversarial = 61.9/100** ("infra de garantia construída, garantia não exercida") · **2 proposals** pra [W] numerar · **ledger firme de conclusão** (7 já-feitos / 6 PR-READY / 3 decisões / 0 meio-feito). Off-cycle (CYCLE-08 = Receita).

## Estado MCP no momento
- **Cycle CYCLE-08** (Receita — Onda A, 13d restantes). Esta sessão foi **off-cycle/governança** — drift conhecido (a métrica-mãe é receita, não suite-verde).
- my-work: 30 tasks (4 review / 6 blocked-dormente Gold / 20 todo). Nada desta sessão entrou no cycle.

## O que aconteceu
1. **Burn-down corruptores era-sqlite (Frente C do floor): 67→0.** Guard sqlite-only (`markTestSkipped` não-sqlite + teardown guard) em 4 Governance afterEach + 20 Whatsapp + 28 Jana/Mcp/Copiloto + 9 genuínos. **Correção-chave (refutada na execução):** NÃO converter pra RefreshDatabase — a lane per-PR roda em **sqlite** e as migrations são **MySQL-only** (`ci.yml`), converter quebraria o gate. Guard mantém cobertura sqlite + para a corrupção no nightly.
2. **corruptor-linter honesto (v2/v3):** v1 tinha ~48% falso-positivo (text-match). v2 = comportamento-no-MySQL (só DROP real em código, detecta drop-por-variável, afterEach-sem-guarda). v3 = dual-mode `if(===sqlite){drop}` com polaridade. Meta-teste `tests/sqliteCorruptors.spec.ts` (sensibilidade+especificidade), advisory no umbrella. **Refutador pegou sub-contagem minha 2× (variable-drop, flag-var $isSqliteMemory) — corrigido antes de mergear.**
3. **Scorecard SDD adversarial (sdd-avaliar, 8 agents): 61.9/100.** GT 85 · FV 81 · CH 73 · F2b 62 · KL 58 · SA 42 · PROM 16. Veredito: tudo advisory, nada armado, métricas-mãe (full_suite/floor) não-governadas.
4. **2 proposals** (workflows de pesquisa profunda + refutação): **medir→governar** (transporte CT100→scorecard) + **arquitetura durável multi-IA anti-vazamento** (lease + reconciler + gate-MCP; refutador: gate é CONSTRUIR não "ligar"; SPOF = watcher per-máquina).
5. **Ledger firme de conclusão** (2 frentes paralelas, 29 agents): 7 bugs JÁ FEITOS (verificados em main — adversário evitou re-trabalho), 6 PR-READY com spec cold, 3 decisões Wagner.

## Artefatos gerados (todos mergeados salvo nota)
- **Floor:** #2746 (Governance afterEach ×4) · #2749 (linter v2+meta-teste) · #2753 (Whatsapp ×20) · #2756 (Jana/Mcp ×28) · #2758 (linter v3 dual-mode) · #2759 (9 genuínos) → **corruptor floor 67→0**.
- **Scorecard:** #2764 (`sessions/2026-06-15-sdd-avaliacao-adversarial-scorecard.md`).
- **Proposals:** #2765 (`proposals/sdd-medir-governar-floor-nightly.md`, OPEN) · #2766 (`proposals/arquitetura-rede-ia-duravel-anti-vazamento.md`).
- **Ledger:** #2767 (`sessions/2026-06-15-sdd-conclusao-ledger-firme.md`) + este handoff.

## Persistência
- git: PRs acima (webhook GitHub→MCP ~2min). MCP: nenhuma task tocada (off-cycle).
- **Worktree `sdd-triage-main`** (off origin/main, full-checkout) criado pra rodar Pest/linter — **remover (`git worktree remove`) quando os PRs assentarem.** O worktree-sessão `frosty-greider` é o órfão M3.

## Próximos passos pra retomar (decisões D1-D5 JÁ tomadas por [W] "escolha as melhores e faça")
1. **Aplicar os 6 PR-READY** do ledger (`sessions/2026-06-15-sdd-conclusao-ledger-firme.md` §A-§F): unificar anchor_coverage (D3 ✅), re-armar ratchets, regenerar scorecard.json stale, ledger-check advisory, drift doc↔código US-GOV-018, fixtures NumUf (valor a confirmar). Todos mecânicos, verificáveis sem Pest.
2. **D1 ✅ aprovado — construir a versão MÍNIMA da arquitetura** (proposal #2766): `mcp_work_leases` `UNIQUE(task_id)` + TTL/heartbeat + carimbo `agent_id+human_principal` + wire `whats-active` no SessionStart + check "estou-ingerido?". **Sessão fresca** (mexe na camada canônica MCP — "onde NÃO inventar").
3. **D2 ✅** read-side `full_suite` com fallback honesto + matar comentário falso `sdd-scorecard.mjs:112`.
4. **D4/D5** (precisam Pest): FeedbackRelevance = corrigir LÓGICA (ADR 0195, não relaxar teste); ContactObserver = checar estático cacheKey-DDI (provável typo).
5. **Gargalo do floor-verde:** consertar o **harness da nightly** (imagem CT100 sem binário `mysql`; floor não-determinístico 1514–2197) + ≥2 runs limpos → interseção. **Nenhum deliverable codável espera por isso.**
6. **NÃO promover** nada a required (PROM=16) até medir→governar + ≥2 medições honestas.

## Lições catalogadas
- **CONVERT vs GUARD:** a lane per-PR é sqlite + migrations MySQL-only → RefreshDatabase quebra o gate; o fix de corruptor era-sqlite é GUARD sqlite-only, não convert. (Refutou a triagem inicial.)
- **A medição mente:** corruptor-linter v1 ~48% FP; scorecard hardcoda `full_suite=notYet` com comentário falso. "A suite mente" vale pros próprios instrumentos.
- **Adversário evita re-trabalho:** 7 "tarefas" estavam feitas; sem refutar, re-faríamos. O vazamento é real.
- **Arquitetura:** ~80% do SOTA já existe (MCP/git-canon/scorecard/gates) — gap é mecanismo, não ferramenta. NÃO trazer Temporal/CRDT/A2A/GraphRAG/Letta. Cuidado: "ligar o gate" é mentira de custo (ActionGate é HTTP middleware a 0 rotas; tools MCP não passam por ele = construir).

## Pointers detalhados (on-demand, NÃO duplicar)
- Ledger firme + specs §A-F: `sessions/2026-06-15-sdd-conclusao-ledger-firme.md`
- Scorecard 7 streams: `sessions/2026-06-15-sdd-avaliacao-adversarial-scorecard.md`
- Triagem refutada (CONVERT→GUARD): `sessions/2026-06-15-sdd-longtail-triage-refuted.md`
- Arquitetura durável: `decisions/proposals/arquitetura-rede-ia-duravel-anti-vazamento.md`
