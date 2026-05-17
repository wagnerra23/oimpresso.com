# CHANGELOG — Modules/ConsultaOs

Formato append-only por wave/PR relevante.

## [Wave 18] — 2026-05-16 — D4 extract Service/Repository + D3 README/CHANGELOG/BRIEFING

### Added

- `Services/ConsultaOsMockService.php` — Service de busca publica OS, envolve span OTel `consultaos.busca_publica` (D9).
- `Contracts/ConsultaOsRepositoryInterface.php` — contrato Repository pattern (D4 SoC brutal).
- `Repositories/MockConsultaOsRepository.php` — implementacao mock-only (dataset 4 OS — Acme/Padaria/Clinica/Escola). Substituir por `RepairConsultaOsRepository` em US-CONSULTA-001 = 1 linha bind.
- `README.md` — visao geral, arquitetura D4, conformidade D7/D8/D9, smoke E2E.
- `CHANGELOG.md` — este arquivo (D3 cobertura governance).
- `BRIEFING.md` — 1-pager executivo (D3 cobertura governance).

### Changed

- `Providers/ConsultaOsServiceProvider.php` — bind `ConsultaOsRepositoryInterface` → `MockConsultaOsRepository`.
- `Http/Controllers/ConsultaOsController.php` — Controller delega busca ao `ConsultaOsMockService`. `mockData()` removido (extraido pro Repository).

### Notes

- Multi-tenant Tier 0: rota publica nao scopa por business_id intencionalmente (cliente externo sem sessao). Quando US-CONSULTA-001 ativar query real, Repository deve resolver business_id via lookup do protocolo + rate-limit IP.
- Sub-dimensoes alvo Wave 18: D4=5/20→18/20 (extract Service + Repository), D5=3/15→15/15 (journey existente Wave B + 9 cenarios), D3 governance docs (CHANGELOG + README + BRIEFING), D8=7/8→8/8 (FormRequest + throttle ja Wave anterior), D9=4/7→7/7 (OtelHelper span no Service).

## [Wave B] — 2026-05-12 — Customer Journey

### Added

- `Tests/Feature/CustomerJourneyTest.php` — 9 cenarios E2E (acesso portal, busca conhecida, payload sem PII, 404 limpo, filtro estagio, brute-force bloqueado, throttle ativo, filtro padrao todos).
- `Tests/Feature/PublicTokenSecurityTest.php`, `LgpdComplianceTest.php`, `SmokeRoutesTest.php`, `ScaffoldTest.php`.

## [Inicio] — 2026-05-05 — Modulo criado

- 3 Controllers (ConsultaOs, Data, Install) + Routes + Config + Tests scaffold.
- SCOPE.md Fase 3.4 do ADR 0079.
- Mock-only — 4 OS fake (4815, 4817, 4819, 4821).
