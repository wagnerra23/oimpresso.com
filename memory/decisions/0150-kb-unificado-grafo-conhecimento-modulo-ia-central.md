---
slug: 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
number: 0150
title: "KB Unificado como Grafo de Conhecimento — módulo IA central do oimpresso"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
proposed_at: 2026-05-15
accepted_at: 2026-05-16
implemented_in_pr: 934
implemented_via:
  - branch: claude/practical-engelbart-8d8eb0 (10 commits, 132 files, +25465 LOC, 7 agents paralelos)
  - merge_commit: 3204fffe5
  - merged_by: wagnerra23
  - merged_at: 2026-05-16T00:32:13Z
module: kb
quarter: 2026-Q2
tags: [kb, knowledge-graph, ia, rag, governance, capterra, p0]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0048-framework-agentes-laravel-ai-vizra-rejeitada
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0080-trust-tiers-operacional-audit-findings
  - 0089-capterra-ficha-formato-canonico
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
pii: false
review_triggers:
  - "Time MCP crescer >5 pessoas — re-validar visualização-grafo cabe na UX"
  - "Custo IA RAG passar de R$500/mês por business — re-pensar cache/embedding strategy"
  - "Bench KB score cair abaixo de 9.0/10 vs concorrentes (Glean, Mem, Notion AI 2026+)"
---

# ADR 0149 — KB Unificado como Grafo de Conhecimento (módulo IA central)

## Contexto

Wagner declarou em sessão 2026-05-15 (após análise do handoff Cowork v5):

> *"na minha opinião é o Módulo mais importante para ia. Ter essa visualização sobre meus dados vai me ajudar muito a entender melhor a empresa. e meus arquivos mais importantes."*

Esta declaração re-escala o `Modules/KB/` atual de duas formas:

**(1) Re-escopo do KB.** Hoje `Modules/KB/` é browser dos 352 docs canônicos do MCP (ADRs, sessions, runbooks, charters) servidos a partir de `mcp_memory_documents`. Persona única: Wagner / governança. O protótipo do Cowork [CC] (handoff (5), Bench v2 self-score 9,40/10 + features v5 que sobem estimativa pra ~9,55-9,70) introduz **3 estruturas conceituais sobrepostas** sobre o mesmo corpus: **Artigo** (lista linear de blocos), **Trilha** (sequência ordenada por persona com checkbox de progresso), **Decisão** (grafo Q→Sim/Não→Fix com cross-link). E adiciona um corpus operacional totalmente diferente (18 artigos de gráfica pra Larissa balcão ROTA LIVRE).

**(2) Re-posicionamento como módulo IA central.** Tradicionalmente o KB é "browser de docs". Wagner posiciona o KB unificado como o **cérebro consultável da empresa** — visualização-grafo + RAG IA + trilhas + decisões cobrindo TODOS os tipos de conhecimento do oimpresso: ADRs, sessions, charters, runbooks, briefings, artigos operacionais, dados ERP (OS, vendas, clientes, NFe), e arquivos externos (manuais Roland/HP, contratos digitalizados, fotos balcão).

### Estado atual relevante

- `Modules/KB/` existe, 6 controllers, Etapa 2 modularização concluída 2026-05-06 (Fase 3.7 PR-1)
- `Modules/KB/Http/Controllers/Admin/GraphController.php` já é knowledge graph — **MAS** é de ADS (Skills × Meta-skills × Tools × Policy × Memory MCP), NÃO de ADRs/sessions/charters. Renderiza `ads/Admin/Graph`, não `kb/Graph`. Há precedente arquitetural (formato nodes/edges Cytoscape/ReactFlow) reaproveitável.
- `resources/js/Pages/kb/Index.tsx` (721 linhas, V3) já tem markdown render + keyboard nav + filtros + soft-delete LGPD + history. Skeleton ~50% do que Wagner-governança precisa.
- 352 docs sincronizados via webhook GitHub em `mcp_memory_documents` (ADRs 143 + sessions ~500 + charters ~30 + runbooks ~50 + briefings ~10 + outros).
- Cowork (handoff 5) entregou protótipo F1 completo em `prototipo-ui/prototipos/kb/` (9 arquivos, 280KB total). Auto-score Bench v2 = 9,40/10 vs Notion 7,75 / Confluence 7,40 / Guru 7,95 / Slab 7,30 / Stonly 7,15 / Intercom 7,85.
- Não existe schema editável pra artigos operacionais (campo `body_blocks` JSON, votes, status, comments, versões locais, favoritos, anexos imagem) — `mcp_memory_documents` é read-only fotografia git.

