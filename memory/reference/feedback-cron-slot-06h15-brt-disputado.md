---
id: reference-feedback-cron-slot-06h15-brt-disputado
type: feedback
domain: scheduling
date: 2026-05-28
discovered_in: ADR 0216 Drift Framework wire-up (PR #1874)
status: confirmed
---

# Slot cron `06:15 BRT` em Kernel.php tem 4 schedules disputando — escolher alternativo

## Inventário slot 06:00-08:00 BRT (validado 2026-05-28)

| Hora BRT | Comandos | Disponibilidade |
|---|---|---|
| 06:00 | `jana:health-check --notify` | usado |
| 06:05 | `module:grade-snapshot` | usado |
| **06:15** | `jana:system-audit --notify` + `governance:detect-drift` + `secrets:audit --auto-pr --notify` + `nfebrasil:dist-dfe-puxar` | **🔴 4 disputando — NÃO adicionar mais** |
| 06:20 | `mcp:tasks:health-check` | borderline (1 schedule) |
| 06:30 | `charter:health --notify` + `arquivos:health-check --alert` + `sells:smoke-daily` | usado (3 — saudável) |
| **06:35** | livre | ✅ **escolha canônica novos schedules** |
| **06:45** | livre | ✅ alternativa |
| 07:00 | `governance:scorecard-snapshot` + `brief:generate` | usado |

## Por que 06:15 está disputado

Tradição histórica: brief Jana regenera 06:00 → schedules pós-brief lambem dados frescos → "06:15 = primeira janela útil". Cada feature nova foi adicionando ao 06:15 sem conferir contenção.

Risco real: 4 commands rodando concurrentemente em CT 100 → DB connection pool stress + timeout em comando mais lento (`nfebrasil:dist-dfe-puxar` faz HTTP externo SEFAZ).

## Como aplicar

Ao adicionar `$schedule->command('...')->dailyAt('HH:MM')` em `app/Console/Kernel.php`:

1. **Default novo schedule diário pós-brief:** `06:35` ou `06:45`
2. **Sempre** acompanhar com `->onOneServer()->withoutOverlapping(60)->environments(['live'])`
3. **Sempre** adicionar `->onFailure(fn() => Log::channel('single')->error(...))`
4. **Nunca** adicionar mais ao 06:15 sem decisão arquitetural explícita

## Validado

PR #1874 (ADR 0216 Drift Framework) ship `governance:audit --all --notify` em 06:35 BRT — primeira ocupação do slot livre.

## Refs

- `app/Console/Kernel.php:716+` — schedule canon governance:audit
- ADR 0216 §Plano implementação D5 — slot decision
- Dossier `memory/sessions/2026-05-28-como-integrar-governance-framework.md` §1.3 — inventário schedule completo
