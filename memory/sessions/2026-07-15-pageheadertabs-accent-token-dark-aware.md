# 2026-07-15 — PageHeaderTabs: aba ativa binda tokens dark-aware (fecha resíduo de cor)

**Sessão:** happy-wu-6f36bd · off-cycle · worktree `pht-accent` @ origin/main
**PR:** [#4297](https://github.com/wagnerra23/oimpresso.com/pull/4297) **MERGED** (squash `37d3217`) → deploy prod success → prod live 200
**Continuação de:** [handoff 2026-07-15 00:30 consolidação DS tabnav](../handoffs/2026-07-15-0030-consolidacao-ds-tabnav-dark.md) (chip dark-fix `task_b2b0f4ee`)

## O quê

O componente canônico `resources/js/Components/shared/PageHeaderTabs.tsx` hardcodava `oklch(0.55 0.15 295)` (4×) no `style` inline da aba ATIVA — o smell que a regra `ds/no-inline-raw-color` combate, mas fora do escopo de tela do gate (é camada DS). Bindado nos tokens do protótipo Cowork (`.cli-moduletopnav-tab.active`):

| prop | antes | depois |
|---|---|---|
| underline (`borderBottomColor`) | `oklch(0.55 0.15 295)` | `var(--accent)` |
| pill (`backgroundColor`) | `oklch(0.55 0.15 295 / 0.10)` | `color-mix(in oklch, var(--accent-soft) 50%, transparent)` |
| ícone ativo (`color`) | `oklch(0.55 0.15 295)` | `var(--accent)` |
| badge ativo (bg/fg) | `oklch(0.55 0.15 295)` / `oklch(0.99 0 0)` | `var(--accent)` / `var(--accent-fg)` |

## Pré-requisito investigado (o risco NÃO se confirmou)

A dúvida-âncora era: existe `--accent` **global** dark-aware, ou ele só vive scopeado a bundles (`fin-cowork.css`, `sells-cowork*.css`)? Se `var(--accent)` não estivesse em escopo numa tela sem bundle, o underline/pill perderia a cor (foi por isso que o literal foi hardcodado).

**Achado (fatos reutilizáveis pra futuro trabalho de token DS):**
- Os tokens `--accent` / `--accent-soft` / `--accent-fg` vivem na **camada gerada** `resources/css/tokens/_generated-cockpit-{light,dark}.css` (Style Dictionary DTCG, NÃO editar à mão), importada por `resources/css/cockpit.css`.
- O `AppShellV2` (`resources/js/Layouts/AppShellV2.tsx:465`) embrulha **todo** o app em `.cockpit[data-theme=…]`. Logo `var(--accent)` resolve em **toda** tela que usa PageHeaderTabs (Financeiro, Clientes, Sells, Jana, Ponto…), não só nas com bundle. **Não precisou criar token de Fundações.**
- **Nuance:** `--accent` NÃO é diferenciado no dark (o `_generated-cockpit-dark.css` só redefine `--accent-soft`: light `0.95 0.04 295` → dark `0.32 0.06 295`; `--accent` e `--accent-fg` herdam o light `0.55 0.15 295` / `#fff`). Então:
  - bindar underline/ícone/badge em `var(--accent)` = **pixel-idêntico no light E no dark hoje** (puro refactor + governança).
  - o **pill** é o único ponto onde o dark melhora de fato hoje, porque `--accent-soft` é dark-aware.
- **Fonte canônica** (ADR 0299): o protótipo `prototipo-ui/cowork/clientes-page.css` `.cli-moduletopnav-tab.active` usa `var(--accent)` + `color-mix(--accent-soft 50%)`. O literal `accent/10%` do componente era um **desvio** do protótipo.

## Decisão (fork do pill — apresentado ao [W] com harness claro+escuro)

Duas opções, ambas mostradas num harness HTML com os tokens reais:
- **A conservador** — pill `oklch(from var(--accent) l c h / 0.1)`: pixel-idêntico light+dark; só governança; dark não melhora visualmente.
- **B fiel ao protótipo** (ESCOLHIDA) — pill `color-mix(--accent-soft 50%)`: idêntico ao Cowork; dark fica legível (`0.32 0.06 295 @50%` vs `0.55/10%` quase invisível); light muda de leve (lavanda pálido).

Não dá pra ter "light idêntico" **e** "dark visivelmente melhor" no pill com os tokens de hoje (só `--accent-soft` é dark-aware). Um dark mais forte no próprio underline exigiria adicionar `--accent` dark no `_generated-cockpit-dark.css` = **decisão de Fundações → ADR UI separado** (não forçado neste PR).

## Teste de fidelidade — atualizado conscientemente

`tests/pageHeaderTabsFidelity.spec.tsx` (o ponto único de verdade da fidelidade, fecha o bug do `rounded-md` que [W] pegou no olho em 2026-07):
- `ACCENT = 'var(--accent)'` (era `'oklch(0.55 0.15 295)'`)
- `PILL_BG = 'color-mix(in oklch, var(--accent-soft) 50%, transparent)'` (era `ACCENT_SOFT = 'oklch(0.55 0.15 295 / 0.1)'`)
- **jsdom preserva valores contendo `var(` verbatim** — provado por probe (`node` + jsdom real do repo) ANTES de commitar, pra não queimar loop de CI. O runner do CI serializa igual.

## Evidência (todos os gates verdes)

- Gate dedicado `PageHeaderTabs · aba ativa fiel ao protótipo (radius 0 · accent · font 600)`: ✅
- `visual-regression` (required, pixel-diff enforcing): ✅ — o delta do pill no light ficou dentro da tolerância.
- `Screen Smoke After Merge`: ✅ · `Vite build`: ✅ · `E2E Playwright`: ✅ · `Pest Unit`: ✅ — **69 pass / 0 fail**.
- Deploy prod success · `curl` prod `/login` e `/` → HTTP 200.

## Ressalva honesta

A captura de screenshot do Browser pane da sessão travou por completo (a página renderiza — `read_page`/`javascript_tool` funcionam, zero console error — só `screenshot`/`zoom` dão timeout, inclusive na tela de login). Então o smoke visual pós-merge ficou por conta dos gates automáticos de imagem (`visual-regression` + `Screen Smoke After Merge`, ambos verdes) e do harness claro+escuro que [W] revisou antes do merge — não de uma captura manual minha.

## Follow-up aberto (opcional, não forçado)

Se quiser o dark mais forte no **próprio underline** (mexer no valor de `--accent` no dark, hoje herdado do light): decisão de Fundações → ADR UI que adiciona `--accent` dark em `_generated-cockpit-dark.css`, aí o dark melhora de UM lugar só em todo componente que usa `var(--accent)`.

## Refs

ADR 0299 (protótipo é fonte de design) · ADR 0190 (primary/accent roxo 295) · ADR 0338 (eixo valor-vs-token) · ADR 0258 (todo ✅ visto falhar) · [handoff consolidação tabnav 00:30](../handoffs/2026-07-15-0030-consolidacao-ds-tabnav-dark.md)
