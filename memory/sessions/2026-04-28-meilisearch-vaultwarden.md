# Sessão 2026-04-28 — Meilisearch CT 100 + Vaultwarden + Inventário infra

**Branch:** `main`
**Continuação de:** `2026-04-28-reverb-docker-host.md` (mesma data, 3ª janela de contexto)
**Duração:** ~1h
**Implementador:** Claude + Wagner [W]

---

## Contexto de entrada

Sessão retomada após context limit. Reverb e docker-host já estavam prontos (sessão anterior).
Pendências: DNS `meilisearch.oimpresso.com`, credenciais para Vaultwarden, inventário geral.

---

## O que foi feito

### 1. Verificação do estado dos containers CT 100

Via Portainer API (`https://portainer.oimpresso.com/api/auth` → JWT):

```
Containers em execução (todos 5 running):
- meilisearch   ✅ running
- portainer     ✅ running
- reverb        ✅ running
- traefik       ✅ running
- vaultwarden   ✅ running
```

### 2. Tentativa de criar DNS `meilisearch.oimpresso.com` — BLOQUEADO

Hostinger API (`api.hostinger.com`) retornando **HTTP 530** — Cloudflare não consegue alcançar o origin server da Hostinger. Não é issue do lado Claude ou da empresa — é indisponibilidade do lado Hostinger.

```
curl -sk -v "https://api.hostinger.com/v1/dns/zone/oimpresso.com/records"
→ HTTP/1.1 530 <none>
→ Server: cloudflare
→ CF-RAY: 9f389dbe9d0a8772-GRU
```

Testado de 2 origens (sandbox e Hostinger SSH) — mesmo resultado.

**Ação necessária (Wagner):** criar manualmente no hPanel Hostinger:
- Domínios → oimpresso.com → DNS → Add A record
- Name: `meilisearch` | Value: `177.74.67.30` | TTL: 3600 | Proxy: OFF

### 3. Confirmação Reverb ativo em produção

Verificado no `.env` Hostinger:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=oimpresso
REVERB_APP_KEY=5921152f-5c00-4bb6-92f0-0ed94a75c68d
REVERB_APP_SECRET=8e101a3e-7d35-4dcb-a27b-6f5fd5474c64
REVERB_HOST=reverb.oimpresso.com
REVERB_PORT=443
REVERB_SCHEME=https
```
✅ Smoke test `reverb:ping` passou (sessão anterior). Streaming Copiloto funcional.

### 4. Inventário completo infra + memória AI (detalhe abaixo)

---

## Inventário de estado 2026-04-28

### Infra física
| Camada | Host | IP | Status |
|--------|------|----|--------|
| Proxmox VE 9.1.1 | sistema | 192.168.0.2 / 177.74.67.30:8006 | ✅ |
| CT 100 docker-host | LXC Debian 12 | 192.168.0.50 | ✅ onboot |
| Hostinger Cloud Startup | PHP-FPM 8.4 | 148.135.133.115:65002 | ✅ |

### Serviços docker (CT 100)
| Container | Subdomínio | TLS | Status | Notas |
|-----------|-----------|-----|--------|-------|
| Traefik v3.6 | traefik.oimpresso.com | ✅ R13 | ✅ running | Dashboard BasicAuth admin |
| Portainer CE LTS | portainer.oimpresso.com | ✅ R13 | ✅ running | admin / Infra@Docker2026! |
| Vaultwarden 1.35.8 | vault.oimpresso.com | ✅ R12 | ✅ running | Wagner criou conta; SIGNUPS=false |
| Reverb (Laravel) | reverb.oimpresso.com | ✅ R12 | ✅ running | REVERB_APP_KEY=5921152f... |
| Meilisearch v1.10.3 | meilisearch.oimpresso.com | ❌ DNS pendente | ✅ running | Vol meilisearch-data; MEILI_KEY=9c08... |

### Hostinger `.env` — status IA
| Variável | Status |
|---------|--------|
| BROADCAST_CONNECTION=reverb | ✅ |
| REVERB_APP_KEY/SECRET | ✅ |
| OPENAI_API_KEY | ❌ falta — bloqueio crítico |
| SCOUT_DRIVER=meilisearch | ❌ falta |
| MEILISEARCH_HOST | ❌ falta (aguarda DNS) |
| MEILISEARCH_KEY | ❌ falta |
| COPILOTO_AI_ADAPTER | ❌ falta |
| COPILOTO_MEMORIA_DRIVER | ❌ falta |

### Copiloto — stack de memória IA (estado atual)
| Camada | Componente | Código | Prod |
|--------|-----------|--------|------|
| A | `laravel/ai ^0.6.3` | ✅ instalado | ⚠️ NullDriver sem key |
| B | `LaravelAiSdkDriver` + 4 Agents | ✅ | ⚠️ aguarda key |
| C Hot | `MemoriaContrato` + SqlDriver | ✅ | ⚠️ DB OK mas sem AI real |
| C Cold | `MeilisearchDriver` | ✅ | ❌ Meilisearch não conectado |
| Obs | Langfuse | ❌ não implementado | — |

**LongMemEval tier estimado:** 5-6 (nunca medido formalmente)

### ADRs existentes (44 total)
| Faixa | Tema | Qtd |
|-------|------|-----|
| 0001-0010 | Fundação (UPos, módulos, memória) | 10 |
| 0011-0020 | Officeimpresso + Delphi + organização | 10 |
| 0021-0030 | CMS, IA stack, posicionamento, credenciais | 10 |
| 0031-0037 | Copiloto stack canônica + roadmap memória | 7 |
| 0038-0041 | Promoção main + UI + policy + QA IA | 4 |
| **0042-0044** | **Reverb + Docker+Traefik + Vaultwarden** | **3 (novos 2026-04-28)** |

---

## Estado da arte da memória — próximos passos (ADR 0037)

```
HOJE (Tier 5-6 estimado):
  Hot: SqlDriver (conversas recentes em DB) ✅
  Cold: MeilisearchDriver (fatos em vetor) ✅ código, ❌ prod

