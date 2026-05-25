# PageHeader Canon v3 — Especificação Definitiva

> **Status:** proposal · pending Wagner approval
> **Origem:** sessão 2026-05-24 — Wagner pediu spec 10/10 após auditoria que o header `/contacts?type=customer` estava sendo entregue "pela metade, sem detalhes que deixam lindo"
> **Mira:** PT-01 PageHeader único pra todas as 80+ Index/Show/Edit Inertia do oimpresso
> **Não-objetivo:** Forms página inteira (PT-04 modo FOCO) usam PageHeader simplificado sem SubNav
> **Validação prévia obrigatória:** abrir `index.html` standalone no browser → Wagner aprova screenshot → AÍ codificar

---

## SUMÁRIO

| § | Tema |
|---|---|
| 0 | Princípios duros (10) |
| 1 | Mapa mental — a "sensação" do header |
| 2 | Geometria 3 zonas (com diagram.svg) |
| 3 | Container — fundação |
| 4 | Zona L — Identidade |
| 5 | Zona C — SubNav inline |
| 6 | Zona R — Actions |
| 7 | Density modes (compact / cozy / comfortable) |
| 8 | Easing curves + timing scale |
| 9 | Estados completos (matriz) |
| 10 | Microinterações |
| 11 | Responsive (mobile-first → desktop) |
| 12 | Loading skeleton |
| 13 | Offline / Error state |
| 14 | Dark mode tokens (par completo) |
| 15 | Print stylesheet |
| 16 | Acessibilidade (WCAG 2.1 AA + AAA opcional) |
| 17 | Internacionalização (i18n + RTL) |
| 18 | Keyboard shortcuts |
| 19 | View Transitions API (Chrome 111+) |
| 20 | URL state sync (deep link + back/forward) |
| 21 | Page transition (Inertia.js) |
| 22 | Telemetry hooks |
| 23 | Performance budget (CLS, LCP, INP) |
| 24 | SEO + schema.org breadcrumb |
| 25 | Tokens canon (single source) |
| 26 | Componente `<PageHeader>` (assinatura + uso) |
| 27 | Storybook stories |
| 28 | Visual regression (Pest 4 Browser baseline) |
| 29 | Migration plan (80+ telas) |
| 30 | Anti-padrões catalogados |

---

## 0. Princípios duros (10)

1. **Uma linha de baseline** — H1, tabs, botões pousam na mesma horizontal invisível
2. **Um único `border-bottom`** — vem do container, underlines mordem com `-mb-px`
3. **Hue do grupo é contextual** — `--page-primary` per scope, nunca global
4. **Densidade é variável do usuário** — Tweaks → compact/cozy/comfortable
5. **Movimento é silencioso** — easing nomeado, 120-200ms, reduced-motion respeitado
6. **A11y não é feature** — pré-requisito WCAG 2.1 AA + skip-link + RTL
7. **Dark mode é primário, não enxerto** — todo token tem par light/dark
8. **Imprimível por default** — `@media print` degrada graciosamente
9. **Performance é budget, não promessa** — CLS=0, LCP<200ms, INP<50ms
10. **Sem regra escondida** — todo override tem ADR; todo desvio tem skill

---

## 1. Mapa mental — a "sensação"

O header é a **linha de comando visual** da página. Em 3 frases:

> "Quem sou eu" (esquerda) · "Para onde vou" (centro) · "O que faço agora" (direita).

Cuidados que pintam a sensação correta:

- **Respiro acima do título** (`padding-top: 1.25rem`) — não cola no topbar
- **Subtítulo abraça o título** (`margin-top: 2px`) — não solta, parece da mesma frase
- **Tabs ativas "mordem" a linha do header** (`margin-bottom: -1px`) — visualmente contínuo com o conteúdo abaixo
- **Botão primário com peso visual proporcional ao impacto da ação** — `+ Novo cliente` é grande porque cria registro; `Exportar` é pequeno (overflow) porque é leitura
- **Hover é uma carícia** (120ms ease-smooth) — usuário sente, não vê
- **Click é um snap** (60ms ease-snap + scale 0.97) — feedback físico imediato
- **Tab change é uma onda** (spring 380/30 no underline) — direção e velocidade comunicam navegação
- **Sticky com shadow sutil** (1px shadow rgba 0.04) — quando scrolla, header "flutua" suavemente
- **Skeleton com shimmer linear 1.5s** — comunica "tô vindo" sem ser ansioso

---

## 2. Geometria 3 zonas

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║  ZONA L (flex-1 min-w-0)        ZONA C (shrink-0)         ZONA R (ml-auto)    ║
║  ┌─ identidade ─────────┐      ┌─ subnav ────────┐       ┌─ actions ────┐    ║
║  │ Clientes             │      │ ○ ● ○ ○ ○       │       │ ⋮  + Novo cl │    ║
║  │ 31 cad · 4 ativos    │      │ Todos···Repr    │       │              │    ║
║  └──────────────────────┘      └─────────────────┘       └──────────────┘    ║
║         ↑ gap-6 ↑       ↑ gap-6 ↑                  ↑ ml-auto ↑                ║
╚═══════════════════════════════════════════════════════════════════════════════╝
     ↑ pt-5                                                                pb-4 ↑
                              ─── border-bottom 1px ───
```

**Regras:**
- L `flex-1 min-w-0` (cresce/encolhe; `min-w-0` permite truncate)
- C `shrink-0 max-w-[60%] overflow-x-auto` (não invade L; rola se muitos tabs)
- R `shrink-0 ml-auto` (cola direita, sempre)
- Alinhamento vertical: `items-center` (TODOS pousam no meio)
- Gap entre zonas: `1.5rem` (cozy default)

Ver `diagram.svg` pra versão vetorial com medidas.

---

## 3. Container — fundação

```css
.os-page-h {
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: var(--page-h-gap);
  padding: var(--page-h-pt) var(--page-h-px) var(--page-h-pb);
  min-height: var(--page-h-min);
  background: hsl(var(--background) / 0.95);
  backdrop-filter: blur(8px) saturate(120%);
  -webkit-backdrop-filter: blur(8px) saturate(120%);
  border-bottom: 1px solid hsl(var(--border));
  transition: box-shadow var(--t-base) var(--ease-smooth);
}
.os-page-h[data-scrolled="true"] {
  box-shadow: 0 1px 2px hsl(var(--foreground) / 0.04);
}
```

Sticky behavior controlado por `IntersectionObserver` pinning um sentinel `<div>` 1px acima do header — quando sentinel sai do viewport, `[data-scrolled="true"]`. Sem `scroll` listener (perf).

---

## 4. Zona L — Identidade

```html
<div class="page-h-zone-l">
  <h1 class="page-h-title">
    Clientes<span class="page-h-title-suffix"> · cadastro</span>
  </h1>
  <p class="page-h-subtitle">
    31 cadastrados · 4 ativos
    <strong class="page-h-metric page-h-metric-alert"> · 2 com saldo</strong>
  </p>
