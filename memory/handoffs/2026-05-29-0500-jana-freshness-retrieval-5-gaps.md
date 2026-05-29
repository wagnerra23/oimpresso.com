---
title: "Handoff — Jana freshness/retrieval pipeline: 5 gaps (3 fechados, 1 proposta, 1 Wagner)"
type: handoff
status: ativo
authority: tecnico
decided_at: 2026-05-29
module: Jana
related: [FRESHNESS-PIPELINE.md, AUDIT-SENIOR-2026-05-25.md, AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md]
related_adrs: [0053, 0067, 0092, 0093, 0094, 0130, 0232]
prs: [1917, 1918, 1919, 1920]
---

# Handoff — Jana freshness/retrieval: 5 gaps

> **Gap #1 era:** handoff salvo solto na worktree `.claude/worktrees/` (`.gitignore:38`)
> se perde. **Por isso este doc vive em `memory/handoffs/` (rastreado) e foi COMMITADO+PUSHED.**
> O handoff original desta investigação só existia no contexto da sessão — perdido.

## Estado final dos 5 gaps

| # | Gap | Status | PR |
|---|---|---|---|
| 4 | drift git↔DB tipo-SHA desligado (`repoBasePath=null`) + STALE/CRITICAL mesmo cutoff 30d | ✅ **fechado** | [#1918](https://github.com/wagnerra23/oimpresso.com/pull/1918) |
| 3 | seed-adrs dormente + metadata errada | ✅ **fechado** (em 2 PRs) | #1917 (metadata+schedule) + [#1919](https://github.com/wagnerra23/oimpresso.com/pull/1919) (Scout) |
| 2 | tools MCP usam FULLTEXT puro, não o pipeline bom | 🟡 **proposta** (decisão Wagner) | [#1920](https://github.com/wagnerra23/oimpresso.com/pull/1920) |
| 1 | worktree filha `.gitignored` — handoff se perde | ✅ **mitigado** (este doc rastreado) + guard proposto abaixo | — |
| 5 | retention LGPD + contextual retrieval dormentes (flags off) | ⏸️ **Wagner-gated** (não é código) | — |

## Correção importante de entendimento (gap #3)

O handoff original dizia gap #3 = "sem schedule + chaves erradas". Ao verificar
o código de `origin/main`, **ambos já estavam fechados pelo #1917** (`buildFatoMetadata`
grava `doc_type`/`status`/`published_at` + schedule `copiloto-seed-adrs-daily`).

O furo **real** que sobrava: o `SeedAdrsCommand` insere via `DB::table()->insert()`
(raw) → **bypassa os eventos Eloquent que o Scout escuta** → o fato entrava no DB
mas nunca no índice Meilisearch. Mesmo com cron + metadata correto, "ADR novo não
virava fato pesquisável". [#1919](https://github.com/wagnerra23/oimpresso.com/pull/1919)
fecha: pós-insert, particiona por `shouldBeSearchable()` e chama `searchable()`/
`unsearchable()` (métodos de instância do trait, não o macro). +3 testes (40 passed).

## Gap #2 — decisão pendente de Wagner (ver [proposta](../decisions/proposals/jana-mcp-search-tools-pipeline-bom.md))

- Ponto de entrada reutilizável: `MemoriaContrato::buscar(bizId, userId, query, topK)`.
- Sutileza: `memoria-search` está na MESMA tabela do pipeline (`jana_memoria_facts`,
  swap limpo = Área A); `decisions-search`/`kb-answer` estão em `mcp_memory_documents`
  (índice diferente → decisão B1 buscar fatos seedados vs B2 generalizar driver).
- Plano: flag-off default + fallback FULLTEXT + gate golden-set recall@5. B1 tem
  #1917+#1919 como pré-requisitos (já fechados).
- **3 checkboxes pra Wagner** no fim da proposta.

## Gap #5 — Wagner-gated (não é código)

`retention.enabled=false` ([retention.php:58](../../Modules/Jana/Config/retention.php#L58))
+ `contextual_retrieval.enabled=false` ([config.php:425](../../Modules/Jana/Config/config.php#L425)).
Engines existem; o schedule `jana:retention-purge` existe gated por flag. Ligar exige
(AUDIT-SENIOR §7): canary 7d biz=1 + sign-off retention + orçamento LLM Haiku
(contextual backfill). **NÃO flipar sozinho** (ADR 0105 + ADR 0101). Decisão do Wagner.

## Gap #1 — guard proposto (follow-up, não implementado)

Mitigado na prática (este handoff rastreado). Fix de processo mais forte, se Wagner
quiser: hook `Stop`/`PreToolUse` que **bloqueia/avisa** quando um `*handoff*.md` for
escrito fora de `memory/handoffs/` (ex: solto na raiz da worktree). Padrão dos hooks
já existentes (`block-automem`, `modulo-preflight-warning`). Não implementei sem o
ok do Wagner (muda comportamento de sessão).

## Pré-requisito operacional pós-merge

Quando #1917+#1919 estiverem em prod, rodar 1× `php artisan copiloto:seed-adrs --type=all`
(ou esperar o cron 04:45 BRT) pra popular+indexar os fatos de ADR — habilita o B1 do gap #2.
