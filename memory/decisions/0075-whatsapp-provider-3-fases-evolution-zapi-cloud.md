---
slug: 0075-whatsapp-provider-3-fases-evolution-zapi-cloud
number: 0075
title: "WhatsApp provider em 3 fases — Evolution self-host (dogfooding) → Z-API (beta) → Meta Cloud (escala)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: Copiloto
quarter: 2026-Q3
tags: [whatsapp, provider, evolution-api, z-api, meta-cloud, channel-adapter, copiloto, fases]
supersedes: []
supersedes_partially: [0074]
superseded_by: []
related: [0035, 0058, 0059, 0060, 0073, 0074]
pii: true
review_triggers:
  - "Number do dogfooding banido pela 2ª vez no mesmo trimestre — antecipar Fase 1"
  - "Z-API mudar pricing > +50% ou perder estabilidade BR — antecipar Fase 2"
  - "Meta liberar pricing dramaticamente menor (< R$0,01/conversa) — pular Fase 1"
  - "Cliente pagante pedir WhatsApp antes de Fase 1 estar pronta"
---

# ADR 0075 — WhatsApp provider em 3 fases

## Contexto

ADR 0074 (mesmo dia, manhã) escolheu **Meta WhatsApp Cloud API oficial** como provider único, descartando Evolution API, Baileys e Z-API com argumento curto ("viola ToS, banimento aleatório"). Wagner pediu pesquisa fresca mai/2026 — dados mostraram que o argumento de "viola ToS" precisa ser nuançado por **fase de adoção** e **risco financeiro**:

**Dados frescos mai/2026:**
- **Evolution API** (open-source, Baileys-based): R$0 self-host. 6× crescimento em busca em 2026. Laravel client `samuelterra22` atualizado fev/2026. Filament plugin com multi-tenant. **Risco ban real e ativo** — issue [#2298](https://github.com/EvolutionAPI/evolution-api/issues/2298) de 2026: bloqueio QR 24h após 1-2 dias de uso normal. Bots Baileys de 3+ anos sendo banidos.
- **Baileys raw**: mesmo risco do Evolution, com mais fricção. `baileys-antiban` middleware testou 1000 msgs sem ban; em produção ("WhatsApp Auction live"). Mas issue [#1869](https://github.com/WhiskeySockets/Baileys/issues/1869) "alto número de bans" continua aberta.
- **Z-API** (BR wrapper Baileys): **R$55-99/mês fixo** (não R$/conversa). Ban rate reportado <0.3% (line marketing — bate com narrativas BR). Trial 3 dias. Suporte BR, billing R$. **Praticamente não-pago** (R$55 ≈ um almoço).
- **Meta Cloud API** (oficial): R$0,03-0,28/conversa. Zero ban risk. Custo cresce linear com volume.

**Razão pela revisão:**
- ADR 0074 otimizou pra "responsabilidade em produção massa" — etapa 3-4 da jornada.
- Ignorou que **Larissa = canário interno** (biz=4, ROTA LIVRE), não cliente pagante de terceiros. Banimento ali = lição barata, não incidente comercial.
- Ignorou que Z-API resolve o trade-off custo/risco no meio do caminho — mensalidade fixa em R$ é mais previsível que conversa-by-conversa Meta pra clientes pequenos.
- Wagner pediu explicitamente: "estudar alternativa ao pago".

## Decisão

Adotar estratégia **3-fase de WhatsApp** com **driver-per-fase atrás da mesma interface `ChatChannel`** (definida na ADR 0074, parte que segue válida):

### Fase 0 — Dogfooding (R$0)
- **Provider:** Evolution API self-host CT 100 (Docker compose, mesma stack ADR 0058 Centrifugo + FrankenPHP).
- **Quem usa:** Wagner + Larissa (biz=4 ROTA LIVRE) — canário interno; ninguém de fora.
- **Driver:** `EvolutionApiChannel` (novo) — Laravel client baseado em `samuelterra22/laravel-evolution-client` ou `happones/laravel-evolution-client`.
- **Risco aceito:** banimento do número de teste. Mitigação: número dedicado (chip pré-pago R$15), rotina de rotação se banir, NUNCA usar número pessoal.
- **Saída desta fase:** validar adapter pattern + opt-in flow + multi-tenant scope + métricas OTel + experiência de chat ponta-a-ponta funciona em prod CT 100.
- **Gate pra próxima fase:** 30 dias sem banimento OU 2 banimentos consecutivos (early exit pra Fase 1).

### Fase 1 — Beta clientes pequenos (R$55-99/mês fixo)
- **Provider:** Z-API (BR wrapper). Trial 3 dias antes do commit.
- **Quem usa:** 2-3 clientes piloto (negociar gratuidade ou desconto Sprint 0).
- **Driver:** `ZApiChannel` (novo) — REST HTTP simples, mesmo `ChatChannel` interface. Stub vira drop-in replacement do Evolution.
- **Por que Z-API e não outro wrapper BR:** R$ fixo, suporte BR, ban rate baixo reportado, trial sem cartão. Alternativas (UltraMsg, Twilio WhatsApp) ou são USD-billing, ou wrapper Baileys também (mesmo risco do Evolution sem o desconto financeiro).
- **Saída desta fase:** 3 clientes pagantes em produção 60d sem banimento. Métricas: latência p95, custo mês, satisfação Larissa-style.
- **Gate pra próxima fase:** volume > 5k conversas/mês × tenant **OU** 3 banimentos em 90d **OU** cliente enterprise pedir SLA contratual.

### Fase 2 — Escala (Meta Cloud R$0,03-0,28/conversa)
- **Provider:** Meta WhatsApp Cloud API oficial — exatamente como descrito na ADR 0074.
- **Quem usa:** clientes em volume + enterprise + qualquer ambiente que exija SLA contratual.
- **Driver:** `WhatsAppCloudChannel` — implementação prevista na ADR 0074.
- **Por que aqui e não antes:** custo R$/conversa só compensa com **volume e/ou exigência regulatória**. Pra Larissa 200 turns/mês × 1 tenant, Z-API ganha; pra 50 tenants × 5k turns/mês, Meta ganha (governance + SLA).
- **Coexistência:** clientes Fase 1 não migram automaticamente — só se pedirem ou se atingirem gate. Multi-driver convive na mesma instalação.

### Comum às 3 fases (herda de ADR 0074, segue válido)
- Interface `ChatChannel` única — drivers trocam, agent core não muda.
- Opt-in LGPD obrigatório no primeiro turn ("Para sair: SAIR.").
- Tabela `copiloto_channel_identity` mapeia `(channel, wire_id)` → `(user_id, business_id)` — multi-tenant scope.
- Webhook receiver em CT 100 (FrankenPHP) — NUNCA Hostinger.
- Retention 365d + delete cascata + audit log.
- Métricas OTel `gen_ai.channel=evolution|zapi|meta-cloud` pra observabilidade comparativa.
- Auto-capture turn-level (ADR 0073) atrás de todos os drivers — captura é channel-agnostic.

## Justificativa

- **Por estratégia em fases e não escolha única**: cada provider serve uma etapa diferente da jornada produto. Evolution = experimentação zero-custo; Z-API = beta com cliente pagante mas sem SLA; Meta Cloud = produção escala. Tentar usar Meta Cloud na Fase 0 desperdiça crédito Anthropic + Meta enquanto valida UX. Tentar usar Evolution na Fase 2 vira incidente comercial.
- **Por Evolution na Fase 0 e não Z-API trial direto**: Evolution dá controle total da stack (CT 100 Docker, mesmas ferramentas operacionais que já dominamos). Z-API trial só tem 3 dias; insuficiente pra validar adapter pattern + opt-in + multi-tenant + OTel + bugs de UX. Quando trocar pra Z-API na Fase 1, é só drop-in do driver.
- **Por Z-API na Fase 1 e não Meta Cloud**: R$55/mês fixo é praticamente não-pago. Permite oferecer WhatsApp como diferencial pra cliente pagante sem esconder R$/conversa imprevisível na conta. Risco ban repassado ao Z-API (incentivo deles manterem baixo).
- **Por Meta Cloud só na Fase 2**: pricing oficial só compensa com volume real. Antes disso, o custo R$/conversa pesa mais do que o ganho de SLA percebido pelo cliente.
- **Por driver-per-fase atrás de `ChatChannel`**: troca em 1 commit (mudar `config/copiloto.php` + adicionar driver class). Migration cliente Fase 1 → Fase 2 é gradual, não big-bang.
- **Por número dedicado dogfooding (não pessoal)**: chip pré-pago R$15 é seguro contra "se banir, perdi WhatsApp do Wagner". Linha pessoal NUNCA toca o adapter. Eliana e Felipe seguem mesma regra.

**Quando reabrir:**
- Number dogfooding banido 2× no mesmo trimestre — antecipa Fase 1 (ou estuda baileys-antiban).
- Z-API mudar pricing > +50% ou perder estabilidade — antecipa Fase 2.
- Meta liberar pricing dramaticamente menor (< R$0,01/conversa) — pula Fase 1.
- Cliente pagante pedir WhatsApp antes de Fase 1 estar pronta — força sequência (não pula etapas).

## Consequências

**Positivas:**
- Validação UX/adapter na Fase 0 sem queimar dinheiro com Meta Cloud antes de saber se a feature é boa.
- Beta com cliente pagante (Fase 1) por R$55/mês é viável imediatamente — não precisa esperar volume.
- Driver pattern garante que mudar provider é mudança isolada — agent core, memória, reranker, ContextoNegocio não tocam.
- Métricas OTel comparativas (latência, ban rate, custo R$) deixam dados pra decidir Fase 1→Fase 2 com base em fato, não palpite.
- Aprendizado cumulativo: Fase 0 ensina o que perguntar ao Z-API; Fase 1 ensina o que negociar com Meta.

**Negativas / Trade-offs:**
- **3 drivers em manutenção** (Evolution + Z-API + Meta Cloud) em momentos diferentes — risco de drift se não houver suite de testes do contrato `ChatChannel`. Mitigação: testes Pest do contrato (input/output) compartilhados entre drivers.
- **Fase 0 é cabra-cega controlada**: provedor pode mudar protocolo Baileys da noite pro dia (já aconteceu). Mitigação: número dedicado + rotina de rotação documentada.
- **Wagner precisa comprar chip pré-pago R$15 e ativar número dedicado** — tarefa fora-código.
- **ADR 0074 fica em estado misto** (`superseded_partially`) — adapter pattern dela continua válido, só provider escolhido mudou. Auditoria precisa entender a separação.
- **Pricing dogfooding é R$0 mas tempo é R$**: setup Evolution self-host + manutenção daemon CT 100 + lidar com bans = horas Wagner. Quantificar antes de assumir "grátis".

**Riscos mitigados:**
- Banimento na Fase 0 → número dedicado pré-pago, rotação documentada, NUNCA pessoal.
- Drift entre drivers → testes Pest contrato `ChatChannel` (mesmas asserções pra todos).
- Cliente Fase 1 ficar preso a Z-API → driver substituível, dados em `copiloto_channel_identity` portáveis.
- Esquecer auditar Fase 0 antes de Fase 1 → gate explícito (30d / 2 bans).

## Implementação — diff incremental sobre ADR 0074

```
Modules/Copiloto/Channels/
  Contracts/
    ChatChannel.php                          ← já previsto na ADR 0074
    IncomingMessage.php
    OutgoingMessage.php
  Drivers/
    WebChannel.php                            ← ADR 0074
    EvolutionApiChannel.php                   ← FASE 0 (novo, default)
    ZApiChannel.php                           ← FASE 1 (novo, drop-in)
    WhatsAppCloudChannel.php                  ← FASE 2 (novo, ADR 0074)
  Services/
    ChannelIdentityResolver.php               ← ADR 0074
    HsmTemplateRegistry.php                   ← só Fase 2 (Meta exige)
    MediaIngestService.php                    ← ADR 0074
```

Config evolui em fases:
```php
// config/copiloto.php
'channels' => [
    'whatsapp' => [
        'provider' => env('COPILOTO_WHATSAPP_PROVIDER', 'evolution'),  // 'evolution' | 'zapi' | 'meta'
        'enabled' => env('COPILOTO_WHATSAPP_ENABLED', false),

        // Fase 0 — Evolution self-host CT 100
        'evolution' => [
            'base_url' => env('EVOLUTION_BASE_URL', 'http://evolution.ct100.local:8080'),
            'api_key' => env('EVOLUTION_API_KEY'),
            'instance' => env('EVOLUTION_INSTANCE', 'oimpresso-canario'),
            'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        ],

        // Fase 1 — Z-API
        'zapi' => [
            'instance_id' => env('ZAPI_INSTANCE_ID'),
            'token' => env('ZAPI_TOKEN'),
            'webhook_secret' => env('ZAPI_WEBHOOK_SECRET'),
        ],

        // Fase 2 — Meta Cloud (ADR 0074)
        'meta' => [
            'business_phone_id' => env('META_WA_PHONE_ID'),
            'access_token' => env('META_WA_ACCESS_TOKEN'),
            'webhook_secret' => env('META_WA_WEBHOOK_SECRET'),
            'verify_token' => env('META_WA_VERIFY_TOKEN'),
        ],

        'tenant_allowlist' => [4],   // ROTA LIVRE canário em todas as fases
    ],
],
```

Webhook único multi-provider (router por header/path):
- `POST /api/copiloto/whatsapp/evolution/webhook`
- `POST /api/copiloto/whatsapp/zapi/webhook`
- `POST /api/copiloto/whatsapp/meta/webhook`

Cada um valida assinatura no formato do provider. Lógica downstream (resolver identity → enqueue job → ChatService) é compartilhada.

Tests Pest mínimos:
- `ChatChannelContractTest` (compartilhado) — assinatura input/output válida pros 3 drivers.
- `EvolutionApiChannelTest` — happy path + reconexão QR + retry após 1-2 dias.
- `ZApiChannelTest` — happy path + ban rate metric exposed.
- `WhatsAppCloudChannelTest` — assinatura X-Hub-Signature + HSM template lookup.
- `MultiProviderRoutingTest` — config switch troca driver sem reiniciar app.

## Custos comparativos (ROTA LIVRE como referência, ~200 conversas/mês)

| Fase | Provider | Custo mensal estimado | Risco | Quando |
|---|---|---|---|---|
| 0 | Evolution self-host | R$0 (+ chip pré-pago R$15 único) | 🔴 ban | Dogfooding 30d |
| 1 | Z-API | R$55 (plano básico) | 🟡 ban baixo | Beta 60-180d |
| 2 | Meta Cloud | R$6-56/tenant (200 conv × R$0,03-0,28) | 🟢 zero | Quando volume e/ou SLA exigirem |

## Referências

- ADR 0074 — Channel adapter WhatsApp (parte do adapter pattern segue válida; provider escolhido revisto aqui)
- ADR 0073 — Auto-capture turn-level (channel-agnostic)
- ADR 0058 — Centrifugo + FrankenPHP CT 100 (mesmo runtime hospeda webhooks)
- ADR 0059 — Governança self-host estilo Anthropic Team
- ADR 0060 — Tudo rede interna Proxmox
- US-COPI-089 — implementa esta ADR (atualizada pra começar pela Fase 0)
- [Evolution API GitHub](https://github.com/EvolutionAPI/evolution-api)
- [Issue #2298 Evolution v2.3.7 — bloqueio QR 24h (2026)](https://github.com/EvolutionAPI/evolution-api/issues/2298)
- [Baileys issue #1869 — high ban rate](https://github.com/WhiskeySockets/Baileys/issues/1869)
- [`samuelterra22/laravel-evolution-client`](https://github.com/samuelterra22/laravel-evolution-client) (atualizado fev/2026)
- [Evolution API webhooks docs](https://doc.evolution-api.com/v2/en/configuration/webhooks)
- [Z-API home + pricing](https://www.z-api.io/)
- [Filament WhatsApp Connector (Evolution v2 multi-tenant)](https://filamentphp.com/plugins/wallacemartins-whatsapp-connector)
- [WhatsApp Automation Ban Risk 2026 — kraya-ai](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)
