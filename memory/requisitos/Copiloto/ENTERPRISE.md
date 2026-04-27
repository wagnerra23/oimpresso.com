# Copiloto — Enterprise Architecture & Operations Overview

> **Audiência:** decisor técnico (CIO/CTO/Arquiteto), comprador de ERP enterprise, auditor de compliance (LGPD/ISO 27001), próximo Wagner numa empresa que herde o projeto.
> **Versão:** 1.0 — 2026-04-27
> **Status:** Production-deployed. Tier 5-6 LongMemEval estimado. R$0/mês recorrente.
> **Última verdade canônica:** ADR 0035 + ADR 0036 + ADR 0037.
> **Companion:** [README.md](README.md) (overview) · [SPEC.md](SPEC.md) (US/Gherkin) · [ARCHITECTURE.md](ARCHITECTURE.md) (low-level) · [RUNBOOK.md](RUNBOOK.md) (ops dia-a-dia) · [GLOSSARY.md](GLOSSARY.md)

---

## 1. Executive summary (60 segundos)

O **Copiloto** é o assistente de IA conversacional do oimpresso.com (UltimatePOS v6.7 fork). Permite que gestores de PMEs brasileiras (target: gráficas e empresas de comunicação visual) **conversem com seu ERP em português**, recebam sugestões de metas baseadas em dados reais, e tenham um sistema que **aprende e lembra** de cada cliente entre sessões.

**Diferenciais competitivos vs concorrentes verticais brasileiros (Mubisys, Zênite, Calcgraf, Calcme, Visua):**
- ✅ Único com chat IA-first integrado ao schema do ERP (sem ETL)
- ✅ Memória semântica per-tenant LGPD-compliant (esquecer = soft delete + remoção de índice)
- ✅ Stack 100% PHP/Laravel — implantável em hosting compartilhado, sem container Python
- ✅ Custo recorrente R$0/mês até comprovar tese (Meilisearch self-hosted) — concorrentes que adotam Mem0/Letta pagam $25-300/mês desde dia 1

