---
id: requisitos-whatsapp-auditoria-midia-outbound-2026-05-28
---

# AUDITORIA — Mídia + Outbound web UI Inbox (Modules/Whatsapp)

**Data:** 2026-05-28
**Auditor:** Claude (sub-agent audit-research)
**Driver atual produção biz=1:** whatsmeow (Go daemon WuzAPI) — [ADR 0204](../../decisions/0204-whatsmeow-driver-substituto-baileys.md), substituiu Baileys ADR 0202 em 2026-05-27.
**Tema:** Mídia (imagem/vídeo/áudio/documento/sticker) inbound+outbound + mensagens enviadas pela web UI Inbox (text/template/interactive).
**Status:** AUDITORIA — produto não-pronto; bugs P0 confirmados via leitura de código.
**Sessão de origem:** Wagner cobrou "imagens videos midias mensagens enviadas pelo web ja fez a pesquisa do que precisa ter e o que testar sabe testar?" (5 bugs Centrifugo já fechados hoje).

---

## 1. Sumário executivo (≤300 palavras)

**Nota de maturidade: ~48% (frágil-funcional).**

Composição (peso × % cada):
- **Outbound texto (peso 20%)**: 60% — funciona Baileys legacy, NÃO funciona whatsmeow (gate de tipo bloqueia em `InboxController::send` L848).
- **Outbound mídia (peso 25%)**: 40% — pipeline existe (`SendMediaJob`) mas SÓ Baileys; whatsmeow nunca chega no `WhatsmeowDriver::sendMedia` via Job; UX preview-then-send OK mas sem drag-drop+paste no novo composer Caixa Unificada V4.
- **Inbound mídia (peso 25%)**: 35% — Guardião 6-camadas robusto pra Baileys/Z-API/Meta, **mas para whatsmeow está QUEBRADO**: `ProcessIncomingWebhookJob::extractFromWhatsmeow` NÃO extrai `media_mime`/`mediaKey`/`url` do payload → Observer Camada 1 nunca dispara `DownloadMediaJob` → toda mídia inbound whatsmeow biz=1 fica como `[imagem]`/`[áudio]` texto sem download.
- **Realtime + UI (peso 15%)**: 65% — bug Centrifugo `omnichannel:business:{id}` corrigido hoje 2026-05-28; lightbox + thumb existem; multi-tab OK.
- **Segurança/Tier 0 (peso 15%)**: 80% — MIME whitelist sólido (SVG/HTML bloqueados), business_id em paths, multi-tenant isolado em testes; mas signed URL TTL não enforce em disk `public` (`Storage::url()` sem expiração).

**Recomendação: EVOLUIR (urgente, NÃO consolidar).**

Top 5 gaps P0:
1. **whatsmeow inbound mídia QUEBRADO** — extractor `ProcessIncomingWebhookJob::extractFromWhatsmeow` L325-369 nunca preenche `media_mime`/`payload.message.imageMessage` no INSERT (linhas 237-249 inserem só `type`+`body`+`payload` raw outer event). Resultado: ZERO downloads de mídia inbound desde cutover 2026-05-27.
2. **whatsmeow outbound mídia QUEBRADO** — `SendMediaJob` L96-102 + `InboxController::sendMedia` L1319-1325 hardcoded `TYPE_WHATSAPP_BAILEYS`; mídia outbound em channel whatsmeow falha com "só implementado pra Baileys".
3. **whatsmeow outbound texto QUEBRADO** — `InboxController::send` L848 mesmo gate hardcoded; web UI envia → falha. Wagner provavelmente NÃO testou outbound em whatsmeow pós-cutover.
4. **media disk `public` sem TTL** — `resolveMediaUrl` cai em `Storage::url()` quando `temporaryUrl` falha (config canônica default = `public` → SEMPRE cai); URL não expira → vazamento se path leak via console/devtools, embora UUID v4 ~122 bits de entropia mitigue força-bruta.
5. **Sem paste clipboard + drag-drop ausente no Composer V4 produção** — `ComposerV4.tsx` (rota live `/atendimento/caixa-unificada`) NÃO tem `onDrop`/`onPaste`; só `ConversationThread.tsx` legacy tem. UX 2026 (Slack/Notion/Intercom) tem ambos.

---

## 2. Estado-da-arte 2026 — Best-of-class

### 2.1 Chatwoot OSS — auto-host equivalente do nosso target

