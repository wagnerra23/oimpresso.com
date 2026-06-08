---
title: "Proposta — tools MCP de busca usam o pipeline bom (hybrid+rerank+decay), não FULLTEXT puro"
type: adr-proposal
status: proposed
authority: tecnico
lifecycle: ativo
decided_at: 2026-05-29
module: Jana
related_adrs: [0031, 0036, 0053, 0056, 0067, 0094, 0232]
gap: "freshness/retrieval handoff 2026-05-29 — gap #2"
---

# Proposta — tools MCP de busca usam o pipeline bom

> **Gap #2** do handoff [2026-05-29](../../handoffs/2026-05-29-0500-jana-freshness-retrieval-5-gaps.md).
> Esta é uma proposta de DESIGN — não código. Wagner decide antes de implementar
> (muda recall do time inteiro: Felipe/Maiara/Eliana/Luiz + Wagner via MCP).

## Problema (verificado linha-a-linha)

O oimpresso tem um pipeline de retrieval estado-da-arte — `MeilisearchDriver`
(implementa `MemoriaContrato`): hybrid (Scout `semanticRatio`) + HyDE
(`HydeQueryExpander`) + RRF + time-decay (`applyTimeDecay`) + Peso Real (ADR 0232)
+ reranker (`LlmReranker`/`BgeReranker`/`RrfReranker`). **Só o chat Copiloto usa.**

As tools MCP de busca — que o time inteiro consome — usam **FULLTEXT puro**:

| Tool | Tabela | Como busca hoje | Evidência |
|---|---|---|---|
| `memoria-search` | `jana_memoria_facts` | `MATCH(fato) AGAINST` raw | [MemoriaSearchTool L92-101](../../../Modules/Jana/Mcp/Tools/MemoriaSearchTool.php#L92) |
| `decisions-search` | `mcp_memory_documents` | scope `buscarTexto` (FULLTEXT) | [DecisionsSearchTool L62](../../../Modules/Jana/Mcp/Tools/DecisionsSearchTool.php#L62) |
| `kb-answer` | `mcp_memory_documents` | `buscarTexto` + síntese gpt-4o-mini | [KbAnswerTool L196](../../../Modules/Jana/Mcp/Tools/KbAnswerTool.php#L196) |

Efeito: o Claude/time recupera **pior do que poderia** — sem expansão semântica,
sem decay temporal (ADR velho pesa igual a recente), sem rerank, sem Peso Real.

## ⚠️ Constraint crítico (Wagner, 2026-05-29): memória do MCP é da EMPRESA

**Escopo desta proposta = MCP** (ingestão/retrieval de conhecimento da empresa pelas
tools MCP). O **chat Copiloto NÃO faz parte disto** — o chat tem seu próprio modelo de
acesso por **permissões Spatie** e é um concern separado. Não misturar os dois.

No MCP, memória é **business-level** — o conhecimento (ADRs, fatos, docs) pertence ao
`business`, não ao usuário. Logo, busca de memória via MCP **NÃO deve filtrar por
`user_id`**. A tool `memoria-search` HOJE está correta: FULLTEXT filtra só `business_id`
([L92-93](../../../Modules/Jana/Mcp/Tools/MemoriaSearchTool.php#L92)).

**O que isto implica pro gap #2:** o método `MemoriaContrato::buscar()` filtra
`business_id AND user_id` (`MeilisearchDriver::buscarInterno`). Esse contrato foi
desenhado pra outro consumidor; **não serve pras tools MCP** sem virar business-only.
Rotear as tools MCP por `buscar(biz, user)` como está **ESTRAGARIA as regras
empresariais** (estreitaria pra 1 usuário, perdendo os fatos da empresa — ex: ADRs
seedados `user_id=1`).

**Pré-requisito do gap #2 (novo):** o retrieval das tools MCP precisa de um caminho
**business-scoped** — `buscar(businessId, query, topK)` **sem** `user_id` no filtro.
Sem isso, nenhuma das áreas abaixo pode ligar com segurança.

> Tentativa de Área A user-scoped foi convertida pra **draft em #1922** por causa disto.

## A sutileza que impede o "swap único"

O pipeline bom (`MemoriaContrato::buscar`) opera sobre **`jana_memoria_facts`**
(os *fatos* — derivados de ADRs via `seed-adrs`). Mas `decisions-search`/`kb-answer`
buscam **`mcp_memory_documents`** (o *markdown completo* do ADR). São índices
diferentes. Logo o gap #2 não é uniforme — divide em 3 áreas com risco distinto:

### Área A — `memoria-search` → pipeline **business-scoped** (BAIXO risco *após* o pré-req)
Mesma tabela (`jana_memoria_facts`). **NÃO** é o swap direto pro `buscar(bizId, userId,…)`
— isso estreitaria pra 1 usuário (ver constraint acima). Precisa da variante
business-scoped do pipeline primeiro. Só então: flag + fallback FULLTEXT business-only.
A tentativa user-scoped (#1922) ficou em **draft**.

### Área B — `decisions-search` (MÉDIO risco — depende de decisão)
Duas opções:
- **B1** — buscar os *fatos seedados de ADR* (`jana_memoria_facts` com
  `metadata.doc_type=adr`) via pipeline bom. Já existe e ficou correto com #1917
  (metadata canônico) + #1919 (indexação Scout). Muda o retorno: snippet do fato
  vs markdown do ADR. Citação aponta `source_slug`.
- **B2** — apontar o pipeline bom pro índice de `mcp_memory_documents`
  (que TAMBÉM é Scout-Searchable). Mantém o markdown completo, mas o decay/HyDE/Peso
  Real do `MeilisearchDriver` é específico de `MemoriaFato` — exigiria generalizar
  o driver pra um segundo índice. Mais trabalho, menos reuso.

**Recomendo B1** (reuso máximo; o seed já existe e ficou robusto). `decisions-fetch`
continua servindo o markdown completo on-demand.

### Área C — `kb-answer` (MÉDIO risco)
Reusa a mesma decisão de B. A síntese gpt-4o-mini fica intacta; só a etapa de
recuperação de fontes (`buscarFontes`) passa pelo pipeline bom. Citação obrigatória
preservada.

## Proposta

1. **Flag mestre** `JANA_MCP_SEARCH_PIPELINE` (default **OFF** → comportamento atual
   idêntico, byte-a-byte). Liga por tool se quiser (`...PIPELINE_MEMORIA`, `..._DECISIONS`, `..._KB`).
2. **Fallback gracioso**: se o driver lançar/Meilisearch cair, cai no FULLTEXT atual
   (degradação, nunca erro pro time). Padrão ADR 0036/0056.
3. **Gate golden-set ANTES de ligar default**: rodar `copiloto:eval` (recall@5) com
   pipeline ON vs FULLTEXT sobre um golden set de ~20 queries reais do time. Só vira
   default se recall não regredir (≥ baseline). Sem isso, fica flag-off.
4. **Business-scoped, NÃO user-scoped** (constraint acima): variante de `buscar`
   sem filtro `user_id` é **pré-requisito** de todas as áreas. Multi-tenant Tier 0
   (ADR 0093) preservado: filtro por `business_id` + cross-tenant assert em cada tool.
5. **Sequência**: (0) criar variante business-scoped do pipeline → (A) `memoria-search`
   valida o padrão flag+fallback → golden set → (B+C) `decisions-search`/`kb-answer`.

## Custo / risco

- Custo IA: HyDE usa 1 chamada LLM barata por query (já em prod no chat). Cap por tool.
- Risco: recall regressão → **mitigado pelo gate golden-set + flag-off default**.
- Reversível: flag OFF restaura o estado atual sem deploy.

## Decisão pendente de Wagner

- [ ] Confirmar: memória do MCP é **business-level** (sem `user_id` no retrieval das tools)?
- [ ] Aprovar o paradigma (tools MCP via retrieval business-scoped, flag-gated)?
- [ ] B1 (buscar fatos seedados) vs B2 (generalizar driver pros docs)?

## Relacionado

- [ADR 0036](../0036-replanejamento-meilisearch-first.md) · [ADR 0056](../0056-mcp-fonte-unica-memoria-copiloto-claude-code.md) · [ADR 0067](../0067-sprint8-mcp-memory-document-searchable-retrieval.md) · [ADR 0232](../0232-modelo-peso-real-classificacao-por-meta.md)
- [AUDIT-SENIOR Jana 2026-05-25 §5](../../requisitos/Jana/AUDIT-SENIOR-2026-05-25.md) — G4/G5 retrieval
- PRs irmãos: #1917 (seed metadata+schedule) · #1919 (seed indexa Scout) — **pré-requisitos do B1**
