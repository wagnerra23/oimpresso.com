---
date: 2026-06-18
topic: "Estado da arte — Channel/Session reliability + observability + realtime UX (WhatsApp não-oficial)"
type: session
---

# Estado da arte — Channel/Session reliability + observability + realtime UX (WhatsApp não-oficial)

**Data:** 2026-06-18 · **Agent:** estado-da-arte · **Escopo:** confiabilidade + observabilidade + realtime de canal whatsmeow/WuzAPI no oimpresso (CT 100), comparado com os melhores do mercado.

**Não-objetivo:** repesquisar POC WAHA-GOWS (já em `2026-06-18-como-integrar-whatsapp-loggedout-faseA.md`). Construímos em cima da decisão **EVOLUIR o WuzAPI** + Fases A/B já feitas (US-WA-308 detecção app-side, ADR 0286 corroboração por inbound, evento `LoggedOut` nativo PRs #2994/#2997).

---

## Fase 1 — Pesquisa (os melhores)

| Player | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **WAHA** (devlikeapro) | Evento `session.status` (STOPPED/STARTING/SCAN_QR_CODE/WORKING/FAILED) com array `statuses` das 3 últimas transições + timestamp. Retry policy declarativa (`constant/linear/exponential`, jitter 20%, attempts). HMAC-SHA512 do body + `X-Webhook-Request-Id` + `X-Webhook-Timestamp`. **Dashboard Event Monitor** mostra webhooks em tempo real. | Engine GOWS = mesmo whatsmeow do oimpresso. É o "o que poderíamos ter virado" — o gold standard direto do nosso path. |
| **Evolution API** | Evolution Manager (UI gráfica) com connection status + monitor de webhook. Webhook signature HMAC-SHA256; manda idempotência via request-id; retry exponencial até 7 dias (espelha Cloud API). | Maior comunidade BR open-source de WhatsApp não-oficial; referência de UX de "instance manager". |
| **Baileys** (WhiskeySockets) + middleware antiban | Auto-reconnect com backoff exponencial (init 2s, max 30s, fator 1.8, jitter 25%, max 12 retries) pra matar thundering-herd. Health-monitor detecta socket stale + 403/disconnect → fila de retry. Bug conhecido: stale-socket check agressivo gera status 440 (session conflict) — **lição: não reconectar cedo demais**. | Lib base de quase todo player não-oficial; backoff+jitter dela virou o de-facto. |
| **Twilio / 360dialog / Respond.io** (oficial Cloud API) | Webhook com assinatura, entrega com retry exponencial até 7 dias, status page pública por componente, SLA contratual. 360dialog/Respond.io: uptime por número + alerta de "channel disconnected". | Padrão-ouro de **confiabilidade de borda** + status page; é o hedge ban-zero. |
| **OneUptime / OTel SLO patterns** | 3 pilares de webhook reliability: success rate, retry count, delivery latency — como métricas OTel. SLI = "proporção de X sob limiar"; SLO = alvo sobre janela rolante (ex 99.9%/30d). Instrumentar pipeline de entrega+retry com spans. | Referência metodológica de SLO/SLI/OTel pra ciclo de sessão (não específico de WhatsApp, mas é o framework). |

**3 invariantes que emergiram:**
1. **Status é evento de primeira classe** (não derivado de "msg parou de chegar"). Todo player emite `session.status`/`connection.update` com transições + timestamp.
2. **Reconnect tem que ser tímido** — backoff+jitter, e o pior bug recorrente é reconectar agressivo demais (440/session-conflict). Detectar stale ≠ recriar sessão.
3. **Observabilidade = success-rate + latency + retry-count** como SLI, com alvo (SLO) e alerta. Uptime por canal é a métrica de topo.

---

## Fase 2 — Comparação com o oimpresso (6 pilares)

Validei no código real (`Modules/Whatsapp`, paths abaixo). Notas honestas.

### Pilar 1 — Realtime na tela (evento → UI < 1s) · **35%**
- **Backend bate o estado da arte:** `WhatsmeowWebhookController::publish` já publica `whatsmeow.paired/disconnected/ban_detected/qr_updated` no Centrifugo `whatsapp:business:{id}` (linhas 270-276). Há fallback polling 5s (defense-in-depth, US-WA-066).
- **Front rasga o pilar (gap a CONFIRMADO):** `CaixaUnificada/Index.tsx:138-139` filtra `ctx.data?.type` e **early-return** em tudo que não for `message.received/sent`. Os eventos de saúde chegam em `data.event` (`whatsmeow.*`) → **caem no chão**. Banner de canal é prop server-side → só muda no reload. Resultado: evento de borda existe no fio mas a tela só descobre no refresh (≫ 1s).
- Distância: **curta** (o caro — publicar no Centrifugo — já está feito; falta o front escutar `data.event`).

### Pilar 2 — Auto-cura (reconnect/backoff/circuit breaker) · **55%**
- **Bom:** `HealthProbeChannelsCommand` tem backoff exponencial `[1,5,30]s`, 3 retries, `STATES_NEED_RECOVERY`, não reconecta `banned` (escalation manual), `instance_not_found` recria idempotente. Reconciler é single-source-of-truth do estado daemon (ADR 0206) — evita o bug clássico "POST connect já conectado → 500".
- **Furos:**
  - **Gap b CONFIRMADO + grave:** o probe pinga `GET /instances/{id}/status` (path **Baileys**), mas o canal real é whatsmeow → reconcile via `WhatsmeowReconciler::reconcile` lê `/session/status` (`Connected`/`LoggedIn`). O probe e o reconciler falam com APIs diferentes. Pior: quando o Reconciler vê `connected && !loggedIn` e o canal nunca foi `active`, cai em `QR_PENDING`; sem token cai em `PROVISION_PENDING` → nenhum desses marca `channel_health=disconnected`. **Queda real (`connected=False`) fica invisível** — exatamente a Jana que vc achou viva mas a UI dizia "ativo".
  - Sem circuit breaker (consecutive_failures conta, mas não abre circuito / não troca pro hedge oficial).
  - Auto re-pair: só marca `LOGGED_OUT` "re-conecte com QR" — humano tem que escanear. Aceitável (limite do protocolo), mas sem notificação proativa hoje.
- Distância: **média** (lógica existe, mas o probe está cego pro driver certo).

### Pilar 3 — Observabilidade com SLO + alerta · **20%**
- **Tem:** `WhatsappObservabilityHealthCommand` (phones, msgs 24h, taxa de falha >10% por business). `OtelHelper` existe (mas magro — 4 funções). Logs estruturados Pino-compat com `event`+`business_id`+`channel_id` (bom pra grep).
- **Não tem (gaps c+d CONFIRMADOS):** zero uptime% por canal, zero time-to-detect, zero webhook-delivery-rate como SLI, zero span OTel do **ciclo de sessão** (paired→disconnected→recovered), zero dashboard de saúde de canal, **zero alerta de canal-down > N min**. `jana:health-check` (5 checks SQL) **não cobre** whatsapp channel health — confirmei: nenhum check de canal lá.
- Distância: **longa** (é o pilar mais atrás vs WAHA/Evolution/OneUptime).

### Pilar 4 — Confiabilidade de borda · **65%**
- **Forte:** `VerifyWhatsmeowSignature` faz HMAC-SHA256 timing-safe (`hash_equals`) + fallback Token per-channel, **fail-closed** (removeu IP-allowlist spoofável via XFF em 2026-06-14 — maduro). Multi-tenant Tier 0 respeitado (business_uuid no path, scope bypass justificado pré-auth).
- **Furos:** webhook whatsmeow **não tem nonce/request-id dedup** (o Baileys tem — `ChannelBaileysWebhookIdempotencyTest`). Idempotência inbound existe só via DB UNIQUE (`conv_biz_ch_ext_uniq` + `firstOrCreate`) — protege duplicata de mensagem, **não** replay de webhook de estado. Sem backpressure/retry-queue explícita no receiver (job assíncrono ajuda, mas sem DLQ visível pra eventos de estado).
- Distância: **curta** (HMAC já no nível dos melhores; falta paridade de nonce com o Baileys).

### Pilar 5 — Hedge oficial (Cloud API ban-zero) · **60%**
- **Existe de verdade:** `MetaCloudDriver` 705 linhas, implementado (sendTemplate/Freeform/Media/Interactive) — ADR 0096 + emenda 0111 (bypass/fallback per-business). Onboarding embedded-signup documentado.
- **Furo:** é hedge de **envio**, não há failover automático de canal (quando whatsmeow cai/bane, nada promove o tenant crítico pro Cloud API). É manual/por-config, não acionado por sinal de saúde. US-WA-310 (ban-zero pra tenant crítico) ainda não fecha o loop saúde→failover.
- Distância: **média** (capacidade existe, automação do hedge não).

### Pilar 6 — Gates que mordem (E2E ciclo + canário) · **30%**
- **Tem:** muito teste de feature (webhook auth, isolation, broadcast filter, idempotency Baileys, reconciler). `HealthProbeChannelsCommandTest` existe.
- **Não tem:** E2E do **ciclo completo** parear→msg→logout→recuperar (smoke real contra daemon). Sem canário de saúde de canal. `keyword 'logged_out'` (underscore) **não casa** o reason real `"logged out from another device"` (gap e CONFIRMADO, `WhatsmeowWebhookController:209`) — bug latente que nenhum gate pega porque não há teste do reason real.
- Distância: **média**.

### Nota geral de maturidade: **44%**
Média ponderada (realtime e observabilidade pesam mais por impacto no cliente): backend de eventos é forte, **a ponta (front + probe certo + SLO/alerta) é o que falta**.

---

## Fase 3 — Top 10 gaps priorizados (impacto × esforço)

Esforço em IA-pair (ADR 0106: 10x humano + margem 2x). "Pré-req?" = depende de outro gap.

| # | Gap | Pilar | Impacto | Esforço IA-pair | Pré-req? | ADR? |
|---|---|---|---|---|---|---|
| 1 | **Probe whatsmeow cego** (b): probe pinga path Baileys, não marca `connected=False`. Queda invisível. | 2 | **alto** (P1 — cliente perde msg sem ninguém saber) | ~60 min | não | não (corrige dentro de ADR 0206) |
| 2 | **Front não consome `data.event`** (a): banner não reage a `whatsmeow.disconnected/ban_detected/paired`. | 1 | **alto** (UX + confiança; cliente já cancelou por msg perdida) | ~40 min | não | não |
| 3 | **Sem alerta canal-down > N min** (d) | 3 | **alto** (time-to-detect = ∞ hoje) | ~45 min | dep #1 (precisa do health correto) | não (estende `jana:health-check`) |
| 4 | **banKeyword `logged_out` não casa reason real** (e) | 6 | **médio-alto** (ban/logout vira `disconnected` genérico → não escala) | ~10 min | não | não |
| 5 | **Sem uptime% / time-to-detect por canal** (c) | 3 | **médio-alto** (SLI base; sem isso não há SLO) | ~90 min | dep #1 | **sim** — ADR de SLO/SLI de canal |
| 6 | **Webhook whatsmeow sem nonce/replay dedup** | 4 | **médio** (paridade Baileys; replay de estado pode reverter health) | ~40 min | não | não (porta padrão Baileys) |
| 7 | **Sem E2E ciclo parear→logout→recuperar** | 6 | **médio** (gate que morde o #1,#2,#4) | ~90 min | dep #1,#2,#4 | não |
| 8 | **Span OTel do ciclo de sessão** (paired→disc→recovered) | 3 | **médio** (debug + base de SLO via traces) | ~60 min | dep #5 | dep ADR #5 |
| 9 | **Hedge oficial não automatiza failover por saúde** | 5 | **médio** (ban-zero só pra tenant crítico) | ~3-4 h | dep #1,#3 | **sim** — emenda ADR 0096/0111 (failover por sinal) |
| 10 | **Sem circuit breaker / dashboard de saúde de canal** | 2/3 | **baixo-médio** (nice-to-have após #3,#5) | ~3 h | dep #5 | dep ADR #5 |

---

## Roadmap — CONSOLIDAR vs EVOLUIR

**CONSOLIDAR (fechar o que já está 80% pronto — alto impacto, baixo esforço, sem ADR):**
- #1 probe whatsmeow correto → marca `connected=False`. **Desbloqueia 3,5,7.**
- #2 front escuta `data.event` → banner reativo < 1s.
- #4 banKeyword reason real (10 min, trivial, latente).
- #6 nonce no webhook whatsmeow (paridade Baileys).
Estes 4 sobem a nota de **44% → ~62%** sem nenhum ADR novo, fechando os 5 gaps (a-e) que vc já achou.

**EVOLUIR (capacidade nova — merece ADR + planejamento):**
- #5 SLO/SLI de canal (uptime%, time-to-detect) → **ADR próprio**.
- #3 alerta canal-down (extensão `jana:health-check`, depende de #1).
- #8 OTel ciclo de sessão (depende #5).
- #9 failover automático pro Cloud API por sinal de saúde → **emenda ADR 0096/0111**.
- #7 E2E smoke real do ciclo (gate que morde).
- #10 circuit breaker + dashboard.

**Gaps que merecem ADR próprio:** #5 (SLO/SLI de canal — define os alvos, é decisão arquitetural) e #9 (failover saúde→Cloud API — muda quem decide o driver, toca Tier 0 multi-tenant + custo). #3 e #10 viram seções dessas ADRs, não ADR isolada.

---

## Recomendação concreta

**Comece por #1 — probe whatsmeow correto.** Alto-impacto-baixo-esforço (~60 min IA-pair), **sem pré-req bloqueante**, e é o desbloqueador de #3, #5 e #7. Hoje a sua queda real (`connected=False/loggedIn=False` no daemon) fica **invisível na UI** — esse é o P1 de confiabilidade. Faça #1+#4 (10 min) no mesmo PR (ambos tocam o caminho de detecção de saúde whatsmeow, ≤300 linhas, 1 intent: "probe e classifica saúde whatsmeow corretamente").

**Próxima ação hoje:** em `HealthProbeChannelsCommand`, fazer o caminho whatsmeow usar `WhatsmeowReconciler::reconcile()` (que já lê `/session/status`→`Connected/LoggedIn`) em vez de `GET /instances/{id}/status`, e mapear `LOGGED_OUT`/`!connected` → `channel_health='disconnected'` + `consecutive_failures++`. No mesmo PR, trocar `'logged_out'` por `'logged out'` (espaço) na `$banKeywords` do `WhatsmeowWebhookController:209` pra casar `"logged out from another device"`. Escrever 1 teste vermelho-primeiro que injeta `connected=False` e exige `channel_health='disconnected'`.

---

### Apêndice — paths load-bearing
- `Modules/Whatsapp/Console/Commands/HealthProbeChannelsCommand.php` (probe — gap b; pinga `/instances/{id}/status` path Baileys, `STATES_NEED_RECOVERY` não inclui caminho whatsmeow real)
- `Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php` (`publish` L270-276 OK; `$banKeywords` L209 com `'logged_out'` — gap e)
- `Modules/Whatsapp/Services/WhatsmeowReconciler.php` (`reconcile` L52-113 lê `/session/status`; `connected && !loggedIn` cai em QR_PENDING/LOGGED_OUT — não seta disconnected via probe)
- `resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx:138-139` (filtra `data.type`, dropa `data.event` — gap a)
- `Modules/Whatsapp/Http/Middleware/VerifyWhatsmeowSignature.php` (HMAC fail-closed — pilar 4 forte; sem nonce)
- `Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php` (705 linhas, hedge implementado — pilar 5)
- `Modules/Jana` (jana:health-check NÃO cobre channel uptime — gaps c/d)
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
