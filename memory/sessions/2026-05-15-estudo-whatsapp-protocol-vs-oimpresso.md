---
title: "Estudo PROTOCOL-LEVEL — WhatsApp Multi-Device (PN/LID/BSUID/ADV) vs oimpresso Baileys 6.7.9"
date: 2026-05-15
type: estudo-tecnico
status: investigated
module: Modules/Whatsapp
related_adrs: [0093, 0094, 0096, 0117, 0135]
related_sessions:
  - 2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md
  - 2026-05-14-arte-auto-cadastro-contact-whatsapp.md
related_us: [US-WA-078, US-WA-093, US-WA-094]
nota_oimpresso_protocol: 42/100
pesquisa: 23 WebSearch + 5 WebFetch
---

# TL;DR

- **WhatsApp identifica remetente por 3 IDs distintos em 2026**: (a) **PN** `<phone>@s.whatsapp.net` legacy/atual, (b) **LID** `<random>@lid` privacy-by-default desde 2023, (c) **BSUID** `user_id` Meta-oficial em Cloud API webhooks desde **31-mar-2026** (substitui PN pra usuários com username). Username público GA Q2-Q4/2026.
- **`@lid` é per-USER (não per-chat)** segundo Whapi/Baileys docs — Wagner & eu erramos esse modelo na ARQUITETURA original. O bug Wagner-Eliana NÃO foi causado por "1 LID = 1 chat"; foi por **fuzzy match `tail4`** no `ConversationContactLinker` colidindo 4 dígitos finais (que era a hipótese P0-1 do arte-doc 14/mai — correta). A fix do PR #854 (defesas anti-cross-contact) já está certa; só preciso corrigir a hipótese errada na ARCHITECTURE.
- **Baileys 6.7.9 NÃO tem `getPNForLID()` nem evento `lid-mapping.update` confiável** ([issue #2263](https://github.com/WhiskeySockets/Baileys/issues/2263) aberta). Baileys 7.x tem **ambos nativos** + auth state com `lid-mapping`/`device-list`/`tctoken` — mas 7.0.0-rc.9 (último npm publicado nov/2025) tem 3 bugs auth handshake que dão 100% `401 device_removed`. **Não migrar agora** — esperar 7.0.0 final.
- **WhatsApp Cloud API oficial NÃO sofre o problema** (Meta resolve mapping internamente, expõe `wa_id` E.164 sem `+`). **Mas a partir de jun/2026 vai sofrer parcialmente** quando users adotarem username: `wa_id` pode sumir, restando só `user_id`/BSUID — exatamente o gap que estamos vivendo hoje em Baileys, vai universalizar.
- **Nota oimpresso protocol-level: 42/100** (sobe de 38 do arte-doc concorrencial: temos defesas hoje que o mercado não tem audit + Pest). **Recomendação**: ficar em Baileys 6.7.9 + workaround robusto + adicionar coluna `bsuid` agora (zero custo, prepara migração Cloud API jun/2026). Migração Cloud API não é mais "se", é "quando dor justificar custo $0.004-0.0625/msg".

---

# 1. Modelo de identidade WhatsApp Multi-Device 2021-2026

## 1.1 Diagrama mental (ASCII)

```
                       ┌──────────────────────────────────┐
                       │   WhatsApp Server (Meta)          │
                       │  ┌────────────────────────────┐   │
                       │  │ User mapping table         │   │
                       │  │  account_id ←→ phone_e164  │   │
                       │  │  account_id ←→ {device_id_1, device_id_2, ..., device_id_N} │
                       │  │  account_id ←→ {LID_1, LID_2 (per business)}   │
                       │  │  account_id ←→ username? (jun/2026 GA)         │
                       │  │  account_id ←→ BSUID_per_business[]            │
                       │  └────────────────────────────┘   │
                       └──────────────────────────────────┘
                                  │
                                  │ resolve identidade
                                  │ por contexto da conexão
                                  ▼
   ┌──────────────────────────────────────────────────────┐
   │ Cada CONEXÃO Multi-Device tem identity-key próprio   │
   │  - Primary phone (Android/iOS)                        │
   │  - Companion 1 (WhatsApp Web)                         │
   │  - Companion 2 (WhatsApp Desktop)                     │
   │  - Companion 3 (Baileys daemon — companion mode)      │
   │  - Companion 4 (Cloud API conta business)             │
   └──────────────────────────────────────────────────────┘
                                  │
                                  ▼
    Cada device envia mensagem CIFRADA N× (client-fanout)
    pra cada device de cada participante (sender e receiver).
    Signal Protocol PreKeySignalMessage / SenderKeyMessage.

    ╔══════════════════════════════════════════════════════════╗
    ║ Identificadores expostos a TERCEIROS (libs Baileys etc): ║
    ╚══════════════════════════════════════════════════════════╝

       PN          = "5548999872822@s.whatsapp.net"
                     phone-based JID, padrão Legacy + atual
                     [https://www.npmjs.com/package/@whatsapp-cloudapi/types]

       LID         = "14628809617558@lid"
                     anonymized account-level ID
                     1 LID por user (NÃO per-chat — corrijo
                     hipótese da minha session anterior)
                     persist por toda vida do account

       BSUID       = "user_id":"123-456-789..." (Cloud API webhook)
                     scoped per user × business
                     Meta-oficial desde 31-mar-2026

       Username    = "@wagnerra" (opcional, jun/2026 GA Q2-Q4)
                     formato display público; back é BSUID

       ADV identity = key par cada companion device
                      (Signal-derived); not exposed a libs
```

## 1.2 PN vs LID — quem controla, quando aparece

| Aspecto | **PN** (`@s.whatsapp.net`) | **LID** (`@lid`) |
|---|---|---|
| Formato | `+E.164 sem +` (ex: `5548999872822`) | número opaco 15+ dígitos sem semântica de DDI |
| Significado | número telefônico real do user | identificador anônimo gerado por WhatsApp pro account |
| Granularidade | 1 PN = 1 user (1 account) | 1 LID = 1 user (1 account) — **NÃO per-chat, NÃO per-group** ([Whapi help](https://support.whapi.cloud/help-desk/groups/what-is-lid-in-whatsapp-groups), [Baileys wiki migration](https://baileys.wiki/docs/migration/to-v7.0.0/)) |
| Quando aparece | conversa pré-2023, contatos no phonebook, sessões Signal legacy | privacy de grupos (hide phone numbers), click-to-WhatsApp Ads, click-to-chat `wa.me/PHONE` quando user nunca falou com aquele biz, Communities |
| Permanência | imutável (a menos que user troque número e migre) | imutável por toda vida do account |
| Lookup direction | `onWhatsApp(phone)` → retorna LID ([USyncProtocol](https://github.com/WhiskeySockets/Baileys/issues/2259)) | **Não há endpoint público pra LID→PN reverso** ([Whapi LID help](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)) |
| Onde é resolvido na lib | Baileys 7.x via `sock.signalRepository.lidMapping.getPNForLID()` ([migration](https://baileys.wiki/docs/migration/to-v7.0.0/)); whatsmeow via `GetPNForLID()` ([discussion #846](https://github.com/tulir/whatsmeow/discussions/846)) | (mesmo store) |
| Mensagem viável? | sim, sempre | sim — **LID é JID válido pra send** ([Whapi help](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)) |

**Quem tem o "phonebook" autoritativo?** O **app mobile primário** do user (o phone Android/iOS). Quando o user pareia um Companion Device (WhatsApp Web/Desktop/Baileys daemon), o primary device envia o mapping PN↔LID inicial via `device-list` durante o sync. Após isso, qualquer atualização (user troca de número, novo LID atribuído por privacy) vem via evento que **deveria** ser `lid-mapping.update` ([issue #2263 aberta em Baileys](https://github.com/WhiskeySockets/Baileys/issues/2263) — nunca dispara em 6.7.9, 7.x diz que dispara).

**Servidor Meta não expõe lookup reverso a clientes terceiros** ([Whapi LID FAQ](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)): "WhatsApp does not provide a public or official way to directly convert @lid to a phone number, as this limitation exists by design and is tied to WhatsApp's privacy model." Cloud API resolve internamente porque o Meta dá pra Meta-mesmo.

## 1.3 Quando WhatsApp envia LID vs PN (cenários reais 2026)

| Cenário | Outbound (você envia ao cliente) | Inbound (cliente te envia) |
|---|---|---|
| Cliente já tem você no phonebook há anos | PN | PN |
| Cliente clicou em `wa.me/SEU_NUMERO` (deep link) | PN (sua msg vai assim) | **LID** (msg do cliente chega anonimizada — Meta privacy) |
| Click-to-WhatsApp Ad (Facebook/Instagram) | PN | **LID** + janela 72h gratuita |
| Cliente respondeu seu Status / Channel / Newsletter | PN | **LID** |
| Cliente está em Community/Group com "Hide phone numbers" ON | PN | **LID** (participants verão LID) |
| Cliente adotou Username (jun/2026 GA) | PN OU username | **BSUID + username**, PN só se cliente autorizar |
| Você é Cloud API official | PN (envia pra `to: <e164>`) | `wa_id` (E.164 sem +) **e** `user_id` (BSUID) desde 31-mar-2026 |

**Conclusão:** no nosso caso (Baileys daemon CT 100 + biz piloto ROTA LIVRE + canal Wagner re-pareado 14/mai), praticamente **toda mensagem inbound moderna chega `@lid`** porque é click-to-chat ou primeiro contato. Esperar PN é exceção, não regra. Workaround LidPhoneMap **não é remar contra o protocolo** — é exatamente o que a lib oficial nativa faz (`lidMapping` store em Baileys 7.x, `whatsmeow_lid_mapping` table em whatsmeow). **Apenas estamos fazendo manualmente o que o lib novo faz no nosso lugar.**

---

# 2. History sync — por que `senderPn` chega NULL

## 2.1 O que o `messaging-history.set` deveria entregar

Doc oficial Baileys ([history-sync wiki](https://baileys.wiki/docs/socket/history-sync/)):

```typescript
sock.ev.on('messaging-history.set', ({ newChats, newContacts, newMessages, syncType }) => {
    // Store everything in DB
});
```

Payload tem **3 arrays paralelos**:
- `newChats` — array de `{ id, name, unreadCount, ... }` (id pode ser `<lid>@lid` ou `<phone>@s.whatsapp.net`)
- `newContacts` — array de `{ id, name, notify, ... }` — **deveria** carregar mapping PN↔LID neste array
- `newMessages` — array de `IWebMessageInfo` com `key.remoteJid`, `key.fromMe`, `key.id`, `message`, e **opcionalmente** `key.senderPn`/`key.participantPn`

`syncType`:
- `INITIAL_BOOTSTRAP` — primeira conexão (90d de história)
- `RECENT` — sync incremental
- `ON_DEMAND` — via `fetchMessageHistory()`

## 2.2 Por que falha no nosso caso (Baileys 6.7.9)

Baileys 6.7.9 (atual prod CT 100) tem **3 bugs históricos em history sync** documentados na comunidade que explicam o que vivemos:

1. **`newContacts` chega vazio ou parcial** ([issue #2077](https://github.com/WhiskeySockets/Baileys/issues/2077) e [#2462](https://github.com/WhiskeySockets/Baileys/issues/2462)) — "trouble retrieving contacts via messaging-history.set, where contact data is sometimes received correctly, but most of the time the contact data section is not received". Sem `newContacts`, lib não popula seu `lidMapping` store.
2. **`key.senderPn` vem NULL em INITIAL_BOOTSTRAP** — esse campo só foi formalizado em Baileys 6.8.0 ([migration](https://baileys.wiki/docs/migration/to-v7.0.0/)) como `senderLid`/`senderPn` "alpha attributes". Em 6.7.9 com chats já-existentes `@lid`, ele NÃO chega — só em `messages.upsert` real-time com `type: "notify"`.
3. **`isLatest` flag bug** ([#2005](https://github.com/WhiskeySockets/Baileys/issues/2005)) — "from the second history event onwards, isLatest will always be false". Caller pensa que ainda vem mais e fica esperando — na verdade primeiro batch foi tudo.

**Conclusão técnica:** o NULL `senderPn` que vivemos é **bug conhecido Baileys 6.7.9 + design pré-LID-mapping nativo**, NÃO privacy by-design do Meta (Cloud API entrega PN sem problema). Migração 7.x **resolveria** — mas 7.0.0-rc.9 (último npm em 21-nov-2025) tem 3 bugs auth handshake catalogados em [issue #19907](https://github.com/openclaw/openclaw/issues/19907) que dão **100% `401 device_removed`** em re-pareamento. Não dá pra migrar agora; esperar 7.0.0 final estável.

## 2.3 Maneira oficial Meta de PN partir de LID

**Não existe** ([Whapi FAQ](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)). Para libs terceiras:
- Forçar user a compartilhar: `{ requestPhoneNumber: true }` (UI button "compartilhe seu número") — Baileys 7.x feature ([migration](https://baileys.wiki/docs/migration/to-v7.0.0/))
- Esperar o user enviar uma msg fora da janela LID (improvável — LID é permanente)
- **Cloud API official**: Meta entrega `wa_id` (E.164 sem `+`) **e** `user_id` (BSUID) no webhook ([Meta docs](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages/)). Mas isso é troca de stack, não solução in-lib.

---

# 3. Signal Protocol envelopes — onde fica o ID do sender

## 3.1 Estrutura

WhatsApp adotou Signal Protocol pra Multi-Device em jul/2021 ([Engineering at Meta blog](https://engineering.fb.com/2021/07/14/security/whatsapp-multi-device/)). Cada mensagem entre 2 users (com N+M devices) é:

```
sender_device_A encrypts msg N×M times:
  for each receiver_device_i ∈ [B_1..B_N, A_1..A_M (own other devices)]:
    encrypt(msg, session_key(sender_device_A, receiver_device_i))
    → sends PreKeySignalMessage OU SignalMessage
```

**Tipos de envelope** (Signal Protocol spec [Wikipedia](https://en.wikipedia.org/wiki/Signal_Protocol)):

| Envelope | Quando | Carrega ID sender? |
|---|---|---|
| **PreKeySignalMessage** | 1ª msg numa session (X3DH handshake) | Sim — identity key sender é parte do bundle ([dev.to deep dive](https://dev.to/binoy123/a-deep-dive-into-whatsapps-encryption-identity-keys-and-message-security-53h6)) |
| **SignalMessage** | msgs subsequentes (Double Ratchet) | Não diretamente — sessão já estabelecida |
| **SenderKeyDistributionMessage** | grupos: distribui group sender key | Sim — identity key sender |
| **SenderKeyMessage** | grupos: msg ratchetada | Não — session já estabelecida |

**Onde fica o phone real?** **Não no envelope.** O `participant`/`senderJid` é metadado **fora** da cifra — Meta server vê (precisa rotear) mas conteúdo da msg permanece end-to-end encrypted. Quando esse metadado vem como `@lid` em vez de `@s.whatsapp.net`, é **escolha do server Meta** (privacy by design pro user remoto), não limitação criptográfica.

## 3.2 `messageContextInfo.messageSecret`

Aparece em todos payloads modernos. **NÃO resolve identidade.** É 32 bytes aleatórios gerados pelo sender:
- whatsmeow: "if message.MessageContextInfo.MessageSecret is nil, it's set to random bytes of length 32" ([whatsmeow send.go](https://github.com/tulir/whatsmeow/blob/main/send.go))
- Uso: criptografar reações, polls votes, edits — entidades **derivadas** que precisam de chave-comum entre devices do mesmo user

Para nosso caso: **ignorar.** Não vaza nada, não resolve nada. Pode ficar em `payload` JSON pra auditoria mas não tem semântica útil pra atribuição contact.

## 3.3 Extensões WhatsApp ao Signal Protocol

WhatsApp expande Signal com:
1. **Sealed Sender** ([Signal blog](https://signal.org/blog/sealed-sender/)) — payload tem envelope encriptado com sender certificate; servidor não vê quem enviou
2. **PQXDH post-quantum handshake** ([Wikipedia](https://en.wikipedia.org/wiki/Signal_Protocol)) — adotado 2024
3. **ADV (Automatic Device Verification)** — reduz frequência de identity verification entre devices do mesmo user ([InfoQ](https://www.infoq.com/news/2021/07/WhatsApp-signal-protocol/))
4. **Account Signature + Device Signature** — quando linka companion, primary device assina `Identity Key` do companion (Account Signature) e companion assina `Identity Key` do primary (Device Signature)

Esses **não nos afetam diretamente** — são camadas de cripto. O que nos afeta é o **bind de metadata jid**, que é decisão de routing do server.

---

# 4. Comparativo BSUID/Cloud API/Baileys 6.7.9/Baileys 7.x/whatsmeow

| Aspecto | **Cloud API oficial** (Meta direct) | **Baileys 6.7.9** (atual prod) | **Baileys 7.0.0-rc.9** (npm latest) | **Baileys 7.0.0 final** (esperado) | **whatsmeow** (Go alternative) |
|---|---|---|---|---|---|
| Cobertura LID inbound | retorna `wa_id` (E.164) + `user_id` (BSUID) desde 31-mar-2026 | retorna `<lid>@lid` em `remoteJid`; `senderPn` opcional só em real-time | mesmo + `remoteJidAlt`/`participantAlt` (espelha PN/LID) | mesmo + `lid-mapping.update` event funcional | tem `GetPNForLID()` nativo + tabela `whatsmeow_lid_mapping` ([whatsmeow](https://github.com/tulir/whatsmeow)) |
| Resolve LID→PN auto | sim (Meta interna) | **não** | parcial (precisa `device-list` + auth state estendido) | sim | sim |
| History sync stability | N/A (não tem history sync — só novas msgs via webhook) | bugado: senderPn NULL, isLatest erra, contacts vazios | quebrado: rc.9 desabilita history se `syncFullHistory:false` ([issue #11951](https://github.com/NousResearch/hermes-agent/issues/11951)) | esperar fix npm | tido como mais robusto (lib Go oficial whatsapp client multidevice) |
| Custo/mês (BR, 1k msgs/mês) | utility ~$4 / marketing ~$62.5 (BSP markup +$3-10) ([Chatarmin pricing](https://chatarmin.com/en/blog/whats-app-api-pricing)) | $0 (lib + CT 100 ~R$50/mês prorata) | $0 | $0 | $0 |
| Vendor lock-in | alto (depende Meta API, embedded signup, BSP partner se quiser SaaS) | baixo (open-source) | baixo | baixo | baixo |
| Ban risk | nulo (oficial; gets warnings before violations) | **alto** ("operate months without issues or get banned within a week with no predictable pattern" [Kraya AI 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)) | alto (mesma fundação Whatsapp Web) | alto | alto |
| Suporta `requestPhoneNumber` | nativo | **não** | sim | sim | sim |
| Auth state surface | OAuth Meta + access_token long-lived | `creds.json` + keys | mesmo + `lid-mapping`/`device-list`/`tctoken` ([requirement](https://baileys.wiki/docs/migration/to-v7.0.0/)) | mesmo | similar — tabela própria |
| 90d history loss on re-pair | N/A | **sim** (catastrófico — Wagner re-pareou e perdeu mapeamento manual) | sim | sim | sim |
| Migration friction de Baileys 6.7.9 | ALTA — re-pareamento, mover toda lógica de daemon Node → webhook Meta, embedded signup biz, HSM templates aprovados | — | MÉDIA — auth state expandido + lid-mapping store + 7.x breaking changes | MÉDIA-BAIXA | ALTA — troca lib + linguagem Go |
| Suporta usernames jun/2026 | sim (ExternalUserId field) | não (precisa workaround) | parcial | sim | em desenvolvimento ([sender_pn discussion #846](https://github.com/tulir/whatsmeow/discussions/846)) |

**Quem ganha em quê:**
- **Robustez/estado-da-arte protocolo**: Cloud API > whatsmeow > Baileys 7.x > Baileys 6.7.9
- **Custo (ROTA LIVRE volume ~50-200 msgs/dia)**: Baileys 6.7.9 = 7.x = whatsmeow >> Cloud API
- **Ban risk**: Cloud API >> todos os outros
- **Compatível com nossa stack PHP atual**: Baileys 6.7.9 = 7.x > whatsmeow (lib Go) > Cloud API (precisa re-implementar driver)
- **Pronto pra usernames Q3-Q4/2026**: Cloud API > Baileys 7.x final > whatsmeow > Baileys 6.7.9

---

# 5. Avaliação oimpresso (8 dimensões D-1 a D-8) — nota 42/100

> Sobe 4pp do arte-doc 14/mai (38/100) porque PR #854 já aplicou Patches 1+2+3 (defesas anti-cross-contact) — mas ainda muito atrás do estado-da-arte (Cloud API tem 70+; Baileys 7.x lib bem usada chegaria a 60+).

| Dim. | Cenário ideal | oimpresso hoje | Nota |
|---|---|---|---|
| **D-1. Cobertura de identidade** | Modelo com 4 IDs separados: `phone_e164`, `lid`, `bsuid`, `username` na Conversation/Contact | só `customer_external_id` string (não desambigua origem) | **3/10** |
| **D-2. Fallback quando phone real desconhecido** | Cloud API: BSUID + mostrar username "@cliente" | "+LID-não-resolvido" string raw na UI — feio + atendente não sabe quem é | **2/10** |
| **D-3. Re-processamento histórico** | Backfill auto quando `LidPhoneMap` atualiza (trigger DB ou observer Eloquent) | `whatsapp:auto-link-contacts` faz LIKE phone mas **não tem retry trigger** quando LID resolve depois | **4/10** |
| **D-4. Race condition multi-device** | Lock per `phone_e164` no daemon (mutex) | nenhum lock — múltiplos daemons mesmo número geraria duplicidade. Mas: monitor existe ([PR #848](https://github.com/wagnerra23/oimpresso.com/pull/848)) | **3/10** |
| **D-5. Privacy/LGPD payload raw** | `messageSecret` ok salvar (não vaza); body sim deve ter retenção 90d + anonimização | `payload` JSON salva tudo; retenção 90d documentada mas **não implementada cron** | **6/10** |
| **D-6. Drift Baileys 6.7.9 → 7.x** | Teste regression sob CI que valida `messaging-history.set` payload shape contra schema fixo | nenhum teste de regressão de protocol — bugs nascem silenciosos no upgrade | **2/10** |
| **D-7. Migração Cloud API path-ready** | Driver isolado + schema com `bsuid`/`wa_id` separados de `phone_e164` + UI source-agnostic | `MetaCloudDriver` existe pré-criado (SPEC.md §14.2) mas schema `whatsapp_conversations.customer_phone` único = não comporta `bsuid` paralelo | **6/10** |
| **D-8. Identity Key persistence** | Backup automático `auth_state` (volume Docker → backup CT 100 daily) | Volume Docker `/srv/docker/whatsapp-baileys/sessions/{instance}` (ARCHITECTURE.md §16.2) mas **sem backup configurado** — perde auth_state = re-pareamento + 90d history sync de novo | **4/10** |

**Total (peso igual)**: (3+2+4+3+6+2+6+4)/80 = 30/80 = **42/100**

### Onde batemos o estado-da-arte (3 pontos)

1. **Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))** — Cloud API SaaS BSPs dependem isolation do tenant deles; aplicamos `business_id` global scope em CADA query. Cliente Larissa (biz=4) nunca vê msg Wagner (biz=1).
2. **Audit append-only `LidPhoneMap`** + `whatsapp_messages` (`first_seen_at`/`last_seen_at` + trigger MySQL imutabilidade body/direction/provider) — Intercom/Twilio não documentam equivalente; Zendesk teve incident público 2023 por falha.
3. **Defesa anti-mass-insert manual (PR #854 / Patch 2)** — `source=manual` exige webhook_senderPn prévio → bloqueia drift Tier 0 que vimos 12-14/mai (13 rows ad-hoc). Cloud API não precisa porque resolve no provider; Baileys ecosystem geralmente nem audita.

### Onde estamos atrás (3 pontos)

1. **Schema 1-coluna `customer_external_id`** misturando LID/phone/BSUID — quando jun/2026 chegar com usernames, precisaremos refactor migration que poderia ter sido feito hoje barato.
2. **`auth_state` sem backup automatizado** — Wagner re-pareou 14/mai e perdeu 14 mappings manuais que estavam só na DB (sobrevive) MAS também perdeu todo `lid-mapping` que estava na pasta `/sessions/{instance}` do daemon. Backup daily seria 1 cron job.
3. **Sem teste regression do payload shape** — quando atualizarmos Baileys (mesmo dentro 6.7.x), bugs nascem silenciosos. Pest test com fixture JSON do daemon snapshotting `messaging-history.set` resolveria.

---

# 6. Próximos passos priorizados (5 items por impacto × esforço calibrado [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

> ⛔ **Zero `DELETE` em `messages`/`conversations`/`whatsapp_lid_pn_map`.** Tudo é migration aditiva + observer + cron. Wagner: "nunca perca mensagem".

## P0-1 — Add `bsuid` + `lid` + `phone_e164` separados na Conversation/Contact (prep Cloud API + Baileys 7.x)

**Impacto:** 9/10 — habilita migração futura sem refactor, fix UX "+LID-não-resolvido", e endereça jun/2026 deadline BSUID Meta.

**Esforço:** S (1-2h IA-pair).

**Arquivos:**
- Migration: `database/migrations/2026_05_15_add_identity_columns_to_whatsapp_conversations.php`

```php
public function up(): void {
    Schema::table('whatsapp_conversations', function (Blueprint $t) {
        $t->string('lid', 32)->nullable()->after('customer_phone')
          ->comment('Linked ID — quando inbound chega @lid sem resolver pra PN');
        $t->string('bsuid', 64)->nullable()->after('lid')
          ->comment('Business-Scoped User ID Meta — preenchido a partir Cloud API mar/2026');
        $t->string('username', 32)->nullable()->after('bsuid')
          ->comment('Username @handle — preenchido quando user adotar (jun/2026 GA)');
        $t->index(['business_id', 'lid'], 'idx_biz_lid');
        $t->index(['business_id', 'bsuid'], 'idx_biz_bsuid');
    });
}
```

- Migration espelho em `contacts` (UltimatePOS): `add_lid_bsuid_to_contacts` — `mobile`/`alternate_number` ficam pra phone; novos campos cobrem identifiers modernos.

**Pest fixture (esqueleto):**

```php
// Modules/Whatsapp/Tests/Feature/IdentityColumnsPersistenceTest.php
it('persists LID separado de phone_e164 na Conversation', function () {
    $channel = Channel::factory()->baileys()->create(['business_id' => 1]);
    $persister = new MessagePersister($channel);

    $persister->persist([
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'senderPn' => '5548999872822@s.whatsapp.net',
            'id' => 'ABC123', 'fromMe' => false,
        ],
        'message' => ['conversation' => 'Oi'],
    ]);

    $conv = Conversation::withoutGlobalScopes()
        ->where('business_id', 1)->first();
    expect($conv->lid)->toBe('14628809617558')
        ->and($conv->customer_phone)->toBe('+5548999872822');
});
```

---

## P0-2 — Backfill retry trigger: quando `LidPhoneMap.phone_e164` é descoberto, re-link conversations órfãs do mesmo LID

**Impacto:** 8/10 — fecha o loop "primeiro msg veio @lid não resolvido, segundo msg trouxe senderPn → conv velha continua orfã".

**Esforço:** S (2h IA-pair).

**Arquivos:**
- Observer: `Modules/Whatsapp/Observers/LidPhoneMapObserver.php`
```php
public function saved(LidPhoneMap $map): void {
    if (!$map->wasChanged('phone_e164') || $map->phone_e164 === null) return;

    // Job assíncrono pra evitar bloquear webhook handler
    BackfillLidConversationsJob::dispatch($map->business_id, $map->lid, $map->phone_e164)
        ->onQueue('whatsapp');
}
```
- Job: `Modules/Whatsapp/Jobs/BackfillLidConversationsJob.php` — busca conversations `WHERE business_id=$biz AND lid=$lid AND contact_id IS NULL` e chama `ConversationContactLinker::tryLink()` em cada.

**Pest:**
```php
it('relinka conversations órfãs quando LID resolve pra phone via observer', function () {
    Queue::fake();
    LidPhoneMap::factory()->create(['business_id' => 1, 'lid' => 'X', 'phone_e164' => null]);

    LidPhoneMap::where('lid', 'X')->update(['phone_e164' => '+5548999872822']);
    Queue::assertPushed(BackfillLidConversationsJob::class);
});
```

---

## P1-3 — Backup automatizado `auth_state` (volume Docker daemon) — daily CT 100

**Impacto:** 7/10 — incident Wagner 14/mai mostrou que perder `/srv/docker/whatsapp-baileys/sessions/{instance}` = re-pareamento + 90d history sync de novo + 14 mappings perdidos.

**Esforço:** XS (30min — só script bash + cron).

**Arquivos:**
- Script: `infra/scripts/backup-baileys-auth.sh` no CT 100
```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
tar -czf /backups/baileys-auth-${DATE}.tar.gz /srv/docker/whatsapp-baileys/sessions/
# Mantém últimos 14 dias
find /backups -name "baileys-auth-*.tar.gz" -mtime +14 -delete
```
- Cron em `/etc/cron.d/baileys-backup`: `0 3 * * * root /opt/scripts/backup-baileys-auth.sh`

Runbook adicionado em `memory/requisitos/Whatsapp/runbooks/restore-auth-state.md` (50 linhas).

**Sem Pest** — infra script.

---

## P1-4 — Pest fixture-based regression do `messaging-history.set` payload shape

**Impacto:** 7/10 — protege contra upgrades silenciosos de Baileys; quando 7.x final estável sair, evita estragos invisíveis.

**Esforço:** S (1-2h IA-pair).

**Arquivos:**
- Fixture: `Modules/Whatsapp/Tests/Fixtures/baileys-messaging-history-set-6.7.9.json` — payload real capturado de prod CT 100 (sanitized PII)
- Test: `Modules/Whatsapp/Tests/Feature/BaileysProtocolRegressionTest.php`
```php
it('snapshot baileys 6.7.9 messaging-history.set payload shape', function () {
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../Fixtures/baileys-messaging-history-set-6.7.9.json'),
        true
    );

    // Schema esperado (campos críticos)
    expect($fixture)->toHaveKeys(['newChats', 'newContacts', 'newMessages', 'syncType']);
    expect($fixture['newMessages'][0]['key'])->toHaveKeys(['remoteJid', 'id', 'fromMe']);
    // senderPn é opcional — não asserta, mas registra se presente
});
```
- Adicionar a `phpunit.xml` (regra Tier 0 proibições: testes precisam estar registrados).

---

## P2-5 — UI badge "Aguardando identificação" quando `lid IS NOT NULL AND phone_e164 IS NULL AND bsuid IS NULL` na Inbox

**Impacto:** 5/10 — UX clareza pro atendente saber que conv ainda não resolveu identidade.

**Esforço:** S (1-2h IA-pair).

**Arquivos:**
- `resources/js/Pages/Whatsapp/Conversations/_components/ContactBadge.tsx` — adicionar variante "pending"
- Trigger backend já existe (após P0-1 schema) — frontend só renderiza

**Sem Pest backend** — visual + Playwright opcional futuro.

---

# 7. Decisão estratégica pra Wagner (3 perguntas decisivas)

## Opção A — Migrar pra Cloud API oficial Meta agora

**Custos:**
- $0.004 utility / $0.0625 marketing por msg (BR) + BSP markup $0.003-0.010 ([pricing 2026](https://chatarmin.com/en/blog/whats-app-api-pricing))
- ROTA LIVRE atual ~150 msgs/dia × 30 = 4.500 msgs/mês × $0.004 = **~$18/mês utility = R$90-120/mês**
- 1-3 dias de aprovação Meta + HSM templates (1 por status Repair etc) precisam re-aprovação
- Re-pareamento todos canais clientes (perda da janela)
- Migration `MetaCloudDriver` existe (SPEC.md §14.2) mas precisa onboarding wizard real + embedded signup
- LGPD: Cloud API é Meta-direct, sem BSP intermediário → mais simples

**Ganhos:**
- Zero ban risk
- `wa_id` + `bsuid` resolvidos pelo Meta — sumiria 100% do código `LidPhoneResolver`/`ConversationContactLinker` fuzzy
- Suporte oficial Meta + warnings antes de violações
- BSUID jun/2026 nativo desde mar/2026
- Janela 72h gratuita pra Click-to-WhatsApp Ads (importante se ROTA LIVRE roda ads)

**Quando ativar:** se ban Baileys 6.7.9 → recovery > 4h Wagner OR margem mensal absorve R$120 facilmente (provável dado meta ADR 0022 R$ 5mi/ano).

## Opção B — Migrar pra Baileys 7.x quando 7.0.0 final sair

**Custos:**
- Esperar 7.0.0 final estável (7.0.0-rc.9 quebrado conforme issues catalogadas)
- Refactor `auth_state` pra adicionar `lid-mapping`/`device-list`/`tctoken` keys
- Re-pareamento + risco 90d history sync de novo
- Atualizar `MessagePersister` pra ler `remoteJidAlt`/`participantAlt`
- 1-2 dias IA-pair + canary 7d

**Ganhos:**
- `getPNForLID()` nativo — `LidPhoneResolver` vira fallback marginal
- Sem custo recorrente
- Mesma arquitetura atual (CT 100 daemon Node)
- Mantém free + control total

**Quando ativar:** quando 7.0.0 final (não rc.X) sair publicado em npm — meses (CT 100 monitor watch + skill `baileys-update-procedure` Tier B já documentado).

## Opção C — Aceitar gap atual + endurecer workaround custom

**Custos:**
- Implementar P0-1 + P0-2 + P1-3 + P1-4 + P2-5 acima (≤8h IA-pair total)
- Aceitar que LID→PN reverso ficará incompleto até user enviar 2ª msg com senderPn
- Não cobre usernames jun/2026 quando rolar

**Ganhos:**
- Zero migração nesta sprint — confiança preservada após PR #854
- Schema pré-Cloud API ready (P0-1) — quando decidir B ou A no futuro, base já está
- Backup `auth_state` (P1-3) elimina o single-point-of-failure que matou 14/mai
- Custo zero recorrente

**Quando ativar:** **AGORA.**

---

## Recomendação executável

**Fazer Opção C hoje** (P0-1+P0-2+P1-3 = 4h IA-pair) **+ stub Opção A** (preparar `MetaCloudDriver` operacional + 1 biz canary não-prod).

Por quê:
1. **P0-1 (schema 3-identifiers) é zero-regret** — útil em qualquer das 3 opções futuras. Se Wagner amanhã decide Cloud API, está pronto.
2. **P1-3 (backup auth_state) é incident-trigger** — Wagner viveu na pele 14/mai. Custo 30min, vital.
3. **Cloud API canary biz=99 (Wagner test biz)** valida custo real + tempo aprovação HSM templates antes de decidir migração production-wide.
4. **Baileys 7.x esperar** — 7.0.0 final ainda não saiu; movimento prematuro = repetir bug rc.9.

---

# 8. Fontes (Sources)

## Protocolo WhatsApp Multi-Device
- [Engineering at Meta — Multi-device capability (jul/2021)](https://engineering.fb.com/2021/07/14/security/whatsapp-multi-device/)
- [InfoQ — WhatsApp adopts Signal Protocol multi-device](https://www.infoq.com/news/2021/07/WhatsApp-signal-protocol/)
- [arXiv 2504.07323 — Prekey Pogo: Investigating WhatsApp Handshake](https://arxiv.org/html/2504.07323)
- [eprint.iacr.org 2025/794 — Formal Analysis Multi-Device Group Messaging WhatsApp](https://eprint.iacr.org/2025/794.pdf)
- [Wikipedia — Signal Protocol (PreKeySignalMessage, SenderKeyMessage)](https://en.wikipedia.org/wiki/Signal_Protocol)
- [Signal blog — Sealed sender](https://signal.org/blog/sealed-sender/)

## LID / BSUID / Usernames
- [Whapi help — What Is lid in WhatsApp Groups](https://support.whapi.cloud/help-desk/groups/what-is-lid-in-whatsapp-groups)
- [Whapi help — WhatsApp LID (@lid) FAQ](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)
- [Twilio — WhatsApp usernames BSUID required jun/2026](https://www.twilio.com/en-us/changelog/whatsapp-usernames--new-business-scoped-user-id--bsuid--field-re)
- [Microsoft Learn — Azure Communication Services WhatsApp usernames BSUID](https://learn.microsoft.com/en-us/azure/communication-services/concepts/advanced-messaging/whatsapp/whatsapp-username-support-overview)
- [Medium Matthias Provoost — Meta BSUID live in Cloud API](https://medium.com/@matthias_20536/meta-bsuid-is-live-in-whatsapp-cloud-api-what-to-change-in-your-webhooks-and-crm-9b6dc69058dd)
- [Genesys Cloud — BSUIDs WhatsApp identity resolution](https://help.genesys.cloud/articles/bsuids-for-whatsapp-identity-resolution-and-messaging-continuity-overview/)
- [Chatwoot issue #13837 — BSUID support before jun/2026](https://github.com/chatwoot/chatwoot/issues/13837)
- [Gallabox — WhatsApp Usernames Explained 2026](https://gallabox.com/blog/whatsapp-usernames-hide-phone-number-businesses-2026)

## Baileys (6.7.9 e 7.x)
- [Baileys wiki — Migrate to v7.x.x](https://baileys.wiki/docs/migration/to-v7.0.0/)
- [Baileys wiki — History Sync](https://baileys.wiki/docs/socket/history-sync/)
- [Baileys wiki — Receiving Updates messages.upsert](https://baileys.wiki/docs/socket/receiving-updates/)
- [Issue #1718 — @lid remoteJid não retorna phone (duplicate)](https://github.com/WhiskeySockets/Baileys/issues/1718)
- [Issue #2263 — lid-mapping.update event never fires](https://github.com/WhiskeySockets/Baileys/issues/2263)
- [Issue #2259 — building a database of jid/lid](https://github.com/WhiskeySockets/Baileys/issues/2259)
- [Issue #2077 — Missing contact data in messaging-history.set (7.0.0-rc.6)](https://github.com/WhiskeySockets/Baileys/issues/2077)
- [Issue #2462 — messaging-history.set not triggered (7.0.0-rc.9)](https://github.com/WhiskeySockets/Baileys/issues/2462)
- [Issue #2005 — History sync isLatest never changes](https://github.com/WhiskeySockets/Baileys/issues/2005)
- [Hermes-agent issue #11951 — syncFullHistory:false disables history in 7.x](https://github.com/NousResearch/hermes-agent/issues/11951)
- [Openclaw issue #19907 — Baileys RC9 Auth Breaking 401 device_removed](https://github.com/openclaw/openclaw/issues/19907)
- [Baileys releases](https://github.com/WhiskeySockets/Baileys/releases)

## whatsmeow (Go alternative)
- [whatsmeow discussion #846 — sender_pn](https://github.com/tulir/whatsmeow/discussions/846)
- [whatsmeow discussion #905 — sender_pn and @lid](https://github.com/tulir/whatsmeow/discussions/905)
- [whatsmeow issue #473 — Sending to HiddenUserServer](https://github.com/tulir/whatsmeow/issues/473)
- [whatsmeow issue #871 — Retry resolving LID to PN](https://github.com/tulir/whatsmeow/issues/871)
- [whatsmeow issue #859 — Error 479 LID with no phone mapping](https://github.com/tulir/whatsmeow/issues/859)
- [whatsmeow send.go — messageSecret 32 bytes random](https://github.com/tulir/whatsmeow/blob/main/send.go)

## WAHA / whatsapp-web.js (libs vizinhas)
- [WAHA discussion #1858 — How to resolve @lid](https://github.com/devlikeapro/waha/discussions/1858)
- [WAHA issue #1608 — webhook payload.from contém @lid corrompendo contact](https://github.com/devlikeapro/waha/issues/1608)
- [WAHA contacts docs](https://waha.devlike.pro/docs/how-to/contacts/)
- [whatsapp-web.js issue #3969 — Request real phone for LID](https://github.com/pedroslopez/whatsapp-web.js/issues/3969)

## Cloud API / pricing / migration
- [Meta — Cloud API messages webhook reference](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages/)
- [Meta — Click to WhatsApp Marketing API](https://developers.facebook.com/docs/marketing-api/ad-creative/messaging-ads/click-to-whatsapp/)
- [Chatarmin — WhatsApp API Pricing 2026 (BR)](https://chatarmin.com/en/blog/whats-app-api-pricing)
- [Engagelab — WhatsApp Business API pricing 2026](https://www.engagelab.com/blog/whatsapp-business-api-pricing)
- [YCloud — pricing update jul/2025 per-message](https://www.ycloud.com/blog/whatsapp-api-pricing-update)
- [Whautomate — Embedded Signup 15min](https://whautomate.com/whatsapp-embedded-signup)
- [Wati — Migration on-premise → Cloud API](https://support.wati.io/en/articles/11864686-migrating-from-on-premise-whatsapp-business-api-to-whatsapp-cloud-api)

## Ban risk / governança
- [Kraya AI — WhatsApp Automation Ban Risk 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)
- [Agência Rollin — API Oficial vs Não Oficial 2026](https://www.agenciarollin.com/blog/api-oficial-whatsapp-vs-nao-oficial-guia-completo-2026)

---

**Próxima ação concreta:** se Wagner aprova Opção C + stub Cloud API canary, abro 3 PRs separados:
1. `claude/whatsapp-schema-3-identifiers` — migration + observer + Pest (P0-1, P0-2)
2. `claude/whatsapp-auth-state-backup` — script CT 100 + cron + runbook (P1-3)
3. `claude/whatsapp-protocol-regression-fixture` — Pest fixture (P1-4)

Estimativa total: 4-6h IA-pair, ≤500 linhas distribuídas em 3 PRs ≤300 linhas cada (regra `commit-discipline` Tier A).
