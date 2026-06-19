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
> (48.569 em `media_download_status='pending'`, incidente DNS 2026-06-19) = **Camada 6** abaixo.
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

**Incidente 2026-06-19 (root cause provado):** **48.569** mensagens em
`media_download_status='pending'` (vs 659 `success`, 14 `failed_permanent`) acumuladas desde
~12/jun. Diagnóstico read-only (handoff `2026-06-19-1103-midia-dns-rootcause`):

- O A record **`whatsapp-whatsmeow.oimpresso.com` sumiu da zona DNS Hostinger** → **NXDOMAIN**
  (DoH `dns.google` Status 3 + `getent` do próprio CT 100; SOA serial `2026061801`).
- O daemon (CT 100, `177.74.67.30`) está **`Up 3 semanas (healthy)`**; Traefik **casa o `Host()` rule**
  (loopback → `http_404`, não-000 = rota OK) e o daemon serve `/health` `http_200` direto.
- `DownloadMediaJob::fetchViaWhatsmeowDownload` (roda no **Hostinger**) chama
  `config('whatsapp.whatsmeow.daemon_url')` = o host que NXDOMAINa → **soft-fail silencioso** →
  fica `pending` pra sempre. Webhooks inbound seguem OK (vão pro `oimpresso.com`, que resolve).

→ **Não é bug de código.** O worker, o endpoint e o env estão corretos. Quebrou **1 entrada de DNS**.

**Fix imediato (PROD/DNS — Wagner aprova/executa, publication-policy):** recriar o A record via
Hostinger DNS API ([ADR 0045](../0045-hostinger-dns-api-endpoint-canonico.md)), `overwrite:false`:

```bash
curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" \
  -d '{"overwrite":false,"zone":[{"name":"whatsapp-whatsmeow","type":"A","ttl":300,"records":[{"content":"177.74.67.30"}]}]}'
```

**Drain (pós-DNS, idempotente):** `php artisan whatsapp:backfill-media-download --business=all --since=2026-06-01`
em lotes (`--limit`); a Camada 4 (`RetryFailedMediaDownloadsJob`, hourly) também redreca sozinha.

**O gap durável (o que o US-WA-311 pedia — observabilidade):** o daemon ficou inalcançável ~7 dias
**sem nenhum alarme**. Proposta: **sentinela "daemon whatsmeow alcançável"** —
1. checagem ativa (resolve DNS + `GET /health` via Traefik) no `jana:health-check`, OU
2. detecção passiva: N soft-fails de download consecutivos **business-wide** na mesma janela →
   `mcp_alertas` ALERT em <5min (não 1 por mensagem — agregado, anti-ruído).

Isso transforma "48k mídias presas em silêncio por uma semana" em "alerta em minutos". Tier 0
preservado (agregação por `business_id`). Pareia com a Camada 1 (canário) — mesma filosofia
aplicada ao caminho **app→daemon** (download), que hoje não tem canário.

## Faseamento (cada fase = 1 PR testado, pode parar entre fases)
0. **Mídia/DNS (Camada 6)** — hotfix DNS (imediato, destrava 48k) + drain; depois a sentinela de
   alcançabilidade (baixo risco, só observa). Independente das demais.
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
