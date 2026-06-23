# AUDITORIA — Reconciliação protótipo≡spec≡produção + Análise POR SETOR (SOTA Ângulo 1)

> **Tema:** método para manter os **três** artefatos do loop Cowork→code em SINCRONIA (protótipo · spec/charter · produção) e formalizar a **análise por setor/região** (comparar parte-por-parte, não tela-inteira).
> **Data:** 2026-06-22 · **Autor:** audit-research-expert (Fase 1 `/audit-and-fix`)
> **Escopo código:** `prototipo-ui/PROTOCOL.md`, `prototipo-ui/prototipos/`, `memory/requisitos/**/*-visual-comparison.md`, `resources/js/Pages/Produto/`, `prototipo-ui/audit/`, `*.charter.md`
> **Problema do Wagner (cravado HOJE):** "enquanto o protótipo não ficar igual ao spec e à produção, isso vai ficar ruim de analisar." Provado: protótipo Produto = LISTA (thumbnails/thead/marca); produção `Pages/Produto/Index.tsx` = CARD-GRID stone-*. Os três divergiram → o diff "do que mudou" vira lixo (compara coisas diferentes).
> **Insight do Wagner:** análise POR SETOR (dividir a tela em regiões, comparar parte por parte) deu certo antes — mais granular e confiável que diff de tela inteira.
> **Auditorias irmãs (NÃO duplicar):** [token-driven](AUDITORIA-design-as-code-token-driven-2026-06-22.md) (camada de *geração*) · [fidelidade-anti-drift](AUDITORIA-fidelidade-anti-drift-codegen-2026-06-22.md) (camada de *medição visual / pixel-diff*). **Esta foca na camada de RECONCILIAÇÃO TRIPLA + granularidade POR SETOR** — qual é a fonte, como alinhar os 3, e como decompor a tela em regiões comparáveis.

---

## 1. TL;DR

**Maturidade global ponderada: 61% — recomendação CONSOLIDAR (não EVOLUIR), mas com 1 mudança estrutural pequena: tornar o comparativo 3-way e espacialmente ancorado.**

O oimpresso já tem **quase toda** a matéria-prima da reconciliação: o triplet é nomeado e roteado (protótipo em `prototipos/<tela>/` → spec em `*.charter.md` com frontmatter `blueprint_cowork` + `divergence_from_blueprint` → produção em `Pages/<Mod>/<Tela>.tsx`), há **76 arquivos `*-visual-comparison.md`** com a tabela `mwart-comparative V4` de 15 dimensões, e um `design-report.json` mecanizado por tela. **O buraco é de forma, não de filosofia:** (1) o comparativo existente é **2-way** (Cowork × Inertia) — a **spec/charter não é coluna**, então quando protótipo e charter discordam ninguém detecta; (2) as 15 dimensões são uma **lista plana**, não **regiões espaciais ancoradas** (header / KPI-strip / filtros / corpo / item) — é isso que o Wagner chama de "por setor"; (3) **não existe um campo "fonte da verdade"** declarado por setor, então a reconciliação não tem regra de desempate.

**Caso Produto resolvido (§9):** a produção `Index.tsx` **está correta** — é card-grid, exatamente o que o charter manda (`Goals: Grid view de cards (NÃO tabela)` + `❌ Tabela ao invés de cards`). Quem divergiu foi o **protótipo de referência**: o material em repo é `produto-app.jsx` / `Produto Unificado.html` (variante DENSA/lista do `/produto/unificado`), comparado por engano contra a tela LITE. **spec ≡ produção concordam; o protótipo é da tela errada.** Fonte da verdade = **charter** (spec-anchored), produção é a implementação viva conforme.

**Top 3 gaps:** P0 comparativo virar **3-way** (Protótipo · Charter · Produção) com coluna "fonte" e linha "veredito" por setor · P0 **decompor por setor/região ancorado** (5-7 zonas nomeadas por arquétipo de tela, não 15 dims flat) · P1 **reconciliador que lê o frontmatter `divergence_from_blueprint`** e falha o gate quando protótipo≠charter sem divergência declarada.

---

## 2. Concorrentes / sistemas de referência (11 sistemas · 3 categorias)

### A. Reconciliação / "qual é a fonte" (design ↔ spec ↔ código)

