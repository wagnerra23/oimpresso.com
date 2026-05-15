# 2026-05-15 — Agent C — Contact sync no history.sync (fix Baileys 7.x)

**Wave:** paralela 3-agents. Agent C, isolado em `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php` + `Modules/Whatsapp/Jobs/` + `Modules/Whatsapp/Tests/Feature/`.

**Trigger:** Wagner reportou 2026-05-15 09:25: *"sincronia dos contatos não trouxe contatos"* pós re-pareamento Baileys 7.x em ROTA LIVRE biz=1.

## Decisão de escopo (mínimo viável)

| Escopo | Decisão | Razão |
|---|---|---|
| Persistir `Conversation.contact_name` quando vazio/E.164 fallback | ✅ SIM | Resolve sintoma reportado por Wagner — conv com nome real ao invés de E.164 cru |
| Sobrescrever nome existente | ❌ NÃO | Preserva nome curado pelo atendente (`ConversationContactLinker::tryLink` linha 256 já tem essa regra) |
| Criar tabela `whatsapp_contacts` separada | ❌ NÃO | Escopo separado — ADR futura. Hoje basta hidratar a denormalização existente |
| Mexer no `ContactObserver` / `ConversationContactLinker` | ❌ NÃO | Separação de concerns — Linker continua linkando Contact CRM real-time |
| Mexer no daemon Node | ❌ NÃO | Daemon já envia `contacts` corretamente — bug é só no PHP backend |
| Mexer no frontend React | ❌ NÃO | Inertia pega `contact_name` automaticamente do payload existente |
| Adicionar ADR | ❌ NÃO | Wagner aprovou direto — handoff suficiente; gap real catalogado |

## Root cause confirmado

1. **Daemon Node** (`Modules/Whatsapp/daemon-node/src/baileys/Instance.ts:243-288`):
   - Recebe `messaging-history.set` do Baileys SDK com `{chats, contacts, messages, syncType}`
   - **ENVIA** `contacts` no webhook payload (linhas 278-281): `contacts: i === 0 ? contacts : undefined` (só no chunk_index=0 anti-duplicação)
   - Shape: array de `{id, name, notify, verifiedName}` onde `id` é `5511X@s.whatsapp.net` ou `X@lid`

2. **Backend PHP ANTES** (`Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php::handleHistorySync` linhas 151-220):
   - Lia SÓ `$data['messages']` (linha 156)
   - `$data['contacts']` ignorado completamente → drop silencioso
   - Conversation só ganhava nome quando msg com `pushName` chegava no path real-time (`MessagePersister:246-249`)

3. **Sintoma**: 1ª msg real-time pós re-pareamento exibia `+5511999998888` ao invés de "Maria" até que mensagem com `pushName` populado chegasse (pode demorar horas/dias dependendo do volume).

## Shape do payload `$data['contacts']`

Cita Instance.ts linhas 244-247 + 278-281:

```js
sock.ev.on('messaging-history.set', async (payload) => {
  const { chats = [], contacts = [], messages = [], syncType } = payload;
  // ...
  await this.webhook.dispatch({
    event: 'history.sync',
    data: {
      sync_type: syncType,
      chunk_index: Math.floor(i / CHUNK),
      chunk_total: Math.ceil(messages.length / CHUNK),
      messages: slice,
      chats: i === 0 ? chats : undefined,
      contacts: i === 0 ? contacts : undefined, // SÓ NO chunk_index=0
    },
  });
});
```

Cada contact (Baileys SDK 7.x):
```json
{
  "id": "5511999998888@s.whatsapp.net",
  "name": "Maria da Silva",
  "notify": "Maria",
  "verifiedName": null
}
```
ou para LID (Linked ID Multi-Device anti-spam):
```json
{
  "id": "X@lid",
  "name": null,
  "notify": "Bruno",
  "verifiedName": null
}
```

## Solução implementada

### 1. Controller — `handleHistorySync` (edit)

`Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php:151-220`:
- Lê `$data['contacts']` defensivamente (cast `(array)`)
- Se `! empty($contacts)` → dispatch `PersistContactsFromHistorySyncJob` (assíncrono, queue=database)
- Log estruturado `metric_name="whatsapp_history_contacts_queued"` para Loki/Grafana
- Edge case: chunk só com contacts (sem messages) retorna 202 `history_chunk_contacts_only_queued` — não responde 200 vazio
- Daemon recebe 202 imediato (não trava webhook handler — pattern já validado no `PersistHistorySyncBatchJob`)

### 2. Job novo — `PersistContactsFromHistorySyncJob`

`Modules/Whatsapp/Jobs/PersistContactsFromHistorySyncJob.php` (novo, 230 linhas):
- Pattern idêntico ao `PersistHistorySyncBatchJob`: `tries=3`, `backoff=[10,30,90]`, `onConnection('database')`, `onQueue('whatsapp-history')`
- `businessId` no constructor (Tier 0 ADR 0093 — Jobs sem session)
- `withoutGlobalScope(ScopeByBusiness::class)` + filtro explícito por `business_id` + `channel_id` (justificado SUPERADMIN)
- **Idempotente via WHERE compound**: só atualiza Conversation quando `contact_name IS NULL OR contact_name = '' OR contact_name = customer_external_id` (preserva nome curado)
- **Prioridade de nome**: `verifiedName` > `name` > `notify` (Baileys SDK semantics — verifiedName é Business Profile oficial)
- **LID resolution**: contact com `@lid` consulta `LidPhoneResolver` (cache 24h populado pelo path real-time via `MessagePersister:104-123`). Se LID não resolvido ainda, skip silencioso — próxima msg real-time vai descobrir
- **Métricas OTel lightweight bridge**: `whatsapp_history_contacts_persisted` + `whatsapp_history_contacts_failed` (logQL Loki agrega)
- **PII redact**: zero phones em log — só counts (`updated`, `skipped_no_name`, `skipped_no_match`, `skipped_lid_unresolved`)

### 3. Pest test — `PersistContactsFromHistorySyncJobTest`

`Modules/Whatsapp/Tests/Feature/PersistContactsFromHistorySyncJobTest.php` (novo, 220 linhas):

- **R-WA-HSC-001 happy-path**: 4 cenários num test só:
  1. Conv com `contact_name='+5511999998888'` (fallback E.164) → ganha "Maria da Silva"
  2. Conv com `contact_name='João VIP (atendente)'` curado → PRESERVA (não sobrescreve com "João Comum")
  3. Contact sem nome em nenhum campo → skip silencioso
  4. Contact com `verifiedName` → prioridade > `name` > `notify`

- **R-WA-HSC-002 cross-tenant Tier 0**: contacts do biz=99 NÃO afetam Conversation do biz=1 com phone idêntico. Conv biz=1 mantém `contact_name='+5511999998888'`, conv biz=99 ganha "Cliente Tenant 99".
  - Usa biz=1 vs biz=99 (NUNCA biz=4 ROTA LIVRE per ADR 0101).

## Smoke E2E (manual prod biz=1)

```bash
# 1. Estado pré-fix:
ssh -4 -o ServerAliveInterval=3 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    u906587222@148.135.133.115 'cd domains/oimpresso.com/public_html && \
    php artisan tinker --execute="
echo \Modules\Whatsapp\Entities\Conversation::query()
    ->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
    ->where(\"business_id\", 1)
    ->where(\"contact_name\", \"LIKE\", \"+%\")
    ->count();"'
# Esperado: N (conversas com nome E.164 cru = bug visível)

# 2. Trigger re-sync via daemon CT 100 (Wagner aprova via Vaultwarden):
tailscale ssh root@ct100-mcp 'docker exec baileys-daemon \
    curl -X POST http://localhost:3000/admin/instances/biz1-XXX/fetch-history \
    -H "Authorization: Bearer $API_KEY"'

# 3. Aguardar 30s (PHP-FPM worker processa queue=whatsapp-history):
sleep 30

# 4. Estado pós-fix — esperado: 0 (todos com nome real):
# (repete tinker query acima)

# 5. Validar log:
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'tail -100 domains/oimpresso.com/public_html/storage/logs/laravel.log | \
     grep "whatsapp_history_contacts_persisted"'
```

