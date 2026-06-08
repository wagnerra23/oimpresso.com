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
| 11 | **Z-API** | Não-oficial (Baileys-based) | Brasil | `z-api.io` | **DRIVER DEFAULT Sprint 1** — onboarding 5 min, ban risk MUITO ALTO mitigado por fallback obrigatório |
| 12 | **Baileys (lib raiz)** | Não-oficial (lib JS pura) | community | `github.com/WhiskeySockets/Baileys` | **DRIVER CUSTOM Sprint 3** — daemon Node próprio CT 100 (`BaileysDriver`); estrutura customizada de atendimento autorizada por Wagner emenda 4 (razões concretas: Evolution baniu, schema ruim, falta observabilidade) |
| ❌ | ~~Evolution API~~ | Não-oficial (Baileys-based) | Brasil (community open-source) | `evolution-api.com` | **PROIBIDO permanente** — Wagner viu números banidos em produção + schema não atende + falta observabilidade |
| ❌ | ~~whatsapp-web.js~~ | Não-oficial (lib JS pura) | community | `wwebjs.dev` | **PROIBIDO** (sobreposição funcional com BaileysDriver custom; sem suporte comercial; lib mais antiga) |

> **Decisão final ADR 0096 (após emendas 1-4 de 2026-05-07):**
>
> - **Z-API = DRIVER DEFAULT Sprint 1** — mercado BR PME real, onboarding 5 min. Risco ban MUITO ALTO mitigado por: fallback Meta Cloud OBRIGATÓRIO + termo LGPD + `WhatsappDriverHealthCheck` + fallback automático.
> - **Meta Cloud = fallback obrigatório Sprint 1** (e default opcional pra enterprise compliance). Free 1k conv/mês.
> - **`BaileysDriver` custom = Sprint 3** — daemon Node CT 100 próprio rodando lib `@whiskeysockets/baileys`. Schema/logs/métricas/health check sob nosso controle. Justificativa: Wagner sentiu na pele bans + schema ruim + falta de observabilidade do Evolution; quer construir estrutura customizada de atendimento.
> - **Evolution = PROIBIDO permanente** — bans em produção, schema não atende, observabilidade ruim.
> - **whatsapp-web.js = PROIBIDO** — sobreposição funcional com BaileysDriver custom.
>
> Razão da assimetria Baileys puro autorizado / Evolution proibido: nenhuma assimetria filosófica — Wagner já tentou Evolution e não funcionou pra ele (3 razões concretas). Construir BaileysDriver custom resolve as 3 dores (schema próprio, logs próprios, métricas próprias).

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

## 4. Z-API default Sprint 1 + BaileysDriver custom Sprint 3 — risco aceito conscientemente (emendas 3 e 4 ADR 0096)

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

### Por que Evolution PROIBIDO permanente (não Tier 0 abstrato — razões concretas Wagner)

| Critério | Evolution real (experiência Wagner) | BaileysDriver custom Sprint 3 |
|---|---|---|
| **Bans Meta** | **Acontecendo em produção** (motivo principal) | Mesmo risco, mas com observabilidade nossa pra detectar/agir rápido |
| **Schema de banco** | **Não atende** estrutura customizada de atendimento Wagner | Schema é nosso (`whatsapp_messages` append-only, `whatsapp_conversations`, etc) — desenhado pra nossa necessidade |
| **Observabilidade** | **Opaca** — Wagner sentiu na pele quando bans aconteceram | OTel traces ponta-a-ponta + métricas Prometheus + dashboard Grafana dedicado |
| **Suporte quando Baileys lib quebra** | Comunidade Evolution (~dias-semanas) | Mesmo problema (lib raiz é mesma); mas patch pode vir mais rápido se time interno fizer fork |
| **Quem mantém Wagner debugando 02h** | Wagner debugando código alheio (Evolution) | Wagner debugando código próprio (mais barato cognitivamente) |

Construir BaileysDriver custom resolve as 3 dores principais (schema, logs, métricas). Os riscos restantes (ban, manutenção lib) são iguais — mas com nossa estrutura debaixo.

**Decisão registrada como reversível:** se manutenção do daemon Node consumir > 4h/mês de Wagner OU bans cross-tenant ≥ 5/mês, reabrir ADR pra reavaliar (ARCHITECTURE.md §16.11).

### Política Tier 0 oimpresso (versão final 2026-05-07 emenda 4)

