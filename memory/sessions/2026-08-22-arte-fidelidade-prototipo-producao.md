# Estado da arte 2026 — fidelidade design → produção

**Data:** 2026-08-22 · **Agente:** `estado-da-arte` · **Escopo:** EXTERNO (mercado, engenharia, literatura). Inventário do repo oimpresso é de outro agente — este doc **não** o duplica.

**Pergunta do [W]:** *"Alguém consegue reproduzir FIEL o protótipo em produção? Como?"*

---

## 0. Método e limite da evidência (leia antes de citar qualquer número daqui)

- Toda a coleta saiu de **WebSearch**. `WebFetch` e `curl` foram **bloqueados pelo egress proxy** nesta sessão em 100% das tentativas (smashingmagazine, help.figma.com, arxiv.org, engineering.atspotify.com, developers.mews.com, chromatic.com). Consequência honesta: **não abri fonte primária nenhuma** — os números abaixo vêm de *snippets* de busca citando as fontes. Nível de confiança: bom para "existe e diz X", fraco para transcrição literal.
- Classifiquei cada evidência em: **[V]** claim de vendor · **[E]** relato de engenharia (blog de time que opera o sistema) · **[P]** paper/benchmark · **[T]** terceiro independente · **[?]** não verificado.
- Onde não achei evidência, escrevi **"não achei evidência"**.

---

## 1. Alguém consegue mesmo? (separando marketing de medição)

| Quem | O que afirma | Tipo | Veredito |
|---|---|---|---|
| **Builder.io Visual Copilot** | "pixel-perfect accuracy, ajusta a todos os tamanhos de tela" | **[V]** | Claim de marketing. Nenhuma medição publicada. |
| **Locofy** | "Figma to React: pixel perfect, high-quality code" (página de produto) | **[V]** | Idem. |
| **Anima** | conversão fiel Figma→código | **[V]** | Reviews de terceiros dizem que serve melhor como **QA de design** do que como gerador de produção **[T]** |
| **Reviews independentes** (figr.design, sitegrade.io, pixelperfecthtml — 2025/2026) | testaram Locofy × Builder.io × Anima nos **mesmos** arquivos Figma: *"nenhum entrega a promessa pixel-perfect production-ready"*; Locofy erra layout responsivo e usa px fixo onde deveria ser unidade relativa; qualidade do Anima é proporcional à limpeza do arquivo Figma | **[T]** | Convergente entre 3 fontes. Mas são blogs de agência/SEO — **não é medição instrumentada**. O número "20-40% de refino manual" que circula tem **origem fraca [?]**. |
| **Design2Code** (arXiv 2403.03163, mar/2024, 484 páginas reais) | GPT-4V: anotadores acharam a página gerada **substituível pela original em 49% dos casos** e **melhor que a original em 64%**; GPT-4o hoje: Block-Match 93,0 · Text 98,2 · Position 85,5 · Color 84,1 · CLIP 90,4 | **[P]** | A melhor medição pública de "copiar imagem → código". Note: **"substituível" ≠ "fiel"** — o julgamento é de aceitabilidade, não de identidade. Degrada forte com complexidade de HTML e conteúdo não-inglês. |
| **UI-Bench** (arXiv 2508.20410, ago/2025) | 10 ferramentas, 30 prompts, 300 sites, **4.000+ julgamentos de especialistas**, ranking TrueSkill | **[P]** | Mede **excelência visual**, não fidelidade a um protótipo dado. E **exclui explicitamente** a11y, performance e qualidade de código. Snapshot de ago/2025. |
| **Pinterest / Gestalt** | criou a métrica **"design adoption"** (tool interno FigStats) porque adoção-em-código não bastava; 85 componentes code-backed; exemplo publicado de tela onde **apenas 1% do design era Gestalt** | **[E]** | O relato mais honesto que achei: um time maduro **publicando o próprio número ruim**. |
| **Spotify / Encore** | coleta estatística **diária** de qual time usa qual versão da lib; Backstage tem "Check Insights" de conformidade com o DS; exceção a padrão de produção exige aprovação formal | **[E]** (Spotify Engineering, mai/2023) | Mede **adoção e versão**, não fidelidade visual a um mockup. |
| **Airbnb DLS** | site interno navega componentes com **screenshots renderizados do código de produção**, cada um linkado ao Git | **[E]** secundário | Inversão importante: a **doc deriva do código**, não o código do mockup. Não achei o post primário aberto. |
| **Shopify Polaris** | `polaris-migrator` (codemod) converte valores hardcoded → tokens; a doc **admite** que parte da migração exige hardcode manual via mapa de substituição | **[E]** | Prova de que mesmo o dono do DS não fecha 100% por máquina. |
| **Google/Material, Atlassian, Salesforce Lightning, IBM Carbon, Stripe, Uber Base** | — | — | **Não achei evidência** de claim publicado de reprodução fiel de protótipo, nem de medição de fidelidade design↔produção. Todos publicam sobre tokens/componentes; nenhum sobre "o protótipo virou a tela". |

