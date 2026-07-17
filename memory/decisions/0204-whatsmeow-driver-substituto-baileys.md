---
slug: 0204-whatsmeow-driver-substituto-baileys
number: 204
title: "WhatsApp whatsmeow Go driver — substituto não-oficial Baileys (amend ADR 0202)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
proposed_by: audit-implement-expert (opus-4.7)
prompted_by: wagner
created: "2026-05-27"
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: "2026-05-27"
accepted_by: wagner
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, integracao, whatsmeow, baileys-substituto, daemon-go, wuzapi, multi-tenant, profissionalizacao, embedded-signup-v4]
parent_adr: 0094-constituicao-v2-7-camadas-8-principios
supersedes_partially: [0202-whatsapp-profissionalizacao-baileys-out]
supersedes: []
related: [0058-reverb-substituido-por-centrifugo-frankenphp, 0062-separacao-runtime-hostinger-ct100, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0096-modulo-whatsapp-meta-cloud-api-direto, 0105-cliente-como-sinal-guiar-sem-mandar, 0117-multiplos-numeros-whatsapp-por-business, 0202-whatsapp-profissionalizacao-baileys-out]
authors: [audit-implement-expert, wagner]
pii: false
review_triggers:
  - Onda detecção Meta 2026 atinge whatsmeow com taxa > 2x Cloud API → reavaliar
  - WuzAPI projeto descontinuado / wrapper instável → fork interno ou whatsmeow direto
  - Cliente pagante reporta dor "Meta Cloud ok mas onboarding burocrático" → priorizar Embedded Signup v4
  - Beeper (mantenedor whatsmeow) deprecar lib → migrar pra fork ou alternativa
  - Sessões whatsmeow drift > 5%/mês banidas → fallback agressivo pra Meta Cloud
  - Custo CT 100 daemon Go + sessões superar US$ 30/mês → otimizar imagem ou abandonar
---

# ADR 0204 — WhatsApp whatsmeow Go driver — substituto não-oficial Baileys (amend ADR 0202)

## Contexto

[ADR 0202](0202-whatsapp-profissionalizacao-baileys-out.md) (aceita 2026-05-27, mesma data desta ADR) descontinuou `BaileysDriver` + daemon Node CT 100 baseado no sinal qualificado Wagner:

> "ninguém está ativo no Baileys, pode desconectar todos. é instável não deu pra usar"

A interpretação da ADR 0202 foi **eliminar driver não-oficial inteiro** e ir direto pra Meta Cloud (oficial Meta) + Z-API (SaaS BR) como únicas opções. Decisão executada: BaileysDriver removido, daemon Node deletado, schema dropado, UI marca Baileys `enabled=false`.

### Sinal qualificado novo (2026-05-27, horas após ADR 0202)

Wagner em sessão de revisão pediu (palavras textuais):

> "queria o driver novo (substituto Baileys), o outro qual o nome?"
>
> "sim era isso que deveria"

Tradução: ADR 0202 acertou em remover Baileys **instável**, mas errou ao interpretar que Wagner queria **eliminar driver não-oficial inteiro**. O pedido real era **substituto direto** Baileys — onboarding simples (scan QR 5 min, custo zero, sem Meta Business Manager) com **estabilidade técnica melhor**.

### Por que substituir e não eliminar

Meta Cloud API (oficial) tem onboarding complexo pra PME real Brasil:

- Exige Meta Business Manager (BM) verificado — cliente PME (Larissa, Termas) raramente tem
- Exige Embedded Signup v4 OAuth Facebook — burocracia 5-15 min vs scan QR 30s
- Custo: free 1k conv/mês, depois R$ [redacted Tier 0]-0,30/msg (utility vs marketing) — barato mas previsível
- Janela 24h — fora dela exige HSM template aprovado

Driver não-oficial (Baileys/whatsmeow) atende:

- Onboarding 30s (scan QR no celular)
- Custo zero (só CT 100 que já paga)
- Sem janela 24h
- Cliente PME real que ainda não tem BM verificado

Wagner precisa de **ambos** — Meta Cloud pra clientes maduros / regulados, não-oficial pra onboarding rápido PME.

### Por que whatsmeow (não voltar Baileys)

Pesquisa estado-da-arte 2026 (já catalogada na proposta rejeitada [2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md](proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md)):