| Provedor | Status |
|---|---|
| **Z-API** | ✅ DRIVER DEFAULT Sprint 1 (com fallback Meta Cloud obrigatório) |
| **Meta Cloud API** | ✅ Fallback obrigatório / driver alternativo enterprise Sprint 1 |
| **Baileys (lib raiz, daemon Node próprio)** | ✅ DRIVER CUSTOM Sprint 3 — `BaileysDriver` |
| Evolution API | ❌ PROIBIDO permanente (bans Wagner + schema + observabilidade) |
| whatsapp-web.js | ❌ PROIBIDO (sobreposição com BaileysDriver custom) |
| Qualquer wrapper Whatsapp Web de terceiro rodando em servidor oimpresso | ❌ PROIBIDO (já que vamos construir BaileysDriver custom, não há razão pra rodar wrapper de terceiro) |

**O que continua proibido (compatível com ADR 0062):** subir Whatsapp via container ou daemon no **Hostinger**. Hostinger ≠ CT 100. Reabrir Evolution só via nova ADR explícita Wagner-aceita.

**Nota Sprint 3:** `BaileysDriver` custom roda **exclusivamente no CT 100** (container Docker compose-managed). Hostinger continua HTTP-only (PHP webhook receiver + UI Inertia + DB primary).

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

---

## UX heuristics (Capterra v2 — eixo Usabilidade)

> Capterra v2 ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 eixos): além de medir features, mede **como** o concorrente entrega — cliques, tempo, recuperação de erro.
> Curado em 2026-05-10 (US-WA-051) cruzando Take Blip / Wati / Z-API + estado funcional Inbox prod biz=1.