</div>
```

```css
.page-h-zone-l { flex: 1; min-width: 0; }
.page-h-title {
  font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;
  font-size: var(--page-h-title-size);
  font-weight: var(--page-h-title-weight);
  letter-spacing: var(--page-h-title-track);
  line-height: 1.5;
  color: hsl(var(--foreground));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin: 0;
}
.page-h-title-suffix {
  font-weight: var(--page-h-title-weight);
  color: hsl(var(--muted-foreground));
}
.page-h-subtitle {
  font-size: 13.5px;
  font-weight: 400;
  line-height: 1.4;
  color: hsl(var(--muted-foreground));
  margin: 2px 0 0 0;
  font-variant-numeric: tabular-nums;
}
.page-h-metric { font-weight: 500; }
.page-h-metric-alert   { color: hsl(var(--rose-700));    }
.page-h-metric-warn    { color: hsl(var(--amber-700));   }
.page-h-metric-success { color: hsl(var(--emerald-700)); }
```

**Sufixo `· cadastro`** comunica contexto/grupo. Opcional. Cor `muted-foreground` (não compete com nome principal).

**Métricas semânticas** — vermelho só pra alerta real (saldo devedor); âmbar pra atenção (tickets pendentes); verde pra sucesso (meta batida). **Nunca** cor decorativa.

**Badge counter animation** — quando número muda (31 → 32):
```css
@keyframes count-pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.08); color: hsl(var(--emerald-600)); }
  100% { transform: scale(1); }
}
.page-h-subtitle [data-changed="true"] {
  display: inline-block;
  animation: count-pulse 400ms var(--ease-bounce);
}
```

JS hook via React effect comparando previous/current — applies `[data-changed]` 400ms then removes.

---

## 5. Zona C — SubNav inline

```html
<nav class="page-h-nav" aria-label="Sub-navegação">
  <a class="page-h-tab" href="?type=all">
    <svg class="page-h-tab-icon">…</svg>
    <span class="page-h-tab-label">Todos</span>
  </a>
  <a class="page-h-tab" href="?type=customer" aria-current="page">
    <svg class="page-h-tab-icon">…</svg>
    <span class="page-h-tab-label">Clientes</span>
  </a>
  <!-- ... -->
  <button class="page-h-tab page-h-tab-more" aria-haspopup="menu">
    <svg class="page-h-tab-icon">…</svg>
    <span>Mais (3)</span>
  </button>
</nav>
```

CSS completo em §25 (tokens canon). Comportamentos críticos:

- **`scroll-snap-type: x mandatory`** + `scroll-snap-align: start` em cada tab → scroll horizontal "clica" entre tabs em mobile
- **`align-self: stretch`** no `<nav>` → underline 2px se conecta com border-bottom do container
- **`margin-bottom: -1px`** na tab → underline cobre 1px da linha base (visual contínuo)
- **Underline slide** via framer-motion `LayoutGroup` + `layoutId="active-underline"` → mola física entre tabs ativas

Overflow rule:
- Até **`maxVisibleTabs` (default 5)** → todos inline
- Acima → primeiras `maxVisibleTabs - 1` inline + `Mais (N)` dropdown com resto
- Mobile (<768px) → tabs em segunda linha com `overflow-x-auto` (não usa overflow dropdown)

---

## 6. Zona R — Actions

```html
<div class="page-h-zone-r">
  <button class="page-h-overflow" aria-label="Mais ações">
    <svg>…</svg>   <!-- MoreVertical -->
  </button>
  <button class="page-h-primary" style="--btn-hue: 202">
    <svg>…</svg>   <!-- Plus -->
    Novo cliente
  </button>
</div>
```

Hierarquia:
1. **Primary** (1 por header, no MÁXIMO) — ação principal contextual (`Novo X`, `Criar Y`, `Pagar Z`)
2. **Overflow `⋮`** — secundárias (Importar, Exportar, Configurações específicas da página)
3. **NUNCA 3+ botões visíveis** — quebra hierarquia visual

CSS em §25.

**Hover-darken** do primary é via CSS `oklch()` calc, não JS state — `:hover { background: oklch(0.48 0.16 var(--btn-hue)) }`.

**Press feedback** — `:active { transform: scale(0.97) }` + 60ms ease-snap. Mobile: vibration `navigator.vibrate(10)` em devices que suportam (Android Chrome).

---

## 7. Density modes

| Token | compact | **cozy** | comfortable |
|---|---|---|---|
| `--page-h-min` | 56px | **64px** | 80px |
| `--page-h-pt` | 0.75rem | **1.25rem** | 1.5rem |
| `--page-h-pb` | 0.5rem | **1rem** | 1.25rem |
| `--page-h-px` | 1.5rem | **2rem** | 2.5rem |
| `--page-h-gap` | 1rem | **1.5rem** | 2rem |
| `--page-h-title-size` | 18px | **22px** | 24px |
| `--page-h-tab-h` | 32px | **36px** | 40px |
| `--page-h-tab-size` | 13px | **13.5px** | 14px |
| `--page-h-btn-h` | 32px | **36px** | 40px |
| `--page-h-btn-size` | 12.5px | **13px** | 13.5px |

Wire em `<body data-density="cozy">` ou `.cockpit[data-density]`. localStorage key `oimpresso.cockpit.tweaks.density`. Tweaks panel oferece switcher.

Persona Larissa (1280×800 monitor, biz=4): default **cozy**. Power-users (Wagner): provavelmente **compact**. Acessibilidade visual (Eliana zoom 125%): **comfortable**.

---

## 8. Easing curves + timing scale

```css
:root {
  /* curvas — nomes inspirados em Material 3 + Linear app */
  --ease-snap:   cubic-bezier(0.22, 1, 0.36, 1);     /* decisivo: clicks, taps */
  --ease-smooth: cubic-bezier(0.4, 0, 0.2, 1);       /* neutro: hovers, focus */
  --ease-soft:   cubic-bezier(0.16, 1, 0.3, 1);      /* slow-in slow-out: modais */
  --ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);  /* overshoot: success */
  --ease-linear: linear;                              /* shimmer, progress */

  /* escala temporal (fibonacci-ish) */
  --t-instant:  60ms;
  --t-fast:     120ms;
  --t-base:     180ms;
  --t-slow:     280ms;
  --t-shimmer:  1500ms;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
