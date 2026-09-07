# EXPORT MÓDULO FORJA — pacote completo pro Code

> **Pedido de [W] (2026-09-03):** *"exporte módulo Forja"*. Este é o pacote inteiro: leis que valem, ancoragem dupla por seção, **alvo medido** de todas as 6 views, contrato de comportamento, DoD e placar. Um PR por seção.
> **Método:** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` (mesmo diretório). **Ponte** — destino `prototipo-ui/` (root), nunca `cowork/`. **Eu não commito**: desce por `cowork-inbox`/Issue → PR.
> **Medição:** app do espelho servido, tema **dark**, após `__oiLazyDone` e **duas leituras iguais** de `querySelectorAll('*').length` (1491/1491 na lista · 1030/1030 no quadro). Todo número abaixo é `getComputedStyle`/`children`, não leitura de código.

---

## 0 · Leis que este pacote NÃO renegocia

1. **Réplica primeiro** (ADR 0388 + [W] 2026-09-02): o protótipo é o contrato de **layout**. Divergir é bug, salvo divergência declarada.
2. **Ancoragem dupla** ([W] 2026-09-03): o **alvo** vem do protótipo; a **âncora de implementação** é o arquivo real do `main`, reusando os átomos que já existem lá (eles têm `aria-*`/`data-testid` que o protótipo não tem).
3. **1 seção = 1 PR ≤300 linhas de prosa** (CSS/JSX copiado não conta). Big-bang de `casos.md` proibido (`casos-gate` G-2).
4. **Escrita é PROPOSTA**; sem fonte no banco ⇒ `—` + linha no PR. **Nunca número inventado.**
5. **Zero CSS novo** — `cowork-forja-bundle.css` já cobre `fj-*`/`ap-*`/`tf-*`. Zero utilitária Tailwind de cor/espaço no que o bundle cobre.
6. **Nada é "0 bug" antes do T7** (`design-diff --compare --check` nos dois renders, prod deployada e autenticada).

---

## 1 · Ordem de execução

| # | seção | âncora no `main` | depende |
|---|---|---|---|
| **0a** | **a11y do alvo** — corrigido **no build daqui**, não é PR do Code | — | — |
| **1** | shell/header + topnav (6 destinos · 3 grupos) | `team-mcp/Forja/Cockpit.tsx` + `ForjaHub.tsx` + `ForjaTabBar.tsx` | — |
| **2** | Trabalho · chrome (frentebar · KPI · toolbar · filterbar) | `Forja/Trabalho/Index.tsx` | 1 |
| **3** | Trabalho · **lista** (grupo + linha + totalbar) | `_components/TrabalhoLista.tsx` + `trabalhoAtomos.tsx` | 2 |
| **4** | Trabalho · **quadro** (2 eixos) | `_components/TrabalhoQuadro.tsx` | 2 |
| **5** | Trabalho · **gantt** | `Forja/Roadmap/Gantt.tsx` | 2 |
| **6** | **Aprovações** (landing) | `Forja/Aprovacoes/Index.tsx` | 1 |
| **7** | **Saúde** | `team-mcp/Forja/_components/ForjaSaude.tsx` + `team-mcp/Scorecard/Index.tsx` | 1 |
| **8** | **MCP + Handoffs** | `_components/ForjaMcp.tsx` + `ForjaHandoffs.tsx` | 1 |
| **9** | **Changelog** | `_components/ForjaChangelog.tsx` | 1 |
| **10** | **Integrador** | `_components/ForjaIntegrador.tsx` | 1 |
| **11** | Triagem (aba `/forja`) | `_components/ForjaTriage.tsx` | 6 |

**Sem receptor no `main` hoje — construção, não re-skin (declarar, não inventar):** `forja-issue-drawer` · `forja-cmdk` (⌘K + `?`) · `forja-notifs` · `forja-novo-issue` · `forja-runbook` · `forja-handoff` · `forja-ia` · `forja-rag`. Cada um vira onda própria **depois** de [W] declarar o receptor.

---

## 1-bis · Instrução de execução por onda — a forma padrão

**Sim: fica ancorado no código de produção, e a instrução é sempre esta forma.** Cada onda abre com este bloco preenchido; o que não foi lido no turno **não** entra preenchido (regra "não verifiquei").

```
ONDA <n> — <seção>
  ARQUIVOS A EDITAR   : <caminho:componente>  (1 a 3 arquivos, nada além)
  REUSAR (não recriar): <átomos/serviços que já existem lá>
  CRIAR               : <só o que não existe — nomear>
  NÃO TOCAR           : <arquivos vizinhos, shell, CSS>
  PASSO A PASSO       : 1) … 2) … 3) …   (ordem em que o diff nasce)
  DADO                : <Service/coluna real por slot>
  PARAR SE            : <condição que exige [W]: dado inexistente, decisão aberta>