| Métrica | Baileys (Node) | whatsmeow (Go) |
|---|---|---|
| Footprint RAM por sessão | ~80 MB | ~50 MB |
| Memory leak long-running | reportado v6.x, fix parcial v7 | estável |
| Mantenedor | Comunidade (WhiskeySockets) | Pago Beeper (Tulir Asokan) |
| Linguagem | TypeScript ESM | Go (binary) |
| Ban risk Meta 2026 | Igual whatsmeow | Igual Baileys |
| Multi-session | Custom (mysqlAuthState) | Nativo + multi-device |
| Sessões long-running estáveis | Frequente reconnect | Sólido |

**Decisão racional:** Wagner já abandonou Baileys (instável). Voltar Baileys = recriar mesmo problema. whatsmeow tem **mesmo modelo de uso** (scan QR, custo zero, ban risk) com **estabilidade técnica melhor**.

### Trade-off explícito (Wagner ciente e aceita)

**Risco ban Meta = igual Baileys.** [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) documenta onda detecção Meta 2026 atingindo whatsmeow tanto quanto Baileys — ML do WhatsApp olha reply-ratio, contact-graph, patterns temporais; trocar lib **não protege** de ban.

**O ganho é técnico**, não de risco:
- Footprint menor (~37% menos RAM por sessão)
- Sessões long-running estáveis (Baileys v7 fechou parte do gap mas whatsmeow é referência)
- Mantenedor pago Beeper (comparado a comunidade Baileys)

**Mitigações reusadas integralmente do canon Meta Cloud (ADR 0096 emenda 1-3 preservadas):**
- Fallback Meta Cloud obrigatório (gating duro FormRequest)
- Termo LGPD aceito explicitamente (mesmo padrão Baileys)
- `WhatsappDriverHealthCheckJob` 6h em 6h
- Fallback automático whatsmeow → Meta Cloud quando driver_health ≥ degraded
- Cross-tenant ban alarm threshold (3 businesses banidos em 24h = alerta Wagner)

## Decisão

**Adicionar driver `whatsmeow`** como alternativa não-oficial ao `meta_cloud` e `zapi`. Baileys continua **forbidden** (deletado integralmente em ADR 0202 + commits f1554ab790 → 0535ddad2).

### Drivers válidos pós ADR 0204

| Driver | Tipo | Status | Onboarding | Risco ban |
|---|---|---|---|---|
| `meta_cloud` | Oficial Meta Cloud API | ✅ default universal | 5-15min Embedded Signup v4 | Zero |
| `zapi` | SaaS BR Z-API | ✅ opcional legacy | 5min scan QR | Médio (Meta) |
| `whatsmeow` | Daemon Go próprio CT 100 (via WuzAPI) | ✅ opcional novo | 30s scan QR | Médio (Meta) |
| `null` | Dev/CI | ✅ Pest | n/a | n/a |
| `baileys` | Custom Node (descontinuado) | ❌ **forbidden** | n/a | n/a |
| `evolution` | Evolution API | ❌ **forbidden permanente** | n/a | n/a |
| `whatsapp_web_js` | whatsapp-web.js | ❌ **forbidden permanente** | n/a | n/a |

### Implementação técnica

