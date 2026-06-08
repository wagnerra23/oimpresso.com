---
page: /atendimento/inbox
component: resources/js/Pages/Atendimento/Inbox/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-15
parent_module: Whatsapp
parent_adr: memory/decisions/0135-omnichannel-inbox-arquitetura.md
related_adrs: [0039, 0058, 0093, 0094, 0104, 0110, 0135]
tier: A
charter_version: 1
---

# Page Charter — `/atendimento/inbox`

> Define as invariantes da tela Inbox omnichannel — substituição long-term
> de `/whatsapp/conversations` legacy. Mudanças que violem este charter
> exigem PR + atualização do charter.

---

## Mission

Tela única que centraliza todas as conversas omnichannel do business
(Whatsapp Baileys hoje · Whatsapp Meta Cloud · Whatsapp Z-API · futuramente
Instagram DM, Messenger, Email, Mercado Livre), num **Cockpit V2 3-painéis**
([ADR 0110](memory/decisions/0110-cockpit-pattern-v2.md)) com **real-time
sem reload manual** (Centrifugo WSS + polling 5s defesa em profundidade)
e **atalhos teclado** (J/K/E/A/`/`) pra atendente operar tudo sem mouse.

Substitui `/whatsapp/conversations` legacy. Coexiste durante PRs B+C até
todos os Channels Z-API/Meta migrarem do schema legacy (WhatsappBusinessConfig)
pro schema novo polimórfico (Channel/Conversation/Message).

---

## Goals — Features (faz)

### Layout & navegação
- **Cockpit 3-painéis** (esquerda lista · centro thread · direita contexto)
  com sidebar esquerda e direita colapsáveis individualmente (LS-persisted).
- **Atalhos teclado** J/K (próx/ant), `/` (foca search), E (resolver), A
  (aguardar humano).
- **Tabs filtro:** Todas · Não lidas · Minhas · Bot · Resolvidas
  (counters live).
- **Search debounced 250ms** server-side com `q` query param
  (LS-persisted).
- **Paginação compacta** 50 conversas/página com último-page hint.
- **Topnav único** — entry `Inbox` substitui `Conversas` legacy
  (escondido). Acesso via `/whatsapp/conversations` direto ainda funciona
  pra debug.

### Real-time defense in depth (US-WA-068/070)
- **Centrifugo WSS** subscribe em `omnichannel:business:{id}` — token
  JWT HS256 TTL 3600s emitido pelo `CentrifugoTokenIssuer` em cada
  `Inertia::render`.
- **Polling 5s SEMPRE** em paralelo (cliente real cancelou contrato
  2026-05-11 por mensagem perdida quando Centrifugo falhou silencioso —
  daí defesa em profundidade obrigatória).
- **Anti-flash** — todos `router.reload` usam `preserveScroll: true` +
  `preserveState: true` pra evitar piscada de tela em tab ativa.
- **Pausa em aba inativa** — polling skipa quando
  `document.visibilityState !== 'visible'` (economia bateria/banda).

### Mensagens
- **Outbound texto** via `POST /atendimento/inbox/{id}/send` →
  `InboxController::send` → daemon Baileys CT 100 direto (sem passar
  pelo BaileysDriver legacy que ainda usa WhatsappBusinessPhone)
  (US-WA-069).
- **Outbound do próprio chip** (Wagner manda do celular) aparece na
  thread em ~5s via polling — daemon NÃO filtra `fromMe` (patch em
  `Instance.ts:317` US-WA-068).
- **Inbound idempotente** — webhook reentrega mesmo `provider_message_id`
  não cria duplicata; `firstOrCreate` no `ChannelBaileysWebhookController`
  (US-WA-070).
- **Preview última mensagem** embaixo do nome do contato — `reorder()`
  limpa default ASC do relation antes de pegar mais recente (US-WA-070).
- **Status indicators** (✓ enviada · ✓✓ entregue · ✓✓ azul lida · ⚠ falhou).
- **Unread count badge** zera ao abrir thread.

### Cross-channel
- **Filtro por tipo de channel** (whatsapp_baileys · whatsapp_meta_cloud ·
  whatsapp_zapi) — futuro instagram_dm · messenger · email · mercadolivre.
- **Multi-channel display** — label do channel exibido em cada linha
  (ex: "Suporte" vs "Suorte" pra diferenciar 2 chips Baileys do mesmo
  business).

### Customer 360 — Memória persistente cliente (US-WA-VOZ-001/002/003)
- **Bloco `CustomerMemoryBlock`** no topo do ConversationSidebar mostra:
  identidade Contact CRM linkado · stats agregados (n_conversations / n_msgs
  / dias desde última interação) · top 3 reclamações 30d com severity badge
  (heurística keywords PT-BR) · temas recorrentes · fontes externas (Firebird
  OfficeImpresso match) · notas qualitativas Jana · flags VIP/frágil.
