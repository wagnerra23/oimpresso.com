---
slug: 0198-hot-cold-tiering-migracao-transacional-legacy
number: 198
title: "Estratégia hot/cold tiering pra migração transacional histórica de 8-10 clientes legacy (Firebird → MySQL Hostinger + Object Storage)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
module: Officeimpresso
tags: [migracao-legacy, escala, hostinger, mysql-partitioning, object-storage, lgpd-retention, performance]
supersedes: []
superseded_by: []
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0131-tiering-memoria-canonico-local-segredo
  - 0137-modules-oficinaauto-qualificada
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0197-extend-contacts-absorcao-pessoas-legacy
---

# ADR 0198 — Hot/Cold tiering pra migração transacional histórica · 8-10 clientes legacy

## Status

`aceito` 2026-05-27 — Wagner direcionou execução ("não me pergunte resolva"). Decisão arquitetural pra resolver preocupação explícita: *"se passar todos os clientes para online, são muito cada cliente tem 6 a 10 giga de dados de 10 15 anos isso vai se tornar um gargalo? a martinho ja é grande. estou preocupado"*.

## Contexto

[ADR 0137](0137-modules-oficinaauto-qualificada.md) + [ADR 0171](0171-oficinaauto-ativacao-piloto-martinho-faseada.md) + [ANALISE-CROSS-CLIENTE](../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) qualificaram 4-10 clientes legacy WR Comercial pra migração (Martinho 1º, Gold 2º, Vargas 3º, Extreme 4º + rounds futuros nos 33 remanescentes). Cada banco Firebird tem **6-10 GB** acumulados em 10-15 anos de operação.

**Decomposição típica do peso 6-10 GB por cliente:**

| Tipo de dado | Volume típico 15 anos | Peso real | Em MySQL? |
|---|---|---|---|
| `PESSOAS` cadastros | ~5-20k linhas | ~10-50 MB | ✅ ADR 0197 |
| `PRODUTOS` cadastros | ~10-50k linhas | ~20-100 MB | ✅ ADR 0197 |
| `VENDA` + `VENDA_ITEM` transações granulares | ~milhões linhas | **2-4 GB** | ✅ esta ADR |
| **XMLs SEFAZ + DANFEs PDF em BLOB Firebird** | ~100k+ docs | **3-6 GB** (o monstro) | ❌ esta ADR — Object Storage |
| `FINANCEIRO` títulos + baixas | ~milhões linhas | ~500 MB-1 GB | ✅ esta ADR (cleanup-first per ADR 0171) |
| Movimentação estoque histórica | ~milhões linhas | ~500 MB | ✅ esta ADR |

**Realidade atual:**

- Martinho **biz=164 já em prod oimpresso** (Fase 1 + Fase 2 ✅; Fase 3+4 pendentes) — "já é grande" segundo Wagner sem migrar transações ainda
- Plano Hostinger atual não documentado em ADR — provavelmente shared Business (limites por DB ~1-3 GB) ou Cloud
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md): Hostinger ≠ CT 100 (Proxmox) — runtime separado já decidido. CT 100 pode hospedar bancos analytics se necessário; Hostinger fica operacional puro

**Decisão Wagner 2026-05-27 (pré-input desta ADR):**

1. Migrar transacional dos N clientes pra MySQL Hostinger (sim, tudo) — escolha consciente
2. XMLs/DANFEs vão pra **Object Storage** (Hostinger Object Storage S3-compatible) — MySQL guarda só link
3. Sem corte temporal (não "só 24 meses") — 15 anos completos pra preservar storytelling cliente fiel + auditoria legal

**Padrão da indústria (Bling/Tiny/Omie 2026):**

Nenhum guarda 15 anos de transações granulares em DB operacional sem partitioning. Padrão observado:

