# CAPTERRA-FICHA — Whatsapp transacional (BSP / API providers)

> **Cruzamento gerado:** 2026-05-07
> **Skill aplicada:** `comparativo-do-modulo` (cruza com SPEC.md → CAPTERRA-INVENTARIO.md em sprint próximo)
> **Referência ADR:** [0096 — Meta Cloud API direto](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)

## 1. Provedores avaliados (BSPs e plataformas Whatsapp)

| # | Provedor | Tipo | Origem | Site | Especialidade |
|---|---|---|---|---|---|
| 1 | **Meta Cloud API** | Oficial direto | EUA (Meta) | `developers.facebook.com/docs/whatsapp` | Self-host webhook; sem intermediário |
| 2 | **Twilio** | Oficial BSP | EUA | `twilio.com/whatsapp` | Multi-canal global, $$$ |
| 3 | **Take Blip** | Oficial BSP | Brasil (BH) | `take.net` | Enterprise BR, conversational AI próprio |
| 4 | **Zenvia** | Oficial BSP | Brasil (SP) | `zenvia.com` | Mid-market BR, multi-canal |
| 5 | **360dialog** | Oficial BSP | Alemanha | `360dialog.com` | Dev-friendly, sem markup |
| 6 | **MessageBird (Bird)** | Oficial BSP | Holanda | `bird.com` | Global, omnichannel |
| 7 | **Gupshup** | Oficial BSP | Índia | `gupshup.io` | Volume alto, preço agressivo |
| 8 | **Wati** | Oficial BSP | Hong Kong | `wati.io` | SaaS pronto pra PME, no-code |
| 9 | **Sinch** | Oficial BSP | Suécia | `sinch.com` | Telco-grade global |
| 10 | **Infobip** | Oficial BSP | Croácia | `infobip.com` | Enterprise telco |
| 11 | **Z-API** | Não-oficial (Baileys-based) | Brasil | `z-api.io` | **DRIVER ATIVO** — ZapiDriver Sprint 1 (orçamento PME, ban risk monitorado) |
| 12 | **Evolution API** | Não-oficial (Baileys-based) | Brasil (community open-source) | `evolution-api.com` | **DRIVER ATIVO** — EvolutionDriver Sprint 2 (self-host CT 100, ban risk monitorado) |
| ⚠️ | whatsapp-web.js | Não-oficial (lib JS pura) | community | `wwebjs.dev` | Não implementado (sobreposição com Evolution; sem suporte comercial) |
| ⚠️ | Baileys (puro) | Não-oficial (lib JS pura) | community | `github.com/WhiskeySockets/Baileys` | Não implementado direto (raiz de Evolution/Z-API; lib JS, requereria daemon Node próprio) |

> **Observação importante (emenda ADR 0096 de 2026-05-07):** Z-API e Evolution
> são tratados como drivers oficiais do módulo (não Tier 0 PROIBIDO).
> Justificativa: mercado BR PME real usa massivamente; onboarding 5 min vs
> 1-3 dias Meta. Risco ban Meta documentado e mitigado via
> `WhatsappDriverHealthCheck` + fallback automático pro `MetaCloudDriver`
> (Sprint 2). Princípio duro #8 da Constituição (Confiabilidade com fallback)
> atendido via redundância de drivers, não evitando o risco.

## 2. Capacidades baseline do mercado (P0/P1/P2/P3)

> **P0** = obrigatório pra paridade de mercado; **P1** = competitivo; **P2** = diferencial; **P3** = futuro

### Capacidades P0 (obrigatórias)

| ID | Capacidade | Meta Cloud | Twilio | Take Blip | Zenvia | Wati | oimpresso (alvo Sprint 1-3) |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| C-001 | Send template HSM (utility/marketing) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 1 |
| C-002 | Send freeform (janela 24h) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 2 |
| C-003 | Receive webhook + verificar assinatura | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 2 |
| C-004 | Status delivery (sent/delivered/read/failed) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 1 |
| C-005 | Inbox UI (lista conversas + chat) | ❌ DIY | ✅ Studio | ✅ Blip Desk | ✅ | ✅ | ✅ Sprint 2 |
| C-006 | Templates manager (sync HSM aprovados) | ✅ API | ✅ Console | ✅ Studio | ✅ | ✅ | ✅ Sprint 2 |
| C-007 | Multi-número / multi-tenant | ✅ (dev) | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 1 (Tier 0) |
| C-008 | HMAC signature webhook | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ Sprint 2 |

