---
name: Roadmap milestones — estratégia interleaved Laravel 9 → 13
description: Ordem de upgrade da stack alternada com entrega de UI, decidida com Wagner em 2026-04-22
type: project
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Wagner pediu clareza sobre quando subir a stack. Estratégia **interleaved** (C) escolhida: alterna entrega de valor visível (telas React) com upgrades de Laravel, cada um isolado em sessão dedicada + smoke test.

## Milestones

- **M1 — AppShell + Welcome + Relatórios + Dashboard** (atual, 2026-04-22)
- **M2 — Intercorrências CRUD com IA classificador** (demo "wow")
- **M3 — Laravel 9 → 10** (breakings leves, ~1-2 sessões)
  - PHP 8.1+ mínimo (já OK)
  - Poucos ajustes no código UltimatePOS
  - Dependências conflitantes precisam update
- **M4 — Aprovações + Espelho migradas**
- **M5 — Laravel 10 → 11** (🔴 salto grande, 2-3 sessões)
  - `Kernel.php` removido → `bootstrap/app.php` unificado
  - `HandleInertiaRequests` precisa reregistro em novo lugar
  - Middleware groups configurados em lugar novo
  - Config files consolidados
  - `nwidart/laravel-modules` precisa upgrade
  - **Testes (Pest) ajudam muito aqui** — considerar fase 14 antes se não estiver confiante
- **M6 — Resto das 7 telas Ponto** (3 sessões incrementais)
- **M7 — Laravel 11 → 12** (Starter Kit oficial vira realidade)
  - PHP 8.2+ mínimo
  - Adotar componentes shadcn oficiais (substituir custom)
  - Inertia + React + TW4 são *default* do framework
- **M8 — Fase 14 A+** (Sentry, Pest, GH Actions CI/CD)
- **M9 — Fase 15 Fiscal** (Boleto eduardokum + NFe sped + tributação cascata)
- **M10 — Laravel 12 → 13 + Boost + IA agentes**
  - Laravel Boost maduro (MCP)
  - Alvo final: IA-first no core

## Regras de upgrade

1. **Nunca upgrade + feature nova na mesma sessão** — cada upgrade vira milestone isolado
2. **Smoke test obrigatório** pós-upgrade: login + /home + /ponto/react + /ponto/relatorios + criar venda + login/logout
3. **Rollback pronto**: branch de antes do upgrade preservada por 1 semana
4. **Dependências primeiro**: conferir `nwidart/laravel-modules`, `laravel/passport`, `laravel/ui`, `barryvdh/dompdf` compat antes de upgrade do core
5. **Testes automáticos** entram firme a partir do M5 — upgrade 11 sem Pest é muito arriscado

## Risco estimado por milestone

| M | Risco | Principal fonte |
|---|---|---|
| M1-M2 | 🟢 baixo | só UI nova, não toca legado |
| M3 | 🟡 médio | deps do composer podem conflitar |
| M4 | 🟢 baixo | só UI nova |
| M5 | 🔴 alto | Kernel → bootstrap/app.php, middleware reorganiza |
| M6 | 🟢 baixo | só UI nova |
| M7 | 🟡 médio | Starter Kit traz mudanças de convenção |
| M8-M9 | 🟡 médio | depende de pacotes externos |
| M10 | 🟢 baixo | 12→13 é evolução menor, Boost é aditivo |

Total estimado: ~25 sessões.
