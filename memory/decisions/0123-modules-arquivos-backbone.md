---
slug: 0123-modules-arquivos-backbone
number: 123
title: "Modules/Arquivos — backbone DMS (todo arquivo anexado entra aqui)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-09"
module: null
supersedes: []
related:
  - 0042-proxmox-docker-host-canonico
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0124-curador-conhecimento-pipeline
  - 0122-admin-center-ct100
---

## Contexto

Wagner pediu (2026-05-09): **"todo arquivo anexado deve cair lá"** — referência a um módulo `Arquivos` único que é ponto de entrada físico/lógico de qualquer arquivo da empresa (D:\Conhecimento via Curador-script, anexo de ticket Suporte, XML NF-e import, print de bug em chamado, foto produto, contrato cliente, etc).

Hoje cada módulo lida com seus anexos isolado:
- `Modules/NfeBrasil/` salva XML em `storage/nfe/`
- `Modules/Suporte/` (futuro) — anexos de ticket
- `Modules/Repair/` — fotos da OS (atualmente em campo BLOB?)
- `Modules/Cms/` — uploads de blog/landing
- `Modules/Officeimpresso/` — relatórios FastReport gerados

Resultado: **fragmentação total** — sem busca unificada, sem dedupe cross-módulo, sem audit log unificado, sem retenção/LGPD política única, sem signed-URL, classificação ad-hoc.

Curador ([ADR 0124](0124-curador-conhecimento-pipeline.md)) e Admin Center ([ADR 0122](0122-admin-center-ct100.md)) sozinhos não resolvem isso — Curador é pipeline de **ingestão** filesystem-aware (D:\Conhecimento), Admin é **UI de governança**. Falta o **backbone storage**.

## Decisão

Criar `Modules/Arquivos/` como **DMS interno** (Document Management System) — backbone transversal que **todo módulo do oimpresso passa a usar pra anexar arquivos**.

### 8 princípios duros

1. **Polimorfismo via Eloquent morph.** Tabela `arquivos` com `arquivable_type` + `arquivable_id` — qualquer model (Transaction, Ticket, Repair, Contact, etc) pode ter N arquivos via `morphMany`.

2. **Multi-tenant Tier 0 obrigatório.** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — toda linha em `arquivos` tem `business_id` indexado + FK + global scope. Cliente uploadando NF-e do biz=4 NUNCA vê arquivo do biz=1.

3. **Storage abstraído (Laravel Filesystem).** Disk `arquivos` configurável via `.env`:
   - **Default MVP:** `local-ct100` — volume mounted no CT 100 (`/var/lib/oimpresso-arquivos/`)
   - **Swap futuro (Fase 3):** S3-compatible (Backblaze B2 / Wasabi ~R$ [redacted Tier 0]/TB/mês)
   - Disk `vault` separado pra `bucket=sensitive` (encryption-at-rest mandatório)
   - Hostinger app web NUNCA armazena arquivo — lê via signed URL apontando pro CT 100

4. **Trait `HasArquivos`.** Outros models incluem o trait → ganham relação `arquivos()` + métodos `attachArquivo($file)`, `arquivosClassificados('memory')`, etc. Migração progressiva: cada módulo adota no seu ritmo.

5. **Curador como engine.** Classificação acontece em `Modules/Arquivos/Services/CuradorEngine.php` (port das 15+ regras de [scripts/curador/lib/rules.mjs](../../scripts/curador/lib/rules.mjs)). Test parity test garante que JS+PHP retornam mesmo bucket pro mesmo arquivo.

6. **Sensitive bloqueia disk default.** Upload de `.env`/`.pfx`/`.rdp`/`.pem`/XML PII redireciona pra disk `vault` (criptografado, signed URL com expiração 1h, audit log obrigatório). Tentativa de baixar sem auth → 403 + log.

7. **Soft-delete (não rm).** Coluna `deleted_at` substitui `_DESCARTADO/`. Recovery: `Arquivo::withTrashed()->find($id)->restore()`. Hard-delete em job separado após N=90 dias.

8. **Audit log integral.** Toda operação (upload, download, classify, soft-delete, restore, hard-delete) gera linha em `arquivos_audit_log` — quem, quando, qual business_id, qual arquivable, IP, signed-URL token usado.

### Schema (migrations)