- **Lazy fetch client-side** em `GET /atendimento/customer/{ext}/profile` —
  não bloqueia abertura da conversa nem polling 5s.
- **Auto-prefix nome atendente** em outbound humano freeform via
  `InboxController::maybeAutoPrefixSenderName`. Body fica `*Maiara:* texto`
  ⇒ cliente vê quem responde + `EmployeePerformanceRebuilder` heurística
  captura 100% mesmo sem `sender_user_id` (defesa em profundidade).
  Skips: nota interna, template, body vazio, idempotente (já tem `*Nome:*`).

### Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)
- Todas queries `Conversation`/`Message`/`Channel` passam pelo global
  scope `business_id`.
- Centrifugo channel segregado por `business_id` — biz=99 nunca vê
  payload de biz=1 (token JWT só autoriza o channel do business
  logado).

---

## Non-Goals — Features (NÃO faz nesta tela)

- ❌ **Configurar Channel** — vai em `/atendimento/canais` (ChannelsController).
- ❌ **Editar templates HSM** — vai em `/whatsapp/templates`.
- ❌ **Wizard Z-API/Meta** — vai em `/whatsapp/settings`.
- ❌ **Histórico completo (>200 msgs)** — limita 200 últimas no `messages`
  payload pra Hostinger não engasgar. Paginação infinita = backlog.
- ❌ **Marketing em massa / broadcast** — outras tools (RD Station etc).
- ❌ **Voice call / chamadas Whatsapp** — beta Meta, fora de escopo.

---

## UX Targets

- **First-paint:** p95 < 1500ms num Hostinger frio.
- **Switch entre conversas:** p95 < 800ms (HOJE NÃO ATINGE — ver
  Roadmap §1).
- **Latência real-time WSS:** p95 < 1s do webhook chegar ao UI atualizar.
- **Polling 5s fallback** garante upper bound de 5s mesmo com WSS quebrado.
- **0 erros JS console** com config válida.
- **Cabe em 1280px** sem scroll horizontal (cliente piloto ROTA LIVRE).
- **Atalhos teclado funcionais sem foco no input.**
- **Sidebar colapsável** preserva preferência LS-persisted entre sessões.

---

## UX Anti-patterns

- ❌ **Reload total** (`router.reload()` sem `only`) quando msg chega —
  precisa partial reload com `preserveScroll`+`preserveState` (US-WA-068
  enforce).
- ❌ **Polling sem `document.visibilityState` check** — drena bateria em
  tabs abertas idle.
- ❌ **Filtrar `fromMe` no daemon** — esconde mensagens enviadas pelo
  chip externo, cliente percebe (US-WA-068).
- ❌ **`create()` puro em INSERT idempotente** — webhook reentregue
  quebra UNIQUE → 500 → Observer/Centrifugo nunca rodam (US-WA-070).
- ❌ **Re-buscar conversations[] no thread switch** — selectedId é
  estado local React; refetch de 50 conversations com N+1 lastMsg é
  desperdício.
- ❌ **N+1 em `convToListArray`** — `$c->messages()->reorder()->first()`
  por conv. Eager-load ou denormalize.
- ❌ **Centrifugo channel compartilhado entre tenants** — biz=99 sub
  em `omnichannel:business:1` viola Tier 0 (token issuer enforce).
- ❌ **`[mídia]` placeholder** persistir como UX final — média sem
  preview = downgrade vs Whatsapp Web. Resolver em US-WA-072.

---

## Automation Hooks

- **Centrifugo subscribe** `omnichannel:business:{id}` no `useEffect`
  do componente.
- **Polling 5s SEMPRE** (defesa em profundidade) com cleanup no unmount.
- **Webhook daemon → `Api/ChannelBaileysWebhookController` →
  `firstOrCreate` Message → MessageObserver → Event → Listener →
  CentrifugoPublisher.publish().
- **Send daemon HTTP direto** — `InboxController::send` chama
  `POST {daemon_url}/instances/{instance_id}/text` (US-WA-069). Refator
  futuro: passar pelo `ChannelDriverFactory` quando drivers consumirem
  Channel polimórfico.

---

## Automation Anti-hooks

- ❌ Não chama daemon direto no `Inertia::render` — estado vem do DB +
  Centrifugo updates.
- ❌ Não dispara webhook downstream (Jana bot etc) síncrono no
  controller — vai via Job na queue `whatsapp` (Horizon CT 100).
- ❌ Não persiste tokens Centrifugo plaintext em DB — emitidos
  on-the-fly em cada page load via `CentrifugoTokenIssuer`.
- ❌ Não baixa media binary no `handleMessage` controller — daemon
  responsabilidade (US-WA-071 planejada).

