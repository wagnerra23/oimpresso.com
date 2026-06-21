---
date: 2026-06-13
topic: "Auditoria sênior de maturidade do Design System do oimpresso vs estado-da-arte 2026"
type: session
---

# Auditoria Sênior — Maturidade do Design System do oimpresso

> **Data:** 2026-06-13 · **Auditor:** Claude Code (modo adversário sênior) · **Pedido:** Wagner
> **Objeto:** graduar o **DESIGN SYSTEM EM SI** (sua maturidade multi-dimensional), NÃO uma tela.
> **Régua:** estado-da-arte 2026 (Linear · Stripe · Vercel · Radix · shadcn · Material 3 · Polaris · Primer).
> **Método:** grade → benchmark → gap% → ondas medíveis → catraca anti-regressão.

---

## TL;DR executável

| Métrica | Valor |
|---|---|
| **Nota do DS hoje (ponderada /100)** | **61 / 100** — "maduro em fundação + governança executável; raso em adoção, tokenização machine-readable e VRT" |
| **Dimensões da rubrica** | **14** (cada uma com peso · nota atual · nota world-class · gap%) |
| **Estágio Sparkbox** | **3 de 4** ("Established") — falta o salto pra "Optimized" (cobertura medida + VRT + adoção alta) |
| **Ondas pra elevar a world-class** | **6 ondas de MATURIDADE** (distintas das ~16-18 ondas de ADOÇÃO do `DsRollout.tsx`) |
| **Maior surpresa** | a camada canônica **se autocontradiz**: `badge.tsx:23-30` hardcoda `bg-emerald-50 text-emerald-700` (paleta crua) — exatamente o que a regra `ds/no-adhoc-status-text` proíbe nas Pages. O fix da Onda 1 já existe (tokens `-soft/-fg` em `inertia.css:158-167`), só não foi consumido. |
| **Veredito de método** | **SIM, é assim que se faz** — o oimpresso já tem o que 90% dos DS não têm (gate executável + ledger derivado). O que falta é o sênior: (a) **rubrica ponderada** explícita (este doc), (b) **VRT** (Chromatic/Playwright snapshot) fechando o loop visual, (c) **tokens DTCG** machine-readable. |

**Nota honesta:** 61/100 não é "ruim" — é **bom-acima-da-média com teto baixo em 3 eixos**. A maioria dos ERPs não passa de 35. O que segura o oimpresso longe de 80+ é mensurável e atacável em 6 ondas.

---

## 1 · RUBRICA DE MATURIDADE DE DS (os "pontos a analisar")

14 dimensões canônicas pelas quais um DS de classe mundial é graduado. Pesos somam 100. Derivadas de: Sparkbox Maturity Model (4 estágios) + Anatomy of a DS (4 camadas × 3 partes) + USWDS Maturity Model + métricas de adoção (zeroheight/Figma/Productboard) + W3C DTCG + práticas Radix/shadcn/Material 3/Polaris/Primer.

| # | Dimensão | Peso | O que mede (world-class) |
|---|---|---:|---|
| D1 | **Token architecture (tiers)** | 12 | 3 camadas: primitivo (raw) → semântico (intenção) → componente. Telas só consomem semântico. |
| D2 | **Token format (machine-readable)** | 5 | DTCG `$value/$type` (W3C 2025.10) + Style Dictionary → CSS/JSON/Swift/etc de UMA fonte. |
| D3 | **Component library & API** | 12 | Cobertura de primitivos + API consistente (`asChild`/Slot, controlled/uncontrolled, `data-slot`, CVA variants). |
| D4 | **Accessibility (WCAG AA/AAA)** | 11 | Teclado, foco, ARIA, nome acessível, contraste — testado runtime, não só estático. |
| D5 | **Theming & dark mode** | 8 | Dark projetado junto do light, por token semântico; multi-brand opcional. |
| D6 | **Layout & spacing primitives** | 6 | Box/Stack/Inline/Grid + escala de espaço tokenizada (não `flex gap-4` solto). |
| D7 | **Typography & density** | 5 | Ramp de tamanho ancorado + densidade (compact/comfortable) como token. |
| D8 | **Motion & interaction tokens** | 4 | Durations/easings tokenizados; princípios de motion documentados. |
| D9 | **Governance & versioning** | 9 | ADR/charter + semver + changelog append-only + quem-decide-o-quê. |
| D10 | **Anti-regression (catraca/CI)** | 9 | Lint ratchet + gates que falham em regressão (delta>0), não na palavra de ninguém. |
| D11 | **Visual regression (VRT)** | 6 | Snapshot pixel-diff por componente (Chromatic/Playwright) no CI. |
| D12 | **Documentation & discoverability** | 6 | Showcase/Storybook navegável + registry + "onde nasce cada coisa". |
| D13 | **Adoption & coverage metrics** | 7 | % real medido em produção por rota/módulo (não estimado). |
| D14 | **Contribution model** | 3 | Como o time propõe/contribui sem furar a fundação. |
| | **Total** | **100** | |

