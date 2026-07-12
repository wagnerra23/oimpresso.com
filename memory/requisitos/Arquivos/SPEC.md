---
slug: modules-arquivos-spec
title: "Modules/Arquivos — SPEC"
type: spec
module: Arquivos
version: "1.0"
last_updated: "2026-07-02"
owners: [W]
status: rascunho
anchor_format: "v1"
related_adrs: [0123-modules-arquivos-backbone, 0093-multi-tenant-isolation-tier-0, 0122-admin-center-ct100, 0053-mcp-server-governanca-como-produto]
---
<!-- schema-allowlist: US ativas sob "## Sprint 1 — backbone" / "## Sprint 2..." / "## Sprint 3..." (headings US-ARQ-NNN por sprint); módulo criado (Sprint 1 landed), backlog organizado por sprint em vez de heading canônico "## US ativas". -->

# Arquivos — DMS backbone do oimpresso

> Módulo Laravel: `Modules/Arquivos/` (criado — Sprint 1 landed)
> ADR mãe: [0123](../../decisions/0123-modules-arquivos-backbone.md)
> Princípio: **todo arquivo anexado deve cair lá**

## O que é

Backbone único que armazena, classifica, audit-loga e serve qualquer arquivo da empresa — anexos de ticket, XML NF-e, foto de OS, upload de blog, secrets, certificados, manuais. Outros módulos delegam upload via trait `HasArquivos`.

## Princípios duros (do ADR 0123)

1. Polimorfismo via Eloquent morph (`arquivable_type`, `arquivable_id`)
2. **Multi-tenant Tier 0 obrigatório** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
3. Storage abstraído (Laravel Filesystem) — disk default `local-ct100`, swap S3 futuro
4. Trait `HasArquivos` — outros models adotam opt-in
5. Curador como engine (`Services/CuradorEngine.php` port das regras JS)
6. Sensitive bloqueia disk default (vai pra `vault` encrypted-at-rest)
7. Soft-delete (não rm); hard-delete agendado N=90 dias
8. Audit log integral

## Stack

- Laravel 13.6 + PHP 8.4 (CT 100 FrankenPHP)
- MySQL via autossh tunnel (mesma DB do app principal)
- Storage disk `arquivos` mounted em `/var/lib/oimpresso-arquivos/`
- Storage disk `vault` separado, encrypted-at-rest
- Inertia v3 + React 19 (UI no Admin Center, [ADR 0122](../../decisions/0122-admin-center-ct100.md))
- Horizon queue (job `ApplyBatchJob` pra ingest do Curador script — pendente, US-ARQ-016)

## Sprint 1 — backbone (~3-5 dias IA-pair)

> Landed — módulo `Modules/Arquivos/` existe no disco com 8 peças nWidart, 6 migrations, Service, trait, CuradorEngine, disks e 22 arquivos de teste Pest (~174 casos).

