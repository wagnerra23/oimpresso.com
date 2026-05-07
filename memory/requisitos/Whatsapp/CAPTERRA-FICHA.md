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
| 11 | **Z-API** | Não-oficial (Baileys-based) | Brasil | `z-api.io` | **DRIVER DEFAULT** — ZapiDriver Sprint 1 (mercado BR PME, ban risk MUITO ALTO mitigado por fallback obrigatório) |
| ❌ | ~~Evolution API~~ | Não-oficial (Baileys-based) | Brasil (community open-source) | `evolution-api.com` | **PROIBIDO Tier 0** — self-host CT 100 = oimpresso direto na linha de fogo, sem terceiro pra responsabilizar |
| ❌ | ~~whatsapp-web.js~~ | Não-oficial (lib JS pura) | community | `wwebjs.dev` | **PROIBIDO Tier 0** (mesmo motivo Evolution + lib JS exige daemon Node próprio) |
| ❌ | ~~Baileys (puro)~~ | Não-oficial (lib JS pura) | community | `github.com/WhiskeySockets/Baileys` | **PROIBIDO Tier 0** (raiz de Evolution/Z-API; lib JS, requereria daemon Node próprio) |

> **Decisão final ADR 0096 (emenda 3, 2026-05-07):**
>
> - **Z-API = DRIVER DEFAULT** — mercado BR PME real, onboarding 5 min, freeform sem janela 24h. Risco ban MUITO ALTO mitigado por: fallback Meta Cloud OBRIGATÓRIO (gating FormRequest) + termo LGPD assinado + `WhatsappDriverHealthCheck` (6h em 6h) + fallback automático Z-API → Meta Cloud.
> - **Meta Cloud = fallback obrigatório** (e default opcional pra enterprise compliance). Free 1k conv/mês. Sem risco ban.
> - **Evolution / wwebjs / Baileys puro = PROIBIDOS Tier 0** — self-host = oimpresso responsável direto. Sem terceiro responsabilizável. Stakes operacionais altos demais pro ganho marginal.
>
> Razão da assimetria Z-API permitido / Evolution proibido: **terceirização do risco**. Z-API SaaS responde pelo ban; Evolution self-host CT 100 = oimpresso/Wagner responde direto. Reabrir Evolution só via nova ADR explícita.

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
| **Z-API** ✅ DEFAULT | R$ 99-299 | freeform incluído | **R$ 99** | 5 min (scan QR) | **muito alto** (mitigado fallback obrigatório) |
| **Meta Cloud direto** ✅ FALLBACK | R$ 0 | Free 1k/mês utility; após R$ 0,07 utility / R$ 0,30 marketing | **R$ 0** | 1-3 dias (verificação Meta) | nenhum |
| ~~Evolution API~~ ❌ PROIBIDO | — | — | — | — | (não considerado) |
| Twilio | $0 (pay-go) | $0,005/msg + Meta fee + ~30% markup | ~R$ 50 | 1-3 dias | nenhum |
| Take Blip | R$ 1.500 | + R$ 0,12/msg utility | R$ 1.518 | 1-3 dias | nenhum |
| Zenvia | R$ 500 | + R$ 0,10/msg utility | R$ 515 | 1-3 dias | nenhum |
| 360dialog | EUR 49 (~R$ 270) | + Meta fee bruto | R$ 280 | 1-3 dias | nenhum |
| Wati | $39 (~R$ 200) | + Meta fee bruto | R$ 210 | 1-3 dias | nenhum |
| Gupshup | $20 (~R$ 110) | + Meta fee bruto | R$ 120 | 1-3 dias | nenhum |

**Custo total oimpresso (perfil ROTA LIVRE com fallback ativo):** **R$ 99/mês** Z-API + R$ 0 Meta Cloud (free tier dormente cobre fallback).

**Conclusão pricing:**

