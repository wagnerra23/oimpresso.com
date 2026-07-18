---
slug: 0199-errata-bucket-b-json-catchall-amends-0197
number: 199
title: "Errata Bucket B · pivot tabela satélite 10 cols → 2 cols JSON catch-all em contacts (amends ADR 0197)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: crm
tags: [migracao-legacy, contacts, json, schema-flexivel, errata]
supersedes: []
superseded_by: []
amends: [0197-extend-contacts-absorcao-pessoas-legacy]
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
---

# ADR 0199 — Errata Bucket B · JSON catch-all em vez de tabela satélite (amends ADR 0197 §B)

## Status

`aceito` 2026-05-27 — Wagner argumentou que "o período para errar é agora" (1 cliente ativo Larissa biz=4 · 4-10 clientes legacy entrando · schema rígido caro depois). Pivot adotado pré-implementação Bucket B.

## Contexto

[ADR 0197 §B Bucket B](0197-extend-contacts-absorcao-pessoas-legacy.md) decidiu criar tabela satélite `contact_profile_legacy` 1:1 com 10 colunas (`legacy_codigo_raw`, `legacy_data_cadastro`, `legacy_dt_alteracao`, `legacy_usuario_cadastro`, `legacy_usuario_alteracao`, `legacy_emails_extras` JSON, `legacy_observacoes` JSON, `legacy_raw` JSON, etc.) pra preservar retro-rastreabilidade Delphi WR Comercial.

