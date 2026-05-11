# Langfuse self-host CT 100

> **US-INFRA-016 · [ADR 0132](../../memory/decisions/0132-langfuse-self-host-ct100.md)**
> Observabilidade GenAI pro Jana — recebe spans OTel emitidos pelo `LaravelAiSdkDriver`.

## O que é

Langfuse v3 self-host rodando em `langfuse.oimpresso.com` (CT 100 docker-host 192.168.0.50). Recebe traces de toda chamada de LLM feita pelo Jana — custo, latência, tokens, cache hit-rate, error-rate — e mostra dashboard agregado por business/modelo/tool.

**Por que CT 100, não Hostinger:** Langfuse v3 precisa ClickHouse + Postgres + Worker (3 daemons). Hostinger é shared hosting; daemons proibidos por [ADR 0062](../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md).

## Recursos no CT 100

- **RAM:** ~2.5GB (ClickHouse é o maior — ~1.5-2GB)
- **Disk:** ~25GB (cresce com retention; padrão 30 dias)
- **CPU:** ~0.5 core ocioso, picos de 1 core durante ingestion

⚠️ **Antes de subir, validar capacidade do CT 100:** `free -h && df -h /opt`. Se RAM total <8GB, considerar adicionar 2GB ao CT antes do deploy.

## Deploy (primeira vez)

```bash
# 1. SSH no CT 100 via Tailscale (Wagner pode precisar re-auth na 1ª vez)
tailscale ssh root@ct100-mcp

# 2. Clone do repo oimpresso.com no CT 100 (se ainda não tem)
test -d /opt/langfuse/code || git clone https://github.com/wagnerra23/oimpresso.com /opt/langfuse/code

# 3. Atualizar
cd /opt/langfuse/code && git pull

# 4. Preparar storage volumes
mkdir -p /opt/langfuse/data/postgres /opt/langfuse/data/clickhouse/data /opt/langfuse/data/clickhouse/logs
chown -R 1000:1000 /opt/langfuse/data/postgres
chown -R 101:101 /opt/langfuse/data/clickhouse  # uid do user clickhouse na imagem

# 5. Configurar secrets (.env não-commitado)
cd /opt/langfuse/code/docker/langfuse
cp .env.example .env
# Editar .env preenchendo os 5 secrets (gerar via openssl rand, salvar em Vaultwarden item "langfuse-ct100")

# 6. Subir stack
docker compose up -d

# 7. Aguardar healthchecks (~60s)
docker compose ps  # todos "healthy"

# 8. Validar Traefik route
curl -fsS https://langfuse.oimpresso.com/api/public/health
# Esperado: {"status":"OK"} ou similar

# 9. Acessar UI e criar org/project
# Browser: https://langfuse.oimpresso.com
# Login: criar conta admin (Wagner) — depois desabilitar signup quando time entrar
# Criar organization "oimpresso"
# Criar project "oimpresso-prod" → anotar Public Key (pk-lf-*) e Secret Key (sk-lf-*)

# 10. Salvar keys no Vaultwarden item "langfuse-keys" — vão pro Hostinger .env
```

## Após o deploy — wire no Laravel (Hostinger)

```bash
# No Hostinger (via SSH):
cd /home/u906587222/public_html
# Adicionar no .env:
#   LANGFUSE_HOST=https://langfuse.oimpresso.com
#   LANGFUSE_PUBLIC_KEY=pk-lf-...
#   LANGFUSE_SECRET_KEY=sk-lf-...

# Validação: emitir 1 chamada Jana qualquer, checar se aparece em
# https://langfuse.oimpresso.com/project/<id>/traces em <30s
```

> PR-1 da US-INFRA-016 codifica o exporter OTLP HTTP que usa essas keys.

## Backup

Volumes em `/opt/langfuse/data/` — adicionar ao plano de backup nightly do CT 100 (ou usar `restic` pra B2/R2).

**Sem backup, perde:**
- Histórico de spans (perda tolerável — só auditoria, não state crítico)
- Projects/users (recoverable — recriar manualmente em ~5min)

Recomendação: backup semanal de `/opt/langfuse/data/postgres` (~100MB compacted). ClickHouse data é volumosa mas substituível.

## Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `langfuse-web` crashloop com `Database connection refused` | postgres ainda não healthy | Aguardar 30s — depends_on resolve |
| `clickhouse-langfuse` crashloop com `ulimit` | host não permite 262144 nofile | Verificar `/etc/security/limits.conf` no CT 100 |
| Traefik 404 em `langfuse.oimpresso.com` | DNS não propagou OU label típo | `dig langfuse.oimpresso.com` + checar Traefik dashboard |
| Spans não aparecem no UI mas POST retorna 207 | Public/Secret key wrong no .env Hostinger | Re-gerar par no UI Langfuse + atualizar .env |
| ClickHouse out-of-disk | Retention default 30d cresceu além de 25GB | Reduzir retention via `langfuse-web` env `LANGFUSE_TRACES_RETENTION_DAYS=14` |

## Atualização

```bash
tailscale ssh root@ct100-mcp
cd /opt/langfuse/code && git pull
cd docker/langfuse
docker compose pull && docker compose up -d
docker compose ps  # confirmar healthy
```

**Major version upgrade** (v3 → v4 quando sair): seguir release notes Langfuse + backup postgres antes.

## Referências

- ADR 0132 (este escopo) — decisão de infra
- ADR 0053 — MCP server canônico (mesmo padrão de stack)
- ADR 0062 — separação runtime Hostinger ≠ CT 100
- ADR 0094 — Constituição v2 princípio 4 (loop fechado por métrica)
- US-INFRA-016 — task tracking PR-1..PR-4
- [Langfuse docs self-host](https://langfuse.com/docs/deployment/self-host)
