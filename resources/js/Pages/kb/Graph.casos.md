---
id: resources-js-pages-kb-graph-casos
casos: "KB — visualização-grafo · /kb/graph"
irmaos: "Graph.charter.md (lei · v1.0 · status draft) · Index.casos.md · SDD-tela-kb-unificado-v1.0.md (§6.2 CU-KB-09)"
tecnica: "Caso de uso = narrativa + critério de aceite verificável, derivado do §6 do SDD (nunca do .tsx)"
owner: wagner
last_run: "2026-08-12"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (KB · MySQL)"
status_tela: "viva-em-MOCK — a rota existe e responde, mas NENHUM Controller a serve: closure Inertia::render sem props + /kb/graph/data hardcoded vazio → a tela cai em _lib/mockGraphData.ts"
---

# Casos de uso — `/kb/graph` (visualização-grafo)

> **Leia isto antes de qualquer UC.** Esta tela é **fachada medida**, não promessa vaga:
> `Modules/KB/Http/routes.php` registra `/kb/graph` como **closure `Inertia::render('kb/Graph')`
> sem props**, e `/kb/graph/data` devolve `{nodes:[],edges:[],kpis:null}` **hardcoded** — então o
> `Graph.tsx` cai em `_lib/mockGraphData.ts` e mostra badge "modo mock". O `anchor-lint` já acusa por
> outro caminho: *"US-KB-006 wired porém NÃO-SERVIDO — 0 hits na janela do ledger"*.
> Detalhe e varredura contada: [SDD §5.3 F7 e §9 D-3](../../../../memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md).
>
> **Consequência pro contrato:** os UC abaixo travam **o piso da rota** (auth · render · leitura pura) —
> exatamente o que a tela irmã fez na era-mock dela (`Index.v2.casos.md` UC-KBV2-01..04). Contratar
> *conteúdo* de grafo agora seria contratar o mock. O grafo real vira UC **no mesmo PR** que trouxer
> o Controller.
>
> **Status por UC:** ✅ verde provado · 🧪 escrito, aguarda veredito da lane · ⬜ não verificado.
> **Força do veredito:** lane `PHP / Pest (KB · MySQL)` — consultado `governance/required-checks-baseline.json`,
> **não é required** → **advisory** (reprova visível, não bloqueia merge). E o arquivo de teste
> **ainda não está na allowlist** (catraca-por-prova-verde): entrada **proposta** a [W].

---

## Rastreabilidade UC → CU → US

| UC | CU no SDD | US no SPEC | Teste que o cita |
|---|---|---|---|
| UC-KBG-01 | CU-KB-09 | US-KB-006 | `KbGraphContratoTest.php` G1/G1b |
| UC-KBG-02 | CU-KB-09 | US-KB-006 | `KbGraphContratoTest.php` G2 |
| UC-KBG-03 | CU-KB-09 | US-KB-006 | `KbGraphContratoTest.php` G3/G3b |

---

## UC-KBG-01 — A rota do grafo exige autenticação
Status: 🧪 (`Modules/KB/Tests/Feature/KbGraphContratoTest.php` — G1/G1b)
Visitante não autenticado que abre `/kb/graph` (ou o endpoint `/kb/graph/data`) é barrado pela stack
middleware canônica antes de ver qualquer coisa — inclusive antes de ver a *estrutura* do grafo, que
é mapa da governança da empresa. **Âncora:** `Modules/KB/Http/routes.php` (grupo `/kb` com
`['web','SetSessionData','auth',…]`) + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
SDD `CU-KB-09`.
**Pronto quando:** GET anônimo em `/kb/graph` e em `/kb/graph/data` não devolve 200 nem 500.

## UC-KBG-02 — A rota serve a tela do grafo, e ela está registrada nomeada
Status: 🧪 (`KbGraphContratoTest.php` — G2)
Wagner autenticado abre `/kb/graph` e recebe a página Inertia `kb/Graph`. A rota tem nome canônico
registrado (o que permite `route()` no front e o que o `anchor-lint` usa pra medir "wired").
**Âncora:** charter `Graph.charter.md` (`component: resources/js/Pages/kb/Graph.tsx`) + `routes.php` ·
SDD `CU-KB-09`.
**Pronto quando:** `/kb/graph` responde 200 com o componente Inertia `kb/Graph`, e o nome da rota
está registrado. ⚠️ O nome REAL é `kb.graph.page` — o charter dizia `kb.graph`, que **não existe**
(`Route::has('kb.graph')` → false). Corrigido no charter neste run; o teste trava o nome real pra
que a divergência não volte calada.

## UC-KBG-03 — Abrir o grafo é leitura pura
Status: 🧪 (`KbGraphContratoTest.php` — G3/G3b)
Renderizar o grafo não escreve nada e não enfileira Job — em particular **não dispara bridge nem
derivação de arestas** ao abrir (isso é trabalho de fila/cron, não de request de tela).
**Âncora:** charter §Non-Goals (a tela não edita nó) + paridade com `UC-KBV2-03/04` (mesmo módulo,
mesma stack) · SDD `CU-KB-09`.
**Pronto quando:** a contagem de `kb_nodes` e `kb_edges` é idêntica antes e depois do GET, e com a
fila fingida nenhum Job é despachado.

---

## Backlog — o grafo de verdade

**[BACKLOG] Grafo servido por Controller real, com `business_id` scope e `Inertia::defer`** (SDD `CU-KB-09` / §9 D-3)
Hoje: closure sem props + endpoint vazio hardcoded. Alvo declarado pelo próprio código
(`routes.php`: *"Agent A substitui por KbGraphController@vis real (Inertia::defer business_id scope)"*)
e pelo charter (`controller: …KbGraphController@index (TODO Agent A — ONDA 5 backend)`).
**Vira UC quando** o Controller existir — e aí o primeiro UC é `[T0]`: nó/aresta de outro business
não aparece no payload (mesma prova forte que a `UC-KBV2-05` já faz pra tela irmã, com biz=99
fictício — **NUNCA biz=4**, [ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
**Construir isso é decisão de produto de [W]**, não conserto de agente: envolve escolher se o grafo
lê `kb_nodes`/`kb_edges` (multi-tenant) ou o acervo canon repo-wide — os dois mundos do módulo
(SDD §5.4).

**[BACKLOG] Divergência de lib não reconciliada** (SDD `CU-KB-09` item 4)
SPEC US-KB-006 diz **Cytoscape**; o código escolheu **Reactflow 11**, com justificativa registrada no
cabeçalho de `Graph.tsx` (lib já instalada + precedente em `Pages/ads/Admin/Graph.tsx`). Nenhum dos
dois lados é "o perdedor" óbvio: reconciliar é decisão de [W]. Não é UC — é doc.
