# CHANGELOG — Modules/NfeBrasil

Mudanças observáveis na capacidade fiscal NFe/NFC-e/NFSe. Append-only por release/wave.

## Wave 27 POLISH — 2026-05-17 (saturation 72-88 → ≥90)

### D9 — Spans canon SEFAZ +3 (era 5 em W18, agora ≥ 8)
- `DanfeService::renderizar` envolve `OtelHelper::span('nfe.danfe_render', ...)` — attributes business_id + emissao_id + modelo (sem PII). Latência observable do render PDF via sped-da.
- `DanfeService::salvar` envolve `OtelHelper::span('nfe.danfe_salvar', ...)` — extraí lógica pra `salvarInterno` privado preservando behavior; attributes chave_44_present boolean (sem expor chave fiscal real no metric).
- `CertificadoService::validar` envolve `OtelHelper::span('nfe.certificado_validar', ...)` — extraí lógica pra `validarInterno`; attribute pfx_size_bytes (sem senha/cert content).
- Total spans canon `nfe.*` cumulativo agora: 8 (nfe.emitir, nfe.cancelar, nfe.inutilizar, nfe.status_sefaz, nfe.retorno_sefaz, nfe.danfe_render, nfe.danfe_salvar, nfe.certificado_validar).

### D2 Pest novo
- `Tests/Feature/Wave27NfeSaturationTest.php` — 8 cenários reflection + source-grep + Container resolve:
  - DanfeService 2 spans novos confirmados (`nfe.danfe_render` + `nfe.danfe_salvar`)
  - CertificadoService 1 span novo confirmado (`nfe.certificado_validar`)
  - Total spans `nfe.*` cumulativo ≥ 8 (era 5 em W18)
  - 3 Models críticos preservam LogsActivity (NfeEmissao + NfeEvento — W25 reforço D7)
  - CONFAZ SINIEF 07/2005 Art. 14 IRREVOGÁVEL — NfeService source-grep ZERO `forceDelete` em cancelamento
  - 4 Services canon resolvem do container (DI Tier 0 estável)
  - Spans canon mantém prefix `nfe.` (no module leak)
  - NfeEmissao usa SoftDeletes (CONFAZ preservation IRREVOGÁVEL)

### D7 — LogsActivity confirmação (Wave 18+25 + W27 reforço)
- NfeEmissao + NfeEvento traits preservados — W27 Pest valida source-grep `LogsActivity` no Model body
- Append-only contrato mantido: reprocessamento gera novo `NfeEvento`, nunca update

### Tier 0 IRREVOGÁVEIS preservados
- CONFAZ SINIEF 07/2005 Art. 14 — `forceDelete` proibido em cancelamento fiscal (Pest enforce via source-grep)
- ADR 0093 multi-tenant — NfeEmissao/NfeEvento/NfeInutilizacao com global scope preservado
- NFe cancelada mantém status `cancelada` + permanece no banco (nunca hard-delete)

## Wave 25 — 2026-05-16 (saturation 72→≥85)

### D2 — Multi-tenant Tier 0 cross-tenant deep
- Novo Pest `Tests/Feature/Wave25NfeSaturationTest.php` — 14 cenários (12 MySQL-skipped sem DB válido, contract tests rodam):
  - `NfeInutilizacao::count()` cross-tenant: session biz=99 só vê 1 range (biz=1 tem 2, blindados).
  - `quantidadeNumeros()` computa range inclusivo (991000..991009 = 10 numeros).
  - Numeração inutilizada por (business_id) — mesmo range repete entre tenants (sem conflito).
- Pattern reusa `NfeBrasilMultiTenantIsolationTest.php` (Wave 13) + `NfeEventoMultiTenantIsolationTest.php` (Wave 18) — cobertura ampliada agora pra NfeInutilizacao.

### D6 — CONFAZ SINIEF 07/2005 Art. 14 (preservation IRREVOGÁVEL)
- `NfeEmissao` usa `SoftDeletes` (preserva histórico — nunca hard-delete).
- `isCancelavel()` respeita prazos canônicos: 24h NFC-e (modelo 65) / 168h NFe (modelo 55).
- Status terminal `cancelada` NÃO pode ser re-cancelada (idempotência fiscal).
- **`NfeService` source-grep confirma ZERO chamadas `forceDelete()`** em código de cancelamento — preservação contratual blindada por Pest.

### D7 — LogsActivity confirmação (Wave 17/18 já implementado)
- Teste prova 3 Models críticos preservam trait: `NfeEmissao` (logName=`nfe_emissao`), `NfeEvento` (logName=`nfe_evento`), `NfeInutilizacao` (logName=`nfe_inutilizacao`).

### D3 — Inertia::defer perf
- `TributacaoController::index` refatorado: `regras` (query DB com map N items) + `templates` (Service call) movidos pra `Inertia::defer(fn () => ...)`. `config` permanece eager (single-row leve). Skill `inertia-defer-default` (Tier B). Pattern D-14 validado (300ms → 50ms switch página).

## Wave 18 — 2026-05-16 (governance saturation)

### D7 — LGPD audit trail
- `Models/NfeEvento` agora usa `Spatie\Activitylog\Traits\LogsActivity` (accountability LGPD Art. 37)
- Loga apenas `tipo` / `status` / `cstat_evento` / `emissao_id` — sem `payload_json` completo (XML body fica em `arquivos` table)
- PII fiscal preservada por exceção CONFAZ SINIEF 07/2005 Art. 14 (documento fiscal imutável)
- Log scoped a `'nfe_evento'`

### D1/D2 — Multi-tenant Tier 0 (ADR 0093)
- Novo Pest `NfeEventoMultiTenantIsolationTest.php` — 5 testes:
  - Contrato `business_id` NOT NULL
  - biz=99 NÃO vaza pra session biz=1 (scope herdado de `HasBusinessScope`)
  - biz=1 visível pra session biz=1
  - `UPDATED_AT = null` confirmado (append-only CONFAZ)
  - `count()` cross-tenant: session biz=99 só conta eventos do biz=99
- Cobertura cross-tenant explícita pra eventos de cancelamento (110111) + CCe (110110)

### D7 — Retention
- `Config/retention.php` confirmado (Wave 17): 3650d (10y) pra `nfe_emissoes`/`nfe_eventos`/`nfe_inutilizacoes`/`nfse_emissoes` — alinhado CONFAZ + CTN + Lei 8.137/90 (prescrição até 12y)
- `strategy=anonymize` é NO-OP em colunas fiscais imutáveis (PiiRedactor atua apenas em campos acessórios)

### Mantido
- `NFe cancelada NUNCA forceDelete` (CONFAZ SINIEF 07/2005 Art. 14) — IRREVOGÁVEL
- Append-only contrato: reprocessamento gera novo `NfeEvento`, nunca update