### Capacidades P1 (competitivo)

| ID | Capacidade | Meta Cloud | Twilio | Take Blip | Zenvia | Wati | oimpresso (alvo) |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| C-101 | Bot conversacional integrado | ❌ | ⚠️ Studio | ✅ Blip AI | ⚠️ | ✅ | ✅ Sprint 3 (Jana/Copiloto) |
| C-102 | HITL handoff bot↔humano | ❌ | ⚠️ | ✅ | ⚠️ | ✅ | ✅ Sprint 3 (PolicyEngine ADS) |
| C-103 | Mídia outbound (img/PDF/audio) | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 Sprint 2.5 (boleto+NFe) |
| C-104 | Mídia inbound (cliente envia) | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 Backlog (US-WA-033) |
| C-105 | Atribuição conversa a atendente | ❌ | ⚠️ | ✅ | ✅ | ✅ | ✅ Sprint 2 |
| C-106 | Tags / labels conversa | ❌ | ⚠️ | ✅ | ✅ | ✅ | 🟡 Backlog |
| C-107 | Métricas custo/deflection | ❌ DIY | ⚠️ | ✅ | ✅ | ✅ | ✅ Sprint 3 |
| C-108 | Quick replies / atalhos | ❌ | ⚠️ | ✅ | ✅ | ✅ | 🟡 Backlog |

### Capacidades P2 (diferencial)

| ID | Capacidade | Meta Cloud | Twilio | Take Blip | Zenvia | Wati | oimpresso (alvo) |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| C-201 | Botões interativos (CTA) | ✅ API | ✅ | ✅ | ✅ | ✅ | 🟡 Backlog (US-WA-030) |
| C-202 | List messages (cardápio) | ✅ API | ✅ | ✅ | ✅ | ✅ | 🟡 Backlog (US-WA-031) |
| C-203 | Catalog / commerce nativo | ✅ API | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ Fora escopo |
| C-204 | Pix Copia-e-Cola via Whatsapp | ❌ DIY | ❌ | ⚠️ parceiros | ⚠️ | ❌ | 🟡 Backlog (US-WA-038) |
| C-205 | NFe/boleto anexo (compliance BR) | ❌ DIY | ❌ | ✅ parceiros | ⚠️ | ❌ | ✅ **DIFERENCIAL** Sprint 2 |
| C-206 | Integração ERP nativa (transactional) | ❌ DIY | ❌ | ❌ | ❌ | ❌ | ✅ **DIFERENCIAL ÚNICO** Sprint 1-3 |
| C-207 | Multi-canal (SMS + Email + Whatsapp) | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ Fora escopo (Whatsapp-first) |
| C-208 | Click-to-Whatsapp Ads (CTWA) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ Fora escopo (não fazemos ads) |
| C-209 | A/B testing templates | ❌ | ⚠️ | ✅ | ⚠️ | ✅ | 🟡 Backlog |
| C-210 | Customer 360 (perfil unificado) | ❌ | ⚠️ | ✅ | ✅ | ✅ | ✅ via Contact UltimatePOS Sprint 1 |

### Capacidades P3 (futuro)

| ID | Capacidade | Meta Cloud | Take Blip | Wati | oimpresso (alvo) |
|---|---|:-:|:-:|:-:|:-:|
| C-301 | Voice (chamadas Whatsapp) | ⚠️ beta | ❌ | ❌ | ❌ Fora escopo |
| C-302 | Whatsapp Pay BR | ⚠️ beta | ⚠️ | ❌ | ❌ Fora escopo (Pix Automático cobre) |
| C-303 | IA generativa próprias (LLM-built bot) | ❌ | ✅ Blip GPT | ⚠️ | ✅ via Jana (S3) |
| C-304 | Voice transcription inbound | ❌ | ⚠️ | ❌ | 🟡 Backlog (whisper.cpp local CT 100) |