### US-ARQ-001 · Scaffold `Modules/Arquivos/` (módulo nWidart, skill `criar-modulo`) `p1`
**Implementado em:** `Modules/Arquivos/module.json` · `Modules/Arquivos/Providers/ArquivosServiceProvider.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-002 · Migration `arquivos` (22 colunas, 5 índices; FKs business/user comentadas até validação homolog) `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000001_create_arquivos_table.php` · verificado@dad0b11 (2026-07-02) — schema real: 22 colunas + 5 índices; as 2 FKs (business/users) estão **comentadas** na migration (L59-60, "Wagner valida em homolog") — isolamento Tier 0 via `business_id` indexado + `BusinessScope`, não via FK física ainda

### US-ARQ-003 · Migration `arquivos_audit_log` + `arquivos_dedupe` `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php` · `Modules/Arquivos/Database/Migrations/2026_05_10_000003_create_arquivos_dedupe_table.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-004 · Service `ArquivosService` (attach, classify, signedUrl, softDelete, restore, dedupe) `p0`
**Implementado em:** `Modules/Arquivos/Services/ArquivosService.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-005 · Trait `HasArquivos` + Pest test polimorfismo (anexa em 3 models diferentes) `p1`
**Implementado em:** `Modules/Arquivos/Concerns/HasArquivos.php` · `Modules/Arquivos/Tests/Feature/ConsumersTraitTest.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-006 · `CuradorEngine.php` (port das 15+ regras de `lib/rules.mjs`) `p1`
**Implementado em:** `Modules/Arquivos/Services/Curador/CuradorEngine.php` · `Modules/Arquivos/Tests/Feature/CuradorEngineTest.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-007 · ParityTest JS×PHP (mesmo MD5+path → mesmo bucket — 30 fixtures) `p1`
**Implementado em:** `Modules/Arquivos/Tests/Feature/CuradorParityTest.php` · `scripts/curador/parity-fixtures.mjs` · verificado@dad0b11 (2026-07-02)

### US-ARQ-008 · Storage disks config (`arquivos`+`vault`) + signed URL controller (expiração 1h, audit log) `p0`
**Implementado em:** `config/filesystems.php` · `Modules/Arquivos/Http/Controllers/DownloadController.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-009 · Pest tests (multi-tenant isolation, sensitive blocking, dedupe, soft-delete, audit log) `p0`
**Implementado em:** `Modules/Arquivos/Tests/Feature/MultiTenantTest.php` · `Modules/Arquivos/Tests/Feature/VaultEncryptionServiceTest.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-010 · Migration backfill: 1 fixture XML real → ingest via `attach()` → smoke validation `p1`
**Implementado em:** _parcial_ · `Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@dad0b11 (2026-07-02) — ingest de XMLs reais coberto pela migration+teste de backfill NFe; falta o smoke isolado "1 fixture XML canônica"

## Sprint 2 — UI Admin Center + Curador script integration (~3-5 dias)

> Não construída. Curador só existe como scripts CLI (`scripts/curador/*.mjs`) que mexem filesystem direto; não há endpoint `upload-batch`, nem Pages Inertia, nem `ApplyBatchJob`, nem token scope `arquivos:write`.

### US-ARQ-011 · API `POST /admin/arquivos/api/upload-batch` recebe JSONL do `scripts/curador/discover.mjs` `p1`
**Implementado em:** _pendente_ — endpoint upload-batch não existe; `scripts/curador/apply.mjs` ainda mexe filesystem direto (US-ARQ-017)

### US-ARQ-012 · Auth Bearer token gerado em `/admin/tokens` (escope `arquivos:write`) `p0`
**Implementado em:** _pendente_ — scope `arquivos:write` não existe; depende de US-ADM-003

### US-ARQ-013 · Page `Modules/Admin/Pages/Arquivos/Index.tsx` (lista batches/arquivos, filtro por bucket+business) `p1`
**Implementado em:** _pendente_ — nenhuma Page Inertia de Arquivos existe (sem `Modules/Admin/Pages/Arquivos/` nem `resources/js/Pages/Arquivos/`); módulo é backbone sem UI própria

### US-ARQ-014 · Page `Pages/Arquivos/Review.tsx` (substitui markdown `[x]` — checkbox UI, search, bulk-approve) `p1`
**Implementado em:** _pendente_ — Page não existe; depende de US-ARQ-013

### US-ARQ-015 · Page `Pages/Arquivos/Detail.tsx` (preview MIME-aware: PDF embed, image, code highlight, JSON tree) `p2`
**Implementado em:** _pendente_ — Page não existe; depende de US-ARQ-013

### US-ARQ-016 · Job `ApplyBatchJob` Horizon (recebe approved IDs, move pro storage final + dispara classification) `p1`
**Implementado em:** _pendente_ — job `ApplyBatchJob` não existe no repo

### US-ARQ-017 · Refactor `scripts/curador/apply.mjs` → vira "submit pro Admin API" (deixa de mexer filesystem direto) `p2`
**Implementado em:** _pendente_ — `scripts/curador/apply.mjs` ainda mexe filesystem direto; depende do endpoint US-ARQ-011

