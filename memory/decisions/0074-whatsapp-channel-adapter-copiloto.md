---
slug: 0074-whatsapp-channel-adapter-copiloto
number: 0074
title: "Channel adapter WhatsApp pro Copiloto — multi-canal sem clonar lógica de chat"
type: adr
status: superseded_partially
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: Copiloto
quarter: 2026-Q3
tags: [whatsapp, channel-adapter, multi-canal, copiloto, larissa, integration]
supersedes: []
supersedes_partially: []
superseded_by: [0075]
related: [0035, 0048, 0050, 0058, 0059, 0060, 0073, 0075]
pii: true
review_triggers:
  - "Meta WhatsApp Cloud API mudar pricing > +50% (rever vendor)"
  - "Larissa pedir Telegram/Instagram (avaliar adapter genérico)"
  - "Volume mensagens > 10k/mês × tenant (avaliar Horizon worker dedicado)"
---

# ADR 0074 — Channel adapter WhatsApp pro Copiloto

> ⚠️ **Superseded partially por ADR 0075 (2026-05-05)** — escolha de provider revisada.
> O **adapter pattern** (interface `ChatChannel`, `ChannelIdentityResolver`, opt-in LGPD, schema multi-tenant, webhook em CT 100, métricas OTel) **continua válido** e é a base.
> O **default provider para Fase 0 (dogfooding) e Fase 1 (beta)** mudou de Meta Cloud API → Evolution API self-host (Fase 0) → Z-API (Fase 1) → Meta Cloud API (Fase 2 — escala). Pesquisa fresca mai/2026 mostrou que Z-API custa R$55/mês fixo (praticamente não-pago) e Evolution API tem ecosystem Laravel maduro pra dogfooding zero-custo. Detalhes na ADR 0075.

## Contexto

Copiloto hoje só fala via web (Inertia + React, `/copiloto`). Larissa (ROTA LIVRE, biz=4) já pediu informalmente acesso por WhatsApp — é onde ela vive durante o dia. Pedido se repete em `cliente_rotalivre.md` auto-mem e nas sessões 17-19 (handoff narrativo).

OpenClaw demonstra o padrão certo (pesquisa mai/2026): **channel adapter** como módulo separado do agent core. Eles têm 30+ canais (WhatsApp, Telegram, Discord, etc.), todos plugando no mesmo agent. **Não clonam a lógica de chat** — adaptam I/O.

**Por que multi-canal não é só "feature visual":**
- WhatsApp tem regras próprias (24h customer service window, message templates pré-aprovados pra outbound, opt-in obrigatório).
- Identidade do remetente (`+55 11 9XXXX...`) precisa mapear pra `user_id` + `business_id` no UltimatePOS — NÃO é trivial; multi-tenant scope tem que ser perfeito ou vaza dado entre clientes.
- Mídia (foto de orçamento, áudio de pedido) entra como input → processamento ≠ texto puro.
- LGPD: WhatsApp = canal de comunicação com cliente da Larissa; mensagens contêm PII de terceiros. Retention precisa ser explícita.

**Alternativas de provider avaliadas:**
1. **WhatsApp Cloud API (Meta direto)** — oficial, custo R$0,03-0,28/conversa (faixa BR), webhook simples, infra Meta. ✅ default.
2. **Twilio WhatsApp** — wrapper sobre Cloud API, +30% custo, suporte multi-país melhor. ❌ overkill pra escopo BR.
3. **Z-API / WppConnect / Baileys (não-oficial)** — barato/grátis mas viola ToS Meta, banimento aleatório do número. ❌ inviável pra cliente real.
4. **WPPConnect via CT 100 self-host** — mesma família dos não-oficiais. ❌ mesmo motivo.

**Onde rodar o webhook receiver:**
- Hostinger NÃO (ADR 0062 — sem daemons longos, sem Horizon de fila pesada).
- CT 100 SIM (FrankenPHP + Centrifugo já lá, ADR 0058) — encaixa.
- Tunnel Tailscale CT 100 ↔ Meta cloud OK (CT 100 já tem Tailscale 100.99.207.66).

## Decisão

Implementar **submódulo `Modules/Copiloto/Channels/`** com:

