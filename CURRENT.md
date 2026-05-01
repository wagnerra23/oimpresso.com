# CURRENT — Cycle 01 (29-abr → 12-mai-2026, 10 dias úteis)

> Foto do agora. Backlog completo: [`TASKS.md`](TASKS.md). Histórico: `memory/sessions/`. Cycles fechados: `memory/cycles/`.

**Branch ativa:** `main` · **Cycle anterior:** N/A (primeiro Cycle formal) · **Cycle owner:** Wagner [W] · **Modo:** ⚡ **SOLO** (time redistribuído no Cycle 02)

---

## 🎯 Goal do ciclo (outcome, não output)

> **"Copiloto assertivo e econômico em produção: Larissa pergunta faturamento e recebe resposta correta, com cache semântico reduzindo custos de token ≥50%."**

**3 métricas de sucesso:**
1. ✅ **Copiloto responde faturamento/metas Larissa corretamente** — chat real prod 29-abr 09:06-09:25 com 3 perguntas distintas (Quanto vendi/Líquido/Caixa) retornou 3 números diferentes corretos (caixa R$ 27.272,62 ≠ bruto R$ 31.513,29). MEM-FAT-1 (`fac96a19`) + ADR 0052 validados em produto, não só em código.
2. ✅ **`memoria_recall_chars > 0` nos logs** — bateu 190 em 2026-04-29 (prod) após MEM-HOT-1
3. 🔲 **Dashboard `/copiloto/admin/custos` validado em test + merged** (US-COPI-070)

**Gate opcional Cycle 01:**
- 🔲 Semantic cache hit rate >30% após 10 conversas similares (MEM-S8-1)
- 🔲 50 perguntas golden set — baseline RAGAS registrado (MEM-P2-1)

---

## 🔥 Active — Wagner solo (WIP máx 2)

