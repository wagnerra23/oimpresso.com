---
date: "2026-06-20"
topic: "Scorecard adversarial dos planos pendentes — 30 céticos verificaram estado real vs origin/main: 6 REAL, 13 PARCIAL, 8 prometido-não-existe, 3 bloqueado (~44/100). Parte do refutador de 19/jun já está stale (SDD landou 19→20/jun)."
authors: [C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0279-sdd-medir-governar-floor-nightly", "0281-dark-mode-bridge-data-theme-tokens", "0291-distiller-modulo-verdade-contrato-emenda-0270-f3"]
prs: []
---

# Scorecard adversarial — planos pendentes (sessão fresca, default cético)

> Ref de verificação: `origin/main @ 1819d09126bcb4ff3c06c62f6edaee7ba8cf6f90` (2026-06-19 22:30 UTC).
> Método: 30 agentes adversariais paralelos (Workflow), 1 por plano, cada um tentando REFUTAR "está pronto"
> contra git+gh+código. Regra anti-stale: doc que promete ≠ peça implementada. Confiança ALTA em 30/30.
> MCP `oimpresso` offline na sessão — daí git/gh como fonte da verdade (que é o padrão-ouro mesmo).

## Distribuição dos vereditos

| Veredito | Qtd | IDs |
|---|---|---|
| ✅ REAL | 6 | caixa-prs-merged · us-wa-058-059-omni · deploy-classmap-failsafe · ext-sodium-flag · ds-governance-ledger(90) · sdd-nightly-vivo(85) |
| 🟡 PARCIAL | 13 | sdd-distiller-f3(62) · sdd-distiller-freshness(35) · sdd-medir-governar(55) · sdd-stream-mem(55) · sdd-scorecard-unico(25) · sdd-porta-indice(20) · sdd-automem-morta(35) · ds-tokens-aplicados(65) · caixa-teammcp-drift(40) · wa-loggedout-faseA(60) · wa-channel-reliability(62) · frosty-cleanup(30) · gates-enforcement(55) |
| ❌ PROMETIDO-NÃO-EXISTE | 8 | sdd-gate-required(0) · ds-branch-landed(0) · caixa-os-vinculada(0) · caixa-tom-canal-sla(5) · us-sell-009-rotalivre(10) · us-sell-036-fsm(30) · us-oficina-026-martinho(0) · inbox-index-removal(30) |
| ⛔ BLOQUEADO | 3 | us-infra-011-mysql(0) · us-fiscal-018-larissa(60) · us-nfe-043-048-gold(0) |

**Prontidão média ponderada: ~44/100** (soma readiness 1309/30).

## ACHADO-CHAVE: o estado de 19/jun já está parcialmente STALE

`origin/main` andou de `5f324707` (ref do refutador 19/jun) → `1819d0912` (20/jun). Várias peças que o refutador de 19/jun e a MEMORY.md davam como inexistentes **landaram**:

| O que 19/jun / MEMORY.md dizia | Estado REAL em 1819d0912 |
|---|---|
| "workflows .claude/workflows/sdd-*.js VAZIOS (0 bytes)" | **FALSO** — sdd-avaliador-processo.js (7123B), sdd-fase-1/2/semana-0 todos não-vazios; skill sdd-avaliar (3495B) |
| "distiller F3 0%, bloqueado em CT 100" | **62%** — `DistillerModuloVerdade.php` + `jana:distill-module-truth` + 4 testes EXISTEM (cron comentado por gate) |
| "distiller_freshness: 0 hits em código" | **wired** — check existe em HealthCheckCommand.php:106/438 (mas muda enquanto 0 portas têm `distilled_at`) |
| "nightly-floor.json não existe / transporte não feito" | **feito** — PR #2961 merged; branch órfã `governance/nightly-floor` com 3 runs reais (gitignored no main por design) |
| "nightly morto há 3 dias (insertAuditLog)" | **ressuscitada** — fix #2953 (commit bdfb61dc, 18/jun); floor tem runs 19/20-jun |
| "visual-regression ainda advisory" (MEMORY.md) | **agora REQUIRED** — consta nos 18 contexts da branch protection |

→ **Recomendação:** marcar a tese do refutador 19/jun como verificada-e-parcialmente-superada, e atualizar a MEMORY.md (visual-regression).

## Truly-open (atacar) — ❌/⛔ baixa prontidão

1. **⛔🔐 us-infra-011-mysql (0)** — senha MySQL Hostinger **exposta 2026-05-20, NUNCA rotacionada**; incidente de credenciais (10 arquivos em claro) sem rotação. Ato humano Wagner (hPanel + Vaultwarden + 2× .env + restart). **Maior prioridade — segurança.**
2. **❌ ds-branch-landed (0)** — `feat/governance-ds-rollout-ledger` (a branch desta sessão) **nunca foi mergeada**; commit WIP 82f005341 só vive na própria branch, sem PR. `merge-base --is-ancestor` = NOT_ANCESTOR.
3. **❌ sdd-gate-required (0)** — 0/18 required são SDD; todos os 3 steps do sdd-scorecard.yml com `continue-on-error:true`; métricas `armed:false`. Calendário ADR 0275 não atingido.
4. **❌ caixa-os-vinculada (0)** — vínculo conversa↔OS não decidido (C1/C2); sem coluna `repair_jobsheet_id`, só placeholder UI + TODO honesto.
5. **⛔ us-nfe-043-048-gold (0)** — confirmado dormente; bloqueado por US-NFE-042 (discovery Gold, ato humano).
6. **❌ us-oficina-026-martinho (0)** — outreach humano, zero código.
7. **❌ caixa-tom-canal-sla (5)** — só nos protótipos Cowork; precisa `queue.dist` no payload + render Inertia.
8. **❌ us-sell-009-rotalivre (10)** — bloqueado por US-SELL-008 (canary 7d não rodado); flag biz=4 não setada.
9. **❌ inbox-index-removal (30)** — `InboxController::index()` intacto; 3 testes ainda o usam; só o cutover de rota (301) foi feito (PR #926).
10. **❌ us-sell-036-fsm (30)** — infra FSM em prod (ADR 0143), mas o bulk-start das 14 vendas legadas nunca rodou.

## Quase-lá (fechar a cauda) — 🟡 com gate humano/CT100

- **us-fiscal-018-larissa (60, ⛔)** — código pronto (4 critérios [x]); faltam 4 atos humano-limitados (artisan prod, briefing Larissa 30min, canary 7d, smoke MCP). `module_clients.yaml` ainda `piloto_reportando_dor`.
- **wa-channel-reliability (62)** — 5/10 gaps viraram código (#2/3/4/5/7 — PRs #3002/3017/3005/3006); falta #1 raiz (driver-mismatch probe Baileys↔whatsmeow), #6 nonce whatsmeow, #9 failover Cloud API, #10 circuit breaker. ADRs 0287/0288/0289 ainda `proposto`.
- **wa-loggedout-faseA (60)** — probe + schedule (3min) existem (PR #2956/#2994), mas `logged_out` nunca chega ao DB (enum sem o valor, `markLoggedOutInDb` ausente) → logout indistinguível de queda de rede; frontend `ChannelsDrawer` usa chave morta `down`.
- **ds-tokens-aplicados (65)** — 329 edits aplicados (não 360; 33 rejeitados pelo adversário) via PR #2666; 641 hits de paleta crua restam em 151 arquivos; 169 incertos pendentes do olho do Wagner.
- **sdd-distiller-f3 (62)** — motor + comando + testes existem; cron COMENTADO por design (gate Wagner/CT100, ADR 0291 ainda `proposto`). Descomentar Kernel.php após smoke dry-run ativa a auditabilidade.
- **caixa-teammcp-drift (40)** — floor em **79** (não 80; PR #2914 recuperou 75→79); `module-grades-gate` não está nos required checks (não trava de fato).
- **gates-enforcement (55)** — visual-regression JÁ required; mas item 7 (fusão dos 4 gates de cor em 1) não executado, e `required_approving_review_count=0` (reviews≥1 não ligado).
- **frosty-cleanup (30)** — git já desregistrou o worktree, mas o diretório físico persiste no disco (a sessão atual roda nele). Deleção = ato humano após nenhuma sessão apontar pra lá.
- **sdd-scorecard-unico (25)** — composta NÃO calculada (6/10 not_yet_measured); 4 famílias paralelas confirmadas.
- **sdd-porta-indice (20)** — 13 arquivos INDEX concorrentes; D-2 decidido, consolidação não executada.
- **sdd-distiller-freshness (35)** — check wired mas mudo (0 portas carimbadas); front_door_coverage/read_path_hops não existem no health-check.
- **sdd-automem-morta (35)** — 17 arquivos vivos na auto-mem; `user_profile.md` não migrado; hook só bloqueia escrita nova.
- **sdd-stream-mem (55)** — não há stream MEM (7 streams: SA/FV/KL/GT/Charters/Fase2b/Promoções); a casca (workflows/skill) existe e é real.

## Já fechado (marcar done / não reabrir) — ✅

- **caixa-prs-merged (100)** — 6 PRs merged 16/jun + ADR 0281 bridge live em inertia.css:7.
- **us-wa-058-059-omni (100)** — PRs #536/#553 merged; omnichannel em prod (Channel.php, Centrifugo listener, migration).
- **deploy-classmap-failsafe (100)** — PR #2952 merged; dump-autoload + boot-gate 503 gracioso ativos.
- **ext-sodium-flag (100)** — flag presente (deploy.yml:246/248), #2959/#2960 merged. MANTER.
- **ds-governance-ledger (90)** — `governance/ds-ledger.json` (36 telas) + `scripts/ds-ledger.mjs` reais; só colunas probe/dark pendem de gate de browser.
- **sdd-nightly-vivo (85)** — colisão insertAuditLog resolvida; floor com runs reais 19/20-jun (junit.xml só verificável direto no CT100).

## Sequência recomendada de ataque

1. **🔐 Rotacionar senha MySQL** (us-infra-011) — segurança, ato humano, sem dependência.
2. **Abrir PR da branch atual** (ds-branch-landed) — o trabalho desta sessão não está em main.
3. **Decidir OS vinculada** (caixa-os-vinculada, C1/C2) — destrava a Onda 3 da Caixa.
4. **Fechar driver-mismatch do probe whatsmeow** (wa-channel #1) — o P1 real de confiabilidade.
5. **Descomentar cron do distiller** após dry-run (sdd-distiller-f3) — ativa auditabilidade real.
6. Caudas de governança quando houver folga: item 7 (fusão gates cor), composta SDD, consolidação de índices.

---
> Gerado por workflow `adversario-planos-pendentes` (30 agentes, ~1.3M tokens, 264s). Vereditos crus completos no transcript do run `wf_04bc0487-c42`.