- Tabelas grandes → MySQL/Postgres particionado por (`business_id`, `YEAR(date)`)
- Anexos volumosos (XML/PDF) → S3-compat (Cloudflare R2, Hostinger Object Storage, AWS S3)
- Backup → snapshot a nível filesystem/VPS, não SQL dump
- Histórico ultra-frio (>5 anos sem acesso) → archive table opt-in OU Parquet em Object Storage com query Athena/DuckDB sob demanda

## Decisão

**Aceitar direção Wagner (tudo no MySQL Hostinger) MAS com 5 mitigações arquiteturais obrigatórias** pra escala não virar gargalo nem em 1 cliente nem em 10.

### Mitigação 1 — Object Storage pros anexos (XMLs SEFAZ + DANFEs PDF)

**Tabelas afetadas:** `nfe_xmls`, `nfe_danfes`, `transaction_attachments`, qualquer BLOB > 100 KB.

**Schema mudança:**

```sql
-- DEPOIS desta ADR
ALTER TABLE nfe_xmls
  ADD COLUMN storage_disk        VARCHAR(20) DEFAULT 'object_storage',
  ADD COLUMN storage_path        VARCHAR(255) NULL,
  ADD COLUMN storage_provider    ENUM('hostinger_objstor','s3','local') DEFAULT 'hostinger_objstor';
-- xml_content BLOB pode permanecer NULL ou ser progressivamente migrado pra path

ALTER TABLE nfe_danfes
  ADD COLUMN storage_path        VARCHAR(255) NULL,
  ADD COLUMN storage_provider    ENUM('hostinger_objstor','s3','local') DEFAULT 'hostinger_objstor';
```

**Estratégia:**

- **Novos uploads (>= 2026-05-27):** vão direto pra Object Storage; MySQL só guarda metadata + path
- **Backfill legacy (dump Firebird):** importer extrai BLOB do Firebird, faz `Storage::disk('object_storage')->put($path, $blob)`, persiste só `storage_path` no MySQL
- **Lazy retrieval:** quando UI clica "Visualizar XML 2012", Controller faz `Storage::disk('object_storage')->get($contact_xml_path)` — latência ~200ms aceitável vs MySQL inchado
- **LGPD retention:** Object Storage tem lifecycle policy ("delete após 6 anos" pra docs fiscais não-obrigatórios, "preserve forever" pros que Receita exige 5+ anos)

**Custo estimado:** Hostinger Object Storage ~R$ 0,02/GB/mês — 60 GB de XMLs × 8 clientes = ~R$ 10/mês. Trivial vs aumentar plano MySQL.

### Mitigação 2 — MySQL partitioning por (business_id, YEAR(date)) nas tabelas grandes

**Tabelas afetadas:** `transactions`, `transaction_sell_lines`, `transaction_payments`, `fin_titulos`, `fin_titulo_baixas`, `stock_movements`.

**Schema mudança (Migration nova, idempotente):**

```sql
-- Exemplo transactions (template aplicável às outras)
ALTER TABLE transactions
  PARTITION BY RANGE (YEAR(transaction_date)) (
    PARTITION p_2010 VALUES LESS THAN (2011),
    PARTITION p_2011 VALUES LESS THAN (2012),
    PARTITION p_2012 VALUES LESS THAN (2013),
    PARTITION p_2013 VALUES LESS THAN (2014),
    PARTITION p_2014 VALUES LESS THAN (2015),
    PARTITION p_2015 VALUES LESS THAN (2016),
    PARTITION p_2016 VALUES LESS THAN (2017),
    PARTITION p_2017 VALUES LESS THAN (2018),
    PARTITION p_2018 VALUES LESS THAN (2019),
    PARTITION p_2019 VALUES LESS THAN (2020),
    PARTITION p_2020 VALUES LESS THAN (2021),
    PARTITION p_2021 VALUES LESS THAN (2022),
    PARTITION p_2022 VALUES LESS THAN (2023),
    PARTITION p_2023 VALUES LESS THAN (2024),
    PARTITION p_2024 VALUES LESS THAN (2025),
    PARTITION p_2025 VALUES LESS THAN (2026),
    PARTITION p_2026 VALUES LESS THAN (2027),
    PARTITION p_future VALUES LESS THAN MAXVALUE
  );
```

