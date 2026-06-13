---
slug: modules-arquivos-spec
title: "Modules/Arquivos — SPEC"
type: spec
module: Arquivos
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: rascunho
related_adrs: [0123-modules-arquivos-backbone, 0093-multi-tenant-isolation-tier-0, 0122-admin-center-ct100, 0053-mcp-server-governanca-como-produto]
---
<!-- schema-allowlist: US ativas sob "## Sprint 1 — backbone" / "## Sprint 2..." / "## Sprint 3..." (tabelas US-ARQ-NNN por sprint); módulo a criar, backlog organizado por sprint em vez de heading canônico "## US ativas". -->

# Arquivos — DMS backbone do oimpresso

> Módulo Laravel: `Modules/Arquivos/` (a criar)
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
- Horizon queue (job `ApplyBatchJob` pra ingest do Curador script)

## Sprint 1 — backbone (~3-5 dias IA-pair)

| ID | Título | Prioridade | Estimate |
|---|---|---|---|
| US-ARQ-001 | Scaffold `Modules/Arquivos/` (módulo nWidart, skill `criar-modulo`) | p1 | 2h |
| US-ARQ-002 | Migration `arquivos` (28 colunas, 5 índices, 2 FKs com business_id Tier 0) | p1 | 2h |
| US-ARQ-003 | Migration `arquivos_audit_log` + `arquivos_dedupe` | p1 | 1h |
| US-ARQ-004 | Service `ArquivosService` (attach, classify, signedUrl, softDelete, restore, dedupe) | p0 | 4h |
| US-ARQ-005 | Trait `HasArquivos` + Pest test polimorfismo (anexa em 3 models diferentes) | p1 | 2h |
| US-ARQ-006 | `CuradorEngine.php` (port das 15+ regras de `lib/rules.mjs`) | p1 | 4h |
| US-ARQ-007 | ParityTest JS×PHP (mesmo MD5+path → mesmo bucket — 100 fixtures) | p1 | 3h |
| US-ARQ-008 | Storage disks config (`arquivos`+`vault`) + signed URL controller (expiração 1h, audit log) | p0 | 3h |
| US-ARQ-009 | Pest tests (multi-tenant isolation, sensitive blocking, dedupe, soft-delete, audit log) | p0 | 4h |
| US-ARQ-010 | Migration backfill: 1 fixture XML real → ingest via `attach()` → smoke validation | p1 | 1h |

## Sprint 2 — UI Admin Center + Curador script integration (~3-5 dias)

| ID | Título | Prioridade | Depends |
|---|---|---|---|
| US-ARQ-011 | API `POST /admin/arquivos/api/upload-batch` recebe JSONL do `scripts/curador/discover.mjs` | p1 | US-ARQ-004 |
| US-ARQ-012 | Auth Bearer token gerado em `/admin/tokens` (escope `arquivos:write`) | p0 | US-ADM-003 |
| US-ARQ-013 | Page `Modules/Admin/Pages/Arquivos/Index.tsx` (lista batches/arquivos, filtro por bucket+business) | p1 | US-ARQ-011 |
| US-ARQ-014 | Page `Pages/Arquivos/Review.tsx` (substitui markdown `[x]` — checkbox UI, search, bulk-approve) | p1 | US-ARQ-013 |
| US-ARQ-015 | Page `Pages/Arquivos/Detail.tsx` (preview MIME-aware: PDF embed, image, code highlight, JSON tree) | p2 | US-ARQ-013 |
| US-ARQ-016 | Job `ApplyBatchJob` Horizon (recebe approved IDs, move pro storage final + dispara classification) | p1 | US-ARQ-013 |
| US-ARQ-017 | Refactor `scripts/curador/apply.mjs` → vira "submit pro Admin API" (deixa de mexer filesystem direto) | p2 | US-ARQ-011 |
| US-ARQ-018 | Widget Admin Center "Arquivos" (count por bucket, sensitive aguardando vault, métricas saúde) | p2 | US-ARQ-014 |

## Sprint 3 — primeiro consumer real

| ID | Título | Prioridade | Depends |
|---|---|---|---|
| US-ARQ-019 | `Modules/NfeBrasil/Models/NfeXml` adota trait `HasArquivos` | p1 | US-ARQ-005 |
| US-ARQ-020 | Migration backfill: NFe XMLs existentes em `storage/nfe/` → `arquivos` table com `arquivable=NfeXml` | p1 | US-ARQ-019 |
| US-ARQ-021 | Smoke: novo NFe import → XML em `/var/lib/oimpresso-arquivos/biz-1/...` + audit log linha | p0 | US-ARQ-020 |
| US-ARQ-022 | Officeimpresso UI lê NFe XML via `arquivable->arquivos()` (não path direto) — backward compat preservada | p1 | US-ARQ-019 |

## Sprint 4+ — outros módulos opt-in (conforme prioridade)

> **2026-05-10 — mapeamento completo via Agent F (Curador subagente)**: 10 consumers identificados em 26 migrations + 15 controllers de `Modules/*`.

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

1. **US-ARQ-023**: Migrar `Modules/Financeiro/FinBoletoRemessa` (PDFs boleto gerados — volume razoável, baixo cliente-facing)
2. **US-ARQ-024**: Migrar `Modules/Ponto/Importacao` (arquivos folha eSocial — médio risco compliance)
3. **US-ARQ-025**: Migrar `Modules/Jana/TaskAttachment` (consolida sha256 dedup com `arquivos_dedupe`)
4. **US-ARQ-026**: Migrar `Modules/SRS/DocSource` (knowledge base — volume crescente)

### Sprint 5 (deferred)

- Whatsapp inline mídia (volume gigante, vai precisar S3 antes — adiar até disk swap)
- CMS feature_image (baixo volume, pode esperar)
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

- ✅ Pest: query em context biz=1 NÃO retorna arquivos biz=4 (multi-tenant Tier 0)
- ✅ Pest: upload `.env` → bucket=sensitive + disk=vault automático
- ✅ Pest: 2× upload mesmo MD5 mesmo business → mesma row (dedupe)
- ✅ ParityTest: 100 fixtures comuns → mesmo bucket em CuradorEngine.php e classify-rules.mjs
- ✅ Smoke: NFe import deposita XML em CT 100 mount + audit log linha
- ✅ Audit log preenche pras 8 ações enum

## Métricas de saúde (jana:health-check + Admin Center widget)

- `arquivos_orphaned` (`arquivable_id NULL` AND `bucket NOT IN sensitive,active`) — alerta se >0
- `vault_count_growing_drift` — `vault` deve crescer monotonamente; queda = tampering?
- `dedupe_collisions_24h` — quantos uploads viraram dedup hit (alto = origem caótica)
- `audit_gaps` — arquivo sem audit log entry de upload (data inconsistency)
