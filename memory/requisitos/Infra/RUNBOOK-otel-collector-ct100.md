# RUNBOOK — Deploy OpenTelemetry Collector + Grafana Tempo CT 100

> **ADR 0162** — observability tracing canônico oimpresso (Wave 26 governance).
> **Stack:** otel-collector-contrib 0.110.0 + Tempo 2.6.0, docker-compose, rede `oimpresso-mcp` reusada.
> **Anti-PII:** filtro ativo descarta `http.url`, headers de auth, `user.email/cpf/cnpj`, `db.statement`; faz hash de `business_id/user_id` (Tier 0 ADR 0093).
> **Sampling:** 5% random + 100% erros + 100% ops >2s + 100% FSM transitions + 100% Jana LLM.

---

## 1. Pré-requisitos

- ✅ CT 100 docker-host (192.168.0.50) acessível via Tailscale (`tailscale ssh root@ct100-mcp`)
- ✅ Rede docker `oimpresso-mcp` JÁ EXISTE (criada pelo stack `mcp.oimpresso.com` Centrifugo+FrankenPHP). Verificar: `docker network ls | grep oimpresso-mcp`
- ✅ ~1.5 GB RAM livre no CT 100 (collector ~500MB + tempo ~1GB com retention 7d)
- ✅ ~10 GB disco livre em `/var/lib/docker/volumes/` (Tempo 7d retention, estimativa inicial 5-50 GB conforme tráfego)
- ✅ Vaultwarden item `oimpresso/otel/ingest-token` criado com token Bearer pra endpoint Laravel custom `POST /api/observability/ingest` (Wave 26 Agent 3 cria endpoint)
- ⛔ NUNCA deployar este stack no Hostinger (Tier 0 ADR 0062 — separação runtime)

---

## 2. Deploy passo-a-passo

### 2.1 Copiar config pro CT 100

Os arquivos canônicos vivem em git em `infra/ct100/otel/`. Sync via:

```bash
# Local (Windows ou WSL):
scp infra/ct100/otel/docker-compose.yml \
    infra/ct100/otel/otel-collector-config.yaml \
    infra/ct100/otel/tempo.yaml \
    root@ct100-mcp:/opt/oimpresso/otel/

# OU via git clone direto no CT 100 (preferido — versionado):
tailscale ssh root@ct100-mcp
mkdir -p /opt/oimpresso/otel
cd /opt/oimpresso
git clone git@github.com:wagnerra23/oimpresso.com.git canon
ln -sf /opt/oimpresso/canon/infra/ct100/otel/docker-compose.yml /opt/oimpresso/otel/docker-compose.yml
ln -sf /opt/oimpresso/canon/infra/ct100/otel/otel-collector-config.yaml /opt/oimpresso/otel/otel-collector-config.yaml
ln -sf /opt/oimpresso/canon/infra/ct100/otel/tempo.yaml /opt/oimpresso/otel/tempo.yaml
```

### 2.2 Criar `.env` com token Bearer

```bash
tailscale ssh root@ct100-mcp
cd /opt/oimpresso/otel/
cat > .env <<EOF
OIMPRESSO_OTEL_INGEST_TOKEN=<copiar-do-Vaultwarden:oimpresso/otel/ingest-token>
EOF
chmod 600 .env
```

### 2.3 Subir stack

```bash
cd /opt/oimpresso/otel/
docker compose pull       # baixa imagens pinadas (0.110.0 + 2.6.0)
docker compose up -d
docker compose ps         # ambos devem estar Up + healthy
```

### 2.4 Health check imediato

```bash
# Collector health endpoint:
curl -s http://localhost:13133/ && echo " -- collector OK"
# Esperado: status 200 + body vazio

# Tempo ready endpoint:
curl -s http://localhost:3200/ready
# Esperado: "ready"

# Collector métricas internas (Prometheus scrape):
curl -s http://localhost:8888/metrics | head -20
```

---

## 3. Validation (smoke trace local)

### 3.1 Disparar trace OTLP HTTP manual

```bash
tailscale ssh root@ct100-mcp
curl -X POST http://localhost:4318/v1/traces \
  -H 'Content-Type: application/json' \
  -d '{
    "resourceSpans": [{
      "resource": {
        "attributes": [
          {"key": "service.name", "value": {"stringValue": "oimpresso-smoke-test"}}
        ]
      },
      "scopeSpans": [{
        "spans": [{
          "traceId": "00112233445566778899aabbccddeeff",
          "spanId": "0011223344556677",
          "name": "smoke.span",
          "startTimeUnixNano": "'$(date +%s)000000000'",
          "endTimeUnixNano": "'$(($(date +%s)+1))000000000'",
          "kind": 1
        }]
      }]
    }]
  }'
# Esperado: {"partialSuccess":{}}
```

### 3.2 Confirmar trace chegou no Tempo

Aguardar ~35s (decision_wait do tail_sampling = 30s + flush ~5s):

```bash
curl -s "http://localhost:3200/api/search?tags=service.name%3Doimpresso-smoke-test" | jq
# Esperado: array `traces` com pelo menos 1 trace (caiu na regra random-5pct ou status_code)
```

### 3.3 Conferir logs collector

```bash
docker logs oimpresso-otel-collector --tail 50
# Esperado: lines "TracesExporter ... " sem erro
```

---

## 4. Wagner ativa em produção (`.env` Hostinger)

> ⚠️ Só ativar APÓS smoke validation OK (passos 2-3) + Wagner alinhar com Felipe/Maiara que vai ter tráfego de observabilidade saindo do Hostinger.

```bash
# .env Hostinger (oimpresso/public/.env):
OTEL_ENABLED=true
OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=http://mcp.oimpresso.com:4318/v1/traces
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_SERVICE_NAME=oimpresso-laravel
OTEL_SAMPLE_RATE=0.05
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=production,oimpresso.runtime=hostinger
```