**Reflexão Wagner 2026-05-27 (pós-merge ADR 0197 + #1723 Bucket A):**

> *"quanto mais campos criarmos agora depois vai ser pior mudar. hoje tenho ativo só o rotalive, e vai vir bastante clientes na migração. muito cliente e migrações então espero que seja melhor ideia"*

E reforçou:

> *"o período para errar é agora"*

### Situação real que muda o cálculo de A→B

- **Hoje:** 1 cliente operacional ativo (Larissa biz=4 · ROTA LIVRE · não tem Firebird) + Martinho biz=164 já migrado parcialmente (9.938 contacts) sem usar `contact_profile_legacy`
- **Próximos 6-12 meses:** Gold + Vargas + Extreme + 33 outros clientes legacy WR Comercial entrando
- **Schema Delphi varia por cliente:** Vargas tem cavalo+reboque (PLACA2/CHASSI2), Extreme tem PCP industrial, Gold tem DT_PROMETIDO maduro, Martinho tem FSM 2 estados, custom-tipos Delphi (`OIM`/`AGE`/`LOC`/`OFI`) variam por cliente
- **Cada col física nova** em `contact_profile_legacy` = migration retroativa em N clientes prod + retest cascata
- **Cada cliente legacy** pode trazer `URL_COBRANCA_INTERNA`, `OBS_TECNICA_PRODUCAO`, `WHATSAPP_FINANCEIRO_RESPONSAVEL`, etc. — campos custom WR por nicho

ADR 0197 §B subestimou a **heterogeneidade dos 38 Firebirds**. Tratamos como se todos tivessem o mesmo `PESSOAS` schema canônico — mas dump Vargas + reflexão Wagner mostram que cada cliente customiza.

## Decisão

**Pivotar Bucket B de "tabela satélite 10 cols" pra "2 cols JSON catch-all em `contacts`"** — esta ADR amenda [ADR 0197 §B Bucket B](0197-extend-contacts-absorcao-pessoas-legacy.md#b--satélite-contact_profile_legacy-1:1-tabela-nova).

### Schema novo (substitui `contact_profile_legacy` 1:1)

Migration `2026_05_27_140000_contacts_bucket_b_legacy_raw_json.php`:

```sql
ALTER TABLE contacts
  ADD COLUMN legacy_source ENUM('wr-comercial-delphi','outro') NULL AFTER legacy_id,
  ADD COLUMN legacy_raw JSON NULL AFTER legacy_source;
```

- **`contacts.legacy_source`** (enum nullable) — origem da migração. Default `wr-comercial-delphi` quando importer rodar; `outro` reservado pra futuras integrações (Bling/Tiny export, planilha cliente, etc.). NULL = não migrado (cadastro nativo oimpresso).
- **`contacts.legacy_raw`** (JSON nullable) — catch-all do dump bruto Delphi. Importer persiste TODO `PESSOAS` row aqui após `PiiRedactor::redact()` em CNPJ/CPF/EMAIL/FONE.

Estrutura típica do JSON (não-vinculante — cada cliente pode variar):

```json
{
  "codigo_raw": "123-empresa01",
  "data_cadastro": "2003-04-12 14:32:00",
  "dt_alteracao": "2024-11-08 09:15:33",
  "usuario_cadastro": "JOAO.SILVA",
  "usuario_alteracao": "MARIA.SANTOS",
  "emails_extras": {
    "cobranca": "cobranca@cliente.com.br",
    "financeiro": "financ@cliente.com.br"
  },
  "observacoes": {
    "principal": "Cliente fiel desde 2003",
    "financeiro": "Aceita boleto 30 dias",
    "producao": "Sempre pede entrega rápida"
  },
  "campos_custom_cliente": {
    "URL_COBRANCA_INTERNA": "...",
    "WHATSAPP_RESPONSAVEL_COMPRAS": "..."
  },
  "raw_dump_pessoas_row": { "..._329_cols_redacted_pii_..." }
}
```

### Queries forensic (canônicas)

Storytelling "cliente desde 2003":

```php
// Eloquent accessor sugerido em App\Contact:
public function getClienteDesdeAttribute(): ?string {
    return data_get($this->legacy_raw, 'data_cadastro');
}

// Query massiva — JSON_EXTRACT (MariaDB 11.8 nativo):
Contact::whereBusinessId(164)
    ->whereRaw("JSON_EXTRACT(legacy_raw, '$.data_cadastro') < '2010-01-01'")
    ->get();
```

Quando virar gargalo real em produção (cenário hipotético: dashboard "rotear 38 clientes Delphi por data cadastro" tocando milhões de rows), Wagner cria **functional index**:

```sql
ALTER TABLE contacts ADD INDEX idx_legacy_data_cadastro (
    (CAST(JSON_EXTRACT(legacy_raw, '$.data_cadastro') AS DATE))
);
```

— **somente quando precedente real aparecer** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado).

### Eloquent

```php
// App\Contact (já existe nas migrations Bucket A merged):
protected $casts = [
    // ...
    'legacy_raw' => 'array',  // ADR 0199 — JSON cast (legacy_source enum string nativo)
];
```

### Trade-offs aceitos

| Dimensão | A (tabela 10 cols — ADR 0197 original) | **B (JSON catch-all — esta ADR)** |
|---|---|---|
| Migration cost por cliente novo | Alta (cada campo novo = ALTER TABLE retroativo N clientes) | **Zero** (JSON aguenta qualquer schema sem migration) |
| Schema rigidity | Forte (10 cols nomeadas) | **Flex max** (JSON aceita tudo) |
| Query forensic speed | Rápida (JOIN PK + col física) | OK (JSON_EXTRACT MariaDB 11.8 ~10-20% mais lento, irrelevante até milhões de rows) |
| Storytelling UI | Trivial (`$contact->profileLegacy->legacy_data_cadastro`) | Trivial via accessor (`$contact->cliente_desde`) |
| Forensics audit | Alta (campos nomeados) | Alta (JSON preserva TUDO bruto) |
| Heterogeneidade clientes | Mal — cada custom-campo Delphi exige col nova | **Bom** — cada cliente persiste suas próprias keys JSON |
| Reverter se erro | Caro (drop tabela + drop migration + retest) | **Barato** (drop 2 cols nullable) |

### Quando A teria sido melhor escolha

Cenário hipotético (não nosso):
- 1 schema Delphi canônico estável em **todos** os clientes
- Queries forensic massivas diárias em prod
- Time grande precisando IDE-autocomplete em cada campo
- Sem espaço pra iterar (deploy imutável)

**Nenhum desses cenários se aplica ao oimpresso 2026-05.** Wagner está no início da migração de N clientes heterogêneos, com 1 cliente ativo, com permissão pra errar e iterar.

## Consequências

### Positivas

- **2 cols em `contacts`** em vez de tabela nova + 10 cols + Model + Eloquent relation + Pest extra
- **Zero migration por cliente legacy novo** — JSON aguenta qualquer schema Delphi customizado
- **Importer mais simples** — `legacy_raw = json_encode($pessoas_row_redacted)` em 1 linha vs. 10 mappings cuidadosos
- **Reverte barato** — se errado, drop 2 cols nullable sem cascata
- **Mantém princípio** [ADR 0105 cliente como sinal qualificado](0105-cliente-como-sinal-guiar-sem-mandar.md) — schema rígido só quando uso real aparecer

### Negativas

- **JSON query** ~10-20% mais lenta que col física em queries massivas — irrelevante até cenário hipotético "milhões de rows + dashboard daily"
- **Sem IDE autocomplete** dos campos legacy individuais — mitigação: documentar shape JSON canônico em PHPDoc do `App\Contact::$casts`
- **Risco de "JSON soup" desorganizado** — mitigação: importer canônico define chaves padrão (`data_cadastro`/`dt_alteracao`/`usuario_cadastro`/`observacoes`/`emails_extras`); custom-fields cliente entram em sub-key `campos_custom_cliente`

### Neutras

- **Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) preservado** — JSON column dentro de `contacts` herda `business_id` scope existente
- **Backfill Martinho biz=164** (9.938 contacts) pode rodar depois desta migration mergear, sem urgência (operacional não depende)
- **Acelera testes A/B com clientes diferentes** — schema flex permite Felipe rodar importer Vargas + Gold em paralelo sem coordenar migrations

