---
topic: "Resgate de backlog não-commitado (US-WA-308..311) da drift do checkout do CT 100 + nota de deploy"
date: "2026-06-19"
authors: ["Claude Code"]
related_adrs:
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0062-separacao-runtime-hostinger-ct100
  - 0202-whatsapp-profissionalizacao-baileys-out
---

# Resgate da drift do CT 100 — backlog US-WA-308..311 + lição de deploy

## Como apareceu

Ao deployar o MCP (CT 100) pra ativar o `recusado` consultável (#2998/#3000/#3008), achei o
checkout `/opt/oimpresso-mcp/code` com **1 arquivo tracked sujo** — `memory/requisitos/Whatsapp/SPEC.md`
com **54 linhas adicionadas e NUNCA commitadas** (4 USs escritas direto no host de prod, a drift
exata que o README §"NÃO editar via UI" proíbe). Preservado aqui antes de restaurar o checkout.

## Status de cada US (cruzado com `origin/main`)

| US | No SPEC do main? | Implementado? | Ação |
|---|---|---|---|
| **US-WA-308** (whatsmeow LoggedOut + health-probe) | ❌ (só a drift) | ✅ código + cron + **ADR 0286** | narrativa de US redundante — o trabalho já é canônico |
| **US-WA-309** (banner "canal caiu" na Caixa) | ❌ | provável (branches `caixa-unif-health-banner`/`banner-businesswide-probe`) | **Wagner confere** se ainda é backlog |
| **US-WA-310** (matar cron Baileys reconcile) | ✅ **já está no main** | — | **duplicata** — não recommitar |
| **US-WA-311** (observabilidade ingest + backlog mídia 48k pending) | ❌ | provavelmente ABERTO | **candidato real a virar US no SPEC** |

**Conclusão:** commitar a drift verbatim **duplicaria a US-WA-310** e fabricaria status (308 diz
`todo` mas está feito). Por isso NÃO foi recommitada no SPEC — preservada aqui pro Wagner
cherry-pickar só o que ainda é backlog vivo (309?/311).

## Conteúdo resgatado (verbatim)

```markdown
### US-WA-308 · Canal whatsmeow deslogado não gera aviso — assinar LoggedOut no WuzAPI + health-probe agendado
> owner: wagner · priority: p0 · estimate: 4h · status: todo · type: story
Incidente 2026-06-18: canal 11 'Suporte' (biz 1) deslogou 07:50 BRT, app NÃO soube (channel_health
ficou healthy, ~2h caído). Causa: subscription WuzAPI sem `LoggedOut` + sem cron health/reconcile
whatsmeow. AC: incluir LoggedOut na subscription; cron whatsmeow:health-probe; alerta mcp_alertas.
[→ ENTREGUE: ver ADR 0286 + WhatsmeowHealthProbeCommand + Kernel.php]

### US-WA-309 · Caixa Unificada: banner "canal caiu — religar" usando channel_health
> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: story
Caixa já recebe channel_health mas só renderiza banner pra preview_only. AC: banner persistente no
topo quando canal != healthy ("Canal X desconectado — religar" + link /atendimento/canais/{id});
agregado leve unhealthyChannels no Controller; indicador no chip + header da thread.

### US-WA-310 · Matar/escopar cron whatsapp:channels-reconcile (Baileys morto — exit 1 a cada 5min)
> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
[→ JÁ NO SPEC DO MAIN — duplicata, ignorar esta cópia]

### US-WA-311 · Observabilidade ingest whatsmeow: logar drops silenciosos + backlog mídia 48k pending
> owner: wagner · priority: p2 · estimate: 4h · status: todo · type: story
ProcessIncomingWebhookJob tem 3 saídas-cedo sem log (extractMessages vazio L122, provider_message_id
vazio L146, dedup L155). AC: logar as 3 com provider_message_id/instance (sem PII). Mídia: 48.514
linhas media_download_status='pending' (636 success, 14 failed_permanent); daemon download dá cURL 28
timeout pra whatsapp-whatsmeow.oimpresso.com desde ~12/jun — investigar worker + drenar backlog.
```

## Lição de deploy (pra não repetir)

- O **deploy canônico do MCP é `bash .../docker/oimpresso-mcp/scripts/self-update.sh`** (reset --hard
  origin/main + força recreate + smoke), NÃO `docker compose restart` solto. Eu usei `restart` (funcionou
  pra estourar o OPcache), mas o caminho certo é o script — que também teria limpado a drift do SPEC.
- **Achado P1 de infra:** o container servia código velho **apesar do checkout já estar no #3008**. Ou seja
  o GitOps-pull (cron) puxou o código mas **não recriou o container** (ou parou de rodar). É o mesmo
  sintoma do incidente 2026-06-17 (README §Atualização: "~17 dias de código velho servido em silêncio").
  **Vale o Wagner checar a sentinela externa + o cron de self-update.**