| # | WIP | Task | Prazo | Status |
|---|---|---|---|---|
| ~~A1~~ | — | ~~MEM-MET-3: Scheduler diário~~ | qui 30-abr | ✅ **29-abr** (`01e4e214`) |
| ~~A1~~ | — | ~~A4 rodada 2: Validar Larissa~~ | sex 02-mai | ✅ **29-abr 09:25** chat real prod 3 perguntas → 3 respostas distintas |
| ~~A1~~ | — | ~~MEM-CC-team-1 Sprint A+B~~: `.mcp.json` + skill onboarding + skill watcher + CcSearchTool + 3 tabelas mcp_cc_* em prod | qui 30-abr | ✅ **29-abr noite** (`4fa97dd8` + `c807d5db` + `acc3b0c1`) |
| ~~A1~~ | — | ~~MEM-TEAM-1 Self-host equiv Anthropic Team plan~~ — Team Admin + QuotaEnforcer + alertas idempotentes 50/80/100% + ADR 0055 | qui 30-abr | ✅ **29-abr** (`c4706bef` + `c2339ba1` + `8c8b7ccb`) |
| ~~A1~~ | — | ~~MEM-MEM-MCP-1 MCP-as-memory-source~~ — McpMemoriaDriver + MemoriaSearchTool + ADR 0056 + Copiloto chat usa MCP | qui 30-abr | ✅ **29-abr** (`a58e7f34`) |
| ~~A1~~ | — | ~~MEM-CC-team-2: Wagner roda watcher~~ — V1 watcher Node criado em `scripts/cc-watcher/` (npm install + npm start). Ingestou várias sessões em prod (4697+ msgs em 1 sessão validada) | sex 02-mai | ✅ **30-abr** (`770357af`) |
| ~~A2~~ | — | ~~COP-002 = MEM-MET-5: Golden set v1~~ — 50 perguntas em prod (`copiloto_memoria_gabarito`); comando `copiloto:eval` em prod com flags `--persist`/`--resposta`/`--top-k`/`--business`; eval baseline rodado | seg 05-mai | ✅ **30-abr** (gabarito seedado + comando AvaliarGabaritoCommand validado) |
| ~~A1~~ | — | ~~MEM-MET-4 Page /copiloto/admin/qualidade~~ V1 entregue 30-abr (`4fa0eb1d`) — KPIs 8 métricas + sparklines + gates ADR 0049/0050 | qui 08-mai | ✅ **30-abr** |
| ~~A1~~ | — | ~~MEM-MEM-WIRE Phase 2~~ (HyDE + Reranker + Negative cache) — **EM ESPERA** (30-abr): foco trocou pra MCP memória + ADRs. Retomar quando Recall@3 for bloqueante pro goal do Cycle 02. | dom **11-mai** | ⏸️ **ESPERA** |
| ~~A2~~ | — | ~~F2 migração auto-mems P1~~ — **EM ESPERA** (30-abr): englobada pela nova prioridade MCP KB (MEM-KB-3 cobre ADRs + referências infra juntos). | seg **05-mai** | ⏸️ **ESPERA** |
| ~~A1~~ | — | ~~**MemoriaAutonoma F1**: `copiloto:sintese-semanal` + cron sex 18h + `SinteseSemanalAgent` (Haiku 4.5)~~ | sex 30-abr | ✅ **30-abr** (`395be83a`) |
| ~~A1~~ | — | ~~**TaskRegistry F0** (Jira-like MCP): migration `mcp_tasks` + `McpTasksSyncCommand` + parser SPEC + `TasksListTool` + `TasksDetailTool` + 133 tasks sincronizadas em prod + US-TR-004 webhook GitHub→`mcp:tasks:sync` automático~~ | qua 30-abr | ✅ **30-abr/01-mai** (`009dc127` + webhook id 614879953 ativo) |
| A1 | 1/2 | **MEM-KB-3 F2: frontmatter YAML + migração 57 ADRs** — frontmatter obrigatório em `memory/decisions/*.md` + Claude infere campos faltantes (status/authority/lifecycle/quarter) + colunas tipadas em `mcp_memory_documents`. Destrava filtros queryable na KB UI. | sex **09-mai** | 🔥 **NOVO FOCO** |
| A2 | 2/2 | **MEM-MEM-MCP-1.b: ligar driver MCP no Copiloto** — `copiloto:mcp:system-token` + `COPILOTO_MEMORIA_DRIVER=mcp` no `.env` Hostinger + smoke chat → recall via MCP | ter **06-mai** | 🔥 **NOVO FOCO** |

**On-deck imediato (puxar quando A1/A2 fechar, em ordem de impacto×esforço):**