**Implicação operacional:**

- Query "vendas Vargas 2024" → MySQL faz **partition pruning** automático (toca só `p_2024`, não escaneia 15 anos)
- Query "vendas Martinho últimos 30d" → toca só `p_2026` (partição atual)
- Backup partição-by-partição via `mysqldump --where="YEAR(transaction_date) >= 2024"` — incremental real
- DDL `DROP PARTITION p_2010` (LGPD retention expired) é instantâneo, não scan

**Limitação MySQL:** partitioning não acelera `business_id` filter sozinho — global scope multi-tenant continua precisando index composto. Recomendação: índice `(business_id, transaction_date, status)` já existente em UPOS canon basta. Esta ADR **adiciona** partição por ano em cima.

**Gotcha multi-tenant:** queries cross-business (raras — só superadmin / oimpresso interno) ficam mais lentas com partition. Aceitável dado que prod 99% das queries são single-business via global scope ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)).

### Mitigação 3 — Tabela archive opt-in pra ultra-cold (>5 anos sem acesso)

**Schema:**

```sql
CREATE TABLE transactions_archive LIKE transactions;
ALTER TABLE transactions_archive
  ADD COLUMN archived_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN archived_by_job_id VARCHAR(40) NULL,
  ADD INDEX  idx_biz_archived (business_id, archived_at);
```

**Job mensal `ArchiveOldTransactionsJob`:**

- Critério: `transaction_date < NOW() - INTERVAL 60 MONTH` (5 anos — LGPD/Receita 5 anos retenção legal)
- Move row: `INSERT INTO transactions_archive SELECT * FROM transactions WHERE ...; DELETE FROM transactions WHERE ...;`
- Idempotente, chunked 5k rows/iter, roda 1× mês em janela noturna
- UI hot: query default `transactions WHERE ...` (não vê archive)
- UI cold ("Histórico Completo"): query explícita `UNION ALL` cobre archive

**Acionamento:** **opt-in por business**. Default = OFF (mantém tudo em hot). Cliente que pedir "quero ver 15 anos rapidão" fica OFF. Cliente que tem 15 GB e quer baratear: ON via `business_settings.archive_old_transactions = true`.

### Mitigação 4 — Cleanup-first no histórico financeiro (NÃO migrar inadimplência irrecuperável)

Alinhamento com [migracao-officeimpresso-pattern.md §2.4](../reference/migracao-officeimpresso-pattern.md) + [ADR 0171](0171-oficinaauto-ativacao-piloto-martinho-faseada.md) já decididos.

**Regra obrigatória pra Fase 4 (Financeiro):**

```python
# Pattern obrigatório no import-financeiro-from-firebird.py
WRITE_OFF_CANDIDATE = (
  fin_row.DT_VENCTO < (NOW - timedelta(days=365)) and
  not fin_row.BOLETO_ASAAS_ID and
  not fin_row.has_movimentacao_recent
)

if WRITE_OFF_CANDIDATE:
  audit_writeoff_log.append(fin_row)  # NÃO importa — só registra
else:
  insert_fin_titulo(fin_row)
```

**Justificativa:** Martinho tem 76.7% inadimplência legacy. Migrar 100% dos `FINANCEIRO` rows enche `fin_titulos` com milhões de linhas write-off que ninguém vai cobrar. ROI maior é cleanup + foco nos cobráveis. Audit JSON preserva forensics se Wagner mudar opinião depois.

### Mitigação 5 — Plano Hostinger upgrade ANTES do 2º cliente em prod

**Gate obrigatório:**

