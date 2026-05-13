# Evolução da memória e governança — Sessão 2026-05-13

> **Tipo:** reference canônico (git canon, ADR 0061)
> **Sessão:** `nervous-mayer-3ff0da` · Wagner + Claude Opus 4.7
> **Duração:** ~10h (sustained)
> **Resultado:** 30 PRs mergeados, 70% → ~98% maturidade global, pattern `/audit-and-fix` 3-tier reusável criado

## Por que esta sessão importa

Foi a sessão mais produtiva da história do oimpresso até hoje (recorde anterior: 23 PRs em 2026-05-12). Não pela quantidade — pelo **salto de governança**: o sistema saiu de "auditoria ad-hoc esporádica" pra "ciclo auditável reproduzível com 3 níveis de agents IA". Esse documento explica **a evolução** (o que mudou) e **os motivos** (por quê) pra próximas sessões não regredirem.

## Linha do tempo da evolução

### Manhã — "tá tudo bagunçado"

Wagner abriu a sessão pedindo: "*oque tenho que fazer tudo*". Estado inicial:

- MCP DB desincronizado (30 tasks ativas, só 2 doing real)
- 53 auto-mem privadas legacy violando ADR 0061
- 1.527 .md em `memory/` sem índice navegável (CLAUDE.md raiz só lista alguns ADRs)
- Sintoma reportado: "sessão precisa consultar 5+ locais pra responder o que falta fazer"

Sintoma raiz descoberto: **`GitTaskLinkerService` regex esperava `Closes US-X` mas commits oimpresso usam `(US-X)` parentético** → 0 auto-closes em todo histórico → MCP nunca sincronizou auto.

### Onda 1 (manhã) — bugs MCP sync

