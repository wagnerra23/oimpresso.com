---
date: "2026-07-28"
hour: "20:39 BRT"
duration: "1.5h"
topic: "Degradação invisível no RAG do KB — sinal na resposta e conserto da auditoria que nunca gravou"
authors: [W, C]
outcomes:
  - "Quatro caminhos de falha do ask() deixam de colapsar no mesmo retorno — dois deles não estavam no levantamento"
  - "A auditoria dos 3 endpoints de IA do KB nunca gravou em MySQL: endpoint e status violavam o ENUM e o catch engolia"
  - "Resultado degradado deixa de ser cacheável, com a condição explícita e não como efeito colateral"
  - "Falhas da suíte KB no CT 100 provadas como flakiness de banco persistente, não regressão do PR"
prs:
  - 4989
us:
  - "US-KB-003"
---

# KB — degradação invisível no RAG

## O pedido

Levantamento de 2026-07-28 (workflow `jana-architecture-reescrita`) apontou que, em `KbRagService::ask`, duas situações colapsavam no mesmo retorno com HTTP 200: Meilisearch fora do ar e busca legítima sem resultado. Do lado de fora, impossível distinguir "a base não tem isso" de "a busca não funcionou" — o cliente conclui que o conteúdo não existe.

Área isolada: `KbRagService`, `KbCorpusBuilder`, `KbAiController`. `Modules/Jana/**` proibido (três sessões paralelas ativas lá).

## O que a leitura achou além do pedido

Os dois caminhos existiam onde o levantamento dizia (as linhas tinham drifado). Mas eram **quatro**, não dois:

| caminho | onde | estado anterior |
|---|---|---|
| retrieval falhou | `KbCorpusBuilder:295` | `collect()` vazio + log warning |
| cliente Meilisearch ausente | `KbCorpusBuilder:405` | `collect()` vazio, **sem log nenhum** |
| LLM caiu **depois** de achar as fontes | `KbRagService:227` | `notFound()` — "Não encontrei isso no KB" |
| busca legítima sem resultado | `KbRagService:186` | `notFound()` — idêntico |

O terceiro é o mais enganoso: o conteúdo existe, foi encontrado, e o usuário é informado de que não existe.

## O achado maior: a auditoria nunca funcionou

`mcp_audit_log.endpoint` é `ENUM('tools/list','tools/call','resources/list','resources/read','prompts/list','prompts/get','initialize')` e o controller gravava `'kb.ai.ask'`. `status` é `ENUM('ok','denied','error','quota_exceeded')` e gravava `'ok_empty'` (valor que aparece **1 vez em todo o repo**). Em MySQL strict o INSERT falha e o `try/catch` do próprio controller engole como "degradação".

Ou seja: **um bug invisível protegendo o outro** — e não só no caso vazio, em *toda* chamada dos 3 endpoints.

Provado contra o banco, não lido: o teste `os valores legados do controller NAO cabiam no enum` passa lendo o `information_schema`.

Conserto pelo padrão canônico medido (`'tools/call'` + identidade em `tool_or_resource` — 12 usos no repo, incluindo `McpAuthMiddleware` e `McpSchemaTest`), com os valores em constantes que o teste confronta contra o **enum vivo**. O schema do `mcp_audit_log` é Tier 0 e **não** foi alterado.

`status='error'` foi escolhido *depois* de medir consumidores: `FilterAuditRequest` só aceita `in:ok,error,denied,quota_exceeded` (valor novo seria rejeitado pela UI e pelo enum) e `HealthSnapshotService` conta `error` em `taxa_erro`. Efeito consciente: a degradação passa a aparecer no painel de saúde.

## Onde a medição mudou a conclusão

**Quase reportei regressão que não existia.** A suíte KB completa no CT 100 mostrou falhas. Antes de reportar:

| configuração | resultado |
|---|---|
| código **original** | 8 failed / 2 passed |
| com o PR | 9 failed / 1 passed |
| com o PR, **de novo, sem mudar nada** | 9 failed / 1 passed |
| com o PR, terceira vez | **10 failed / 0 passed** |

