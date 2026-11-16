---
id: requisitos-arquivos-charters-vault-curador
---

# CHARTERS — Vault Encryption + Curador (Modules/Arquivos)

> **Contrato vivo** dos componentes de backend do módulo (serviço do cofre, curador, trait). A tela `/arquivos` tem charter próprio ao lado do `.tsx`: [`resources/js/Pages/Arquivos/Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md). Até 2026-08-24 o módulo não tinha tela e este arquivo dizia "backend-only".

---

## CHARTER 1 — VaultEncryptionService

**Path:** `Modules/Arquivos/Services/VaultEncryptionService.php`
**Status:** `live`
**Owner:** [W]
**Cliente:** biz=1 (Wagner uso interno) — pré-piloto vault pessoal antes de expor multi-tenant prod

### Mission

Cifrar arquivos sensíveis em repouso (vault encryption AES-256-GCM envelope) com chave por tenant rotacionável, mantendo audit log append-only de toda operação encrypt/decrypt/rotate. Garantir que vazamento de DB sem chave KMS é inútil; vazamento de chave KMS sem DB é inútil.

> **Divergência medida em 2026-11-16 — o código não faz o que a Mission e os Goals 1 e 4 descrevem.**
> `VaultEncryptionService` usa `Crypt::encryptString` do Laravel: AES-256-CBC com HMAC, chave
> única da aplicação (`APP_KEY`). Não há DEK por arquivo nem KEK por empresa; o isolamento entre
> empresas vem do global scope de `business_id`, não da chave. A escolha está no docblock do
> serviço (decisão [W] 2026-05-10). Mudar o contrato ou o código é decisão [W]; até lá, o
> comportamento real é o do serviço.

### Goals

1. Envelope encryption — DEK random per-file cifrada com KEK per-tenant
2. Key rotation sem downtime — `arquivos:reencrypt-vault --old-key=...` re-cifra o cofre depois da troca da `APP_KEY`
3. Audit log append-only — toda decrypt registra `user_id` + `arquivo_id` + `ip` + `motivo`
4. Multi-tenant Tier 0 — KEK biz=1 NUNCA decifra arquivo biz=99 (Pest `MultiTenantTest.php`)
5. LGPD compliance — retention policy + right-to-be-forgotten via `arquivos:retention-cleanup`

### Non-Goals

- ❌ Encryption-in-transit (TLS responsibility do Hostinger/CT 100)
- ❌ Client-side encryption (overhead inviabiliza preview/thumbnail)
- ❌ HSM/cloud KMS externo (KEK local em config encrypted; ADR feature-wish)
- ❌ Quantum-resistant crypto (AES-256-GCM suficiente até NIST publicar standard pós-quântico)

### UX targets

- Decrypt latency ≤50ms p95 pra arquivos ≤10MB (stream chunks)
- Rotate vault command ≤2min pra biz com 10k arquivos (background queue)
- Audit log query ≤200ms p95 por `arquivo_id` (índice composto)

### Anti-hooks (red flags = bug)

- 🚨 `decrypt()` sem `business_id` scope check → vaza cross-tenant (Tier 0 violation)
- 🚨 KEK em `.env` plaintext → use `config/arquivos.php` com `decrypt()` Laravel + APP_KEY rotacionável
- 🚨 Audit log via `Cache::put` ou log file → DEVE ser tabela `arquivos_audit_log` (append-only, FK arquivo_id)
- 🚨 Rotate vault sem dry-run flag → biz grande quebra sem preview

### Tests canônicos

- `VaultEncryptionServiceTest.php` — encrypt/decrypt round-trip + tampering detection
- `MultiTenantTest.php` — biz=1 KEK não decifra arquivo biz=99
- `ReencryptVaultCommandTest.php` — rotate sem perda + idempotência

---

## CHARTER 2 — CuradorEngine (pipeline 5-fase)

**Path:** `Modules/Arquivos/Services/Curador/CuradorEngine.php`
**Status:** `live`
**Owner:** [W]
**Cliente:** biz=1 (Wagner) — curadoria pessoal D:\Conhecimento (manuais Delphi WR Comercial, ADRs históricas, transcripts atendimento)

### Mission

Ingerir batch de arquivos heterogêneos (PDFs, .md, .docx, .xml, .key, .pfx) e classificar em buckets canônicos (conhecimento canon / segredo / lixo / ambíguo) com pipeline determinístico-first (heurística regex+mime+path) e Claude-second (só pra ambíguos) — economizando crédito Anthropic + sobrevivendo `/clear`/`/compact` via estado persistente JSONL.

### Goals

1. **DISCOVER** — scan recursivo + hash SHA-256 + metadata extract (size, mime, path, mtime)
2. **CLASSIFY** — heurística 70-80% determinística (regex path, mime, magic bytes); Claude API só pros 20-30% ambíguos
3. **REPORT** — markdown batch-by-batch pra Wagner aprovar (`/curador report`)
4. **REVIEW** — Wagner aprova/edita/rejeita batch (publication-policy)
5. **APPLY** — move arquivos pra vault encrypted OU deleta OU marca canon git
6. Sensitive (.env, .pfx, .rdp, .key, XML cliente) BLOQUEIA commit (hook pré-apply)
7. Multi-usuário consent-first (LGPD) — 1 usuário curador por tenant

### Non-Goals

- ❌ Classificação 100% autônoma — Wagner aprova batch (ADR publication-policy)
- ❌ OCR PDFs scanned — backlog feature-wish (Tesseract sidecar CT 100)
- ❌ Embedding semântico via Jana — backlog feature-wish (depende HyDE Meilisearch)
- ❌ Cross-tenant curadoria — engine respeita `business_id` scope (Tier 0)

### UX targets

- Heurística stage ≤5min pra batch 1k arquivos
- Claude stage ≤30s por arquivo ambíguo (Haiku model)
- Report markdown legível ≤500 linhas (paginação se batch >100)
- Estado JSONL sobrevive `/clear` `/compact` reboot (scripts/curador/db/files.jsonl)

### Anti-hooks (red flags = bug)

- 🚨 Aplicar batch sem Wagner aprovar → viola publication-policy
- 🚨 Mover .key/.pfx pra vault sem alerta → segredo deve ir Vaultwarden, não vault Arquivos
- 🚨 Commitar XML cliente (CPF/CNPJ) → PII leak (Tier 0 violation)
- 🚨 Re-classificar arquivo já processado sem flag `--force` → desperdiça crédito Claude
- 🚨 Engine chamar Claude API com batch >50 arquivos numa request → estoura context window

### Tests canônicos

- `CuradorEngineTest.php` — heurística determinística estável
- `CuradorParityTest.php` — re-run mesma fonte = mesmo resultado (idempotência)
- `MultiTenantTest.php` — curador biz=1 não vê arquivos biz=99

---

## CHARTER 3 — HasArquivos trait (consumer pattern)

**Path:** `Modules/Arquivos/Concerns/HasArquivos.php`
**Status:** `live`
**Owner:** [W]
**Cliente:** módulos consumidores (NfeBrasil, Consumidores, Repair, futuros)

### Mission

Permitir que qualquer Eloquent Model anexe arquivos via `$model->arquivos()` polimórfico, mantendo `business_id` scope + vault encryption transparente + dedupe por MD5 dentro da empresa, sem que consumer precise conhecer detalhes do vault.

### Goals

1. API minimalista — `$model->attachArquivo($uploadedFile, $opts)` + `$model->arquivos` (collection) + `$model->arquivosClassificados($bucket)`
2. Polimórfico — funciona em qualquer Eloquent (Transaction, JobSheet, Contact, etc.)
3. Multi-tenant transparente — herda `business_id` do model parent
4. Dedupe automático — mesmo MD5 na mesma empresa reusa o blob já gravado (`ArquivosService::attachInternal`)
5. Eager-load friendly — `with('arquivos')` sem N+1

### Non-Goals

- ❌ UI upload widget — consumer fornece (Inertia React/Blade)
- ❌ Thumbnail generation — backlog feature-wish (image proxy CT 100)
- ❌ Versioning de arquivos — backlog feature-wish

### UX targets

- Attach ≤300ms p95 pra arquivos ≤5MB
- Eager-load arquivos ≤50ms p95 pra 100 parent rows

### Anti-hooks (red flags = bug)

- 🚨 Consumer chama `VaultEncryptionService::encrypt()` direto → use trait API
- 🚨 Consumer query `Arquivo::where()` sem `business_id` scope → global scope já garante, mas evitar pattern
- 🚨 Attach mesmo arquivo 2x sem dedupe → checagem de MD5 obrigatória

### Tests canônicos

- `ConsumersTraitTest.php` — attach/detach + dedupe + eager-load
- `BackfillConsumersTest.php` — backfill legado idempotente
- `BackfillNfeXmlTest.php` — backfill XMLs NfeBrasil → vault

---

**Notas de manutenção:**

- Charters acima são `live` — alterações exigem PR + ADR se mudar mission/non-goals
- A UI Inertia surgiu em 2026-08-24 (`resources/js/Pages/Arquivos/Index.tsx`), com charter próprio ao lado; este doc segue cobrindo só os componentes de backend
- Skill `charter-first` (Tier A dormente até S4) aplicará automaticamente quando charters virarem path-scoped
