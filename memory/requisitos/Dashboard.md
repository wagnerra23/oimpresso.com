---
module: Dashboard
status: ressuscitado
phase: F6 Soft wrapper Inertia
ressuscitado_em: 2026-05-21
last_generated: 2026-05-21
---

# Dashboard `/home` — landing page pós-login

> **Ressuscitado em 2026-05-21** pelo caminho Soft wrapper Inertia (ADR 0104 §F6). Documentação canônica vive agora em [`Dashboard/`](Dashboard/) folder.

## Onde está agora

- [Dashboard/SPEC.md](Dashboard/SPEC.md) — User Stories US-DASH-*
- [Dashboard/RUNBOOK-home-index.md](Dashboard/RUNBOOK-home-index.md) — runbook MWART F6 Soft
- [Dashboard/BRIEFING.md](Dashboard/BRIEFING.md) — 1-pager executivo

## Decisão de ressuscitar (vs deprecar)

Aprovada por Wagner 2026-05-21 — caminho A Soft wrapper. Justificativa:
- `/home` é a landing pós-login (blast radius alto, todo usuário cai aqui)
- Blade legacy 1.4k LOC + jQuery DataTables + ECharts PHP wrapper → carregamento ~3s
- Soft wrapper Inertia preserva mecanismo Blade legacy via `?legacy=1`
- Precedente PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) (mergeado mesmo dia)

---
_Atualizado por execução MWART F6 Soft em 2026-05-21._