Piora sozinho a cada run — estado residual no banco persistente do CT 100, exatamente o que `proibicoes.md` descreve. Verificação estática: **zero** ocorrências dos arquivos do PR em `KbEdgeAutoDeriverTest`/`KbBridgeFromMcpJobTest`. Não era regressão.

Ler o número uma vez teria produzido um achado falso. É a mesma família da LC-08 — a fonte certa aqui era *rodar o baseline*, não comparar contra a memória do que "deveria" passar.

## Evidência (CT 100, `oimpresso-staging`, MySQL real — nunca local)

| execução | resultado |
|---|---|
| testes novos | **13 passed (59 assertions)** |
| regressão nos 5 testes que consomem os arquivos tocados | **26 passed (333 assertions), 0 failed** |
| `php -l` × 8 arquivos | limpo |

Números lidos de *assertions*, não de "0 failed" — skip também sai exit 0.

Os arquivos foram enviados por stdin (`tar` → `docker cp`), rodados e **restaurados**: o checkout compartilhado do CT 100 voltou ao estado original, com os 12 arquivos não-commitados de outra sessão intactos.

## Decisões preservadas de propósito

- **Disponibilidade**: nada virou 500. A informação existe no payload; mostrar é decisão de produto.
- **Texto da resposta**: `"Não encontrei isso no KB"` segue literalmente falso no caso LLM-caiu. Trocar é produto — o gancho está pronto.
- **Drift de `SUPERFICIE.md` em Jana**: pré-existente (entrou por [#4985](https://github.com/wagnerra23/oimpresso.com/pull/4985)), arquivo idêntico ao de `origin/main`. Não regenerado — colidiria com as sessões ativas no módulo e voltaria a driftar. Gate não é required.

## Sessão irmã resolveu o MESMO padrão no outro módulo — com vocabulário diferente

O [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979) (mergeado hoje, 20:47) atacou exatamente esta classe no lado do Jana: no `kb-answer`, *"não encontrei nada"* significava tanto índice fora do ar quanto KB sem o assunto — lá eram **cinco** caminhos colapsados, incluindo um totalmente mudo (`host` vazio).

Os dois trabalhos são **complementares, não duplicados** — a separação de áreas foi por desenho (`Modules/Jana` era proibido aqui, três sessões ativas). Mas as convenções divergiram:

| | #4979 (Jana) | este PR (KB) |
|---|---|---|
| mecanismo | `$status` **por referência** no método | `degradations()` acumulado na instância |
| nome no payload | `retrieval_status` (`fulltext_degradado`, `vazio`, `hybrid`) | `meta.degraded` + `meta.degradation` |

Ambos preservaram disponibilidade e ambos evitaram alarme em vazio legítimo (lá, *"controle negativo é o ponto"*; aqui, `degradations()` vazio quando a busca só não achou).

**Vale harmonizar num vocabulário só** antes que um terceiro módulo invente o terceiro nome — decisão de [W], não conserto silencioso, e nenhum dos dois PRs deve ser revertido por isso.

> Nota de processo: esta seção deveria estar no handoff, mas o `block-memory-drift` (append-only, ADR 0130) barra `Edit` **e** `Write` em `memory/handoffs/**` mesmo para arquivo criado na própria sessão e ainda não commitado. Não usei o override — fica aqui, que é o lugar de contar o trabalho.

## Aberto

- **US-COPI-125** (`todo`, `unowned`, `_pendente_`) toca os mesmos arquivos: insere filtro ACL pré-retrieve. Sem colisão de lógica — e herda um lugar pronto (`degradations()`) para sinalizar se o próprio filtro ACL falhar.
- Chip registrado: o SPEC US-KB-003 lista o reranker BGE como critério de aceite, mas ele não entra no `ask()` (está no ServiceProvider e num dashboard do Admin). Medido, não fechado como defeito — pode ser intencional por custo/latência.