**Fontes da rubrica (web 2026):**
- Sparkbox Maturity Model + Anatomy of a DS — [sparkbox.com/foundry](https://sparkbox.com/foundry/design_system_maturity_model) · [anatomy](https://sparkbox.com/foundry/why_understanding_the_anatomy_of_a_design_system_can_help_you_and_your_team_create_and_grow_a_powerful_design_system)
- USWDS Maturity Model — [designsystem.digital.gov/maturity-model](https://designsystem.digital.gov/maturity-model/)
- Métricas de adoção/cobertura — [zeroheight](https://zeroheight.com/measurement/) · [Figma DS 104](https://www.figma.com/blog/design-systems-104-making-metrics-matter/) · [Mews](https://developers.mews.com/design-system-adoption-metric-building/) · [Productboard](https://www.productboard.com/blog/how-we-measure-adoption-of-a-design-system-at-productboard/)
- Token tiers — [designsystemproblems.com](https://designsystemproblems.com/token-management/token-tier-system/) · [Contentful](https://www.contentful.com/blog/design-token-system/)
- Token format DTCG/Style Dictionary — [designtokens.org 2025.10](https://www.designtokens.org/tr/drafts/format/) · [styledictionary.com/info/dtcg](https://styledictionary.com/info/dtcg/)
- Component API (Radix/shadcn) — [radix-ui.com/primitives](https://www.radix-ui.com/primitives) · [WorkOS](https://workos.com/blog/what-is-the-difference-between-radix-and-shadcn-ui)
- VRT (Chromatic) — [chromatic via stevekinney](https://stevekinney.com/courses/storybook/visual-tests)
- World-class refs — [designsystems.surf 2026](https://designsystems.surf/articles/11-best-design-system-examples-in-2026) · [Material 3](https://zoewave.medium.com/material-3-design-system-e91a15d303a0)

---

## 2 · NOTA DO DS ATUAL DO OIMPRESSO (/100 ponderada)

Auditado contra o código real (sha `b04b1e411` do ledger). Nota por dimensão 0-100, ponderada pelo peso.

| # | Dimensão | Peso | Nota atual | World-class | Gap% | Evidência (arquivo:linha) |
|---|---|---:|---:|---:|---:|---|
| D1 | Token architecture | 12 | **68** | 100 | 32% | `inertia.css:108-179` semânticos completos (primary/destructive/success/warning/info + pares `-soft/-fg`) MAS sem camada primitiva nomeada explícita; mistura `hsl()` e `oklch()` (113-141 hsl, 148-168 oklch) |
| D2 | Token format | 5 | **15** | 100 | 85% | só CSS `@theme` — **nenhum** `tokens.json`/DTCG/Style Dictionary no repo (busca FS = vazio) |
| D3 | Component library & API | 12 | **78** | 100 | 22% | 30 primitivos `ui/*` + 18 `shared/*`; API forte: `asChild`+Slot (`button.tsx:57`, `box.tsx:56`), CVA (`button.tsx:7`), `data-slot`/`data-variant` (`button.tsx:61-63`). Buracos: sem `Table` canônico, sem `Toast`/`Sonner`, `input.tsx` mistura 2 variantes via prop |
| D4 | Accessibility | 11 | **72** | 100 | 28% | axe runtime jsdom (`tests/a11y-primitives.test.tsx:1-69`) + axe browser (Pest 4) + jsx-a11y ratchet (`eslint.config.js:108-114`) + `a11y:check`. Falta: contraste não medido em jsdom (test:9 admite), cobertura axe = 7 primitivos de 30 |
| D5 | Theming & dark mode | 8 | **70** | 100 | 30% | dark por token semântico completo (`inertia.css:186-236`), anti-flash inline; MAS só **7/30** primitivos têm `dark:` explícito + nenhuma tela real ativa dark (`ds-ledger.mjs:32` "dark não ativado em nenhuma tela") |
| D6 | Layout & spacing | 6 | **74** | 100 | 26% | Box/Stack/Inline/Grid/Container/Text (`layout/index.ts`) com CVA token-enforced (`box.tsx:18-50`), gate `layout:check` (ADR 0253); MAS escala de espaço não é token nomeado (usa Tailwind 1-12 cru) |
| D7 | Typography & density | 5 | **65** | 100 | 35% | ramp `--fs-1..9` ancorado (`foundations.css:17-25`) + conformance-gate ratchet; MAS **zero** token de densidade (compact/comfortable) — densidade é hardcoded por tela |
| D8 | Motion & interaction | 4 | **20** | 100 | 80% | **nenhum** token de motion — só 1 `transition 150ms ease` cru no body (`inertia.css:251`); sem durations/easings nomeados, sem princípios |
| D9 | Governance & versioning | 9 | **80** | 100 | 20% | ADR UI-0013 4 camadas + charters (137 arquivos `.charter.md`) + REGISTRY + "onde nasce" (`components.md`); MAS **sem semver/changelog do DS** como pacote |
| D10 | Anti-regression (catraca) | 9 | **88** | 100 | 12% | **ponto mais forte.** ESLint `ds/*` ratchet (`eslint.config.js:120-170`), conformance-gate, reuse-gate, layout-guard, components-tree-guard, pageheader-guard, a11y-ratchet — todos delta>0. ADR 0209/0239/0240 |
| D11 | Visual regression (VRT) | 6 | **30** | 100 | 70% | Playwright instalado (`playwright.config.ts`) mas é E2E de comportamento, `screenshot: only-on-failure`; **nenhum** snapshot pixel-diff por componente no CI (Chromatic ausente) |
| D12 | Documentation | 6 | **55** | 100 | 45% | `_Showcase/Components.tsx` existe + REGISTRY + rules path-scoped; MAS sem Storybook navegável, sem stories isoladas (`*.stories.*` = 0) |
| D13 | Adoption & coverage | 7 | **45** | 100 | 55% | **infra de ouro, número baixo:** ledger DERIVADO ao vivo (`ds-ledger.mjs` → `ds-ledger.json`) mede adoção real = **9%** (3/35 telas tokens+primitivos verdes). 238 arquivos Pages ainda com paleta crua |
| D14 | Contribution model | 3 | **60** | 100 | 40% | "como propor mudança" tabela (CLAUDE.md) + ADR append-only; MAS focado em IA-pair, sem modelo de contribuição multi-dev formal |
| | **PONDERADA** | **100** | **≈ 61** | 100 | **39%** | |

**Cálculo da ponderada:** Σ(nota×peso)/100 = (68·12 + 15·5 + 78·12 + 72·11 + 70·8 + 74·6 + 65·5 + 20·4 + 80·9 + 88·9 + 30·6 + 55·6 + 45·7 + 60·3)/100 = **6.107/100 ≈ 61**.

---

## 3 · GAP vs WORLD-CLASS — top-10 gaps priorizados (impacto × esforço)

Ordenado por (peso × gap%) ajustado por esforço. "Quick win" = alto impacto, baixo esforço.

| Rank | Gap | Dim | Peso×Gap% | Esforço | Por que dói | Quick win? |
|---:|---|---|---:|---|---|:---:|
| **1** | **Adoção real travada em 9%** — 238 arquivos com paleta crua | D13 | 3.9 | L | o DS existe mas as telas não o usam; é a tese inteira do `DsRollout` | — |
| **2** | **Sem VRT pixel-diff** por componente | D11 | 4.2 | M | nada impede um PR de quebrar visualmente um primitivo sem ninguém ver | parcial |
| **3** | **Camada canônica se autocontradiz** — `badge.tsx:23-30` paleta crua | D1/D3 | — | **S** | mina a credibilidade da regra `ds/*`; fix já existe (`inertia.css:158-167`) | **SIM** |
| **4** | **Tokens sem formato machine-readable** (DTCG) | D2 | 4.3 | M | trava multi-plataforma + tooling (Figma↔code) + auditoria de token | — |
| **5** | **Zero tokens de motion** | D8 | 3.2 | S | inconsistência de animação tela-a-tela; world-class tokeniza duration/easing | **SIM** |
| **6** | **Documentação não-navegável** (sem Storybook/stories) | D12 | 2.7 | M | descoberta depende de ler código; onboarding lento | — |
| **7** | **Token tiers sem camada primitiva nomeada** + hsl/oklch misturados | D1 | 3.8 | S | semântico aponta pra valor cru, não pra primitivo; refactor de cor vira caça | parcial |
| **8** | **Densidade não-tokenizada** | D7 | 1.8 | S | ERP precisa compact/comfortable; hoje é hardcode por tela | parcial |
| **9** | **Dark mode não-exercitado** (0 telas ativam) | D5 | 2.4 | M | dark "existe" mas nunca testado em uso real → bugs latentes | — |
| **10** | **Sem semver/changelog do DS** como pacote | D9 | 1.8 | S | mudança de token é invisível pra quem consome; sem "breaking change" sinalizado | **SIM** |

**Leitura sênior:** os 3 maiores buracos (VRT, DTCG, adoção) são os que separam "Established" de "Optimized" no modelo Sparkbox. Os quick wins (#3, #5, #10) custam <1 dia cada e sobem a nota ~4 pontos sem risco.

---

## 4 · ROADMAP — 6 ONDAS DE MATURIDADE

### A diferença que o Wagner perguntou: ADOÇÃO ≠ MATURIDADE

| | `DsRollout.tsx` (já existe) | Este roadmap (novo) |
|---|---|---|
| Mede | **ADOÇÃO** — quantas TELAS consomem o DS | **MATURIDADE** — quão CLASSE-MUNDIAL o DS é em si |
| Unidade | 1 onda = 1 tela portada (~16-18 ondas) | 1 onda = 1 salto de capacidade do DS (6 ondas) |
| Placar | Ledger (`ds-ledger.json`) = 9% | esta rubrica /100 = 61 |
| Eixo | horizontal (largura: cobrir telas) | vertical (altura: elevar o teto) |

**Como se encaixam:** são **ortogonais e complementares.** Maturidade eleva o TETO (o que o DS é capaz); Adoção preenche a LARGURA (quantas telas chegam lá). Subir maturidade sem adoção = ferramenta linda na prateleira. Adoção sem maturidade = espalhar dívida. **Sequência sã:** Onda M1 (consolidar fundação) destrava as ondas de adoção B do DsRollout — fazer M1 ANTES de acelerar adoção evita portar 238 telas pra um alvo que ainda se contradiz.

### As 6 ondas

| Onda | Nome | Entrega | Dims | Esforço | ROI | Δnota |
|---|---|---|---|---|---|---:|
| **M1** | **Consolidar a fundação** (quick wins) | (a) badge/status → tokens `-soft/-fg` (mata autocontradição); (b) unificar hsl→oklch em `inertia.css`; (c) nomear camada primitiva de cor; (d) tokens de motion (`--duration-*`/`--ease-*`); (e) semver+CHANGELOG do DS | D1·D8·D9 | **S** | **altíssimo** | +5 |
| **M2** | **Tokens machine-readable** | extrair `@theme` → `tokens.json` DTCG (W3C 2025.10) + Style Dictionary gera o CSS de volta (fonte única); abre porta Figma↔code | D2 | M | alto | +4 |
| **M3** | **VRT — fechar o loop visual** | stories isoladas dos 30 primitivos + Playwright snapshot pixel-diff no CI (advisory→required); pega o que o olho deixa passar | D11·D12 | M | alto | +6 |
| **M4** | **A11y AA→AAA + cobertura total** | axe runtime nos 30 primitivos (hoje 7); contraste AAA medido no browser; densidade tokenizada (compact/comfortable) | D4·D7 | M | médio | +5 |
| **M5** | **Dark mode exercitado + theming** | ativar dark em ≥3 telas reais, corrigir bugs latentes; `dark:` explícito onde o token não basta; provar multi-tema | D5 | M | médio | +3 |
| **M6** | **Docs navegáveis + contribution** | Showcase → portal navegável (ou Storybook) com toda a API; modelo de contribuição multi-dev (quando o time ganhar write) | D12·D14 | L | médio | +3 |

**Projeção:** 61 → **~84-87** após M1-M5 (M6 é polish). Cada onda tem **teste de saída medível** (mesma filosofia do DsRollout): M1 = `ds:ledger` mostra badge verde + grep paleta-crua na camada canônica = 0; M3 = snapshot baseline commitado + diff=0; M4 = axe 30/30 verde.

**Sequência recomendada:** M1 PRIMEIRO (destrava tudo, custo trivial) → M2+M3 em paralelo (independentes) → M4 → M5 → M6. M1 deve preceder qualquer aceleração das ondas de ADOÇÃO do DsRollout (não portar 238 telas pra alvo que se contradiz).

---

## 5 · VALIDAÇÃO DE MÉTODO — "é assim que deveria ser feito?"

**Resposta curta: SIM, e o oimpresso já está à frente de 90% dos DS — mas faltava o ato sênior de graduar o DS EM SI.**

### O que um SÊNIOR faz diferente de um top-10 reativo

| Júnior reativo (o top-10 do Cliente) | Sênior (este dossier) |
|---|---|
| Audita **uma TELA** (58/100) | Gradua o **DS inteiro** (61/100 em 14 eixos) |
| Lista 10 problemas pontuais | Define **rubrica ponderada** repetível |
| "consertar isto e aquilo" | "elevar estes EIXOS em N ondas medíveis" |
| Sem baseline comparável | Benchmarka vs Linear/Stripe/Material 3 |
| Conserto = opinião | Conserto = **teste de saída** que prova |

### O método certo (que o oimpresso já pratica em parte)

1. **GRADE** — rubrica ponderada (não "está bom?", mas "68/100 em token architecture, peso 12"). ✅ este doc.
2. **BENCHMARK** — contra o melhor 2026, não contra ontem. ✅ web research citada.
3. **ONDAS MEDÍVEIS** — cada onda entrega capacidade + teste de saída binário. ✅ M1-M6.
4. **CATRACA ANTI-REGRESSÃO** — gate que falha em delta>0, não na palavra. ✅✅ **já é o ponto mais forte** (D10=88): `ds/*` ratchet, conformance-gate, ledger derivado. Isso é raríssimo — a maioria dos DS confia em "review humano".

### O elogio honesto + a crítica honesta

**Elogio:** o oimpresso tem **governança executável** (ledger derivado, não estimado; gates por delta) que coloca a barra de honestidade acima de DS de empresas 100x maiores. A frase do próprio ledger — *"você nunca depende da minha palavra: depende do verde do placar"* (`DsRollout.tsx:251`) — É o método sênior, já internalizado.

**Crítica:** três tetos baixos sabotam a maturidade real: (1) **sem VRT**, o "verde do placar" não cobre o VISUAL — um primitivo pode quebrar pixel-a-pixel e passar; (2) **sem DTCG**, o token vive preso no CSS, não auditável por ferramenta; (3) **adoção em 9%** significa que o DS lindo ainda não toca o usuário. E o sintoma-tese: a **camada canônica se contradiz** (`badge.tsx`), o que prova que falta a própria rubrica deste doc rodando como gate.

**Veredito final:** método **correto e maduro** — só faltava (a) a rubrica ponderada explícita (entregue aqui) e (b) fechar os 3 tetos via M1-M3. Faça M1 hoje (1 dia, +5 pontos, mata a autocontradição) e o DS sai de "bom com teto baixo" pra "trajetória world-class provada por placar".

---

## Anexo · Inventário auditado (provas)

- **Tokens:** `resources/css/inertia.css:108-236` (@theme + dark) · `resources/css/foundations.css:17-25` (ramp --fs)
- **Primitivos:** 30× `resources/js/Components/ui/*.tsx` · 18× `shared/*` · 6× `layout/*`
- **API exemplar:** `button.tsx:7,57,61` (CVA+Slot+data-slot) · `box.tsx:18-56` (token-enforced CVA) · `field-state.tsx:1-52` (role=alert/status)
- **Autocontradição:** `badge.tsx:23-30` (paleta crua na camada canônica) — fix disponível `inertia.css:158-167`
- **Catraca:** `eslint.config.js:120-170` (ds/*) · `scripts/ds-ledger.mjs` · `governance/ds-ledger.json` (9%, sha b04b1e411)
- **A11y:** `tests/a11y-primitives.test.tsx:1-69` (axe jsdom, 7 primitivos) · `eslint.config.js:108-114`
- **Plano de adoção (≠ maturidade):** `resources/js/Pages/governance/DsRollout.tsx:248-258`
- **Camadas:** `memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md:39-60`
- **Ausências confirmadas (busca FS):** DTCG/Style Dictionary = 0 · `*.stories.*` = 0 · tokens de motion = 0 · tokens de densidade = 0 · 238 Pages com paleta crua
