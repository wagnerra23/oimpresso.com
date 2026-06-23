# AUDITORIA — Design-as-code / Token-driven como melhor transporte design→code

> **Tema:** Estrutura da fronteira design↔code (ÂNGULO SOTA 2: tokens + componente codado = fonte; "design" é view).
> **Data:** 2026-06-22 · **Autor:** audit-research-expert (Fase 1 `/audit-and-fix`)
> **Escopo de código:** `prototipo-ui/` (tokens.css, ds-guard, integrity-check, contratos) + `scripts/*grade*.mjs` + `resources/js/Components/ui/` + `resources/js/Pages/`
> **Pergunta literal do Wagner:** "qual é o melhor transporte entre Claude Design e o code, como estruturar isso, e o meu está em quantos % de maturidade?"

---

## 1. TL;DR

**Maturidade global do transporte token-driven: 68%** (weighted — fórmula §4). Recomendação: **CONSOLIDAR** (não EVOLUIR de paradigma). O oimpresso já tem a coluna vertebral do estado-da-arte 2026 que a maioria das equipes não tem: **token CSS único versionado em git como SSOT** (`tokens.css`, ADR 0239) + **guards determinísticos que travam drift no CI** (`design-identity-grade.mjs` com σ=0, `ds-guard`, ratchet). O paradigma já é "a fonte é o token + o componente codado; o design é uma view" — exatamente o veredito SOTA. Os 32% de gap são de **formato e contrato**, não de filosofia.

**Top 3 gaps:** (P0) tokens não estão em **DTCG JSON portável** — estão presos em CSS, sem Style Dictionary, logo zero portabilidade multi-plataforma e zero validação de schema; (P1) **sem Storybook** = não há "contrato vivo" navegável dos componentes nem visual-regression por story (Chromatic-style); (P1) **mapping protótipo↔componente é markdown manual** (`REGISTRY_DS_COMPONENTES.md` + `ds-v6/REUSE_MAPPING.md`), não um Code-Connect-like máquina-legível que a IA consome.

---

## 2. Concorrentes / sistemas de referência (10 sistemas · 3 categorias)

