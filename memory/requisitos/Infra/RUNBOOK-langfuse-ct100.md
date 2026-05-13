# RUNBOOK — Deploy Langfuse v3 self-host CT 100 + wire PHP client

> **ADR 0132** — Langfuse v3 self-host docker-host CT 100 (192.168.0.50). Status atual: STACK LIVE 2026-05-10 (vide [RUNBOOK-langfuse-operacional.md](RUNBOOK-langfuse-ct100-operacional.md)).
> **Este RUNBOOK** cobre **deploy + wire PHP client + RAGAS scores** (cliente Onda 4 P0 — fechar observability LLM 40% → ~95%).

---

## Pré-requisitos

- CT 100 docker-host (192.168.0.50) acessível via Tailscale (`tailscale ssh root@ct100-mcp`)
- Traefik com `acme` resolver válido pra `*.oimpresso.com`
- Vaultwarden disponível em `vault.oimpresso.com` (pra salvar keys)
- Hostinger (app web) com permissão de outbound HTTPS pra `langfuse.oimpresso.com` (Tier 0 — não há restrição padrão Hostinger)
- ~1.8 GB RAM livre no CT 100 (postgres + clickhouse + minio + redis + 2× langfuse)

---

## Fase 1 — Stack docker-compose (CT 100)

> ⚠️ Se já existe (verifique `tailscale ssh root@ct100-mcp 'docker ps | grep langfuse'`) pular direto pra Fase 3.

### 1.1 Clone repo upstream em `/opt/langfuse/code`

```bash
tailscale ssh root@ct100-mcp
mkdir -p /opt/langfuse
cd /opt/langfuse
git clone https://github.com/langfuse/langfuse code
cd code/docker/langfuse
```

### 1.2 `.env` canônico

```bash
# Em /opt/langfuse/code/docker/langfuse/.env
NEXTAUTH_SECRET=$(openssl rand -hex 32)
SALT=$(openssl rand -hex 32)
ENCRYPTION_KEY=$(openssl rand -hex 32)
DATABASE_URL=postgresql://langfuse:CHANGE-ME-STRONG@postgres-langfuse:5432/langfuse?sslmode=disable
CLICKHOUSE_URL=http://clickhouse-langfuse:8123
CLICKHOUSE_USER=default
CLICKHOUSE_PASSWORD=CHANGE-ME-CK
REDIS_HOST=redis-langfuse
REDIS_PORT=6379
REDIS_AUTH=CHANGE-ME-REDIS
LANGFUSE_S3_EVENT_UPLOAD_BUCKET=langfuse
LANGFUSE_S3_EVENT_UPLOAD_REGION=us-east-1
LANGFUSE_S3_EVENT_UPLOAD_ACCESS_KEY_ID=minio
LANGFUSE_S3_EVENT_UPLOAD_SECRET_ACCESS_KEY=CHANGE-ME-MINIO
LANGFUSE_S3_EVENT_UPLOAD_ENDPOINT=http://minio-langfuse:9000
LANGFUSE_S3_EVENT_UPLOAD_FORCE_PATH_STYLE=true
NEXTAUTH_URL=https://langfuse.oimpresso.com
TELEMETRY_ENABLED=false
LANGFUSE_ENABLE_EXPERIMENTAL_FEATURES=false
```

> Salve todas senhas no Vaultwarden → folder `Infra/Langfuse` antes de continuar.

### 1.3 Traefik labels + IP-whitelist (cuidado: rede empresa)

Em `docker-compose.yml`, no service `langfuse-web`, adicione:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.langfuse.rule=Host(`langfuse.oimpresso.com`)"
  - "traefik.http.routers.langfuse.entrypoints=websecure"
  - "traefik.http.routers.langfuse.tls.certresolver=acme"
  - "traefik.http.routers.langfuse.middlewares=langfuse-ipwhitelist"
  - "traefik.http.middlewares.langfuse-ipwhitelist.ipallowlist.sourcerange=177.74.67.30/32,192.168.0.0/16,100.99.0.0/16"
  - "traefik.http.services.langfuse.loadbalancer.server.port=3000"