### US-ARQ-018 · Widget Admin Center "Arquivos" (count por bucket, sensitive aguardando vault, métricas saúde) `p2`
**Implementado em:** `Modules/Admin/Services/CuradorStatsReader.php` · `resources/js/Pages/Admin/_components/WidgetCurador.tsx` · `Modules/Admin/Http/Controllers/IndexController.php` · `IndexController@__invoke` · verificado@dad0b11 (2026-07-02) — widget "W5 Curador" injeta `curador` prop (IndexController L64, controller invokable) com count por bucket + `sensitive_count` + audit 24h + dedupe; badge sensitive na tela `resources/js/Pages/Admin/Index.tsx`

## Sprint 3 — primeiro consumer real

> Backfill NFe landed. Os models `NfeEmissao`/`NfeDfeRecebido` adotam `HasArquivos`; a migration 000010 backfilla os XMLs existentes. Pendências: o model literal `NfeXml` do texto original não existe (a adoção foi em Emissao/DfeRecebido) e falta o smoke de import ao vivo.

### US-ARQ-019 · `Modules/NfeBrasil/Models/NfeXml` adota trait `HasArquivos` `p1`
**Implementado em:** _parcial_ · `Modules/NfeBrasil/Models/NfeEmissao.php` · `Modules/NfeBrasil/Models/NfeDfeRecebido.php` · `Modules/NfeBrasil/Tests/Feature/HasArquivosTraitTest.php` · verificado@dad0b11 (2026-07-02) — trait adotado em NfeEmissao/NfeDfeRecebido (não há model `NfeXml` literal; a US refere-se aos models de XML reais)

### US-ARQ-020 · Migration backfill: NFe XMLs existentes em `storage/nfe/` → `arquivos` table com `arquivable=NfeXml` `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@dad0b11 (2026-07-02)

### US-ARQ-021 · Smoke: novo NFe import → XML em `/var/lib/oimpresso-arquivos/biz-1/...` + audit log linha `p0`
**Implementado em:** _parcial_ · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@dad0b11 (2026-07-02) — ingest+audit validados em teste de backfill; falta o smoke de novo import NFe ao vivo depositando no mount CT 100

### US-ARQ-022 · Officeimpresso UI lê NFe XML via `arquivable->arquivos()` (não path direto) — backward compat preservada `p1`
**Implementado em:** _parcial_ · `Modules/NfeBrasil/Models/NfeEmissao.php` · `Modules/NfeBrasil/Services/DanfeService.php` · `Modules/NfeBrasil/Tests/Feature/DanfeServicePrefersArquivosTest.php` · verificado@dad0b11 (2026-07-02) — accessor `xml_arquivo` via `arquivos()` existe em NfeEmissao e `DanfeService` (L85) já PREFERE o accessor com fallback legacy `xml_path`; falta só a UI Officeimpresso consumir pela relação

## Sprint 4+ — outros módulos opt-in (conforme prioridade)

> **2026-05-10 — mapeamento completo via Agent F (Curador subagente)**: 10 consumers identificados em 26 migrations + 15 controllers de `Modules/*`.

> ⚠️ Nomes deste snapshot (2026-05-10) são aspiracionais; reais no código: Financeiro = `BoletoRemessa` (não `FinBoletoRemessa`); **não existe Entity `TaskAttachment`** (só tabela `mcp_task_attachments`); Jana = `McpMemoryDocument` (singular); o disk DFe é resolvido via `config('nfebrasil.dfes_recebidos_disk', 'local')` (não há disk `nfe_dfes_recebidos` em `config/filesystems.php`).

