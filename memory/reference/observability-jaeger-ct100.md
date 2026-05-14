---
name: Observability — Jaeger CT 100 (deploy, OTel daemon Baileys, troubleshoot, pegadinhas)
description: Jaeger all-in-one no CT 100 (UI + OTLP receiver in-memory) — onde mora, como conecta com daemon Baileys via network observability, comandos canônicos, pegadinhas catalogadas, evolução pra storage persistente (Tempo+S3 ou ES). Deploy 2026-05-14 (US-WA-083 OTel ponta-a-ponta).
type: reference
---
# Jaeger CT 100 — distributed tracing daemon Baileys

> Deploy 2026-05-14 (US-WA-083 OTel). Single binary, UI embedded, OTLP receiver. Cobre daemon Baileys + propagação W3C `traceparent` pro Laravel/Hostinger via lightweight middleware (sem PECL ext).

## Onde mora

- **Compose:** `/opt/observability/jaeger/docker-compose.yml` (CT 100)
- **Container:** `jaeger` (single)
- **Imagem:** `jaegertracing/all-in-one:1.60`
- **Network:** `observability` (external — compartilhada com whatsapp-baileys)
- **Storage:** **in-memory** (volátil — restart perde traces), 50k traces max

## Endpoints

| Porta | Protocolo | Pra quê |
|---|---|---|
| `127.0.0.1:16686` | HTTP UI | Jaeger UI (Traefik → `jaeger.oimpresso.com` quando DNS configurar) |
| `4317` | OTLP gRPC | Receiver gRPC (interno network) |
| `4318` | OTLP HTTP | Receiver HTTP — **daemon Baileys usa este** (`http://jaeger:4318`) |
| `14269` | HTTP admin | Healthcheck (`/`) |

## docker-compose.yml canônico

```yaml
services:
  jaeger:
    image: jaegertracing/all-in-one:1.60
    container_name: jaeger
    restart: unless-stopped
    environment:
      COLLECTOR_OTLP_ENABLED: "true"
      COLLECTOR_ZIPKIN_HOST_PORT: ":9411"
      SPAN_STORAGE_TYPE: memory
      QUERY_BASE_PATH: /
      MEMORY_MAX_TRACES: "50000"
    ports:
      - "127.0.0.1:16686:16686"  # UI só via tailscale ou Traefik
      - "4317:4317"
      - "4318:4318"
    networks:
      - observability
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.jaeger.rule=Host(`jaeger.oimpresso.com`)"
      - "traefik.http.routers.jaeger.entrypoints=websecure"
      - "traefik.http.routers.jaeger.tls.certresolver=letsencrypt"
      - "traefik.http.services.jaeger.loadbalancer.server.port=16686"
      - "traefik.docker.network=observability"
    healthcheck:
      test: ["CMD-SHELL", "wget -qO- http://localhost:14269/ || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3

networks:
  observability:
    external: true
```

## Daemon Baileys — env vars que conectam OTel

No `docker-compose.yml` do whatsapp-baileys (`/opt/whatsapp-baileys/build/docker-compose.yml`):

```yaml
services:
  whatsapp-baileys:
    environment:
      OTEL_ENABLED: "true"
      OTEL_EXPORTER_OTLP_ENDPOINT: "http://jaeger:4318"
      OTEL_SERVICE_NAME: "whatsapp-baileys-daemon"
      ...
    networks:
      - default          # docker-host_default (Traefik público + Hostinger reach)
      - observability    # jaeger reach

networks:
  default:
    name: docker-host_default
    external: true
  observability:
    name: observability
    external: true
```

**Sem `observability` na lista de networks do daemon, ele não enxerga `jaeger:4318` → SDK loga erro de conexão.**

## Como funciona ponta-a-ponta (US-WA-083)

```
[Baileys daemon CT 100]
    │
    │ (1) Evento webhook (ex: history.sync)
    ▼
[WebhookDispatcher.ts]
    │
    │ (2) tracer.startSpan('webhook.dispatch')
    │     atributos: whatsapp.event, instance_id, business_uuid
    │
    │ (3) propagation.inject(context, headers)
    │     → injeta `traceparent: 00-{trace_id 32hex}-{parent_id 16hex}-01`
    ▼
[HTTP POST /api/atendimento/channels/baileys/{uuid}]
    │
    │ (4) Hostinger Laravel middleware PropagateTraceparent
    │     → extrai traceparent
    │     → Log::withContext(['trace_id', 'parent_span_id', 'sampled'])
    ▼
[Logs Laravel já carregam trace_id em TODOS subsequent log entries]
    │
    │ (5) Daemon ASYNC: SDK exporta span batch p/ OTLP /v1/traces
    ▼
[Jaeger ingests via 4318]
    │
    │ (6) Wagner abre UI → busca trace_id → vê span tree
    ▼
[Trace completo daemon→Hostinger correlacionado]
```

## Comandos canônicos

### Validar daemon→jaeger conectados
```bash
tailscale ssh root@ct100-mcp 'curl -s http://127.0.0.1:16686/api/services'
# Deve retornar:
# {"data":["jaeger-all-in-one","whatsapp-baileys-daemon"], ...}
```

### Acessar UI sem DNS configurado
```bash
# Local
tailscale ssh -L 16686:127.0.0.1:16686 root@ct100-mcp
# Abre http://localhost:16686
```

### Buscar trace por ID
```bash
tailscale ssh root@ct100-mcp 'curl -s "http://127.0.0.1:16686/api/traces/{trace_id}"'
```

