# DEPRECATION-PLAN — Modules/SRS

> **Status:** 📋 Planejado · **Owner:** Wagner · **Sucessor canônico:** múltiplos (KB primário + Jana + TeamMcp/Governance + MCP server canon)
> **Atualizado:** 2026-05-17 · **Gerado por:** agent `deprecar-modulo`
> **Decisão Wagner:** Caminho 1 (deprecar SRS) — BRIEFING.md 2026-05-16 já declarou "Substituído na prática pelo MCP server canon. ❌ Não investir em features novas."

## TL;DR

Plano de 6 etapas (~47 dias úteis, com 30d wait E5) pra deprecar `Modules/SRS` (estado: ZUMBI — SCOPE 2026-05-05 prevê repurpose SRS browser nunca executado; BRIEFING 2026-05-16 admite substituição prática pelo MCP server). **Sucessor primário:** `Modules/KB` (já dono de `mcp_memory_documents` + ingest webhook git→DB). **Sucessores secundários:** `Modules/Jana` (ChatAssistant + chat_messages), `Modules/Governance` (validation/audit), `mcp_audit_log` canon (validation_runs append-only). **Dados:** 7 tabelas `docs_*` (2 MIGRATE → KB · 2 PRESERVE→view legacy bookmarks · 2 ARCHIVE→`governance/archive/` · 1 MIGRATE+ARCHIVE híbrido). **Risks Tier 0:** 4 críticos (multi-tenant cross-tenant em 7 entities, PII em `docs_chat_messages.content` LGPD, FULLTEXT index re-criação custosa em `mcp_memory_documents`, cron `memcofre:sync-memories` daily 23:00 em `app/Console/Kernel.php` quebraria silenciosamente).

### Reporte rápido

| Top 5 features | Receptor |
|---|---|
| ChatAssistant + DocChatMessage | Modules/Jana (chat IA canon) |
| DocSource + IngestController + Sync* commands + FULLTEXT search | Modules/KB (browser docs + mcp_memory_documents) |
| DocValidator + DocValidationRun + ModuleAuditor | Modules/Governance (DriftAlertsController canon) |
| DocPage + SyncPagesCommand (cache .tsx ↔ requirements) | Modules/Governance OU descontinuar (MCP server cobre) |
| DocRequirement + DocLink (cache US-XXX-NNN ↔ evidências) | ❓ ORPHAN → Wagner decide (descontinuar — `memory/requisitos/<X>/SPEC.md` é fonte canônica via MCP) |

| Top tabelas | Destino |
|---|---|
| `docs_sources` (FK in: docs_evidences) | MIGRATE → `kb_sources` (Modules/KB) |
| `docs_evidences` (FULLTEXT + biz_id + Searchable) | MIGRATE → `mcp_memory_documents` (já tem FULLTEXT + Meilisearch hybrid) |
| `docs_chat_messages` (PII content + biz_id) | MIGRATE → `jana_memoria_chat_corpus` (Modules/Jana) |
| `docs_validation_runs` (repo-wide, audit-ish) | ARCHIVE + MIGRATE pra `mcp_audit_log` (canon append-only) |
| `docs_requirements` (cache US-XXX-NNN) | ARCHIVE → `governance/archive/srs-docs-requirements-2026-05-17.sql.gz` |
| `docs_links` (M2M repo-wide evidence×requirement) | ARCHIVE (depende de requirements) |
| `docs_pages` (repo-wide, .tsx ↔ status) | DROP (substituído por `kb_nodes` + module-grade-v3) |

### 3 risks Tier 0 mais críticos

1. **PII em `docs_chat_messages.content` LGPD Art. 16** — Conteúdo LLM completion pode ecoar CPF/email/nome próprio sem redação garantida (PiiRedactor só roda em ChatAssistant defense-in-depth; rows legadas podem ter PII raw). MIGRATE pra `jana_memoria_chat_corpus` exige re-run `PiiRedactor` no payload antes de archive/migrate.
2. **Multi-tenant cross-tenant em 7 entities** — `HasBusinessScope` trait aplicado Wave 12, mas 3 tabelas repo-wide (`docs_links`, `docs_pages`, `docs_validation_runs`) por exceção Wave 17. Migration de FK exige Pest cross-tenant biz=1 vs biz=99 ANTES e DEPOIS, plus auditoria explícita das 3 repo-wide pra não vazar contexto cliente entre business.
3. **Cron `memcofre:sync-memories` daily 23:00** ([app/Console/Kernel.php](../../../app/Console/Kernel.php) §34-46) — Schedule já tem histórico de drift (40+ arquivos atrasados 2026-04-24 → 2026-05-04 por rename sem update). Deletar `SyncMemoriesCommand` sem migration de schedule = silent failure repetido. Bloqueador E5.

### Próximo passo concreto

Wagner promover [`memory/decisions/proposals/deprecate-srs.md`](../../decisions/proposals/deprecate-srs.md) (draft inline §"ADR de deprecação" abaixo) pra `memory/decisions/0168-deprecate-srs.md` com status `accepted` e `supersedes: [0080]`. Esse PR é E1 (≤80 LOC docs only).

---

## Fase 1 — Inventário

| Item | Valor |
|---|---|
| Módulo | Modules/SRS (ex-MemCofre — rename PHP-only Fase 3.7 PR-2 em 2026-05-06) |
| SCOPE vs BRIEFING | **CONFLITANTE** — SCOPE 2026-05-05 prevê repurpose (`srs_entries` table + trigger MySQL append-only) que NUNCA aconteceu; BRIEFING 2026-05-16 (11 dias depois) admite "substituído na prática pelo MCP server canon. Não investir." `transition_plan.migration_phase: 3.7` declarado, mas Entities ainda `Doc*` (não renomeadas), tabelas ainda `docs_*` (não `srs_entries`). |
| Code stats | 8 Controllers · 6 Services · 7 Entities (`DocSource/Page/Evidence/Requirement/Link/ChatMessage/ValidationRun`) · 8 Migrations (`docs_*` legacy) · 9 Console Commands (signature `memcofre:*` legacy) · 10 Tests Pest (incluindo Wave 23/25/26/27/28 saturação) |
| Git activity 90d | 16 commits — picos Waves 11/12/16/17/18/23/24/25/26/27/28 (saturation tests). Funcionalmente parado desde Wave 12 (12/maio/2026: HasBusinessScope + LogsActivity). **Atividade alta é de governance/Pest, não feature** — confirma zumbi. |
| module-grade-v3 atual | 100/118 raw → **Bom** bucket (24/30 18/20 10/15 9/20 15/15 6/10 6/10 6/8 6/7). D1 (24/30 — explained: 3 tabelas repo-wide intencional) e D4 (9/20 — arquitetura/SoC zumbi cofre↔SRS detectado corretamente). Baseline em [governance/module-grades-baseline.json](../../../governance/module-grades-baseline.json) linha 40 = `"SRS": 58`. |
| Cross-refs externos | **SCOPE.md mencionando SRS:** 5 (TeamMcp/KB/Brief/Arquivos/SRS próprio). **Código PHP citando `Modules\SRS` ou `memcofre`:** 40+ (Module SRS interno · `tests/Feature/Memory/*` ·  `app/Services/ModuleRequirementsGenerator.php` · `app/Console/Commands/MemSyncStatusCommand.php` (wrapper sobre `memcofre:sync-memories --dry`) · `app/Console/Commands/GenerateModuleRequirementsCommand.php` · `app/Console/Kernel.php` (schedule daily 23:00) · `KbController.php` (cita `/memcofre/memoria` legacy em docblock) · `prototipo-ui/_cowork-export-*/...` (5 controllers prototipo só citam, não dependem)). **Skills/agents/rules:** 5 (`deprecar-modulo`, `sidebar-menu-arch`, `migrar-modulo`, `meta-skill-roi-erp-autonomo`, `cockpit-runbook` EXAMPLES). **ADRs:** 20+ (0080 charter mãe, 0088 rename PHP-only, 0092 tabela rename copiloto→jana, 0121 modular vertical, 0123 Arquivos, 0148-149 Cascade, 0153-160 module-grade Waves, e proposals/drafts). **CI:** zero workflows com SRS-specific (apenas governance-gate.yml genérico). **Governance:** `governance/buckets/_INDEX.md` (bucket `functional_horizontal`), `governance/module-grades-baseline.json` (entry SRS 58 + renames MemCofre→SRS). |

