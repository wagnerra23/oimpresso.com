# Runbook — Ativar Cloud API Meta canary em biz=99 sandbox

> **Status:** preparado 2026-05-15, **NÃO executado ainda** — aguarda Wagner ler estudo + decidir
>
> **Origem:** decisão estratégica pós-estudo protocol-level (Cloud API ganha 71% no comparativo % vs Baileys 6.7.9 endurecido 58%). Custo R$ 90-120/mês ROTA LIVRE cabe na meta R$ 5mi/ano ([ADR 0022](../../../decisions/0022-meta-5mi-ano-financeira.md)).
>
> **Diferença pra PR #858 (já mergeável):** PR #858 adicionou `MetaCloudDriver::parseInboundWebhook()` + 4 Pest mock tests + template `.env.canary.example`. Esse runbook documenta a **ativação operacional real** (Meta Business + HSM templates + webhook endpoint + SQL biz=99 setup) — não tocar biz=1 prod.

## Pré-requisitos Wagner (manual, fora código)

### 1. Meta Business Manager (1-3 dias)

- [ ] Acessar https://business.facebook.com/
- [ ] Criar Business Manager **separado pra teste canary** (não usar a conta ROTA LIVRE existente — isolamento estrito)
- [ ] Verificar negócio (Business Verification) — Meta pede docs CNPJ + comprovante endereço. Tempo médio: 1-3 dias úteis
- [ ] Confirmar permissões: "Business Admin" + "WhatsApp Account Admin"

### 2. WhatsApp Business Account (WABA)

- [ ] Em Business Manager → "WhatsApp Accounts" → "Adicionar" → WhatsApp Business Account
- [ ] Adicionar número de **TESTE** (NÃO o número de ROTA LIVRE). Opções:
  - Comprar SIM card BR R$ 30-50 dedicado ao teste
  - Reutilizar número antigo desativado se não estava em WhatsApp Business há 90+ dias
- [ ] Verificar via SMS + voz Meta envia (5 min)

### 3. Phone Number ID + Tokens

Após verify:
- [ ] Anotar **Phone Number ID** (ex: `123456789012345`) — Wagner copia da UI Meta
- [ ] Gerar **System User Token** (long-lived, 60d default + renovação): Business Settings → System Users → Add → "Permanent Token"
- [ ] Anotar **App Secret** + **App ID** — Apps → "Add App" → WhatsApp use case
- [ ] Definir **Webhook Verify Token** (string random qualquer — ex: gerar via `openssl rand -hex 32`)

### 4. HSM Templates (1-3 dias aprovação Meta)

Criar pelo menos 1 template canary "hello_world" simples:
- [ ] Business Manager → WhatsApp Manager → Templates → "Criar"
- [ ] Categoria: **UTILITY** (mais barato $0.0040/msg BR vs MARKETING $0.0625)
- [ ] Idioma: pt_BR
- [ ] Nome: `canary_hello_v1`
- [ ] Body: `Olá {{1}}, este é um teste do canary Cloud API oimpresso biz=99. Sem ação necessária.`
- [ ] Aguardar aprovação (1-3 dias úteis Meta review)

### 5. Webhook endpoint público

Cloud API Meta envia webhooks pra URL HTTPS pública. Setup:
- [ ] **Subdomínio dedicado canary** (não misturar com prod): `meta-canary.oimpresso.com` apontando pro Hostinger Laravel
- [ ] Cert SSL automático (Hostinger gerencia)
- [ ] Route `/whatsapp/meta-cloud/webhook/{channel_uuid}` já existe (?) — confirmar via `php artisan route:list | grep meta` (TODO)

## Pré-requisitos código (PRs adicionais necessários)

PR #858 (já mergeável) é STUB. Pra ativar canary REAL, precisa ainda:

- [ ] **PR-A** — `ChannelMetaCloudWebhookController` (similar `ZapiWebhookController`) com HMAC SHA-256 signature verify Meta (`X-Hub-Signature-256` header)
- [ ] **PR-B** — Rota `POST /whatsapp/meta-cloud/webhook/{channel_uuid}` + middleware `whatsapp.meta-cloud.signature`
- [ ] **PR-C** — Seeder/comando artisan `whatsapp:setup-meta-canary --biz=99` que cria `Channel` + `WhatsappBusinessConfig` + `WhatsappBusinessPhone` em biz=99 com config Meta
- [ ] **PR-D** — Pest test integração end-to-end mock: simula webhook Meta → ChannelDriverFactory → MetaCloudDriver → MessagePersister → Conversation criada

