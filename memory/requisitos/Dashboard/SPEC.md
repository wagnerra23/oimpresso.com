# Modules/Dashboard — Especificação (TODO/AUSENTE)

> **Status (2026-04-27):** módulo **NÃO EXISTE** na branch `6.7-bootstrap`.
> Existia em `3.7-com-nfe` (UltimatePOS 3.7) e foi **perdido na migração 3.7 → 6.7**.
> O 6.7 tem `CustomDashboard` (parte do upstream) e `DashboardController`s
> internos por módulo (Essentials, Financeiro, Crm, Repair, etc.) — não há
> um módulo Dashboard separado.

---

## Contexto

Em `memory/claude/preference_modulos_prioridade.md` (2026-04-22):

| Módulo | Existia em 3.7 | Existia no backup main-wip | Decisão |
|--------|----------------|----------------------------|---------|
| **Dashboard** | sim | sim | Overlap com `CustomDashboard` (existe em 6.7) — comparar |

## DashboardControllers já cobertos no lote 5

Mesmo sem o módulo Dashboard separado, já temos cobertura dos
DashboardControllers de outros módulos:

| Localização                                                    | Coberto por |
|----------------------------------------------------------------|-------------|
| `Modules/Essentials/Http/Controllers/DashboardController.php`  | `Modules/Essentials/Tests/Feature/DashboardControllerTest.php` (lote 5) |
| `Modules/Financeiro/Http/Controllers/DashboardController.php`  | (próximos lotes) |
| `Modules/Crm/Http/Controllers/DashboardController.php`         | (próximos lotes) |
| `Modules/Repair/Http/Controllers/DashboardController.php`      | (próximos lotes) |
| `Modules/PontoWr2/Http/Controllers/DashboardController.php`    | (cobertura via PontoWr2 batch) |
| `Modules/MemCofre/Http/Controllers/DashboardController.php`    | (próximos lotes) |
| `Modules/Accounting/Http/Controllers/DashboardController.php`  | (próximos lotes) |
| `Modules/Copiloto/Http/Controllers/DashboardController.php`    | (próximos lotes) |

## Próximos passos sugeridos

1. **Confirmar com Wagner** se o "Dashboard" do 3.7 era um agregador
   cross-módulo (parecido com `CustomDashboard` upstream) ou um ERP
   visual diferenciado.
2. **Avaliar overlap com `CustomDashboard`** — pode não fazer sentido
   restaurar.
3. **Se vale restaurar:** cherry-pick de `3.7-com-nfe` + adapt para
   Inertia/React.

## TODO

- [ ] Decisão Wagner: restaurar Dashboard (3.7) vs investir em CustomDashboard?
- [ ] Caso restaurar: ADR + tests no formato Essentials.
- [ ] Cobrir os DashboardControllers restantes (lotes futuros).