### Detalhes inventário código

```
Modules/SRS/
├── SCOPE.md         (conflito explícito — frontmatter declara `transition_plan` Fase 3.7 não executado)
├── README.md        (cita "ex-MemCofre" — explicitamente referencia rename PHP-only)
├── CHANGELOG.md     ⚠️ MERGE CONFLICT NÃO RESOLVIDO ATIVO no topo (linhas <<<<<<< HEAD / ======= / >>>>>>> origin/main) — Wave 28 vs Wave 27 conflito ainda aberto
├── module.json      (governance.bucket: functional_horizontal, fsm_n_a: true)
├── composer.json    (psr-4 Modules\SRS\)
├── start.php
├── Config/
│   ├── config.php   (NÃO LIDO — provável `memcofre.*` config keys legacy)
│   └── retention.php (D7 LGPD — janelas 90d/365d/1825d + base_legal Art. 7º II+IX)
├── Console/Commands/ (9 cmds — todas signature `memcofre:*` legacy)
│   ├── AuditModuleCommand.php    (`memcofre:audit-module` — 15 checks)
│   ├── GenTestCommand.php
│   ├── InstallHooksCommand.php
│   ├── MigrateModuleCommand.php
│   ├── SrsHealthCommand.php      (renamed signature `srs:*`?)
│   ├── SyncMemoriesCommand.php   (`memcofre:sync-memories` — cron daily 23:00 em Kernel.php)
│   ├── SyncPagesCommand.php      (cache .tsx → docs_pages)
│   ├── ValidateCommand.php       (drive DocValidator → docs_validation_runs)
│   └── (1 a confirmar)
├── Database/Migrations/ (8 docs_*, todas idempotentes)
│   ├── 000001 docs_sources         (biz_id indexed, FK in: evidences)
│   ├── 000002 docs_evidences       (biz_id + Searchable + FULLTEXT, FK out: sources)
│   ├── 000003 docs_requirements    (biz_id, external_id UNIQUE)
│   ├── 000004 docs_links           (REPO-WIDE evidence_id+requirement_id+role — sem biz_id)
│   ├── 000005 docs_chat_messages   (biz_id + user_id + PII content + tokens_used)
│   ├── 000006 docs_pages           (REPO-WIDE path UNIQUE — sem biz_id, .tsx ↔ stories)
│   ├── 000007 docs_validation_runs (REPO-WIDE — sem biz_id, audit-ish global)
│   └── 000008 add_fulltext_index_to_docs_evidences (FULLTEXT content+notes)
├── Entities/ (7 Doc* — 4 tenant-scoped via HasBusinessScope Wave 12+27, 3 repo-wide Wave 17 EXCEÇÃO)
├── Http/
│   ├── Controllers/
│   │   ├── ChatController       (chat assistente — /memcofre/chat /ask /new)
│   │   ├── DashboardController  (/memcofre)
│   │   ├── DataController       (sidebar UltimatePOS — permission `memcofre.access`)
│   │   ├── InboxController      (/memcofre/inbox triage/apply/destroy)
│   │   ├── IngestController     (/memcofre/ingest)
│   │   ├── InstallController    (/srs/install/* + 301 de /memcofre/install/*)
│   │   ├── MemoriaController    (/memcofre/memoria)
│   │   └── ModuloController     (/memcofre/modulos/{module})
│   ├── Requests/                (4 FormRequests Wave 11 D8)
│   └── routes.php               (prefix /memcofre/* + /srs/install/* throttle:60,1)
├── Providers/SrsServiceProvider.php  (registra 8 commands, RequirementsFileReader singleton)
├── Resources/
│   ├── lang/pt/memcofre.php     (legacy compat)
│   └── menus/topnav.php         (5 itens com `can => memcofre.access`)
├── Services/ (6)
│   ├── ChatAssistant            (OTel span `srs.chat.ask`, RAG offline+AI, PII redacted)
│   ├── DocRetentionCleaner      (LGPD Art. 16 hard delete, ainda stub Wave 18)
│   ├── DocValidator             (OTel `srs.doc.validate`, 5 checks ADR 0005)
│   ├── MemoryReader             (OTel `srs.memory.list_roots`, lê primer+project+claude)
│   ├── ModuleAuditor            (OTel `srs.audit.module`, 15 checks ADR 0007)
│   └── RequirementsFileReader   (singleton, parser de memory/requisitos/<X>/*.md)
└── Tests/Feature/ (10 testes incluindo 6 saturação Wave 23/25/26/27/28)
```

### Cross-ref crítico — schedule cron

[`app/Console/Kernel.php`](../../../app/Console/Kernel.php) linhas 36-46:
- **Comando agendado:** `memcofre:sync-memories` (daily 23:00 BRT, `withoutOverlapping`)
- **Histórico documentado de drift:** `docvault:sync-memories` → `memcofre:sync-memories` (rename 2026-04-24) → SRS rename 2026-05-06 PR-2 manteve signature `memcofre:*` por compat. Schedule ficou apontando pro nome antigo `docvault:*` até 2026-05-04 (40+ arquivos atrasados).
- **Risco:** se E5 deletar `SyncMemoriesCommand` sem migration de schedule, repete o drift histórico = silent failure.

---

## Fase 2 — Mapeamento Features → Receptores

