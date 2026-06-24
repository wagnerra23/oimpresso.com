# fixture — charter-live-signal (gate `status: live` precisa de sinal de prod)

Par good/bad que prova que `scripts/governance/charter-live-signal.mjs --check` **morde** (doutrina ADR 0303 — cada catraca prova ANTES de ratificar). Rodado pelo `gate-selftest.mjs` (sandbox por cwd: o script REAL roda com a fixture como `process.cwd()`).

- **good/** — `Selftest/LiveOk` é `status: live` E está em `governance/prod-flags.json` `live` → `live_ok` → **exit 0** (`✓ todo charter status: live carrega sinal`).
- **bad/** — `Selftest/LiveNoSignal` é `status: live` mas o `prod-flags.json` está vazio e não há `smoke:` → `live_sem_sinal` → **exit 1** (`⚠️ ... SEM sinal de prod`).

Origem: reconciliação Cliente 2026-06-24 — um charter foi promovido a `live` sem prova de prod e só o adversário/Wagner pegaram. Este gate mecaniza a recusa. Proposta: `memory/decisions/proposals/2026-06-24-charter-live-derivado-sinal-prod-anti-fachada.md`.
