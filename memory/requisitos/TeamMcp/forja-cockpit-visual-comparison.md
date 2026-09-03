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