| # | Task | Dias | Por que esta ordem |
|---|------|------|---------------------|
| O1 | **MEM-MEM-MCP-1.b**: gerar system token (`copiloto:mcp:system-token`) + add `COPILOTO_MEMORIA_DRIVER=mcp` em `.env` Hostinger + smoke chat → recall via MCP | 0.5d | Liga MCP no fluxo Copiloto chat real (config commitada, falta env) |
| O2 | **MEM-EVAL-3 backfill facts**: `copiloto:backfill-fatos --business=all --sync` + re-rodar gabarito → mede ΔR@3 | 0.5d | Phase 1→2 ADR 0054 (corpus famished, deve subir R@3 0.125→~0.30) |
| O3 | **MEM-MEM-WIRE Phase 2**: wire HyDE + Reranker + Negative cache no MeilisearchDriver | 1.5d | +15-20pp recall esperado (services prontos do `3d060fec`) |
| O4 | **Fix ProfileDistiller** (output vazio com biz=4) | 1h | -30% system prompt em todas requests |
| O5 | **MEM-S8-4 Auto-promote logic** — service que marca facts hits≥5 → core_memory | 0.5d | Phase 4 ADR 0054 |
| O6 | **MEM-MET-4 = `/copiloto/admin/qualidade`** trend 30d das 8 métricas + RAGAS + HITL | 2d | Cycle 01 goal métrica 3 |
| O7 | **MEM-P2-2 RRF tuning** A/B `semantic_ratio` 0.3 vs 0.7 (Sprint 9 ADR 0037) | 0.5d | Phase 2-3 ADR 0054 |
| O8 | **MEM-GAP-1: Knowledge UI** equivalente Anthropic Projects — upload PDF/MD/CSV, dedup SHA256, perms can-use/can-edit | 2d | Saved gap ADR 0055 |
| O9 | **MEM-GAP-2/3/4** (Projects shared / file restrictions / centralized policy) — equivalentes Team plan | 4.5d | Saved gaps ADR 0055 |
| O10 | **MEM-KB-1: Page `/copiloto/admin/memoria` — KB browser do MCP server** (DataTable filtros type/module/scope, Sheet 600px preview markdown + git_sha→GitHub, history/diff inline, audit log read-only, PII badge + soft-delete double-confirm) | ✅ entregue 2026-04-30 (`d687b890..5c250cfd`) | Transparência LGPD Art. 18 + auditoria pré-onboarding Felipe/Maíra/Luiz/Eliana; refs Mem0 OpenMemory, Letta ADE, MCP Inspector |
| O11 | **MEM-KB-2 F1: sync expansion** — `IndexarMemoryGitParaDb` cobre comparativos + ADRs por módulo + RUNBOOK/AUDITS/CHANGELOG + memory raiz (~270 docs faltando) | ✅ entregue 2026-04-30 | Backfill destrava 488/488 docs no MCP |
| O12 | **MEM-KB-3 F2 (Cycle 02):** frontmatter YAML obrigatório + migração 57 ADRs antigos com Claude inferindo + colunas tipadas (status/authority/lifecycle/quarter) em `mcp_memory_documents` | 1d | Pré-filtros queryable + governance taxonomy |
| O13 | **MEM-KB-4 F3 (Cycle 02):** taxonomia 2 tabelas (`mcp_taxonomy_terms` + `mcp_document_terms`) — kinds tag/stakeholder/area | 0.5d | Filtros multi-eixo na KB UI; tag cloud sidebar |
| O14 | **MEM-KB-5 F4 (Cycle 02):** grafo `mcp_memory_relations` (supersedes/related/cites/depends_on parsed) + tool MCP `memory-graph` | 0.5d | Claude descarta superseded automaticamente |
| O15 | **MEM-KB-6 F5 (Cycle 02):** chunking semântico (~800 tokens overlap 150) + tabela `mcp_memory_chunks` + Scout searchable + Meilisearch index dedicado com hybrid embedder | 1.5d | -75% tokens contexto IA — biggest win |
| O16 | **MEM-KB-7 F6 (Cycle 02):** signals dinâmicos (`authority_score`/`freshness_score`/`usage_score` + `hits_count`) + auto-promote `hits>=5` (Phase 4 ADR 0054) | 1d | Claude prefere docs canônicos automático |
| O17 | **MEM-KB-8 F7 (Cycle 02):** integração log retrieval com `copiloto_memoria_metricas` (NÃO duplicar OTel GenAI ADR 0051) + dashboard "docs mais lidos pela IA" | 0.5d | Governança do que IA está usando vs ignorando |
| O18 | **INFRA-RT-1: Centrifugo + FrankenPHP no CT 100** (ADR 0058) — DNS `realtime.oimpresso.com`, JWT auth, broadcast driver, migra hooks Echo→Centrifuge | 4d | Reverb crashou em testes; realtime canônico empresa |
| O19 | **MEM-CC-UI-1: Page `/copiloto/admin/cc-sessions`** — KB sessões Claude Code do time (lista split/preview + thread reconstruction + search FULLTEXT + Cmd+K + drill-down per-dev + watcher Node ingest) | 6d | **Maior lacuna estratégica** — schema `mcp_cc_*` pronto, falta UI; capitaliza R$ 11k/dia em conhecimento institucional. SPEC: [memory/requisitos/Copiloto/SPEC-cc-sessions.md](memory/requisitos/Copiloto/SPEC-cc-sessions.md) |