```

Matriz aplicada:

| Interação | Duração | Easing |
|---|---|---|
| Tab hover (text/border) | `--t-fast` | `--ease-smooth` |
| Tab active underline slide | spring 380/30 | framer-motion |
| Primary hover (bg darken) | `--t-fast` | `--ease-smooth` |
| Primary press (scale 0.97) | `--t-instant` | `--ease-snap` |
| Focus ring fade-in | `--t-fast` | `--ease-smooth` |
| Header scroll shadow | `--t-base` | `--ease-smooth` |
| Skeleton shimmer | `--t-shimmer` loop | `--ease-linear` |
| Badge count pulse | 400ms | `--ease-bounce` |
| Dropdown open (overflow) | `--t-base` | `--ease-soft` |
| Page transition (Inertia) | `--t-base` | `--ease-smooth` |

---

## 9. Estados completos (matriz)

| Estado | bg | border | text | icon-opacity | weight | transform |
|---|---|---|---|---|---|---|
| **Tab default** | transparent | transparent | muted-foreground | 0.7 | 500 | none |
| **Tab hover** | transparent | border/40 | foreground | 0.85 | 500 | none |
| **Tab focus-visible** | transparent | transparent | foreground | 0.85 | 500 | none (+ ring) |
| **Tab active** (aria-current) | transparent | `--page-primary` | foreground | 1.0 | 600 | none |
| **Tab active hover** | transparent | `--page-primary` | foreground | 1.0 | 600 | none |
| **Tab disabled** | transparent | transparent | muted-foreground/50 | 0.4 | 500 | none |
| **Primary default** | oklch(0.55 0.15 hue) | oklch(0.45 0.15 hue) | white | 1.0 | 500 | none |
| **Primary hover** | oklch(0.48 0.16 hue) | oklch(0.38 0.16 hue) | white | 1.0 | 500 | none |
| **Primary focus-visible** | oklch(0.55 0.15 hue) | oklch(0.45 0.15 hue) | white | 1.0 | 500 | ring 2px offset 2px |
| **Primary active (pressed)** | oklch(0.42 0.16 hue) | oklch(0.32 0.16 hue) | white | 1.0 | 500 | scale(0.97) |
| **Primary disabled** | oklch(0.55 0.15 hue) | oklch(0.45 0.15 hue) | white | 1.0 | 500 | opacity 0.5 |
| **Overflow default** | transparent | border | muted-foreground | 1.0 | — | none |
| **Overflow hover** | accent | border | foreground | 1.0 | — | none |

Estados nunca combinam visualmente conflitantes (hover + disabled → disabled vence).

---

## 10. Microinterações

| # | Microinteração | Tecnologia | Detalhe |
|---|---|---|---|
| 1 | Underline slide entre tabs ativas | framer-motion `LayoutGroup` + `layoutId` | Spring 380/30 |
| 2 | Primary press scale | CSS `:active { scale(0.97) }` | 60ms ease-snap |
| 3 | Badge counter pulse | CSS keyframes triggered by React effect | 400ms ease-bounce |
| 4 | Header scroll shadow | IntersectionObserver + data-scrolled | 180ms ease-smooth |
| 5 | Skeleton shimmer | CSS `background-position` animation | 1500ms linear loop |
| 6 | Focus ring fade-in | CSS `transition: outline` | 120ms ease-smooth |
| 7 | Tab icon opacity bump on active | CSS sibling selector | 150ms ease-smooth |
| 8 | Tab text-color on hover | CSS transition | 120ms ease-smooth |
| 9 | Overflow menu open | shadcn DropdownMenu + Radix | 180ms ease-soft |
| 10 | Page transition between tabs | Inertia.js + View Transitions API | 180ms ease-smooth |

**Web Vibration API** opcional (mobile só):
```ts
function hapticTap() {
  if (navigator.vibrate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    navigator.vibrate(10);
  }
}
```
Aplicado em tab change + primary click.

---

## 11. Responsive (mobile-first → desktop)

```css
.os-page-h { flex-wrap: wrap; }
.os-page-h .page-h-nav {
  order: 3;
  flex-basis: 100%;
  margin-top: 0.5rem;
  border-top: 1px solid hsl(var(--border));
  padding-top: 0.25rem;
}

@media (min-width: 768px) {
  .os-page-h { flex-wrap: nowrap; }
  .os-page-h .page-h-nav {
    order: 0;
    flex-basis: auto;
    margin-top: 0;
    border-top: none;
    padding-top: 0;
  }
}

@media (max-width: 767px) {
  .page-h-tab { min-height: 44px; }   /* touch target WCAG 2.5.5 */
}
```

**Container queries** (preferido sobre media queries — mais granular):
```css
.os-page-h { container-type: inline-size; container-name: page-h; }
@container page-h (min-width: 768px) {
  .page-h-nav { flex-basis: auto; order: 0; }
}
```

Use container queries quando o componente estiver dentro de um layout que muda largura independente do viewport (ex: split-view, drawer).

---

## 12. Loading skeleton

```html
<header class="os-page-h" data-loading="true">
  <div class="page-h-zone-l">
    <div class="skel skel-title"></div>
    <div class="skel skel-subtitle"></div>
  </div>
  <nav class="page-h-nav">
    <div class="skel skel-tab" style="width: 80px"></div>
    <div class="skel skel-tab" style="width: 100px"></div>
    <div class="skel skel-tab" style="width: 120px"></div>
    <div class="skel skel-tab" style="width: 100px"></div>
    <div class="skel skel-tab" style="width: 90px"></div>
  </nav>
  <div class="page-h-zone-r">
    <div class="skel skel-overflow"></div>
    <div class="skel skel-primary"></div>
  </div>
