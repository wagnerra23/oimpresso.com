---
id: resources-js-pages-kb-index-v2-casos
casos: KB Unificado V2 (tri-pane) — DADO REAL · /kb/v2 + /sops
irmaos: Index.v2.charter.md (lei · v4) · Index.charter.md (V3 atual, coexiste)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
status_tela: viva-dado-real (roteada /kb/v2 + /sops via KbController@indexV2; serve kb_nodes reais; leitor do corpo ligado em 2026-07-29 via GET /kb/nodes/{slug} — JOIN mcp_memory_documents, read-only)
last_run: "2026-07-29"
---

# Casos de uso — /kb/v2 (KB Unificado tri-pane · DADO REAL)

> **Status por UC:** ✅ passa (provado por teste verde) · 🧪 em teste (Pest/Vitest escrito ou a criar, aguarda run verde CT100) · ⬜ não verificado · ❌ quebrou (medido) · ⚠️ contrato pendente de decisão/backend.

> Derivados do charter `Index.v2.charter.md` **v4** (Goals §4 + Non-Goals §5 + Anti-hooks §6 + "Como se prova" §8) + protótipo Cowork `kb-page.jsx`. Persona principal: Wagner / governança (1440px). Secundária: Larissa / balcão (1280px).
>
> **Onde os números vivem (lei "fato derivado não se restateia" — proibicoes.md §5, 2026-07-17):** este contrato **NÃO guarda contagem de acervo**. O dono do número é `kb_nodes` (a query), e o recibo datado mora no **charter §3** (medição 2026-07-17). Quando um critério precisa de um fato medido, ele aponta pra lá ou carrega recibo próprio (query + resultado + data + sistema). Não invente aqui um total que o banco sabe melhor.

