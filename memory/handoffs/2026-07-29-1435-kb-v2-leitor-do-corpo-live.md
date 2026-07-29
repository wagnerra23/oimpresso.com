---
date: "2026-07-29"
time: "1435"
slug: kb-v2-leitor-do-corpo-live
tldr: "/kb/v2 ganhou leitor: o corpo do documento canônico agora renderiza (markdown GFM via JOIN). O endpoint JÁ existia — o gap era o consumidor no .tsx. PR #5018 mergeado e LIVE, smoke em prod confirmado com screenshot."
decided_by: [W]
cycle: null
prs: [5018]
us: [US-KB-001]
---

# Handoff — KB V2 ganhou leitor do corpo (LIVE em prod)

## TL;DR

`/kb/v2` era índice sem leitor. **[PR #5018](https://github.com/wagnerra23/oimpresso.com/pull/5018)
mergeado (squash `7bbbde2022`), deploy verde, smoke em prod confirmado.** O corpo do
documento canônico renderiza como markdown GFM, vindo por **JOIN** com
`mcp_memory_documents` — nunca copiado pra `kb_nodes` (invariante Tier 0 intacta).

## O achado que mudou o plano

O brief pedia rota `kb.v2.show` + método novo no Controller. **A medição em prod matou a
premissa antes da 1ª linha de código:**

```
GET /kb/nodes/session-2026-07-29-varredura-de-populacao-tres-rodadas   (sessão [W], biz=1)
  → HTTP 200 · content_md 6.933 chars (17× o excerpt) · github_url ok
  → is_editable=false · body_blocks=null   ← invariante intacta
  → tem_tabela_gfm: true
```

`KbNodeController@show` já fazia o JOIN e já era **lei declarada** em
`NodeReader.charter.md` Goal 2. Faltava o **consumidor**: **21 de 21** menções a
`/kb/nodes` em `resources/js/` eram comentário, **zero `fetch`**. Rota nova seria 2º
oráculo (§5 "duplica régua consolidada") — não foi criada.

## Estado — o que ficou LIVE

| peça | estado |
|---|---|
| leitor do corpo (`useKbNodeBody` + `BridgeBody`) | ✅ LIVE, smoke confirmado |
| markdown GFM (tabelas do corpus) | ✅ 2 tabelas / 7 linhas renderizadas em prod |
| invariante `body_blocks=NULL` | ✅ intacta e provada por teste **depois** de servir o corpo |
| `KbNodeBodyReaderTest` (4 casos) | ✅ na allowlist; lane KB **106 passed / 597 assertions** |
| `BRIEFING` · `SUPERFICIE` · `casos.md` | ✅ reconciliados (UC-KBV2-14) |

## ⚠️ Aberto — o smoke achou 2 defeitos de acabamento MEUS (fix em PR de follow-up)

O CI passou (20+ checks) e **só o smoke real pegou** — a razão de a R1 existir:

1. **`"Sem conteúdo ainda"` aparecia JUNTO com o corpo.** O `BlockRenderer` continuava
   sendo chamado com `body_blocks = null` (sempre null em bridge) e renderizava o próprio
   empty state logo abaixo do texto real — a tela se contradizendo.
2. **O excerpt ecoava o começo do corpo.** O bridge gera o excerpt cortando o `content_md`
   em 400 chars (markdown cru), então `# Título` + `**TL;DR:**` apareciam crus acima do
   mesmo trecho já renderizado.

**Fix:** `BlockRenderer` só no ramo não-bridge; excerpt só enquanto carrega ou em erro
(quando é a única coisa que o leitor tem). **Segue em PR de follow-up — não mergeado ainda.**

## ⚠️ Aberto — dívida que NÃO é minha, mas está no caminho

- **Flakiness Spatie order-dependent (GAP 2 do `kb-pest.yml`).** Provada por duas runs de
  **vítimas opostas** (run 1: `V3` caiu / meu `L3` passou; run 2: `L3` caiu / `V3` passou).
  Mitiguei no meu teste medindo o **contrato** (`{403,404}` — as duas fail-secure) em vez
  do código da negativa, **com controle positivo** obrigatório pra não virar verde por
  não-execução (LC-13). **A dívida em si segue aberta** e o próprio workflow diz o caminho:
  *"isolamento por-teste REAL — PR de test-infra dedicado, não mais tweaks."*
- **`PHP / Pest (Estoque · MySQL)` vermelho em `main`** há 5+ runs (contratos do Produto,
  failing-first por desenho). Não-required. Zero relação com este PR.
- **`react-markdown` não declarado no `package.json`** — resolve como transitiva de
  `@assistant-ui/react-markdown`. `Pages/kb/Index.tsx` já dependia disso; latente herdado.
- **`/kb/graph` segue fachada** (closure sem props; `/kb/graph/data` devolve `{}` hardcoded).

## Duas defesas do repo que morderam em mim — e estavam certas

`block-destructive` barrou o `--force-with-lease` (push pós-rebase) **e** o
`git reset --hard` do plano B. Resolvido sem bypass: `git merge -s ours` da própria branch
registra os commits pré-rebase como superseded, mantém a árvore certa e permite push
fast-forward. Main faz squash — o ruído não chega lá.

## O padrão que apareceu 2× no mesmo dia (vale pro próximo)

O rebase conflitou no `BRIEFING.md` com o [#5021](https://github.com/wagnerra23/oimpresso.com/pull/5021),
mergeado horas antes. As duas verdades foram **fundidas, nenhuma sobrescrita** — e são a
**mesma família de erro**:

- **#5021:** *"`auto_match` tem ZERO leitores em PHP"* — falso desde 07-17; o classificador
  existia, faltava **invocação**.
- **#5018:** *"o bridge copia metadata, não `body_blocks`"* — descrevia o mecanismo certo,
  mas soava como dívida do bridge; o endpoint existia, faltava **consumidor**.

Duas frases de doc canônico que mandaram sessões inteiras na direção errada pelo mesmo
motivo: **descreviam o mecanismo, não a invocação dele**. É *"correção-do-mecanismo ≠
invocação"* (§5 2026-07-09) chegando à camada de **doc**. Se virar lápide, é do [W] decidir
— não escrevi §5 por conta própria.

## Estado MCP no momento do fechamento

Consultado 2026-07-29 ~14:30 UTC (11:30 BRT):

- **`cycles-active`** → `Nenhum cycle ATIVO em COPI.`
- **`decisions-search "KB leitor corpo body_blocks bridge mcp_memory_documents"`** → 5 ADRs,
  nenhuma sobre o leitor do KB (retornou governança de memória: 0053, 0056, 0059, 0080 +
  ARQ-0009). **Não há ADR a criar** — o contrato do leitor já vive no
  `NodeReader.charter.md` Goal 2, que este PR só passou a cumprir.
- **`sessions-recent`** (via `ls -t memory/sessions/`) → as 5 mais novas são de 07-28/07-29
  (varredura de população, revert Blade/tenant-98, ativação online eval, ciclo de
  observação Jana, KB RAG degradação). **Nenhuma cobria o leitor do KB** — sem duplicação.
- **Handoffs de 2026-07-29 já existentes:** `0250`, `0836`, `1056`, `1150`. Este é o 5º,
  arquivo **novo** (append-only, nada sobrescrito).

## Próximo passo

1. Mergear o **PR de follow-up** com os 2 fixes de acabamento + re-smoke.
2. (opcional, [W] decide) PR de test-infra pro isolamento do GAP 2 — é o que destrava a
   lane KB de vez e para de fazer PRs alheios pagarem por flake.

## Refs

- [Session log](../sessions/2026-07-29-kb-v2-leitor-do-corpo.md) — narrativa + as 4 rodadas de CI
- [`Index.v2.casos.md`](../../resources/js/Pages/kb/Index.v2.casos.md) UC-KBV2-14 + recibo
- [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0253](../decisions/0253-primitivos-layout.md)