```yaml
ux_heuristics:
  - id: clicks-primeira-resposta
    nome: "Cliques pra responder o primeiro inbound da Inbox"
    score: P0
    benchmark: "Take Blip 2 cliques (lista → thread + textarea já focada). Wati 2 cliques. Z-API web 3 cliques (lista → abrir → clicar campo)."
    target: "<= 2 cliques (lista → thread com textarea autoFocus)"
    metrica: "ux_clicks_primeira_resposta"
    evidencia_atual: "resources/js/Pages/Whatsapp/Conversations/Show.tsx — falta autoFocus no textarea ao montar thread (verificar)"
  - id: tempo-render-inbox-100-conversas
    nome: "Tempo médio first-paint da Inbox com 100 conversas carregadas"
    score: P0
    benchmark: "Wati < 400ms (lazy-load). Take Blip < 600ms (paginado). Concorrentes legacy (Olos, Octadesk) > 1.2s."
    target: "< 500ms first-paint (paginação 50/page + lazy-load avatar/thumb)"
    metrica: "ux_inbox_first_paint_ms"
    evidencia_atual: "Inbox biz=1 com 32 conversas hoje OK; falta teste de carga sintética 100+ pra validar < 500ms"
  - id: recuperacao-qr-expirado
    nome: "Recuperação quando QR Z-API/Baileys expira (sessão Web cai)"
    score: P0
    benchmark: "Z-API painel próprio: 1 clique 'Gerar novo QR' + toast. Wati N/A (Cloud). Evolution: precisa restart container."
    target: "1 clique 'Regenerar QR' em Settings + toast amigável + auto-refresh 30s; banner topbar quando driver=degraded"
    metrica: "ux_qr_recovery_clicks"
    evidencia_atual: "WhatsappDriverHealthCheckJob detecta + flipa pra fallback Meta Cloud, mas UI Settings.tsx não tem botão 'Regenerar QR' explícito (verificar PR3 US-WA-040)"
  - id: switch-numero-multi-phone
    nome: "Trocar contexto do número ativo (multi-phone per-business)"
    score: P0
    benchmark: "Take Blip: dropdown topbar 1 clique. Wati: tab por número. Z-API: instância separada (re-login)."
    target: "Dropdown topbar Inbox 1 clique + persistir escolha em session + filtrar conversas/templates do número"
    metrica: "ux_switch_phone_clicks"
    evidencia_atual: "Schema whatsapp_business_phones migrated (PR1 US-WA-040), UI dropdown ainda pendente (PR3)"
  - id: feedback-erro-envio
    nome: "Feedback quando envio falha (driver down, número inválido, sem janela 24h)"
    score: P1
    benchmark: "Wati: ícone vermelho + tooltip motivo + botão 'Reenviar'. Take Blip: status no balão + retry automático 3x."
    target: "Balão com status 'failed' + tooltip motivo (driver/janela/número) + botão 'Reenviar' inline; retry automático Job 3x antes de marcar failed final"
    metrica: "ux_envio_falha_recovery_seconds"
    evidencia_atual: "MessageStatus.php tem failed status; UI Show.tsx renderiza ícone mas sem tooltip motivo nem botão reenviar"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz **sem humano**? Listener? Cron? Job? Webhook?
> Curado em 2026-05-10 (US-WA-051) — cruza com inventário CAPTERRA-INVENTARIO.md (back-compat com `NotifyRepairCustomer`, `WhatsappDriverHealthCheckJob`, `DispatchToJanaBot`, `BillingNotificationListener`).

```yaml
automation_targets:
  - id: auto-template-repair-status
    nome: "Auto-disparar template `repair_status_ready` quando OS muda pra status `pronto`/`aguardando_pecas`"
    score: P0
    benchmark: "Take Blip via Studio (configurar fluxo manual). Wati via Zapier (cliente paga extra). oimpresso nativo."
    target: "Listener Repair `JobSheet status changed` → SendWhatsappMessageJob, p95 < 30s"
    metrica: "auto_repair_notify_p95_seconds"
    evidencia_atual: "✅ JÁ IMPLEMENTADO — Modules/Whatsapp/Listeners/NotifyRepairCustomer.php + NotifyRepairCustomerTest.php (US-WA-004 done)"
  - id: auto-fallback-driver-degraded
    nome: "Auto-fallback driver Z-API → Meta Cloud quando health check detecta degraded (ban/disconnect)"
    score: P0
    benchmark: "Z-API: nenhum (mono-driver). Wati: nenhum. Twilio: requer config manual. oimpresso diferencial nativo (ADR 0096 emenda 4)."
    target: "Cron WhatsappDriverHealthCheckJob 6h/6h → flipa `driver_health=degraded` → DriverFactory resolve fallback automático sem intervenção; alerta Wagner via banner topbar + email"
    metrica: "auto_fallback_trigger_total / auto_fallback_recovery_seconds"
    evidencia_atual: "✅ JÁ IMPLEMENTADO — WhatsappDriverHealthCheckJob + WhatsappDriverHealthCheckJobTest.php + DriverFactory resolve por driver_health"
  - id: auto-anexo-boleto-nfe
    nome: "Auto-anexar boleto + NFe ao Whatsapp quando RecurringBilling cria fatura paga"
    score: P0
    benchmark: "BSPs: nenhum (não conhecem ledger Financeiro). Take Blip via parceiros Asaas (manual). oimpresso diferencial único (C-205)."
    target: "Listener `RecurringBilling::InvoicePaid` → AnexarBoletoNFe → SendWhatsappMessageJob com mídia, p95 < 60s"
    metrica: "auto_anexo_boleto_nfe_p95_seconds"
    evidencia_atual: "✅ JÁ IMPLEMENTADO parcial — Modules/RecurringBilling/Listeners/AnexarBoletoNFe.php + BillingNotificationListener (US-RB-044 v1); v2 com mídia outbound depende US-WA-NEW-MIDIA-OUT"
  - id: auto-tagging-keyword-inbound
    nome: "Auto-tagging conversa por keyword inbound (ex: 'orçamento'→tag:vendas, 'reclamação'→tag:suporte, 'segunda via'→tag:financeiro)"
    score: P0
    benchmark: "Wati: regras keyword no admin (cobre). Take Blip: via Blip AI (paga). Z-API: nenhum."
    target: "Listener `WhatsappInboundReceived` → TaggingService (regras keyword per-business config) → grava `whatsapp_conversation_tags`, p95 < 5s"
    metrica: "auto_tagging_inbound_p95_seconds"
    evidencia_atual: "❌ TODO — depende US-WA-NEW-TAGS (schema tags ausente) + WhatsappTaggingService a criar"
  - id: auto-handoff-bot-humano
    nome: "Auto-handoff bot Jana → humano quando confidence < threshold OU keyword 'falar com atendente'"
    score: P1
    benchmark: "Take Blip: via Blip AI nativa. Wati: regras estáticas. oimpresso via PolicyEngine ADS (4 outcomes)."
    target: "DispatchToJanaBot retorna outcome REQUIRE_HUMAN_REVIEW → conversation.status = `awaiting_human` + notifica atendente assigned, p95 < 10s"
    metrica: "auto_handoff_p95_seconds"
    evidencia_atual: "✅ JÁ IMPLEMENTADO — Modules/Whatsapp/Listeners/DispatchToJanaBot.php + DispatchToJanaBotTest.php (US-WA-020 done) integrado com PolicyEngine ADS"
```
