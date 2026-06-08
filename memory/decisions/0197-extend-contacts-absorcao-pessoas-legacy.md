---
slug: 0197-extend-contacts-absorcao-pessoas-legacy
number: 197
title: "Extensão `contacts` pra absorver schema legacy `PESSOAS` (WR Comercial Delphi/Firebird) — Fase 1 CLI+FOR+REP via Bucket A+B"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: crm
tags: [migracao-legacy, officeimpresso, firebird, contacts, multi-tenant, lgpd, schema]
supersedes: []
superseded_by: []
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0131-tiering-memoria-canonico-local-segredo
  - 0137-modules-oficinaauto-qualificada
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0178-canon-br-fiscal-restaurado-v3-7
  - 0179-drawer-cliente-wave-b-c-cowork
  - 0188-multi-type-flags-aditivas
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
---

# ADR 0197 — Extend `contacts` pra absorver `PESSOAS` legacy · Fase 1 (CLI+FOR+REP, Bucket A+B)

## Status

`aceito` 2026-05-27 — Wagner direcionou execução sem perguntas adicionais ("não me pergunte resolva"). Análise canônica em [`memory/sessions/2026-05-26-gap-pessoas-vs-contacts.md`](../sessions/2026-05-26-gap-pessoas-vs-contacts.md) — esta ADR materializa a decisão como contrato canon.

## Contexto

Migração dos 38 clientes legacy **WR Comercial (Delphi/Firebird)** pro oimpresso (Laravel/MySQL multi-tenant) tem 4 candidatos saudáveis identificados em [`_ANALISE-CROSS-CLIENTE.md`](../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) — Martinho (1º cutover), Gold, Vargas, Extreme. Martinho já parcialmente migrado (biz=164: Fase 1 EMPRESA + Fase 2 EQUIPAMENTO_VEICULO ✅; Fase 3 VENDA + Fase 4 FINANCEIRO pendentes — RUNBOOK em [RUNBOOK-migracao-martinho-fase3-fase4.md](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md)).

A tabela `PESSOAS` Firebird tem **329 colunas físicas**:

- ~24 colunas fixas canônicas (SQL base `TControllerPessoas.Create`)
- ~285 colunas dinâmicas `IS_<TIPO>` + `SEQUENCIA_<TIPO>` (multi-papel Delphi — já resolvido em oimpresso via [ADR 0188 flags aditivas](0188-multi-type-flags-aditivas.md))
- ~20 FKs e relacionamentos (`CODCIDADE`, `PESSOA_ASSOCIADO_CODIGO`, `PESSOA_REPRESENTANTE_CODIGO` etc.)

8 waves Maio/2026 ([ADR 0178](0178-canon-br-fiscal-restaurado-v3-7.md), [0179](0179-drawer-cliente-wave-b-c-cowork.md), [0188](0188-multi-type-flags-aditivas.md), city_code, numero, SEFAZ, role flags) já cobriram ~28 colunas. Sobra **~14 colunas Bucket A + 1 tabela satélite Bucket B** — esta ADR formaliza esse delta.

**Proposta alternativa rejeitada (2026-05-27):** redesign full-normalization em 11 tabelas (`contact_addresses` 1:N + `contact_fiscal` 1:1 + `contact_financial` 1:1 + `contact_personal` 1:1 + `contact_professional` 1:1 + `contact_banking` 1:N + `contact_roles` 1:N + `contact_commercial` 1:1 + `contact_web` 1:1 + `contact_extra` JSON). Rejeitada por:

- Quebra UltimatePOS canon (Sells / Compras / Modules consultam `contacts.address_line_1` / `tax_number` / `credit_limit` direto)
- Quebra Cowork drawer Wave C ([ADR 0179](0179-drawer-cliente-wave-b-c-cowork.md)) que mapeia flat
- Conflita com [ADR 0188 flags aditivas](0188-multi-type-flags-aditivas.md) (já aprovada — `is_customer`/`is_supplier`/`is_employee`/`is_representative`)
- Estimado 40-60h Felipe + cascata de retests vs 6-10h da Fase 1 desta ADR

## Decisão

