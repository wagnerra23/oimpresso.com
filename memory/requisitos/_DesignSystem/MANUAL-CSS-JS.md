---
id: requisitos-design-system-manual-css-js
name: Manual CSS & JS — como deveria ser e como fazer no oimpresso
description: Manual canônico de administração de CSS/JS (frontend) do oimpresso — arquitetura-alvo comparada com os melhores (Linear/Stripe/Shopify Polaris/GitHub Primer/Vercel), diagnóstico do estado atual com números, as regras duras, o workflow concreto com as ferramentas que já existem (ESLint/Stylelint ratchet, conformance-gate, foundation-guard, ds-report), e o plano de migração pra sair do sprawl de 28k linhas de CSS bespoke.
type: reference
status: ativo
owner: wagner
version: "1.1"
last_updated: "2026-06-06"
related_adrs: ["0094-constituicao-v2-7-camadas-8-principios", "0235-ds-v4-accent-roxo-universal", "0249-ds-v6-naming-amends-0235", "0239-governanca-design-system-git-ssot-regressao-ia", "0013-constituicao-ui-v2-camadas", "0209-eslint-9-flat-config"]
ssot: INDEX-DESIGN-MEMORIAS.md
---

# Manual — CSS & JS no oimpresso: como deveria ser e como fazer

> ⚠️ **SUBORDINADO AO SSOT.** A **identidade e a governança de design já são canon** — vivem no
> SSOT [INDEX-DESIGN-MEMORIAS.md](INDEX-DESIGN-MEMORIAS.md): cor `primary` roxo `oklch(0.55 0.15 295)`
> ([ADR 0235](../../decisions/0235-ds-v4-accent-roxo-universal.md)) + camada de tokens semânticos
> **DS v6** ([ADR 0249](../../decisions/0249-ds-v6-naming-amends-0235.md)), sob governança git-SSOT
> ([ADR 0239](../../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)). Este manual
> **não redefine** identidade — é o **regimento de CSS/JS** (regras do/don't + alvo + caminho de
> convergência do código). Azul de **marca** = débito a migrar; azul **semântico** (status/origin-badge)
> sobrevive (SSOT §4).
>
> **Origem:** 2026-06-04 — Wagner: *"investigar a lista de problemas de css e js, isso está
> bem administrado como deveria ser? compare com os melhores. Crie um manual de como deveria
> ser e como fazer."* · Ampliado 2026-06-06 (absorveu o plano de convergência standalone, evitando
> 3º doc — anti-duplicação ADR 0249/0239): + gap de **primitivos de layout** (§2.1) + roadmap como tasks MCP (§5).
>
> **Veredito de uma linha:** o **JS/TS está bem administrado** (TS strict, stack moderna,
> lint com ratchet); o **CSS NÃO** — ~28k linhas de CSS bespoke por-tela (dumps de protótipo
> Cowork) minam o design-system que já existe no papel. **A visão existe; falta o código convergir nela.**

---

## 1. Diagnóstico atual (com números — 2026-06-04)

### ✅ O que JÁ está bom (manter)

- **TypeScript strict** — `tsconfig.json` com `strict: true` + `noUncheckedIndexedAccess`. (top-tier; muita gente não tem).
- **Stack moderna e correta** — React + Inertia v3, **Tailwind v4** (`@tailwindcss/vite`), **Radix UI** (primitivos acessíveis), **CVA** + `clsx` + `tailwind-merge` (variantes type-safe), TanStack Query/Table, `lucide-react`, Vite.
- **Lint com ratchet** — ESLint 9 flat-config ([ADR 0209](../../decisions/0209-eslint-9-flat-config.md)) + Stylelint, ambos com baseline JSON e CI que **falha só em regressão** (delta > 0). `eslint-plugin-jsx-a11y` ligado (acessibilidade).
- **Governança de design-system** — scripts `ds:report`, `conformance:check`, `foundation:check`, `design:review` + Vitest (`tests/conformanceGate.spec.ts`, `tests/foundationGuard.spec.ts`). Constituição UI v2 (4 camadas) + Padrões de Tela + PRE-MERGE-UI.

### ❌ A "lista de problemas" (o que está errado)