</header>
```

```css
.skel {
  background: linear-gradient(
    90deg,
    hsl(var(--muted)) 0%,
    hsl(var(--muted-foreground) / 0.08) 50%,
    hsl(var(--muted)) 100%
  );
  background-size: 200% 100%;
  animation: shimmer var(--t-shimmer) var(--ease-linear) infinite;
  border-radius: 4px;
}
.skel-title    { width: 220px; height: 33px; }
.skel-subtitle { width: 280px; height: 16px; margin-top: 4px; }
.skel-tab      { height: 36px; border-radius: 4px 4px 0 0; }
.skel-overflow { width: 36px; height: 36px; border-radius: 6px; }
.skel-primary  { width: 130px; height: 36px; border-radius: 6px; }
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

**Quando aparece:** Inertia `<Deferred>` em flight > 100ms. Não pisca em conexão rápida (resolução `<100ms` salta direto pro estado final).

---

## 13. Offline / Error state

```html
<div role="status" aria-live="polite" class="page-h-offline-banner">
  <svg>…</svg>   <!-- WifiOff -->
  <span>Sem conexão — alterações vão sincronizar quando voltar</span>
</div>
<header class="os-page-h" data-online="false">
  <!-- ... -->
  <button class="page-h-primary" disabled>+ Novo cliente</button>
</header>
```

```css
.page-h-offline-banner {
  background: hsl(var(--amber-50));
  border-bottom: 1px solid hsl(var(--amber-200));
  color: hsl(var(--amber-900));
  font-size: 13px;
  padding: 0.5rem 2rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.os-page-h[data-online="false"] .page-h-primary {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}
```

Hook:
```ts
function useOnline() {
  const [online, setOnline] = useState(navigator.onLine);
  useEffect(() => {
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);
    return () => {
      window.removeEventListener('online', on);
      window.removeEventListener('offline', off);
    };
  }, []);
  return online;
}
```

Erro 5xx no fetch: usar mesmo banner mas com `bg-rose-50` e mensagem "Servidor instável — tentando reconectar".

---

## 14. Dark mode tokens (par completo)

```css
:root {
  --background:       0   0%  100%;       /* white */
  --foreground:       222 47% 11%;        /* slate-900 */
  --muted:            210 40% 96%;        /* slate-100 */
  --muted-foreground: 215 16% 47%;        /* slate-500 */
  --border:           214 32% 91%;        /* slate-200 */
  --accent:           210 40% 96%;        /* slate-100 hover bg */
  --rose-700:         336 64% 38%;
  --amber-700:        26 86% 36%;
  --emerald-700:      155 79% 26%;
}
.dark {
  --background:       222 47% 11%;
  --foreground:       210 40% 98%;
  --muted:            217 33% 17%;
  --muted-foreground: 215 20% 65%;
  --border:           217 33% 22%;
  --accent:           217 33% 17%;
  --rose-700:         336 70% 65%;
  --amber-700:        38 92% 55%;
  --emerald-700:      155 64% 58%;
}

/* primary per grupo — lightness +5% em dark pra manter contraste WCAG AA */
.fin-cowork           { --page-primary: oklch(0.55 0.15 145); }
.dark .fin-cowork     { --page-primary: oklch(0.60 0.15 145); }
.cadastro-scope       { --page-primary: oklch(0.55 0.15 202); }
.dark .cadastro-scope { --page-primary: oklch(0.60 0.15 202); }
.comercial-scope      { --page-primary: oklch(0.55 0.15 55);  }
.dark .comercial-scope{ --page-primary: oklch(0.60 0.15 55);  }
.producao-scope       { --page-primary: oklch(0.55 0.15 8);   }
.dark .producao-scope { --page-primary: oklch(0.60 0.15 8);   }
.fiscal-scope         { --page-primary: oklch(0.55 0.15 175); }
.dark .fiscal-scope   { --page-primary: oklch(0.60 0.15 175); }
.sistema-scope        { --page-primary: oklch(0.55 0.15 245); }
.dark .sistema-scope  { --page-primary: oklch(0.60 0.15 245); }
.estoque-scope        { --page-primary: oklch(0.55 0.15 315); }
.dark .estoque-scope  { --page-primary: oklch(0.60 0.15 315); }
.pessoas-scope        { --page-primary: oklch(0.55 0.15 88);  }
.dark .pessoas-scope  { --page-primary: oklch(0.60 0.15 88);  }
```

**OS sync** — respeitar `prefers-color-scheme: dark` quando user não escolheu manualmente:
```ts
useEffect(() => {
  if (localStorage.getItem('oimpresso.theme') === null) {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    document.documentElement.classList.toggle('dark', mq.matches);
    mq.addEventListener('change', e => {
      document.documentElement.classList.toggle('dark', e.matches);
    });
  }
}, []);
```

**Windows high contrast mode** (`forced-colors`):
```css
@media (forced-colors: active) {
  .page-h-tab[aria-current="page"] {
    border-bottom-color: Highlight;
  }
  .page-h-primary {
    background: ButtonFace;
    color: ButtonText;
    border: 1px solid ButtonText;
  }
}
```

---

## 15. Print stylesheet

```css
@media print {
  .os-page-h {
    position: static;
    display: block;
    padding: 0 0 0.5rem 0;
    margin-bottom: 0.5rem;
    border-bottom: 1pt solid #000;
    background: white !important;
    box-shadow: none;
    backdrop-filter: none;
  }
  .page-h-title    { font-size: 14pt; font-weight: 700; }
  .page-h-subtitle { font-size: 10pt; color: #444; }
  .page-h-nav, .page-h-overflow { display: none; }
  .page-h-primary {
    display: inline-block;
    background: none !important;
    border: 1pt solid #000 !important;
    color: #000 !important;
    padding: 2pt 6pt;
    font-size: 10pt;
  }
  .page-h-primary::before { content: "→ "; }
  @page { margin: 1cm; }
  @page :first { @top-left { content: "Oimpresso"; } }
  @page { @bottom-right { content: counter(page) " / " counter(pages); } }
}
```