| Módulo | Model | Tabela | Coluna(s) anexo | Storage atual | Risco | Sprint |
|---|---|---|---|---|---|---|
| **NfeBrasil** | `NfeDfeRecebido` | `nfe_dfe_recebidos` | `xml_path` | disk `nfe_dfes_recebidos` | high | **3** ✅ planejado |
| **NfeBrasil** | `NfeEmissao` | `nfe_emissoes` | `xml_path`, `danfe_path` | disk `nfe_dfes_recebidos` | high | **3** |
| **NfeBrasil** | `NfeCertificado` | `nfe_certificados` | `uuid.pfx.enc` (encrypted-at-rest) | custom encrypted | **high** | **3** (já encrypted, validar parity vault) |
| **Financeiro** | `FinBoletoRemessa` | `fin_boleto_remessas` | `pdf_path` | TBD | medium | **4** |
| **Ponto** | `Importacao` | `ponto_importacoes` | `arquivo_path` | disk `local` | medium | **4** |
| **Jana** | `TaskAttachment` | `mcp_task_attachments` | `file_url` + `sha256` dedupe | TBD | medium | **4** |
| **SRS** | `DocSource` | `docs_sources` | `storage_path` | `config('memcofre.upload.disk')` | medium | **4** |
| **Whatsapp** | `WhatsappMessage` | `whatsapp_messages` | payload JSON (mídia inline) | inline | low | **5** |
| **CMS** | `CmsPage` | `cms_pages` | `feature_image` | TBD | low | **5** |
| **Jana** | `McpMemoryDocuments` | `mcp_memory_documents` | `embedding` (binary vetores) | TBD | low | **5** (caso especial — vetores Meilisearch) |

### 3 riscos transversais

1. **Storage disks NÃO padronizado.** Cada módulo usa disk próprio (`nfe_dfes_recebidos`, `local`, `config('memcofre.upload.disk')`). **Trait `HasArquivos` precisa abstrair multi-disk** OU rodar **ADR-novo** consolidando tudo em disk único `arquivos` antes de Sprint 4 (recomendação Agent F).

2. **Encryption-at-rest só em NfeBrasil.** Certificados PFX cifrados em disco (uuid.pfx.enc); outros módulos (Ponto/SRS/Jana) armazenam em claro. **Audit LGPD pré-migração** obrigatório. Jana memory docs terão dados sensíveis em breve ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) §Encryption).

3. **Deduplicação inconsistente.** Ponto + Jana usam SHA-256 em DB; SRS + Whatsapp não. Trait `HasArquivos` deve **enforçar dedup global por hash** (`arquivos_dedupe` table do [ADR 0123](../../decisions/0123-modules-arquivos-backbone.md)) antes de Sprint 4 começar.

### Ordem de execução Sprint 4 (proposta com base em valor × risco)

> Bucket CMS `feature_image` + Financeiro `fin_boleto_remessas.pdf_path` já têm backfill na migration `2026_05_10_000020_backfill_consumers_arquivos.php`. As US abaixo cobrem a adoção completa (trait + read-path) de cada consumer.

### US-ARQ-023 · Migrar `Modules/Financeiro/FinBoletoRemessa` (PDFs boleto gerados — volume razoável, baixo cliente-facing) `p1`
**Implementado em:** `Modules/Financeiro/Models/BoletoRemessa.php` · `BoletoRemessa::getPdfArquivoAttribute` · `Modules/Arquivos/Database/Migrations/2026_05_10_000020_backfill_consumers_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillConsumersTest.php` · verificado@dad0b11 (2026-07-02) — `BoletoRemessa` adota o trait `HasArquivos` (L21) + accessor de leitura via relação `getPdfArquivoAttribute` (L95, `sub_destination=fin-boleto-pdf`) + backfill do `pdf_path` legado; double-write intencional (ADR 0123 Sprint 4) enquanto consumidores externos do `pdf_path` não migram

### US-ARQ-024 · Migrar `Modules/Ponto/Importacao` (arquivos folha eSocial — médio risco compliance) `p1`
**Implementado em:** _pendente_ — sem backfill nem adoção de trait para `ponto_importacoes.arquivo_path`

### US-ARQ-025 · Migrar `Modules/Jana/TaskAttachment` (consolida sha256 dedup com `arquivos_dedupe`) `p1`
**Implementado em:** _pendente_ — `mcp_task_attachments` não migrado; consolidação sha256 com `arquivos_dedupe` não feita

