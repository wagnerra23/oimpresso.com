# Handoff 2026-05-11 18:30 — Paralelização Omnichannel frustrada (subagents mortos com worktree)

> **TL;DR:** Tentei 4 frentes paralelas (Outbound Inbox, Centrifugo, Sync daemon, Drift webhook) via 4 subagents em worktrees isoladas. Worktree pai foi deletada antes dos filhos produzirem PR — **nenhum dos 4 PRs foi aberto**. Único artefato sobrevivente: **PR #551 merged** registrando US-WA-058..061 no SPEC.md. Próximo Claude pega US prontas no backlog.

---

## O que rolou

### Pedido original do Wagner
4 frentes pra próxima sessão Omnichannel (ADR 0135 Fase 0):

1. **Envio outbound do Inbox novo** (refactor drivers) ~3h
2. **Real-time Centrifugo no Inbox** ~1h
3. **Drift webhook legacy Z-API/Meta** (paralelo, sem urgência) ~1h
4. **Sync daemon source `Modules/Whatsapp/daemon-node/`** com patches CT 100 ~30min

### Proposta validada
Skill `wagner-request-refiner` aplicada — propus shim minimal pra outbound (não refactor profundo de `DriverInterface`) pra preservar Z-API/Meta legacy em prod. Wagner respondeu "em paralelo" → interpretei como autorização ampla pra spawnar 4 subagents simultâneos.

### Execução
1. ✅ **Criadas 4 US no MCP** via `tasks-create`: US-WA-058 (outbound, p1, 3h), US-WA-059 (centrifugo, p1, 1h), US-WA-060 (daemon sync, p2, 0.5h), US-WA-061 (drift webhook, p3, 1h)
2. ✅ **PR #551 mergeado** — append em `memory/requisitos/Whatsapp/SPEC.md` com bloco markdown das 4 US (102 insertions). Commit `09b22e08` na branch `claude/flamboyant-gauss-2e35d3`; squash merge `d4f2968d`
3. ❌ **4 subagents spawn em paralelo via `Agent` tool com `isolation: "worktree"`** — todos `run_in_background:true`
4. ❌ **Worktree pai `flamboyant-gauss-2e35d3` foi deletada** antes dos subagents produzirem output → todos os 4 morreram sem abrir PR. Lista de PRs após reabertura confirma: nenhum US-WA-058..061 apareceu

### Estado final do trabalho
- US-WA-058..061 no MCP/SPEC.md com status `todo`, owner `wagner`, sprint `CYCLE-05`, escopo detalhado
- Próximo Claude pode pegar 1 por vez sequencial OU re-tentar paralelo

---

## Lição operacional (memória pro futuro)

**Não spawn subagent worktree de dentro de worktree filha.** Padrão observado:
- Worktree pai `D:\oimpresso.com\.claude\worktrees\flamboyant-gauss-2e35d3` (sessão Claude A)
- Subagents criam sub-worktrees **dentro** do pai (cleanup automático ao final da sessão)
- Se o pai morre por qualquer razão (timeout, cleanup, reload), filhos morrem juntos
- Subagents `run_in_background:true` **não persistem trabalho** se forem mortos antes do PR push

**Padrão robusto pra paralelização:**
- Spawn subagents diretamente do repo origem (`D:\oimpresso.com`) ou via `gh` orchestration
- Cada agent abre PR **antes** de qualquer outra ação custosa (commit + push primeiro, polish depois)
- Subagent deve sinalizar "PR aberto: #NNN" como primeira linha do output reportável
- Considerar usar tasks-update DOING tag no MCP no início pra marcar ownership e detectar morte

**Anti-pattern documentado nesta sessão:** "spawn 4 agents em paralelo via Agent tool com run_in_background" = trabalho perdível se worktree pai cleanup roda antes dos PRs serem criados.

---

## Próxima sessão — pegar de onde parei

### Opção A — re-tentar paralelo (robusto)
Spawn os 4 agents direto do repo origem (não de worktree filha):
- Cada um cria branch própria `claude/us-wa-NNN-...`
- Push + PR ANTES de polish
- Cada agent já tem prompt detalhado nesta sessão acima (procurar Agent calls com escopo de PR-1..4)

### Opção B — sequencial nesta worktree
1. `tasks-update task_id:US-WA-058 status:doing owner:wagner`
2. Implementar US-WA-058 (outbound shim Phone — ~2h)
3. PR → merge
4. Repete pra US-WA-059, 060, 061