1. **CSS sprawl — ~20.294 linhas em 34 arquivos** (2026-06-06; era ~28.5k antes da limpeza de morto #2291/#2293/#2295). Dois arquivos ainda concentram a maior parte:
   - `resources/css/cowork-canon-financeiro-bundle.css` — **4.747 linhas** (era 8.666)
   - `resources/css/sells-cowork.css` — **3.969 linhas** (era 7.540)
   - +~20 arquivos `sells-cowork-*`, `cowork-*`, `fin-*` — **CSS por-tela/por-módulo**, o oposto de um design-system. São dumps do protótipo Cowork colados, não tokens compartilhados.
2. **Dívida de lint — ~1.073 violações ESLint** no baseline (ADR 0209; era 1.340 — `__parser_error__` e `no-undef`-em-TS já zerados, PRs #2326/#2327). Resta dívida `jsx-a11y/*` (acessibilidade) + 31 `no-undef` em `bootstrap.js` (.js).
3. **Build dual** — `vite.config.js` (legacy Blade) + `vite.inertia.config.mjs` (Inertia). Migração Blade→Inertia (MWART) inacabada; dois pipelines coexistindo.
4. **Tokens não são fonte única** — Tailwind v4 existe, mas ~20k linhas de CSS bespoke definem cor/espaço/tipo à mão em paralelo aos tokens → drift visual e impossível refatorar com confiança.
5. **Colisões de símbolo global** (tests/helpers) — sintoma cultural de copy-paste (mesma raiz do sprawl CSS). Ver [feedback-nao-tocar-pr-fora-escopo] / US-INFRA-031.

---

## 2. Como deveria ser — arquitetura-alvo (comparada com os melhores)

Referências estado-da-arte 2026: **Linear, Stripe, Shopify Polaris, GitHub Primer, Vercel/Geist, Notion**. O padrão comum:

| Princípio dos melhores | Tradução pro oimpresso |
|---|---|
| **Token é a fonte única** (cor/espaço/tipo/raio/sombra) | Tudo vem de `@theme` Tailwind v4 + camada Fundações da Constituição UI v2. Nenhum hex/px solto. |
| **Utility-first, ZERO CSS por-página** | Estilo mora no JSX via classes Tailwind. `resources/css/*` só pra: reset, tokens, e raras exceções globais. **Não existe `sells-cowork.css`.** |
| **Componentes, não páginas, são a unidade** | Primitivos Radix + variantes CVA em `resources/js/Components/ui/`. Telas compõem componentes; não redefinem estilo. |
| **Layout via primitivos, não CSS por-tela** | `Box`/`Stack`/`Inline`/`Grid`/`Container`/`Text` (Polaris/Radix Themes/Geist têm). **Falta no oimpresso** — ver §2.1. |
| **Lint estrito, baseline → ZERO** | O ratchet nunca sobe; baseline só **encolhe**. `__parser_error__`/`no-undef` são P0 (bug), não dívida tolerada. |
| **Um build, um grafo** | Um pipeline Vite. Code-splitting por rota automático. Bundle budget no CI. |
| **Visual regression automatizado** | Snapshot por componente/tela (Playwright/Chromatic-like) — o projeto já tem `design:review` + screen-grade; ligar o gate. |
| **Acessibilidade é gate, não opção** | `jsx-a11y` sem baseline crescente + axe nos testes de tela (screen-qa). |

**Regra-mestre (Constituição UI v2):** Fundações (tokens) → Shell → Padrão de Tela → Módulo. Camada superior **herda** e **nunca contradiz**. CSS bespoke por-tela viola isso por construção.

---

## 2.1 A camada que FALTA: primitivos de layout

> Achado da auditoria 2026-06-06: o REGISTRY do projeto cobre componentes de **UI/form**
> (`@/Components/ui`), mas **não existe nenhum primitivo de LAYOUT**. Hoje o layout é feito com
> `flex`/`grid` solto espalhado nas telas OU dentro do CSS bespoke — o oposto de sistema. Essa é a
> única lacuna **fora** do canon de design atual (identidade/governança já estão resolvidas no SSOT).

**O que os melhores têm (e nós não):** uma camada fina de primitivos onde *espaço* só vem de token.

| Primitivo | Faz | Equivalente best-in-class |
|---|---|---|
| `Box` | container neutro com props de espaço/cor via token | Polaris Box · Radix Themes Box |
| `Stack` | empilha vertical com `gap` token | Polaris BlockStack · Geist |
| `Inline` | alinha horizontal com `gap` + wrap | Polaris InlineStack |
| `Grid` | grid responsivo por colunas-token | Radix Grid · Polaris Grid |
| `Container` | largura máxima + padding de página | Geist Container |
| `Text` | tipografia 100% via type-scale token (sem `text-[22px]` solto) | Radix Text · Primer |

**Regra:** assim que `Components/layout/` existir, layout é **composição de primitivos**, nunca
`<div className="flex gap-4 ...">` repetido nem `.css`. Wrappers finos Tailwind+CVA; props = tokens
DS v6. Entra no roadmap §5 (passo de criação) e vira itens do REGISTRY quando pronto.

---

## 3. As regras duras (do / don't)

### CSS
- ✅ **DO** estilizar via classes Tailwind no JSX. Cor/espaço/tipo **sempre** via token (`bg-primary`, `text-muted-foreground`, `gap-4`), nunca hex/px literal.
- ✅ **DO** abstrair repetição em **componente** (CVA), não em arquivo `.css`.
- ❌ **DON'T** criar `resources/css/<tela>.css` ou `<modulo>-bundle.css`. Arquivo CSS novo exige ADR.
- ❌ **DON'T** colar CSS de protótipo Cowork direto. O protótipo é referência visual; a saída é Tailwind+componentes (ver [RUNBOOK-replicar-prototipo-cowork](RUNBOOK-replicar-prototipo-cowork.md), fase F2 mapping CSS→Tailwind).
- ❌ **DON'T** usar `!important` (sinal de guerra de especificidade = falta de token/componente).

### JS/TS
- ✅ **DO** TS strict, sem `any` (use `unknown` + narrow). Props tipadas. Zod nos boundaries (já é dep).
- ✅ **DO** componente pequeno e puro; lógica de dados em hooks (TanStack Query). `Inertia::defer` pra payload pesado (skill `inertia-defer-default`).
- ✅ **DO** rodar `npm run lint` + `npm run typecheck` antes de PR. Baseline só encolhe.
- ❌ **DON'T** suprimir erro novo no baseline pra "passar o CI". Baseline é dívida congelada, não lixeira.
- ❌ **DON'T** declarar helper/const global no escopo de arquivo de teste sem `function_exists`/escopo único (colisões — US-INFRA-031).

---

## 4. Como fazer — workflow concreto (ferramentas que JÁ existem)

```bash
# Antes de codar UI:
#  - ler Constituição UI v2 (ADR UI-0013) + Padrão de Tela aplicável + PRE-MERGE-UI

# Durante:
npm run dev:inertia          # dev server Inertia (HMR)
npm run typecheck            # tsc --noEmit (strict)
npm run lint                 # ESLint 9 flat
npm run stylelint            # Stylelint

# Gates de design-system (rodar antes do PR):
npm run foundation:check     # tokens/fundações não driftaram
npm run conformance:check    # conformidade com o DS
npm run ds:report            # relatório de uso do DS
npm run design:review:check  # frescor da review visual

# Baseline (só pra ABSORVER dívida pré-existente reconhecida, nunca pra esconder bug novo):
npm run lint:baseline:check        # CI: falha se delta > 0
npm run stylelint:baseline:check

# Testes:
npm run test                 # Vitest (unit/conformance)
# E2E/visual + axe de tela: agente screen-qa-specialist (/screen-qa <Mod>/<Tela>)
# Pest backend: SEMPRE no CT 100 (feedback-testes-no-ct100-nao-local), nunca local.
```

Replicar protótipo Cowork **do jeito certo** (sem gerar `.css` novo): [RUNBOOK-replicar-prototipo-cowork](RUNBOOK-replicar-prototipo-cowork.md) — F1 vocabulário → **F2 mapping CSS Cowork → Tailwind tokens** → F3 hierarquia de componentes → F4 perf → F5 Pest.

---

## 5. Plano de migração — sair do sprawl (incremental, por sinal)

> Não é big-bang. Cada tela tocada paga um pedaço. Ordem por impacto×esforço.
> **O roadmap executável vive como tasks MCP** (ADR 0070 — trabalho rastreado nas tools `tasks-*`,
> não em tabela `.md`), módulo `_DesignSystem`, prefixo **F0–F7**. Esta lista é o mapa; o backlog é a verdade.

1. **F0 · Congelar o crescimento** (já feito em parte): gate que **proíbe arquivo `.css` novo** em `resources/css/` sem ADR + proíbe crescer linhas dos bundles (ratchet de tamanho) + rule `.claude/rules/css.md` (carrega SSOT+manual ao tocar CSS). _Esforço baixo, trava o problema._
2. **F1 · Inventariar o débito de identidade**: mapear azul de **marca** legacy (`#1572E8`, `@apply`) vs azul **semântico** que sobrevive; listar onde `.cockpit` define token paralelo ao `@theme`. _Baixo. Sem ADR — cor já é canon (0235/0249)._
3. **F2 · Token único em `@theme`**: dobrar `.cockpit`/legacy nos tokens DS v6 do Tailwind v4 (`inertia.css`); `cockpit.css` passa a **consumir** (`var(--…)`), não definir. `foundation:check` cobre. _Médio._
4. **F3 · Criar `Components/layout/`** (Box/Stack/Inline/Grid/Container/Text — §2.1): a camada que falta. Doc no DS + ≥1 tela composta 100% por primitivos (zero flex solto, zero `.css`). _Médio._
5. **F4 · Unificar o `PageHeader` (incremental — NÃO big-bang)**: há 2 hoje — o **canon novo** `@/Components/PageHeader` (v3.8 flat, ADR 0189/0190, ~15 telas) e o **antigo** `@/Components/shared/PageHeader` (**104 telas**). Migrar 104 é visual-pesado (aprovação por tela). Política: **(a) congelado** — `pageheader-gate` (ratchet `pageheader-shared-baseline.json`) proíbe tela NOVA adotar o antigo; **(b)** toda tela tocada migra seu header antigo→canon no mesmo PR (aprovação visual natural); **(c)** `shared/PageHeader` só é **deletado** quando o contador zerar. _Incremental._
6. **F5 · Dissolver os 2 mega-bundles** (`cowork-canon-financeiro-bundle` ~4.7k + `sells-cowork` ~4.0k — já caíram de 8.6k/7.5k) tela-a-tela: tela tocada (MWART/feedback) é reescrita em Tailwind+primitivos e a fatia correspondente do bundle é **deletada**. Medir: linhas bespoke ↓ a cada PR. _Alto, incremental._
7. **F6 · Saúde do lint**: zerar `__parser_error__` + `no-undef` (bugs P0); depois encolher `jsx-a11y/*`. _Médio._
8. **F7 · Gates de regressão**: ligar visual-regression como gate (screen-grade/`design:review` existem) + axe nas telas (`screen-qa`); unificar `vite.config` quando o último Blade morrer. _Médio, dep. MWART._

**Métrica-mãe deste manual:** linhas de CSS em `resources/css/` **caindo** PR a PR (**~20.294** em 2026-06-06, de ~28.5k) + baseline ESLint **encolhendo** (**1.073**, de 1.340) + fontes de token **3 → 1** + telas no `PageHeader` antigo **104 → 0** (ratchet `pageheader-gate`), sem regressão visual.

---

## 6. Checklist pré-merge (UI)

- [ ] Zero hex/px literal — tudo token.
- [ ] Nenhum arquivo `.css` novo (ou ADR justificando).
- [ ] `typecheck` + `lint` + `stylelint` verdes; baseline não cresceu.
- [ ] `foundation:check` + `conformance:check` verdes.
- [ ] Componentes Radix+CVA reusados (não estilo redefinido na página).
- [ ] Acessibilidade: sem novo `jsx-a11y/*`; axe na tela (screen-qa).
- [ ] Se tocou tela com bundle Cowork: deletou a fatia correspondente do `.css` (sprawl ↓).
- [ ] PRE-MERGE-UI + Padrão de Tela aplicável conferidos.

---

## Refs

- [Constituição UI v2 — ADR UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md) · [PRE-MERGE-UI](PRE-MERGE-UI.md) · [Padrões de Tela](padroes-tela/)
- [ADR 0209 — ESLint 9 baseline ratchet](../../decisions/0209-eslint-9-flat-config.md)
- [RUNBOOK replicar protótipo Cowork](RUNBOOK-replicar-prototipo-cowork.md) · [ARCHITECTURE](ARCHITECTURE.md) · [SCREEN-GRADE-METODO](SCREEN-GRADE-METODO.md)
- Estado-da-arte comparado: Linear · Stripe · Shopify Polaris · GitHub Primer · Vercel/Geist · Notion
