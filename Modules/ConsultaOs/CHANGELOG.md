# CHANGELOG — Modules/ConsultaOs

Formato append-only por wave/PR relevante.

## [Wave 28] — 2026-05-17 — SATURATION FINAL functional → ≥92

### Added

- `Tests/Feature/Wave28SaturationTest.php` — D2 portal público resilience (3 casos source-level): throttle middleware na rota canônica + FormRequest dedicado em `buscar()` + Repository contract `ConsultaOsRepositoryInterface` preservado pra switch Mock↔Real US-CONSULTA-001 (1-line bind). D9 catalog confirmação 2 spans canon (`consultaos.busca_publica` Service + `consultaos.health` Command) + log estruturado Controller redacted via PiiRedactor.

### Notes

- Sub-dimensoes alvo Wave 28: D2 (+3 = portal público defesa em camadas — throttle + FormRequest + Repository contract) + D9 (+1 = catalog cobertura confirmação 2 spans + audit log Controller).
- Tier 0 ADR 0093 §"Exceção repo-wide" preservado — portal público sem `business_id` (cliente externo sem sessão). US-CONSULTA-001 quando ativar busca real DEVE resolver `business_id` via lookup protocolo + rate-limit IP (Repository injection contract estável já garante isso — só muda implementação `binding` no Provider).
- Pattern alinhado com Wave 26 (SQLite-friendly source-level + reflexão + zero hit prod).

## [Wave 27] — 2026-05-17 — POLISH final → ≥88

### Added

- `Tests/Feature/Wave27PolishTest.php` — 18 cenarios cobrindo:
  - D9.A Repository span OTel `consultaos.repository.lookup` (defesa em profundidade)
  - D5.A README portal publico completo (jornada feliz + filtro estagio + feedback + observabilidade — 10+ passos)
  - D8.A `ConsultaPorEstagioRequest` (4 cenarios — estagio in: lista + paginacao max + authorize)
  - D8.B `FeedbackPublicoRequest` (5 cenarios — numero_os + nota 1-5 + comentario + PiiRedactor doc)
- `Http/Requests/ConsultaPorEstagioRequest.php` — D8.A FormRequest scaffold US-CONSULTA-002 (lista OS por estagio). Validacao `estagio` em lista controlada + paginacao max 50/pag + por_pagina max 20 (anti-scraping).
- `Http/Requests/FeedbackPublicoRequest.php` — D8.B FormRequest scaffold US-CONSULTA-002 (feedback NPS-like). `numero_os` alpha_num + nota 1-5 + comentario nullable max:500 + PiiRedactor wraps documentado.

### Changed

- `Repositories/MockConsultaOsRepository.php` — D9.A adicionado `OtelHelper::span('consultaos.repository.lookup')` envolvendo `buscarPorNumero()`. Isolamento de latencia da fonte de dados — quando RepairConsultaOsRepository entrar (US-CONSULTA-001), span ja existe pra observabilidade SQL real.
- `README.md` — D5.A expandido "Como cliente usa portal publico completo": 4 secoes (Jornada feliz + Filtro estagio + Feedback + Operacao timeline) + secao Observabilidade defesa em profundidade (4 layers).

### Notes

- Sub-dimensoes alvo Wave 27: D9 (+2 = Repository span canon + defesa em profundidade duas layers — Service + Repository), D5 (+2 = README portal publico canonico 4 secoes), D8 (+2 = 2 FormRequests scaffold US-CONSULTA-002 ready).
- Tier 0 IRREVOGAVEL: rota publica mantida sem business_id scope (cliente externo sem sessao); FormRequests scaffolds documentam onde Repository real resolveria biz via numero_os/telefone lookup.
- Defesa em profundidade observabilidade: 4 layers spans canonicos (Repository → Service → Controller audit log → Health probes).
- bucket governance v4 mantido `functional_horizontal` em module.json.

## [Wave 25] — 2026-05-16 — SATURATION functional → ≥85

### Added

- `Tests/Feature/Wave25SaturationTest.php` — 23 cenarios cobrindo:
  - D5.B README "Como cliente usa" (4 passos canonicos + LGPD privacy contract + arquitetura D4 SoC + cita US-CONSULTA-001)
  - D5.C CustomerJourneyTest existe + cobre 9 passos canonicos (brute-force + payload limpo + filtros)
  - D9.A OtelHelper canon spans no Service (consultaos.busca_publica com atributo estagio)
  - D9.B Controller audit log estruturado com PiiRedactor + IP truncado /24 + User-Agent 80c
  - D9.C Rota pública com middleware throttle + Controller DI testável
  - D9.D ConsultaOsHealthCommand 5 probes (repository_bound, service_resolvable, retention_declared, smoke_known_ok, smoke_unknown_ok) + log estruturado + `--detail` (não `--verbose`)
  - D7 retention.php Wave 23 preservado (entities + strategy + notice_period)

### Changed

- `config/governance/module_clients.yaml` ConsultaOs promovido `backlog_hipotese` → `biz_1_wagner_active` (Wagner valida fluxo end-to-end dev — busca + audit + retention + OTel span; US-CONSULTA-001 substitui mock preservando contrato Repository).

### Notes

- Sub-dimensoes alvo Wave 25: D5 (+3 = README "Como cliente usa" canonico + CustomerJourneyTest cobertura source-level), D9 (+6 = audit log estruturado Controller + spans Service + HealthCommand 5 probes + middleware throttle confirmado em rota).
- Portal público mock-only mantido — US-CONSULTA-001 substitui MockConsultaOsRepository → RepairConsultaOsRepository (1 linha bind Provider) preservando interface ConsultaOsRepositoryInterface.
- bucket governance v4 declarado `functional_horizontal` em module.json.

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