## 3. Pricing comparativo (BR, ROTA LIVRE perfil ~150 conv/mês)

| Provedor | Custo fixo/mês | Custo conversa | Total ROTA LIVRE | Onboarding | Risco ban Meta |
|---|---|---|---|---|---|
| **Meta Cloud API direto** ✅ | R$ [redacted Tier 0] | Free 1k/mês utility; após R$ [redacted Tier 0] utility / R$ [redacted Tier 0] marketing | **R$ [redacted Tier 0]** | 1-3 dias (verificação Meta) | nenhum |
| **Z-API** ✅ | R$ [redacted Tier 0]-299 | freeform incluído | **R$ [redacted Tier 0]** | 5 min (scan QR) | médio (mitigado fallback) |
| **Evolution API self-host** ✅ | R$ [redacted Tier 0] (CT 100 Wagner) | freeform incluído | **R$ [redacted Tier 0]** | 30 min (Docker compose CT 100 + scan QR) | médio (mitigado fallback) |
| Twilio | $0 (pay-go) | $0,005/msg + Meta fee + ~30% markup | ~R$ [redacted Tier 0] | 1-3 dias | nenhum |
| Take Blip | R$ [redacted Tier 0] | + R$ [redacted Tier 0]/msg utility | R$ [redacted Tier 0] | 1-3 dias | nenhum |
| Zenvia | R$ [redacted Tier 0] | + R$ [redacted Tier 0]/msg utility | R$ [redacted Tier 0] | 1-3 dias | nenhum |
| 360dialog | EUR 49 (~R$ [redacted Tier 0]) | + Meta fee bruto | R$ [redacted Tier 0] | 1-3 dias | nenhum |
| Wati | $39 (~R$ [redacted Tier 0]) | + Meta fee bruto | R$ [redacted Tier 0] | 1-3 dias | nenhum |
| Gupshup | $20 (~R$ [redacted Tier 0]) | + Meta fee bruto | R$ [redacted Tier 0] | 1-3 dias | nenhum |

**Conclusão pricing:**

- **Custo absoluto vencedor:** Meta Cloud direto (R$ [redacted Tier 0]) ou Evolution self-host (R$ [redacted Tier 0]).
- **Onboarding rápido vencedor:** Z-API (5 min) — Meta Cloud em 1-3 dias é gargalo de demo.
- **Compliance vencedor:** Meta Cloud (oficial Meta).
- **Estratégia oimpresso:** oferecer **Z-API como "demo rápida" + Meta Cloud como "produção formal"**. Business escolhe — pode usar Z-API enquanto Meta Cloud é aprovado, depois migrar (ou ficar no Z-API se não pediu compliance enterprise).

## 4. Z-API e Evolution API — drivers ativos com risco aceito (emenda ADR 0096)

A "verdade prática" do mercado brasileiro de PME é Whatsapp via lib não-oficial. Wagner aceitou em 2026-05-07 implementar drivers pra esse mundo, com risco documentado:

- **Z-API** (~R$ [redacted Tier 0]-299/mês) — wrapper Whatsapp Web, SaaS BR com chat suporte em português
- **Evolution API** (~R$ [redacted Tier 0] self-host CT 100) — open-source Brazilian (fork de Baileys), Docker compose-managed
- Chatpro / API-Whatsapp / 50+ outros — não implementados (sobreposição funcional + sem critério forte de adoção; abrir nova ADR se algum cliente trouxer caso de uso)

### Riscos reais (não eliminados, mitigados)

1. **Violam Meta TOS** — Whatsapp Web não foi concebido pra automação de business. Meta tem detection ativa.
2. **Ban arbitrário** — número some sem aviso. Mitigação: `WhatsappDriverHealthCheck` + fallback Meta Cloud automático.
3. **Compliance LGPD parcial** — Z-API tem contrato BR (cobre parte); Evolution open-source é responsabilidade do business. Mitigação: business assina termo ciente.
4. **Sessão Whatsapp Web cai** — Z-API notifica via webhook + UI. Evolution depende de monitor próprio CT 100.
5. **Suporte limitado** — Z-API tem chat (em português, BR). Evolution só comunidade. Sem SLA enterprise.

