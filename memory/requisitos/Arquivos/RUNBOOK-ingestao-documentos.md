# RUNBOOK — Ingestão de documentos no oimpresso

> **Use sempre que for ingerir qualquer arquivo no oimpresso (NFe XML, DANFE PDF, fotos OS, contratos, anexos ticket, comprovantes pagamento, etc).**
>
> Este RUNBOOK é a **regra canônica única** pra ingestão. Qualquer caminho diferente do descrito aqui é **anti-padrão Tier 0** — bypassa audit log + classify + multi-tenant scope.

## Quando usar

Sempre que o sistema (ou um Producer Module) precisar persistir um arquivo associado a um Model de negócio:
- NfeService autoriza nota → ingere XML autorizado
- DanfeService renderiza PDF → ingere DANFE
- Repair JobSheet → operador anexa foto
- Officeimpresso → recebe boleto-pago e gera NFe automática
- Contato cliente envia contrato assinado
- Suporte recebe anexo em ticket

## Quando NÃO usar

- **Skill `curador` (ingestão de conhecimento canon do projeto):** pipeline próprio (DISCOVER→CLASSIFY→REPORT→REVIEW→APPLY) com fixtures + JS rules. Use ADR 0124 + skill direta.
- **Memory canônica do oimpresso (`memory/*`):** vai por git push (ADR 0061 zero auto-mem privada) → webhook GitHub → MCP server. NÃO usar Modules/Arquivos backbone pra docs internos do projeto.
- **Backup de banco:** rotina Hostinger separada (auto-mem `reference_hostinger_hpanel.md`).

## Princípios duros (cada ingestão respeita TODOS)

1. **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — toda row em `arquivos` tem `business_id` indexado + global scope; jobs assíncronos passam `$businessId` no constructor; CLI usa `--business=N` explícito.

2. **Append-only audit** ([ADR 0123](../../decisions/0123-modules-arquivos-backbone.md) §6) — toda ação (upload, classify, signed_url_issued, signed_url_consumed, soft_delete, restore, hard_delete, exported) gera row em `arquivos_audit_log`. Nunca pular audit pra "performance".

3. **LGPD-first** — consent registrado, retention configurado (config `arquivos.retention_days_default=90`), portabilidade disponível (`arquivos:export-zip`), PII redactada em logs.

4. **Encryption-at-rest pra sensitive** ([ADR 0123](../../decisions/0123-modules-arquivos-backbone.md) §3, [ADR 0126](../../decisions/0126-vault-chunked-encryption-sprint-2.md)) — bucket `sensitive` → disk `vault` → `VaultEncryptionService::putEncrypted` (Crypt::encryptString APP_KEY-backed AES-256-CBC) com cap 50MB.

5. **Imutabilidade legal** — XML NFe autorizado nunca é editado (CFE Art. 7º MTP 671/2021 análogo); marcações ponto append-only (`Marcacao::anular()` em vez de UPDATE).

6. **Dedupe per-business** — `arquivos.md5` lookup limitado a `business_id` do uploader; **nunca** retorna match cross-tenant (Agent E security review §dedupe leak).

7. **Server-side authoritative** — backend valida + classifica + roteia. Frontend NUNCA decide bucket/disk/encryption.

## Caminhos canônicos de ingestão

### A) Producer Module via `ArquivosService::attach` (preferido)

```php
use Modules\Arquivos\Services\ArquivosService;

$arquivos = app(ArquivosService::class);
$arquivo = $arquivos->attach(
    owner: $jobSheet,           // Model com trait HasArquivos
    file: $uploadedFile,         // Illuminate\Http\UploadedFile
    opts: ['context' => 'repair-foto']
);

// arquivo->signedUrl() retorna URL temporária 60min
```

Quem usa: Controllers (com `$request->file('foto')`), Services (NfeService::writeArquivoXml), Jobs assíncronos (passar `$businessId` no constructor).

### B) UI Inertia upload (futuro — Sprint 2+)

Endpoint REST POST com middleware multi-tenant. Validação Laravel Form Request + chamada `ArquivosService::attach`.

```
POST /admin/arquivos/upload
  multipart/form-data:
    file: <UploadedFile>
    arquivable_type: "Modules\\Repair\\Entities\\JobSheet"
    arquivable_id: 42
    context: "repair-foto"
```

