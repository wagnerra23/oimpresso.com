---
date: 2026-06-19
time: "1103 BRT"
slug: "midia-dns-rootcause"
tldr: "Fecha as 2 decisões do handoff 0730. (1) Mídia WhatsApp travada (48.569 pending): root cause CONFIRMADO — o A record whatsapp-whatsmeow.oimpresso.com sumiu da zona DNS Hostinger (NXDOMAIN). Daemon CT100 (177.74.67.30) Up 3 semanas healthy + Traefik OK → único elo quebrado é DNS, zero bug de código. Fix = recriar A record (Hostinger DNS API, ADR 0045), ESCALA pro W. (2) US-WA-309 (banner) JÁ feito (#2956/#2963); falta só mergear #2964."
decided_by: [W]
cycle: null
prs: [2956, 2963, 2964, 3002, 3009, 3014]
us: ["US-WA-309"]
next_steps:
  - "DECISÃO W (prod/DNS): aprovar recriar A record whatsapp-whatsmeow.oimpresso.com -> 177.74.67.30 via Hostinger DNS API (ADR 0045, token no Vaultwarden). Comando pronto no corpo. Claude NÃO aplica sozinho (publication-policy)."
  - "Pós-DNS: drenar backlog com `php artisan whatsapp:backfill-media-download --business=all --since=2026-06-01` em lotes (--limit), idempotente. Camada 4 (RetryFailedMediaDownloadsJob hourly) também drena sozinha mais devagar."
  - "DECISÃO W (produto): onde registrar o achado DNS — (a) seção nova no proposal whatsapp-ingestao-perda-zero.md [recomendado] OU (b) US própria US-WA-312 (311 está reservado pelo proposal). Recomendo também uma sentinela 'daemon whatsmeow alcançável' pra esse silêncio de ~7d nunca repetir."
  - "US-WA-309: mergear #2964 (banner business-wide + probe provision_pending). NÃO re-specar — já é canônico via #2956."
related_adrs:
  - 0045-hostinger-dns-api-endpoint-canonico
  - 0204-whatsmeow-driver-substituto-baileys
  - 0062-separacao-runtime-hostinger-ct100
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0202-whatsapp-profissionalizacao-baileys-out
---

# Handoff 2026-06-19 11:03 BRT — root cause da mídia travada (DNS) + US-WA-309 fechada

## TL;DR

