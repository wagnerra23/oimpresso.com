---
slug: 0096-modulo-whatsapp-meta-cloud-api-direto
number: 96
title: "Módulo Whatsapp — Z-API default + Meta Cloud fallback + BaileysDriver custom (Sprint 3); Evolution PROIBIDO permanente"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
accepted_at: "2026-05-07"
decided_by: [W]
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, integracao, meta, zapi, baileys, multi-tenant, modulo-novo, evolution-proibido, custom-driver-sprint-3]
related_adrs: ["0011-alinhamento-padrao-jana", "0024-instalacao-1-clique-modulos", "0035-stack-ai-canonica-wagner-2026-04-26", "0048-framework-agentes-laravel-ai-vizra-rejeitada", "0058-reverb-substituido-por-centrifugo-frankenphp", "0062-separacao-runtime-hostinger-ct100", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
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

# ADR 0096 — Módulo Whatsapp: Z-API/Baileys default + Meta Cloud fallback (Evolution PROIBIDO)

> **Histórico de emendas (mesmo dia da proposta — 2026-05-07):**
>
> - **Emenda 1 (manhã):** Wagner aceitou ADR original com emenda: **Z-API e
>   Evolution API também são drivers válidos** (originalmente eram Tier 0
>   PROIBIDO). Razão: mercado BR PME usa massivamente; onboarding 5 min.
>
> - **Emenda 2 (tarde):** Wagner reavaliou risco e endureceu:
>   - **Evolution API → PROIBIDO Tier 0** (volta à proposta original)
>   - **Z-API → ATIVO com risco muito alto** (mais salvaguardas)
>
> - **Emenda 3 (final do dia):** Wagner inverte a hierarquia de drivers e
>   autoriza Lote 2:
>   - **Z-API → DRIVER PADRÃO** (`default_driver=zapi`). Onboarding 5 min.
>   - **Meta Cloud → fallback obrigatório.**
>   - **Evolution API → PROIBIDO Tier 0.**
>
> - **Emenda 4 (encerramento — versão atual):** Wagner detalha experiência
>   real e autoriza driver custom Sprint 3:
>   - **`BaileysDriver` (custom oimpresso) → AUTORIZADO Sprint 3** como
>     "estrutura customizada de atendimento". Daemon Node próprio em CT 100
>     com schema/observabilidade próprios.
>   - **Evolution API → continua PROIBIDO permanente.**
>
>   **Razões concretas Wagner pra essa assimetria (Baileys puro autorizado /
>   Evolution proibido):**
>
>   1. **Evolution está banindo seus números** — experiência real, não
>      especulação. Razão suficiente pra abandonar.
>   2. **Schema de banco do Evolution não atendeu** — Wagner tentou usar
>      e o modelo de dados não batia com a estrutura customizada de
>      atendimento que ele quer construir.
>   3. **Observabilidade** — Wagner sentiu falta de controle quando
>      bans/desconexões aconteceram no Evolution. BaileysDriver custom =
>      nosso schema, nossos logs OTel, nossas métricas, nosso health
>      check. Dor de observabilidade é o que justifica o código extra
>      do daemon Node.
>   4. **Ciência do custo** — Wagner explicitamente reconhece "vai ter
>      código extra por essa decisão" (daemon Node CT 100, container
>      Docker, wrapper HTTP, persistência sessão Whatsapp Web).
>
>   **Recomendação Claude (aceita pelo Wagner):** começar simples Sprint 1
>   com Z-API + Meta Cloud, validar em produção, **deixar Baileys puro
>   anotado pra Sprint 3** quando estrutura customizada de atendimento
>   for construída. Não construir daemon Node sem antes ter operação
>   básica funcionando.

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

**Criar `Modules/Whatsapp/` com `ZapiDriver` como default + `MetaCloudDriver` como fallback obrigatório** + Driver pattern (ADR 0050 Copiloto). `business_id` pode mudar via `whatsapp_business_configs.driver` quando enterprise compliance pedir Meta Cloud puro. **Evolution API permanece PROIBIDO Tier 0**.

### Drivers suportados

- `Services/Drivers/DriverInterface.php` — contrato comum (sendTemplate, sendFreeform, sendMedia, fetchStatus, ping)