### C) CLI batch (cenário migração ou import inicial)

Use `php artisan arquivos:import-batch` (futuro — gap Sprint 2). Hoje, fazer via `php artisan tinker` com loop chamando `ArquivosService::attach` per-file.

### D) Migration backfill (apenas DEV/staging)

Idempotente, com tag `classified_by='backfill-us-arq-NNN'` pra rastreio. Padrão estabelecido em PR #398 (NFe XMLs) e PR #402 (Repair media). Em prod, rodar 1x via `php artisan migrate --force`.

## ❌ Caminhos PROIBIDOS (anti-padrões Tier 0)

| Anti-padrão | Por que proibido | Caminho correto |
|-------------|------------------|-----------------|
| `ssh user@host scp arquivo storage/app/foo.pdf` | Bypassa audit log + classify + multi-tenant | A) Producer via `ArquivosService::attach` |
| `Storage::put('foo.pdf', $contents)` direto | Sem row em `arquivos` table → arquivo invisível ao DMS | A) `ArquivosService::attach` |
| `DB::table('arquivos')->insert([...])` manual | Pula `CuradorEngine::classify` (sem bucket/sub_destination) | A) `ArquivosService::attach` |
| Job assíncrono sem `$businessId` no constructor | Quebra multi-tenant — `session()` não funciona em fila | Constructor `__construct(int $businessId)` |
| `withoutGlobalScopes()` sem comentário `SUPERADMIN` | Vaza dados cross-tenant | Comentário obrigatório `// SUPERADMIN: <razão>` |
| `Crypt::encryptString` direto em vez de Service | Sem cap 50MB + sem audit log | `VaultEncryptionService::putEncrypted` |
| Hard-delete em `ponto_marcacoes` ou `nfe_emissoes` autorizadas | Lei MTP 671/2021 + CFE — append-only | `Model::anular()` ou nunca apagar |
| Logar PII real (CPF/CNPJ cliente, telefone, email) | LGPD Art. 7º + Constituição §7 | `PiiRedactor::redact($string)` antes |

## Pipeline obrigatório (toda ingestão)

```
1. DISCOVER     → Identifica tipo (mime + extensão + filename pattern)
2. CLASSIFY     → CuradorEngine.classify() retorna bucket + sub_destination + sensitive_flags
3. DEDUPE       → md5 lookup per-business; se match → retorna existing (não duplica storage)
4. ROUTE DISK   → bucket=sensitive → disk=vault (encrypted); else → disk=arquivos
5. PERSIST      → Storage::disk(disk).putFileAs(...) + arquivos.insert(metadata)
6. AUDIT        → arquivos_audit_log.insert(action=upload, payload, business_id, user_id, timestamp)
```

`ArquivosService::attach` implementa este pipeline completo. Usar este Service garante conformidade automática.

## Roteamento canônico por tipo de documento

