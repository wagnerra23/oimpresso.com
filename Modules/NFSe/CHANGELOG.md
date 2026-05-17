# CHANGELOG — Modules/NFSe

Mudanças observáveis na capacidade NFSe (ISSQN municipal). Append-only por release/wave.

## Wave 18 — 2026-05-16 (governance saturation)

### D1 — Multi-tenant Tier 0 (ADR 0093)
- Novo Pest `NfseCertificadoMultiTenantIsolationTest.php` — 3 testes:
  - Alias `NfseCertificado` herda scope `HasBusinessScope` corretamente (biz=99 não vaza pra session biz=1)
  - `isExpirado()` consistente com `isVencido()` do pai `NfeCertificado`
  - Contrato `business_id` NOT NULL em `nfe_certificados`
- Cobertura cross-tenant explícita pra credenciais fiscais A1/A3 (CNPJ titular + encrypted_password)

### D7 — Retention
- `Config/retention.php` confirmado: 5 anos (1825d) pra `nfse_emissoes` autorizadas/canceladas (CONFAZ); 1 ano pra rejeitadas/erro (sem efeito fiscal)
- Append-only `activity_log` (LogsActivity em NfseEmissao) — NUNCA purgada

### Mantido
- Multi-tenant via `NfseBusinessScope` (session `user.business_id`)
- `NfseCertificado` é alias de `Modules\NfeBrasil\Models\NfeCertificado` — schema unificado migration `2026_05_07_210000`