### Decisão Wagner sessão 2026-05-15 (3 perguntas P1/P2/P3)

- **P1** — Commit imediato do sync Cowork v5 em `prototipo-ui/prototipos/kb/`: **APROVADO** (commit `e601471f1`)
- **P2** — Schema: estender `mcp_memory_documents` OU criar `kb_nodes` novo: **`kb_nodes` NOVO** com bridge read-only (preserva imutabilidade da fotografia git)
- **P3** — Persona-piloto pra ONDAS 1-3: **Wagner governança PRIMEIRO** (ADRs/sessions/charters/runbooks/briefings com visualização-grafo + RAG IA), Larissa operacional vem em ONDA 6 ou paralela

## Decisão

### 1. Modelo conceitual canônico

O `Modules/KB/` é re-escopado como **módulo IA central** sobre um **grafo de conhecimento multi-tenant**. Estruturas conceituais que coexistem:

| Estrutura | Forma | Source-of-truth | Editável? |
|---|---|---|---|
| **Nó (kb_node)** | Unidade atômica de conhecimento — artigo, ADR, session, charter, runbook, briefing, ou entidade ERP referenciada | Tabela `kb_nodes` + bridge read-only pra `mcp_memory_documents` | Sim (artigos novos) / Não (bridge canônico) |
| **Aresta (kb_edge)** | Ligação semântica tipada entre 2 nós (next-in-path, fix-of-decision, supersedes, charter-of, references-data, ai-related, cross-link, related-by-tag) | Tabela `kb_edges` | Sim |
| **Trilha (kb_path)** | Sequência ordenada de nós com checkbox de progresso e persona-target (Larissa/Wagner/Mateus/Eliana/Iniciante) | Tabela `kb_paths` + `kb_path_steps` | Sim (curador) |
| **Decisão (kb_decision_tree)** | Grafo Q→Sim/Não→Q'/Fix com cross-link `#kb-XXX` linkificado pros fixes | Tabela `kb_decision_trees` + `kb_decision_tree_steps` | Sim (editor visual) |
| **Versão (kb_node_version)** | Snapshot append-only do `body_blocks` + tags + status do nó na hora de cada edit | Tabela `kb_node_versions` | Não (append-only) |
| **Comentário** | Comentário inline ancorado em `block_idx` específico do nó | Tabela `kb_comments` | Sim (autor + admin) |
| **Favorito** | Bookmark pessoal por user_id | Tabela `kb_favorites` | Sim (próprio user) |

### 2. Bridge canônico ↔ operacional

Os 352 docs canônicos vivem em `mcp_memory_documents` (alimentado pelo webhook GitHub). Não duplicamos conteúdo. O `kb_nodes` referencia via `source_doc_id` (FK nullable). Bridge job (assíncrono `KbBridgeFromMcpJob`) cria/atualiza `kb_nodes` de tipo `adr`/`session`/`charter`/`runbook`/`briefing` apontando pra `mcp_memory_documents.id`. Esses nodes são `is_editable=false` (Tier 0 IRREVOGÁVEL: ADRs canon são append-only — [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) §6).

Artigos operacionais (corpus Cowork) nascem `is_editable=true`, têm `source_doc_id=NULL`, e usam `kb_node_versions` pra histórico local.

