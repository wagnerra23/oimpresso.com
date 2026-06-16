---
date: "2026-06-15"
time: "23:36"
slug: sdd-leva2-fechamento
tldr: "Leva 2 do SDD 8/8 no main (PRs #2794-2805) + gargalo do ci.yml morto (allowlist vira manifesto merge=union). Teto honesto ~86-88; F5 (A6/A7 hard-gates, C1 lease thread-aware) é signal-gated — adiado até o sinal do lease do A5 acumular (ADR 0105). Cleanups opcionais: re-armar foundation-ratchet baseline + remover worktrees órfãos. Retomável a frio."
cycle: CYCLE-08
prs: [2794, 2795, 2796, 2797, 2798, 2799, 2800, 2801, 2802, 2803, 2804, 2805]
related_adrs:
  - "0278-arquitetura-rede-ia-duravel-anti-vazamento"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0237-jana-reconcile-loop-unico"
---

# Handoff — SDD Leva 2 COMPLETA (8/8) + gargalo morto · retomável a frio

## TL;DR

Continuação do handoff #2793 ("continuar"). A **Leva 2 inteira entrou no main** — 8/8 itens, cada um adversário-verificado, mais os furos 1-2 da Leva 1 e o **conserto do gargalo do `ci.yml`** (a lane sqlite era 1 linha editada por toda PR → conflito garantido; virou arquivo `merge=union`, fim do rebase-train). As lanes novas **caçaram bugs reais** (não verde-de-teatro). Resto = **F5 signal-gated**, adiado de propósito (ADR 0105) até o sinal do lease (que o A5 acabou de começar a gerar) provar que colisão é real. **Teto honesto ~86-88 — cravar 90.6 não se paga.**

## Estado MCP