**Resposta direta:** **ninguém publica evidência de reprodução fiel sustentada.** O que existe é (a) claim de vendor sem método, (b) benchmark acadêmico que mede *aceitabilidade* de página gerada a partir de screenshot, e (c) times sérios medindo **adoção de componente/token** — que é uma pergunta diferente e mais fácil.

---

## 2. O mecanismo: a hipótese do [W] — **CONFIRMADA, com um recorte que ela não cobre**

> Hipótese: *quem chega perto não copia o protótipo — elimina o passo de reprodução, fazendo protótipo e produção consumirem a MESMA fonte.*

**Confirmada** na direção: todo mecanismo que a indústria construiu em 2024-2026 empurra para **fonte compartilhada**, não para cópia melhor.

| Mecanismo | Estado 2026 | O que **garante de fato** | O que **continua não garantindo** | Custo |
|---|---|---|---|---|
| **Tokens W3C DTCG** | Format Module **2025.10 — 1ª versão estável, 28/10/2025**, 24+ orgs (Adobe, Google, Meta, Figma, Salesforce, Shopify) **[P/E]**. Adoção declarada: 84% dos times (zeroheight Design Systems Report 2025, ~300 respondentes) **[T]** | Que **o valor** (cor, espaço, tipo, raio) é o mesmo dos dois lados, e que trocar na origem propaga | **Nada sobre composição**: qual token foi usado, onde, em que ordem, com que hierarquia. Token certo em layout errado passa | Baixo-médio. Pipeline Style Dictionary/Terrazzo é commodity |
| **Style Dictionary / Tokens Studio / Figma Variables** | maduro | Transformação determinística JSON → CSS/Swift/XML | Que o dev **use** o token. Daí a necessidade de lint (`stylelint-declaration-strict-value`, ESLint custom, `@lapidist/design-lint`) rodando em CI **[T]** | Baixo o pipeline; **médio o enforcement** |
| **Figma Code Connect** | Org/Enterprise, seat Full ou Dev | Que ao inspecionar o componente no Dev Mode apareça **o snippet real do seu DS**, com props mapeadas, em vez de CSS auto-gerado. Alimenta o MCP como contexto | **Não verifica que o componente Figma e o componente de código são visualmente iguais.** É um mapeamento declarado, não uma prova. Se o Figma driftar, o snippet continua "certo" e errado | Alto: plano Enterprise + trabalho real de mapear cada componente |
| **Figma Dev Mode MCP** | leitura **GA** em 2025 (todos os planos); escrita no canvas ainda **beta** | Entrega ao agente **dado estruturado** (árvore, variáveis, layout, mapeamentos Code Connect) em vez de imagem achatada → código que referencia token e reusa componente | Duas limitações estruturais declaradas por quem documenta o server **[V/T]**: (1) **"nenhuma visibilidade do output renderizado final"** — não sabe se um estilo global sobrescreveu, nem se quebrou na página; (2) **"sem trigger e sem scheduler"** — o agente descobre que o token mudou na próxima vez que perguntar, ou seja, depois de alguém já ter shipado contra o valor velho | Médio + Enterprise pro Code Connect |
| **Storybook / prototipagem no próprio DS** ("design in the browser", component-driven, UXPin Merge) | maduro; a variante comercial é vendor-pesada | **Elimina o passo de reprodução por construção** — o protótipo *é* o componente de produção | Não resolve **dado real, densidade e integração**; e o protótipo fica limitado ao que o DS já tem (mata exploração) | Alto culturalmente: obriga designer a operar no repo |
| **Docs geradas do código** (padrão Airbnb) | — | Que a referência visual **nunca** esteja à frente do código, porque é derivada dele | Não impede que o **desejo** (o mockup) esteja à frente | Baixo |