```

> Whitelist: Hostinger NAT (177.74.67.30), LAN empresa (192.168.0.0/16), Tailscale (100.99.0.0/16).

### 1.4 Subida + verificação

```bash
docker compose up -d
sleep 90 # langfuse-web demora pra healthcheck pegar
docker compose ps  # esperar TODOS 'healthy'
curl -fsS https://langfuse.oimpresso.com/api/public/health
# resposta esperada: {"status":"OK","version":"3.x.x"}
```

---

## Fase 2 — First-run setup (browser + keys)

> Wagner faz isso manualmente — gerar keys NÃO automatizável.

1. Abrir `https://langfuse.oimpresso.com` no browser empresa (IP-whitelist exige LAN).
2. Sign up Wagner como primeiro admin (`wagner@oimpresso.com`).
3. Criar Organization `oimpresso`.
4. Criar Project `oimpresso-prod`.
5. Em **Settings → API Keys → Create new API keys**:
   - Copiar **Public Key** (`pk-lf-...`)
   - Copiar **Secret Key** (`sk-lf-...`) — **só aparece uma vez**.
6. Salvar AMBAS no **Vaultwarden** → folder `Infra/Langfuse` → item `API Keys oimpresso-prod`.

---

## Fase 3 — Wire PHP client (Hostinger + CT 100)

> **Status atual:** Cliente PHP + Job + Service Provider binding + Pest test JÁ entregues no PR Onda 4 (Agent L1). Falta só popular `.env` Hostinger.

### 3.1 `.env` Hostinger (via SSH com warm-up — vide `runtime-rules-hostinger-ct100`)

```bash
# Warm-up 5 hits curl IPv4 (ver CLAUDE.md §SSH Hostinger)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 << 'EOF'
cd ~/domains/oimpresso.com/public_html
cat >> .env << 'ENV_VARS'

# ADR 0132 — Langfuse observability LLM
LANGFUSE_ENABLED=true
LANGFUSE_HOST=https://langfuse.oimpresso.com
LANGFUSE_PUBLIC_KEY=pk-lf-COLAR-DO-VAULTWARDEN
LANGFUSE_SECRET_KEY=sk-lf-COLAR-DO-VAULTWARDEN
LANGFUSE_DISPATCH=queue
LANGFUSE_SAMPLE_RATE=1.0
LANGFUSE_REDACT_PII=true
LANGFUSE_RELEASE=$(git rev-parse --short HEAD)
LANGFUSE_ENV=production
ENV_VARS
php artisan config:cache
EOF
```

> ⚠️ NUNCA colar keys no chat — usar referência Vaultwarden.

### 3.2 Validar wire — Pest local (CI já roda automático)

```bash
cd D:/oimpresso.com
./vendor/bin/pest Modules/Jana/Tests/Feature/Telemetry/
# esperado: 10 passed
```

### 3.3 Smoke prod (post-deploy)

1. Login `https://oimpresso.com` como Wagner (biz=1).
2. Abrir Jana chat → mandar 1 mensagem qualquer.
3. Em `https://langfuse.oimpresso.com/project/{proj-id}/traces` confirmar:
   - 1 trace `jana-chat` apareceu (filtrar last 5 min)
   - Metadata mostra `business_id=1`, `user_id={wagner_id}`
   - Generation child com `model=gpt-4o-mini`, tokens > 0, duration_ms > 0

---

## Fase 4 — Wire RAGAS scores → Langfuse (PR #772 follow-up)

> **Pipeline H4** já existe em `Modules/Jana/Services/Ragas/RagasJudgeService.php` (4 métricas, gpt-4o-mini judge). Falta wire pra emitir `recordScore` ao final.

### 4.1 Patch sugerido em `JanaRagasEvalCommand`:

No fluxo onde RagasJudgeService.scoreFaithfulness/etc retorna, capturar `traceId` da pergunta avaliada e:

```php
app(\Modules\Jana\Services\Telemetry\LangfuseClient::class)
    ->recordScore($traceId, 'ragas_faithfulness', $faithfulnessScore, 'judge: gpt-4o-mini');
```

Repetir pras 4 métricas (`ragas_faithfulness`, `ragas_relevancy`, `ragas_precision`, `ragas_recall`).

### 4.2 Validar dashboard Langfuse

`https://langfuse.oimpresso.com/project/{id}/scores` deve listar histórico das 4 métricas com sparkline temporal.

---

## Fase 5 — Tools LLM instrumentadas (cobertura ~95%)

