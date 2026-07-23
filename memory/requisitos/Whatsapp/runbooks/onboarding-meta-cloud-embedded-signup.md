---
id: requisitos-whatsapp-runbooks-onboarding-meta-cloud-embedded-signup
---

# Runbook — Onboarding Meta Cloud via Embedded Signup v4

> **Status:** canon US-WA-310 Fase 2 (ADR 0202)
> **Quem executa:** Wagner (admin) — passo 1-6 são 1x por instalação
> **Tempo total:** 30-60 minutos (criação Meta App) + 5-15 min por business onboardado

Este runbook descreve como configurar o **Meta App** que o oimpresso usa
pra Embedded Signup v4. Após configurado, qualquer admin de business
pode conectar seu WhatsApp Business em 5-15 minutos via `/whatsapp/settings`
sem fricção técnica.

## Pré-requisitos

- Conta Meta Business Manager ativa (Wagner já tem em `oimpresso.com`)
- Acesso SSH ao Hostinger (env vars)
- Webhook endpoint canônico já configurado: `https://oimpresso.com/api/whatsapp/webhook/meta/{biz_uuid}`
  (já estava em prod desde ADR 0096 — apenas reusar)

## Passo 1 — Criar Meta App em developers.facebook.com

1. Acessar https://developers.facebook.com/apps/
2. Clicar **"Create App"** → tipo **"Business"** → Next
3. Display Name: `oimpresso WhatsApp Bridge`
4. App Contact Email: wagnerra@gmail.com (admin)
5. Business Manager: selecionar **oimpresso.com** (já criada)
6. Submit → entra no App Dashboard

## Passo 2 — Adicionar produto "WhatsApp"

1. Dashboard → "Add products" → procurar **"WhatsApp"** → "Set up"
2. Selecionar Business Portfolio: **oimpresso.com**
3. Meta cria automaticamente um WABA test associado ao app
4. Reservar o `App ID` (topo da página, ~16 digits) e `App Secret` (Settings →
   Basic → Show)

## Passo 3 — Configurar Embedded Signup v4

1. App Dashboard → produto WhatsApp → **"Configuration"** → "Embedded Signup"
2. Clicar **"Create configuration"**:
   - Use case: **"Solution Partner"** (oimpresso revende WhatsApp pra businesses dele)
   - Subscription type: **"Standard"** (free tier Meta cobre 1k conversas/mês BR)
   - Permissions: ✓ `whatsapp_business_messaging` + ✓ `whatsapp_business_management`
3. Confirmar → Meta gera **Configuration ID** (~16 digits) — reservar

## Passo 4 — Configurar Webhook

1. App Dashboard → produto WhatsApp → **"Webhooks"**
2. Subscription URL: `https://oimpresso.com/api/whatsapp/webhook/meta/{biz_uuid}`
   - O `{biz_uuid}` é per-business — Meta aceita placeholder na URL de cadastro;
     subscription real acontece via `provisionViaEmbeddedSignup` no callback
   - Para o cadastro inicial use o uuid de Wagner biz=1
3. Verify Token: gerar string aleatória ≥32 chars (`openssl rand -hex 32`)
4. Fields a assinar: ✓ `messages` + ✓ `message_template_status_update`
5. "Verify and Save"

## Passo 5 — Configurar Redirect URI (OAuth callback)

1. App Dashboard → Settings → **Basic** → "Add Platform" → "Website"
2. Site URL: `https://oimpresso.com`
3. Settings → **"Use cases"** → "WhatsApp" → permissions:
   - Valid OAuth Redirect URIs: **`https://oimpresso.com/whatsapp/settings/meta-embedded-callback`**
4. Save

## Passo 6 — Adicionar env vars no Hostinger

SSH no Hostinger e editar `.env` em `~/domains/oimpresso.com/public_html/`:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
nano .env
```

Adicionar (substituir VALUES pelos reais do Meta App):

```env
# US-WA-310 Fase 2 (ADR 0202) — Embedded Signup v4
WHATSAPP_DEFAULT_DRIVER=meta_cloud

META_APP_ID=1234567890123456
META_APP_SECRET=abcdef0123456789abcdef0123456789
META_BUSINESS_CONFIG_ID=9876543210987654

# Já existente (NÃO duplicar — confirmar presença):
WHATSAPP_META_API_VERSION=v21.0
WHATSAPP_META_BASE_URL=https://graph.facebook.com
WHATSAPP_META_TIMEOUT=10
```

Salvar (`Ctrl+O`, Enter, `Ctrl+X`) e limpar cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## Passo 7 — Validar setup

1. Acessar `https://oimpresso.com/whatsapp/settings` logado como admin biz=1
2. Deve mostrar wizard "Conectar com Meta" sem aviso amarelo
3. Clicar **"Conectar com Meta"** → popup Facebook abre em 600x750
4. Autorizar app + escolher WABA + número → popup fecha
5. Card deve virar verde "Conectado via Meta Cloud" com display_phone correto
6. Verificar logs em `storage/logs/laravel.log` por entrada
   `whatsapp.embedded_signup.success` com `phone_redacted` (não phone completo)

## Custo declarado

- **Meta Cloud free tier BR:** 1.000 conversas user-initiated / mês
- **Após cap:** R$ [redacted Tier 0]-0,15 por conversa (varia por tipo: utility/marketing/auth)
- **Estimado oimpresso 2026:** biz=1 + biz=4 < 500 conversas/mês = **R$ [redacted Tier 0] prod**
- **Latência:** 4 chamadas Graph sequenciais ~2-4s (popup OAuth é maior parte do tempo)

## Troubleshooting

| Erro | Causa provável | Resolução |
|---|---|---|
| `meta_app_not_configured` (503) | env vars ausentes | Passo 6 |
| Popup "Invalid redirect_uri" | URI não cadastrada no App | Passo 5 |
| Popup OK, callback 422 csrf_state_mismatch | Cookie de sessão expirado durante popup | Reiniciar fluxo (Meta state vive ~10min) |
| Callback 500 "Meta /me/businesses falhou: HTTP 400" | User não tem WABA criado | Usuário cria WABA primeiro em business.facebook.com |
| Card verde mas mensagens não chegam | Webhook URL errado | Passo 4 — confirmar URL contém `{biz_uuid}` correto |

## Rollback

Se algo der errado durante onboarding biz=1:

```bash
# Remover config (volta pro estado pre-conexão)
php artisan tinker --execute="\\Modules\\Whatsapp\\Entities\\WhatsappBusinessConfig::where('business_id', 1)->update(['driver_health' => 'never_checked', 'meta_access_token' => null]);"
```

Wagner reinicia o fluxo via `/whatsapp/settings` quando estiver pronto.

## Referências

- ADR 0202 — Meta Cloud default universal + Baileys OUT
- ADR 0096 — Módulo Whatsapp Meta Cloud API direto
- US-WA-310 — SPEC.md
- Embedded Signup v4 docs: https://developers.facebook.com/docs/whatsapp/embedded-signup
- Implementation guide: https://developers.facebook.com/docs/whatsapp/embedded-signup/implementation
