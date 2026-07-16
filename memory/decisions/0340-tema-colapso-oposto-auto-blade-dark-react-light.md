---
slug: 0340-tema-colapso-oposto-auto-blade-dark-react-light
number: 340
title: "O tema colapsa em direções OPOSTAS no mesmo request — `auto`→dark no Blade × `auto`→light no React; o gate visual fotografa o híbrido (errata da premissa da 0281)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-16"
module: design-system
quarter: 2026-Q3
tags: [ui, dark-mode, tokens, foundations, cockpit, shell, visual-regression, gate, tier-0, errata, wcag, a11y]
supersedes: []
superseded_by: []
related:
  - 0281-dark-mode-bridge-data-theme-tokens
  - 0114-prototipo-ui-cowork-loop-formalizado
---

# ADR 0340 — O tema colapsa em direções opostas no mesmo request

## Contexto

O [PR #4367](https://github.com/wagnerra23/oimpresso.com/pull/4367) corrigiu o defeito
principal do Quadro de OS da Oficina no escuro (14 `bg-white` → tokens). Sobrou um
**resíduo medido**: as 2 colunas de espera ficavam em AA-large, não AA. A leitura inicial
foi *"`bg-muted/40` é translúcido e deixa passar um fundo claro — a translucidez é o
problema"*, com a hipótese de que o `--atmo` do `.cockpit` estaria sem par `dark`.

**As duas leituras estão erradas.** O par `dark` do `--atmo` existe
([`_generated-foundations-dark.css:9`](../../resources/css/tokens/_generated-foundations-dark.css)).
E a translucidez não é a causa — é o **revelador**. A causa é que o `.cockpit` renderiza
com os tokens **light** enquanto o Tailwind renderiza **dark**: um **híbrido**.

Isto é Fundação/Shell (Constituição UI v2 · [ADR UI-0013](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)),
por isso a decisão é registrada aqui e não no charter da tela.

### A cadeia — provada em código, não deduzida

| # | Elo | Prova |
|---|---|---|
| 1 | `HandleInertiaRequests` roda **antes** do `VisregStateMiddleware` | [`Kernel.php:55`](../../app/Http/Kernel.php) × [`:59`](../../app/Http/Kernel.php) — mesmo grupo `web`, ordem de array |
| 2 | O `share()` do Inertia é **eager** (antes do `$next`) | `vendor/inertiajs/inertia-laravel/src/Middleware.php:116` — `Inertia::share($this->share($request))` |
| 3 | O `ui_theme` da prop é leitura **escalar eager**, não closure | `HandleInertiaRequests::share()` → `'ui_theme' => $user->ui_theme` |
| 4 | O override do VRT chega **tarde demais** para a prop | [`VisregStateMiddleware`](../../app/Http/Middleware/VisregStateMiddleware.php) — `$user->ui_theme = 'dark'` |
| 5 | O React colapsa o ausente em **light** | [`AppShellV2.tsx:242`](../../resources/js/Layouts/AppShellV2.tsx) — `?? 'light'` → `:471` `data-theme={userTheme}` |
| 6 | O Blade colapsa o mesmo ausente em **dark** | [`inertia.blade.php`](../../resources/views/layouts/inertia.blade.php) — script anti-flash faz `classList.add('dark')` sem tocar `data-theme` |
| 7 | Os tokens dark do cockpit exigem o seletor que não casa | `_generated-cockpit-dark.css` → `.cockpit[data-theme="dark"]` |

O comentário do próprio `VisregStateMiddleware` — *"blade + share leem a mesma instancia"* —
é **falso**: leem a mesma instância em **tempos diferentes**, e o `share()` já copiou o
escalar 4 linhas antes na pilha.

### O que foi medido (baseline canon `20e947c0`, não impressão visual)

⚠️ A primeira medição desta investigação foi feita num worktree **3 commits atrás**, sobre
o `.snap` **pré-#4367**. Refeita no canon — a conclusão sobreviveu e a prova melhorou.

Cadeia de composição de 3 camadas, prevista × medida (±2):

| Camada | Previsto | Medido (canon) |
|---|---|---|
| cockpit `--bg` **light** | ≈(249,249,249) | *(coberto pelo board)* |
| + `bg-muted/40` do board | (160,162,162) | **(160,162,163)** — 48,4% da tela |
| + coluna `dark:bg-amber-950/30` | (132,121,115) | **(132,121,113)** |
| + coluna `dark:bg-violet-950/25` | (131,125,147) | **(132,124,148)** |

Assinaturas decisivas no mesmo PNG:

- `bg-card` dark (42,46,49) = **24,0%** → o Tailwind dark **está ligado**;
- cockpit `--bg` dark (33,36,39) = **0 px** → os tokens dark do cockpit **nunca são aplicados**;
- em `sells_index · dark`, o `--bg` light do cockpit está **diretamente visível em 62% da tela**
  (1.197.400 px) coexistindo com `bg-card` dark — a assinatura do híbrido.

Contraste WCAG do título das colunas (`text-foreground`), **mesmo código do Board**:

| Coluna | Hoje (cockpit vazando light) | Se o cockpit renderizasse dark |
|---|---|---|
| Aguardando aprovação | **4,06:1** ✗ AA | **15,23:1** ✓ |
| Aguardando peças | **3,80:1** ✗ AA | **15,42:1** ✓ |
| *Controle: Recepção (`bg-card` opaco)* | *13,12:1 ✓* | *(não vaza)* |

O **controle fecha a causa**: fundo opaco passa, translúcido reprova — a variável é o que
está **por baixo**, não o Board.

## Decisão

**O resíduo NÃO é defeito do Board e não se corrige no Board.** Três frentes:

### D-1 — O estado `dark` do gate visual é um híbrido; o harness mente

O VRT fotografa um estado que **nenhum usuário de dark explícito vê**. As 6 baselines dark
estão erradas por construção. O override precisa acontecer **antes** do `share()` do
Inertia (ordem no `Kernel`) ou por um caminho que a prop enxergue. Enquanto isso não
mudar, `visreg` verde no escuro **não é evidência de dark correto** — é a família já
catalogada em [proibicoes §2026-06-29](../proibicoes.md) (*"CI verde não prova render"*).

### D-2 — Em produção, o modo `auto` sofre o mesmo híbrido

Blade colapsa `auto → dark` (consultando o SO via anti-flash); React colapsa `auto → light`
(coalescência cega). O elo a corrigir é o **`?? 'light'` do `AppShellV2:242`** — não o CSS.
Agrava:

- `users.ui_theme` é `DEFAULT NULL` e o **único** writer é o `UserPreferencesController`,
  acionado só pelo subpainel *Aparência* (`Sidebar.tsx:1075`, condicional) — logo **NULL é o
  estado padrão de quem nunca o abriu**, não um edge case;
- `ThemeToggle.tsx` é **órfão** (zero imports) — o hook existe sem caller;
- `inertia.css` tem `html.dark { color-scheme: dark }` → scrollbars/controles nativos
  escuros sobre shell claro **agravam** o híbrido;
- `visreg-states.json` **não tem estado `auto`** → o cenário é invisível a todos os gates;
- **a cura existe e nunca é invocada**: [`useTheme.ts:103-104`](../../resources/js/Hooks/useTheme.ts)
  (`applyClass`) seta `data-theme` no `.cockpit` — mas só roda no subpainel *Aparência*.
  Abrir *Aparência* **cura o sintoma sozinho**, o que torna o repro manual traiçoeiro.
  É o anti-padrão *"correção-do-mecanismo ≠ invocação"* ([proibicoes §2026-07-09](../proibicoes.md)).

### D-3 — Errata à premissa da [ADR 0281](0281-dark-mode-bridge-data-theme-tokens.md)

A 0281 (aceita 2026-06-16) afirma: *"O furo: **nada nunca aplica a classe `.dark`** (o
AppShellV2 só seta `data-theme`)"*. **Isso era factualmente falso quando foi escrito** —
verificado no blade no commit `3072c2a7` (2026-06-16): `$htmlClass = $userTheme === 'dark'
? 'dark' : ''` + script anti-flash **já existiam desde 2026-04-22** (`aadab18a29`).

