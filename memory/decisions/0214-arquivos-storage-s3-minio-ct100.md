---
slug: 0214-arquivos-storage-s3-minio-ct100
number: 214
title: "Arquivos backbone — aceite ADR 0123 + emenda storage default S3 MinIO CT 100"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: infra
tags: [arquivos, storage, s3, minio, ct100]
amends: [0123-modules-arquivos-backbone]
related:
  - 0123-modules-arquivos-backbone
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

## Contexto

ADR 0123 (proposed 2026-05-09) definiu `Modules/Arquivos` como **DMS backbone polimórfico** pra todo anexo do oimpresso (NFe XML, foto OS, comprovante venda, mídia WhatsApp, etc). Módulo implementado em estágio avançado: schema (3 migrations), Service, Trait `HasArquivos`, CuradorEngine, VaultEncryptionService, 6 commands, 6+ Pest test waves, backfill NFe XML.

**Status original do storage default no ADR 0123:**
- Fase MVP: `local-ct100` (volume mounted `/var/lib/oimpresso-arquivos/`)
- Fase 3: S3-compatible (Backblaze B2 / Wasabi ~R$ [redacted Tier 0]/TB/mês)

Wagner pediu 2026-05-28 (incident mídia WhatsApp, sessão 14 PRs): "foi feito servidor S3 para isso? pesquise". Após audit:

- ✅ MinIO CT 100 já roda (container `minio-langfuse`, dedicado pro Langfuse hoje)
- ✅ Laravel `config/filesystems.php` `s3` driver declarado (espera env vars)
- ❌ Sem ADR aceito sobre antecipar Fase 3
- ❌ Sem bucket dedicado pra arquivos oimpresso

Wagner aprovou 2026-05-28: antecipar Fase 3 reusando MinIO CT 100 existente. Custo adicional zero. Sem dependência S3 externa (Backblaze/Wasabi/AWS).

## Decisão

**Parte 1 — Aceite ADR 0123:** status `proposed` → `accepted`. Os 8 princípios duros + schema + plano de adoção (Sprints 1-4) ficam canon.

**Parte 2 — Emenda ao princípio 3 do ADR 0123 (Storage abstraído):**

Substituir o defaultsetting do disk `arquivos`:

| Era (ADR 0123) | Vira (ADR 0214) |
|---|---|
| `local-ct100` volume CT 100 (`/var/lib/oimpresso-arquivos/`) | **`arquivos-minio`** — bucket dedicado MinIO CT 100 (S3-compat) |
| Fase 3 future: S3 Backblaze/Wasabi | Fase 4 future opcional: S3 externo se MinIO CT 100 saturar |

### Por que MinIO CT 100 agora?

✅ **Custo zero adicional** — container `minio-langfuse` já em prod CT 100. Adicionar bucket `oimpresso-arquivos` é setup 5min.
✅ **Resolve Hostinger LiteSpeed 403** — browser baixa direto do MinIO via Traefik (vs Hostinger `/storage/*` bloqueado).
✅ **Signed URLs com TTL** — `Storage::disk('arquivos-minio')->temporaryUrl($path, $expires)` nativo.
✅ **API S3-compat real** — swap pra Backblaze/Wasabi/AWS futuro = só trocar 5 env vars (`AWS_ENDPOINT`).
✅ **Performance** — Traefik HTTPS direto, sem proxy PHP intermediário.
✅ **Tier 0 limpo** — bucket-per-business OU prefix `biz=N/` (mais simples manutenção).
✅ **Aproveita infra existente** — TLS Let's Encrypt Traefik, Tailscale interno, backup volume CT 100.

### Por que NÃO Backblaze/Wasabi externos (ADR 0123 Fase 3 original)?

❌ **Custo egress** — ainda existe ($0.01/GB Backblaze, sem free tier);
❌ **Latência rede pública** vs MinIO interno;
❌ **Auth/key management externo** — mais 1 secret pra rotacionar;
❌ **Dependência terceiro** — incoerente com princípio "tudo sob controle Wagner".

### Configuração MinIO CT 100

