---
slug: infra-runbook-bge-reranker-ct100
title: "Infra — Runbook deploy BGE-v2-m3 reranker FastAPI (CT 100) + ativação driver bge no Jana"
type: runbook
module: Infra
status: active
date: 2026-05-13
---

# RUNBOOK — Deploy BGE-reranker-v2-m3 self-host CT 100 + ativação driver `bge` no Jana

> **Tipo:** runbook reproduzível (Onda 4 R1 P0 — fecha Knowledge R3 hybrid retrieval)
> **Refs:** [ADR 0053 MCP server canônico](../../decisions/0053-mcp-server-governanca-como-produto.md), [ADR 0058 Centrifugo+FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0062 Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md), [ADR 0037 RAG roadmap](../../decisions/0037-roadmap-evolucao-tier-7-plus.md), [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md §5 G3](../Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md), [GAP-ANALYSIS-91-100-2026-05-13.md](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md)
> **Pré-requisito:** acesso SSH ao CT 100 via Tailscale (`tailscale ssh root@ct100-mcp` — ver [RUNBOOK-acesso-ct100.md](RUNBOOK-acesso-ct100.md))

Objetivo: subir container Docker `bge-reranker` no CT 100 servindo BGE-v2-m3
via FastAPI, expor endpoint LAN (`http://bge-reranker.ct100:8080/rerank`) +
público IP-whitelisted (`https://bge-reranker.ct100.oimpresso.com/rerank`) via
Traefik, e ativar driver `bge` no Jana com fallback graceful pra RRF.