### 3. Persona-piloto sequencial

Conforme P3 Wagner:

| Persona | Onda piloto | Conteúdo-foco | Ações principais |
|---|---|---|---|
| **Wagner / governança** | ONDAS 1-5 (~12 dias) | 143 ADRs + ~500 sessions + charters + runbooks + briefings | Visualizar grafo de dependências; RAG sobre canon; trilhas didáticas (Constituição v2, Multi-tenant Tier 0, FSM Pipeline); decisões curativas (governance gate falhou → o que fazer) |
| **Larissa / operacional gráfica** | ONDA 6+ | 18 artigos seed Cowork (Roland VS-540, HP Latex 365, NF-e SEFAZ, etc) + dados ERP cross-link | Tri-pane com troubleshooters; imprimir SOP pra colar no balcão; favoritos; bloco IMAGEM |

ONDA 6 não bloqueia ONDA 1-5: schema e UI suportam ambos desde o dia 1; apenas o seed inicial e o `Pages/kb/Index.tsx` priorizam corpus canônico no primeiro release.

### 4. Posicionamento competitivo

O Bench KB v2 do Cowork (`prototipo-ui/prototipos/kb/Bench KB v2.html`) já cobre o caso operacional gráfica vs 7 concorrentes. Esta ADR amplia o posicionamento pra **categoria de produto distinta**: KB-como-cérebro-consultável-da-empresa, onde o diferencial não é o KB em si (Glean/Mem cobrem isso), mas **a integração nativa do KB com o ERP** (dados de OS, vendas, NFe), com a **governança canônica** (143 ADRs + sessions navegáveis como grafo), e com a **IA generativa do Copiloto** ([ADR 0035](../0035-stack-ai-canonica-wagner-2026-04-26.md), Jana) que já consome o mesmo corpus.

### 5. Inviolabilidades (Tier 0 IRREVOGÁVEL)

- `business_id` global scope em TODAS as tabelas `kb_*` ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md))
- `kb_nodes` bridge canônico (`is_editable=false`) NUNCA tem versionamento local — vem só do git ([ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md))
- ADRs canon append-only continua valendo via bridge — `kb_nodes.body_blocks` NULL pra type ADR; o conteúdo vem de `mcp_memory_documents.content_md` na query
- IA RAG roteia via `Modules/Copiloto/Ai/` ([ADR 0035](../0035-stack-ai-canonica-wagner-2026-04-26.md)) — laravel/ai SDK + MeilisearchDriver + hybrid embedder. NÃO criar provider novo.
- Pest tests biz=1 e cross-tenant biz=99 obrigatórios ([ADR 0101](../0101-tests-business-id-1-nunca-cliente.md))
- F3 do `Pages/kb/Index.tsx` segue MWART canônico 5 fases ([ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md)) + gate visual screenshot Wagner ([ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md))

### 6. Arquitetura de execução (fora do escopo desta ADR — detalhada em `memory/requisitos/KB/SCHEMA-DB-V1.md`)

5 ondas de implementação:

- **ONDA 1** Backend grafo core (3-4d, 1-2 agents) — migrations, models, controllers, services, bridge job
- **ONDA 2** Frontend tri-pane Inertia (3-4d, 1-2 agents paralelos) — port `kb-page.jsx` pra React 19/TS
- **ONDA 3** Editor + composer + autoria (2-3d, 1 agent) — block editor, comments, versions, editor visual de árvore
- **ONDA 4** IA RAG sobre grafo completo (2-3d, 1 agent) — `KbRagService` consumindo Copiloto
- **ONDA 5** Visualização-grafo + impressão SOP + favoritos (2-3d, 1 agent) — `/kb/graph` Cytoscape.js
- **ONDA 6** Dados ERP no grafo (3-5d, escopo escalável) — `references-data` edges + reverse use

## Consequências

