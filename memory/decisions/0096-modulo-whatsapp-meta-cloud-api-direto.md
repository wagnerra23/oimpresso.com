---
slug: 0096-modulo-whatsapp-meta-cloud-api-direto
number: 96
title: "Módulo Whatsapp — Meta Cloud API direto + Driver abstraction"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-07
module: Whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, integracao, meta, multi-tenant, modulo-novo]
related_adrs: [0011, 0024, 0035, 0048, 0058, 0062, 0093, 0094]
parent_charter: null
parent_adr: 0094
supersedes: []
supersedes_partially: []
superseded_by: []
related: []
referenced_by: []
authors: [wagner, opus-4.7]
pii: false
review_triggers:
  - Meta mudar pricing Cloud API BR
  - Volume passar 5k conversas/mês em algum business (avaliar BSP)
  - Algum business pedir compliance ISO/SOC2 que BSP entrega out-of-box
---

# ADR 0096 — Módulo Whatsapp: Meta Cloud API direto + Driver abstraction

## Contexto

Demanda de WhatsApp transacional vem de 5 módulos do oimpresso, todos já entregues ou em SPEC:

- **Repair** — ADR tech/0001 já antecipou (status `ready`/`waiting_parts` precisa notificar cliente; SMS funciona mas custa caro e tem baixa taxa de leitura)
- **RecurringBilling** — US-RB-044 (enviar boleto+NFe ao cobrar) e dunning multicanal previsto em SPEC
- **Financeiro** — `app/Console/Commands/AutoSendPaymentReminder.php` já prepara `whatsapp_text` mas só gera link `wa.me/click-to-chat` manual
- **ConsultaOs** — cliente externo acompanha OS sem login; pings via Whatsapp seriam o canal natural
- **Jana / Copiloto** — bot conversacional com handoff humano (HITL) é o produto natural depois que `MeilisearchDriver` + `ContextoNegocio` estabilizaram (sprint memória, [ADR 0050](0050-stack-memoria-recall.md))

Estado atual no repo: campo legacy `notification_templates.whatsapp_text` (UltimatePOS v6), flag `auto_send_wa_notif` em `NotificationUtil::autoSendNotification()`. **Zero API real.** Só monta link `wa.me` pra clicar manual.

Mercado 2026 — provedores avaliados:

| Provedor | Tipo | Custo BR (típico) | Risco ban Meta | Multi-tenant | NF-e/boleto nativo |
|---|---|---|---|---|---|
| Meta Cloud API direto | Oficial | ~R$ [redacted Tier 0] (free 1k conv/mês) + R$ [redacted Tier 0] utility / R$ [redacted Tier 0] marketing por conversa após | nenhum | sim (1 phone_number_id/business) | manual |
| Twilio | Oficial (BSP) | $0,005/msg + Meta fee + markup ~30%; cobrança USD | nenhum | sim | manual |
| Take Blip (BR) | Oficial (BSP) | R$ [redacted Tier 0]+/mês fixo + por mensagem | nenhum | sim | parcial (parceiros) |
| Zenvia (BR) | Oficial (BSP) | R$ [redacted Tier 0]+/mês fixo + por mensagem | nenhum | sim | manual |
| 360dialog | Oficial (BSP) | EUR, sem markup pesado | nenhum | sim | manual |
| Evolution API / Z-API / Baileys | **Não-oficial** (WhatsApp Web reverse-engineered) | R$ [redacted Tier 0]-299/mês self-host | **🔴 ALTO** (viola TOS Meta; ban arbitrário) | sim | manual |

ROTA LIVRE (`business_id=4`, ~99% do volume) projetada em ~50-200 conversas/mês — fica integral dentro do free tier Meta Cloud API. Take Blip a R$ [redacted Tier 0]+/mês é overkill 30×.

## Decisão

**Criar `Modules/Whatsapp/` com Meta Cloud API direto como Driver default**, seguindo padrão Driver canônico do Copiloto ([ADR 0050](0050-stack-memoria-recall.md)) — `MeilisearchDriver` default + `NullDriver` dev/CI. Mesmo modelo aqui:

- `Services/Drivers/DriverInterface.php` — contrato (sendTemplate, sendFreeform, fetchStatus)
- `Services/Drivers/MetaCloudDriver.php` — implementação default, fala direto com `graph.facebook.com/v21.0/{phone_number_id}/messages`
- `Services/Drivers/NullDriver.php` — dev local + Pest (não estoura rede; `fake()` ergonômico)
- (futuro) `TwilioDriver` / `BlipDriver` — só se algum business enterprise pedir

**Provedores não-oficiais (Evolution API, Z-API, Baileys) são PROIBIDOS** em produção — viola Meta TOS, risco ban arbitrário (lei de Murphy: ban acontece exatamente no dia da régua de cobrança). Tier 0 do módulo.

**Webhook receiver no Hostinger** (HTTP-only, não precisa daemon — Hostinger pode hostear). **Job consumer no CT 100 Horizon** (ADR 0062 — Hostinger ≠ CT 100). **Real-time UI via Centrifugo** (ADR 0058) — mesmo channel pattern do Copiloto.

**Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093):
- 1 número Meta `phone_number_id` por business — cada um usa seu (não compartilhar);
- `access_token` cifrado em DB (`encrypted` cast Laravel);
- Webhook URL com slug `business_uuid` no path (não no body) — autentica antes de processar;
- HMAC-SHA256 de cada payload com `app_secret` Meta (rejeita forjados);
- `WhatsappMessage` tem `business_id` indexado + global scope + FK;
- PII (telefone cliente) **redacted** em logs via `PiiRedactor` (skill `commit-discipline` Tier A).

## Justificativa

**Por que Meta direto e não BSP brasileiro:**

1. **Custo 30× menor** no perfil real (50-200 conv/mês ROTA LIVRE). Take Blip R$ [redacted Tier 0]/mês fixo justifica em ~5k conv/mês ou compliance enterprise — não é nosso caso hoje.
2. **Zero markup intermediário** — cada R$ [redacted Tier 0] que economizamos é margem.
3. **Multi-tenant nativo** — cada business cadastra seu próprio número Meta no Business Manager dele (não há intermediário compartilhado, melhor pra LGPD).
4. **Driver pattern preserva opcionalidade** — se algum business enterprise pedir BSP, troca-se 1 classe sem refatorar callers.

**Por que NÃO Evolution API / Z-API:**

- Não-oficial. Funciona via WhatsApp Web reverse-engineered (Baileys).
- Meta bane sem aviso quando detecta. Já aconteceu com 3 empresas que conheço (1 perdeu número de cobrança — recuperação leva semanas).
- Princípio duro 8 da Constituição (ADR 0094): "Confiabilidade com fallback". Não-oficial não tem fallback — quando ban acontece, número some.
- Tier 0 nas Proibições (junto com não-instalar `octane` no Hostinger).

**Por que Driver e não Service direto:**

- Padrão já validado no Copiloto (`MeilisearchDriver` + `NullDriver` permite Pest sem rede).
- Padrão já validado no Stack IA (ADR 0035 — `LaravelAiSdkDriver`).
- Trocar provedor depois custa 1 PR, não refactor cross-module.

## Consequências

**Positivas:**

- Repair finalmente cumpre ADR tech/0001 sem custo de SMS (R$ [redacted Tier 0] vs R$ [redacted Tier 0]+ SMS).
- RecurringBilling US-RB-044 destrava (boleto+NFe via Whatsapp ao receber pagamento).
- Jana ganha canal de entrada novo (handoff HITL via PolicyEngine — `REQUIRE_HUMAN_REVIEW` vira ticket pra atendente).
- Free tier Meta cobre piloto inteiro (ROTA LIVRE ~3-6 meses sem custo).
- Padrão Driver permite trocar pra BSP em 1 PR se algum cliente pedir SLA enterprise.
- Pest com `NullDriver` não estoura rede em CI (suite continua rápida).

**Negativas / Trade-offs:**

- Aprovação número Meta toma 1-3 dias (Business Manager + verificação) — onboarding business novo tem gargalo manual humano.
- HSM templates (mensagens fora da janela 24h) precisam aprovação Meta (1-3 dias por template) — UI tem que mostrar status `pending/approved/rejected` claro.
- Pricing Meta pode mudar (eles já mudaram 2× em 2024-2025) — `review_triggers` registra.
- Webhook em produção precisa estar 99.9% UP — falha silenciosa = cliente respondeu e ninguém viu. Mitigação: alarme se webhook não recebe nada em 24h pra businesses ativos.
- Sem suporte humano BR (Meta só docs em inglês + comunidade) — Wagner/equipe assume troubleshooting.

**Riscos mitigados:**

- **Ban arbitrário**: zero (provedor oficial Meta).
- **PII vazamento**: telefone cliente redacted em logs; `access_token` cifrado em DB; webhook valida HMAC antes de processar.
- **Cross-tenant leak**: webhook URL tem `business_uuid` no path; global scope `business_id` em todas Models; teste `MultiTenantIsolationTest` obrigatório.
- **Vendor lock-in**: Driver pattern.

## Alternativas consideradas

- **BSP brasileiro (Take Blip / Zenvia)** — descartado por custo 30× pra perfil atual; reabrir se algum business passar 5k conv/mês ou pedir compliance enterprise.
- **Twilio** — descartado por cobrança USD volátil + markup 30%.
- **Evolution API / Z-API** — descartado por risco ban (TOS Meta) — Tier 0 PROIBIDO.
- **Esperar laravel/whatsapp oficial** — não existe; pacotes Composer pra Meta Cloud API são todos community + abandonados. Implementação direta com `Http::post()` (Laravel HTTP client) é trivial e nos deixa donos do código.

## Referências

- ADR [0011](0011-alinhamento-padrao-jana.md) — alinhamento padrão Jana (módulo referência)
- ADR [0024](0024-receita-criar-modulo.md) — receita criar módulo nWidart
- ADR [0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — stack IA canônica (padrão Driver)
- ADR [0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) — Vizra rejeitada (precedente "não-oficial = não")
- ADR [0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo (real-time UI)
- ADR [0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100 (Job consumer no CT 100)
- ADR [0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- ADR [0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (mãe)
- ADR Repair tech/0001 — auto-SMS em mudança de status
- Meta Cloud API docs — `developers.facebook.com/docs/whatsapp/cloud-api`
- SPEC: [memory/requisitos/Whatsapp/SPEC.md](../requisitos/Whatsapp/SPEC.md)
- Capterra: [memory/requisitos/Whatsapp/CAPTERRA-FICHA.md](../requisitos/Whatsapp/CAPTERRA-FICHA.md)