- Cliente piloto (Martinho biz=164) Fase 3 completa → medir tamanho real DB pós-import
- Se DB > 70% do limite plano → **upgrade obrigatório** antes do Gold/Vargas/Extreme
- Plano alvo (recomendação): **Hostinger VPS KVM 8** ou **Cloud Enterprise** (storage 200+ GB flex, MySQL self-managed) — Wagner decide com número real
- Migração de plano: snapshot VPS → restore em plano maior → swap DNS (downtime < 5min se janela noturna)

**Decisão NÃO tomada nesta ADR:** qual VPS exato comprar. Aguarda diagnóstico Martinho pós-Fase 3.

### Job batched + idempotente pra ETL Firebird → MySQL

**NÃO** rodar importer Python direto inline. Pattern obrigatório:

```php
// Fila Horizon — ETL roda em background, não trava prod
Bus::batch([
  new ImportTransactionsBatchJob(business_id: 164, year: 2010, chunk_size: 5000),
  new ImportTransactionsBatchJob(business_id: 164, year: 2011, chunk_size: 5000),
  // ... 1 job por ano por business
])->onQueue('migrations-legacy')->dispatch();
```

**Idempotência:** `legacy_id` chave natural (índice `(business_id, legacy_id)`) — re-execução não duplica. Per [migracao-officeimpresso-pattern.md §3](../reference/migracao-officeimpresso-pattern.md): UPSERT manual SELECT-then-UPDATE/INSERT.

## Consequências

### Positivas

- **Migração tudo-no-MySQL escala até ~50 clientes** (estimado 5-7 GB × 50 = 250-350 GB, viável em VPS adequado)
- **Object Storage absorve 60-70% do peso bruto** (XMLs/DANFEs) — MySQL fica enxuto pro operacional puro
- **Partitioning + archive** mantém query "últimos 30d" em ~milissegundos mesmo com 15 anos no DB
- **Cleanup-first financeiro** evita inflar `fin_titulos` com 76.7% lixo write-off (caso Martinho)
- **Job Horizon batched** = zero downtime na migração (vs `INSERT` inline)

### Negativas

- **Custo VPS sobe** vs shared Hostinger atual — Wagner aceita (incluído na decisão "tudo no MySQL")
- **Backup mais complexo** — snapshot VPS substitui mysqldump diário; precisa validar restore em sandbox 1×/trimestre
- **Partitioning DDL pesado** pra rodar em prod (lock tabela `transactions` se já tem dados em biz=164) — fazer ANTES de Fase 3 Martinho
- **Object Storage tem latência ~200ms** vs MySQL ~5ms — UX "abrir XML 2012" sente; mitigação: spinner + cache CDN

### Neutras

- **Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) preservado** — global scope continua. Partitioning é ortogonal ao scope (acelera dentro do business, não cross)
- **Runtime Hostinger ≠ CT 100 ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)) preservado** — MySQL operacional fica Hostinger; CT 100 pode ganhar Snowflake/DuckDB analytics futuro (out-of-scope)
- **LGPD retention** — Object Storage lifecycle policy + tabela archive permitem expurgo legal (5 anos docs fiscais; 6 anos contábil) sem trabalho manual

### Tier 0 Risks (mitigação obrigatória)

| Risco | Mitigação |
|---|---|
| ❌ Cross-business data leak via partitioning bug | Pest test `tests/Feature/MultiTenant/PartitionedTablesScopeTest.php` valida que global scope continua filtrando após partitioning |
| ❌ Object Storage credencial vazar | Credencial em Vaultwarden + `.env` (`HOSTINGER_OBJSTOR_KEY`/`SECRET`) — `gitignore` + redação obrigatória em logs |
| ❌ ETL Firebird→MySQL travar prod (lock contention) | Job em fila separada `migrations-legacy` + chunk 5k + `INSERT IGNORE` se row duplicada; rate limit 1k inserts/s |
| ❌ Restore VPS snapshot falhar quando precisar | Drill restore trimestral em sandbox + audit log Vaultwarden |
| 🟡 Archive table sair de sincronia (orphan refs) | FK soft em `transactions_archive` (sem CASCADE); job nightly valida integridade referencial |

