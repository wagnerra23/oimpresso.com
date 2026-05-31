# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md). Como reportar: [PROTOCOL.md §10.2](PROTOCOL.md).

---

## Estado atual: 2026-05-31 — DS adoção · Onda 1 MERGED · autonomia ativada

**Onda 1 entregue e mergeada em `main`** (autônomo · gates CI = [W2]):
- **Onda G / Fase C — badge variants** (PR #2025 · main `f3001f0e0`): +5 variants soft (`success/warning/danger/info/neutral`) no `badge.tsx` + story `_Showcase 3b`. Destrava o lote-badge (Fase D).
- **Fase A — Sells** (PR #2026 · main `8d7c45507`): controles + rounded + FieldSuccess T1, **42→17 `ds/*`** (10 select→Select, 4 checkbox→Checkbox, 9 rounded-xl→lg, 2 FieldSuccess). Os 17 restantes = **Tipo 2** (status-badge/Alert/icon) → **Fase D**, não Fase A. eslint-baseline 1373→1348.

**Mudança de modo (2026-05-31):** Wagner pediu **zero intervenção humana**. Gate visual [W2] manual → delegado aos **gates CI** (PR UI Judge Sonnet 4.5 + visual-regression, ambos green). Merge autônomo via `gh --admin` (CI verde = o gate). Playbook + custo-benefício em **[AUTOMACAO-LOOP-AUTONOMO.md](AUTOMACAO-LOOP-AUTONOMO.md)**.

**Placar** (`npm run ds:report` · ver [DS_ADOCAO_INDICE.md](DS_ADOCAO_INDICE.md)): Sells 17 · RecurringBilling 58 · OficinaAuto 44 · Repair 14 · Purchase 19 · Admin 23 · Whatsapp 21 · Settings 18 · Financeiro 84 · Cliente 62 (+ fora-da-fila).

### Fila Fase A (controles + FieldError/Success T1)
| Módulo | Fase A | Nota |
|---|---|---|
| Sells | ✅ MERGED | restam 17 Tipo-2 → Fase D |
| **RecurringBilling** | 🎯 **próximo** | Planos Create/Edit (ciclo/intervalo/gateway); billing não pode quebrar |
| OficinaAuto | fila | DnD Kanban; `ServiceOrderStatusBadge` = Tipo 2 (deixa) |
| Repair | fila | mobile-first ≥44px; JobSheet/DeviceModels |
| Purchase | fila | Create/Edit/Index/Show |
| Admin · Whatsapp · Settings | fila | descobrir com eslint; Settings toggles→`<Switch>` |
| Financeiro · Cliente | fila (só FieldError T1) | controles já migrados (PR-C1/C2) |

Depois da Fase A → **Onda G / Fase D (lote-badge)**: migra os Tipo-2 pros `<Badge variant>` (as variants já existem desde #2025). É o grosso do drift (`no-adhoc-status-text`).

### O que [CL] faz agora
Disparar **Fase A RecurringBilling** no mesmo loop autônomo (1 módulo = 1 branch = 1 PR · CI verde → merge → sync → próximo).

### 🔑 Bootstrap p/ tirar o `--admin` (decisão Wagner)
Provisionar token do `grokwr2` (collaborator ≠ autor) → Action auto-approve+merge quando todos os checks passam. Ver [AUTOMACAO-LOOP-AUTONOMO.md §3](AUTOMACAO-LOOP-AUTONOMO.md).

### Workstreams parados (ponteiro)
- **Jana** (`Chat.tsx`/`Cockpit.tsx`) — congelado até Wagner reabrir.
- **Financeiro** Fluxo/Plano-contas/DRE/Conciliação — bloqueados por ADRs arq + migrations.
