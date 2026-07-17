---
slug: 0202-whatsapp-profissionalizacao-baileys-out
number: 202
title: "WhatsApp profissionalização — Meta Cloud API default universal + Z-API opcional fallback + BaileysDriver/daemon OUT"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
proposed_by: audit-senior-expert (opus-4.7)
prompted_by: wagner
created: 2026-05-27
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: 2026-05-27
accepted_by: wagner
audit_pre_flight:
  baileys_active_business_configs: 0
  baileys_messages_historical: 0
  baileys_tables_existing: [whatsapp_baileys_auth_state]
  audited_at: 2026-05-27
  audited_by: claude-code-opus-4.7
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, integracao, meta-cloud, zapi, baileys-out, daemon-removido, profissionalizacao, embedded-signup-v4, multi-tenant]
parent_adr: 0094
supersedes_partially: [0096-modulo-whatsapp-meta-cloud-api-direto]
supersedes: []
related: [0035-stack-ai-canonica-wagner-2026-04-26, 0058-reverb-substituido-por-centrifugo-frankenphp, 0062-separacao-runtime-hostinger-ct100, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0096-modulo-whatsapp-meta-cloud-api-direto, 0105-cliente-como-sinal-guiar-sem-mandar, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0117-multiplos-numeros-whatsapp-por-business, 0140-jana-pro-produto-comercial-saas]
authors: [audit-senior-expert, wagner]
pii: false
companion_dossier: ../../sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md
review_triggers:
  - Meta muda pricing BR substancialmente (≥ 50% utility ou ≥ 100% marketing)
  - Cliente pagante pede compliance ISO/SOC2 / BSP enterprise dedicado
  - Embedded Signup v4 deprecated antes Q4/2026 (Meta soltar v5 incompatível)
  - Volume médio passa 5k conv/mês em algum business → avaliar BSP com SLA
  - Cliente legacy aparece 100% volume Z-API ativo → considerar Z-API per-business default
  - Onda detecção Meta atinge Cloud API oficial (improvável catastrófico)
  - JANA Pro custo LLM + WhatsApp combined > 20% revenue → repricing ou otimização
  - 3+ clientes pedirem feature WhatsApp que só BSP entrega (broadcast list nativo, catálogo Pix profundo, etc)
---

# ADR proposta · WhatsApp profissionalização: Meta Cloud default universal + BaileysDriver OUT

> **Status:** 🟡 **PROPOSED** — aguardando aceite Wagner em ≤10 min via dossier companion.
> **Companheiro:** [`memory/sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md`](../../sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md)
> **Supersede:** parcialmente [ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md) emenda 4 (BaileysDriver custom Sprint 3 autorizado) — explicitamente revogada.
> **Não toca:** ADR 0096 emenda 1-3 (Z-API driver válido + Evolution PROIBIDO + fallback obrigatório) — preservadas.

## Contexto

Em 2026-05-07 [ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md) estabeleceu, via 4 emendas no mesmo dia:

1. Drivers válidos: **Z-API (default) + Meta Cloud (fallback obrigatório) + BaileysDriver custom (Sprint 3) + NullDriver (CI)**
2. **Evolution API PROIBIDO permanente** (bans recorrentes em produção Wagner + schema não atende + observabilidade ruim)
3. **Z-API como default Sprint 1** por: onboarding 5 min vs 1-3 dias Meta + mercado BR PME real usa + sem janela 24h restritiva
4. **BaileysDriver custom Sprint 3** autorizado pra "estrutura customizada de atendimento" — daemon Node CT 100 próprio com schema/observabilidade próprios

### O que aconteceu desde então