## Pegadinhas catalogadas

1. **LID resolution depende do path real-time prévio**: contact com `@lid` no history.sync SÓ é resolvido se o `LidPhoneResolver` já tem cache (alimentado pelo `MessagePersister:104-123` quando msg real-time com `senderPn` chegou antes). Caso re-pareamento absoluto, alguns LIDs podem ficar não-resolvidos até primeira msg real-time chegar — não é regressão, é mesmo limite que já existia no path real-time.

2. **Nome pode vir vazio**: contato bloqueado pelo cliente, ou contato sem nome cadastrado no WhatsApp do lado dele → `name=null, notify=null, verifiedName=null`. Job skip silencioso (counter `skipped_no_name`).

3. **Conv não existe ainda**: contact pode vir antes de qualquer msg recebida. UPDATE retorna 0 rows affected → counter `skipped_no_match`. Não é regressão — próxima msg real-time cria conv com `pushName` ou fallback E.164, e re-sync futuro hidrata.

4. **Re-run safe**: re-pareamento WhatsApp manda histórico FULL de novo. COALESCE+WHERE compound garante no-op em conversas já com nome. Webhook idempotente nível Job.

5. **Chunk apenas com contacts (sem messages)**: edge case raro mas suportado — retorna 202 `history_chunk_contacts_only_queued` ao invés de 200 vazio.

6. **`@s.whatsapp.net` é o caminho feliz**; `@lid` exige `LidPhoneResolver`; sem `@` (formato bruto) normalizado defensivamente. Casos exóticos (`@g.us` grupo, `@broadcast` status) NÃO chegam aqui — daemon filtra antes (controller `handleMessage` linha 247-256 do filter já documenta isso).

## Pré-flight feito (arquivos lidos antes de implementar)

1. ✅ `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php` (linhas 1-250) — entender `handleHistorySync` atual
2. ✅ `Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php` — copiar padrão Job
3. ✅ `Modules/Whatsapp/Services/Webhook/MessagePersister.php` — entender LID resolution + E.164 normalization
4. ✅ `Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php` — API `resolve(businessId, lid)` retorna `+E.164|null`
5. ✅ `Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php` — entender separação (este Job NÃO duplica linker; só `contact_name`)
6. ✅ `Modules/Whatsapp/Entities/Conversation.php` — campo é `contact_name` (NÃO `customer_name` como mencionado no prompt — correção feita)
7. ✅ `Modules/Whatsapp/daemon-node/src/baileys/Instance.ts:230-289` — shape exato do payload
8. ✅ `Modules/Whatsapp/Tests/Feature/HistorySyncQueueArchitectureTest.php` — pattern Pest Job
9. ✅ `Modules/Whatsapp/Tests/Feature/LinkContactTest.php` — schema inline conversations + channels

## Correção sobre o prompt

Prompt mencionava `customer_name` mas o campo real é `contact_name` (Conversation:62 fillable). Match é por `customer_external_id` (formato `+E.164`). Usei nomenclatura correta na implementação.

## Arquivos modificados/criados

- ✏️ `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php` (edit `handleHistorySync` — +50 linhas)
- 🆕 `Modules/Whatsapp/Jobs/PersistContactsFromHistorySyncJob.php` (230 linhas)
- 🆕 `Modules/Whatsapp/Tests/Feature/PersistContactsFromHistorySyncJobTest.php` (220 linhas, 2 testes)
- 🆕 `memory/sessions/2026-05-15-agent-c-contact-sync-history-sync.md` (este arquivo)

## Próximos passos (não-bloqueadores, fora escopo Agent C)

- ⏭️ Aguardar Agents A + B concluírem
- ⏭️ Parent consolidar 3 PRs separados por domínio
- ⏭️ Wagner aprova merge ordem (canary 7d em ROTA LIVRE)
- ⏭️ Backfill manual opcional: cron daily ou one-shot `php artisan whatsapp:resync-contacts-from-history --biz=1` (ADR futura se Wagner pedir)