| Tipo | mime/extension | bucket | sub_destination | disk | encrypted | Producer |
|------|----------------|--------|-----------------|------|-----------|----------|
| **NFe XML autorizado** | application/xml `.xml` | archive | nfe-xml | arquivos | false | `Modules/NfeBrasil/Services/NfeService::writeArquivoXml` |
| **DANFE PDF** | application/pdf `.pdf` | archive | nfe-danfe | arquivos | false | `Modules/NfeBrasil/Services/DanfeService::salvar` |
| **Foto OS Repair** | image/jpeg, image/png | active | repair-foto | arquivos | false | `Modules/Repair/Http/Controllers/JobSheetController::uploadFoto` |
| **Contrato cliente PDF** | application/pdf | sensitive | contrato | **vault** | **true** | Contratos (planejado — não existe) |
| **Comprovante pagamento** | image/* ou pdf | active | comprovante-pagamento | arquivos | false | `Modules/Financeiro/...` |
| **Anexo ticket suporte** | qualquer | active | ticket-anexo | arquivos | false | Suporte (planejado — não existe) |
| **NFSe XML** | application/xml | archive | nfse-xml | arquivos | false | `Modules/NfeBrasil/Services/NfseService` |
| **Boleto-pago PDF** | application/pdf | archive | boleto-recibo | arquivos | false | `Modules/RecurringBilling/...` |
| **`.env` / `.key` / `.pfx`** | qualquer | — | — | — | — | **❌ BLOQUEAR** (CuradorEngine flag `sensitive_env_real` → throw) |

Atualizar esta tabela ao adicionar novo tipo.

## LGPD compliance — checklist por ingestão

Antes de chamar `ArquivosService::attach`:

- [ ] **Consent registrado** — titular autorizou ingestão (cláusula contrato + log evidência)
- [ ] **Tipo de documento permitido** — Tabela acima
- [ ] **business_id resolvido** — sessão web OU `$businessId` parameter (CLI/Job)
- [ ] **PII NÃO está em filename** — renomear arquivo se filename contém CPF/CNPJ raw

Após ingestão, automático:
- [x] Audit log `upload` (Service faz)
- [x] Dedupe lookup (Service faz)
- [x] Encryption-at-rest se sensitive (Service faz)
- [x] Retention 90 dias default (config)

Operações periódicas (cron):
- `arquivos:health-check --alert` (daily 06:30 BRT — schedule em `app/Console/Kernel.php`)
- `arquivos:retention-cleanup` (manual — Wagner roda mensal pós-revisão de acordo com retention configurada)

## Quando ingestão FALHAR

`ArquivosService::attach` lança `\RuntimeException` em:
- `business_id` ausente da sessão (sem multi-tenant scope) → fix: passar `--business` ou injetar manual no Job
- `disk` (vault ou arquivos) write falhou → fix: ver permissões filesystem, espaço disco
- File excede cap (50MB sensitive — ADR 0126) → fix: aguardar Sprint 2 chunked OR aumentar cap config
- CuradorEngine retorna `sensitive_env_real` → **arquivo não deve ser armazenado** (ex: `.env`, `.key`)

Logs em `storage/logs/laravel.log` channel `arquivos` com prefix `arquivos.attach.error`.

## Operações pós-ingestão

| Tarefa | Command |
|--------|---------|
| Inspecionar saúde DMS | `php artisan arquivos:health-check --business=N` |
| Audit últimas 24h | `php artisan arquivos:audit-log --business=N --hours=24` |
| Detectar duplicatas | `php artisan arquivos:dedupe-stats` |
| Hard-delete pós-retention | `php artisan arquivos:retention-cleanup --dry-run` |
| Recalcular md5/size | `php artisan arquivos:recalcular-metadata --tag=backfill-...` |
| Re-encrypt vault (rotation APP_KEY) | `php artisan arquivos:reencrypt-vault --old-key=...` |
| Export LGPD Art. 18 | `php artisan arquivos:export-zip --business=N --output=/path` |

## Histórico (origem do documento)

- [ADR 0123](../../decisions/0123-modules-arquivos-backbone.md) — Modules/Arquivos backbone DMS
- [ADR 0124](../../decisions/0124-curador-conhecimento-pipeline.md) — Skill Curador (ingestão conhecimento canon, separada)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0126](../../decisions/0126-vault-chunked-encryption-sprint-2.md) (proposed) — Chunked encryption Sprint 2
- Sessão 2026-05-10 — 29 PRs entregaram backbone completo + 7 commands operacionais + RUNBOOK validação pós-deploy

## Próximos gaps (Sprint 2+)

- [ ] `arquivos:import-batch` command pra ingestão CLI batch (caminho C atual usa `tinker`)
- [ ] UI Inertia upload `/admin/arquivos/upload` (caminho B — depende charter MWART)
- [ ] Chunked encryption pra files >50MB (ADR 0126 aceitação Wagner)
- [ ] Hook UltimatePOS upload (substituir `BusinessUtil::uploadFile` legacy → `ArquivosService::attach`)
- [ ] OCR opcional pós-ingestão pra PDFs (sub_destination=contrato → extrai texto pra Jana RAG)

---

**Owner:** Felipe (Sprint dev), Wagner (governança)
**Última atualização:** 2026-05-10 — origem sessão massiva (PR #481 export-zip + PR #482 fix audit-log)
**Refs:** ADR 0123, ADR 0124, ADR 0093, ADR 0061, RUNBOOK-validacao-pos-deploy.md
