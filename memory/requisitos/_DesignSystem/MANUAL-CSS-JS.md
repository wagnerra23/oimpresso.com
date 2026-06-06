---
name: Manual CSS & JS — como deveria ser e como fazer no oimpresso
description: Manual canônico de administração de CSS/JS (frontend) do oimpresso — arquitetura-alvo comparada com os melhores (Linear/Stripe/Shopify Polaris/GitHub Primer/Vercel), diagnóstico do estado atual com números, as regras duras, o workflow concreto com as ferramentas que já existem (ESLint/Stylelint ratchet, conformance-gate, foundation-guard, ds-report), e o plano de migração pra sair do sprawl de 28k linhas de CSS bespoke.
type: reference
status: ativo
owner: wagner
version: "1.1"
last_updated: "2026-06-06"
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

---

## Anexo A — Financeiro: ordem de dissolução de-riscada (2026-06-06)

> **Origem:** Wagner 2026-06-06 — *"Design está me ferrando, tem muito css do ultimatepos
> ainda e os novos que botei no sótão em conflito"* → foco escolhido: **Financeiro**.
> Instancia o **passo 3** deste manual pra UM módulo, com as armadilhas já mapeadas
> (sessão de investigação, branch `claude/design-css-conflicts-k2T6H`).
>
> **Veredito:** o "conflito" do Financeiro **não é CSS morto pra deletar** — é uma
> **migração pela metade**. Algumas telas no token novo, ~7 no CSS bespoke, e Fluxo/DRE/Caixa
> ainda em Blade legado. Parece "ferrado" porque convivem 3 gerações de estilo na mesma área.

### A.1 Snapshot medido (2026-06-06)

