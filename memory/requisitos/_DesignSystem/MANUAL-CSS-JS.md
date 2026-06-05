---
name: Manual CSS & JS — como deveria ser e como fazer no oimpresso
description: Manual canônico de administração de CSS/JS (frontend) do oimpresso — arquitetura-alvo comparada com os melhores (Linear/Stripe/Shopify Polaris/GitHub Primer/Vercel), diagnóstico do estado atual com números, as regras duras, o workflow concreto com as ferramentas que já existem (ESLint/Stylelint ratchet, conformance-gate, foundation-guard, ds-report), e o plano de migração pra sair do sprawl de 28k linhas de CSS bespoke.
type: reference
status: ativo
owner: wagner
version: "1.0"
last_updated: "2026-06-04"
related_adrs: ["0094-constituicao-v2-7-camadas-8-principios", "0209-eslint-9-flat-config"]
---

# Manual — CSS & JS no oimpresso: como deveria ser e como fazer

> **Origem:** 2026-06-04 — Wagner: *"investigar a lista de problemas de css e js, isso está
> bem administrado como deveria ser? compare com os melhores. Crie um manual de como deveria
> ser e como fazer."*
>
> **Veredito de uma linha:** o **JS/TS está bem administrado** (TS strict, stack moderna,
> lint com ratchet); o **CSS NÃO** — ~28k linhas de CSS bespoke por-tela (dumps de protótipo
> Cowork) minam o design-system que já existe no papel. Este manual define o alvo e o caminho.

---

## 1. Diagnóstico atual (com números — 2026-06-04)

### ✅ O que JÁ está bom (manter)

- **TypeScript strict** — `tsconfig.json` com `strict: true` + `noUncheckedIndexedAccess`. (top-tier; muita gente não tem).
- **Stack moderna e correta** — React + Inertia v3, **Tailwind v4** (`@tailwindcss/vite`), **Radix UI** (primitivos acessíveis), **CVA** + `clsx` + `tailwind-merge` (variantes type-safe), TanStack Query/Table, `lucide-react`, Vite.
- **Lint com ratchet** — ESLint 9 flat-config ([ADR 0209](../../decisions/0209-eslint-9-flat-config.md)) + Stylelint, ambos com baseline JSON e CI que **falha só em regressão** (delta > 0). `eslint-plugin-jsx-a11y` ligado (acessibilidade).
- **Governança de design-system** — scripts `ds:report`, `conformance:check`, `foundation:check`, `design:review` + Vitest (`tests/conformanceGate.spec.ts`, `tests/foundationGuard.spec.ts`). Constituição UI v2 (4 camadas) + Padrões de Tela + PRE-MERGE-UI.

### ❌ A "lista de problemas" (o que está errado)

1. **CSS sprawl — 28.585 linhas em 33 arquivos.** Dois arquivos concentram **57%**:
   - `resources/css/cowork-canon-financeiro-bundle.css` — **8.666 linhas**
   - `resources/css/sells-cowork.css` — **7.540 linhas**
   - +~20 arquivos `sells-cowork-*`, `cowork-*`, `fin-*` — **CSS por-tela/por-módulo**, o oposto de um design-system. São dumps do protótipo Cowork colados, não tokens compartilhados.
2. **Dívida de lint — 1.340 violações ESLint suprimidas** no baseline (ADR 0209). Inclui `__parser_error__` (arquivo que nem parseia) e `no-undef` (bug real escondido) + vários `jsx-a11y/*` (dívida de acessibilidade).
3. **Build dual** — `vite.config.js` (legacy Blade) + `vite.inertia.config.mjs` (Inertia). Migração Blade→Inertia (MWART) inacabada; dois pipelines coexistindo.
4. **Tokens não são fonte única** — Tailwind v4 existe, mas 28k linhas de CSS bespoke definem cor/espaço/tipo à mão em paralelo aos tokens → drift visual e impossível refatorar com confiança.
5. **Colisões de símbolo global** (tests/helpers) — sintoma cultural de copy-paste (mesma raiz do sprawl CSS). Ver [feedback-nao-tocar-pr-fora-escopo] / US-INFRA-031.

---

## 2. Como deveria ser — arquitetura-alvo (comparada com os melhores)

Referências estado-da-arte 2026: **Linear, Stripe, Shopify Polaris, GitHub Primer, Vercel/Geist, Notion**. O padrão comum:

| Princípio dos melhores | Tradução pro oimpresso |
|---|---|
| **Token é a fonte única** (cor/espaço/tipo/raio/sombra) | Tudo vem de `@theme` Tailwind v4 + camada Fundações da Constituição UI v2. Nenhum hex/px solto. |
| **Utility-first, ZERO CSS por-página** | Estilo mora no JSX via classes Tailwind. `resources/css/*` só pra: reset, tokens, e raras exceções globais. **Não existe `sells-cowork.css`.** |
| **Componentes, não páginas, são a unidade** | Primitivos Radix + variantes CVA em `resources/js/Components/ui/`. Telas compõem componentes; não redefinem estilo. |
| **Lint estrito, baseline → ZERO** | O ratchet nunca sobe; baseline só **encolhe**. `__parser_error__`/`no-undef` são P0 (bug), não dívida tolerada. |
| **Um build, um grafo** | Um pipeline Vite. Code-splitting por rota automático. Bundle budget no CI. |
| **Visual regression automatizado** | Snapshot por componente/tela (Playwright/Chromatic-like) — o projeto já tem `design:review` + screen-grade; ligar o gate. |
| **Acessibilidade é gate, não opção** | `jsx-a11y` sem baseline crescente + axe nos testes de tela (screen-qa). |

**Regra-mestre (Constituição UI v2):** Fundações (tokens) → Shell → Padrão de Tela → Módulo. Camada superior **herda** e **nunca contradiz**. CSS bespoke por-tela viola isso por construção.

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

1. **Congelar o crescimento** (já feito em parte): gate que **proíbe arquivo `.css` novo** em `resources/css/` sem ADR + proíbe crescer linhas dos bundles (ratchet de tamanho). _Esforço baixo, trava o problema._
2. **Tokenizar** `cockpit.css`/`inertia.css` → mover cor/espaço/tipo pra `@theme` Tailwind v4 (fonte única). _Médio._
3. **Dissolver os 2 mega-bundles** (`cowork-canon-financeiro-bundle` 8.6k + `sells-cowork` 7.5k) tela-a-tela: quando uma tela Financeiro/Sells for tocada (MWART/feedback), reescrever o estilo dela em Tailwind+componentes e **deletar** a fatia correspondente do bundle. Medir: linhas de CSS bespoke ↓ a cada PR. _Alto, mas incremental._
4. **Zerar `__parser_error__` + `no-undef`** do baseline ESLint (são bugs, P0). Depois encolher `jsx-a11y/*`. _Médio._
5. **Unificar build** quando o último Blade legacy morrer → um `vite.config`. _Depende do fim do MWART._
6. **Ligar visual-regression como gate** (screen-grade/design:review já existem) — trava regressão visual ao refatorar CSS. _Médio._

**Métrica-mãe deste manual:** linhas de CSS em `resources/css/` **caindo** PR a PR (hoje 28.585) + baseline ESLint **encolhendo** (hoje 1.340), sem regressão visual.

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