### A. Padrões e formatos (a "fronteira" canônica)
1. **W3C DTCG spec** — primeira versão estável out/2025. JSON portável (`$value`/`$type`/`$ref`). É o contrato de interoperabilidade. ([w3.org/community/design-tokens](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/))
2. **Tailwind v4 `@theme`** — CSS-first: tokens nativos como CSS vars, geram utilities. 3 camadas (base→semantic→component). ([maviklabs](https://www.maviklabs.com/blog/design-tokens-tailwind-v4-2026/))

### B. Tooling de pipeline (token → artefato)
3. **Style Dictionary v4** — first-class DTCG; transforma JSON → CSS/Tailwind/TS/Swift/Kotlin. Motor do "build once, emit many". ([styledictionary.com/info/dtcg](https://styledictionary.com/info/dtcg/))
4. **Tokens Studio** — edição de tokens + push branch p/ GitHub; suporta formato W3C DTCG vs legacy. ([docs.tokens.studio](https://docs.tokens.studio/manage-settings/token-format))
5. **Terrazzo** — implementação de referência DTCG (alternativa a Style Dictionary). ([designtokens.org](https://www.designtokens.org/tr/drafts/))
6. **design-extract (OSS)** — extrai DS de site → DTCG + MCP server p/ Claude Code/Cursor; faz **drift-check contra SSOT vivo** + visual-diff + WCAG. ([github/Manavarya09](https://github.com/Manavarya09/design-extract))

### C. Contrato vivo + bridge componente (anti-drift na camada de componente)
7. **Storybook (2026)** — plataforma de teste: stories = SSOT visual + interaction + a11y + visual-regression, "zero duplicação". ([medium/john-lewis](https://medium.com/john-lewis-software-engineering/turning-storybook-into-a-visual-testing-platform-ff1f7db24c00))
8. **Chromatic / Percy / Applitools** — visual-regression sobre stories; baseline compartilhado designer↔dev. ([chromatic.com/storybook](https://www.chromatic.com/storybook))
9. **Figma Code Connect** — mapeia componente-de-design ↔ componente-de-código; Dev Mode/MCP devolve snippet **de produção**, não autogen. "#1 way to get consistent component reuse — sem isso o modelo chuta". ([github/figma/code-connect](https://github.com/figma/code-connect) · [developers.figma.com](https://developers.figma.com/docs/code-connect/))
10. **Penpot MCP** — OSS; expõe tokens/componentes a agentes, **mas sem equivalente a Code Connect** — uso real maduro é auditoria de DS, não geração. ([penpot.app/blog/design-tokens](https://penpot.app/blog/design-tokens-for-designers/) · [whoisryosuke](https://whoisryosuke.com/blog/2026/designing-with-llms-using-penpot-mcp/))

> **Contexto oimpresso:** **Claude Design** (Anthropic, lançado 17/abr/2026) é o motor de design. Update jun/2026 adicionou **import de design system via repo GitHub** + `/design-sync` + round-trip com Claude Code. ([anthropic.com/news/claude-design](https://www.anthropic.com/news/claude-design-anthropic-labs) · [venturebeat](https://venturebeat.com/technology/anthropic-ships-major-claude-design-overhaul-with-design-system-imports-code-round-trips-and-a-fix-for-its-token-burning-problem)) — isto é decisivo: Claude Design **lê DS do repo**, o que premia ter o DS em formato máquina-legível (DTCG).

---

## 3. Matriz de capacidades (18 dimensões × referência × oimpresso)

| # | Dimensão | SOTA (quem) | oimpresso HOJE | Status |
|---|---|---|---|---|
| 1 | SSOT de token único | Tailwind v4 `@theme` / DTCG JSON | `tokens.css` git (ADR 0239) | ✅ FORTE |
| 2 | Formato **portável** (DTCG JSON) | DTCG / Style Dictionary | CSS vars apenas, sem JSON | ❌ GAP |
| 3 | Validação de schema do token | JSON-schema vs DTCG em CI | nenhuma | ❌ GAP |
| 4 | Build multi-plataforma | Style Dictionary (CSS/TS/Swift…) | só CSS (web-only) | ⚠️ N/A hoje |
| 5 | Camadas base→semantic→component | Tailwind v4 / Mavik | base+semantic (`--accent` → consumo) | ✅ PARCIAL |
| 6 | Trocar 3 linhas re-tematiza tudo | token-driven canônico | **sim** (comentário no tokens.css) | ✅ FORTE |
| 7 | Ban de valor arbitrário (lint) | ESLint no-arbitrary | `ds-guard.mjs` (regex paleta ≥4) | ✅ FORTE |
| 8 | Grade de identidade determinístico | — (raro no mercado) | `design-identity-grade.mjs` σ=0 (ADR 0254) | 🟢 SUPERA |
| 9 | Ratchet anti-regressão de drift | Chromatic baseline | `--check` falha se nota cai (ADR 0209/0254) | 🟢 SUPERA |
| 10 | Contrato vivo de componente | Storybook | `REGISTRY_DS_COMPONENTES.md` (markdown) | ⚠️ ESTÁTICO |
| 11 | Visual-regression por componente | Chromatic/Percy | screen-grade + contrato-de-tela (advisory) | ⚠️ PARCIAL |
| 12 | a11y como gate | Storybook a11y addon | `a11y-axe-gate` + `a11y-gate` ratchet | ✅ FORTE |
| 13 | Mapping design-comp ↔ code-comp | Figma Code Connect | `cowork-map.json` (roteia arquivos) + REUSE_MAPPING.md | ⚠️ MANUAL |
| 14 | IA consome o mapping | Code Connect → MCP snippet | só humano lê o markdown | ❌ GAP |
| 15 | Protótipo read-only (regra de ouro) | — | sim (PROCESSO_MEMORIA_CC §5) | 🟢 SUPERA |
| 16 | Round-trip design↔code | Claude Design `/design-sync` / Figma bidir | handoff bundle CC→CL (1 via forte) | ✅ PARCIAL |
| 17 | DS importável pelo motor de design | Claude Design import via repo | repo tem DS, mas em CSS+HTML | ⚠️ PARCIAL |
| 18 | Multi-tenant safe na camada de token | — | tokens globais, sem PII (Tier 0 ok) | ✅ OK |

---

## 4. Score % por área (5 áreas weighted)

**Fórmula global:** `0.25·SSOT + 0.20·Contrato + 0.20·Anti-drift + 0.20·Bridge + 0.15·Roundtrip`

| Área | Peso | Nota | Evidência (link + nota) |
|---|---|---|---|
| **A. SSOT de token** | 25% | **75%** | `tokens.css` é git-SSOT (ADR 0239) com camada semântica `--accent` consumida por tudo; comentário cabeçalho prova "trocar 3 linhas re-tematiza". Perde 25 pts: **não é DTCG JSON**, é CSS — não passa em Style Dictionary, sem schema. |
| **B. Contrato de componente vivo** | 20% | **50%** | `REGISTRY_DS_COMPONENTES.md` + `ds-v6/showcase.html` (11 componentes em token) + REUSE_MAPPING são bons, **mas estáticos** (HTML/markdown, não Storybook executável). Sem story = sem teste de interação/visual por componente. |
| **C. Anti-drift determinístico** | 20% | **88%** | `design-identity-grade.mjs` σ=0 (ADR 0254) + ratchet `--check` (ADR 0209) + `ds-guard.mjs` (paleta ≥4 falha) + `a11y-gate`/`design-identity-gate`/`design-spec-gate` em `gates-registry.json`. **Supera o mercado** em rigor de CI. Perde 12 pts: visual-regression de pixel ainda advisory. |
| **D. Bridge design-comp ↔ code-comp** | 20% | **45%** | `cowork-map.json` roteia arquivo→destino (bom) e REUSE_MAPPING dá "kit `c-*`→React", **mas não há Code-Connect-like máquina-legível que a IA consome** p/ reusar componente em vez de re-gerar. É o gap nº1 da camada de componente segundo a Figma. |
| **E. Round-trip / handoff** | 15% | **65%** | Handoff bundle Claude Design→Claude Code (MWART 5 fases) é forte numa via; `/design-sync` (jun/2026) e import de DS via repo existem no motor mas **subusados** porque o DS no repo é CSS+HTML, não o formato que o import premia. |

**Cálculo:** `0.25·75 + 0.20·50 + 0.20·88 + 0.20·45 + 0.15·65 = 18.75 + 10 + 17.6 + 9 + 9.75 = `**`68.1% ≈ 68%`**

**Saturação (onde parar de subir):** ~92%. Acima disso exige Code-Connect-like + Storybook + DTCG com manutenção que, p/ time pequeno + cliente único (Larissa 1280px), tem ROI marginal decrescente. **Meta realista: 68% → 85%** (consolidação), deixando os últimos 7 pts (visual-regression de pixel bloqueante, multi-plataforma) como opt-in futuro.

---

## 5. Top 10 gaps priorizados

| # | Gap | Sistema-ref | Esforço (dev-days, recalibrado 10×) | ROI | Prio |
|---|---|---|---|---|---|
| 1 | Tokens não estão em **DTCG JSON** (presos em CSS) | DTCG / Style Dictionary | 1.5 | Alto — destrava import Claude Design + schema + portabilidade | **P0** |
| 2 | **Sem validação de schema** de token no CI | JSON-schema vs DTCG | 0.5 | Médio-alto — pega token malformado antes do merge | **P0** |
| 3 | **Bridge IA-legível** protótipo↔componente (Code-Connect-like) | Figma Code Connect | 2 | Alto — IA reusa componente em vez de re-gerar = -drift estrutural | **P1** |
| 4 | **Sem Storybook** = contrato de componente não-executável | Storybook 2026 | 3 | Médio-alto — story vira teste+doc+baseline, zero duplicação | **P1** |
| 5 | Camada **component-token** ausente (só base+semantic) | Tailwind v4 3 camadas | 1 | Médio — isola variação por componente | **P2** |
| 6 | Visual-regression de pixel ainda **advisory** (não bloqueia) | Chromatic | 1.5 | Médio — fecha o loop visual de verdade | **P2** |
| 7 | `tokens.css` derivado de **HTML manual** (`Design System v4.html`) | Style Dictionary build | 0.5 | Médio — inverter: JSON é fonte, HTML/CSS são emitidos | **P1** |
| 8 | DS **não exportado** pro `/design-sync` da Claude Design | Claude Design import | 1 | Médio — round-trip real em vez de handoff one-shot | **P2** |
| 9 | `REUSE_MAPPING` 3/11 componentes são "buraco do DS" | — | 2 | Médio — nascer na 1ª tela que consome | **P2** |
| 10 | Sem **build multi-plataforma** (só web) | Style Dictionary emitters | — | Baixo — não há mobile nativo; deixar dormir | **P3** |

---

## 6. Decisão estratégica: **CONSOLIDAR**

O oimpresso **não precisa trocar de paradigma** — ele já está no paradigma vencedor de 2026 ("a fonte é o token + o componente codado; o design é uma view"), e em duas dimensões (grade determinístico σ=0 e ratchet anti-regressão) **supera a média do mercado**, que ainda confia em revisão humana ou em LLM-judge instável. O que falta é **formato e contrato**: trocar o invólucro do token de CSS-only para **DTCG JSON como fonte → Style Dictionary emite CSS/TS** (invertendo a derivação hoje feita à mão a partir do HTML), e tornar o mapping componente↔código **máquina-legível** para a IA consumir. São melhorias incrementais sobre fundações sólidas — EVOLUIR (refazer o transporte) destruiria os guards determinísticos que são o ativo mais raro do sistema. Logo: **consolidar até ~85%**, mantendo o handoff Claude Design→Claude Code como espinha dorsal.

---

## 7. Roadmap (3 ondas — modo consolidação)

**Onda 1 — formato portável (P0, ~2.5 dev-days):** extrair `tokens.css` → `tokens.json` DTCG (fonte) + `style-dictionary` build emitindo o CSS atual (zero mudança visual) + `ajv` schema-check no CI. Inverte a derivação: JSON manda, CSS/HTML são saídas. **Saída:** DS importável pela Claude Design via repo.

**Onda 2 — contrato de componente vivo (P1, ~5 dev-days):** Storybook sobre `resources/js/Components/ui/*` (já são shadcn+CVA) → cada story = doc+teste; promover visual-regression de advisory→bloqueante nos 11 componentes canônicos do `ds-v6/showcase`. **Saída:** REGISTRY estático vira contrato executável.

**Onda 3 — bridge IA-legível (P1/P2, ~3 dev-days):** gerar `code-connect.json` (mapa protótipo-`c-*` ↔ componente React, formato máquina) que a Claude Design/Claude Code consomem no handoff → IA reusa em vez de re-gerar; ligar `/design-sync` pro round-trip. **Saída:** drift estrutural de componente cai porque o modelo para de "chutar".

---

## 8. Surpresa positiva & negativa

**🟢 Positiva (oimpresso > mercado):** o **grade de identidade determinístico σ=0** (`design-identity-grade.mjs`, ADR 0254) + **ratchet `--check`** que falha o build se a nota de conformidade-token cair. A indústria ainda mede conformidade com LLM-judge (instável: 91→71 no mesmo PR, σ=14) ou revisão humana ("padding 14 vs 16"). O oimpresso transformou drift em número que não alucina e o travou no CI — isso é **acima do SOTA** comercial. Bônus: **regra de ouro do protótipo read-only** (PROCESSO_MEMORIA_CC §5) é uma disciplina anti-drift que Figma/Penpot não impõem.

**🔴 Negativa (mercado > oimpresso):** a **fronteira ainda mora em CSS+HTML manual**, não em **DTCG JSON**. O `tokens.css` é derivado à mão do `Design System v4.html` — o mundo inverteu isso há ~1 ano (JSON é fonte, Style Dictionary emite). Consequência prática: a Claude Design (jun/2026) ganhou **import de DS via repo GitHub**, recurso feito sob medida pro oimpresso, mas o DS está num formato que o import não consome bem. Está-se deixando na mesa o recurso mais relevante do próprio motor de design que já se usa.

---

> **Fontes (8 WebSearch + 1 WebFetch):** W3C DTCG, Style Dictionary, Tokens Studio, Terrazzo, design-extract, Tailwind v4 (Mavik), Storybook/Chromatic, Figma Code Connect, Penpot MCP, Claude Design (Anthropic/VentureBeat). Inventário: `prototipo-ui/tokens.css`, `ds-guard.mjs`, `integrity-check.mjs`, `CODE_DESIGN_CONTRACT.md`, `REGISTRY_DS_COMPONENTES.md`, `ds-v6/REUSE_MAPPING.md`, `cowork-map.json`, `scripts/design-identity-grade.mjs`, `scripts/governance/gates-registry.json`.
> **ADRs tocados:** 0239 (git-SSOT DS), 0235/0249 (DS v6 roxo), 0254 (grade determinístico), 0209 (ratchet), 0093 (multi-tenant Tier 0 — tokens globais sem PII, ok).