**O recorte que a hipótese não cobre — e é o que sobra:** fonte comum resolve **valor** (token) e **peça** (componente). Não resolve **composição** (qual peça, onde, em que ordem, com que densidade e responsividade) — e é exatamente aí que a fidelidade se perde. A prova externa é o número do Pinterest: eles tinham componentes adotados **e ainda assim** uma tela com 1% de design system. Uso de peça ≠ tela igual.

**Contraponto nomeado, para não inflar:** Ethan Marcotte, *"Truthish."* — argumenta que "the source of truth" **atrapalha mais do que ajuda** e que a maioria dos DS tem **múltiplas fontes de verdade reconhecendo formalmente só uma**. Não é ceticismo de blogueiro anônimo; é uma das vozes canônicas de web standards. Vale registrar que a própria hipótese tem crítica publicada.

---

## 3. Taxonomia dos modos de falha da reprodução por CÓPIA

Cada linha é um mecanismo distinto de perda — não sinônimos.

| # | Modo | Mecanismo concreto | Evidência |
|---|---|---|---|
| 1 | **Token drift** | valor diverge entre design e código (hex/spacing hardcoded que um dia foi igual) | [T] literatura de drift; `polaris-migrator` existe por causa disso [E] |
| 2 | **Component variant drift** | o componente renderizado tem props/variantes que não existem mais no design (ou vice-versa) | [T] |
| 3 | **Pattern drift** | mesma peça, composição diferente entre áreas do produto | [T] |
| 4 | **Documentation drift** | a doc descreve comportamento que o componente não tem mais | [T] |
| 5 | **Estados ausentes no mockup** | o arquivo mostra **o happy path**; faltam vazio, carregando, erro, offline, sem-permissão. É a classe que mais custa porque não aparece como divergência — aparece como **ausência** | [T] forte e convergente. ⚠️ o número que circula ("análise NN/g de 50 dashboards: 92% sem estado vazio, 78% sem erro, 100% com spinner genérico") só aparece **citado por um blog**; **não consegui verificar no nngroup.com** — tratar como **[?]** |
| 6 | **Dado real** | mockup tem string de 12 caracteres; produção tem nome de 60, valor negativo, lista de 3.000 linhas, campo nulo | [T] |
| 7 | **Densidade** | protótipo desenhado em 1 largura; produção roda em N, com zoom do usuário e escala de fonte do SO | [T] |
| 8 | **Responsivo** | o argumento central de "pixel perfect é morto": RWD tornou "idêntico em toda tela" **incoerente por definição** | [T] (Smashing Magazine, jan/2026, *Rethinking "Pixel Perfect" Web Design* — só li título/data, egress bloqueou o corpo) |
| 9 | **Tema** | claro/escuro/alto-contraste multiplicam o espaço de estados; o mockup normalmente cobre um | [T] |
| 10 | **i18n** | expansão de texto: W3C recomenda **~30% de folga** em botões e diálogos; **labels curtos crescem 100-300%**; regra prática = projetar para o dobro do inglês; RTL quebra o layout inteiro | **[E/W3C]** — a evidência mais dura desta tabela |
| 11 | **a11y** | foco, ordem de tabulação, nome acessível, contraste em estado hover/disabled — não existem no mockup estático | [T] |
| 12 | **Performance** | o que o mockup não paga: skeleton, virtualização, paginação, lazy — e todos **mudam o layout** | [T] |
| 13 | **Renderização de ambiente** | mesma página, SO diferente: antialiasing sub-pixel, fonte, GPU. Um screenshot idêntico no macOS **falha** no runner Ubuntu | **[E]** doc Playwright + relatos de CI |

