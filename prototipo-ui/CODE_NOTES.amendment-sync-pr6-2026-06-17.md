# CODE_NOTES — amendment · loop de handoff zero-paste fechado (SYNC-PR6 + Fase 2)

> **De:** [CL] Claude Code · **Para:** [CC] Cowork (Wagner cola no chat do Design)
> **Data:** 2026-06-17 · **Re:** handoff `PROMPT_PARA_CODE_HANDOFF-SYNC-PR6.md` ([ADR 0283](../memory/decisions/0283-handoff-loop-zero-paste.md))
> **Regra:** append-only (ADR 0003 / L-07). Não edita entradas antigas.

---

## [PROCESSADO 2026-06-17] — PR-6 (core) já no `main`; Gap 3 (levers) aberto; Gap 2 (gate conflito) em voo

Processei a ponte **SYNC-PR6**. O honesto, sem maquiar: **o core do PR-6 já tinha
sido entregue antes desta sessão** (sessão paralela), então o trabalho novo foi
fechar os 2 resíduos que o handoff deixou como "se trivial; senão = chips".

### ✅ PR-6 core — JÁ no `main` ([#2921](https://github.com/wagnerra23/oimpresso.com/pull/2921), merge 2026-06-17 15:43 BRT)
- **6a `handoff-submit`** — landing-pad HTTP assinado. `HandoffSubmitTool` reusa o
  `HandoffIngestService` (mesma validação HMAC/`source_hash`/append-only do PR-1).
  Scope `jana.mcp.handoff.submit` (A7). Pulsa o heartbeat.
- **6b transporte on-push** — `bin/sign-handoff.php` (HMAC, CRLF→LF, `--self-test`,
  dependency-free) + `.github/workflows/handoff-sign-submit.yml` (advisory, skip-as-pass
  sem secrets). Registrado no `gates-registry.json`.
- **Respeitou o NÃO FAZER:** sem auto-merge; `HANDOFF_SECRET` só no Action/servidor; só ADD (não recriou ingest/tabela/tools).

**"Pronto quando" do handoff → atendido por #2921.** NB: *este* handoff específico
chegou pela **URL do Cowork** (não por `prototipo-ui/handoffs/*.md`), então não há
`.md` no repo pra carimbar `[PROCESSADO]` — **esta nota É o registro de processamento.**

### ✅ Gap 3 — levers (`re-disparar`/`devolver`/`supersede`) — PR-7 [#2924](https://github.com/wagnerra23/oimpresso.com/pull/2924) (aberto, aguardando seu 1-clique)
As 3 levers que a Forja só **pintava** (`ForjaMcp.tsx`, botão `disabled "em breve"`)
agora têm tool MCP real: **`handoff-lever`** (scope `jana.mcp.handoff.lever`, auditada,
idempotente, **sem auto-merge**). Espelha o `HandoffAckTool`.
- `re-disparar`: `pending` parado (stale) → re-arma a freshness.
- `devolver`: `rejected` → reabre pra `pending` + limpa o ack (`pr_url`/`gate_status`/`applied_*`).
- `supersede`: `pending`|`applied` → `superseded` (append-only — a linha **fica**, só sai da lista ativa).
- Re-arm **sem coluna nova** (`created_at = now()`) → zero migration, zero toque em `ForjaMcpService`.

### 🔄 Gap 2 — gate `conflito` (ack auto-reportado × required checks REAIS do PR) — EM VOO (sessão paralela)
**Não dupliquei.** Uma sessão paralela já está com WIP forte: um `PrChecksResolver`
que lê o estado real dos required checks via GitHub API (`config('services.github.*')`,
ADR 0076), degrada gracioso (`null` quando não dá pra afirmar) e cruza com o
`gate_status` do ack → badge `conflito`. Vai virar PR próprio dessa sessão.

### ⚠️ Fica de propósito como follow-up (NÃO é esquecimento)
- **Botão de lever no web (`ForjaMcp.tsx`) segue surface-only.** ADR 0283 diz que as
  levers roteiam via **tool MCP auditada — NÃO [W] operando no web**. Ligar o botão
  exige uma ponte web→scope-MCP que ainda não existe. → chip separado.
- **Zero-toque real (publisher Cowork→repo).** O Cowork é read-only no GitHub; o
  on-push (6b) é o piso real até existir um publisher do lado Cowork. → chip já previsto no handoff.

---

### Mapa rápido pro [CC]
| Peça do handoff | Estado | Onde |
|---|---|---|
| 6a `handoff-submit` | ✅ no main | #2921 |
| 6b Action on-push | ✅ no main | #2921 |
| Gap 3 levers | ✅ PR aberto | #2924 (este loop) |
| Gap 2 gate `conflito` | 🔄 em voo | sessão paralela (`PrChecksResolver`) |
| Botão lever no web | ⏸ follow-up | ADR 0283 (não-[W]-no-web) |
| Publisher Cowork→repo (zero-toque) | ⏸ follow-up | chip do handoff |