- **Sprint 3 BaileysDriver foi implementado:** daemon Node TypeScript em `Modules/Whatsapp/daemon-node/` com Fastify + zod + Baileys 7.0.0-rc11 (migrado 2026-05-15) + `mysqlAuthState.ts` + `antiBan.ts` + `banDetector.ts` + `historySync` + OTel + Prometheus + 13 testes Vitest + 5 runbooks + 12 drivers PHP
- **Container `whatsapp-baileys` rodando em CT 100** desde Sprint 3 com Traefik route `whatsapp-baileys.oimpresso.local` + IP whitelist Hostinger + Docker secret `whatsapp_baileys_api_key`

### Sinal qualificado novo (2026-05-27 hoje)

Wagner em sessão atual reportou (palavras textuais):

> "**ninguém está ativo no Baileys, pode desconectar todos. é instável não deu pra usar**"
>
> "**tem que o processo melhorar muito né, não sei como fazer. pode ajudar a profissionalizar?**"

Tradução: **BaileysDriver custom FALHOU em produção.** Wagner tentou usar, deu instabilidade, abandonou. Autorização explícita pra desligar daemon CT 100. Pedido: definir caminho profissional do zero.

**Por [ADR 0105 cliente como sinal qualificado](../0105-cliente-como-sinal-guiar-sem-mandar.md):** Wagner = cliente (do oimpresso e da decisão arquitetural). Reportou dor concreta. **Sinal qualificado existe** — backlog deve responder, não esperar mais sinal.

### Pesquisa estado-da-arte 2026 (fontes citadas)

1. **Onda detecção Meta 2026 atinge whatsmeow E Baileys igualmente** ([whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810)): "Your account may be at risk" warning afetando clients usando WhatsMeow (e reportado com Baileys) independente de patterns de uso ou safeguards como WAM. Algumas accounts já banidas com uso legítimo low-volume. **Conclusão:** trocar lib JS→Go não resolve risco.