## Plano de execução

### Fase 0 — Diagnóstico Hostinger atual (BLOQUEADOR)

Wagner (ou Felipe) roda em phpMyAdmin Hostinger prod:

```sql
-- 1. Tamanho do DB hoje
SELECT table_schema AS db, ROUND(SUM(data_length + index_length)/1024/1024, 1) AS size_mb
FROM information_schema.tables WHERE table_schema = DATABASE() GROUP BY 1;

-- 2. Top 10 tabelas pesadas (esperado: nfe_xmls / transaction_sell_lines / transactions no topo)
SELECT table_name, ROUND((data_length + index_length)/1024/1024, 1) AS size_mb, table_rows
FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY 2 DESC LIMIT 10;

-- 3. Martinho biz=164 hoje
SELECT
  (SELECT COUNT(*) FROM transactions WHERE business_id = 164) AS vendas_total,
  (SELECT COUNT(*) FROM transaction_sell_lines tsl
   JOIN transactions t ON t.id = tsl.transaction_id WHERE t.business_id = 164) AS itens_total;
```

Output cola no chat → decisão Plano Hostinger atual vs upgrade.

### Fase 1 — Migration partitioning (PR isolado, antes Martinho Fase 3)

Felipe: 1 migration `database/migrations/2026_05_XX_partition_transactions_by_year.php` + Pest test scope multi-tenant. ~3h IA-pair.

### Fase 2 — Object Storage setup + adapter Laravel

Felipe: `config/filesystems.php` add disk `object_storage` (Hostinger S3-compat) + Vaultwarden credenciais + Pest test upload/retrieve. ~2h IA-pair.

### Fase 3 — Cleanup-first job financeiro (Felipe per ADR 0171)

Já planejado em [ADR 0171 §Cleanup tools](0171-oficinaauto-ativacao-piloto-martinho-faseada.md). Esta ADR só confirma pattern obrigatório.

### Fase 4 — ETL ImportTransactionsBatchJob + Horizon queue migrations-legacy

Felipe: `Modules/Officeimpresso/Jobs/ImportTransactionsBatchJob.php` + console command `php artisan officeimpresso:migrate-batch --biz=164 --tabela=transactions`. ~4h IA-pair.

### Fase 5 — Smoke Martinho biz=164 (RUNBOOK executável)

Ver [RUNBOOK-migracao-martinho-fase3-fase4.md](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md).

## Review triggers

- DB Hostinger atinge 70% limite plano → upgrade obrigatório (alerta Grafana / health-check)
- 2º cliente em prod (Gold/Vargas/Extreme) → revalidar partitioning sustenta crescimento real
- Object Storage credencial rotacionada (anual) → confirmar zero downtime
- Archive job órfão >1k rows → investigar FK soft drift
- 12 meses após esta ADR → ADR de evolução considerando Snowflake/DuckDB analytics no CT 100 se reports históricos virarem gargalo

## Refs

- [ADR 0197 — Schema PESSOAS→contacts (Fase 1 cadastros)](0197-extend-contacts-absorcao-pessoas-legacy.md)
- [ADR 0171 — Ativação piloto Martinho faseada (cleanup-first)](0171-oficinaauto-ativacao-piloto-martinho-faseada.md)
- [ADR 0062 — Separação runtime Hostinger vs CT 100](0062-separacao-runtime-hostinger-ct100.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md)
- [migracao-officeimpresso-pattern.md (pattern canônico)](../reference/migracao-officeimpresso-pattern.md)
- [ANALISE-CROSS-CLIENTE.md (ordem cutover: Martinho→Gold→Vargas→Extreme)](../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md)
- [Perfil Martinho biz=164 cliente piloto](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- [RUNBOOK-migracao-martinho-fase3-fase4.md (executável Felipe)](../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md)
