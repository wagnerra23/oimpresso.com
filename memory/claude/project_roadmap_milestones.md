---
name: Roadmap milestones — estratégia interleaved Laravel 9 → 13
description: Ordem de upgrade da stack alternada com entrega de UI, decidida com Wagner em 2026-04-22. Atualizado em 2026-04-23 pós-M3/M5/M8 concluídos.
type: project
originSessionId: 35b2b09f-6215-4da4-babc-740643587a77
---
Wagner pediu clareza sobre quando subir a stack. Estratégia **interleaved** (C) escolhida: alterna entrega de valor visível (telas React) com upgrades de Laravel, cada um isolado em sessão dedicada + smoke test.

## Milestones

- **M1 — AppShell + Welcome + Relatórios + Dashboard** ✅ 2026-04-22
- **M2 — Intercorrências CRUD com IA** — em aberto (será feito com Vizra ADK + Prisma)
- **M3 — Laravel 9 → 10** ✅ 2026-04-23
- **M4 — Aprovações + Espelho migradas** — em aberto
- **M5 — Laravel 10 → 11** ✅ 2026-04-23 (Cursor iniciou sessões 11+12, Claude validou e commitou)
  - Inclui migração `laravelcollective/html` → `spatie/laravel-html` via shim `App\View\Helpers\Form`
  - Inclui remoção de `openai-php/laravel`
- **M6 — Resto das 7 telas Ponto** — em aberto
- **M7 — Laravel 11 → 12** ✅ 2026-04-23 (commit `422cec54`) — só 2 breaks (spatie-backup config disks array, KeepLatestBackups void)
- **M8 — Fase 14 A+** 🟡 70% 2026-04-23 — Pest + GH Actions CI + 99 tests; falta Sentry + validar CI em PR
- **M9 — Fase 15 Fiscal** (Boleto + NFe + tributação cascata)
- **M10 — Laravel 12 → 13** ✅ 2026-04-23 (commit `361a5e56`) — DESBLOQUEADO via **inline knox/pesapal** em `app/Vendor/Pesapal` (576 LOC), remoção de `arcanedev/log-viewer` (config-only) e `barryvdh/laravel-debugbar` (dev-only). Inertia v1→v2, Passport v12→v13, Tinker v2→v3, Pest v3→v4, PHPUnit v11→v12. 99 tests passing + browser validado. Próximo: Laravel Boost + IA agentes.

## Regras de upgrade (validadas na prática em 2026-04-23)

1. **Nunca upgrade + feature nova na mesma sessão** — cada upgrade vira milestone isolado ✓
2. **Smoke test obrigatório** pós-upgrade: login + /home + views críticas (business/settings, products/create, contacts/create) ✓
3. **Rollback pronto**: branch de antes preservada ✓
4. **Dependências primeiro**: checar `nwidart/laravel-modules`, `laravel/passport`, `laravel/ui`, `barryvdh/dompdf` ✓
5. **Testes automáticos** salvaram M5 — shim Form foi validado em 25 tests + 14 views antes de mergear

## Risco estimado — updated pós-sessão

| M | Risco | Status |
|---|---|---|
| M1-M2 | 🟢 | M1 done, M2 aberto |
| M3 | 🟡 → 🟢 | DONE sem incidentes |
| M4 | 🟢 | aberto |
| M5 | 🔴 → 🟢 | DONE — Pest + shim Form previniram regressões |
| M6 | 🟢 | aberto |
| M7 | 🟡 | próximo framework upgrade |
| M8 | 🟡 | 70% done — CI workflow não testado em PR ainda |
| M9 | 🟡 | aberto |
| M10 | 🔴 → 🟢 | **DONE** — shim knox/pesapal desbloqueou; 99 tests + browser L13 verdes |