2. **Embedded Signup v4 default mandatório 15/out/2026** ([PPCLand 2026-05-14](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/)): Meta publicou chamada pra developers migrar pra v4. Hard deprecation 15/out/2026. v4 onboarding 5-15 min em popup ([Whautomate 2026](https://whautomate.com/whatsapp-embedded-signup)). **Conclusão:** premissa "Z-API default por onboarding 5min vs Meta 1-3 dias" da emenda 3 ADR 0096 **caiu**.

3. **Service messages unlimited grátis desde nov/2024** ([Chatarmin 2026](https://chatarmin.com/en/blog/whats-app-api-pricing)): Meta removeu cap 1.000 free service conversations/mês. Customer-initiated service msg agora **unlimited free**. **Conclusão:** fluxo conversacional inbound oimpresso entra integral em R$ [redacted Tier 0] — JANA Pro custo marginal zero reforçado.

4. **Custo Meta Cloud BR 200 conv/mês** ([Message Central 2026](https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil)): utility R$ [redacted Tier 0]/conv × 200 = **R$ [redacted Tier 0]/mês** + service free. **MENOR** que R$ [redacted Tier 0]/mês fixo Z-API.

5. **BSP enterprise BR 2026** ([Message Central comparison](https://www.messagecentral.com/blog/best-whatsapp-business-api-platform-brazil)): Take Blip ~R$ [redacted Tier 0]+/mês fixo + per-msg markup 20-40%; Twilio USD instável; 360dialog EUR €49/mês mínimo + zero markup. **Overkill 30×** pra perfil 50-200 conv/mês atual.

6. **Z-API SaaS continua viável mas não-oficial** ([Z-API.io 2026](https://z-api.io/)): claim 99.9% uptime + ban rate < 0.3% — mas é explicitamente **NÃO BSP licenciado Meta**. Não satisfaz cenário enterprise compliance.

7. **Realidade time:** Wagner + Felipe + Maiara/Eliana operam PHP + TypeScript. Sem Go expert. Rewrite whatsmeow (Go) ou adopção WuzAPI (Go) adicionaria stack runtime crítico em linguagem que ninguém domina.

## Decisão

**Profissionalizar via 3 movimentos simultâneos:**

### Movimento 1: BaileysDriver + daemon Node CT 100 OUT integral

- **Driver PHP `BaileysDriver`** — DELETADO
- **Daemon Node `Modules/Whatsapp/daemon-node/`** — ARQUIVADO (branch `archive/baileys-daemon` + git tag `baileys-final-2026-05-27`) → DELETADO de `main`
- **Schema:** colunas `baileys_*` de `whatsapp_business_configs` + `whatsapp_business_phones` DROPADAS via migration
- **Container CT 100 `whatsapp-baileys`** — STOPPED + REMOVED (Wagner autorizou "desconectar todos hoje")
- **Volume sessions** — preservado 90d backup LGPD, deletado 2026-08-27
- **Driver `baileys`** — adicionado à lista `forbidden_drivers` em `config/whatsapp.php` junto com `evolution` (FormRequest 422 ValidationException se tentar salvar)
- **Runbooks 5 docs Baileys** — movidos pra `runbooks/_archive/`
- **ARCHITECTURE.md §16 (Sprint 3 Baileys)** — marcada SUPERSEDED com link pra esta ADR

### Movimento 2: Meta Cloud API como DEFAULT universal

- `config/whatsapp.php` → `default_driver = 'meta_cloud'` (era `zapi`)
- **Embedded Signup v4 implementado** na UI Settings — onboarding 5-15 min via OAuth popup Meta
- `MetaCloudDriver::provisionViaEmbeddedSignup(code, state)` — troca OAuth code → access_token permanent + auto-subscribe webhook fields
- **Gating FormRequest** ajustado: `driver=meta_cloud` exige apenas campos `meta_*` preenchidos. Não precisa fallback gating (driver oficial = sem ban risk).
- **Runbook canônico:** `runbooks/onboarding-meta-cloud-embedded-signup.md` (rename de `ativar-cloud-api-canary-biz99.md`)

### Movimento 3: Z-API rebaixado pra opcional não-default

- `ZapiDriver` PHP **preservado intacto** (~150 linhas, testes Pest mantidos)
- UI Settings: aba Z-API vira "Opções avançadas — Z-API legacy" (collapsed default)
- **Quando usar Z-API:**
  - Business legacy que já tem número Z-API ativo em produção e prefere não migrar provedor (custo zero manter)
  - Fallback emergencial se Meta Cloud tiver outage raro (improvável dado SLA 99.9% Meta oficial)
- **Gating FormRequest preservado:** se `driver=zapi`, exige Meta Cloud cadastrado como `fallback_driver` + `lgpd_acknowledged_at` not null (proteção emenda 2/3 ADR 0096 preservada)
- **Termo LGPD Z-API** preservado: business assina "ciente que Z-API não-oficial, risco ban Meta"

### O que NÃO muda (preservado integralmente)

- ✅ **Evolution API PROIBIDO permanente** (ADR 0096 emenda 2/4 preservada)
- ✅ **whatsapp-web.js PROIBIDO** (ADR 0096 preservada)
- ✅ **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) — `business_id` global scope, webhook URL com `business_uuid`, tokens cifrados Laravel encrypted cast
- ✅ **Múltiplos números Whatsapp por business** ([ADR 0117](../0117-multiplos-numeros-whatsapp-por-business.md)) — Meta Cloud suporta nativo (1 `phone_number_id` por número), Z-API suporta multi-instance
- ✅ **Hostinger ≠ CT 100** ([ADR 0062](../0062-separacao-runtime-hostinger-ct100.md)) — webhook receiver Hostinger HTTP-only, Horizon worker CT 100 (sem novo daemon CT 100)
- ✅ **Append-only** `whatsapp_messages` (trigger MySQL bloqueia UPDATE em colunas-chave)
- ✅ **PII redacted em logs** (`App\Support\PiiRedactor`)
- ✅ **OTel/Prometheus métricas** (`whatsapp.*` namespace continua)
- ✅ **Centrifugo real-time UI** ([ADR 0058](../0058-reverb-substituido-por-centrifugo-frankenphp.md)) — channel `whatsapp:business:{id}` independente de driver
- ✅ **HSM templates Meta** (workflow aprovação 1-3 dias via Meta Business Manager mantido)

## Justificativa

### Por que Meta Cloud como default universal (era fallback)

1. **Embedded Signup v4 5-15 min** ([Whautomate 2026](https://whautomate.com/whatsapp-embedded-signup)) — derruba premissa "Meta onboarding 1-3 dias" da emenda 3 ADR 0096. Onboarding agora COMPARÁVEL ao Z-API.
2. **Mandatório 15/out/2026** ([PPCLand](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/)) — alinhar agora dá 4.5 meses runway antes de pressure emergencial.
3. **Service messages unlimited grátis** ([Chatarmin 2026](https://chatarmin.com/en/blog/whats-app-api-pricing)) — destrava custo zero JANA Pro [ADR 0140](../0140-jana-pro-produto-comercial-saas.md) em margem 96-98% real.
4. **R$ [redacted Tier 0]/mês 200 conv vs R$ [redacted Tier 0] Z-API** — MAIS BARATO no default. Pricing favorece migrar.
5. **Zero ban risk** — driver oficial, SLA 99.9%, compliance enterprise possível.
6. **Constituição v2 [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) #7 Transparência** — driver default oficial, sem TOS violation por padrão.

### Por que BaileysDriver/daemon OUT (sem trocar por whatsmeow ou WuzAPI)

1. **Wagner sinal qualificado [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)** — "ninguém ativo + instável". Cliente reportou dor concreta. Backlog deve responder.
2. **Onda detecção Meta 2026 atinge whatsmeow IGUAL Baileys** ([whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810)) — rewrite Go (Opção C/γ do dossier) ou adopção WuzAPI (Opção δ) seria queimar 40-120h pra MESMO risco que Wagner rejeitou.
3. **Time sem Go expert** — adicionar Go ao stack runtime crítico (não experimento) viola SoC brutal Constituição v2 #5.
4. **Daemon não justificável** — razão da emenda 4 ADR 0096 ("dor de observabilidade Evolution") já não se aplica: Evolution proibido + Z-API SaaS responde por sua própria infra + Meta Cloud oficial.
5. **CT 100 livra ~2.4GB RAM** que estava reservado pra daemon Baileys (`MAX_INSTANCES=30 × 80MB`) — economia recursos pra Horizon/Centrifugo crescerem.

### Por que Z-API preservado opcional (não excluído junto)

1. **Sem custo manutenção** — driver maduro ~150 linhas, sem dependência exótica. Pest passa, deploy passa.
2. **Hedge contra outage Meta** — Meta SLA 99.9% mas eventos existem (5 conhecidos 2024-2025). Z-API independente. Constituição #8 fallback honrado.
3. **Onboarding business legacy** — futuro business que aparece com número Z-API ativo pode entrar sem migrar provedor.
4. **Permite mix per-phone** — ADR 0117 multi-phone permite mix drivers por número (Comercial Meta + Financeiro Z-API se preferir).

### Por que NÃO BSP enterprise agora

1. **Take Blip R$ [redacted Tier 0]+/mês** = 30× overkill perfil 50-200 conv/mês atual
2. **Twilio USD volátil + markup 30%** — câmbio = imprevisível
3. **360dialog €49/mês + zero markup** — best-in-class BSP mas alvo enterprise EUR (latência BR + suporte EU)
4. **Reaberto via review_trigger** quando cliente pagante específico pedir (≥ R$ [redacted Tier 0]/mês recorrente assinado)

## Consequências

### Positivas

- **JANA Pro [ADR 0140] margem 96-98% real** (vs 92-94% projetado) — service messages unlimited free destravam 4-5 pontos percentuais
- **Custo operacional WhatsApp ↓** — R$ [redacted Tier 0]/mês Z-API economizados + R$ [redacted Tier 0] daemon CT 100
- **Risco ban Meta default → zero** — driver oficial. Compliance enterprise possível (ISO/SOC2 destravável se cliente pedir)
- **Stack mais simples** — sem daemon CT 100 = 1 menos fronteira de falha + 5 runbooks deprecados
- **Alinhamento mandatório Meta v4 outubro 2026** — runway 4.5 meses ANTES da pressão
- **Wagner deixa de ser mantenedor Node runtime crítico** — bug Baileys às 02h da manhã sai do horizonte
- **Time MCP onboarding mais simples** — menos doc, menos linguagem, menos magia
- **Constituição v2 [ADR 0094] honrada em 8 princípios** (dossier §1.3 detalha)

### Negativas / Trade-offs

- **Sunk cost daemon Node ~5 sprints (antiBan, banDetector, mysqlAuthState, historySync, scripts, rotação chave, 13 Vitest tests, 5 runbooks)** — preservado em git tag `baileys-final-2026-05-27` + branch arquivada `archive/baileys-daemon` 90d. Aceito como lição (R10/R11 PROTOCOLO-WAGNER-SEMPRE: continuar autonomamente; sunk cost cliente disse não-funcionou).
- **Migração Baileys 7.0.0-rc11 feita 12 dias atrás (2026-05-15) vira investimento queimado** — ~4h Wagner perdidas naquela sessão. Aceito como lição "premissa precisava ser validada com cliente antes de migrar lib".
- **Embedded Signup v4 implementação adicional ~12-18h IA-pair** — não-trivial pra Pages/Settings.tsx (OAuth popup + callback + auto-token persistência). Mitigado: docs Meta oficiais robustas + Whautomate guide.
- **Cliente legacy raro com número Z-API ativo precisa onboarding mais explícito** — UI vai esconder Z-API por padrão. Mitigação: aba "Opções avançadas" descobrível + runbook `runbooks/onboarding-zapi-legacy.md`.
- **Drop colunas `baileys_*` perde tokens cifrados permanentemente** — encrypted cast Laravel não permite recovery pós-DROP. Aceito (Wagner autorizou "desconectar todos").

### Riscos mitigados

| Risco | Mitigação |
|---|---|
| Embedded Signup v4 deprecated antes outubro 2026 | Monitor changelog Meta + skill Tier C `meta-cloud-deprecation-watch` |
| Cross-tenant leak pós-migration | `MultiTenantIsolationTest` em CI cobre + `business_id` global scope preservado + `phone_number_id` Meta per-business + webhook URL com `business_uuid` |
| LGPD direito esquecimento dados Baileys | Volume sessions preservado 90d + `whatsapp_messages` rows imutáveis (provider='baileys' fica) + script `php artisan whatsapp:forget-contact` continua funcional |
| Migration DROP columns falha em prod | Dry-run biz=1 ANTES + `DROP IF EXISTS` defensivo + Pest cobre + rollback = down migration recria colunas vazias |
| Z-API SaaS fechar/mudar API | Driver opcional não-default, custo de impacto = baixo. Deprecar em 1 PR se necessário. |
| Onda detecção Meta atingir Cloud API oficial (improvável) | Catastrófico → emergência BSP enterprise (review_trigger) |

## Alternativas consideradas (não escolhidas)

Dossier companion §1 detalha 5 candidatas α/β/γ/δ/ε com tabela comparativa. Resumo razão de rejeição:

### (α) Meta Cloud only puro
**Rejeitada quase** — bom default mas remove `ZapiDriver` legacy. Custo zero manter Z-API opcional + hedge fallback + onboarding business legacy = melhor reter.

### (γ) Meta Cloud + whatsmeow daemon Go custom
**Rejeitada hard** — onda detecção Meta 2026 atinge whatsmeow igual Baileys ([issue #810](https://github.com/tulir/whatsmeow/issues/810)). Time sem Go expert. 80-120h trabalho pra MESMO risco que Wagner já rejeitou. Não responde ao sinal cliente.

### (δ) Meta Cloud + WuzAPI wrapper
**Rejeitada hard** — mesmo argumento γ + perda controle wrapper (open-source community-driven) + roadmap WuzAPI fora controle oimpresso + perde antiBan custom (que era justificativa #3 daemon próprio).

### (ε) BSP enterprise (Take Blip / Twilio / 360dialog)
**Rejeitada por ora** — R$ [redacted Tier 0]+/mês = 30× overkill perfil PME atual. Reaberto via review_trigger quando cliente pagante específico pedir SLA enterprise. **Não fecha porta.**

### Opção D híbrida experimental (proposta de manhã 2026-05-27)
**Rejeitada (auto-substituída)** — proposta de manhã `2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md` recomendava experimentar whatsmeow paralelo 90 dias. **Sinal Wagner de tarde** ("ninguém ativo + instável") torna proposta D obsoleta — Wagner não vai investir 40-60h sprint experimental pra tecnologia que JÁ falhou em produção dele.

## Plano de implementação faseado

Detalhe completo em dossier companion §4. Resumo 5 fases:

| Fase | Quando | Wagner-h | Codáveis IA-h | Relógio |
|---|---|---|---|---|
| Fase 0: desligar daemon CT 100 | hoje 27/mai | 1h | 0 | 1 dia |
| Fase 1: remoção código + schema cleanup | 28-29/mai | 2h | 4-6h | 2 dias |
| Fase 2: Embedded Signup v4 + Meta default | 30/mai - 03/jun | 6h | 12-18h | 4 dias |
| Fase 3: smoke produção biz piloto (canary 7d) | 04-10/jun | 7h | 0 | 7 dias |
| Fase 4: cutover/rollout + governança 30d | 11/jun - 30/jun + ongoing | 2h | 0 | 30 dias |
| **TOTAL** | **27/mai - 30/jun** | **~18h** | **16-24h** | **~37 dias** |

Estimates conforme [ADR 0106 recalibração 10x](../0106-recalibracao-velocidade-fator-10x-ia-pair.md) + margem 2× aplicada.

## Métricas de sucesso (gates)

Detalhe em dossier §6. Resumo:

### Quantitativas 90 dias
- Uptime sessão: 99.9% (vs "instável" Wagner reportou Baileys)
- Custo/conv: R$ [redacted Tier 0] utility + R$ [redacted Tier 0] service (vs R$ [redacted Tier 0]/mês fixo Z-API)
- Latência send p95: < 2s
- **0 bans Meta** (driver oficial)
- `whatsapp.driver.fallback` counter Z-API ativado = 0 (saúde Meta 100%)
- 0 cross-tenant leak (Pest + manual review log)

### Qualitativas
- Wagner usa sem dor 30 dias
- JANA Pro brief diário [ADR 0140 Sprint JANA-A US-COPI-201] entrega via Meta Cloud
- Repair status `ready` dispara WhatsApp automaticamente (US-WA-004 finalmente cumprida)

### Gate Mês 1
Fase 3 (canary 7d) deve ter:
- Wagner cadastrou número via Embedded Signup v4 em ≤ 15 min real
- 10 mensagens utility + 10 service trocadas
- Zero alarme Loki/Grafana

### Gate Mês 3
Fase 4 (monitor 30d) deve ter:
- 2+ businesses ativos no Meta Cloud
- 0 incidente cross-tenant
- Custo cumulativo dentro projeção [ADR 0140]

## Triggers de reavaliação (review_triggers)

Conforme frontmatter:

1. **Meta muda pricing BR substancialmente** (≥ 50% utility ou ≥ 100% marketing)
2. **Cliente pagante pede compliance ISO/SOC2** / BSP enterprise dedicado (reabrir Take Blip / Twilio / 360dialog)
3. **Embedded Signup v4 deprecated antes Q4/2026** (adapt path v5 ou manual fallback)
4. **Volume médio passa 5k conv/mês** algum business (avaliar BSP com SLA dedicado)
5. **Cliente legacy aparece 100% volume Z-API ativo** (considerar Z-API per-business default)
6. **Onda detecção Meta atinge Cloud API oficial** (improvável catastrófico, emergência BSP)
7. **JANA Pro custo combined > 20% revenue** (repricing ou otimização cache)
8. **3+ clientes pedirem feature WhatsApp que só BSP entrega** (proativo broadcast list, catálogo Pix profundo, etc)

## Referências

### ADRs canon oimpresso preservadas/superadas
- **Mãe:** [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 7 camadas + 8 princípios duros
- **Supersede parcial:** [ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md) emenda 4 (BaileysDriver custom Sprint 3) — revogada
- **Preservadas:** [ADR 0093](../0093-multi-tenant-isolation-tier-0.md), [ADR 0117](../0117-multiplos-numeros-whatsapp-por-business.md), [ADR 0140](../0140-jana-pro-produto-comercial-saas.md), [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- **Infra:** [ADR 0058 Centrifugo](../0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0062 Hostinger ≠ CT 100](../0062-separacao-runtime-hostinger-ct100.md), [ADR 0035 Stack IA](../0035-stack-ai-canonica-wagner-2026-04-26.md)
- **Companheiros documentais:** [ARCHITECTURE.md Whatsapp](../../requisitos/Whatsapp/ARCHITECTURE.md), [SPEC.md Whatsapp](../../requisitos/Whatsapp/SPEC.md)
- **Proposta antiga (rejeitada hoje 27/mai tarde):** [2026-05-27 Baileys vs whatsmeow](./2026-05-27-baileys-vs-whatsmeow-substituicao-daemon-whatsapp.md)

### Fontes externas 2026 (pesquisa profunda)
- [Whautomate · Embedded Signup 5-15min 2026](https://whautomate.com/whatsapp-embedded-signup) — onboarding flow oficial Meta
- [PPCLand · Embedded Signup v4 outubro 2026 deadline](https://ppc.land/metas-embedded-signup-v4-is-here-but-the-october-15-clock-is-ticking/) — mandatoriedade Meta
- [Chatarmin · WhatsApp API Pricing 2026](https://chatarmin.com/en/blog/whats-app-api-pricing) — service msg unlimited free desde nov/2024
- [Message Central · Brazil API Pricing 2026](https://www.messagecentral.com/blog/whatsapp-business-api-pricing-in-brazil) — R$ [redacted Tier 0] utility BR
- [Message Central · Best WhatsApp BSP Brazil 2026](https://www.messagecentral.com/blog/best-whatsapp-business-api-platform-brazil) — comparison 8 BSPs
- [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — onda detecção Meta atinge whatsmeow IGUAL Baileys
- [kraya-ai · Automation Ban Risk 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk) — análise ban risk não-oficial
- [github.com/asternic/wuzapi](https://github.com/asternic/wuzapi) — WuzAPI wrapper rejeitado
- [Z-API.io](https://z-api.io/) — SaaS BR não-oficial preservado opcional

## Aprovação

Wagner aceita ADR alterando:

```yaml
status: accepted
decided_at: 2026-05-27
accepted_at: 2026-05-27
accepted_by: wagner
number: <próximo número disponível em memory/decisions/>
```

E executando Fase 0 (SSH CT 100 `docker compose stop whatsapp-baileys`) hoje 27/mai conforme autorização explícita "pode desconectar todos".

Wagner rejeita ADR alterando `status: rejected` + razão em campo `rejected_reason` (criar campo no frontmatter).

---

**Proposta autoria:** audit-senior-expert (opus-4.7) · 2026-05-27 · PT-BR · Sem hedge
**Decisão final:** Wagner em ≤ 10 min via dossier companion
