---
date: "2026-07-28"
time: "20:47 BRT"
slug: jana-retrieval-degradacao-visivel
tldr: "kb-answer dizia 'não encontrei nada · confiança baixa' tanto quando a KB não tinha o assunto quanto quando o Meilisearch estava fora do ar. Separado por status; smoke real em prod nos 4 estados. A premissa do briefing (flag OFF) estava invertida: o hybrid roda em produção."
prs: [4979]
decided_by: [W]
next_steps:
  - "Registrar a US do descasamento eval↔prod (bloqueada: token sem scope jana.mcp.tasks.write)"
  - "Decidir se jana:ragas-real-eval passa a medir o mesmo pipeline que produção serve"
---

# Retrieval da KB: degradação deixou de ser indistinguível de ausência

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 8 tasks, **todas em REVIEW** (US-COPI-123 p0 · US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023) — nenhuma tocada nesta sessão
- `tasks-create` → **NEGADO**: `requer scope jana.mcp.tasks.write`. A US desta sessão não pôde ser registrada no MCP; o texto está no corpo do [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979)
- Handoffs irmãos de 2026-07-28: **11** (sessões paralelas), nenhum sobre retrieval — sem duplicação

## O que aconteceu

`McpMemoryDocument::buscarHybrid` devolvia a **mesma** `Collection` vazia em **cinco** situações — o briefing apontava três:

| # | situação | natureza |
|---|---|---|
| 1 | `scout.meilisearch.host` vazio | infra/config — **único totalmente mudo, nem log** |
| 2 | exceção de rede | infra caída |
| 3 | HTTP ≠ 2xx | infra caída |
| 4 | zero hits | **ausência legítima** |
| 5 | hits > 0, hidratação 0 | índice à frente do banco, ou scopes Tier 0 barrando |

O `kb-answer` respondia `Não encontrei nada conclusivo… Confiança: baixa` em todas. A única pista era um `warning` em log — que ninguém abre para julgar a confiança de uma resposta.

**A premissa do briefing estava invertida, e isso subiu a prioridade em vez de rebaixá-la.** O briefing dizia que a flag `copiloto.mcp_search.docs_pipeline` estava desligada e que o caminho híbrido "nem roda em produção". Medido **no runtime** (não no arquivo): `oimpresso-mcp` (`env=production`, quem serve o `kb-answer`) tem **`true`**; `oimpresso-staging` tem `false`. O default `false` do `Config/config.php` é só o default — o container de produção liga por env, como o [SPEC-retrieval §US-RET-001](../requisitos/Jana/SPEC-retrieval-tools-mcp-unificado.md) já registrava desde 2026-05-29.

## Solução

`$status` por referência — opcional, último parâmetro. Escolhido porque `buscarHybrid` tem **2 chamadores de produção** (varredura contada, `git grep` sem limite) e um deles, `DecisionsSearchTool.php:70`, estava fora do escopo: mudar o tipo de retorno o quebraria. Segue byte-a-byte.

- índice **respondendo** vazio → `HYBRID_VAZIO`, **sem alarme** (senão o aviso vira ruído permanente e ninguém lê)
- índice **não respondendo** → `HYBRID_INDISPONIVEL` → `RETRIEVAL_FULLTEXT_DEGRADADO`
- **o fallback FULLTEXT continua nos dois casos e nenhuma exceção nova sobe** — a degradação silenciosa foi escolha de disponibilidade; o objetivo era torná-la visível, não fatal

## Smoke real — produção, Meilisearch de verdade

| caso | situação | `status` | fallback | alarme |
|---|---|---|---|---|
| 1 | índice vivo, tem resposta | `hybrid` | — | mudo ✅ |
| 2 | **índice fora do ar** | `fulltext_degradado` | **5 docs** | **avisa** ✅ |
| 3 | índice vivo, pergunta absurda | `hybrid` | — | mudo ✅ |
| 4 | índice vivo, **zero hits** (filtro zera) | `vazio` → `fulltext` | — | mudo ✅ |

