---
date: "2026-07-28"
slug: "kb-degradacao-rag-visivel"
tldr: "No RAG do KB, quatro caminhos de falha colapsavam no mesmo 200 com a mesma frase — dois deles fora do levantamento. Agora há sinal no payload sem virar 500. Achado maior: a auditoria dos 3 endpoints de IA do KB nunca gravou em MySQL (endpoint e status violavam o ENUM e o catch engolia). PR #4989."
hour: "20:39 BRT"
topic: "Degradação invisível no RAG do KB + auditoria que nunca gravou"
authors: [W, C]
prs: [4989]
us:
  - "US-KB-003"
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0093-multi-tenant-isolation-tier-0"
  - "0053-mcp-server-governanca-como-produto"
next_steps:
  - "Decidir se a mensagem 'Não encontrei isso no KB' muda no caso LLM-caiu (produto — gancho pronto)"
  - "Regenerar memory/requisitos/Jana/SUPERFICIE.md quando as sessões do Jana fecharem (drift pré-existente de #4985)"
  - "US-COPI-125 (ACL pré-retrieve) pode seguir — sem colisão de lógica com este PR"
---

# Handoff 2026-07-28 20:39 — a degradação do RAG deixou de ser invisível

## O que mudou

`KbRagService::ask` tinha **quatro** situações caindo no mesmo retorno com HTTP 200 e a mesma frase "Não encontrei isso no KB": retrieval caído, cliente Meilisearch ausente (esse **sem log nenhum**), LLM caído *depois* de achar as fontes, e busca legítima vazia. O levantamento citava dois; os outros dois apareceram na leitura.

Agora `meta.degraded` + `meta.degradation` existem no payload, de forma **aditiva** (nenhuma chave anterior mudou). Continua 200 — a escolha por disponibilidade é deliberada e foi preservada. Mostrar na interface é decisão de produto.

O mesmo padrão estava em `summarize` (o fallback é o `excerpt` do node — plausível a ponto de nada indicar que a IA não rodou) e em `suggestMeta`. Ambos sinalizados.

## O achado que ninguém tinha pedido

**A auditoria dos 3 endpoints de IA do KB nunca gravou.** `mcp_audit_log.endpoint` é ENUM com 7 valores MCP e o controller gravava `'kb.ai.ask'`; `status` é ENUM de 4 e gravava `'ok_empty'`. Em MySQL strict o INSERT falha e o `catch` do próprio controller engole — um bug invisível protegendo o outro, e não só no caso vazio: em *toda* chamada.

Corrigido para o padrão canônico (`'tools/call'` + identidade em `tool_or_resource`), com constantes que o teste confronta contra o **enum vivo do banco**. Schema do `mcp_audit_log` **não** foi alterado (Tier 0).

Efeito consciente: `status='error'` faz a degradação aparecer em `HealthSnapshotService::taxa_erro` e no filtro da UI de governança. Escolhido depois de medir os consumidores, não por preferência.

## Onde a medição derrubou uma conclusão minha

A suíte KB no CT 100 mostrou falhas e eu ia reportá-las como possível regressão. Medindo o baseline: código original **8 failed/2 passed**; com o PR **9/1**; rodando **de novo sem mudar nada**, 9/1 e depois **10/0**. Piora sozinho — banco persistente do CT 100. E os arquivos do PR têm **zero** ocorrências nesses testes. Não era regressão.

Fica como lembrete: "o número mudou" não é achado enquanto o baseline não for rodado na mesma condição.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `tasks-detail US-COPI-125` → `status: todo`, `owner: unowned`, `Implementado em: _pendente_` — **não está em execução**; toca os mesmos arquivos mas insere filtro ACL pré-retrieve (sem colisão de lógica).
- `decisions-search "degradação graceful fail-open observabilidade RAG"` → 0284 (pipeline de incidente graduado por confiança), 0318 (RAGAS real mata tautologia), 0067, 0133. Nenhuma contradiz este PR.
- Brief do início da sessão: gerado há ~2h, 4 HITL pendentes para [W].

## Cuidados para quem pegar daqui

- **Não regenerar `memory/requisitos/Jana/SUPERFICIE.md` agora.** O drift é pré-existente (entrou por [#4985](https://github.com/wagnerra23/oimpresso.com/pull/4985)), o arquivo aqui é idêntico ao de `origin/main`, e há três sessões ativas no Jana. O gate não é required. Regenerar colidiria e voltaria a driftar.
- **CT 100**: o checkout de `oimpresso-staging` tem 12 arquivos não-commitados de outra sessão. Foi preservado — os arquivos deste trabalho foram enviados, rodados e restaurados. Não dar `git pull` lá sem combinar.
- A branch `claude/youthful-cerf-7a103e` guarda 7 commits de outra sessão (censo de IA). Este PR nasceu de `origin/main` fresco justamente para não arrastá-los.
