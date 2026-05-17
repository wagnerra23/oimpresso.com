# CHANGELOG — Modules/Ponto

Formato append-only por wave/PR relevante. Modulo Ponto Eletronico Portaria MTP 671/2021 — ex-PontoWr2 (rename PHP-only Fase 3.7 PR-2, 2026-05-06; URLs/permissions/config keys legacy `pontowr2.*` preservados).

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