```

### Exemplo preenchido — ONDA 3 · Trabalho · lista (a única lida no turno)
```
ARQUIVOS A EDITAR   : Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoLista.tsx
                      (+ trabalhoAtomos.tsx SÓ se faltar átomo; tokens em trabalhoTokens.ts)
REUSAR (não recriar): PrioDot · TypeChip · PhaseBadge · StatusPill · OwnerSeal · LockIco ·
                      VincChip · Star · Pin · GroupChevron  — já com aria-pressed /
                      aria-hidden / role=img / data-testid. Recriar seria regredir a11y.
CRIAR               : nada de átomo novo nesta onda.
NÃO TOCAR           : Index.tsx (chrome = Onda 2) · TrabalhoQuadro.tsx (Onda 4) ·
                      bundle CSS (Onda 1) · shell.
PASSO A PASSO       : 1) conferir a ordem dos slots da .fj-row contra §3.3 (13 no protótipo,
                         11 entregáveis) — ordem é parte do alvo, não detalhe;
                      2) manter os 2 ausentes DECLARADOS (fj-rowcheck, fj-fresco) e não
                         renderizar placeholder no lugar;
                      3) .fj-totalbar com 6 filhos e gap 14px (§3.3);
                      4) .fj-group-head: toggle com aria-expanded + contagem;
                      5) casos.md da seção com ≥1 UC por comportamento do §4;
                      6) regenerar design-spec.json por máquina; escrever o PLACAR.
DADO                : display_id · title · priority · forja_tipo??type · estimate_h /
                      story_points · blocked_by (máx 2) · module · is_blocked ·
                      forja_fase??status · forja_papel??owner · fav/pin do viewer.
PARAR SE            : pedirem o checkbox de seleção (exige mutação em massa sem endpoint —
                      afordância falsa, LC-15) ou o chevron de épico (epic_id → McpEpic,
                      outra entidade: a hierarquia do protótipo não existe aqui).