**Adotar pattern híbrido A+B+C+D+E** documentado em [§4 do gap doc 2026-05-26](../sessions/2026-05-26-gap-pessoas-vs-contacts.md#4-tabela-de-gap-núcleo). Resumo:

### A — EXTEND `contacts` (Wave D · 1 migration aditiva · ~14 cols nullable)

Cols com consulta direta por business logic (Sells/Financeiro/NfeBrasil/Compras):

| Coluna nova | Tipo | Origem PESSOAS | Aplica a |
|---|---|---|---|
| `complemento` | string(120) null | `COMPLEMENTO` | CLI+FOR+REP |
| `bloqueado` | bool default 0 | `BLOQUEADO` (`S/N`) | CLI+FOR |
| `limite_desconto_percentual` | decimal(5,2) null | `LIMITE_DESCONTO` | CLI |
| `boleto_desconto_pontualidade_pct` | decimal(5,2) null | `BOLETO_PERC_DESCONTO_PADRAO` | CLI |
| `cobrar_custo_boleto` | bool default 0 | `COBRAR_CUSTO_BOLETO` | CLI |
| `fatura_previsao` | date null | `FATURA_PREVISAO` | CLI |
| `prioridade_producao` | tinyint 0-5 null | `PRIORIDADE_PRODUCAO` | CLI |
| `iss_retido` | tinyint 1/2 null | `ISS_RETIDO` | CLI+FOR (NFSe) |
| `aniversario_mmdd` | string(5) null `MM-DD` | `ANIVERSARIO` | CLI PF (comemoração, distinto de `dob`) |
| `parent_contact_id` | bigint FK self null INDEX | `PESSOA_ASSOCIADO_CODIGO` | CLI+FOR (rede filial/matriz) |
| `sales_rep_contact_id` | bigint FK self null | `PESSOA_REPRESENTANTE_CODIGO` | CLI (comissão Sells) |
| `primary_role` | enum('customer','supplier','employee','representative') null | `TIPO_PADRAO` | display UI |
| `situacao` | string(30) null | `SITUACAO` (livre) | back-compat string Delphi (lado `tags` JSON cobre semântica) |
| **TBD (1-2 cols)** | — | a confirmar dump real Vargas | — |

**Mapeamentos pra cols já existentes** (sem migration nova — só importer):

| PESSOAS | `contacts` existente | Wave |
|---|---|---|
| `OBSERVACAO` | `obs_comercial` (text) | Wave drawer 2026-05-22 |
| `TIPO_CONTRIBUINTE` | `indicador_ie` | Wave 2026-05-21 |
| `CRT` | `regime` | Wave 2026-05-21 |
| `CODPRODUTO_TABELA` | `customer_group_id` (FK UPOS) | UPOS core |

Migration: **`Modules/Crm/Database/Migrations/2026_05_XX_extend_contacts_wave_d_legacy_absorption.php`** — idempotente (`Schema::hasColumn()` em cada `addColumn`); todas as cols nullable + defaults seguros; retrocompat 100%.

### B — SATÉLITE `contact_profile_legacy` (1:1 · tabela nova)

Retro-rastreabilidade Delphi pra auditoria + storytelling ("cliente desde 2003"). **NÃO consultada por business logic** — só lazy-load no card do contato e em forensics LGPD. Pattern alinhado com `contact_metadata` UPOS canon.

```sql
CREATE TABLE contact_profile_legacy (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  contact_id      BIGINT UNSIGNED NOT NULL UNIQUE,
  business_id     INT UNSIGNED NOT NULL,           -- Tier 0 redundante (ADR 0093)
  legacy_source   ENUM('wr-comercial-delphi','outro') DEFAULT 'wr-comercial-delphi',
  legacy_codigo_raw           VARCHAR(40) NULL,    -- `CODIGO` Delphi com sufixo `-empresa`
  legacy_data_cadastro        TIMESTAMP NULL,      -- distinto de contacts.created_at (data migração)
  legacy_dt_alteracao         TIMESTAMP NULL,      -- auditoria Delphi
  legacy_usuario_cadastro     VARCHAR(50) NULL,    -- string livre pré-multi-user
  legacy_usuario_alteracao    VARCHAR(50) NULL,
  legacy_emails_extras        JSON NULL,           -- EMAIL_COBRANCA/FINANC/COMERCIAL multi-aba
  legacy_observacoes          JSON NULL,           -- OBS_FINANCEIRO/PRODUCAO/INTERNA multi-aba
  legacy_raw                  JSON NULL,           -- catch-all dump bruto pra forensics
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  FOREIGN KEY (business_id) REFERENCES business(id),
  INDEX idx_biz_legacy_source (business_id, legacy_source)
);
```

Eloquent: `App\Contact::hasOne(ContactProfileLegacy::class)`. Carrega via `$contact->load('profileLegacy')` quando exibir card detalhado.

### C — REUSO `tags` JSON + `obs_comercial` + `custom_field1..10`

Zero migration. Mapping no importer:

- `ETIQUETA = 'S'` → `tags` JSON append `'imprime_etiqueta'`
- `OBSERVACAO` raw → `obs_comercial` (text já existe)
- Custom-fields cliente-específico ad-hoc → `custom_field1..10` (slots UPOS já existem)

### D — DESCARTAR (Bucket morto, documentado)

| PESSOAS | Razão descarte |
|---|---|
| `PLACA`, `MARCAMODELO`, `ANO`, `RENAVAM`, `CHASSI` em PESSOAS | Vazaram de oficina. `Modules/OficinaAuto.vehicles` tem schema próprio (ADR 0137 + 0194) |
| `SEQUENCIA_<TIPO>` (todos) | Resolvido por `contacts.id` auto-increment |
| `IS_<TIPO>` raw | Mapping pra flags aditivas (ADR 0188) — importer converte sem persistir raw |
| `URL_COBRANCA` / `URL_SPC` | Config por business → `business_settings` (não em contact) |
| Campos 100% nulos no dump Vargas | Auditar dump real antes de descartar definitivamente |

### E — VAULTWARDEN (secrets que aparecerem)

Pattern obrigatório `PESSOAS_SECRET_FIELDS` análogo a `EMPRESA_SECRET_FIELDS` em [`import-empresas.py`](../../scripts/legacy-migration/import-empresas.py). Se aparecer `SENHA_*` qualquer no dump, importer chama `PiiRedactor::redactAndStashSecret($value, $vault_key)` — **não migra pro MySQL**.

### Roteamento por TIPO Delphi (Fase 1 = CLI+FOR+REP em `contacts`)

`PESSOAS` Firebird convive 6+ tipos (`CLI`/`FOR`/`FUN`/`REP`/`T`/`AGE`/custom) com flags `IS_<TIPO>`. **No oimpresso não unificamos** — cada tipo tem destino canônico:

| TIPO Delphi | Destino Laravel | Importer Fase 1? | Notas |
|---|---|---|---|
| `CLI` cliente | `contacts.is_customer=1` | ✅ | importer `import-pessoas-from-firebird.py --tipo CLI` |
| `FOR` fornecedor | `contacts.is_supplier=1` | ✅ | mesma tabela, mesma importer (flag aditiva) |
| `REP` representante | `contacts.is_representative=1` + `sales_rep_contact_id` self-FK | ✅ | precisa 2-pass FK resolution |
| `FUN` funcionário | **`users`** HRM canon + `users.crm_contact_id` ponte | ❌ Fase 2 | importer separado `import-funcionarios-from-firebird.py` (CTPS/PIS/SALARIO etc. fora desta ADR) |
| `T`/`TRA` transportador | **`transportadoras`** legacy 2017 BR fork | ❌ Fase 2 | importer separado `import-transportadoras-from-firebird.py`; schema mínimo MDFe pode precisar extensão se Vargas usar transportador completo |
| `AGE`/`OIM`/custom | descartar OU mapping caso-a-caso no dump real | ❌ | analisar no Bucket D |

**Implicação operacional:**

- 1 PESSOA com `IS_CLI=1 AND IS_FOR=1` → 1 `contacts` row com `is_customer=1 AND is_supplier=1` (ADR 0188 aditivo)
- 1 PESSOA com `IS_FUN=1 AND IS_CLI=1` (funcionário que também compra) → **2 rows** — `users` (papel funcionário) + `contacts` (papel cliente) ligados via `users.crm_contact_id`
- 1 PESSOA com `IS_T=1 AND IS_CLI=1` (transportador que compra) → **2 rows** — `transportadoras` + `contacts` (FK ponte fica Fase 2 se necessidade real surgir)

### Idempotência canônica

Pattern obrigatório (alinhado [migracao-officeimpresso-pattern.md §3](../reference/migracao-officeimpresso-pattern.md)):

```python
cur.execute(
  "SELECT id FROM contacts WHERE business_id=%s AND legacy_id=%s LIMIT 1",
  (business_id, legacy_id_normalized),
)
row = cur.fetchone()
if row:
    cur.execute("UPDATE contacts SET ..., updated_at=NOW() WHERE id=%s", (..., row[0]))
else:
    cur.execute("INSERT INTO contacts (...) VALUES (...)", (...))
```

NÃO usar `INSERT ... ON DUPLICATE KEY UPDATE` — schema usa `index` (não `unique`) em `(business_id, legacy_id)`.

### Importer 2-pass (FK self-referencing)

- **Pass 1:** INSERT/UPDATE todos os `contacts` sem `parent_contact_id` / `sales_rep_contact_id` (deixar NULL)
- **Pass 2:** UPDATE resolvendo FKs via lookup `WHERE business_id=X AND legacy_id=Y` — se lookup falha (FK aponta pra ID inexistente ou ciclo), **warning** + NULL (não hard-fail)

## Consequências

### Positivas

- **Modelo `Contact` único** — zero duplicação `clientes` paralelos ([ADR ARQ-0001 Crm](../requisitos/Crm/adr/ARQ-0001-crm-estende-contacts-do-ultimatepos.md) + [ADR 0179](0179-drawer-cliente-wave-b-c-cowork.md) reforçado)
- **Retrocompat 100%** — todas migrations idempotentes + cols nullable; clientes em prod hoje (biz=4 Larissa, biz=164 Martinho, etc.) não quebram
- **Auditoria Delphi preservada** em satélite sem inchar `contacts` — `legacy_raw` JSON serve forensics LGPD futura
- **Importer Vargas pronto** pra dry-run com schema completo (resta só dump real pra confirmar 1-2 cols TBD)
- **Roteamento por tipo documentado** — agente futuro não re-pensa FUN→users vs T→transportadoras

### Negativas

- **+14 cols em `contacts`** (já está ~95 → ~109) — Wagner pode achar bloated, mas Bucket B satélite absorve o pesado
- **Tabela `contact_profile_legacy` adiciona 1 JOIN** quando exibe "cliente desde" — mitigação: lazy-load Eloquent (`$contact->load('profileLegacy')`), não eager por default
- **Decisão `situacao` vs `tags` JSON** pode ser revisitada após primeiro cliente em prod — se 0 uso após 90d, migration drop col

### Neutras

- **Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) preservado** — `business_id` em `contacts` (já existe + indexado composto via Wave 2026-05-13 + Wave drawer); `contact_profile_legacy.business_id` redundante mas garantido nos query scopes Eloquent
- **FUN + T fora desta ADR** — importers separados ficam Fase 2 (não bloqueia Martinho — sub-vertical mecânica pesada usa principalmente CLI+FOR; representante REP via Sells)
- **Esforço estimado** com IA-pair: **6-10h Felipe** (migration + Eloquent fillable/casts + Pest schema test + retest importer Vargas dry-run + Wagner aprovação)