### Tier 0 Risks (mitigação obrigatória)

| Risco | Mitigação |
|---|---|
| ❌ PII vazar em `legacy_raw` JSON (CNPJ/CPF/EMAIL/FONE raw) | `PiiRedactor::redact()` obrigatório no importer antes de `json_encode()` — pattern alinhado com [migracao-officeimpresso-pattern.md §6](../reference/migracao-officeimpresso-pattern.md) |
| ❌ `legacy_raw` virar dumping ground sem estrutura | PHPDoc em `App\Contact` define chaves canônicas; review code rejeita importer que não siga |
| 🟡 Query forensic lenta no futuro | Functional index ad-hoc quando virar gargalo real (não premature) |

## Plano de execução (substitui ADR 0197 §B execução)

Esta ADR vem com migration + Pest test no MESMO PR:

1. **Migration** `database/migrations/2026_05_27_140000_contacts_bucket_b_legacy_raw_json.php` — 2 cols nullable + idempotência
2. **Contact $casts** — `'legacy_raw' => 'array'`
3. **Pest test** `tests/Feature/Contact/ContactBucketBLegacyRawJsonTest.php` — schema + cast round-trip + multi-tenant scope
4. **Importer Python** (próximo PR) — `import-pessoas-from-firebird.py` persiste `legacy_source='wr-comercial-delphi'` + `legacy_raw=PiiRedactor::redact($pessoas_row)` JSON
5. **NÃO criar** `contact_profile_legacy` table (revogada)
6. **NÃO criar** `ContactProfileLegacy` Model (revogada)
7. **NÃO criar** `Contact::profileLegacy()` Eloquent relation (revogada)

## Review triggers

- **6 meses após esta ADR** (2026-11-27): se >3 clientes legacy migrados E queries `JSON_EXTRACT(legacy_raw, ...)` aparecem em dashboards regulares → avaliar promover 1-2 chaves frequentes pra col física dedicada
- **Performance flag** — se `EXPLAIN` em query forensic mostrar full-table-scan > 1s em `contacts` → adicionar functional index na chave específica
- **PII leak** — Pest test trimestral valida que `legacy_raw` JSON dump não contém regex CPF/CNPJ unredacted

## Lição arquitetural documentada

**"Schema rígido é caro quando o input é heterogêneo + você está iterando."**

Wagner aplicou intuição correta: durante fase de descoberta (1 cliente ativo, 38 prospects, schemas variados), o custo de errar uma col física é alto (migration retroativa N clientes). JSON catch-all dá flex pra iterar; promover pra col física é barato DEPOIS de saber qual chave é usada de verdade.

Generalizável pra outras ADRs futuras: **prefira JSON catch-all em fase exploratória; promova pra col física quando uso virar precedente qualificado**.

## Refs

- [ADR 0197 — Bucket A+B schema PESSOAS→contacts (esta ADR amends §B)](0197-extend-contacts-absorcao-pessoas-legacy.md)
- [ADR 0105 — Cliente como sinal qualificado (justificativa filosófica)](0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md)
- [migracao-officeimpresso-pattern.md (pattern canônico)](../reference/migracao-officeimpresso-pattern.md)
- [Sessão 2026-05-27 — diagnóstico Hostinger + Martinho biz=164](../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md)
- [Gap doc 2026-05-26](../sessions/2026-05-26-gap-pessoas-vs-contacts.md)