```sql
-- arquivos: tabela mãe
CREATE TABLE arquivos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    arquivable_type VARCHAR(255) NULL,         -- ex: 'Modules\NfeBrasil\Models\NfeXml'
    arquivable_id BIGINT UNSIGNED NULL,
    disk VARCHAR(32) NOT NULL,                 -- 'arquivos' | 'vault'
    storage_path VARCHAR(512) NOT NULL,        -- relativo ao disk
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(127) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    md5 CHAR(32) NOT NULL,                     -- dedupe key
    bucket ENUM('sensitive','memory','user','spec','ambiguous','discard','active') NOT NULL DEFAULT 'active',
    sub_destination VARCHAR(255) NULL,
    sensitive_flags JSON NULL,
    classified_by VARCHAR(64) NULL,            -- 'curador-engine' | 'manual:wagner' | 'inherit:nfebrasil'
    classified_at TIMESTAMP NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    visibility ENUM('private','business','public') NOT NULL DEFAULT 'private',
    encrypted BOOLEAN NOT NULL DEFAULT FALSE,
    retention_days INT NULL,                   -- LGPD: NULL = sem expiração explícita
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_business (business_id),
    INDEX idx_arquivable (arquivable_type, arquivable_id),
    INDEX idx_md5 (md5),                       -- dedupe lookup
    INDEX idx_bucket (bucket),
    INDEX idx_deleted (deleted_at),
    FOREIGN KEY (business_id) REFERENCES business(id),
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- arquivos_audit_log: append-only
CREATE TABLE arquivos_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    arquivo_id BIGINT UNSIGNED NOT NULL,
    business_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    action ENUM('upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued') NOT NULL,
    payload JSON NULL,                         -- IP, token, motivo, etc
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_arquivo (arquivo_id),
    INDEX idx_business_action (business_id, action, created_at),
    FOREIGN KEY (arquivo_id) REFERENCES arquivos(id)
);

-- arquivos_dedupe: hash-only metadata pra detectar dup cross-business safe
CREATE TABLE arquivos_dedupe (
    md5 CHAR(32) PRIMARY KEY,
    first_seen_at TIMESTAMP NOT NULL,
    occurrences INT UNSIGNED NOT NULL DEFAULT 1
    -- NÃO armazena business_id aqui (cross-business dedupe leak) — só conta global
);
```

### API (Trait + Service)

```php
// Trait — outros models incluem
trait HasArquivos
{
    public function arquivos(): MorphMany
    {
        return $this->morphMany(Arquivo::class, 'arquivable');
    }

    public function attachArquivo(UploadedFile $file, array $opts = []): Arquivo
    {
        return app(ArquivosService::class)->attach($this, $file, $opts);
    }
}

// Service principal
class ArquivosService
{
    public function attach(Model $owner, UploadedFile $file, array $opts): Arquivo;
    public function classify(Arquivo $arquivo): Arquivo;  // chama CuradorEngine
    public function signedUrl(Arquivo $arquivo, int $expiresMinutes = 60): string;
    public function softDelete(Arquivo $arquivo): void;
    public function restore(Arquivo $arquivo): void;
    public function dedupe(string $md5, int $businessId): ?Arquivo;
}

// CuradorEngine (port das regras JS)
class CuradorEngine
{
    public function classify(Arquivo $arquivo): array; // {bucket, sub_destination, flags, rule_matched}
}
```

### Storage layout (disk `arquivos` no CT 100)

```
/var/lib/oimpresso-arquivos/
├── biz-1/                    # business_id=1 (Wagner WR2)
│   ├── 2026/05/abc123def.xml
│   └── 2026/05/789xyz.pdf
├── biz-4/                    # business_id=4 (ROTA LIVRE)
│   └── 2026/05/...
└── biz-N/

/var/lib/oimpresso-vault/     # disk `vault`, encrypted-at-rest
├── biz-1/
│   └── secrets/...
```

Path absoluto = `disk_root + business_id + YYYY/MM + md5_prefix.ext`. Dedupe via md5 dentro do mesmo business.

## Não-goals

- ❌ NÃO substitui MemCofre (Cofre de Memórias) — é distinto: MemCofre = anotações/decisões; Arquivos = binary files
- ❌ NÃO replica Copiloto/Memoria — Memoria consome metadados de `arquivos` mas tem semantic recall próprio
- ❌ NÃO indexa conteúdo full-text MVP (só metadata) — Meilisearch full-text vem em Fase 4
- ❌ NÃO faz OCR/transcrição MVP — só armazena binário (Fase 5)
- ❌ NÃO antivirus scan MVP — assume MIME whitelist + cap upload 50MB suficiente; ClamAV vem se cliente upload abrir vetor real
- ❌ NÃO substitui storage existente automaticamente — outros módulos migram **opt-in** via trait, não compulsório
- ❌ NÃO acessível pela internet pública pra cliente externo — cliente upload via app principal Hostinger → API auth → relay pro CT 100

## MIME whitelist por contexto

| Contexto (uso `attach($owner, ...)`) | MIME types permitidos | Cap |
|---|---|---|
| `nfe-xml` | `application/xml`, `text/xml` | 5MB |
| `ticket-anexo` (Suporte) | xml, pdf, png, jpg, doc, docx, xlsx | 25MB |
| `repair-foto` | png, jpg, heic, webp | 10MB |
| `cms-blog-image` | png, jpg, webp, svg, gif | 5MB |
| `admin-curador` | * (qualquer — admin Wagner) | 50MB |

Cap-passada → 413 + log. MIME-rejeitado → 415 + log.

## Plano de adoção

