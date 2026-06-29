---
date: 2026-06-29
hour: "13:27 BRT"
topic: Busca de ADR consertada — snippet útil (frente 1) + hybrid quebrado desligado (frente 2), tudo medido por workflows adversariais
duration: ~4h
authors: [CL]
tldr: "Busca de ADR consertada em 2 frentes medidas — snippet útil (#3383) + hybrid quebrado desligado (ADR 0312/#3385). Tudo merged + smoke prod ✓. Causa-raiz da frente 2: embedder qwen3 exige instrução de query que o Meilisearch não envia → similaridade invertida."
---

# decisions-search consertada: snippet + hybrid-off (3 workflows adversariais, tudo medido)

> **Cumprindo R12 PROTOCOLO via skill `encerrar-sessao` (ativação lazy).** Wagner: *"tudo esta merge salve tudo"*.

## TL;DR
A busca de ADR (`decisions-search`) foi consertada em 2 frentes, cada uma **medida** (Wagner exigiu "adversário, por números"): **(1)** snippet cego → `montarResumo` ([#3383](https://github.com/wagnerra23/oimpresso.com/pull/3383)); **(2)** o pipeline **hybrid** estava ligado com um embedder **qwen3 quebrado** (exige instrução de query; Meilisearch envia raw → similaridade invertida) → desligado, volta ao FULLTEXT ([ADR 0312](../decisions/0312-decisions-search-fulltext-hybrid-docs-off.md) / [#3385](https://github.com/wagnerra23/oimpresso.com/pull/3385)). Tudo merged + smoke prod ✓. Nada pendente.

## Estado MCP no momento do fechamento
- **Cycle:** CYCLE-08 Receita Onda A · **0 dias** (encerrou 28/jun). Goals (todos 🔲): pricing público, 5 migrações-demo, MRR R$2k, ComVis V1, Agrosys de-riscado.
- **my-work @wagner:** 30 tasks (8 REVIEW · 8 BLOCKED · 14 TODO). **Nenhuma é deste trabalho** — conserto da busca de ADR é off-cycle (infra/governança, iniciado por pergunta do Wagner sobre constituição+ADRs).
- **Último handoff irmão:** 2026-06-24 20:45 (auditoria das máquinas). **ADRs aceitas no intervalo:** 1 (a minha, 0312).

## O que aconteceu
Pergunta crua: *"a constituição é fixa e minhas ADRs também — seria melhor um resumo delas, indexaria melhor"*. Virou conserto da busca de ADR (`decisions-search`) em frentes, **cada uma medida por workflow adversarial** (Wagner exigiu "adversário" + "por números, não avaliação minha", 3×):

- **Frente 0 (verificação):** meu 1º "adversário" ERROU a contagem (553 vs **728** linhas always-on). Verificação forense (10 agentes, `wc -l`) pegou. Lição: eu também sou falível.
- **Frente 1 (snippet):** medição N=30 na busca real → recall@5=90% mas **53% dos snippets eram chunk posicional cego** (`extrairSnippet` ancorava na 1ª palavra da query). Fix `montarResumo` (summary > Decisão/Contexto > legado). **PR #3383 merged + deploy + smoke** (0093: "### Garantia 1"→resumo da decisão).
- **Frente 2 ("ranking"):** investigação revelou que **não era ranking** — a tool usava o **pipeline hybrid** (flag `JANA_MCP_SEARCH_PIPELINE_DOCS=true`) com embedder **qwen3-embedding:0.6b QUEBRADO**: exige prompt de instrução na query, Meilisearch envia raw → similaridade **invertida** (cosseno: lixo 0.7068 > alvo 0.5306). Trocar pra nomic não resolve. **FULLTEXT mede melhor** (~6/7 vs ~4/7). **Desliguei a flag** (`=false` no .env oimpresso-mcp) → **ADR 0312 + PR #3385 merged**. Smoke A/B: "daily brief" lixo→0091 r1.
- **Tail (canon-vs-canon, 0035/0093):** Wagner pediu "faça". Medi 4 alavancas (summary / contextual_context / boost-title-SQL / re-rank-PHP) — **TODAS falham ou são caras**. Wagner: *"não quero não"*. Documentado na 0312 (condição de reativação).

## Artefatos gerados (tudo merged)
- **PR #3383** (frente 1): `DecisionsSearchTool::montarResumo` + 9 testes Pest (CT100 verde) + remove números stale da description.
- **ADR 0312** + **PR #3385** (frente 2): `JANA_MCP_SEARCH_PIPELINE_DOCS=false` em prod + `_INDEX-GENERATED` regenerado (318 ADRs) + dossiê.
- **Dossiê:** `memory/sessions/2026-06-29-arte-constituicao-adr-resumo-indexacao.md` (estado-da-arte + errata + frente 2).
- **Mudança operacional em prod (não-git):** flag .env oimpresso-mcp `=false` (backup `.env.bak-pipeline-docs-off` no CT100, bind-mount `/opt/oimpresso-mcp/code/.env`).

## Persistência
- **git:** 2 PRs merged em main (#3383 `f88ff24`, #3385 `30e343a`). Handoff neste PR.
- **MCP:** webhook GitHub→MCP propaga ADR 0312 + session em ~2min.
- **prod:** flag aplicada no oimpresso-mcp (config:cache + restart, READY, smoke ✓).

## Próximos passos pra retomar
Busca de ADR está **consertada e fechada — nada pendente.** Se um dia quiser o tail canon-vs-canon: ADR 0312 §"condição de reativação" = Contextual Retrieval (rodar `ContextualizeBackfillCommand` nos 451 docs via LLM + consertar embedder: instrução de query + documentTemplate + reindex + religar hybrid medindo). ROI duvidoso pra 2 casos — Wagner já disse não.

## Lições catalogadas
- **Medir, não confiar (nem em mim):** errei a contagem (553 vs 728), a Alavanca C (orderBy inútil) E o diagnóstico do workflow. Nas 3 frentes quem pegou o erro foi a medição forense / ir ao banco — não a hipótese.
- **Workflow adversarial mede a HIPÓTESE, não o SISTEMA:** a frente 2 concluiu "canon-vs-canon ranking" porque mediu a *tool* sem saber que ela usava hybrid. Só apareceu indo ao `tinker` no oimpresso-mcp.
- **Feature dormente ≠ funcionando:** hybrid LIGADO e quebrado; `contextual_context` IMPLEMENTADO e **0/451** vazio. Verificar estado real antes de assumir "está no ar = funciona".
- **`summary` só ajuda snippet, não ranking** (não está no `toSearchableArray`/FULLTEXT); **append-only bloqueia retrofit** de ADR aceita.
- **mexer em ranking de prod é sensível** — não toquei sem A/B medido; descartei C e boost-title quando mediram ≈0 / regressão. O DB do mcp_memory_documents é **Hostinger** (DDL lá é Tier-0 cuidado).

## Pointers detalhados (on-demand)
- Dossiê: `memory/sessions/2026-06-29-arte-constituicao-adr-resumo-indexacao.md`
- ADR: `memory/decisions/0312-decisions-search-fulltext-hybrid-docs-off.md`
- Código: `Modules/Jana/Mcp/Tools/DecisionsSearchTool.php` (montarResumo) · `Modules/Jana/Entities/Mcp/McpMemoryDocument.php` (buscarHybrid:138 · toSearchableArray:220 · scopeBuscarTexto:112)
- Causa-raiz embedder (cosseno): qwen3 raw 0.5306<0.7068 (invertido) · qwen3 instruído 0.4788>0.4286 · nomic raw 0.6558>0.5536