Estimativa código (calibrado [ADR 0106](../../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair):
- PR-A + PR-B: 2-3h IA-pair
- PR-C: 1-2h IA-pair
- PR-D: 1-2h IA-pair
- **Total: 4-7h IA-pair (~1 dev-day)**

## Sequência execução canary biz=99

### Dia 0: Setup Meta (Wagner)

1. Wagner executa Pré-requisitos 1-3 acima (Meta Business + WABA + tokens). Aguarda 1-3 dias Meta verify.

### Dia 1-3: Aguarda verify + cria templates (Wagner)

2. Wagner executa Pré-requisitos 4 (HSM templates). Aguarda aprovação Meta.

### Dia 4: Código (Claude)

3. Spawn 1 agent implementando PR-A/B/C/D em sequência. ~1 dev-day.

### Dia 5: Setup biz=99 (Claude + Wagner)

4. Wagner adiciona `.env.canary` com tokens reais Meta (Pré-req 3) — NÃO commitado (gitignored)
5. Claude roda `php artisan whatsapp:setup-meta-canary --biz=99` que cria Channel biz=99 dedicado
6. Wagner configura webhook URL no Meta Business: `https://meta-canary.oimpresso.com/whatsapp/meta-cloud/webhook/{channel_uuid_biz99}`
7. Meta envia challenge (verify_token) — controller valida + responde OK

### Dia 6-7: Smoke test (Claude + Wagner)

8. Claude executa `php artisan whatsapp:meta-canary-smoke --biz=99 --to=+5548...PESSOAL`:
   - Envia 1 template `canary_hello_v1` pra número pessoal Wagner
   - Wagner responde no WhatsApp
   - Webhook recebe + parseInboundWebhook extrai `wa_id`+`user_id`+`profile.name`
   - Conversation biz=99 criada com `phone_e164='+5548...'`, `bsuid='abc...'`, `lid=null` (Cloud API não tem)
9. Wagner valida visualmente no Inbox UI biz=99 que mensagens aparecem corretamente

### Dia 7-30: Canary observation (Claude)

10. Métricas OTel coletadas:
    - `whatsapp_meta_canary_msgs_sent_total{biz=99}` — quantas saíram
    - `whatsapp_meta_canary_msgs_received_total{biz=99}` — quantas chegaram
    - `whatsapp_meta_canary_webhook_signature_failures_total{biz=99}` — alertar se >0
    - `whatsapp_meta_canary_template_rejected_total{biz=99}` — fora janela 24h
11. Custo real medido via dashboard Meta Business — comparar com estimativa R$ 90-120/mês

### Dia 30+: Decisão Wagner

12. Se uptime >99% E custo real ≤R$ 150/mês E zero cross-contact incident → **promover Cloud API a primary driver futuro biz=1** (ADR formal nova)
13. Se uptime ruim OU custo alto OU friction (HSM templates ban) → **reverter pra Opção C** (Baileys 6.7.9 endurecido continua), arquivar canary biz=99

## Riscos + mitigações

| Risco | Mitigação |
|---|---|
| Meta verify business demora >3 dias | Wagner pode pedir support@business.facebook.com — costuma acelerar |
| HSM templates rejeitados | Manter neutralidade — texto NÃO comercial, NÃO promocional. Categoria UTILITY |
| Webhook signature inválida | Middleware retorna 401 + log alerta — Claude debug em ≤1h |
| Account Meta banido | Canary biz=99 isolado — biz=1 prod NÃO afetado |
| Custo explode acima R$ 200/mês | OTel alarme `whatsapp_meta_canary_msgs_sent_total >50/dia` → para envio automático |

## Tier 0 garantias

- ⛔ NÃO toca biz=1 prod, NÃO toca daemon Baileys CT 100, NÃO toca tabela `messages` legacy
- ⛔ Canary biz=99 100% isolado — Channel/Config/Phone próprios
- ⛔ Tokens Meta NUNCA em git/log/test — só `.env.canary` gitignored
- ⛔ Webhook HMAC SHA-256 obrigatório (rejeita request sem signature)
- ⛔ Rate limit hard: 50 msgs/dia max biz=99 canary (config flag)
- ⛔ Multi-tenant Tier 0 ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)) — todas queries com `business_id=99` explícito

## Próxima ação concreta

Wagner decide: começa **Dia 0** (Meta Business + WABA + tokens) HOJE OU agenda pra próximo sprint?

Se HOJE: Claude prepara PR-A/B/C/D em paralelo enquanto Wagner espera Meta verify (1-3 dias).

Se próximo sprint: este runbook fica documentado, executa depois com mesmo passo-a-passo.

## Referências

- [memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](../../../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) — estudo 797 linhas, score 71%
- [ADR 0146](../../../decisions/0146-contact-lid-canonico-pk-refactor.md) — refactor contact_lid (feature-wish ativa quando canary maduro)
- [PR #858](https://github.com/wagnerra23/oimpresso.com/pull/858) — stub MetaCloudDriver pronto
- [Meta WhatsApp Cloud API docs](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Meta Business verification](https://www.facebook.com/business/help/2058515294227817)
- [Meta HSM template guidelines](https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates)