**Sprint 1 — backbone (~3-5 dias IA-pair):**
- US-ARQ-001..003: scaffold módulo + 3 migrations + Service + Trait `HasArquivos`
- US-ARQ-004: CuradorEngine PHP (port regras JS)
- US-ARQ-005: ParityTest JS×PHP (mesmo MD5 → mesmo bucket)
- US-ARQ-006: Storage disks config (local-ct100 + vault)
- US-ARQ-007: Signed URL controller + audit log
- US-ARQ-008: Pest tests (multi-tenant isolation, sensitive blocking, soft-delete)

**Sprint 2 — UI Admin Center + Curador script integration:**
- US-ARQ-009: API `POST /admin/arquivos/upload-batch` recebe JSONL do `scripts/curador/discover.mjs`
- US-ARQ-010: Pages `Modules/Admin/Pages/Arquivos/{Index,Review,Detail}.tsx`
- US-ARQ-011: Job `ApplyBatchJob` Horizon

**Sprint 3 — primeiro consumer real:**
- US-ARQ-012: `Modules/NfeBrasil/Models/NfeXml` adota trait → migrar XML existentes pra `arquivos` table
- Validação: 100% dos novos NF-e imports caem em `arquivos` + Officeimpresso ainda funciona

**Sprint 4+ — outros módulos opt-in:**
- Suporte (anexos ticket), Repair (foto OS), Cms (uploads), conforme prioridade

## Alternativas consideradas

### A. Manter cada módulo isolado (fragmentação atual)
**Rejeitada.** Sem busca unificada, dedupe, audit, LGPD coerente. Wagner já viu o problema.

### B. Spatie Media Library (pacote Laravel popular)
**Considerada parcial.** Boa abstração mas (i) não tem Curador-engine nativo, (ii) não tem multi-tenant Tier 0 forte como precisamos, (iii) trade-off de dependência externa de pacote third-party em backbone. Decisão: **inspirar-se no design** (morphMany, collections) mas escrever próprio + **avaliar reuso de partes específicas** (signed URL, conversions) na implementação.

### C. Tabela única flat (sem polimorfismo)
**Rejeitada.** Precisa coluna FK pra cada módulo (transaction_id, ticket_id, repair_id...) — impossível escalar. Polimorfismo Eloquent é canon Laravel.

### D. Storage MySQL BLOB
**Rejeitada.** Inflar DB com binary é antipattern. CT 100 disk é purpose-built.

### E. S3 desde MVP
**Adiada (Fase 3).** S3 cobra egress; se Wagner uploadar 50GB de D:\Conhecimento, custos exploram. CT 100 2TB é grátis (já paga Proxmox). S3 swap fica fácil via Laravel Filesystem disk swap quando volume justificar.

### F. NextCloud / SharePoint integration
**Rejeitada.** Adiciona dependência externa + auth duplicada. Wagner quer "tudo lá" — singular, sob controle dele.

## Consequências

✅ **Boas:**
- Backbone único pra anexo cross-módulo elimina fragmentação
- Multi-tenant Tier 0 + audit log resolve LGPD em 1 lugar
- Curador-engine reusado server-side mantém parity com script local
- Trait `HasArquivos` permite migração opt-in (não-disruptiva)
- Soft-delete + hard-delete agendado dá margem de erro humano
- Sensitive bloqueado no disk default impede vazamento acidental
- Arquivos table vira fonte pra Jana fazer recall semântico (Fase 4 Meilisearch)

⚠️ **Tradeoffs:**
- 2 lugares pra manter regra Curador (JS script + PHP Service) — mitigação: ParityTest obrigatório, mesma data fixture
- CT 100 vira ponto crítico storage — fallback: backup nightly pra Hostinger ou OneDrive
- Migrar anexos existentes (NFe XML, repair fotos) é trabalho não-trivial — Sprint 3+ progressivo
- Cap upload 50MB pode dar problema com PDF FastReport grande — revisar caso a caso
- `withoutGlobalScopes` em queries cross-business obrigatoriamente comentado — skill `multi-tenant-patterns` Tier A já enforça

## Validação Sprint 1

- ✅ Pest test: `Arquivo::query()->count()` em context biz=1 NÃO retorna arquivos biz=4
- ✅ Pest test: upload `.env` → bucket=sensitive + disk=vault automaticamente
- ✅ Pest test: dedupe — 2× upload mesmo MD5 mesmo business retorna mesma row
- ✅ ParityTest: 100 fixtures comuns → mesmo bucket em CuradorEngine.php e classify-rules.mjs
- ✅ Smoke: NFe import deposita XML em `/var/lib/oimpresso-arquivos/biz-1/2026/05/...`
- ✅ Audit log preenche pra todas 8 ações enum

## Notas de governança

- ADR 0123 é proposta (status `proposed`). Wagner aprova ou rejeita após revisar princípios duros.
- Sprint 1 NÃO começa até ADR estar `accepted`.
- ADR 0124 (Curador) é amendado: Curador-engine vira biblioteca compartilhada (`scripts/curador/lib/rules.mjs` + `Modules/Arquivos/Services/CuradorEngine.php`).
- ADR 0122 (Admin) é amendado: `Pages/Arquivos/*` substitui `Pages/Curador/*`.