**Daemon Go via [WuzAPI](https://github.com/asternic/wuzapi)** (asternic/wuzapi — REST API wrapper sobre [whatsmeow](https://github.com/tulir/whatsmeow)):

- Imagem Docker pronta `asternic/wuzapi:latest` rodando em CT 100
- Multi-session nativa (1 sessão por channel oimpresso)
- Persistência sessões em volume Docker
- Webhook outbound configurável per-session
- Endpoints REST documentados em [API.md](https://github.com/asternic/wuzapi/blob/main/API.md)
- HMAC signature verification (`x-hmac-signature` header SHA-256)

**Driver PHP (`WhatsmeowDriver`):**
- Implementa `DriverInterface` igual ZapiDriver/MetaCloudDriver
- Resolve daemon via `config('whatsapp.whatsmeow.daemon_url')` + Bearer token
- Tokens cifrados em DB via `encrypted:array` no `channels.config_json`
- Spans OTel padronizados (`whatsapp.driver.whatsmeow.<method>`)

**UI integração:**
- Adiciona `WhatsmeowFields` em `Atendimento/Channels/Index.tsx` (mesmo pattern BaileysFields)
- Tipo `whatsapp_whatsmeow` em `Channel::TYPES`
- LGPD ack obrigatório no FormRequest (mesma rule Baileys)
- Connect modal mostra QR PNG retornado pelo daemon

### Onde NÃO mudar

- **Meta Cloud continua default universal** (ADR 0202 §Decisão preservada)
- **Z-API continua opcional legacy** (ADR 0202 §Decisão preservada)
- **Embedded Signup v4 continua disponível** (ADR 0202 §Decisão preservada)
- **forbidden_drivers** continua bloqueando `baileys` + `evolution` + `whatsapp_web_js`
- **Multi-tenant Tier 0** preservado integralmente — webhook URL leva `{business_uuid}`, business_id global scope, tokens cifrados (ADR 0093)

## Justificativa

**Não escolhemos whatsmeow direto (sem WuzAPI):**

- Time não tem Go expert hoje (Wagner + Felipe + Maiara/Eliana operam TypeScript/PHP)
- Construir daemon-go from scratch = ~500-1000 LOC Go + risco recriar bugs já resolvidos no WuzAPI
- WuzAPI tem ~3 anos produção, Docker image pronta, multi-session, webhook nativo
- Se gap aparecer (endpoint que WuzAPI não cobre), fork interno + patch depois

**Não escolhemos voltar Baileys ajustado:**

- Wagner já reportou abandono ("instável não deu pra usar")
- Daemon Node Baileys foi DELETADO (ADR 0202) — preservado em branch `archive/baileys-daemon` + tag `baileys-final-2026-05-27` pra arqueologia, não pra reativar
- Re-implementar Baileys = recriar mesmo problema instabilidade que motivou descontinuação

**Não escolhemos só Meta Cloud (status quo ADR 0202):**

- Sinal qualificado Wagner 2026-05-27 explícito: queria substituto
- PME real Brasil (Larissa, Termas) ainda não tem Meta Business Manager verificado
- Onboarding 5-15 min Meta vs 30s scan QR = barreira real cliente

## Consequências

**Positivas:**
- Wagner re-cria Jana + Suporte channels via UI scan QR (cliente sinal atendido em ≤1 dia pós-merge)
- Onboarding cliente PME novo cai de 5-15min (Embedded Signup v4) pra 30s (scan QR whatsmeow)
- Sessões long-running estáveis (vs instabilidade Baileys reportada)
- Footprint CT 100 menor (~37% menos RAM por sessão)
- WuzAPI mantenedor ativo + Beeper sponsoriza whatsmeow upstream

**Negativas / Trade-offs:**
- Risco ban Meta IGUAL Baileys (Wagner ciente, documentado neste ADR + UI)
- Mais 1 container CT 100 pra Wagner gerenciar (1 + Centrifugo + Brain + ...)
- WuzAPI é dependência externa (vs daemon Node interno antes) — risco de descontinuação
- Pré-requisito Wagner deploy CT 100 manual (runbook step-by-step provido)
- Time aprende API WuzAPI (mas é REST simples, sem complexidade Go runtime)

**Riscos mitigados:**
- Multi-tenant Tier 0 — webhook URL com `{business_uuid}`, business_id global scope (ADR 0093)
- Tokens daemon cifrados em DB (cast `encrypted:array` no `channels.config_json`)
- LGPD ack obrigatório FormRequest (mesma rule Baileys)
- Fallback Meta Cloud automático quando driver_health degrada (ADR 0096 emenda 3 preservada)
- Cross-tenant ban alarm (3 banidos/24h → alerta Wagner)
- IP whitelist Hostinger no Traefik daemon (CT 100 não exposto público total)

## Referências

- ADR 0094 — Constituição v2 (parent — Tier 0 multi-tenant + fallback obrigatório)
- ADR 0202 — WhatsApp Profissionalização (amend partially — Baileys OUT integral, esta ADR adiciona whatsmeow)
- ADR 0096 — Meta Cloud direto (emendas 1-3 preservadas: Z-API válido + Evolution PROIBIDO + fallback obrigatório)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0058 — Centrifugo + FrankenPHP runtime CT 100 (whatsmeow daemon vai no mesmo host)
- ADR 0062 — Hostinger ≠ CT 100 separação (whatsmeow é CT 100)
- ADR 0105 — Cliente como sinal qualificado (Wagner = cliente arquitetural, 2026-05-27 sinal explícito)
- ADR 0117 — Multi-números WhatsApp por business (whatsmeow respeita 1:N business→channels)
- [whatsmeow GitHub](https://github.com/tulir/whatsmeow) (Tulir Asokan / Beeper)
- [WuzAPI GitHub](https://github.com/asternic/wuzapi) + [API.md](https://github.com/asternic/wuzapi/blob/main/API.md)
- [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — onda detecção Meta 2026 (transparência ban risk)
- Proposta rejeitada [`proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md`](proposals/2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md) — análise técnica original
- Companion dossier [`memory/sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md`](../sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md)
