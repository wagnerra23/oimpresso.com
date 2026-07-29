---
id: sessions-2026-07-29-kb-classificacao-categoria-medicao
date: "2026-07-29"
type: session
topic: "KB — classificação por categoria: o classificador já existia há 12 dias; faltava invocação e 2 subcategorias no banco"
tldr: "O trabalho que pagou não foi construir — foi medir antes de acreditar no diagnóstico herdado. O chip e dois docs canônicos diziam 'falta o classificador'; ele existia desde 07-17. Causas reais: invocação zero + drift de seed."
prs: [5017, 5021]
---

# KB — o que faltava não era código, era alguém puxar o gatilho

## Como a sessão começou errada

O chip de entrada trazia um diagnóstico pronto e plausível:

> 🔴 **Lista por categoria nasce vazia (biz=1)** — Falta o **classificador** que lê `auto_match` — hoje com **zero leitores em PHP**.

E recomendava atacá-lo primeiro, com a justificativa de que "destrava a tela que já existe e já serve dado real". A justificativa estava certa. O diagnóstico, não.

O `BRIEFING.md` do módulo repetia a mesma frase. O `Index.v2.charter.md` também, com o classificador listado como *"(a construir)"*. Três fontes concordando — e as três derivavam da mesma escrita, de 2026-07-17.

**O que quebrou a concordância foi um `git grep` de 10 segundos** antes de escrever a primeira linha de código.

## O que a medição achou

`KbAutoClassifierService` + `KbClassifyCommand` existiam desde **2026-07-17** ([#4465](https://github.com/wagnerra23/oimpresso.com/pull/4465)) — serviço, comando com `--dry-run` default, 7 testes (incluindo cross-tenant biz=1 vs biz=99), registro no `KBServiceProvider:132`.

O gap era **operacional**, decomposto em duas causas independentes:

1. **Ninguém o invocava.** `git grep "kb:classify"` no repo inteiro: zero schedule, zero chamador. O `--apply` era manual e nunca tinha rodado.
2. **Drift de seed.** As subcategorias `reference` e `comparativo` tinham regra `auto_match` no `KbSubcategoriesSeeder` desde 07-17 — decisão do [W] registrada em comentário no próprio código — mas nunca chegaram ao banco de biz=1. Por isso 404 nós apareciam como *"nenhuma regra `auto_match` casa"*, mensagem que o comando qualifica como *"dívida de taxonomia, não erro"*. Correta no geral; aqui, escondia drift prod↔git.

## O segundo diagnóstico herdado que também estava errado

O chip listava `body_blocks` como gap 🟡: *"o bridge copia metadata, não `body_blocks`"* — sugerindo consertar o bridge.

Medido: `KbNodeObserver:33` lança `DomainException` se `is_editable=false` e `body_blocks` não-vazio. É **invariante Tier 0** ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)), não esquecimento. E o próprio Observer diz onde o corpo deve vir: *"deve vir do JOIN com `mcp_memory_documents`"*.

Ou seja: seguir o chip teria produzido um PR que o Observer barra em runtime. Uma sessão paralela fechou o gap pelo caminho certo no [#5018](https://github.com/wagnerra23/oimpresso.com/pull/5018) — consumidor no `.tsx`, não escrita no bridge.

## O que foi entregue

**Em prod (com go do [W], por caminho canônico — zero INSERT ad-hoc):**

| momento | classificáveis | sem casa |
|---|---:|---:|
| antes | 1224 | 404 |
| após re-rodar o `KbSubcategoriesSeeder` (idempotente, 18→20) | 1628 | **0** |
| após `kb:classify --business=1 --apply` | **1628** | 0 NULL |

Smoke: `Governança 0` → **1589** na lateral (1628 − 39 `deleted`), subcategorias populadas, filtro respondendo.

**Em código:** [#5017](https://github.com/wagnerra23/oimpresso.com/pull/5017) — o `KbBridgeFromMcpJob` passa a chamar o `KbAutoClassifierService` no fim do run, senão o backfill decai (o Job cria nós NULL a cada 15 min). Bite-test + controle negativo.

**Em documentação:** [#5021](https://github.com/wagnerra23/oimpresso.com/pull/5021) — errata do BRIEFING e do charter, com o erro **visível** em vez de apagado, e os números como recibo (query + resultado + data + sistema), na forma da lápide §5 de 2026-07-17.

## Erros meus, para o ledger

| erro | como apareceu | o que salvou |
|---|---|---|
| **`exit code` do wrapper ≠ do comando** | notificação disse `exit code 0`; o arquivo tinha `exit=137` — meu `timeout 600` matou o `--apply` com 738/1628 | ler o arquivo em vez de confiar na notificação |
| **medir UI antes da hidratação** | cliquei logo após `navigate`, estado não mudou, quase reportei o filtro como quebrado | clicar por `ref` com a página pronta |
| **verde de validator que não executou** | `memory-schema-validate` deu `exit=0` com `[OK] nenhum arquivo NOVO/MODIFICADO` — ele lê `changed-files.txt`, que eu não tinha gerado | reproduzir o passo do CI |
| **comparar lanes com `paths-filter` diferentes** | afirmei que o #5008 quebrou Compras/Estoque/Ponto porque "passam em outras branches" | ler o header do `estoque-pest.yml`: os testes são **failing-first por desenho** |

O último é o mais instrutivo porque **eu tinha escrito o alerta contra ele no início da própria sessão** (*"verde de lane com paths-filter não é verde — confira a duração"*) e caí assim mesmo, três horas depois, no eixo espelhado (vermelho em vez de verde).

## O padrão da sessão

Quatro diagnósticos herdados, todos plausíveis, todos com fonte canônica citável:

- "falta o classificador" → existia há 12 dias
- "o bridge não copia `body_blocks`" → é invariante Tier 0 com trava
- "a lane KB está vermelha" → estava verde; o vermelho é flaky intermitente
- "os 404 sem casa são dívida de taxonomia" → era drift de seed

Nenhum foi derrubado por raciocínio. Todos por rodar um comando.
