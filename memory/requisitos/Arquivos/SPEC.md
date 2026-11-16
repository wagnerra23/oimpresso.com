---
id: requisitos-arquivos-spec
slug: modules-arquivos-spec
title: "Modules/Arquivos — SPEC"
type: spec
module: Arquivos
version: "1.0"
last_updated: "2026-11-16"
owner: wagner
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
3. Storage abstraído (Laravel Filesystem) — disk default em `arquivos.disk_default`, hoje `local`. O `local-ct100` nunca foi criado; a troca para MinIO ([ADR 0214](../../decisions/0214-arquivos-storage-s3-minio-ct100.md)) está pendente da decisão B4 do [W]
4. Trait `HasArquivos` — outros models adotam opt-in
5. Curador como engine (`Services/CuradorEngine.php` port das regras JS)
6. Sensitive bloqueia disk default (vai pra `vault` encrypted-at-rest)
7. Soft-delete (não rm); hard-delete agendado N=90 dias
8. Audit log integral

## Stack

- Laravel 13.6 + PHP 8.4 (CT 100 FrankenPHP)
- MySQL via autossh tunnel (mesma DB do app principal)
- Storage disks `arquivos` e `vault` em `config/filesystems.php`; o `vault` grava cifrado com `Crypt::encryptString` (`VaultEncryptionService`). O mount em `/var/lib/oimpresso-arquivos/` previsto aqui não existe
- Inertia v3 + React 19: tela `/arquivos` (`resources/js/Pages/Arquivos/Index.tsx`). O Admin Center previsto em 2026-05 ([ADR 0122](../../decisions/0122-admin-center-ct100.md)) foi removido em 2026-07-29 ([ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md))
- Horizon queue (job `ApplyBatchJob` pra ingest do Curador script — pendente, US-ARQ-016)

## Sprint 1 — backbone (~3-5 dias IA-pair)

> Landed — em 2026-07-02 o módulo tinha 8 peças nWidart, 6 migrations, Service, trait, CuradorEngine, disks e 22 arquivos de teste Pest (~174 casos). A contagem de testes cresceu depois; o número vivo é `ls Modules/Arquivos/Tests/Feature`.

### US-ARQ-001 · Scaffold `Modules/Arquivos/` (módulo nWidart, skill `criar-modulo`) `p1`
**Implementado em:** `Modules/Arquivos/module.json` · `Modules/Arquivos/Providers/ArquivosServiceProvider.php` · verificado@580be57 (2026-11-16)
**Aceite:** o `module.json` declara o `ArquivosServiceProvider`, e o provider registra as rotas, a config e os comandos `arquivos:*` do módulo.

### US-ARQ-002 · Migration `arquivos` (22 colunas, 5 índices; FKs business/user comentadas até validação homolog) `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000001_create_arquivos_table.php` · verificado@580be57 (2026-11-16) — schema real: 22 colunas + 5 índices; as 2 FKs (business/users) estão **comentadas** na migration (L59-60, "Wagner valida em homolog") — isolamento Tier 0 via `business_id` indexado + `BusinessScope`, não via FK física ainda
**Aceite:** a tabela `arquivos` tem `business_id` indexado; o isolamento vem do escopo de empresa do model, não de FK física (as 2 FKs seguem comentadas).

### US-ARQ-003 · Migration `arquivos_audit_log` + `arquivos_dedupe` `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php` · `Modules/Arquivos/Database/Migrations/2026_05_10_000003_create_arquivos_dedupe_table.php` · verificado@580be57 (2026-11-16)
**Aceite:** `arquivos_audit_log` recebe uma linha por ação, com a ação dentro do enum da tabela (`AuditLogActionEnumTest`); `arquivos_dedupe` guarda o md5 por empresa.

### US-ARQ-004 · Service `ArquivosService` (attach, classify, signedUrl, softDelete, restore, dedupe) `p0`
**Implementado em:** `Modules/Arquivos/Services/ArquivosService.php` · verificado@580be57 (2026-11-16)
**Aceite:** o mesmo dono anexando o mesmo conteúdo 2× recebe a mesma linha; outro dono ganha linha própria apontando para o mesmo blob; purgar a linha de um dono não apaga o blob que o outro usa (`DedupeMultiOwnerTest`, UC-ARQ-DEDUP-01..07). Bucket `sensitive` vai para o disk `vault`, cifrado.