---

## ✅ Desbloqueados neste Cycle (2026-04-28 → 04-29)

### Infra (28-abr)

| Item | Data | Notas |
|------|------|-------|
| OPENAI_API_KEY no Hostinger | 28-abr | Wagner forneceu |
| DNS `meilisearch.oimpresso.com` | 28-abr | API `developers.hostinger.com` — PUT overwrite:false |
| Cert Let's Encrypt R12 Meilisearch | 28-abr | Restart Traefik após DNS propagar |
| `config/ai.php` commitado | 28-abr | Era untracked; gpt-5.4 fallback eliminado |
| Log channel `copiloto-ai` | 28-abr | Estava faltando em `config/logging.php` |
| SCOUT_DRIVER + MEILISEARCH env | 28-abr | `.env` Hostinger configurado |
| Embedder OpenAI text-embedding-3-small | 28-abr | Index `copiloto_memoria_facts` configurado e validado e2e |
| **Copiloto IA real em produção** | 28-abr | gpt-4o-mini respondendo (Wagner + Larissa testaram) |

### Sprint memória (29-abr — 8 entregas em 1 dia)

| Item | Commit | Notas |
|------|--------|-------|
| **MEM-HOT-1** Hybrid embedder MeilisearchDriver | `c631042c` | Recall 0→190 chars em log conversa Larissa real |
| **MEM-HOT-2** ContextoNegocio injetado no ChatCopilotoAgent | `2be9930c` | Prompt biz=4 ROTA LIVRE em 164 tokens (4 meses faturamento + 5993 clientes) |
| **ADR 0047** Wagner solo + sprint memória | `da6ce166` | Modo solo formalizado; donos F/M/L/E → W |
| **ADRs 0048-0050 + 0036 estendida** Pesquisa Wagner | `793f3efa` | Vizra rejeitada (COP-015 cancelada); 6 camadas memória; 8 métricas; benchmark 95.2% LongMemEval |
| **ADR 0051** Schema próprio + adapter + OTel GenAI | `21644f4e` | Estratégia formal pós-pesquisa de tendências |
| **MEM-MET-1** Tabela `copiloto_memoria_metricas` em prod | `21644f4e` | 14 colunas (8 obrigatórias + 3 RAGAS-aligned + 3 contexto) |
| **MEM-OTEL-1** Emissão `gen_ai.*` OpenTelemetry GenAI | `5acf27de` | 12 atributos OTel-compliant em log channel `otel-gen-ai`; pronto pra Langfuse/Datadog/Arize |
| **MEM-MET-2** Comando `copiloto:metrics:apurar` + baseline | `6d2dc7eb` `6aa9b524` | 3 linhas baseline gravadas em prod (plataforma + biz=1 + biz=4) |

**Suite Copiloto:** 50 → **77 passed (+27 testes hoje)**, 3 skipped, **zero regressão**.

---

## 🚧 Gaps conhecidos (não-bloqueantes, roadmap)

