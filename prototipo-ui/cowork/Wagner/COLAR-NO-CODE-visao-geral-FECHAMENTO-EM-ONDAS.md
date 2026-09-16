# COLAR NO CODE — "Visão geral" (`/dashboard-legacy`) · pacote de export em 10 blocos

> **De:** [CC] Claude Design (Cowork) · **Para:** [CL] Claude Code no `main` · **Data:** 2026-09-04
> **Reescreve** a versão de 2026-08-28 deste mesmo arquivo (§2-ter anti-scatter: módulo que já tem ponte se reescreve, não ganha doc novo). Este É o pacote do módulo.
> **Nada aqui está commitado** — as tools de GitHub do Cowork são read-only. Ponte = [W] colar 1× ou Issue `cowork-intake`.

---

## 1. Base de fato — o que foi LIDO NESTE TURNO (2026-09-04T16:31Z, `main`)

Lido integralmente no `main` neste turno:

1. `resources/js/Pages/Home/Index.tsx` (18.048 bytes)
2. `resources/js/Pages/Home/_components/GradesPainel.tsx` (12.984 bytes)

Medido neste turno no protótipo (host `oimpresso.com.html`, rota `dash-legacy`, tema dark, após duas leituras iguais de `querySelectorAll('*').length` = **1.032 nós**, sonda com caso de sanidade `rgb(1,2,3)` conferido):
`prototipo-ui/cowork/dash-legacy-page.jsx` — 12 gatilhos de aba, 7 linhas na grade aberta, 6 SVG, 31 focáveis, `lang=pt-BR`.

**NÃO li neste turno — logo não afirmo nada sobre:** `Pages/Home/Index.charter.md`, `Index.casos.md`, `HomeController.php`, `GradesDoPainelService.php`, `Components/cockpit/Sidebar.tsx` (só vi 6 linhas por busca, não o arquivo), `PageHeaderTabs.tsx`, `shared/DataTable.tsx`, `shared/PeriodBar.tsx`, `lang/pt/home.php`, `routes/web.php`, `prototipo-ui/contrato/dashboard-visao-geral.contract.json`, `cockpit.css`, os gates em `scripts/`. Onde este pacote cita esses arquivos, é **hipótese a confirmar no PR**, não fato.

---

## 2. a11y do ALVO (A1–A12) — medido neste turno, e o alvo REPROVOU

| # | Critério | Alvo (antes) | Ação |
|---|---|---|---|
| A1 | Hierarquia de cabeçalho | `h1` → **`h3`** (pula h2) nos 4 painéis | ❌ → **corrigido no build daqui** (`h3`→`h2`) |
| A2 | Alternativa textual de gráfico | 5 de 6 SVG sem `aria-hidden`, sem `role`, sem `<title>`; nenhum número em texto | ❌ → **corrigido aqui** (`SerieSR` sr-only + `aria-hidden` nos 2 Charts) |
| A3 | Landmark / rótulo de seção | só "Período" e "Sub-navegação" tinham `aria-label` | ❌ → **corrigido aqui** (4 sections + grades rotuladas) |
| A4 | `data-screen-label` | ausente | ❌ → **corrigido aqui** (`data-screen-label="Visão geral"`) |
| A5 | Alvo clicável semântico | 0 `div` clicável; Pendências são `<button>` | ✅ |
| A6 | Nome acessível em focável | 1 `INPUT` sem nome (campo do `PeriodBar` do DS) | ⚠️ **defeito do DS**, não da tela — ver bloco 7 |
| A7 | Cabeçalho de tabela | `th` sem `scope`, tabela sem `caption` (grade do `DataTablePro` do DS) | ⚠️ **defeito do DS** — ver bloco 7 |
| A8 | Idioma | `lang="pt-BR"` | ✅ |
| A9 | `<main>` único | shell do Cowork não expõe `main` na medição | ⚠️ host, fora do escopo desta onda |
| A10 | Cor não é único portador | status via `StatusBadge` (dot + texto) | ✅ |
| A11 | Foco visível | anel de accent do DS | ✅ |
| A12 | Cor crua | zero — tudo `var(--*)` | ✅ |