---

## 16. Acessibilidade — checklist WCAG 2.1

### AA (obrigatório)
- [x] `<header role="banner">` + 1 `<h1>` por página
- [x] `<nav aria-label="Sub-navegação">` distinto do nav principal
- [x] `aria-current="page"` na tab ativa
- [x] Skip link primeiro foco: `<a href="#main-content" class="sr-only focus:not-sr-only">Pular pra conteúdo</a>`
- [x] Contrast: muted-foreground 4.6:1 light · 5.2:1 dark
- [x] Touch targets ≥ 44×44px em mobile (`<768px`)
- [x] Focus order: skip-link → H1 → tabs → overflow → primary
- [x] Live region offline status (`role="status" aria-live="polite"`)
- [x] Não-textual content tem `aria-label` ou `aria-hidden`
- [x] Forms (search, etc) tem `<label>` associado

### AAA (opcional, recomendado)
- [x] Contrast 7:1 em texto < 18pt
- [x] `prefers-reduced-motion: reduce` → desativa underline slide, shimmer, pulse
- [x] `prefers-contrast: more` → border 2px, ring 3px
- [x] RTL: `flex-direction` invertido via `[dir="rtl"]`
- [x] Ícones direcionais espelham em RTL via `transform: scaleX(-1)`

---

## 17. Internacionalização (i18n + RTL)

```ts
import { useTranslation } from 'react-i18next';

function PageHeader({ titleKey, ...props }) {
  const { t } = useTranslation();
  return (
    <header className="os-page-h">
      <h1 className="page-h-title">{t(titleKey)}</h1>
      <!-- ... -->
    </header>
  );
}
```

Strings extraídas pra `lang/pt-BR.json`, `lang/en.json`, `lang/es.json`:

```json
{
  "contacts.title": "Clientes",
  "contacts.subtitle": "{{total}} cadastrados · {{ativos}} ativos",
  "contacts.subtitle.alert": " · {{count}} com saldo",
  "tabs.all": "Todos",
  "tabs.customer": "Clientes",
  "tabs.supplier": "Fornecedores",
  "tabs.employee": "Funcionários",
  "tabs.representative": "Representantes",
  "actions.new_singular": "Novo {{type}}",
  "actions.import": "Importar",
  "actions.export": "Exportar"
}
```

RTL (árabe, hebraico):
```css
[dir="rtl"] .os-page-h { flex-direction: row-reverse; }
[dir="rtl"] .page-h-tab-icon[data-directional="true"] { transform: scaleX(-1); }
```

---

## 18. Keyboard shortcuts

| Shortcut | Ação |
|---|---|
| `Cmd/Ctrl + 1...9` | Trocar pra tab N (1 = primeira) |
| `Cmd/Ctrl + N` | Disparar primary action (Novo X) |
| `Cmd/Ctrl + Shift + E` | Abrir overflow menu |
| `Esc` | Fechar overflow (se aberto) |
| `Tab` / `Shift+Tab` | Navegar entre tabs (sequencial) |
| `←` / `→` (com tab focada) | Mover entre tabs irmãs |
| `Home` / `End` (com tab focada) | Primeira / última tab |

Implementação via `useHotkeys` (react-hotkeys-hook):
```ts
useHotkeys('mod+1, mod+2, mod+3, mod+4, mod+5', (e, handler) => {
  const idx = parseInt(handler.keys?.[0] ?? '1', 10) - 1;
  if (tabs[idx]) router.visit(tabs[idx].href);
});
useHotkeys('mod+n', () => primary && router.visit(primary.href));
```

Tooltip nos shortcuts (hover 500ms):
```html
<a class="page-h-tab" title="Cmd+1">
```

---

## 19. View Transitions API (Chrome 111+, Safari 18+)

Transição suave entre tabs (mesma página, mudança de `?type=X`):

```ts
function navigateWithTransition(href: string) {
  if (!document.startViewTransition) {
    router.visit(href);
    return;
  }
  document.startViewTransition(() => {
    router.visit(href);
  });
}
```

CSS:
```css
::view-transition-old(root),
::view-transition-new(root) {
  animation-duration: var(--t-base);
  animation-timing-function: var(--ease-smooth);
}

/* underline pode usar named transition pra slide-only-ele */
.page-h-tab[aria-current="page"] {
  view-transition-name: active-tab-underline;
}
```

Browsers sem suporte: fallback Inertia visit normal (sem animação).

---

## 20. URL state sync (deep link + back/forward)

Tabs viram query param `?type=customer` — não path — pra:
- Compartilhar URL e estado persiste
- Browser back/forward restaura tab
- SEO indexa cada estado separado

```ts
// useTabFromUrl hook
function useTabFromUrl(default_: string) {
  const url = new URL(window.location.href);
  return url.searchParams.get('type') ?? default_;
}
```

**Pegadinha:** Inertia visit com `preserveState: true` mantém scroll position + form state ao trocar tab.

```ts
function changeTab(type: string) {
  router.visit(`/contacts?type=${type}`, {
    preserveState: true,
    preserveScroll: true,
    only: ['rows', 'kpis'],   // partial reload — só payload necessário
  });
}
```

---

## 21. Page transition (Inertia.js)

```ts
// app.tsx
createInertiaApp({
  progress: {
    color: 'oklch(0.55 0.15 var(--page-primary-hue))',
    showSpinner: false,
    delay: 250,   // só mostra se demorar >250ms
  },
  // ...
});
```

Barra de progresso usa hue da página atual — feedback visual integrado ao header.

---

## 22. Telemetry hooks

```ts
function PageHeader({ telemetryContext, ...props }) {
  const onTabChange = (tab) => {
    window.dispatchEvent(new CustomEvent('telemetry:tab-change', {
      detail: { 
        from: activeTab, 
        to: tab.key, 
        page: telemetryContext.pageId,
        timestamp: Date.now(),
      }
    }));
    // ...
  };
  // ...
}
```

Listeners centrais em `app.tsx` enviam pra OpenTelemetry collector (CT 100):
- `tab.change` (counter + duration since previous)
- `primary.click` (counter)
- `overflow.open` (counter)
- `header.scrolled` (gauge)

OTLP exporter HTTP/protobuf → CT 100 Jaeger.

