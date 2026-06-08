---
title: "Estado-da-arte 2026 — auto-cadastro/dedup de Contact CRM a partir de inbound WhatsApp (pós-incidente Wagner)"
date: 2026-05-14
type: estado-da-arte
status: investigated
module: Modules/Whatsapp
related_adrs: [0093, 0094, 0096, 0117, 0135]
related_sessions: [2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md]
related_us: [US-WA-078, US-WA-093]
nota_oimpresso: 38/100
top_3_gaps: [P0-tail4-cross-contact, P0-lid-per-chat-vs-per-pessoa, P0-historysync-sem-lidresolver]
---

# TL;DR

- **Nota oimpresso hoje: 38/100** nas 12 dimensões pesquisadas. Já temos pilares fortes (multi-tenant Tier 0, idempotência `provider_message_id`, audit `LidPhoneMap`, anti-spam status/grupo, cache 24h) — perdemos em **dedup robusto (fuzzy 4 dígitos = causa raiz do incidente)**, **modelo conceitual LID** (assumimos 1 LID = 1 pessoa; Baileys 6.7.9 entrega 1 LID = 1 chat), **history-sync não consulta LidPhoneResolver** (cross-path drift).
- **Top 3 gaps fatais (rankeados):**
  1. **Fuzzy match `tail4` no `ConversationContactLinker`** ([linker:312-325](../../Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php#L312)) — 4 dígitos coincidem por puro acaso a cada 10k phones BR. Quem ganha: zero cross-contact incidente Wagner-Eliana repetido.
  2. **Mapping manual sem evidência webhook prévia** ([resolver:115](../../Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php#L115)) — UI/CLI hoje pode gravar `source=manual` direto, sem ver webhook real do daemon. Causou as 13 rows ad-hoc 14/mai 08:40.
  3. **`MessagePersister` não consulta `LidPhoneResolver`** ([persister:72-77](../../Modules/Whatsapp/Services/Webhook/MessagePersister.php#L72)) — caminho real-time controller já faz; caminho `PersistHistorySyncBatchJob` não. Drift garantido entre 2 paths que persistem msgs.
- **Recomendação one-liner:** P0-fix em 3 patches isolados (≤300 linhas total, IA-pair ≤2h cada), zero deletes em `messages`/`conversations` — só `UPDATE` curativo + defesa de código + Pest regression. Detalhes seção 5.

---

# 1. Estado-da-arte 2026 — 8 concorrentes × 12 dimensões

Tabela compacta. Legenda: **✓** tem nativo · **~** parcial/manual · **✗** não tem documentado · **?** docs não claras.

| Dimensão | Intercom | Front | Take Blip | HubSpot | Twilio Conv. | Zendesk SC | Octadesk | Crisp |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| **1. Auto-create silencioso 1ª inbound** | ✓ cria lead | ✓ contato auto | ✓ overwrite | ~ via Operations Hub | ✓ Identity Resolution | ~ duplica por design ([Knots app](https://knots.io/zendesk-apps/merge-duplicate-whatsapp-users/)) | ✓ | ✓ |
| **2. Nome inicial (push_name)** | ✓ WhatsApp profile | ~ | ~ comunidade reporta `{{contact.name}}` vazio | ~ workaround | ✓ via participant attrs | ✓ se vier | ✓ | ✓ |
| **3. Normalização E.164 (libphonenumber)** | ✓ | ? | ~ identifier `+E164@wa.gw.msging.net` | ✓ Operations Hub + SPNF | ✓ pré-validado Verify | ✓ | ✓ | ✓ |
| **4. Dedup phone (estratégia)** | ✓ **opt-in** "Identify existing users" — flag desligada por default | ✗ docs não claros | ~ overwrite via identifier | ~ Operations Hub batch | ✓ profile linking proativo | ✗ "doesn't match by phone" oficial | ✓ | ✓ |
| **5. Multi-Device / LID handling** | (cloud BSP — não sofre) | (cloud) | (cloud) | (cloud) | (cloud) | (cloud) | (cloud) | (cloud) |
| **6. Merge contacts (auto/manual)** | ✓ API REST + merge automático opt-in | ✗ | ~ | ✓ retain + overwrite | ✓ programmatic merge | ✓ surviving + discarded IDs | ✓ | ✓ |
| **7. Source attribution** | ✓ "WhatsApp" channel | ✓ | ✓ | ✓ source property | ✓ channel per participant | ✓ externalId per channel | ✓ | ✓ |
| **8. Mass-update protection** | ? | ? | ? | ✓ Operations Hub audit | ✓ identity events | ✓ merge audit | ? | ? |
| **9. Anti-cross-contact ambiguity** | ✓ "duplicate? apply to most recent" | ✗ | ? | ~ confidence score Operations Hub | ✓ proactive linking | ✗ (default: cria duplicado, deixa atendente) | ? | ? |
| **10. Janela 24h Meta** | ✓ template fallback | ✓ template | ✓ HSM | ✓ | ✓ | ✓ | ✓ | ✓ |
| **11. LGPD / opt-in / right-to-erase** | ✓ GDPR ready | ✓ | ✓ | ✓ | ✓ | ✓ | ~ BR | ✓ |
| **12. Performance (cache LID→phone)** | (não aplica — Meta resolve) | (não aplica) | (não aplica) | (não aplica) | (não aplica) | (não aplica) | (não aplica) | (não aplica) |

**Lições centrais que vou roubar:**

- **Intercom** ([docs](https://www.intercom.com/help/en/articles/9881312-using-whatsapp-as-a-channel)): merge automático **opt-in com flag** — default cria lead novo. Sensato: cross-contact por dedup excessivo é tão ruim quanto ghost contact por dedup-zero.
- **Twilio Identity Resolution** ([docs](https://www.twilio.com/docs/conversations/memory/identity-resolution)): a melhor prática documentada é **proativa** (upload de profiles antes da 1ª msg) — mas tem fallback reativo: phone+ProxyAddress identifica unicamente um participant. **Match phone EXACT, não fuzzy.**
- **Zendesk Sunshine Conversations** ([incident 2023](https://support.zendesk.com/hc/en-us/articles/6019428389274-Service-Incident-August-9-2023-Sunshine-Conversations-All-Pods-Brazil-WhatsApp-ticket-user-duplication)): incidente público de **duplicação WhatsApp BR específica** — eles oficialmente **NÃO** matcham por phone (deixa atendente decidir + app de 3rd-party [Knots](https://knots.io/) preenche o gap). Filosofia: "errar duplicando é menos pior que cross-contact".
- **HubSpot** ([community](https://community.hubspot.com/t5/HubSpot-Native-Apps/WhatsApp-Business-API-integration/td-p/1167154)): admite que sem normalização **prévia** ao matching (libphonenumber-js), o problema BR (9º dígito móvel pós-2010, falta dele em phones legacy) é insolúvel pelo CRM puro. **A normalização precisa rolar ANTES do match, não DEPOIS.**
- **Crisp / Take Blip BR** ([Blip community](https://community.blip.ai/duvidas-e-perguntas-4/obter-o-contact-name-na-mensagem-ativa-766)): `push_name` chega frequentemente vazio em ativos. **Não dá pra confiar nele como source-of-truth de nome** — só como dica inicial.
- **Baileys 6.7.9 = vetor de risco** ([issue #1554](https://github.com/WhiskeySockets/Baileys/issues/1554), [#1605](https://github.com/WhiskeySockets/Baileys/issues/1605), [#1832](https://github.com/WhiskeySockets/Baileys/issues/1832), [#2030](https://github.com/WhiskeySockets/Baileys/issues/2030), [#2263](https://github.com/WhiskeySockets/Baileys/issues/2263)): **dezenas de issues abertas** confirmam LID per-chat (não per-pessoa), `lid-mapping.update` que nunca dispara, mismatch incoming `@lid` vs outgoing `@s.whatsapp.net`. **Baileys 7.x → migração inevitável** ([migration guide](https://baileys.wiki/docs/migration/to-v7.0.0/)) — exige auth state com lid-mapping + device-list + tctoken keys nativos.

---

# 2. Diagnóstico oimpresso — onde estamos por dimensão (com evidência)

| Dim. | Estado-da-arte | oimpresso hoje | Nota | Evidência |
|---|---|---|---|---|
| 1. Auto-create silencioso | merge opt-in / lead default | ✓ `Conversation::firstOrCreate(customer_external_id)` cria silencioso | 7/10 | [persister:145-161](../../Modules/Whatsapp/Services/Webhook/MessagePersister.php#L145) |
| 2. Nome inicial | push_name + fallback E.164 | ✓ `pushName ?: $customerExternalId` + não sobrescreve curado | 8/10 | [persister:154,163-167](../../Modules/Whatsapp/Services/Webhook/MessagePersister.php#L154) |
| 3. Normalização E.164 | libphonenumber recomendado | ~ regex `preg_replace('/\D+/','')` apenas. **Sem detecção 9º dígito BR.** Sem libphonenumber. | 3/10 | [linker:150-153](../../Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php#L150) |
| 4. Dedup phone | exact match preferido + opt-in | ✗ **fuzzy LIKE `%tail4%`** (4 últimos dígitos) — falso positivo garantido em ROTA LIVRE (vestuário, ~80 contacts) e fatal em biz com 10k+ | 1/10 | [linker:312-325](../../Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php#L312) |
| 5. Multi-Device/LID | (cloud não sofre); Baileys 7.x tem nativo | ~ `LidPhoneResolver` workaround, cache 24h, audit `LidPhoneMap` — bom design mas **assume 1 LID = 1 pessoa** (errado: 1 LID = 1 chat) | 4/10 | [resolver:37-202](../../Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php) |
| 6. Merge contacts | API auto + UI manual | ✗ **nenhuma feature de merge** — atendente troca contact_id via modal mas não consolida duplicados (`WAGNER` ×4 no biz=1) | 0/10 | grep `merge.*contact` em `Modules/Whatsapp` = 0 hits |
| 7. Source attribution | channel name + tags | ~ `messages.provider` (whatsapp_baileys/meta/zapi) mas **Contact não carrega** origem | 3/10 | Contact UltimatePOS sem `source`/`origin_channel` |
| 8. Mass-update protection | audit + confidence | ✗ **drift Tier 0 cometido 14/mai 08:40** — 13 rows manuais sem rastro git/sessão (sessão incident registra) | 2/10 | [incident:25,46-48](2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md) |
| 9. Anti-cross-contact ambiguity | "duplicate? most recent" + warn | ~ `log warning ambiguous` mas **linka primeiro mesmo assim** sem confidence score | 3/10 | [linker:237-246](../../Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php#L237) |
| 10. Janela 24h Meta | rastreado | ~ `last_inbound_at` rastreia mas sem warning UI pré-expiração | 5/10 | [persister:215](../../Modules/Whatsapp/Services/Webhook/MessagePersister.php#L215) |
| 11. LGPD opt-in | GDPR + erasure | ~ `Contact::canReceiveWhatsappNotification()` existe (FSM proibição) — mas **só outbound**. Auto-cadastro inbound não pede opt-in | 4/10 | [proibicoes:Mail::raw](../../memory/proibicoes.md) |
| 12. Performance cache | (não aplica cloud) | ✓ `Cache::remember TTL 24h` no LidPhoneResolver + `attempt_link cache 1h` no Linker | 9/10 | [resolver:72-85](../../Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php#L72) |

**Total ponderado:** 38/100 (peso P0=2 nas dimensões 4,5,8,9; demais peso 1).

**Onde já batemos o mercado:**
- **Tier 0 multi-tenant** (ADR 0093) — Intercom/HubSpot/Twilio dependem de account isolation do tenant deles; nós aplicamos `business_id` global scope em CADA query. Vantagem real.
- **Idempotência forte** (`provider_message_id` UNIQUE) — Zendesk SC teve incident público em 2023 por falha disso.
- **Audit append-only** (`LidPhoneMap.first_seen_at`/`last_seen_at`) — Intercom/Front não documentam equivalente.

---

# 3. Top 10 gaps priorizados (impacto × esforço — calibrado ADR 0106 fator 10x IA-pair)

| # | Gap | Impacto (1-10) | Esforço IA-pair | Quem ganha | Pré-req? |
|---|---|---|---|---|---|
| **P0-1** | `ConversationContactLinker` fuzzy `tail4` LIKE → trocar por **exact phone digits match** + suffix mín 8 dígitos | **10** (causa raiz incidente Wagner) | S (1-2h) | Wagner + Larissa + futuro | nenhum |
| **P0-2** | `LidPhoneResolver::record(source=manual)` aceita sem evidência webhook prévia → defender (já há diff pronto no incident) | **10** (causa raiz mapping ad-hoc 13 rows) | XS (30min) | Wagner | nenhum |
| **P0-3** | `MessagePersister` não consulta `LidPhoneResolver` no path history-sync — adicionar consulta + log auditoria | **9** (drift entre 2 paths) | S (1h) | Wagner | nenhum |
| P1-4 | Normalização E.164 BR — adicionar `propaganistas/laravel-intl-phone-input` ou implementar mini-helper `BrPhoneNormalizer` (detecta 9º dígito móvel, DDI 55 implícito, strip 0 trunk) | 7 | S (2h) | Larissa (Contacts legacy mistos) | nenhum |
| P1-5 | UI **merge contacts** — modal `/contacts/{id}/merge?candidate={other_id}` no UltimatePOS Vue + backend `ContactMergeService` que move `transactions`/`messages`/`conversations`/`reminders` pro surviving Contact | 7 | M (4-6h) | Wagner (4 Wagners), Larissa (futuro) | nenhum |
| P1-6 | Add `Contact.source_channel` + `Contact.created_via_message_id` — atribuição auto-cadastro | 6 | S (1h) | analytics + auditoria | migration |
| P1-7 | `whatsapp:lid-map:audit` artisan command + cron daily — detecta `source=manual` SEM companion `source=webhook_senderPn` row + alerta Loki | 7 | S (1-2h) | governança Tier 0 | nenhum |
| P1-8 | Métrica OTel `whatsapp_lid_resolver_returned_phone_count` — picos anômalos cross-contact bug | 6 | XS (30min) | observabilidade | OTel já presente (US-WA-085) |
| P2-9 | "Pending verification" badge na conv quando `contact_id=null` E `customer_external_id` é `@lid` ou `+phone-sem-match` — UX clareza | 5 | S (1-2h) | atendente | merge UI (P1-5) |
| P2-10 | **Baileys 7.x migration** — auth state com `lid-mapping`+`device-list`+`tctoken` nativos. Issues confirmam fix definitivo upstream | 8 | L (8-12h) | Wagner + futuro | runbook re-pairing + canary 7d |

---

# 4. ROI estimado (top 3 P0 fechados)

| Métrica | Hoje | Pós-fix top 3 | Impacto |
|---|---|---|---|
| Incidentes inbox cross-contact / mês | **1** (Wagner-Eliana 14/mai) — primeiro mas latente desde 12/mai | **0** projetado | -100% |
| Tempo atendente vincular contact manualmente | ~30s/conv × 32 convs orfãs biz=1 = 16min/mês | ~0 (auto-link confiável) | -90% |
| % Contacts ricos vs ghost no CRM (biz=1) | Estimado 60% ghost (`name=+E.164`) | 80%+ (push_name + source attribution P1-6) | +20pp |
| Drift Tier 0 (rows manuais sem trail) | 14 rows acumuladas 12-14/mai | 0 — defesa de código bloqueia | -100% |
| Confiança Wagner no Inbox | quebrada (incident hoje) | restaurada se 3 patches landam | qualitativa P0 |

---

# 5. Proposta concreta de patch — top 3 P0

> ⛔ **Zero `DELETE` em `messages` ou `conversations`.** Tudo é `UPDATE` curativo + defesa código. Wagner: "nunca perca mensagem".

## Patch 1 (P0-1) — `ConversationContactLinker` fuzzy → exact

**Arquivo:** `Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php`

```diff
@@ -293,18 +293,15 @@ class ConversationContactLinker
         // Suffix dos últimos 8 dígitos pra match BR ("48999872822") vs E.164
         // ("+5548999872822"). DDD+phone é único o suficiente no BR.
         $suffix = mb_substr($phoneDigits, -8);
-        // Tail mais curto (5 últimos dígitos) usado APENAS no SQL pre-fetch
-        // pra capturar formatos legados com separadores entre dígitos —
-        // ex: "(48) 99872-2822" tem `2822` literal mas não `99872822`
-        // sem-separador. Filtragem fina rola em PHP depois (normaliza)
-        // então o pre-fetch pode ser fuzzy sem custo de correção.
-        $tail = mb_substr($phoneDigits, -5);
+        // INCIDENT 2026-05-14: tail4 LIKE causou cross-contact Wagner→Eliana
+        // (4 dígitos coincidem por puro acaso a cada 10k phones). Removido.
+        // Pre-fetch agora exige suffix mínimo de 8 dígitos no LIKE — colisão
+        // por coincidência cai pra ~10^-8 (8 dígitos aleatórios).
@@ -311,18 +308,15 @@
-        $tail4 = mb_substr($phoneDigits, -4);
         $candidates = Contact::query()
             ->withoutGlobalScope(ScopeByBusiness::class)
             ->where('business_id', $conversation->business_id)
-            ->where(function ($q) use ($phoneDigits, $tail4) {
-                // Match direto E.164 vs E.164 (caso bonito)
+            ->where(function ($q) use ($phoneDigits, $suffix) {
+                // Match direto E.164 vs E.164 OU suffix 8 dígitos (formato com separadores)
                 $q->where('mobile', 'LIKE', '%' . $phoneDigits . '%')
                   ->orWhere('landline', 'LIKE', '%' . $phoneDigits . '%')
                   ->orWhere('alternate_number', 'LIKE', '%' . $phoneDigits . '%')
-                  // Match fuzzy 4 últimos dígitos pra pegar formatos com
-                  // separadores (PHP filter elimina falsos positivos)
-                  ->orWhere('mobile', 'LIKE', '%' . $tail4)
-                  ->orWhere('landline', 'LIKE', '%' . $tail4)
-                  ->orWhere('alternate_number', 'LIKE', '%' . $tail4);
+                  ->orWhere('mobile', 'LIKE', '%' . $suffix . '%')
+                  ->orWhere('landline', 'LIKE', '%' . $suffix . '%')
+                  ->orWhere('alternate_number', 'LIKE', '%' . $suffix . '%');
             })
```

**E aplicar mesma mudança em `findMatchesForPhone()` linha 167-180 (gêmeo do `findMatches()`).**

**Test Pest regression (esqueleto):**

```php
<?php
// Modules/Whatsapp/Tests/Feature/LinkerExactMatchNoTail4Test.php

use App\Contact;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;

it('NÃO linka contact com tail4 coincidente (regression 2026-05-14 cross-contact)', function () {
    // biz=1 com 2 contacts: Wagner (mobile ...2822) e Eliana (alternate ...2822)
    $wagner = Contact::factory()->create([
        'business_id' => 1,
        'name' => 'WAGNER',
        'mobile' => '+554899999XXXX', // último 4 ≠ 2822
    ]);
    $eliana = Contact::factory()->create([
        'business_id' => 1,
        'name' => 'ELIANA',
        'alternate_number' => '+554899872 2822', // contém 2822 mas suffix 8 ≠ Wagner
    ]);

    // Conv do Wagner real (+554899987 2822 — suffix 8 = 99872822, diferente)
    $conv = Conversation::factory()->create([
        'business_id' => 1,
        'customer_external_id' => '+5548999872822',
        'contact_id' => null,
    ]);

    app(ConversationContactLinker::class)->tryLink($conv);
    $conv->refresh();

    // NÃO deve linkar à Eliana (tail4 2822 batia, suffix8 99872822 não bate com 99872 2822→987222822)
    expect($conv->contact_id)->not->toBe($eliana->id);
});

it('linka exact match phone E.164 sem ambiguidade', function () {
    $contact = Contact::factory()->create([
        'business_id' => 1, 'mobile' => '+5548999872822',
    ]);
    $conv = Conversation::factory()->create([
        'business_id' => 1,
        'customer_external_id' => '+5548999872822',
        'contact_id' => null,
    ]);

    app(ConversationContactLinker::class)->tryLink($conv);
    expect($conv->fresh()->contact_id)->toBe($contact->id);
});
```

## Patch 2 (P0-2) — `LidPhoneResolver::record(source=manual)` exige webhook prévio

**Já pronto no incident** (sessão `2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md` linha 139-169). Aplicar diff lá descrito + Pest test idem (linhas 204-251 do incident).

## Patch 3 (P0-3) — `MessagePersister` consulta `LidPhoneResolver`

**Arquivo:** `Modules/Whatsapp/Services/Webhook/MessagePersister.php` linhas 72-77

```diff
@@ -72,11 +72,28 @@
         $resolvedJid = ($senderPn && str_contains($senderPn, '@s.whatsapp.net'))
             ? $senderPn
             : $remoteJid;
         $rawNumber = preg_replace('/@.+$/', '', $resolvedJid);
         $customerExternalId = '+' . $rawNumber;
 
+        // INCIDENT 2026-05-14 P0-3: history_sync path ANTES ignorava o
+        // LidPhoneResolver (só o controller real-time consultava). Resultado:
+        // 81 msgs do Wagner caíram no contato Eliana pois MessagePersister
+        // não trocava o LID pelo phone real cacheado. Espelha o controller
+        // [ChannelBaileysWebhookController:289-312] mas com log auditoria
+        // extra (path history vs real-time tem riscos distintos).
+        if (is_string($remoteJid) && str_contains($remoteJid, '@lid')) {
+            $resolver = app(\Modules\Whatsapp\Services\Contacts\LidPhoneResolver::class);
+            $cached = $resolver->resolve($this->channel->business_id, $remoteJid);
+            if ($cached !== null && $cached !== $customerExternalId) {
+                \Log::info('[whatsapp.persister.lid_resolved_to_different_phone]', [
+                    'business_id' => $this->channel->business_id,
+                    'lid_prefix' => substr(preg_replace('/@.+$/','',$remoteJid), 0, 6) . '...',
+                    'is_history_sync' => $data['is_history_sync'] ?? false,
+                ]);
+                $customerExternalId = $cached;
+            } elseif ($cached === null) {
+                // Histórico ENCONTROU LID novo sem mapping — REGISTRA sem phone
+                // (mesma semântica do controller real-time caso b).
+                $resolver->record($this->channel->business_id, $remoteJid, null);
+            }
+        }
```

**Pest regression:**

```php
<?php
// Modules/Whatsapp/Tests/Feature/MessagePersisterUsesLidResolverTest.php

it('MessagePersister history-sync trocou LID pelo phone cacheado', function () {
    // Arrange: LID já cacheado pra phone real via webhook anterior
    $channel = Channel::factory()->baileys()->create(['business_id' => 1]);
    app(LidPhoneResolver::class)->record(1, '14628809617558@lid', '+5548999000000');

    // Act: history_sync entrega msg com remoteJid=@lid (sem senderPn)
    $persister = new MessagePersister($channel);
    $result = $persister->persist([
        'key' => ['remoteJid' => '14628809617558@lid', 'id' => 'ABC123', 'fromMe' => false],
        'message' => ['conversation' => 'Oi do histórico'],
        'is_history_sync' => true,
    ], bumpUnread: false);

    // Assert: Conversation NÃO foi criada com customer_external_id=LID raw
    expect($result->conversation->customer_external_id)->toBe('+5548999000000');
});

it('history-sync sem cache REGISTRA LID novo com phone=null pra rastreio', function () {
    $channel = Channel::factory()->baileys()->create(['business_id' => 1]);

    $persister = new MessagePersister($channel);
    $persister->persist([
        'key' => ['remoteJid' => '99999999999999@lid', 'id' => 'DEF456', 'fromMe' => false],
        'message' => ['conversation' => 'Hello LID novo'],
        'is_history_sync' => true,
    ], bumpUnread: false);

    expect(LidPhoneMap::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('lid', '99999999999999')
        ->exists())->toBeTrue();
});
```

## SQL recovery (zero perda de mensagem)

Já descrito no incident sessão 14/mai linhas 76-122. **Não duplico aqui.** Curativo é:
- `UPDATE whatsapp_lid_pn_map.id=1 SET phone_e164=NULL` (anula mapping ofensivo, preserva row pra rastreio)
- `UPDATE conversations.id=37 SET contact_id=NULL, contact_name='+14628809617558 (LID não resolvido)'` (desassocia Eliana, **mensagens permanecem todas em conv 37**)
- `Cache::forget` keys LID dos 14 mappings
- Wagner identifica manualmente quem é a contraparte → re-link com UPDATE pontual

**Nada deletado. Tudo preservado.**

---

# 6. Decisões pra Wagner aprovar (yes/no, sem detalhe-pequeno)

1. **Sim/não:** Aplico 3 patches P0 (Patch 1+2+3 acima) **em PR único** com 3 commits separados + 3 Pest test files? Estimativa total ≤300 linhas, ≤2h IA-pair.
2. **Sim/não:** Crio US-WA-094 "Anti-cross-contact P0 hardening" rastreando esses 3 patches + ADR-mãe leve "1 LID ≠ 1 pessoa em Baileys 6.7.9 — defense-in-depth até Baileys 7.x" (status `aceito`, supersedes nenhum, amends US-WA-078)?
3. **Sim/não:** Você consegue confirmar (pelo celular pareado, abrindo conv real no WhatsApp app) **quem é a contraparte das 81 mensagens 18:39-18:40 14/mai** antes de eu propor o re-link em conv #37? Sem essa confirmação, deixo conv #37 com `contact_id=NULL` + nome "número oculto".
4. **Sim/não:** Aceito promover P1-5 (UI merge contacts) pra próximo sprint? 4 contacts "Wagner" no biz=1 vão continuar incomodando até consolidar — não bloqueia P0 mas é o próximo P1 natural.
5. **Sim/não:** Aceito que P2-10 (Baileys 7.x migration) entra no backlog **com sinal qualificado** apenas — se top 3 P0 + audit cron P1-7 reduzirem incidentes pra 0, não vale ainda; só quando volume biz crescer ou bug Baileys 6.7.9 voltar (cliente como sinal — [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))?

---

# 7. Referências (Sources)

- [Intercom — Using WhatsApp as a channel](https://www.intercom.com/help/en/articles/9881312-using-whatsapp-as-a-channel)
- [Intercom Deduplication via Insycle](https://www.insycle.com/intercom/deduplication/)
- [Twilio Identity Resolution](https://www.twilio.com/docs/conversations/memory/identity-resolution)
- [Twilio Conversations — WhatsApp participants](https://www.twilio.com/docs/conversations/using-whatsapp-conversations)
- [Zendesk SC — duplicate WhatsApp users](https://whatsappconnector.zendesk.com/hc/en-gb/articles/41317998219795-Managing-duplicated-end-users-and-tickets-from-WhatsApp-conversations)
- [Zendesk SC — incident BR WhatsApp duplication 2023](https://support.zendesk.com/hc/en-us/articles/6019428389274-Service-Incident-August-9-2023-Sunshine-Conversations-All-Pods-Brazil-WhatsApp-ticket-user-duplication)
- [HubSpot community — WhatsApp BR phone normalization](https://community.hubspot.com/t5/HubSpot-Native-Apps/WhatsApp-Business-API-integration/td-p/1167154)
- [HubSpot — phone validation E.164](https://knowledge.hubspot.com/properties/phone-number-property-validation)
- [Front — Omnichannel + WhatsApp](https://front.com/integrations/whatsapp)
- [Take Blip — Saving contact name WhatsApp](https://help.blip.ai/hc/en-us/articles/5817133358871-Saving-contact-name-from-WhatsApp-Broadcast)
- [Blip community — contact.name vazio em ativos](https://community.blip.ai/duvidas-e-perguntas-4/obter-o-contact-name-na-mensagem-ativa-766)
- [Octadesk — 360dialog WhatsApp setup](https://kb.octadesk.com/docs/whatsapp-oficial-ativacao-numero)
- [Crisp — WhatsApp Business API quickstart](https://docs.crisp.chat/guides/messaging-apis/whatsapp-api/quickstart/)
- [Baileys issue #1554 — LID resolution](https://github.com/WhiskeySockets/Baileys/issues/1554)
- [Baileys issue #1605 — LID em chats 1:1 privados](https://github.com/WhiskeySockets/Baileys/issues/1605)
- [Baileys issue #1832 — mismatch @lid incoming vs @s.whatsapp.net outgoing](https://github.com/WhiskeySockets/Baileys/issues/1832)
- [Baileys issue #2263 — lid-mapping.update never fires](https://github.com/WhiskeySockets/Baileys/issues/2263)
- [Baileys 7.x migration guide](https://baileys.wiki/docs/migration/to-v7.0.0/)
- [Brazilian phone numbers — 9th digit rules](https://en.wikipedia.org/wiki/Telephone_numbers_in_Brazil)
- [libphonenumber-js — E.164 normalization](https://github.com/floere/phony)

---

**Próxima ação concreta hoje:** se Wagner aprova decisão 1, abro PR com 3 commits (1 patch each + Pest) em `claude/wa-anti-cross-contact-p0`. Estimativa 2h IA-pair, ≤300 linhas total. Recovery SQL fica para Wagner rodar após responder decisão 3 (confirmar quem é a contraparte das 81 msgs).
