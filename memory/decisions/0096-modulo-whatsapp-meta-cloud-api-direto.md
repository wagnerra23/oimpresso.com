---
slug: 0096-modulo-whatsapp-meta-cloud-api-direto
number: 96
title: "Módulo Whatsapp — Meta Cloud API + Z-API/Baileys (2 drivers oficiais)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-07
accepted_at: 2026-05-07
accepted_by: wagner
module: Whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, integracao, meta, zapi, baileys, multi-tenant, modulo-novo]
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
  - Ban Meta em algum business usando ZapiDriver/EvolutionDriver (forçar fallback)
  - Mudança Meta TOS afetar tooling Whatsapp Web (Baileys deprecado)
---

# ADR 0096 — Módulo Whatsapp: 2 drivers (Meta Cloud API + Z-API/Baileys)

> **Emenda 2026-05-07 (mesmo dia da proposta):** Wagner aceitou ADR original
> com emenda explícita: **Z-API / Baileys também são drivers válidos do módulo**
> (não Tier 0 PROIBIDO como na versão proposta). Razão: cobrir mercado BR PME
> que já tem número no Z-API e quer trazer pro oimpresso sem trocar provedor +
> oferecer alternativa orçamento (Z-API ~R$ 99/mês vs Meta token+verificação).
> Risco de ban Meta documentado no bloco "Risco aceito conscientemente" abaixo.

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
| Meta Cloud API direto | Oficial | ~R$ 0 (free 1k conv/mês) + R$ 0,07 utility / R$ 0,30 marketing por conversa após | nenhum | sim (1 phone_number_id/business) | manual |
| Twilio | Oficial (BSP) | $0,005/msg + Meta fee + markup ~30%; cobrança USD | nenhum | sim | manual |
| Take Blip (BR) | Oficial (BSP) | R$ 1.500+/mês fixo + por mensagem | nenhum | sim | parcial (parceiros) |
| Zenvia (BR) | Oficial (BSP) | R$ 500+/mês fixo + por mensagem | nenhum | sim | manual |
| 360dialog | Oficial (BSP) | EUR, sem markup pesado | nenhum | sim | manual |
| Evolution API / Z-API / Baileys | **Não-oficial** (WhatsApp Web reverse-engineered) | R$ 99-299/mês self-host | **🔴 ALTO** (viola TOS Meta; ban arbitrário) | sim | manual |

ROTA LIVRE (`business_id=4`, ~99% do volume) projetada em ~50-200 conversas/mês — fica integral dentro do free tier Meta Cloud API. Take Blip a R$ 1.500+/mês é overkill 30×.

## Decisão

**Criar `Modules/Whatsapp/` com 2 drivers oficiais paralelos** + Driver pattern (ADR 0050 Copiloto), `business_id` escolhe qual usar via coluna `whatsapp_business_configs.driver`:

### Drivers suportados

- `Services/Drivers/DriverInterface.php` — contrato comum (sendTemplate, sendFreeform, sendMedia, fetchStatus, ping)
- `Services/Drivers/MetaCloudDriver.php` — **default**, oficial Meta. Fala com `graph.facebook.com/v21.0/{phone_number_id}/messages`. HSM templates obrigatórios fora janela 24h. Custo Meta direto (free 1k conv/mês).
- `Services/Drivers/ZapiDriver.php` — **alternativa orçamento PME**, SaaS BR Z-API (`api.z-api.io`). Mensagens freeform sem janela 24h restritiva. R$ 99-299/mês fixo. **Risco ban Meta** (não-oficial, baseado em Whatsapp Web).
- `Services/Drivers/EvolutionDriver.php` — **self-host CT 100** (Evolution API Docker). Zero custo SaaS, controle total. **Risco ban Meta** (mesmo motivo Z-API). Sprint 2 (futuro).
- `Services/Drivers/NullDriver.php` — dev/CI Pest, não estoura rede.
- (backlog) `TwilioDriver` / `BlipDriver` — só se algum business enterprise pedir.

### Onboarding por driver

| Driver | Onboarding | Custo perfil 150 conv/mês | Quem cuida |
|---|---|---|---|
| `meta_cloud` | Meta Business Manager + verificação número (1-3 dias) + HSM templates pendentes (1-3 dias cada) | R$ 0 (free tier) | tenant + Wagner ajuda |
| `zapi` | Cadastro Z-API + scan QR Code Whatsapp (~5 min) | R$ 99/mês fixo | tenant sozinho |
| `evolution` | Subir container Docker CT 100 + scan QR | R$ 0 (CT 100 dele) | Wagner |

### Infraestrutura comum

**Webhook receiver no Hostinger** (HTTP-only, não precisa daemon — ADR 0062). Cada driver tem rota webhook própria:
- `POST /api/whatsapp/webhook/meta/{business_uuid}` — Meta Cloud (HMAC SHA-256 com `app_secret`)
- `POST /api/whatsapp/webhook/zapi/{business_uuid}` — Z-API (token compartilhado `client_token` Z-API)
- `POST /api/whatsapp/webhook/evolution/{business_uuid}` — Evolution (apikey Evolution)

