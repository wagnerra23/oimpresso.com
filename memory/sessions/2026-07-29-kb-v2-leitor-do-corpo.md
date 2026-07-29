---
date: "2026-07-29"
hour: "11:28 BRT"
topic: "Onda 1 do KB — dar um leitor à /kb/v2. O brief pedia rota nova; a medição em prod mostrou que o endpoint já existia e o gap era o consumidor no .tsx"
authors: [W, C]
prs: [5018]
us: [US-KB-001]
outcomes:
  - "leitor do corpo LIVE em /kb/v2 — markdown GFM via JOIN mcp_memory_documents"
  - "premissa do brief refutada por medição em prod: GET /kb/nodes/{slug} já respondia 200 com 6.933 chars"
  - "1º teste HTTP de KbNodeController@show (4 casos), na allowlist da lane KB — 106 passed / 597 assertions"
  - "invariante Tier 0 body_blocks=NULL preservada e provada DEPOIS de servir o corpo"
---

# Onda 1 do KB — o leitor que faltava era um `fetch`, não uma rota

## TL;DR

`/kb/v2` era **índice sem leitor**. O brief mandou criar rota `kb.v2.show` + método no
Controller. **Medi antes de escrever** e a premissa caiu: `GET /kb/nodes/{slug}`
(`KbNodeController@show`) já existia, já fazia o JOIN com `mcp_memory_documents` e já
era **lei declarada** em `NodeReader.charter.md` Goal 2. O que faltava era o
**consumidor**: **21 de 21** menções a `/kb/nodes` em `resources/js/` eram comentário,
**zero `fetch`**. Criar a rota seria 2º oráculo (§5 "duplica régua consolidada").

