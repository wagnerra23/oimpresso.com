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
| ❌ | ~~Evolution API~~ | **NÃO-OFICIAL** | Brasil (community) | `evolution-api.com` | **PROIBIDO Tier 0** (viola Meta TOS) |
| ❌ | ~~Z-API~~ | **NÃO-OFICIAL** | Brasil | `z-api.io` | **PROIBIDO Tier 0** (Whatsapp Web reverse) |
| ❌ | ~~whatsapp-web.js~~ | **NÃO-OFICIAL** | community | `wwebjs.dev` | **PROIBIDO Tier 0** (reverse-engineered) |
| ❌ | ~~Baileys~~ | **NÃO-OFICIAL** | community | `github.com/WhiskeySockets/Baileys` | **PROIBIDO Tier 0** (raiz de Evolution/Z-API) |

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

| Provedor | Custo fixo/mês | Custo conversa | Total ROTA LIVRE | Vs Meta direto |
|---|---|---|---|---|
| **Meta Cloud API direto** | R$ 0 | Free 1k/mês utility; após R$ 0,07 utility / R$ 0,30 marketing | **R$ 0** | baseline |
| Twilio | $0 (pay-go) | $0,005/msg + Meta fee + ~30% markup | ~R$ 50 | 50× mais caro |
| Take Blip | R$ 1.500 | + R$ 0,12/msg utility | **R$ 1.518** | 1.518× mais caro |
| Zenvia | R$ 500 | + R$ 0,10/msg utility | R$ 515 | 515× mais caro |
| 360dialog | EUR 49 (~R$ 270) | + Meta fee bruto | R$ 280 | 280× mais caro |
| Wati | $39 (~R$ 200) | + Meta fee bruto | R$ 210 | 210× mais caro |
| Gupshup | $20 (~R$ 110) | + Meta fee bruto | R$ 120 | 120× mais caro |

**Conclusão pricing:** Meta direto é absurdamente mais barato no nosso perfil. BSP só compensaria se entregassem capacidade que não temos como replicar (UI Inbox + métricas + bot). **Como temos Cockpit pattern + Jana + Centrifugo já no stack, replicamos a UI a custo de dev e ficamos com Meta direto.**

## 4. Por que NÃO o BR padrão de mercado de fato (Z-API / Evolution API)

A "verdade prática" do mercado brasileiro de PME é Whatsapp via lib não-oficial:

- **Z-API** (~R$ 99/mês) — wrapper Whatsapp Web, marketing como "API simples"
- **Evolution API** (~R$ 0 self-host) — open-source Brazilian (BR fork de Baileys), popular em integradores
- **Chatpro / API-Whatsapp / 50+ outros** — todos rodam em cima de Baileys

**Por que estão proibidos no oimpresso (Tier 0):**

1. **Violam Meta TOS** — Whatsapp Web não foi concebido pra automação de business. Meta tem detection ativa.
2. **Ban arbitrário** — número some sem aviso. Já vi 3 empresas perdendo número de cobrança principal.
3. **Compliance LGPD/MEI** — sem CONTRATO formal com Meta, business não consegue alegar conformidade.
4. **Não escala** — sessão Whatsapp Web cai em horário de pico (qrcode re-scan).
5. **Sem suporte** — quando quebra, comunidade open-source ou ban.

**Decisão:** ADR 0096 marca não-oficiais como **Tier 0 PROIBIDO** alongado com proibição de `octane` no Hostinger. Princípio duro 8 da Constituição (ADR 0094): "Confiabilidade com fallback" — não-oficial não tem fallback.

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
