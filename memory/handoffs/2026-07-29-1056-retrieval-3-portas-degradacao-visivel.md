---
date: "2026-07-29"
time: "10:56 BRT"
slug: retrieval-3-portas-degradacao-visivel
tldr: "2ª onda: decisions-search e memoria-search também distinguem degradação de ausência. A revisão pós-merge da 1ª onda mostrou que eu tinha corrigido só 1 das 3 portas — e a que ficou de fora é a mais usada do dia a dia."
prs: [4990]
decided_by: [W]
next_steps:
  - "Rebuild do oimpresso-mcp — a 2ª onda está no main mas não em produção (o rebuild de 28/07 20:01 levou só a 1ª)"
  - "Smoke real das 2 tools novas depois do deploy, no formato dos 4 estados"
  - "Registrar a US do descasamento eval↔prod (bloqueada: token sem scope jana.mcp.tasks.write)"
---

# Retrieval: as três portas, não só uma

> Continuação de [2026-07-28-2047](2026-07-28-2047-jana-retrieval-degradacao-visivel.md) (1ª onda, `kb-answer`). Aquele handoff é append-only e cobre só a primeira parte — este registra o que veio depois.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **6 tasks, todas em REVIEW** (era 8 no fechamento anterior; US-TR-305/306 saíram) — nenhuma tocada nesta sessão
- Handoffs de 2026-07-29 no main: **1** (`0250-varredura-populacao-alarmes-que-nao-mediam`, sessão paralela) — sem duplicação de tema
- `tasks-create` → segue **NEGADO** (`requer scope jana.mcp.tasks.write`)

## O que aconteceu

O [W] pediu revisão depois do merge da 1ª onda. A varredura contada respondeu o que eu não tinha perguntado: **três** superfícies degradam para FULLTEXT em silêncio, e eu havia corrigido **uma**.

| tool | corrigida em |
|---|---|
| `kb-answer` | [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979) (1ª onda) |
| `decisions-search` | [#4990](https://github.com/wagnerra23/oimpresso.com/pull/4990) |
| `memoria-search` | [#4990](https://github.com/wagnerra23/oimpresso.com/pull/4990) |

O `DecisionsSearchTool` respondia `Nenhum ADR encontrado pra query: "X"` tanto para ausência real quanto para índice fora do ar — e é a tool canônica de *"qual ADR fala sobre X"*, das mais usadas. Não foi descuido: a área isolada do briefing listava 3 arquivos e ele não estava nela. Respeitar o escopo foi certo; o efeito colateral é que o bug seguia vivo em 2 de 3 portas, e isso só apareceu porque a revisão foi pedida.

No `memoria-search` o achado foi mais fino: o `null` de `buscarViaPipeline` tinha **três** causas (driver sem suporte · vazio · exceção) e **só a terceira é degradação**. Os três casos **já tinham teste** — todos afirmando o mesmo `null`.

## Onde a frase mora agora

A frase de aviso nasceu dentro do `KbAnswerService` na 1ª onda. Mantê-la lá faria o service de Q&A virar dono do texto que `decisions-search` e `memoria-search` também emitem. Criado `Modules/Jana/Support/RetrievalStatus` só com a frase e o predicado; `KbAnswerService::AVISO_DEGRADADO` virou alias — consumidores existentes seguem byte-a-byte. Há teste garantindo que as duas constantes são **literalmente** a mesma string.

Cada tool manteve **seu próprio vocabulário de estado** (os caminhos são distintos: Meilisearch/`mcp_memory_documents` num, `MeilisearchDriver`/`jana_memoria_facts` no outro). O que se compartilha é o que chega ao humano.

## Verificação

- `RetrievalDegradacaoToolsTest` (novo, 9 casos): **9 passed · 19 assertions**
- Regressão (`MemoriaSearchBusinessPipeline` + `DecisionsSearchResumo` + `McpDocsHybridSearch` + `KbAnswerService` + `KbRetrievalStatus`): **34 passed · 83 assertions**, incluindo as **2 guardas cross-tenant Tier 0** (ADR 0093)
- CI do #4990: **99 pass · 0 fail** (duas vezes — antes e depois de integrar o `main`)

Container de staging restaurado ao original nas duas idas (12 arquivos da outra sessão intactos).

## Erros meus

- **O teste falhou e o defeito era do teste.** A 1ª versão do caso "driver sem suporte" assumia o driver *de dev/CI* resolvido por config; no CT 100 o container resolve o **Meilisearch real**, então veio `vazio` — que é a resposta **correta**. O código estava certo; a premissa sobre o ambiente é que estava errada. Driver passou a ser injetado explicitamente.
- **Enviei os arquivos ao CT 100 sem o `McpMemoryDocument.php`.** O checkout do container está em **07-23**, anterior ao merge do #4979 → `Undefined constant HYBRID_INDISPONIVEL` em 3 casos. Não é defeito do código: **o staging não acompanha o `main`**, então teste que dependa de código recém-mergeado precisa levar as dependências junto.
- **O merge deu `DIRTY`** por conflito em `memory/requisitos/Jana/SUPERFICIE.md`. Não resolvi à mão — é **derivado**: regenerei com `module-surface.mjs Jana --write` sobre a árvore já mergeada. Resolver conflito de arquivo gerado no editor é escolher qual palpite parece certo, em vez de recalcular da fonte.

## Aberto

- **A 2ª onda não está em produção.** Está no `main` (`c7f0414010`); o `oimpresso-mcp` carregou só a 1ª no rebuild de 28/07 20:01. Até o próximo rebuild, `decisions-search` e `memoria-search` seguem com o comportamento antigo. O smoke real dos estados novos fica para depois do deploy — a 1ª onda tem smoke em prod, esta só tem teste no CT 100.
- **O avaliador segue cego.** `jana:ragas-real-eval` roda em `oimpresso-staging` (`docs_pipeline=false`), medindo FULLTEXT enquanto produção serve hybrid, e **não lê** o `retrieval_status` que agora existe nas três tools. A US disso continua sem registro no MCP por falta de scope; o texto vive no corpo do #4979, no handoff anterior e aqui.

## Pointers

- Handoff da 1ª onda: [2026-07-28-2047](2026-07-28-2047-jana-retrieval-degradacao-visivel.md) · session log: [2026-07-28](../sessions/2026-07-28-jana-retrieval-degradacao-visivel.md)
- SPEC: [SPEC-retrieval-tools-mcp-unificado.md](../requisitos/Jana/SPEC-retrieval-tools-mcp-unificado.md) (US-RET-001/002)
- ADRs de contexto: 0053 · 0056 · 0093 · 0318
