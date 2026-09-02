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

### Onda 3 (2026-09-02) — o "0,55 × 0,70" fechou NA FUNDAÇÃO ([ADR UI-0030](../_DesignSystem/adr/ui/0030-fundacao-dark-adota-o-accent-do-prototipo.md))

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