As 2 decisões deixadas frias no handoff [0730](2026-06-19-0730-decisoes-pendentes-dns-midia-us-309.md) estão **resolvidas no diagnóstico**, faltando só o **OK do Wagner** (são prod/produto, não código). A mídia do WhatsApp não baixa porque o **A record do daemon sumiu do DNS** — não é o worker, não é timeout, não é o daemon. O daemon está vivo e saudável há 3 semanas; o Traefik roteia certo. O único elo morto é o DNS. **Fix = recriar 1 A record.** O banner "canal caiu" (US-WA-309) **já foi entregue** — não precisa especificar nada.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 10:30 | Li os 2 artefatos canônicos (handoff 0730 #3014 + session log #3009). MCP oimpresso não conectado nesta sessão — usei git/gh/DoH/CT100 como fonte de verdade. |
| 10:40 | Mapeei o worker (`DownloadMediaJob::fetchViaWhatsmeowDownload`) + config (`whatsmeow.daemon_url`) + runbook + docker-compose do daemon. |
| 10:50 | DoH (dns.google) + nslookup: `whatsapp-whatsmeow.oimpresso.com` = **NXDOMAIN** (Status 3). `mcp.oimpresso.com` = `177.74.67.30` (CT100). |
| 11:00 | `tailscale ssh ct100-mcp` (read-only): daemon `Up 3 semanas (healthy)`, Traefik up, count real **48.569 pending**, Traefik Host-routing = 404 (rota OK), daemon direto = **200**. |
| 11:03 | US-WA-309 confirmado feito (#2956/#2963/#3002 merged; #2964 aberto). Escrevi este handoff. |

## Achado 1 — Mídia WhatsApp travada: é DNS, não código

**Sintoma:** ~48k mensagens com `media_download_status='pending'` desde ~12/jun. O handoff 0730 atribuiu a "DNS quebrado" a partir de um `curl (6)` rodado **do CT 100** (vantage errado — o CT 100 não resolve o próprio hostname público do Traefik, e o worker roda no **Hostinger**, não no CT 100). Verifiquei do ângulo certo:

**Evidência (toda read-only):**

| Verificação | Resultado | Lê como |
|---|---|---|
| DoH `dns.google` A `whatsapp-whatsmeow.oimpresso.com` | `Status:3` (**NXDOMAIN**), NS = `dns.hostinger.com`, SOA serial `2026061801` | A record **removido** da zona Hostinger (zona editada 2026-06-18). NÃO é Cloudflare (o runbook está errado nisso). |
| `nslookup … 8.8.8.8` (local) + `getent hosts` (CT100) | NXDOMAIN nos dois | Some globalmente, não é split-horizon. |
| `mcp.oimpresso.com` (irmão no CT100) | A → `177.74.67.30` | CT 100 vivo e com DNS OK → o alvo correto do A record é `177.74.67.30`. |
| `docker ps` no CT100 | `whatsapp-whatsmeow Up 3 semanas (healthy)`, `traefik Up 2 semanas` | **Daemon nunca caiu.** |
| `curl --resolve …:443:127.0.0.1 …/health` (loopback Traefik) | `http_404` | Traefik **casa o `Host()` rule** e roteia pro daemon (não-000 = caminho TLS+Traefik intacto). |
| `docker run --network container:whatsapp-whatsmeow curl …localhost:8080/health` | `http_200` | Daemon serve `/health` internamente. |
| Count via connection do app (oimpresso-mcp tinker, query builder) | `pending:48569, success:659, failed_permanent:14` | Confirmado pela connection configurada (não PDO cru). `success` cresceu (mídia Meta/Z-API direct ainda baixa; só whatsmeow trava). |

**Causa-raiz (provada):** o worker `DownloadMediaJob` (Hostinger) chama o daemon em `config('whatsapp.whatsmeow.daemon_url')` = `https://whatsapp-whatsmeow.oimpresso.com` (default no [config.php](../../Modules/Whatsapp/Config/config.php)). Esse host **NXDOMAINa** → toda mídia whatsmeow dá soft-fail → fica `pending` e nunca dreca. Webhooks inbound seguem funcionando (vão pro `oimpresso.com`, que resolve) → por isso mensagens chegam mas a mídia não.

**O "endpoint vivo" É o canônico** — não há host diferente. O código e o env estão **certos**; o que quebrou foi a entrada de DNS. **Não há bug de worker/endpoint pra corrigir em código.**

### Fix (PROD/DNS — ESCALA pro Wagner, publication-policy)

Recriar o A record via Hostinger DNS API ([ADR 0045](../decisions/0045-hostinger-dns-api-endpoint-canonico.md)). Token Bearer no Vaultwarden. `overwrite:false` = **adiciona sem destruir** os outros records:

```bash
TOKEN="<bearer Hostinger — Vaultwarden>"
curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" \
  -d '{
    "overwrite": false,
    "zone": [
      { "name": "whatsapp-whatsmeow", "type": "A", "ttl": 300, "records": [{"content": "177.74.67.30"}] }
    ]
  }'
```

Verificar: `dig whatsapp-whatsmeow.oimpresso.com` deve voltar `177.74.67.30`. Sem mudança de código, sem mudança de env, sem re-point do worker.

### Drain do backlog (depois do DNS, idempotente)

```bash
# Conta primeiro (dry-run):
php artisan whatsapp:backfill-media-download --business=all --since=2026-06-01 --dry-run
# Drena em lotes (cap --limit; rodar repetido ou subir o limite com cuidado na fila whatsapp):
php artisan whatsapp:backfill-media-download --business=all --since=2026-06-01 --limit=2000
```

A Camada 4 (`RetryFailedMediaDownloadsJob`, hourly) também redreca `pending` sozinha, só mais devagar. `DownloadMediaJob` é idempotente (pula `success`).

### Por que ninguém viu por ~7 dias (gap real = o conteúdo do US-WA-311)

`DownloadMediaJob` faz **soft-fail silencioso** quando o daemon é inalcançável e confia no retry hourly — não existe alarme pra "daemon sistematicamente inalcançável (DNS/edge morto)". É exatamente o que o US-WA-311 do drift pedia: **observabilidade de drops silenciosos**. Recomendação de produto abaixo.

## Achado 2 — US-WA-309 (banner "canal caiu") JÁ ESTÁ FEITO

| Evidência | Onde |
|---|---|
| Componente do banner | [`ChannelHealthBanner.tsx`](../../resources/js/Pages/Atendimento/CaixaUnificada/_components/ChannelHealthBanner.tsx) |
| Página renderiza | [`Index.tsx:510`](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx) `<ChannelHealthBanner unhealthyChannels={…}/>` (+ partial-reload `only:['unhealthyChannels']`) |
| Agregado no Controller | `CaixaUnificadaController::buildUnhealthyChannelsPayload()` (L650) — `channel_health IN (disconnected,banned,degraded)`, Tier 0 por `business_id` |
| PRs merged | #2956 (banner + health-probe), #2963 (redesign Cowork multi-canal dispensável), #3002 (realtime via Centrifugo), #2985, #2984 |
| Aberto | **#2964** — follow-up: banner **business-wide** (sem o filtro ACL-de-canal que escondia o banner de contas sem grant) + probe detecta `provision_pending` |

Bate 1:1 com o AC do US-WA-309. **Não re-specar.** Só **mergear #2964** (sem ele, na prod só a conta admin enxerga o banner).

## PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#2956](https://github.com/wagnerra23/oimpresso.com/pull/2956) | merged | banner canal-caiu + whatsmeow:health-probe (US-WA-308/309) |
| [#2963](https://github.com/wagnerra23/oimpresso.com/pull/2963) | merged | redesign ChannelHealthBanner (Cowork, multi-canal, dispensável) |
| [#3002](https://github.com/wagnerra23/oimpresso.com/pull/3002) | merged | saúde de canal em tempo real (Centrifugo) |
| [#2964](https://github.com/wagnerra23/oimpresso.com/pull/2964) | **aberto** | banner business-wide + probe provision_pending |
| [#3009](https://github.com/wagnerra23/oimpresso.com/pull/3009) | merged | resgate do backlog US-WA-308..311 (session log) |
| [#3014](https://github.com/wagnerra23/oimpresso.com/pull/3014) | merged | handoff 0730 (este o sucede) |

## Decisões tomadas

| Pergunta | Decisão | Justificativa | Referência |
|---|---|---|---|
| Mídia travada é bug de worker/endpoint? | **Não** — é DNS (A record removido) | daemon healthy 3sem + Traefik roteia + count via app | Achado 1 |
| US-WA-309 ainda é backlog aberto? | **Não** — feito | banner + agregado + PRs merged | Achado 2 |

## Bloqueios / pendências (decisões do Wagner)

- [ ] **(prod)** Aprovar recriar o A record `whatsapp-whatsmeow.oimpresso.com → 177.74.67.30` — owner: W. Claude tem o comando pronto, NÃO aplica sozinho (DNS/prod escala).
- [ ] **(produto)** Onde registrar o achado DNS: (a) seção no proposal `whatsapp-ingestao-perda-zero.md` [recomendado] ou (b) US própria `US-WA-312` (311 reservado) — owner: W.
- [ ] **(produto)** Aprovar sentinela "daemon whatsmeow alcançável" (DNS+ping) pra esse silêncio não repetir — owner: W.
- [ ] **(merge)** #2964 (banner business-wide) — owner: W.

## Próximos passos (ordem)

1. **W aprova** o A record → restaura download (impacto imediato em 48.569 mídias).
2. Após DNS verde (`dig`), rodar o drain `whatsapp:backfill-media-download` em lotes.
3. **W escolhe** (a)/(b) pro registro do achado; Claude escreve o doc na próxima sessão.
4. **W mergeia** #2964.
5. (Recomendado) abrir US pra sentinela de alcançabilidade do daemon.

## Estado MCP no momento do fechamento

> **MCP oimpresso NÃO conectado nesta sessão** (worktree órfão `frosty-greider-83ab2f`; `brief-fetch`/`my-work`/`cycles-active` indisponíveis — verificado via ToolSearch). Fonte de verdade desta sessão = git/gh + DoH + CT100 read-only. Snapshot equivalente abaixo.

### Estado via git/gh (substituto)
```
origin/main HEAD: 190b31a58 feat(jana): núcleo puro ModuleTruthEventCollector (#3016)
Maior US-WA no SPEC: US-WA-310 (308/309/311 ausentes; 311 reservado pelo proposal ingestao-perda-zero)
PRs banner US-WA-309: #2956/#2963/#3002 merged, #2964 aberto
DB prod (via oimpresso-mcp tunnel): messages.media_download_status = pending:48569 / success:659 / failed_permanent:14
```

### whats-active
```
N/A — MCP indisponível. git worktree list mostra ~25 worktrees ativos (várias sessões paralelas).
```

## Referências

- Handoff anterior: [2026-06-19-0730-decisoes-pendentes-dns-midia-us-309.md](2026-06-19-0730-decisoes-pendentes-dns-midia-us-309.md)
- Session log do resgate: [2026-06-19-ct100-spec-drift-rescue.md](../sessions/2026-06-19-ct100-spec-drift-rescue.md)
- Proposal dono do "backlog US-WA-311": [whatsapp-ingestao-perda-zero.md](../decisions/proposals/whatsapp-ingestao-perda-zero.md)
- Worker: [DownloadMediaJob.php](../../Modules/Whatsapp/Jobs/DownloadMediaJob.php) · Config: [config.php](../../Modules/Whatsapp/Config/config.php) · Runbook daemon: [whatsmeow-daemon-deploy-ct100.md](../requisitos/Whatsapp/runbooks/whatsmeow-daemon-deploy-ct100.md)
- ADR 0045: [Hostinger DNS API endpoint canônico](../decisions/0045-hostinger-dns-api-endpoint-canonico.md)