```

> Para as ondas 1, 2, 4-11 a **âncora está declarada** no §1 e o alvo no §3; o passo-a-passo se preenche **ao abrir a onda**, relendo os 2-4 arquivos daquela seção no `main` — é o §3-bis do protocolo (`main` responde *onde* e *com que dado*; o protótipo responde *como*). Preencher agora, sem ler, seria inventar o interior de arquivo que não abri.

---


## 2 · Onda 0a — a11y do ALVO (o protótipo não é certificado de a11y)

Medido no protótipo em 2026-09-03. **Estes são defeitos MEUS**: pedir ao Code que replique é exportar dívida. Corrijo no build; o Code **não** deve copiá-los.

| item | medido no protótipo | como a produção já resolve (reusar!) |
|---|---|---|
| `.fj-row` é `DIV` sem `role`/`tabindex` (23×) | 🔴 | — (corrigir dos dois lados) |
| `svg` em clicável sem `aria-hidden`/nome: **66 de 66** | 🔴 | `trabalhoAtomos.tsx` já usa `aria-hidden` + `role="img" aria-label` no cadeado |
| drawer sem `role`/`aria-modal`, foco fica no `BODY` | 🔴 | — |
| topnav: `aria-selected/pressed/current` em **0 de 6** | 🔴 | `Star`/`Pin` da produção já usam `aria-pressed`; `fj-group-toggle` já usa `aria-expanded` |
| `[aria-live]` = **0** no documento | 🔴 | — |
| contraste AA: `.fj-id` **3,02** · `.tf-kpi-l` **3,18** · `.fj-groupby-lbl` **3,94** · `.fj-fresco` **3,94** (ok: `.fj-title` 10,84 · `.fj-group-title` 13,02 · `.fj-gb-btn` 4,85) | 🔴 tokens de texto pequeno | — |
| alvo de toque: **81 de 118** botões < 24×24 (`fj-rowcheck` 16 · `pin`/`star` 22) | ⚪ **decisão [W]**: ERP denso 1280 × WCAG 2.2 2.5.8 | — |
| `outline:none/0` nas folhas: **139** × `:focus-visible`: **57** | 🟠 auditar por seção | — |
| arraste (quadro, 5 arrastáveis) sem alternativa por teclado; `aria-keyshortcuts` = 0 | 🟠 é o gap da 0367 | — |
| indicador só por cor: 23 `.fj-prio-dot`, **0** sem título | ✅ | idem na produção (`title` no `PrioDot`) |
| estado vazio real ("Nenhum issue casa com o filtro." + ação) | ✅ | `.fj-empty` já existe em `TrabalhoLista.tsx` |

---

## 3 · ALVO MEDIDO — por view e seção

### 3.1 Shell (Onda 1)
`.os-page-h` → 2 zonas `[os-page-h-l, os-page-h-r]` · direita na ordem **`[fj-bell, fj-kbtn, fj-viewtabs, os-btn]`** · `.fj-viewtabs` = **6 destinos** em **3** `.fj-navgroup` (Trabalho: Aprovações·Trabalho · Esteira: Saúde·MCP · Histórico: Changelog·Integrador) · badge de pendências no destino Aprovações · `--accent` **dark = `oklch(0.70 0.15 295)`** (não o `0.55` do light).

### 3.2 Trabalho · chrome (Onda 2)
| seção | alvo |
|---|---|
| `.fj-frentebar` | 2 filhos: segmented (Lista·Quadro·Gantt, `role=tablist`, **3 de 3** com `aria-selected`) + nota mono com a contagem de `mcp_tasks` |
| `.fj-kpirow` | **5** filhos · `gap 10px` · KPI é **`BUTTON`** · valor **17px** · rótulo **10px** · `text-align: left` · clique **filtra lista E quadro** |
| `.fj-toolbar` | **4** filhos · `gap 14px` · `padding 11px 18px` · **18** `.fj-gb-btn` (agrupar · ordem · densidade · favoritos) |
| `.fj-filterbar2` | **9** filhos base (12 com visões salvas) · `gap 6px` · 8 chips de papel |

### 3.3 Trabalho · lista (Onda 3)
- `.fj-group-head` → 2 filhos (toggle com chevron·título·contagem) · título **11px** · **5 grupos** no estado medido.
- **`.fj-row` → 13 filhos, NESTA ordem:** `fj-rowcheck`(BUTTON) · `fj-row-indent` · `fj-prio-dot` · `fj-id` · `fj-type` · `fj-title` · `fj-tam` · `fj-row-mid` · `fj-fresco` · `fj-exec` · `fj-role` · `fj-pin`(BUTTON) · `fj-star`(BUTTON) · altura **34px** (28px compacta) · `font-size 13px` · id em mono 11,5px.
- `.fj-totalbar` → `display:flex` · **6** filhos · `gap 14px`.
- **Ausências já declaradas na produção (mantê-las declaradas):** `fj-rowcheck` (mutação em massa sem endpoint — afordância falsa), `fj-fresco` (campo inexistente em `mcp_tasks`), `carry` (sem histórico de ondas), épico (`epic_id` → `McpEpic`, outra entidade). Placar vigente: **11 de 13**.

### 3.4 Trabalho · quadro (Onda 4)
`.fj-quadro-wrap` → `display:flex`, 2 filhos `[fj-quadro-ancora, fj-kanban]` · **o scroller é o `.fj-kanban`** (`overflow-x:auto`, `scrollWidth 1580 > clientWidth 649`) — o wrap é `overflow:hidden` **de propósito** · **6** `.fj-kcol` de **248px**, cada uma com `fj-kcol-head` (dot·label·count·quem·faz·sai) · card **`.fj-kc` = `DIV[draggable=true]`, 3 filhos na ordem `[fj-kc-top, fj-kc-title, fj-kc-foot]`**, altura 111px, 13,5px · eixo alternável (pipeline de telas × execução).

### 3.5 Trabalho · gantt (Onda 5)
`.fj-gantt` → 4 filhos `[fj-quadro-ancora, fj-g-scale, fj-g-body, fj-totalbar]` · `.fj-g-row` = **32** linhas, 2 filhos `[fj-g-lbl, fj-g-track]` · **33** `fj-g-lbl` · **192** `fj-g-fds` (faixas de fim de semana) · **32** `fj-g-bar` · rodapé `.fj-totalbar` com 6 filhos, incluindo `fj-total-warn` + **3** `fj-g-leg` + `fj-total-hint` · âncora `.fj-quadro-ancora` 12px `oklch(0.58 0.005 90)` = `--text-mute`.
⚠️ O **corpo** do gantt é decisão [W] em aberto: o protótipo desenha `.fj-g-*` à mão, a tela usa `@svar-ui/react-gantt`. Trocar o motor custa as 163 dependências que hoje viram setas.

### 3.6 Aprovações (Onda 6 · landing)
`.ap-page` → 4 filhos na ordem **`[ap-head, ap-vivo, ap-mesa, fj-mcp-card]`** (herói · faixa "Ao vivo no MCP" · mesa · placar) · `.ap-mesa` → 2 filhos `[ap-fila, ap-painel]` · fila com **7** itens · `.ap-item` → 3 filhos `[ap-av, ap-item-tx, ap-espera]` · `.ap-item-top` → 3 filhos (título nu · `ap-nivel` · `ap-tipo`) · **11** `.ap-nivel` · **8** `.ap-tipo`.

### 3.7 Saúde (Onda 7)
`.fj-saude` → 5 filhos `[fj-mcp-intro, fj-saude-grid, fj-mcp-card ×3]` · **4** `.fj-metric`, cada uma 3 filhos `[fj-metric-top, fj-metric-mid, fj-metric-foot]` · **4** `.fj-spark` · **7** `.fj-wip-col` · **7** `.fj-gate` (1 filho `fj-gate-dot`) · `.fj-rules` com 3 filhos nus (toggles de automação).

### 3.8 MCP + Handoffs (Onda 8)
`.fj-mcp` → 4 filhos na ordem `[fj-mcp-intro, fj-mcp-card (Handoffs F1→F3), fj-mcp-grid (contrato | tokens), fj-mcp-card (auditoria)]` · `.fj-ho-list` = **6** `.fj-ho-item` · **6** `.fj-ho-tab` (todas/pendente/aplicado/mergeado/bloqueado/parado, com contador) · **9** `.fj-perm` · **9** `.fj-role` · `.fj-audit` com 6 linhas, 2 marcadas `deny`.
⚠️ `.mono` é classe do **shell do protótipo** e **não existe em produção** — desce escopada (causa-raiz do D4 já medida).

### 3.9 Changelog (Onda 9)
`.fj-changelog` → 2 filhos `[fj-clog-tabs, fj-feed]` · **8** `.fj-feed-item`, cada um **2 filhos** `[fj-feed-dot, fj-feed-body]` · `.fj-feed-top` → `[fj-feed-ref, fj-feed-when]` · corpo em 3 blocos (ref/when · resumo · flags/módulos) · segmentos `Tudo · PRs · ADRs · Sessões · Ondas`.

### 3.10 Integrador (Onda 10)
`.fj-integra` → 4 filhos `[fj-int-verdict, ds-tabbar, fj-int-table, fj-int-foot]` · **9** `.fj-int-row` de **4 células nuas** (Forja · rota · aba · estado + ação) · **8** `.fj-int-tab`.

---

## 4 · Contrato de COMPORTAMENTO

**Invariantes (valem em todas as seções, não repetir):** escrita é proposta · filtro reversível (clicar no ativo desliga) · `stopPropagation` em clique aninhado · `esc` fecha **um** nível · teclado escopado à seção montada (`j/k/x/p`) e global só `⌘K`/`?` · persistência com chave declarada · estado vazio diz por que e o que fazer · `focus-visible` accent em tudo clicável · marcador de rede sobrevive ao clique · sem número inventado.

**Linhas medidas (as que o Code precisa reproduzir):**

| elemento | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|
| `.fj-row` | clique · `↵`/`e` | abre o drawer único do issue | não | `esc` fecha | drawer + 5 `.fj-phase` |
| `.fj-rowcheck` | clique-no-filho · `x` | entra na seleção ⇒ `.fj-bulkbar` com contador | não | clique desmarca | `x` ⇒ 1 `.fj-bulkbar` |
| `.fj-star` | clique-no-filho | favorita; alimenta o filtro "favoritos" | `oimpresso.forja.fav` | sim | reload mantém |
| `.fj-pin` | clique-no-filho · `p` | fixa no topo do grupo, furando o rank | `oimpresso.forja.pin` | sim | 1º do grupo |
| `.tf-kpi` (BUTTON) | clique | filtra **lista e quadro** | não | clique desliga | contagem cai |
| segmented Lista/Quadro/Gantt | clique · tecla `v` cicla | troca a sub-visão | `oimpresso.forja.trabvis` | sim | `aria-selected` migra |
| `.fj-group-toggle` | clique | colapsa o grupo | não | sim | `aria-expanded` |
| `.fj-kc` | clique | abre o drawer | não | `esc` | medido: abre e fecha |
| `.fj-kc` | **arrasto** p/ outra `.fj-kcol` | move fase/status **como proposta** | não | atividade registra | ⚠️ falta alternativa por teclado (`E`/`A`) — gap 0367 |
| barra de busca | `/` ou `c` foca · gramática `is: @ ~ tipo: mod:` | filtra; `tab` completa a sugestão | não | limpar volta 23 linhas | medido: `zzzz` ⇒ `.fj-empty`, volta a 23 |
| view do topnav | clique | troca a view | `oimpresso.forja.view` | sim | 6 destinos medidos |

---

## 5 · B · NÃO INVENTAR

- **CSS:** `resources/css/cowork-forja-bundle.css` (já no chão pela Onda 1). Zero CSS novo. `oklch()` inline por **hue calculado** (prioridade/tipo/fase/status) é divergência **já declarada** em `INCONSISTENCIAS-replica.md` — não trocar por token.
- **Átomos:** reusar `_components/trabalhoAtomos.tsx` (`PrioDot`, `TypeChip`, `PhaseBadge`, `StatusPill`, `RoleBadge`, `OwnerSeal`, `LockIco`, `VincChip`, `Star`, `Pin`, `GroupChevron`) — **não recriar**: já têm `aria-pressed`/`aria-hidden`/`data-testid`.
- **Dados:** `mcp_tasks` + `TrabalhoService` (agentes vêm da **allowlist** `mcp_actors type=ai_agent`, nunca de padrão no nome) · `ForjaMcpService` (MCP/handoffs) · `ForjaChangelogService` · `ForjaSaudeService` · `ForjaAprovacoesService` · `RoadmapGanttController` (`MAX_TASKS=500`, filtro por cycle).
- **Copy:** literal do protótipo, PT-BR, sentence case. Números `R$ 1.234,56`, datas `dd/mm/aaaa`, hora `08:42` mono tabular.
- **Componentes canônicos:** `@/Components/ui` (o `Segmented` existe lá) · shell `AppShellV2` · sidebar dark-fixo (UI-0023).

---

## 6 · DoD por onda + placar

1. contagem **e ordem** = §3 da seção · 2. cada linha do §4 com teste que a prova · 3. `design-diff --compare --check` = 0 `DIVERGE(bug)` · 4. screenshot prod autenticado dark 1280 · 5. `casos.md` da seção com ≥1 UC citado por teste (mesmo PR) · 6. **placar** · 7. bloco de contrato no charter (mesmo PR) · 8. `design-spec.json` regenerado por `scripts/design-spec-gen.mjs` (derivado — não editar à mão) · 9. `github.md` com a linha do ciclo.

```
PLACAR — <seção>
entregue X de Y elementos do alvo
ausentes: <classe> — <motivo: sem endpoint | campo inexistente | decisão [W]>
divergências declaradas: <item> — <motivo>
```

---

## 7 · O que NÃO foi medido (declarado, não silenciado)

- **Estados de hover/focus/disabled** de cada átomo: medi presença de regra, não o valor por estado. Onda que tocar a seção mede.
- **Aprovações, Saúde, MCP, Changelog, Integrador**: medi a **estrutura** (filhos e ordem) e as contagens; **não** medi tipografia/gap por seção como fiz na lista/quadro.
- **1280px**: a medição rodou a **924px** de viewport. O `PARIDADE` §11 registra que a 1280 o shell do protótipo vira rail 56px e o header quebra em 3 linhas — **fundação**, fora do escopo da Forja.
- **Fidelidade final**: nenhum número aqui autoriza "igual ao design". Isso é T7.

---

## 7-bis · O que a ANCORAGEM resolve — e o que ela não resolve

Resposta direta: a ancoragem entrega **a diferença de layout**, que é a maioria das ondas. Ela **não** entrega quatro classes de diferença — e nenhuma delas é falha do Code.

| classe de diferença | ancoragem resolve? | o que falta |
|---|---|---|
| **layout/estrutura/ordem/tokens** — ondas 1, 2, 3, 4, 6, 7, 8, 9, 10, 11 | ✅ **sim** — é diff em arquivo que o Code mantém, com átomo pronto | nada além do PR |
| **dado que não existe** — `fj-fresco` (carimbo de verificação vs `main`), `carry ×N` (histórico de ondas), seleção em massa (`fj-rowcheck` sem endpoint), hierarquia de épico (`epic_id` → `McpEpic`) | ❌ **não** | backend novo ou decisão de escopo. Hoje são **ausências declaradas** — placar 11 de 13, sem placeholder |
| **superfície sem receptor** — `issue-drawer` · `cmdk` · `notifs` · `novo-issue` · `runbook` · `handoff` · `ia` · `rag` (**8 arquivos do build**) | ❌ **não** — é **construção**, não re-skin | [W] declarar receptor e rota. `notifs` esbarra na 0367 D5 (Inbox morto sem receptor); `ia`/`rag` exigem backend de IA real |
| **decisão [W] aberta** — corpo do gantt (`@svar-ui/react-gantt` × `.fj-g-*` à mão, 163 dependências viram setas) · alvo de toque (81 de 118 botões < 24×24) | ❌ **não** | 2 decisões suas; até então o Code entrega só o que está fora do motor |
| **afirmar que ficou igual** (T7) | ❌ **não** | snapshot do `--compare` **órfão** nesta área · **E2E = 0** na Forja · alvo ainda não serializado no `design-spec.json` |

**Leitura honesta:** com a ancoragem, o Code consegue fechar **as 10 ondas de layout**; as diferenças restantes são **4 slots de dado**, **8 superfícies a construir**, **2 decisões suas** e **1 verificação a desbloquear**. Isso é o placar do módulo — e é ele que o `PLACAR Forja` soma, onda a onda, em vez de "quase pronto".

---


## 8 · Recibo do ciclo (o que o Code audita ao abrir)

- Build: **17 arquivos** `forja-*.jsx` + `forja-page.css` no `prototipo-ui/cowork/` (1 arquivo por tela, Ondas 1-3 de 2026-09-03).
- ⚠️ **Todos abaixo de ~48 KB** ⇒ a rota avulsa `get_file` **não serve**; a descida **exige o pacote**:
  `node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json`
  **Não roda do lado do agente** (ADR 0374) — logo **não afirmo que regenerei**.
- Órfãos do build declarados: `FjTriagemView` e `ForjaTarefas` (nenhum ponto de render os monta).