| Gap | ADR | Sprint | Status |
|-----|-----|--------|--------|
| ~~MeilisearchDriver usa Scout default = full-text~~ | ADR 0046 | A1 fechou 29-abr | ✅ resolvido `c631042c` (prod recall=190 chars) |
| ~~ChatCopilotoAgent "burrinho" — sem contexto de negócio~~ | ADR 0046 | A2 fechou 29-abr | ✅ resolvido `2be9930c` (164 tokens ctx em prod) |
| ~~Tabela de métricas inexistente~~ | ADR 0050 | MEM-MET-1 fechou 29-abr | ✅ resolvido `21644f4e` |
| ~~Telemetria estruturada inexistente~~ | ADR 0051 | MEM-OTEL-1 fechou 29-abr | ✅ resolvido `5acf27de` |
| ~~Apuração diária de métricas~~ | ADR 0050 | MEM-MET-2 fechou 29-abr | ✅ resolvido `6d2dc7eb` (baseline gravado) |
| Apuração diária NÃO automatizada (cron) | ADR 0050 | A1 esta semana | 🔴 fix imediato (MEM-MET-3) |
| Validação real Larissa pendente | Goal #1 do Cycle | A2 esta semana | 🔴 dependência humana |
| RAGAS golden set (50 perguntas) — baseline nunca medido | ADR 0049/0050 | O1 fila | 🟠 destrava 6 colunas RAGAS |
| Page `/copiloto/admin/qualidade` não existe | ADR 0050 | O2 fila | 🟡 sem dashboard métricas viram informativas |
| Semantic cache não implementado (-68.8% tokens) | ADR 0037 Sprint 8 | O3 fila | 🟡 |
| Conversation summarizer não implementado | ADR 0047 | O4 fila | 🟡 |
| Profile distiller não implementado | ADR 0047 | O5 fila | 🟡 |

---

## 📊 Métricas do Cycle (Wagner atualiza toda sexta)

| Indicador | Alvo | Track |
|-----------|------|-------|
| `memoria_recall_chars > 0` | sim | ❌ hoje |
| Copiloto responde faturamento Larissa | sim | ❌ hoje |
| Conv 20 turnos usa <2.000 tokens contexto | sim | — |
| Semantic cache hit rate | >30% | — |
| PRs merged / tasks fechadas | ≥ 5 | 0 |

---

## 🖥️ Infra ativa (2026-04-28 estado final)

| Serviço | URL | Status |
|---------|-----|--------|
| App Hostinger | `https://oimpresso.com` | ✅ L13.6 PHP 8.4 |
| Traefik v3.6 | `https://traefik.oimpresso.com` | ✅ TLS auto |
| Portainer | `https://portainer.oimpresso.com` | ✅ |
| Vaultwarden | `https://vault.oimpresso.com` | ✅ |
| Reverb | `https://reverb.oimpresso.com` | ✅ WebSocket |
| Meilisearch v1.10.3 | `https://meilisearch.oimpresso.com` | ✅ TLS R12 |
| Copiloto IA | `/copiloto/chat` | ✅ gpt-4o-mini |

**Copiloto stack de memória:**

| Camada | Componente | Estado |
|--------|-----------|--------|
| A | `laravel/ai ^0.6.3` + `config/ai.php` | ✅ gpt-4o-mini |
| B | `LaravelAiSdkDriver` + 4 Agents | ✅ prod |
| C Hot | `SqlDriver` — conversas em DB | ✅ |
| C Cold | `MeilisearchDriver` | ✅ hybrid embedder ativo (29-abr commit `c631042c`) |
| Embedder | OpenAI text-embedding-3-small | ✅ funcional |

---

## 📅 Próximo Cycle 02 (13-mai → 26-mai-2026)

**Goal provável:** *Redistribuir time + iniciar PontoWr2 Tier A + Eliana(WR2) validação.*

Tasks candidatas (não puxar antes!):
- MEM-P2-2: RRF tuning (semantic_ratio A/B)
- PNT-001: PontoWr2 Tier A Dashboard vivo
- PNT-002: Validar Eliana(WR2)

---

## 🔄 Mudanças desde abertura do Cycle (2026-04-28 → 29)

