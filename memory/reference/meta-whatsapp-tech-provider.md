---
name: Meta WhatsApp Tech Provider + Embedded Signup — guia técnico 2026
description: Caminho canônico pra oimpresso revender WhatsApp Business em escala (4000+ clientes) — Wagner vira Tech Provider Meta direto, Embedded Signup elimina cadastro manual, custo R$ 0,034/msg utility BR.
type: reference
---
# Meta WhatsApp Business — Tech Provider + Embedded Signup

## Por que importa

Cadastro manual de phone numbers no Meta Business Manager é **inviável em escala** (~10-15min por número, sujeito a aprovação humana). Pra 4000 clientes (deal Agrosys 2026-05-12), única arquitetura viável é **Wagner = Tech Provider Meta** + **Embedded Signup**.

## Tech Provider vs alternativas

| Modelo | Quem cadastra | Fee fixo | Aprovação Meta | Fit 4000 |
|---|---|---|---|---|
| **Tech Provider direto** (recomendado) | Cliente final via Embedded Signup | **zero** | 4-8 semanas | ok |
| BSP 360dialog | Cliente via Embedded Signup deles | **€49-99/número/mês** | dias | inviável (€196k/mês de fee) |
| BSP Take Blip | Comercial manual | sob contrato | semanas | concorrente direto |
| BSP Twilio | Embedded Signup | sem fee fixo | dias | markup $0,005/msg |
| Wagner provisiona OBO | Wagner manualmente | zero | 4-8 semanas | LGPD: Wagner = responsável legal por todo conteúdo |

**Tech Provider direto** elimina BSP markup e mantém margem 98%+ no modelo R$ 50/número.

## Embedded Signup — UX do cliente final

5-7 cliques (~10-15min se cliente já tem Facebook business; ~30min do zero):

1. Cliente clica botão "Conectar WhatsApp" no oimpresso
2. Popup Meta abre (não é Wagner que renderiza — é Meta-hosted)
3. Login Facebook (conta do cliente)
4. Escolhe/cria **Meta Business Portfolio**
5. Escolhe/cria **WhatsApp Business Account (WABA)**
6. Informa phone number + Display Name → OTP via SMS/voz
7. Confirma OTP → Meta retorna credentials API via callback

**Wagner recebe via callback**: `phone_number_id`, `waba_id`, `access_token`, `business_id`.

## Como Wagner vira Tech Provider Meta

### Pré-requisitos
1. **CNPJ ativo** (oimpresso já tem)
2. **Meta Business Manager verificado** (business verification com docs CNPJ + comprovante endereço — provavelmente Wagner já tem, Baileys roda)
3. **2FA habilitado** no Facebook do admin
4. **Termo "Partner Solution" aceito** no console Meta

### Processo
1. Criar Meta App novo (developers.facebook.com)
2. Adicionar produto "WhatsApp Business Cloud API"
3. Adicionar permissions: `whatsapp_business_management` + `whatsapp_business_messaging`
4. Implementar Embedded Signup (Facebook Login for Business SDK + callback handler Laravel)
5. **Submeter App pra review** — Meta valida implementação
6. Aprovação 3-8 semanas (range oficial — realisticamente 4-6 semanas)

### Custos
- **Zero fee Tech Provider** (Meta não cobra programa)
- **Só paga as mensagens** (pricing seção abaixo)

### Deadline crítico
- **Enrollment ISV→Tech Provider tinha deadline 30 jun 2025** — passou. Wagner entra como Tech Provider novo desde já.

## Pricing Meta Cloud API Brasil 2026

Modelo **per-message** desde 1 jul 2025 (não mais conversation-based):

| Categoria | Preço Brasil 2026 (USD) | ~BRL | Quem inicia |
|---|---|---|---|
| **Utility** (NFe emitida, OTP, status pedido) | $0,0068 | ~R$ 0,034 | empresa → cliente |
| **Authentication** (OTP login) | $0,0068 | ~R$ 0,034 | empresa → cliente |
| **Marketing** (promo, divulgação) | $0,0625 | ~R$ 0,31 | empresa → cliente |
| **Service** (resposta dentro janela 24h cliente-iniciou) | **GRÁTIS** | grátis | cliente iniciou |

### Mudanças críticas 2025
- **Free tier 1000 conv/mês DESCONTINUADO** mid-2025
- **Service messages user-initiated GRÁTIS desde 1 nov 2024**
- **Utility/auth dentro janela 24h service window = grátis** (incentivo manter conversa "fresh")
- **Volume tiers**: descontos 5-25% acima de 100k msgs/mês

### Fiscal Brasil
Meta fatura via **Irlanda** → triggera **ISS + IRRF + CIDE + PIS + COFINS** = ~15-25% adicional. R$ 0,034/msg vira efetivo R$ 0,04-0,043.

## Arquitetura multi-phone