**Sprint 1 (entrega rápida — validar oficiais):**
- `Services/Drivers/ZapiDriver.php` — **DEFAULT**, SaaS BR Z-API (`api.z-api.io`), Whatsapp Web via Baileys. Onboarding 5 min (scan QR). Freeform sem janela 24h. R$ 99-299/mês fixo. **Risco ban MUITO ALTO**, mitigado por fallback Meta Cloud obrigatório.
- `Services/Drivers/MetaCloudDriver.php` — **fallback oficial obrigatório** (e default pra businesses enterprise compliance). Fala com `graph.facebook.com/v21.0/{phone_number_id}/messages`. HSM obrigatórios fora janela 24h. Free 1k conv/mês Meta. **Risco ban Meta: nenhum.**
- `Services/Drivers/NullDriver.php` — dev/CI Pest, não estoura rede.

**Sprint 3 (estrutura customizada de atendimento — autorizado emenda 4):**
- `Services/Drivers/BaileysDriver.php` — **driver custom oimpresso**, fala com nosso próprio daemon Node CT 100 que roda Baileys diretamente. Schema, logs OTel, métricas e health check sob nosso controle total. **Dor de observabilidade do Evolution justifica o código extra.**
  - Componente Node: novo container Docker compose-managed `whatsapp-baileys` em CT 100 (ADR 0058) — wrapper HTTP REST minimal sobre Baileys lib (`@whiskeysockets/baileys`), persistência de auth state em volume mapeado.
  - Componente PHP: `BaileysDriver` fala com daemon via `Http::baseUrl(config('whatsapp.baileys.daemon_url'))` — daemon nunca exposto fora do CT 100.
  - Roadmap detalhado em `ARCHITECTURE.md §16`.

**Backlog enterprise:**
- `TwilioDriver` / `BlipDriver` — só se enterprise pedir.

### ❌ Drivers PROIBIDOS permanentes

- **`EvolutionDriver` (Evolution API)** — **PROIBIDO permanente** (não Tier 0 abstrato; razão concreta documentada por Wagner em 2026-05-07):
  - Está **banindo números reais** dos businesses do Wagner em produção
  - **Schema de banco** Evolution não atende a estrutura customizada de atendimento que vamos construir
  - **Falta de observabilidade** — Wagner sentiu na pele a opacidade quando bans aconteceram
  - Reabrir só se Evolution mudar substancialmente esses 3 pontos (improvável; não esperar)
- **`WhatsappWebJsDriver`** — PROIBIDO. Sobreposição funcional com BaileysDriver custom + sem suporte comercial.
- **Qualquer wrapper Whatsapp Web de terceiros rodando em servidor oimpresso** — PROIBIDO. Já que vamos construir BaileysDriver custom, não há razão pra rodar wrapper de terceiro nosso.

### Onboarding por driver (ordem prática real do oimpresso)

| Ordem | Driver | Quando usar | Onboarding | Custo perfil 150 conv/mês | Risco ban |
|---|---|---|---|---|---|
| **1º (default Sprint 1)** | `zapi` | Maioria dos businesses PME | Cadastro Z-API + scan QR Code (~5 min) | R$ 99/mês | **muito alto** (mitigado por fallback) |
| **2º (obrigatório Sprint 1)** | `meta_cloud` | Cadastrar como fallback OU usar como default em enterprise | Meta Business Manager + verificação (1-3 dias) + HSM (1-3 dias cada) | R$ 0 (free tier) | **nenhum** |
| **3º (Sprint 3 — custom)** | `baileys` | Quando estrutura customizada de atendimento estiver pronta + business quiser controle total / observabilidade rica | Subir container `whatsapp-baileys` no CT 100 + cadastrar instance via Settings + scan QR Code | R$ 0 (CT 100 do Wagner) | **muito alto** (mitigado por fallback Meta Cloud + nosso health check) |

### Infraestrutura comum

**Webhook receiver no Hostinger** (HTTP-only, não precisa daemon — ADR 0062). 2 rotas webhook:
- `POST /api/whatsapp/webhook/meta/{business_uuid}` — Meta Cloud (HMAC SHA-256 com `app_secret`)
- `POST /api/whatsapp/webhook/zapi/{business_uuid}` — Z-API (header `Client-Token` timing-safe compare)

**Job consumer no CT 100 Horizon** (ADR 0062 — Hostinger ≠ CT 100). **Real-time UI via Centrifugo** (ADR 0058) — mesmo channel `whatsapp:business:{id}` independente de driver.

### Fallback automático Z-API → Meta Cloud (OBRIGATÓRIO)

