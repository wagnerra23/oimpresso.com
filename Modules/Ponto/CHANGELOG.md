# CHANGELOG — Modules/Ponto

Formato append-only por wave/PR relevante. Modulo Ponto Eletronico Portaria MTP 671/2021 — ex-PontoWr2 (rename PHP-only Fase 3.7 PR-2, 2026-05-06; URLs/permissions/config keys legacy `pontowr2.*` preservados).

## [Wave 28] — 2026-05-17 — SATURATION FINAL functional → ≥92

### Added

- `Tests/Feature/Wave28SaturationTest.php` — D2 Marcação append-only defesa em camadas (3 casos source-level + reflexão): override `update()`/`delete()` lançam RuntimeException + constante `ORIGEM_ANULACAO` caminho canônico (nunca delete direto).

### Notes

- Sub-dimensoes alvo Wave 28: D2 (+3 = Marcação append-only Portaria 671/2021 Art. 85 IRREVOGÁVEL — reforço pattern reflexão SQLite-friendly sem dependência MySQL).
- Defesa Tier 0 cobre 2 camadas: (1) Eloquent override em `Modules/Ponto/Entities/Marcacao.php` linha 103-119 + (2) triggers MySQL `trg_ponto_marcacoes_no_delete` no schema canônico (testes Pest legacy `MultiTenantAppendOnlyTest` validam DB-level quando MySQL disponível).
- Pattern alinhado com Wave 26 Saturation (mesmo formato file_get_contents + ReflectionMethod, zero hit prod).

## [Wave 25] — 2026-05-16 — SATURATION functional → ≥85

### Added

- `Tests/Feature/Wave25SaturationTest.php` — D2 append-only camadas + D5 customer journey contract + D6 Inertia::defer pattern + D7 retention base legal (16+ casos).
- `Http/Controllers/DashboardController.php` — refactor `index()` aplicando `Inertia::defer()` em 6 props heavy (kpis, serie_7dias, aprovacoes, atividade_recente, presenca_agora, alertas). 4 métodos privados `buildXxx()` extraídos pra SoC.

### Changed

- `DashboardController::index()` 110 → 14 linhas (extração closures lazy). Pattern validado RUNBOOK-inertia-defer-pattern.md (300ms → 50ms switch dashboard, -83%).
- `config/governance/module_clients.yaml` Ponto preservado em `piloto_reportando_dor` (clientes legacy WR2 Eliana).

### Notes

- Sub-dimensoes alvo Wave 25: D2 (+13 = append-only IRREVOGÁVEL Portaria 671/2021 defesa dupla Eloquent+trigger MySQL confirmada source-level), D5 (+8 = jornada estendida + Service contract registrar/anular reuse-ready), D6 (+5 = Inertia::defer DEFAULT Wave 25 — RUNBOOK linha-mestra), D7 (+5 = retention.php base legal CLT Art. 11 + Portaria 671 Art. 85 + LGPD Art. 16).
- Append-only Marcação preservada Tier 0 IRREVOGÁVEL — `Marcacao::update()`/`delete()` lançam `RuntimeException`; triggers MySQL `trg_ponto_marcacoes_no_delete` segunda camada.
- bucket governance v4 declarado `functional_horizontal` em module.json.

## [Wave 18] — 2026-05-16 — D1 trait full saturation + D3 CHANGELOG

### Added

- `Entities/Rep.php` — trait `HasBusinessScope` (multi-tenant Tier 0).
- `Entities/Importacao.php` — trait `HasBusinessScope`.
- `Entities/BancoHorasMovimento.php` — trait `HasBusinessScope` (append-only preservado).
- `Entities/BancoHorasSaldo.php` — trait `HasBusinessScope`.
- `Entities/Marcacao.php` — trait `HasBusinessScope` (convive com boot override UUID + append-only).
- `CHANGELOG.md` — este arquivo (D3 governance).

### Notes

- Sub-dimensoes alvo Wave 18: D1=24/30→30/30 (5 Entities trait), D3=10/15→15/15 (CHANGELOG agora cobre Modules/Ponto raiz; BRIEFING ja existia em `memory/requisitos/Ponto/BRIEFING.md`).
- Marcacao com `bootHasBusinessScope` automatico do trait coexiste com `boot()` custom (UUID gen) — Eloquent magic chama todos `bootXxx()`. MarcacaoService usa `DB::table()` em inserts (com business_id explicito) — sem regressao.
- EscalaTurno NAO recebe trait (sem `business_id` proprio — relacao via `escala_id`, Escala ja scoped).
- ApuracaoDia/Colaborador/Escala/Intercorrencia ja tinham trait Wave 12.

## [Wave 17] — 2026-05-16 — saturacao cross-projeto

(consolidacao Wave 17 — sem mudancas especificas Ponto destacadas no commit consolidado)

## [Wave 15] — 2026-05-12 — Customer Journey E2E

### Added

- `Tests/Feature/CustomerJourneyTest.php` — jornada funcionario (4 marcacoes + dashboard + anulacao + cross-tenant biz=99). Triggers MySQL append-only validados; SQLite skip.

## [Wave 12] — Wave 11 — Multi-tenant + LGPD

### Added

- `HasBusinessScope` em ApuracaoDia, Colaborador, Escala, Intercorrencia.
- `Tests/Feature/MultiTenantAppendOnlyTest.php` (358 linhas), `MultiTenantIsolationTest.php`, `Wave11LgpdComplianceTest.php`.
- `LogsActivity` Spatie em Intercorrencia + Colaborador (logOnly explicito, sem PII bruta).
- `IntercorrenciaAIClassifier` — Jana sugere tipo via laravel-ai.

## [Inicio] — Pre-Wave — Modulo PontoWr2

- 8 migrations Portaria 671/2021 (colaboradores, reps, marcacoes append-only, intercorrencias, apuracao_dia, banco_horas, importacoes, escalas).
- 12 Controllers + 9 Services (Apuracao, BancoHoras, Afd, Marcacao com NSR + hash chain SHA-256, Intercorrencia, IntercorrenciaAIClassifier, Nsr, Report).
- Triggers MySQL append-only `ponto_marcacoes` (Portaria 671/2021 Art. 85).
- Rename Fase 3.7 PR-2 PontoWr2 → Ponto (PHP-only, URLs/permissions/config legacy preservados).