A 0281 também **rejeitou** a alternativa *"aplicar `.dark` no `<html>` via JS espelhando
`ui_theme`"* por ser *"dual de estado (classe + atributo), mais JS, risco de flash"* — sem
notar que **esse dual de estado já era o mecanismo vigente** no blade. E menciona `auto`
**zero vezes**.

**A decisão da 0281 (o bridge de seletor) permanece válida e em vigor** — por isso esta ADR
não a supersede. Só a **premissa** é corrigida, append-only, como manda o processo.

## Consequências

**Positivas**
- O Board fica **intocado**: as duas colunas vão a ~15:1 sem uma linha de `.tsx`.
- Fix sistêmico de fonte única — vale para as 398 Pages do `AppShellV2`.

**Riscos / pegadinhas**
- **Blast radius = app inteiro** (mesma natureza da 0281). Telas que "pareciam ok no
  escuro" só porque o shell estava claro podem revelar contraste a corrigir. **Latente,
  não introduzido** — igual ao risco que a própria 0281 registrou.
- **As 6 baselines dark mudam massivamente** — 62% dos pixels em `sells_index`, 41% no
  financeiro. **Não é regeneração de formalidade**: é evento visual grande e o τ vai gritar
  com razão. Regenerar **depois** do fix, no ref do branch. ⛔ Não afrouxar τ_alto nem usar
  `visreg-gray-approved` para mascarar.