### Listar últimos traces de um service
```bash
tailscale ssh root@ct100-mcp 'curl -s "http://127.0.0.1:16686/api/traces?service=whatsapp-baileys-daemon&limit=20"'
```

### Restart Jaeger (perde traces in-memory!)
```bash
tailscale ssh root@ct100-mcp 'cd /opt/observability/jaeger && docker compose restart'
```

## Pegadinhas catalogadas

### J1. Imagem `jaegertracing/all-in-one:1.62` NÃO existe
Sempre conferir [docker hub tags](https://hub.docker.com/r/jaegertracing/all-in-one/tags). `1.60` funcionou 2026-05-14.

### J2. Daemon Baileys não envia spans pra Jaeger
**Sintomas:** `curl /api/services` só mostra `jaeger-all-in-one`, não `whatsapp-baileys-daemon`.

**Checklist de debug:**
1. `OTEL_ENABLED=true` no compose daemon? `grep OTEL /opt/whatsapp-baileys/build/docker-compose.yml`
2. `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318` setado?
3. Daemon na network `observability`? `docker network inspect observability --format "{{range .Containers}}{{.Name}} {{end}}"` — deve listar AMBOS `jaeger` e `whatsapp-baileys`
4. Daemon resolve DNS `jaeger`? `docker exec whatsapp-baileys node -e "require('dns').lookup('jaeger', console.log)"` → deve retornar IP da network observability
5. Daemon logs têm erro OTel? `docker logs whatsapp-baileys 2>&1 | grep -iE "otel|exporter|jaeger"`
6. Daemon `/health` mostra `otel: true`? Se `false` → env vars não foram absorvidas (recreate o container, não restart)

### J3. Traces somem ao restart Jaeger
**Why:** `SPAN_STORAGE_TYPE: memory`. Trade-off aceito pra simplicidade nesta fase.

**Quando migrar:** quando Wagner precisar audit forense >24h. Veja [§ Evolução futura](#evolução-futura).

### J4. DNS `jaeger.oimpresso.com` não resolve
**Workaround atual:** `tailscale ssh -L 16686:...` (tunnel).

**Fix permanente:** Cloudflare API → CNAME `jaeger.oimpresso.com → mcp.oimpresso.com` (já tem Traefik em mcp). Esforço 2min.

### J5. `traceparent` chega no Hostinger mas log Laravel NÃO carrega trace_id
**Why:** middleware `PropagateTraceparent` não está na rota.

**Check:**
```bash
grep -A 5 "channels/baileys" Modules/Whatsapp/Routes/api.php
# Deve ter middleware 'whatsapp.otel.propagate' na lista
```

### J6. OTel SDK init falha silenciosamente
**Sintoma:** daemon `/health` mostra `otel: true` mas Jaeger não recebe spans.

**Why possível:** `OTEL_EXPORTER_OTLP_ENDPOINT` válido mas Jaeger não respondendo (down/network split).

**Debug:**
```bash
docker exec whatsapp-baileys node -e "
const { OTLPTraceExporter } = require('@opentelemetry/exporter-trace-otlp-http');
const exp = new OTLPTraceExporter({ url: 'http://jaeger:4318/v1/traces' });
console.log('exporter url:', exp.url);
"
```

## Evolução futura

### Storage persistente (escolher 1)
**Opção A: Tempo + S3** (mais escalável)
- Tempo single-binary + MinIO local (já tem `minio-langfuse` no CT 100)
- Read API compatível com Jaeger UI
- Esforço ~4h

**Opção B: Jaeger + Elasticsearch**
- Substitui storage memory por ES
- ES = mais um container, custo memória ~1-2GB
- Esforço ~3h

**Opção C: Jaeger + ClickHouse**
- Reuse `clickhouse-langfuse` container existente
- Mais experimental, menos comunidade
- Esforço ~5h

### Grafana datasource Tempo/Jaeger
- Adicionar Grafana standalone no CT 100 (ainda não rodando)
- Configurar Tempo (ou Jaeger) como datasource → trace_id virar link clickável em dashboards
- Junto com dashboard Grafana já criado em `infra/grafana/dashboards/whatsapp-baileys.json`
- Esforço ~2h

### Sampling adaptive (anti-flood)
- Hoje: 100% sample (volume baixo, ok)
- Futuro: tail-based sampling (só guarda traces com erro ou latency > p95)
- Esforço ~2h via OTel Collector intermediário

## Métricas de saúde Jaeger

`curl http://127.0.0.1:14269/` (admin endpoint, expõe healthcheck + métricas Prometheus).

- `jaeger_collector_traces_received_total` — quantidade total de traces
- `jaeger_collector_spans_received_total` — quantidade de spans
- `jaeger_collector_save_latency_bucket` — latência ingest

## Referências

- [Jaeger docs](https://www.jaegertracing.io/docs/1.60/)
- [W3C Trace Context spec](https://www.w3.org/TR/trace-context/)
- Daemon source: [`Modules/Whatsapp/daemon-node/src/observability/otel.ts`](../../Modules/Whatsapp/daemon-node/src/observability/otel.ts)
- Hostinger middleware: [`Modules/Whatsapp/Http/Middleware/PropagateTraceparent.php`](../../Modules/Whatsapp/Http/Middleware/PropagateTraceparent.php)
- Config: [`config/otel.php`](../../config/otel.php)
- Session log deploy: [`memory/sessions/2026-05-14-maratona-whatsapp-onda-1-2-otel-completa.md`](../sessions/2026-05-14-maratona-whatsapp-onda-1-2-otel-completa.md)

---

**Última atualização:** 2026-05-14 — Deploy inicial Jaeger all-in-one CT 100. Daemon Baileys reportando spans.