**Z-API só pode ser ativado se Meta Cloud estiver cadastrado como `fallback_driver`** — gating no FormRequest. Sem fallback configurado = não deixa salvar `driver=zapi`.

Se Z-API falhar 5× consecutivas com erro auth/ban, sistema:
1. Marca `whatsapp_business_configs.driver_health = 'degraded'`
2. Notifica admin business via UI (badge vermelho + email)
3. Troca automaticamente pra `fallback_driver=meta_cloud`
4. Retém histórico de mensagens (não perde inbox)
5. Notifica Wagner ops (cross-tenant alarme se ≥3 businesses caírem em 24h)

Implementação: `WhatsappDriverHealthCheck` job + Sentinel pattern (Sprint 2).

**Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093):
- 1 número Meta `phone_number_id` por business — cada um usa seu (não compartilhar);
- `access_token` cifrado em DB (`encrypted` cast Laravel);
- Webhook URL com slug `business_uuid` no path (não no body) — autentica antes de processar;
- HMAC-SHA256 de cada payload com `app_secret` Meta (rejeita forjados);
- `WhatsappMessage` tem `business_id` indexado + global scope + FK;
- PII (telefone cliente) **redacted** em logs via `PiiRedactor` (skill `commit-discipline` Tier A).

## Justificativa

**Por que Z-API/Baileys como default (emenda 3, 2026-05-07):**

1. **Onboarding 5 min vs 1-3 dias Meta** — fundamental pro fluxo comercial PME. Cliente decide na hora; padrão tem que estar pronto na hora.
2. **Mercado BR PME real já está nesse mundo** — empresas que chegam ao oimpresso muitas vezes têm número no Z-API há anos. Forçar Meta como default = atritar onboarding.
3. **Sem janela 24h restritiva** — Z-API manda freeform a qualquer hora. Pra dunning/cobrança/lembrete sem ter que pré-aprovar HSM destrava 80% do caso de uso.
4. **Risco terceirizado** — Z-API SaaS responde pelo ban. Recriar instance é problema deles, não nosso.
5. **R$ 99/mês cabível** — entra no plano Pro do oimpresso (R$ 99/mês) sem comer margem.

**Por que Meta Cloud como fallback obrigatório (não default):**

1. **Sem risco ban** — provedor oficial Meta. Princípio duro #8 Constituição (ADR 0094) "Confiabilidade com fallback" atendido via redundância.
2. **Free tier 1k conv/mês BR** — fallback ativo não custa nada se Z-API estiver saudável.
3. **Multi-tenant nativo** — cada business cadastra seu próprio número Meta (Business Manager dele). Melhor pra LGPD em caso de uso prolongado do fallback.
4. **Driver pattern preserva opcionalidade** — businesses enterprise compliance podem flipar `default_driver=meta_cloud` em qualquer momento via UI Settings.

**Por que Z-API entra (com salvaguardas reforçadas):**

1. **Mercado BR PME real usa massivamente** — empresas que chegam ao oimpresso muitas vezes já têm número no Z-API há anos. Forçar Meta Cloud = forçar trocar de provedor + perder histórico de conversas.
2. **Onboarding 100× mais rápido** — Z-API: scan QR code, 5 minutos. Meta Cloud: Business Manager + verificação número (1-3 dias) + HSM aprovação (1-3 dias por template).
3. **Sem janela 24h restritiva** — Z-API manda freeform a qualquer hora, sem template HSM. Pra dunning/cobrança simples isso destrava caso de uso.
4. **Custo previsível** — R$ 99/mês Z-API enquanto Meta Cloud é aprovado.
5. **Risco terceirizado** — Z-API SaaS responde pelo ban (instance pode ser recriada). oimpresso continua operando com fallback Meta Cloud.
6. **Driver pattern preserva opcionalidade** — se ban Meta acontecer no Z-API, fallback automático pro Meta Cloud (gating obrigatório).

**Por que Evolution API NÃO entra (proibido):**

1. **Self-host CT 100 = oimpresso direto na linha de fogo.** Container Docker é nosso, sessão Whatsapp Web é nossa, ban é nosso problema.
2. **Sem terceiro pra responsabilizar.** Z-API tem chat, contrato, suporte BR. Evolution só comunidade open-source — quando lib quebra, a gente fica em silêncio até patch chegar.
3. **Risco LGPD eleva.** Evolution self-host = dado do cliente final do tenant transita pelo nosso CT 100. Cadeia de responsabilidade direta.
4. **Mudança Meta TOS quebra Baileys com mais frequência** — comunidade demora dias a semanas pra patch. Z-API tem time pago pra isso.
5. **Ganho marginal não compensa stakes.** R$ 99/mês Z-API economizados não justificam o aumento de superfície operacional pro oimpresso.

