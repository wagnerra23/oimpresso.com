# RUNBOOK — Smoke local OpenTelemetry Collector + Tempo + Grafana

> **Wave 28 Agent 6** — valida pipeline OTel localmente ANTES de deploy CT 100 ([RUNBOOK-otel-collector-ct100.md](RUNBOOK-otel-collector-ct100.md), ADR 0162).
> **Stack:** otel-collector-contrib 0.110.0 + Tempo 2.6.0 + Grafana 11.3, docker-compose local.
> **Objetivo:** Wagner roda em ~5 min na máquina dele, valida que (1) collector aceita OTLP, (2) Tempo armazena, (3) Grafana mostra, (4) **anti-PII filter funciona** (user.email + http.url + Authorization removidos), (5) só depois sobe pro CT 100.

---

## 0. Pré-requisitos

- ✅ Docker Desktop rodando (Windows/macOS) OU docker engine + compose plugin (Linux)
- ✅ Portas livres na máquina local: **4317, 4318, 3200, 3000, 8888, 13133, 9095**
- ✅ ~2 GB RAM livre + ~1 GB disco
- ✅ `curl` + `jq` instalados (Windows: `winget install jqlang.jq`)
- ⛔ **NÃO subir este stack ao mesmo tempo que o CT 100** (mesmas portas) — local-dev é local

---

## 1. Subir o stack

```bash
cd infra/local-dev/otel
docker compose up -d
```

Saída esperada (3 containers Up): `oimpresso-otel-collector-localdev`, `oimpresso-tempo-localdev`, `oimpresso-grafana-localdev`.

Aguardar ~30s pro Tempo ficar ready (`ingester not ready yet` é normal nos primeiros logs).

```bash
docker compose ps
```

---

## 2. Health check collector + Tempo

```bash
# Collector health endpoint (esperado: HTTP 200, body vazio):
curl -i http://localhost:13133/

# Tempo ready (esperado: "ready"):
curl http://localhost:3200/ready
```

Se collector retornar 503: `docker logs oimpresso-otel-collector-localdev --tail 50` (provável erro config YAML).
Se Tempo retornar `ingester not ready`: aguardar +30s e tentar de novo.

---

## 3. Disparar trace OTLP de teste

```bash
curl -v -X POST http://localhost:4318/v1/traces \
  -H 'Content-Type: application/json' \
  --data-binary @test-spans.json
```

Saída esperada: `HTTP/1.1 200 OK` + body `{"partialSuccess":{}}`.

3 spans foram enviados:
- `jana.agent.run` (200ms) — raiz, com PII attrs (`user.email`, `user.cpf`) que DEVEM ser redacted
- `jana.llm.call` (1500ms) — filho, com `http.url` contendo `?key=SECRET` e `Authorization: Bearer sk-ant-FAKE` que DEVEM ser deletados
- `jana.embed.compute` (50ms) — filho, com `db.statement` SQL que DEVE ser deletado

---

## 4. Validar Tempo recebeu o trace

Aguardar ~10s (decision_wait tail_sampling = 5s + flush batch 1s + ingester idle 1s).

```bash
# Search por service.name:
curl -s "http://localhost:3200/api/search?tags=service.name=oimpresso-localdev-smoke" | jq

# Ou puxar o trace inteiro pelo traceID conhecido (do test-spans.json):
curl -s "http://localhost:3200/api/traces/0102030405060708090a0b0c0d0e0f10" | jq '.batches[0].scopeSpans[0].spans | length'
# Esperado: 3
```

Se `traces: []`: ver logs collector `docker logs oimpresso-otel-collector-localdev --tail 100` — provável drop no tail_sampling ou exporter Tempo down.

---

## 5. Abrir Grafana e visualizar

Abrir browser em **http://localhost:3000** (anonymous admin auto-login — sem senha).

Navegar: **Explore (compass icon)** → datasource **Tempo** → tab **Search** → service `oimpresso-localdev-smoke` → **Run query**.

Esperado: 1 trace listado, expand → 3 spans (agent.run → llm.call + embed.compute como filhos). Clicar no span → painel lateral abre.

---

## 6. Validar PII redaction (CRÍTICO Tier 0 ADR 0093)

No painel lateral do span no Grafana (passo 5), conferir attributes do span `jana.agent.run`:

| Atributo | Esperado |
|---|---|
| `user.email` | **AUSENTE** (foi deletado pelo processor `attributes/redact`) |
| `user.cpf` | **AUSENTE** |
| `oimpresso.tenant_id` | **PRESENTE mas HASH** (não `1` — hash hex) |
| `oimpresso.user_id` | **PRESENTE mas HASH** (não `42`) |
| `oimpresso.feature` | `jana.brain` (preservado — não é PII) |

No span `jana.llm.call`:

| Atributo | Esperado |
|---|---|
| `http.url` | **AUSENTE** |
| `http.request.header.authorization` | **AUSENTE** |
| `llm.model` | `claude-opus-4-7` (preservado — não é PII) |
| `llm.input_tokens` | `1234` (preservado) |

No span `jana.embed.compute`:

| Atributo | Esperado |
|---|---|
| `db.statement` | **AUSENTE** |
| `embedding.model` | `BAAI/bge-m3` (preservado) |