1. **Interface `ChatChannel`** — abstração mínima:
   ```php
   interface ChatChannel {
       public function receive(IncomingMessage $msg): ConversationContext;  // wire → conversa_id + user_id + business_id
       public function send(ConversationContext $ctx, string $reply): void; // resposta → wire
       public function name(): string;                                      // 'web' | 'whatsapp' | ...
   }
   ```
2. **Driver `WhatsAppCloudChannel`** usando WhatsApp Cloud API oficial (Meta).
3. **Webhook receiver** em `POST /api/copiloto/whatsapp/webhook` (rota CT 100 via `mcp.oimpresso.com`, FrankenPHP). Valida signature `X-Hub-Signature-256`.
4. **Tabela `copiloto_channel_identity`** mapeia `(channel='whatsapp', wire_id='+5511...')` → `(user_id, business_id)`. Onboarding manual no admin no início; auto-link futuro via opt-in.
5. **Job assíncrono** `ProcessWhatsAppMessageJob` (Horizon CT 100) — pega mensagem, resolve conversa, chama mesma `ChatService::send()` do web.
6. **Templates outbound** (HSM) registrados na Meta com versão. Notificação proativa SÓ via template aprovado; resposta dentro de 24h-window pode ser livre.
7. **Mídia** — foto/áudio salvo em S3-compat (já temos via UltimatePOS) com escopo `business_id`; stub OCR/STT pra fase 2.
8. **Feature flag `COPILOTO_WHATSAPP_ENABLED=false`** + canário **biz=4 ROTA LIVRE only** primeiro.
9. **LGPD:** opt-in explícito no primeiro contato ("Você está conversando com o Copiloto da {business_name}. As mensagens serão armazenadas conforme nossa política. Para sair: digite SAIR."). Retention 365d (ADR 0059). Hard delete em `DELETE /copiloto/admin/channels/whatsapp/identity/{id}`.

## Justificativa

- **Por adapter pattern e não fork da `ChatController`**: a hora que Larissa pedir Telegram (~Q4), o trabalho é só novo driver — agent core não muda. OpenClaw provou que isso escala (30+ canais sobre mesmo core).
- **Por Meta Cloud API direto e não Twilio**: stack BR-only por ora (Larissa, futuras gráficas SP). Twilio adiciona +30% custo + camada extra sem ganho real. Reabrir se entrar cliente multi-país.
- **Por CT 100 e não Hostinger**: ADR 0062 fechou — Hostinger é shared hosting do app web; daemons (webhook receiver com fila + assinatura validation + media download) pertencem a CT 100. Webhook fica em `mcp.oimpresso.com/api/copiloto/whatsapp/*`.
- **Por canário biz=4 antes de geral**: dogfooding ROTA LIVRE (ADR 0049). Larissa é tester ativa que reporta. Sem isso, gargalos de UX (templates HSM rejeitados, latência alta, contexto perdido) viram incidente em escala.
- **Por opt-in LGPD obrigatório no primeiro turn**: WhatsApp envolve PII de terceiros (clientes da Larissa). LGPD Art. 7º consentimento explícito é gate legal, não opcional.
- **Por templates HSM versionados**: Meta exige aprovação de templates outbound; mudar fora do controle = bloqueio. Manter registry com versão evita drift.

**Quando reabrir:**
- Meta WhatsApp Cloud API mudar pricing > +50% (rever Twilio ou alternativa).
- Larissa/cliente novo pedir Telegram ou Instagram (avaliar adapter genérico Composio-style).
- Volume mensagens > 10k/mês por tenant (avaliar worker Horizon dedicado, separar do general queue).

## Consequências

**Positivas:**
- Larissa fala com Copiloto onde ela já vive — sem trocar de contexto pro web.
- Adapter pattern abre porta pra Telegram/Instagram/Discord no futuro com custo marginal.
- Toda lógica de chat (tools, memória, reranker, ContextoNegocio) reaproveita igual web — zero bug de drift entre canais.
- Onboarding com opt-in + retention explícita = LGPD-aware desde dia 1.
- Métricas OTel (ADR 0050) ganham label `gen_ai.channel=whatsapp` pra observabilidade.