**Maturidade hoje (2026-04-27):**
- 6 sprints implementados em produção (PRs #24/25/26/27/28)
- 7 ADRs interlocked (formato Nygard) governando decisões
- 4 comparativos Capterra/G2 (templates oficiais do projeto)
- 48 testes Pest passando (3 skipped intencionais, dependentes de DB real)
- 3 telas Inertia/React indexadas no MemCofre (`docs_pages`)

---

## 2. Arquitetura de referência (4 camadas + tooling)

```
┌──────────────────────────────────────────────────────────────────────┐
│                       USUÁRIO FINAL (Larissa, etc)                    │
│                  Browser · ChatController@send · Inertia/React       │
└───────────────────────────────┬──────────────────────────────────────┘
                                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  CAMADA B — FRAMEWORK DE AGENTE (orquestração)                       │
│  Atual: LaravelAiSdkDriver + 4 Agents (Briefing/Sugestoes/           │
│  Chat/ExtrairFatos)                                                   │
│  Futuro: Vizra ADK (aguardando suporte L13 upstream)                  │
└──────────────┬─────────────────────────────────┬─────────────────────┘
               ▼                                 ▼
┌─────────────────────────┐        ┌─────────────────────────────────┐
│  CAMADA A — LLM WRAPPER │        │  CAMADA C — MEMÓRIA (semântica) │
│  laravel/ai (Laravel    │        │  MemoriaContrato (interface PHP)│
│  AI SDK oficial fev/2026│        │     ├─ MeilisearchDriver (def)  │
│  Anthropic / OpenAI /   │        │     ├─ NullMemoriaDriver (dev)  │
│  Gemini / Ollama)       │        │     └─ Mem0RestDriver (sprint   │
│                         │        │        8+ condicional, 5        │
│  Embeddings nativos via │        │        triggers em ADR 0036)    │
│  Laravel\\Ai\\Embeddings │        │                                 │
└─────────────────────────┘        └────────────────┬────────────────┘
                                                    ▼
                              ┌──────────────────────────────────────┐
                              │  Eloquent + MySQL                    │
                              │  copiloto_memoria_facts              │
                              │  (multi-tenant business_id+user_id   │
                              │   + valid_from/until + soft_deletes) │
                              │     ↕  Scout Searchable observers   │
                              │  Meilisearch v1.10.3 (self-hosted)  │
                              │  (local Windows + Hostinger ~/...)   │
                              └──────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  TOOLING & OBSERVABILITY                                             │
│  laravel/horizon (queue monitor)  · laravel/telescope (debug AI)     │
│  laravel/pail (log tail)          · laravel/boost (Cursor/Claude)    │
│  laravel/mcp (futuro: expor app pra agentes externos)                │
└──────────────────────────────────────────────────────────────────────┘
```

**Princípios arquiteturais:**

1. **Camadas trocáveis por interface** (`AiAdapter`, `MemoriaContrato`) — vendor lock-in evitado por design
2. **Multi-tenant nativo** via `business_id + user_id` em toda query — herdado do schema multi-empresa do UltimatePOS
3. **Append-only temporal** em fatos de memória (`valid_from/valid_until`) — conflict resolution sem destrutivo
4. **LGPD by design** — soft delete = "esquecer" + observer Scout remove índice automaticamente
5. **Falha gracefull** — se camada C falhar, chat continua funcionando (recall vazio injeta string vazia no system prompt)
6. **Custo recorrente travado em R$0/mês** — Mem0 só ativa via 5 triggers documentados (ADR 0036)

---

## 3. Fluxos críticos (5 cenários enterprise)

### Fluxo 1 — Pergunta normal do usuário (Hot Path)

**Trigger:** `POST /copiloto/conversas/{id}/mensagens` (Inertia)
**Latência alvo:** p95 < 5s (limite aceitável de chat); p50 < 2s
**Custo:** ~R$0,01-0,05/mensagem (depende do plan OpenAI/Anthropic)

```
1. ChatController@send valida CSRF + auth + business_id (~5ms)
2. Persiste Mensagem(role=user) (~3ms)
3. LaravelAiSdkDriver::responderChat:
   3a. recallMemoria() → MemoriaContrato.buscar(top-K=5) → Scout/Meilisearch (~50-200ms)
   3b. ChatCopilotoAgent.prompt() → laravel/ai → OpenAI/Anthropic API (~1-4s)
   3c. Persiste Mensagem(role=assistant) com tokens_in/out (~3ms)
   3d. ExtrairFatosDaConversaJob.dispatch() → queue copiloto-memoria (~5ms, async)
4. Resposta retorna pra UI (~10ms)
```

**Pontos de falha:**
- LLM provider down → fallback automático provider-down (laravel/ai built-in)
- Meilisearch down → recall vazio + chat continua (grace degradation)
- Tokens exceeded → fallback fixture string + log no canal `copiloto-ai`

### Fluxo 2 — Extração assíncrona de fatos (Cold Path)

**Trigger:** Job `ExtrairFatosDaConversaJob` na queue `copiloto-memoria` (Horizon monitora)
**Latência alvo:** depende do queue worker, sem SLA UX (background)
**Custo:** ~R$0,005/turno (gpt-4o-mini structured output)

```
1. Worker pega job (Horizon)
2. Carrega Conversa.mensagens últimas 10 (~5ms)
3. ExtrairFatosAgent.prompt() → laravel/ai com schema JSON (~2-5s)
4. Filtra fatos com relevancia >= 5 (PHP)
5. Pra cada fato: MemoriaContrato.lembrar() → grava em copiloto_memoria_facts → Scout indexa em Meilisearch automatic via observer
6. Log estruturado canal `copiloto-ai` (sucesso/erro)
```

**Idempotência:** Job tries=2; falha silente (extração falhar não bloqueia chat).
**Backpressure:** queue separada `copiloto-memoria` evita poluir queue default.

### Fluxo 3 — Esquecer fato (LGPD opt-out)

**Trigger:** `DELETE /copiloto/memoria/{id}` (UI clique "esquecer" + confirmação JS)
**SLA:** efeito imediato visível pro usuário; remoção de índice em <30s

```
1. MemoriaController@destroy
2. MemoriaContrato.esquecer(id) → MeilisearchDriver
3. CopilotoMemoriaFato::find(id)->delete() (soft delete, mantém row pra audit)
4. Observer Scout dispara unsearchable() → Meilisearch DELETE no índice (~100-500ms async)
5. Log estruturado + flash success
```

**Compliance LGPD:**
- Direito de esquecimento: ✅ atendido (soft delete + remoção de índice)
- Audit trail: ✅ row preservada com `deleted_at` pra investigação interna
- Hard delete (purge total): runbook `php artisan copiloto:purge-deleted --older-than=180d` (não implementado ainda; sprint futuro)

### Fluxo 4 — Atualização de fato (supersedes temporal)

**Trigger:** `PATCH /copiloto/memoria/{id}` com `fato` no body
**Padrão:** append-only — antigo recebe `valid_until=now()`, novo é criado com `valid_from=now()`

```
1. MemoriaController@update valida fato (string max:1000)
2. MemoriaContrato.atualizar() → MeilisearchDriver
3. Antigo: UPDATE copiloto_memoria_facts SET valid_until=NOW()
4. Novo: INSERT copiloto_memoria_facts com valid_from=NOW()
5. Observer Scout: shouldBeSearchable() = false pro antigo, true pro novo
6. Meilisearch reflete: antigo sai do índice, novo entra
```

**Auditoria:** histórico completo navegável via `CopilotoMemoriaFato::where('valid_until', '<', now())` — útil pra "qual era a meta da Larissa em janeiro?"

### Fluxo 5 — Onboarding novo cliente (futuro — não implementado)

```
1. Cliente assina Tier 1A (R$199-599/mês — ADR 0026)
2. Wagner roda `php artisan copiloto:seed-memory --business=N --user=email`
3. Importa fatos da auto-memória do agente (`cliente_*.md`) → MeilisearchDriver
4. Larissa abre /copiloto pela 1ª vez — Copiloto já chega "sabendo" do business
```

**Status:** comando ainda não implementado. Tracked como US-COPI-MEM-011 (sprint futuro).

---

## 4. Modelo de dados (ER simplificado)

```
┌─────────────────────────┐         ┌──────────────────────────────┐
│  business (UltimatePOS) │ 1     N │  copiloto_conversas           │
│  id, name, time_zone    │─────────│  id, business_id, user_id,    │
└─────────────────────────┘         │  titulo, status, iniciada_em  │
         │                          └────────────────┬──────────────┘
         │                                          1│
         │                                           │N
         │                          ┌────────────────▼──────────────┐
         │                          │  copiloto_mensagens            │
         │                          │  id, conversa_id, role, content│
         │                          │  tokens_in, tokens_out, created│
         │                          └────────────────────────────────┘
         │
         │ 1     N
         │
┌────────▼─────────────────────────────────┐
│  copiloto_memoria_facts                   │  ← Sprint 4 (PR #25)
│  id, business_id, user_id, fato (text),   │
│  metadata (json: categoria, relevancia,   │
│   origem, conversa_id, extraido_em),      │
│  valid_from, valid_until,                 │
│  deleted_at (LGPD soft delete),           │
│  timestamps                                │
│  INDEX (business_id, user_id)             │
│  INDEX (valid_from, valid_until)          │
└────────────────┬──────────────────────────┘
                 │ Scout Searchable observer (auto-sync)
                 ▼
┌─────────────────────────────────────────┐
│  Meilisearch (self-hosted, R$0/mês)     │
│  index: copiloto_memoria_facts           │
│  embedder: openai-text-embedding-3-small│
│  hybrid: full-text + semantic (ratio=0.5)│
└─────────────────────────────────────────┘
```

**Características de produção:**
- Multi-tenant: TODA query carrega `WHERE business_id=? AND user_id=?` (Tenant Scope sugerido sprint futuro)
- Volume estimado: 100-500 fatos/cliente ativo · ~10k clientes em 5 anos = ~5M rows
- Crescimento: linear com nº mensagens (1 mensagem ~ 0-2 fatos)
- Hot data: últimos 30 dias (recall sempre busca em ativos: `valid_until IS NULL`)
- Cold data: histórico temporal — útil pra audit e analytics, raramente acessado

---

## 5. Compliance, segurança e LGPD

### LGPD — Lei Geral de Proteção de Dados

| Requisito (LGPD) | Implementação |
|---|---|
| Art. 7º — base legal de tratamento | Consentimento explícito no onboarding + interesse legítimo (operação do ERP) |
| Art. 9º — minimização | `LaravelAiSdkDriver::mascararDocumentos()` mascara CPF/CNPJ antes de mandar ao LLM |
| Art. 16º — eliminação dos dados | Soft delete + remoção de índice ✅ (`MemoriaContrato.esquecer()`) |
| Art. 18º — direitos do titular | Tela `/copiloto/memoria` permite acesso, correção, eliminação ✅ |
| Art. 37º — registro das operações | Log estruturado canal `copiloto-ai` + audit trail no DB (`deleted_at`, `valid_until`) |
| Art. 46º — segurança | Multi-tenant scope obrigatório; observer só indexa fatos ativos do usuário correto |
| Art. 48º — comunicação de incidentes | RUNBOOK documenta procedimento (sprint futuro: integrar com Sentry/PagerDuty) |

### Segurança aplicacional

- **Autenticação:** middleware `auth` em todas as rotas `/copiloto/*` (UltimatePOS sessão)
- **CSRF:** habilitado em rotas POST/PATCH/DELETE
- **Authorization:** `business_id` resolvido de `session('user.business_id')` — não confiável em input do cliente
- **Rate limiting:** sugerido em sprint futuro via `RateLimiter::for('copiloto', ...)` (não implementado)
- **Sanitização de prompt:** CPF/CNPJ mascarados antes de chegar ao LLM
- **Secrets:** `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `MEILISEARCH_KEY` em `.env` (não git)
- **Observação técnica:** `BROADCAST_DRIVER=null` em prod (sem WebSocket exposto)

### Riscos conhecidos & mitigações

| Risco | Severidade | Mitigação atual | Sprint futuro |
|---|---|---|---|
| Prompt injection via fato persistido | Média | Sanitização CPF/CNPJ; system prompt com regras rígidas no ExtrairFatosAgent | Validador adicional contra prompt injection (regex + LLM judge) |
| Vazamento cross-tenant | **Alta** | `business_id + user_id` em toda query; testes Pest cobrem isolamento | Tenant Scope global no Eloquent (defesa em profundidade) |
| LLM provider abuso (DoS via tokens) | Média | Falha gracefull, log estruturado | Rate limiting per-user + cost ceiling ($X/mês/cliente) |
| Meilisearch daemon down | Baixa | Recall falha silente | Cron de auto-restart; alerta |
| Segredo Meilisearch vazado | Baixa | Master key em .env | Rotação periódica + 2nd factor |

---

## 6. Operações dia-a-dia (runbook resumido)

### Deploy

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin 6.7-bootstrap
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan migrate --no-interaction
php artisan optimize:clear
php artisan memcofre:sync-pages   # popula docs_pages
```

### Health check

```bash
# App health
curl -s -o /dev/null -w "%{http_code}\n" https://oimpresso.com/         # esperado 200
curl -s https://oimpresso.com/copiloto                                  # 302 (redirect login se anon)

# Meilisearch health
curl -s http://127.0.0.1:7700/health                                    # {"status":"available"}

# Queue health
php artisan horizon:status                                              # running
```

### Métricas críticas (a instrumentar — sprint 7)

| Métrica | Meta | Sintoma de problema |
|---|---|---|
| `copiloto_responderchat_latency_p95_ms` | < 5000 | LLM provider lento OU recall lento |
| `copiloto_memoria_recall_latency_p95_ms` | < 200 | Meilisearch sob pressão |
| `copiloto_extraction_job_failure_rate` | < 5% | Schema LLM quebrando |
| `copiloto_tokens_per_user_month_brl` | < R$5 | User abusivo OU caching off |
| `copiloto_memoria_facts_per_user` | 50-500 | <50 = bridge não está extraindo; >500 = falta cleanup |

### Backups

- DB MySQL: backup diário automático (Hostinger plan) + spatie/laravel-backup
- Meilisearch: snapshots manuais (`/snapshots` endpoint) — sprint futuro automatizar
- ADRs/specs: versionados em git ✅

---

## 7. SLA / SLO targets (proposta — não contratual ainda)

| Componente | Disponibilidade | Latência p95 | Janela de manutenção |
|---|---|---|---|
| `/copiloto` (chat) | 99.5% (mensal) | < 5s | sábado 02-04h BRT |
| `/copiloto/memoria` (LGPD) | 99.9% (mensal) | < 1s | só com 30d de aviso |
| Meilisearch (recall) | 99% (mensal) | < 200ms | embarcado no plan App |
| Extração de fatos (queue) | best-effort | sem SLA UX | embarcado |

**Justificativa LGPD 99.9%:** direitos do titular (Art. 18º) não podem depender de janela de chat — esquecer/listar tem que estar disponível mesmo quando chat estiver caído.

---

## 8. Modelo de custo

| Item | Tier atual (0 clientes pagantes) | Tier 1A (R$199-599/mês) | Tier 5+ (50 clientes) |
|---|---|---|---|
| Hostinger Cloud Startup | já pago | já pago | provavelmente VPS dedicado |
| Meilisearch self-host | R$0 | R$0 | R$0 (mesmo servidor) |
| OpenAI/Anthropic API tokens | R$0 (dry_run) | ~R$5-50/cliente/mês | ~R$250-2500/mês total |
| `laravel/ai`, `boost`, `scout`, `horizon`, `telescope`, `pail` | R$0 (OSS MIT) | R$0 | R$0 |
| Mem0 (sprint 8+ condicional) | R$0 | R$0 | R$1.500-18.000/ano se trigger ativar |
| **Total recorrente** | **R$0** | **~R$50-500/mês** | **~R$3000-30000/mês** (10% do MRR) |

**Margem operacional estimada Tier 5+:** ~70-90% (dependendo de mix de tokens vs caching).

---

## 9. Roadmap de evolução (próximos 9 sprints)

Sequencial, mensurável, com gatilho de pivot por sprint. Detalhado em ADR 0037.

| Sprint | Entrega | Métrica de sucesso | Custo |
|---|---|---|---|
| 7 (gate) | RAGAS evaluation no CI/CD | baseline numérica registrada | 1 sprint |
| 8 | Semantic caching | -68.8% tokens em queries similares | 0.5 sprint |
| 9 | RRF tuning Meilisearch | +10-15% recall mensurado | 0.5 sprint |
| 10 | HyDE / query expansion | +15% recall mensurado | 1.5 sprints |
| 11 (condicional) | Mem0RestDriver upgrade | 1+ trigger ativado (ADR 0036) | 0-3 sprints |

**Trade-off explícito:** não vale ir além de Tier 8 LongMemEval sem 10+ clientes pagantes.

---

## 10. Inventário de artefatos (rastreabilidade)

### ADRs canônicos (memory/decisions/)

| ID | Título | Status |
|---|---|---|
| 0026 | Posicionamento "ERP gráfico com IA" | ✅ Aceita |
| 0027 | Gestão de memória do projeto | ✅ Aceita |
| 0030 | Credenciais nunca em git | ✅ Aceita |
| 0031 | `MemoriaContrato` + driver default | ✅ Aceita (revisado por 0036) |
| 0032 | Vizra ADK + Prism PHP | ✅ Aceita (sprint 1 revisado por 0034) |
| 0033 | Vector store backend | ✅ Aceita (revisado por 0036) |
| 0034 | Laravel AI ecosystem 2026 | ✅ Aceita |
| 0035 | Stack canônica IA — verdade | ✅ Aceita |
| 0036 | Replanejamento Meilisearch first | ✅ Aceita |
| 0037 | Roadmap evolução Tier 7-9 | ✅ Aceita |

### Comparativos Capterra (memory/comparativos/)

| Arquivo | Foco |
|---|---|
| `_TEMPLATE_capterra_oimpresso.md` v1.0 | Template oficial |
| `oimpresso_vs_concorrentes_capterra_2026_04_25.md` | Produto vs Mubisys/Zênite/Calcgraf etc |
| `sistemas_memoria_oimpresso_capterra_2026_04_26.md` | 9 sistemas de memória dev (camada A) |
| `copiloto_runtime_memory_vs_mem0_*` | 7 frameworks de memória runtime |
| `stack_agente_php_vizra_prism_mem0_*` | Stack completa A+B+C |
| `revisao_caminho_2026_04_27_capterra.md` | 5 caminhos pós-sprint 6 |

### PRs mergeados em `6.7-bootstrap`

| PR | Sprint | Conteúdo |
|---|---|---|
| #24 | 1 | `laravel/ai` + 3 Agents + `LaravelAiSdkDriver` |
| #25 | 4 | `MemoriaContrato` + `MeilisearchDriver` + `NullDriver` + horizon/telescope/pail |
| #26 | 5 | Bridge memória↔chat (recall + extração async) |
| #27 | 6 | Tela `/copiloto/memoria` (LGPD US-COPI-MEM-012) |
| #28 | — | MemCofre score: 3 telas com bloco `@memcofre` indexáveis |

### Telas indexadas em `docs_pages` (MemCofre)

| Tela | Stories | Rules | ADRs | Tests |
|---|---|---|---|---|
| `/copiloto` (Chat) | 4 | 2 | 6 | 2 |
| `/copiloto/dashboard` | 3 | 2 | 4 | 1 |
| `/copiloto/memoria` | 3 | 2 | 5 | 2 |

---

## 11. Disclaimers & dependências externas

- **Vizra ADK** ainda incompatível com Laravel 13 (sem issue aberta no upstream em 2026-04-27)
- **Laravel AI SDK** é new (fev/2026) — bug surface emergente; mitigação: `LaravelAiSdkDriver` faz fallback pra fixtures em qualquer Throwable
- **Meilisearch v1.10.3** (2024-09) usado no Hostinger por GLIBC 2.34; latest exige 2.35
- **Pusher 5.0** lockado bloqueia adoção de Reverb (PR separado pra fazer upgrade pusher 5→7 + Reverb quando Wagner aprovar)
- **MemCofre** módulo pretende-se evoluir pra UI de upload (GAP 3 do comparativo de sistemas de memória) — atualmente só formato de markdown + sync de blocos `@memcofre`

---

## 12. Glossário rápido (para o leitor enterprise)

- **Tenant** = `business` no UltimatePOS. Multi-tenant nativo via `business_id` em toda tabela.
- **Hot Path / Cold Path** = padrão dual-layer 2026: recall síncrono antes do LLM, extração assíncrona depois (queue).
- **MemoriaContrato** = interface PHP da camada C. Trocável (Meilisearch/Mem0/Null) sem reescrever app.
- **LongMemEval** = benchmark ICLR 2025 pra qualidade de memória de agente.
- **RAGAS** = framework standard pra avaliar pipelines RAG (faithfulness, recall, precision) sem gold answers.
- **`@memcofre` block** = comentário JS no header de pages Inertia que `memcofre:sync-pages` parseia pra `docs_pages`.

Para terminologia legal/CLT/fiscal/comercial, ver [GLOSSARY.md](GLOSSARY.md).

---

**Autor desta versão:** Claude Opus 4.7 (1M context) sob direção do Wagner — *"profissionalizar os pequenos detalhes... descreva [nível] enterprise"*.
**Próxima revisão sugerida:** quando Sprint 7 (RAGAS) entregar baseline mensurável OU quando 1º cliente pagante assinar.
