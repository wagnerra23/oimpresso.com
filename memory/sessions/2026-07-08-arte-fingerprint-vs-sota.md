# Estado da arte — `style-fingerprint.mjs` vs. os melhores do mundo em visual-diff / design-to-code fidelity

> **Tipo:** estado-da-arte (pesquisa + nota + roadmap). **NÃO é fix de código.** O sweep de cor que a máquina achou tem chip próprio (`task_73b3a834`).
> **Data:** 2026-07-08. **Base lida:** `origin/main` fresco (checkout local estava −4909; canon lido via `git show origin/main:`).
> **Fontes do NOSSO:** [`prototipo-ui/style-fingerprint.mjs`](../../prototipo-ui/style-fingerprint.mjs) (selftest 18/18) + handoff [2026-07-08-1044](../handoffs/2026-07-08-1044-financeiro-fidelidade-fingerprint-furos.md) + session [2026-07-08 fidelidade](2026-07-08-financeiro-fidelidade-fingerprint-protocolo.md).
> **Companion:** este doc é o benchmark; a narrativa de como o fingerprint virou mecanismo está na session acima. 1 tema = 1 doc (não dupliquei).

---

## TL;DR (a nota e o veredito honesto)

- **Nota do nosso, rubrica ponderada (fidelidade design→código, 19 dimensões): ~58/100.**
- **Líder single mais próximo (Applitools Eyes, DOM+CSS-aware): ~92/100.** Teto best-in-class (o melhor de cada mundo somado): **~95/100.**
- **Re-pesando SÓ pro trabalho que ele foi feito ("essa uma tela está fiel ao protótipo aprovado, nos 2 temas, sem infra de baseline, com relatório legível campo-a-campo"): o nosso sobe pra ~72** e a distância pros líderes encolhe — porque nesse recorte as vantagens deles (nuvem, cross-browser, colaboração) não contam, e as NOSSAS (sem baseline-imagem, diff semântico legível, portátil, dual-tema) pesam.
- **Onde GANHAMOS:** técnica-núcleo. Somos *semânticos + sem baseline-imagem + portáteis + auto-provados* — à frente do pelotão pixel-diff open-source (Playwright/BackstopJS/Lost Pixel/reg-suit) exatamente nos eixos que importam pra fidelidade. O paper 2026 de VRT ([arXiv 2607.01728](https://arxiv.org/html/2607.01728v1)) mede: pixel-diff suprime **6,72%** do ruído não-significativo; abordagem semântica suprime **85–97%**. Nós somos semânticos por construção.
- **Onde PERDEMOS:** **cobertura de dimensões visuais**, não a técnica. Cegos a sombra/elevação, padding/margin, `justify-content`/`gap` (a *causa* do layout), estados (hover/focus), ícone/SVG, e responsivo (1 viewport/run). É por isso que "o retângulo atrás do filtro" só apareceu como *delta de cor*, nunca como *"falta uma camada"*.
- **Conclusão estratégica:** não devemos virar um Applitools (VRT de nuvem, cross-browser, pixel-baseline, app-genérico). Devemos virar o **melhor linter de fidelidade design→código** — e a fronteira 2026 pra isso (captioning semântico do arXiv + RCA DOM/CSS do Applitools) é justamente o que já *parecemos em miniatura*. O gap é **somar eixos ao vetor**, não trocar a técnica.

---

## 1. O SOTA 2026 — como os melhores fazem

### 1.1 O espectro de técnicas (do mais burro ao mais esperto)

| Técnica | Quem representa | Como funciona | Força | Fraqueza |
|---|---|---|---|---|
| **Pixel-diff puro** | BackstopJS, Lost Pixel, reg-suit, Playwright `toHaveScreenshot` | Compara PNG baseline × atual pixel-a-pixel (pixelmatch/ODiff), `misMatchThreshold`/`maxDiffPixels` manual | Vê **tudo** que renderiza (sombra, ícone, gradiente, layout) sem saber o que é | **Flake** de anti-aliasing/fonte/sub-pixel; só diz *onde* mudou (máscara), não *o quê*; exige baseline-imagem gerido |
| **DOM-snapshot + render controlado** | Percy (BrowserStack) | Captura o DOM+CSS no seu ambiente, **re-renderiza na nuvem do Percy** (mata flake de ambiente), depois pixel-diff + filtro AI | Reduz flake controlando o render; cross-browser/device nativo | Ainda pixel no fim; precisa SDK+nuvem |
| **Perceptual / Visual AI** | **Applitools Eyes** | CV treinado em **4B+ telas reais** imita olho+cérebro: ignora ruído (anti-alias, sub-pixel), acusa só o que humano percebe. **Root Cause Analysis** guarda DOM+CSS junto do checkpoint e mostra o diff de tag/id/classe/bbox/dimensão/texto/propriedade-CSS. Self-healing locators | Menos falso-positivo; **RCA já é semântico** (mostra a propriedade CSS que mudou) | Caixa-preta cara; abstrai você do pixel; baseline+nuvem |
| **Component-anchored (git+deps)** | **Chromatic + Storybook** (TurboSnap) | Cada *story* vira teste visual; TurboSnap lê git history + grafo de dependências e só re-snapa componentes que mudaram (corta 40–60% do custo). Diff **pixel** | Âncora forte (componente, não página); rápido; estados via interaction stories | Precisa Storybook; diff ainda pixel (falso-positivo de fonte) |
| **Semantic change captioning** (fronteira acadêmica) | arXiv 2607.01728 "Beyond Pixel Diffs" (2026) | Gera **descrição em linguagem natural** da mudança (IDC/Mamba/Transformer treinado em 9.906 amostras, taxonomia de 37 regras: 9 significativas × 3 não-significativas) | Suprime **85–97%** do ruído mantendo **93–96%** de detecção real (pixel: **6,72%** de supressão) | Acurácia absoluta ainda modesta (BLEU-4 0,26); VLMs zero-shot trocam sensibilidade por especificidade |
| **Design→code por token** | Figma **Code Connect** + Dev Mode + **DTCG** + **Style Dictionary** | Mapeia componente Figma 1:1 ao React; tokens exportam DTCG (padrão W3C estável fim-2025, ~24 grandes) → Style Dictionary → CSS/Tailwind. Dev Mode mostra snippet de produção | Fecha o loop design↔código **na fonte** (não no pixel) | Não *mede* fidelidade do render; AI ainda "hardcoda cor que devia ser token, mistura convenções" — **exatamente a dor que o nosso mede** |

### 1.2 Os quatro consensos de 2026 (o que "bom" quer dizer hoje)

1. **Semântico > pixel.** O diferenciador é quão bem a ferramenta separa regressão real de ruído de render. Pixel puro perdeu; ou você tem AI perceptual (Applitools/Percy) ou diff estrutural (RCA / captioning).
2. **Âncora importa.** Component-anchored (Chromatic/TurboSnap) e text/DOM-anchored batem posicional/por-classe, que "não existe" entre DOMs diferentes.
3. **Baseline é dívida.** Todo mundo com baseline-imagem paga gestão de baseline (aprovação, regeneração, flake por ambiente). Percy re-renderiza pra mitigar; a alternativa é **não ter baseline** (o nosso caminho).
4. **A saída tende a virar linguagem.** RCA (Applitools) já lista *a propriedade que mudou*; o arXiv aponta a NL. O futuro não é uma máscara vermelha, é *"superfície+borda ficaram frias sistematicamente"*.

---

## 2. O NOSSO — o que ele JÁ é

`style-fingerprint.mjs` é um **comparador semântico de fidelidade protótipo×produção, sem baseline-imagem, injetável em qualquer página renderizada**. Não é um VRT genérico — é um **linter de fidelidade design→código de uma tela**, dual-tema.

**Núcleo (o que já cobre bem):**
- **Diff semântico por elemento** (não pixel): vetor de 14 campos (`tag,w,h,xnorm,linhas,overflowX,fontSize,fontWeight,color,bgEfetivo,radius,borderW,borderColor,display`), casado por **texto normalizado + tag**.
- **Sem baseline-imagem:** roda proto×prod **ao vivo** (snippet injetável no console/MCP/playwright), zero golden-image, **zero flake de rendering**.
- **Duas passadas honestas sobre o limite:** (1) elementos com texto; (2) **divisórias/bordas sem texto** (furo 1) inventariadas por lado+cor+espessura+span → linha/régua *consta*.
- **`normTexto` strippa glifos de UI** (⇅ ↑↓ chevrons — furo 2): header "Vencimento⇅" (prod) pareia com "Vencimento" (proto) em vez de forkar a chave e sumir com o diff.
- **`xnorm`** (furo 6): posição horizontal como fração da largura → mesmo elemento em lugar diferente vira **DIVERGE**, mata o "IDENTICO mentiroso" de alinhamento.
- **`resumoCampos`** — **auto-diagnóstico**: histograma que NOMEIA a propriedade dominante + flag **⚠ SISTEMÁTICO** (≥70%). No real cuspiu `bgEfetivo 56/57 + borderColor 56/57 SISTEMÁTICO` sozinho.
- **Selftest hermético 18/18** — comparador provado pelos DOIS lados (L-31): divergências plantadas (2-linhas, cor, radius, só-de-um-lado, glifo, divisória recolorida, xnorm). **A maioria das VRT do mercado não auto-prova o próprio comparador.**

**Posição no espectro §1.1:** somos um híbrido **DOM/semantic-diff + component/text-anchored + no-baseline**, com um embrião de **captioning** (`resumoCampos` nomeia o padrão). Estamos na coluna certa da história (semântico), só rasos em cobertura.

---

## 3. O que o NOSSO AINDA NÃO cobre (checklist de gaps — mapeado 2026-07-08)

**Furos abertos catalogados:**
- **nº3** — compostos filtrados por `childElementCount <= 2`: cards/linhas com >2 filhos escapam do vetor de texto.
- **nº4** — chave ambígua: `<BRL>`/`<N>`/`<DATA>` normalizados colam elementos distintos → casa par errado (precisão).
- **nº5** — `SO_PROTO`/`SO_PROD` não forçam triagem: um elemento não-casado é sinal de fidelidade, mas hoje passa em silêncio.

**Eixos nunca capturados (o vetor não tem o campo):**
- **Posição VERTICAL (y) / ordem** — só `xnorm`; metade da geometria é invisível, regressão de ordem não aparece.
- **`box-shadow` / elevação** — nada. É *a* razão do "retângulo atrás do filtro" ter virado só delta-de-cor, não "falta camada".
- **Gradiente / `background-image`** — `bgEfetivo` só lê cor sólida.
- **`justify-content` / `gap` / `flex-direction` do container** — a **CAUSA** do layout, não o sintoma.
- **Estados** hover/focus/active — snippet estático não força pseudo-classe.
- **Tipografia fina** — `letter-spacing`, `line-height`, `text-transform`, `font-family` (só temos size/weight).
- **`padding` / `margin` interno** — zero (afeta densidade de painel diretamente).
- **`opacity` / `transform`.**
- **Ícone / SVG / sparkline sem texto nem borda** — declarado fora (sem âncora).
- **Responsivo** — 1 viewport por run.
- **Conteúdo colapsado/off-screen, `z-index`, animação.**

**A nuance da "superfície":** o `bgEfetivo` faz *walk-up* até achar cor não-transparente — então **não distingue painel PRÓPRIO de fundo herdado**. Sem `box-shadow` não vê elevação. Resultado: enxerga *"a cor diferiu"* mas nunca *"falta uma camada de painel"*.

---

## 4. Tabela de dimensões — NÓS × best-in-class

Peso = relevância pro trabalho de **fidelidade design→código** (soma 100). Score 0-100. "Líder" = melhor que *qualquer* ferramenta atinge naquele eixo.

| # | Dimensão | Peso | **Nós** | Líder (quem) | Contrib. nossa |
|---|---|---:|---:|---|---:|
| 1 | Primitiva de diff (semântico vs pixel) | 8 | **85** | 92 (Applitools/captioning) | 6,80 |
| 2 | Resistência a ruído/flake | 7 | **95** | 90 (Applitools AI) | 6,65 |
| 3 | Legibilidade da saída (campo→campo, nomeia padrão) | 7 | **88** | 90 (captioning NL) | 6,16 |
| 4 | Root-cause / diagnóstico | 4 | **80** | 92 (Applitools RCA) | 3,20 |
| 5 | Cor / superfície | 7 | 68 | 100 (pixel vê tudo) | 4,76 |
| 6 | Borda / divisória | 5 | **85** | 100 | 4,25 |
| 7 | Tipografia fina | 6 | 55 | 100 | 3,30 |
| 8 | Geometria de layout (x/y/ordem/justify/gap) | 9 | 40 | 100 | 3,60 |
| 9 | Elevação / sombra / gradiente | 5 | 5 | 100 | 0,25 |
| 10 | Espaçamento (padding/margin) | 6 | 5 | 100 | 0,30 |
| 11 | Estados (hover/focus/active) | 5 | 5 | 90 (Storybook interaction) | 0,25 |
| 12 | Ícone / SVG / sparkline | 4 | 5 | 100 | 0,20 |
| 13 | Responsivo / multi-viewport | 6 | 25 | 100 (Percy/Applitools) | 1,50 |
| 14 | Dual-tema (light+dark) | 4 | **90** | 92 | 3,60 |
| 15 | Baseline mgmt / no-baseline live | 4 | **92** | 90 (baseline gerido) | 3,68 |
| 16 | Âncora (component/text) | 4 | 68 | 92 (Chromatic TurboSnap) | 2,72 |
| 17 | Portabilidade / self-contained | 3 | **95** | 70 (Playwright/OSS) | 2,85 |
| 18 | CI / nuvem / colaboração / histórico | 3 | 25 | 97 (Applitools/Percy) | 0,75 |
| 19 | Determinismo / auto-prova (selftest) | 3 | **92** | 80 | 2,76 |
| | **TOTAL** | **100** | | | **≈ 57,6** |

**Leitura:**
- **Nós ≈ 58/100** (rubrica cheia). **Teto best-in-class ≈ 95.** **Applitools ≈ 92** (líder single mais próximo — DOM+CSS-aware como nós, mas com nuvem + pixel-perceptual + cobertura cheia).
- Ordem aproximada dos líderes single nessa rubrica: **Applitools ~92 · Percy ~88 · Chromatic ~82 · Playwright/BackstopJS/Lost Pixel ~66-68 · reg-suit ~65.** **Já estamos colados no pelotão pixel-diff open-source** — perdemos em cobertura, ganhamos em técnica/portabilidade/auto-prova.

**Re-pesando pro trabalho real** (linter de 1 tela: zera CI-nuvem-colaboração e responsivo-nativo; dobra semântico-legível, no-baseline, portátil, dual-tema, determinismo): **Nós ≈ 72.** O gap que sobra é **puro coverage** (sombra/espaço/causa-de-layout) — que é *somável* ao vetor, barato.

---

## 5. Onde GANHAMOS × onde PERDEMOS

### ✅ Ganhamos (e devemos defender)
1. **Zero flake de rendering (dim. 2 = 95).** Sem baseline-imagem, não existe falso-positivo de anti-aliasing/fonte/sub-pixel — a dor operacional nº1 de todo pixel-diff (BackstopJS/Chromatic sofrem; Percy gasta nuvem re-renderizando pra mitigar). Nós **sidestepamos por construção**.
2. **Portátil e self-contained (dim. 17 = 95).** Snippet injetável roda em *qualquer* página renderizada (console, MCP, playwright), zero SDK/infra. Applitools/Percy exigem SDK+conta+nuvem.
3. **Saída legível + nomeia o padrão (dim. 3 = 88).** `resumoCampos` + `⚠ SISTEMÁTICO` entrega diagnóstico ("superfície+borda são o erro") em CLI, sem dashboard. É a *direção* do arXiv/RCA, em miniatura e grátis.
4. **Auto-provado (dim. 19 = 92).** Selftest hermético 18/18 prova o comparador pelos dois lados. Raro no mercado — a maioria confia que o diff-engine está certo.
5. **Dual-tema de primeira classe (dim. 14 = 90).** Roda por tema por design; dark não é "só mais um baseline".
6. **No-baseline live proto×prod (dim. 15 = 92).** Compara o design *aprovado vivo* contra prod *vivo* — nicho que nenhum líder pixel-baseline serve bem.

### ❌ Perdemos (o roadmap ataca isto)
1. **Geometria/causa de layout (dim. 8 = 40)** — só `xnorm`; sem `y`/ordem e sem `justify-content`/`gap`. Vemos o sintoma, não a causa (o caso "retângulo/alinhamento").
2. **Elevação/sombra + espaçamento + estados + ícone (dims. 9-12 ≈ 5)** — pontos cegos totais.
3. **Tipografia fina (dim. 7 = 55)** — letter-spacing/line-height/transform/family fora.
4. **Cor/superfície rasa (dim. 5 = 68)** — walk-up não separa painel próprio de fundo herdado → "cor difere", nunca "falta camada".
5. **Responsivo (dim. 13 = 25)** e **CI/colaboração (dim. 18 = 25)** — 1 viewport/run, sem dashboard/aprovação/histórico (aceitável hoje; escala mal com time).

---

## 6. Roadmap impacto × esforço — pra ser o melhor *no que ele é*

Princípio: **o gap é coverage, não técnica.** Quase tudo abaixo é *somar campo ao vetor* — o snippet já anda o `getComputedStyle`, adicionar propriedade é barato. Ordenado por mordida em fidelidade ÷ esforço.

### 🌊 Onda 1 — "campos baratos" (horas cada, zero infra nova; alvo 58→~72)
*Add ao vetor `CAMPOS` + espelho no SNIPPET + selftest plantado. Cada um é 1 propriedade computada + tolerância.*

| Item | Eixo | Impacto | Esforço | Por quê agora |
|---|---|:--:|:--:|---|
| **`box-shadow` / elevação** | 9 | 🔴 alto | 🟢 baixo | Fecha o "falta camada" — a classe de miss que gerou o furo 1. **A maior mordida por real.** |
| **`padding`/`margin` interno** | 10 | 🔴 alto | 🟢 baixo | Densidade de painel é fidelidade direta; 4 props computadas. |
| **superfície própria vs herdada** (flag) | 5 | 🔴 alto | 🟢 baixo | Captura `backgroundColor` PRÓPRIO separado do `bgEfetivo` walk-up → "falta camada" vira dizível. Pareia com box-shadow (própria+shadow+borda ⇒ "é painel"). |
| **furo 5 — forçar triagem de `SO_*`** | — | 🟠 médio-alto | 🟢 baixo | Um não-casado é sinal, não silêncio. Muda relatório/exit-code, sem novo campo. |
| **furo 4 — desambiguar chave `<BRL>`/`<N>`** | — | 🟠 médio | 🟡 baixo-médio | Tiebreak posicional (bucket `xnorm`/ordinal) entre candidatos de mesma chave → mata par errado. |
| **tipografia fina** (`letter-spacing`,`line-height`,`text-transform`,`font-family`) | 7 | 🟠 médio | 🟢 baixo | 4 props; sobe precisão sem novo conceito. |
| **gradiente/`background-image`** (flag has-gradient) | 5 | 🟠 médio | 🟢 baixo | Ao menos ACUSA "sólido × gradiente" em vez de mentir cor sólida. |
| **`ynorm` (posição vertical)** | 8 | 🟠 médio-alto | 🟢 baixo | Espelha o `xnorm` — completa metade faltante da geometria de elemento. |
| **`opacity`/`transform`** | — | 🟡 baixo-médio | 🟢 baixo | Barato, fecha ponto cego pequeno. |

### 🌊 Onda 2 — "causa do layout + compostos" (precisa passada de container; alvo ~72→~80)

| Item | Eixo | Impacto | Esforço | Por quê |
|---|---|:--:|:--:|---|
| **passada de container** (`display` flex/grid, `justify-content`, `align-items`, `gap`, `flex-direction`) | 8 | 🔴 alto | 🟡 médio | Move de *sintoma* ("cor difere") pra *CAUSA* ("falta gap/justify"). Espelha a 2ª passada das divisórias, mas mira containers, não bordas. |
| **furo 3 — passada de composto/card** (>2 filhos) | 3 | 🟠 médio | 🟡 médio | Cards/linhas hoje escapam do filtro `childElementCount<=2`. Vetor próprio de composto (bbox+superfície+borda+shadow), casado por texto-agregado. |

### 🌊 Onda 3 — "driver + a cauda anancorada" (estrutural; alvo ~80→~88, teto prático do nicho)

| Item | Eixo | Impacto | Esforço | Por quê |
|---|---|:--:|:--:|---|
| **harness Playwright/MCP** (captura proto×prod, N viewports × 2 temas × estados forçados) | 13, 11 | 🔴 alto | 🟡 médio-alto | **O desbloqueio estrutural:** transforma o snippet-de-console em captura automatizada. Só ele destrava responsivo (loop de viewport) + estados (força `:hover/:focus` e re-captura) + repetibilidade/CI. |
| **backstop perceptual (SSIM) SÓ em regiões anancoradas** | 12 | 🟠 médio | 🔴 alto | Ícone/SVG/sparkline sem texto nem borda: recorta pela bbox e faz um diff perceptual *só ali*, tolerância alta. É o **híbrido que Applitools e o arXiv apontam**: semântico nos 95% ancorados, perceptual na cauda. Escopo apertado pra não reintroduzir flake. |
| **captioning-lite (verdito em NL)** | 3 | 🟠 médio | 🟢 baixo-médio | Estende `resumoCampos` de "campo dominante" pra uma linha ("superfície+borda frias sistemáticas; 1 camada de elevação ausente no filtro"). Barato porque já temos o diff estruturado — é a fronteira 2026 quase de graça. |
| **CI gate / histórico / aprovação** | 18 | 🟡 baixo (hoje) | 🟡 médio | **De-priorizado**: só vale quando o time MCP escalar o uso além do turn-a-turn do Wagner. Anotado, não urgente. |

### Sequência recomendada
**Onda 1 primeiro, inteira** (mordida máxima, custo mínimo, zero infra) → mede de novo com `--compare` real → **Onda 2** (causa de layout, o próximo "por que não pegou?") → **Onda 3** só quando o harness Playwright justificar o investimento (responsivo/estados/CI juntos). O item que **mais move fidelidade de painel agora**: **box-shadow + padding + superfície-própria** (Onda 1). O que **mais move precisão**: **furo 4 + tipografia fina**. O que **mais move cobertura**: **harness (responsivo+estados)** na Onda 3.

### ⚠️ Elo com o sweep de cor (`task_73b3a834`) — ordem importa
O sweep de superfície+borda que a máquina achou (`bgEfetivo 56/57 + borderColor 56/57 SISTEMÁTICO`) vai mexer **exatamente no ponto cego de elevação**. Se o sweep rodar **antes** de `box-shadow` + `superfície-própria-vs-herdada` (Onda 1) entrarem no vetor, o fingerprint valida *"cor certa"* mas segue **cego a "falta camada"** — dá para achatar/inflar um painel e o comparador retornar OK. **Ordem correta: box-shadow + superfície-própria ANTES ou JUNTO do sweep**, senão o sweep voa no escuro no eixo que motivou o furo 1 (o "retângulo atrás do filtro" só apareceu como delta-de-cor porque a elevação não é medida). Os demais eixos-B (animação, z-index, responsivo-num-run) são cauda longa e não bloqueiam o sweep.

---

## 7. Fontes

- [Applitools Eyes — Visual AI & plataforma](https://applitools.com/platform/eyes/) · [Root Cause Analysis (docs)](https://applitools.com/docs/eyes/concepts/reviewing-tests/root-cause-analysis)
- [Percy — AI in Visual Testing 2026](https://percy.io/blog/ai-in-visual-testing) · [10 Best VRT Tools](https://percy.io/blog/visual-regression-testing-tools)
- [Chromatic — TurboSnap (docs)](https://www.chromatic.com/docs/turbosnap/) · [Storybook — Visual tests](https://storybook.js.org/docs/writing-tests/visual-testing)
- [Lost Pixel (OSS alt. a Percy/Chromatic/Applitools)](https://github.com/lost-pixel/lost-pixel) · [Crosscheck — VRT Tools 2026 ranked](https://crosscheck.cloud/blogs/best-visual-regression-testing-tools-2026/)
- [**arXiv 2607.01728 — "Beyond Pixel Diffs: Image Change Captioning for Web UI VRT" (2026)**](https://arxiv.org/html/2607.01728v1) — a fonte-chave que quantifica semântico (85–97% supressão de ruído) vs pixel (6,72%).
- [Figma Dev Mode / Code Connect](https://www.figma.com/dev-mode/) · [Figma Design Tokens + DTCG + Style Dictionary (2026)](https://atomize.tools/blog/figma-design-tokens-guide/)

---

## 8. Uma linha pra guardar

> Estamos na **coluna certa da história** (semântico, sem baseline, portátil, auto-provado) — colados no pelotão pixel-diff OSS e à frente dele na técnica. **O que falta pra ser o melhor no nicho é somar eixos ao vetor** (sombra, espaço, causa-de-layout), não trocar a máquina. Onda 1 sozinha nos leva de ~58 a ~72 em horas.
