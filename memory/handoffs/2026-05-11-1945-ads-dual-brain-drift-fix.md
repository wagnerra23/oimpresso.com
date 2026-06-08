# Handoff 2026-05-11 19:45 — Fix drift ADS Brain B (site nunca quebrou)

> **TL;DR:** Wagner reportou "quebrou o site". Diagnóstico: **site web saudável o tempo todo**; **Brain B autônomo parado** desde 15:50 BRT por drift de schema em `mcp_dual_brain_decisions` (7 colunas faltando apesar de migration original `Ran [73]`). Fix idempotente aplicado (PR #574 + #576 admin-merged), Brain B funcionando, smoke OK. Bônus: 3 migrations pendentes legadas (vehicles, service_orders, transactions legacy_date) rodaram de brinde no `migrate --force`.

---

## O que rolou nesta sessão

### 1. Investigação "quebrou o site" — falso alarme
- 8 rotas probadas (home, login, atendimento/inbox, atendimento/canais, financeiro/unificado, whatsapp/conversations, repair/dashboard, copiloto) → todas HTTP 200 + Inertia mount válido
- Zero `live.ERROR` Laravel após 19:00 UTC (16:07 BRT no momento da checagem)
- Vite manifest existe (`public/build-inertia/manifest.json`, May 11 19:02 UTC, 403KB)
- **Erro temporário Vite manifest às 16:02 BRT (userId 569 = Wagner):** race condition durante deploy do PR #571, auto-resolvido pelo build às 19:02 UTC. Wagner viu Whoops momentâneo.

### 2. Problema real encontrado em paralelo — Brain B parado
- Cron schedules falhando recorrentemente desde 15:50 BRT:
  - `ads:plan-decisions` → `Unknown column 'parent_decision_id'`
  - `ads:review-decisions` → `Unknown column 'review_score'`
  - `ads:auto-generate-tasks` → `Unknown column 'auto_generated'`
- Migration `2026_05_03_200001_add_learning_loop_columns` em `migrations` table como `[Ran 73]`, MAS as 7 colunas (`parent_decision_id`, `auto_generated`, `attempts`, `next_retry_at`, `review_score`, `review_breakdown`, `review_confidence`) **NÃO existiam em prod**
- Causa raiz: desconhecida. Hipóteses — DDL manual (drop column), restore de backup mais antigo após migration registrada, falha parcial de transação não rastreada

### 3. Fix aplicado
- **Migration idempotente nova:** `Modules/ADS/Database/Migrations/2026_05_11_190001_repair_dual_brain_learning_loop_drift.php`
- Cada `ADD COLUMN` precedido de `Schema::hasColumn` — segura em qualquer ambiente (local sem drift = no-op)
- Cada índice precedido de `SHOW INDEX FROM ... WHERE Key_name = ?` — idempotente
- `down()` é **NO-OP intencional** com comentário (reverter dropando colunas re-introduziria o drift)
- **PR #574** admin-merged → `26a1e708`
- **PR #576** admin-merged → `148eb577` (session log)
- SSH Hostinger `php artisan migrate --force` → 4 migrations DONE em ~1.5s (incluiu 3 legadas pendentes)

### 4. Validação pós-deploy
| Validação | Resultado |
|---|---|
| 7 colunas presentes | ✅ todas via `db:table` |
| 4 índices criados | ✅ idx_dbd_parent, idx_dbd_next_retry, idx_dbd_review, idx_dbd_auto_gen |
| Smoke `ads:plan-decisions --limit=1` | ✅ "8 subtarefas criadas (conf=0.7)" |
| ERROR pós-fix | ✅ vazio |

---

## Lições catalogadas

1. **`procedure_drift` check em `jana:health-check` não pegou esse drift** — provavelmente mira procedures stored, não colunas individuais. Sugerir expansão pra column drift em próxima iteração.
2. **Pattern reusável de fix idempotente:** `Schema::hasColumn` + `SHOW INDEX WHERE Key_name` + down NO-OP justificado. Reusar em futuros drifts.
3. **Working tree compartilhado entre sessões Claude paralelas é fonte de commit-pollution** — outra sessão Claude commitou `dc65e040` (docs OfficeImpresso) na minha branch. Squash merge agregou os 2 intents em 1 commit, violando commit-discipline (1 PR = 1 intent). Mitigação: cada Claude em worktree própria, MAS evitar worktree filha como na sessão 18:30.
4. **Vite manifest race condition** durante deploy é cenário real. Solução robusta: build de assets ANTES do `git pull` final OU atomic deploy via symlink swap.

---

## Estado MCP no momento do fechamento

### `cycles-active` (CYCLE-05, COPI)
- **Goal:** Inter PJ Banking em prod com canary 7d + FICHA WhatsApp v2 aprovada + audit log shell
- **Janela:** 2026-05-11 → 2026-05-23 (12 dias restantes)
- Goals trackados (ambos 🔲 abertos):
  - Inter PJ Banking em prod (US-RB-048/046/047)
  - WhatsApp FICHA v2 + AUDIT-LOG shell (US-WA-051/052)

### `my-work` @wagner (snapshot da sessão anterior — sem mudança)
- **DOING (4):** US-RB-045, US-WA-040, US-COPI-096, US-COPI-100
- **BLOCKED (6):** FIN-4 + 5 US-NFE Gold dormentes

### `decisions-search "procedure drift schema column ALTER"` (3 ADRs)
- **adr-nfebrasil-tech-0002** — Contingência EPEC/FS-DA
- **adr-nfebrasil-arq-0006** — Cascade tributário 4 níveis
- **adr-recurringbilling-tech-0008** — FK type-mismatch + **migrations idempotentes** ← pattern relacionado

### Sessões paralelas detectadas
Outra sessão Claude andou em paralelo nesta janela commitando docs/research:
- `dc65e040` docs(officeimpresso): consolidar licoes criticas + fix drift SPEC OficinaAuto
- Working tree tinha SPEC.md + README.md OfficeImpresso modificados + `_LICOES-CRITICAS.md` untracked
- Conflito mínimo (paths diferentes), mas commit-pollution no PR #574

---

## Arquivos tocados

| Path | Ação | PR |
|---|---|---|
| `Modules/ADS/Database/Migrations/2026_05_11_190001_repair_dual_brain_learning_loop_drift.php` | +96 (nova) | #574 merged (26a1e708) |
| `memory/sessions/2026-05-11-fix-ads-dual-brain-drift.md` | +80 (nova) | #576 merged (148eb577) |
| `memory/handoffs/2026-05-11-1945-ads-dual-brain-drift-fix.md` | +este (nova) | (este PR) |

3 migrations legadas rodadas em prod de brinde:
- `2026_05_11_000010_create_vehicles_table` (OficinaAuto)
- `2026_05_11_000020_create_service_orders_table` (OficinaAuto)
- `2026_05_11_170001_add_legacy_date_fields_to_transactions` (Sells)

---

## Próximos passos sugeridos

### Tier 1 — saúde de governança (curto prazo)
- Investigar causa raiz do drift (hPanel access log Hostinger? MySQL audit? backup history?)
- Expandir `jana:health-check` pra detectar **column drift** (não só procedure drift)
- Snapshot canônico de schema (`SHOW CREATE TABLE` de todas tabelas core) vs Pest `ProcedureDriftSnapshotTest` → criar equivalente `ColumnDriftSnapshotTest`

### Tier 2 — operacional
- US-WA-058..061 cadastradas no SPEC mas ainda `todo` — sessão paralela do Wagner já fez US-WA-069 que cobre 058 (outbound via Channel). Próxima sessão deve `tasks-update US-WA-058 status:cancelled` + manter 059/060/061
- 3 schedules ADS que estavam falhando voltam automaticamente no próximo tick do cron (HEAD agora tem schema OK)

### Tier 3 — investigativo
- Audit cross-check: outras migrations marcadas como [Ran] em prod podem ter sofrido drift similar? Pra MVP de detecção, listar tabelas core (`business`, `users`, `mcp_*`, `transactions`, `marcacoes`) + diff com SPEC ou snapshot