### Opção C — pega só o low-hanging
US-WA-060 (sync daemon 30min) primeiro — exercita SSH CT 100, valida que daemon-node tem source committable, sem creds. Daí escolhe próxima.

### Recomendação
**Opção A** é o caminho certo arquiteturalmente (Wagner explicitou "em paralelo"), MAS exige spawn de fora da worktree filha. **Opção B** é fallback seguro se quer evitar overhead. **Opção C** é warm-up.

---

## Estado MCP no momento do fechamento

### `cycles-active` (CYCLE-05, COPI)
- **Goal:** Inter PJ Banking em prod com canary 7d + FICHA WhatsApp v2 aprovada + audit log shell
- **Janela:** 2026-05-11 → 2026-05-23 (12 dias restantes)
- Goals trackados (ambos 🔲 abertos):
  - Inter PJ Banking em prod (US-RB-048/046/047)
  - WhatsApp FICHA v2 + AUDIT-LOG shell (US-WA-051/052)

### `my-work` @wagner (10 tasks ativas)
**DOING (4):**
- US-RB-045 p1 — Inter PJ saldo via Banking API v2 (Fase 1 OF direto)
- US-WA-040 p2 — Múltiplos números por business (Sprint 4)
- US-COPI-096 p2 — Setup Horizon (provider + auth gate + flag CT-only)
- US-COPI-100 p2 — NarrarSaudeEcosistemaJob (hourly + escalation HITL)

**BLOCKED (6):** FIN-4 (ROTA LIVRE cobrança) + 5 US-NFE Gold dormentes (43..47)

### Tasks recém-criadas (nesta sessão)
- **US-WA-058** todo p1 3h — Inbox omnichannel outbound via Channel (shim Phone)
- **US-WA-059** todo p1 1h — Inbox omnichannel real-time Centrifugo
- **US-WA-060** todo p2 0.5h — Sync daemon-node CT 100 → repo
- **US-WA-061** todo p3 1h — Drift webhook legacy Z-API/Meta observability

### `decisions-search "omnichannel inbox channel driver"` (3 ADRs ativas)
- **0135-omnichannel-inbox-arquitetura** — Channel polimórfico + Driver pattern + 4 fases com gate cliente-sinal (mãe desta sessão)
- **0096-modulo-whatsapp-meta-cloud-api-direto** — Z-API default + Meta Cloud fallback + Baileys custom; Evolution PROIBIDO permanente
- **adr-jana-tech-0001-drivers-apuracao-plugaveis** — drivers plugáveis (SQL/PHP/HTTP)

### Sessão paralela detectada (importante)
Branch `claude/us-wa-076-077-inbox-cleanup-atendente` está ativa em outra worktree (touched files: `Modules/Whatsapp/Entities/Message.php`, `InboxController.php`, `ChannelBaileysWebhookController.php`, `ConversationThread.tsx`, `helpers.ts`, `InboxCleanupTest.php`). Working tree stash `WIP outra sessão Claude` preservado pelo resume hook. **Próximo Claude:** se for pegar US-WA-058 outbound, conflito alto com `InboxController.php` — coordene via `whats-active` ([ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)) antes de editar.

---

## Arquivos tocados nesta sessão

| Path | Ação | PR |
|---|---|---|
| `memory/requisitos/Whatsapp/SPEC.md` | +102 (4 US blocks anexados) | #551 merged (d4f2968d) |

Zero código de produção mexido. Zero risco regressão.

---

## Skills usadas
- `brief-first` (Tier A) — `brief-fetch` rodou no SessionStart
- `wagner-request-refiner` — decompôs pedido 4-frentes, propôs ordem + escopo, confirmou shim vs refactor profundo antes de codar
- `mcp-first` — todas leituras canônicas via MCP (cycles-active, my-work, tasks-list, decisions-search) antes de filesystem
- `commit-discipline` — 1 PR = 1 intent (#551 só SPEC), ≤300 linhas, conventional + `Refs: CYCLE-05`

## Skills que **não** ativaram (intencionalmente)
- `multi-tenant-patterns` — nenhuma Model/Controller/Job mexido nesta sessão (só SPEC.md)
- `mwart-process` — nenhuma migração Blade→Inertia
- `module-completeness-audit` — nada marcado como done

---

**Próxima sessão começa aqui:** US-WA-058..061 backlog. Sugestão de ordem: 060 (warmup CT 100) → 058 (outbound) → 059 (centrifugo) → 061 (drift obs).
