# CHANGELOG — Modules/NfeBrasil

Mudanças observáveis na capacidade fiscal NFe/NFC-e/NFSe. Append-only por release/wave.

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
