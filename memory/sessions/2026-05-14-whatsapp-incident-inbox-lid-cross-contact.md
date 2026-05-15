---
title: "WhatsApp incident — todas msgs do Wagner caem em contato errado via mapping LID manual stuck"
date: 2026-05-14
status: investigated
severity: high
biz_impact: prod biz=1 (núcleo Wagner) — inbox cruzado
component: Modules/Whatsapp
related_adrs: [0093, 0094, 0135]
related_prs_recentes: [848, 850]
related_us: US-WA-093
---

> ⚠️ **CORREÇÃO 2026-05-15 (estudo protocol-level):** a hipótese H1 emendada "LID é chat-level identifier" estava ERRADA. Pesquisa profunda confirmou que **1 LID = 1 USER (per-account)** em Baileys 6.7.9 — persiste por toda vida do account, não é per-chat. **Causa real do incident:** fuzzy match `tail4` no `ConversationContactLinker` (4 últimos dígitos coincidindo entre número Wagner e `alternate_number` da Eliana). PR #854 corrigiu via suffix-8. Detalhe completo em [2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) §1.2 + §4c.

# TL;DR

- **Vence H1 emendada (CORRIGIDA 15/mai — ver nota acima):** mapping manual `whatsapp_lid_pn_map.id=1` cadastrado **2026-05-12 17:01:34** (source=`manual`) liga `lid=14628809617558` → `phone=+5548[REDACTED-últ4]2822` (Eliana, contact_id=6005). Causa real revisada 15/mai: **fuzzy match `tail4` no `ConversationContactLinker`** — Eliana tinha `alternate_number=48999872822` (número do Wagner cadastrado errado nela), tail4=2822 batia com phone Wagner → linker auto-vinculou conv ao Contact errado.
- **Agravante (H3 parcial):** Wagner re-pareou o canal 7 hoje 14/mai (sessão monitor-pairing PR #848). Daemon Baileys 6.7.9 re-disparou `messaging-history.set` e cadastrou 13 LIDs adicionais (id 4-16, todos `manual` às 08:40:10) — mass insert ad-hoc não-rastreado no git (drift Tier 0 proibido).
- **Impacto:** Inbox biz=1 mostra **1 conversa única (#37) com nome "ELIANA MARCELINO ALVES 06075269983"** para 81 mensagens que misturam Wagner + interlocutor real. Sem perda de dado — só atribuição cruzada.

# Cronologia reconstruída

| Quando | O que aconteceu | Evidência |
|---|---|---|
| 2026-05-12 17:01:34 | `whatsapp_lid_pn_map.id=1` inserido: `lid=14628809617558` → `phone=+5548[REDACTED]2822` source=`manual` | DB prod `whatsapp_lid_pn_map` |
| 2026-05-14 08:33:45 | Mappings 2-3 criados via webhook (source=`webhook_senderPn`, phone=NULL — só LID rastreado) | DB |
| 2026-05-14 08:40:10 | Mass insert de 13 mappings adicionais (ids 4-16, todos source=`manual`, phones distintos). Cadastro ad-hoc no Hostinger, sem rastro no git. `id=1.last_seen_at` também bumpado | DB |
| 2026-05-14 ~18h | Wagner re-pareou canal 7 (Baileys, ROTA WhatsApp Suporte ou Vendas) seguindo runbook do PR #848 | channel.status=active healthy desde 21:51 UTC |
| 2026-05-14 18:39:26-18:40:24 | 81 msgs persistidas (39 inbound + 25 outbound + 17 history-sync). 100% com `payload.key.remoteJid="14628809617558@lid"`, `senderPn=null`, `payload.is_history_sync=true`. Todas threadaram em `conversations.id=37` (criada 18:39:26 com `customer_external_id=+5548[REDACTED]2822` `contact_name="ELIANA MARCELINO ALVES 06075269983"`) | DB messages 360-440+ |
| 2026-05-14 18:40 | Wagner abre inbox e vê 1 conversa única com contato Eliana | screenshot |

**Por que mistura Wagner + outro?** No Baileys 6.7.9, `remoteJid` é o JID do OUTRO LADO do chat 1:1. `fromMe=true` indica msg do owner do canal pareado, `fromMe=false` indica msg da contraparte. **Ambas direções compartilham o mesmo `remoteJid`** — então quando Wagner testou o canal mandando vários "Oi"/"Maravilha", as próprias mensagens dele saem identificadas pelo LID **da pessoa do outro lado** (que está respondendo "Conectado"/"Certo deu"/etc).

# Evidências (comandos rodados)

```bash
# 1) Canal — único biz=1, healthy
DB::table('whatsapp_channels')->where('business_id',1)->get()
→ id=7 type=whatsapp_baileys status=active channel_health=healthy
  last_health_check_at=2026-05-14T21:51:25Z

# 2) Mapping LID id=1 (a fonte do dano)
DB::table('whatsapp_lid_pn_map')->where('id',1)->first()
→ id=1 business_id=1 lid="14628809617558" phone_e164="+5548[REDACTED]2822"
  source="manual" first_seen_at="2026-05-12 17:01:34"
  last_seen_at="2026-05-14 08:40:10"

# 3) 13 mappings manuais 08:40:10 (mass insert ad-hoc no Hostinger)
DB::table('whatsapp_lid_pn_map')->whereBetween('id',[4,16])->get()
→ todos source="manual" first_seen_at="2026-05-14 08:40:10"

# 4) Messages 18:39-18:40 — TODAS apontam pro mesmo LID
SELECT direction, JSON_EXTRACT(payload,'$.key.remoteJid') as rjid,
       JSON_EXTRACT(payload,'$.key.senderPn') as spn,
       JSON_EXTRACT(payload,'$.key.fromMe') as fm
FROM messages WHERE business_id=1 AND created_at BETWEEN '2026-05-14 18:39:00' AND '2026-05-14 18:40:59'
→ 100% rjid="14628809617558@lid" senderPn=null fm misto (true/false)

# 5) Conversation única
DB::table('conversations')->where('business_id',1)->get()
→ id=37 channel_id=7 contact_id=6005 customer_external_id="+5548[REDACTED]2822"
  contact_name="ELIANA MARCELINO ALVES 06075269983"

# 6) Payload msg #360 ("Oi" Wagner fromMe=true)
{
  "key": { "remoteJid":"14628809617558@lid", "fromMe":true, "id":"ACACB..." },
  "message": { "conversation":"Oi" },
  "pushName": null,
  "is_history_sync": true
}
```

# Root cause (1 parágrafo técnico)

Baileys 6.7.9 (atual prod CT 100) entrega `messages.upsert` e `messaging-history.set` com `key.remoteJid="<lid>@lid"` quando o chat foi iniciado via Click-to-Chat/Status/Ads. Esse LID é **único POR CHAT 1:1** (não por pessoa) — ambos os interlocutores compartilham o mesmo remoteJid (fromMe diferencia). O mapping manual `id=1` cadastrado 12/mai associou esse LID ao phone+contact da Eliana, transformando o LID em "Eliana" do ponto de vista do `LidPhoneResolver`. Quando Wagner testou (14/mai 18:39) com pareamento fresco, todas as 81 mensagens passaram pelo `PersistHistorySyncBatchJob → handleMessage → LidPhoneResolver::resolve()` que retornou `+5548[REDACTED]2822` do cache, threadando tudo na conversa #37 com nome da Eliana. **O verdadeiro defeito não é só o mapping errado — é arquitetural: 1 LID @lid representa 1 chat (relação owner-canal × interlocutor), NÃO 1 pessoa, e o sistema atual assume 1:1 entre LID e pessoa.** Workaround viável é continuar 1:1 mas exigir validação senderPn explícita antes de aceitar `source=manual` UI/CLI — qualquer mapping sem confirmação webhook anti-bypass.

# Plano recovery (proposta — não executar até Wagner liberar)

**Passo 1 — Inspecionar dados antes de deletar nada:**

```sql
-- Confirmar que conversation 37 só tem msgs do bug (não conversas legítimas pré-bug)
SELECT MIN(created_at), MAX(created_at), COUNT(*) FROM messages
 WHERE business_id=1 AND conversation_id=37;
-- Esperado: MIN=2026-05-14 18:39:26 (consistente — toda conversa nasceu no bug)
```

**Passo 2 — Recovery SQL (Wagner roda no Hostinger após confirmar):**

```sql
-- a) Anular mapping ofensivo (deixa o LID rastreado mas sem phone associado)
UPDATE whatsapp_lid_pn_map
   SET phone_e164=NULL, source='webhook_senderPn',
       last_seen_at=NOW(), updated_at=NOW()
 WHERE business_id=1 AND id=1;

-- b) Desconectar conversation 37 do contact_id Eliana
UPDATE conversations
   SET contact_id=NULL,
       contact_name='+14628809617558 (número oculto - LID não resolvido)',
       customer_external_id='+14628809617558',
       updated_at=NOW()
 WHERE business_id=1 AND id=37;

-- c) Auditar os outros 13 manuais inseridos 08:40 — origem desconhecida
UPDATE whatsapp_lid_pn_map
   SET phone_e164=NULL, source='webhook_senderPn',
       updated_at=NOW()
 WHERE business_id=1 AND id BETWEEN 4 AND 16
   AND source='manual';
```

**Passo 3 — Flush cache LID:**

```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && \
  php artisan cache:forget "whatsapp:lid:1:14628809617558" && \
  for lid in 60765331554480 52905608499444 115607668289612 51969154633945 \
             255271414804561 230412211245300 98393540001865 92367633833996 \
             221341760360571 195425457930323 36387030081735 280500153405577 \
             158273873395781; do
    php artisan cache:forget "whatsapp:lid:1:$lid"
  done'
```

**Passo 4 — Re-popular contact_id de conv #37 apenas se Wagner confirmar quem é a contraparte:**

Após Wagner identificar (via celular dele, olhando a thread real no WhatsApp app), rodar:

```sql
UPDATE conversations SET contact_id=<id-real>, contact_name=<nome-real>,
       customer_external_id=<phone-real> WHERE id=37 AND business_id=1;
```

# Patch código (PR separada — bug arquitetural)

**Arquivo:** `Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php` linha 115 (`record()`)

Defesa: NÃO permitir `source='manual'` sem evidência de senderPn vista pelo webhook pelo menos 1×.

```diff
@@ -115,7 +115,7 @@ class LidPhoneResolver
     public function record(
         int $businessId,
         string $lid,
         ?string $phone = null,
         string $source = LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN,
     ): ?LidPhoneMap {
+        // Tier 0: source='manual' exige confirmação webhook prévia (ADR 0093 +
+        // incident 2026-05-14 inbox cross-contact). LID sem webhook_senderPn
+        // confirmation NÃO pode ser linkado a phone via UI/CLI — alto risco
+        // de associar LID-de-chat a contact errado (LID é per-chat, não per-pessoa).
+        if ($source === LidPhoneMap::SOURCE_MANUAL && $phone !== null) {
+            $hasWebhookEvidence = LidPhoneMap::query()
+                ->withoutGlobalScope(ScopeByBusiness::class)
+                ->where('business_id', $businessId)
+                ->where('lid', $this->normalize($lid))
+                ->where('source', LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN)
+                ->exists();
+            if (! $hasWebhookEvidence) {
+                Log::warning('[whatsapp.lid_resolver.manual_rejected_no_webhook_evidence]', [
+                    'business_id' => $businessId,
+                    'lid_prefix' => substr($this->normalize($lid), 0, 6) . '...',
+                ]);
+                throw new \DomainException(
+                    'LID manual requer webhook_senderPn prévio (anti-cross-contact incident 2026-05-14).'
+                );
+            }
+        }
         $normalizedLid = $this->normalize($lid);
```

**Por que isso resolve sem ferir o caso legítimo:** webhook real do daemon SEMPRE precede manual — quem cadastra manualmente está sempre corrigindo um LID já visto. Bloqueia mass-insert ad-hoc sem trail.

**Arquivo secundário:** `Modules/Whatsapp/Services/Webhook/MessagePersister.php` linha 72-77

Defesa: usar `LidPhoneResolver` no path do `PersistHistorySyncBatchJob` para consistência E adicionar log auditoria quando LID resolve para phone existente em outro contact:

```diff
@@ -72,6 +72,16 @@ class MessagePersister
         $resolvedJid = ($senderPn && str_contains($senderPn, '@s.whatsapp.net'))
             ? $senderPn
             : $remoteJid;
         $rawNumber = preg_replace('/@.+$/', '', $resolvedJid);
         $customerExternalId = '+' . $rawNumber;
+
+        // US-WA-093++: history_sync path também consulta LidPhoneResolver
+        // mas com flag de auditoria (incident 2026-05-14: mapping manual stuck).
+        if (str_contains((string) $remoteJid, '@lid')) {
+            $resolver = app(\Modules\Whatsapp\Services\Contacts\LidPhoneResolver::class);
+            $cached = $resolver->resolve($this->channel->business_id, $remoteJid);
+            if ($cached !== null && $cached !== $customerExternalId) {
+                Log::info('[whatsapp.persister.lid_resolved_to_different_phone]', [
+                    'business_id' => $this->channel->business_id,
+                    'lid_prefix' => substr(preg_replace('/@.+$/','',$remoteJid), 0, 6) . '...',
+                    'is_history_sync' => $data['is_history_sync'] ?? false,
+                ]);
+                $customerExternalId = $cached;
+            }
+        }
```

# Pest regression test (esqueleto)

```php
<?php
// Modules/Whatsapp/Tests/Feature/LidManualRejectedWithoutWebhookEvidenceTest.php

use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;

it('rejeita mapping manual de LID sem evidência webhook prévia (incident 2026-05-14)', function () {
    // Arrange: business_id=1, LID nunca antes visto via webhook
    $resolver = app(LidPhoneResolver::class);

    // Act + Assert: tentativa de manual direto deve falhar
    expect(fn () => $resolver->record(1, '14628809617558@lid', '+5548999000000', LidPhoneMap::SOURCE_MANUAL))
        ->toThrow(\DomainException::class, 'requer webhook_senderPn prévio');

    // Sanity: row não foi criada
    expect(LidPhoneMap::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('lid', '14628809617558')
        ->exists())
        ->toBeFalse();
});

it('aceita mapping manual após webhook ter cadastrado o LID', function () {
    $resolver = app(LidPhoneResolver::class);

    // 1) Webhook real grava o LID com phone=null (descoberta rastreada)
    $resolver->record(1, '14628809617558@lid', null);
    expect(LidPhoneMap::withoutGlobalScopes()->count())->toBe(1);

    // 2) Operador depois cadastra manualmente phone real — agora permitido
    $resolver->record(1, '14628809617558@lid', '+5548999000000', LidPhoneMap::SOURCE_MANUAL);

    expect(LidPhoneMap::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('lid', '14628809617558')
        ->value('phone_e164'))
        ->toBe('+5548999000000');
});

it('respeita business_id Tier 0 — LID idêntico em biz diferente NÃO compartilha mapping', function () {
    $resolver = app(LidPhoneResolver::class);
    $resolver->record(1, '14628809617558@lid', null);
    $resolver->record(99, '14628809617558@lid', null);

    expect($resolver->resolve(1, '14628809617558@lid'))->toBeNull(); // sem webhook→phone
    expect($resolver->resolve(99, '14628809617558@lid'))->toBeNull();
    expect(LidPhoneMap::withoutGlobalScopes()->count())->toBe(2);    // 2 rows distintas
});
```

# Pré-reqs aprovação Wagner antes de executar

1. **Sim/não:** Quem é a outra pessoa nas msgs 18:39-18:40? (Você consegue confirmar pelo celular pareado abrindo a conversa real no WhatsApp app — número/nome real)
2. **Sim/não:** Posso rodar o passo 2 do recovery (UPDATE em `whatsapp_lid_pn_map.id=1` zerando phone_e164 + UPDATE em `conversations.id=37` desassociando contact Eliana)? Há janela 5min de manutenção aceitável.
3. **Sim/não:** Os 13 mappings manuais inseridos hoje 08:40 são intencionais (alguém do time cadastrou) ou também são ruído? Se intencionais, quem fez (procurar evidência fora git)?
4. **Sim/não:** Abrir PR com os 2 patches + Pest test (path `Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php` + `Modules/Whatsapp/Services/Webhook/MessagePersister.php` + novo test)?
5. **Sim/não:** Criar task MCP `tasks-create US-WA-???` rastreando esse incidente + ADR-mãe pra "1 LID ≠ 1 pessoa" (revisão arquitetural pré-Baileys 7.x Alt JID nativo)?

# Observações governança

- 13 inserts manuais 14/mai 08:40 sem rastro em git/sessions = **drift Tier 0** (Constituição "Ambiente": "Nunca editar arquivo direto via SSH sem commit no git — drift mata governança"). Recomendação: criar comando artisan `whatsapp:lid-map:audit` que detecta `source=manual` sem `source=webhook_senderPn` companion row + alerta cron daily.
- Falta métrica de monitoração: `whatsapp_lid_resolver_returned_phone_count` por business — pico anômalo (ex: 80 msgs/min resolvendo pro mesmo phone) é sinal de cross-contact bug.
- PRs #848/#850 mergeados hoje (monitor re-pairing + métricas history-sync) **NÃO causaram esse bug** — bug estava latente desde 12/mai com mapping ofensivo. Re-pareamento de hoje apenas amplificou efeito.