### Tier 0 Risks (mitigação obrigatória)

| Risco | Mitigação |
|---|---|
| ❌ Quebrar multi-tenant ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) IRREVOGÁVEL) | `business_id` redundante em `contact_profile_legacy` + Pest test enforcing scope (`tests/Feature/Schema/ContactsBucketALegacyTest.php`) |
| ❌ PII vazar em log / audit JSON | `PiiRedactor::redact()` obrigatório em `CNPJCPF`/`EMAIL`/`FONE*` antes de `json.dumps()` no importer |
| ❌ Subir senha/certificado pro MySQL inesperado | Blacklist `PESSOAS_SECRET_FIELDS` análoga a `EMPRESA_SECRET_FIELDS` |
| 🟡 FK self-referencing 2-pass falhar parcial (ciclo, ID inexistente) | `LEFT JOIN` no SELECT Firebird + warning ao invés de hard-fail |

## Plano de execução

1. **Felipe abre PR 1** — Bucket A migration + Eloquent `Contact` $fillable/$casts + Pest test (~2-3h IA-pair; ≤300 linhas)
2. **Felipe abre PR 2** — Bucket B migration + Model `ContactProfileLegacy` + relação `hasOne` + Pest test (~2h IA-pair; ≤200 linhas)
3. **Felipe escreve `import-pessoas-from-firebird.py`** baseado em `import-empresas.py` + `import-contacts-from-venda.py` patterns (~3h IA-pair) — 2-pass + idempotência + audit JSON
4. **Wagner roda dump Vargas** ([scripts/legacy-migration/dump-pessoas-schema.py](../../scripts/legacy-migration/dump-pessoas-schema.py) — 15min, ZERO PII) e cola output → confirma 1-2 cols TBD restantes
5. **Felipe dry-run** importer Vargas → audit JSON em `scripts/legacy-migration/output/audit-pessoas-biz{N}-{ts}.json` → Wagner aprova → roda local Laragon → smoke biz=164 Martinho (cliente piloto) → prod canary 24h → cleanup

## Review triggers

- 90d sem uso da col `situacao` em queries reais → avaliar drop migration
- Importer Vargas falhar FK self-resolution >5% dos rows → revisar pattern 2-pass
- Cliente piloto Martinho biz=164 reportar drift em algum campo → amend desta ADR
- Fase 2 (FUN→users + T→transportadoras) ganhar cliente real → criar ADR 0197+ paralelo

## Refs

- [Gap doc 2026-05-26 (análise canônica fonte)](../sessions/2026-05-26-gap-pessoas-vs-contacts.md)
- [TELA-PESSOAS.md (mapping source-first WR Comercial)](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-PESSOAS.md)
- [OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- [migracao-officeimpresso-pattern.md (pattern canônico migração)](../reference/migracao-officeimpresso-pattern.md)
- [ADR 0198 — Hot/Cold tiering migração transacional histórica](0198-hot-cold-tiering-migracao-transacional-legacy.md)
- [RUNBOOK-migracao-martinho-fase3-fase4.md (executável Felipe)](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md)
