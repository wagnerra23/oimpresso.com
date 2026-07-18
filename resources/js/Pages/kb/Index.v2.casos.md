---
casos: KB Unificado V2 (tri-pane) — DADO REAL · /kb/v2 + /sops
irmaos: Index.v2.charter.md (lei · v4) · Index.charter.md (V3 atual, coexiste)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
status_tela: viva-mock (roteada /kb/v2 + /sops com auth real; render mock-only — Controller indexV2 + classificador auto_match pendentes; D6 aberta)
last_run: "2026-07-17"
---

# Casos de uso — /kb/v2 (KB Unificado tri-pane · DADO REAL)

> **Status por UC:** ✅ passa (provado por teste verde) · 🧪 em teste (Pest/Vitest escrito ou a criar, aguarda run verde CT100) · ⬜ não verificado · ❌ quebrou (medido) · ⚠️ contrato pendente de decisão/backend.

> Derivados do charter `Index.v2.charter.md` **v4** (Goals §4 + Non-Goals §5 + Anti-hooks §6 + "Como se prova" §8) + protótipo Cowork `kb-page.jsx`. Persona principal: Wagner / governança (1440px). Secundária: Larissa / balcão (1280px).
>
> **Onde os números vivem (lei "fato derivado não se restateia" — proibicoes.md §5, 2026-07-17):** este contrato **NÃO guarda contagem de acervo**. O dono do número é `kb_nodes` (a query), e o recibo datado mora no **charter §3** (medição 2026-07-17). Quando um critério precisa de um fato medido, ele aponta pra lá ou carrega recibo próprio (query + resultado + data + sistema). Não invente aqui um total que o banco sabe melhor.

> **Contexto de maturidade (âncora honesta, medido 2026-07-17):** a rota `/kb/v2` (`kb.v2`) e o alias `/sops` (`sops.index`) são **closures inline** (`Modules/KB/Http/routes.php:42-44`) que fazem `Inertia::render('kb/Index.v2')` **sem props**. O Controller `KbController@indexV2` do charter **nunca foi implementado** → em prod a tela roda 100% em **modo mock** (`Index.v2.tsx:71` → `usingMock = !props.nodes` → sempre `true`). Os UC-01..06 blindam o **contrato da rota viva** (auth · render · read-only · sem side-effects · Tier 0). Os UC-07/08 travam **comportamento client-side** (localStorage · atalhos). Os UC-09/10/11/12 registram **dívida e comportamento de dado real** que vira teste forte quando o Controller + classificador `auto_match` chegarem (charter §8-bis).

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
Status: 🧪 (prova FORTE no MODELO — `Modules/KB/Tests/Feature/CrossTenantIsolationTest.php` R5, existe/verde; falta a asserção direcional de governança + o payload do Controller)

> **Reforço 2026-07-17 (era ⬜ "passa por construção"):** a V5 do `KbIndexV2ContractTest` (`:139-141`) confessa que passa *"por construção"* — render mock não serve dado, então biz=99 não aparece **porque nada aparece**. Isso não prova isolamento; prova tela vazia. A prova FORTE não depende do render: mora no **global scope de `KbNode`** (`BelongsToBusinessTrait::bootBelongsToBusinessTrait` → `addGlobalScope('business_id', ...)`, ADR 0093 Tier 0), e é medível **hoje** independente do Controller.

O KB é multi-tenant real. Duas direções, ancoradas em **fato medido** (recibo 2026-07-17, `kb_nodes` @ CT 100 `oimpresso-mcp`, biz=1; ver charter §3):
- **(a) Piso já provado no modelo:** nó de outro tenant não é lido. `CrossTenantIsolationTest.php` "blocks kb_node read across businesses (R5)" seeda nó em **biz=99** e prova `KbNode::all()->toHaveCount(0)` atuando como biz=1 (+ 404 no HTTP). **NUNCA biz=4** (ROTA LIVRE prod) — só biz=99 fictício.
- **(b) Direcional de governança (a criar):** o eixo `Governança` (`type = adr`) é 100% de biz=1. Fato de prod: **adr em biz=1 = 498 · adr em biz=4 = 0** (recibo 2026-07-17). O teste seeda `type=adr` em biz=1, atua como **biz=99** e prova `KbNode::where('type','adr')->count() === 0` — Larissa nunca enxerga um ADR do projeto. (A medição real usa biz=4; o teste usa biz=99 pra respeitar ADR 0101.)
- **(c) Payload (pendente Controller):** quando `indexV2` chegar, a V5 do `KbIndexV2ContractTest` vira a prova de que o payload servido a biz=1 **não contém** o slug/título de biz=99 — a asserção `missing('nodes.data')` de hoje passa a morder um payload scopado de verdade.

**Pronto quando:** (a) `KbNode::all()` atuando como biz=1 não inclui nó seedado em biz=99 (verde); (b) atuando como biz=99, `KbNode::where('type','adr')->count()` é 0 com ADR seedado só em biz=1; (c) com Controller vivo, o payload de biz=1 não contém slug/título de biz=99.

## UC-KBV2-06 — Fallback mock declarado — ⚰️ **MORRE junto com o Controller**
Status: 🧪 (`KbIndexV2ContractTest.php` — V6, render OK sem props) · **marcado pra revogação**

> ⚰️ **Este UC é dívida da era-mock e SAI no mesmo commit que entrega o Controller `indexV2` (charter §8-bis passo 2). NÃO apagar antes.** A V6 asserta `missing('nodes')` — enquanto a closure não passa props, isso protege contra "prop undefined". Mas no instante em que o Controller injetar `nodes`, **este mesmo teste deixa o CI vermelho por ter funcionado**. Revogá-lo cedo (antes do Controller) reabre o buraco; revogá-lo tarde (depois) trava a promoção. A regra é: **um PR, um commit** — Controller entra, UC-06 sai.

Enquanto `KbController@indexV2` não existir, a tela renderiza com `MOCK_NODES`/`MOCK_CATEGORIES` (`Index.v2.tsx:45-50, 76`) sem 500. Âncora: charter §8-bis + `usingMock = !props.nodes`.
**Pronto quando (só até o Controller chegar):** GET `/kb/v2` autenticado responde 200 mesmo sem nenhuma prop passada pela closure (sem exceção de "prop undefined").

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