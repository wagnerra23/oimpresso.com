# CURRENT — Cycle 01 (29-abr → 12-mai-2026, 10 dias úteis)

> Foto do agora. Backlog completo: [`TASKS.md`](TASKS.md). Histórico: `memory/sessions/`. Cycles fechados: `memory/cycles/`.

**Branch ativa:** `main` · **Cycle anterior:** N/A (primeiro Cycle formal) · **Cycle owner:** Wagner [W] · **Modo:** ⚡ **SOLO** (time redistribuído no Cycle 02)

---

## 🎯 Goal do ciclo (outcome, não output)

> **"Copiloto assertivo e econômico em produção: Larissa pergunta faturamento e recebe resposta correta, com cache semântico reduzindo custos de token ≥50%."**

**3 métricas de sucesso:**
1. 🟡 **Copiloto responde faturamento/metas Larissa corretamente** — código deployed (29-abr `2be9930c`); ctx prod = 4 meses faturamento + 5993 clientes em 164 tokens; aguarda validação real Larissa (A4)
2. ✅ **`memoria_recall_chars > 0` nos logs** — bateu 190 em 2026-04-29 (prod) após MEM-HOT-1
3. 🔲 **Dashboard `/copiloto/admin/custos` validado em test + merged** (US-COPI-070)

**Gate opcional Cycle 01:**
- 🔲 Semantic cache hit rate >30% após 10 conversas similares (MEM-S8-1)
- 🔲 50 perguntas golden set — baseline RAGAS registrado (MEM-P2-1)

---

## 🔥 Active — Wagner solo (WIP máx 2)

| # | WIP | Task | Prazo | Status |
|---|---|---|---|---|
| ~~A1~~ | — | ~~MEM-MET-3: Scheduler diário~~ | qui 30-abr | ✅ **29-abr** (`01e4e214` — cron Hostinger 55 23 * * * confirmado) |
| A1 | 1/2 | **A4 rodada 2: Validar Larissa** — chat real com 3 perguntas (Quanto vendi? / Líquido? / Caixa?) → 3 respostas distintas | sex **02-mai** | ⏳ |
| A2 | 2/2 | **COP-002 = MEM-MET-5: Golden set v1** — 50 perguntas Larissa-style (destrava 6 colunas RAGAS) | seg **05-mai** | ⏳ |

**On-deck imediato (puxar quando A1/A2 fechar, em ordem de impacto×esforço):**

| # | Task | Dias | Por que esta ordem |
|---|------|------|---------------------|
| O1 | **MEM-MET-4 = COP-007 ampliada: Page `/copiloto/admin/qualidade`** trend 30d das 8 métricas + HITL anotação | 2d | Bug visibility — sem dashboard, métricas viram "informativas" |
| O2 | **MEM-S8-1: SemanticCacheMiddleware** (-68.8% tokens LLM) | 1.5d | Token economy direta; depende de mais conversas pra calibrar threshold |
| O3 | **MEM-S8-2: ConversationSummarizer** (>15 turnos → resumo <200 tokens) | 1.5d | Token economy em conversas longas (não temos ainda) |
| O4 | **MEM-S8-3: ProfileDistiller** (job diário perfil negócio <300 tokens) | 1d | Refina ContextoNegocio do MEM-HOT-2 |
| O5 | **COP-P22 = MEM-P2-2: RRF tuning** A/B `semanticRatio` 0.3 vs 0.7 | 0.5d | Calibração; precisa de golden set primeiro |

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

---

> Esse arquivo é sobrescrito quando Cycle muda. Cycle anterior arquivado em `memory/cycles/CICLO-NN-YYYY-MM-DD.md` com retro de 5 linhas.