| # | Feature (Controller/Service/Entity/Command) | Path atual | Receptor proposto | Justificativa (cita SCOPE receptor) | Esforço | Bloqueador |
|---|---|---|---|---|---|---|
| 1 | `ChatController::ask` + `newSession` | Modules/SRS/Http/Controllers/ChatController.php | **Modules/Jana** | Jana `purpose: "Chat IA do business — conversa..."` + `contains: ChatController` já é canon chat IA. Cross-corpus = extensão natural. | Médio | OpenAI config (`memcofre.ai.*` → `jana.ai.*`) + LLM provider key compartilhado |
| 2 | `ChatAssistant` service + `retrieve()` keyword RAG offline | Modules/SRS/Services/ChatAssistant.php | **Modules/Jana** | Jana já tem `Services/Memoria/*` (recall hybrid HyDE+Reranker+Meilisearch). ChatAssistant é versão "lite" do mesmo padrão. Renomear `ChatCorpusAssistant` pra evitar colisão. | Médio | OTel span rename `srs.chat.ask` → `jana.chat-corpus.ask`; reescrever 80 LOC sem reaproveitar Memoria atual (Memoria é HyDE; ChatAssistant é keyword) |
| 3 | `IngestController` (upload PDF/MD/URL) + `storeSource` | Modules/SRS/Http/Controllers/IngestController.php | **Modules/KB** | KB `db_tables_owned: mcp_memory_documents (sync git → DB via webhook — bridge read-only fotografia git)` + `contains: KbNodeController — CRUD kb_nodes (artigos editáveis + bridge canônico)`. KB já tem ingest webhook git→DB; ingest manual humano cabe como complemento. | Médio | Storage path `public/memcofre/` → `public/kb/uploads/` com migration + signed URLs (Modules/Arquivos pode hospedar via `HasArquivos` trait — preferido) |
| 4 | `InboxController` (triage evidências) | Modules/SRS/Http/Controllers/InboxController.php | **Modules/KB** | KB já tem `KbCommentController` (comments inline) + workflow editorial bridge canônico. Inbox vira draft state de `kb_nodes`. | Grande | UI Inertia/React precisa migrar Blade legacy (SRS é Blade) — ou aceitar deprecação de UI (CLI-only) |
| 5 | `MemoriaController` (browser memory/ trees) | Modules/SRS/Http/Controllers/MemoriaController.php | **Modules/KB** | KB já tem `MemoriaController — tela LGPD 'O Copiloto lembra de você' (US-COPI-MEM-012); URL /copiloto/memoria mantida` (Fase 3.7 PR-1 absorvido do Copiloto). SRS Memoria é overlap parcial. Mesclar funcionalidade. | Médio | URL `/memcofre/memoria` precisa Route::redirect 301 pra `/kb/memoria` ou `/copiloto/memoria` (decidir) |
| 6 | `ModuloController` (ver requisitos consolidados por módulo) | Modules/SRS/Http/Controllers/ModuloController.php | **Modules/Governance** | Governance `contains: ModuleGradeController` + `routes: GET /governance/module-grades/{name}`. Vista por módulo de requirements/coverage = governance, não chat. | Médio | Mapear `docs_requirements` cache → SPEC.md fonte canônica via MCP (não bate 1:1) |
| 7 | `DashboardController` (overview SRS) | Modules/SRS/Http/Controllers/DashboardController.php | **descontinuar** | Substituído por `Modules/Governance/DashboardController` (KPIs ADR + audit + drift). SRS dashboard é variante zumbi. | Trivial | Route::redirect 301 `/memcofre` → `/governance` |
| 8 | `DataController::modifyAdminMenu` + `superadmin_package` + `user_permissions` | Modules/SRS/Http/Controllers/DataController.php | **descontinuar** | Sidebar entry "Cofre de Memórias" + permission `memcofre_module` desativam após E5. Bookmarks 301. | Trivial | Update `permissions` table: `memcofre.access` mantém compat até E5 |
| 9 | `InstallController` (Install/Uninstall hooks) | Modules/SRS/Http/Controllers/InstallController.php | **descontinuar** (E5) | Padrão UltimatePOS Install — quando módulo deixa de existir, hooks vão junto. | Trivial | — |
| 10 | `DocValidator` service + 5 checks doc integrity | Modules/SRS/Services/DocValidator.php | **Modules/Governance** | Governance `DriftAlertsController — runtime scan SCOPE.md vs filesystem real + persisted alerts cron`. DocValidator é versão "lite" do mesmo workflow. Mesclar checks. | Grande | Re-mapear 5 checks (STORY_ORPHAN/RULE_NO_TEST/ADR_DANGLING/PAGE_NO_META/PAGE_STALE) pra schema `mcp_drift_alerts` |
| 11 | `ModuleAuditor` service + 15 checks (C01-C15) | Modules/SRS/Services/ModuleAuditor.php | **Modules/Governance** | Sobreposição direta com `ModuleGradeController` (rubrica v3/v4 9 dimensões). 15 checks do ModuleAuditor são subset granular das dimensões D1-D9. | Grande | Decidir se 15 checks viram sub-dimensões ou se descontinua (rubrica v3 já cobre na prática) |
| 12 | `MemoryReader` service (lê primer/project/claude) | Modules/SRS/Services/MemoryReader.php | **Modules/KB** | KB acessa `mcp_memory_documents` que já tem snapshot git. MemoryReader vira leitor canon. | Médio | Path `~/.claude/.../memory/` fora do repo — manter ler local OU forçar migração pra `mcp_memory_documents` only |
| 13 | `RequirementsFileReader` (parser memory/requisitos/<X>/*.md) | Modules/SRS/Services/RequirementsFileReader.php | **app/Services** (root, já tem `ModuleRequirementsGenerator`) | Já existe `app/Services/ModuleRequirementsGenerator.php` (irmão). Consolidar. | Médio | Refactor namespace + reaproveitar parser (ModuleRequirementsGenerator gera, RequirementsFileReader lê — complementares) |
| 14 | `DocRetentionCleaner` service (Wave 18 stub) | Modules/SRS/Services/DocRetentionCleaner.php | **Modules/Governance** OU **descontinuar** | Service ainda é stub (sem comando agendado). Se Governance absorver `docs_validation_runs`, retention vai junto. Se ARCHIVE all, descontinuar. | Trivial | Decisão depende de Fase 3 tabelas |
| 15 | `Entities/DocSource` | Modules/SRS/Entities/DocSource.php | **Modules/KB** (`KbSource` ou consolidar com `KbNode.kind=source`) | KB já tem `kb_nodes` (artigos + bridge canônico). Source = subset semântico. | Médio | Migration tabela `docs_sources` → `kb_sources` OR insert as `kb_nodes` com `kind=source` |
| 16 | `Entities/DocEvidence` (Searchable, FULLTEXT) | Modules/SRS/Entities/DocEvidence.php | **Modules/KB** → tabela `mcp_memory_documents` | KB owns `mcp_memory_documents` com FULLTEXT + Meilisearch hybrid embedder. DocEvidence é cópia legacy. Migrar conteúdo + dropar FULLTEXT duplicado. | Grande | Re-criar FULLTEXT em `mcp_memory_documents` (custoso — tabela já tem indexes; verificar não colidir) |
| 17 | `Entities/DocChatMessage` (PII content) | Modules/SRS/Entities/DocChatMessage.php | **Modules/Jana** → tabela nova `jana_chat_corpus_messages` | Jana owns `jana_memoria_*` tables. Chat history SRS é caso especial corpus-scoped. | Médio | PII LGPD: re-run PiiRedactor antes de INSERT migration |
| 18 | `Entities/DocPage` (cache .tsx ↔ stories) | Modules/SRS/Entities/DocPage.php | **descontinuar** | Substituído por `Modules/KB/kb_nodes` (artigos editáveis) + module-grade-v3 D3 docs internas. Cache `.tsx ↔ stories` deduplica com charter (`<Tela>.charter.md` ao lado do `.tsx`). | Trivial | Migration drop tabela; remover SyncPagesCommand schedule |
| 19 | `Entities/DocRequirement` + `DocLink` (cache US-XXX-NNN) | Modules/SRS/Entities/DocRequirement.php | **❓ ORPHAN — Wagner decide** | Cache opcional `external_id UNIQUE` (US-ESSE-001, R-ESSE-007). Fonte canônica é `memory/requisitos/<X>/SPEC.md` via MCP server (`mcp_memory_documents`). Cache pode ser descontinuado SE Wagner aceitar query lenta no MCP. | Trivial | Decisão produto — query latency MCP vs cache SQL |
| 20 | `Entities/DocValidationRun` (audit append-only) | Modules/SRS/Entities/DocValidationRun.php | **`mcp_audit_log`** (canon TeamMcp + trigger MySQL) | TeamMcp owns `mcp_audit_log` (mantém append-only com trigger; UI Fase 5 fica em Modules/Governance). Run records cabem como audit entries. | Médio | Schema mapping `docs_validation_runs.issues_critical/health_score` → `mcp_audit_log.payload` JSON |
| 21 | `Console: memcofre:sync-memories` (cron daily 23:00) | Modules/SRS/Console/Commands/SyncMemoriesCommand.php | **Modules/KB** (`kb:sync-memories`) OU **descontinuar** | MCP webhook git→DB já cobre sync de `memory/*`. Sync local `~/.claude/.../memory/` → repo (one-way) ainda útil. Decidir: KB absorve ou Wagner roda manual. | Médio | Schedule entry em `app/Console/Kernel.php` MIGRA NO MESMO PR — não pode esquecer |
| 22 | `Console: memcofre:audit-module` (ModuleAuditor driver) | Modules/SRS/Console/Commands/AuditModuleCommand.php | **Modules/Governance** | Cobre subset de `php artisan module:grade <X>` (canon Wave 25+). | Trivial | Renomear pra `governance:audit-module-legacy` ou descontinuar |
| 23 | `Console: memcofre:gen-test` | Modules/SRS/Console/Commands/GenTestCommand.php | **descontinuar** | Code-gen experimental sem uso documentado. | Trivial | — |
| 24 | `Console: memcofre:install-hooks` | Modules/SRS/Console/Commands/InstallHooksCommand.php | **descontinuar** | Install hooks já vivem em `Modules/SRS/Http/Controllers/InstallController.php` (E5 elimina). | Trivial | — |
| 25 | `Console: memcofre:migrate-module` | Modules/SRS/Console/Commands/MigrateModuleCommand.php | **descontinuar** | Migration de schema doc-ingest — fim da utilidade ao deprecar. | Trivial | — |
| 26 | `Console: srs:health` (`SrsHealthCommand`) | Modules/SRS/Console/Commands/SrsHealthCommand.php | **Modules/Jana** (`jana:health-check` canon) | `jana:health-check` daily 06:00 é canon health do projeto. SrsHealthCommand é redundância. | Trivial | Mesclar checks SRS-specific em `jana:health-check` se relevante OU descontinuar |
| 27 | `Console: memcofre:validate` (DocValidator driver) | Modules/SRS/Console/Commands/ValidateCommand.php | **Modules/Governance** | Mesma lógica do ModuleAuditor — drift detection canon. | Trivial | Renomear ou descontinuar |
| 28 | `Console: memcofre:sync-pages` (SyncPagesCommand) | Modules/SRS/Console/Commands/SyncPagesCommand.php | **descontinuar** | DocPage cache será dropado (item 18). Command sem destino. | Trivial | Verificar se há schedule cron — provavelmente NÃO (não listado em Kernel.php LIDO) |
| 29 | Lang `memcofre.php` + topnav `Cofre de Memórias` | Modules/SRS/Resources/lang/pt/memcofre.php + menus/topnav.php | **descontinuar** | Sidebar entry removida em E5; topnav legacy 301. | Trivial | — |
| 30 | Compat layer `memcofre.*` URLs/permissions | Modules/SRS/Http/routes.php + DataController.php | **Route::redirect 301 PRESERVE indefinido** | Wagner bookmarks `/memcofre/*` documentados em SCOPE Fase 3.7 PR-2 — pattern Tier 0 IRREVOGÁVEL pra preservar. Permissions `memcofre.access` mantidas até E5+30d. | Trivial | — |

**SEM `❓ ORPHAN` sem decisão exceto item 19 (DocRequirement+DocLink) — Wagner decide se cache SQL ou query lenta MCP.**

---

## Fase 3 — Consistência de Dados

> ⚠️ Volumes estimados marcados `?` — `Bash mysql` indisponível em worktree sem .env. Wagner roda `php artisan tinker; DB::table('docs_X')->count()` E3 pré-flight.

| # | Tabela | Linhas | FK in | FK out | PII | Append-only | LGPD retention | Decisão | Receptor | Notas |
|---|---|---|---|---|---|---|---|---|---|---|
| T1 | `docs_sources` | ? | `docs_evidences.source_id` | `users.id` (`created_by`) | 🟡 médio (URL externa + nome arquivo + body_text pode ter info cliente) | ❌ | 90d (drafts) / 1825d (generated) | **MIGRATE → KB** | Modules/KB | Migration: `RENAME TABLE docs_sources TO kb_sources` + atualizar FK em `docs_evidences.source_id`. Namespace `Modules\KB\Entities\KbSource`. Pest cross-tenant. PiiRedactor em `body_text` antes archive backup. |
| T2 | `docs_evidences` | ? (provavelmente alto — Scout Searchable) | `docs_links.evidence_id` (M2M) | `docs_sources.id` | 🟡 médio (`content` text raw OCR + extracted) | ❌ | 1825d (5y governance) | **MIGRATE → mcp_memory_documents** | Modules/KB → TeamMcp | Mais complexo: FULLTEXT existing em `docs_evidences (content, notes)` + Scout/Meilisearch já indexa. Migration: INSERT INTO `mcp_memory_documents (source, kind, business_id, content, meta, ...)` SELECT FROM `docs_evidences` mapeando campos. DROP `docs_evidences` E DROP FULLTEXT (já existe em `mcp_memory_documents`). Pest cross-tenant + smoke Meilisearch reindex. |
| T3 | `docs_chat_messages` | ? | nenhum direto | `users.id` (`user_id`) | 🔴 **ALTO** (content = LLM completion raw, pode ter CPF/email/nome vazado mesmo com PiiRedactor defense-in-depth) | ❌ (mas tratado como append-only via LogsActivity Wave 12) | 365d | **MIGRATE → Jana** | Modules/Jana | Migration: `RENAME TABLE docs_chat_messages TO jana_chat_corpus_messages`. **Tier 0 IRREVOGÁVEL:** re-run PiiRedactor em TODO `content` row-by-row ANTES de archive backup (gzip). Pest cross-tenant biz=1 vs biz=99. |
| T4 | `docs_validation_runs` | ? (uso ~mensal, dezenas a centenas de rows) | nenhum direto | nenhum | ❌ | 🟡 sim (Wave 17 — issues_critical/health_score são audit-ish) | 365d | **ARCHIVE → governance/archive/ + MIGRATE → mcp_audit_log** | TeamMcp `mcp_audit_log` | `mysqldump` SQL.gz + cripto AES-256 storage (governance/archive/srs-docs-validation-runs-2026-MM-DD.sql.gz). Plus INSERT INTO `mcp_audit_log` mapeando `issues_critical → severity, health_score → payload.health, module → component`. Drop `docs_validation_runs` SÓ APÓS dump validado. **CUIDADO:** trigger MySQL append-only NÃO existe em `docs_validation_runs` hoje (verificado em migration 000007 — sem trigger). Não há append-only enforcer pra preservar — segura. |
| T5 | `docs_requirements` | ? | `docs_links.requirement_id` (M2M) | nenhum | ❌ (cache de US text + status, sem CPF) | ❌ | 1825d (governance audit decisões) | **ARCHIVE** | — | `mysqldump` snapshot + drop. Fonte canônica é `memory/requisitos/<X>/SPEC.md` via MCP (`mcp_memory_documents`). Wagner aceita query latency MCP. SE Wagner discordar (item 19 orphan), upgrade pra MIGRATE → `kb_requirements` table nova. |
| T6 | `docs_links` (REPO-WIDE — Wave 17 EXCEÇÃO) | ? | nenhum entrante | `docs_evidences.id` + `docs_requirements.id` | ❌ | ❌ | follows requirements | **ARCHIVE** | — | Depende T5 — se requirements archived, links go together. `mysqldump` + drop. NUNCA chega a `mcp_*` (M2M ARCHIVE-only). |
| T7 | `docs_pages` (REPO-WIDE — Wave 17 EXCEÇÃO) | ? | nenhum | nenhum | ❌ | ❌ | 365d (cache regenerável) | **DROP** (após archive) | — | Cache regenerável de `.tsx ↔ stories`. Substituído por charter `<Tela>.charter.md` + module-grade-v3 D3. `mysqldump` cautelar → DROP TABLE. Pest test `Schema::hasTable('docs_pages') === false` em E5. |

### Cross-cutting: índice FULLTEXT `docs_evidences_fulltext (content, notes)`

Existing index pesa ~10% tamanho tabela em MySQL InnoDB. Migration E3 drop FULLTEXT antes de drop tabela (`ALTER TABLE docs_evidences DROP INDEX docs_evidences_fulltext` — já tem `down()` migration 000008). `mcp_memory_documents` já tem próprio FULLTEXT — não recriar.

### Tier 0 IRREVOGÁVEL — checklist Fase 3

- ✅ Cada DROP precedido de `mysqldump` cripto + entry em `governance/archive/_INDEX.md`
- ✅ Toda migration tem `down()` reverso (verificado nas 8 migrations existentes — todas têm)
- ✅ PII redactor em T3 row-by-row ANTES backup (LGPD Art. 16)
- ✅ Pest cross-tenant biz=1 vs biz=99 ANTES (snapshot pre-migration) E DEPOIS (validate target receptor) cada MIGRATE
- ✅ NUNCA DROP T6 antes de T5 (FK direction)
- ✅ NUNCA DROP T1 antes de T2 (FK direction — `docs_evidences.source_id`)
- ✅ Trigger MySQL append-only NÃO existe nas tabelas (confirmado) — preservação só do `LogsActivity` Spatie via espelhamento em `mcp_audit_log` se necessário
- ✅ Cliente piloto ROTA LIVRE biz=4: SRS é tool interna Wagner (SPEC.md `D5: ROTA LIVRE não interage` — Wagner aviso 7d formal não necessário, mas Larissa não pode ter UX cliente quebrada → checar se `/memcofre/*` está em qualquer link de ROTA LIVRE views — provavelmente NÃO

---

## Fase 4 — Incorporação nos Receptores

### Receptor Modules/KB — patches

```yaml
# Modules/KB/SCOPE.md — adicionar a `contains`:
contains:
  - "KbSourceController (absorvido de SRS E4) — ingest manual humano de PDFs/URLs (complementa webhook git→DB)"
  - "KbInboxController (absorvido de SRS E4) — triage de evidências raw → kb_nodes draft"
  - "KbMemoryReader service (absorvido de SRS E4) — leitor 3 fontes (primer/project/claude); usado por KbController e KbAiController"

# Adicionar a `db_tables_owned`:
db_tables_owned:
  - kb_sources (ex-docs_sources, MIGRATE E3)
  # docs_evidences NÃO entra — conteúdo merged em mcp_memory_documents (TeamMcp owns)

# Adicionar a `url_prefixes`:
url_prefixes:
  - /kb/sources/* (canônico — Route::redirect 301 de /memcofre/ingest)
  - /kb/inbox/* (canônico — Route::redirect 301 de /memcofre/inbox)
```

```php
# routes/web.php OU Modules/KB/Http/routes.php — Route::redirect 301:
Route::redirect('/memcofre',          '/kb',               301); # dashboard
Route::redirect('/memcofre/ingest',   '/kb/sources/new',   301);
Route::redirect('/memcofre/inbox',    '/kb/inbox',         301);
Route::redirect('/memcofre/memoria',  '/kb/memoria',       301); # ou /copiloto/memoria — Wagner decide
Route::redirect('/memcofre/modulos/{module}', '/governance/module-grades/{module}', 301);
Route::redirect('/memcofre/chat',     '/jana/chat-corpus', 301);
Route::redirect('/memcofre/chat/ask', '/jana/chat-corpus/ask', 301);
Route::redirect('/memcofre/chat/new', '/jana/chat-corpus/new', 301);
# /memcofre/install/* já tem 301 → /srs/install/* (atual). Em E5 ambos /memcofre/install e /srs/install desativam — substituir por 410 Gone OU 301 → /admin/modules.

# Namespace refactor:
Modules\SRS\Http\Controllers\IngestController  → Modules\KB\Http\Controllers\KbSourceController
Modules\SRS\Http\Controllers\InboxController   → Modules\KB\Http\Controllers\KbInboxController
Modules\SRS\Http\Controllers\MemoriaController → Modules\KB\Http\Controllers\KbMemoriaController (merge com existing)
Modules\SRS\Services\MemoryReader              → Modules\KB\Services\KbMemoryReader
Modules\SRS\Entities\DocSource                 → Modules\KB\Entities\KbSource
```

```php
# Permissions Spatie (seeder cleanup):
'memcofre.access'  → ADD ALIAS 'kb.sources.read' + 'kb.inbox.read' (preservar atribuições users); destroy em E5+30d
'memcofre.write'   → ADD ALIAS 'kb.sources.write'
'memcofre.ingest'  → ADD ALIAS 'kb.sources.create'
'memcofre.chat'    → ADD ALIAS 'jana.chat-corpus.use'

# Tests migrar:
Modules/SRS/Tests/Feature/MultiTenantIsolationTest.php → Modules/KB/Tests/Feature/KbSourceMultiTenantIsolationTest.php
Modules/SRS/Tests/Feature/SmokeRoutesTest.php          → Modules/KB/Tests/Feature/KbInboxSmokeTest.php
Modules/SRS/Tests/Feature/Wave23/25/26/27/28*          → MIGRAR mas re-attribuição em module-grade-v3 (afeta nota SRS→0, KB ganha pontos)
```

### Receptor Modules/Jana — patches

```yaml
# Modules/Jana/SCOPE.md — adicionar:
contains:
  - "ChatCorpusController (absorvido de SRS E4) — chat IA sobre corpus docs ingested; URL /jana/chat-corpus mantida via 301 de /memcofre/chat"
  - "Services/ChatCorpusAssistant (absorvido de SRS E4) — keyword RAG offline + opt-in AI mode (defense PiiRedactor)"
  - "Entities/jana_chat_corpus_messages (ex-docs_chat_messages MIGRATE E3)"

# Adicionar a `db_tables_owned`:
db_tables_owned:
  - jana_chat_corpus_messages (ex-docs_chat_messages, com PII re-redacted)

# Adicionar a `url_prefixes`:
url_prefixes:
  - /jana/chat-corpus/* (NOVA — Route::redirect 301 de /memcofre/chat/*)
```

```php
# OTel span rename:
'srs.chat.ask' → 'jana.chat-corpus.ask'  # OtelHelper::spanBiz em ChatCorpusAssistant::ask

# Permissions:
'memcofre.chat' (mantém compat 30d) → 'jana.chat-corpus.use'
```

### Receptor Modules/Governance — patches

```yaml
# Modules/Governance/SCOPE.md — adicionar:
contains:
  - "DocValidatorService (absorvido de SRS E4) — 5 checks doc integrity (STORY_ORPHAN, RULE_NO_TEST, ADR_DANGLING, PAGE_NO_META, PAGE_STALE)"
  - "ModuleAuditorService (absorvido de SRS E4) — 15 checks doc quality (C01-C15) — opcional, avaliar sobreposição com module-grade-v3 antes"
  - "ModuleRequirementsController (absorvido de SRS E4) — ver requirements consolidados por módulo; URL /governance/module-grades/{name}/requirements"

# Cron entries em Kernel.php — ADICIONAR:
$schedule->command('governance:validate-docs --all')->dailyAt('04:00')->withoutOverlapping();
# Substitui (decisão Fase 5) ou complementa: $schedule->command('memcofre:sync-memories')->dailyAt('23:00');
```

### Receptor TeamMcp — patches

```yaml
# Modules/TeamMcp/SCOPE.md — sem mudança em `contains` (mcp_audit_log já listado)
# Migration: INSERT INTO mcp_audit_log SELECT FROM docs_validation_runs mapeando colunas (E3)
```

### Skills/agents/hooks/rules a atualizar (E4)

| Path | Mudança |
|---|---|
| `.claude/skills/sidebar-menu-arch/SKILL.md` | remover SRS dos exemplos OR atualizar pra refletir KB+Jana absorveu |
| `.claude/skills/migrar-modulo/SKILL.md` | atualizar exemplo: MemCofre→SRS PHP-only rename é case histórico; SRS→deprecação é case canon |
| `.claude/skills/meta-skill-roi-erp-autonomo/SKILL.md` | remover SRS de lista de módulos (mention) |
| `.claude/skills/cockpit-runbook/EXAMPLES.md` | atualizar exemplo se referencia SRS |
| `.claude/rules/modules.md` | rule passiva path-scoped permanece (cobre Modules/**) |
| `app/Console/Kernel.php` | E3+E4: migration `memcofre:sync-memories` → `kb:sync-memories` (renomear comando, manter schedule) OR remover schedule e Wagner roda manual; documentar decisão |
| `governance/module-grades-baseline.json` | E5: remover entry `"SRS": 58` OR marcar `deprecated_in: "0168-deprecate-srs"`; renames entry `MemCofre→SRS` permanece histórico |
| `governance/buckets/_INDEX.md` | E5: SRS sai do bucket `functional_horizontal` count |
| `Modules/SRS/CHANGELOG.md` | E0 (PRÉ-E1): **RESOLVER MERGE CONFLICT** Wave 28 vs Wave 27 (linhas `<<<<<<< HEAD` ativas) — esse conflito impede E1 limpo |

### MCP webhook git→DB sync impact

`memory/requisitos/SRS/*` (BRIEFING + SPEC + DEPRECATION-PLAN) hoje sincronizam pra `mcp_memory_documents` via webhook. Pós-deprecação E5:
- **Opção A:** mover `memory/requisitos/SRS/` → `memory/requisitos/_archive/SRS/` (pattern Tier 0 archive)
- **Opção B:** manter em-loco com frontmatter `lifecycle: historical` (consistente com ADRs canon append-only)

Recomendo **Opção B** — append-only canon (Wagner regra `proibicoes.md`).

---

## Fase 5 — Risk Register

| # | Risk | Severity | Tier 0? | Mitigation | Aplicar em |
|---|---|---|---|---|---|
| R1 | **PII em `docs_chat_messages.content`** — LLM completions raw podem ter CPF/email/nome cliente vazado apesar PiiRedactor defense-in-depth. Migration cega pra `jana_chat_corpus_messages` propaga vazamento + viola LGPD Art. 7º+16. | **Crítico** | ✅ SIM | Re-run `PiiRedactor::redact()` em TODO `content` row-by-row PRÉ-migration (script PHP via tinker dentro de transaction). Pest test counting `content REGEXP '[0-9]{11}'` (CPF padding) deve dar 0. Backup `mysqldump` cripto AES-256 antes. | E3 |
| R2 | **Multi-tenant Tier 0 cross-tenant em 7 entities** — `HasBusinessScope` em 4 entities + 3 repo-wide intencionais (`docs_links/pages/validation_runs`) Wave 17. Migration pode vazar contexto biz=4 ROTA LIVRE em biz=1 ou vice-versa se INSERT INTO target preserva business_id incorretamente. | **Crítico** | ✅ SIM | Pest cross-tenant biz=1 vs biz=99 ANTES (snapshot expectativa) e DEPOIS (validar receptor não vazou). Migrations explicitamente carregam `WHERE business_id = ?` em INSERT INTO SELECT. Wave 25 `Wave25CrossTenantSaturationTest` re-roda pré-merge. ROTA LIVRE biz=4 amostra: 0 rows esperadas em `docs_*` (tool interna Wagner). | E3 |
| R3 | **Cron `memcofre:sync-memories` daily 23:00** quebra silenciosamente se SyncMemoriesCommand deletado sem migration de schedule. Histórico 2026-04-24→2026-05-04 já teve drift de 40+ arquivos atrasados por mesmo motivo (catalogado em `app/Console/Kernel.php` §40-43). | **Alto** | ✅ SIM | E3+E4 PRs migram schedule no MESMO commit do comando: `$schedule->command('kb:sync-memories')` substitui `memcofre:sync-memories`. Pest `php artisan schedule:list` deve listar `kb:sync-memories` daily 23:00. Wagner valida prod 24h pós-deploy. SE Wagner decidir descontinuar (não migrar), remover schedule entry no mesmo PR + 7d watch. | E3-E4 |
| R4 | **FULLTEXT index re-criação em `mcp_memory_documents`** — `docs_evidences` tem FULLTEXT (content, notes). `mcp_memory_documents` já tem FULLTEXT próprio. INSERT INTO mass de evidências pode levar MySQL InnoDB a rebuild FULLTEXT (~minutos pra GBs de texto). Em prod, lock de tabela bloqueia leituras de `/kb/*` por janela visível. | **Alto** | ❌ | Migration E3 roda em janela de baixo tráfego (madrugada BRT). Verificar `SHOW TABLE STATUS LIKE 'mcp_memory_documents'` ANTES (estimar tempo). Considerar `ALTER TABLE ... ALGORITHM=INPLACE` se MySQL 8+ permitir. Smoke `curl -sv /kb` pós-migration. | E3 |
| R5 | **Wagner bookmarks `/memcofre/*`** (compat layer mantido por design Fase 3.7) — se E4 substituir 301 por 410 Gone, bookmarks privados Wagner quebram. | **Médio** | ❌ | Mantém Route::redirect 301 indefinido em E4 (não promover pra 410). Smoke `curl -sv https://oimpresso.com/memcofre/chat -I` deve retornar `301 Location: /jana/chat-corpus`. Skill `smoke-prod-evidence` (Tier B) força evidência. | E4 |
| R6 | **Webhook externo apontando pra `/memcofre/*`** — improvável (SRS é tool interna sem webhook documentado), mas Asaas/Inter/Meta WhatsApp/Pluggy callbacks podem ter URL legacy se Wagner configurou ad-hoc. | **Médio** | ❌ | Grep `routes.php` por endpoints `/api/memcofre/*` ou `/api/srs/*` (não encontrado em PRE-FLIGHT — confirmado zero). Auditoria manual painel Asaas/Inter/Meta antes de E4 (Wagner check). | E4 |
| R7 | **6 saturation tests Wave 23/25/26/27/28 contam pra module-grade-v3 SRS hoje** — Migrar pra KB/Jana/Governance re-attribui pontos D1-D9 pra outros módulos. SRS module-grade vira `deprecated_in: 0168` mas KB/Jana/Governance vão SUBIR de bucket. Wagner pode não querer (regressão visual de score). | **Médio** | ❌ | Pre-flight E5: rodar `php artisan module:grade KB --json` + Jana + Governance antes/depois pra documentar delta nota. Update `governance/module-grades-baseline.json` consciente em E5. | E5 |
| R8 | **Time MCP entrante (Felipe/Maiara/Eliana/Luiz) tokens scoped `memcofre.*`** — Spatie permissions com scope `memcofre.access` em `mcp_user_scopes`. Após E5 alias `kb.sources.read` ativa, tokens legacy quebram acesso. | **Médio** | ❌ | Seeder de E4 PRESERVA atribuições — `$user->givePermissionTo('kb.sources.read')` pra cada user que tinha `memcofre.access`. Pest test `User::find($id)->can('kb.sources.read') === true`. Comunicação Slack Wagner→time avisando aliases ativos. | E4 |
| R9 | **MERGE CONFLICT ATIVO em `Modules/SRS/CHANGELOG.md`** — linhas `<<<<<<< HEAD` Wave 28 vs `>>>>>>> origin/main` Wave 27 não resolvidos. Bloqueia E0/E1 clean. | **Alto** | ❌ | E0 pré-E1 PR separado dedicado: resolver merge conflict CHANGELOG mantendo append-only (ambas waves coexistem em ordem cronológica decrescente). Skill `governance-pr-summary` (Tier B) check. | E0 |
| R10 | **Module renames legacy entries em `governance/module-grades-baseline.json`** — entry `"MemCofre": {new_name: "SRS"}` permanece histórico. Se E5 remover SRS sem entry follow-up `"SRS": {new_name: null, deprecated_in: "0168"}`, baseline fica inconsistente (rubrica espera todos modules vivos). | **Médio** | ❌ | E5 PR atualiza baseline: substituir `"SRS": 58` por `"SRS": {"status": "deprecated", "deprecated_in": "0168-deprecate-srs", "successor": ["KB", "Jana", "Governance"]}`. CI `module-grades-gate.yml` ajustado pra ignorar deprecated modules. | E5 |

---

## Fase 6 — Roadmap 6 Etapas

| Etapa | Tipo PR | LOC est. | Pré-req | Gate Wagner | ETA dias úteis |
|---|---|---|---|---|---|
| **E0** | hotfix (PR docs) | ~30 | Plano aprovado | Resolver merge conflict CHANGELOG.md (Wave 28+27) — pré-E1 limpo | 0.5d |
| **E1** | docs only (PR) | ~80 | E0 mergeado | Promove ADR proposal `memory/decisions/proposals/deprecate-srs.md` → `memory/decisions/0168-deprecate-srs.md` `status: accepted` + `supersedes: [0080]`. Atualiza `Modules/SRS/SCOPE.md` frontmatter com `lifecycle: deprecating, deprecated_by: 0168`. | 1d |
| **E2** | docs/comments only (PR) | ~50 | E1 mergeado | Adiciona PHPDoc `@deprecated since 2026-05-17, será removido em E5, use \Modules\KB\... ou \Modules\Jana\... instead` em CADA Controller/Service/Entity de SRS (15 classes). Review code Wagner. **Não muda comportamento.** | 1d |
| **E3** | feat/data migration (PR) | ~280 | E2 mergeado + staging dump validado | Migrations T1 MIGRATE → kb_sources, T2 MIGRATE → mcp_memory_documents (FULLTEXT cuidado R4), T3 MIGRATE → jana_chat_corpus_messages (PII redact R1), T4 ARCHIVE+MIGRATE → mcp_audit_log, T5/T6 ARCHIVE, T7 DROP. Inclui `mysqldump` script + Pest cross-tenant (R2) biz=1/99 ANTES/DEPOIS. Schedule cron migration (R3) `memcofre:sync-memories` → `kb:sync-memories`. **NÃO toca código de Controllers/Services ainda — só DB layer.** | 5-7d |
| **E4** | refactor (PR) | ~280 | E3 mergeado + LGPD audit + cross-tenant Pest green | Namespace refactor 15 classes SRS→KB/Jana/Governance. Route::redirect 301 (R5) em 8 URLs. Permissions Spatie ALIAS preservando atribuições (R8). SCOPE.md de 3 receptores atualizado (`contains` + `url_prefixes` + `db_tables_owned`). Pest tests migrados (Waves 23/25/26/27/28 reatribuídas — R7 documenta delta). Skills/agents/rules atualizados. **Smoke `curl -sv` cada URL crítica** (`smoke-prod-evidence` Tier B). Canary biz=4 ROTA LIVRE 24h (mesmo SRS sendo tool interna, biz=4 não pode UI quebrada). | 7-10d |
| **E5** | chore (PR) | ~150 | E4 30d estável + zero Sentry/log error apontando `/memcofre/*` | `git rm -r Modules/SRS/` (preserva CHANGELOG histórico em `memory/requisitos/_archive/SRS/CHANGELOG.md`). Remove entry `bootstrap/providers.php` + `module.json` SRS. Remove schedule `memcofre:sync-memories` se decidido descontinuar (R3 follow-up). Update `governance/module-grades-baseline.json` SRS → deprecated entry (R10). Update `governance/buckets/_INDEX.md` count. Seeder cleanup permissions `memcofre.*` órfãs (R8). **Storage criptografado:** `governance/archive/srs-docs-*.sql.gz` preservado per LGPD retention (T4 365d, T5 1825d). | 30d wait + 2d code |
| **E6** | docs (PR) | ~100 | E5 mergeado | Update final: `Modules/SRS/SCOPE.md` status `deprecated`, `lifecycle: historical`. `memory/requisitos/SRS/BRIEFING.md` estado final ("deprecado em ADR 0168 — sucessor MCP server + KB + Jana + Governance"). `memory/08-handoff.md` entry append-only (ADR 0167 canônico checklist). `memory/proibicoes.md` entry nova: "NÃO criar features novas em `Modules/SRS` deprecated em ADR 0168". | 1d |
| **Total** | — | **~970** | — | — | **~47d** (com 30d wait E5) |

---

## ADR de deprecação — DRAFT inline (Nygard format)

```yaml
---
id: 0168
title: Deprecar Modules/SRS — sucessor MCP server canon + KB + Jana + Governance
status: proposed
date: 2026-05-17
deciders: [Wagner]
supersedes: [0080]
relates_to: [0053, 0061, 0088, 0092, 0094, 0123, 0153, 0160]
tags: [deprecation, governance, multi-tenant, modular]
---

# Contexto

Modules/SRS (ex-MemCofre, rename PHP-only Fase 3.7 PR-2 em 2026-05-06) entrou em estado ZUMBI:
- `SCOPE.md` 2026-05-05 prevê repurpose "cofre → System Rules Spec" com `srs_entries` table append-only + trigger MySQL — **nunca executado**. Entities ainda `Doc*`, tabelas ainda `docs_*`.
- `BRIEFING.md` 2026-05-16 admite: "Substituído na prática pelo MCP server canon (mcp.oimpresso.com). ❌ Não investir em features novas. Avaliar deprecação."
- Cliente piloto ROTA LIVRE biz=4 não interage com SRS (D5 `na_justified` no SPEC.md).
- module-grade-v3 atual 58/100 (D4 arquitetura 9/20 detecta drift cofre↔SRS corretamente).
- Funcionalidades-chave (ingest docs, search FULLTEXT, chat assistido sobre corpus, validation runs) **todas têm receptor canônico**: Modules/KB owns `mcp_memory_documents` (FULLTEXT + Meilisearch hybrid), Modules/Jana owns chat IA, Modules/Governance owns drift/audit, `mcp_audit_log` (TeamMcp) owns append-only audit canon.

ADR 0080 (Trust Tiers operacional audit findings) declarou SRS como L1 charter. Esta ADR substitui — SRS sai do mapa modular.

# Decisão

**Deprecar `Modules/SRS` em 6 etapas (E0-E6, ~47 dias úteis incluindo 30d wait pós-E4), distribuindo features e dados pra:**

| Feature | Receptor |
|---|---|
| ChatAssistant + DocChatMessage | Modules/Jana (`jana_chat_corpus_messages` + `/jana/chat-corpus`) |
| DocSource + IngestController + Sync*Commands | Modules/KB (`kb_sources` + `/kb/sources/*`) |
| DocEvidence + FULLTEXT search | Modules/KB → `mcp_memory_documents` (TeamMcp owns) |
| DocValidator + DocValidationRun + ModuleAuditor | Modules/Governance (DriftAlertsController + DocValidatorService) + `mcp_audit_log` |
| DocPage + DocRequirement + DocLink | Descontinuar / ARCHIVE (fonte canônica é `memory/requisitos/<X>/SPEC.md` via MCP server) |
| Compat layer `memcofre.*` URLs/permissions | Route::redirect 301 PRESERVE indefinido (Wagner bookmarks) |

**Plano detalhado:** `memory/requisitos/SRS/DEPRECATION-PLAN.md` (este documento).

# Consequências

## Positivas
- **Score governance v4** sobe (KB/Jana/Governance herdam Pest Waves 23/25/26/27/28 — re-atribuição de pontos cross-modules).
- **Time MCP entrante** (Felipe/Maiara/Eliana/Luiz) tem 1 módulo a menos pra ler — redução cognitiva.
- **SoC brutal** ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §5) — chat IA fica em Jana, audit em Governance, knowledge em KB. SRS era overlap parcial.
- **Storage** — drop de `docs_pages` (cache regenerável) e ARCHIVE de `docs_links/requirements` libera ~MB-GB tabelas (volume real validar em E3).

## Negativas / Custos
- **47d ETA** com 30d wait E5 — não é fast. Compensa porque governance escala (não acelera com IA-pair, é human-limited).
- **Bookmarks Wagner** `/memcofre/*` preservados via 301 indefinido — não é remoção limpa.
- **PR ≤300 LOC commit-discipline** (Tier A) — E3 e E4 estão no limite (~280 LOC cada). Acompanhar de perto, dividir em sub-PRs se passar.
- **Migration FULLTEXT** (R4 risk) — janela de bloqueio MySQL InnoDB. Madrugada BRT obrigatório.
- **PII LGPD** (R1 risk Tier 0) — re-run PiiRedactor row-by-row em `docs_chat_messages.content` ANTES de archive. Bloqueador E3.

## Neutras / monitorar
- Module Grade v4 baseline (R10) — entry SRS vira `deprecated_in: 0168`, KB/Jana/Governance sobem. Pode parecer regressão visual no painel se Wagner não atualizar baseline conscientemente.
- 6 saturation tests reatribuídos (R7) — dimensões D1-D9 dos receptores ganham, SRS perde mas vira histórico.

# Refs

- [memory/requisitos/SRS/DEPRECATION-PLAN.md](../requisitos/SRS/DEPRECATION-PLAN.md) — este plano
- [memory/requisitos/SRS/BRIEFING.md](../requisitos/SRS/BRIEFING.md) — declaração 2026-05-16 "substituído na prática"
- [Modules/SRS/SCOPE.md](../../Modules/SRS/SCOPE.md) — transition_plan Fase 3.7 nunca executado
- [Modules/KB/SCOPE.md](../../Modules/KB/SCOPE.md) — receptor primário (mcp_memory_documents)
- [Modules/Jana/SCOPE.md](../../Modules/Jana/SCOPE.md) — receptor chat IA
- [Modules/Governance/SCOPE.md](../../Modules/Governance/SCOPE.md) — receptor validation/drift
- [Modules/TeamMcp/SCOPE.md](../../Modules/TeamMcp/SCOPE.md) — owns mcp_audit_log + mcp_memory_documents
- [ADR 0053 MCP server canon](0053-mcp-server-governanca-como-produto.md) — sucessor canônico declarado
- [ADR 0061 Zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md) — knowledge canon git→MCP
- [ADR 0088 Module rename PHP-only](0088-module-rename-php-only.md) — pattern Fase 3.7 PR-2 (MemCofre→SRS)
- [ADR 0093 Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md) — IRREVOGÁVEL
- [ADR 0094 Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md) — SoC brutal §5
- [ADR 0123 Modules/Arquivos backbone](0123-modules-arquivos-backbone.md) — DMS canon (alternativa pra storage)
- [governance/module-grades-baseline.json](../../governance/module-grades-baseline.json) — baseline entry `SRS: 58`
- [app/Console/Kernel.php §34-46](../../app/Console/Kernel.php) — schedule `memcofre:sync-memories` daily 23:00 (R3)
```

---

## Refs cruzados

### ADRs canônicas citadas
- **Mãe SRS legacy:** [0080 Trust Tiers operacional audit findings](../../decisions/0080-trust-tiers-operacional-audit-findings.md) (SRS charter)
- **Sucessor primário:** [0053 MCP server canon governança](../../decisions/0053-mcp-server-governanca-como-produto.md)
- **Pattern rename:** [0088 Module rename PHP-only](../../decisions/0088-module-rename-php-only.md) — MemCofre→SRS Fase 3.7 PR-2
- **Pattern tabela rename:** [0092 Tabela rename Copiloto→Jana](../../decisions/0092-tabela-rename-copiloto-para-jana.md)
- **Tier 0:** [0093 Multi-tenant isolation](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- **Constituição:** [0094 Constituição v2 7 camadas 8 princípios](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- **DMS canon (alt storage):** [0123 Modules/Arquivos backbone](../../decisions/0123-modules-arquivos-backbone.md)
- **Modular vertical:** [0121 oimpresso modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- **Append-only handoff:** [0130 Handoff append-only MCP-first](../../decisions/0130-handoff-append-only-mcp-first.md)
- **Tiering memória:** [0131 Tiering memória canônico/local/segredo](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)
- **Pós-merge checklist:** [0167 Checklist pós-merge canônico + errata 0130](../../decisions/0167-*.md) (PR #1027)
- **Module Grade:** [0153-0160 rubrica v1→v4 Scoped Scorecards](../../decisions/0160-governance-v4-scoped-scorecards-buckets.md)

### SCOPE.md de receptores referenciados
- [Modules/KB/SCOPE.md](../../../Modules/KB/SCOPE.md)
- [Modules/Jana/SCOPE.md](../../../Modules/Jana/SCOPE.md)
- [Modules/Governance/SCOPE.md](../../../Modules/Governance/SCOPE.md)
- [Modules/TeamMcp/SCOPE.md](../../../Modules/TeamMcp/SCOPE.md)
- [Modules/Brief/SCOPE.md](../../../Modules/Brief/SCOPE.md) (ref — não receptor mas cita SRS no purpose L1)
- [Modules/Arquivos/SCOPE.md](../../../Modules/Arquivos/SCOPE.md) (alternativa DMS pra storage de uploads ingest)

### Skills mencionadas
- `deprecar-modulo` (este agent — Tier C)
- `preflight-modulo` (Tier A always-on)
- `multi-tenant-patterns` (Tier A)
- `commit-discipline` (Tier A)
- `smoke-prod-evidence` (Tier B — E4 gate `curl -sv` URLs 301)
- `governance-pr-summary` (Tier B — E1/E3/E4 PR injeta Module Grade v4 seção)
- `module-grades-gate` (Tier C — E5 baseline reconciliation)
- `mcp-first` (Tier A — operações via tools MCP)

### Configs/runbooks referenciados
- [app/Console/Kernel.php §34-46](../../../app/Console/Kernel.php) — schedule `memcofre:sync-memories`
- [Modules/SRS/Config/retention.php](../../../Modules/SRS/Config/retention.php) — janelas LGPD
- [governance/module-grades-baseline.json linha 40](../../../governance/module-grades-baseline.json) — `"SRS": 58`
- [governance/buckets/_INDEX.md](../../../governance/buckets/_INDEX.md) — bucket `functional_horizontal`
- [memory/requisitos/Infra/RUNBOOK-acesso-ct100.md](../Infra/RUNBOOK-acesso-ct100.md) (ref — SSH CT 100 pra rodar mysqldump E3)
- [memory/governance/TRUST-TIERS.md](../../governance/TRUST-TIERS.md) (ref — L1 charter SRS legacy)

---

**Última atualização:** 2026-05-17 · Plano gerado por agent `deprecar-modulo` em sessão `claude/frosty-greider-83ab2f` · Wagner aprovou Caminho 1 (deprecar SRS) baseado em pre-flight desta sessão.