### Positivas
- **Wagner ganha "visualização sobre meus dados e arquivos mais importantes"** desde o primeiro release (ONDA 1-5)
- Decisões arquiteturais do projeto (143 ADRs) deixam de ser texto morto e viram **grafo navegável** com dependências, supersedes, charter-of, references
- Onboarding de novos devs do time MCP (Felipe/Maiara/Eliana/Luiz) ganha trilhas didáticas curatoriais
- IA RAG do Copiloto/Jana passa a citar fontes específicas (ADR 0093 §3, session 2026-05-12 hotfix #640) — confiança vs alucinação
- Re-aproveita 280KB de design F1 já entregue pelo Cowork sem retrabalho visual
- Bench v5 estima score ~9,55-9,70/10 — provavelmente líder de categoria ERP-com-KB-IA-integrado no Brasil

### Negativas / custos
- Schema novo (~9 tabelas) é manutenção real
- Bridge job tem que ser resiliente a deletes de `mcp_memory_documents` (LGPD soft-delete cascateia)
- Custo IA RAG: estimar R$0,01-0,05 por pergunta com Sonnet + caching; monitorar mensalmente
- Visualização-grafo pode degradar performance com >5000 nodes (Wagner com 352 docs hoje + ADRs 143 = ok; cliente futuro com 10k docs precisa virtualization)
- Cowork ainda vai iterar (próximos handoffs) — protocolo append-only + screenshots pra commits adicionais

### Riscos
- **R1** Duplicação acidental de conteúdo entre `kb_nodes` e `mcp_memory_documents` — mitigado por convenção `is_editable=false ⇒ body_blocks IS NULL`
- **R2** Custo IA RAG explode sem caching — mitigado por cache de embeddings em Meilisearch ([ADR 0035](../0035-stack-ai-canonica-wagner-2026-04-26.md)) + cache de respostas curtas
- **R3** UX de visualização-grafo confunde Wagner — mitigado por gate visual screenshot ([ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md)) antes de F4 merge
- **R4** Concurrent edit em artigo operacional (raro, Larissa + Mateus mesmo artigo) — mitigado por versão otimista (`kb_node.updated_at` checked pre-save)
- **R5** Multi-tenant leak via bridge cross-business — mitigado por `business_id` global scope em `kb_nodes` E em `mcp_memory_documents` (já aplicado)

## Alternativas consideradas e rejeitadas

- **A1** Estender `mcp_memory_documents` com colunas editáveis (body_blocks_override, status_local, etc.) — **REJEITADO**: viola contrato append-only canônico da fotografia git, polui schema da governança
- **A2** Criar `Modules/KbOperacional` separado pra Larissa + manter `Modules/KB` pra Wagner — **REJEITADO**: duplica componentes (tri-pane, command palette, IA RAG) e impossibilita cross-link semântico canon↔operacional
- **A3** Substituir KB atual pelo desenho Cowork direto, perdendo `/kb` legacy (Wagner pode buscar ADR em `/copiloto/admin/memoria` redirect) — **REJEITADO**: alto risco de regressão, Wagner usa diariamente
- **A4** Adiar implementação até protótipo Cowork estabilizar — **REJEITADO**: Wagner declarou "vou querer implementar" + "faça acontecer". Iterações Cowork posteriores são tratadas como amendments incrementais.

## Implementação

Plano detalhado em `memory/requisitos/KB/SCHEMA-DB-V1.md` (contrato técnico) e `memory/requisitos/KB/BRIEFING.md` (estado consolidado).

Estimate total recalibrada por [ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md): ~12-18 dias úteis com 4-5 agents paralelos para ONDAS 1-5. ONDA 6 escopo aberto.

## Status

**PROPOSTA.** Aguardando aceitação Wagner ([W]) pra promover de `memory/decisions/proposals/` → `memory/decisions/0149-*.md` com `status: aceito`. Implementação ONDAS 1-5 pode rodar em paralelo ao aceite da ADR (paralelismo aceitável conforme [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) §3 — protótipo+spec+schema enquanto ADR é amadurecida).