**Risco aceito conscientemente (Z-API)** — ver bloco abaixo.

**Por que Driver pattern e não Service direto:**

- Padrão já validado no Copiloto (`MeilisearchDriver` + `NullDriver` permite Pest sem rede).
- Padrão já validado no Stack IA (ADR 0035 — `LaravelAiSdkDriver`).
- Trocar provedor depois custa 1 PR, não refactor cross-module.
- Permite **mix de drivers entre businesses** — ROTA LIVRE pode usar Meta Cloud, outro business pode usar Z-API, sem código bifurcado.

## Risco aceito conscientemente (drivers não-oficiais)

Z-API e Evolution API são baseados em **Whatsapp Web reverse-engineered (Baileys)**. Trade-offs:

### Riscos (Z-API como driver default)

1. **Ban Meta arbitrário** — Meta tem detection de automação não-oficial. Quando detecta: número desconectado sem aviso. Recuperação leva 1-30 dias.
2. **Compliance LGPD parcial** — sem contrato formal com Meta, business não consegue alegar conformidade total. Z-API tem contrato BR (cobre parte). Para enterprise: flipar `driver=meta_cloud` na UI Settings.
3. **Sessão Whatsapp Web cai** — qrcode re-scan necessário se sessão expira. Z-API notifica via webhook `on-disconnected` + UI alerta; fallback Meta Cloud entra em ação.
4. **Suporte limitado** — Z-API tem chat em português, BR. Quando lib Baileys quebra, depende do time deles patchear (vs comunidade pura no Evolution — razão extra do Evolution PROIBIDO).
5. **Mudança Meta TOS quebra biblioteca** — Baileys já teve 3 quebras em 2024-2025. Tempo de patch Z-API: 1-3 dias (chat suporte).

### Mitigações implementadas (Sprint 1-2 — gating duro)

1. **Fallback Meta Cloud OBRIGATÓRIO** — FormRequest de Settings rejeita salvar `driver=zapi` se `meta_*` campos não estiverem todos preenchidos. Não é opcional. Sem fallback Meta cadastrado = não ativa Z-API. Gating físico, não convencional.
2. **`WhatsappDriverHealthCheck` job** (Sprint 2) — ping a cada 6h; 5 falhas = `degraded`, 10 = `disconnected`, auth permanent = `banned`.
3. **Fallback automático** — quando `driver_health` ≥ degraded, sistema troca pra Meta Cloud sem intervenção. Histórico mensagens preservado (DB independente).
4. **UI mostra status driver** — badge verde/amarelo/vermelho na Inbox + Settings com `last_health_check_at` e contador de falhas consecutivas.
5. **Runbook "Como migrar Z-API → Meta Cloud em emergência"** — `memory/requisitos/Whatsapp/runbooks/migrar-emergencia.md` (Sprint 2).
6. **Pricing Pro R$ 99/mês inclui suporte Wagner-mediado** se ban acontecer (ele ajuda re-onboarding).
7. **OTel `whatsapp.driver.bans` por business+driver** — alarme cross-tenant se 3+ businesses banidos no mesmo dia (sinal mudança Meta detection — força planejar migração geral).
8. **CTA permanente na UI Settings com `driver=zapi`**: badge vermelho "⚠️ Provedor não-oficial — risco ban Meta. Fallback Meta Cloud ativo."
9. **Termo LGPD obrigatório** quando `driver=zapi`, registrado em `whatsapp_business_configs.lgpd_acknowledged_at`.

### Quando esse risco vira bloqueador (review_trigger)

- Se ≥3 businesses tiverem ban no mesmo trimestre, abrir ADR 0XXX pra reavaliar — possivelmente forçar migração Meta Cloud em todos.
- Se Meta soltar política nova explicitando ban automático em Whatsapp Web automation, deprecar `ZapiDriver`/`EvolutionDriver` em 60 dias.

## Consequências

**Positivas:**

