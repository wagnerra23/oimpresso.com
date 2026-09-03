---
id: requisitos-team-mcp-forja-cockpit-visual-comparison
slug: teammcp-forja-cockpit-visual-comparison
title: "TeamMcp — Comparativo visual do cockpit Forja (6 abas + projeção do git)"
type: visual-comparison
module: TeamMcp
status: approved
date: "2026-06-16"
approved_by: wagner
approved_at: "2026-06-16"
canon_reference: os-page.jsx
blade_source: "N/A — tela nova (cockpit do cowork loop, sem legacy Blade)"
inertia_target: Modules/Forja/Resources/js/Pages/team-mcp/Forja/*.tsx
---

# Forja — cockpit do cowork loop (F1.5 · referência aprovada)

> **Referência visual aprovada:** protótipos Cowork `forja-*.jsx` colados por Wagner em 2026-06-16
> (estado atual + 6 telas "esperado": Triagem, Backlog, Quadro, Changelog, MCP, Saúde).
> Status `approved` = Wagner forneceu **e aprovou os screenshots** (gate F1.5 ADR 0114 satisfeito
> pela entrega do design — não há 2ª iteração pendente).
> **Mora em `Modules/TeamMcp`** (kickoff: "absorção em TeamMcp, NÃO é módulo novo"). Sidebar "Forja" +
> topnav próprio de 6 abas, ao lado da entry Equipe/Team existente.

## O conceito (o que a Forja É)

Cockpit de **observabilidade + governança do próprio loop de desenvolvimento** (humano ↔ agente).
Não inventa dados: **projeta** o que já existe — `mcp_tasks` (issues), git/PRs/ADRs/sessões (changelog),
baselines de gate + `memory-health` (saúde). Header fixo: breadcrumb `DESENVOLVIMENTO · MCP · PROJEÇÃO DO GIT`,
título **Forja**, subtítulo _"Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente)."_

### Taxonomia do loop (núcleo, transversal às abas)

- **Fases (F0→F4):** F0 Brief → F1 Design → F1.5 Critique → F2 Screenshot → F3 Code → F3.5 A11y → F4 (merged).
- **Papéis/atores (selo):** `[W]` Wagner (humano · aprova) · `[W2]` 2º gate humano (merge) · `[CC]` Claude Code ·
  `[CD]` Claude Design · `[CL]` Claude (loop) · `[CA]` · `[AN]` Analista. **Humano vs agente** é cidadão de 1ª classe.
- **Onda:** agrupador de épico/lote (SEM ONDA, V1.1, FA-1, FA-2, Q1…).
- **Contrato de permissão (soberania — kickoff):** agente default = `read + propose`; **`git.merge` só [W2]**,
  **`constituicao.edit` (ADR/PROTOCOL/BRIEFING) só [W]** — negados no **contrato**, não por convenção.

## Fundações DS v6 (herdadas · imutáveis)

| Token | Uso na Forja |
|---|---|
| Roxo canon `oklch(0.55 0.15 295)` | primário: aba ativa, **+ Novo issue**, **Analisar**, **Aprovar → backlog** |
| Status Stripe-dot (sem bg-fill) | dot colorido por fase/tipo/changelog (sem pílula preenchida de cor crua) |
| `--fs-1..9` / `--sh-1/2` | tipografia numérica (KPIs, contadores) + sombras sutis |
| `tabular-nums` | contadores de aba, KPIs Saúde, contagem por grupo |
| Componentes `Components/ui/*` + `Components/layout/*` | zero select/checkbox nativo, layout via `Inline/Stack/Grid` |
| Drawer lateral | dossiê do Analista (reusa padrão `TriageDossier` já shipado em ProjectMgmt) |

## As 6 abas (layout + dados + decisões)

### 1. Triagem — `F0 formalizado`
- **Texto-âncora:** _"Tickets propostos aguardando o analista [AN] enriquecer e sua aprovação. Entram no backlog só depois."_
- **Linha:** ID `FORJA-152` · badge de **tipo** (Tela=roxo · Bug=âmbar · Refino=azul) · título · selo módulo (KB/Financeiro/Atendimento) · selo ator `[CC]` · botão **Analisar** (roxo).
- **Ação Analisar:** abre **dossiê lateral** (reusa o padrão Analista de ProjectMgmt: valor×esforço, risco Tier-0, duplicatas, Aprovar→backlog / Rejeitar). Badge na aba = nº de propostas (3).
- **Dado:** `mcp_tasks` project=FORJA em estado proposto (sem onda/sem aprovação).

### 2. Backlog — agrupável
- **Controles:** `AGRUPAR [Onda|Fase|Papel|Prioridade|Módulo]` · ☆favoritos · filtro **Papéis** `[todos·W·CC·CD·CL·CA·AN·W2]` · **VISÕES + salvar visão** · busca `is:p0 @CL ~FA-1 tipo:bug` · **+ Perguntar**.
- **Grupos (ex. por Onda):** SEM ONDA (7) · V1.1 · PROTOCOLO V1.1 (1) · FA-1 (1) · FA-2 (1) · Q1 · G-3 E2E REQUIRED (1).
- **Colunas/linha:** ID · tipo · título · refs (`ADR 0235`, `SES …`, `PR #…`) · módulo · `sync Nd` · **fase** (`F0 Brief`/`F1 Design`/`F3 Code`) · selo ator · ☆. Pills: `⚠ inferido` (âmbar), `✓ @main` (verde).
- **Rodapé:** `11 issues · 2 P0 · 1 bloqueados · 4 não-verificados`. Atalhos `j/k navegar · Enter abrir · ⌘K buscar`.
- **Dado:** `mcp_tasks` project=FORJA; agrupador = campo derivado (onda/fase/papel mapeados de metadados existentes).

### 3. Quadro — board F0→F4
- **Colunas:** `F0 Brief (1)` · `F1 Design (5)` · `F1.5 Critique (0)` · `F2 Screenshot (0)` · `F3 Code (5)` · `F3.5 A11y (0)` (`F4` fora do board = merged).
- **Card:** ID · tipo · ☆ · título · selo ator · onda pill · `sync/⚠inferido/✓@main`. Drop zone vazia "arraste aqui".
- **Dado:** mesma projeção, agrupada por **fase**. Drag = `issue.transition` (PROPÕE → [W] aprova; ver contrato MCP).

### 4. Changelog — o que shippou
- **Filtros:** `Tudo · PRs · ADRs · Sessões · Ondas`.
- **Linha:** dot · ID (`#2417` / `ADR 0264 TIER-0` / `2026-06-12-produtos`) · título · selo ator `[CL]/[CC]` · módulo · data à direita.
- **Dado 100% real:** PRs (gh) + ADRs (`memory/decisions`) + sessões (`mcp_cc_sessions` / `memory/sessions`) + ondas.

### 5. MCP — contrato + tokens + auditoria  ·  **MOCKADO por design**
- **Banner:** _"Contrato e auditoria como **design** — o enforcement real é do servidor TeamMcp ([CL]). Default = read + propose; merge e constituicao.edit negados no contrato, não por convenção."_
- **Contrato de ferramentas:** `backlog.read` PERMITIDO · `changelog.read` PERMITIDO · `issue.transition` PROPÕE→[W] · `changelog.append` PROPÕE · `adr.propose` PROPÕE (nunca decisions/NNNN) · **`git.merge` NEGADO só [W2]** · **`constituicao.edit` NEGADO só [W]**.
- **Tokens ativos:** `frj_cc_live` (read+propose · exp 30d) · `frj_cl_ci` (read+propose · 90d) · `frj_cd_rev` (read · 30d) + **revogar**.
- **Auditoria (regra 6 mecanizada):** toda ação de agente — ts · ator · ação · detalhe · resultado (`ok` / `NEGADO — só [W2]`/`[W]`).
- **Dado:** read-only/mock — o enforce real é o servidor TeamMcp. **Token raw nunca persistido/logado** (Tier 0 ADR 0081).

### 6. Saúde — semáforo do loop
- **Texto-âncora:** _"Semáforo do loop, alimentado pelo que já existe (memory-health · baselines de gate · frescor). Cada métrica linka a uma ação — nada decorativo."_
- **KPIs:** Não-verificados `7` (meta 0) · Bloqueados `1` · P0 abertos `2` · Gates verdes `5/7` (ratchet só-desce). Cada card tem sparkline + link `ver →`.
- **Fluxo · WIP por fase:** barras F0=1 · F1=5 · F1.5=0 · F2=0 · F3=5 · F3.5=0 · F4=0. `8 entregas · 3 fresco · 3 atenção · 5 parado`.
- **Automação (toggles):** "Gate vermelho trava avanço de fase" (on) · "F1 exige ✓ lido @main antes de avançar" (on) · "PR merged → move issue p/ F4 (auto)" (off · requer #9).
- **Dado 100% real:** reusa `ScorecardBuilderService` + baselines de gate (`.foundation-guard-baseline.json` etc.) + `memory-health`.

## 15 dimensões (nível cockpit)

| # | Dimensão | Decisão Forja |
|---|---|---|
| 1 | Layout | Sidebar light + header canon (breadcrumb+título+subtítulo) + **topnav 6 abas** + corpo por aba |
| 2 | Hierarquia | 1 primária por aba (Novo issue / Analisar / Aprovar) · contadores tabular-nums |
| 3 | Densidade | linhas compactas (backlog/changelog) · cards arejados (quadro) |
| 4 | Iconografia | lucide (sem emoji) · selos de ator como pílulas `[XX]` monoespaçadas |
| 5 | Estados | empty ("Nada pra triar" / "arraste aqui") · inferido · bloqueado · @main |
| 6 | Atalhos | `⌘K` buscar · `j/k` navegar · `Enter` abrir · `Esc` fecha dossiê |
| 7 | Persistência | VISÕES salvas + agrupador via `localStorage oimpresso.forja.*` |
| 8 | Componentes shared | PageHeader canon · KpiCard · DataTable · drawer (TriageDossier) · ui/* |
| 9 | Tipografia num. | KPIs Saúde + contadores de aba/grupo em `--fs-*` + tabular-nums |
| 10 | Espaçamento | tokens de espaço canon (sem px crus) |
| 11 | Cor semântica | dot por fase/tipo/resultado · sem cor crua (gate conformance) |
| 12 | Microinterações | hover de linha · transição de aba · drag no quadro |
| 13 | Referência aprovada | screenshots Cowork 2026-06-16 (Wagner) |
| 14 | Benchmark | Linear (backlog/board) · Vercel (saúde) · GitHub (changelog) |
| 15 | Persona | Wagner [W] superadmin — decide/aprova/merge; agentes propõem |

## Sequência de entrega (onda · 1 PR por aba · cada uma fecha no gate visual)

| PR | Aba | Fonte | Dep. modelo de issue |
|---|---|---|---|
| A | Shell (sidebar + topnav + rota + landing) | — | não |
| B | Saúde | ScorecardBuilderService + gates + memory-health | não |
| C | Changelog | git/PRs + ADRs + sessões + ondas | não |
| D | Backlog agrupável | `mcp_tasks` project=FORJA | **sim** |
| E | Quadro F0→F4 | idem, por fase | **sim** |
| F | Triagem + dossiê | idem + padrão Analista | **sim** |
| G | MCP (contrato/tokens/auditoria) | read-only MOCKADO | não |

## Anti-regressões (Tier 0 · herdadas)

- ⛔ Token raw **nunca** persistido/logado ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)).
- ⛔ `git.merge` / `constituicao.edit` **negados no contrato** (só [W2]/[W]) — espelha a soberania do kickoff.
- ⛔ `mcp_*` repo-wide cross-tenant **por design** — sem `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- ⛔ Sem dado fantasma: valor×esforço/risco são **sugestão derivada** rotulada; nada inventado como medido.

## 2026-09-02 — primeira medição por SONDA (design-diff) nos dois renders · 5 pares

> **O que rodou (recibo, não afirmação):** espelho provado no dia (`cowork-mirror-freshness --snapshot-from` → `--compare --check --ledger`: 14 SYNC · 0 stale; shell `oimpresso.com.html` e `styles.css` estavam STALE e desceram; 4 adaptadores `cli-*` estavam **ausentes** no espelho e desceram — sem eles o protótipo quebrava em Trabalho e Integrador com `Element type is invalid`). Depois, a **mesma sonda** (`design-diff.mjs --probe`, papéis por seletor) injetada no protótipo servido (`prototipo-ui/cowork`, `oimpresso.route=teammcp`, tema dark, espera `__oiLazyDone` + 2 leituras estáveis) e na produção autenticada (`oimpresso.com`, tema dark), e `--compare prod.json design.json --check` por par. Canário: a sonda pegou nos 5 pares a divergência já conhecida do primary (0,55 × 0,70).
>
> **Limites desta rodada, declarados:** (1) o bundle do DS no snapshot local (`scripts/design-sync/mirror-snapshot/_ds_bundle.js`, sha `9d2f6ce4…`, = o do pacote de 24/08) publica **44** componentes; o vivo publica **55** (faltam `Segmented`, `Kebab`, `Toolbar*`, `Timeline`, `DataGrid`, `Widget`, `PresenterMode`). O `get_file` devolve o bundle vivo **TRUNCADO** (290 KB > 256 KiB), logo o `Segmented` que a Lista|Quadro|Gantt do protótipo usa (`window.CliSeg`) **não renderiza localmente** — o único transporte fiel é o pacote v2, cuja emissão não tem dono (lápide §5 2026-08-27). (2) D0 não rodou: não existe `prototipo-ui/contrato/forja*.contract.json` (decisão [W], já declarada no #6509). (3) O par Aprovações compara estado **vazio** em produção (0 na fila) com mock cheio no protótipo — o `kpi.count` 2×1 é diferença de composição, não de dado.

| par (prod × protótipo) | D2 layout | D4 tipografia | D6 cor | D8 estrutura | resumo |
|---|---|---|---|---|---|
| `/forja/trabalho` × `trabalho·lista` | ✗ filtros em **1** linha × **3** · linha com **3** colunas × **13** | ✗ KPI valor **22px** × **17px** | ✗ primary **0,55** × **0,70** | ✗ KPI é `DIV` × `BUTTON` (no protótipo o KPI **filtra** a lista) | a lista do protótipo é densa (id·tipo·título·tam·refs·frescor·exec·papel·pin·★); a de produção mostra 3 colunas |
| `/forja/aprovacoes` × `hoje` | ✗ 2 KpiCards × 1 número-herói | ✗ **22px** × **30px** | ✗ 0,55 × 0,70 | — | produção = 2 KpiCards + empty-state; protótipo = herói + faixa "Ao vivo no MCP" + fila + painel do artefato + placar da equipe (tabela 8 colunas) |
| `/team-mcp/scorecard` × `saude` | ✓ | ✗ **22px** × **28px** | ✗ 0,55 × 0,70 | ✓ | KPIs do protótipo têm sparkline + link `ver →` + meta; produção KpiCard canon |
| `/forja/mcp` × `mcp` | ✓ | ✗ col0/col2 sem mono × mono | ✗ col1/col2 herdam cor × cor própria · 0,55 × 0,70 | ✗ col2 `right` × `left` | protótipo tem o painel **Handoffs F1→F3** dentro do MCP (produção: rota `/forja/handoffs` separada) |
| `/forja/changelog` × `changelog` | ✗ linha com **5** colunas × **2** (dot + corpo com 5 blocos) | ✓ | ✗ 0,55 × 0,70 | — | produção projeta "Sessão Claude Code" sem título (parede idêntica); protótipo mostra ref · resumo · selo de ator · módulo · data |

**A camada que falta é a do DS de módulo, medido:** produção não tem `resources/css/cowork-forja-bundle.css` (Arquivos, Financeiro, Compras, PaymentGateway e Sells têm o seu); as 15 telas de `Modules/Forja/Resources/js/Pages/Forja/**` têm **0** ocorrências de `fj-`/`ap-`; o protótipo vive de `forja-page.css` (97 KB · 765 seletores `.fj-` + 76 `.ap-`). E o primary: `_generated-inertia-dark.css` já define `--color-primary: oklch(0.7 0.15 295)` no dark (emenda 2026-07-08, "fidelidade ao proto"), mas `PageHeaderPrimary.tsx:70` **fixa** `oklch(0.55 0.15 295)` inline — a divergência D6 dos 5 pares é o componente ignorando o token, não o protótipo fora do canon.

**Topnav** (medido em 2026-09-01 pelo #6537, não repetido aqui): 13 → 9 destinos em andamento; o protótipo tem 6 em 3 grupos-pílula.

**D1 (rede) em `/forja/trabalho`:** clicar o chip de ordenação (Prioridade → Vencimento → Título) fez **um GET Inertia** com a query da tela e o marcador `window.__marker` **sobreviveu** ao clique — navegação parcial, sem full-reload. OK.

## 2026-09-02 (tarde) — Onda 2 em produção: header/topnav medido com a MESMA sonda nos dois lados

> **Recibo.** Produção = `https://oimpresso.com/forja/aprovacoes` autenticado, deploy do merge `e1412acef3` ([#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553), run 33651538620 `success`, 16:00Z). Protótipo = espelho `prototipo-ui/cowork/oimpresso.com.html` servido por HTTP estático, `localStorage["oimpresso.route"]="teammcp"`, esperado `__oiLazyDone` + 2 leituras iguais (817/817). Mesmo Chrome, mesma viewport (2560), tema **dark nos dois**. Sonda: `getComputedStyle` + `getBoundingClientRect` — o que o browser resolveu, nunca a classe declarada. JSONs dos dois lados no PR da Onda 2.1. Smoke Infra Contract pós-deploy (sem sessão): `/forja/integrador` **404 → 302 → /login**; as 5 rotas irmãs seguem `302 → /login`.

| campo | protótipo | produção (Onda 2) | veredito |
|---|---|---|---|
| destinos no topnav · grupos | 6 · 3 | 6 · 3 | IGUAL |
| topnav na linha do `h1` (`.os-page-h-r`, 4 filhos: sino · busca · pílulas · primária) | sim | sim | IGUAL |
| pílula `.fj-navgroup` (bg · borda · radius · padding · gap) | `oklch(0.23 0.006 240)` · 1px `oklch(0.34 0.008 240)` · 8px · 2px · 2px | idêntico | IGUAL |
| rótulo de grupo (`display` · `letter-spacing` · `font-size` · caixa · cor) | block · +0,665px · 9,5px · uppercase · `oklch(0.58 0.005 90)` | idêntico | IGUAL |
| `h1` (22px/700/−0,55px/`oklch(0.94 0.005 90)`) · subtítulo (12,5px/17,5px, max-width 450px) | — | idêntico | IGUAL |
| botão do topnav (fs · padding · radius) | 12px · 5px 11px · 6px | idêntico | IGUAL |
| primária "Novo issue" (h · fs · texto branco) | 32px · 12,5px · `rgb(255,255,255)` | idêntico | IGUAL |
| largura do conteúdo do nav (cabe em 972px a 1280?) | 784,4px (sobra 188) | 749,4px (sobra 223) | IGUAL no critério; −35px vem de **dado** (badge de pendências do protótipo), não de CSS |
| **altura do botão** (`line-height`) | 25px (`normal`) | **28px** (`18px`, herdado do preflight Tailwind `button{line-height:inherit}`) | **DIVERGE (bug)** → Onda 2.1 |
| **padding do `.os-page-h`** · altura do header | `12px 24px 12px` · 88,4px | **`20px 18px 14px`** · **115,9px** | **DIVERGE (bug)** — a Onda 2 copiou o valor do `@media (max-width:1100px)` como se fosse o base (LC-08: lido do CSS, não medido) → Onda 2.1 |
| **`--accent` no dark** (pílula ativa · primária) | `oklch(0.70 0.15 295)` (shell `[data-theme="dark"]`, "VIDA 06-11") | **`oklch(0.55 0.15 295)`** (fundação de prod não tem o retune dark) | **DIVERGE (fundação)** — é o "0,55 × 0,70" de todas as rodadas; na Onda 2.1 a Forja recebe a família `--accent*` do protótipo **escopada** a `.fj-hub`/`.fj-page` (ADR 0388); reconciliar a fundação é decisão [W], listada em `INCONSISTENCIAS-replica.md` |
| `.fj-kbtn` (busca) altura | 31px | 33px | mesma causa do `line-height` → Onda 2.1 |

**Integrador (`/forja/integrador`, nasceu nesta onda):** protótipo = veredito + `<nav>` de 2 abas (**CliTabs do DS**: 13px, `0 14px`, ativa `oklch(0.94 0.005 90)` com borda inferior 2px `--accent` e fundo `oklch(0.32 0.06 295 / .5)`) + tabela de 9 linhas em grid de 4 colunas + rodapé. Produção usa `.fj-int-tabs` com `<button>` (versão anterior do próprio protótipo, declarada no header do `ForjaIntegrador.tsx`) — **compare 0-bug é a Onda 10**, não esta.

**O que a máquina NÃO viu e o olho viu:** nada desta vez — os 3 DIVERGE saíram da sonda antes do screenshot. O screenshot do header em prod (zoom 260→2560 × 0→130) confirma o layout do protótipo: título + subtítulo à esquerda; sino, `Buscar ⌘K`, 3 pílulas e "Novo issue" à direita, na mesma linha.

### Onda 2.1 em produção (deploy `a91ce0cd5c`, [#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563), 2026-09-02 ~16:45Z) — os 3 DIVERGE re-medidos

Mesma sonda, mesma viewport (2560), dark nos dois lados. Smoke pós-deploy: `/forja/aprovacoes` e `/forja/integrador` `302 → /login`.

| campo | protótipo | produção (Onda 2) | produção (Onda 2.1) | veredito |
|---|---|---|---|---|
| padding `.os-page-h` · altura do header | `12px 24px` · 88,4px | `20px 18px 14px` · 115,9px | **`12px 24px` · 88,4px** | **IGUAL** |
| botão do topnav (altura · `line-height`) | 25px · `normal` | 28px · `18px` | **25px · `normal`** | **IGUAL** |
| pílula ativa · primária (`--accent` dark) | `oklch(0.70 0.15 295)` | `oklch(0.55 …)` | **`oklch(0.7 0.15 295)`**, primária com texto `rgb(255,255,255)` | **IGUAL** (escopado a `.fj-hub`/`.fj-page`; a fundação segue 0,55 — inconsistência listada) |
| `.fj-kbtn` altura · `nav` altura | 31px · 31px | 33px · 34px | **31px · 31px** | **IGUAL** |
| largura do conteúdo do nav | 784,4px | 749,4px | 759,7px | dado (badge de pendências), não CSS |

**Larguras menores (o que "na linha do título" significa):** medido no protótipo pelo Browser pane com emulação de viewport — a **1280** o shell do protótipo vira rail de **56px** e o header quebra em **3 linhas** (título / sino+busca+primária / pílulas, tudo à direita, 174px); a **1728** o shell segue em rail e o header fica em **1 linha** (88px). Em produção o sidebar só vira rail por `data-sidebar="rail"` (`cockpit.css` L57), então a 1728 o conteúdo tem 1468px (< 450+16+1020) e os controles quebram pra 2ª linha — é o que o `.snap` do CI mostra. Isso é **shell (fundação)**, não CSS da Forja: o `.os-page-h` é idêntico nos dois lados (`flex-wrap: wrap`). Produção a 1280 **não foi medida** nesta rodada (o Browser pane não tem a sessão; a janela do Chrome não aceitou o resize) — fica declarado, não inferido.

**D1 (rede) nas pílulas novas:** `window.__marker` gravado antes do clique **sobreviveu** a Trabalho → Aprovações (dois cliques, dois marcadores vivos), a URL e a pílula ativa mudaram, e a rede mostrou `GET /forja/aprovacoes 200` via Inertia (`<Link as="button">`) — sem full reload. D1 **parcial**, como a meta do §11 pede.

---

## 2026-09-02 (noite) — ERRATA da linha "Larguras menores": o rail do protótipo era `localStorage`, não a regra

> **Append-only.** O parágrafo acima fica como está — era o que se mediu naquele momento. O que
> esta errata corrige é o **ponteiro**: a conclusão que ele sustenta é falsa, e ela virou a
> premissa do pedido de auto-rail. Recibo abaixo, reproduzível.

**O que aquele parágrafo afirma:** *"a 1280 o shell do protótipo vira rail de 56px"* e
*"a 1728 o shell **segue** em rail"*.

**O que a re-medição mostra** (espelho `prototipo-ui/cowork/oimpresso.com.html` em
`http://localhost:5623`, sonda = `getComputedStyle(.app).gridTemplateColumns` +
`.os-page-h → getBoundingClientRect().left`, esperando `__oiLazyDone` + 2 leituras iguais de
`querySelectorAll('*').length`, **com `localStorage.removeItem("oimpresso.sidebar.mode")` antes
de cada largura**):

| `innerWidth` | localStorage | modo | `grid-template-columns` | header `left` |
|---|---|---|---|---|
| 1279 | limpo | **rail** | `56px 1223px` | 56 |
| **1280** | limpo | **expanded** | `260px 1020px` | **260** |
| 1440 | limpo | expanded | `260px 1180px` | — |
| 1728 | limpo | **expanded** | `260px 1468px` | **260** |
| 1920 | limpo | expanded | `260px 1660px` | 260 |
| 1728 | herdado `"rail"` | rail | `56px 1672px` | — |
| 1279 → 1728 **ao vivo** | — | continua rail (sem listener) | `56px 1672px` | — |

**A regra real** (`prototipo-ui/cowork/app.jsx:624-634`): `innerWidth < 1280 ? "rail" : "expanded"`,
**só no mount**, e o `localStorage` vence sempre. Como o `useEffect` ao lado grava **todo** valor —
inclusive o automático —, a regra dispara **uma vez por navegador** e a chave nunca mais solta.

**Causa provável do retrato errado, e ela é reproduzível:** ao abrir o espelho no Browser pane
**sem `resize_window` antes**, `window.innerWidth` vale **0** (medido nesta sessão, primeira
navegação). `0 < 1280` é verdadeiro → rail → persistido. Toda medição seguinte naquele perfil
herda `rail` em **qualquer** largura, inclusive 1728. É LC-08 no vetor mais traiçoeiro: a sonda
estava certa, o **estado** é que era de outra corrida.

**Consequência pra Forja, e é a parte que muda a conclusão:** a 1728 o protótipo dá
`260px 1468px` — **os mesmos 1468px de produção**. Naquela largura **não há divergência de
shell**; a quebra do header em 2 linhas no `.snap` do CI é da largura de conteúdo em si, igual
nos dois lados. A divergência real vivia em **≤1279**.

**Como não repetir:** medição de shell no espelho limpa `oimpresso.sidebar.mode` **e** seta a
viewport **antes** da primeira navegação — `innerWidth: 0` é o estado default do pane, e ele
mente a favor do rail.

Decisão que saiu daqui: [ADR UI-0030](../_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md)
(produção passa a fazer auto-rail a **≤1280**, com a escolha manual vencendo).

## 2026-09-02 (noite) — Onda 3: Aprovações vira a view `hoje` do protótipo

> **Estado: APLICADO no código, NÃO MEDIDO em produção.** A comparação por sonda
> (`design-diff --probe` nos dois lados → `--compare --check`) só pode rodar depois do deploy,
> e o merge de `.tsx` é humano ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)).
> Esta seção registra o que foi feito e o que falta medir — não afirma paridade.
> Escrever "IGUAL" antes da sonda seria o strike que a [LC-06](../../LICOES_CODE.md) catalogou.

### A âncora estava no arquivo errado (achado desta onda)

O charter apontava `related_prototype: prototipo-ui/cowork/forja-page.jsx`. **O markup da view
`hoje` não está lá**: o `forja-page.jsx` só a MONTA (linha 1229, `<window.ForjaAprovacoes …/>`);
o componente inteiro mora em [`forja-aprova.jsx`](../../../prototipo-ui/cowork/forja-aprova.jsx).
Quem fosse copiar do arquivo declarado não acharia a tela. Corrigido no frontmatter
(`charter_version: 2`) e re-resolvido por `ancora.mjs`.

**Fonte provada fresca**, não suposta: o ledger de frescor registra a rodada de
2026-09-02T11:17:56Z com `forja-aprova.jsx` entre os 14 arquivos **SYNC**, sha
`cc4cde3692da…`; o arquivo local bate com esse hash (conferido byte a byte nesta sessão, junto
com `forja-data.jsx` `f043f5bd…` e `forja-page.css` `9c180a5d…`).

### O que entrou na tela (markup 1:1, classes do bundle da Onda 1)

| seção do protótipo | classe | dado que a alimenta |
|---|---|---|
| número-herói "N esperando o seu aval" | `.ap-head` › `.fj-hj-n` | `contagem` (`mcp_tasks` em `pending_approval`) |
| alerta de handoff com problema | `.ap-handoff-alert` | `handoffsComProblema()` — delega ao `ForjaMcpService` (dono do tema) |
| faixa "Ao vivo no MCP" | `.ap-vivo` › `.ap-vivo-card` | `mcp_actors` × `mcp_cc_sessions` × `mcp_audit_log` |
| mesa: fila à esquerda | `.ap-mesa` › `.ap-fila` › `.ap-item` | `fila()`, ordem por espera (do backend) |
| mesa: artefato + ações à direita | `.ap-painel` › `.ap-art` › `.ap-acoes` | `fila()[n]` + `decisoesPossiveis()` |
| placar "Equipe de agentes" | `.fj-hj-team` › `.fj-team-tbl` | `cowork_handoffs` por `created_by`, janela 7d |
| toast com desfazer | `.ap-toast` › `.ap-undo` | estado de front (janela de 6s, antes do POST) |

Saíram o `PageHeader` canon e o `KpiGrid`/`KpiCard` (2 cards): o protótipo põe o número no herói e
não tem segundo cabeçalho. O `ui:lint` R4 registra isso como item de lista, não como veto —
é o que a [ADR 0388](../../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) D-2 decide.

**Os dois itens que saíram do `[BACKLOG]` do `casos.md`** foram pedido do [W] em 2026-08-08 e
estavam parados *"ainda sem backend"*: o placar e a faixa ao vivo. O backend chegou nesta onda.

### As divergências DELIBERADAS (categoria, não bug — ADR 0385)

A 0388 é de **aparência** e diz, em D-5, que réplica **não toca comportamento**. Onde o protótipo
e uma lei de domínio discordam, a lei ganha e a diferença fica escrita:

| # | protótipo | produção | lei que manda |
|---|---|---|---|
| 1 | "Aprovar aplicação / Devolver / Rejeitar" | **Admitir · Parquear · Recusar**, vindos de `decisoes` | ADR 0368 §6 proíbe "aprovado" **e** o anti-hook do charter proíbe hardcodar a lista (ela deriva de `McpTask::TRANSITIONS`) |
| 2 | caixa de nota pertence ao "Devolver" | abre na decisão que declara `exige_motivo` | ADR 0368 §5 — o dono da regra é o FSM |
| 3 | 4 tipos com diff, passos e screenshot | só o artefato que `mcp_tasks` guarda | só `Proposta` tem estado canônico; os outros vivem em `cowork_handoffs` e **fundir as fontes é decisão [W]** |

### O que NÃO tem fonte — e mostra "—" em vez de número inventado

As colunas **Sessões hoje** e **Custo hoje / quota** do placar são por **usuário**
(`mcp_cc_sessions`, `mcp_audit_log` e `mcp_quotas` são todos `user_id`), e o schema **não tem
vínculo papel→usuário**: os atores semeados são `wagner`/`felipe`/`maira`/`luiz`/`eliana`/
`claude-code-wagner-laptop`, nunca `CC`/`CD`/`CL`. Preenchê-las exigiria inventar o vínculo.
O backend manda `null`, a célula diz o motivo no `title`, e **criar o vínculo é decisão [W]**.

Pelo mesmo critério, o eixo `nivel` do protótipo (sênior/júnior/artista/agente) não foi
replicado: `mcp_actors` declara `type` (human/ai_agent/service) e `trust_level` (L0..L4), que é
outra coisa. O selo mostra o que É declarado.

### O que a réplica NÃO regrediu (e foi medido)

A fila do protótipo é `<li onClick>` cru, que não abre por teclado. A 1ª versão desta onda copiou
isso e o `eslint-baseline` acusou **2 regressões novas de `jsx-a11y`**
(`click-events-have-key-events`, `no-noninteractive-element-interactions`). Corrigido com
`role=listbox/option` + `tabIndex` + `onKeyDown`, mantendo a classe no próprio `<li>` (o
`.ap-item` é `display:flex` e o `:last-child` tira a última borda — mover a classe pra um
`<button>` interno quebraria os separadores). Re-medido: **as duas foram a zero**.
A 0388 tira o veto da conformidade do **DS**, nunca o da **acessibilidade**.

### Gates locais (rodados nesta sessão, exit 0)

| gate | resultado |
|---|---|
| `foundation-guard` | ✅ 33 .css na allowlist · 0 espalhamento novo |
| `conformance-gate --all` | ✅ 30 arquivos conformes |
| `css-size-baseline` | ✅ delta 0 (nenhum CSS tocado nesta onda) |
| `stylelint-baseline` | ✅ delta 0 |
| `layout-primitives-guard` | ✅ sem regressão — o `FLEX-CRU` desta tela **saiu** da lista (1 → 0) |
| `casos-coverage-guard` | ✅ sem violação nova deste PR |
| `ds-guard --report` (bundle) | 1 achado: paleta `--dev-*`, já declarada desde a Onda 1 |
| `tsc --noEmit` | ✅ 0 erro no arquivo — **com o arquivo dentro do programa** (ver ressalva) |
| `eslint-baseline` | +4 `ds/no-os-btn`, absorvidos (réplica) — precedente: `ForjaHub` na Onda 2 |

⚠️ **Ressalva de método que quase virou gate mudo:** o `tsconfig.json` do repo tem `include`
apenas de `resources/js/**`, então `Modules/**/Resources/js/**` **não é typechecado** pelo comando
padrão. Rodar `tsc -p tsconfig.json` e ler "0 erro no meu arquivo" seria `0 failed` de suíte que
não rodou (§5 2026-07-24). Com um config temporário que INCLUI o arquivo, o compilador achou um
erro real (`TS2532`, índice possivelmente `undefined` no atalho `j`/`k`) — corrigido. Depois:
278 erros no repo, os mesmos 278 de antes, **0 no arquivo desta onda**.

### Render do protótipo conferido (e o que ele corrigiu no meu port)

O protótipo foi servido local (`python -m http.server 5620 --directory prototipo-ui/cowork`,
`localStorage["oimpresso.route"]="teammcp"`) e lido **depois** do `window.__oiLazyDone` com duas
contagens iguais de nós (1007 = 1007) — nunca no meio do lazy-load (§5 2026-08-24).

A leitura da estrutura pegou **duas diferenças reais** que a cópia à mão tinha deixado passar, e
as duas foram corrigidas antes do commit:

| o que o render mostrou | o que eu tinha escrito | conserto |
|---|---|---|
| `thead` do placar tem **8** colunas — a 8ª é vazia e guarda o botão "verificar" do papel sem sinal | 7 colunas, sem a saída | 8ª coluna com `.act` + `os-btn ghost`, visível só quando `sinal_ok` é falso |
| rótulo da 1ª coluna é **"Agente"** | eu tinha trocado por "Papel" | voltou pra "Agente" — nenhuma lei de domínio mandava trocar (diferente de "aprovado", que a ADR 0368 §6 proíbe) |

Estrutura conferida e batendo: herói `7 · esperando o seu aval` · alerta `2 handoffs com problema →`
· `.ap-vivo` com 4 cards · `.ap-fila` com 7 `.ap-item` · `.ap-painel` · `.ap-acoes` com 3 botões ·
`.fj-hj-team` com 5 linhas e a chip `1 sem sinal`.

⚠️ Isto é conferência de **estrutura no protótipo**, não comparação prod×protótipo — a produção
ainda não tem este código. **Não vale como o compare da meta**; serve pra provar que a cópia saiu
fiel antes de ir pro CI.

### Baseline visual regravada (recibo, não promessa)

`visual-regression.yml` despachado com `screens='["Forja/Aprovacoes"]'` na branch da onda.

| run | resultado |
|---|---|
| [33668939298](https://github.com/wagnerra23/oimpresso.com/actions/runs/33668939298) | gerou `vrt/baselines-33668939298` → PR #6574, **cherry-pickado** na branch e o PR fechado |
| [33669764425](https://github.com/wagnerra23/oimpresso.com/actions/runs/33669764425) | re-despachado do HEAD atual (a 1ª rodada saiu do commit anterior ao conserto do placar). Veredito do próprio step: **"Baselines já em dia — nada a commitar."** |

A 2ª rodada existe porque a 1ª baseline foi gerada de `da94ac39bb`, **antes** da 8ª coluna e do
rótulo "Agente". Sem ela eu estaria confiando numa baseline de código que já não era o meu — e o
"nada a commitar" é o que prova que a 8ª coluna não muda o pixel neste ambiente (sem
`cowork_handoffs` semeado, a seção do placar não renderiza). Não foi suposto: é a frase do step.

**O que mudou na imagem** (decodificado com `scripts/tests/snap-diff.mjs`, porque diff de `.snap`
é base64 numa linha e ilegível por construção):

```
1728x1117 · px alterados: 480201 de 1930176 (24,88%) · Δmax=253
assinatura: CONTEÚDO  (Δ≤3 rasterização · Δ≥200 conteúdo)
células 16×16 com mudança: 91 de 256 · linhas afetadas: 2,3,4,5,6,7,8
```

Ler isso importa: **a linha 1 não mudou** — o header do `ForjaHub` (Onda 2) ficou intacto, e a
troca é só do corpo (KpiCards → herói + mesa). Fosse a linha 1, eu teria regredido a Onda 2 sem
perceber.

### O que FALTA — e é a condição de fechar a linha 3 do §11

1. Deploy (merge é [W] — ADR 0283).
2. Baseline visual: `visual-regression.yml` com `screens='["Forja/Aprovacoes"]'`.
3. **A medição**: `design-diff --probe` na produção e no protótipo (`python -m http.server 5620
   --directory prototipo-ui/cowork`), mesma viewport, dark nos dois → `--compare --check`,
   com a tabela por dimensão (D2/D4/D6/D8) apensada aqui.
4. D1 (rede): marcador sobrevive ao clique nas ações da mesa.

Até isso acontecer, a linha 3 da tabela de ondas fica **em andamento**, não ✅.

## 2026-09-02 (noite) — Onda 4: o ALVO da lista medido no protótipo, antes de codar contra ele

> **O que rodou (recibo).** Protótipo servido por HTTP estático (`python -m http.server 5620 --directory prototipo-ui/cowork`), `localStorage["oimpresso.route"]="teammcp"` + `oimpresso.forja.view="trabalho"` + `oimpresso.forja.trabvis="lista"`, tema **dark**. Espera **ativa** até `__oiLazyDone` **e** duas leituras consecutivas iguais (1418 = 1418 nós, 4 tentativas) — a 1ª leitura dava 515→533 e teria produzido o número errado (§5 2026-08-24). Medição por `getComputedStyle` + `getBoundingClientRect`, nunca pela classe declarada.
>
> **Por que medir o protótipo isolado:** a comparação pareada exige a produção deployada, e o merge é ato [W]. Medir o ALVO antes fecha metade do par e evita a classe LC-08 — construir contra o que eu *li* do `.jsx` em vez do que o browser *resolve*. A outra metade (produção) fica declarada como pendência.

| campo | protótipo (medido) | o que a Onda 4 entrega | veredito esperado |
|---|---|---|---|
| linhas visuais da barra de filtro | **3** (`.fj-frentebar` · `.fj-toolbar` · `.fj-filterbar2`, três `top` distintos) | as mesmas 3 | **IGUAL** |
| `kpi.count` | **4** | 4 | **IGUAL** |
| `kpi.tag` | **BUTTON** | `<button>` (o KPI filtra) | **IGUAL** |
| `kpi` valor `font-size` | **17px** (`.tf-kpi-v`) | `.tf-kpi-v` do mesmo bundle | **IGUAL** |
| `kpi` `text-align` | **left** | idem (mesma classe) | **IGUAL** |
| `--accent` no dark | **`oklch(0.70 0.15 295)`** (resolvido em `.fj-page`) | herdado do mesmo bloco | **IGUAL** |
| filhos diretos da `.fj-row` | **13** | **11** | **DIVERGE (declarado)** |

**Os 13 slots do protótipo, na ordem medida:** `fj-rowcheck` · `fj-row-indent` · `fj-prio-dot` · `fj-id` · `fj-type` · `fj-title` · `fj-tam` · `fj-row-mid` · `fj-fresco` · `fj-exec` · `fj-role` · `fj-pin` · `fj-star`.

**Os 2 que a Onda 4 não entrega, e o número é esse por decisão:**

| slot | por que fica de fora |
|---|---|
| `fj-rowcheck` | alimenta a `.fj-bulkbar` (fase/papel/prio/onda/status em massa) — **mutação sem endpoint**. Escrever fora do `TaskCrudService` seria o segundo caminho de escrita que a Mesa evitou; caixa que não age é afordância falsa (LC-15) |
| `fj-fresco` | pílula de frescor (`lido @main` / `não verificado` / `sync Nd`) — **campo que `mcp_tasks` não tem**. É condicional no protótipo, então a falta do dado já a apaga lá |

⚠️ **Nota de honestidade sobre esta contagem.** O número **13** é medido no browser; o **11** é derivado do JSX da réplica (contagem de slots de nível superior da `fj-row`), **não** medido — o par completo exige o deploy. E a contagem depende do DADO: nesta linha do protótipo não apareceram `fj-carry`, `fj-epic-roll` nem `fj-lockico`, que são condicionais; numa linha com épico ou bloqueio, os dois lados sobem juntos. Ou seja: **"3 × 13" da rodada da manhã e "11 × 13" desta são medidas da PRIMEIRA linha de cada lado**, não da estrutura máxima.

**O que segue pendente, declarado:**

- [ ] sonda pareada `design-diff --probe` nos dois renders → `--compare prod.json design.json --check`, dark, mesma viewport, **depois do deploy**. É ela que dá o veredito D2/D4/D6/D8 — nada aqui afirma "0 bug".
- [ ] **D1 (rede)**: `window.__marker` sobrevivendo ao clique do KPI-filtro, do agrupamento e do papel (todos são `router.get` parcial com `only:[...]`), com `GET` Inertia visível — medível só em produção autenticada.
- [ ] `/forja/trabalho` **não está** no visreg (conferido no dono do inventário, `tests/Browser/visreg-screens.json`: das 39 telas, a única da Forja é `Forja/Aprovacoes`) — não há `.snap` a regravar nesta onda.

### Adendo da mesma rodada — a estrutura de CADA bloco, medida filho a filho

A tabela acima comparou a `fj-row`. Faltava o resto, e a medição por `children` mostrou **três diferenças que eu não tinha declarado** e **uma ressalva sobre o próprio instrumento**:

| bloco | protótipo (filhos medidos) | a réplica | diferença |
|---|---|---|---|
| `.fj-frentebar` | **1** — só `fj-frente-note` | 2 — `Segmented` + nota | ⚠️ **o instrumento, não a tela** — ver ressalva abaixo |
| `.fj-toolbar` | **4** — `fj-groupby` · `fj-ia-btn` · `fj-ia-btn` · `fj-search` | 2 — `fj-groupby` · `fj-search` | os 2 `fj-ia-btn` (`Papéis`, `Perguntar ✦`) abrem painéis inexistentes — **já declarado** |
| `.fj-group-head` | **2** — `fj-group-toggle` · `fj-onda-meta` | 1 — `fj-group-toggle` | **NOVO, não estava declarado** — ver abaixo |
| `.fj-totalbar` | 6 blocos: `23 issues` · `4 P0` · `2 bloqueados` · **`3 não-verificados`** · `ordem: automática` · **hint `j k ↵ ?`** | 4 blocos, sem os dois em negrito | `não-verificados` é frescor (campo ausente); a hint anunciaria teclado que a tela não escuta |

**⚠️ A ressalva do instrumento (e ela inverte o sinal da 1ª linha).** O `.fj-frentebar` do protótipo aparece com **1** filho aqui porque o `window.CliSeg` **retorna `null` quando o `Segmented` do DS não está publicado** — e o bundle do DS no snapshot local está truncado pelo teto do `get_file` (limite já registrado na rodada da manhã: 44 componentes publicados × 55 no vivo, sem `Segmented`). Ou seja: **o protótipo VIVO tem 2 filhos ali; o espelho local desenha 1.** A réplica com 2 está **mais** fiel, não menos — e é o que o pedido do [W] instruiu (*"em produção ele existe em `resources/js/Components/ui`; ignore o snapshot, use o de produção"*). Registrado porque medir esta barra contra o espelho local produziria o veredito invertido.

**`fj-onda-meta` — a diferença que faltava declarar.** Quando o agrupamento é por **Onda**, o cabeçalho do grupo do protótipo ganha `estado` (ativa/planejada) · `janela` (jun 11–16) · `carga` por tamanho (1M) · botão **encerrar onda** · botão **✦ resumir**. Isso exige o catálogo `window.FORJA.ONDAS` (ondas com estado, janela e dependências), que **não existe em produção** — `forja_onda` é um `custom_field` de texto em `mcp_tasks`, sem entidade por trás. Os dois botões, além disso, são **ação**: `encerrar onda` é mutação em cascata (carrega não-concluídos pra próxima) e `resumir` chama IA. Fica de fora pela mesma razão dos outros três: a ADR 0388 é licença de **aparência**, e nada disso é aparência.

### Onda 8 (2026-09-02) — a view `mcp` vira réplica e o painel Handoffs volta pra dentro

> **Recibo do que rodou, e o que ele NÃO cobre.** Fonte provada: `forja-page.css` (sha `9c180a5d92ae`) e `forja-page.jsx` (`e4339537969d`) baixados do Cowork vivo por `DesignSync.get_file` e medidos contra o espelho por `cowork-mirror-freshness --snapshot-from --emit-snapshot` → **`igual` nos dois**. Protótipo servido por HTTP estático (`prototipo-ui/cowork`), `localStorage["oimpresso.route"]="teammcp"`, **tema dark**, **viewport 1440**, view `mcp` aberta pelo clique na pílula do topnav, duas leituras estáveis (9/9 linhas de contrato, 6/6 handoffs).
>
> ⚠️ **A comparação PAREADA prod × protótipo NÃO aconteceu nesta sessão** e não se declara fechada: o código desta onda ainda não está em produção (merge de `.tsx` é humano — [ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)). O que segue são os **valores-alvo do lado design, medidos**, para que o pós-deploy seja um `--compare` direto em vez de uma remedição do zero.
>
> 🩺 **Uma armadilha paga nesta rodada, registrada porque quase virou número falso:** a primeira leitura devolveu `color: rgb(0, 0, 0)` em toda a tabela e `--text-dim` **vazio** no `:root`. Não era divergência — era o `_ds/` (gitignored) ausente no espelho: `colors_and_type.css` e `cockpit_domains.css` carregaram com **0 regras**, e o protótipo renderizou sem token nenhum. O portão `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds` repôs 10 deps (2 CSS + `_ds_bundle.js` + 7 fontes) e a medição foi refeita. **Ele é fail-closed e roda ANTES de medir** — sem ele, qualquer cor lida é lixo com aparência de dado.

**Valores-alvo do protótipo** (dark · 1440 · pós-`--preview-ds`), que é onde os três `DIVERGE` da rodada da manhã se resolvem:

| campo | protótipo (medido) | produção ANTES desta onda | o que muda |
|---|---|---|---|
| `.fj-mcp-tbl` linhas | 9 | 9 | — |
| col0 (Ferramenta) | **mono** · `oklch(0.94 0.005 90)` | sem mono (`font-mono` só em parte) | **D4** |
| col1 (Ação) | não-mono · `oklch(0.72 0.005 90)` | herdava `text-muted-foreground` | **D6** |
| col2 (Permissão) | não-mono · `oklch(0.72 0.005 90)` · **`start`** | **`text-right`** | **D8** |
| `th` (3 colunas) | **`left`** nas 3 | col2 `right` | **D8** |
| `.fj-perm-ok` | `oklch(0.84 0.13 150)` sobre `oklch(0.275 0.06 150)`, **mono** | pílula `bg-success/15`, sem mono | **D4+D6** |
| `.fj-perm-deny` | `oklch(0.84 0.18 25)` | `text-destructive-fg` | **D6** |
| os 6 pontos `.mono` | `fj-token-id` · `fj-audit-ts|tool|args` · `fj-ho-slug` · `fj-ho-pr` — **todos monoespaçados** | nenhum deles | **D4** |
| painel Handoffs | **DENTRO** de `.fj-mcp` (medido: `.fj-mcp` contém `.fj-ho`) | rota separada `/forja/handoffs` | estrutura |

**A causa-raiz do D4, medida (não deduzida):** `.mono` é uma utilitária do **shell** do protótipo — `prototipo-ui/cowork/styles.css:1740` — e **não existe em produção**: `grep` por `.mono` global em `resources/css/*.css` = **0 ocorrências**; o bundle da Onda 1 só a traz escopada em 3 pontos (`.fj-dr-meta`, `.fj-team-tbl`, `.ap-files`). Copiar o markup 1:1 sem isso deixaria o **DOM igual e o render diferente** — o formato de erro que o §5 chama de LC-08. Desceu escopada (`.fj-mcp .mono, .fj-ho .mono`), com os dois roots porque o painel renderiza em dois lugares.

**Desvios declarados, todos por DADO** (o mock tem campo que `cowork_handoffs` não tem): sem `~onda`; 5 abas de filtro em vez de 6 (o mock tem `merged`, que o dado real não produz; o real tem `superseded`, que o mock não previu e ganhou pílula neutra); selo de gate omitido quando `gate = 'na'`, como no protótipo faz.

**Conformidade (ADR 0388 — vira lista, não vira bloqueio):** `replica-inconsistencias --modulo Forja` foi de **101 → 102** itens. O saldo é melhor do que o número sugere: `FLEX-CRU` caiu de **31 → 1** nos dois componentes (as classes `fj-*` substituíram o flex solto do DS v6); entraram os glifos do protótipo (`⚿ ↗ ⚠` → R3) e as 7 cores de ator do `ForjaRoleBadge` (R1), que são **dado do ator**, não token de tema. Nota: o `R1` novo do `ForjaMcp` é o texto **`#2417`** da auditoria mock — número de PR lido como cor hex pelo lint, falso-positivo herdado do mesmo padrão que já existia no `#2924` do `ForjaHandoffs`.

**O que fica pro pós-deploy:** injetar a MESMA sonda (`design-diff.mjs --probe`, papéis `tableRow=.fj-mcp-tbl tbody tr` · `filterControls=.fj-ho-tab` · `title=.os-page-h-l h1` · `primary=.os-btn.primary`) em `oimpresso.com/forja/mcp` autenticado, dark, 1440, e rodar `--compare prod.json design.json --check`. A meta do §11 é **0 `DIVERGE(bug)`** em D2/D4/D6/D8.

### Onda 8 · verificação PÓS-DEPLOY (2026-09-03) — o `--compare` que a rodada anterior deixou pendente

> **O que esta seção é, e o que ela NÃO é.** A Onda 8 já foi implementada e mergeada em
> [#6575](https://github.com/wagnerra23/oimpresso.com/pull/6575) (`baf3d173c7`, 2026-09-02). Nada de
> layout foi reimplementado aqui. Esta rodada fecha as **duas pernas que a seção acima declarou em
> aberto**: o `--compare` pareado (que não pôde rodar porque o código ainda não estava em produção) e a
> tipografia/gap por seção, que o pacote de export marca como **não medida** no seu §7.

> **Recibo do que rodou.** Produção = `https://oimpresso.com/forja/mcp` **autenticado**, tema **dark**,
> deploy do sha `63b9fecba1` (`deploy.yml` `success` 18:09Z) — e `git merge-base --is-ancestor
> baf3d173c7 63b9fecba1` confirma que o código da onda ESTÁ no ar. Protótipo = espelho
> `prototipo-ui/cowork` servido por HTTP estático, `localStorage["oimpresso.route"]="teammcp"` +
> `oimpresso.forja.view="mcp"`, dark. Nos **dois** lados: espera ativa até `__oiLazyDone` e **duas
> leituras consecutivas iguais** do número de nós (design 1014/1014 · prod 803/803) — nunca medir
> durante o lazy (§5 2026-08-24). Medição por `getComputedStyle`, jamais pela classe declarada.

**Frescor da fonte — provado onde importa, e o limite dito por inteiro.** O `forja-mcp.jsx` do espelho
é de **23/06** e o vivo mudou (o cabeçalho dele diz *"Overlays e RAG saíram daqui (Onda 3)"*): 31.406
bytes contra ~6,9 KB. O arquivo **está STALE** e não se finge o contrário. Mas a pergunta que decide a
validade desta medição não é *"o arquivo mudou?"* e sim *"a parte que renderiza esta view mudou?"* —
e essa foi respondida por diff mecânico, não por leitura:

| função que gera o DOM de `.fj-mcp` | espelho | vivo (`get_file`) | diff |
|---|---|---|---|
| `HandoffPanel` | 83 linhas | 83 linhas | **vazio — idêntico** |
| `ForjaMCPView` | 62 linhas | 62 linhas | **vazio — idêntico** |

E o CSS, que é a fonte de toda a tipografia/gap/cor medida abaixo, foi provado pela máquina:
`cowork-mirror-freshness --snapshot-from --emit-snapshot` → `forja-page.css` **`igual`** (`7097103e6f1c`).
O `--compare` do `design-diff` **recusou** a conclusão com `⛔ NÃO MEDI` (exit 2) porque a rodada de
frescor do espelho segue **parcial** (1/258) — está certo, e a ressalva fica: o veredito abaixo vale para
`forja-mcp.jsx` + `forja-page.css`, provados um a um, não para o espelho inteiro. Regenerar o pacote é o
pedido de [#6671](https://github.com/wagnerra23/oimpresso.com/pull/6671).

**Veredito do comparador** (`design-diff --compare prod.json design.json --check`, papéis exatamente os
que a seção acima declarou):

```
OK [D2] layout      -> IGUAL      OK [D8] kpi align       -> IGUAL
OK [D4] tipografia  -> IGUAL      OK [D9] texto           -> IGUAL
OK [D6] cor         -> IGUAL      OK [D4] linha da tabela -> IGUAL
DIVERGE(bug): 0
```

**A meta do §11 — 0 `DIVERGE(bug)` em D2/D4/D6/D8 — foi atingida.** Os três `DIVERGE` da rodada da
manhã de 02/09 (col0 sem mono · col1/col2 sem cor própria · col2 `right`) estão **fechados**: as três
células agora medem `align=left`, `larguraPct` 26,3/34,2/39,4 e `mono` na col0 dos **dois** lados.

**Estrutura — §3.8 do pacote de export, conferida item a item:**

| alvo (§3.8) | protótipo | produção | |
|---|---|---|---|
| `.fj-mcp` 4 filhos, ordem `[intro, card Handoffs, grid, card auditoria]` | 4, na ordem | 4, **ordem idêntica** | OK |
| `.fj-mcp-grid` = contrato + credenciais | 2 colunas | 2 colunas | OK |
| 9 `.fj-perm` | 9 | 9 | OK |
| 9 `.fj-role` | 9 | 9 | OK |
| `.fj-audit` 6 linhas, 2 `deny` | 6 / 2 | 6 / 2 | OK |
| `.fj-mcp-tbl` linhas de contrato | 9 | 9 | OK |
| itens de credencial | 3 | 3 | OK |
| 6 `.fj-ho-tab` | 6 | **5** | X desvio de DADO, já declarado acima |
| 6 `.fj-ho-item` | 6 (mock) | **1** (banco) | dado real, não defeito |

**Tipografia e gap — o que o §7 do pacote marcava como NÃO MEDIDO.** 11 seletores de tipografia e 9
caixas, medidos nos dois lados:

- **`font-size`, `font-weight`, `font-family`, `letter-spacing` e `color`: idênticos em 11 de 11.**
- **`display`, `gap`, `padding` e `grid-template-columns`: idênticos em 9 de 9** — `.fj-mcp` `18px 32px 40px` ·
  `.fj-mcp-card` `16px 18px` · `.fj-mcp-grid` `gap 16px` · `.fj-ho-list` `gap 7px` · `.fj-ho-item` `gap 11px`,
  `padding 11px 13px` · `.fj-ho-meta` `gap 8px` · `.fj-ho-tabs` `gap 3px` · `.fj-audit li` `gap 10px`, `padding 6px 8px`.
- **As 3 cores de `.fj-perm` batem** — e este ponto quase virou falso-positivo: prod reporta
  `rgb(137, 226, 157)` e design `oklch(0.84 0.13 150)`. **Notação diferente, cor igual.** Convertidas ao
  mesmo espaço pintando num canvas (a engine resolve, o olho não): `ok` 136,226,156 × 137,226,157 ·
  `propoe` 166,136,240 × 166,137,241 · `deny` 254,151,141 × 255,150,141 — delta de arredondamento.
  Comparar a string teria produzido três `DIVERGE` falsos.

**A ÚNICA divergência que a tipografia revelou — e ela é de FUNDAÇÃO, não desta view.** O
`line-height` difere em 6 seletores, e a causa-raiz está medida na raiz do documento:

| | protótipo | produção |
|---|---|---|
| `body` font-size / line-height | **13,5px** / **19,575px** (x1,45) | **16px** / **24px** (x1,5) |
| `.fj-mcp` line-height | 19,575px | 20,25px |
| `.fj-mcp-card h3` · `.fj-ho-head h3` | 14,4px (12 x **1,2** = `normal`) | 18px (12 x **1,5**) |
| `.fj-audit-ts` · `.fj-ho-slug` · `.fj-perm` | x1,45 | x1,5 |

Onde o bundle **declara** `line-height` (`.fj-mcp-intro` 1,55 · `.fj-ho-sub` 1,5 · `.fj-ho-nota` 1,45) os dois
lados batem exatamente. A divergência aparece **só onde o valor é herdado** — logo não é defeito da view
`mcp`: atinge toda tela que use o bundle, e o conserto é na fundação. Mesmo tratamento que o §7 do pacote
dá ao 1280px (*"fundação, fora do escopo da Forja"*). **Declarado, não consertado — é decisão [W].**

**Corrigido nesta rodada (1 item).** O link do PR mostrava `PR` e o protótipo desenha o **número**
(`{h.pr}`). O número **não é dado novo** — já vem dentro do `pr_url` que o `ForjaMcpService` projeta;
derivar o rótulo é formatar o que existe, não inventar. `rotuloPr()` no `ForjaHandoffs.tsx`, com fallback
`PR` para URL fora do padrão `/pull/<n>` — melhor um `PR` honesto que um `#` vazio. Bite-test **8/8**, com
4 controles negativos (issue, GitLab, lixo, vazio), e a regex lida **do próprio componente**, não de uma
cópia que poderia divergir.

**NÃO corrigido, com o motivo — nenhum destes é aparência com dado disponível:**

| item | protótipo | produção | por que fica |
|---|---|---|---|
| 6ª aba de filtro | `mergeado` | ausente | `cowork_handoffs` não produz `merged`; aba com contador 0 eterno é afordância falsa |
| rótulo do 4º estado | `bloqueado` | `rejeitado` | o estado no banco é `rejected`; chamar de "bloqueado" mentiria sobre o dado |
| lever extra em `parado` / `rejeitado` | 2 (`+ supersede`) | 1 | é **mutação**, não aparência — a ADR 0388 é licença de aparência. Decisão [W] |
| sufixo `.fj-ho-sig-ok` | tem | não tem | o bundle **não define** `.fj-ho-sig-*` (só `.fj-ho-sig`, linha 401) — a classe nasceria morta |

```
PLACAR - Onda 8 - MCP + Handoffs
entregue 7 de 8 elementos estruturais do alvo 3.8 (o 8o e a 6a aba, sem dado que a produza)
tipografia/gap: 11 de 11 seletores e 9 de 9 caixas IGUAIS - 3 de 3 cores IGUAIS
comparador: 0 DIVERGE(bug) em D2/D4/D6/D8/D9 + linha da tabela
ausentes: .fj-ho-tab[mergeado] - estado inexistente em cowork_handoffs
divergencias declaradas: line-height herdado (fundacao, x1,45 x x1,5) - decisao [W]
                         rotulo "bloqueado"->"rejeitado" - fidelidade ao dado
                         2a lever em parado/rejeitado - mutacao, fora da 0388
nao medido: hover/focus/disabled por atomo - viewport 1280 (fundacao, 7 do pacote)
```

**Duas notas de método que valem além desta tela.** (1) O `resize_window` do Chrome MCP devolveu
**"Successfully resized"** duas vezes e o `innerWidth` seguiu em **2560** — a armadilha que a seção do
1280px acima já registra. A medição só sobreviveu porque a largura foi **provada pelo `innerWidth`**,
nunca pela mensagem da tool; o pareamento foi fechado igualando o lado controlável. (2) A sonda devolveu
`larguraPct` **26,3 / 34,2 / 39,4 idênticos** apesar de viewports diferentes — porque é fração da linha,
não px cru; é o que o docblock dela promete, e aqui isso foi **observado**, não assumido.

### Onda 3 (2026-09-02) — o "0,55 × 0,70" fechou NA FUNDAÇÃO ([ADR UI-0031](../_DesignSystem/adr/ui/0031-fundacao-dark-adota-o-accent-do-prototipo.md))

A linha `--accent no dark` acima dizia *"reconciliar a fundação é decisão [W]"*. [W] decidiu em 2026-09-02
(*"não me importo com a decisão que vai escolher (…) apenas faça"*) e a fundação adotou o protótipo — o escopo
`.fj-hub`/`.fj-page` da Onda 2.1 devolveu `--accent` e `--accent-soft`, que agora vêm da fundação.

**A medição que mudou o desenho:** o CSS não era a camada que decidia. O `style` inline do `AppShellV2` vai no
**mesmo** `<div>` que carrega o `data-theme`, e inline vence qualquer seletor — inclusive
`.cockpit[data-theme="dark"]`. Mexer só no DTCG teria dado PR verde e **zero** mudança no browser. Controle
positivo da sonda: um `.cockpit[dark]` com `--accent: 0.55` inline sobre o CSS que diz 0,70 computa **0,55**.

| camada | antes | agora |
|---|---|---|
| DTCG `cockpit.accent` (`semantic.tokens.json`) | `dark_absent` — escuro herdava o claro | par escuro: 0,70 · 0,76 · 0,33 0,09 · fg 0,14 |
| `_generated-cockpit-dark.css` | sem `--accent` / `--accent-2` / `--accent-fg` | os três, gerados por `tokens:build` |
| `AppShellV2` inline | `oklch(0.55 …)` cravado nos dois temas | par por tema (mesmo padrão do [#6306](https://github.com/wagnerra23/oimpresso.com/pull/6306)) |
| `[data-theme="dark"] .fj-hub/.fj-page` | 4 tokens copiados do protótipo | **2** (`--accent-hi`, `--accent-line`) · ratchet 4 → 2 |

**O que a Forja ainda declara, e por quê:** `--accent-hi` e `--accent-line` são vocabulário ds-v6 que a fundação
**não tem em tema nenhum** (0 definições em `resources/`; este bundle é o único consumidor — 25 usos de
`--accent-line`, 1 de `--accent-hi`). Removê-los trocaria a borda sutil 0,47 pelo accent cheio 0,70 em 25 sítios do
escuro, **divergindo mais** do protótipo. Promovê-los é token novo no DS = soberania [W].

**Recibos:** `ds-token-diff` no escopo `cockpit-dark` saiu de **diverge:4 → diverge:0** (o espelho é derivado do
git e foi regerado). `replica-inconsistencias --modulo Forja`: **101 → 101** itens, mas o **R1** (cor crua) do
bundle caiu **335 → 333**. O item `PALETA` **não mudou** e não mudaria — ele é sobre a família `--dev-*(4)`, nunca
sobre `--accent-*`; a premissa de que ele sumiria estava errada.

**Ainda diverge (nomeado, fora do escopo desta onda):** o botão primário — `PageHeaderPrimary.tsx:70` fixa
`oklch(0.55 0.15 295)` por **literal**, sem ler token nenhum, em todos os módulos. É o mesmo achado que a linha
"D6" desta página já registrava; o conserto é fazer o componente consumir `var(--color-primary)` (0,70 no escuro
desde a UI-0021), em PR próprio.

## 2026-09-03 — produção a 1280 medida por fora: o custo de NÃO ter auto-rail, quantificado (corrobora UI-0030)

> ⚠️ **Esta seção nasceu com a conclusão errada e é publicada já corrigida — o erro fica registrado, não apagado.** Ela foi medida em 2026-09-02 à noite, numa sessão paralela, para fechar o *"Produção a 1280 não foi medida"* que a Onda 2.1 declarou. A conclusão original era *"o protótipo raila a 1280 e produção não ⇒ divergência de shell ⇒ decisão [W]"*. **A ERRATA acima derruba essa premissa** e chegou ao `main` antes deste texto: com o `localStorage` limpo, o protótipo a **1280** dá `260px 1020px` — **idêntico** à produção; o rail só ocorre a **≤1279**. Portanto **não há divergência de shell a 1280**, e a decisão que eu abriria já estava tomada ([ADR UI-0030](../_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md), `accepted`, [W] *"apenas faça"*). Eu li o registro narrativo antigo (`rail 56 · 3 linhas · 174,4px`) como se fosse medição do protótipo — era estado poluído de outra corrida. **LC-08 no mesmo vetor que a errata descreve.**
>
> **O que sobrevive, e é o motivo de publicar:** a medição do **lado produção**, que ninguém tinha feito e que **quantifica o defeito que a UI-0030 conserta**.

**Recibo.** `oimpresso.com/forja/aprovacoes` autenticado, dark, `data-sidebar="expanded"`. Viewport de 1280 por **iframe same-origin** na própria aba autenticada, com `contentWindow.innerWidth === 1280` conferido **antes** de medir e **781/781** nós estáveis após a montagem do Inertia. Sonda = `getBoundingClientRect` + `getComputedStyle`.

**O defeito, com número.** Com a sidebar `expanded`, o `.cockpit` fica `260px 1020px 0px` e o `.os-page-h-r` precisa de **1033,9px** — `.fj-viewtabs` termina em **x=1318**, 38px além da viewport. Por borda direita: Aprovações 731 · Trabalho 819 · Saúde 967 · MCP 1032 · Changelog 1216 · **Integrador 1315 → fora da tela**. Não há scroll de página (`scrollWidth === clientWidth === 1280`); o `.main-body` absorve com 38px de `overflow-x`, dentro de um `.cockpit` com `overflow:hidden`. Header = **136,4px em 2 linhas** (`.os-page-h-l` y=12 × `.os-page-h-r` y=91,4), com o padding `12px 24px` da Onda 2.1 **resistindo** a 1280.

**A evidência que valida o remédio da UI-0030, medida no próprio ambiente.** Alternando `data-sidebar` para `rail` na mesma página, sem tocar em CSS:

| | `expanded` (comportamento antigo) | `rail` (o que a UI-0030 passa a fazer a ≤1280) |
|---|---|---|
| grid do `.cockpit` | `260px 1020px 0px` | `56px 1224px 0px` |
| `overflow-x` do `.main-body` | **38px** | **0** |
| destinos fora da viewport | **1 de 6** | **0 de 6** |
| altura · linhas do header | 136,4px · 2 | 136,4px · 2 |

Ou seja: a ≤1280 o auto-rail **elimina o corte** — é o defeito concreto que a decisão fecha. O header **continua em 2 linhas** nos dois casos; a 2ª linha não é defeito e não é o que a UI-0030 se propõe a resolver.

**Limites declarados.** (1) **Não medi o protótipo a 1280 com `localStorage` limpo** — pela errata ele tem os mesmos 1020px de conteúdo, então é de se esperar que corte um destino também; isso **não foi verificado** e não deve ser citado como medido. (2) O `@media (max-width:1280px)` do [`cockpit.css`](../../../resources/css/cockpit.css) L57-59 que eu inspecionei colapsa a coluna direita (`320px → 0`, batendo com o `0px` medido) e **não** fazia auto-rail — retrato da base **anterior** à UI-0030; quem for conferir depois dela deve re-medir, não citar esta linha.

**Nota de método que vale além desta tela:** `resize_window` do Chrome MCP devolve `"Successfully resized"` **sem redimensionar** (`innerWidth` ficou em 2560) — o veredito só sobreviveu porque foi conferido pelo `innerWidth`, nunca pela mensagem da tool. E **não há Chrome** neste ambiente: a extensão roda no **Brave**. Some-se o `innerWidth: 0` do Browser pane que a errata documenta: **toda medição de largura aqui precisa provar a largura antes de medir qualquer outra coisa.**

## 2026-09-02 (Onda 9) — Changelog: alvo do protótipo medido + estrutura da réplica provada em harness ANTES do deploy

> **O que rodou (recibo).** Fonte provada por **hash**, não por afirmação: `forja-page.jsx` e `forja-page.css` do espelho batem byte-a-byte (normalizado) com o `DesignSync.get_file` do projeto vivo `019dcfd3` — `e4339537…62a43a9` e `9c180a5d…f21ed44`, iguais ao `repoHash` do `--manifest`. O render local só ficou VÁLIDO depois de duas correções que valem registrar: (1) `colors_and_type.css` e `cockpit_domains.css` do `_ds/` vinham com **0 regras** (`--text`/`--bg` não resolviam e o `h1` saía preto) — repostos pelo `--preview-ds`, que existe exatamente pra isso; (2) a aba do Browser pane estava com `innerWidth = 0` (o shell entrava em `app--mobile`) — resolvido com `resize_window` 2560×1440. Medir antes disso teria devolvido número plausível e errado.
>
> **Limite declarado desta rodada:** a metade de produção **não foi medida** — o código desta onda ainda não foi deployado, e o merge de `.tsx` é humano ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)). O que foi medido do lado da réplica veio de um **harness**: os dois componentes (`ForjaChangelog` + `ForjaRoleBadge`, React puro) compilados com esbuild e renderizados sob `html.cockpit[data-theme=dark]` + `resources/css/cowork-forja-bundle.css` (o CSS que produção serve — a 1ª tentativa carregou o CSS do protótipo por engano e foi refeita). O harness prova **estrutura e cascata de token**; **não** prova geometria (a cadeia do shell colapsa fora dele: `secA` mediu 56px de largura) nem a cascata real de produção (preflight do Tailwind + fundação). O `compare --check` 0-`DIVERGE(bug)` continua pendente e é pós-deploy.

**Alvo — protótipo (`changelog` de `forja-page.jsx`), dark, 2560, `__oiLazyDone` + 3 leituras estáveis:**

| campo | protótipo |
|---|---|
| linha `.fj-feed-item` | **2 células** (dot + corpo) · corpo em **3 blocos** (topo · resumo · meta) · `flex` · gap 14px |
| `.fj-feed-dot` | 12×12 · radius 50% · `oklch(0.52 0.1 195)` (kind `pr`) |
| `.fj-feed-ref` | 12,5px · 600 · `IBM Plex Mono` |
| `.fj-feed-when` | 10,5px · `oklch(0.58 0.005 90)` |
| `.fj-feed-resumo` | 13px / 19,5px · `oklch(0.72 0.005 90)` |
| `.fj-clog-tab` | 27px · 12px · `line-height: normal` · `5px 13px` · radius 999px |
| `.fj-clog-tab.active` | cor `oklch(0.7 0.15 295)` · bg `oklch(0.32 0.06 295)` · borda `oklch(0.47 0.13 295)` |
| `.fj-changelog` · `.fj-feed` | padding `18px 32px 40px` · max-width **760px** |

**Réplica no harness (mesmas 8 linhas do mock, pra a contagem não confundir a comparação):**

| campo | protótipo | réplica (harness) | veredito |
|---|---|---|---|
| células por linha | 2 | **2** | IGUAL (produção tinha **5** — era o DIVERGE de D2 desta onda) |
| blocos no corpo | 3 | **3** | IGUAL |
| `.fj-changelog` padding | `18px 32px 40px` | **`18px 32px 40px`** | IGUAL |
| `.fj-feed` max-width | 760px | **760px** | IGUAL |
| chips · linhas · selos de ator · módulos · flags | 5 · 8 · 8 · 8 · 3 | **5 · 8 · 8 · 8 · 3** | IGUAL |
| altura do chip | 27px | 28px | **artefato do harness** — `document.fonts.check('12px "IBM Plex Sans"')` = `false`; sem a Plex a fallback muda o `normal`. Não é diferença de código |

**Achado que a medição pegou e que teria virado bug em produção:** no `Cockpit.tsx` a `<section>` das abas é **irmã** do `<ForjaHub>` (que é quem renderiza `.fj-hub`), e o bundle escopa a família `--accent*` do dark a `[data-theme="dark"] .fj-hub, .fj-page` (Onda 2.1, linha 1227). Fora desse escopo os chips herdariam o `--accent` **0,55** da fundação em vez do **0,70** do protótipo, e os `<button>` herdariam o `button{line-height:inherit}` do preflight do Tailwind — a MESMA causa que a Onda 2.1 mediu no botão do topnav (28px → 25px). Corrigido pondo `fj-hub` na `<section>` do changelog. Medido antes de aplicar: `.fj-hub` **não tem regra própria** — as 20 regras dela são todas de descendente, então a classe não carrega layout. `.fj-page` foi testada e **descartada**: tem `height:100%; overflow:hidden` e clipou o feed (1440px de wrapper para 2603px de conteúdo).

**Errata de si mesma, no mesmo dia (o #6581 mergeou depois desta medição):** o parágrafo anterior desta seção apontava como residual o `--accent-soft` dark do bundle (`oklch(0.33 0.09 295)`) contra o do protótipo vivo (`oklch(0.32 0.06 295)`), e dizia que mexer nele era decisão de [W]. **[W] decidiu**: o [#6581](https://github.com/wagnerra23/oimpresso.com/pull/6581) levou `--accent` e `--accent-soft` para a fundação no escuro (0,55 → 0,70) e **encolheu** o bloco escopado `[data-theme="dark"] .fj-hub, .fj-page` de **4 tokens para 2**. Re-medido no bundle depois do merge: o bloco tem hoje só `--accent-hi` e `--accent-line` (linhas 1242-1243). Logo o residual **não existe mais**, e a razão do escopo `fj-hub` na `<section>` mudou — ela agora vale por **duas** coisas, medidas: (a) `--accent-line`, que o `.fj-clog-tab.active` e o `.fj-flag-tier-0` pedem como `var(--accent-line, var(--accent))` — sem o escopo a borda cai no fallback e vira o accent CHEIO (0,70) no lugar da linha sutil (0,47); e (b) `.fj-hub button{line-height:normal}` (linha 1239), o fix de altura do chip. **Fica registrado que a premissa que eu escrevi de manhã caducou à noite** — é o eixo que a LC-10 nomeia: afirmação em presente sobre estado medido apodrece.

**O selo de ator NÃO é componente meu:** o `ForjaRoleBadge` já tinha nascido na **Onda 8** ([#6575](https://github.com/wagnerra23/oimpresso.com/pull/6575)) para a view MCP, com o mesmo `FORJA_ACTORS` verbatim. Eu havia escrito um igual sem saber (não rodei `whats-active` — LC-19); no merge **descartei o meu e usei o dele**, que é o canônico. Diferença que isso traz, declarada: o componente da Onda 8 devolve `null` para papel fora dos 7 do protótipo, igual ao `RoleBadge` do `forja-page.jsx`. Medido no corpus, `decided_by` traz `[W]` em 313 dos 393 ADRs, mas também `[E]` e `[F]` — nesses o selo **não desenha**, e a linha mostra só os módulos. É o comportamento do protótipo; se incomodar, é mudança no componente compartilhado da Onda 8, não aqui.

**Pendente pós-deploy (1 comando cada):** injetar a MESMA sonda (`design-diff.mjs --probe`, papéis `filterControls: .fj-clog-tabs` · `tableRow: .fj-feed-item`) em `https://oimpresso.com/forja/changelog` autenticado, dark, 2560 → `--compare prod.json design.json --check`; e o D1 (clicar um chip e provar que `window.__marker` sobrevive — o filtro é client-side, então o esperado é **zero** requisição).

---

## 2026-09-03 — Onda 6 (Gantt): smoke em PRODUÇÃO, e ele corrigiu uma afirmação minha

Deploy `cb38ae2af` (success 13:27Z), que contém o merge da Onda 6 (`20875e152`, [#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624) às 11:39Z) — ancestralidade conferida com `git merge-base --is-ancestor`, não presumida. Medido em `https://oimpresso.com/forja/roadmap-gantt` **autenticado**, tema **dark** (`data-theme="dark"`), Chrome real.

### O que renderizou (contado no DOM, não olhado)

| elemento | seletor | medido |
|---|---|---|
| parágrafo-âncora | `.fj-quadro-ancora[data-testid="gantt-ancora"]` | **1** |
| barra de totais | `.fj-totalbar.fj-g-foot[data-testid="gantt-totalbar"]` | **1** · `display: flex` |
| legendas | `.fj-g-leg` | **3** (progresso · prazo vencido · hoje) |
| gantt (motor SVAR) | `[data-testid="roadmap-gantt"]` | **1** — montou |

Texto literal da barra em produção:

```
500 tarefas · 5 com prazo vencido · progresso · prazo vencido · hoje
arraste a barra = reagendar prazo · clique = detalhe
```

### Fidelidade ao bundle — computed style, não screenshot

O `.fj-quadro-ancora` do `cowork-forja-bundle.css` declara `font-size:12px; color:var(--text-mute)`. Medido em prod: **`12px`** e **`oklch(0.58 0.005 90)`** — que é exatamente o `--text-mute` do `tokens/_generated-cockpit-dark.css`. O elemento herda os tokens **globais** do cockpit sem precisar do root `.fj-page`, que foi a aposta declarada no charter da onda. **Confirmada.**

### ⚠️ A afirmação que o smoke DERRUBOU (minha, não do código)

O charter e o comentário do `.tsx` previam que o contador de vencidas *"normalmente mostra 0"*, porque só 7 de 1186 tasks têm `due_date`. **Em produção mostra 5.** Das 7 com prazo, **5 já venceram** — o contador está dizendo algo útil, e a previsão pessimista era afirmação sem medição (LC-08). O **comportamento do código estava certo desde o início**: ele lê `due_date` real e ignora a janela `start + 3d` que o `toGanttTasks` inventa. O que estava errado era o que eu escrevi sobre ele. Corrigido nos dois sites no mesmo PR deste recibo.

### O que este smoke NÃO prova

Não é comparação pareada com o protótipo, e não podia ser: o corpo do gantt tem **motores diferentes** dos dois lados (`.fj-g-*` desenhado à mão no protótipo × `@svar-ui/react-gantt` em produção), o que a onda declarou como diferença medida — 163 dependências contra 7 prazos, decisão em aberto de [W]. O que este recibo prova é o que a onda **entregou**: os dois elementos que vivem fora do motor, com a fidelidade de token verificada. A tela também não tem contrato em `tests/Browser/visreg-screens.json`; o comando para criá-lo está no [#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624), e o passo final é a aprovação visual de [W] (F1.5).

**Observação lateral, não do escopo:** o cabeçalho diz `Timeline (530 linhas)` e a barra diz `500 tarefas`. São contagens de coisas diferentes (linhas do gantt incluem as *summary* por módulo; tarefas são o teto `MAX_TASKS = 500`), mas a proximidade dos números convida à leitura errada de quem olhar rápido.


## 2026-09-03 — Onda 1 do export (shell/header + topnav): o alvo §3.1 JÁ estava entregue, menos o badge

> **O achado, com recibo.** O pacote `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` (Cowork, 2026-09-03) numera
> "Onda 1 — shell/header + topnav" como se fosse trabalho a fazer. **Medido, ela já estava no `main`
> desde 02/09**, sob a numeração do PARIDADE §11: estrutura na **Onda 2** ([#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553)),
> geometria na **Onda 2.1** ([#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563)), `--accent` dark na
> [UI-0031](../_DesignSystem/adr/ui/0031-fundacao-dark-adota-o-accent-do-prototipo.md) ([#6581](https://github.com/wagnerra23/oimpresso.com/pull/6581)).
> Reimplementar seria autorar em paralelo a um dono existente (LC-19). Item a item do §3.1:

| item do §3.1 | estado medido no `main` | onde |
|---|---|---|
| `.os-page-h` com 2 zonas | **já entregue** | `ForjaHub.tsx` |
| direita na ordem `[fj-bell, fj-kbtn, fj-viewtabs, os-btn]` | **já entregue** | idem — e medido IGUAL na Onda 2 |
| 6 destinos em 3 `.fj-navgroup` (Trabalho/Esteira/Histórico) | **já entregue** | `FORJA_GRUPOS` + `FORJA_TABS`, defendidos por UC-FORJA-02/14 |
| `--accent` dark `oklch(0.70 0.15 295)` | **já entregue** | `resources/css/tokens/_generated-cockpit-dark.css` (função, não escopo) |
| **badge de pendências no destino Aprovações** | **→ ERA O ÚNICO ABERTO** | fechado aqui (UC-FORJA-19) |

**O que estava errado, e por que ninguém viu.** A prop `pendencias` existia no `ForjaHub` desde a Onda 2, mas
só `Forja/Aprovacoes/Index` a passava — o badge aparecia na única tela onde é redundante (a fila já está na
frente) e sumia nas outras oito, que é justamente onde ele serve. Esta página **já tinha medido o efeito**
— os −**35px** de largura do nav, classificados como *"dado (badge de pendências), não CSS"* nas linhas de
2026-09-02 — sem nomear a causa. Os 7 controllers do hub passam a servir a prop; o `ForjaHub` a lê via
`usePage()`, de modo que a próxima Page do hub nasce com o badge.

⚠️ **Isso muda um número desta página.** As linhas de 2026-09-02 registram o nav de produção em **749,4px**
contra **784,4px** do protótipo, com o delta atribuído ao badge ausente. Com o badge servido nas 9 telas
esse delta deve fechar — **e isso NÃO foi medido**: o código ainda não está deployado e o merge de `.tsx`
é ato [W] ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)). Aquelas linhas seguem válidas como **fato datado**
do dia; quem re-medir depois do deploy deve **re-rodar a sonda**, nunca citar os 749,4px como estado atual.

**Divergência DECLARADA, não consertada — a fórmula.** No protótipo (`forja-page.jsx:936`) `pendencias` soma
**três** fontes: aprovações + triagem + handoffs `stale`/`gateConflito`. Em produção o badge usa
`ForjaAprovacoesService::contagem()`, que conta **só** `mcp_tasks` em `AWAITING_HUMAN`. Esta onda **propaga a
fórmula que a mesa já usa desde a Onda 3**; trocá-la mudaria o número que `/forja/aprovacoes` exibe hoje, o
que é outra decisão. O Service já tem `handoffsComProblema()` se [W] quiser as 3 parcelas.

**PLACAR — Onda 1 (shell/header + topnav)**
```
entregue 5 de 5 elementos do alvo §3.1 (4 já estavam; 1 fechado aqui)
ausentes: nenhum
divergências declaradas: fórmula do `pendencias` (1 parcela em prod × 3 no protótipo) — decisão [W]
não medido: compare pareado pós-deploy (D2/D4/D6/D8) — o código não está em produção
```
---

## 2026-09-03 (Onda 9 · fecho) — Changelog: a tipografia/gap que o §7 do export declarou NÃO medida

> **Por que esta seção existe.** O `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` §7 declara, com todas as
> letras, o que ficou de fora da medição de 03/09: *"Aprovações, Saúde, MCP, Changelog, Integrador:
> medi a **estrutura** (filhos e ordem) e as contagens; **não** medi tipografia/gap por seção como
> fiz na lista/quadro."* A réplica já tinha sido aplicada ([#6591](https://github.com/wagnerra23/oimpresso.com/pull/6591))
> e a estrutura provada em harness (seção de 2026-09-02 acima). O que faltava era o eixo de
> **valor**: a folha que produção serve entrega os mesmos px/cor que a do protótipo? Esta seção
> mede isso e **não** repete o que já estava medido.

### O alvo mudou de arquivo — e não mudou de conteúdo

A build de 03/09 quebrou o protótipo em **1 arquivo por tela**: o `ChangelogFeed`, que vivia dentro
do `forja-page.jsx` (a âncora que a Onda 9 portou), agora é o `forja-changelog.jsx`. Esse arquivo
**não está no espelho** — é um dos 157 que o `cowork-mirror-freshness --sla` lista como presentes só
no vivo (`⬜ existem no VIVO e não estão no espelho`, medido 2026-09-01; a regeneração do bundle é o
[#6671](https://github.com/wagnerra23/oimpresso.com/pull/6671)). Foi lido pelo `DesignSync.get_file`
do projeto `019dcfd3`, `truncated: false`, 1.699 B.

**Risco real:** portar de uma âncora que a refatoração do build podia ter mudado. Medido — não
mudou. A sequência de classes que cada lado escreve no JSX, na ordem:

```
protótipo vivo : fj-changelog → fj-clog-tabs → fj-clog-tab → fj-feed → fj-feed-item →
                 fj-feed-dot → fj-feed-body → fj-feed-top → fj-feed-ref → fj-flag →
                 fj-feed-when → fj-feed-resumo → fj-feed-meta → fj-mod
réplica (main) : … idêntica, com fj-empty a mais …
```

Único delta: **`fj-empty`**, a divergência que o docblock do `ForjaChangelog.tsx` já declarava
(estado vazio usando o idioma do próprio protótipo, `forja-page.jsx:588`) — o protótipo não precisa
dele porque o mock nunca fica vazio, e produção filtrando por `PRs`/`Ondas` sempre dá zero.

**Isto corrige uma dúvida que o §3.9 do export podia induzir.** Ele descreve `.fj-feed-top` como
`[fj-feed-ref, fj-feed-when]` e o 3º bloco como `flags/módulos`. O `forja-changelog.jsx` vivo põe as
**flags no topo** (entre ref e data) e o 3º bloco é `[selo de ator, módulos]` — que é exatamente o
que a réplica faz. A leitura do §3.9 casa com itens do mock **sem flag** (3 dos 8 têm); a estrutura
do protótipo é a da réplica.

### Tipografia/gap: o diff de VALOR, seletor a seletor

Regras de `.fj-changelog`/`.fj-clog*`/`.fj-feed*`/`.fj-flag`/`.fj-mod` no `forja-page.css` do
protótipo × `resources/css/cowork-forja-bundle.css` (a folha que produção serve), comparadas por
corpo normalizado — não por presença do seletor, que seria presence-gate (LC-11):

| | |
|---|---|
| seletores da seção no protótipo | **17** |
| corpo **idêntico** no bundle | **14** |
| corpo divergente | **3** |
| ausentes no bundle | **0** |

As 3 divergências são **a mesma**, e não mudam um pixel:

| seletor | protótipo | bundle | efeito |
|---|---|---|---|
| `.fj-mod.sm` | `font-size:var(--fs-1)` | `font-size:10.5px` | idêntico |
| `.fj-feed-ref` | `font-size:var(--fs-3)` | `font-size:12.5px` | idêntico |
| `.fj-feed-when` | `font-size:var(--fs-1)` | `font-size:10.5px` | idêntico |

O bundle **inlinou o token**. Os dois lados definem os mesmos valores — protótipo em
`prototipo-ui/cowork/styles.css:6407-6409`, repo em
`resources/css/tokens/_generated-foundations-light.css:7-9`: `--fs-1: 10.5px` · `--fs-2: 11.5px` ·
`--fs-3: 12.5px`. Logo o px computado é o mesmo e **não há bug de tamanho**. O que há é perda de
aderência ao DS (literal onde existe token), e ela é do **bundle**, cuja dona é a Onda 1 — esta onda
**reporta, não conserta**, porque mexer no CSS aqui violaria o `NÃO TOCAR: bundle CSS (Onda 1)` do
§1-bis e a lei 5 (`zero CSS novo`).

### PLACAR — Changelog (§3.9)

```
entregue 6 de 6 elementos do alvo
  ✓ .fj-changelog com 2 filhos [fj-clog-tabs, fj-feed]
  ✓ .fj-feed-item com 2 filhos [fj-feed-dot, fj-feed-body]
  ✓ .fj-feed-top começando em fj-feed-ref e terminando em fj-feed-when
  ✓ corpo em 3 blocos (topo · resumo · meta)
  ✓ segmentos Tudo · PRs · ADRs · Sessões · Ondas
  ✓ tipografia/gap: 14 de 17 seletores idênticos, 3 equivalentes, 0 bug
ausentes: nenhuma
divergências declaradas:
  · fj-empty — estado vazio; o mock do protótipo nunca fica vazio, produção fica
  · resumo condicional — sessão sem summary e sem 1º prompt não ganha rótulo sintético
  · 3 font-size literais no bundle onde o protótipo usa var(--fs-N) — MESMO px; dona é a Onda 1
contagem do alvo: os "8 .fj-feed-item" do §3.9 são o MOCK. Em produção o teto é
  ForjaChangelogService::MAX_ENTRIES = 30 — não é divergência de estrutura.
```

### O que esta seção NÃO prova

O `design-diff --compare --check` pós-deploy **segue pendente**, exatamente como a seção de
2026-09-02 registrou e o §11 da PARIDADE marca. Re-testado hoje, não herdado (a §5 2026-09-01 proíbe
herdar afirmação de bloqueio): `curl` em `https://oimpresso.com/forja/changelog` devolve
**`302 → /login`** — o compare exige sessão autenticada, e sem ela nenhum número aqui autoriza
dizer "igual ao design" (lei 6 do export: nada é 0-bug antes do T7). O comando continua escrito na
seção de 2026-09-02. Esta medição é do eixo **folha × folha**, que não depende de deploy; a cascata
real de produção (preflight do Tailwind + fundação) continua fora dela.

---

## 2026-09-03 (Onda 10 · fecho) — Integrador: tipografia/gap medida, e o TabBar do DS confirmado por leitura do bundle

> **Mesmo motivo da seção anterior.** O §7 do `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` lista o
> Integrador entre as views com **estrutura** medida e **tipografia/gap não** medida. A réplica já
> tinha sido aplicada na Onda 10 ([#6620](https://github.com/wagnerra23/oimpresso.com/pull/6620)),
> que trocou o `.fj-int-tabs` de `<button>` pelo TabBar do DS. Falta o eixo de **valor**.

### Estrutura: re-conferida contra o protótipo vivo

`forja-integra.jsx` **está** no espelho (7.263 B, 01/09) e foi relido do vivo por
`DesignSync.get_file` (`truncated: false`) — os dois conferem. Sequência de classes que cada lado
escreve no JSX:

```
protótipo vivo : fj-int-acao → fj-integra → fj-int-verdict → fj-int-src → fj-int-tabs →
                 fj-int-table → fj-int-row → fj-int-head → … → fj-int-conf → fj-int-foot
réplica (main) : … idêntica, SEM fj-int-tabs …
```

O alvo do §3.10 bate: `.fj-integra` com **4** filhos `[fj-int-verdict, ds-tabbar, fj-int-table,
fj-int-foot]` · **9** `.fj-int-row` na aba `absorb` (1 cabeçalho + 8 dados) de **4 células nuas** ·
**8** `.fj-int-tab`.

### O `fj-int-tabs` ausente é correto — e agora está PROVADO, não afirmado

O docblock do `ForjaIntegrador.tsx` já declarava que `.fj-int-tabs` é *"regra MORTA nos dois lados,
já que o TabBar do DS ignora `className`"*. Essa é uma afirmação sobre comportamento de terceiro, do
tipo que a §5 2026-09-01 manda **re-executar em vez de herdar**. Re-executada, em duas camadas:

1. `prototipo-ui/cowork/cli-tabs.jsx:123` — o adaptador **passa** `className` adiante
   (`className={className || undefined}`). Sozinho, isso sugeriria que a regra vive.
2. `scripts/design-sync/mirror-snapshot/_ds_bundle.js` — o `TabBar` do DS desestrutura
   **`{ tabs, active, onChange }`** e nada mais. `className`, `ariaLabel` e `inset` são
   **descartados**; o `<nav>` nasce sem classe e com `aria-label="Sub-navegação"` fixo.

Logo a regra é morta de fato, e **duas consequências** caem junto:

- **`pad={0}`** do protótipo vira `inset` no `CliTabs` e é descartado pelo DS. O `NAV` do
  `ForjaTabBar` não escreve `paddingInline` e o `<nav>` computa `0px`. **Equivalentes** — por razão
  mais forte do que "os dois calham em zero".
- **`ariaLabel="Integrador"`** também é descartado pelo DS: no protótipo o `<nav>` sai como
  `"Sub-navegação"`. A réplica **implementa** o `ariaLabel` e rotula `"Integrador"`. É divergência
  **deliberada e a favor** — exatamente o que a Onda 0a (§2 do export) manda: *"o protótipo não é
  certificado de a11y; estes são defeitos MEUS, pedir ao Code que replique é exportar dívida."*

### Tipografia/gap: o diff de VALOR

Regras `.fj-integra`/`.fj-int-*`/`.fj-est-*` no `forja-page.css` × `cowork-forja-bundle.css`, por
corpo normalizado:

| | |
|---|---|
| seletores da seção no protótipo | **29** |
| corpo **idêntico** no bundle | **22** |
| corpo divergente | **6** |
| reescrito com efeito equivalente | **1** |

As **6** são a mesma divergência da Onda 9 — token inlinado, `var(--fs-N)` → o px que aquele token
já vale (`.fj-int-verdict code` · `.fj-int-tabs button` · `.fj-int-tela, .fj-int-mud` ·
`.fj-int-tela` · `.fj-int-foot` · `.fj-int-foot code`). Zero bug de tamanho; a dona é a Onda 1.

A **1 reescrita** merece nome, porque quase virou um achado falso: minha primeira varredura, que
casava seletor por string exata, reportou `.fj-int-rota small.fj-int-tab` como **ausente** no bundle.
Não está — foi reescrita como `.fj-int-tab{ color:var(--text-dim) !important; }` (`:703`).
Conferida a cascata, o efeito é o mesmo no único consumidor que existe
(`<small className="mono fj-int-tab">`, 1 ocorrência em cada lado): o protótipo vence por
especificidade `(0,3,1)` sobre `.fj-int-rota small`; o bundle vence por `!important`. **Declarado o
que difere:** a versão do bundle é mais **larga** — pinta qualquer `.fj-int-tab`, não só o que está
dentro de `.fj-int-rota small`. Hoje isso não tem consequência porque o seletor tem um consumidor só;
se ganhar outro, o bundle pinta e o protótipo não.

### PLACAR — Integrador (§3.10)

```
entregue 4 de 4 elementos do alvo
  ✓ .fj-integra com 4 filhos [fj-int-verdict, ds-tabbar, fj-int-table, fj-int-foot]
  ✓ 9 .fj-int-row (1 cabeçalho + 8 dados) de 4 células nuas
  ✓ 8 .fj-int-tab
  ✓ tipografia/gap: 22 de 29 idênticos, 6 equivalentes, 1 reescrita equivalente, 0 bug
ausentes: nenhuma
divergências declaradas:
  · className="fj-int-tabs" não escrito — o TabBar do DS descarta className (medido no
    _ds_bundle.js); a regra é morta nos DOIS lados
  · aria-label real ("Integrador") em vez do "Sub-navegação" fixo do DS — melhoria de a11y
    deliberada, Onda 0a §2
  · .fj-int-tab reescrito com !important e escopo mais largo que o do protótipo — mesmo
    efeito no único consumidor; dona é a Onda 1
  · 6 font-size literais no bundle onde o protótipo usa var(--fs-N) — MESMO px; dona é a Onda 1
```

### O que esta seção NÃO prova

O mesmo limite da Onda 9, pela mesma razão e re-testado hoje: `curl` em
`https://oimpresso.com/forja/integrador` devolve **`302 → /login`**. O `compare --check` exige prod
autenticada; sem ele, **nada aqui é "0 bug"** (lei 6 do export). O que esta seção fecha é o eixo
folha × folha e a estrutura contra o protótipo vivo — não o T7.

---

## 2026-09-03 (Onda 3 do export · fecho da lista) — Trabalho · lista: a onda já estava entregue; o que faltava era o registro

> **Numeração:** esta é a **Onda 3** do `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` §1 (`Trabalho · lista`) e a
> **linha 4** da tabela de ondas do [PARIDADE](../Forja/PARIDADE-area-forja-diagnostico-e-ondas.md) — as duas
> numerações são independentes e sempre foram. Quem procurar "Onda 3" naquela tabela cai em *Aprovações*.
>
> **Escopo deste PR: zero código.** A réplica e a a11y já estão no `main`; o que este PR faz é medir de novo,
> declarar o desfecho e corrigir uma linha de tabela que estava factualmente errada.

### 1 · Quem entregou o quê — e a errata que este PR paga

Medido com `gh pr view` + `git log` do próprio arquivo (`git rev-parse --is-shallow-repository` = `false`,
então a história vale — a lápide §5 2026-07-24 não se aplica aqui):

| PR | estado medido | commit no `main` | o que trouxe |
|---|---|---|---|
| [#6577](https://github.com/wagnerra23/oimpresso.com/pull/6577) | **CLOSED**, `mergedAt = null`, fechado 2026-09-02 19:05Z | — | **nada** |
| [#6582](https://github.com/wagnerra23/oimpresso.com/pull/6582) | MERGED 2026-09-02 23:44Z | `ae7689d8d8` | a réplica da lista (3 barras de filtro, `.fj-row` densa, KPI que filtra) |
| [#6669](https://github.com/wagnerra23/oimpresso.com/pull/6669) | MERGED 2026-09-03 18:09Z | `63b9fecba1` | `role="list"` / `group` / `listitem` + `role="status" aria-live="polite"` |

A linha 4 do PARIDADE afirmava **"🟡 #6577 aberto, aguardando merge [W]"**. Estava errada nos dois pontos: o PR
não está aberto, e não foi ele que entregou. `git log --oneline origin/main -- .../TrabalhoLista.tsx` devolve
exatamente **2** commits, os da tabela acima. Corrigido na mesma leva.

### 2 · A ordem dos slots, re-medida nos DOIS lados (não citada)

Abri os dois arquivos e comparei a sequência de emissão, não a lembrança dela:

| # | protótipo — `forja-page.jsx:414-441` (`IssueRow`) | produção — `TrabalhoLista.tsx` |
|---|---|---|
| 1 | `fj-rowcheck` (BUTTON) | — *(ausência declarada)* |
| 2 | `fj-epic-chev` **ou** `fj-row-indent` | `fj-row-indent` *(sempre — ver §3)* |
| 3 | `fj-prio-dot` | `PrioDot` |
| 4 | `fj-id` | `fj-id` |
| 5 | `TypeChip` | `TypeChip` |
| 6 | `fj-title` | `fj-title` |
| 7 | `fj-carry` *(condicional)* | — *(ausência declarada)* |
| 8 | `fj-tam` *(condicional)* | `fj-tam` *(condicional)* |
| 9 | `EpicRoll` *(condicional)* | — *(ausência declarada)* |
| 10 | `fj-row-mid` | `fj-row-mid` |
| 11 | `LockIco` *(condicional)* | `LockIco` *(condicional)* |
| 12 | `FrescorPill` *(condicional)* | — *(ausência declarada)* |
| 13 | `PhaseBadge` **ou** `StatusPill` | `PhaseBadge` **ou** `StatusPill` |
| 14 | `OwnerSeal` | `OwnerSeal` |
| 15 | `Pin` | `Pin` |
| 16 | `Star` | `Star` |

**Veredito:** removidos os 4 slots sem dado, a sequência é **idêntica posição a posição**. A ordem é parte do
alvo (§3.3 do export), e é o item que este fecho estava devendo por medição própria em vez de herdada.

### 3 · PLACAR — Trabalho · lista (§3.3)

```
entregue 11 de 13 elementos do alvo (linha) · 4 de 6 (.fj-totalbar)
ausentes:
  · fj-rowcheck + .fj-bulkbar — mutação em massa sem endpoint. O charter proíbe escrita fora do
    TaskCrudService (que valida o FSM); selecionar sem poder agir é afordância falsa (LC-15)
  · fj-fresco / FrescorPill  — exige carimbo de verificação contra o main; não existe fora do mock
  · fj-epic-chev / EpicRoll  — no protótipo o pai é issue da MESMA lista (kidsOf); em mcp_tasks
    epic_id é FK pra McpEpic, OUTRA entidade. Chegou a ser implementado e ficaria mudo pra
    sempre; removido antes do merge (LC-08)
  · carry xN                 — exige histórico de ondas, que mcp_tasks não guarda
  · fj-total-warn            — depende do mesmo frescor acima
  · fj-total-hint (kbd j k ? )— a tela não escuta esses atalhos; anunciar seria afordância falsa
divergências declaradas:
  · .fj-row é role="listitem", não role="row"/button — ela NÃO tem onClick nesta tela (não há
    drawer aqui); prometer navegação 2D por teclado seria o mesmo LC-15, só que invisível.
    Produção à frente do protótipo, que tem 0 papel de lista
  · role="status" aria-live="polite" no span de issues, e não na barra inteira: role="status" é
    atômico, e na barra todo pin re-anunciaria os quatro números
```

As 6 ausências estão escritas no docblock do `TrabalhoLista.tsx`, com o motivo de cada uma. Nenhuma
renderiza placeholder no lugar.

### 4 · O `gap 14px` do §3.3 × o `gap:20px` da folha — dissolvido pela medição

O export §3.3 pede `.fj-totalbar` com **gap 14px**; as duas folhas dizem **20px**. Não é divergência:

- `grep -n "fj-totalbar"` devolve as **mesmas 4 regras** nos dois arquivos, com os **mesmos valores** —
  base `gap:20px` (`cowork-forja-bundle.css:133` · `prototipo-ui/cowork/forja-page.css:114`) e
  `gap:14px` dentro de `@media (max-width:1100px)` (`:296` · `:277`). O `diff` do bloco `.fj-totalbar`
  volta **vazio**.
- O **§7 do próprio export declara** que a medição rodou a **924px** de viewport. 924 < 1100 ⇒ o media
  query estava ativo ⇒ **14px é o valor certo naquela viewport, nos dois lados**. A 1280 (a do DoD) os
  dois dão 20px.

**Resíduo honesto:** o build de 03/09 não desceu ao espelho (§6 abaixo), então não dá pra *excluir* que ele
tenha mudado o valor-base. Só que nada do que é mensurável hoje sustenta isso — e o export §5 proíbe CSS
novo. **Nenhuma folha foi tocada neste PR.**

### 5 · O que já estava verde, e não se mexeu

- **UC:** `Index.casos.md` tem **17** `UC-TRAB-*`, e o `TrabalhoListaTest.php` cita os **17** — cobertura
  17/17, contada com `grep -o … | sort -u` nos dois arquivos.
- **`design-spec.json`:** `node scripts/design-spec-gen.mjs --check` → *"3 spec(s) por-tela em sincronia"*,
  `rc=0`. O `measured_against_sha: c1263f2e53` do `Index.design-spec.json` aponta pra um commit anterior ao
  #6616 e ao #6669, mas isso **não é drift**: o campo está em `VOLATILE` (`design-spec-gen.mjs:87`), que a
  comparação de frescor descarta por desenho — ela mede a **estrutura derivada**
  (`stable(committed) !== stable(fresh)`, `:125`). Ler o `sha` como régua seria medir a propriedade errada;
  o spec **não foi regravado**.
- **Pest:** não rodou aqui. Teste é CT 100 ou CI, nunca local (proibição Tier 0).

### 6 · O que esta seção NÃO prova

1. **T7 continua pendente**, pelo mesmo motivo das Ondas 9 e 10: prod pede auth (`302 → /login`), e o
   `design-diff --compare --check` exige os dois renders. **Nada aqui é "0 bug"** (lei 6 do export).
2. **O espelho não está fechado.** `node scripts/governance/cowork-mirror-freshness.mjs --sla` acusa
   rodada **PARCIAL**: mediu **1 de 258** (1 sync · 0 stale · **257 unchecked**), e reporta **157**
   arquivos que existem no vivo e não estão no espelho. A regeneração do bundle é pedida no
   [#6671](https://github.com/wagnerra23/oimpresso.com/pull/6671) e **não roda do lado do agente**
   ([ADR 0374](../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)) — logo o `forja-page.jsx`
   que a §2 mede é o do espelho, que pode estar atrás do build de 03/09.
3. **A a11y não foi re-auditada** — a §3 registra o que o #6669 entregou, não uma varredura axe nova.

## 2026-09-03 (Onda 5 do export · fecho) — Gantt: o corpo NÃO vira réplica, e o custo está medido

> **Numeração — os dois documentos contam diferente.** Esta é a **Onda 5 do
> `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md`** (§1: *"5 · Trabalho · gantt"*), que é a mesma tela da
> **Onda 6 da tabela do [PARIDADE §11](../Forja/PARIDADE-area-forja-diagnostico-e-ondas.md)**. Não
> são duas ondas: são duas réguas sobre a mesma tela. Quem cruzar os documentos pelo número vai
> concluir que uma onda sumiu.

**Esta seção fecha declarando** — a moldura foi entregue e medida em produção; o corpo é
**decisão [W] em aberto**, e o desfecho da onda é devolver a escolha com o custo ao lado.

### O que já está entregue (Onda 6 do PARIDADE, [#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624) + recibo [#6644](https://github.com/wagnerra23/oimpresso.com/pull/6644))

Não re-medido aqui — o recibo em produção já está na [§2026-09-03 (Onda 6)](#2026-09-03--onda-6-gantt-smoke-em-produção-e-ele-corrigiu-uma-afirmação-minha)
desta mesma página, autenticado e dark: `.fj-quadro-ancora` (**1** · 12px · `oklch(0.58 0.005 90)`
= `--text-mute`) + `.fj-totalbar.fj-g-foot` (**1** · `display:flex` · **3** `.fj-g-leg`). Re-medir
para reafirmar seria refazer trabalho pago; o que esta seção acrescenta é o **outro lado do
placar**.

Uma conferência que faltava, e que fecha a estrutura da barra: o §3.5 pede `.fj-totalbar` com
**6 filhos**. A produção escreve exatamente **6** `<span>` (`Gantt.tsx` :704-711) — total ·
`fj-total-warn` · 3× `fj-g-leg` · `fj-total-hint`. **6 de 6.**

### O corpo: os 7 alvos do §3.5, medidos no repo

O §3.5 do export descreve o corpo como `.fj-gantt` → `[fj-quadro-ancora, fj-g-scale, fj-g-body,
fj-totalbar]`, com `.fj-g-row` (**32**), `fj-g-lbl` (**33**), `fj-g-fds` (**192**) e `fj-g-bar`
(**32**). Varredura contada no `main`, excluindo o espelho (`git grep -l <classe> -- ':(glob)**'
':(exclude)prototipo-ui/**'`):

| classe do §3.5 | regra no bundle | markup em `.tsx` |
|---|---|---|
| `fj-g-scale` | ✅ `cowork-forja-bundle.css:1107` | **0** |
| `fj-g-body` | ✅ `:1113` | **0** |
| `fj-g-row` | ✅ `:1115-1119` (5 seletores) | **0** |
| `fj-g-lbl` | ✅ `:1108` | **0** |
| `fj-g-track` | ✅ `:1122, :1134` | **0** |
| `fj-g-bar` | ✅ `:1124-1130` (7 seletores) | **0** |
| `fj-g-fds` | ✅ `:1133` | **0** |

E o contêiner: `.fj-gantt` também existe **só como regra** (`:1106`), sem markup — em produção os
três blocos vivem soltos no `Stack` da página, não dentro de um wrapper.

**O número que resume o eixo:** o bundle publica **17** classes `fj-g-*` distintas (34 seletores);
**2** têm consumidor em `.tsx` — `fj-g-foot` e `fj-g-leg`, exatamente as que a Onda 6 entregou. As
outras **15 estão inertes** enquanto o motor for o SVAR.

Isso muda o formato da decisão de [W], e a favor dela: **o CSS já está pago**. A Onda 1 desceu o
bundle inteiro (lei 5 do export: zero CSS novo), então trocar o motor não custaria folha de estilo
— custaria markup + a capacidade que o motor atual entrega.

### O custo de trocar o motor — medido, não estimado

A tela usa `@svar-ui/react-gantt` (`package.json:247` → `^2.6.1`, MIT), com **1** consumidor no
repo inteiro (`Gantt.tsx` :46-47). Os números de produção são de 2026-09-03 e estão ancorados na
tabela do [`Gantt.charter.md`](../../../Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.charter.md) §PARIDADE §11 Onda 6 — não os reescrevo aqui, aponto:

| medida | valor | o que decorre |
|---|---:|---|
| tasks | 1186 | teto do controller: `MAX_TASKS = 500` (`RoadmapGanttController:53`) |
| com `due_date` real | **7** (0,6%) | a linha do tempo tem 7 pontos de dado |
| com `blocked_by` | **163** (13,7%) | as setas **têm** o que desenhar — servidas como links SVAR `{source,target,type:'e2s'}` (`Gantt.tsx` :219-243) |

O protótipo **não desenha setas de dependência** (só um cadeado no card). Trocar o motor, então,
troca **163 dependências desenhadas** por um cronograma mais bonito de **7 prazos** — e o desenho
das setas o protótipo não define, então portá-las seria inventar design, que é o que a
[ADR 0388](../../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
D-5 evita ("réplica não é licença para tocar comportamento").

**Por isso esta onda não decide.** Ela devolve a escolha com o custo ao lado, que é o que o §7-bis
do export já listava como uma das 2 decisões [W] abertas do módulo.

### PLACAR — Gantt (§3.5)

```
entregue 2 de 4 filhos do alvo .fj-gantt
  ✓ .fj-quadro-ancora  — 1 · 12px · oklch(0.58 0.005 90) = --text-mute (prod, #6644)
  ✓ .fj-totalbar.fj-g-foot — 1 · display:flex · 6 filhos · 3 fj-g-leg (6 de 6 do §3.5)
  ✗ .fj-g-scale — 0 markup
  ✗ .fj-g-body  — 0 markup
ausentes: fj-g-scale · fj-g-body · fj-g-row · fj-g-lbl · fj-g-track · fj-g-bar · fj-g-fds
  motivo ÚNICO: decisão [W] em aberto (trocar @svar-ui/react-gantt pelo markup à mão)
  — não é esquecimento, não é dívida da onda, e não é falta de CSS (as 7 regras estão
    no bundle desde a Onda 1; 15 das 17 classes fj-g-* estão inertes)
alvos que ficam SEM contraparte enquanto o motor for o SVAR:
  32 .fj-g-row · 33 fj-g-lbl · 192 fj-g-fds · 32 fj-g-bar · 1 wrapper .fj-gantt
divergências declaradas:
  · copy do arrasto é condicional a can_edit — sem jana.mcp.tasks.write a barra é
    readonly, e anunciar o gesto seria afordância falsa (LC-15)
  · o contador de vencidas lê due_date REAL, não a janela start+3d que o toGanttTasks
    inventa pra dar largura à barra
```

### O que esta seção NÃO prova

Nada aqui é "0 bug" — e neste caso não pode ser, por duas razões independentes:

1. **`compare --check` exige prod autenticada**, e `https://oimpresso.com/forja/roadmap-gantt`
   responde `302 → /login` sem sessão (mesmo limite das Ondas 9 e 10). Lei 6 do export.
2. **A comparação pareada do corpo não seria honesta enquanto a decisão estiver aberta.** Os dois
   lados têm **motores diferentes** — `.fj-g-*` à mão no protótipo × `@svar-ui/react-gantt` em
   produção. Um `--compare` do corpo mediria a distância entre duas implementações que ninguém
   decidiu unificar, e devolveria `DIVERGE` para cada célula: ruído com aparência de veredito.
   O eixo comparável é a **moldura**, e ela foi medida em prod no #6644.

A tela também **não tem contrato** em `tests/Browser/visreg-screens.json` (39 entradas, nenhuma do
gantt — `grep` contado); o comando para criá-lo está no [#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624).

**Observação lateral já catalogada no #6644, repetida aqui só para não ser redescoberta como
achado:** o cabeçalho diz `Timeline (530 linhas)` e a barra diz `500 tarefas`. São contagens de
coisas diferentes — linhas incluem as *summary* por módulo, tarefas é o teto `MAX_TASKS = 500` —
mas a proximidade convida à leitura errada.
## 2026-09-03 (Onda 2 do export · fecho) — Trabalho · chrome: o alvo §3.2 já estava no `main`, e o `padding` do alvo era o do `@media`

Fui executar a **Onda 2 do export** (`Trabalho · chrome` — §3.2: frentebar · KPI · toolbar ·
filterbar2) e, medindo antes de escrever, encontrei o alvo **já entregue** desde 02/09 — pela **Onda
4 do `PARIDADE §11`**, que é outra numeração para o mesmo trabalho. Reimplementar seria autorar em
paralelo a um dono existente ([LC-19](../../LICOES_CODE.md)), então esta seção **declara**, não recodifica.

**Recibo da proveniência — e ele corrige o que a tabela do PARIDADE dizia.** A linha da Onda 4
creditava o [#6577](https://github.com/wagnerra23/oimpresso.com/pull/6577) *"aberto, aguardando merge [W]"*.
Medido hoje: o **#6577 está `CLOSED` sem merge** (`mergedAt: null`). Quem levou o chrome ao `main` foi
o **[#6582](https://github.com/wagnerra23/oimpresso.com/pull/6582)** (merge `ae7689d8d8`, 2026-09-02
23:44Z), achado por `git log -S "fj-frentebar"` no arquivo — não por proximidade de data
(§5 2026-08-15). A linha da tabela é corrigida no mesmo PR desta seção.

### O alvo §3.2 × o `main`, bloco a bloco

Medição por leitura do `origin/main` (`Trabalho/Index.tsx`, 414 ln) contra `prototipo-ui/cowork/forja-page.jsx`.

| bloco do alvo | alvo §3.2 | medido no `main` | veredito |
|---|---|---|---|
| `.fj-frentebar` | 2 filhos: segmented + nota mono com contagem de `mcp_tasks` | `<Segmented>` + `<span class="fj-frente-note">` com `<b class="mono">{kpis.total}</b> mcp_tasks` — copy literal do protótipo (:1138) | **2 de 2** ✓ |
| `.fj-kpirow` | 5 filhos · `gap 10px` · KPI é `BUTTON` · valor 17px · rótulo 10px · clique filtra lista **e** quadro | 4 `<button class="tf-kpi">` + `.fj-kpirow-note`; `onClick={alternarSaude}` + `aria-pressed`; `gap:10px` no bundle | **5 de 5** ✓ |
| `.fj-toolbar` | **4** filhos · `gap 14px` | `.fj-groupby` + `<form class="fj-search">` — os 2 `.fj-ia-btn` não existem | **2 de 4** ⚠️ |
| `.fj-filterbar2` | 9 filhos base · `gap 6px` · 8 chips de papel | `.fj-groupby-lbl` + "todos" + `papeis.map` — estrutura e ordem idênticas | **9 de 9** ✓ |

### Os 2 ausentes da toolbar são SUPERFÍCIE SEM RECEPTOR, não re-skin esquecido

No protótipo (`forja-page.jsx:1061-1062`) os filhos que faltam são os dois `.fj-ia-btn` — **Papéis**
(`onClick={() => setRunbook(true)}`) e **Perguntar ✦** (`onClick={() => setIaPanel({mode:"ask"})}`).

Varredura contada (`rg --hidden -g '!.git/**'` + `git grep` no `origin/main` como oráculo de
desempate, §5 2026-07-30): `fj-ia-btn` aparece em **4 arquivos — protótipo (`.jsx` + `.css`), o
bundle de produção e este próprio doc. Zero em `.tsx` de produção.** Os painéis que eles abrem
(`forja-runbook`, `forja-ia`) estão no **§1 do export** entre as **8 superfícies sem receptor no
`main`** — construção, não re-skin. O header do `Index.tsx` (:36-40) já declara isso desde a Onda 4:
*"`Papéis` e `Perguntar ✦` → abrem painéis (runbook e IA) que não existem"*.

**Renderizá-los desabilitados seria pior que ausentá-los:** botão que não leva a lugar nenhum é
afordância falsa ([LC-15](../../LICOES_CODE.md)) — a mesma razão pela qual a hint de atalhos `j`/`k`/`?`
também ficou de fora. Declarar o receptor é decisão [W]; até lá o placar diz **2 de 4** e não finge 4.

### Achado que corrige a leitura do alvo: `padding 11px 18px` é o valor do `@media`, não divergência

O §3.2 pede `.fj-toolbar` com `padding 11px 18px`. O bundle de produção diz `11px 32px`
(`cowork-forja-bundle.css:48`) — o que pareceria bug. **Não é.** O `11px 18px` mora em
`@media (max-width:1100px)` (mesma folha, :295), e o próprio §7 do export declara que *"a medição
rodou a **924px** de viewport"*. A 924 o alvo caiu na media query.

Conferido nos **dois** breakpoints, protótipo × bundle: `11px 32px` fora e `11px 18px` dentro —
**idênticos**. O mesmo vale para os outros três gaps do §3.2 (`frentebar` 10px · `kpirow` 10px ·
`filterbar2` 6px): todos byte-idênticos entre `forja-page.css` e `cowork-forja-bundle.css`.
Zero CSS novo nesta onda, como a lei 5 exige.

> ⚠️ **Fora dos 4 seletores do §3.2 as duas folhas NÃO são byte-idênticas** — o bundle usa literais
> (`12.5px`, `11.5px`) onde o protótipo usa `var(--fs-3)`/`var(--fs-2)`. Mesmo px, fonte diferente;
> é a divergência que as Ondas 9 e 10 já registraram e cuja dona é a Onda 1. Não a re-abro aqui.

### Divergência declarada: o segmentado emite `aria-pressed`, não `aria-selected`

O §3.2 pede `role=tablist` + `aria-selected` 3 de 3. A produção usa o `Segmented` canon
(`resources/js/Components/ui/segmented.tsx`), que é **Radix `ToggleGroup` `type="single"`** — lido no
arquivo, o import é `ToggleGroup as ToggleGroupPrimitive`, **não** `Tabs`. Logo não há `tablist`.

**Isto é consequência de uma decisão [W] já registrada, não descuido.** O `Index.charter.md` §"A 3ª
vista: Gantt — ATALHO, não fusão de payload" mede **4 colisões** (payload defer-first × eager por
hotfix de produção; a prop `tasks` com shapes distintos; mutação própria `PATCH .../schedule`; trio
próprio) e o anti-hook §153 é explícito: *"**Não** dar `aria-pressed` ao botão Gantt. Ele não é
estado desta tela, é navegação"*. O charter (:217-220) fecha o raciocínio: aqui o valor do
segmentado **nunca** é `gantt` — escolher Gantt navega na hora para `/forja/roadmap-gantt`.

`role=tablist` prometeria um `tabpanel` que não existe nesta tela — afordância falsa outra vez.
**Não reverto decisão [W] declarada em charter** ([ADR 0388](../../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
é licença de **aparência**, nunca de comportamento).

> **Nota de precisão, porque os dois lados dizem "Segmented" e são artefatos diferentes.** O
> `cli-seg.js` (:15-21) registra que o `Segmented` do **DS publicado no Cowork** *"crava
> `role="tablist"`"*, e o chama de **pendência 12 do DS** — com a ressalva do próprio autor de que
> dois call sites são semanticamente rádio, não aba. Ou seja: o `tablist` do alvo vem do DS
> publicado, e o `ToggleGroup` da produção vem do DS em git. Reconciliar os dois é trabalho do **DS**,
> não desta tela — e é onde a pendência 12 já está.

### Os 7 papéis batem — e a primeira medição minha estava errada

O `.fj-filterbar2` é data-driven dos dois lados. Contei as chaves de `FORJA_ACTORS` com um `grep -oE`
cuja classe de caracteres não aceitava dígito, e obtive **6** — o regex descartou a chave `W2`. Lendo
o bloco inteiro: `W · CC · CD · CL · CA · AN · W2` = **7**, e `TrabalhoService::PAPEIS` traz
exatamente os mesmos 7. Logo 1 rótulo + "todos" + 7 chips = **9 filhos base** e **8 chips de papel**,
que é o alvo. Registro o erro porque a sonda respondeu a pergunta errada calada (§5 2026-08-13) — um
número plausível não é prova de execução.

### PLACAR — Trabalho · chrome (§3.2)

```
PLACAR — Trabalho · chrome (§3.2)
entregue 18 de 20 elementos do alvo
  ✓ .fj-frentebar  2 de 2  (Segmented + .fj-frente-note com <b class="mono"> e a contagem)
  ✓ .fj-kpirow     5 de 5  (4 tf-kpi BUTTON com aria-pressed + .fj-kpirow-note; gap 10px)
  ⚠ .fj-toolbar    2 de 4  (.fj-groupby + form.fj-search; gap 14px)
  ✓ .fj-filterbar2 9 de 9  (7 papéis medidos nos DOIS lados; gap 6px; 8 chips)
ausentes:
  · .fj-ia-btn "Papéis"      — sem receptor: painel forja-runbook não existe no main (§1 do export)
  · .fj-ia-btn "Perguntar" — sem receptor: painel forja-ia não existe no main (§1 do export)
divergências declaradas:
  · segmentado emite aria-pressed (Radix ToggleGroup), não role=tablist/aria-selected —
    o Gantt e OUTRA tela (charter §"A 3a vista", 4 colisoes medidas); tablist prometeria
    um tabpanel inexistente. Decisao [W] em charter §153/§217-220, nao revertida aqui
  · o padding "11px 18px" do alvo e o valor @media(max-width:1100px) — a medicao do export
    rodou a 924px; a 1280 sao 11px 32px, identicos nos dois CSS. NAO e divergencia
```

### O que esta seção NÃO prova

O mesmo limite das Ondas 9 e 10, re-testado hoje: `curl` em `https://oimpresso.com/forja/trabalho`
devolve **`302 → /login`**. O `compare --check` exige prod autenticada; sem ele **nada aqui é "0 bug"**
(lei 6 do export). O que esta seção fecha é a **estrutura e a contagem** do §3.2 contra o protótipo
vivo, mais a proveniência do código — não o T7.

> **Errata da própria nota (2026-09-03, mesmo dia) — o #6691 mergeou, e a previsão que eu tinha
> escrito aqui estava ERRADA.** A nota dizia que, com o [#6691](https://github.com/wagnerra23/oimpresso.com/pull/6691)
> (*painel "Papéis"*) no `main`, a toolbar passaria a **3 de 4**. Ele mergeou (`fd83e6db06`) e o
> placar estrutural **continua 2 de 4** — medido, não previsto.
>
> **O que de fato mudou, e o que não mudou.** O receptor **existe**: nasceu
> `_components/ForjaRunbook.tsx`, e o botão está montado e funcional
> (`aria-haspopup="dialog"` + `aria-expanded`, `data-testid="trabalho-papeis"`). Logo a **razão**
> da ausência que esta seção declarou — *"painel que não existe"* — **caducou** para este item.
> Mas a régua do §3.2 conta **filhos diretos da `.fj-toolbar`**, e eles seguem **2**
> (`.fj-groupby` + `form.fj-search`, conferido por indentação no `main`): o botão foi montado
> **dentro** do `.fj-groupby` e com `className="fj-gb-btn"`, não como filho direto da toolbar com
> `.fj-ia-btn`, que é o que o protótipo desenha (`forja-page.jsx:1061`).
>
> Então o item deixa de ser *ausência por falta de receptor* e passa a ser **divergência de posição
> e de classe** vs o protótipo — outra natureza, mesmo número. Se isso deve ser reconciliado (mover
> o botão para filho direto com `.fj-ia-btn`) é decisão [W]: o #6691 tem charter e casos próprios,
> e mexer neles a partir daqui seria tocar onda alheia. **O `Perguntar ✦` segue sem receptor**
> (painel `forja-ia`), esse inalterado.
