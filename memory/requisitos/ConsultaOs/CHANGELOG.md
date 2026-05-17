# Changelog — Modules/ConsultaOs

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - Wave 18 RETRY (2026-05-16)

### Confirmado / Reforçado

- **D4 Service extraction** — `ConsultaOsMockService` + `ConsultaOsRepositoryInterface` + `MockConsultaOsRepository` (Wave 18 anterior) reconfirmados; Controller delega busca + filtragem (SoC Constituição v2 §5). Swap fonte mock → real via 1 linha no Provider.
- **D5 Customer Journey** — `CustomerJourneyTest.php` cobre 9 passos jornada cliente público (acessa portal → busca OS → filtros → 404 limpo → brute-force bloqueado → throttle ativo). Mantém contrato P0 da rota pública.
- **D7 LGPD** — `Config/retention.php` (consulta_os_logs 365d), `LgpdComplianceTest.php` cobre PII redaction via `PiiRedactor` + IP truncation /24.
- **D8 segurança** — `ConsultaPublicaRequest` (FormRequest) valida `alpha_num + max:20` anti-enumeration; throttle ativo na rota.
- **D9 observabilidade** — span `consultaos.busca_publica` via `OtelHelper::span` (rota pública não tem session, business_id intencionalmente omitido).

### Notes

- Mock-only até US-CONSULTA-001 substituir `MockConsultaOsRepository` por query real `Modules/Repair`
- Grade target Wave 18 RETRY: 63 (D4=5/20+, D5=3/15+, D9=4/7+)