**Job consumer no CT 100 Horizon** (ADR 0062 — Hostinger ≠ CT 100). **Real-time UI via Centrifugo** (ADR 0058) — mesmo channel `whatsapp:business:{id}` independente de driver.

### Fallback automático (Tier 1)

Se driver não-oficial (Z-API/Evolution) falhar 5× consecutivas com erro auth/ban, sistema:
1. Marca `whatsapp_business_configs.driver_health = 'degraded'`
2. Notifica admin business via UI (badge vermelho + email)
3. Se `fallback_driver` configurado (ex: meta_cloud), troca automaticamente
4. Retém histórico de mensagens (não perde inbox)

Implementação: `WhatsappDriverHealthCheck` job + Sentinel pattern (Sprint 2).

**Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093):
- 1 número Meta `phone_number_id` por business — cada um usa seu (não compartilhar);
- `access_token` cifrado em DB (`encrypted` cast Laravel);
- Webhook URL com slug `business_uuid` no path (não no body) — autentica antes de processar;
- HMAC-SHA256 de cada payload com `app_secret` Meta (rejeita forjados);
- `WhatsappMessage` tem `business_id` indexado + global scope + FK;
- PII (telefone cliente) **redacted** em logs via `PiiRedactor` (skill `commit-discipline` Tier A).

## Justificativa

**Por que Meta Cloud direto como default:**

1. **Custo 30× menor** que BSP no perfil real (50-200 conv/mês ROTA LIVRE). Take Blip R$ 1.500/mês fixo justifica em ~5k conv/mês ou compliance enterprise — não é nosso caso hoje.
2. **Zero markup intermediário** — cada R$ 0,07 que economizamos é margem.
3. **Multi-tenant nativo** — cada business cadastra seu próprio número Meta (Business Manager dele). Sem intermediário compartilhado, melhor pra LGPD.
4. **Sem risco ban** — provedor oficial Meta. Princípio duro #8 da Constituição (ADR 0094) "Confiabilidade com fallback" plenamente atendido.

**Por que Z-API / Baileys (Evolution) também (emenda Wagner 2026-05-07):**

1. **Mercado BR PME real usa massivamente** — empresas que chegam ao oimpresso muitas vezes já têm número no Z-API ou Evolution há anos. Forçar Meta Cloud = forçar trocar de provedor + perder histórico de conversas.
2. **Onboarding 100× mais rápido** — Z-API: scan QR code, 5 minutos. Meta Cloud: Business Manager + verificação número (1-3 dias) + HSM aprovação (1-3 dias por template).
3. **Sem janela 24h restritiva** — Z-API/Evolution mandam freeform a qualquer hora, sem template HSM. Pra dunning/cobrança simples isso destrava muito caso de uso.
4. **Custo previsível por business** — R$ 99/mês Z-API ou R$ 0 Evolution self-host CT 100 (Wagner já tem CT 100).
5. **Driver pattern preserva opcionalidade** — se ban Meta acontecer no Z-API/Evolution, fallback automático pro Meta Cloud (com aviso ao business pra completar onboarding).

**Risco aceito conscientemente** — ver bloco abaixo.

**Por que Driver pattern e não Service direto:**

- Padrão já validado no Copiloto (`MeilisearchDriver` + `NullDriver` permite Pest sem rede).
- Padrão já validado no Stack IA (ADR 0035 — `LaravelAiSdkDriver`).
- Trocar provedor depois custa 1 PR, não refactor cross-module.
- Permite **mix de drivers entre businesses** — ROTA LIVRE pode usar Meta Cloud, outro business pode usar Z-API, sem código bifurcado.

## Risco aceito conscientemente (drivers não-oficiais)

Z-API e Evolution API são baseados em **Whatsapp Web reverse-engineered (Baileys)**. Trade-offs:

### Riscos

1. **Ban Meta arbitrário** — Meta tem detection de automação não-oficial. Quando detecta: número desconectado sem aviso. Recuperação leva 1-30 dias (depende do critério Meta).
2. **Compliance LGPD** — sem CONTRATO formal Meta, business não consegue alegar conformidade total se cliente final acionar (mitigação: contrato Z-API/Evolution prevê isso parcialmente).
3. **Sessão Whatsapp Web cai** — qrcode re-scan necessário se sessão expira (ex: cliente troca celular). Z-API mostra notificação; Evolution self-host depende de monitor próprio.
4. **Suporte limitado** — Z-API tem chat (BR, em português). Evolution só comunidade open-source.
5. **Mudança Meta TOS quebra biblioteca** — Baileys já teve 3 quebras grandes em 2024-2025. Tempo de patch da comunidade: dias a semanas.

### Mitigações implementadas (Sprint 1-2)