- **Conflito de sessão paralela**: `claude/oficina-kpi-dark-tons` está empilhada no #4367 e
  edita o mesmo `boardTone.ts`. O fix de KPI dela é **legítimo e imune** (fundo `bg-card`
  **opaco** não vaza) e deve seguir. Mas o enquadramento *"translúcido ⇒ compensar"* é o que
  esta ADR corrige — **escurecer as colunas agora as deixaria escuras demais** quando o
  shell for consertado.

## Alternativas consideradas

- **Escurecer mais as colunas no `boardTone.ts`** (compensar o vazamento): over-fit a um
  shell quebrado; quebra ao consertar a causa. **Rejeitada** — trata o revelador, não a causa.
- **Aplicar `dark:` por-tela onde o vazamento aparecer**: multiplica dívida em 398 Pages e
  contradiz tokens. **Rejeitada** (é a mesma alternativa que a 0281 já rejeitou).
- **Chamar `applyClass()` num `useEffect` global no boot**: conserta depois da hidratação —
  aceita flash e mantém o dual de estado. **Rejeitada como fix primário**; o colapso
  `auto` deve ser resolvido onde nasce (server-side/prop).

## Verificação

**Feita (não-executada além disto):** medição por pixel do `.snap` canon `20e947c0`
(composição de 3 camadas batendo em ±2 + controle opaco a 13,12:1) e leitura literal dos 7
elos da cadeia, incluindo o `vendor`.

**Pendente (Tier 0, R1 — smoke real):**
1. `auto` em produção: logar com `ui_theme=NULL`, SO em dark, `localStorage.removeItem('oi.theme')`,
   F5, e inspecionar `document.querySelector('.cockpit').dataset.theme` (esperado `"light"`)
   contra `document.documentElement.classList` (esperado conter `"dark"`).
   **Não abrir o subpainel *Aparência* antes** — ele cura o sintoma.
2. População afetada: `count(*) WHERE ui_theme IS NULL` × quantos rodam SO em dark. É o
   único elo genuinamente empírico — a cadeia CSS/JS está provada, a **população não**.
3. Magnitude visual do híbrido em produção (governa a severidade de D-2, não a existência).

## Lição de método

O briefing desta investigação trazia **números certos medidos na cena errada**. É o
corolário seguinte do **LC-06** (*"o strike 1 é olhar; o strike 2 é medir a coisa errada — e
esse passa despercebido porque vem com número"*): medir o **pixel certo** num **rig que
renderiza um estado que não existe em produção**. Antídoto aplicado aqui: **controle que
isola a variável** (a coluna opaca a 13,12:1) e **conferência da baseline contra o canon**
antes de acreditar no número.