### Razões pra incluir mesmo assim (emenda Wagner)

1. **Onboarding 100× mais rápido** (5 min vs 1-3 dias Meta) — fundamental pra demo comercial e PME que decide na hora.
2. **Mercado BR PME já está nesse mundo** — empresas que migrarem pro oimpresso muitas vezes têm número Z-API há 2+ anos. Forçar Meta = perder demanda.
3. **Sem janela 24h restritiva** — manda freeform a qualquer hora, sem HSM. Pra dunning/cobrança simples destrava uso.
4. **Custo previsível** — R$ [redacted Tier 0]/mês Z-API ou zero (Evolution self-host). Meta tem custo variável que assusta PME.

### Política Tier 0 oimpresso (atualizada)

| Provedor | Status anterior (proposta ADR 0096) | Status atual (emenda 2026-05-07) |
|---|---|---|
| Meta Cloud API | ✅ ATIVO default | ✅ ATIVO default |
| Z-API | ❌ Tier 0 PROIBIDO | ✅ ATIVO (driver alternativo, risco aceito monitorado) |
| Evolution API | ❌ Tier 0 PROIBIDO | ✅ ATIVO Sprint 2 (self-host CT 100, risco aceito) |
| whatsapp-web.js | ❌ Tier 0 PROIBIDO | ⚠️ Não implementado (sobreposição com Evolution) |
| Baileys puro | ❌ Tier 0 PROIBIDO | ⚠️ Não implementado direto (lib JS, requereria daemon Node) |

**O que continua proibido:** subir Whatsapp via container ou daemon **no Hostinger** (continua valendo ADR 0062 — Hostinger ≠ CT 100). Evolution API só roda no CT 100. Z-API é SaaS, sem daemon nosso.

## 5. Capacidades baseline → Score atual oimpresso

| Score | Capacidades | % cobertura mercado |
|---|---|---|
| **P0 cobertas** | 8/8 ✅ (todas Sprint 1-2) | 100% |
| **P1 cobertas** | 5/8 (3 backlog: tags, quick replies, mídia inbound) | 62% |
| **P2 cobertas** | 2/10 (sprint 1-3) + 2 diferenciais únicos = **4 P2 alvo de 10** | 40% |
| **P3** | 1 alvo Sprint 3 (Jana bot) de 4 | 25% |

**Score total ponderado** (P0=4, P1=2, P2=1, P3=0.5):
- Mercado top (Take Blip): 8×4 + 8×2 + 10×1 + 2×0.5 = **59**
- oimpresso Sprint 1-3 alvo: 8×4 + 5×2 + 4×1 + 1×0.5 = **46.5** (78% do top, no nosso perfil é suficiente)

## 6. Diferenciais únicos do oimpresso (não-replicáveis pelos BSPs)

1. **Integração nativa ERP transacional** (C-206) — nenhum BSP envia status OS, NFe paga, boleto vencido amarrados ao ledger Financeiro. Take Blip integra como "API client", não nativo.
2. **NFe/boleto anexo automático** (C-205) — RecurringBilling US-RB-044 fecha o loop pago→Whatsapp→NFe sem intervenção humana.
3. **Multi-tenant `business_id` Tier 0** — BSPs assumem 1 tenant por conta. Multi-tenant nativo é diferencial pra revenda Officeimpresso.
4. **Bot conversacional ancorado em Jana/Copiloto** com `ContextoNegocio` (3 ângulos faturamento — ADR 0052) — bot que sabe o que cliente comprou, quanto deve, status OS.

## 7. Próximo passo (skill `comparativo-do-modulo`)

Cruzar esta CAPTERRA-FICHA.md com [SPEC.md](SPEC.md) (US-WA-001…NNN) → gerar **CAPTERRA-INVENTARIO.md** com 3 buckets:

- ✅ **APROVADO** — entregue na SPEC Sprint 1-3
- 🟡 **PARCIAL** — backlog futuro (US-WA-030+)
- ❌ **AUSENTE** — fora escopo deliberado (CTWA, Voice, Whatsapp Pay)

Wagner aprova → batch `tasks-create` MCP pros gaps P0 não cobertos (se houver).