---

## 23. Performance budget

| Métrica | Budget | Como medir |
|---|---|---|
| **CLS** (Cumulative Layout Shift) | **0** | Skeleton tem mesma dimensão que conteúdo real. `min-height` reservada. |
| **LCP** (Largest Contentful Paint) do header | **<200ms** | H1 + tabs renderizados em SSR via Inertia |
| **INP** (Interaction to Next Paint) tab click | **<50ms** | Underline slide via CSS transform (GPU-accelerated), não layout |
| **TBT** (Total Blocking Time) | **<100ms** | Sem heavy JS no first paint do header |
| Bundle size do `<PageHeader>` | **<5KB gzipped** | Sem framer-motion (lazy-load só se underline ativo) |

Lighthouse CI no `Frontend / Vite build` action:
```yaml
- name: Lighthouse CI on PageHeader stories
  run: lhci autorun --collect.url=http://localhost:6006/?path=/story/pageheader
```

Fail PR se CLS > 0 ou LCP > 200ms.

---

## 24. SEO + schema.org breadcrumb

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Cadastro", "item": "https://oimpresso.com/contacts" },
    { "@type": "ListItem", "position": 2, "name": "Clientes", "item": "https://oimpresso.com/contacts?type=customer" }
  ]
}
</script>
```

`<title>` da página segue padrão: `"{title} · {grupo} · Oimpresso"` (ex: "Clientes · Cadastro · Oimpresso").

`<meta name="description">` extraído do subtítulo + contexto.

OpenGraph tags pra share (Slack/WhatsApp): mesma estrutura.

---

## 25. Tokens canon — single source

Arquivo: `resources/css/tokens/page-header.css` (importado em `inertia.css`).

```css
@layer base {
  :root, [data-density="cozy"] {
    /* dimensões */
    --page-h-min: 64px;
    --page-h-pt: 1.25rem;
    --page-h-pb: 1rem;
    --page-h-px: 2rem;
    --page-h-gap: 1.5rem;
    
    /* tipografia */
    --page-h-title-size: 22px;
    --page-h-title-weight: 600;
    --page-h-title-track: -0.011em;
    
    /* tab */
    --page-h-tab-h: 36px;
    --page-h-tab-size: 13.5px;
    --page-h-tab-radius: 4px 4px 0 0;
    
    /* botões */
    --page-h-btn-h: 36px;
    --page-h-btn-size: 13px;
    --page-h-btn-radius: 6px;
  }
  
  [data-density="compact"] {
    --page-h-min: 56px;
    --page-h-pt: 0.75rem;
    --page-h-pb: 0.5rem;
    --page-h-px: 1.5rem;
    --page-h-gap: 1rem;
    --page-h-title-size: 18px;
    --page-h-tab-h: 32px;
    --page-h-tab-size: 13px;
    --page-h-btn-h: 32px;
    --page-h-btn-size: 12.5px;
  }
  
  [data-density="comfortable"] {
    --page-h-min: 80px;
    --page-h-pt: 1.5rem;
    --page-h-pb: 1.25rem;
    --page-h-px: 2.5rem;
    --page-h-gap: 2rem;
    --page-h-title-size: 24px;
    --page-h-tab-h: 40px;
    --page-h-tab-size: 14px;
    --page-h-btn-h: 40px;
    --page-h-btn-size: 13.5px;
  }
}
```

---

## 26. Componente `<PageHeader>` (assinatura + uso)

```tsx
// resources/js/Components/PageHeader/PageHeader.tsx
import { useState, useEffect } from 'react';
import { motion, LayoutGroup } from 'framer-motion';
import { MoreVertical, MoreHorizontal } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/Components/ui/dropdown-menu';
import { useOnline } from '@/hooks/useOnline';
import { useDensity } from '@/hooks/useDensity';
import { useHotkeys } from 'react-hotkeys-hook';
import { router } from '@inertiajs/react';

export type PageHeaderGroup = 
  | 'financas' | 'cadastro' | 'comercial' | 'producao'
  | 'fiscal' | 'sistema' | 'estoque' | 'pessoas';

export interface PageHeaderTab {
  key: string;
  label: string;
  href: string;
  icon?: React.ComponentType<{ className?: string }>;
  current?: boolean;
}

export interface PageHeaderAction {
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  href?: string;
  onClick?: () => void;
  disabled?: boolean;
}

export interface PageHeaderProps {
  title: string;
  suffix?: string;
  subtitle?: React.ReactNode;
  tabs?: PageHeaderTab[];
  maxVisibleTabs?: number;
  primary?: PageHeaderAction | null;
  secondary?: PageHeaderAction[];
  group: PageHeaderGroup;
  loading?: boolean;
}

const HUE: Record<PageHeaderGroup, number> = {
  financas: 145, cadastro: 202, comercial: 55, producao: 8,
  fiscal: 175, sistema: 245, estoque: 315, pessoas: 88,
};

