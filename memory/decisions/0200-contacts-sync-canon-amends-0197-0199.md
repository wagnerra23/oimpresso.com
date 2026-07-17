---
slug: 0200-contacts-sync-canon-amends-0197-0199
number: 200
title: "Contacts adopta canon sync bidirecional Wagner 2024-11 (officeimpresso_codigo + dt_alteracao) · amends ADR 0197 + 0199"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: crm
tags: [migracao-legacy, sync-bidirecional, contacts, canon-wagner, consolidacao]
supersedes: []
superseded_by: []
amends:
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0199-errata-bucket-b-json-catchall-amends-0197
related:
  - 0017-officeimpresso-restaurado-superadmin-exclusivo
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0199-errata-bucket-b-json-catchall-amends-0197
---

# ADR 0200 — `contacts` adopta canon sync bidirecional Wagner 2024-11

## Status

`aceito` 2026-05-27 — Wagner identificou em sessão pos-merge dos 4 PRs (#1717/#1723/#1727/#1731) que existe pattern canônico anterior pra sync bidirecional Delphi ↔ oimpresso ("os campos que eu tinha preparado era algo como officeimpresso_id? e data de alteração?"), e pediu consolidação ("eu prefiro consolidar qual?").

## Contexto

Entre **2024-11-11 e 2025-01-06**, Wagner estabeleceu pattern canônico de sync bidirecional Delphi WR Comercial ↔ oimpresso em 11 tabelas (migrations + [`Modules/Connector/Http/Controllers/Api/BaseApiController.php`](../../Modules/Connector/Http/Controllers/Api/BaseApiController.php)):

| Tabela | Migration | Data |
|---|---|---|
| `brands` | `2024_11_11_162652_add_officeimpresso_migration_to_brands_table` | 2024-11-11 |
| `users` | `2024_11_21_164209_add_officeimpresso_fields_to_users_table` | 2024-11-21 |
| `categories` | `2024_12_16_*_add_officeimpresso_categories_table` | 2024-12-16 |
| `units` | `2024_12_16_075504_add_officeimpresso_fields_to_units_table` | 2024-12-16 |
| `condicaopagto` | `2024_12_18_145324_create_condicaopagto_table` | 2024-12-18 |
| `cidades` | `2024_12_19_092905_create_cidades_table` | 2024-12-19 |
| `pessoas_grupo` | `2024_12_19_174606_create_pessoas_grupo_table` | 2024-12-19 |
| `nf_natureza_operacao` | `2024_12_30_093956_create_nf_natureza_operacao_table` | 2024-12-30 |
| `produto_grupo` | `2024_12_30_095801_create_produto_grupo_table` | 2024-12-30 |
| `nf_natureza_operacao_prodgrupo` | `2024_12_30_142344` | 2024-12-30 |
| `products` | `2025_01_06_073702_add_sync_fields_to_products_table` | 2025-01-06 |

Pattern canon (2 campos por tabela):

```sql
ALTER TABLE <tabela>
  ADD officeimpresso_codigo       VARCHAR(255) NULL,  -- CODIGO Delphi (chave cross-system)
  ADD officeimpresso_dt_alteracao TIMESTAMP NULL;     -- DT_ALTERACAO Delphi (conflict detection)
```

E sync genérico via `BaseApiController::syncData` ([linha 67-73](../../Modules/Connector/Http/Controllers/Api/BaseApiController.php)) implementa **last-write-wins com conflict detection**:

```php
$updatedAt = $record->updated_at->format('Y-m-d H:i:s');
$itemUpdatedAt = Carbon::parse($item['oimpresso_updated_at'])->format('Y-m-d H:i:s');

if ($updatedAt !== $itemUpdatedAt) {
    // CONFLITO — Delphi tinha visão desatualizada
    $response[] = $this->formatResponse($record, 'conflict', ...);
    continue;
}

// IGUAL — aceita update do Delphi
$record->update($this->mapData($item));
```

**Gap identificado 2026-05-27:** apesar de 11 tabelas usarem o canon, **`contacts` NÃO foi incluída** (foi feita apenas a Wave `legacy_id` 2026-05-13 com VARCHAR CNPJ). PRs #1717/#1723/#1727/#1731 mergeados hoje pra Bucket A+B reforçaram esse gap criando pattern alternativo (`legacy_id` + `legacy_source` + `legacy_raw`) sem alinhar com canon Wagner.

**Pergunta Wagner 2026-05-27:** *"podemos usar isso? eu prefiro consolidar qual?"*

## Decisão

**Consolidar `contacts` pro canon Wagner 2024-11.** Esta ADR amenda [ADR 0197](0197-extend-contacts-absorcao-pessoas-legacy.md) e [ADR 0199](0199-errata-bucket-b-json-catchall-amends-0197.md).

### Schema novo (Migration `2026_05_27_160000_contacts_consolidate_officeimpresso_sync_canon`)

**Adicionar:**

```sql
ALTER TABLE contacts
  ADD officeimpresso_codigo       VARCHAR(255) NULL AFTER legacy_id,
  ADD officeimpresso_dt_alteracao TIMESTAMP NULL    AFTER officeimpresso_codigo;

CREATE INDEX idx_contacts_biz_officeimpresso_codigo
  ON contacts(business_id, officeimpresso_codigo);
```

**Dropar (limpeza):**

```sql
ALTER TABLE contacts DROP INDEX idx_contacts_biz_legacy_source;
ALTER TABLE contacts DROP COLUMN legacy_source;
```

### 4 campos finais em `contacts` pra migração legacy (clareza)

| Campo | Propósito | Quem consome | Origem |
|---|---|---|---|
| `legacy_id` (VARCHAR 32) | **Dedup inicial** — CNPJ normalizado pra match record-by-record na migração one-shot | Importer Python `import-pessoas-from-firebird.py` | Wave 2026-05-13 |
| `officeimpresso_codigo` (VARCHAR 255) | **Sync bidirecional viva** — CODIGO Delphi pra correlação cross-system | `BaseApiController::syncData` (genérico canon Wagner) | **Esta ADR** |
| `officeimpresso_dt_alteracao` (TIMESTAMP) | **Conflict detection** — DT_ALTERACAO Delphi vs `updated_at` Laravel | `BaseApiController::syncData` linha 67-73 | **Esta ADR** |
| `legacy_raw` (JSON) | **Forensics catch-all** — dump bruto Delphi com PII redacted | UI accessor `cliente_desde`, queries ad-hoc auditoria LGPD | [ADR 0199](0199-errata-bucket-b-json-catchall-amends-0197.md) |

**Dropada:** `legacy_source` ENUM — redundante com `officeimpresso_codigo IS NOT NULL` (indica origem Delphi). Detecção de origem agora é:

```php
// Antes (ADR 0199 mergeada hoje):
$contact->legacy_source === 'wr-comercial-delphi'

// Depois (canon consolidado):
$contact->officeimpresso_codigo !== null
```

### Diferença sutil entre os 4 — quando usar cada um

```
[CENÁRIO 1] Migração one-shot Firebird → MySQL (importer Python):
   PESSOAS.CNPJCPF normalizado → contacts.legacy_id  (match dedup)
   PESSOAS.CODIGO              → contacts.officeimpresso_codigo  (FK pra sync futura)
   PESSOAS.DT_ALTERACAO        → contacts.officeimpresso_dt_alteracao
   raw_row_redacted            → contacts.legacy_raw JSON

[CENÁRIO 2] Sync bidirecional viva (cliente continua Delphi LOCAL + oimpresso ONLINE):
   Delphi PUSH:  POST /connector/api/contacts/sync  com body {oimpresso_updated_at, ...}
                 → BaseApiController compara record.updated_at vs item.oimpresso_updated_at
                 → updates ou conflict
   oimpresso PULL: GET /connector/api/contacts/getData?date=...
                   → retorna records updated_at > date (filter business_id)
                   → Delphi atualiza local

[CENÁRIO 3] Storytelling UI ("cliente desde 2003"):
   $contact->cliente_desde  (accessor lê legacy_raw.data_cadastro)

[CENÁRIO 4] Forensics LGPD ("dump bruto Delphi pra auditoria"):
   SELECT JSON_EXTRACT(legacy_raw, '$.raw_dump_pessoas_row') ...
```

Zero redundância real entre os 4 — cada um cobre cenário distinto.

## Consequências

### Positivas

- **`contacts` entra no fluxo `BaseApiController::syncData`** sem código novo — 11 tabelas canon comprovam pattern maduro
- **Delphi WR Comercial não precisa update** — já manda `oimpresso_updated_at` no payload de outros endpoints (`/connector/api/*`)
- **Conflict detection automático** — last-write-wins resolve quem ganha (Delphi local vs oimpresso online)
- **Cliente continua usando Delphi LOCAL** enquanto se acostuma com oimpresso ONLINE em paralelo — não precisa cutover hard
- **Schema mais limpo** — 4 campos com propósitos distintos vs. 5 anteriores com 1 redundante
- **Alinhamento Tier 0 IRREVOGÁVEL** — segue [contrato-delphi-inviolavel.md](../reference/contrato-delphi-inviolavel.md) (wire imutável; campos novos OK, comportamento canon preserved)

### Negativas

- **Drop `legacy_source` ENUM** que existia há ~30min (mergeada em #1731 às 13:50 BRT). Mitigação: 0 dados em prod (nenhum importer rodou ainda persistindo `legacy_source`)
- **+2 cols em `contacts`** (já está com 15 pós-Bucket A+B → 16 pós-consolidação; dropa 1 = 16)
- **Onboarding de novo dev** precisa entender 4 campos similares-mas-distintos — mitigação: PHPDoc canon + ADR 0200 documenta propósito de cada

### Neutras

- **Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) preservado** — índice composto `(business_id, officeimpresso_codigo)`
- **`legacy_raw` JSON preserved** — forensics catch-all não conflita com sync bidirecional canon
- **`legacy_id` (CNPJ) preserved** — Martinho biz=164 tem 9.938 contacts já usando; chave natural distinta de `officeimpresso_codigo` (int Delphi)
- **Pest test novo** — herda padrão de `tests/Feature/Contact/ContactBucketALegacyAbsorptionTest` + valida compat com BaseApiController

### Tier 0 Risks (mitigação obrigatória)

| Risco | Mitigação |
|---|---|
| ❌ Quebrar 11 tabelas canon ao mexer em `contacts` | Migration aditiva + idempotente — não toca `BaseApiController` (uso de model genérico) |
| ❌ Drop column `legacy_source` causar erro em código que referencia | `git grep -rn "legacy_source"` antes do PR — só Pest test + Contact $casts; ambos atualizam no mesmo PR |
| ❌ Cliente Delphi PUSH com `officeimpresso_codigo` que conflita com `legacy_id` | App layer trata como chaves distintas; backfill controlado no importer |
| 🟡 Backfill 9.938 contacts Martinho com `officeimpresso_codigo` | Importer Python (próximo PR) lê `legacy_raw.codigo_raw` (já persistido em JSON) e backfilla `officeimpresso_codigo` |

## Plano de execução

1. **Migration** `database/migrations/2026_05_27_160000_contacts_consolidate_officeimpresso_sync_canon.php`:
   - Add `officeimpresso_codigo` VARCHAR(255) NULL
   - Add `officeimpresso_dt_alteracao` TIMESTAMP NULL
   - Add índice `idx_contacts_biz_officeimpresso_codigo`
   - Drop índice `idx_contacts_biz_legacy_source`
   - Drop coluna `legacy_source`
   - Idempotente + reversível
2. **Contact model** — remover cast `legacy_raw → array` mantém; adicionar cast `officeimpresso_dt_alteracao → datetime`
3. **Pest test** `tests/Feature/Contact/ContactSyncCanonOfficeimpressoTest.php`:
   - Schema guard (2 cols novas + 1 col dropada)
   - Conflict detection compatível com `BaseApiController::syncData` (mesmo padrão de products/brands)
   - Multi-tenant scope Tier 0 (ADR 0093)
4. **PR isolado** — merge admin pos-CI verde
5. **Apply prod** — `php artisan migrate --force` via SSH Hostinger
6. **Smoke prod** — transaction+rollback testando sync flow contra `BaseApiController`
7. **Próximo (não bloqueia merge):** importer Python persiste `officeimpresso_codigo` extraindo de `PESSOAS.CODIGO` Firebird

## Review triggers

- **3 meses após** (2026-08-27): se nenhum cliente Delphi LOCAL usou sync bidirecional via `BaseApiController::syncData` em `contacts` → reavaliar se os 2 campos canon valem a manutenção
- **Drift detection** — se nova tabela for adicionada no projeto sem seguir canon `officeimpresso_codigo` + `officeimpresso_dt_alteracao` → flag em audit
- **PII leak** — campo `officeimpresso_codigo` é CODIGO Delphi internal (não PII); validar trimestralmente que não foi misturado com CNPJ no importer

## Lição arquitetural documentada

**"Antes de criar pattern novo, faça `git grep` de patterns canon existentes."**

Eu (Claude) errei ao mergear Bucket A+B (PRs #1723 + #1731) introduzindo `legacy_id` + `legacy_source` + `legacy_raw` sem checar que Wagner já tinha estabelecido canon `officeimpresso_codigo` + `officeimpresso_dt_alteracao` em 11 tabelas entre Nov/2024 e Jan/2025. Resultado: 1 ADR errata extra (esta), 1 col redundante criada e dropada no mesmo dia.

**Mitigação futura:** [skill `como-integrar`](../../.claude/skills/como-integrar/SKILL.md) deve incluir verificação `git grep -rn "<tema>" database/migrations/` antes de propor schema novo.

Generalizável: **introspectar > propor**. Quando o projeto já tem pattern estabelecido, reuso > re-criação.

## Refs

- [ADR 0197 — Bucket A+B schema PESSOAS→contacts (esta ADR amends 0197 expandindo Bucket B)](0197-extend-contacts-absorcao-pessoas-legacy.md)
- [ADR 0199 — Errata Bucket B JSON catch-all (esta ADR amends 0199 dropando `legacy_source`)](0199-errata-bucket-b-json-catchall-amends-0197.md)
- [ADR 0021 — Contrato real API Delphi 3 gerações](0021-contrato-real-api-delphi-3-geracoes.md)
- [ADR 0019 — Passport v10/v13 auth Delphi](0019-passport-v10-v13-auth-delphi.md)
- [contrato-delphi-inviolavel.md (wire IRREVOGÁVEL)](../reference/contrato-delphi-inviolavel.md)
- [Modules/Connector/Http/Controllers/Api/BaseApiController.php (engine sync genérico)](../../Modules/Connector/Http/Controllers/Api/BaseApiController.php)
- [Sessão 2026-05-27 — diagnóstico Hostinger + Martinho biz=164](../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md)
