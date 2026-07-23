---
title: "RUNBOOK — Operação Langfuse self-host (CT 100)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Operação Langfuse self-host (CT 100)

> **ADR 0132** — Langfuse self-host CT 100 docker-host (192.168.0.50). Stack 6 services healthy 2026-05-10.
> URL: https://langfuse.oimpresso.com

## Stack atual

| Service | Imagem | RAM real | Função |
|---|---|---|---|
| postgres-langfuse | postgres:16-alpine | 83 MB | metadata (orgs/projects/users) |
| clickhouse-langfuse | clickhouse/clickhouse-server:24.3-alpine | 492 MB | spans/traces OLAP |
| minio-langfuse | minio/minio:latest | 78 MB | S3-compat event uploads |
| redis-langfuse | redis:7-alpine | 6 MB | queue assíncrona |
| langfuse-web | langfuse/langfuse:3 | 728 MB | UI + OTLP receiver |
| langfuse-worker | langfuse/langfuse-worker:3 | 374 MB | async processing |
| **TOTAL** | | **~1.76 GB** | |

## Comandos canônicos

### A. Status diário

```bash
# Stack containers
tailscale ssh root@ct100-mcp 'cd /opt/langfuse/code/docker/langfuse && docker compose ps'

# Health endpoint público
curl -sS https://langfuse.oimpresso.com/api/public/health

# RAM/CPU em uso
tailscale ssh root@ct100-mcp 'docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}" $(docker ps --filter "name=langfuse" -q)'
```

### B. Logs

```bash
# Web (Next.js)
tailscale ssh root@ct100-mcp 'docker logs --tail 50 langfuse-web 2>&1'

# Worker (async)
tailscale ssh root@ct100-mcp 'docker logs --tail 50 langfuse-worker 2>&1'

# ClickHouse errors (atenção bug IPv6 catalogado PR #521)
tailscale ssh root@ct100-mcp 'docker exec clickhouse-langfuse tail -50 /var/log/clickhouse-server/clickhouse-server.err.log 2>/dev/null'
```

### C. Upgrade Langfuse (minor/patch)

```bash
tailscale ssh root@ct100-mcp
cd /opt/langfuse/code && git pull origin main
cd docker/langfuse
docker compose pull       # pega imagens v3.X latest
docker compose up -d      # restart com healthcheck
sleep 90
docker compose ps         # confirmar healthy
curl -fsS https://langfuse.oimpresso.com/api/public/health
```

⚠️ **Major version upgrade (v3 → v4)**: ler release notes Langfuse antes; backup postgres obrigatório (procedure D).

### D. Backup (procedure obrigatória — P2 follow-up auto-cron)

```bash
# Manual nightly (TODO automatizar)
tailscale ssh root@ct100-mcp '
DATE=$(date +%Y-%m-%d)
mkdir -p /opt/langfuse/backups
docker exec postgres-langfuse pg_dump -U langfuse langfuse | gzip > /opt/langfuse/backups/langfuse-pg-${DATE}.sql.gz
# ClickHouse: usually expendable (spans são telemetria histórica)
# MinIO: copy event uploads em bursts mensais se quiser
ls -lh /opt/langfuse/backups/ | tail -7
'
```

**Restore postgres** (recovery após corrupção):
```bash
tailscale ssh root@ct100-mcp '
docker exec -i postgres-langfuse psql -U langfuse -d langfuse < /opt/langfuse/backups/langfuse-pg-YYYY-MM-DD.sql
'
```

## Troubleshooting (5 bugs catalogados em PR #521 — referência)

| # | Sintoma | Causa | Fix permanente já aplicado |
|---|---|---|---|
| 1 | ClickHouse `Listen [::]:8123 failed: EAI` | CT 100 LXC sem IPv6 | Volume mount `/opt/langfuse/config/clickhouse/listen-ipv4.xml` (commitado) |
| 2 | ClickHouse `(unhealthy)` mas ping OK | `localhost` → IPv6 first | Healthcheck `http://127.0.0.1:8123/ping` explicit |
| 3 | Prisma `P1013 invalid port` | `openssl rand -base64` com `/+` quebra URL | Gerar com `openssl rand -hex 24` |
| 4 | `LANGFUSE_S3_EVENT_UPLOAD_BUCKET undefined` | v3 exige S3 + Redis | Stack passou 4→6 services (MinIO + Redis) |
| 5 | langfuse-web `health: starting` ∞ | Next.js v16+ não binda 0.0.0.0 | `HOSTNAME=0.0.0.0` env |