O eixo comum de 5-13: **o protótipo é um recorte de um espaço de estados, e a produção habita o espaço inteiro.** Copiar o recorte com perfeição não produz o espaço.

---

## 4. O teto honesto

**Não achei evidência** de nenhum caso documentado de fidelidade 1:1 sustentada ao longo do tempo. Nem em vendor, nem em blog de engenharia, nem em paper.

O que existe é **convergência com tolerância declarada** — e as tolerâncias são publicadas:

| Ferramenta | Tolerância declarada | Observação |
|---|---|---|
| **Chromatic** | `threshold` default **0.063** (distância de cor no espaço YIQ). A doc diz textualmente que é o balanço entre acurácia e **falso-positivo vindo de antialiasing** | é o número mais próximo de um "padrão de indústria" que achei |
| **Playwright** `toHaveScreenshot` | `threshold` default **0.2** (YIQ); `maxDiffPixels`/`maxDiffPixelRatio` **desligados por padrão** | prática recomendada por terceiros: `threshold: 0.2` + `maxDiffPixelRatio: 0.01` (1% dos pixels) **[T]** |
| **Percy** | "~40% menos falsos positivos" com revisor de IA | **[V]** sem método publicado |
| **Applitools** | "99,9999%" de acurácia | **[V]**. Uma review de 2026 registra que a claim é *"marketing-grade"* e que **não localizou verificação independente, divulgação de metodologia nem benchmark reprodutível** **[T]** |
| **Genérico** | "ajustar tolerância reduz flakiness em ~80% vs pixel-perfect estrito" | **[?]** número solto, circula sem estudo |

**Sinal metodológico que vale mais que os números:** os autores do **Design2Code** se recusam deliberadamente a **agregar** as métricas num score único — declaram que são *"fine-grained diagnostic scores"*, cada uma diagnóstica de uma dimensão diferente. A melhor pesquisa pública do tema **não produz um "índice de fidelidade"**.

---

## 5. Como os melhores MEDEM (e por que cada abordagem erra)

| Abordagem | O que enxerga | Taxa de FP e por quê |
|---|---|---|
| **Diff de pixel** (Playwright/Vitest snapshots, BackstopJS) | tudo que muda na imagem | **FP alto e estrutural**: antialiasing, fonte por SO, GPU, 1px de shift. Relatos de "ruído visual e testes flaky" em escala com BackstopJS **[T]**. Mitigação = tolerância declarada, que é admitir que 1:1 não é o alvo |
| **Diff de pixel com baseline por ambiente + threshold** (Chromatic) | idem, com ruído domado | FP controlado pelo 0.063; **o custo é o inverso** — sobe o falso-negativo (mudança real de 1px "não flagrada", daí existir `diffIncludeAntiAliasing`) |
| **Diff visual com IA** (Applitools, Percy) | classifica layout vs conteúdo | Vendor afirma FP quase zero; **sem verificação independente [V]**. Contrapartida admitida em review: *"pode classificar uma regressão real de layout como ruído; a taxa de erro é baixa mas não-zero"*, e o classificador é **caixa-preta** **[T]** |
| **Diff de DOM / computed style** | mudança estrutural e de estilo computado | Falha **nos dois sentidos**, segundo a própria literatura de vendor concorrente: FP quando o DOM muda e a UI não; **FN quando a UI quebra e o DOM não muda** — imagem carregando em aspect-ratio errado, `overflow:hidden` cortando conteúdo, botão sobrepondo label, modal atrás de outro elemento **[V]** |
| **Token/lint diff** (stylelint `declaration-strict-value`, ESLint custom, `@lapidist/design-lint`) | valor hardcoded fora do token | FP baixo, **cobertura estreita**: prova conformidade de valor, nada sobre composição |
| **Adoção medida em produção** (Pinterest FigStats, Spotify daily stats, Backstage Check Insights) | quanto da tela real vem do sistema | **É a métrica que os times maduros escolheram.** Não mede fidelidade a um mockup — mede **cobertura do sistema** |
| **Auditoria manual periódica** | desvio acumulado vs a *spec* | FP zero, não escala. Recomendação corrente: trimestral, mensal se o time usa IA para gerar código **[T]** |