### US-ARQ-005 · Trait `HasArquivos` + Pest test polimorfismo (anexa em 3 models diferentes) `p1`
**Implementado em:** `Modules/Arquivos/Concerns/HasArquivos.php` · `Modules/Arquivos/Tests/Feature/ConsumersTraitTest.php` · verificado@580be57 (2026-11-16)
**Aceite:** model que usa o trait expõe `arquivos()`; os accessors de leitura devolvem `null` sem arquivo e preservam o campo legado (`ConsumersTraitTest`: `JobSheet`, `CmsPage`, `BoletoRemessa`).

### US-ARQ-006 · `CuradorEngine.php` (port das 15+ regras de `lib/rules.mjs`) `p1`
**Implementado em:** `Modules/Arquivos/Services/Curador/CuradorEngine.php` · `Modules/Arquivos/Tests/Feature/CuradorEngineTest.php` · verificado@580be57 (2026-11-16)
**Aceite:** `.env` real é `sensitive` e `.env.example` não; `.pfx`, chave SSH e arquivo de credenciais caem em `sensitive`; `node_modules` e clone de OSS caem em `discard` (`CuradorEngineTest`).

### US-ARQ-007 · ParityTest JS×PHP (mesmo MD5+path → mesmo bucket — 30 fixtures) `p1`
**Implementado em:** `Modules/Arquivos/Tests/Feature/CuradorParityTest.php` · `scripts/curador/parity-fixtures.mjs` · verificado@580be57 (2026-11-16)
**Aceite:** `rule_matched` igual entre JS e PHP em ≥95% dos casos não-fallback, e as fixtures `sensitive`, `discard` e `memory` caem no mesmo bucket dos dois lados (`CuradorParityTest`).

### US-ARQ-008 · Storage disks config (`arquivos`+`vault`) + signed URL controller (expiração 1h, audit log) `p0`
**Implementado em:** `config/filesystems.php` · `Modules/Arquivos/Http/Controllers/DownloadController.php` · `Modules/Arquivos/Tests/Feature/DownloadAuditTest.php` · verificado@580be57 (2026-11-16)
**Aceite:** a rota `arquivos.download` exige o middleware `signed`; link adulterado ou expirado é barrado; arquivo de outra empresa responde 404; consumir o link grava `signed_url_consumed` na trilha (`MultiTenantTest`, `DownloadAuditTest` UC-ARQ-DL-01..05).

### US-ARQ-009 · Pest tests (multi-tenant isolation, sensitive blocking, dedupe, soft-delete, audit log) `p0`
**Implementado em:** `Modules/Arquivos/Tests/Feature/MultiTenantTest.php` · `Modules/Arquivos/Tests/Feature/VaultEncryptionServiceTest.php` · verificado@580be57 (2026-11-16)
**Aceite:** com sessão da empresa 98, o arquivo da 99 não aparece na listagem nem no `find()` por id; o `creating` preenche o `business_id` da sessão; a dedupe não cruza empresas (`MultiTenantTest`).

