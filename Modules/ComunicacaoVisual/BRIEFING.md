# BRIEFING — Modules/ComunicacaoVisual

> Estado consolidado da capacidade (1 página executiva, atualizado por PR).
> Vertical: gráfica rápida BR · CNAE 1813-0/01 · Status: 🟡 construção (piloto Q3/2026)
> Última atualização: **2026-05-16** (Wave 18 — saturação governance)

## 1. Para que serve

ERP vertical para gráficas rápidas / comunicação visual BR (lona, fachada, plotter, banner, adesivo). Cobre orçamento por m² → ordem de produção (PCP) → apontamento de máquina → NFSe/NFe → entrega/instalação. Diferencial: cálculo m² automático + PCP gráfico FSM + dual-doc fiscal NFe55+NFSe56 paralelo + NFe-de-boleto-pago + IA conversacional (Jana) — 5 capacidades juntas que nenhum concorrente tem completas (Mubisys, Zênite, Calcgraf cobrem 2-3 cada).

## 2. Cliente piloto (status)

| # | Cliente | Status | Ativação |
|---|---------|--------|----------|
| - | (nenhum em prod ainda) | 🟡 candidatos identificados | Q3/2026 |

**Pipeline candidatos:** 6 OfficeImpresso legacy saudáveis — Vargas, Extreme, Gold (confirmado vertical comvis), Zoom, Fixar, Mhundo, Produart. Sinal qualificado pendente (ADR 0105). Sem cliente reportando dor = backlog hipótese (D5=3/15).

## 3. Capacidades implementadas

| US | Capacidade | Status |
|----|-----------|--------|
| US-COMVIS-001 | Scaffold módulo + migrations + ServiceProvider | ✅ done |
| US-COMVIS-002 | Migration `comvis_orcamentos` + cálculo m² + multi-tier price | ✅ done (legacy Sprint 1) |
| US-COMVIS-003 | OrcamentoCalculator (área × preço/m² + acabamentos + instalação) | ✅ done |
| US-COMVIS-004 | ApontamentoTracker (drift m² produzido vs orçado) | ✅ done |
| US-COMVIS-NEW-001..010 | FSM canon 16 stages + 30+ actions + 10 roles | ✅ done (Sprint 1) |
| US-COMVIS-005 | Pages Inertia próprias (orçamento, PCP, apontamento) | 🟡 TODO Sprint 2 |
| US-COMVIS-006 | Integração NfeBrasil (NFe-de-boleto-pago automática) | 🟡 TODO Sprint 2 |
| US-COMVIS-LGPD-001 | Retention policy + audit log + LGPD compliance | ✅ done (Wave 18) |

## 4. Multi-tenant + Tier 0

- 100% das entities (`Orcamento`, `OrcamentoItem`, `Os`, `Apontamento`, `Material`, `Substrato`, `Acabamento`, `Instalacao`, `InstalacaoCatalogo`, `OrdemProducao`) com `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Pest cross-tenant biz=1 vs biz=99 em `Tier0GuardTest.php` (Wave 16) — 100% verde
- Append-only `comvis_apontamentos` (sem SoftDeletes) — registro legal de produção

## 5. FSM Canon ([ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

`cv_ordens_producao.current_stage_id` consome `sale_processes` shared. 16 stages canônicos: `arte_aprovada → corte → impressao → laminacao → acabamento → conferencia → faturamento → expedicao → instalacao_agendada → instalacao_em_curso → entregue → concluida` + terminais (`cancelada`, `on_hold`). Trait `GuardsFsmTransitions` bloqueia UPDATE direto.

## 6. Observabilidade (D7)

- `OtelHelper` (canônico `App\Util\OtelHelper`) instrumentação span/counter em ApontamentoController + OrcamentoController
- `ObservabilityTest.php` verifica spans `comvis.apontamento.start`, `comvis.orcamento.calculate`
- `LgpdComplianceTest.php` verifica retention.php + LogsActivity nas 3 entities core

## 7. Concorrentes (Capterra reference)

| Concorrente | Pontos fortes | Gap vs nós |
|-------------|---------------|-----------|
| Mubisys | Cálculo m² maduro + base instalada gráficas SP | Sem IA, sem dual-doc, sem multi-tenant Tier 0 |
| Zênite | PCP visual robusto | Sem cálculo m² automático, sem NFe-boleto-pago |
| Calcgraf | Calculadora simples gratuita | Sem PCP, sem apontamento, sem FSM |

## 8. Próximos 30 dias

1. Aguardar cliente piloto reportar dor concreta (Gold confirmado preferência — Wagner outreach)
2. Pages Inertia (`resources/js/Pages/ComVis/Orcamento/*.tsx`) com charter MWART F1.5 visual gate
3. NfeBrasil integração (US-COMVIS-006)

## 9. Não-goals (NÃO codificar até sinal)

- ❌ Features sem cliente piloto pagante ([ADR 0105](../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
- ❌ Duplicar Modules/Repair Kanban (consome via repair_settings)
- ❌ Substituir núcleo UltimatePOS transactions/contacts

## 10. Health-check

- 6 Pest test suites — `MultiTenantTest`, `Tier0GuardTest`, `OrcamentoCalculatorTest`, `OrcamentoControllerTest`, `ApontamentoControllerTest`, `ApontamentoTrackerTest`, `MaterialSeederTest`, `MigrationsTest`, `ObservabilityTest`, `LgpdComplianceTest`, `CustomerJourneyTest` (Wave 18)
- Comando demo: `php artisan comvis:seed-demo --business=99 --reset` (idempotente)