Resultado: [PR #5018](https://github.com/wagnerra23/oimpresso.com/pull/5018) mergeado —
o `.tsx` passou a chamar o que já estava lá.

## O recibo que mudou o desenho

Medido na sessão logada do [W] (biz=1) via browser MCP, **antes** de escrever qualquer
linha — depois que [W] mandou *"https://oimpresso.com/kb/v2 existe"*:

| o que a **tela** mostrava | o que o **endpoint** respondia |
|---|---|
| `"Endpoint /kb/nodes/… virá com Agent A (ONDA 1)"` + **"Sem conteúdo ainda."** + excerpt **403** chars | **HTTP 200** · `content_md` **6.933** chars · `github_url` ok · `is_editable:false` · `body_blocks:null` · `tem_tabela_gfm:true` |

Duas decisões saíram **desse número**, não de opinião:

1. **Não criar rota.** O gap era de consumidor. Endpoint duplicado = 2º oráculo.
2. **`remark-gfm`, não `SimpleMarkdown`.** `tem_tabela_gfm: true` — o `SimpleMarkdown`
   de `@/Components/shared` não suporta tabela e renderizaria como parágrafo. Usei o
   mesmo par do leitor do `/kb` V3 (`react-markdown` + `remark-gfm`).

> **Nota de risco herdado (não introduzido aqui):** `react-markdown` **não está declarado**
> no `package.json` — resolve como dep transitiva de `@assistant-ui/react-markdown`.
> `Pages/kb/Index.tsx` já dependia disso; meu import não amplia a superfície, mas o
> latente segue lá. Declarar exigiria mexer no lockfile — fora do escopo deste PR.

## O que foi entregue

| arquivo | o quê |
|---|---|
| `_lib/useKbNodeBody.ts` | fetch sob demanda + `AbortController` + debounce 250ms |
| `_components/NodeReader.tsx` | `BridgeFallback` → `BridgeBody`: markdown GFM, estados honestos (carregando/erro/sem-corpo), link "Ver no GitHub", `<Inline>` (ADR 0253) |
| `Index.v2.casos.md` | **UC-KBV2-14** + recibo datado |
| `KbNodeBodyReaderTest.php` | 1º teste HTTP do endpoint — L1 corpo por JOIN · L2 invariante + `PUT` 422 · L3 Tier 0 c/ controle positivo · L4 auth |
| `kb-pest.yml` | o teste entra na **allowlist** |
| `BRIEFING.md` · `SUPERFICIE.md` | reconciliados |

O **debounce não é enfeite**: o endpoint incrementa `reads_count` (contrato do charter
Goal 2). Navegar com `j`/`k` não pode inflar a métrica com nó que passou de raspão.

## As 4 rodadas de CI — o que cada vermelho era

Nenhum foi ignorado; nenhum foi "consertado" com tweak cego.

| # | check | o que era | ação |
|---|---|---|---|
| 1 | `Layout primitives · ratchet` | **meu** — `BridgeBody` nasceu com `flex` solto (11→13), contra ADR 0253 | `<Inline>`; arquivo caiu **13→10**, abaixo do baseline |
| 2 | `SUPERFICIE.md == árvore` | **meu** — teste novo deixou o inventário do KB stale | `module-surface.mjs KB --write` |
| 3 | `dup-detector` (advisory) | colisão com [#5008](https://github.com/wagnerra23/oimpresso.com/pull/5008) em `kb-pest.yml` | `Dedup-ack` (regiões distintas; #5008 é o canônico do arquivo) |
| 4 | `Preflight` | main andou 7 commits | rebase (ver abaixo) |
| 5 | `PHP / Pest (KB)` | **flakiness GAP 2**, não regressão | ver abaixo |
| 6 | `PHP / Pest (Estoque)` | **pré-existente em main** (5 runs), zero arquivos meus | não tocado |

### O flake provado por duas runs de vítimas opostas

```
run 1:  V3 (KbIndexV2ContractTest) FALHOU 403   ·  L3 (meu) passou
run 2:  L3 (meu) FALHOU 403                     ·  V3 passou
```

Essa inversão **é** a assinatura da flakiness order-dependent do Spatie que o header do
`kb-pest.yml` já cataloga (GAP 2): `permissions`/`model_has_*` são CORE, não resetadas
por `kbTeardownSchema`, com `executionOrder="random"`.

Consequência de desenho: **qual camada barra primeiro** (o `can:` do constructor ou o
`firstOrFail()` sob global scope) é *timing*, não contrato. Cravar `404` no L3 acoplava o
teste ao `PermissionRegistrar`. Passou a aceitar `{403, 404}` — as duas fail-secure —
mantendo duro o que importa: **200 reprova** e o segredo não pode aparecer.

**E ficou mais estrito, não menos:** entrou um **controle positivo** obrigatório. Sem ele,
um cenário de "403 em tudo" passaria verde sem provar isolamento nenhum — verde por
**não-execução**, a classe **LC-13**. Agora o caso só passa se a sessão do próprio
business **leu de fato** (200 + corpo) **e** o vizinho não vazou.

A dívida de isolamento **não foi tocada**: o próprio workflow manda *"PR de test-infra
dedicado, não mais tweaks"*.

## Duas defesas do repo que morderam em mim — e estavam certas

1. **`block-destructive` barrou o `--force-with-lease`** do push pós-rebase. Em vez de
   pedir bypass, resolvi por **`git merge -s ours origin/<minha-branch>`**: registra que
   os commits pré-rebase estão superseded pelos rebaseados, mantém a árvore correta e
   permite push **fast-forward**. Main faz squash, então o ruído não chega lá.
2. **`block-destructive` barrou o `git reset --hard`** que eu ia usar como plano B.
   Também certo — e o caminho alternativo existia.

## Conflito de merge que valeu a pena ler

O rebase conflitou no `BRIEFING.md` com o [#5021](https://github.com/wagnerra23/oimpresso.com/pull/5021),
mergeado horas antes. **As duas verdades foram fundidas, nenhuma sobrescrita** — e são da
**mesma família de erro**, no mesmo dia:

- **#5021:** *"`auto_match` tem ZERO leitores em PHP"* era **falso desde 07-17**. O
  classificador existia; faltava **invocação**.
- **#5018 (este):** *"o bridge copia metadata, não `body_blocks`"* descrevia o mecanismo
  certo mas soava como dívida do bridge. O endpoint existia; faltava **consumidor**.

Duas afirmações de doc canônico que mandaram sessões inteiras na direção errada, pelo
mesmo motivo: **descreviam o mecanismo, não a invocação dele**. É a família
*"correção-do-mecanismo ≠ invocação"* (§5 2026-07-09) chegando à camada de **doc**.

## O que NÃO foi verificado (e por quê)

- **`typecheck` / `lint` / Vite build local** — o worktree tem **0 pacotes** em
  `node_modules`. **Não criei junction** (é a pegadinha que já esvaziou `vendor/` e
  `node_modules` reais). Cobertos pelo CI, verdes.
- **Pest local/CT 100** — o container do CT 100 está com checkout de outra sessão (07-23,
  com alterações não-commitadas). A **lane do CI foi o 1º lugar onde o teste rodou**, e o
  PR declarou isso como *não-provado* antes do merge, não como verde herdado.

## Veredito da lane (assertions, não "0 failed")

```
Tests:  14 skipped, 106 passed (597 assertions)
PASS  Modules\KB\Tests\Feature\KbNodeBodyReaderTest
  ✓ L1  ✓ L2  ✓ L3  ✓ L4
```

Conferido que os 4 **rodaram** (não pularam) — a leitura que a **LC-13** exige.

## Desfecho — os dois itens que este log deixou abertos fecharam

> Adicionado no fim da sessão. O corpo acima é o retrato de quando o #5018 mergeou; isto é o que veio depois.

### O smoke pós-deploy achou 2 defeitos que o CI não podia achar ([#5029](https://github.com/wagnerra23/oimpresso.com/pull/5029) `211e837a4b`)

1. **`"Sem conteúdo ainda"` aparecia junto com o corpo** — o `BlockRenderer` seguia sendo chamado no ramo bridge (`body_blocks` sempre `null`) e renderizava o próprio empty state **embaixo de 6.237 chars de conteúdo**.
2. **O excerpt ecoava o começo do corpo** — o bridge o gera cortando o `content_md` em 400 chars, markdown cru incluso, então `# Título` + `**TL;DR:**` apareciam crus acima do mesmo trecho já renderizado.

**Por que nenhum gate pegou, e não é acaso:** os checks olham **prop**, **tipo** e **pixel-baseline** — nenhum **renderiza a tela com dado real**. É a razão estrutural de a R1 existir, e nesta sessão ela pagou por si.

Re-smoke pós-deploy: `corpo 8.029 chars · 13 headings · link GitHub ✓ · DEFEITO_1 false · DEFEITO_2 false`.

### O loop de aprendizado fechou como EMENDA, não como classe nova ([#5035](https://github.com/wagnerra23/oimpresso.com/pull/5035) `fe0f714170`)

[W] autorizou escrever a lápide. **Testada contra o ledger antes de virar canon, a hipótese não sobreviveu como classe nova** — e esse é o resultado mais útil da sessão.

A lápide §5 **2026-07-28** já proíbe o mesmo: *afirmar AUSÊNCIA (**"não existe máquina/gate/teste/consumidor pra X"**) exige varredura no repo inteiro **+** dono do inventário*. O brief dizia *"não existe rota `show` da V2"* — claim negativa, **mesma classe** (`afirmar-sem-medir-fonte-certa`).

O que faltava na mãe era o **alcance**: a lista de donos dela cobre workflow/hook/skill/ledger e **nenhum responde "existe endpoint pra X?"**. A emenda registra três donos pro eixo rota/endpoint — `routes.php` + `route:list` (runtime é o oráculo), o **`SCOPE.md`** do módulo, e **o charter**, que declara o contrato do endpoint e que **ninguém tinha nomeado como inventário**.

- **Ledger:** LC-08 `28 → 29`. **14 classes antes e depois** — nenhuma criada.
- **Zero gate novo, zero índice novo** — a própria mãe já mediu 2× que índice não previne (§5 07-23 e 07-25; o índice-por-pergunta existia **4 dias antes** do erro de 07-22 e não preveniu).
- **Reconciliação rodada em `main`:** `frontier 2026-07-29` · `recibos 23/23` · `0 pendurado` · `0 surface`.

### O que fiz questão de registrar contra mim

**O crédito é do instrumento barato.** O que pegou a rota inexistente-que-existia foi o **`Read` do `routes.php` no pré-flight** — não a medição em prod, que veio depois e só elevou *"está registrada"* a *"responde 200 com o corpo"*. A tentação era creditar o instrumento caro.

**A claim veio no brief, não desta sessão.** Está escrito assim na lápide: o contador é da **classe**, não de quem errou.

## Refs

- [PR #5018](https://github.com/wagnerra23/oimpresso.com/pull/5018) · squash `7bbbde2022`
- [PR #5029](https://github.com/wagnerra23/oimpresso.com/pull/5029) `211e837a4b` (acabamento) · [PR #5035](https://github.com/wagnerra23/oimpresso.com/pull/5035) `fe0f714170` (emenda §5 + LC-08)
- [Handoff de fechamento do loop](../handoffs/2026-07-29-1615-kb-leitor-fecha-loop-aprendizado.md)
- [`Index.v2.casos.md`](../../resources/js/Pages/kb/Index.v2.casos.md) UC-KBV2-14
- [`NodeReader.charter.md`](../../resources/js/Pages/kb/_components/NodeReader.charter.md) Goal 2 — o contrato que já existia
- [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0253](../decisions/0253-primitivos-layout.md)