**Outros sintomas comuns:**

| Sintoma | Diagnóstico | Fix |
|---|---|---|
| HTTP 503 em `langfuse.oimpresso.com` | CT 100 Proxmox down | Wagner Proxmox console + start CT 100 |
| Traces param de chegar (Langfuse UI vazio) | `LANGFUSE_HOST` Hostinger trocou OR keys revogadas | `ssh hostinger 'grep LANGFUSE ~/public_html/.env'` + reconciliar com UI Langfuse |
| Disk full em ClickHouse | retention default 30d cresceu além de 25GB | Reduzir `LANGFUSE_TRACES_RETENTION_DAYS=14` em web/.env |
| Cert LE expirou | Traefik não renovou | Procedure E abaixo |

### E. Cert LE manual retry (se Traefik falhou auto-renovação)

```bash
tailscale ssh root@ct100-mcp '
# Backup acme.json
cp /var/lib/docker/volumes/traefik-acme/_data/acme.json /tmp/acme.bak.json

# Remove entry langfuse failed
python3 -c "
import json
with open(\"/var/lib/docker/volumes/traefik-acme/_data/acme.json\") as f: d = json.load(f)
for resolver in d.values():
    if isinstance(resolver, dict) and \"Certificates\" in resolver:
        resolver[\"Certificates\"] = [c for c in (resolver[\"Certificates\"] or []) if \"langfuse.oimpresso.com\" not in (c.get(\"domain\", {}).get(\"main\", \"\") or \"\")]
with open(\"/var/lib/docker/volumes/traefik-acme/_data/acme.json\", \"w\") as f: json.dump(d, f, indent=2)
"

# Restart Traefik pra retry
docker restart traefik
sleep 60

# Verificar cert
docker logs traefik 2>&1 | grep -i "langfuse\|cert" | tail -10
curl -sS -I https://langfuse.oimpresso.com | head -3
'
```

## Secrets

Inventário em [RUNBOOK-credenciais-hierarquia.md](RUNBOOK-credenciais-hierarquia.md). Resumo:

- **Hostinger `.env`** (tier 2 prod): `LANGFUSE_HOST`, `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY`
- **CT 100 `/opt/langfuse/code/docker/langfuse/.env`** (tier 3 stack): 5 secrets stack (POSTGRES_PASSWORD, CLICKHOUSE_PASSWORD, NEXTAUTH_SECRET, SALT, **ENCRYPTION_KEY NUNCA MUDA**, MINIO_ROOT_USER, MINIO_ROOT_PASSWORD)

## Métricas de saúde + audit

```bash
# Audit automático Constituição v2 — check observability_pipeline (ADR 0133)
ssh hostinger 'cd ~/public_html && php artisan jana:system-audit'

# Spans últimas 24h via API Langfuse (programmatic)
curl -sS -u "${LANGFUSE_PUBLIC_KEY}:${LANGFUSE_SECRET_KEY}" \
  "https://langfuse.oimpresso.com/api/public/traces?limit=1" | jq '.meta.totalItems'
```

## Tasks follow-up conhecidas

- **P2** Backup nightly automatizado postgres + cron Hostinger
- **P2** Alerta proativo cert LE >7d de expirar
- **P2** Dashboard saved view `/copiloto/admin/observability` (4 widgets — US-INFRA-016 PR-4)
- **P3** Auth OAuth/SAML pra time (atual: email/senha + AUTH_DISABLE_SIGNUP)

## Referências

- [ADR 0132](../../decisions/0132-langfuse-self-host-ct100.md) — Stack decision + 6 review_triggers
- [ADR 0051](../../decisions/0051-schema-proprio-adapter-otel-genai.md) — Schema OTel GenAI já emitido pelo Jana
- [PR #521](https://github.com/wagnerra23/oimpresso.com/pull/521) — 5 bugs deploy catalogados
- [PR #522](https://github.com/wagnerra23/oimpresso.com/pull/522) — OtlpHttpHandler exporter
- [PR #523](https://github.com/wagnerra23/oimpresso.com/pull/523) — dual-emit `.str` fallback
- [Langfuse docs self-host](https://langfuse.com/docs/deployment/self-host)