1. **`WhatsappDriverHealthCheck` job** (Sprint 2) — tenta enviar mensagem-piloto a cada 6h; se falhar 5× consecutivas, marca `driver_health=degraded` e alerta admin business (badge UI + email).
2. **Fallback automático** — se `fallback_driver` configurado (recomendação: cadastrar Meta Cloud antes; ele fica dormente até precisar), sistema troca automaticamente. Histórico mensagens preservado (DB independente).
3. **UI mostra status driver** — badge verde/amarelo/vermelho na Inbox + Settings com last_health_check.
4. **Documento "Como migrar Z-API → Meta Cloud em emergência"** — runbook humano em `memory/requisitos/Whatsapp/runbooks/migrar-emergencia.md` (Sprint 2).
5. **Pricing Pro R$ 99/mês inclui suporte Wagner-mediado** se ban acontecer (ele ajuda re-onboarding).
6. **OTel metric `whatsapp.driver.bans` por business + driver** — alarme cross-tenant se 3+ businesses banidos no mesmo dia (sinal mudança Meta detection — força planejar migração geral pra Meta Cloud).
7. **CTA na UI Settings de drivers não-oficiais**: "⚠️ Provedor não-oficial. Recomendamos cadastrar Meta Cloud como fallback agora pra evitar interrupção em caso de ban."

### Quando esse risco vira bloqueador (review_trigger)

- Se ≥3 businesses tiverem ban no mesmo trimestre, abrir ADR 0XXX pra reavaliar — possivelmente forçar migração Meta Cloud em todos.
- Se Meta soltar política nova explicitando ban automático em Whatsapp Web automation, deprecar `ZapiDriver`/`EvolutionDriver` em 60 dias.

## Consequências

**Positivas:**

- Repair finalmente cumpre ADR tech/0001 sem custo de SMS (R$ 0,07 Meta / freeform Z-API vs R$ 0,30+ SMS).
- RecurringBilling US-RB-044 destrava (boleto+NFe via Whatsapp ao receber pagamento).
- Jana ganha canal de entrada novo (handoff HITL via PolicyEngine — `REQUIRE_HUMAN_REVIEW` vira ticket pra atendente).
- Free tier Meta cobre piloto inteiro (ROTA LIVRE ~3-6 meses sem custo).
- **Z-API permite onboarding em 5 minutos** (scan QR Code) vs 1-3 dias Meta — fundamental pra demo comercial.
- **Mercado BR PME pode trazer número Z-API existente** sem trocar provedor — entrada mais larga.
- Padrão Driver permite trocar pra BSP enterprise em 1 PR se algum cliente pedir SLA.
- Pest com `NullDriver` não estoura rede em CI (suite continua rápida).
- Fallback automático Z-API→Meta protege contra ban (mitigação documentada).

**Negativas / Trade-offs:**

- **2 drivers paralelos = ~30% mais código** (interface + 2 implementações + 2 webhook handlers + 2 onboarding flows). Mitigado por interface comum + factory.
- Aprovação número Meta toma 1-3 dias (gargalo Meta Cloud) — Z-API é alternativa rápida.
- HSM templates (Meta Cloud) precisam aprovação Meta (1-3 dias) — UI mostra status `pending/approved/rejected`. Z-API não precisa HSM (manda freeform).
- Pricing Meta pode mudar (eles já mudaram 2× em 2024-2025) — `review_triggers` registra.
- Webhook em produção precisa 99.9% UP — falha silenciosa = cliente respondeu e ninguém viu. Mitigação: alarme se webhook não recebe nada em 24h.
- **Risco ban Meta nos drivers Z-API/Evolution** (ver bloco "Risco aceito conscientemente").
- Documentação dual aumenta — guia onboarding Meta + guia Z-API + guia Evolution.

**Riscos mitigados:**

- **Ban Meta no driver oficial**: zero.
- **Ban Meta nos drivers Z-API/Evolution**: monitorado via `WhatsappDriverHealthCheck` + fallback configurável (Sprint 2).
- **PII vazamento**: telefone cliente redacted em logs; tokens cifrados em DB; webhook valida assinatura antes de processar.
- **Cross-tenant leak**: webhook URL tem `business_uuid` no path; global scope `business_id` em todas Models; teste `MultiTenantIsolationTest` obrigatório.
- **Vendor lock-in**: Driver pattern + 3 implementações desde dia 1 (Meta + Z-API + Null em Sprint 1; Evolution Sprint 2).

## Alternativas consideradas

- **BSP brasileiro (Take Blip / Zenvia)** — descartado por custo 30× pra perfil atual; reabrir se algum business passar 5k conv/mês ou pedir compliance enterprise.
- **Twilio** — descartado por cobrança USD volátil + markup 30%.
- **Apenas Meta Cloud (proibir não-oficial)** — descartado por emenda Wagner 2026-05-07: mercado BR PME real usa Z-API/Evolution e exige onboarding 5min, não 1-3 dias. Restringir = perder demanda real.
- **Apenas Z-API (sem Meta Cloud)** — descartado: businesses enterprise futuros vão exigir oficial; ban risk é real e fallback precisa existir desde dia 1.
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