**A distinção conceitual mais útil que achei, e vale citar inteira:** *regressão visual detecta **mudança contra um baseline**, não **desvio contra a especificação**.* São instrumentos de perguntas diferentes: o primeiro protege o que já está no ar; só o segundo responde "está fiel ao protótipo". Quase todo o ferramental de mercado é do primeiro tipo. (fonte: blog de vendor de QA **[V]**, mas o argumento se sustenta sozinho.)

---

## 6. O que isso responde à pergunta do [W]

1. **Não. Ninguém publica evidência de reprodução fiel sustentada de protótipo em produção** — nem Shopify, nem Airbnb, nem Spotify, nem Pinterest, nem GitHub. Quem afirma "pixel-perfect" é vendor (Builder.io, Locofy, Anima), sem método; três reviews independentes que testaram os três no mesmo arquivo dizem que nenhum entrega.
2. **A pergunta certa mudou de "reproduzir" para "não precisar reproduzir".** Todo investimento sério de 2024-2026 (DTCG estável em out/2025, Code Connect, Dev Mode MCP, prototipagem no próprio DS) é para **eliminar o passo de cópia**, não para copiar melhor. A hipótese do [W] está **confirmada** pela direção do mercado inteiro.
3. **Mas fonte comum tem um teto conhecido:** ela garante **valor** (token) e **peça** (componente) — **não garante composição**. Recibo: o Pinterest, com 85 componentes code-backed e adoção medida, publicou uma tela onde **1% do design era do sistema**. Usar as peças certas não produz a tela certa.
4. **O melhor mecanismo tem dois buracos declarados pelo próprio ecossistema Figma:** o MCP **não enxerga o output renderizado** (não sabe se um estilo global sobrescreveu) e **não tem trigger/scheduler** (o agente só descobre que o token mudou quando pergunta — depois de alguém ter shipado contra o valor velho). E **Code Connect mapeia, não verifica**: ele não prova que o componente Figma e o de código são visualmente iguais.
5. **A cópia falha por 13 mecanismos distintos**, e os 9 mais caros não são "erro de execução" — são **ausência estrutural**: o mockup é um recorte do espaço de estados (vazio/erro/carregando, dado real, densidade, tema, responsivo, a11y, i18n, performance, renderização por SO). O único com número duro: W3C manda **~30% de folga** por i18n, com labels curtos crescendo **100-300%** — sozinho isso inviabiliza 1:1.
6. **O teto honesto é convergência com tolerância declarada, e as tolerâncias são públicas:** Chromatic `0.063` (YIQ), Playwright `0.2` + prática de `maxDiffPixelRatio 0.01`. Quem promete acurácia de "99,9999%" (Applitools) **não tem verificação independente nem metodologia divulgada** — está registrado por terceiro em 2026.
7. **Nenhuma abordagem de medição é boa sozinha, e cada uma erra de um jeito nomeável:** pixel = FP por antialiasing/SO; IA = caixa-preta que pode classificar regressão real como ruído; DOM/computed-style = **cega** para aspect-ratio errado, `overflow:hidden` e sobreposição; lint de token = FP baixo mas cobertura estreita. O sinal metodológico mais forte de todos: **o Design2Code se recusa a agregar as métricas num score único**, por serem diagnósticas de dimensões diferentes.
8. **O que os melhores realmente medem não é fidelidade — é cobertura do sistema em produção** (Pinterest "design adoption"/FigStats, Spotify estatística diária de versão por time, Backstage Check Insights). Eles trocaram uma pergunta insolúvel ("está igual ao mockup?") por uma mensurável e acionável ("quanto da tela real vem do sistema?"), e é essa que sustenta decisão.

---

### Fontes (com data quando conhecida)