- Infra CT 100: Traefik + Portainer + Vaultwarden + Reverb + Meilisearch todos ✅ em prod
- Copiloto IA real ativo (gpt-4o-mini) — primeiro dia de conversas reais
- **MEM-HOT-1 deployado** 29-abr (`c631042c`) — recall 0→190 chars em prod
- **MEM-HOT-2 deployado** 29-abr (`2be9930c`) — ContextoNegocio injetado, 164 tokens em prod
- ADRs do Cycle: 0042-0044 (infra) · 0045 (DNS API) · 0046 (ChatAgent gap) · 0047 (Wagner solo + sprint memória)
- **ADRs novos 29-abr (pesquisa Wagner consolidada):**
  - **0048** — Vizra ADK rejeitada oficialmente (quebrou L13); `laravel/ai` consolidado; **COP-015 cancelada**
  - **0049** — 6 camadas memória (Working/ConvHist/Episodic/Semantic/Procedural/Reflective); gate Recall@3>0.80
  - **0050** — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`; tasks MEM-MET-1..5 adicionadas
  - **0051** — Schema próprio + adapter pattern + emissão OpenTelemetry GenAI (estratégia formal pós-pesquisa de tendências)
  - **0036 estendida** — benchmark BM25+vetor=95.2% LongMemEval (supera Mem0 93.4%, Zep 71.2%) + 5 triggers concretos pra reavaliar
- **MEM-MET-1 deployado** 29-abr (`21644f4e`) — `copiloto_memoria_metricas` em prod com 8 obrigatórias + 3 RAGAS (faithfulness, answer_relevancy, context_precision); 7 testes passing
- **MEM-OTEL-1 deployado** 29-abr (`5acf27de`) — emissão `gen_ai.*` OpenTelemetry GenAI em prod; 12 atributos OTel-compliant por evento (system/model/usage/duration + custom business_id); 5 testes passing; pronto pra plug Langfuse/Datadog/Arize
- **MEM-MET-2 deployado** 29-abr (`6d2dc7eb`) — comando `copiloto:metrics:apurar` em prod; **baseline 2026-04-29 gravado** (3 linhas: plataforma + biz=1 + biz=4 ROTA LIVRE com latência p95=1234ms, tokens_médios=307, 6 interações, 2 memórias, bloat=1.000, contradições=0%); 9 testes passing
- **MEM-FAT-1 deployado** 29-abr (`fac96a19`) — `ContextoNegocio.faturamento90d` expõe 3 ângulos (bruto/líquido/caixa) — Larissa testou e gap exposto (mesmo R$ pra 3 perguntas); fix: glossário inline + 3 valores por mês; prompt 270 tokens; ADR 0052 formaliza padrão "expose multiple angles"
- **MEM-MCP-1.a Dia 1 entregue** 29-abr (em commit) — schema 9 migrations `mcp_*` + 9 Entities (McpScope/UserScope/Token/Quota/AuditLog/UsageDiaria/Alerta/MemoryDocument/MemoryDocumentHistory) + service `IndexarMemoryGitParaDb` (PII redactor, frontmatter parser, idempotente, soft-delete) + comando `mcp:sync-memory` + endpoint webhook `/api/mcp/sync-memory` + 2 skills paralelas (oimpresso-stack, copiloto-arch); 18 testes Mcp passing; ADR 0053 formaliza decisão "MCP = governança como produto"; DNS `mcp.oimpresso.com → 177.74.67.30` criado e propagado
- **MEM-MCP-1.b Dia 2 entregue** 29-abr (`ff6abc0a`) — **MCP server vivo em prod**: container Docker em CT 100 Proxmox + Tailscale SSH automatizado pra deploy + Hostinger Remote MySQL whitelist 177.74.67.30 + cert Let's Encrypt R13 emitido + 9 migrations rodadas em prod + comando `mcp:token:gerar` + middleware McpAuth + endpoint `/api/mcp/health` (público) + `/api/mcp/health/auth` (token Bearer) — 3/3 endpoints funcionando: público=200, autenticado=200, sem-token=401 com audit log; Wagner conectado via Tailscale (próximas sessões: zero fricção manual); 5 bugs encontrados+corrigidos durante deploy (compose YAML folded, ro/rw mount, .env path, Telescope DB, certresolver `le` vs `letsencrypt`); skill `proxmox-docker-host` + session log capturam aprendizados

---

> Esse arquivo é sobrescrito quando Cycle muda. Cycle anterior arquivado em `memory/cycles/CICLO-NN-YYYY-MM-DD.md` com retro de 5 linhas.