Quando o cliente PHP estiver wired no `LaravelAiSdkDriver` (já feito), todas chamadas chat são traced. Pra fechar 100% das tools LLM canônicas listadas em `config('langfuse.instrumented_tools')`, adicionar `startTrace` + `endTrace` em:

| Tool / Service | Arquivo | Onde adicionar |
|---|---|---|
| `kb-answer` | `Modules/Jana/Mcp/Tools/KbAnswerTool.php` | método `handle()` — wrap retrieval + LLM call |
| `handoff-fetch-summarized` | `Modules/Jana/Mcp/Tools/HandoffFetchSummarizedTool.php` | `summarize()` |
| `handoff-diff` | `Modules/Jana/Mcp/Tools/HandoffDiffTool.php` | `diff()` |
| `weekly-digest` | `Modules/Jana/Console/Commands/JanaWeeklyDigestCommand.php` | `handle()` |
| `brief-fetch` | `Modules/Jana/Services/BriefDiarioService.php` | já parcial via `BriefDiarioAgent` — adicionar trace |

Cada tool segue mesmo pattern: `startTrace(tool: 'X', business_id: $biz)` → operação → `endTrace($traceId, output: $resultado)`. Ver `LaravelAiSdkDriver::emitirLangfuseTrace()` como template.

---

## Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `curl /api/public/health` HTTP 502 | langfuse-web crash loop | `docker logs langfuse-web` — provavelmente DB postgres unhealthy |
| Traces não aparecem no dashboard | Worker não processou queue | `docker logs langfuse-worker` — redis auth fail comum |
| `Unauthorized` em ingestion | Keys erradas no `.env` Hostinger | Comparar com Vaultwarden, rodar `php artisan config:clear` |
| ClickHouse OOM | RAM 492MB excedida | Aumentar `MEMORY=2gb` no compose ou desabilitar telemetry |
| Bug IPv6 (ADR 0132 catalogado PR #521) | ClickHouse não bind IPv6 | Já fix upstream v3.40+ |

Operação contínua: ver [RUNBOOK-langfuse-operacional.md](RUNBOOK-langfuse-operacional.md).

---

## Custo e capacidade

- **RAM CT 100:** ~1.76 GB (postgres 83 MB + clickhouse 492 MB + minio 78 MB + redis 6 MB + langfuse-web 728 MB + langfuse-worker 374 MB)
- **Disco:** ~500 MB inicial; cresce ~10 MB/dia em uso típico (vol < 1M events/mês)
- **CPU idle:** < 5%
- **Free tier self-host:** ilimitado eventos. Plano cloud Langfuse paga acima 50k/mês — irrelevante self-host.
- **Custo OpenAI judge RAGAS:** ~$0.016/semana (4 métricas × 5 perguntas × 2 suites × 1 run = 40 chamadas gpt-4o-mini)

---

## Métricas pós-deploy

| Métrica | Antes (sem Langfuse) | Depois (com Langfuse + RAGAS wire) |
|---|---|---|
| Cobertura observability LLM | ~40% (logs estruturados channel `otel-gen-ai`) | ~95% (traces visualizáveis + cost tracking + RAGAS gráficos) |
| Time-to-debug LLM-issue | ~30 min (grep logs + correlacionar) | ~3 min (filtrar dashboard por business_id/duration) |
| RAGAS visibilidade | 🟡 pipeline existe, JSON apenas | ✅ dashboard temporal por métrica |
| Cost per tenant | ❌ não rastreado | ✅ aggregated por `business_id` metadata |

ROI ADR 0037 §GAP-1: +5pp IA-eval + 2pp UX + 3pp Cost-tracking = ~10pp global score weighted (vide [GAP-ANALYSIS-91-100-2026-05-13.md](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md)).

---

## Referências

- [ADR 0132 — Langfuse self-host CT 100](../../decisions/0132-langfuse-self-host-ct100.md) (canônica)
- [ADR 0037 — Roadmap tier-7-plus §GAP-1](../../decisions/0037-roadmap-evolucao-tier-7-plus.md)
- [GAP Analysis 91-100 sessão 2026-05-13](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md)
- [Langfuse docs — self-host docker-compose](https://langfuse.com/self-hosting/deployment/docker-compose)
- [Langfuse docs — public API ingestion](https://langfuse.com/docs/api-and-data-platform/features/public-api)
- [Langfuse docs — OpenTelemetry backend](https://langfuse.com/integrations/native/opentelemetry)
