# OficinaAuto — Changelog

## [Wave 18 RETRY — 2026-05-16] Saturação governance v3 — D2/D4/D5/D9 +Δ

### D4 Architecture — Service extraction (RETRY +1 service)
- `Services/Producao/CapacidadeService.php` — calcula capacidade ocupada/disponível da oficina (heurística V0: aberta=4h, em_servico=6h) + taxa de ocupação + status thresholds (ociosa/normal/apertada/lotada/overcommit) + decisão "pode aceitar nova OS?". 5 spans canon `oficinaauto.producao.*`. Pronto pra migrar pra DB-real quando US-OFICINA-007 entregar `duration_estimate_hours`.

### D9 Observabilidade (RETRY +5 spans)
- `oficinaauto.producao.capacidade_ocupada_hoje`
- `oficinaauto.producao.capacidade_disponivel_hoje`
- `oficinaauto.producao.taxa_ocupacao`
- `oficinaauto.producao.pode_aceitar_nova_os`
- `oficinaauto.producao.resumo_capacidade`

Total spans canon do módulo (cumulativo Wave 18+RETRY): 14.

### D2 Pest novo (RETRY +2 arquivos)
- `Tests/Feature/CapacidadeServiceTest.php` — 10 cenários: Container resolve, source-grep 5 spans, DB vazio, soma heurística, taxa %, thresholds status, podeAceitarNovaOs, clamp ≥0, anti div-by-zero, exception preservada.
- `Tests/Feature/E2EJourneyMartinhoBiz1Test.php` — 4 cenários E2E: journey full vehicle→OS orçamento→token→PIN, capacidade 3 abertas+1 em_servico = 18h, cross-biz isolation count, Container resolve 4 services.

### D5 Cliente real / Journey (RETRY)
- E2E formalizado em `E2EJourneyMartinhoBiz1Test.php` cobre o journey README de ponta a ponta (passos 1-6: criar veículo → abrir OS → gerar token → validar PIN one-shot).

## [Wave 18 — 2026-05-16] Saturação governance v3 (inicial)

### D4 Architecture — Service extraction
- `Services/VehicleQueryService.php` — extrai listagem/contagem/busca de Vehicle do `VehicleController`. Stateless + multi-tenant Tier 0 via global scope. 3 spans canon `oficinaauto.vehicle.*`.
- `Services/ServiceOrderSummaryService.php` — KPIs dashboard combinada (locação + manutenção + concluida_mes + atrasada) + contagem por status + próximas a vencer. 3 spans canon `oficinaauto.so.*`.

### D9.a Observabilidade — spans em AprovacaoOsService
- `gerarTokenAprovacao` → span `oficinaauto.aprovacao.gerar_token` (attributes: business_id + os_id + module, SEM PII)
- `validarToken` → span `oficinaauto.aprovacao.validar_token` (extraído pra `validarTokenInterno`)
- `validarPin` → span `oficinaauto.aprovacao.validar_pin`

Total spans novos: 9 (cobre D9 +5).

### D2 Pest novo
- `Tests/Feature/ServicesObservabilityTest.php` — 8 cenários: DI Container resolve 3 services, source-grep confirma `App\Util\OtelHelper` canon, span name prefix `oficinaauto.`, fail-soft schema ausente, exception preservada.
- `Tests/Feature/AprovacaoOsTokenTest.php` — 8 cenários edge: token malformado, tamper HMAC, lockout 5 tentativas, reset em nova geração, PIN não-numérico, one-shot consumption, multi-tenant isolation cross-business, status não-orcamento rejeitado.

### D5 Cliente real / Journey
- `README.md` criado — journey 6 passos biz=1 (login → criar veículo → OS → FSM → WhatsApp aprovação) + cliente piloto Martinho + permissões Spatie per ADR 0093.

## Histórico anterior

(ver `memory/requisitos/OficinaAuto/SPEC.md` pra histórico Wave 5-A schema multi-vertical + demo Martinho 2026-05-13)