- **Cycle ativo:** CYCLE-08 (Receita — Onda A). Esta sessão foi **off-cycle** (governança SDD), continuação direta do handoff `2026-06-15-2000-sdd-leva1-coordenacao-duravel`.
- **Leva 2:** **8/8 no main** (+ furos #1/#2 + gargalo do ci.yml). Nada bloqueado, nada em voo.
- **Próximo passo real:** F5 (hard-gates) está **DIFERIDO** — ver seção F5. Não há trabalho de código pendente que não seja signal-gated.

## O que ENTROU no main nesta sessão (verificado)

| PR | Item Leva 2 | Entrega |
|---|---|---|
| #2794 | **Furo #1** | Lane `jana-pest` (Pest MySQL) roda `ImmutabilityTriggersTest` (#2790) — antes não rodava em lane nenhuma (falsa cobertura). |
| #2795 | **Furo #2** | Baseline module-grade TeamMcp 82→81 (aceite consciente da diluição do heartbeat #2791). |
| #2796/#2797 | **B-LIVE-CHECK** | `IngestLivenessService` (reader fresh/stale/dead de `mcp_ingest_heartbeat`); #2797 troca DTO por array shape (PHPStan + restaura TeamMcp 81). |
| #2799 | **B-R0** | `TasksReconciler` detect-only (R-A doing-órfã / R-B done-sem-acceptance_ref / R-E blocked_by-resolvido) plugado no `jana:reconcile` (ADR 0237 — sem comando novo). |
| #2798 | **A1+A8 (FSM)** | Guarda de transição em `TaskCrudService::applyLockedUpdate` — proíbe `todo→done` teleporte; matriz `McpTask::TRANSITIONS` (espelha o seeder); teste que morde. |
| #2802 | **Gargalo ci.yml** | Allowlist sqlite vira `.github/ci-sqlite-pest.list` + `.gitattributes merge=union` → PRs concorrentes não conflitam mais na lista de testes. |
| #2801 | **A4 (RBAC)** | Trait `AuthorizesMcpMutation` — gate de scope server-side nas tools mutadoras; corrige o comentário mentiroso do `McpAuthMiddleware` ("cada tool checa o scope"). |
| #2800 | **C2+C3** | `LeaseBriefSectionService` — leases ativos + nudge "claim antes de pegar" injetados sob `## EM VOO AGORA` no Daily Brief (injetor pós-LLM, 7-header validator intacto). |
| #2803 | **B-SPOF-WA** | `whats-active` injeta liveness: pipeline de ingest cego (fresh=0) → "NÃO SEI" em vez de "tudo livre" falso (bug central da dim 10). |
| #2804 | **A5** | Aviso SOFT de mutação claim-less em `TaskCrudService` (principal do token vs lease) — **instrumenta o sinal** que o A7 vai ler. |
| #2805 | **A3** | Split `jana.mcp.tasks.write` em `advance` vs `close` (gate fino no tool-layer, `.write` legado como fallback backward-safe). |

## Bugs reais que as lanes/review pegaram (prova de que não é verde-de-teatro)

1. **DEFINER de prod no schema baseline** — os 19 triggers de imutabilidade do dump (`mcp_audit_log` etc.) vinham com `DEFINER=u906587222_oimpresso@localhost` (usuário Hostinger inexistente no CI) → disparavam `HY000` ANTES do `SIGNAL 45000` → o append-only **não enforçava no CI**. A lane `jana-pest` (B-LIVE-CHECK / furo #1) expôs; consertado com strip de DEFINER no `pest-mysql-setup`.
2. **FSM quebrou `AcceptanceRefTest`** — 3 casos chegavam em `done` direto de `todo` (atalho ilegal). A guarda A1+A8 os pegou; reseman `review→done` (legal).
3. **Stub de teste do A4 sem `Authenticatable`** — a revisão adversarial pegou que `CyclesCloseToolTest`/`LgpdEsquecerTitularToolTest` injetavam `new class { can() }` que daria `TypeError` em `Request::user()`; corrigido antes do merge.

## Teto honesto (refutador) — ~86-88, NÃO 90.6

- **dim 1 (concorrência):** estaciona ~78-80 enquanto A7 não tiver sinal — e **pode ficar lá pra sempre** se o sinal disser "colisão é rara".
- **dim 6 (gate no barramento):** ~60-75 — parte do enforcement segue por skill/hook de propósito (pragmático pra 1 org).
- **dim 10 (observabilidade):** ~88 — SPOF de 1-watcher é estrutural.
- Cravar 90.6 = over-engineering pra 1 org (ADR 0105). A Leva 2 levou ao teto honesto.

## F5 — signal-gated (NÃO construir agora · ADR 0105)

Os hard-gates **esperam o sinal do lease acumular** (o A5/#2804 é o que GERA esse sinal). Regra: medir primeiro, construir só se o dado provar a colisão real — senão fica fechado pra sempre, e tá certo.

- **A6** hard-gate: barra `→done` sem `acceptance_ref` (hoje é só AVISO soft — Fase 2).
- **A7** hard-gate: barra mutação sem lease (decidir hard-block vs auto-claim **lendo o sinal** do A5).
- **C1** lease thread-aware: chave por (humano+sessão) — hoje 2 sessões do mesmo humano = 1 dono. Só depois do sinal mostrar colisão real.
- **C8** (merge bidirecional SPEC↔DB; gatilho = C4 reportar divergência recorrente) · **D-SCOPE-FINE** (sub-scopes finos).

**Gatilho de retomada:** daqui a ~2-4 semanas, consultar `mcp_task_events` por notas "mutação SEM lease ativo" (o aviso do A5). Se frequente → construir A7. Se raro → fechar F5 conscientemente.

## Cleanups pendentes (opcionais, FORA da Leva 2)

1. **Re-armar `scripts/tests/baselines/foundation-ratchet-baseline.json`** — `n_refresh_database` 71→72 (drift de main pré-existente, de um teste com `RefreshDatabase` que entrou sem re-armar). É **advisory** (não bloqueia), mas pinta vermelho em toda PR. Fix = `node scripts/tests/foundation-ratchet.mjs --write --force` num PR de baseline OU migrar o teste pra `DatabaseTransactions`.
2. **Worktrees órfãos** — esta sessão criou ~7 worktrees (`sdd-jana-mysql-lane`, `sdd-teammcp-baseline`, `leva2-blc`, `leva2-fsm`, `leva2-rbac`, `leva2-reconciler`, `leva2-brief`) — todos com branch já mergeada. `git worktree remove` + `git worktree prune`. Some-se ao furo #3 do handoff #2793 (`wf_ada61997-3ce-*`).

## Refs canônicas

- ADR 0278 (arquitetura durável + roadmap faseado F0-F5) · ADR 0105 (signal-gated / cliente-como-sinal) · ADR 0237 (Reconciler/`jana:reconcile`) · ADR 0070 (FSM + scopes mcp_tasks).
- Handoff origem: `memory/handoffs/2026-06-15-2000-sdd-leva1-coordenacao-duravel.md` (#2793).
- Tese que sobrevive (do #2793, agora com mais peças no chão): (i) done = verificação por não-executor; (ii) status observado, não escrito à mão (FSM A1+A8 + reconciler B-R0); (iii) coordenação = lease com TTL + sinal claim-less (A5); (iv) núcleo pequeno + extensão; (v) hard-gate só com dado (F5 diferido).