**Limites Meta:**
- 1 WABA: 2 phones (não-verificado) → **até 20 phones (verificado)** — escala com volume/qualidade
- 1 Business Manager: default **20 WABAs** (pode escalar via solicitação)
- 1 WABA pode ser shared com até **2 partners** via Meta Business Suite

**Pra 4000 clientes**:
- **NÃO**: Wagner concentrar 4000 numbers no Business Manager dele (limite 20 phones/WABA + juridicamente o número pertence ao cliente final)
- **SIM**: cada cliente final tem **Business Manager + WABA + phone próprio**, Embedded Signup automatiza isso, Wagner gerencia via API com share permission

## Compliance/responsabilidade legal

- **Embedded Signup**: cliente final aceita Meta TOS diretamente → **responsabilidade conteúdo = cliente final**. Wagner = Tech Provider, facilitador.
- **OBO (On-Behalf-Of)**: Wagner provisiona "em nome de" → responsabilidade compartilhada (Wagner pode ser banido junto se cliente spammer).
- **LGPD**: 4000 clientes finais enviando msgs → cada um precisa base legal própria. Wagner = OPERADOR; cada cliente = CONTROLADOR.

## Implementação técnica (escopo PR futuro)

### Frontend (React/Inertia)
```tsx
// Componente embedded signup
import { useEffect } from 'react';

export function EmbeddedSignupButton({ onComplete }) {
  useEffect(() => {
    // Load FB SDK
    (function(d, s, id) { /* ... */ })(document, 'script', 'facebook-jssdk');
  }, []);

  function launch() {
    FB.login(callback, {
      config_id: 'WAGNER_META_APP_CONFIG_ID', // do Meta App console
      response_type: 'code',
      override_default_response_type: true,
      extras: {
        setup: {
          phone: '+5547...',
          display_name: 'Cooperativa XYZ',
        }
      }
    });
  }
  
  return <button onClick={launch}>Conectar WhatsApp</button>;
}
```

### Backend (Laravel)
```php
// Callback POST com code → trocar por access_token long-lived
Route::post('/atendimento/embedded-signup/callback', [EmbeddedSignupController::class, 'callback']);

// EmbeddedSignupController::callback:
// 1. Exchange code → access_token (Meta Graph API /oauth/access_token)
// 2. Subscribe webhook (POST /<phone_number_id>/subscribed_apps)
// 3. Create Channel row no oimpresso (business_id = cliente final)
// 4. Save credentials encrypted
```

### Webhook receiver (já existe — `ChannelBaileysWebhookController` pattern, adaptar)

## Riscos operacionais

1. **Ban-em-cascata**: 1 cooperativa spammer → quality score do Wagner App cai → afeta TODA rede
2. **Misclassification utility/marketing**: "NFe emitida" = utility ($0,034). "Aproveite promo" no mesmo template = marketing ($0,31, 9× mais caro)
3. **Quality rating phone**: Meta rebaixa pra tier "low" (1k conv/dia) ou "high" (ilimitado) — monitorar via Meta Business Suite
4. **Embedded Signup popup bloqueado** por ad-blockers + corp firewalls
5. **Migration WhatsApp Business app → Cloud API**: cliente que já usa app precisa fluxo "coexistence" (360dialog doc específica)

## Mitigações implementáveis

- **Template approval centralizado** Wagner-side (revisor antes submit)
- **Monitoring quality score per-phone** via Meta Graph API
- **Phone "ejection" capability**: Wagner pode remover cliente abusivo do App
- **Webhook quality_update event** → alerta UI quando phone rebaixado

## Comparativo concorrentes (custo per-msg utility BR)

| Provider | Custo utility | Fee fixo/número/mês | Margem deal Agrosys (R$50 líquido) |
|---|---|---|---|
| **Tech Provider Meta direto** | R$ 0,034 | **R$ 0** | **98%** |
| 360dialog | R$ 0,034 + markup | €49-99 (~R$ 300-600) | **NEGATIVA** |
| Take Blip | sob contrato (premium) | sob contrato | concorrente, não usar |
| Twilio WhatsApp | R$ 0,034 + $0,005 | sem fee fixo | ~90% |
| Gupshup | R$ 0,034 + markup | varia | ~90% mas BR fraco |

## Referências oficiais

- [Embedded Signup overview — Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/overview/)
- [Onboarding business customers as a Tech Provider](https://developers.facebook.com/documentation/business-messaging/whatsapp/embedded-signup/onboarding-customers-as-a-tech-provider/)
- [Become a Tech Provider](https://developers.facebook.com/documentation/business-messaging/whatsapp/solution-providers/get-started-for-tech-providers)
- [Pricing Brazil 2026 — Flowcall](https://www.flowcall.co/blog/whatsapp-business-api-pricing-2026)
- [Phone Number Limits — Vonage Docs](https://api.support.vonage.com/hc/en-us/articles/13159743458460-WhatsApp-Platform-Phone-Number-Limits)