---

## Métricas vivas (Pest GUARD)

| Status | Test | Arquivo |
|---|---|---|
| ✅ | `R-WA-070-001 — firstOrCreate keyed em (business_id, provider_message_id) é idempotente` (PR seguinte) | `Modules/Whatsapp/Tests/Feature/ChannelBaileysWebhookIdempotencyTest.php` |
| ✅ | `R-WA-070-002 — reorder("created_at", "desc") retorna mensagem mais recente, anti-regressão stacked orderBy ASC+DESC` (PR seguinte) | mesmo arquivo |
| ⏳ | `InboxCharterTest::it_isolates_centrifugo_channel_per_business()` — business=1 token NÃO autoriza sub em `omnichannel:business:99` | pendente US-WA-084 |
| ⏳ | `InboxCharterTest::it_partial_reloads_only_with_preserve_state()` — toda `router.reload` na page tem `preserveScroll: true` + `preserveState: true` (regex source TS) | pendente US-WA-084 |
| ⏳ | `InboxCharterTest::it_polls_5s_always_not_only_on_fallback()` — `setInterval(5000)` presente independente de `centrifugoConfig` estado (regex source TS) | pendente US-WA-084 |

---

## Roadmap — O que falta (priorizado pelo Wagner)

### §1 Performance — switch de conversa pesado (HOTFIX recomendado)

**Sintoma observado:** "ta pesado trocar de pessoa parece que esta
carregando tudo" (Wagner 2026-05-11).

**Causas:**
1. **N+1 em `InboxController::convToListArray`** — `$c->messages()->reorder()->first()`
   roda 1 query/conv. 50 convs = 50 queries só pra preview.
2. **`selectThread` partial reload pesado** — atualmente inclui
   `'conversations'` em `only[]`, refetchando os 50 + N+1.
3. **Polling 5s SEMPRE refetcha tudo** — incluindo `conversations` +
   `stats` mesmo sem mudança.

**Fixes possíveis:**
- (a) Remover `'conversations'` do `only[]` em `selectThread` —
  ganho imediato, baixíssimo risco.
- (b) Substituir N+1 por subquery JOIN OU denormalizar
  `last_message_preview` + `last_message_direction` direto na
  `conversations` row (atualizado via MessageObserver). Migration nova.
- (c) Polling só reload `stats` + `last_message_at` por conv (campo
  cacheado) — diff DB → cliente decide se refetch full.

**Sugestão de PR:** US-WA-071a (fix (a) imediato) → US-WA-071b (fix
(b) com migration).

### §2 Mídia inbound (imagem · vídeo · áudio · documento)

**Sintoma observado:** msgs `type=image|video` chegam no DB com
`body=null` → MessageBubble mostra `[mídia]` placeholder
(`ConversationThread.tsx:353`).

**O que falta:**
1. **Daemon Baileys** — handler em `messages.upsert` chama
   `downloadMediaMessage()` quando type ∈
   {imageMessage, videoMessage, audioMessage, documentMessage,
    stickerMessage}.
2. **Storage** — definir onde mídia vive. Opções:
   - (a) Daemon salva em volume Docker CT 100 + serve via HTTP
     auth via Traefik (`https://media.oimpresso.com/<channel>/<msg_id>`).
   - (b) Daemon faz upload pra S3 / R2 Cloudflare / Hostinger
     storage + webhook entrega URL pública.
   - (c) Daemon base64 → webhook → Hostinger salva em
     `storage/app/private/whatsapp-media/{channel_uuid}/<msg_id>` (heavy
     network).
   - **Recomendação:** (a) com Traefik basic auth + Channel ACL no
     backend (proxied via Laravel se quiser controle fino).
3. **Schema messages** — adicionar colunas:
   ```
   media_url       VARCHAR(500) NULL  -- URL absoluta (ou path relativo a media base)
   media_mime      VARCHAR(60)  NULL  -- image/jpeg, video/mp4, audio/ogg, application/pdf
   media_size      INT          NULL  -- bytes
   media_duration  INT          NULL  -- segundos (audio/video)
   media_caption   VARCHAR(1024) NULL -- texto que acompanha a mídia (extendedTextMessage)
   media_thumb_url VARCHAR(500) NULL  -- preview gerado pelo daemon ou ondemand
   ```
   OU usar `payload->media` JSON sub-object (sem migration). Trade-off:
   colunas dedicadas = queries mais fáceis + index; JSON = menos migration.
4. **Webhook controller** — extrair URL/mime/size do payload e
   persistir.
