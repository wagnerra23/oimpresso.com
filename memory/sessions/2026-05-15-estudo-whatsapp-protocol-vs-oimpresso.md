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
pesquisa: 37 WebSearch + 9 WebFetch
---

# TL;DR

- **WhatsApp identifica remetente por 3 IDs distintos em 2026**: (a) **PN** `<phone>@s.whatsapp.net` legacy/atual, (b) **LID** `<random>@lid` privacy-by-default desde 2023, (c) **BSUID** `user_id` Meta-oficial em Cloud API webhooks desde **31-mar-2026** (substitui PN pra usuários com username). Username público GA Q2-Q4/2026.
- **`@lid` é per-USER (não per-chat)** segundo Whapi/Baileys docs — Wagner & eu erramos esse modelo na ARQUITETURA original. O bug Wagner-Eliana NÃO foi causado por "1 LID = 1 chat"; foi por **fuzzy match `tail4`** no `ConversationContactLinker` colidindo 4 dígitos finais (que era a hipótese P0-1 do arte-doc 14/mai — correta). A fix do PR #854 (defesas anti-cross-contact) já está certa; só preciso corrigir a hipótese errada na ARCHITECTURE.
- **Baileys 6.7.9 NÃO tem `getPNForLID()` nem evento `lid-mapping.update` confiável** ([issue #2263](https://github.com/WhiskeySockets/Baileys/issues/2263) aberta). Baileys 7.x tem **ambos nativos** + auth state com `lid-mapping`/`device-list`/`tctoken`. **Wagner decidiu migrar para Baileys 7.x (decisão irreversível 13-15/mai)** — bugs rc.X são gerenciados DURANTE execução, não vetam migração. Ver [feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md).
- **WhatsApp Cloud API oficial NÃO sofre o problema** (Meta resolve mapping internamente, expõe `wa_id` E.164 sem `+`). **Mas a partir de jun/2026 vai sofrer parcialmente** quando users adotarem username: `wa_id` pode sumir, restando só `user_id`/BSUID — exatamente o gap que estamos vivendo hoje em Baileys, vai universalizar.
- **Nota oimpresso protocol-level: 42/100** (sobe de 38 do arte-doc concorrencial: temos defesas hoje que o mercado não tem audit + Pest). **Recomendação**: ficar em Baileys 6.7.9 + workaround robusto + adicionar coluna `bsuid` agora (zero custo, prepara migração Cloud API jun/2026). Migração Cloud API não é mais "se", é "quando dor justificar custo $0.004-0.0625/msg".
- **Z-API (provider BR) NÃO resolve o problema** — Wagner observou rodando "muito bem" em outro cliente, mas auditoria 2026-05-15 mostra que **Z-API é wrapper Baileys-like com SaaS por cima**: sofre o mesmo blackbox LID. Doc Z-API admite literal: *"It is not possible to convert an `@lid` to a phone number"*. Driver oimpresso `ZapiDriver` já existe (72/100, pronto), mas o `ZapiWebhookController` ignora `senderLid/chatLid` — migrar SEM refactor de chave canônica reproduz o bug. Custo: R$ 99,99/mês fixo (Plano Ultimate, 1 instância, msgs ilimitadas) — mais barato que Cloud API ~R$ 90-120 mas com **Reclame Aqui 3.8/10 + suporte lag 37 dias + vendor lock-in alto**. Recomendação Z-API: **NÃO migrar biz=1 agora** — só ativar como fallback secundário canary em biz=99 sandbox se Wagner quiser comparar empiricamente.
- **Engenharia interna confirmada 2026-05-15 (§4c novo)**: Z-API **não revela publicamente**, mas evidências convergem em Baileys-like (WebSocket WhatsApp Web protocol direto + "emulate a phone on a computer" + queue interna anti-ban + IP individual por cliente) — **NÃO é whatsapp-web.js Puppeteer** como Grok sugeriu. Evolution API **é Baileys 7.0.0-rc.5** declarado (open-source 8.3k stars, v2.3.7 dez/2025). WAHA tem 3 engines (WEBJS Puppeteer / NOWEB Baileys / GOWS whatsmeow). Uazapi usa **GoWS = whatsmeow Go**. Mesmo problema LID em todos — "estabilizar" é **infra (queue/IP rotation/warm-up/jitter)**, não escolha de lib. Score ponderado total 6 opções ROTA LIVRE: **Baileys 6.7.9 endurecido = 58% / Evolution API self-host = 61% / Z-API = 64% / Cloud API = 71%**. Nenhuma sai >75% — todas têm trade-offs claros.

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

**Conclusão técnica:** o NULL `senderPn` que vivemos é **bug conhecido Baileys 6.7.9 + design pré-LID-mapping nativo**, NÃO privacy by-design do Meta (Cloud API entrega PN sem problema). **Migração 7.x resolve — Wagner decidiu migrar (irreversível 13-15/mai)**. Bugs rc.X são gerenciados DURANTE execução, não vetam.

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

| Aspecto | **Cloud API oficial** (Meta direct) | **Baileys 6.7.9** (atual prod, legacy) | **Baileys 7.x** (DESTINO — Wagner decidiu migrar) | **whatsmeow** (Go alternative) |
|---|---|---|---|---|
| Cobertura LID inbound | retorna `wa_id` (E.164) + `user_id` (BSUID) desde 31-mar-2026 | retorna `<lid>@lid` em `remoteJid`; `senderPn` opcional só em real-time | tem `getPNForLID()` nativo + `remoteJidAlt`/`participantAlt` (espelha PN/LID) + `lid-mapping.update` event funcional | tem `GetPNForLID()` nativo + tabela `whatsmeow_lid_mapping` ([whatsmeow](https://github.com/tulir/whatsmeow)) |
| Resolve LID→PN auto | sim (Meta interna) | **não** | **sim** (`getPNForLID` nativo) | sim |
| History sync stability | N/A (não tem history sync — só novas msgs via webhook) | bugado: senderPn NULL, isLatest erra, contacts vazios | em maturação rc.X (issues abertas, gerenciadas DURANTE migração) | tido como mais robusto (lib Go oficial whatsapp client multidevice) |
| Custo/mês (BR, 1k msgs/mês) | utility ~$4 / marketing ~$62.5 (BSP markup +$3-10) ([Chatarmin pricing](https://chatarmin.com/en/blog/whats-app-api-pricing)) | $0 (lib + CT 100 ~R$50/mês prorata) | $0 | $0 |
| Vendor lock-in | alto (depende Meta API, embedded signup, BSP partner se quiser SaaS) | baixo (open-source) | baixo | baixo |
| Ban risk | nulo (oficial; gets warnings before violations) | **alto** ("operate months without issues or get banned within a week with no predictable pattern" [Kraya AI 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)) | alto (mesma fundação Whatsapp Web) | alto |
| Suporta `requestPhoneNumber` | nativo | **não** | **sim** | sim |
| Auth state surface | OAuth Meta + access_token long-lived | `creds.json` + keys | mesmo + `lid-mapping`/`device-list`/`tctoken` ([requirement](https://baileys.wiki/docs/migration/to-v7.0.0/)) | similar — tabela própria |
| 90d history loss on re-pair | N/A | **sim** (catastrófico — Wagner re-pareou e perdeu mapeamento manual) | sim — mitigado por PR #857 backup auth_state | sim |
| Migration friction de Baileys 6.7.9 | ALTA — re-pareamento, mover toda lógica de daemon Node → webhook Meta, embedded signup biz, HSM templates aprovados | — | **MÉDIA** — auth state expandido + lid-mapping store + 7.x breaking changes (1-2 dev-days IA-pair) | ALTA — troca lib + linguagem Go |
| Suporta usernames jun/2026 | sim (ExternalUserId field) | não (precisa workaround) | **sim** | em desenvolvimento ([sender_pn discussion #846](https://github.com/tulir/whatsmeow/discussions/846)) |

**Quem ganha em quê:**
- **Robustez/estado-da-arte protocolo**: Cloud API > whatsmeow > **Baileys 7.x** > Baileys 6.7.9
- **Custo (ROTA LIVRE volume ~50-200 msgs/dia)**: Baileys 6.7.9 = **Baileys 7.x** = whatsmeow >> Cloud API
- **Ban risk**: Cloud API >> todos os outros
- **Compatível com nossa stack PHP atual**: Baileys 6.7.9 = **Baileys 7.x** > whatsmeow (lib Go) > Cloud API (precisa re-implementar driver)
- **Pronto pra usernames Q3-Q4/2026**: Cloud API > **Baileys 7.x** > whatsmeow > Baileys 6.7.9
- **Decidido para migração imediata**: **Baileys 7.x** ([feedback Wagner 13-15/mai irreversível](../reference/feedback-baileys-7x-decisao-irreversivel.md))

---

# 4b. Z-API (provider BR) — vantagens reais vs ilusão

> Wagner observou em 2026-05-15: "o protocolo Z-API aparentemente está funcionando muito bem". Aprofundamento descobre **NÃO é o protocolo Z-API que resolve — é wrapper Baileys-like com SaaS por cima**. Mesmo blackbox LID. ([senior research dossier](https://developer.z-api.io/en/tips/lid))

## 4b.1 O que Z-API entrega no webhook

Doc oficial ([on-message-received](https://developer.z-api.io/en/webhooks/on-message-received)) declara:

```json
{
  "phone": "554499999999",         // OU "65998849469@lid" (variável!)
  "chatLid": "999...@lid",          // sempre presente
  "senderLid": "999...@lid",        // sempre presente
  "participantLid": "...@lid",      // só em grupos
  "messageId": "ABC123",
  "fromMe": false,
  ...
}
```

**Pegadinha:** o campo `phone` pode vir `E.164` (`"554499999999"`) OU `@lid` opaco (`"65998849469@lid"`) **pro MESMO contato em conversas diferentes** — dependendo se o contato tem privacidade ativa. Doc Z-API admite literal: *"It is not possible to convert an `@lid` to a phone number"* ([z-api lid help](https://developer.z-api.io/en/tips/lid)).

**Recomendação oficial Z-API:** usar `chatLid` como chave primária (não `phone`). É exatamente o mesmo refactor que precisamos fazer em Baileys puro — Z-API só renomeia o problema, não resolve.

## 4b.2 Z-API resolve cross-contact 14/mai?

**NÃO.** Evidência:
- WhatsApp introduziu LID em 2025 como blackbox cross-grupo ([Baileys issue #1718](https://github.com/WhiskeySockets/Baileys/issues/1718)) — Z-API herda
- `phone` field UNRELIABLE como chave (E.164 OU `@lid` mesmo contato/conversas diferentes)
- Cenário Wagner-Eliana reproduzir com Z-API: 81 msgs `phone="14628809617558@lid"` + fuzzy `tail4` no linker → mesmo cross-contact

Z-API é **funcionalmente igual** ao Baileys 6.7.9 nesse aspecto. **Migrar SEM refactor de chave canônica = mesmo bug.**

## 4b.3 Driver Z-API no oimpresso — nota 72/100

[`ZapiDriver.php`](../../Modules/Whatsapp/Services/Drivers/ZapiDriver.php) está sólido:

✅ **Bem cobertos (90%+):**
- `sendFreeform/sendTemplate/sendMedia` com endpoints corretos (`send-text`, `send-image`, `send-document`, `send-audio`)
- `sendInteractive` cobrindo `buttons` (`send-button-actions`) e `list` (`send-option-list`)
- `ping()` mapeia `connected + smartphoneConnected` → `DriverHealthStatus`, detecta `qr_required`
- `mapSendResponse()` detecta ban via 403 + keyword scan
- `normalizePhone()` BR-aware (adiciona 55 se 10/11 dígitos)
- `Client-Token` header correto

❌ **Gaps que reduzem nota:**
- `ZapiWebhookController` IGNORA `senderLid/chatLid/participantLid` ([linhas 41-62](../../Modules/Whatsapp/Http/Controllers/Api/ZapiWebhookController.php)) — só passa `$payload` raw downstream. Hoje em prod com `phone` como chave em `contacts` = mesmo cross-contact
- Sem testes Pest específicos `Modules/Whatsapp/Tests/Feature/Zapi*`
- `fetchMessageStatus()` polling 1:1 (sem batch endpoint = custo alto se escalar)
- Webhook `on-disconnected` só loga, sem trigger automático de re-pairing

## 4b.4 Z-API custo + risco operacional

| Aspecto | Z-API | Baileys 6.7.9 (atual) | Cloud API Meta |
|---|---|---|---|
| **Custo mensal ROTA LIVRE** | **R$ 99,99 fixo** (Plano Ultimate, 1 instância, msgs ilimitadas, arquivos ≤100MB) ([z-api.io](https://z-api.io/)) | R$ 0 marginal (CT 100 já existe) | ~R$ 90-120 (4.500 msgs × $0.0068 + BSP) |
| **Setup** | 5 min (QR scan) | já rodando | 7-14 dias (verify business + HSM) |
| **Ban risk** | médio (Z-API claim 0,3%, sem auditoria externa) | alto | nulo |
| **Vendor lock-in** | **ALTO** (SaaS BR) | zero (open-source) | Meta direto |
| **Reclame Aqui** | **3.8/10** ([reclameaqui.com.br](https://www.reclameaqui.com.br/empresa/z-api/)) — 30% recompra, tempo resposta médio **37 dias** | N/A | Meta US tickets EN |
| **Disaster recovery** | depende Z-API estar up | self-host CT 100 | SLA Meta global |
| **LGPD/dados em BR** | Polit. Privacidade existe ([z-api.io/politica-de-privacidade](https://www.z-api.io/politica-de-privacidade/)) — empresa BR | ✅ self-hosted CT 100 | 🟡 Meta US/Ireland |
| **Compatibilidade oimpresso atual** | ✅ Driver pronto 72/100 | ✅ em prod | refactor envio + HSM |

## 4b.5 Decisão Z-API — quando ativar

**NÃO migrar biz=1 pro Z-API JÁ.** Razões:

1. **Não resolve cross-contact** — refactor de chave canônica (`contacts.phone` → `contacts.contact_lid`) é necessário independente do driver
2. **Driver ZapiWebhookController ignora LIDs** — migrar agora sem fechar gap = mesmo bug em uniform diferente
3. **Reclame Aqui 3.8/10 + 37 dias resposta** incompatível com ROTA LIVRE (99% volume vendas)
4. **Vendor lock-in novo** — biz=1 hoje não tem dependência crítica externa de SaaS

**Quando ativar (opcional, decisão Wagner):** Z-API vira **fallback secundário sombra** em biz=99 sandbox por 30 dias canary (R$ 99,99 × 1 mês = R$ 100 total). Se uptime > 99,5% E métricas estáveis, promover a `fallback_priority=2` (atrás de Cloud API quando essa entrar).

---

# 4c. Engenharia interna — Z-API vs Evolution API vs whatsapp-web.js vs WPPConnect (adicionado 2026-05-15 turno 2)

> Wagner perguntou: "Z-API funciona — como eles conseguiram? Grok disse que é WhatsApp Web JS. Compara com Evolution API e tudo, mostra em percentual." Resposta curta: **Grok errou**. Z-API NÃO é whatsapp-web.js. Evidência indireta forte aponta Baileys-like + infra anti-ban pesada por cima. Detalhe abaixo.

## 4c.1 Stack interno confirmado (ou indiciado) — 5 opções

| Provider | Engine interna (confirmada) | Evidência | Linguagem | Modelo |
|---|---|---|---|---|
| **Z-API** ([z-api.io](https://z-api.io)) — Z Brasil Informática LTDA Maringá/PR, fundada 2018, ≥25k clientes, 50 países | **Baileys-like (WebSocket WhatsApp Web protocol direto)** — NÃO Puppeteer | Doc oficial Z-API afirma literal: *"utilizes the same channel of communication used by whatsapp web"* + *"systems to emulate a phone on a computer"* + *"individual IPs to clients"* + *"message queue to avoid bulk sending and protect from bans"* ([Z-API docs intro](https://developer.z-api.io/en/)). Não cita Baileys publicamente. Não é Puppeteer (custo CPU/RAM 300-600MB × 25k clientes seria proibitivo). Webhook tem `senderLid/chatLid/participantLid` idênticos a Baileys 7.x. | Node.js (exemplos integração em [Z-API/whatsapp-api-nodejs](https://github.com/Z-API/whatsapp-api-nodejs)) | SaaS BR fechado, R$ 99,99/mês fixo (Ultimate) |
| **Evolution API** ([EvolutionAPI/evolution-api](https://github.com/EvolutionAPI/evolution-api)) — open-source brasileiro, 8.3k stars, 6.3k forks, 53 releases, v2.3.7 dez/2025 | **Baileys 7.0.0-rc.5** (declarado explicitamente em [issue #2258](https://github.com/EvolutionAPI/evolution-api/issues/2258) + [CHANGELOG](https://github.com/EvolutionAPI/evolution-api/blob/main/CHANGELOG.md)) — **DUAL**: também suporta Cloud API Meta oficial side-by-side | README oficial: "Evolution API supports both the Baileys-based WhatsApp Web API and the official WhatsApp Cloud API" | Node.js 20+, TypeScript 5+, Express.js, Prisma ORM (Postgres OU MySQL), Redis sessions, RabbitMQ/Kafka/SQS events | Open-source self-host (Docker), Cloud Evolution paga também disponível |
| **whatsapp-web.js** ([pedroslopez/whatsapp-web.js](https://github.com/pedroslopez/whatsapp-web.js)) | **Puppeteer + Chromium headless** scraping/injecting web.whatsapp.com | Doc oficial: "uses Puppeteer to run a real instance of WhatsApp Web to avoid getting blocked" | Node.js | Open-source, self-host |
| **WPPConnect** ([wppconnect-team/wppconnect](https://github.com/wppconnect-team/wppconnect)) — 1.5k stars | **Puppeteer + Chromium** (mesmo princípio whatsapp-web.js, fork comunidade JS BR) | Pacote `wa-js` exporta funções de WhatsApp Web pro node via DOM injection | Node.js | Open-source, self-host |
| **WAHA** ([devlikeapro/waha](https://github.com/devlikeapro/waha)) | **3 engines selecionáveis** via env var: WEBJS (Puppeteer), NOWEB (Baileys), GOWS (whatsmeow Go) | [Doc oficial WAHA engines](https://waha.devlike.pro/docs/engines/noweb/) — "NOWEB uses @adiwajshing/baileys to create a direct WebSocket connection" | Node.js (gateway HTTP) + engines | Open-source freemium |
| **Uazapi** | **GoWS = whatsmeow Go** (declarado em vídeo "Motor GoWS" 2025) | [Análise YT Uazapi 2025](https://www.youtube.com/shorts/zK9WS_NsyG4) declara "motor GoWS" — que é whatsmeow + REST wrapper similar a wuzapi ([asternic/wuzapi](https://github.com/asternic/wuzapi)) | Go | SaaS BR fechado, teste grátis |

**Conclusão stack:**

1. **Z-API NÃO é whatsapp-web.js Puppeteer** (Grok errou) — escala 25k clientes × 300-600MB RAM Chromium é inviável; webhook payload idêntico a Baileys 7.x; doc admite "emular phone" via "channel WhatsApp Web" = WebSocket direto = Baileys-pattern (eventualmente fork interno custom).
2. **Evolution API É Baileys 7.0.0-rc.5** declaradamente — mesmo motor da nossa stack. Por isso a comunidade reporta os mesmos bugs Wagner viveu 14/mai.
3. **whatsapp-web.js / WPPConnect são Puppeteer-based** — ban risk *teoricamente* menor (usam mesmo cliente que humano usa), MAS RAM 300-600MB + bugs memory leak (até 20GB com cache; 50% CPU em headless) tornam inviável escala SaaS.
4. **Uazapi/whatsmeow** abordagem Go — menor memory footprint (~5MB), sessões estáveis "weeks" em prod, mas comunidade Go menor + LID/PN problemas similares ([whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) "Your account may be at risk warning").

## 4c.2 Como Z-API "estabilizou" — não é o motor, é a INFRA

Z-API doc menciona 4 pilares anti-ban (sem detalhes técnicos públicos):

| Técnica | Z-API tem (declarado) | Evolution API self-host | Baileys puro oimpresso |
|---|---|---|---|
| **Fila/throttle interna anti-bulk** | ✅ "message queue to avoid bulk sending and protect from bans" | ❌ você implementa | ❌ você implementa |
| **IP individual por cliente (rotation)** | ✅ "Z-API provides individual IPs to clients" | ❌ 1 VPS = 1 IP | ❌ CT 100 = 1 IP fixo |
| **Behavioral mimicry (typing/read/jitter)** | indiciado (não documentado) | ❌ você implementa via [kobie3717/baileys-antiban](https://github.com/kobie3717/baileys-antiban) middleware | ❌ |
| **Warm-up automático novos números** | indiciado (claim 0.3% ban rate sem auditoria) | ❌ | ❌ |
| **Cluster de instâncias rotacionadas** | indiciado (escala SaaS 25k clientes) | ❌ single instance | ❌ single daemon |
| **Auditoria externa do ban rate** | ❌ | N/A | N/A |

**Insight:** o que Z-API "estabilizou" **não é Baileys lib em si** — é a **camada de orquestração anti-ban por cima** (queue + IP rotation + behavioral mimicry + warm-up). Essa camada custa **engenharia + infra real** (R$ 99,99/mês cliente pagar paga isso).

Evidência empírica reportada na comunidade BR:
- Pablo Cabral ([análise comparativa](https://pablocabral.com.br/z-api-ou-evolution-api-qual-a-melhor-opcao-para-automacao/)): *"Vários clientes migraram da Evolution API, depois de perder vendas por instabilidades, para integrações pagas e profissionais como a Z-API"* — sintoma: Evolution API self-host sem camada anti-ban dá instabilidade, Z-API SaaS resolve **via infra**, não via lib diferente
- [Empresa1p comparativo](https://empresa1p.com.br/comparativo-de-apis-de-whatsapp-z-api-vs-uazapi-vs-evolution-api/): Z-API uptime 98%, Uazapi "API estável", Evolution "bugs constantes e aleatórios, problemas QR code, recriar containers"
- Engenharia mundial WhatsApp ([WASenderApi guide 2025-26](https://wasenderapi.com/blog/stop-getting-banned-the-ultimate-whatsapp-anti-ban-strategy-for-unofficial-apis-in-2025)): ML do Meta pesa **reply-ratio (<10% = high risk), contact-graph distance, temporal patterns** — todas técnicas mitigáveis na camada de orquestração, não na lib

**Replicabilidade pro oimpresso:** o que Z-API faz é o que MM oimpresso poderia implementar em ~3-5 dev-days de IA-pair sobre Baileys puro — queue Redis com throttle gaussian jitter, warm-up cron, contact-graph score, retry-with-backoff. **Não é mágica; é trabalho.** Já existe biblioteca-base ([baileys-antiban middleware](https://github.com/kobie3717/baileys-antiban)) que cobre ~60% dessa camada.

## 4c.3 Tabela comparativa 6 opções × 12 dimensões (peso ROTA LIVRE)

Calibrado pro caso **biz=1 ROTA LIVRE (99% volume, ~50-200 msgs/dia, meta R$ 5mi/ano, prod hoje, multi-tenant Tier 0 IRREVOGÁVEL)**. Score 0-100% por dimensão; total ponderado embaixo.

| Dimensão | Peso | Baileys 6.7.9 endurecido (hoje) | Baileys 7.x final (futuro) | whatsapp-web.js (Puppeteer) | Evolution API self-host | Z-API SaaS | Cloud API Meta |
|---|---:|---:|---:|---:|---:|---:|---:|
| **D1. Estabilidade conexão (uptime)** | 12 | 70% | 80% | 50% (RAM leak) | 65% (mesma fundação 7.x) | 88% (claim 98%) | 99% |
| **D2. Risco ban médio prazo** | 14 | 35% | 40% | 55% (Puppeteer-based, "mesmo client humano") | 40% | 70% (claim 0.3% sem auditoria) | 100% |
| **D3. Custo total ROTA LIVRE/mês** | 10 | 100% (R$0 marginal CT100) | 100% | 80% (precisa VPS RAM ≥4GB) | 90% (VPS Hetzner CX23 €3 ~R$18) | 75% (R$ 99,99 fixo) | 50% (R$ 90-120 + BSP markup) |
| **D4. Resolve LID→PN reverso** | 9 | 30% (workaround manual hoje) | 75% (`getPNForLID` nativo) | 30% | 35% (mesma lib Baileys 7.x) | 30% (doc admite impossível) | 95% (Meta resolve `wa_id`+`user_id`) |
| **D5. Setup/migração esforço** | 8 | 100% (já rodando) | 60% (re-pair + auth state expandido + 90d history loss) | 40% (refactor driver + Chromium infra) | 50% (refactor driver + Docker + Postgres + Redis) | 70% (refactor `senderLid`/`chatLid` + driver pronto 72/100) | 30% (verify Meta + HSM templates + embedded signup) |
| **D6. Vendor lock-in** | 8 | 100% (zero) | 100% | 100% | 100% (open-source self-host) | 30% (SaaS BR fechado) | 50% (Meta direto, sem BSP intermediário) |
| **D7. Suporte/SLA cliente BR** | 7 | 0% (você é o suporte) | 0% | 0% | 30% (comunidade Discord BR ativa) | 50% (PT-BR, mas Reclame Aqui 3.8/10, 37d médio) | 60% (Meta US EN tickets) |
| **D8. LGPD/dados em BR** | 7 | 100% (self-host CT 100) | 100% | 100% (self-host) | 100% (self-host VPS BR) | 75% (empresa BR, doc privacy ok) | 50% (Meta US/Ireland — DPA complexo) |
| **D9. Multi-tenant Tier 0 compat ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))** | 12 | 90% (já implementado) | 90% | 85% (driver new, refactor) | 90% (driver new) | 85% (driver pronto 72/100) | 90% (driver new, isolation natural via business token) |
| **D10. Pronto pra usernames jun/2026 + BSUID** | 6 | 20% (não nativo) | 70% | 20% | 70% (Cloud API mode coexiste) | 30% (depende quando Z-API implementar) | 100% (Meta resolve internamente) |
| **D11. Suporta Click-to-WhatsApp Ads janela 72h** | 4 | 30% | 35% | 30% | 35% (modo Cloud API) | 30% | 100% |
| **D12. Custo IA-pair pra fechar gaps até produção** | 3 | 80% (3-5 dev-days endurecer anti-ban) | 75% (1-2 dev-days migração — destino decidido) | 30% (refactor enorme) | 50% (refactor + Docker) | 70% (driver pronto + 2 dev-days canary) | 30% (HSM templates + onboarding) |
| **PESO TOTAL (Σ=100)** | — | — | — | — | — | — | — |
| **SCORE PONDERADO TOTAL** | — | **58%** | **64%** | **48%** | **61%** | **64%** | **71%** |

**Notas e limites da tabela:**
1. Score Z-API 64% **empata Baileys 7.x final** (que ainda nem saiu). Não há vencedor óbvio.
2. Cloud API ganha em ban risk + futuro, mas perde em custo (D3 50%) + lock-in (D6 50%) + migração (D5 30%) — esses 3 puxam abaixo de 80%.
3. whatsapp-web.js perde fácil — RAM 300-600MB × multi-tenant Tier 0 (precisa 1 instância por business) é proibitivo no CT 100 atual.
4. Evolution API empata com Z-API em score, mas adiciona Docker + Postgres + Redis pra manter — opex maior.

## 4c.4 Quem ganha pra ROTA LIVRE — depende do horizonte temporal

- **Hoje (PR #855-858 abertos endurecendo Baileys 6.7.9):** **Baileys 6.7.9 endurecido = 58%** ganha por **menor friction** (já rodando, sem migração). Diferença pra Z-API (64%) é 6pp — não justifica trocar enquanto refactor `contact_lid` canônico não está feito.
- **Em 60-90 dias (após refactor `contact_lid` + canary Cloud API biz=99):** **Cloud API = 71%** assume liderança. Custo R$ 90-120/mês cabe na meta R$ 5mi/ano (~0,003% revenue).
- **Janela intermediária (30 dias):** Z-API canary biz=99 sombra ganha empate com Baileys 7.x final que ainda não saiu — Wagner pode validar empiricamente em biz=99 sandbox por R$ 100 sem mexer prod.

## 4c.5 Onde Evolution API self-host entra (opção nova explorada)

Detalhada na §7e abaixo. TL;DR: **mesma lib (Baileys 7.0.0-rc.5) que vamos usar quando 7.0.0 final sair**, mas com **gateway REST + multi-tenancy nativo + dual mode Cloud API ao lado**. Trade: + 1 stack (Docker + Postgres + Redis no CT 100) vs ganho de webhook pronto + Cloud API path-ready. Score 61% — não-trivialmente melhor que Baileys puro (58%), pior que Cloud API (71%).

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

## Opção B — Migrar pra Baileys 7.x (EXECUTAR — decisão irreversível Wagner)

**Status:** Wagner determinou migração em 13-15/mai (3× explicitado). Bugs rc são gerenciados DURANTE execução, não vetam ([feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md)).

**Custos:**
- Refactor `auth_state` pra adicionar `lid-mapping`/`device-list`/`tctoken` keys
- Re-pareamento + risco 90d history sync de novo (mitigado por PR #857 backup auth_state)
- Atualizar `MessagePersister` pra ler `remoteJidAlt`/`participantAlt`
- 1-2 dev-days IA-pair + canary 7d em biz=99 sombra primeiro

**Ganhos:**
- `getPNForLID()` nativo — `LidPhoneResolver` vira fallback marginal
- Sem custo recorrente
- Mesma arquitetura atual (CT 100 daemon Node)
- Mantém free + control total
- Alinhado com PR #855 (schema 3-identifiers) + ADR 0145 (contact_lid canônico) — destrava esses

**Quando executar:** **AGORA.** Próxima ação: spawn agent migração Baileys 7.x seguindo skill `baileys-update-procedure` (5-fase já documentado). Canary 7d em biz=99 sombra antes de promover biz=1.

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

## Opção D — Z-API canary biz=99 sombra (30 dias) — _adicionada 2026-05-15 pós-pergunta Wagner_

**Custos:**
- R$ 99,99/mês fixo (Plano Ultimate, 1 instância) × 1 mês canary = R$ ~100 total
- Setup: 5 min QR scan (driver ZapiDriver oimpresso 72/100 pronto)
- 2 dev-hours: criar canal biz=99 type=`whatsapp_zapi`, ativar sombra (mirror traffic só), métricas dashboard

**Ganhos (validados na auditoria 2026-05-15):**
- Z-API tem `Client-Token` middleware HMAC pronto + driver suporte send freeform/template/media/interactive
- Suporte BR PT-BR (vs Meta US tickets EN)
- Setup instantâneo (5 min vs 7-14 dias verify Meta)
- Custo previsível R$ 99,99 fixo independente volume

**Ganhos NÃO-validados (mito):**
- ❌ **NÃO resolve cross-contact LID** — Z-API é wrapper Baileys-like, mesmo blackbox (doc oficial admite *"It is not possible to convert an `@lid` to a phone number"*)
- ❌ **NÃO tem auditoria externa do claim 0,3% ban rate**
- ❌ Reclame Aqui 3.8/10 + tempo médio resposta **37 dias** — incompatível com ROTA LIVRE (99% volume)

**Quando ativar:** apenas se Wagner quiser comparar empiricamente. **NÃO substitui Opção C** (refactor `contact_lid` canônico necessário pra Z-API funcionar também). **NÃO migrar biz=1 prod** — só biz=99 sandbox sombra.

## Opção E — Evolution API self-host CT 100 — _adicionada 2026-05-15 turno 2 (engenharia interna)_

**Custos:**
- VPS extra (Postgres + Redis dedicados pra Evolution): pode reusar Postgres CT 100 existente + Redis → **R$ 0 marginal**
- Docker container `evolution-api:2.3.7` no CT 100: **R$ 0** (CT 100 tem capacidade)
- 3-5 dev-days IA-pair: criar `EvolutionApiDriver` no oimpresso (similar a `ZapiDriver`, ~250 linhas) + `EvolutionApiWebhookController` + Pest fixtures + smoke biz=99
- Operacional: manter Docker + monitorar atualizações Baileys quando Evolution publica (commit cadence: 53 releases até dez/2025 — ~1 release/2 semanas)

**Ganhos:**
- **Multi-tenancy nativo** — 1 Evolution instance roda N WhatsApp accounts isoladas (vs 1 daemon Baileys = 1 número hoje)
- **Dual mode**: mesmo container expõe REST API tanto pra Baileys-WhatsApp Web Web socket quanto pra Cloud API oficial Meta — migração biz-a-biz sem trocar driver oimpresso
- **Webhook REST padronizado** — não precisa mais gerenciar daemon Node custom CT 100; gateway HTTP estável
- **Comunidade ativa BR**: 8.3k stars, 297 issues abertas, Discord BR ativo, atualização Baileys ~2 semanas
- **Open-source self-host**: zero vendor lock-in (vs Z-API SaaS)
- **LGPD**: dados ficam no CT 100 (BR)

**Riscos (calibrados):**
- ❌ **Mesma lib Baileys 7.0.0-rc.5** = mesmos bugs (cross-contact LID + history sync). Evolution **NÃO resolve** problema raiz, **só padroniza interface**
- ❌ **Empresa1p comparativo** ([link](https://empresa1p.com.br/comparativo-de-apis-de-whatsapp-z-api-vs-uazapi-vs-evolution-api/)) reporta: *"Bugs constantes e aleatórios, problemas com leitura de QR Code, necessidade de recriar containers Docker"* — sintoma de Baileys-rc cycle
- ❌ **Pablo Cabral**: *"clientes migraram da Evolution API para Z-API após perder vendas por instabilidades"* — anti-ban infra é o gap, não a lib
- ❌ Adiciona 1 stack (Postgres + Redis + Docker dedicated) no CT 100 — monitor + backup + atualização novos
- ❌ Sem suporte comercial SLA (vs Z-API PT-BR ou Cloud API Meta)

**Quando ativar:** **NÃO antes de fechar refactor `contact_lid` canônico** (P0-1 + P0-2 em §6 acima). Evolution só faz sentido se: (a) oimpresso decidir oferecer **WhatsApp como produto multi-instância nas verticais Modules/<X>** (cada cliente vertical com seu canal isolado escalável) E (b) custo Cloud API ($0.004-0.0625/msg) ficar proibitivo no plan de venda. Hoje **não atende** ROTA LIVRE (1 canal, 1 número, 99% volume) — Baileys puro endurecido (Opção C) cobre. **Considerar em 6-12 meses** se vertical Vestuario ganhar 5+ clientes paralelos OU se Cloud API rejeitar ROTA LIVRE no embedded signup. Score 61% vs 58% Baileys puro = +3pp marginal, não justifica adicionar stack hoje.

---

## Recomendação executável

**Fazer Opção C hoje** (P0-1+P0-2+P1-3 = 4h IA-pair, ✅ **PRs #855-857 abertos 2026-05-15**) **+ stub Opção A** (preparar `MetaCloudDriver` operacional + 1 biz canary não-prod, ✅ **PR #858 aberto**) + **Opção D opcional** (canary biz=99 Z-API sombra 30 dias se Wagner quiser comparar). **Opção E (Evolution API) parqueada 6-12 meses** — só faz sentido se modelo de produto pivotar pra multi-WhatsApp escalado por vertical.

Por quê:
1. **P0-1 (schema 3-identifiers) é zero-regret** — útil em qualquer das 5 opções futuras. Se Wagner amanhã decide Cloud API OU Z-API OU Baileys 7.x OU Evolution API, está pronto.
2. **P1-3 (backup auth_state) é incident-trigger** — Wagner viveu na pele 14/mai. Custo 30min, vital.
3. **Cloud API canary biz=99 (Wagner test biz)** valida custo real + tempo aprovação HSM templates antes de decidir migração production-wide.
4. **Baileys 7.x EXECUTAR migração agora** — Wagner decidiu irreversível 13-15/mai ([feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md)). Skill `baileys-update-procedure` Tier B documenta processo 5-fase. Canary 7d biz=99 sombra antes de promover biz=1.
5. **Z-API NÃO é solução pro cross-contact** — é trade de risco (self-host CT 100 zero-custo) por SaaS BR (R$ 100/mês + Reclame Aqui 3.8/10 + vendor lock-in). Se Wagner quiser comparar, faz em biz=99 sandbox sombra, **nunca biz=1 prod sem refactor `contact_lid` canônico antes**.
6. **Evolution API self-host NÃO é solução pro cross-contact tampouco** — mesma lib Baileys 7.0.0-rc.5, mesmos bugs. Só faz sentido se modelo de produto pivotar pra multi-WhatsApp escalado por vertical (6-12 meses out).

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
- [Issue #1869 — High number of bans on WhatsApp](https://github.com/WhiskeySockets/Baileys/issues/1869)
- [Hermes-agent issue #11951 — syncFullHistory:false disables history in 7.x](https://github.com/NousResearch/hermes-agent/issues/11951)
- [Openclaw issue #19907 — Baileys RC9 Auth Breaking 401 device_removed](https://github.com/openclaw/openclaw/issues/19907)
- [Baileys releases](https://github.com/WhiskeySockets/Baileys/releases)

## Z-API (provider BR — adicionado 2026-05-15)
- [Z-API home / pricing](https://z-api.io/)
- [Z-API Lid Docs](https://developer.z-api.io/en/tips/lid)
- [Z-API Docs introduction (engine indícios "channel WhatsApp Web" + "emulate phone")](https://developer.z-api.io/en/)
- [Z-API Blog — LID no WhatsApp e como funciona](https://www.z-api.io/blog/lid-no-whatsapp-e-como-funciona/)
- [Z-API Blog — LID por que aparece e como tratar](https://www.z-api.io/blog/lid-no-whatsapp-o-que-e-por-que-aparece/)
- [Z-API webhook on-message-received](https://developer.z-api.io/en/webhooks/on-message-received)
- [Z-API Blog — bloqueios e banimentos no WhatsApp](https://www.z-api.io/blog/bloqueios-e-banimentos-no-whatsapp/)
- [Z-API Blog — API do WhatsApp muda modelo de cobrança](https://www.z-api.io/blog/api-do-whatsapp-muda-modelo-de-cobranca/)
- [Z-API Blog — como funciona Z-API e vantagens sobre concorrentes](https://www.z-api.io/blog/como-funciona-a-api-do-z-api-e-suas-vantagens-sobre-concorrentes/)
- [Z-API Política de privacidade](https://www.z-api.io/politica-de-privacidade/)
- [Z-API Reclame Aqui (3.8/10)](https://www.reclameaqui.com.br/empresa/z-api/)
- [Z-API/whatsapp-api-nodejs GitHub — exemplos integração Node.js](https://github.com/Z-API/whatsapp-api-nodejs)
- [CNPJ Z Brasil Informática LTDA (Maringá/PR)](https://cnpj.biz/46974205000179)
- [Pablo Cabral — Z-API vs Evolution API comparativo BR](https://pablocabral.com.br/z-api-ou-evolution-api-qual-a-melhor-opcao-para-automacao/)

## Evolution API / WAHA / WPPConnect / Uazapi / whatsapp-web.js (engenharia interna — adicionado 2026-05-15 turno 2)
- [EvolutionAPI/evolution-api GitHub (8.3k stars, v2.3.7, Baileys 7.0.0-rc.5)](https://github.com/EvolutionAPI/evolution-api)
- [Evolution API CHANGELOG (Baileys version)](https://github.com/EvolutionAPI/evolution-api/blob/main/CHANGELOG.md)
- [Evolution API issue #2258 — Baileys v7 upgrade](https://github.com/EvolutionAPI/evolution-api/issues/2258)
- [Evolution API docs — Docker install](https://doc.evolution-api.com/v2/en/install/docker)
- [DeepWiki — Evolution API WAMonitoringService multi-tenant](https://deepwiki.com/EvolutionAPI/evolution-api)
- [gurusup blog — Evolution API self-host alternative](https://gurusup.com/blog/evolution-api-whatsapp)
- [devlikeapro/waha GitHub — 3 engines WEBJS/NOWEB/GOWS](https://github.com/devlikeapro/waha)
- [WAHA docs — NOWEB engine (Baileys)](https://waha.devlike.pro/docs/engines/noweb/)
- [WAHA docs — GOWS engine (whatsmeow Go)](https://waha.devlike.pro/docs/engines/gows/)
- [WAHA issue #1796 — Difference between NOWEB and WEBJS](https://github.com/devlikeapro/waha/issues/1796)
- [pedroslopez/whatsapp-web.js GitHub — Puppeteer-based](https://github.com/pedroslopez/whatsapp-web.js)
- [whatsapp-web.js issue #5817 — High memory leak 1GB infinite loop](https://github.com/pedroslopez/whatsapp-web.js/issues/5817)
- [whatsapp-web.js issue #88 — High CPU memory many chats](https://github.com/pedroslopez/whatsapp-web.js/issues/88)
- [wppconnect-team/wppconnect GitHub (1.5k stars)](https://github.com/wppconnect-team/wppconnect)
- [asternic/wuzapi GitHub — whatsmeow REST wrapper Go](https://github.com/asternic/wuzapi)
- [YouTube Análise Uazapi — Motor GoWS = whatsmeow](https://www.youtube.com/shorts/zK9WS_NsyG4)
- [Empresa1p — Comparativo Z-API vs Uazapi vs Evolution API](https://empresa1p.com.br/comparativo-de-apis-de-whatsapp-z-api-vs-uazapi-vs-evolution-api/)
- [kobie3717/baileys-antiban — middleware Gaussian jitter + warm-up](https://github.com/kobie3717/baileys-antiban)

## whatsmeow (Go alternative)
- [whatsmeow discussion #846 — sender_pn](https://github.com/tulir/whatsmeow/discussions/846)
- [whatsmeow discussion #905 — sender_pn and @lid](https://github.com/tulir/whatsmeow/discussions/905)
- [whatsmeow discussion #979 — Whatsmeow vs Baileys for 10K device scale](https://github.com/tulir/whatsmeow/discussions/979)
- [whatsmeow issue #473 — Sending to HiddenUserServer](https://github.com/tulir/whatsmeow/issues/473)
- [whatsmeow issue #871 — Retry resolving LID to PN](https://github.com/tulir/whatsmeow/issues/871)
- [whatsmeow issue #859 — Error 479 LID with no phone mapping](https://github.com/tulir/whatsmeow/issues/859)
- [whatsmeow issue #810 — "Your account may be at risk" warning](https://github.com/tulir/whatsmeow/issues/810)
- [whatsmeow send.go — messageSecret 32 bytes random](https://github.com/tulir/whatsmeow/blob/main/send.go)

## WAHA / whatsapp-web.js / WPPConnect (libs vizinhas — legacy)
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

## Ban risk / governança / anti-ban techniques
- [Kraya AI — WhatsApp Automation Ban Risk 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)
- [Agência Rollin — API Oficial vs Não Oficial 2026](https://www.agenciarollin.com/blog/api-oficial-whatsapp-vs-nao-oficial-guia-completo-2026)
- [WASenderApi — Anti-ban guide unofficial APIs 2025](https://wasenderapi.com/blog/stop-getting-banned-the-ultimate-whatsapp-anti-ban-strategy-for-unofficial-apis-in-2025)
- [Warmer.wadesk.io — Warm-up 2026 strategy](https://warmer.wadesk.io/blog/whatsapp-account-warm-up)
- [quackr.io — Warm Up WhatsApp Number 2025](https://quackr.io/blog/warm-up-whatsapp-number/)
- [whatsnap.ai — Warmup WhatsApp without ban 2025](https://whatsnap.ai/blog/warmup-whatsapp-without-getting-banned)

---

**Próxima ação concreta:** se Wagner aprova Opção C + stub Cloud API canary, abro 3 PRs separados:
1. `claude/whatsapp-schema-3-identifiers` — migration + observer + Pest (P0-1, P0-2)
2. `claude/whatsapp-auth-state-backup` — script CT 100 + cron + runbook (P1-3)
3. `claude/whatsapp-protocol-regression-fixture` — Pest fixture (P1-4)

Estimativa total: 4-6h IA-pair, ≤500 linhas distribuídas em 3 PRs ≤300 linhas cada (regra `commit-discipline` Tier A).
