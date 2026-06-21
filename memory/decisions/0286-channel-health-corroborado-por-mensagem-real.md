---
slug: 0286-channel-health-corroborado-por-mensagem-real
number: 286
title: "channel_health de canal whatsmeow é corroborado por fluxo de mensagem real — inbound recente suprime o falso 'fora do ar' do loggedIn não-confiável do WuzAPI"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-18"
module: whatsapp
tags: [whatsapp, whatsmeow, health-probe, channel-health, incident, tier-0, caixa-unificada, atendimento]
supersedes: []
superseded_by: []
related:
  - 0206-state-machine-whatsmeow-reconciliacao
  - 0093-multi-tenant-isolation-tier-0
  - 0130-handoff-append-only-mcp-first
---

# ADR 0286 — `channel_health` corroborado por mensagem real

## Contexto

Incidente reportado por [W] em 2026-06-18 na Caixa/Atendimento (`/atendimento/caixa-unificada`): o banner mostrava **"WhatsApp · Suporte está fora do ar — 94 conversas afetadas"**, mas ao clicar **Reconectar** o modal respondia **"Canal já pareado — sessão ativa"**. Contradição na mesma tela.

**Verificação ao vivo** (SSH read-only no daemon WuzAPI, CT 100): o canal estava **logado** (JID presente), **recebendo ~48 msg/h** (124 em 24h), **webhook entregando 200** (151× na última hora), **zero logout em 24h**. Ou seja: o canal estava **no ar o tempo todo** — quem mentia era o app.

Diagnóstico de duas camadas:

1. **Contrato de UI** (resolvido em PR separado): o `connect` sinaliza canal já ativo com `state:'paired'`, o `status` com `state:'connected'`; o `ReconnectModal` só reconhecia `'connected'` → a verdade caía no ramo de erro vermelho.
2. **Heurística do probe** (este ADR): `WhatsmeowReconciler::reconcile()` tem o ramo `connected && !loggedIn → LOGGED_OUT` (pegajoso: `channel_health==='disconnected'` se auto-perpetua). O `whatsmeow:health-probe` ([ADR 0206](0206-state-machine-whatsmeow-reconciliacao.md), US-WA-308) marcava `disconnected` baseado **só** no `loggedIn` do WuzAPI — flag que o próprio docblock do probe admite ser não-confiável (WuzAPI não assina `LoggedOut`). Nunca cruzava com "tá chegando mensagem?".

Observações de processo no mesmo incidente:

- **Catraca não mordeu.** O reconnect foi entregue como "piloto da catraca" (#2974). A catraca valida **presença** dos marcadores `data-contract`, não a **semântica** (que `paired` ≡ `connected` nos dois lados). Contrato estruturalmente presente, comportamento quebrado — passou no gate.
- **Memória superestimou verificação.** O handoff 2026-06-16 afirmava a Caixa "verificada na prod nos 2 temas"; a verificação não cobriu a semântica banner↔reconnect. Correção registrada abaixo (append-only, [ADR 0130](0130-handoff-append-only-mcp-first.md)).

## Decisão

**1. Inbound recente é prova autoritativa de "no ar".** O `whatsmeow:health-probe`, no ramo caído (`LOGGED_OUT`/`NOT_EXISTS`), consulta `whatsapp_conversations.last_inbound_at`. Se houve inbound dentro da janela (`whatsapp.whatsmeow.health_fresh_inbound_minutes`, **default 10 min**, martelo [W]):

   - **Suprime** o `disconnected` (não marca "fora do ar");
   - **Auto-cura**: se já estava `disconnected`, volta pra `healthy` (quebra o loop pegajoso sem esperar re-pareamento).

**2. Ausência de inbound ≠ queda.** Canal pode estar legitimamente quieto. Sem inbound recente, mantém o sinal do daemon (marca `disconnected`) — a regra **não cria falso-negativo**.

**3. `BANNED` não é suprimível.** Uma mensagem anterior ao ban não invalida o ban (P0, alerta humano).

**4. Decisão pura isolada.** A lógica vive em `WhatsmeowHealthProbeCommand::decideAction(state, healthBefore, freshInbound)` — sem DB/daemon, travada por teste determinístico (`WhatsmeowHealthProbeDecisionTest`, 7 casos). A query de inbound fica fina no `handle()` (Tier 0: escopada por `channel_id`, único por business; `withoutGlobalScopes` porque o cron roda sem session — [ADR 0093](0093-multi-tenant-isolation-tier-0.md)).

**5. Princípio derivado (catraca semântica).** Um gate de contrato de tela deve validar o **acordo de valores** entre backend e frontend (vocabulário de `state`), não só a presença do marcador `data-contract`. Endereçar na evolução da catraca (follow-up).

## Consequências

- ✅ O banner "fora do ar" deixa de disparar (e some sozinho) quando o canal está provadamente recebendo mensagens.
- ✅ Auto-cura encerra o estado pegajoso `disconnected` sem intervenção humana.
- ✅ Quedas reais (sem tráfego) continuam detectadas — sem regressão de cobertura.
- ⚠️ Janela de 10 min: uma queda real logo após uma mensagem leva até ~10 min + 1 ciclo de cron pra ser declarada. Aceitável (martelo [W]); ajustável por env `WHATSMEOW_HEALTH_FRESH_INBOUND_MINUTES`.
- 📝 **Correção de memória** (append-only): o handoff `2026-06-16-1545-caixa-unificada-dark-ondas` afirma a Caixa "verificada na prod"; a verificação **não** cobriu o contrato banner↔reconnect nem a semântica do `channel_health`. Este ADR é a fonte de verdade do estado real pós-2026-06-18.

## Anchor

**Implementado em:** `Modules/Whatsapp/Console/Commands/WhatsmeowHealthProbeCommand.php` (`decideAction` + `hasRecentInbound`), `Modules/Whatsapp/Config/config.php` (`health_fresh_inbound_minutes`), `Modules/Whatsapp/Tests/Feature/WhatsmeowHealthProbeDecisionTest.php`.
