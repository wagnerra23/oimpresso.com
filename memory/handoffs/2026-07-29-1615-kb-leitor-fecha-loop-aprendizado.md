---
date: "2026-07-29"
time: "1615"
slug: kb-leitor-fecha-loop-aprendizado
tldr: "Fecha o que o handoff das 14:35 deixou aberto: os 2 defeitos que o smoke achou foram corrigidos (#5029) e o loop de aprendizado virou emenda §5 + LC-08 → 29 (#5035). Os 3 PRs da sessão estão em main."
decided_by: [W]
cycle: null
prs: [5018, 5029, 5035]
us: [US-KB-001]
---

# Handoff — fecha o loop da sessão do leitor do KB

> **Continuação de [`2026-07-29-1435-kb-v2-leitor-do-corpo-live.md`](2026-07-29-1435-kb-v2-leitor-do-corpo-live.md)**, que é o handoff completo da sessão (recibo da medição, as 4 rodadas de CI, a flakiness GAP 2, as defesas do repo que morderam). Este aqui **só registra o que mudou depois dele** — append-only, o anterior não foi tocado.

## TL;DR

O handoff das 14:35 fechou com dois itens abertos. **Os dois fecharam.**

| item que estava aberto às 14:35 | estado agora |
|---|---|
| *"fix dos 2 defeitos de acabamento — **segue em PR de follow-up, não mergeado ainda**"* | ✅ [#5029](https://github.com/wagnerra23/oimpresso.com/pull/5029) `211e837a4b` — CI 100% verde |
| *"se virar lápide, é do [W] decidir — não escrevi §5 por conta própria"* | ✅ [W] autorizou → [#5035](https://github.com/wagnerra23/oimpresso.com/pull/5035) `fe0f714170` — CI 100% verde |

## O que o #5029 corrigiu (achado só pelo smoke)

Dois defeitos que **20+ checks verdes não pegaram**, porque os gates olham prop, tipo e pixel-baseline — **nenhum renderiza a tela com dado real**:

1. **`"Sem conteúdo ainda"` aparecia junto com o corpo.** O `BlockRenderer` continuava sendo chamado no ramo bridge, onde `body_blocks` é sempre `null` → renderizava o próprio empty state **embaixo de 6.237 chars de conteúdo**.
2. **O excerpt ecoava o começo do corpo.** O bridge gera o excerpt cortando o `content_md` em 400 chars (markdown cru), então `# Título` + `**TL;DR:**` apareciam crus acima do mesmo trecho já renderizado.

Nenhum quebrava algo que funcionava — mas os dois faziam a **tela mentir**. É o irmão do anti-hook da §6 do charter: *nunca afirmar ausência do que está na tela*.

**Re-smoke pós-deploy confirmou:** `corpo 8.029 chars · 13 headings · link GitHub ✓ · DEFEITO_1 false · DEFEITO_2 false · placeholder false`.

## O que o #5035 registrou — e o que ele deliberadamente NÃO fez

A hipótese (*"doc que descreve mecanismo sem dizer onde é invocado convida a concluir que não existe"*) foi **testada contra o ledger antes de virar canon**, e **não sobreviveu como classe nova**.

A lápide §5 **2026-07-28** já proíbe o mesmo: *afirmar AUSÊNCIA ("não existe máquina/gate/teste/**consumidor** pra X") exige varredura no repo inteiro **+** dono do inventário*. O brief dizia *"não existe rota `show` da V2"* — claim negativa, **mesma classe**.

Então virou **emenda**, cujo conteúdo real é o **alcance**: a lista de donos da mãe cobre workflow/hook/skill/ledger e **nenhum responde "existe endpoint pra X?"**. Três donos novos pro eixo rota/endpoint:

- `Modules/<X>/Http/routes.php` + `route:list` — runtime é o oráculo (§5 07-17)
- **o charter** (`Goal`/`route:` no frontmatter) — declara o contrato do endpoint; **inventário de 1ª classe que ninguém tinha nomeado**
- o `SCOPE.md` do módulo (`url_prefixes`/`contains`)

**Não entrou:** classe nova (14 antes e depois), gate novo, índice novo. Contador: **LC-08 28 → 29**.

## Estado MCP no momento do fechamento

Consultado 2026-07-29 ~16:15 UTC (13:15 BRT):

- **`cycles-active`** → `Nenhum cycle ATIVO em COPI.` (idem 14:35)
- **`decisions-search "KB leitor corpo body_blocks bridge mcp_memory_documents"`** → 5 ADRs, nenhuma sobre o leitor. **Nada de ADR a criar** — o contrato já vive no `NodeReader.charter.md` Goal 2.
- **Reconciliação §5↔ledger rodada EM `main`** (não só na branch): `frontier 2026-07-29` · `recibos_resolvem 23/23` · `recibos_pendurados_S2 []` · `surface_S3_pos_frontier []` — a lápide está corretamente wirada ao contador.
- **`memory-health`** → exit 0.
- **Handoffs de 2026-07-29 já existentes:** `0250`, `0836`, `1056`, `1150`, `1435`. Este é o **6º**, arquivo novo — nada sobrescrito.

## Placar da sessão — tudo em `main`

| PR | o quê | sha |
|---|---|---|
| [#5018](https://github.com/wagnerra23/oimpresso.com/pull/5018) | leitor do corpo em `/kb/v2` | `7bbbde2022` |
| [#5029](https://github.com/wagnerra23/oimpresso.com/pull/5029) | acabamento + memória R12 | `211e837a4b` |
| [#5035](https://github.com/wagnerra23/oimpresso.com/pull/5035) | emenda §5 + LC-08 → 29 | `fe0f714170` |

## Segue aberto (nada disto é regressão desta sessão)

- **Isolamento do GAP 2 da lane KB** — a flakiness Spatie order-dependent. O próprio `kb-pest.yml` diz o caminho: *"isolamento por-teste REAL (DatabaseTransactions sem drop de schema, ou limpeza targeted das CORE tables) — PR de test-infra dedicado, não mais tweaks."* **É o próximo passo natural, e é decisão [W]** (mexe em infra de teste compartilhada por 15 arquivos).
- **`PHP / Pest (Estoque · MySQL)` vermelho em `main`** há 5+ runs (contratos do Produto, failing-first por desenho). Não-required, zero relação com esta sessão.
- **`react-markdown` não declarado no `package.json`** — transitiva de `@assistant-ui/react-markdown`; `kb/Index.tsx` já dependia. Latente herdado, não ampliado.
- **`/kb/graph` segue fachada** (closure sem props; `/kb/graph/data` devolve `{}` hardcoded).

## Nota de ferramenta

O `ci-monitor` disparou **3×** na sessão (#5018, #5029, #5035) pedindo *"address the feedback"* sobre comentários que eram o **`Module Grades Gate` reportando `✅ all clear`**. Ele trata todo comentário de bot como achado, sem distinguir relatório verde. Não respondi nenhum — postar "endereçado" num gate que passou é ruído. Se incomodar, o ajuste é no monitor, não nos PRs.

## Refs

- [Handoff completo das 14:35](2026-07-29-1435-kb-v2-leitor-do-corpo-live.md) — o recibo, as 4 rodadas de CI, a flakiness
- [Session log](../sessions/2026-07-29-kb-v2-leitor-do-corpo.md)
- `memory/proibicoes.md` §5 2026-07-28 (mãe) + emenda 2026-07-29 · [`LICOES_CODE.md`](../LICOES_CODE.md) LC-08