Testes antes do merge (CT 100, nunca local): `KbRetrievalStatusTest` **9 passed · 28 assertions** · regressão MySQL **19 passed · 40 assertions** · `KbAnswerToolTest` **8 passed · 21 assertions** na lane sqlite (os 8 são `sqlite-only` e vinham como `skipped` no MySQL — justamente os do arquivo com a mudança visível; rodados para não dar por coberto o que não rodou).

## Achado que o smoke revelou

**O hybrid praticamente nunca devolve vazio.** "receita de brigadeiro quântico" trouxe 5 documentos — busca semântica retorna sempre os top-K, sem corte de similaridade. Logo `HYBRID_VAZIO` só dispara quando um filtro zera o conjunto (caso 4). O "não encontrei nada" que o usuário vê vem quase sempre do FULLTEXT ou do próprio LLM concluindo que as fontes não respondem — **não do retrieval**. Não muda a correção; ajusta a expectativa: o valor real está em separar **infra-caída** de tudo o mais (caso 2).

## Artefatos

- [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979) **MERGED** (`61ecda6d79`) — 99 checks pass · 0 fail. 130 inserções em 3 arquivos + `KbRetrievalStatusTest.php` (9 casos) + `SUPERFICIE.md` regenerado pela máquina (`module-surface.mjs Jana --write`, 563→564)
- Deploy: container `oimpresso-mcp` reconstruído **20:01** (11 min após o merge); `aab09129c` contém o merge — confirmado por `merge-base --is-ancestor`

## Lições

- **`git branch --contains` disse NÃO para uma branch mergeada** — squash merge não põe o commit original no `main`, só o conteúdo. Parar na primeira leitura teria fabricado uma perda inexistente. O que resolve é comparar **conteúdo** (blob hash / diff), não ancestralidade.
- **`Grep` com alternância devolveu lista incompleta** (5 arquivos, faltando o `KbAnswerService.php` que eu tinha acabado de ler). Refeito com `git grep` contado antes de qualquer conclusão — a varredura parcial é LC-08 esperando acontecer.
- **Estado de produção é medição datada, não fato permanente**: reportei "produção não tem a mudança" (verdade às 20:0x) e minutos depois o deploy havia ocorrido. Re-medir antes de testar evitou rodar smoke contra código antigo e chamar o resultado de prova.
- O teste existente `McpDocsHybridSearchTest:22` **não fixa** `scout.meilisearch.host`: o caminho que ele exercita depende do env do runner. No CT 100 o host vem preenchido e o 503 é exercido; num runner sem `MEILISEARCH_HOST` cairia no early-return. O teste novo fixa sempre e prova o caminho com `Http::assertSent`/`assertNothingSent`.

## Aberto

- **O eval mede pipeline diferente do de produção.** `jana:ragas-real-eval` roda com `environments(['staging'])` ([Kernel.php:571](../../app/Console/Kernel.php)) e o invocador real (`scripts/tests/ct100-jana-evals.sh:77`) usa `oimpresso-staging`, onde `docs_pipeline=false` → mede FULLTEXT enquanto produção serve hybrid. Some-se: queda do Meilisearch em prod não vira `n_no_context` (o fallback devolve algo), então a nota cai e o diagnóstico lê "a IA piorou". O `retrieval_status` existe no service mas o comando **não o lê**. Antes de mexer no baseline, ver [proibicoes §5 2026-07-17](../proibicoes.md) (regravar baseline de instrumento sem provar que ele mede o que diz medir).
- Branch `claude/amazing-bhaskara-d6137d` foi **pushed** para preservar 7 commits de outra frente (censo de IA / `system-map.mjs` / lápide LC-08) que só viviam no disco local e se perderiam no arquivamento da sessão.

## Pointers

- Session log: [2026-07-28-jana-retrieval-degradacao-visivel.md](../sessions/2026-07-28-jana-retrieval-degradacao-visivel.md)
- SPEC: [SPEC-retrieval-tools-mcp-unificado.md](../requisitos/Jana/SPEC-retrieval-tools-mcp-unificado.md) (US-RET-001)
- ADRs de contexto: 0053 (MCP server) · 0093 (multi-tenant Tier 0) · 0318 (ragas real mata tautologia) · 0322 (instruction-prefix qwen3)