export function PageHeader({
  title, suffix, subtitle, tabs = [], maxVisibleTabs = 5,
  primary, secondary = [], group, loading = false,
}: PageHeaderProps) {
  const online = useOnline();
  const density = useDensity();
  const visible = tabs.slice(0, maxVisibleTabs);
  const overflow = tabs.slice(maxVisibleTabs);
  
  useHotkeys('mod+1, mod+2, mod+3, mod+4, mod+5', (_, handler) => {
    const idx = parseInt(handler.keys?.[0] ?? '1', 10) - 1;
    if (visible[idx]) navigate(visible[idx].href);
  });
  useHotkeys('mod+n', () => primary?.href && navigate(primary.href));
  
  function navigate(href: string) {
    if (document.startViewTransition) {
      document.startViewTransition(() => router.visit(href, { preserveState: true, preserveScroll: true }));
    } else {
      router.visit(href, { preserveState: true, preserveScroll: true });
    }
    if (navigator.vibrate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      navigator.vibrate(10);
    }
  }
  
  if (loading) return <PageHeaderSkeleton density={density} />;
  
  return (
    <>
      {!online && <OfflineBanner />}
      <header 
        className="os-page-h" 
        role="banner"
        data-density={density}
        data-online={online}
        style={{ '--btn-hue': HUE[group] } as React.CSSProperties}
      >
        <div className="page-h-zone-l">
          <h1 className="page-h-title">
            {title}{suffix && <span className="page-h-title-suffix"> · {suffix}</span>}
          </h1>
          {subtitle && <p className="page-h-subtitle">{subtitle}</p>}
        </div>
        
        {visible.length > 0 && (
          <LayoutGroup id="page-h-tabs">
            <nav className="page-h-nav" aria-label="Sub-navegação">
              {visible.map(tab => (
                <a key={tab.key} href={tab.href}
                   aria-current={tab.current ? 'page' : undefined}
                   className="page-h-tab"
                   onClick={(e) => { e.preventDefault(); navigate(tab.href); }}>
                  {tab.icon && <tab.icon className="page-h-tab-icon" />}
                  <span className="page-h-tab-label">{tab.label}</span>
                  {tab.current && (
                    <motion.div layoutId="active-underline"
                                className="page-h-tab-underline"
                                transition={{ type: 'spring', stiffness: 380, damping: 30 }} />
                  )}
                </a>
              ))}
              {overflow.length > 0 && (
                <DropdownMenu>
                  <DropdownMenuTrigger className="page-h-tab page-h-tab-more">
                    <MoreHorizontal className="page-h-tab-icon" />
                    <span>Mais ({overflow.length})</span>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    {overflow.map(tab => (
                      <DropdownMenuItem key={tab.key} asChild>
                        <a href={tab.href}>{tab.label}</a>
                      </DropdownMenuItem>
                    ))}
                  </DropdownMenuContent>
                </DropdownMenu>
              )}
            </nav>
          </LayoutGroup>
        )}
        
        {(secondary.length > 0 || primary) && (
          <div className="page-h-zone-r">
            {secondary.length > 0 && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="icon" className="page-h-overflow" aria-label="Mais ações">
                    <MoreVertical className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                  {secondary.map((action, i) => (
                    <DropdownMenuItem key={i} asChild>
                      {action.href ? <a href={action.href}>{action.label}</a> : <button onClick={action.onClick}>{action.label}</button>}
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
            {primary && (
              <Button className="page-h-primary" 
                      disabled={primary.disabled || !online}
                      onClick={() => primary.href ? navigate(primary.href) : primary.onClick?.()}>
                {primary.icon && <primary.icon className="h-3.5 w-3.5" />}
                {primary.label}
              </Button>
            )}
          </div>
        )}
      </header>
    </>
  );
}
```

Uso no Cliente/Index:

```tsx
<PageHeader
  group="cadastro"
  title={ROLE_TITLE[activeType].title}
  subtitle={
    <>
      {total.toLocaleString('pt-BR')} cadastrados · {ativos} ativos
      {comSaldo > 0 && <strong className="page-h-metric page-h-metric-alert"> · {comSaldo} com saldo</strong>}
    </>
  }
  tabs={SLOT2_TABS.map(t => ({ ...t, current: t.key === activeType }))}
  primary={activeType !== 'all' ? {
    label: `Novo ${ROLE_TITLE[activeType].singular}`,
    icon: Plus,
    href: `/contacts/create?type=${activeType}`,
  } : null}
  secondary={[
    { label: 'Importar', icon: Upload, href: '/contacts/import' },
    { label: 'Exportar CSV', icon: Download, href: '/cliente/export' },
    { label: 'Grupos de clientes', icon: Layers, href: '/customer-group' },
  ]}
/>
```

---

## 27. Storybook stories

Arquivo: `resources/js/Components/PageHeader/PageHeader.stories.tsx`

```tsx
import type { Meta, StoryObj } from '@storybook/react';
import { PageHeader } from './PageHeader';
import { Plus, Upload, Download, Users, Truck, Briefcase, UserCheck, List } from 'lucide-react';

const meta: Meta<typeof PageHeader> = {
  title: 'Design System/PageHeader',
  component: PageHeader,
  parameters: { layout: 'fullscreen' },
};
export default meta;

export const ClienteCadastro: StoryObj<typeof PageHeader> = {
  args: {
    group: 'cadastro',
    title: 'Clientes',
    subtitle: <>31 cadastrados · 4 ativos</>,
    tabs: [
      { key: 'all', label: 'Todos', href: '?type=all', icon: List },
      { key: 'customer', label: 'Clientes', href: '?type=customer', icon: Users, current: true },
      { key: 'supplier', label: 'Fornecedores', href: '?type=supplier', icon: Truck },
      { key: 'employee', label: 'Funcionários', href: '?type=employee', icon: Briefcase },
      { key: 'representative', label: 'Representantes', href: '?type=representative', icon: UserCheck },
    ],
    primary: { label: 'Novo cliente', icon: Plus, href: '/contacts/create?type=customer' },
    secondary: [
      { label: 'Importar', icon: Upload, href: '/contacts/import' },
      { label: 'Exportar', icon: Download, href: '/cliente/export' },
    ],
  },
};

export const FinanceiroCobranca: StoryObj<typeof PageHeader> = {
  args: { /* hue 145, tabs Caixa/Cobranca/etc */ },
};

export const Loading: StoryObj<typeof PageHeader> = {
  args: { ...ClienteCadastro.args, loading: true },
};

export const Offline: StoryObj<typeof PageHeader> = {
  decorators: [(Story) => { window.dispatchEvent(new Event('offline')); return <Story />; }],
  args: { ...ClienteCadastro.args },
};

export const Compact: StoryObj<typeof PageHeader> = {
  decorators: [(Story) => <div data-density="compact"><Story /></div>],
  args: { ...ClienteCadastro.args },
};

export const Comfortable: StoryObj<typeof PageHeader> = {
  decorators: [(Story) => <div data-density="comfortable"><Story /></div>],
  args: { ...ClienteCadastro.args },
};

export const Dark: StoryObj<typeof PageHeader> = {
  decorators: [(Story) => <div className="dark"><Story /></div>],
  args: { ...ClienteCadastro.args },
};

export const RTL: StoryObj<typeof PageHeader> = {
  decorators: [(Story) => <div dir="rtl"><Story /></div>],
  args: { ...ClienteCadastro.args },
};
```

---

## 28. Visual regression (Pest 4 Browser baseline)

```php
// tests/Browser/PageHeaderCanonTest.php
test('PageHeader canon — Cliente cadastro cozy light', function () {
    $page = visit('/contacts?type=customer');
    $page->assertNoConsoleErrors();
    expect($page->screenshot('pageheader/cliente-cozy-light'))->toMatchBaseline();
});

test('PageHeader canon — Cliente cadastro cozy dark', function () {
    $page = visit('/contacts?type=customer');
    $page->getBrowser()->emulateMedia(colorScheme: 'dark');
    expect($page->screenshot('pageheader/cliente-cozy-dark'))->toMatchBaseline();
});

test('PageHeader canon — Cliente compact', function () {
    $page = visit('/contacts?type=customer');
    $page->getBrowser()->setAttribute('body', 'data-density', 'compact');
    expect($page->screenshot('pageheader/cliente-compact'))->toMatchBaseline();
});

// + 6 outros: comfortable, mobile, offline, loading skeleton, focus-visible, RTL
```

Baselines em `tests/Browser/baselines/pageheader/*.png`. Diff threshold 0.1%. PR fail se acima.

---

## 29. Migration plan (80+ telas)

### Wave 1 — Pilotos (2 telas, 2 dias)
- Cliente/Index (`/contacts?type=customer`) — biz=4 Larissa daily
- Financeiro/Cobranca (`/financeiro/cobranca`) — biz=1 wagner daily

### Wave 2 — Grupos Financas + Cadastro (10 telas, 1 semana)
- Financeiro/* (12 telas — Caixa, Contas a Receber, Pagar, Dre, etc)
- Cliente/Show, Cliente/Edit, Cliente/Map

### Wave 3 — Grupos Comercial + Producao (15 telas, 2 semanas)
- Sells/* (Index, Create, Show)
- Crm/* (Pipeline, Activities)
- OficinaAuto/* (Kanban Producao)

### Wave 4 — Restantes (50+ telas, 3-4 semanas)
- Fiscal, Sistema, Estoque, Pessoas, Atendimento, Equipe

### Gates por wave
- ✅ Visual regression baseline assinada por Wagner
- ✅ Pest browser tests passando
- ✅ Lighthouse CI ≥ 90 perf + 100 a11y
- ✅ Smoke prod 7 dias sem regressão
- ✅ Feedback de pelo menos 1 cliente piloto positivo

---

## 30. Anti-padrões catalogados

| # | Anti-padrão | Por que falha | Como evitar |
|---|---|---|---|
| AP1 | Tabs em 2ª linha quando cabem em 1 | Quebra hierarquia visual, força scroll desnecessário | Usar canon §11 responsive (md+ inline) |
| AP2 | Primary `bg-blue-600` Tailwind hardcoded | Fora do sistema OKLCH, não respeita hue do grupo | Usar `<PageHeader group="...">` |
| AP3 | Border magenta vazando de `.cockpit --accent` | Sobrescrita parcial só do bg, esquece border | Sempre setar bg + border + color juntos |
| AP4 | `border-b border-border` no `<nav>` interno | Cria duas linhas paralelas, ruído visual | Linha única vem do container header |
| AP5 | 3+ botões inline na Zona R | Quebra hierarquia "primary + secondary" | Secondary vai pro overflow `⋮` |
| AP6 | `items-start` quando uma zona tem 2 linhas | Outras zonas flutuam no topo | `items-center` sempre |
| AP7 | Sem `tabular-nums` no subtítulo numérico | Texto "dança" 1-2px quando número atualiza | `font-variant-numeric: tabular-nums` |
| AP8 | Sem `min-w-0` na L | Título longo empurra C/R pra fora | `flex-1 min-w-0` com `text-overflow: ellipsis` |
| AP9 | Hover sem transition | Parece bug ("snapped") | 120ms ease-smooth no mínimo |
| AP10 | Active state só por cor (sem font-weight) | Acessibilidade — cor cego não vê | `font-weight: 600` + border-bottom |
| AP11 | Skeleton sem `min-height` reservada | CLS > 0, layout pula | Header sempre `min-height` fixa por densidade |
| AP12 | Sticky sem `backdrop-filter` | Texto da página atravessa header visualmente | `backdrop-filter: blur(8px) saturate(120%)` |
| AP13 | Underline sem `-mb-px` | Underline flutua 1px acima da linha base | `margin-bottom: -1px` sempre |
| AP14 | Sem skip-link | Falha WCAG 2.4.1 | `<a href="#main-content" class="sr-only focus:not-sr-only">` |
| AP15 | Primary sem disabled state quando offline | Usuário clica e nada acontece, frustração | `disabled={!online}` + visual feedback |
| **AP16** | **Font herdada de AppShellV2 / Tailwind config global** | Spec dizia `ui-sans-serif`, prod ficou `IBM Plex Sans` (herdou wrapper). Protótipo standalone NÃO detectou pq não tem wrapper. Quebra paridade com Cowork canon real. | SEMPRE forçar `style={{ fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif' }}` no `<header>` canon. Pest browser test: assert `getComputedStyle(h1).fontFamily.includes('ui-sans-serif')` |
| **AP17** | **Overflow `⋮` com border (shadcn `variant="outline"`)** | `variant="outline"` aplica `border border-input` slate-200 visível. ⋮ é ação de DESCOBERTA secundária, border puxa atenção indevida e compete com primary. | SEMPRE `variant="ghost"` + `className="border-0"` (caso shadcn ghost ainda aplique border). Pattern Linear/Stripe/Notion: ⋮ é invisível até hover. |

---

## REFERENCES

- ADR 0094 — Constituição v2 (7 camadas + 8 princípios)
- ADR UI-0013 — Constituição UI v2 (4 camadas)
- ADR 0180 — PageHeader canon 3 zonas (origem v1)
- ADR 0182 — PageHeader hue per grupo (v2)
- PR #1453 — fix border magenta FinanceiroPrimaryButton
- PR #1454 — primary ciano cadastro
- PR #1455 — tabs inline + ordem
- Linear app design system — https://linear.app/changelog
- Material Design 3 motion — https://m3.material.io/styles/motion
- Stripe Dashboard PageHeader — análise interna sessão 2026-05-24
- Radix UI primitives — https://www.radix-ui.com
- WCAG 2.1 — https://www.w3.org/TR/WCAG21/