- **Onboarding 5 min** (Z-API default) destrava demo comercial PME — não precisa esperar Meta Business Manager aprovar.
- **Mercado BR PME entra direto** — número Z-API existente continua funcionando sem migrar provedor.
- Repair finalmente cumpre ADR tech/0001 (status `ready` dispara Whatsapp).
- RecurringBilling US-RB-044 destrava (boleto+NFe via Whatsapp ao receber pagamento).
- Jana ganha canal de entrada novo (handoff HITL via PolicyEngine — `REQUIRE_HUMAN_REVIEW` vira ticket pra atendente).
- **Fallback Meta Cloud obrigatório protege operação** — se ban Z-API, sistema troca sem intervenção. Free tier Meta cobre fallback sem custo extra.
- Padrão Driver permite trocar pra BSP enterprise em 1 PR se algum cliente pedir SLA.
- Pest com `NullDriver` não estoura rede em CI (suite continua rápida).
- Businesses enterprise compliance podem flipar `driver=meta_cloud` na UI sem refactor.

**Negativas / Trade-offs:**

- **2 drivers paralelos = ~30% mais código** (interface + 2 implementações + 2 webhook handlers + 2 onboarding flows). Mitigado por interface comum + factory.
- **Cadastro Meta Cloud é gating duro** — business não consegue ativar Z-API sem Meta cadastrado. Onboarding "completo" leva ~1-3 dias mesmo com Z-API ativo no dia 1 (Meta Business Manager precisa rodar em paralelo). Mitigação: UI mostra wizard "Whatsapp em 2 passos: 1) ativa Z-API hoje, 2) Meta Cloud aprova em 1-3 dias e fica de prontidão".
- HSM templates (Meta Cloud fallback) precisam aprovação Meta (1-3 dias) — UI mostra status `pending/approved/rejected`. Z-API não precisa HSM.
- Pricing Meta pode mudar (eles já mudaram 2× em 2024-2025) — `review_triggers` registra.
- Webhook em produção precisa 99.9% UP — alarme se não recebe nada em 24h.
- **Risco ban Meta no driver Z-API default** — bloco "Risco aceito conscientemente" + mitigações duras.
- Documentação dual aumenta — guia onboarding Meta + guia Z-API + runbook emergência.

**Riscos mitigados:**

- **Ban Meta no driver Z-API**: monitorado via `WhatsappDriverHealthCheck` + fallback OBRIGATÓRIO Meta Cloud (gating no FormRequest) + termo LGPD assinado (Sprint 2).
- **Ban Meta no driver Meta Cloud**: zero (oficial).
- **PII vazamento**: telefone cliente redacted em logs; tokens cifrados em DB; webhook valida assinatura antes de processar.
- **Cross-tenant leak**: webhook URL tem `business_uuid` no path; global scope `business_id` em todas Models; teste `MultiTenantIsolationTest` obrigatório.
- **Vendor lock-in**: Driver pattern + 2 implementações desde dia 1 (Z-API default + Meta Cloud fallback + Null em Sprint 1).
- **Self-host risk (Evolution)**: eliminado — driver PROIBIDO Tier 0.

## Alternativas consideradas

- **BSP brasileiro (Take Blip / Zenvia)** — descartado por custo 30× pra perfil atual; reabrir se algum business passar 5k conv/mês ou pedir compliance enterprise.
- **Twilio** — descartado por cobrança USD volátil + markup 30%.
- **Meta Cloud como default + Z-API opcional** — descartado por emenda 3: mercado BR PME real exige onboarding 5 min, não 1-3 dias. Meta vira rede de segurança.
- **Apenas Z-API (sem Meta Cloud cadastrado)** — descartado: businesses enterprise vão exigir oficial; ban risk é real; fallback precisa existir desde dia 1 com gating duro.
- **Evolution API self-host CT 100** — descartado por experiência real Wagner (emenda 4): bans recorrentes em produção + schema não atende + falta de observabilidade. PROIBIDO permanente.
- **Implementar BaileysDriver custom já no Sprint 1** — descartado por recomendação Claude (aceita Wagner): primeiro validar drivers oficiais (Z-API + Meta Cloud) em produção, aprender com bugs, e só então construir daemon Node próprio. Sprint 3 fica anotado como evolução natural.
- **whatsapp-web.js (lib JS pura alternativa a Baileys)** — descartado: sobreposição funcional com BaileysDriver custom; lib mais antiga; sem razão técnica pra preferir sobre Baileys.
- **Esperar laravel/whatsapp oficial** — não existe; implementação direta com `Http::post()` é trivial.

## Referências

- ADR [0011](0011-alinhamento-padrao-jana.md) — alinhamento padrãa Jana (módulo referência)
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