### US-ARQ-010 · Migration backfill: 1 fixture XML real → ingest via `attach()` → smoke validation `p1`
**Implementado em:** _parcial_ · `Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@580be57 (2026-11-16) — ingest de XMLs reais coberto pela migration+teste de backfill NFe; falta o smoke isolado "1 fixture XML canônica"
**Aceite:** XML e DANFE viram linhas em `arquivos` na empresa da NFe, sem misturar 98 e 99; rodar a migration 2× não duplica; `down()` apaga só as linhas com a tag do backfill (`BackfillNfeXmlTest`).

## Sprint 2 — UI Admin Center + Curador script integration (~3-5 dias)

> Não construída. Curador só existe como scripts CLI (`scripts/curador/*.mjs`) que mexem filesystem direto; não há endpoint `upload-batch`, nem Pages Inertia, nem `ApplyBatchJob`, nem token scope `arquivos:write`.

### US-ARQ-011 · API `POST /arquivos/api/upload-batch` recebe JSONL do `scripts/curador/discover.mjs` `p1`
**Implementado em:** _pendente_ — endpoint upload-batch não existe; `scripts/curador/apply.mjs` ainda mexe filesystem direto (US-ARQ-017)

> **Rota reescrita em 2026-07-29** ([ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md)): perdeu o prefixo `/admin/`, que pertencia ao Admin Center deprecado (e era gated por Tailscale — 403 pra qualquer chamada de fora). Nasce no próprio módulo.

### US-ARQ-012 · Auth Bearer token com scope `arquivos:write` `p0`
**Implementado em:** _pendente_ — scope `arquivos:write` não existe

> **Dependência e rota reescritas em 2026-07-29** ([ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md)): o título citava emissão em `/admin/tokens` — rota que **nunca existiu** (o Admin Center tinha só `/`, `mutations/*`, `governance-v4`, `governance/v4`, `rag-quality`, `screen-review`, `feature-flags`) — e o corpo dependia de `US-ADM-003`, o auth gate que morre com o módulo. A emissão herda do gestor de token canônico do projeto (`/copiloto/admin/team`, per [what-oimpresso.md](../../what-oimpresso.md)); a rota exata se confirma quando a US for implementada, não se inventa aqui.

### US-ARQ-013 · Page `resources/js/Pages/Arquivos/Index.tsx` (lista batches/arquivos, filtro por bucket+business) `p1`
**Implementado em:** _pendente_ — a Page existe desde 2026-08-24 (PR #6216: Index.tsx + rota GET /arquivos) e tem desde então as 4 vistas do charter — Acervo, Retenção, Cofre e Trilha —, todas na mesma rota pelo parâmetro de aba. A US segue `_pendente_` porque ainda nenhum teste declara `@covers-us US-ARQ-013` (ver a sentinela abaixo); o estado anterior desta linha (3 das 4 vistas, fecharia com a Retenção) foi verdade até 2026-08 e caducou

> **Sentinela mantida de propósito** (2026-08-25) — e não é leitura, é bite-test. Aplicando a âncora com path real (`Index.tsx` · `ArquivosAdminController.php` · `verificado@8c30820`) e rodando `anchor-lint --check-entry --check-covers --baseline governance/anchor-entry-baseline.json`, o gate **mordeu com `rc=1`**: `📋 diz IMPLEMENTADA mas SEM aceite/DoD definido` + `🚪 NENHUM teste declara @covers-us dela` (US-ARQ-013 não está no baseline grandfather — `grep -c` = 0, então não é mentira isenta, é mentira nova). A US não tem aceite escrito e o `ArquivosAdminControllerTest` não declara `@covers-us`. Inventar aceite pra passar no gate seria falsificar o contrato: a âncora vira path real no PR que fechar a onda 1, junto com o aceite que [W] declarar.
>
> _Efeito colateral medido no caminho:_ o `extractPaths` do lint trata qualquer trecho com `/` que não comece com `/` como caminho de arquivo — então `` `GET /arquivos` `` dentro da prosa da âncora vira 💀 `anchored_dead`. Por isso a prosa acima cita a rota **sem crase**. Inerte enquanto a sentinela for `_pendente_` (o `classify()` retorna antes de extrair paths), mina armada no dia em que ela mudar.

> **Destino reescrito em 2026-07-29** ([ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md), decisão [W] *"pode ser dentro do arquivo mesmo"*): a tela era planejada dentro do Admin Center (`Modules/Admin/Pages/Arquivos/Index.tsx`), que foi deprecado. Passa a nascer no próprio módulo, em `resources/js/Pages/Arquivos/` — o padrão canônico do projeto (telas Inertia vivem em `resources/js/Pages/<Mod>/`, nunca dentro de `Modules/`).

### US-ARQ-014 · Page `Pages/Arquivos/Review.tsx` (substitui markdown `[x]` — checkbox UI, search, bulk-approve) `p1`
**Implementado em:** _pendente_ — Page não existe; depende de US-ARQ-013

### US-ARQ-015 · Page `Pages/Arquivos/Detail.tsx` (preview MIME-aware: PDF embed, image, code highlight, JSON tree) `p2`
**Implementado em:** _pendente_ — Page não existe; depende de US-ARQ-013

### US-ARQ-016 · Job `ApplyBatchJob` Horizon (recebe approved IDs, move pro storage final + dispara classification) `p1`
**Implementado em:** _pendente_ — job `ApplyBatchJob` não existe no repo

### US-ARQ-017 · Refactor `scripts/curador/apply.mjs` → vira "submit pro endpoint do Arquivos" (deixa de mexer filesystem direto) `p2`
**Implementado em:** _pendente_ — `scripts/curador/apply.mjs` ainda mexe filesystem direto; depende do endpoint US-ARQ-011

> **Destino reescrito em 2026-07-29** ([ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md)): dizia *"submit pro Admin API"*, mas o Admin Center foi deprecado. O endpoint alvo é o do próprio módulo (US-ARQ-011).

### US-ARQ-018 · Widget Admin Center "Arquivos" (count por bucket, sensitive aguardando vault, métricas saúde) `p2`
**Implementado em:** `Modules/Arquivos/Services/Curador/CuradorStatsReader.php` · `Modules/Arquivos/Tests/Feature/CuradorStatsReaderTest.php` · verificado@580be57 (2026-11-16) — count por bucket + `sensitive_count` + audit 24h + dedupe, com recorte por `business_id` (Tier 0) coberto por teste na lane MySQL. **A UI está PENDENTE:** o widget "W5 Curador" e a `IndexController@__invoke` que o injetavam viviam no Admin Center (módulo Wagner-only, REMOVIDO do repo em 2026-07-29 — não há caminho vivo a citar) — a tela nunca foi acessível fora do Tailscale (403). O leitor foi resgatado pra cá porque só consulta tabelas DESTE módulo; falta dar a ele uma superfície própria.
**Aceite:** `fetch()` conta só os arquivos da empresa da sessão, troca o recorte quando a empresa troca e não lança mesmo sem as tabelas do Curador (`CuradorStatsReaderTest`).

## Sprint 3 — primeiro consumer real

> Backfill NFe landed. Os models `NfeEmissao`/`NfeDfeRecebido` adotam `HasArquivos`; a migration 000010 backfilla os XMLs existentes. Pendências: o model literal `NfeXml` do texto original não existe (a adoção foi em Emissao/DfeRecebido) e falta o smoke de import ao vivo.

### US-ARQ-019 · `Modules/NfeBrasil/Models/NfeXml` adota trait `HasArquivos` `p1`
**Implementado em:** _parcial_ · `Modules/NfeBrasil/Models/NfeEmissao.php` · `Modules/NfeBrasil/Models/NfeDfeRecebido.php` · `Modules/NfeBrasil/Tests/Feature/HasArquivosTraitTest.php` · verificado@580be57 (2026-11-16) — trait adotado em NfeEmissao/NfeDfeRecebido (não há model `NfeXml` literal; a US refere-se aos models de XML reais)
**Aceite:** `NfeEmissao` e `NfeDfeRecebido` usam o trait; os accessors `xml_arquivo` e `danfe_arquivo` devolvem `null` sem arquivo; as colunas legadas `xml_path`/`danfe_path` continuam no fillable (`HasArquivosTraitTest`).

### US-ARQ-020 · Migration backfill: NFe XMLs existentes em `storage/nfe/` → `arquivos` table com `arquivable=NfeXml` `p1`
**Implementado em:** `Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@580be57 (2026-11-16)
**Aceite:** o XML da NFe vira linha em `arquivos` com MIME `application/xml` na empresa da NFe; rodar 2× não duplica (`BackfillNfeXmlTest`).

### US-ARQ-021 · Smoke: novo NFe import → XML em `/var/lib/oimpresso-arquivos/biz-1/...` + audit log linha `p0`
**Implementado em:** _parcial_ · `Modules/Arquivos/Tests/Feature/BackfillNfeXmlTest.php` · verificado@580be57 (2026-11-16) — o teste de backfill cobre o ingest (linha em `arquivos` na empresa da NFe); a linha de audit log NÃO é testada lá (corrigido em 2026-11-16: antes dizia "ingest+audit validados", e o teste pulava em toda run por falta de dados); falta o smoke de novo import NFe ao vivo depositando no mount CT 100
**Aceite:** o ingest grava a linha em `arquivos` na empresa da NFe (`BackfillNfeXmlTest`). A linha de audit e o import ao vivo ficam fora deste aceite até ganharem teste.

### US-ARQ-022 · Officeimpresso UI lê NFe XML via `arquivable->arquivos()` (não path direto) — backward compat preservada `p1`
**Implementado em:** _parcial_ · `Modules/NfeBrasil/Models/NfeEmissao.php` · `Modules/NfeBrasil/Services/DanfeService.php` · `Modules/NfeBrasil/Tests/Feature/DanfeServicePrefersArquivosTest.php` · verificado@580be57 (2026-11-16) — accessor `xml_arquivo` via `arquivos()` existe em NfeEmissao e `DanfeService` (L89) já PREFERE o accessor com fallback legacy `xml_path`; falta só a UI Officeimpresso consumir pela relação
**Aceite:** o `DanfeService` lê o XML pela relação quando há linha em `arquivos`, e cai no `xml_path` legado quando não há linha ou o arquivo físico falta (`DanfeServicePrefersArquivosTest`).

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
**Implementado em:** `Modules/Financeiro/Models/BoletoRemessa.php` · `BoletoRemessa::getPdfArquivoAttribute` · `Modules/Arquivos/Database/Migrations/2026_05_10_000020_backfill_consumers_arquivos.php` · `Modules/Arquivos/Tests/Feature/BackfillConsumersTest.php` · verificado@580be57 (2026-11-16) — `BoletoRemessa` adota o trait `HasArquivos` (L21) + accessor de leitura via relação `getPdfArquivoAttribute` (L95, `sub_destination=fin-boleto-pdf`) + backfill do `pdf_path` legado; double-write intencional (ADR 0123 Sprint 4) enquanto consumidores externos do `pdf_path` não migram
**Aceite:** o PDF do boleto vira arquivo `fin-boleto-pdf` com MIME `application/pdf` na empresa da remessa, sem duplicar ao rodar 2× (`BackfillConsumersTest`); `pdf_arquivo` devolve `null` sem arquivo (`ConsumersTraitTest`).

### US-ARQ-024 · Migrar `Modules/Ponto/Importacao` (arquivos folha eSocial — médio risco compliance) `p1`
**Implementado em:** _pendente_ — sem backfill nem adoção de trait para `ponto_importacoes.arquivo_path`

### US-ARQ-025 · Migrar `Modules/Jana/TaskAttachment` (consolida sha256 dedup com `arquivos_dedupe`) `p1`
**Implementado em:** _pendente_ — `mcp_task_attachments` não migrado; consolidação sha256 com `arquivos_dedupe` não feita

### ~~US-ARQ-026 · Migrar o antigo `SRS/DocSource` (knowledge base — volume crescente)~~ `cancelada`
**Implementado em:** _pendente_ — **US CANCELADA em 2026-07-29** ([ADR 0357](../../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md)). Não há o que migrar: medido em produção, `docs_sources` tinha **0 linhas** — o "volume crescente" da premissa nunca existiu. O módulo e a tabela foram removidos na E5.

### Sprint 5 (deferred)

- Whatsapp inline mídia (volume gigante, vai precisar S3 antes — adiar até disk swap)
- CMS feature_image (trait + accessor `feature_image_arquivo` já landed em `CmsPage.php` L13 + coberto no `ConsumersTraitTest`; resta remover fallback da coluna legacy)
- Jana McpMemoryDocuments embedding vetores (caso especial — pode ficar em Meilisearch sem ir pra `arquivos` table)

## Funcionalidades entregues fora dos sprints (US registradas em 2026-11-16)

> Estas funcionalidades entraram no código entre 2026-08 e 2026-11 sem US no SPEC. A auditoria de 2026-11-05 (lote L3) as registrou; cada aceite vem do teste citado, que declara `@covers-us` da US.

### US-ARQ-027 · Classificar um arquivo pela tela `/arquivos`, com motivo na trilha `p1`
**Implementado em:** `Modules/Arquivos/Http/Controllers/ArquivosAdminController.php` · `Modules/Arquivos/Http/Requests/ReclassifyArquivoRequest.php` · verificado@580be57 (2026-11-16)
**Aceite:** a rota é POST, numérica e exige `arquivos.access`; classificar passa pela Request de motivo e pelo `ArquivosService`, grava `classified_at` e a trilha guarda o motivo na empresa do arquivo; arquivo de outra empresa e `force_bucket` são recusados.
**Testado em:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php`

### US-ARQ-028 · Excluir (soft-delete) e restaurar dentro do grace pela tela `/arquivos` `p1`
**Implementado em:** `Modules/Arquivos/Http/Controllers/ArquivosAdminController.php` · `Modules/Arquivos/Http/Requests/DeleteArquivoRequest.php` · `Modules/Arquivos/Http/Requests/RestoreArquivoRequest.php` · verificado@580be57 (2026-11-16)
**Aceite:** excluir é soft-delete com motivo na trilha, nunca hard-delete nem purge; restaurar só vale dentro do grace, só para superadmin, e preserva a linha da exclusão na trilha; fora do grace é recusado e nada muda; arquivo de outra empresa é recusado.
**Testado em:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php`

### US-ARQ-029 · Simular a retenção em dry-run, só superadmin `p1`
**Implementado em:** `Modules/Arquivos/Http/Controllers/RetencaoSimulacaoController.php` · `Modules/Arquivos/Jobs/SimularRetencaoJob.php` · verificado@580be57 (2026-11-16)
**Aceite:** a rota exige `can:superadmin`; o controller força `dry_run=true`, usa a empresa da sessão e recusa `purge`; a simulação lista só arquivos da própria empresa, com id, data e motivo, e não escreve em `arquivos` nem na trilha.
**Testado em:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php`

### US-ARQ-030 · Aviso ao titular antes do vencimento, por e-mail e WhatsApp ([ADR 0421](../../decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md) e [ADR 0422](../../decisions/0422-arquivos-aviso-titular-canal-email-whatsapp.md)) `p1`
**Implementado em:** `Modules/Arquivos/Services/AvisoTitularService.php` · `Modules/Arquivos/Jobs/AvisarTitularJob.php` · `Modules/Arquivos/Console/Commands/AvisarTitularesCommand.php` · verificado@580be57 (2026-11-16)
**Aceite:** entra na janela quem vence em até 30 dias e é sensível com titular; o aviso grava `titular_avisado_at` e uma linha `notice` na trilha, sem PII, e não reenvia; avisar não apaga nem expira; os canais nascem desligados e respeitam o opt-out de cada um; uma empresa não avisa nem marca arquivo de outra.
**Testado em:** `Modules/Arquivos/Tests/Feature/AvisoTitularServiceTest.php` · `Modules/Arquivos/Tests/Feature/AvisarTitularJobTest.php`

### US-ARQ-031 · API de anexos da OS para o app (`/api/app/os/{id}/arquivos`) `p1`
**Implementado em:** `app/Http/Controllers/Api/App/OsArquivosController.php` · `Modules/Arquivos/Services/AnexosDoAppService.php` · verificado@580be57 (2026-11-16)
**Aceite:** o envio de foto cria a linha em `arquivos` ligada à OS, na empresa da OS e com o usuário que enviou; a lista vem do mais novo para o mais antigo com URL assinada; OS de outra empresa responde 404 sem gravar; sem permissão de editar a OS o envio dá 403.
**Testado em:** `tests/Feature/Sells/AppOsArquivosApiContratoTest.php`

### US-ARQ-032 · Exportar o acervo de uma empresa em ZIP (`arquivos:export-zip`) `p2`
**Implementado em:** `Modules/Arquivos/Console/Commands/ExportZipCommand.php` · verificado@580be57 (2026-11-16)
**Aceite:** gera o ZIP com os arquivos da empresa pedida e um `_MANIFEST.json` com empresa, total e lista de arquivos; `--dry-run` não cria o ZIP nem grava na trilha; `--include-vault` decifra os arquivos do cofre; não exporta arquivo de outra empresa.
**Testado em:** `Modules/Arquivos/Tests/Feature/ExportZipCommandTest.php`

### US-ARQ-033 · Re-cifrar o cofre após trocar a `APP_KEY` (`arquivos:reencrypt-vault`) `p1`
**Implementado em:** `Modules/Arquivos/Console/Commands/ReencryptVaultCommand.php` · verificado@580be57 (2026-11-16)
**Aceite:** com `--old-key`, cada arquivo do cofre cifrado com a chave antiga passa a abrir com a chave atual sem perder conteúdo; `--dry-run` não altera bytes; rodar de novo pula o que já foi re-cifrado; arquivo ausente no disco conta como pulado, não como erro.
**Testado em:** `Modules/Arquivos/Tests/Feature/ReencryptVaultCommandTest.php`

### US-ARQ-034 · Revogar de papéis e usuários as permissões que passaram a ser só de superadmin (`arquivos:revogar-permissoes-superadmin`) `p2`
**Implementado em:** `Modules/Arquivos/Console/Commands/RevogarPermissoesSuperadminCommand.php` · verificado@580be57 (2026-11-16)
**Aceite:** `--dry-run` lista os vínculos e não escreve; sem a flag remove os vínculos de papel e de usuário e as permissões `arquivos.restore` e `arquivos.governanca`; rodar de novo não muda nada.
**Testado em:** `Modules/Arquivos/Tests/Feature/ArquivosSuperadminOnlyTest.php`
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
- ✅ Pest: bucket=sensitive automático pra `.env` (`CuradorEngineTest.php`); 🟡 assert `disk=vault` via `attach()` _pendente_ (decisão sensitive→vault e escrita cifrada em `ArquivosService::attach`, sem teste que assegure o disk `vault` via `attach()`)
- ✅ Pest: dedupe por MD5 no business — mesmo DONO 2× → mesma row; DONO diferente → row nova apontando pro MESMO blob (a dedupe economiza storage, não o vínculo) + audit `upload` com `dedupe_de`; retention só apaga o blob na última referência (`DedupeMultiOwnerTest.php` UC-ARQ-DEDUP-01..08, lane `arquivos-pest` — inclui `purgeOne()`, que até então nunca apagava o blob por ler a coluna inexistente `storage_disk`). Antes de 2026-09-29 o 2º dono ficava sem arquivo em `$owner->arquivos()` — medido no CT 100: `4 failed, 1 passed`.
- ✅ ParityTest: 30 fixtures comuns → mesmo bucket em `CuradorEngine.php` e `scripts/curador/lib/rules.mjs`
- 🟡 Smoke: NFe import deposita XML em CT 100 mount + audit log linha — _pendente_ (smoke ao vivo no mount CT 100 não representável em repo; ver US-ARQ-010/021 `_parcial_`)
- ✅ Audit log: o enum nasceu com 8 ações; o código emitia `signed_url_consumed` fora dele até 2026-07-02, quando a migration `2026_07_02_000001_widen_arquivos_audit_log_action_enum.php` o acrescentou. Em 2026-08-10 entrou também `exported`. `AuditLogActionEnumTest` cobre o enum

## Métricas de saúde (`arquivos:health-check`)

Comando real `HealthCheckCommand` (`arquivos:health-check`), 5 checks:

- `orphan_files` — arquivo no DB sem file físico no disk (sample cap 1000; WARN ≥1%, FAIL >10%)
- `dedupe_inconsistent` — divergência de dedupe (md5 duplicado sem row canônica)
- `audit_log_lag` — tempo desde o último registro no audit log (WARN >24h — sistema parado ou log não escrevendo)
- `retention_overdue` — arquivo além do `retention_days` sem purge
- `vault_encryption_ratio` — proporção de arquivos `bucket=sensitive` com `encrypted=true` (encryption-at-rest)