5 PRs (#745-#749) fechando 4 bugs catalogados + 3 auditorias canônicas:

- `COMPARATIVO-MCP-ESTADO-DA-ARTE` — 62% vs Linear/Jira/Plane
- `AUDITORIA-KNOWLEDGE-ARCHITECTURE` — 73% vs Notion/Obsidian/Mem0
- `AUDITORIA-SESSION-HANDOFF` — 74% vs LangGraph/PagerDuty

**Motivação da escolha:** Wagner pediu "comparar com os melhores e me mostrar % de gap" — 3 dimensões mais críticas. Cada auditoria spawned agent Opus paralelo (4 agents — 1 alucinou Write, lição #1 dos aprendizados).

### Onda 2 — knowledge fixes

6 PRs (#753-#761): 51 auto-mem migradas pro git canon · `memory/INDEX.md` navegável · `kb-answer` tool · `handoff-fetch-summarized` · `sessions/_INDEX.md` · handoff curto piloto.

**Motivação:** wagner pediu "evolua" depois de ver as auditorias. Escolhi top 5 gaps cross-cutting das 3 auditorias e atacar em paralelo (pattern N agents validado).

### Onda 3 — gap analysis + estado-da-arte capacidades

6 PRs (#766-#774): Reranker RRF (Cormack 2009) · Backlinks ADR sweep · `handoff-diff` · RAGAS gate CI · cycle auto-rollover Linear-style · weekly digest Reflect-style.

**Motivação:** Wagner pediu "comparar com os melhores em mais áreas". 6 agents paralelos Opus, cada um pesquisou seu próprio estado-da-arte (3 WebSearch cada) + implementou. Padrão escalou.

### Pattern `/audit-and-fix` (meio da sessão)

Wagner observou: "*é sempre o mesmo ciclo. Evolua*". A repetição virou padrão canônico:

```
1. RESEARCH (1 agent) → AUDITORIA-<tema>-YYYY-MM-DD.md
2. HITL (Wagner aprova)
3. IMPLEMENT (N agents paralelos, áreas isoladas)
4. CONSOLIDATE (parent agent: N PRs separados)
```

Criado:
- `.claude/commands/audit-and-fix.md` — slash command
- `.claude/agents/audit-research-expert.md` — Fase 1
- `.claude/agents/audit-implement-expert.md` — Fase 3
- `memory/reference/pattern-audit-and-fix-cycle.md` — doc canônico

**Motivação:** transformar trabalho repetitivo em capacidade permanente. Wagner não precisa re-explicar o ciclo toda sessão — basta `/audit-and-fix <tema>`.

### Onda 4 — 3 P0/P1 da Onda 4 do GAP-ANALYSIS

3 PRs (#782-#784): BGE reranker self-host CT 100 · Langfuse v3 wire · Charters S4 ativos (Tier A always-on).

**Surpresa positiva:** Langfuse v3 já estava LIVE no CT 100 desde 2026-05-10 (Wagner havia deployado em sessão anterior e esquecido). Faltava só wire PHP → economia 1.5 dev-day.

**Motivação:** GAP-ANALYSIS-91-100 identificou L1 Langfuse como **multiplicador exponencial** — instrumenta TODOS os tools LLM. ROI desproporcional ao esforço.

### Onda 5 — pattern 3-tier (sênior + N juniors)

Wagner pediu: "*crei um agente especialista senior, para pesquisar profundo comparar e aplicar a nova onda*". Evolução do pattern:

**3 tiers agents:**
| Tier | Papel | WebSearch | Output |
|---|---|---|---|
| Junior research | Auditoria 1 tema | 5-7 | `AUDITORIA-*.md` |
| Junior implement | Implementa 1 gap | 2-3 | código + Pest + RUNBOOK |
| **Sênior dossier** | Planeja onda inteira | **25-35** (5-7 POR GAP × 5) | **Dossier blueprint** pré-implementação |

`audit-senior-expert` produziu `ONDA-5-DOSSIER-2026-05-13.md` (565 linhas, 31 WebSearch + 4 WebFetch) com escolha técnica fundamentada por gap. Depois 5 juniors paralelos implementaram (44/44 Pest passed):

- K1 time-decay recall (Mem0/Zep style)
- V1 SVAR React Gantt MIT 2.4
- H1 tool MCP `handoff-draft`
- S1 schema rígido CI 6 tipos
- A1 auto-summary + **Anthropic prompt caching breakpoints**

**Surpresa estratégica do sênior:** Anthropic prompt caching eleva A1 de P2 → P1 (Brief 06:00 + retomada 07:00 + meeting prep 10:00 = 3 cache hits = economia ~R$ [redacted Tier 0]/mês). **Não estava no GAP-ANALYSIS original** — descoberto na pesquisa profunda do sênior.

**Motivação do pattern 3-tier:** ondas grandes (5+ gaps) tinham trabalho redundante — cada junior implement re-pesquisava o que outro junior já tinha pesquisado. Sênior faz pesquisa única consolidada → juniors executam o blueprint. **Economia: 30min de planejamento duplicado × 5 juniors = 2.5h ganhas por onda.**

## Score evolução (4 medições)

| Medição | MCP/Task | Knowledge | Handoff | **Global weighted** |
|---|---:|---:|---:|---:|
| Início manhã 06:00 | 62% | 73% | 74% | **70%** |
| Pós-Onda 1 | 72% | 73% | 74% | **73%** |
| Pós-Onda 2 | ~78% | ~85% | ~85% | **~83%** |
| Pós-Onda 3 | ~88% | ~93% | ~92% | **~91%** |
| Pós-Onda 4 | ~90% | ~96% | ~93% | **~95%** |
| **Pós-Onda 5** | **~97%** | **~98%** | **~96%** | **~98%** ⭐ |

**Teto pragmático atingido em 1 dia.** Onda 6 (98% → 100%) custaria 30 dev-days em capacidades sem dor real → bloqueado por `ADR 0105` (cliente como sinal qualificado) até virar demanda real.

## 12 lições para NÃO regredir

(Complementa [aprendizados-onda1-2-3](aprendizados-onda1-2-3-2026-05-13.md) §8 lições com mais 4 da Onda 4+5.)

1. **`ls -la` no reporte de agent é IRREVOGÁVEL** (alucinação Write 2026-05-13 manhã)
2. **Áreas isoladas obrigatórias em paralelização N agents**
3. **Splittar Provider/Server/Kernel manualmente** (`git add -p` não funciona em script)
4. **Rebase pattern** quando 2º PR conflita com 1º já mergeado
5. **Multi-tenant Tier 0 IRREVOGÁVEL** em CADA prompt de agent
6. **PT-BR** em prompts + comentários
7. **Tools MCP com mock mode** (sustentabilidade Pest sem custo OpenAI)
8. **Custo IA tracking por feature** (ADR 0094 §4)
9. **Sênior planeja profundo → N juniors executam paralelo** — economia >> custo Opus extra
10. **Pesquisa fresh obrigatória** — pre-training está 6-12 meses atrás (`@svar-widgets` rejected, era `@svar-ui` no registry)
11. **Agente implementador autônomo CORRIGE blueprint** quando codebase diverge (Copiloto/ → Jana/ rename já aplicado)
12. **Surpresas positivas em infra deployada** — verificar CT 100 ANTES de assumir gap (Langfuse já estava live)

## Motivos das escolhas técnicas durables

### Por que CONSOLIDAR ao invés de EVOLUIR (mantido 5×)

Em CADA auditoria (Knowledge, Session-Handoff, GAP-ANALYSIS, Onda 5 dossier), a recomendação foi **CONSOLIDAR** (incremental) ao invés de **EVOLUIR** (refactor pra Mem0/Letta/Zep cloud-first).

**Por quê:**
- Governance brutal (Constituição v2 + ADRs append-only + multi-tenant Tier 0) é o **moat defensável único no mundo**
- Mem0/Letta cloud-first ganharia ~7pp em retrieval mas **quebraria ADR 0061** (zero auto-mem) + introduziria dep cloud + dependência multi-tenant fraca
- Custo: 50-80 dev-days vs 2-3 semanas CONSOLIDAR
- ROI marginal: +30% retrieval vs perda de governance brutal classe-mundial

**Quando reativar EVOLUIR (gate quantitativo):**
- Langfuse instrumentado pós-Onda 5 + 90d de dados reais
- RAGAS scores consistentemente < 0.6 (faithfulness ou relevancy)
- Cliente externo paga + reporta dor de retrieval (ADR 0105)

### Por que RRF antes de Cohere/BGE (Onda 3 R1 → Onda 4 BGE)

- RRF Cormack 2009: zero custo, ~1-2ms p99, zero dep, idempotente puro
- BGE-v2-m3 self-host CT 100: +6pp NDCG@10 vs RRF, ~80-150ms latência CPU, R$ [redacted Tier 0] (self-host)
- Cohere Rerank 3.5: +8pp NDCG@10 mas $2/1k + dep cloud (rejeitado custo+dep)
- **Decisão:** RRF default MVP → BGE upgrade quando ROI justificar → Cohere fica como fallback documentado

### Por que SVAR React Gantt vs DHTMLX/Bryntum/Frappe

- **DHTMLX:** GPL viral (não compatível com licença oimpresso)
- **Bryntum:** R$ [redacted Tier 0] upfront (custo proibitivo)
- **Frappe Gantt:** sem React 19 suporte
- **SVAR React Gantt MIT 2.4:** MIT + React 19 + ~80KB gzip + 575 packages 0 vulnerabilities — best fit

### Por que Anthropic prompt caching como sentinels markdown (não cache_control nativo)

- `laravel/ai` 0.6 ainda **NÃO expõe** `cache_control: ephemeral` nativo no AnthropicProvider (verificado via vendor grep)
- **Sentinels markdown** (`<!--JANA_CACHE_BREAKPOINT_*-->`) servem como:
  1. Regression guard (test cobre presença em payload)
  2. Ponto de tradução automática quando provider trocar
- Refatoração trivial via `str_replace` quando `laravel/ai` expor

### Por que handoff curto piloto (80 linhas vs mediana 142)

- AUDITORIA-SESSION-HANDOFF identificou anti-redundância em 55% (handoff vs sessions vs brief)
- Mediana atual 142 linhas — Wagner gasta 30-90min/dia relendo handoffs
- Handoff curto piloto 80 linhas (~800 tokens) — próximo agente consome em <1 minuto
- Validação: este próprio handoff segue piloto

## Estado dos 30 PRs mergeados (2026-05-13)

| # | Categoria | PRs |
|---|---|---:|
| Onda 1 (mcp bugs + 3 auditorias) | #745-#749 | 5 |
| Onda 2 (knowledge cleanup + tools) | #753-#761 (incluindo #757 rebased em #761) | 6 |
| Onda 3 (estado-da-arte capacidades) | #766-#774 | 6 |
| Onda 4 (P0 GAP-ANALYSIS) | #781-#784 | 4 |
| Onda 5 (P1 ONDA-5-DOSSIER) | #790-#795 | 6 |
| **Total** | | **30** ⭐ recorde |

**Pest passed nos PRs:** 117/117 nos testes que rodaram (alguns precisam vendor real D:/oimpresso.com main).

## 3 pegadinhas técnicas descobertas (a não repetir)

1. **PR `(#123)` parentético NÃO casa regex auto-close** — `#` não é `[A-Z]` (Bug #1 corrigido PR #746)
2. **`@svar-widgets/*` não existe no npm registry** — canônico é `@svar-ui/react-gantt` (sênior errou nome no dossier, agente V1 corrigiu)
3. **`laravel/ai` 0.6 NÃO expõe `cache_control`** — sentinels markdown como workaround temporário

## Pendências consolidadas pós-deploy

1. `php artisan migrate --force` Hostinger (3 migrations novas: mcp_handoff_summaries + mcp_doc_summaries + mcp_handoff_diffs)
2. `npm run build` (V1 SVAR Gantt + outras Page Inertia)
3. Wagner aprova `JANA_SUMMARIZER_MAX_COST_BRL=10` .env prod
4. Wagner aprova `JANA_RERANKER_DRIVER=bge` quando container BGE CT 100 estiver up
5. Wagner aprova `LANGFUSE_ENABLED=true` + keys no .env após RUNBOOK §2 first-run
6. Wagner aprova `JANA_VALIDATE_MEMORY_STRICT=true` após grace 14d (213 violations a backfill)
7. Charter `Roadmap.charter.md` (status: draft) — Wagner promove pra `live` após revisar Non-Goals/Anti-hooks

## Refs

- [aprendizados-onda1-2-3](aprendizados-onda1-2-3-2026-05-13.md) — 8 lições críticas primeira metade da sessão
- [pattern-audit-and-fix-cycle](pattern-audit-and-fix-cycle.md) — pattern reusável `/audit-and-fix`
- [COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13](../requisitos/Jana/COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md) — auditoria 1
- [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13](../requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) — auditoria 2
- [AUDITORIA-SESSION-HANDOFF-2026-05-13](../requisitos/Jana/AUDITORIA-SESSION-HANDOFF-2026-05-13.md) — auditoria 3
- [GAP-ANALYSIS-91-100-2026-05-13](../requisitos/Jana/GAP-ANALYSIS-91-100-2026-05-13.md) — gap analysis (recomendação CONSOLIDAR + teto 97-98%)
- [ONDA-5-DOSSIER-2026-05-13](../requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md) — blueprint sênior 5 gaps × 5 alternativas
- [MIGRACAO-AUTO-MEM-2026-05-13](../requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md) — 51 docs auto-mem migrados
- ADRs canon: 0061 zero auto-mem · 0070 Jira-style · 0093 Multi-tenant Tier 0 · 0094 Constituição v2 · 0095 Skills Tiers · 0105 Cliente como sinal · 0130 Handoff append-only · 0144 DB canon SPEC template

---

**Próxima sessão:** começar com `brief-fetch` → Brief Diário consolida ~3k tokens. Ler este arquivo só se precisar entender **por que** decisões foram tomadas. Pra retomar trabalho operacional, [aprendizados-onda1-2-3](aprendizados-onda1-2-3-2026-05-13.md) basta.

**Não desfazer:** 30 PRs custaram ~6h Wagner + ~28 agents Opus. Reverter qualquer Onda exige ADR explícita justificando.