**Validação CLI alternativa** (sem Grafana):

```bash
curl -s "http://localhost:3200/api/traces/0102030405060708090a0b0c0d0e0f10" \
  | jq '.batches[0].scopeSpans[0].spans[] | {name, attrs: [.attributes[].key]}'
```

Conferir que `user.email`, `user.cpf`, `http.url`, `http.request.header.authorization`, `db.statement` NÃO aparecem em nenhuma `attrs`. Se aparecer = anti-PII filter está quebrado → STOP, NÃO promover pra CT 100 ([proibicoes.md](../../proibicoes.md) §"PII reais NUNCA").

---

## 7. Conferir logs collector (debug exporter)

```bash
docker logs oimpresso-otel-collector-localdev --tail 100 | grep -A 2 'jana.agent.run'
```

Local-dev tem `debug` exporter com `verbosity: detailed` — Wagner vê o span já redacted no log do collector (prova adicional que o pipeline limpou ANTES de exportar pro Tempo).

---

## 8. Métricas internas collector (Prometheus scrape)

```bash
curl -s http://localhost:8888/metrics | grep -E '(otelcol_receiver_accepted_spans|otelcol_processor_dropped_spans|otelcol_exporter_sent_spans)' | head -10
```

Esperado: `accepted_spans_total{...} 3` + `sent_spans_total{exporter="otlphttp/tempo"} 3` + `dropped 0`.

Se `dropped > 0` ou `sent < accepted`: pipeline está perdendo span — investigar antes de subir prod.

---

## 9. Cleanup (sempre `-v` em local-dev)

```bash
docker compose down -v
```

`-v` apaga os volumes `localdev-oimpresso-tempo-data` e `localdev-oimpresso-grafana-data` (não conflita com volumes de prod CT 100 que têm nome `oimpresso-tempo-data` sem prefixo).

```bash
# Conferir cleanup completo:
docker ps -a | grep oimpresso.*localdev   # esperado: vazio
docker volume ls | grep localdev          # esperado: vazio
```

---

## 10. Promover pra produção CT 100

> ⚠️ Só executar APÓS smoke local 100% verde (passos 1-9 todos OK, especialmente passo 6 anti-PII).

Diff entre local-dev e CT 100 (Wagner sabe o que muda):

| Item | local-dev | CT 100 prod |
|---|---|---|
| docker-compose | `infra/local-dev/otel/docker-compose.yml` | `infra/ct100/otel/docker-compose.yml` |
| collector config | `attributes/redact` IDÊNTICO + `tail_sampling: always_sample` + exporter `debug` ativo | `attributes/redact` IDÊNTICO + `tail_sampling: 5% + erros + slow + FSM + Jana` + exporter `otlphttp/laravel` adicional |
| tempo retention | 24h | 168h (7d) |
| batch flush | 1s/64 | 10s/1024 |
| volume name | `localdev-oimpresso-tempo-data` | `oimpresso-tempo-data` |
| container name | `*-localdev` | sem sufixo |
| rede | `otel-localdev` (bridge própria) | `oimpresso-mcp` (external, reusada do stack MCP) |
| Grafana | incluído (auto-login anonymous) | NÃO incluído ainda (Wave 27+ wireup) |

Caminho promoção: seguir [RUNBOOK-otel-collector-ct100.md](RUNBOOK-otel-collector-ct100.md) §2 "Deploy passo-a-passo" → §3 "Validation smoke trace local" (no CT 100) → §4 "Wagner ativa em produção .env Hostinger" → §5 "Soak 7 dias antes de aumentar sampling".

---

## Troubleshooting rápido

| Sintoma | Causa provável | Fix |
|---|---|---|
| Porta 4318 já em uso | Stack CT 100 SSH-tunneled ou Tempo Grafana Cloud local | `docker ps`; matar OR mudar porta no compose |
| `network otel-localdev not found` | Compose não criou rede | `docker compose down && docker compose up -d` |
| Tempo `wal directory not writable` | Volume permission (Linux non-root) | `user: "0"` já está no compose; se persistir `docker volume rm localdev-oimpresso-tempo-data` e subir de novo |
| `partialSuccess` com erro no body | JSON test-spans.json malformado | `jq . test-spans.json` valida sintaxe |
| Trace some do Tempo após `down -v` | Retention 24h + volume apagado | Esperado em local-dev (sempre `-v`) |
| Grafana 3000 não abre | Container ainda subindo (depends_on tempo healthy) | Esperar ~30s |
| `user.email` AINDA aparece no span | Anti-PII filter quebrado | STOP — não promover. Investigar `attributes/redact` no `otel-collector-config.yaml` |

---

## Custo local-dev

- ✅ Docker images cacheadas após primeiro pull: ~400 MB (collector 100 + tempo 150 + grafana 150)
- ✅ RAM steady-state: ~800 MB-1.2 GB total
- ✅ Disco: <100 MB (retention 24h + tráfego smoke de Wagner)
- ✅ Egress: ZERO (tudo local, nada sai pra internet)

---

**Última atualização:** 2026-05-17 — Wave 28 Agent 6 (smoke local antes deploy CT 100).
