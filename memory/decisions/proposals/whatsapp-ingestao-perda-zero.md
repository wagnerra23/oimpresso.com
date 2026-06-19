---
status: proposal
title: Ingestão WhatsApp à prova de perda (zero-loss) — padrão profissional pós-#2726
proposed_by: Wagner + Claude
proposed_at: 2026-06-16
relates_to:
  - 0204-whatsmeow-driver-substituto-baileys
  - 0135-omnichannel-inbox-arquitetura
  - 0093-multi-tenant-isolation-tier-0
---

# PROPOSAL — Ingestão WhatsApp à prova de perda (zero-loss)

> **Status:** `proposal` — Wagner promove pra ADR aceita após revisão. Construção
> **fase a fase, cada uma testada** (a lição do #2726: mudança na via crítica sem
> verificar = 3 dias mudo). Decisão de ambição: Wagner pediu o **padrão completo
> (perda zero)** em 2026-06-16.

## Contexto

Incidente 2026-06-16 (#2726): o recebimento de WhatsApp ficou morto 3 dias sem
ninguém ver. O fix + a detecção (sentinela `whatsapp_inbound_flow`, PR #2813)
já estão no ar, mas o padrão atual é **piso, não profissional**:

- **Detecção lenta:** ~6h (threshold de silêncio) — não dá pra "perder mensagem
  é coisa séria".
- **Perde na falha:** webhook recusado vai pra fila `webhook_errors`
  fire-and-forget do WuzAPI → **descartado** (sem replay).
- Sem auto-cura, sem fallback ativo pro whatsmeow, sem auto-teste do alarme.

Pra quando clientes reais dependerem disso (hoje é piloto/teste), o alvo é o que
Intercom/Front/Zendesk fazem: **não perder, ponto.**

## Decisão proposta — 5 camadas

### SLOs alvo
- **Perda de mensagem: 0** (toda inbound eventualmente visível na Caixa).
- **Detecção de quebra: < 5 min** (definitiva, sem falso-alarme).
- **Recuperação: auto** pras causas conhecidas; **humano avisado < 5 min** + runbook.

### Camada 1 — Canário (detecção <5min, definitiva) · `whatsapp:webhook-canary`
Cron a cada ~5 min posta um evento sintético benigno (`Presence`, que o controller
ACKa em 200 SEM criar mensagem — provado em `WhatsmeowWebhookAuthTest`) na própria
URL pública com o `?wh=` secret e confere **200**. Não-200 → ALERT imediato (`mcp_alertas`).
Pega a classe do #2726 (app recusando) em 5 min. **Combinado com a janela de retry do
daemon (~10-15 min), a maioria das falhas é detectada+corrigida ANTES de qualquer
mensagem ser descartada → perda zero sem reescrever durabilidade.** O canário também
É o auto-teste do alarme (camada 5).

### Camada 2 — Backfill/reconciliação (a rede final + recupera os 3 dias) ⭐
O WuzAPI expõe **`GET /session/history`** (sync de histórico por chat, parâmetros
count/chat/oldest-id) → emite eventos `HistorySync`. Plano:
1. Controller + Job passam a tratar evento **`HistorySync`** (extrai mensagens do
   payload, faz upsert dedupando por `provider_message_id` — idempotente, já é a chave UNIQUE).
2. Command `whatsapp:backfill-inbound {channel} --since=` que dispara `/session/history`
   pra cada conversa ativa e reconcilia o que faltou.
3. Roda **on-recovery** (quando o canário detecta que voltou) + agendado defensivo.
**Bônus:** o mesmo mecanismo **recupera as mensagens dos 3 dias do #2726** — resolve o
"backlog perdido" da US-WA-311 de graça.

> ⚠️ **Não confundir dois backlogs distintos:** (1) **mensagens perdidas** (#2726, webhook
> recusado) = esta Camada 2. (2) **mídia não-baixada** de mensagens que *chegaram* normalmente
> (backlog real = **10.134**, todo do driver Baileys descomissionado) = **Camada 6** abaixo.
> A mensagem existe na Caixa; só a mídia não desceu. Mecanismos e causas-raiz diferentes.

### Camada 3 — Auto-cura
Sessão caiu (`Disconnected`/stream error) → `WhatsmeowReconciler` reconecta sozinho
(já resolve pending-pair; estender pra reconexão proativa). Falha transitória de
webhook → a retry nativa do daemon já cobre.

### Camada 4 — Sem ponto único: fallback Meta Cloud
Fiar o framework de fallback que já existe (`config('whatsapp.fallback')`,
`auto_switch_after_status=degraded`) pro driver whatsmeow — quando o canal degrada,
roteia envio/recebimento pela API **oficial Meta Cloud**. Tira o daemon não-oficial
de ponto único de falha total.

### Camada 5 — Auto-teste do alarme
O canário (camada 1) PROVA continuamente que o caminho + o alerta funcionam. Sem isso,
o monitor pode apodrecer mentindo "verde" (raiz do #2726). Opcional: um 2º canário
no CT 100 testando o caminho daemon→app (rede), não só app→app.

### Camada 6 — resiliência do download de mídia + alarme de daemon-inalcançável (era o "US-WA-311")

**Incidente 2026-06-19 — dois achados separados** (diagnóstico read-only + verificação na app prod; handoff `2026-06-19-1103-midia-dns-rootcause`):

**Achado A — A record do daemon sumiu (corrigido).** `whatsapp-whatsmeow.oimpresso.com` estava
**NXDOMAIN** (removido da zona Hostinger; DoH `dns.google` Status 3 + `getent` do CT 100; SOA
`2026061801`). O daemon (CT 100, `177.74.67.30`) está **`Up 3 semanas (healthy)`** e o Traefik
**casa o `Host()` rule** (loopback → `http_404`; daemon direto → `http_200`). Como o
`DownloadMediaJob` roda no **Hostinger** e chama `config('whatsapp.whatsmeow.daemon_url')`, o
caminho app→daemon ficou morto. **Recriei o A record** (→ `177.74.67.30`, [ADR 0045](../0045-hostinger-dns-api-endpoint-canonico.md),
`overwrite:false`; `dig`+DoH confirmam). Hygiene de infra **necessária** (health-probe ADR 0286,
admin do daemon, mídia whatsmeow futura) — **mas não era a causa de um backlog**: ver Achado B.

**Achado B — o "48k mídia travada" era um número inflado.** `media_download_status` tem
**default `'pending'` pra TODA mensagem** (texto incluso). Filtrando só tipos de mídia, o backlog
**real é 10.134** — e **100% são `whatsapp_baileys`** (driver descomissionado [ADR 0202](../0202-whatsapp-profissionalizacao-baileys-out.md),
27/mai: daemon de decrypt morto + URLs `.enc` expiradas = **undownloadable**). **Mídia whatsmeow
pending = 0** (teste síncrono na app: pipeline em dia). Ou seja: o DNS estava quebrado, mas não
havia backlog de mídia whatsmeow esperando por ele.

→ **Não é bug de código.** Worker/endpoint/env corretos. (1) A record: corrigido. (2) os 10.134
Baileys: **dead-letter** — recomendado marcar `failed_permanent` (decisão de dados do Wagner; mutação
de 10k linhas não foi aplicada sozinha).

**O gap durável (o que o US-WA-311 pedia — observabilidade):** um A record sumir em silêncio e
ninguém ver é a classe de falha que precisa de alarme (mesmo que desta vez não tenha gerado
backlog). Proposta: **sentinela "daemon whatsmeow alcançável"** —
1. checagem ativa (resolve DNS + `GET /health` via Traefik) no `jana:health-check`, OU
2. detecção passiva: N soft-fails de download consecutivos **business-wide** na mesma janela →
   `mcp_alertas` ALERT em <5min (não 1 por mensagem — agregado, anti-ruído).

Tier 0 preservado (agregação por `business_id`). Pareia com a Camada 1 (canário) — mesma filosofia
aplicada ao caminho **app→daemon** (download), que hoje não tem canário.

## Faseamento (cada fase = 1 PR testado, pode parar entre fases)
0. **Mídia/DNS (Camada 6)** — A record já recriado; resta dead-letter dos 10.134 Baileys + a
   sentinela de alcançabilidade do daemon (baixo risco, só observa). Independente das demais.
1. **Canário** (camada 1+5) — maior salto isolado: detecção <5min + perda-zero pra
   maioria das falhas. Baixo risco (só observa, não toca a via de recebimento).
2. **Backfill/HistorySync** (camada 2) — perda-zero pra qualquer duração + recupera os 3 dias.
   Risco médio (toca controller/Job — testar com fixture real, contract-test).
3. **Auto-cura + fallback Meta** (camadas 3+4) — resiliência. Risco médio-alto (Reconciler
   + roteamento) — staging primeiro.

## Estratégia de teste (anti-#2726)
- Cada fase com Pest cobrindo o caminho REAL (fixture do emissor, não credencial forjada —
  padrão do PR #2814).
- Camada 2/3/4 passam por **staging** antes de prod (`criar-staging`).
- Smoke pós-deploy: o próprio canário confirma 200 em prod logo após o deploy.

## Consequências
- **+:** perda zero, detecção em minutos, recupera os 3 dias, daemon deixa de ser SPOF.
- **−:** mais código na via crítica (mitigado por faseamento + teste + staging); `/session/history`
  pode trazer duplicatas (mitigado pelo dedup `provider_message_id`); custo de CI dos canários (trivial).
- **Tier 0 preservado:** backfill respeita `business_id` (dedup + escopo por channel/business).

## Métrica de pronto
`jana:health-check` ganha `whatsapp_inbound_canary` (verde = caminho provado <5min) e a
contagem de mensagens reconciliadas pelo backfill. Painel: 0 perdidas em 30d.
