# whatsapp-baileys-daemon

Daemon Node.js custom Baileys driver pro `Modules/Whatsapp` (oimpresso ERP).

- **Decisão mãe:** [ADR 0096 emenda 4](../../../memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
- **Arquitetura:** [ARCHITECTURE.md §16](../../../memory/requisitos/Whatsapp/ARCHITECTURE.md)
- **User Story:** [US-WA-002d](../../../memory/requisitos/Whatsapp/SPEC.md)
- **Runtime:** CT 100 Proxmox (Docker compose-managed). **NÃO roda no Hostinger** ([ADR 0062](../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Por que existe

`Modules/Whatsapp` PHP fala via `Http::baseUrl(...)` com este daemon. Daemon mantém 1 socket Whatsapp Web por instance (cliente business) usando `@whiskeysockets/baileys`, e:

1. Expõe REST autenticado por Bearer + IP whitelist Traefik.
2. Posta webhook outbound pro Hostinger quando inbound chega.
3. Exporta OTel + Prometheus pra resolver a "dor de observabilidade" do Evolution.

## Stack

| Camada | Tecnologia | Versão pinned |
|---|---|---|
| Runtime | Node 20 LTS | 20.18.x |
| Linguagem | TypeScript estrito | 5.6 |
| HTTP | Fastify | 4.28 |
| WhatsApp | `@whiskeysockets/baileys` | 6.7.9 (pinned — review_trigger ADR 0096) |
| HTTP client outbound | undici | 6.21 |
| Logger | pino | 9.5 |
| Métricas | prom-client | 15.1 |
| Traces | OpenTelemetry SDK | 1.27 |
| Validação | zod | 3.23 |

## Estrutura

```
src/
├─ server.ts                # bootstrap + graceful shutdown
├─ config/
│  ├─ env.ts                # zod-validated env
│  └─ logger.ts             # pino root logger
├─ observability/
│  ├─ otel.ts               # SDK init (must be first import)
│  └─ metrics.ts            # prom-client registry
├─ baileys/
│  ├─ InstanceManager.ts    # ciclo de vida das instances
│  ├─ Instance.ts           # 1 socket Baileys + estado
│  ├─ authState.ts          # persistência em volume
│  └─ banDetector.ts        # heurísticas Meta TOS
├─ webhook/
│  └─ WebhookDispatcher.ts  # outbound retry exponencial
└─ http/
   ├─ plugins/
   │  ├─ auth.ts            # Bearer token verify
   │  └─ errorHandler.ts
   ├─ schemas.ts            # zod request/response
   └─ routes/
      ├─ health.ts
      ├─ instances.ts       # connect / status / qr / disconnect
      ├─ text.ts
      └─ media.ts
```

## Dev local

```bash
cd Modules/Whatsapp/daemon-node
cp .env.example .env
# Edite API_KEY e WEBHOOK_BASE_URL
npm install
npm run dev
```

Endpoints disponíveis em `http://127.0.0.1:3000`.

## Deploy CT 100

Ver [runbooks/baileys-daemon-deploy-ct100.md](../../../memory/requisitos/Whatsapp/runbooks/baileys-daemon-deploy-ct100.md) (a ser criado em US-WA-002d).

Resumo:

```bash
ssh root@ct100-mcp
cd /etc/docker-compose/services/whatsapp-baileys
echo "$(openssl rand -hex 32)" | docker secret create whatsapp_baileys_api_key -
docker compose pull
docker compose up -d
docker compose logs -f
```

## API

Todos os endpoints (exceto `/health` e `/metrics`) exigem header `Authorization: Bearer ${API_KEY}`.

| Método | Rota | Descrição |
|---|---|---|
| GET | `/health` | Liveness + lista instances |
| GET | `/metrics` | Prometheus scrape |
| POST | `/instances/:id/connect` | Inicia / retoma sessão |
| POST | `/instances/:id/disconnect` | Encerra sessão (mantém auth state) |
| GET | `/instances/:id/status` | Estado atual + telefone |
| GET | `/instances/:id/qr` | QR Code (PNG base64) se aguardando pareamento |
| POST | `/instances/:id/text` | Envia texto |
| POST | `/instances/:id/media` | Envia mídia (image/document/audio) |

Schema completo em [src/http/schemas.ts](src/http/schemas.ts).

## Webhook outbound

Daemon → Hostinger:

```
POST {WEBHOOK_BASE_URL}/{business_uuid}
Authorization: Bearer {API_KEY}
Content-Type: application/json

{
  "instance_id": "...",
  "event": "message" | "message_status" | "session_lost" | "ban_detected" | "qr_updated" | "connected",
  "data": { ... },
  "ts": "2026-05-09T12:34:56.789Z"
}
```

Retry exponencial: tries=5, backoff 1s/3s/9s/27s/81s.

## Observabilidade

- **Traces:** spans `whatsapp-baileys.*` exportados via OTLP HTTP pro Loki CT 100
- **Métricas Prometheus** em `/metrics`:
  - `whatsapp_baileys_session_state{instance_id,business_id}` (gauge)
  - `whatsapp_baileys_message_lag_ms{instance_id}` (histogram)
  - `whatsapp_baileys_send_total{instance_id,status}` (counter)
  - `whatsapp_baileys_recv_total{instance_id}` (counter)
  - `whatsapp_baileys_ban_detected_total{instance_id}` (counter)
  - `whatsapp_baileys_session_age_seconds{instance_id}` (gauge)
  - `whatsapp_baileys_webhook_dispatch_total{event,outcome}` (counter)

## Riscos conhecidos

Ver ARCHITECTURE.md §16.10. Resumidamente:

1. Mudança Meta TOS quebra Baileys → versão pinned + fallback Meta Cloud automático.
2. Wagner vira mantenedor de daemon Node → testes integração CI + canary deploy.
3. Cada instance ~80 MB RAM → CT 100 4 GB suporta ~30-40 instances (env `MAX_INSTANCES`).
4. Ban Meta no IP do CT 100 propaga cross-tenant → alarme + plano "rotacionar IP".