```yaml
# CT 100 docker-compose addition (bucket dedicado dentro do mesmo MinIO):
# Bucket name: oimpresso-arquivos
# Access policy: private (signed URLs only)
# Versioning: disabled (Arquivos table tem soft-delete)
# Lifecycle: ADR 0123 retention_days respeita per-row

# Path canon (paridade ADR 0123 layout original):
#   oimpresso-arquivos/biz=<businessId>/YYYY/MM/<md5_prefix>.<ext>
#   oimpresso-arquivos-vault/biz=<businessId>/secrets/...  (encrypted bucket)
```

### Configuração Laravel

```php
// config/filesystems.php — disk 'arquivos-minio'
'arquivos-minio' => [
    'driver' => 's3',
    'key' => env('MINIO_ACCESS_KEY'),
    'secret' => env('MINIO_SECRET_KEY'),
    'region' => 'us-east-1',  // MinIO default
    'bucket' => env('MINIO_BUCKET_ARQUIVOS', 'oimpresso-arquivos'),
    'endpoint' => env('MINIO_ENDPOINT'),  // https://minio.oimpresso.com (Traefik)
    'use_path_style_endpoint' => true,    // MinIO requer path-style
    'throw' => false,
],

'arquivos-vault' => [
    // Mesma config mas bucket separado encrypted
    'bucket' => env('MINIO_BUCKET_VAULT', 'oimpresso-arquivos-vault'),
    // ... resto idem
],
```

### .env adições (Hostinger app)

```bash
MINIO_ENDPOINT=https://minio-oimpresso.ct100.internal  # ou pública via Traefik
MINIO_ACCESS_KEY=<gerar via mc admin user add>
MINIO_SECRET_KEY=<gerar via mc admin user add>
MINIO_BUCKET_ARQUIVOS=oimpresso-arquivos
MINIO_BUCKET_VAULT=oimpresso-arquivos-vault
```

## Plano de adoção pós-aceite

### Sprint 0 — Setup MinIO + Laravel disk (~1h IA-pair)

- US-ARQ-S3-01: Criar bucket `oimpresso-arquivos` + `oimpresso-arquivos-vault` no MinIO CT 100 (`mc mb`)
- US-ARQ-S3-02: Criar user MinIO `oimpresso-app` com policy `arquivos-rw` (limit aos 2 buckets)
- US-ARQ-S3-03: Adicionar env vars Hostinger + config/filesystems.php disks
- US-ARQ-S3-04: Smoke test `Storage::disk('arquivos-minio')->put('test.txt', 'hi'); ->get('test.txt')`
- US-ARQ-S3-05: ArquivosService default disk: `local-ct100` → `arquivos-minio` (config swap)

### Sprint 1 — WhatsApp inbox adoção (~2h IA-pair)

- US-ARQ-WA-01: Modules\Whatsapp\Entities\Message implementa `HasArquivos` trait
- US-ARQ-WA-02: `DownloadMediaJob` chama `$message->attachArquivo($bytes, ['context'=>'whatsapp-media-inbound'])` em vez de `Storage::disk('public')->put` direto
- US-ARQ-WA-03: `SendMediaJob` lê `$message->arquivos->first()` pra signedUrl outbound
- US-ARQ-WA-04: `msgToUiArray` retorna `route('atendimento.midia.show')` continua funcionando como fallback legacy, mas se `$message->arquivos->isNotEmpty()` retorna signed URL MinIO direto (browser → MinIO direto)
- US-ARQ-WA-05: Backfill 11 mídias já success (sessão 2026-05-28) — `Storage::copy('public', 'arquivos-minio')` + insert linha `arquivos` polymorphic
- US-ARQ-WA-06: Pest test multi-tenant + signed URL TTL + dedupe

### Sprint 2+ — Outros módulos opt-in (mesma ordem ADR 0123 + nova priorização)