DTCG Format Module 2025.10 — [w3.org/community/design-tokens](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/) (28/10/2025) · [designtokens.org](https://www.designtokens.org/tr/drafts/format/) ·
Design2Code — [arXiv 2403.03163](https://arxiv.org/abs/2403.03163) (mar/2024) · [salt-nlp.github.io/Design2Code](https://salt-nlp.github.io/Design2Code/) ·
UI-Bench — [arXiv 2508.20410](https://arxiv.org/abs/2508.20410) (ago/2025) ·
Figma Dev Mode MCP — [figma.com/blog/introducing-figma-mcp-server](https://www.figma.com/blog/introducing-figma-mcp-server/) · [figma/mcp-server-guide](https://github.com/figma/mcp-server-guide) · [builder.io/blog/figma-mcp-server](https://www.builder.io/blog/figma-mcp-server) ·
Code Connect — [help.figma.com](https://help.figma.com/hc/en-us/articles/23920389749655-Code-Connect) · [supernova.io](https://www.supernova.io/blog/what-is-figma-code-connect-and-how-to-use-it) ·
Pinterest Gestalt adoção — [figma.com/blog/how-pinterests-design-systems-team-measures-adoption](https://www.figma.com/blog/how-pinterests-design-systems-team-measures-adoption/) ·
Spotify — [engineering.atspotify.com](https://engineering.atspotify.com/2023/05/multiple-layers-of-abstraction-in-design-systems) (mai/2023) · [backstage.spotify.com](https://backstage.spotify.com/docs/plugins/insights) ·
Shopify — [polaris-migrator](https://github.com/Shopify/polaris/tree/main/polaris-migrator) · [polaris-react.shopify.com/tools/polaris-migrator](https://polaris-react.shopify.com/tools/polaris-migrator) ·
Chromatic threshold — [chromatic.com/docs/threshold](https://www.chromatic.com/docs/threshold/) ·
Playwright — [playwright.dev/docs/api/class-snapshotassertions](https://playwright.dev/docs/api/class-snapshotassertions) ·
Applitools DOM-based — [applitools.com/blog/visual-ai-vs-pixel-matching-dom-based-comparisons](https://applitools.com/blog/visual-ai-vs-pixel-matching-dom-based-comparisons/) · review crítica [aitestingguide.com/applitools-review](https://aitestingguide.com/applitools-review/) (2026) ·
zeroheight Design Systems Report 2025 — [zeroheight.com/blog/design-systems-report-2025-an-overview](https://zeroheight.com/blog/design-systems-report-2025-an-overview/) (Report 2026 existe em [report.zeroheight.com](https://report.zeroheight.com/) — **não consultado**) ·
Ethan Marcotte, *Truthish.* — [ethanmarcotte.com/wrote/truthish](https://ethanmarcotte.com/wrote/truthish/) ·
W3C i18n text expansion — [w3.org/wiki/GeoGettingStartedwithI18n](https://www.w3.org/wiki/GeoGettingStartedwithI18n) ·
Drift (taxonomia) — [overlayqa.com/blog/design-system-drift](https://overlayqa.com/blog/design-system-drift/) · [figr.design/blog/figma-design-system-drift](https://figr.design/blog/figma-design-system-drift) ·
Reviews Figma-to-code — [figr.design/blog/figma-to-code-plugin](https://figr.design/blog/figma-to-code-plugin) · [sitegrade.io](https://sitegrade.io/en/blog/locofy-vs-builder-io-vs-anima-design-to-code-2026/) · [pixelperfecthtml.com](https://www.pixelperfecthtml.com/figma-to-code-plugins-anima-vs-locofy-vs-hand-coding/) ·
Pixel-perfect crítica — [Smashing Magazine, jan/2026](https://www.smashingmagazine.com/2026/01/rethinking-pixel-perfect-web-design/) (só título/data — corpo bloqueado) · [ishadeed.com/article/pixel-perfection](https://ishadeed.com/article/pixel-perfection/) ·
Lint de token — [alwaystwisted.com/articles/where-to-lint-design-tokens](https://www.alwaystwisted.com/articles/where-to-lint-design-tokens) · [@lapidist/design-lint](https://lapidist.net/articles/2025/introducing-lapidist-design-lint/)

**Claim NÃO verificada, registrada como tal:** "NN/g 2025, 50 dashboards gerados por IA: 92% sem estado vazio, 78% sem erro, 100% com spinner genérico" — só localizei em [blog.vibecoder.me](https://blog.vibecoder.me/empty-states-loading-states-error-states), atribuindo à NN/g; **não achei a fonte primária no nngroup.com**. Não usar como recibo.