Restart PHP-FPM/Octane Hostinger (`php artisan config:clear && php artisan optimize`).

---

## 5. Soak 7 dias antes de aumentar sampling

| Dia | Métrica observada | Alvo |
|-----|-------------------|------|
| 1-2 | Disk usage `/var/tempo/blocks` | <2 GB |
| 1-2 | Collector dropped spans (métrica `otelcol_processor_dropped_spans`) | 0 |
| 3-5 | Tempo ingest rate (`otelcol_exporter_sent_spans{exporter=otlphttp/tempo}`) | <200 spans/s |
| 6-7 | Latência endpoint Laravel custom (`/api/observability/ingest` p95) | <50 ms |
| 7 | Verificar trace de incidente real (filtrar `status_code=ERROR`) | Achou ≥ 1 caso útil |

Se métricas OK: aumentar `OTEL_SAMPLE_RATE` no Hostinger pra `0.10` (10%).
Se métricas ruins: rollback (próxima seção).

---

## 6. Rollback (qualquer hora)

### 6.1 Desligar SDK no app (rápido, zero downtime collector)

```bash
# .env Hostinger:
OTEL_SDK_DISABLED=true
# OU:
OTEL_ENABLED=false
```

Apps param de exportar; collector segue rodando vazio. Wagner reativa quando quiser.

### 6.2 Desligar collector + Tempo (drástico)

```bash
tailscale ssh root@ct100-mcp
cd /opt/oimpresso/otel/
docker compose down       # mantém volume tempo-data
# Pra apagar dados também:
docker compose down -v    # apaga volume oimpresso-tempo-data
```

---

## 7. Troubleshooting

### 7.1 Collector não sobe

```bash
docker logs oimpresso-otel-collector --tail 100
# Erros comuns:
#   - "config file invalid" → erro YAML otel-collector-config.yaml; validar com `yq`
#   - "network oimpresso-mcp not found" → criar rede ou subir stack mcp.oimpresso.com primeiro
#   - "OIMPRESSO_OTEL_INGEST_TOKEN not set" → faltou .env (passo 2.2)
```

### 7.2 Tempo não responde `/ready`

```bash
docker logs oimpresso-tempo --tail 100
# Erros comuns:
#   - "wal directory not writable" → permissão volume; `docker exec oimpresso-tempo ls -la /var/tempo`
#   - "ingester not ready yet" → aguardar 30-60s após startup
```

### 7.3 Traces não aparecem em Tempo

```bash
# Confere métricas internas collector:
curl -s localhost:8888/metrics | grep -E '(otelcol_receiver_accepted_spans|otelcol_processor_dropped_spans|otelcol_exporter_sent_spans)'

# Se accepted > 0 mas sent = 0 → problema no exporter (rede docker, Tempo down)
# Se accepted = 0 → app não está exportando (checa Hostinger .env)
# Se dropped > 0 → batch overflow ou tail_sampling num_traces excedido
```

### 7.4 Endpoint Laravel `/api/observability/ingest` 401

Token Bearer errado. Conferir Vaultwarden item `oimpresso/otel/ingest-token` casa com `.env` Hostinger Laravel (`OIMPRESSO_OTEL_INGEST_TOKEN`).

---

## 8. Disk usage e backup

### 8.1 Estimativa Tempo 7d retention

| Tráfego | Storage 7d estimado |
|---------|---------------------|
| 100 traces/dia | ~50 MB |
| 1k traces/dia | ~500 MB |
| 10k traces/dia | ~5 GB |
| 100k traces/dia (soak alvo após sampling 10%) | ~50 GB |

Monitorar: `docker exec oimpresso-tempo du -sh /var/tempo/blocks`

### 8.2 Backup snapshot

Tempo storage é immutable blocks após flush. Cron diário:

```bash
# /etc/cron.d/tempo-backup
0 4 * * * root tar czf /opt/backups/tempo-$(date +\%F).tar.gz -C /var/lib/docker/volumes/oimpresso-tempo-data/_data . && find /opt/backups -name 'tempo-*.tar.gz' -mtime +7 -delete
```

> ⚠️ Backup é OPCIONAL — traces são observabilidade ephemera (não dado de negócio). Aceitar perda em desastre é razoável.

---

## 9. Custo (CT 100, zero infra adicional)

- ✅ Docker images: ~250 MB (collector 100 MB + Tempo 150 MB)
- ✅ RAM steady-state: ~1.5 GB
- ✅ Disco 7d: 5-50 GB conforme sampling/tráfego
- ✅ Network: tráfego interno docker oimpresso-mcp (zero cobrança Tailscale/Hostinger)
- ✅ Egress externo: ZERO (não exporta pra SaaS) — tudo self-hosted

Hostinger envia traces HTTPS pra `mcp.oimpresso.com:4318` → ~1-10 KB/trace × sampling_rate × tráfego.

---

## 10. Próximas waves (não-bloqueante deste runbook)

- **Wave 27+ SLO dashboard:** Grafana datasource Tempo + queries TraceQL pra SLO Jana p95 < 5s, FSM transition success rate > 99%
- **Wave 28+ alerting:** Alertmanager regras (Tempo metrics-generator + Prometheus) pra dropped spans > 100/min, error rate > 5%
- **Wave 29+ trace ↔ log correlation:** wire Langfuse ([RUNBOOK-langfuse-ct100.md](RUNBOOK-langfuse-ct100.md)) trace_id no LLM span span attribute pra deeplink Grafana → Langfuse

---

**Última atualização:** 2026-05-17 — Wave 26 Agent 4 (ADR 0162 deploy declarativo).