5. **Frontend `MessageBubble`** — variantes por `message.type`:
   - `image`: `<img src={media_url} loading="lazy">` + caption + click
     → lightbox.
   - `video`: `<video controls poster={media_thumb_url} preload="metadata">`.
   - `audio`: `<audio controls>` (Baileys envia opus em .ogg —
     navegadores modernos suportam nativo via `audio/ogg; codecs=opus`).
   - `document`: ícone tipo + filename + size + botão download.
   - `location`: lat/lng + preview Google Maps estático (~1 chamada
     API/conv) + link.
   - `contacts`: card com nome + phone + botão "salvar contato".

**US sugeridas:**
- US-WA-072 backend: schema + webhook download + storage daemon
  (~4-6h IA-pair)
- US-WA-073 frontend: bubble variants + lightbox (~2-3h IA-pair)

### §3 Mídia outbound (composer envia imagem/áudio/doc)

**O que falta:**
1. **Composer Whatsapp** — botão `📎 Anexar` no `ConversationThread`.
2. **Upload Inertia** — POST multipart pro `InboxController::send`,
   backend salva temp + manda pro daemon via `POST /instances/{id}/media`.
3. **Daemon endpoint** — recebe arquivo, chama `sock.sendMessage` com
   `image`/`video`/`audio`/`document` payload Baileys.
4. **Áudio voice (WhatsApp PTT)** — gravação no browser via
   `MediaRecorder API` codec opus → upload → daemon usa `ptt: true` no
   send pra aparecer como áudio voz, não doc.

**US sugerida:** US-WA-074 (~6-8h IA-pair).

### §4 Mensagens "internas" do chip (conv #3 com body=null)

**Sintoma:** conv #3 do biz=1 tem `customer_external_id=+554896486699`
(próprio chip Suporte) e ~10 msgs todas `body=null`, `direction=outbound`.

**Causa provável:** daemon registra eventos internos Baileys (sync
keys, protocol messages, presence) como msgs ao webhook. Filter ausente.

**Fix:** no `ChannelBaileysWebhookController::handleMessage`,
adicionar:
```php
if ($body === null && $type === 'text') {
    return response()->json(['ok' => true, 'note' => 'empty_protocol_msg_ignored'], 200);
}
```
E/ou: daemon filtrar `protocolMessage`/`senderKeyDistributionMessage`/
`messageContextInfo` antes do webhook.

**US sugerida:** US-WA-075 (~30min — fix simples).

### §5 Bot Jana HITL (handoff)

Toggle `bot_handling: true|false` por conv. Quando true, Jana responde
auto via Listener `DispatchToJanaBot`. Quando humano atende, vira false.

Hoje a UI tem o badge `Bot` na lista mas o toggle/transição não está
implementado no Inbox. Hoje só funciona em `/whatsapp/conversations`
legacy.

**US sugerida:** US-WA-076 (~2-3h IA-pair).

### §6 Read receipts inbound

Hoje msg inbound entra como `status=received` e fica assim. WhatsApp
quer que enviemos `markRead` pra mostrar visto azul no remetente. Sem
isso, contato externo NÃO vê os 2 traços azuis (UX subpar).

**Fix:** quando conv abre, chamar `POST /instances/{id}/mark-read`
no daemon com `key` da última msg inbound.

**US sugerida:** US-WA-077 (~1-2h IA-pair).

### §7 Indicador de digitação (typing)

WhatsApp suporta presença `composing`/`paused`. Mostrar "Wagner está
digitando..." quando recebido. Enviar quando atendente está digitando.

**US sugerida:** US-WA-078 (~2h IA-pair). Não crítico.

### §8 Reactions emoji 👍

WhatsApp permite reagir com emoji. Hoje ignorado. Pelo Baileys o
payload chega como `reactionMessage`. Render embaixo da bubble alvo.

**US sugerida:** US-WA-079 (~1-2h IA-pair). Não crítico.

### §9 Notas internas (não enviadas pro cliente)

Atendente quer marcar info da conv sem mandar mensagem. Tipo
"chamado #1234 aberto, esperando peça".

**US sugerida:** US-WA-080 (~2h IA-pair) — coluna `internal_note`
em conversations + UI no painel direito.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-11 | Wagner + Sonnet | Charter inicial. Registra US-WA-067 (Inbox unifica UI Cockpit V2 PR #561) · US-WA-068 (anti-flash + outbound do próprio chip PR #562) · US-WA-069 (send via Channel Baileys PR #563) · US-WA-070 (webhook idempotente + preview última msg PR #564). Roadmap 9 pedaços faltantes — perf §1 + mídia §2/§3 prioritários conforme feedback. |
| 2026-05-15 | Wagner + Opus 4.7 | US-WA-VOZ-001/002/003 — Customer 360 sidebar bloco lazy fetch (PR #919) + auto-prefix `*Nome:*` sender outbound. `<CustomerMemoryBlock>` renderiza acima do Card identificação. Charter §Goals adicionada seção "Customer 360 — Memória persistente". |