**Negativas / Trade-offs:**
- Custo Meta R$0,03-0,28/conversa — entra na conta do cliente final ou oimpresso? **Decidir com Wagner antes de habilitar canário**. Default: oimpresso paga até definir pricing modelo SaaS.
- Templates HSM aprovados pela Meta levam 1-3 dias úteis. Slowness inerente.
- Webhook receiver vira ponto crítico de uptime (se cair > 5min, Meta retry e pode banir número).
- Mídia (foto/áudio) sobe storage S3 — custo adicional já contabilizado no UltimatePOS.
- WhatsApp = canal síncrono percebido. Latência > 5s vira "Copiloto travado" pro usuário. Combina com reranker (ADR 0072) que adiciona +200ms; fica tight.

**Riscos mitigados:**
- Multi-tenant leak (mensagem de cliente A virar contexto de cliente B) → `copiloto_channel_identity` tabela com FK ao `business_id` + global scope obrigatório (skill `multi-tenant-patterns` ativa).
- Banimento Meta → uso oficial Cloud API + opt-in correto (não usar não-oficiais).
- LGPD vazamento → opt-in + retention 365d + delete cascata + audit log.
- Spam/abuso → rate limit por wire_id (max 30 msg/min sem template).

## Implementação — referência rápida

```
Modules/Copiloto/Channels/
  Contracts/
    ChatChannel.php
    IncomingMessage.php
    OutgoingMessage.php
  Drivers/
    WebChannel.php                  # adapta o atual ChatController
    WhatsAppCloudChannel.php        # novo
  Services/
    ChannelIdentityResolver.php     # wire → user/business
    HsmTemplateRegistry.php         # templates outbound
    MediaIngestService.php          # foto/áudio → S3 + stub OCR/STT

Modules/Copiloto/Http/Controllers/Channels/
  WhatsAppWebhookController.php     # POST /api/copiloto/whatsapp/webhook

Modules/Copiloto/Jobs/
  ProcessWhatsAppMessageJob.php     # Horizon CT 100

Modules/Copiloto/Database/Migrations/
  2026_07_01_000001_create_copiloto_channel_identity.php
  2026_07_01_000002_create_copiloto_hsm_templates.php

Modules/Copiloto/Http/Controllers/Admin/
  ChannelIdentityController.php     # admin onboarding/LGPD delete
```

Config em `config/copiloto.php`:
```php
'channels' => [
    'whatsapp' => [
        'enabled' => env('COPILOTO_WHATSAPP_ENABLED', false),
        'business_phone_id' => env('META_WA_PHONE_ID'),
        'access_token' => env('META_WA_ACCESS_TOKEN'),    // long-lived 60d
        'webhook_secret' => env('META_WA_WEBHOOK_SECRET'),
        'verify_token' => env('META_WA_VERIFY_TOKEN'),
        'tenant_allowlist' => [4],                        // canário ROTA LIVRE
    ],
],
```

Tests Pest mínimos:
- `WhatsAppWebhookControllerTest` — assinatura X-Hub-Signature válida + invalida
- `WhatsAppCloudChannelTest` — receive/send happy + erro Meta + retry
- `ChannelIdentityResolverTest` — multi-tenant scope (NÃO retorna user de outro business)
- `ProcessWhatsAppMessageJobTest` — fila + retry + dedup msg_id
- Integration: opt-in flow (primeiro turn pede consentimento, segundo turn começa chat)

## Referências

- ADR 0035 — Stack-alvo IA (Copiloto = agent core; channel é I/O)
- ADR 0048 — Vizra rejeitada (definimos que mantemos arquitetura própria; agora ela escala em canais)
- ADR 0050 — OpenTelemetry GenAI (label `channel`)
- ADR 0058 — Centrifugo + FrankenPHP CT 100 (mesmo runtime do webhook)
- ADR 0059 — Governança self-host estilo Anthropic Team (retention 365d)
- ADR 0060 — Tudo rede interna Proxmox (CT 100 hospeda webhook)
- ADR 0062 — Separação runtime Hostinger ≠ CT 100
- ADR 0073 — Auto-capture turn-level (turns WhatsApp passam pela mesma pipeline)
- US-COPI-089 — implementa esta ADR (a ser anexada)
- OpenClaw channel adapter pattern — referência industry
- WhatsApp Cloud API — https://developers.facebook.com/docs/whatsapp/cloud-api
- LGPD Art. 7º — consentimento explícito; Art. 18 — direito ao esquecimento