| Sprint | Módulo | Model | Anexos típicos | Esforço |
|---|---|---|---|---|
| 2 | **Sells** | `Transaction` | comprovante venda, foto produto, OS impressa | 2h |
| 3 | **Financeiro** | `FinTitulo` + `FinTituloBaixa` | boleto PDF, comprovante PIX/depósito | 3h |
| 4 | **Crm / Contacts** | `Contact` | RG, CPF doc, contrato | 2h |
| 5 | **Producao / OficinaAuto** | `ServiceOrder`, `Job` | foto OS, laudo, peça | 3h |
| 6 | **NfeBrasil** | `NfeXml` | XML + DANFE PDF (backfill já scaffolded) | 4h |
| 7 | **Repair** | `RepairJob` | foto antes/depois, vídeo diagnóstico | 2h |
| 8 | **Cms** | `Voucher`, `Page` | imagem produto, banner | 1h |

**Total Sprint 0+1: 3h IA-pair + Wagner aprovação por sprint subsequente.**

## Não-goals (preservam ADR 0123)

- ❌ NÃO substitui MemCofre — Memcofre = anotações/decisões; Arquivos = binary files
- ❌ NÃO força migração compulsória — adoção opt-in por módulo (igual ADR 0123)
- ❌ NÃO usa S3 externo (AWS/Backblaze/Wasabi) — MinIO CT 100 self-hosted
- ❌ NÃO indexa full-text MVP — Meilisearch full-text fica Fase 4
- ❌ NÃO faz OCR MVP — só armazena binário
- ❌ NÃO permite cliente externo upload — relay via app principal Hostinger (paridade ADR 0123)

## Consequências

✅ **Boas:**
- Storage canônico cross-módulo via S3-API (Salesforce ContentDocument-like)
- Hostinger LiteSpeed 403 mídia resolvido sem Controller intermediário (browser → MinIO direto)
- Signed URLs com TTL nativa (segurança LGPD)
- Backup MinIO CT 100 via `mc mirror` simples
- Zero custo adicional vs CT 100 atual
- Swap pra S3 externo futuro = 5 env vars

⚠️ **Tradeoffs:**
- CT 100 vira ponto crítico (já era — Centrifugo, MCP, MinIO) — fallback: backup nightly tarball pra Hostinger
- Bandwidth CT 100 ↔ Hostinger app web pra reads internos (preview pra atendente) — mitigação: signed URL serve direto browser, app só renderiza link
- Migração 11 mídias já success → MinIO (Sprint 1 — script automatizado)
- 10k+ mídias antigas pending continuam órfãs (não migrar — deletáveis após DoD-v1 confirmar nova base sólida)

## Validação Sprint 0

- ✅ MinIO CT 100 responde `mc ls minio-langfuse/oimpresso-arquivos/` (bucket criado)
- ✅ `Storage::disk('arquivos-minio')->put('hello.txt', 'world')` retorna true
- ✅ `Storage::disk('arquivos-minio')->get('hello.txt')` retorna `'world'`
- ✅ `Storage::disk('arquivos-minio')->temporaryUrl('hello.txt', now()->addMinutes(15))` retorna URL signed válida
- ✅ Browser navega URL signed → HTTP 200 + content
- ✅ Sem URL signed → HTTP 403 (private bucket)

## Validação Sprint 1 (DoD-v1 mídia WhatsApp)

- ✅ Wagner manda imagem pro celular Jana → DownloadMediaJob persiste em `arquivos` table + bucket MinIO
- ✅ `Message::find($id)->arquivos->first()->signedUrl()` retorna URL MinIO Traefik
- ✅ UI Conv Thread renderiza `<img src="https://minio.oimpresso.com/...">` direto (sem proxy Controller)
- ✅ Tier 0: user biz=1 não acessa signed URL biz=4 (path prefix valida)
- ✅ Audit log preenche action=upload + signed_url_issued
- ✅ Pest test: 2 businesses cross-isolation

## Notas

- ADR 0214 amenda ADR 0123 (não substitui — 0123 fica `accepted` + `superseded_by_partial: [0214]`)
- Sprint 0 começa imediatamente (Wagner aprovou)
- Sprint 1 (WhatsApp) entrega `awaiting-smoke` até Wagner confirmar imagem real chega via MinIO signed URL
- Próximas sprints (Sells, Financeiro, etc) precisam aprovação Wagner per-sprint conforme skill `incident-done-checklist` DoD-v1