### US-ARQ-026 · Migrar `Modules/SRS/DocSource` (knowledge base — volume crescente) `p1`
**Implementado em:** _pendente_ — `docs_sources.storage_path` não migrado para `arquivos`

### Sprint 5 (deferred)

- Whatsapp inline mídia (volume gigante, vai precisar S3 antes — adiar até disk swap)
- CMS feature_image (trait + accessor `feature_image_arquivo` já landed em `CmsPage.php` L13 + coberto no `ConsumersTraitTest`; resta remover fallback da coluna legacy)
- Jana McpMemoryDocuments embedding vetores (caso especial — pode ficar em Meilisearch sem ir pra `arquivos` table)

## Sprint Future — observability + features avançadas

- Meilisearch full-text indexing (Jana recall on-demand)
- OCR de PDF scan (Tesseract via job)
- Antivirus scan (ClamAV) ativado se cliente externo upload
- S3 swap (Backblaze/Wasabi) quando CT 100 disk passar 80% cap
- Versioning (track changes em arquivo editado)
- Public sharing com signed URL pra cliente (compartilhar contrato)

## Não-goals (do ADR 0123)

- ❌ NÃO substitui MemCofre (anotações ≠ binary)
- ❌ NÃO replica Copiloto/Memoria (RAG semântico ≠ DMS)
- ❌ NÃO indexa full-text MVP
- ❌ NÃO faz OCR/transcrição MVP
- ❌ NÃO antivirus scan MVP
- ❌ NÃO substitui storage existente automaticamente — opt-in

## MIME whitelist por contexto (do ADR 0123)

| Contexto | MIMEs | Cap |
|---|---|---|
| `nfe-xml` | `application/xml`, `text/xml` | 5MB |
| `ticket-anexo` | xml, pdf, png, jpg, doc, docx, xlsx | 25MB |
| `repair-foto` | png, jpg, heic, webp | 10MB |
| `cms-blog-image` | png, jpg, webp, svg, gif | 5MB |
| `admin-curador` | * (admin Wagner) | 50MB |

## Validação Sprint 1

- ✅ Pest: query em context biz=1 NÃO retorna arquivos biz=99 (multi-tenant Tier 0 — `MultiTenantTest.php`)
- ✅ Pest: bucket=sensitive automático pra `.env` (`CuradorEngineTest.php`); 🟡 assert `disk=vault` via `attach()` _pendente_ (decisão sensitive→vault em `ArquivosService` L88-90 + write encrypted L98-101, sem cobertura Pest)
- 🟡 Pest: 2× upload mesmo MD5 mesmo business → mesma row (dedupe) — _pendente_ (comportamento em `ArquivosService::dedupe`, sem Pest; listado como cobertura crítica pendente no `ScaffoldTest`)
- ✅ ParityTest: 30 fixtures comuns → mesmo bucket em `CuradorEngine.php` e `scripts/curador/lib/rules.mjs`
- 🟡 Smoke: NFe import deposita XML em CT 100 mount + audit log linha — _pendente_ (smoke ao vivo no mount CT 100 não representável em repo; ver US-ARQ-010/021 `_parcial_`)
- 🟡 Audit log: enum define 8 ações (`upload`/`reclassify`/`classify`/`download`/`signed_url_issued`/`soft_delete`/`restore`/`hard_delete`); código emite um subconjunto + `signed_url_consumed` (fora do enum — bug catalogado, task separada)

## Métricas de saúde (`arquivos:health-check` + Admin Center widget)

Comando real `HealthCheckCommand` (`arquivos:health-check`), 5 checks:

- `orphan_files` — arquivo no DB sem file físico no disk (sample cap 1000; WARN ≥1%, FAIL >10%)
- `dedupe_inconsistent` — divergência de dedupe (md5 duplicado sem row canônica)
- `audit_log_lag` — tempo desde o último registro no audit log (WARN >24h — sistema parado ou log não escrevendo)
- `retention_overdue` — arquivo além do `retention_days` sem purge
- `vault_encryption_ratio` — proporção de arquivos `bucket=sensitive` com `encrypted=true` (encryption-at-rest)
