---
date: "2026-07-09"
hour: "12:40 BRT"
duration: "0.8h"
topic: "Goal-eval rodada 1 — IA OS avaliado por RESULTADO: US-SELL-036 FSM rollout biz=1 (5→0 vendas legadas sem stage, canary iniciado)"
authors: [C]
outcomes:
  - "prod biz=1: 5→0 vendas type=sell sem current_stage_id (170→175 com stage; sale_stage_history 200→205)"
  - "canary iniciado: fsm:scan-drift day-0 'No drift detected. OK.' + cron daily 03:00 BRT confirmado no schedule:list"
  - "US-SELL-036 todo→doing→review com acceptance_ref durável (ADR 0278)"
us: [US-SELL-036]
related_adrs: [0143-fsm-pipeline-live-prod-marco-2026-05-12, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
---

# Goal-eval rodada 1 — avaliar o IA OS por RESULTADO (não por gate verde)

**Goal escolhido:** US-SELL-036 — vendas legadas biz=1 migradas pro FSM via `fsm:bulk-start-pipeline` + canary iniciado. Escolhido entre os 3 P0 recomendados por ser o único executável autonomamente com evidência SQL antes→depois (RecurringBilling emite cobrança real em gateway = REGRA MESTRE valor exige apresentação prévia ao Wagner; US-OFICINA-026 = envio externo a cliente).

## 1. Outcome — ALCANÇADO (com correção de mundo)

| Métrica (prod biz=1, type=sell) | ANTES | DEPOIS |
|---|---|---|
| `current_stage_id IS NULL` (legadas) | **5** | **0** |
| `current_stage_id` populado | 170 | **175** |
| `sale_stage_history` (biz=1) | 200 | **205** (5 novas com `bulk_command` em 2026-07-09) |

- **Correção de mundo:** o título da task dizia "14 de 162" — números de 2026-05-16. O mundo real (dry-run 2026-07-09): **5 candidatas restantes** (170 já haviam sido migradas por outra via desde a reconciliação de 28/mai, que atualizou "código pronto" mas não o número). Migrei as 5 → rollout FSM biz=1 agora é **100% das vendas type=sell**.
- **Evidência:** dry-run `Total candidatas: 5` → execução real `Processadas: 5, Skipped: 0` (mapping `paid:3, invoiced:2`; OS00130–OS00134, ids 69290/69291/69292/69306/81965). `laravel.log`: 5× `FSM authorized transition via flag` (gateway `FsmAuthorizationFlag` respeitado, zero bypass do `GuardsFsmTransitions`).
- **Canary iniciado:** `fsm:scan-drift transactions` day-0 = `No drift detected. OK.` + cron `0 3 * * *` confirmado via `schedule:list` ("Next Due: em 11 horas"). Fecha 2026-07-16.
- **Smoke R1 prod (HTTP literal):** `/login` → `HTTP/1.1 200 OK` · `/` → `HTTP/1.1 200 OK` · `/sells` → `HTTP/1.1 302 Found` (redirect auth esperado — regression adjacent).
- **Segurança pré-execução:** li o comando de `origin/main` (worktree da sessão estava 4960 commits atrás — guard avisou): só seta `current_stage_id` + history append-only; **zero side-effect valor/estoque** (ReservarEstoque etc. só em actions) → REGRA MESTRE não aplica.

## 2. Custo

- **Tempo até o outcome no mundo:** ~10 min (sessão 15:21 UTC → migração 15:31:49 UTC). Total com smoke + registro: **~50 min**.
- **Interrupções humanas:** **0**.
- **Retrabalhos:** **1** (transição `todo→review` ilegal no FSM do mcp_tasks → refeita em 2 passos `todo→doing→review`; ~30s).

## 3. Log de técnicas do OS (todas que dispararam/interferiram)

| # | Técnica | Efeito | Evidência | Custo |
|---|---|---|---|---|
| 1 | `brief-fetch` via hook SessionStart | ajudou | estado consolidado sem chamada manual (~546 tokens) | zero |
| 2 | Bloco "EM VOO" do brief | atrapalhou | 11 itens `null @ <Module>` — título da task não resolve, bloco vira ruído | leitura inútil |
| 3 | `my-work` MCP | ajudou | 30 tasks com status/prio corretos; US-SELL-036 p0 localizada em 1 call | 1 call |
| 4 | `tasks-detail` MCP | **ajudou muito** | DoD executável passo-a-passo + anchor `Implementado em` + comentário reconciliação 28/mai "código PRONTO" → zero exploração de repo, zero reimplementação | 1 call |
| 5 | Guard base-freshness (SessionStart) | ajudou | worktree −4960; li `FsmBulkStartPipelineCommand` de `origin/main`, não do checkout podre | zero |
| 6 | Lição MSYS revspec (auto-mem) | ajudou | `git show origin/main:<path>` via PowerShell → saída íntegra (Git Bash manglearia) | zero |
| 7 | `--dry-run` nativo do comando | **ajudou muito** | pegou o bookkeeping stale ANTES do write: "Total candidatas: 5" ≠ 14 da task | 1 SSH |
| 8 | Protocolo claim-sem-evidência (Tier 0) | ajudou | forçou formato SQL antes→depois + `curl -sv` HTTP literal — coincide com o DoD do goal | ~5 min |
| 9 | Receita SSH warm-up (how-trabalhar) | ajudou | SSH Hostinger conectou de primeira após 5× curl | ~30s |
| 10 | FSM do mcp_tasks (transições legais) | atrapalhou (leve) | `todo→review` rejeitada; correto por design (audit trail), mas custou 1 retrabalho | ~30s |
| 11 | `sessions-recent` ausente do registry MCP da sessão | atrapalhou | checklist de fechamento (ADR 0130) manda chamá-la; tool não exposta → fallback `git ls-tree origin/main memory/handoffs/` | desvio de protocolo documentado |
| 12 | Rotina "FECHAR O LOOP IA-OS" (SessionStart) | atrapalhou | injeta "pergunte ao Wagner se quer fazer o #3 agora" em TODA sessão, independente do escopo; nesta rodada = ruído puro (regra 2 do teste proibia) | contexto |
| 13 | Hook Stop do `/goal` | ajudou (meta) | manteve execução até desfecho sem pausa interna (R11) | zero |
| 14 | memory-schema-preflight (schemas JSON) | ajudou | frontmatter de session/handoff validado contra `session.schema.json`/`handoff.schema.json` antes do commit — zero loop de CI | 1 git show |

## 4. Top 3 que MAIS ajudaram

1. **`tasks-detail` com DoD executável + anchor + reconciliação** — a task já dizia ONDE o código estava e QUE estava pronto (verificado@cd84a38). Fui do backlog à execução em prod sem abrir o repo. É o contrato task↔código funcionando.
2. **`--dry-run` como primeiro contato com o mundo** — barato (read-only), e foi ele que corrigiu o goal nominal (14) pro goal real (5). Sem ele eu teria reportado meta errada ou "falhado" um goal impossível.
3. **Cultura de evidência (claim-sem-evidência + R1)** — o formato exigido (SQL antes→depois, HTTP literal, log de transição) É o DoD do goal-eval. O OS já estava calibrado pra este tipo de avaliação.

## 5. Top 3 que MAIS atrapalharam → recomendação (espírito ADR 0271)

1. **Bookkeeping stale task↔mundo** (título "14 de 162" vs mundo 5/175) → **AJUSTAR**: a reconciliação de 28/mai atualizou "código pronto" mas não o número vivo. Recomendo que reconciliações de task incluam o número do mundo (1 SQL/dry-run) no comentário — sem criar gate novo pra isso.
2. **Rotina "FECHAR O LOOP IA-OS" no SessionStart** → **CORTAR** do SessionStart (mover pra flag do brief): pede interação Wagner em toda sessão, fora de qualquer escopo; classe terminal indefinida (viola o teto ADR 0298 em espírito). Nesta rodada: ruído puro ignorado conscientemente.
3. **Brief "EM VOO" com títulos null** → **AJUSTAR** o gerador do brief (join do título da task): 11 de 11 itens ilegíveis; o bloco que mais deveria orientar a sessão não orienta.

Menor (registro): `sessions-recent` fora do registry MCP da sessão → AJUSTAR exposição de tools; FSM do mcp_tasks → MANTER (custo mínimo, audit correto).

## 6. Recomendação única pra próxima rodada

**Goal-first começa com o número vivo, não com o número do backlog:** antes de planejar a rodada, 1 consulta barata ao mundo (dry-run/SQL) pra recalibrar o alvo. O backlog dá a direção; o mundo dá o número. (Rodada 2 sugerida: US-RECURRINGBILLING-002/003 — exige janela síncrona com Wagner pela REGRA MESTRE de valor.)

## Fricções anotadas (regra 2 — nada criado, só contornado)

- F1 números stale na task (contornado via dry-run) · F2 brief EM VOO null (ignorado) · F3 `sessions-recent` indisponível (fallback git) · F4 rotina IA-OS fora de escopo (ignorada) · F5 FSM task 2-passos (refeito) · F6 PS 5.1 embrulha progress do git em stderr como erro falso (conhecido, ignorado).