- **Onboarding rápido vencedor:** Z-API (5 min) — destrava demo PME.
- **Custo absoluto vencedor pra fallback:** Meta Cloud (R$ 0 free tier).
- **Compliance vencedor:** Meta Cloud (oficial Meta).
- **Estratégia oimpresso:** **Z-API ativa hoje (5 min) + Meta Cloud aprovando em paralelo (1-3 dias) como rede de segurança** — wizard 2 passos obrigatórios na UI Settings. Em caso de ban Z-API, sistema troca pra Meta Cloud automaticamente sem intervenção humana.

## 4. Z-API como driver default — risco aceito conscientemente (emenda 3 ADR 0096)

A "verdade prática" do mercado brasileiro de PME é Whatsapp via lib não-oficial. Wagner aceitou em 2026-05-07 (emenda 3) **promover Z-API a driver default**, com Meta Cloud como rede de segurança obrigatória.

### Riscos reais (não eliminados, mitigados duramente)

1. **Violam Meta TOS** — Whatsapp Web não foi concebido pra automação de business. Meta tem detection ativa.
2. **Ban arbitrário** — número some sem aviso. Mitigação: `WhatsappDriverHealthCheck` (6h em 6h) + fallback automático Z-API → Meta Cloud (gating duro: não dá pra ativar Z-API sem Meta cadastrado).
3. **Compliance LGPD parcial** — Z-API tem contrato BR (cobre parte). Mitigação: business assina termo ciente (`lgpd_acknowledged_at`); pode flipar pra `driver=meta_cloud` em qualquer momento na UI Settings.
4. **Sessão Whatsapp Web cai** — Z-API notifica via webhook `on-disconnected` + UI alerta + fallback Meta Cloud entra em ação se cair > 5min.
5. **Suporte limitado** — Z-API tem chat em português, BR. Quando lib Baileys quebra, depende do time deles patchear (~1-3 dias).

### Razões pra promover Z-API a default (emenda 3 Wagner)

1. **Onboarding 100× mais rápido** (5 min vs 1-3 dias Meta) — padrão tem que estar pronto na hora pra fluxo comercial PME.
2. **Mercado BR PME já está nesse mundo** — empresas que migrarem pro oimpresso muitas vezes têm número Z-API há 2+ anos. Forçar Meta como default = atritar onboarding.
3. **Sem janela 24h restritiva** — manda freeform a qualquer hora, sem HSM. Pra dunning/cobrança simples destrava 80% do caso de uso.
4. **Custo cabível no Pro R$ 99/mês** — entra direto no plano sem comer margem.

### Por que Evolution NÃO pode ser default (PROIBIDO)

| Critério | Z-API SaaS | Evolution self-host CT 100 |
|---|---|---|
| Quem assume risco do ban | Empresa terceira (Z-API) | **oimpresso/Wagner direto** |
| Quem responde por LGPD na cadeia | Contrato Z-API (BR) | **oimpresso direto** |
| Quem patch quando Meta TOS muda | Time pago Z-API (~1-3d) | Comunidade open-source (~dias-semanas) |
| Recuperação após ban | Recriar instance Z-API | **Recuperar número pessoal** |
| Stakes operacionais | Limitados (R$ 99/mês perdidos) | **Reputação oimpresso direta com cliente final do tenant** |

Z-API SaaS terceiriza o risco. Evolution self-host concentra. Ganho marginal (R$ 99/mês economizados) não compensa.

### Política Tier 0 oimpresso (versão final 2026-05-07 emenda 3)

| Provedor | Status |
|---|---|
| **Z-API** | ✅ DRIVER DEFAULT (com fallback Meta Cloud obrigatório) |
| **Meta Cloud API** | ✅ Fallback obrigatório / driver alternativo enterprise |
| Evolution API | ❌ PROIBIDO Tier 0 |
| whatsapp-web.js | ❌ PROIBIDO Tier 0 |
| Baileys puro (lib JS) | ❌ PROIBIDO Tier 0 |
| Qualquer wrapper Whatsapp Web rodando em servidor oimpresso | ❌ PROIBIDO Tier 0 |

**O que continua proibido (compatível com ADR 0062):** subir Whatsapp via container ou daemon no Hostinger. Hostinger ≠ CT 100. Reabrir Evolution só via nova ADR explícita Wagner-aceita.

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