| Aspecto | Chatwoot 4.x (2026) | Fonte |
|---|---|---|
| Storage | Rails ActiveStorage (local/S3/GCS/Azure) | [DeepWiki §3.6](https://deepwiki.com/chatwoot/chatwoot/3.6-attachments-and-file-storage) |
| Thumb | Gerada on-demand `file_thumb_url` | DeepWiki |
| Direct-upload | Upload cliente→S3 (bypass Rails app) | DeepWiki |
| Multi-attachment | Sim, array de medias por message | DeepWiki |
| MIME validation | Server-side enforcement | [Issue 12757](https://github.com/chatwoot/chatwoot/issues/12757) |
| Outbound media | Direct media URL → Cloud API; bug ASN-rate limit Feb/2026 ([Issue 13540](https://github.com/chatwoot/chatwoot/issues/13540)) |

### 2.2 Front (frontapp.com) — UX inbox profissional

- Drag-drop como uma das 3 formas de anexar ([help.front.com](https://help.front.com/en/categories/191-send-messages))
- Limites por canal (Gmail 25MB, O365 10MB)
- Workflow builder drag-drop

### 2.3 Intercom Messenger 2026

- Drag-drop com overlay "drop files or images here" ([Intercom Help](https://www.intercom.com/help/en/articles/12130647-sharing-files-and-images-in-the-messenger))
- 100MB por attachment (Messenger), 20MB email
- **Optimistic upload**: "attachments start uploading as soon as you insert them, even before you send. You'll see progress, system handles retries"
- GIF picker nativo
- Bloqueio configurável (admin desliga GIFs por brand)

### 2.4 Twilio Conversations API 2026

- MIME validation server-side (GET+HEAD na `MediaUrl` checando Content-Type) ([Twilio docs](https://www.twilio.com/docs/messaging/api/media-resource))
- Signed URL **300s TTL** (5 min) — agressivo
- Multi-attachment array
- Rejeita se `Content-Type` header diverge do MIME esperado

### 2.5 Take Blip (líder BR PME)

- Enterprise pricing on-request (não público) ([Blip pricing](https://www.blip.ai/en/pricing/))
- Hybrid bot+human BLiP Desk com context transfer
- Conecta WhatsApp, Instagram, Messenger, Telegram, Teams, RCS, Apple Business, SMS — **omnichannel real**
- Captado $70M 2025 — bem capitalizado

### 2.6 WhatsApp Cloud API — limites canônicos

- Vídeo: 16MB ([dmly.io](https://dmly.io/whatsapp-file-size-guide/))
- Documentos: 100MB
- Áudio: 16MB
- Imagens: 5MB (não confirmado em search, mas docs Meta apontam)
- Upload via `/v18.0/PHONE_ID/media` → retorna `media_id` → reference em send

### 2.7 WuzAPI endpoints (asternic/wuzapi)

- `/chat/send/image`, `/chat/send/video`, `/chat/send/audio`, `/chat/send/document` ([API.md](https://github.com/asternic/wuzapi/blob/main/API.md))
- Body: URL OU base64 data-URL (`data:image/jpeg;base64,...`)
- Campos: `Phone`, `Image`/`Video`/`Audio`/`Document`, `Caption`, `MimeType`, `PTT` (voice), `Duration`, `Id`
- Flag pra skip download interno (90% perf boost)

### 2.8 whatsmeow Go (tulir/whatsmeow)

- `DownloadAny` loops downloadable parts ([pkg.go.dev](https://pkg.go.dev/go.mau.fi/whatsmeow))
- `DownloadMediaWithPath(directPath, encFileHash, fileHash, mediaKey, fileLength, mediaType, mmsType)` — decrypt manual
- ImageMessage proto: `Url, Mimetype, Caption, FileSha256, FileEncSha256, FileLength, MediaKey, DirectPath` ([Discussion 260](https://github.com/tulir/whatsmeow/discussions/260))
- AudioMessage proto: `Url, Ptt (boolean), DirectPath, Mimetype, FileLength, FileSha256, FileEncSha256, MediaKey`

### 2.9 Best-practice UX upload 2026

- **Optimistic preview ANTES do upload** (Intercom, Notion, Slack)
- **Progress bar + cancel** (Slack 10 files drag, Notion inline)
- **Drag-drop em qualquer lugar do composer** (não só botão) ([Intercom](https://www.intercom.com/help/en/articles/12130647-sharing-files-and-images-in-the-messenger))
- **Paste clipboard print direto** (Linear, Notion, Slack — universal 2026)
- **Lightbox modal** ao click thumb (zoom/download/share)

---

## 3. Introspecção código oimpresso

### 3.1 Camada de driver — `Modules/Whatsapp/Services/Drivers/`

| Arquivo | Linhas | Suporta mídia? | Suporta whatsmeow? |
|---|---|---|---|
| `DriverInterface.php` | 6 métodos canon (sendTemplate/sendFreeform/sendMedia/fetchMessageStatus/ping/sendInteractive) | sim contrato | sim contrato |
| `WhatsmeowDriver.php` | 464 | sim L105-154 — endpoints `/chat/send/image|audio|document` | **driver existe mas Job não chama** |
| `ZapiDriver.php` | L81 sendMedia | sim | n/a |
| `MetaCloudDriver.php` | L103-110 sendMedia + Otel span | sim | n/a |
| `NullDriver.php` | L43 stub | n/a | n/a |
| `ChannelDriverFactory.php` | resolve por `channel.type` | sim | sim |

### 3.2 Outbound texto — `InboxController::send` L744-901

- Gate L848: `if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS)` → falha **TODOS** outros tipos com "só implementado pra Baileys nesta fase". 🚨 BUG P0 — whatsmeow channel produzido pelo ADR 0204 cai aqui.
- Quick-path HTTP direto pro daemon Baileys L857-869 (não usa `BaileysDriver` legacy). Hardcoded URL `{daemonUrl}/instances/{instanceId}/text`.
- Persiste `Message(status='queued')` ANTES do HTTP (defesa-em-profundidade OK).
- `withoutVerifying()` L864 — FIXME cert LE pendente (US-WA-058).
- Auto-prefix `*Nome:*` L795/L1779 — só freeform humano (skip note/template).

### 3.3 Outbound mídia — `InboxController::sendMedia` L1234-1330

- Mesmo gate L1319: `TYPE_WHATSAPP_BAILEYS` exclusivo. 🚨 BUG P0.
- MIME whitelist via `Message::MEDIA_MIME_WHITELIST` (sólido: SVG/HTML/exe bloqueados).
- Size max via `Message::MEDIA_MAX_SIZE_BYTES = 16MB` L179.
- Path multi-tenant: `whatsapp/{business_id}/{Y-m}/{uuid}.{ext}` L1280-1287.
- Persiste Message + dispatch `SendMediaJob` L1327. Job re-checa gate L96-102.
- Frontend `ComposerV4.tsx` hardcoded `25MB` L143 — **mismatch com backend 16MB** 🚨.
- Frontend ACCEPT_MIME L35 **NÃO inclui** `application/msword`, `xlsx`, `pptx`, `txt`, `csv` (que backend aceita) — atendente não consegue anexar Word/Excel pela UI.

### 3.4 `Modules/Whatsapp/Jobs/SendMediaJob.php`

- 168 linhas. `$tries=3` backoff [60s, 300s, 900s].
- Multi-tenant `withoutGlobalScope ScopeByBusiness` + filtro explícito ✅.
- Gate `TYPE_WHATSAPP_BAILEYS` L96-102 🚨.
- `Storage::disk('public')->url($message->media_url)` L112 — daemon CT 100 precisa URL absoluta acessível. Em prod Hostinger `APP_URL` precisa estar set.
- Otel span `whatsapp.message.send_media` L54.
- Payload daemon: `to, media_url, mimetype (não `mime`!), filename, caption, type`. Schema Zod daemon-node `sendMediaBody` aceita `mimetype` ✅ (L29 schemas.js).

### 3.5 Inbound — extração webhook + Observer + Job

**Pipeline esperado:**
```
Daemon webhook → controller → ProcessIncomingWebhookJob → DB insert messages
                                          ↓
                                 MessageObserver::created
                                          ↓
                              maybeDispatchMediaDownload (Camada 1)
                                          ↓
                                 DownloadMediaJob (Camada 3)
```

**Reality whatsmeow:**

- `WhatsmeowWebhookController::handle` L48-174 OK — unwrap WuzAPI envelope (`jsonData` string nested JSON L62-67), drop status@broadcast/groups L104-125, dispatch Job.
- `ProcessIncomingWebhookJob::extractFromWhatsmeow` L325-369 — **EXTRAI só** `provider_message_id`, `external_id`, `from`, `body` (já decidido por type), `type`, `push_name`, `raw`. **NÃO extrai** `media_mime`, `media_url`, `mediaKey`, `fileLength`. Body para imagem é `'[imagem]'`/`'[áudio]'` (L349-353).
- `upsertMessageWhatsmeow` L144-305 — INSERT em L237-249 só inclui `business_id, conversation_id, direction, provider, provider_message_id, type, body, payload (raw FULL outer event), status, timestamps`. **`media_mime` NUNCA preenchido**.
- `MessageObserver::maybeDispatchMediaDownload` L81-119 — **gate L87** `if ($message->media_mime === null) return;` → **TODOS whatsmeow inbound media são pulados**.
- `DownloadMediaJob::fetchViaDaemonDecrypt` L270-315 → endpoint `/media/decrypt-url` no daemon CT 100. Daemon NODE `dist/http/schemas.js` L31-38 aceita esse endpoint — então **o caminho do daemon node está OK**, mas atendente nunca dispara o Job porque media_mime nunca chega.

### 3.6 `MessageObserver.php` — Camada 1 Guardião

- Auto-dispatch correto E1 (created): texto não dispara, mídia com `media_mime` set + `media_url` null dispara, `failed_permanent` skip, double-dispatch idempotente.
- `syncConversationPreview` L133-146 — denormaliza `last_message_preview` + `last_message_direction` (US-WA-072). ✅.

### 3.7 Frontend — `ComposerV4.tsx` (Caixa Unificada V4 — PRODUÇÃO)

- 399 linhas.
- File input + button paperclip ✅.
- MicRecorder voice PTT 5min limit ✅ (`MicRecorder.tsx` 274 linhas, ogg/opus preferido com webm fallback).
- Preview file selected acima do composer ✅.
- **SEM `onDrop`/`onDragOver`** — drag-drop ausente 🚨.
- **SEM `onPaste`** — paste clipboard print ausente 🚨.
- Cap client-side 25MB ≠ backend 16MB 🚨.
- ACCEPT_MIME L35 incompleto (sem doc/xlsx/etc) 🚨.

### 3.8 Frontend legacy `ConversationThread.tsx` (não-prod /atendimento/inbox)

- L957-964: drag-drop EXISTE (`onDragOver` + `onDrop` + `queueMediaFiles`). Funcionalidade não portada pro ComposerV4 🚨.

### 3.9 Config — `Modules/Whatsapp/Config/config.php` L251-257

```php
'media' => [
    'disk' => env('WHATSAPP_MEDIA_DISK', 'public'),
    'max_size_bytes' => 16MB,
    'signed_url_ttl_seconds' => 86400,  // 24h — só vale em S3/GCS
],
```

`public` disk = `Storage::url()` retorna URL pública sem TTL → entropia do UUID é a única defesa (~122 bits — OK pra brute force, mas vazamento via clipboard/inspector → leak permanente).

### 3.10 Tests cobertura

- `MediaMessageTest.php` — 10 testes: multi-tenant, MIME whitelist, size limit, Whisper mock, is_internal_note + send_media gate, DownloadMediaJob Http::fake, SVG block, rate limit Whisper. ✅
- `GuardiaoMidiaTest.php` — 17+ testes: Camadas 1/3/4/5/6, multi-tenant Tier 0, Backfill --since/--force-failed/--dry-run, HealthCheck. ✅ ROBUSTO.
- **ZERO testes whatsmeow inbound media extract** 🚨
- **ZERO testes whatsmeow outbound (texto OU mídia)** 🚨
- `MediaInboundProcessedTest.php` existe mas só cobre Z-API/Meta (não vi whatsmeow).

---

## 4. Catálogo TOP 30 erros possíveis

### A. Outbound texto (5 cenários)

| # | Cenário | Sintoma | Detecção | Fix | Pest test 1-line |
|---|---|---|---|---|---|
| A1 | Composer envia → 500 erro genérico | Toast "Erro ao enviar" sem detalhe | `storage/logs/laravel.log` ALERT `atendimento.inbox.send.exception` | Try/catch no L890 já existe; melhorar UI error tooltip | `it('500 daemon → status=failed + UI back errors')` |
| A2 | **whatsmeow channel → "só Baileys"** 🚨 P0 | Toast "Envio só disponível pra canais Baileys nesta fase" | `Message.failed_reason` LIKE 'Envio só implementado pra Baileys%' | InboxController L848 substituir hardcoded por `ChannelDriverFactory::for($channel)->sendFreeform(...)` | `it('whatsmeow channel → send dispatcha WhatsmeowDriver sendFreeform')` |
| A3 | Job falha + retry sem idempotência → msg duplicada | 2 bubbles outbound idênticas | `SELECT COUNT(*) FROM messages WHERE provider_message_id='X'` >1 | Schema UNIQUE em `provider_message_id` já existe (L139 cross-tenant); investigar se driver retorna o mesmo ID em retry | `it('retry SendWhatsappMessageJob não duplica row')` |
| A4 | `provider_message_id` null retornado → não persiste outbound id | UI exibe bubble mas reply do cliente não correlaciona | `messages WHERE direction='outbound' AND provider_message_id IS NULL` | InboxController L886 fallback `Str::uuid()` já existe quando daemon não retorna id; WhatsmeowDriver L412-414 idem | `it('daemon sem Id → message.provider_message_id != null')` |
| A5 | Inbound do mesmo número que recebeu nosso outbound bate UNIQUE | `Duplicate entry '1-11-' for key 'conv_biz_ch_ext_uniq'` | Sentry/Telegram alert | Já fixado 2026-05-27 (drop empty external_id em ProcessIncomingWebhookJob L198-206) | `it('inbound vazio external_id → drop sem insert')` ✅ |

### B. Outbound mídia (8 cenários)

| # | Cenário | Sintoma | Detecção | Fix | Pest test |
|---|---|---|---|---|---|
| B1 | Upload > 16MB rejeita | "max:16384" Laravel ValidationException | response 422 backend; UI mostra error inline ✅ | OK — mas frontend cap 25MB > backend 16MB; alinhar pra evitar UX "upload travou no final" | `it('upload 20MB → 422 backend + UI error')` |
| B2 | MIME .exe/.heic/.svg | `Tipo de arquivo não permitido: image/svg+xml` | `Message::MEDIA_MIME_WHITELIST` block ✅ | OK | `it('SVG upload → 422 + whitelist enforce')` ✅ existe |
| B3 | Base64 vs binary upload daemon | daemon retorna 400 schema invalido | `SELECT failed_reason WHERE provider='whatsapp_whatsmeow'` | SendMediaJob L120 manda `media_url` (URL string) — WuzAPI aceita URL ✅, mas em dev Hostinger acessar storage/public requer APP_URL set | `it('SendMediaJob payload tem media_url + mimetype + type')` |
| B4 | Preview-then-send: cancelar antes de enviar | Arquivo continua selecionado | `clearPendingFile` L152 ✅ | OK | `it('Composer X button limpa pendingFile')` |
| B5 | Timeout HTTP daemon → frontend trava | UI permanente "Enviando…" | timeout 30s no Job L117; controller send (L865) tem 15s | OK no Job, mas UI não tem timeout próprio; depende do Inertia response | `it('daemon timeout → job retry + Message.status=failed')` |
| B6 | Path traversal nome arquivo | `../../etc/passwd` em filename | `media_filename` é só metadata, path real é UUID — não usa filename do user | OK por design L1280-1287 | `it('filename ../../../etc/passwd → path UUID limpo')` |
| B7 | Vírus scan ClamAV? | Malware doc passa | **NÃO TEM** scan 🚨 | Adicionar pre-upload check via ClamAV daemon ou skip por enquanto (low risk B2B) | `it('clamav scan integration (skip if not configured)')` |
| B8 | Storage cheio (Hostinger 200GB) → fail loud? | `disk_full` exception | Storage::put L1287 lança RuntimeException → Laravel handler 500 | Adicionar health-check storage utilization (% disk) + retention policy LGPD já existe L312 | `it('disk full → 500 graceful + alert log')` |

### C. Inbound mídia (7 cenários)

| # | Cenário | Sintoma | Detecção | Fix | Pest test |
|---|---|---|---|---|---|
| C1 | **mediaKey decrypt fail → mídia corrompida** 🚨 P0 | UI bubble "[imagem]" sem thumb; download 0 bytes | `media_download_status='failed_permanent'` + `failed_reason LIKE 'Daemon decrypt%'` | Daemon CT 100 endpoint `/media/decrypt-url` foi deployado 2026-05-12 (Agent J) — verificar se whatsmeow daemon (NODE) tem esse endpoint ou se substituiu por whatsmeow Go DownloadAny | `it('Baileys mediaKey → daemon decrypt-url retorna bytes')` ✅ existe |
| C2 | Download timeout daemon → retry hourly | UI mostra spinner indefinido | `media_download_status='downloading'` por > 1h | Camada 6 health-check `whatsapp_media_pending_1h` alerta ✅ | `it('downloading > 1h → health-check alerta')` ✅ |
| C3 | Storage 404 quando user clica thumb | UI quebra (icone X ou 404 page) | Browser DevTools network 404 | `resolveMediaUrl` L699-713 try-catch + fallback `Storage::url()` ✅ mas se arquivo foi purgado por retention LGPD, vai 404. Adicionar tombstone | `it('media purgada → resolveMediaUrl retorna placeholder')` |
| C4 | Thumbnail not generated (large video) | Lista mostra ícone genérico | `media_thumbnail_url IS NULL` + `type='video'` | `generateThumbnail` L506-553 só roda pra type='image'; video thumb pode usar ffmpeg ou skip | `it('video sem thumb → UI ícone vídeo placeholder')` |
| C5 | **Audio ogg/opus play em Safari iOS quebra** 🚨 | `<audio>` HTML5 fail iOS | manual test Safari real device | converter pra mp3/m4a server-side OR usar lib JS Opus decoder. Hoje Safari iOS 17+ suporta opus mas com flag — verificar | `it('audio inbound serve com Content-Type audio/ogg + Range header')` |
| C6 | Document filename UTF-8 acento | `latência.pdf` quebra path | DownloadMediaJob L160 usa UUID então OK, mas `media_filename` salva ClientOriginalName que pode ter acentos | Normalizar com `Str::ascii()` ou manter mb_convert_encoding | `it('filename "memória.pdf" → media_filename preserva utf-8')` |
| C7 | Sticker WebP exibe? | UI bubble vazia ou broken img | `image/webp` no whitelist L150 ✅; Observer L130 inclui 'sticker' em MEDIA_TYPES ✅ | OK em teoria; testar stickers animados (WebP animado) | `it('sticker WebP → media_url set + thumb gerado')` |

### D. Realtime mídia (5 cenários)

| # | Cenário | Sintoma | Detecção | Fix | Pest test |
|---|---|---|---|---|---|
| D1 | Centrifugo publish com payload media_url | UI não atualiza ao enviar mídia | DevTools WS frames; `centrifugo.publish.success` log | Fix 2026-05-28 canal `omnichannel:business:{id}` + type `message.received` ✅ (já merged); validar mídia outbound publica idem | `it('SendMediaJob success → publishes omnichannel message.sent')` |
| D2 | UI lightbox modal abre direto do click thumb | Modal não abre OU não fecha (ESC) | manual UI test | `MediaFullscreenModal.tsx` existe — verificar wire-up em ConversationThreadV4 | `it('click thumb → lightbox modal open')` (frontend Vitest) |
| D3 | Progress bar download em real-time | spinner sem progresso | sem progress no DownloadMediaJob (não emite progress) | Best-effort: emit Centrifugo events `media.downloading:X%` (low priority) | `it('Storage progress callback emite percent')` |
| D4 | Múltiplos atendentes vendo mesma conv: receivedOn one closes lightbox quebra outro | manual test 2 tabs | Centrifugo channel per business (não per user) — fechamento local não propaga ✅ | OK por design | `it('Centrifugo evento não fecha lightbox de outro user')` |
| D5 | Multi-tab: msg recebida em tab1 atualiza tab2 | DevTools WS frames | Centrifugo channel `omnichannel:business:1` subscribed em ambas tabs | OK por design (canal compartilhado business-level) | `it('multi-tab WS subscribe mesma channel = ambas recebem')` |

### E. UX (5 cenários)

| # | Cenário | Sintoma | Detecção | Fix | Pest test |
|---|---|---|---|---|---|
| E1 | **Drag-drop em qualquer lugar do composer** 🚨 P1 | Atendente arrasta arquivo no inbox V4 → nada acontece | manual test | Adicionar `onDragOver`/`onDrop` em `ComposerV4.tsx` espelhando `ConversationThread.tsx` L957-964 | `it('ComposerV4 dropzone aceita File via dataTransfer')` (Vitest) |
| E2 | **Paste clipboard print direto** 🚨 P1 | Atendente Ctrl+V print → nada | manual test | Adicionar `onPaste` listener no textarea, ler `e.clipboardData.items` filter `image/*`, criar File → queueMediaFiles | `it('paste clipboard image → pendingFile set')` |
| E3 | Optimistic preview antes de upload completar | Preview demora a aparecer | `pendingFile` set imediato ao onChange ✅ | OK | `it('onFilePicked → pendingFile state imediato')` |
| E4 | Resend após fail (transitory) | Sem botão "Reenviar" | UI não tem retry manual | Adicionar action em bubble com `status='failed'` que re-dispatch SendMediaJob | `it('failed message → POST /retry dispatcha novo Job')` |
| E5 | Aviso 24h Meta window quando responder | Atendente envia freeform a cliente fora janela → fail Meta 131047 | `convToThreadArray` L603-605 calcula `within_24h_window` ✅ | OK mas só mostra texto subtle — destacar com modal-warning antes de enviar | `it('within_24h=false + freeform → UI confirmation dialog')` |

---

## 5. Roadmap 15 Pest tests (mínimos viáveis)

Prioridade: P0 fix paths quebrados, P1 cobertura gaps, P2 polimento UX.

| # | Test | Arquivo destino | Prioridade |
|---|---|---|---|
| T1 | `it('whatsmeow channel → InboxController::send dispatcha WhatsmeowDriver::sendFreeform')` | `Modules/Whatsapp/Tests/Feature/WhatsmeowOutboundTextTest.php` | **P0** |
| T2 | `it('whatsmeow channel → InboxController::sendMedia roteia SendMediaJob via WhatsmeowDriver::sendMedia (não Baileys)')` | `Modules/Whatsapp/Tests/Feature/WhatsmeowOutboundMediaTest.php` | **P0** |
| T3 | `it('ProcessIncomingWebhookJob::extractFromWhatsmeow extrai media_mime + media_url do imageMessage proto')` | `Modules/Whatsapp/Tests/Feature/WhatsmeowMediaExtractTest.php` | **P0** |
| T4 | `it('whatsmeow inbound audioMessage → INSERT messages com media_mime=audio/ogg → Observer dispatcha DownloadMediaJob')` | idem ↑ | **P0** |
| T5 | `it('whatsmeow inbound documentMessage → preserva filename UTF-8 acentos + media_size_bytes')` | idem ↑ | P1 |
| T6 | `it('SendMediaJob whatsmeow → POST {daemon_url}/chat/send/image body Image=url + Caption + Phone E.164')` | `Modules/Whatsapp/Tests/Feature/SendMediaJobWhatsmeowTest.php` | **P0** |
| T7 | `it('Downloader chama daemon CT 100 com mediaKey extraído de payload.message.imageMessage.mediaKey')` | `GuardiaoMidiaTest.php` (já tem skeleton — adicionar caso whatsmeow) | P1 |
| T8 | `it('ComposerV4 onDrop file → queueMediaFiles + sendMedia auto')` (Vitest Playwright) | `resources/js/Pages/Atendimento/CaixaUnificada/__tests__/ComposerV4.spec.tsx` | P1 |
| T9 | `it('ComposerV4 onPaste clipboard image → pendingFile set + preview render')` | idem ↑ | P1 |
| T10 | `it('frontend cap 16MB = backend MEDIA_MAX_SIZE_BYTES (single source of truth via Inertia share)')` | idem ↑ | P1 |
| T11 | `it('media_url disk public → Storage::url() retorna URL com UUID v4 não-enumerável')` | `MediaMessageTest.php` adicionar | P2 |
| T12 | `it('audio inbound serve com Range header (HTTP 206 partial) — Safari iOS streaming OK')` | `Modules/Whatsapp/Tests/Feature/MediaServeRangeTest.php` | P2 |
| T13 | `it('SendMediaJob failed → UI exibe botão "Reenviar" + POST /retry')` | `InboxRetryFailedTest.php` | P2 |
| T14 | `it('outbound mídia status=sent → publishes Centrifugo omnichannel message.sent com media_url')` | `CentrifugoPublishTest.php` adicionar | P1 |
| T15 | `it('Tier 0 — biz=99 NÃO acessa media_url biz=1 mesmo conhecendo UUID (response 403 se signed; 404 se purgado)')` | `MediaMessageTest.php` extender | **P0** |

**Esforço total estimado** (recalibração ADR 0106 fator-10x):
- T1-T6 (whatsmeow path completo): 0.8 dev-day
- T7-T15 (UX + multi-tenant + edge cases): 0.6 dev-day
- **Total: ~1.4 dev-day** (humano-controlado pra revisão Wagner: +0.6 dia = 2 dias).

---

## 6. Snapshot prod biz=1 — SQL queries sugeridas (NÃO execute)

```sql
-- 1. Histograma tipos mídia inbound últimos 7d
SELECT type, COUNT(*) AS total
FROM messages
WHERE business_id=1
  AND direction='inbound'
  AND created_at > NOW() - INTERVAL 7 DAY
GROUP BY type
ORDER BY total DESC;
-- Esperado pós-bug: 'text' dominante, '[imagem]' / '[áudio]' como body em text rows. Mídia type=image/audio/video DEVIA aparecer; provavelmente 0 ou ínfimo.

-- 2. Status download mídia
SELECT media_download_status, COUNT(*)
FROM messages
WHERE business_id=1
  AND type IN ('image','audio','video','document','sticker')
  AND created_at > NOW() - INTERVAL 7 DAY
GROUP BY media_download_status;
-- Whatsmeow rows todas devem ter media_download_status='pending' attempts=0 (Observer nunca rodou — media_mime null).

-- 3. Latência média daemon → persist (provider_message_id já set)
SELECT
  AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) AS avg_persist_lag_s,
  MAX(TIMESTAMPDIFF(SECOND, created_at, updated_at)) AS max_persist_lag_s
FROM messages
WHERE business_id=1
  AND direction='outbound'
  AND provider='whatsmeow'
  AND status='sent'
  AND created_at > NOW() - INTERVAL 1 DAY;

-- 4. Tamanho médio + max attachment outbound
SELECT
  AVG(media_size_bytes) AS avg_bytes,
  MAX(media_size_bytes) AS max_bytes,
  COUNT(*) AS total
FROM messages
WHERE business_id=1
  AND direction='outbound'
  AND media_size_bytes IS NOT NULL
  AND created_at > NOW() - INTERVAL 30 DAY;

-- 5. Health-check whatsapp_media_pending_1h (replica jana:health-check)
SELECT COUNT(*) AS pending_over_1h
FROM messages
WHERE media_download_status IN ('pending','downloading')
  AND media_url IS NULL
  AND created_at < NOW() - INTERVAL 1 HOUR
  AND created_at > NOW() - INTERVAL 7 DAY;

-- 6. Failed permanente últimas 24h — diagnóstico
SELECT id, business_id, provider, type, media_download_failed_reason, media_download_attempts
FROM messages
WHERE media_download_status='failed_permanent'
  AND media_download_last_attempt_at > NOW() - INTERVAL 24 HOUR
ORDER BY media_download_last_attempt_at DESC
LIMIT 50;

-- 7. Outbound failed pelo gate "só Baileys" (P0 #2)
SELECT id, business_id, provider, type, failed_reason, created_at
FROM messages
WHERE direction='outbound'
  AND status='failed'
  AND failed_reason LIKE '%só implementado pra Baileys%'
  AND created_at > NOW() - INTERVAL 7 DAY;
-- Conta de quantos foram bloqueados pelo bug P0 #2/#3 desde cutover whatsmeow.
```

---

## 7. Conclusão + decisão estratégica

**EVOLUIR — não consolidar.** O cutover Baileys → whatsmeow (ADR 0204, 2026-05-27) **deixou outbound texto, outbound mídia e inbound mídia sem código de roteamento real** pro novo driver, mesmo o WhatsmeowDriver existindo e estando correto. O Job + Controller estão hardcoded `TYPE_WHATSAPP_BAILEYS`. Hoje (2026-05-28) biz=1 provavelmente só recebe TEXTO inbound via whatsmeow; mídias chegam como string `'[imagem]'` no body sem download; outbound trava com toast "só Baileys".

Onda 1 (1-2 dias dev): refactor InboxController + SendMediaJob pra usar `ChannelDriverFactory::for($channel)` em vez de hardcode — desbloqueia outbound completo. Extender `extractFromWhatsmeow` pra ler `info.Type` + payload nested → setar `media_mime`/`media_url`/payload mediaKey nos INSERTs.

Onda 2 (0.5 dia): UX paste+drag-drop no ComposerV4 + alinhar caps 16MB/MIME whitelist frontend↔backend (single-source via Inertia shared props).

Onda 3 (0.5 dia): testes T1-T15 (paralelos).

**Surpresa positiva:** Guardião 6-camadas (Observer auto-dispatch + Job retry hourly + ScanDrift command + HealthCheck SQL + Backfill) é estado-da-arte — 17+ Pest tests, multi-tenant Tier 0 enforced, idempotente, retry estruturado retryable vs non-retryable. **Acima do que Chatwoot OSS faz**.

**Surpresa negativa:** Pós-cutover whatsmeow, web UI inbox produção está parcialmente quebrada e Wagner provavelmente notou via "mídia some" sem saber root cause. P0 #1 + P0 #2 + P0 #3 são triviais (refactor 3 lugares) mas IRREVOGAVELMENTE bloqueiam adoção do novo driver.

---

## 8. Saturação (onde parar)

- **75%** após Onda 1 (whatsmeow outbound/inbound funcional) — bloqueia produção atual.
- **88%** após Onda 2+3 (UX moderna + cobertura testes whatsmeow) — paridade com Chatwoot.
- **95%** com multi-attachment + clamav + thumb video + signed URL S3 obrigatório (Onda 4, fora de escopo desta auditoria).
- **>95%** seria over-engineering pro contexto PME oimpresso — não vale ROI vs adicionar features Sells/Jana.

---

**Fim do documento.** Cite linhas: WhatsmeowDriver.php L105-154 (driver tem sendMedia OK), InboxController.php L848+L1319 (gates hardcoded), ProcessIncomingWebhookJob.php L325-369+L237-249 (extract whatsmeow sem media), MessageObserver.php L87 (gate media_mime null = skip), ComposerV4.tsx L37-150 (sem drag/paste).