**Nota de direção:** em A2, A3 e A1 o `main` está **À FRENTE** do protótipo — `Index.tsx` já traz `SerieAcessivel` (tabela sr-only com `caption`/`scope`), `aria-hidden` no sparkline e `aria-label` em `section`, e usa `h2`. **Não vira pedido:** corrigi no build daqui e registrei aqui. Exportar o alvo como estava seria exportar dívida com selo.

---

## 3. Ancoragem dupla

| campo | valor |
|---|---|
| **alvo (layout)** | `prototipo-ui/cowork/dash-legacy-page.jsx` (rota `dash-legacy`, atalho "Visão geral" em `data.jsx:19`) |
| **âncora (código)** | `resources/js/Pages/Home/Index.tsx` + `_components/GradesPainel.tsx` (lidos neste turno) |
| **granularidade** | página única → **onda = seção** (nunca > 1 PR ≤300 linhas) |
| **seções** | cabeçalho+stats · PeriodBar+Loja · 4 KPI · contrapartidas · **pendências** · 2 gráficos · grades+drawer · banner |
| **autoridade de token** | `TabBar` do DS → protótipo → produção. Zero cor crua; `--kpi-feature-*` já existe em `.cockpit` |

---

## 4. Delta medido alvo × main (o que sobrou de verdade)

| # | Seção | Alvo | `main` (lido) | Veredito |
|---|---|---|---|---|
| 1 | Cabeçalho | `PageHeader` com `stats` tabulares | `PageHeader` com `subtitle` em texto corrido + `leading` ícone | **Divergência de forma** — a stat perde o alinhamento tabular. Onda 1 |
| 2 | Pendências | painel com 5 atalhos `<button>` que trocam a aba | **NÃO EXISTE** em `Index.tsx` | **Lacuna real** — Onda 2, depende de [W] (custo: 1 `COUNT`/linha) |
| 3 | Abas de grade | `TabBar` do DS com `count` por aba | `PageHeaderTabs` com `maxVisible` + `md:flex-wrap`, **sem count** (motivo declarado no fonte: 8 COUNT/render vs first-paint ≤800ms) | **main decidiu com motivo** — não vira pedido; só a troca `PageHeaderTabs`→`TabBar` DS entra, se [W] quiser o token do DS |
| 4 | Aba "Fluxo de caixa" | existe (9ª) | ausente, por não existir no Blade | ✅ correto no main |
| 5 | Rodapé da grade | "N de M linhas · clique para abrir" + **Exportar CSV** | tem o texto, **não tem CSV** | Onda 3 (precisa de endpoint) |
| 6 | Drawer | `Drawer`+`DrawerSection` do DS, footer com ação | `Sheet` do shadcn, `dl` de rótulos, "Lançar pagamento" só nas 2 abas de título | ✅ main à frente (respeita o Non-Goal de mutação) |
| 7 | Banner de herança | `Alert tone="warn"` sobre widgets pluggable | ausente (Blade saiu; 0 produtores medidos) | ✅ instrumento de protótipo — **não exportar** |
| 8 | Papel simulado / Simular falha | existem | ausentes, por decisão declarada | ✅ instrumento de protótipo |

---

## 5. ARQUIVOS

**A EDITAR**
- `resources/js/Pages/Home/Index.tsx` — só a seção da onda em curso.
- `resources/js/Pages/Home/_components/GradesPainel.tsx` — só se a onda for grade/abas.
- `resources/js/Pages/Home/Index.casos.md` + `.charter.md` — UC da onda (ler antes; não li neste turno).

**A REUSAR (existe, não recriar)**
- `@/Components/PageHeader` · `@/Components/layout` (Stack/Inline/Grid, ADR 0253) · `shared/Chart` · `shared/KpiCard` · `shared/PeriodBar` · `shared/EmptyState` · `shared/StatusBadge` · `shared/DataTable` · `shared/PageHeaderTabs` · `ui/sheet` · `ui/skeleton` · tokens `--kpi-feature-*` (já em `.cockpit`).

**NÃO TOCAR**
- `Chart.tsx`, `StatusBadge.tsx`, `DataTable.tsx`, `PageHeaderTabs.tsx` — primitivas compartilhadas; mudança ali é PR próprio, com bateria própria.
- `KpiHero` local do `Index.tsx` — o comentário de medição de 2026-09-03 é a lei da figura-fundo; não “simplificar”.
- `SerieAcessivel` e o `aria-hidden` do sparkline — são a correção de a11y já paga.
- Nada de token novo, nada de CSS novo, nada de `.html`/rota nova no Cowork.

