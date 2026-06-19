---
slug: 0287-probe-whatsmeow-provision-pending-ativo-e-queda
number: 287
title: "probe whatsmeow trata PROVISION_PENDING em canal que estava healthy como queda (fim da 'queda invisível') + logout remoto não é ban"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-18"
module: whatsapp
tags: [whatsapp, whatsmeow, health-probe, channel-health, incident, caixa-unificada, atendimento]
supersedes: []
superseded_by: []
related:
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0206-state-machine-whatsmeow-reconciliacao
  - 0096-modulo-whatsapp-meta-cloud-api-direto
---

# ADR 0287 — `PROVISION_PENDING` em canal ativo é queda (não provisionamento)

## Contexto

Durante a Fase B do "evoluir o WuzAPI" (assinar o evento `LoggedOut` nativo, sessão 2026-06-18 / [doc vivo](../sessions/2026-06-18-arte-whatsapp-naoficiais.md)), verificação ao vivo no daemon de produção (CT 100) achou um canal whatsmeow (`ch-a5d3…`, a Jana) com `connected=False, loggedIn=False` — **morto na sessão** — enquanto a Caixa Unificada exibia o canal como `ativo`. Ou seja: **uma queda real estava invisível** pro app.

Causa raiz (duas camadas):

1. **Máquina de estados** ([ADR 0206](0206-state-machine-whatsmeow-reconciliacao.md)): `WhatsmeowReconciler::reconcile()` retorna `PROVISION_PENDING` quando o daemon reporta `connected=False` (independente de o canal já ter sido pareado).
2. **Decisão do probe** ([ADR 0286](0286-channel-health-corroborado-por-mensagem-real.md), US-WA-308): `WhatsmeowHealthProbeCommand::decideAction()` mapeava `PROVISION_PENDING` → `ACTION_NONE` ("estado transitório / pareando, não muta health"). Isso é correto pra um canal **nunca-pareado** (provisionando de fato), mas **errado** pra um canal que estava `healthy` e caiu — o teste `WhatsmeowHealthProbeDecisionTest` inclusive **codificava** esse comportamento como intencional.

Achado correlato no mesmo fluxo: `WhatsmeowWebhookController::handleDisconnected` listava `'logged_out'` entre os `banKeywords` → um logout remoto seria classificado como **ban** (P0, "número banido pela Meta"). Um logout é **re-pareável** (gerar novo QR), não um ban.

## Decisão

**1. `PROVISION_PENDING` num canal cujo `channel_health` era `healthy` é tratado como queda** (`connected=False` num canal que estava no ar = sessão caiu), seguindo o mesmo ramo de `LOGGED_OUT`/`NOT_EXISTS`:
   - sem inbound recente → marca `disconnected`;
   - com inbound recente (janela [ADR 0286](0286-channel-health-corroborado-por-mensagem-real.md)) → **suprime** o falso disconnected / auto-cura.

**2. `PROVISION_PENDING` num canal `never_checked`/`disconnected` segue transitório** (`ACTION_NONE`) — provisionamento legítimo de canal nunca-pareado não vira "fora do ar".

**3. Logout remoto não é ban.** `'logged_out'` sai dos `banKeywords` do webhook → vira `disconnected` (re-pareável), consistente com o probe. (`banned`/`forbidden`/`multidevice_mismatch` permanecem ban.)

**4. A decisão continua PURA e testada** em `decideAction(state, healthBefore, freshInbound)` — a catraca é o teste determinístico; `handle()` usa a mesma definição de "caído" pra computar o inbound.

## Consequências

- ✅ Uma queda real com `connected=False` (logout que não emitiu evento, restart do daemon, socket morto) **passa a ser detectada** e some o "ativo" falso.
- ✅ Sem regressão da supressão de falso-positivo: inbound recente ainda prova "no ar".
- ✅ Logout deixa de disparar alerta de ban indevido (P0) + UX errada ("use Meta Cloud").
- ⚠️ Muda 1 caso que o teste antigo fixava (`PROVISION_PENDING + healthy → NONE`) — atualizado no mesmo PR (decisão append-only: este ADR é a fonte da nova regra).
- 📝 Não fecha a observabilidade (uptime%/alerta) — isso é ADR próprio (SLO/SLI de canal, follow-up).

## Anchor

**Implementado em:** `Modules/Whatsapp/Console/Commands/WhatsmeowHealthProbeCommand.php` (`decideAction` + `handle`), `Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php` (`handleDisconnected` banKeywords), `Modules/Whatsapp/Tests/Feature/WhatsmeowHealthProbeDecisionTest.php`.