Resolve gap R1 P0 da [GAP-ANALYSIS-91-100-2026-05-13.md](../Jana/GAP-ANALYSIS-91-100-2026-05-13.md):
**+6pp NDCG@10 vs RRF baseline (Cormack 2009 já em prod via PR #766)**.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Container `bge-reranker` `Up` no CT 100 | `tailscale ssh root@ct100-mcp 'docker ps \| grep bge-reranker'` |
| Endpoint LAN responde 200 | `tailscale ssh root@ct100-mcp 'curl -sf http://localhost:8080/health'` |
| Endpoint público IP-whitelisted responde 200 | `curl -sf https://bge-reranker.ct100.oimpresso.com/health` (IP empresa) |
| `php artisan tinker` resolve `BgeReranker::class` | `app(Modules\Jana\Services\Retrieval\Reranker::class)` instância `BgeReranker` |
| RAGAS eval mostra +6pp NDCG@10 vs RRF | `php artisan jana:ragas-eval --driver=bge` vs baseline |
| Fallback RRF aciona quando container down | parar container → reranking continua funcionando (log warning) |

## 1. Pré-condições

- [ ] CT 100 acessível via Tailscale (IP `100.99.207.66`, hostname `ct100-mcp`)
- [ ] Docker + Traefik rodando (verificar `docker ps | grep traefik`)
- [ ] DNS A-record `bge-reranker.ct100.oimpresso.com` → `177.74.67.30` (pattern existente subdomínios `mcp.*`, `realtime.*`)
- [ ] Espaço disco CT 100: **≥ 4GB livres** em `/var/lib/docker` (modelo 2.27GB + imagem Python + cache HF)
- [ ] CPU mínimo: 4 vCPU (BGE-v2-m3 em CPU bate ~130ms / batch 16 pares, escala linear com batch)
- [ ] RAM mínimo: 4GB livres pro container (model + overhead PyTorch)
- [ ] Whitelist Traefik configurada (`allowed_ips` middleware existente — reusar do `mcp.ct100.*`)

## 2. Estrutura de arquivos no CT 100

```
/opt/bge-reranker/
├── docker-compose.yml
├── app/
│   └── main.py              # FastAPI ~40 linhas
├── Dockerfile               # python:3.11-slim + FlagEmbedding + FastAPI
└── .env                     # opcional (MODEL_NAME, MAX_BATCH)
```

## 3. Passo-a-passo

### 3.1 SSH no CT 100

```bash
tailscale ssh root@ct100-mcp
mkdir -p /opt/bge-reranker/app
cd /opt/bge-reranker
```

### 3.2 Criar `app/main.py` (FastAPI 40 linhas)

```python
# /opt/bge-reranker/app/main.py
"""BGE-reranker-v2-m3 FastAPI server — Onda 4 R1 P0 Jana hybrid retrieval."""
from __future__ import annotations

import logging
import os
from typing import List

from fastapi import FastAPI, HTTPException
from FlagEmbedding import FlagReranker
from pydantic import BaseModel, Field

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("bge-reranker")

MODEL_NAME = os.getenv("MODEL_NAME", "BAAI/bge-reranker-v2-m3")
MAX_DOCUMENTS = int(os.getenv("MAX_DOCUMENTS", "100"))

app = FastAPI(title="BGE-reranker-v2-m3", version="1.0.0")
log.info("Loading model %s (this takes ~30s first time)...", MODEL_NAME)
reranker = FlagReranker(MODEL_NAME, use_fp16=False)  # CPU-only, no fp16
log.info("Model loaded.")


class RerankRequest(BaseModel):
    query: str = Field(min_length=1, max_length=2000)
    documents: List[str] = Field(min_length=1, max_length=MAX_DOCUMENTS)
    top_k: int = Field(default=5, ge=1, le=100)


class RerankResultItem(BaseModel):
    index: int
    score: float


class RerankResponse(BaseModel):
    results: List[RerankResultItem]
    model: str = MODEL_NAME


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "model": MODEL_NAME}


@app.post("/rerank", response_model=RerankResponse)
def rerank(req: RerankRequest) -> RerankResponse:
    try:
        pairs = [[req.query, doc] for doc in req.documents]
        scores = reranker.compute_score(pairs, normalize=True)
        if not isinstance(scores, list):
            scores = [scores]
        ranked = sorted(enumerate(scores), key=lambda x: x[1], reverse=True)
        top = ranked[: req.top_k]
        return RerankResponse(
            results=[RerankResultItem(index=i, score=float(s)) for i, s in top]
        )
    except Exception as e:  # noqa: BLE001
        log.exception("rerank failure")
        raise HTTPException(status_code=500, detail=str(e)) from e
```

### 3.3 Criar `Dockerfile`

```dockerfile
# /opt/bge-reranker/Dockerfile
FROM python:3.11-slim

WORKDIR /app

# System deps (FlagEmbedding usa torch CPU)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl && \
    rm -rf /var/lib/apt/lists/*

# Pin versões pra reprodutibilidade (snapshot mai/2026)
RUN pip install --no-cache-dir \
    "fastapi==0.115.4" \
    "uvicorn[standard]==0.32.0" \
    "FlagEmbedding==1.3.2" \
    "torch==2.4.1+cpu" --index-url https://download.pytorch.org/whl/cpu \
    "pydantic==2.9.2"

COPY app/ /app/

# Pre-download modelo no build (evita primeira request lenta)
RUN python -c "from FlagEmbedding import FlagReranker; FlagReranker('BAAI/bge-reranker-v2-m3', use_fp16=False)"

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -sf http://localhost:8080/health || exit 1

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8080", "--workers", "1"]
```

### 3.4 Criar `docker-compose.yml`

```yaml
# /opt/bge-reranker/docker-compose.yml
services:
  bge-reranker:
    build: .
    container_name: bge-reranker
    hostname: bge-reranker.ct100
    restart: unless-stopped
    networks:
      - traefik-public
    environment:
      MODEL_NAME: "BAAI/bge-reranker-v2-m3"
      MAX_DOCUMENTS: "100"
    ports:
      - "127.0.0.1:8080:8080"  # LAN-only direto (extras Tailscale acessa via hostname)
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.bge-reranker.rule=Host(`bge-reranker.ct100.oimpresso.com`)"
      - "traefik.http.routers.bge-reranker.entrypoints=websecure"
      - "traefik.http.routers.bge-reranker.tls.certresolver=letsencrypt"
      - "traefik.http.routers.bge-reranker.middlewares=ip-whitelist-empresa@docker"
      - "traefik.http.services.bge-reranker.loadbalancer.server.port=8080"
    deploy:
      resources:
        limits:
          memory: 4G
        reservations:
          memory: 2G

networks:
  traefik-public:
    external: true
```

> ⚠️ **Middleware `ip-whitelist-empresa@docker`** já está definido em outros services CT 100 (ex.: mcp.oimpresso.com). Se o nome for diferente no seu ambiente, conferir `docker inspect traefik | grep -i whitelist`.

### 3.5 Build + up

```bash
cd /opt/bge-reranker
docker compose build       # ~5-8 min primeira vez (baixa torch CPU + modelo HF ~2.3GB)
docker compose up -d
docker compose logs -f bge-reranker
```

Aguardar log: `Application startup complete.` (modelo carrega ~30s na primeira inicialização porque já vem baked no image).

### 3.6 Smoke test LAN

```bash
# Health
tailscale ssh root@ct100-mcp 'curl -sf http://localhost:8080/health'
# → {"status":"ok","model":"BAAI/bge-reranker-v2-m3"}

# Rerank de teste
tailscale ssh root@ct100-mcp 'curl -sf -X POST http://localhost:8080/rerank \
  -H "Content-Type: application/json" \
  -d "{\"query\":\"qual o faturamento de hoje\",\"documents\":[\"vendas do dia: R\$ 1500\",\"recibo do João\",\"faturamento diário ROTA LIVRE atinge R\$ 12 mil\"],\"top_k\":3}"'
# → {"results":[{"index":2,"score":0.95},{"index":0,"score":0.71},{"index":1,"score":0.08}],"model":"..."}
```

### 3.7 Smoke test público (DNS + Traefik)

```bash
# Do IP da empresa (deve responder)
curl -sf https://bge-reranker.ct100.oimpresso.com/health
# → 200 OK

# De fora (deve dar 403/connection refused — whitelist)
# Teste de outro IP qualquer
```

## 4. Ativar driver `bge` no Jana (Hostinger)

### 4.1 SSH no Hostinger + atualizar `.env`

```bash
# Warm-up + SSH (CLAUDE.md §7)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd ~/domains/oimpresso.com/public_html && nano .env'
```

Adicionar / atualizar:

```env
JANA_RERANKER_ENABLED=true
JANA_RERANKER_DRIVER=bge
JANA_RERANKER_BGE_ENDPOINT=https://bge-reranker.ct100.oimpresso.com/rerank
JANA_RERANKER_BGE_TIMEOUT=5
```

> ⚠️ Hostinger NÃO está na rede Tailscale do CT 100. Usar **endpoint público Traefik** + IP da Hostinger no whitelist. Conferir `srv1818.hstgr.io` outbound IP (geralmente `148.135.133.115`) e incluir no middleware `ip-whitelist-empresa`. Se Hostinger usa NAT dinâmico, considerar token shared-secret no header (`X-Jana-Token: ...`) em vez de IP-whitelist.

### 4.2 Limpar cache de config

```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan config:clear && php artisan optimize:clear'
```

### 4.3 Validar binding via tinker

```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan tinker --execute="echo get_class(app(\Modules\Jana\Services\Retrieval\Reranker::class));"'
# → Modules\Jana\Services\Retrieval\BgeReranker
```

### 4.4 RAGAS eval comparativo

```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan jana:ragas-eval --driver=rrf > /tmp/ragas-rrf.txt && php artisan jana:ragas-eval --driver=bge > /tmp/ragas-bge.txt && diff /tmp/ragas-rrf.txt /tmp/ragas-bge.txt'
```

Esperado: BGE mostra **NDCG@10 ~+6pp** vs RRF.

## 5. Métricas observadas (preencher após primeira semana em prod)

| Métrica | Esperado (literatura) | Medido CT 100 | Data |
|---|---|---|---|
| Latência p50 /rerank (batch 10) | ~80ms | _a medir_ | _yyyy-mm-dd_ |
| Latência p95 /rerank (batch 10) | ~150ms | _a medir_ | _yyyy-mm-dd_ |
| Latência p99 /rerank (batch 50) | ~400ms | _a medir_ | _yyyy-mm-dd_ |
| NDCG@10 vs RRF baseline | +6pp | _a medir_ | _yyyy-mm-dd_ |
| Memory steady-state | ~2.5 GB | _a medir_ | _yyyy-mm-dd_ |
| Fallback rate (HTTP fail / total) | < 0.5% | _a medir_ | _yyyy-mm-dd_ |

## 6. Operações

### Restart limpo (após bump versão modelo)

```bash
cd /opt/bge-reranker
docker compose pull && docker compose up -d --build
docker compose logs -f --tail=50 bge-reranker
```

### Logs

```bash
# Tail estruturado
tailscale ssh root@ct100-mcp 'docker logs -f --tail=100 bge-reranker'

# Last 24h
tailscale ssh root@ct100-mcp 'docker logs --since=24h bge-reranker 2>&1 | tail -200'
```

### Rollback (desativar BGE, voltar pra RRF)

```bash
# Hostinger .env
JANA_RERANKER_DRIVER=rrf

# clear cache + verificar
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan config:clear'
```

Driver `bge` tem **fallback automático** pra RRF se HTTP falhar — desligar container CT 100
NÃO derruba retrieval, só perde os +6pp NDCG@10 temporariamente. Mas pra evitar logs warning
constantes, melhor flipar env explícito em rollback.

## 7. Pegadinhas conhecidas

- ⛔ **`use_fp16=True` no FlagReranker quebra em CPU.** Modelo retorna NaN scores. Sempre `False` em CPU CT 100 (sem GPU).
- ⛔ **Não rodar Pest da suite Jana no Hostinger** ([proibições](../../proibicoes.md)) — usar local ou CT 100.
- ⛔ **Modelo BGE não fica em `/var/cache`** — fica em `/root/.cache/huggingface` por default no container. Se container for recriado SEM o `RUN python -c "..."` no Dockerfile, primeiro request demora ~3min (download modelo). Por isso o Dockerfile pre-downloada.
- ⛔ **`--workers > 1` no uvicorn duplica memória.** Cada worker carrega o modelo de novo. Manter `--workers 1` em CT 100 (4 vCPU). Pra paralelismo, considerar `gunicorn -k uvicorn.workers.UvicornWorker -w 1 --threads 4` (compartilha modelo entre threads — limitado pelo GIL mas batch é GIL-released).
- ⛔ **Top_k > documents.length** retorna lista < top_k (não dá erro). Caller (BgeReranker.php) precisa lidar com isso — já tratado via `array_slice` final.
- ⛔ **IP-whitelist Traefik via `@docker`** exige label `traefik.http.middlewares.ip-whitelist-empresa.ipallowlist.sourcerange=...` definida em ALGUM container traefik-public. Se essa middleware não existir, criar antes (ver exemplo nos outros services CT 100).

## 8. Validação pós-deploy (checklist Wagner)

- [ ] `docker ps` mostra container `bge-reranker` `Up (healthy)` há > 5min
- [ ] `/health` retorna 200 LAN + público
- [ ] `/rerank` retorna scores plausíveis (smoke test §3.6)
- [ ] Hostinger `.env` atualizado + cache cleared
- [ ] `tinker` resolve `Reranker` como `BgeReranker`
- [ ] `php artisan jana:ragas-eval` mostra ≥ +5pp NDCG@10 vs baseline RRF (gate: +5pp é o piso aceito; +6pp é o target literatura)
- [ ] Logs `copiloto-ai` no Hostinger sem warnings `BgeReranker::*` nas últimas 24h (= fallback rate < 0.5%)
- [ ] Smoke conversa Jana (chat `/copiloto/chat`) — pergunta com termos não-exatos da memória vem com resposta correta (ex.: "qual o faturamento de hoje?" — sinônimo de "venda diária")

## 9. Próximos passos (fora deste runbook)

- **GPU upgrade CT 100** (se latência p99 > 500ms sustentado): host Proxmox com NVIDIA passthrough + `--gpus all` no docker-compose. Modelo cai pra ~30ms p99 mas custa $$. ADR antes.
- **Scaling horizontal**: réplicas via Docker Swarm OU API Gateway round-robin com 2-3 containers (cada um carrega o modelo). Só vale se p99 > 1s sustentado.
- **Trocar pra `bge-reranker-v2-gemma`** (multilingual + maior, 2.5B params) — só faz sentido pós-GPU.
- **RAGAS eval automatizado em CI**: `php artisan jana:ragas-eval --driver=bge --gate=ndcg@10:0.05` no workflow `jana-eval.yml`, falha PR se driver `bge` regressar abaixo do gate.
