---
date: "2026-06-15"
hour: "20:00 BRT"
topic: "Arquitetura durável de coordenação multi-IA (ADR 0278): A0 re-baseline + Leva 1 inteira no main + plano refutado pro resto. Retomável a frio."
duration: "~sessão gigante (off-cycle, governança)"
authors: [W, C]
---

# Handoff — coordenação durável anti-vazamento: Leva 1 fechada, mapa do resto

> **TL;DR:** [W] "o que VOCÊ construiria do zero, pronto pra evoluir?" → grade adversarial do MCP (**47.9/100**: diagnóstico world-class, mecanismo de coordenação pré-MVP) → plano refutado de 31 tarefas com **teto honesto ~86-88 (NÃO 100)** → executado A0 + **Leva 1 inteira no main** (7 PRs, cada uma adversário-verificada; 4 bugs reais de CI caçados e corrigidos). Resto = backlog abaixo, signal-gated onde o ADR 0105 manda. **Continuar a Leva 2 numa sessão FRESCA** (a própria tese do #2766: durabilidade vem do registro, não de turno gigante).

## O que ENTROU no main nesta sessão (verificado)
| PR | entrega | dims |
|---|---|---|
| #2785 **A0** | `acceptance_ref` + `updateInner` ATÔMICO (`DB::transaction`+`lockForUpdate`) — fecha o lost-update real (causa-raiz do "a lista fura") | 1, 5 |
| #2782 | ADR 0278 (arquitetura durável) + ADR 0279 (medir→governar) numerados | — |
| #2781 | D1 — `mcp_work_leases` + `tasks-claim`/`tasks-heartbeat`/`whats-locked` | 1, 4 |
| #2787 | ADR 0280 — postura multi-tenant das tabelas mcp_* (repo-wide by-design) | 7 |
| #2789 | lease propaga `X-Claude-Code-Session` → **começa a gerar o 1º sinal** | 4 |
| #2786 | D-COST-FIX — custo MCP lia config morta (`copiloto.openai.pricing`→`copiloto.ai.pricing`) | 11 |
| #2788 | C4 — detecta (não resolve) divergência descritiva SPEC↔DB | 2 |
| #2790 | D-EVT-TRIG — trigger de imutabilidade em `mcp_task_events` (espelha o de `mcp_audit_log`) | 3 |
| #2791 | B-LIVE-HB — tabela+writer `mcp_ingest_heartbeat` (em TeamMcp; núcleo do fim do SPOF) | 10 |
| #2792 | consolidação — pluga 3 testes sqlite na lane (Pest verde **comprovado**) | — |

**Bugs reais que o ciclo adversarial+CI pegou (prova de que não é verde-de-teatro):** `$user->id`/`@property` não-tipados (Larastan ×3 ocasiões), `TaskUpdateAtomicTest` com dependência de `users` ausente, conflito de `ci.yml` (lane de 1 linha), narrativa enganosa de PR. Todos corrigidos antes do merge.

## A grade (ADR 0155-style, 11 dims) — baseline e norte
**47.9/100** (refutador: 48). Forte em conhecimento/atribuição (audit 80, RBAC 85, durable-state 68); fraco em execução-coordenação (concorrência 18, state-machine 30, gate-no-barramento 25, observabilidade 35). A Leva 1 move concorrência (lost-update fechado), DoD-state (acceptance_ref), observabilidade (heartbeat), custo. **Teto honesto pra 1 org: ~86-88, não 100** (levar multi-tenant/observabilidade enterprise a 100 é desperdício, ADR 0105).

## BACKLOG — retomável a frio (ordem recomendada)

### Furos honestos da Leva 1 (fechar primeiro)
1. **Lane Jana-MySQL per-PR** — `ImmutabilityTriggersTest` (#2790) é MySQL-only e **não roda em lane nenhuma** (só `ci.yml`=sqlite roda Jana). Mesmo gap do trigger de `mcp_audit_log` (nunca teve teste). Criar a lane destrava verificação de TODO trigger/migration MySQL-only do Jana. **Effort M.**
2. **Débito advisory:** TeamMcp module-grade 82→81 (advisory, não bloqueou — a feature heartbeat diluiu ~1pt). Recuperar a dimensão (provável observabilidade/cobertura) OU aceitar no baseline conscientemente. **S.**
3. **Cleanup:** worktrees órfãos do workflow `wf_ada61997-3ce-*` (`git worktree remove` + prune).

### Leva 2 — buildável, SEM sinal (dep do A0; serializa em `updateInner`/`ci.yml`)
- **A1+A8** — matriz de transição de status (proíbe todo→done teleporte) + selftest que morde. ⚠️ A matriz JÁ EXISTE como dado (`mcp_workflows.transitions`, `McpDefaultsSeeder` ~L67-75) com **zero leitores** → é WIRE, não design. **Emendar `FsmCanonicalTest`/`module.json`** (Jana é marcado `fsm_n_a=true` — chat-Conversa N/A, mas mcp_tasks TEM FSM). Effort M-L.
- **A3** — fatiar `jana.mcp.tasks.write` em advance vs `close` (→done/cancelled). M.
- **A4** — trait `AuthorizesMcpMutation` server-side (gate no barramento; hoje SÓ `CcSearchTool` faz `->can()`; corrigir o comentário mentiroso do `McpAuthMiddleware` "cada tool checa o scope"). M.
- **A5** — aviso SOFT de mutação claim-less (espelha o consumer do #2784; **instrumenta o sinal** que A6/A7 exigem). M.
- **B-LIVE-CHECK** — `IngestLivenessService` (fresh<15min/stale/dead) lendo `mcp_ingest_heartbeat`. S.
- **B-SPOF-WA** — matar o false-clear: `whats-active` sem msg responde "pode pegar tudo" = diz SEGURO quando CEGO; injetar liveness → "NÃO SEI: pipeline DEAD". M. (bug central dim 10)
- **B-R0→B-RA/RB/RE** — reconciler detect-only. **Reusar contrato `Reconciler`/`jana:reconcile` (ADR 0237), NÃO criar `governance:reconciler`.** R-A=doing-órfã(sem lease), R-B=done-sem-acceptance_ref, R-E=blocked_by-resolvido. M.
- **C2/C3** — brief mostra leases ativos + nudge "claim antes de pegar". S.

### Signal-gated (ADR 0105 — só DEPOIS do lease gerar sinal; #2789 começou a coletar)
- **A6** hard-gate: barra →done sem `acceptance_ref` (Fase 3 ADR 0278, "CONSTRUIR não flip").
- **A7** hard-gate: barra mutação sem lease (decidir hard-block vs auto-claim com o sinal).
- **C1** lease thread-aware: chave por (humano+sessão) — hoje colapsa em humano (2 sessões=1 dono). **Só depois do #6 mostrar colisão real.**
- **D-SCOPE-FINE** (sub-scopes finos) · **C8** (merge bidirecional SPEC↔DB; gatilho = C4 reportar divergência recorrente).

### CORTADOS (over-engineering p/ 1 org — NÃO reabrir)
B-SPOF-WATCH (watcher real-time supervisionado), D-MT-GUARD (catraca multi-tenant), C5 (ActorResolver formal com 1 consumidor), Temporal/CRDT/event-sourcing/A2A.

## Refs canônicas
- ADR 0278 `memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md` (tese + roadmap faseado) · ADR 0279 (medir→governar) · ADR 0280 (postura multi-tenant).
- Tese que sobrevive: (i) done = verificação por não-executor; (ii) status observado, nunca escrito à mão; (iii) coordenação = lease com TTL; (iv) núcleo pequeno + extensão. Sem tech distribuída.
- Próximo passo sugerido: **sessão FRESCA** → fechar furos 1-3 → Leva 2 (paraleliza em 4 raias A/B/C/D, serializa nos arquivos compartilhados) → pausa pra ler o sinal do lease → F5 hard-gates.
