---
name: Cross-tenant tests biz=99 (não 4)
description: Refinamento ADR 0101 — biz=1 default, biz=99 cross-tenant (não biz=4 que é cliente real ROTA LIVRE). Guard BusinessIdGuardTest detecta violações
type: feedback
---
Refinamento da regra original ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) + auditoria 2026-05-10 PR #453):

**Convenção pra tests do oimpresso**:

| Cenário | Convenção |
|---|---|
| Single-tenant test default | `biz=1` (Wagner WR2 SC, biz interno) |
| Multi-tenant isolation "outro tenant" / "cross-tenant" | `biz=99` (NÃO biz=4 que é ROTA LIVRE cliente real) |

**Why**: tests com `biz=4` introduzem PII de cliente em fixtures (ROTA LIVRE = LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA). Antes da auditoria 2026-05-10 várias testes usavam `biz=4` como adversário cross-tenant — era violação dupla (cliente real + sem fixtures determinísticas).

**How to apply**:
- Test single-tenant que precisa só "um biz qualquer" → `biz=1`
- Test que precisa 2 tenants pra validar isolamento (Tier 0) → `biz=1` + `biz=99` ou `biz=2`
- URLs/instance_ids/fixture strings também: `biz1-main` (não `biz4-main`)

**Detector automático**: `tests/Unit/BusinessIdGuardTest.php` faz scan de todos `*Test.php` via Symfony Finder + assertion `business_id=4` ausente. Quebra CI se reaparecer.

**Histórico de remediation**:
- PR #453 (auditoria 2026-05-10): 64 trocas em 10 arquivos pra cumprir convenção
- Helpers preservados (aceitam qualquer biz): `seedInterCredential(int $businessId, ...)`, `makeBaileysConfig(array $overrides)`

**ADR canon**: [ADR 0101 — tests business_id=1 nunca cliente](../decisions/0101-tests-business-id-1-nunca-cliente.md)