| Item | Valor |
|---|---|
| CSS Financeiro (`cowork-canon-financeiro-bundle` + `fin-cowork/curadoria/ia/output/mobile`) | ~7.046 linhas |
| `cowork-canon-financeiro-bundle.css` | 4.746 linhas (já podado de 8.666 no #2291), 15 `!important` |
| `!important` na família `fin-*` (guerra de especificidade) | **28** total |
| Telas ainda em `.fin-stat` bespoke (não migradas) | **7** |
| Tela migrada pro componente `<FinStatStrip>` | 1 (PlanoContas, #2301 — **smoke visual PENDENTE**) |

### A.2 As 3 armadilhas (DE-RISK — ler antes de tocar)

1. **Poda automática de CSS é INSEGURA no `fin-*`.** Classes compostas dinamicamente
   vivem em runtime mas o token literal não aparece no `.tsx` → `fin-cowork-coverage.py`
   e o gate `verify-prune` marcam **falso-morto**. Confirmadas vivas via template string:
   `fin-frescor-${kind}` (`FinPillFrescor.tsx`), `fin-xlink-${kind}` (`FinCrossLinkify.tsx`),
   `fin-anomaly-${kind}`/`fin-anomaly-sev-${sev}` (`FinAnomalyDetector.tsx`),
   `fin-audit-${kind}`. **NUNCA** deletar essas por coverage — só com grep de prefixo
   (`fin-frescor-`, `fin-xlink-`, `fin-anomaly-`, `fin-audit-`) confirmando 0 composição dinâmica.
2. **A fatia `.fin-stat*` (`fin-cowork.css:117-227`) é load-bearing — NÃO deletar ainda.**
   Sustenta 7 telas: `Unificado/Index`, `Dre/_components/BalanceteView`, `Dre/_components/BalancoView`,
   `Relatorios/Index`, `Fluxo/Index`, `Dashboard/Index`, `Conciliacao/Index`. A fatia só sai
   **depois** que as 7 migrarem pro `<FinStatStrip>` (critério em A.3).
3. **`resources/js/Pages/Financeiro/_cowork-bundle/*.jsx` NÃO é entulho do "sótão".**
   São 10 dumps de referência canônica (underscore = fora do auto-discovery Inertia, nada
   importa, Vite não empacota). README local proíbe editar/renomear/deletar — é a fonte pra
   portar Fluxo/DRE/Caixa/fsm-stepper que faltam. **Deixar quieto.**

### A.3 Ordem de execução (cheapest-first — cada passo exige smoke visual)

> ⚠️ **Cada tela = 1 PR + smoke visual obrigatório** (Chrome MCP no desktop OU staging).
> Sem browser não dá pra fechar — é o gate que o #2301 furou. Não empilhar reescrita não-validada.

1. **Fechar o débito do piloto:** smoke visual de `<FinStatStrip>` em PlanoContas
   (logar staging → `/financeiro/plano-contas` → comparar KPI strip vs prod + reflow 1100/600 + dark).
   Se destoar: `git revert ffc5f5e3d`. **Bloqueia tudo abaixo** (o componente é a base).
2. **Migrar as 7 telas `.fin-stat` → `<FinStatStrip>`** (componente já existe, "visualmente idêntico"):
   ordem por tráfego — `Unificado/Index` → `Dashboard/Index` → `Conciliacao/Index` →
   `Relatorios/Index` → `Fluxo/Index` → `Dre/BalanceteView` → `Dre/BalancoView`.
   Cada PR: troca markup `.fin-stats`/`.fin-stat` pelo componente + smoke + **não** mexe no CSS ainda.
3. **Deletar a fatia `.fin-stat*`** (`fin-cowork.css:117-227` + equivalente em `fin-mobile.css`)
   SÓ quando `grep -rl "fin-stat" resources/js --include=*.tsx | grep -v _cowork-bundle | grep -v FinStatStrip`
   voltar **vazio**. Aí o ratchet `css-size-gate` encolhe sozinho. _Esse é o "delete a fatia" do passo 3 do manual._
4. **Atacar os 28 `!important`** por último, tela-a-tela já tokenizada (cada `!important` removido
   exige smoke — pode mudar render). Não fazer em lote.

### A.4 O que NÃO fazer (regressões já barradas)

- ❌ Rodar `fin-cowork-prune.py` na família `fin-*` e confiar no resultado (armadilha A.2.1).
- ❌ Deletar `.fin-stat*` antes das 7 telas migrarem (armadilha A.2.2).
- ❌ Tocar/mover/deletar `_cowork-bundle/*.jsx` (armadilha A.2.3).
- ❌ Mergear migração de tela sem smoke visual (débito do #2301 — não repetir).

### A.5 Como um ESPECIALISTA faria (o caminho sistêmico — preferir a A.3)

> A.3 é o caminho artesão (seguro, lento, smoke manual por tela). Um sênior de design-system
> **conserta o sistema primeiro** pra que a migração vire mecânica e sem medo. Ordem real:

1. **Corrigir o modelo mental.** O inimigo **não é o UltimatePOS**. Os dois pipelines já estão
   isolados (`inertia.blade.php` carrega só `inertia.css`; o SASS/AdminLTE legado só vive nas
   telas Blade). O conflito real é **intra-Inertia**: bundles bespoke `cowork/fin-*` vs a camada
   de tokens `@theme`. Atacar o UltimatePOS é energia no alvo errado.

2. **Boundary de cascata com `@layer` (a maior alavanca, 1 mudança mata a classe inteira de bug).**
   Declarar `@layer legacy, ds;` e mover os bundles bespoke pra `@layer legacy`, mantendo
   tokens/componentes/utilities Tailwind em camada superior (ou unlayered). Resultado: **o
   design-system SEMPRE vence o bespoke**, independente de especificidade — sem editar as 7k
   linhas. Mata a guerra de especificidade na raiz. _Caveat honesto:_ `!important` inverte a
   ordem entre layers, então os **28 `!important`** ainda precisam sair individualmente; mas o
   grosso (não-important) é neutralizado de uma vez. Tailwind v4 já usa `@layer` nativo — encaixa.

3. **Rede de regressão visual em CI ANTES de refatorar (é o que torna o refactor destemido).**
   O motivo de a migração estar travada é não ter baseline visual automatizado — aí cada tela
   vira smoke manual (o gargalo, e o gate que o #2301 furou). Especialista liga
   **snapshot Playwright/visual-diff** (o projeto já tem `screen-grade` + `design:review`
   meio-construídos) pras ~14 telas Financeiro. Daí pode arrancar CSS e o **diff prova** que nada
   moveu — zero olho humano por PR.

4. **Codemod, não migração manual.** Um `ts-morph`/`jscodeshift` que reescreve o markup
   `.fin-stats`/`.fin-stat` → `<FinStatStrip>` nas 7 telas **de uma vez**, e o visual-diff (passo 3)
   atesta. Editar 7 telas na mão é trabalho de artesão; o sênior automatiza o transform repetitivo.

5. **Token como fonte única (matar o drift quantitativamente).** Script AST que varre cada
   cor/espaço/raio hardcoded nos bundles bespoke e mapeia pro token mais próximo — expõe o drift
   em número e converte o bespoke pra token-referencing. Aí `foundation:check` vira verdade, não aspiração.

6. **Só então** deletar fatias (passo A.3.3) + unificar build quando o último Blade morrer.

**Tradeoff honesto:** isso é ~1-2 semanas de trabalho de plataforma e exige **congelar CSS bespoke
novo** durante (o `css-size-gate` já faz). Mas troca um débito que cresce por uma fundação que se
auto-protege. A sequência que mais alivia rápido: **passo 2 (`@layer`) + passo 3 (visual CI)** —
depois a migração das telas vira mecânica.