**PARAR SE**
- a onda encostar em 2 seções, ou passar de ~300 linhas;
- o `contrato-de-tela` exigir âncora `data-contract` nova sem ADR;
- a seção exigir `count` por aba (custo de query) ou CSV (endpoint) — isso é [W], não código;
- o charter/casos contradisserem este pacote — **o charter ganha** (não li neste turno).

---

## 6. Ondas propostas

- **Onda 1 · cabeçalho** — `stats` tabulares no lugar do `subtitle` corrido, se [W] confirmar a forma do protótipo. ≤60 ln.
- **Onda 2 · Pendências** ⛔ [W] — painel de 5 atalhos que trocam a aba (query string). Custa 1 `COUNT` por linha.
- **Onda 3 · rodapé da grade** — "Exportar CSV" (precisa de endpoint) e/ou `count` por aba.
- **Onda 4 · sidebar** ⛔ [W] — label `Dashboard`→`Visão geral` **junto** com o `group` declarado; um sem o outro joga o item em MAIS. *(hipótese: `lang/pt/home.php` — não lido neste turno.)*
- **Onda 5 · promover o contrato a required** — verde 3× seguidas.

---

## 7. O que a ancoragem NÃO resolve

1. **A6/A7 são do DS, não da tela.** O `INPUT` sem nome do `PeriodBar` e os `th` sem `scope`/`caption` do `DataTablePro` moram em componente compartilhado — não consigo corrigir no build do Cowork sem editar o DS, e não edito primitiva por atalho. Vira **PR próprio de a11y de primitiva**, com bateria própria.
2. **`count` por aba e CSV são decisão de custo**, não de layout — nenhuma medição resolve.
3. **Alocação/label na sidebar** depende de [W]; e eu **não li** `Sidebar.tsx`, `lang/pt/home.php` nem `routes/web.php` neste turno.
4. **`<main>` único (A9)** é do host do Cowork, não da tela.
5. **Charter e contrato de tela não foram lidos** — se divergirem, este pacote cede.

---

## 8. Gates a rodar (cada PR)

`npm run contrato:check -- prototipo-ui/contrato/dashboard-visao-geral.contract.json` · `node scripts/qa/prototipo-readiness.mjs` · `casos:check` · Pest `HomeIndexInertiaTest` + `GradesDoPainelTest` · `design-memory-gate` (inclui `cowork-ssot-guard` + `cowork-mirror-freshness --absent-local/--check-orfaos/--check-refs`). *(Nomes conforme o pacote anterior deste arquivo — não reconferi os scripts neste turno.)*

---

## 9. DoD

Nada é "fechado" antes do **T7** (`design-diff --compare --check` nos dois renders, prod deployada). a11y da tela: A1–A4 já pagos no alvo; A6/A7 pendem do PR de primitiva.

---

## 10. Placar

| item | estado |
|---|---|
| Âncora lida no `main` neste turno | ✅ 2 arquivos (`Index.tsx`, `GradesPainel.tsx`) |
| Alvo medido (dark, 2 leituras iguais, sanidade) | ✅ 1.032 nós |
| a11y do alvo A1–A12 | 8 ✅ · **4 defeitos** → 4 corrigidos aqui, 2 (A6/A7) são do DS · A9 é do host |
| Correções aplicadas no build daqui | `h3`→`h2` (4) · `SerieSR` sr-only (2 gráficos) · `aria-hidden` nos Charts · `aria-label` em 5 seções · `data-screen-label` |
| Divergências que viram pedido | **2** (cabeçalho, Pendências) + 2 dependentes de [W] (CSV/count, sidebar) |
| Falsos pedidos evitados (main à frente ou com motivo) | **5** (SerieAcessivel, drawer, aba caixa, count por aba, instrumentos de protótipo) |
| Escrita no git | ❌ nenhuma — read-only |
| Pacote regenerado (`gerar-payload-partes.mjs`) | ❌ **não roda daqui** — ciclo fecha sem pacote; comando na `## 🔁 ROTINA` |

**Ciclo fechado sem regenerar o bundle.** Comando pro Code:
`node scripts/design-sync/gerar-payload-partes.mjs --root prototipo-ui/cowork --out sync/ --previous sync/bundle.manifest.json`