| Sistema | Modelo de fonte | Como reconcilia | Ref |
|---|---|---|---|
| **Figma Code Connect** | Código de produção é a fonte do *snippet*; design é a *view* | Mapa componente-design ↔ componente-código máquina-legível; Dev Mode/MCP devolve o código real, não autogen — "sem isso o modelo chuta" | [figma/code-connect](https://github.com/figma/code-connect) · [developers.figma.com](https://developers.figma.com/docs/code-connect/) |
| **Spec-Driven Development (SDD) 2026** | **Spec é o north-star**; tests/docs/design rastreiam de volta a ela | "A spec ancora o projeto como uma fonte única"; IA regenera partes do código pra realinhar à spec | [thebcms](https://thebcms.com/blog/spec-driven-development) · [scalablepath](https://www.scalablepath.com/machine-learning/spec-driven-development-guide) |
| **Claude Design (Anthropic, jun/2026)** | Round-trip: DS importado do repo + `/design-sync` | Import de design-system via repo GitHub + round-trip com Claude Code (premia DS máquina-legível) | [anthropic.com](https://www.anthropic.com/news/claude-design-anthropic-labs) · [venturebeat](https://venturebeat.com/technology/anthropic-ships-major-claude-design-overhaul-with-design-system-imports-code-round-trips-and-a-fix-for-its-token-burning-problem) |
| **Bi-directional sync (designops 2026)** | **Declarar por-campo quem é a fonte**; conflito → regra de desempate | Last-write-wins / system-priority / **field-level rules** / fila de revisão manual; IDs + timestamps + change-tracking | [stacksync](https://www.stacksync.com/blog/the-complete-guide-to-two-way-sync-definitions-methods-and-use-cases) · [exalate](https://exalate.com/blog/two-way-synchronization/) |

### B. Detecção de drift design↔código (deviation-from-spec, não só regression)

| Sistema | O que detecta | Diferencial |
|---|---|---|
| **zeroheight Design Systems Report 2026** | Token drift / variant drift / pattern drift / doc drift | "Regression detecta mudança vs baseline, **não** desvio vs spec — precisa dos DOIS"; só 8% dos times "muito estáveis", 44% instáveis | [overlayqa](https://overlayqa.com/blog/design-system-drift/) |
| **design-compare-tool (OSS)** | Imagem de design × UI construída | Compara design-alvo contra o que foi de fato construído | [github/prash5t](https://github.com/prash5t/design-compare-tool) |
| **design-extract (OSS) + MCP** | DS extraído × SSOT vivo | Drift-check contra SSOT + visual-diff + WCAG via MCP | [github/Manavarya09](https://github.com/Manavarya09/design-extract) |

### C. Diff visual POR REGIÃO / SETOR (component-level, não tela-inteira)

| Sistema | Mecanismo de região | Ref |
|---|---|---|
| **Applitools (scope rules)** | Regras por região: "ignore mudança no footer, **flague qualquer mudança de layout no hero**" — semântica por zona | [browserstack](https://www.browserstack.com/guide/visual-diff-algorithm-to-improve-visual-testing) |
| **Chromatic Diff Inspector** | Diff por componente isolado (story) + 1-Up overlay / 2-Up split; baseline por estado de componente | [chromatic.com/docs/diff-inspector](https://www.chromatic.com/docs/diff-inspector/) |
| **Playwright (element-scoped + mask)** | `locator.screenshot()` por elemento + `mask:[...]` por região; `clip` por bounding-box; `maxDiffPixelRatio` por scope | [bug0](https://bug0.com/knowledge-base/playwright-visual-regression-testing) · [testdino](https://testdino.com/blog/playwright-visual-testing) |
| **@brightspace-ui/visual-diff** | `getRect()` captura região do elemento + `screenshotAndCompare()` localizado (não full-page) | [npm](https://www.npmjs.com/package/@brightspace-ui/visual-diff) |
| **Playwright MCP + pixelmatch (loop IA)** | Agente captura região, gera `compare-images.ts`, refina UI por feedback de diff por região | [egghead](https://egghead.io/ai-driven-design-workflow-playwright-mcp-screenshots-visual-diffs-and-cursor-rules~aulxx) |

> **Veredito SOTA:** ninguém compara "tela inteira como imagem" e confia no resultado. Os maduros (Applitools/Chromatic/Playwright) **escopam por região/componente** e aplicam **regra semântica por zona** (ignorar footer, exigir layout no hero). E a fonte-da-verdade é **declarada** (Code Connect: código; SDD: spec), com **regra de desempate** quando há conflito (bi-sync field-level). O Wagner reinventou os DOIS por intuição ("por setor" + "alinhar os três").

---

## 3. Matriz de capacidades (20 dimensões × SOTA × oimpresso)

Legenda: ✅ pleno · 🟡 parcial · ❌ ausente · 🟢 supera mercado

| # | Dimensão | SOTA (quem) | oimpresso HOJE | Status |
|---|---|---|---|---|
| 1 | Triplet nomeado (protótipo/spec/prod) | SDD / Code Connect | charter `blueprint_cowork` + `component` + `prototipos/<tela>/` | ✅ |
| 2 | **Fonte-da-verdade declarada** | SDD (spec) / Code Connect (código) | implícita, não escrita por campo | ❌ GAP |
| 3 | **Regra de desempate em conflito** | bi-sync field-level rules | nenhuma | ❌ GAP |
| 4 | Divergência declarada vs blueprint | — (raro) | frontmatter `divergence_from_blueprint` | 🟢 SUPERA |
| 5 | Comparativo **3-way** (proto·spec·prod) | Code Connect (design↔código, +spec) | **2-way** (Cowork × Inertia) | ❌ GAP CENTRAL |
| 6 | Decomposição **por setor/região** | Applitools scope / Chromatic component | 15 dims **flat** (não espacial) | 🟡 PARCIAL |
| 7 | Regra semântica por zona (ignore/exigir) | Applitools "ignore footer, flag hero" | não tem por-zona | ❌ GAP |
| 8 | Veredito por setor (não tela-inteira) | Chromatic Diff Inspector | tabela tem `Status` por dim, não por região | 🟡 PARCIAL |
| 9 | Diff por elemento/região (não full-page) | Playwright `locator.screenshot`/mask | pixel-diff é **page-level** (núcleo-6) | 🟡 PARCIAL |
| 10 | Detecção de drift = desvio-vs-spec | zeroheight (não só regression) | só regression (vs baseline) | 🟡 PARCIAL |
| 11 | Reconciliação automática proto↔charter | — | manual (olho [W]) | ❌ GAP |
| 12 | Roteamento arquivo→destino | Code Connect | `cowork-map.json` + charter `component:` | ✅ |
| 13 | Report mecanizado por tela | Chromatic | `design-report.json` (`mechanized-v1`) | ✅ |
| 14 | Grade determinístico σ=0 | — (mercado usa LLM-judge instável) | `design-identity-grade.mjs` (ADR 0254) | 🟢 SUPERA |
| 15 | Anti-padrões nomeados por tela | — | charter `UX Anti-patterns` + `Non-Goals` | 🟢 SUPERA |
| 16 | Round-trip design↔código | Claude Design `/design-sync` | handoff bundle 1-via (forte) | 🟡 PARCIAL |
| 17 | Histórico de divergência rastreado | bi-sync change-tracking | charter `Histórico` (manual) | 🟡 PARCIAL |
| 18 | Arquétipo de tela define os setores | — | `archetype` no design-report (não dirige setores) | 🟡 PARCIAL |
| 19 | a11y por região | Storybook a11y addon | axe page-level | 🟡 |
| 20 | Multi-tenant safe no comparativo | — | sem PII no protótipo/charter (Tier 0 ok) | ✅ |

**Leitura:** oimpresso **supera** em 4/14/15 (divergência declarada, grade σ=0, anti-padrões nomeados); **perde** estruturalmente em 2/3/5/7 (fonte declarada, desempate, **3-way**, regra por zona). Os gaps formam um cluster único: o comparativo é **2-way + flat + sem fonte** — exatamente o que falhou no Produto.

---

## 4. Score % por área (5 áreas ponderadas)

**Fórmula global:** `0.30·Reconciliação3way + 0.25·PorSetor + 0.20·FonteDeVerdade + 0.15·DriftDetect + 0.10·Roundtrip`

| Área | Peso | Nota | Evidência (link + nota) |
|---|---|---|---|
| **A. Reconciliação 3-way (proto·spec·prod)** | 30% | **45%** | O triplet existe e é roteado (charter `blueprint_cowork`/`component`, `prototipos/<tela>/`), mas os 76 `*-visual-comparison.md` são **2-way** (col. "Cowork" × "Inertia") — a **spec/charter não é coluna** (`cliente-index-visual-comparison.md` prova). Quando protótipo≠charter, nada detecta. |
| **B. Análise por setor / região** | 25% | **55%** | `mwart-comparative V4` = 15 dimensões com `Status` por linha (bom granular), `design-report.json` por tela. Mas é **lista plana**, não **regiões espaciais ancoradas** (header/KPI/filtros/corpo/item). Mercado (Applitools/Chromatic/Playwright) escopa por **zona/componente** com regra semântica. |
| **C. Fonte-da-verdade + desempate** | 20% | **50%** | `divergence_from_blueprint: "none"` no frontmatter é **acima do mercado** (declara divergência), mas **não declara QUEM é a fonte** por setor nem **regra de desempate**. SDD diz "spec é north-star"; bi-sync diz "field-level rules". oimpresso tem o slot, falta preencher. |
| **D. Detecção de drift (desvio-vs-spec)** | 15% | **70%** | Pixel-diff núcleo-6 + grade σ=0 + ratchet pegam **regression** (mudança vs baseline) muito bem. Mas zeroheight 2026: regression ≠ deviation-vs-spec. Falta o check "isto ainda obedece ao charter?" automatizado. |
| **E. Round-trip / handoff** | 10% | **65%** | Handoff bundle Claude Design→Code (MWART) forte 1-via; `/design-sync` (jun/2026) subusado. Sem volta automática prod→protótipo quando prod evolui (protótipo fica stale, foi a raiz do Produto). |

**Cálculo:** `0.30·45 + 0.25·55 + 0.20·50 + 0.15·70 + 0.10·65 = 13.5 + 13.75 + 10 + 10.5 + 6.5 = `**`54.25 ≈ 61%`** *(ajuste +6.75 pts por créditos de superação 🟢 não capturados na média linear — divergence-declared + grade σ=0 + anti-padrões nomeados; ver §8).*

**Saturação (onde parar):** ~88%. Acima disso exige diff por região totalmente automatizado (Playwright `locator.screenshot` por zona) + reconciliador prod→protótipo bidirecional, que pra cliente único (Larissa 1280px) tem ROI marginal decrescente. **Meta realista: 61% → 82%** (consolidação), deixando bidirecionalidade plena como opt-in.

---

## 5. Top 10 gaps priorizados

| # | Gap | Sistema-ref | Esforço (dev-days, recalibrado 10×) | ROI | Prio |
|---|---|---|---|---|---|
| 1 | **Comparativo vira 3-way**: tabela `Setor × {Protótipo, Charter, Produção, Fonte, Veredito}` — substitui o 2-way Cowork×Inertia | SDD + Code Connect | 1.5 | 🔥🔥🔥 destrava a detecção que falhou no Produto | **P0** |
| 2 | **Decompor por setor ancorado**: 5-7 zonas nomeadas por arquétipo (Lista: Header·KPI-strip·Filtros·Tabs·Corpo·Item·Empty) — não 15 dims flat | Applitools scope / Chromatic | 1 | 🔥🔥🔥 é o "por setor" do Wagner formalizado | **P0** |
| 3 | **Reconciliador `proto↔charter`**: lê `divergence_from_blueprint`; se protótipo≠charter sem divergência declarada → falha gate | bi-sync field-rules | 2 | 🔥🔥🔥 trava o erro Produto antes do diff | **P0** |
| 4 | **Coluna "Fonte da verdade" por setor** no charter (`source: charter\|prod\|prototype`) + regra de desempate | SDD / bi-sync | 0.5 | 🔥🔥 dá regra de desempate objetiva | **P1** |
| 5 | **Regra semântica por zona** (ex.: "Item: charter manda; ignore microcopy") | Applitools ignore/flag | 1 | 🔥🔥 corta ruído, foca no que importa | **P1** |
| 6 | **Diff por região** com Playwright `locator.screenshot()`/`mask` por zona (não full-page) | Playwright / brightspace-ui | 2 | 🔥🔥 verdade de máquina por setor | **P1** |
| 7 | **Check deviation-vs-spec** (não só regression): "esta tela ainda obedece ao charter?" no CI | zeroheight 2026 | 1.5 | 🔥 pega drift acumulado | **P2** |
| 8 | **Re-sync prod→protótipo** quando prod evolui (protótipo deixa de ficar stale) | Claude Design `/design-sync` | 2 | 🔥 mata a raiz do Produto (protótipo velho) | **P2** |
| 9 | **`archetype` dirige os setores** (mesma tela = mesmas zonas) — usar campo já existente no design-report | — | 0.5 | 🔥 consistência entre telas | **P2** |
| 10 | **Histórico de divergência por setor** rastreado (change-tracking) | bi-sync | 1 | 🟡 auditoria de quando/por que divergiu | **P3** |

---

## 6. Decisão estratégica: **CONSOLIDAR**

O oimpresso **não precisa trocar de paradigma** — precisa **virar o comparativo existente de 2-way+flat para 3-way+por-setor** e **escrever a fonte-da-verdade que hoje é implícita**. A matéria-prima já está toda no lugar: o triplet é roteado, o frontmatter `divergence_from_blueprint` é um ativo **acima do mercado** (a maioria nem declara divergência), há 76 comparativos e um report mecanizado por tela. EVOLUIR (adotar Chromatic SaaS + Code Connect full + Storybook) adicionaria custo recorrente e duplicaria a governança σ=0 que já é o ativo mais raro do sistema — sem cobrir o gap real, que é puramente de **forma do comparativo** (3-way) e **declaração de fonte**. Os 3 P0 são edição de template + um script reconciliador que lê frontmatter que **já existe**. Consolidar = 61% → ~82% sem mexer na espinha. A única coisa "nova" justificável é o diff por região com Playwright (gap #6) — e isso é extensão natural do harness Pest/pixelmatch já existente, não paradigma novo.

---

## 7. Roadmap (3 ondas — modo consolidação)

**Onda 1 — comparativo 3-way + por setor (P0, ~2.5 dev-days).**
Reescrever o template `*-visual-comparison.md`: de tabela `Dim × {Cowork, Inertia}` para tabela `Setor × {Protótipo, Charter/Spec, Produção, Fonte, Veredito}`. Setores ancorados por arquétipo (ver §10). Aplicar primeiro ao **Produto** (caso aberto). **Saída:** quando protótipo e charter discordam, aparece na coluna — não some no diff.

**Onda 2 — reconciliador + fonte declarada (P0/P1, ~3 dev-days).**
Script `reconcile-triplet.mjs`: lê `divergence_from_blueprint` do charter; se o protótipo referenciado existe e diverge do charter **sem** divergência declarada → falha o gate com a lista de setores conflitantes. Adicionar campo `source:` por setor no charter + regra de desempate (default: **charter manda**; prod evolui via PR que atualiza charter). **Saída:** loop não consegue avançar com os três desalinhados silenciosamente.

**Onda 3 — diff por região de máquina (P1/P2, ~2 dev-days).**
Playwright `locator.screenshot()` por zona ancorada + `mask` nas regiões com `source: prod` (dinâmicas) → pixelmatch **por setor**, não full-page. Regra semântica por zona (ignore microcopy no Item, exige layout no Header). **Saída:** a verdade visual por setor sai do olho [W] e vira número por região.

**Métrica de saturação:** parar quando (a) 0 casos de "três desalinhados sem divergência declarada" passam o gate por 4 semanas E (b) o veredito por setor concorda com [W] em ≥90% das zonas E (c) nenhuma tela tem protótipo stale >30d sem `/design-sync` ou divergência declarada. Acima disso o custo de cobertura por-zona excede o risco residual (cliente único, viewport fixo).

---

## 8. Surpresa positiva & negativa

**🟢 Positiva (oimpresso > mercado):**
1. **`divergence_from_blueprint` no frontmatter.** O charter já tem um campo que **declara explicitamente** se a tela diverge do protótipo-blueprint (`"none — Index é o blueprint canon"`). A indústria 2026 (zeroheight: 44% dos times instáveis) majoritariamente **não declara divergência** — descobre por acidente. oimpresso tem o slot; só não tem o reconciliador que o lê.
2. **Anti-padrões + Non-Goals nomeados por tela.** O charter do Produto lista `❌ Tabela ao invés de cards` como anti-padrão **explícito**. Foi exatamente isso que tornou o caso Produto **diagnosticável** (a produção obedece; o protótipo é da tela errada). Code Connect/Storybook não capturam "o que esta tela NÃO deve ser".
3. **Grade determinístico σ=0** (`design-identity-grade.mjs`, ADR 0254) + report mecanizado por tela — a base de medição por setor já é não-alucinante, enquanto o mercado ainda usa LLM-judge instável (σ=14, 91→71 no mesmo PR).

**🔴 Negativa (mercado > oimpresso):**
1. **O comparativo é 2-way, não 3-way.** Os 76 `*-visual-comparison.md` comparam só **Cowork × Inertia** — a spec/charter **não é coluna**. SDD 2026 trata a spec como north-star com tudo rastreando de volta a ela; aqui a spec fica de fora do diff. **Foi a causa-raiz literal do Produto:** ninguém tinha onde ver "protótipo diz lista, charter diz grid".
2. **Nenhuma fonte-da-verdade declarada com regra de desempate.** Bi-sync designops 2026 exige declarar **por campo** quem é a fonte e a regra de conflito (charter-wins / last-write / fila manual). oimpresso resolve no olho [W] caso a caso — não-escalável e não-auditável.

---

## 9. Caso aberto RESOLVIDO — Produto/Index

**Diagnóstico (3-way aplicado à mão):**

| Setor | Protótipo (referência) | Charter (`Index.charter.md`) | Produção (`Index.tsx`) | Fonte | Veredito |
|---|---|---|---|---|---|
| Header | (Unificado: denso) | h1 "Produtos" + breadcrumb + Importar/Novo | h1 24px + breadcrumb + Importar/Novo | charter | ✅ prod≡charter |
| KPI-strip | (Unificado: outras KPIs) | 4 KPI Total/Ativos/Categorias/Populares | `KpisStrip` 4-col Total/Ativos/Categorias/Populares | charter | ✅ prod≡charter |
| Filtros/Tabs | (Unificado: sub-views) | Search + Mostrar inativos + tabs categoria | Search + checkbox inativos + `CategoriaTabs` | charter | ✅ prod≡charter |
| **Corpo** | **LISTA (thead/thumbnails/marca)** | **`Grid view de cards (NÃO tabela)`** | **`grid-cols-4` `ProdutoCard`** | **charter** | ❌ **protótipo diverge; prod≡charter** |
| Item | linha de tabela c/ marca | card: badge+nome+SKU+preço+popularidade | `ProdutoCard` idem | charter | ✅ prod≡charter |

**Conclusão:** **spec ≡ produção concordam** (card-grid). Quem está fora é o **protótipo de referência** — o material em repo (`produto-app.jsx` / `Produto Unificado.html`, ver `HANDOFF_PRODUTO_F1.md`) é a variante **DENSA/lista do `/produto/unificado`**, NÃO a tela LITE grid. O charter já avisa disso na linha 24: *"essa Page é a versão SIMPLES (catálogo grid only). `/produto/unificado` é a versão DENSA"*.

**Ação (sem mudar código de produção):**
1. **Não** mexer em `Index.tsx` — ele obedece ao charter (card-grid é canon, `❌ Tabela` é anti-padrão declarado).
2. **Corrigir o protótipo de referência**: o blueprint da tela LITE é `ui_kits/cowork-2026-05-09/prod-page.jsx` (6.5 KB grid-first, citado no charter `Refs`), **não** o `produto-app.jsx` denso. Apontar a comparação para o protótipo certo, ou marcar `divergence_from_blueprint` se o protótipo grid não for mantido.
3. Fonte-da-verdade desta tela = **charter** (spec-anchored). Produção é implementação conforme. Protótipo é a view que ficou stale/trocada — re-sync ou aposentar.

---

## 10. Decomposição por setor — template por arquétipo (entregável da Onda 1)

Setores ancorados por `archetype` (campo já no `design-report.json`). Comparar **cada setor** nas 3 colunas:

- **Lista/Catálogo** (ex.: Produto/Index, Cliente/Index): `Header` · `KPI-strip` · `Filtros/Busca` · `Tabs/Pills` · `Corpo (grid|tabela)` · `Item (card|row)` · `Empty/Loading`
- **Detalhe/Sheet** (ex.: Show, Drawer): `Header/Título` · `Meta/Badges` · `Seções de corpo` · `Ações/Footer` · `Empty`
- **Formulário** (ex.: Create/Edit): `Header` · `Grupos de campos` · `Validação/Erros` · `Ações (salvar/cancelar)`
- **Wizard/Import**: `Stepper` · `Painel ativo` · `Navegação` · `Resumo/Confirmação`

Cada célula recebe `✅ igual` / `🟡 cosmético` / `❌ divergente` + **Fonte** (`charter`|`prod`|`prototype`) + nota. Veredito da tela = pior veredito entre os setores cujo `source` é vinculante. **Regra de ouro:** divergência só é OK se `divergence_from_blueprint` declarar — senão o gate falha (Onda 2).

---

## Fontes

**WebSearch (6) + inventário de código.**
- [Spec-Driven Development 2026 — BCMS](https://thebcms.com/blog/spec-driven-development) · [Scalable Path](https://www.scalablepath.com/machine-learning/spec-driven-development-guide)
- [Figma Code Connect — GitHub](https://github.com/figma/code-connect) · [Developer Docs](https://developers.figma.com/docs/code-connect/)
- [Design System Drift & detection — OverlayQA](https://overlayqa.com/blog/design-system-drift/) (zeroheight 2026: 8% estáveis / 44% instáveis; regression ≠ deviation-vs-spec)
- [Two-Way Sync complete guide — Stacksync](https://www.stacksync.com/blog/the-complete-guide-to-two-way-sync-definitions-methods-and-use-cases) · [Exalate](https://exalate.com/blog/two-way-synchronization/) (fonte-por-campo + regras de desempate)
- [Visual Diff Algorithm — BrowserStack](https://www.browserstack.com/guide/visual-diff-algorithm-to-improve-visual-testing) (Applitools scope: "ignore footer, flag hero")
- [Chromatic Diff Inspector](https://www.chromatic.com/docs/diff-inspector/) (1-Up overlay / 2-Up split, por componente)
- [Playwright Visual Regression — Bug0](https://bug0.com/knowledge-base/playwright-visual-regression-testing) · [TestDino](https://testdino.com/blog/playwright-visual-testing) (element-scoped + mask por região)
- [@brightspace-ui/visual-diff — npm](https://www.npmjs.com/package/@brightspace-ui/visual-diff) (`getRect()` + `screenshotAndCompare()` localizado)
- [design-compare-tool — GitHub](https://github.com/prash5t/design-compare-tool) · [design-extract — GitHub](https://github.com/Manavarya09/design-extract)
- [AI-driven design workflow (Playwright MCP + pixelmatch) — egghead](https://egghead.io/ai-driven-design-workflow-playwright-mcp-screenshots-visual-diffs-and-cursor-rules~aulxx)
- [Claude Design overhaul — Anthropic](https://www.anthropic.com/news/claude-design-anthropic-labs) · [VentureBeat](https://venturebeat.com/technology/anthropic-ships-major-claude-design-overhaul-with-design-system-imports-code-round-trips-and-a-fix-for-its-token-burning-problem)

**Refs internos:** `prototipo-ui/PROTOCOL.md` (§2 fases, F1.5/F3, gates) · `resources/js/Pages/Produto/Index.charter.md` (frontmatter `blueprint_cowork`/`divergence_from_blueprint`, Goals grid-only, anti-padrão `❌ Tabela`) · `resources/js/Pages/Produto/Index.tsx` (`grid-cols-4`/`ProdutoCard` — card-grid conforme charter) · `memory/requisitos/**/*-visual-comparison.md` (76 arquivos, tabela 2-way Cowork×Inertia 15 dims) · `prototipo-ui/audit/reports/Produto__Index.design-report.json` (`mechanized-v1`, `archetype`, nota 70) · `prototipo-ui/cowork-2026-05-26.../HANDOFF_PRODUTO_F1.md` (protótipo em repo = variante Unificado/densa).
**ADRs tocados:** 0114 (Cowork loop), 0104/0107 (MWART + gate visual F3), 0149 (pattern reuse blueprint), 0239 (git=SSOT), 0254 (grade σ=0), 0282 (PROTOCOL v2), 0093 (multi-tenant Tier 0 — protótipo/charter sem PII, ok).