IMEDIATO (para ligar produção):
  1. OPENAI_API_KEY no Hostinger .env
  2. DNS meilisearch.oimpresso.com → 177.74.67.30
  3. SCOUT_DRIVER + MEILISEARCH_HOST + KEY no Hostinger .env
  4. Embedder OpenAI configurado no índice (curl PATCH)
  → Tier 5-6 medido de verdade pela primeira vez

SPRINT 7 → Tier 7 (gate obrigatório — RAGAS):
  - Golden set 50 perguntas (Wagner)
  - Suite Pest eval (Felipe)
  - GH Actions eval.yml (Felipe)
  - Métricas-alvo: Faithfulness >0.90, Answer relevancy >0.85

SPRINT 8 → ROI mais alto (Semantic Caching):
  - SemanticCacheMiddleware antes de recallMemoria()
  - -68.8% tokens LLM em escala
  - Custo: R$0/mês (usa Redis/Cache existente)

SPRINT 9 → +recall (RRF tuning):
  - A/B semantic_ratio 0.3-0.7 no Meilisearch hybrid search
  - +10-15% recall

SPRINT 10 → bridge phrasing gap (HyDE):
  - ExpandirQueryAgent (gpt-4o-mini) antes da busca
  - +15% recall cross-phrasing
  - Custo: ~10-15% tokens (mitigado por cache)

SPRINT 11 → trigger condicional (Mem0/Zep):
  - SÓ executar se triggers do ADR 0036 ativarem
  - Benchmark LongMemEval: Mem0 ~67%, Zep/Graphiti 71.2%
  - Custo recorrente: começa aqui
```

---

## Vaultwarden — estado da conta

- **URL:** `https://vault.oimpresso.com`
- **Conta criada:** `wagnerra@gmail.com` / senha Wscrct*2312 (Wagner criou nesta sessão)
- **Signups:** `VAULTWARDEN_SIGNUPS_ALLOWED=false` (desabilitado após criação)
- **Admin token:** salvo em auto-memória `reference_vaultwarden_credenciais.md`

**Migrar para o cofre (Wagner — P2 do backlog):**
- CT root: `4R781JvuwYiWqJgTea8oHw`
- Portainer: `admin / Infra@Docker2026!`
- Traefik: `admin:zrG8nSxI0DIcWEIe`
- REVERB_APP_KEY: `5921152f-5c00-4bb6-92f0-0ed94a75c68d`
- REVERB_APP_SECRET: `8e101a3e-7d35-4dcb-a27b-6f5fd5474c64`
- MEILI_MASTER_KEY: `9c08945878571ecb76b70d25deb3852b`
- Proxmox API token: `root@pam!mcp2=e15a341f-cd82-4d99-8fd7-8f3b4d17a09b`

---

## Pendências pós-sessão

| # | Item | Quem | Urgência | Status |
|---|---|---|---|---|
| P1 | Criar DNS `meilisearch.oimpresso.com` no hPanel Hostinger | Wagner [W] | Alta | ⏳ (API down) |
| P2 | OPENAI_API_KEY no Hostinger .env | Wagner [W] | Crítica | ⏳ |
| P3 | SCOUT_DRIVER + MEILISEARCH_HOST + KEY no Hostinger .env | Wagner [W] | Alta | ⏳ (depende P1) |
| P4 | Configurar embedder OpenAI no Meilisearch | Wagner [W] | Alta | ⏳ (depende P2+P3) |
| P5 | Migrar credenciais pro Vaultwarden | Wagner [W] | Média | ⏳ |
| P6 | `postcss.config.cjs` commitado no git | Wagner [W] | Baixa | ⏳ |
| P7 | Sprint 7 RAGAS golden set (50 perguntas) | Wagner [W] | Média | ⏳ (depende Larissa + P2) |
| P8 | Validar Larissa (A1) | Wagner [W] | Alta | ⏳ |