> **Contexto de maturidade (âncora honesta, 2026-07-17):** `/kb/v2` (`kb.v2`) e o alias `/sops` (`sops.index`) roteiam pra `KbController@indexV2` (`Modules/KB/Http/routes.php`), que serve **kb_nodes reais** classificados (`usingMock = !props.nodes` → **false**). UC-01..05 blindam o contrato da rota (auth+permissão · render · read-only · sem side-effects · **Tier 0 forte** — V5 serve nodes e isola biz=99). UC-06 (fallback mock) **revogado** com o Controller. UC-07/08 travam client-side (localStorage · atalhos). **UC-13** surface o drift doc↔código (5º quadrante + badge, Fase #5) — backend contratado (V7), lados visuais em smoke/visual. UC-09/10/11/12 = dívida/backlog (cor crua · toasts · indicador de empresa · categoria vazia). **UC-14** liga o **leitor do corpo** (2026-07-29): o corpo vem por **JOIN** em `GET /kb/nodes/{slug}` (endpoint que já existia — faltava o consumidor no `.tsx`), renderizado como markdown e **read-only por construção** (`body_blocks` segue NULL; `PUT` em bridge devolve 422).

---

## UC-KBV2-01 — Rota viva exige autenticação
Status: 🧪 (`Modules/KB/Tests/Feature/KbIndexV2ContractTest.php` — V1/V1b, GET anônimo redireciona login)
Um visitante não autenticado que abre `/kb/v2` ou `/sops` é barrado pela stack middleware canônica (`auth`) — nunca vê o conteúdo. Âncora: rotas KB com middleware `['web', 'SetSessionData', 'auth', ...]`; ADR 0093 (nada exposto sem sessão).
**Pronto quando:** GET anônimo em `/kb/v2` e `/sops` retorna redirect (302) OU 401/403 — nunca 200 nem 500.

## UC-KBV2-02 — Renderiza o componente Inertia kb/Index.v2
Status: 🧪 (`KbIndexV2ContractTest.php` — V2/V2b/V2c, component + rota nomeada)
Wagner autenticado (biz=1) abre `/kb/v2` e recebe a página Inertia `kb/Index.v2` (tri-pane). O alias `/sops` renderiza o **mesmo** componente (coexistência /kb V3 · /kb/v2 gate · /sops atalho). Âncora: charter `component: kb/Index.v2.tsx` + rotas `kb.v2` / `sops.index`.
**Pronto quando:** `/kb/v2` responde 200 com `assertInertia(component == 'kb/Index.v2')`; `Route::has('kb.v2')` e `Route::has('sops.index')` são true; ambas resolvem o mesmo componente.

## UC-KBV2-03 — GET é read-only (não muta estado)
Status: 🧪 (`KbIndexV2ContractTest.php` — V3, nenhuma escrita no render)
Abrir a tela é leitura pura: nada é escrito no banco no render. Âncora: charter §6 Anti-hook "NUNCA escrever no banco ao abrir a tela".
**Pronto quando:** o count de `kb_nodes` (e de `kb_node_versions`) é idêntico antes e depois do GET `/kb/v2`.

## UC-KBV2-04 — Abrir a tela não dispara Jobs nem IA
Status: 🧪 (`KbIndexV2ContractTest.php` — V4, `Queue::fake()` sem push)
Renderizar `/kb/v2` não enfileira nenhum Job e não chama Brain B/Sonnet — a IA RAG só roda na ação explícita "Perguntar ao KB". Âncora: charter §6 "NUNCA disparar Jobs, e-mail, WhatsApp ou IA ao abrir".
**Pronto quando:** com `Queue::fake()`, GET `/kb/v2` resulta em `Queue::assertNothingPushed()`.

## UC-KBV2-05 — Tier 0: governança de um business NUNCA vaza pra outro
Status: 🧪 (prova FORTE — `KbIndexV2ContractTest.php` V5: com o Controller `indexV2`, o payload TEM `nodes.data` e o nó de biz=99 NÃO aparece; global scope isola, não "por construção". + `CrossTenantIsolationTest.php` R5 no modelo. Aguarda run verde CT100)

> **Reforço 2026-07-17 (era ⬜ "passa por construção"):** a V5 do `KbIndexV2ContractTest` (`:139-141`) confessa que passa *"por construção"* — render mock não serve dado, então biz=99 não aparece **porque nada aparece**. Isso não prova isolamento; prova tela vazia. A prova FORTE não depende do render: mora no **global scope de `KbNode`** (`BelongsToBusinessTrait::bootBelongsToBusinessTrait` → `addGlobalScope('business_id', ...)`, ADR 0093 Tier 0), e é medível **hoje** independente do Controller.

O KB é multi-tenant real. Duas direções, ancoradas em **fato medido** (recibo 2026-07-17, `kb_nodes` @ CT 100 `oimpresso-mcp`, biz=1; ver charter §3):
- **(a) Piso já provado no modelo:** nó de outro tenant não é lido. `CrossTenantIsolationTest.php` "blocks kb_node read across businesses (R5)" seeda nó em **biz=99** e prova `KbNode::all()->toHaveCount(0)` atuando como biz=1 (+ 404 no HTTP). **NUNCA biz=4** (ROTA LIVRE prod) — só biz=99 fictício.
- **(b) Direcional de governança (a criar):** o eixo `Governança` (`type = adr`) é 100% de biz=1. Fato de prod: **adr em biz=1 = 498 · adr em biz=4 = 0** (recibo 2026-07-17). O teste seeda `type=adr` em biz=1, atua como **biz=99** e prova `KbNode::where('type','adr')->count() === 0` — Larissa nunca enxerga um ADR do projeto. (A medição real usa biz=4; o teste usa biz=99 pra respeitar ADR 0101.)
- **(c) Payload (CUMPRIDO 2026-07-17):** o Controller `indexV2` chegou. A V5 agora asserta `has('nodes.data')` (o payload serve nós reais) **e** que o slug/título de biz=99 NÃO está lá — a prova forte que antes passava "por construção" agora morde um payload scopado de verdade.

**Pronto quando:** (a) `KbNode::all()` atuando como biz=1 não inclui nó seedado em biz=99 (verde); (b) atuando como biz=99, `KbNode::where('type','adr')->count()` é 0 com ADR seedado só em biz=1; (c) ✅ com Controller vivo (V5), o payload de biz=1 TEM `nodes.data` e NÃO contém slug/título de biz=99.

## ⚰️ UC-KBV2-06 (Fallback mock) — REVOGADO 2026-07-17
O Controller `indexV2` landou (charter §8-bis passo 2), então a tela NÃO cai mais em mock:
`usingMock = !props.nodes` é `false`. A V6 antiga assertava `missing('nodes')` — que agora seria
**falso**. Revogado no MESMO commit do Controller (a regra prevista). O teste `KbIndexV2ContractTest`
V6 foi reescrito pra provar o OPOSTO: a tela serve `nodes`+`categories`+`business` reais. **Não é
mais um UC ativo** — fica esta lápide (o heading acima NÃO começa com `## UC-` pra não contar no
casos-gate G-2; a numeração 06 fica aposentada, não reusada).

## UC-KBV2-07 — Persistência client-side é localStorage prefixado
Status: 🧪 (`tests/kbIndexV2Client.spec.tsx` — prefixo `oimpresso.kb.` + sobrevive remount + zero sessionStorage; aguarda run verde CT100)
Favoritos, recentes e categorias expandidas persistem via `localStorage` prefixado `oimpresso.kb.*` (nunca `sessionStorage`). Chaves reais medidas: `oimpresso.kb.favs.v1` · `oimpresso.kb.recent.v1` · `oimpresso.kb.paths.v1` (`_lib/useKbFavorites.ts:12` · `useKbRecent.ts:9` · `useKbPathProgress.ts:14`). O contrato travado é o **prefixo** `oimpresso.kb.`, não o sufixo (que é versionamento interno).
**Pronto quando:** favoritar + reload mantém o favorito; as chaves são prefixadas `oimpresso.kb.` e `sessionStorage` fica intocado.

> ⚠️ **NÃO é contrato estável (2026-07-16):** existe favorito **server-side real e não usado** — `routes.php:100` → `KbFavoriteController@toggle` grava `kb_favorites` com `business_id` (cross-device, por tenant). O docblock do hook (`useKbFavorites.ts:6-8`) declara o localStorage como **temporário**. Este UC trava uma decisão da era-mock (favorito device-local); **não o use como argumento pra não migrar** pro server-side. Quando [W] decidir o destino da V2 (D2 §7 do charter), este UC é reescrito junto.

## UC-KBV2-08 — ⌘K/Esc (teste) + tri-pane a 1280px sem scroll (manual)
Status: 🧪 (`tests/kbIndexV2Client.spec.tsx` — ⌘K/Ctrl+K abre paleta, Esc fecha, "/" foca busca, controles-negativos; aguarda run verde CT100)
A 1280px o layout tri-pane (sidebar + lista + leitor) não gera scroll horizontal; `⌘K`/`Ctrl+K` (ou `/`) abre o CommandPalette; `Esc` fecha o leitor. 0 erros no console. Âncora: charter §4 Goals 4 e 8.
**Pronto quando:** ⌘K/Ctrl+K abre a paleta e Esc fecha (automatizado, com controle-negativo: `k` sem modificador NÃO abre, letra não dispara digitando em input); screenshot 1280px sem barra horizontal + console limpo (**manual/browser** — jsdom não tem layout engine; dividido em vez de fingir).

## UC-KBV2-09 — Tokens semânticos, zero cor crua (dívida — juiz é o `ui:lint`)
Status: ❌ (medido 2026-07-16 — a V2 VIOLA: 68 ocorrências de cor crua absorvidas no baseline do `ui:lint`)
A V2 deveria usar só tokens semânticos Cockpit V2 (`text-primary`, `text-muted-foreground`, `border-border`) + ícones lucide — nenhum `bg-(blue|red|green)-N` cru. Âncora: charter §6 "NUNCA usar cor crua (`bg-blue-100`) no lugar de token".
**Pronto quando:** `php artisan ui:lint --path=resources/js/Pages/kb` reporta 0 violações R1 (cor crua) e R3 (emoji) nos arquivos da V2 — hoje reporta 68 R1 (`NodeReader` 22 · `BlockRenderer` 18 · `NodeList` 8 · `HealthPanel` 8 · `TroubleshooterDialog` 8 · `KbFavStar` 2 · `CategorySidebar` 1 · `PathsDialog` 1).

> **Dono do contrato = mecanismo que JÁ existe** (`app/Console/Commands/UiLintCommand.php`, ratchet **required** `UI Lint ratchet vs baseline (LEI)`; as 68 estão fotografadas em `config/ui-lint-baseline.json` — não pode PIORAR). Por isso este UC **NÃO** ganha teste próprio: seria régua paralela ao juiz consolidado (proibicoes.md §5, "gate redundante com régua consolidada"). Fechá-lo = decisão de design de [W] (D4 §7 do charter, gate visual ADR 0114, pendente desde 2026-05-16), não escolha de agente.

## UC-KBV2-13 — Drift doc↔código surfacado (5º quadrante + badge) · Fase #5
Status: 🧪 (`KbIndexV2ContractTest.php` V7 — payload carrega `code_drift_state` scopado; Tier 0 biz=99 não vaza. Aguarda run verde CT100. Lados client-side (b)/(c) = smoke/visual.)
O sinal `kb_nodes.code_drift_state` (veredito do `kb:drift-detector` — Fase A1/A2, PRs [#4715](https://github.com/wagnerra23/oimpresso.com/pull/4715)/[#4720](https://github.com/wagnerra23/oimpresso.com/pull/4720)) sai do log e aparece na tela: **(a)** 5º quadrante do `HealthPanel` — *"Cita código que sumiu no git"* — agrupa os nós com `code_drift_state != null`, mostrando o(s) path(s) quebrado(s) + quando foi detectado (`checked_at`); **(b)** badge *"cita código removido"* no header do `NodeReader` quando o nó aberto tem `refs`. Ambos só LEEM o que já vem no payload (`indexV2` serve `select *` → o campo já viaja; **nenhuma** mudança de Controller) — zero escrita, zero Job. Âncora: charter `Index.v2.charter.md` **v5** §4 Goal 11 + §6 Anti-hook *"NUNCA afirmar drift sem `checked_at`"* + §8.
**Pronto quando:** (a) o payload de biz=1 com nó driftado carrega `code_drift_state.refs[].path` **e** o nó driftado de biz=99 NÃO aparece (Tier 0 — V7, ADR 0093); (b) o quadrante só renderiza quando há drift (nunca *"— nenhum —"*, nunca afirma *"código ok"* — `null` = sem drift OU nunca checado); (c) o badge do `NodeReader` aparece só quando o nó aberto tem `refs`. (b)/(c) = client-side, cobertos por smoke/visual (jsdom não tem layout engine — dividido, não fingido).

## UC-KBV2-14 — O leitor mostra o CORPO do documento canônico (JOIN), sem torná-lo editável
Status: 🧪 (`Modules/KB/Tests/Feature/KbNodeBodyReaderTest.php` — L1 corpo via JOIN · L2 invariante intacta + PUT 422 · L3 Tier 0 biz=99 → 404 · L4 auth. Aguarda run verde CT100.)

Wagner abre um ADR na lista e **lê o documento inteiro** no painel direito — não o excerpt truncado. O corpo **não vem** de `kb_nodes`: o `KbBridgeFromMcpJob` mantém `body_blocks = NULL` + `is_editable = false` (invariante Tier 0, declarada 3× no job e validada pelo `KbNodeObserver` — [ADR 0061](../../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md): doc do git não vira editável no app, senão vira segunda fonte da verdade contra o repositório). O texto vem por **JOIN** com `mcp_memory_documents.content_md`, servido pelo endpoint que **já existia e ninguém chamava**: `GET /kb/nodes/{slug}` → `KbNodeController@show` (contrato em [`NodeReader.charter.md`](_components/NodeReader.charter.md) Goal 2). O que faltava era o **consumidor**: `NodeReader` renderizava um placeholder *"virá com Agent A (ONDA 1)"* — medido 2026-07-29, **21 de 21** menções a `/kb/nodes` em `resources/js/` eram comentário/doc, **zero** `fetch`. Agora `_lib/useKbNodeBody.ts` busca sob demanda e o `BridgeBody` renderiza markdown (GFM — o corpus é cheio de tabela). Rota **nova não foi criada**: um segundo endpoint pro mesmo dado seria 2º oráculo ([proibicoes.md §5](../../../memory/proibicoes.md), "duplica régua consolidada").

**Pronto quando:** (a) `GET /kb/nodes/{slug}` de nó bridgeado devolve `content_md` **igual** ao markdown do doc canônico (igualdade estrita — não "tem a chave", não o excerpt) + `github_url` apontando pro `git_path`; (b) depois de servir o corpo, o nó **continua** `body_blocks IS NULL` e `is_editable = 0` no banco, e `PUT /kb/nodes/{slug}` devolve **422 `NODE_NOT_EDITABLE`** sem alterar o título; (c) Tier 0 — atuando como biz=1, o corpo de um nó de **biz=99** devolve **404** e o conteúdo secreto não aparece na resposta ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)); (d) anônimo não recebe 200. Lados client-side (skeleton de carregamento · erro explícito · "documento sem corpo indexado" · link "Ver no GitHub") = smoke/visual — jsdom não tem layout engine, e o `fetch` é do browser.

### 🧾 Recibo — o endpoint já respondia; faltava o consumidor (medição em PRODUÇÃO)

| campo | valor |
|---|---|
| **sistema medido** | `https://oimpresso.com/kb/v2` + `GET /kb/nodes/{slug}`, sessão logada [W] (biz=1), via browser MCP |
| **quando** | 2026-07-29 |
| **o que a TELA mostrava** | placeholder literal *"Endpoint `/kb/nodes/…` completo virá com Agent A (ONDA 1)"* + **"Sem conteúdo ainda."** + excerpt de **403** chars |
| **o que o ENDPOINT respondia** | **HTTP 200** · `content_md` = **6.933 chars** (17× o excerpt) · `github_url` resolvido · `tem_tabela_gfm: true` |
| **invariante no mesmo payload** | `node.is_editable = false` · `node.body_blocks = null` — intacta |
| **console** | 0 erros |

> Duas leituras que este recibo trava: **(1)** o gap nunca foi de backend — era o `.tsx` não chamar o que já existia (por isso rota nova seria 2º oráculo); **(2)** o corpus real **tem tabela GFM**, então o renderer precisa de `remark-gfm` — o `SimpleMarkdown` de `@/Components/shared` (sem tabela) renderizaria a tabela como parágrafo. O recibo envelhece à vista: se a data incomodar, **re-rode**, não edite o número.

> ⚠️ **Efeito colateral consciente, declarado:** `KbNodeController@show` incrementa `reads_count` (é o contrato do `NodeReader.charter.md` Goal 2 — *"incrementa reads_count atomic"*). Abrir um **nó** escreve essa métrica; abrir a **tela** (`GET /kb/v2`) continua sendo leitura pura (UC-KBV2-03, intacto). Por isso o hook tem debounce de 250ms + `AbortController`: navegar com `j`/`k` não infla a métrica com nós que só passaram de raspão.

## Backlog — decidido, não construído (vira UC quando ganhar teste)

> **Por que estes NÃO são UCs numerados ainda** (padrão `[BACKLOG]` do projeto — [how-trabalhar.md](../../../memory/how-trabalhar.md) §"Pedido de tela"): um `## UC-XX` no casos.md é **contrato executável** e o `casos-gate` (required, ADR 0264 G-2) exige teste citando o id. Os três abaixo são **decididos por [W] e documentados no charter** (§4/§6/§7), mas o **código não existe** (indicador de empresa e filtro de categoria ainda não construídos; toasts esperam de-risk bloqueado). Escrever teste pra comportamento inexistente = teste vermelho ou tautológico. Então ficam **prosa visível sem gate** — viram `UC-KBV2-10/11/12` **no mesmo PR que entregar o código + teste**. Isto NÃO é esconder: a dívida está à vista, medida, com o file:line pronto pra quem for construir.

**[BACKLOG] Nenhuma ação afirma sucesso sem persistir** (charter §6 Anti-hook · de-risk §8-bis passo 3)
4 ações mock respondem `toast.success` de conclusão sem gravar nada: `Index.v2.tsx:322` `voteHelpful` → *"Voto registrado"* · `:326` `voteOutdated` · `:330` `reverify` → *"…marcado como fresco"* · `:334` `attachToOS`. Os honestos de referência: `toggleFav` (`:307`) **persiste** (UC-07); `summarizeAI` (`:338`) usa `toast.info('… em breve')` — o padrão certo. **Vira UC quando:** o de-risk (`toast.success`→`toast.info` sob `usingMock`) + o teste (spy no `toast`) landarem — hoje bloqueado (a tela é mock atrás do gate visual; [#4365](https://github.com/wagnerra23/oimpresso.com/pull/4365) foi fechado).

**[BACKLOG] NOVO-A — indicador da empresa ativa ao lado da busca** (charter §2-bis/§4 Goal 9 · decidido [W] 2026-07-17)
[W]: *"qual KB que o cliente está filtrando? isso deveria estar ao lado do buscar."* Ao lado da busca (`Index.v2.tsx:460`), **RÓTULO** "Buscando em: «empresa ativa»" — leitura, **não** seletor. O KB herda o tenant via `SetSessionData` → global scope de `KbNode`; a troca de empresa vive no `CompanyPicker` do rodapé da Sidebar (`resources/js/Components/cockpit/Sidebar.tsx` ~L342, campo `ativa`) — duplicar seria 2º oráculo. **Não é seletor de eixo** (medido: governança biz=1 `adr=498` não vaza pra biz=4 `adr=0`; ver charter §3 + UC-05 — não há eixo pra Larissa escolher). **Vira UC quando:** o componente ler `props.business.name` e o Controller injetar o nome da sessão + teste (`render` assere "Buscando em: {name}"; troca de business troca o rótulo).

**[BACKLOG] NOVO-B — categoria sem documento não aparece na lateral** (charter §4 Goal 10/§6 Anti-hook · decidido [W] 2026-07-17)
Categoria fantasma promete conteúdo que o multi-tenant nunca deixa aparecer. **Gap de código medido:** `CategorySidebar.tsx:204` faz `categories.map(renderCat)` **sem filtrar** — `renderCat` (`:101`) computa `count` mas não pula `count===0` no nível de categoria (só a **sub**categoria pula, `:158`). Correção: `categories.filter((c) => (countByCat[c.slug] ?? 0) > 0)` antes do `.map` ("Todas" sempre visível). **Vira UC quando:** a mudança de código + teste que **conta os `<li>` RENDERIZADOS** (não a prop — é a prova contra "tela vazia passa verde"): categoria X sem nó ausente do DOM, Y com nó presente. Seed em **biz=99** fictício.

---

> **Decisão pendente (metabolismo — charter §7):**
> `Index.v2` é **viva mas incompleta**: roteada por 2 caminhos com auth real (UC-01..04 verdes), porém **sem Controller** (`indexV2`) e **sem classificador `auto_match`** — roda mock-only. Dois bloqueadores medidos (charter §3, 2026-07-17): (1) **1.412 de 1.415 nós com `category_id` NULL** → filtro `n.category_id === cat.id` (`Index.v2.tsx:147`) vem vazio pra biz=1; (2) **`auto_match` tem ZERO leitores em PHP** (`KbBridgeFromMcpJob.fill()` não seta `category_id`; só seeders setam) → falta o **classificador**, gated na decisão **D6** (template de categorias por vertical — **ABERTA**, [W] decide). A equipe documenta o trio + especifica o classificador; **não** o implementa aqui.
>
> **Ordem de promoção (charter §8-bis, o dado antes do render):** (1) classificador `auto_match`→`category_id` + backfill dos 1.412 + fix `KbArticleService:49` (slug↔int) → (2) Controller `indexV2` **+ revogar UC-06 no mesmo commit** → (3) corrigir os 4 toasts (UC-10) → (4) cores→tokens (UC-09, D4) → (5) contratar no visreg 1× no estado final + F1.5 [W] + **R1 smoke real com screenshot** (a ordem termina em "abrir a tela e ver", não em "cores"). Enquanto indefinido, estes UCs blindam o piso da rota viva (auth + read-only + sem side-effects + Tier 0) e registram a dívida de dado real à vista.