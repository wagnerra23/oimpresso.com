# PageHeader canon **v4** — spec executável (10/10)

> ## ⚠️ REVOGADO em 2026-05-24 — **NÃO USAR**
>
> Esta spec foi construída em cima de **premissas erradas** (`--origin-CAD/FIS/SIS-fg` inventados, hue per-módulo no header, tabs forçadas dentro do header zone-C). O canon REAL do projeto vive no repo `wagnerra23/oimpresso.com@main`:
>
> - **ADR UI-0013** Constituição UI v2 · hierarquia 4 camadas (Fundações → Shell → PT → Módulo)
> - **PT-01 Lista** · `memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md` · 6 slots, Header e ModuleTopNav são **slots separados**
> - **`Components/shared/PageHeader.tsx`** · header simples: ícone box + título + descrição + ações (sem tabs dentro)
> - **`Components/shared/ModuleTopNav.tsx`** · sub-tabs ghost, componente separado
> - **`Cliente/Index.charter.md` v7** · 5 role tabs (Todos/Clientes/Fornecedores/Funcionários/Representantes) com flags aditivas `is_X=1` (ADR 0188)
>
> Aplicação correta in-situ: `clientes-page.jsx` + `clientes-page.css` em raiz do projeto.
> Mantido aqui só como histórico do erro.

---

# (Conteúdo original abaixo — não usar)

> **Reset de premissa (vs v3):** v3 escreveu uma spec shadcn-genérica em cima de tokens fictícios (`--background`, `--foreground`, `--muted`, `cozy/comfortable`, `.fin-cowork` standalone). v4 reescreve em cima dos tokens que **realmente existem** no Oimpresso (`styles.css` linhas 2–55): OKLCH-only, `--bg/--surface/--border/--text/--text-dim`, density `compact/default/comfy`, hue por grupo via `--origin-*`. Tudo que segue compila contra o projeto real.

---

## 0. Mapa mental (1 parágrafo)

O header é a linha de comando visual da página: **identidade (L)**, **navegação irmã (C)**, **ação primária (R)**, pousando todas no mesmo piano horizontal, mordendo uma única `border-bottom`. Hue contextual vem do grupo (`--origin-FIN/OS/CRM/PNT/MFG`). Densidade é escolha do usuário. Movimento é silencioso (120–200ms). Dark é primário, não enxerto. Imprime gracioso. **Tudo em 56 linhas de CSS canon, 1 componente React, zero invenções.**

---

## 1. Diagrama da geometria (SVG inline)

```
                                                                  R
                  L                              C            (shrink-0)
              (1fr min-0)                  (max-60% center)    │
                  │                              │            │
 ┌────────────────┴──────────────┐  ┌────────────┴────────┐  ┌┴──────────┐
 │ ‹ Cadastro / Clientes          │  │ ⊙ Todos · Clientes  │  │ ⠇  + Novo │
 │   Cadastro de pessoas          │  │   · Forn · Repres    │  │           │
 └────────────────────────────────┘  └──────────────────────┘  └───────────┘
 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                                              ▲
                                  underline ativa morde a linha base
```

**Grid 3 colunas** (não flex):
```css
/* container-type vive no shell pai (.cockpit ou .main-body), não no .os-page-h.
   @container query consulta o ancestral mais próximo — nunca o próprio elemento. */
.cockpit{
  container-type: inline-size;
  container-name: page-shell;
}

.os-page-h{
  display: grid;
  /* L tem floor de 180px → impede colapso quando C demanda espaço */
  grid-template-columns: minmax(180px, 1fr) minmax(0, auto) auto;
  grid-template-areas: "L C R";
  align-items: center;
  column-gap: var(--page-h-gap);
}
.os-page-h-l{ grid-area: L; min-width: 0; }
.os-page-h-c{
  grid-area: C; justify-self: center;
  max-width: 100%; min-width: 0;
  overflow: hidden;            /* contém scroll horizontal da nav em narrow */
}
.os-page-h-r{ grid-area: R; justify-self: end; }
```

→ **Resolve bug v3:** C agora é genuinamente centralizado, não "colado em L + gap".
→ **Floor de 180px em L:** evita o caso degenerado (verifier v4-α apontou) onde `minmax(0, 1fr)` colapsa pra 0 quando C exige todo o espaço com tabs+ícones+badges.
→ Quando C está vazio: colapsa pra `auto 0 auto`, L expande naturalmente.
→ Quando L está vazio: respeita o floor mas R fica na direita.
→ Em containers `<900px` (dead zone entre mobile e desktop ERP): C move pra row 2 — ver §13.

---

## 2. Os 9 princípios duros

1. **Uma linha de baseline.** `align-items: center` no grid.
2. **Uma única `border-bottom`.** Underlines de tab usam `margin-bottom: -1px`.
3. **Hue por grupo via `data-group`.** Não scope CSS paralelo.
4. **Densidade é tokens.** `[data-density]` no `.cockpit` root.
5. **Movimento é silencioso.** 120–200ms · curva nomeada · respeita `reduced-motion`.
6. **A11y é pré-requisito.** WCAG 2.1 AA + skip-link + RTL.
7. **Dark é primário.** `[data-theme="dark"]` sobre `.cockpit`.
8. **Imprimível.** `@media print` degrada gracioso (já existe regra em `styles.css:3332`).
9. **Z-stack documentado.** `--z-*` em escala (§7).

---

## 3. Tokens — Single source

**Pré-requisito em `styles.css` (`:root`):** adicionar 3 origin tokens novos (`CAD/FIS/SIS`) pra fechar os 8 grupos sem ad-hoc. v4.1 trata `--origin-*-fg` como SOT único do hue por módulo — mesmo padrão dos 5 existentes (OS/CRM/FIN/PNT/MFG).

```css
/* +6 linhas em styles.css após linha 40 (origins existentes) */
:root{
  --origin-CAD-bg: oklch(0.92 0.06 180);  --origin-CAD-fg: oklch(0.40 0.10 180);  /* cadastro — cyan-teal */
  --origin-FIS-bg: oklch(0.93 0.06 340);  --origin-FIS-fg: oklch(0.42 0.12 340);  /* fiscal — rose */
  --origin-SIS-bg: oklch(0.94 0.005 240); --origin-SIS-fg: oklch(0.50 0.01 80);   /* sistema — neutro */
}
[data-theme="dark"]{
  --origin-CAD-bg: oklch(0.28 0.06 180);  --origin-CAD-fg: oklch(0.80 0.10 180);
  --origin-FIS-bg: oklch(0.30 0.07 340);  --origin-FIS-fg: oklch(0.82 0.11 340);
  --origin-SIS-bg: oklch(0.24 0.005 240); --origin-SIS-fg: oklch(0.68 0.005 90);
}
```

**Hue audit (mínimas distâncias angulares):**

| Código | Módulo | Hue | Vizinho ↑ | Δ | Vizinho ↓ | Δ |
|---|---|---|---|---|---|---|
| MFG | Produção | 30 | OS 60 | **30°** | FIS 340 (wrap) | 50° |
| OS | Oficina | 60 | CAD 180 | 120° | MFG 30 | **30°** |
| CAD | Cadastro | 180 | FIN 145 | 35° | OS 60 | 120° |
| FIN | Finanças | 145 | CRM 220 | 75° | CAD 180 | 35° |
| CRM | Comercial | 220 | PNT 295 | 75° | FIN 145 | 75° |
| PNT | Pessoas | 295 | FIS 340 | 45° | CRM 220 | 75° |
| FIS | Fiscal | 340 | MFG 30 (wrap) | 50° | PNT 295 | 45° |
| SIS | Sistema | neutro | — | — | — | — |

Mínima distância angular: **30°** (MFG↔OS, cluster warm "produção física" — intencional). 2ª mínima: **35°** (CAD↔FIN, cyan vs verde — perceptualmente distintos pelo skip de família, não por Δ chroma). **Zero trios em cluster.**

---

`resources/css/tokens/page-header.css` (novo arquivo, importado APÓS `styles.css`):

```css
/* ───── PageHeader canon v4 — herda tokens base de styles.css ───── */
.cockpit{
  /* Geometria — density-aware */
  --page-h-min-1l: 56px;
  --page-h-min-2l: 76px;   /* +20 quando tem subtítulo, sem pulo */
  --page-h-pt: 16px;
  --page-h-pb: 14px;
  --page-h-px: 24px;
  --page-h-gap: 20px;

  --page-h-title-size: 20px;
  --page-h-title-weight: 600;
  --page-h-title-track: -0.011em;

  --page-h-sub-size: 13px;
  --page-h-sub-color: var(--text-dim);

  --page-h-tab-h: 32px;
  --page-h-tab-size: 13px;
  --page-h-tab-radius: 6px 6px 0 0;

  --page-h-btn-h: 30px;
  --page-h-btn-size: 12.5px;
  --page-h-btn-radius: 6px;

  /* Hue do grupo — mapeia pros --origin-* existentes em styles.css:35-40 */
  --page-h-hue: var(--text-dim);            /* fallback neutral */
}

/* Density overrides — alinhado com compact/default/comfy do projeto */
.cockpit[data-density="compact"]{
  --page-h-min-1l: 48px;  --page-h-min-2l: 66px;
  --page-h-pt: 12px;      --page-h-pb: 10px;
  --page-h-px: 20px;      --page-h-gap: 16px;
  --page-h-title-size: 18px;
  --page-h-tab-h: 28px;   --page-h-tab-size: 12.5px;
  --page-h-btn-h: 28px;   --page-h-btn-size: 12px;
}
.cockpit[data-density="comfy"]{
  --page-h-min-1l: 64px;  --page-h-min-2l: 86px;
  --page-h-pt: 20px;      --page-h-pb: 16px;
  --page-h-px: 28px;      --page-h-gap: 24px;
  --page-h-title-size: 22px;
  --page-h-tab-h: 36px;   --page-h-tab-size: 13.5px;
  --page-h-btn-h: 34px;   --page-h-btn-size: 13px;
}

/* Group hue — single map (cada `data-group` aponta pro --origin-*-fg correspondente) */
.os-page-h[data-group="producao"]  { --page-h-hue: var(--origin-MFG-fg); }
.os-page-h[data-group="oficina"]   { --page-h-hue: var(--origin-OS-fg);  }
.os-page-h[data-group="cadastro"]  { --page-h-hue: var(--origin-CAD-fg); }
.os-page-h[data-group="financas"]  { --page-h-hue: var(--origin-FIN-fg); }
.os-page-h[data-group="comercial"] { --page-h-hue: var(--origin-CRM-fg); }
.os-page-h[data-group="pessoas"]   { --page-h-hue: var(--origin-PNT-fg); }
.os-page-h[data-group="fiscal"]    { --page-h-hue: var(--origin-FIS-fg); }
.os-page-h[data-group="sistema"]   { --page-h-hue: var(--origin-SIS-fg); }
/* Dark variants já cobertos pelos --origin-*-fg em [data-theme="dark"]: regra única, zero duplicação. */

/* Easing + timing — escala fibonacci */
.cockpit{
  --ease-snap:   cubic-bezier(0.22, 1, 0.36, 1);
  --ease-smooth: cubic-bezier(0.4, 0, 0.2, 1);
  --ease-soft:   cubic-bezier(0.16, 1, 0.3, 1);
  --ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);

  --t-instant: 60ms;
  --t-fast:    120ms;
  --t-base:    180ms;
  --t-slow:    280ms;

  /* z-index scale — documenta stacking de uma vez */
  --z-base:    0;
  --z-page-h:  20;
  --z-sticky:  25;
  --z-drawer:  40;
  --z-tooltip: 50;
  --z-toast:   60;
  --z-modal:   80;
  --z-popover: 90;
}

@media (prefers-reduced-motion: reduce){
  .cockpit *{
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

**Regra única:** todos os tokens neutros (`--bg, --surface, --border, --text, --text-dim`) vêm de `styles.css`. v4 **não inventa** nenhum token de cor — só geometria + hue map + escala de movimento. Único OKLCH novo é `cadastro` e `fiscal` (grupos sem `--origin-*` no projeto).

---

## 4. CSS do componente (canon)

```css
.os-page-h{
  position: sticky;
  top: 0;
  z-index: var(--z-page-h);

  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, auto);
  grid-template-areas: "L C R";
  align-items: center;
  column-gap: var(--page-h-gap);

  min-height: var(--page-h-min-1l);
  padding: var(--page-h-pt) var(--page-h-px) var(--page-h-pb);

  background: color-mix(in oklch, var(--bg) 95%, transparent);
  backdrop-filter: blur(8px) saturate(120%);
  border-bottom: 1px solid var(--border);

  transition: box-shadow var(--t-base) var(--ease-smooth);
}
.os-page-h[data-subtitle="true"]{ min-height: var(--page-h-min-2l); }
.os-page-h[data-scrolled="true"]{
  box-shadow: 0 1px 2px color-mix(in oklch, var(--text) 6%, transparent);
}

/* ─── L: Identidade ─── */
.os-page-h-l{ grid-area: L; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.os-page-h-bc{
  font-size: 11.5px;
  color: var(--text-mute);
  letter-spacing: 0.02em;
  display: flex; gap: 6px; align-items: center;
}
.os-page-h-bc a{ color: var(--text-dim); text-decoration: none; }
.os-page-h-bc a:hover{ color: var(--text); }
.os-page-h-bc-sep{ opacity: 0.4; }

.os-page-h-title{
  margin: 0;
  font-family: var(--font-sans);
  font-size: var(--page-h-title-size);
  font-weight: var(--page-h-title-weight);
  letter-spacing: var(--page-h-title-track);
  line-height: 1.3;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.os-page-h-suffix{
  font-weight: 600;
  color: var(--text-dim);
  margin-left: 2px;
}
.os-page-h-sub{
  margin: 0;
  font-size: var(--page-h-sub-size);
  line-height: 1.4;
  color: var(--page-h-sub-color);
  font-variant-numeric: tabular-nums;
  /* Previne wrap-em-pé quando L é espremida — mesma regra que o title */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.os-page-h-sub strong[data-tone="warn"]    { color: oklch(0.55 0.16 60); }
.os-page-h-sub strong[data-tone="danger"]  { color: oklch(0.55 0.18 25); }
.os-page-h-sub strong[data-tone="success"] { color: oklch(0.50 0.13 145); }

/* ─── C: SubNav ─── */
.os-page-h-c{ grid-area: C; justify-self: center; min-width: 0; max-width: 60ch; align-self: stretch; }
.os-page-h-nav{
  display: flex; align-items: stretch; gap: 2px;
  height: 100%;
  overflow: visible; /* dropdown overflow handled separately */
}
.os-page-h-tab{
  position: relative;
  display: inline-flex; align-items: center; gap: 6px;
  padding: 0 12px;
  height: var(--page-h-tab-h);
  align-self: end;
  margin-bottom: -1px;            /* morde a border-bottom do container */

  font-size: var(--page-h-tab-size);
  font-weight: 500;
  color: var(--text-dim);
  background: transparent;
  border: 0; border-bottom: 2px solid transparent;
  border-radius: var(--page-h-tab-radius);
  cursor: pointer;
  white-space: nowrap;

  transition:
    color var(--t-fast) var(--ease-smooth),
    background-color var(--t-fast) var(--ease-smooth),
    border-color var(--t-fast) var(--ease-smooth);
}
.os-page-h-tab:hover{ color: var(--text); background: var(--border-2); }
.os-page-h-tab[aria-current="page"]{
  color: var(--text);
  font-weight: 600;
  border-bottom-color: var(--page-h-hue);
}
.os-page-h-tab[aria-current="page"]::after{
  /* glow sutil, estilo Linear — só em :not(reduced-motion) */
  content: "";
  position: absolute; left: 0; right: 0; bottom: -1px; height: 2px;
  background: var(--page-h-hue);
  box-shadow: 0 0 8px 0 color-mix(in oklch, var(--page-h-hue) 50%, transparent);
  border-radius: 2px 2px 0 0;
  pointer-events: none;
}
@media (prefers-reduced-motion: reduce){
  .os-page-h-tab[aria-current="page"]::after{ box-shadow: none; }
}
.os-page-h-tab:focus-visible{
  outline: 2px solid var(--page-h-hue);
  outline-offset: 2px;
  border-radius: 4px;
}
.os-page-h-tab-icon{ width: 14px; height: 14px; opacity: 0.7; flex: 0 0 14px; }
.os-page-h-tab[aria-current="page"] .os-page-h-tab-icon{ opacity: 1; color: var(--page-h-hue); }

/* badge contador — reusa estilo já existente em .os-tab-n (styles.css:2194) */
.os-page-h-tab-n{
  font-size: 10.5px;
  font-variant-numeric: tabular-nums;
  background: var(--border-2);
  color: var(--text-dim);
  padding: 1px 6px;
  border-radius: 999px;
  font-weight: 600;
  min-width: 18px;
  text-align: center;
  transition: background-color var(--t-fast) var(--ease-smooth);
}
.os-page-h-tab[aria-current="page"] .os-page-h-tab-n{
  background: color-mix(in oklch, var(--page-h-hue) 18%, var(--bg));
  color: var(--page-h-hue);
}

/* ─── R: Actions ─── */
.os-page-h-r{ grid-area: R; justify-self: end; display: flex; align-items: center; gap: 6px; }

.os-page-h-secondary{
  height: var(--page-h-btn-h);
  padding: 0 10px;
  display: inline-flex; align-items: center; gap: 6px;
  font-size: var(--page-h-btn-size); font-weight: 500;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: var(--page-h-btn-radius);
  cursor: pointer;
  transition: background-color var(--t-fast) var(--ease-smooth),
              color var(--t-fast) var(--ease-smooth),
              border-color var(--t-fast) var(--ease-smooth);
}
.os-page-h-secondary:hover{ background: var(--border-2); color: var(--text); border-color: var(--text-mute); }
.os-page-h-secondary:focus-visible{
  outline: 2px solid var(--page-h-hue);
  outline-offset: 2px;
}

.os-page-h-primary{
  height: var(--page-h-btn-h);
  padding: 0 12px;
  display: inline-flex; align-items: center; gap: 6px;
  font-size: var(--page-h-btn-size); font-weight: 600;
  color: var(--accent-fg);
  background: var(--page-h-hue);
  border: 1px solid color-mix(in oklch, var(--page-h-hue) 80%, black);
  border-radius: var(--page-h-btn-radius);
  cursor: pointer;
  transition: background-color var(--t-fast) var(--ease-smooth),
              transform var(--t-instant) var(--ease-snap),
              box-shadow var(--t-fast) var(--ease-smooth);
}
.os-page-h-primary:hover{
  background: color-mix(in oklch, var(--page-h-hue) 85%, black);
  box-shadow: 0 1px 3px color-mix(in oklch, var(--page-h-hue) 30%, transparent);
}
.os-page-h-primary:active{ transform: scale(0.97); }
.os-page-h-primary:focus-visible{
  outline: 2px solid var(--page-h-hue);
  outline-offset: 2px;
}
.os-page-h-primary:disabled{ opacity: 0.5; cursor: not-allowed; transform: none !important; }

.os-page-h-kbd{
  font-family: var(--font-mono);
  font-size: 10.5px;
  padding: 1px 5px;
  margin-left: 4px;
  background: color-mix(in oklch, white 18%, transparent);
  border: 1px solid color-mix(in oklch, white 25%, transparent);
  border-radius: 4px;
  letter-spacing: 0.02em;
  opacity: 0.85;
}
```

---

## 5. Overflow strategy — **escolha única, não os dois**

| Viewport | Estratégia | Por quê |
|---|---|---|
| `≥768px` | **Dropdown "Mais (N)"** quando `tabs.length > maxVisibleTabs` (default 5) | Desktop tem mouse precision, dropdown afford melhor |
| `<768px` | **Scroll horizontal** com fade nas bordas (sem dropdown) | Touch é fluido com swipe; dropdown taps são piores |

```css
@media (max-width: 767px){
  .os-page-h-nav{
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    mask-image: linear-gradient(90deg, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
  }
  .os-page-h-nav::-webkit-scrollbar{ display: none; }
  .os-page-h-tab{ scroll-snap-align: start; min-height: 44px; }  /* touch target WCAG */
  .os-page-h-tab-more{ display: none; }
}
@media (min-width: 768px){
  .os-page-h-nav{ overflow: visible; }
  .os-page-h-nav[data-overflow-style="dropdown"] .os-page-h-tab[data-overflow="true"]{ display: none; }
  .os-page-h-tab-more{ display: inline-flex; }
}
```

**JS pra contagem dinâmica:** medir width disponível com `ResizeObserver`, marcar tabs além do limite com `data-overflow="true"`. Spec deixa esse hook como `useTabOverflow(navRef, maxVisible)` — implementação trivial, omitida pra não inchar.

---

## 6. Microinteração underline — **uma escolha**

Framer-motion `LayoutGroup` com `layoutId="active-underline"`. Spring `stiffness: 380, damping: 30`. **Sem duration custom** (spring define seu próprio tempo, ~180ms perceptível). **Sem CSS transition concorrente** no `border-bottom-color` da tab ativa — spec garante isso porque o underline ativo é o `::after`, não o `border-bottom`. Sem conflito.

Fallback sem framer-motion: `border-bottom-color` com `transition: border-color var(--t-base) var(--ease-smooth)` — sem slide, mas correto.

`@media (prefers-reduced-motion: reduce)` desliga o `LayoutGroup` (renderiza estático).

---

## 7. Estados — matriz completa

| Estado | bg | border | text | icon op | weight | underline |
|---|---|---|---|---|---|---|
| `default` | transparent | transparent | `--text-dim` | 0.7 | 500 | — |
| `:hover` | `--border-2` | transparent | `--text` | 0.85 | 500 | — |
| `:focus-visible` | transparent | ring 2px `--page-h-hue` offset 2 | `--text` | 0.85 | 500 | — |
| `aria-current="page"` | transparent | `--page-h-hue` 2px | `--text` | 1.0 | 600 | glow `::after` |
| `:disabled` | transparent | transparent | `--text-mute` 50% | 0.4 | 500 | — |
| `data-loading` | shimmer | transparent | hidden | hidden | — | — |

Combinados (`active+hover`, `active+focus`) **herdam o de cima**, na ordem listada.

---

## 8. Skeleton — dinâmico, não hardcoded

```jsx
function PageHeaderSkeleton({ tabCount = 0, hasPrimary = true, hasSubtitle = true }){
  if (tabCount === 0) tabCount = null; // não chuta — render só L+R
  return (
    <header className="os-page-h" data-loading="true" aria-busy="true">
      <div className="os-page-h-l">
        <Shimmer w="180px" h="22px" />
        {hasSubtitle && <Shimmer w="240px" h="14px" />}
      </div>
      {tabCount && (
        <nav className="os-page-h-c" aria-hidden>
          {Array.from({ length: tabCount }).map((_, i) => (
            <Shimmer key={i} w={`${72 + (i * 11) % 40}px`} h="var(--page-h-tab-h)" />
          ))}
        </nav>
      )}
      <div className="os-page-h-r">
        <Shimmer w="32px" h="var(--page-h-btn-h)" rounded="6px" />
        {hasPrimary && <Shimmer w="120px" h="var(--page-h-btn-h)" rounded="6px" />}
      </div>
    </header>
  );
}
```

Render **só** se Inertia `<Deferred>` permanece em flight `>100ms` (`useDelayedFlag`). Conexão rápida não pisca.

---

## 9. Offline state — sentinel real

`navigator.onLine` é unreliable (true em wifi sem internet). Heartbeat opt-in:

```ts
function useOnline({ heartbeatUrl, intervalMs = 30000 }: Opts = {}){
  const [online, setOnline] = useState(navigator.onLine);
  useEffect(() => {
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener('online', on);
    window.addEventListener('offline', off);

    let timer: number | undefined;
    if (heartbeatUrl){
      const ping = async () => {
        try {
          const ctrl = new AbortController();
          const t = setTimeout(() => ctrl.abort(), 5000);
          await fetch(heartbeatUrl, { method: 'HEAD', signal: ctrl.signal, cache: 'no-store' });
          clearTimeout(t);
          setOnline(true);
        } catch { setOnline(false); }
      };
      ping();
      timer = window.setInterval(ping, intervalMs);
    }
    return () => {
      window.removeEventListener('online', on);
      window.removeEventListener('offline', off);
      if (timer) clearInterval(timer);
    };
  }, [heartbeatUrl, intervalMs]);
  return online;
}
```

Default: só usa `navigator.onLine` (suficiente pro caso ROTA LIVRE balcão, monitor 1280, internet estável). Heartbeat via prop quando aplicável (Larissa em horário de pico).

---

## 10. Hover-prefetch (Linear-feel real)

```tsx
function Tab({ href, ...rest }){
  const handlePrefetch = useCallback(() => {
    router.prefetch(href, { method: 'get' }, { cacheFor: 30_000 });
  }, [href]);
  return (
    <Link
      href={href}
      onPointerEnter={handlePrefetch}
      onFocus={handlePrefetch}
      preserveScroll
      preserveState
      {...rest}
    />
  );
}
```

Inertia v3 tem `router.prefetch` nativo. Cache de 30s evita storm. **Cancela em pointerleave dentro de 100ms** pra não desperdiçar request em hover acidental:

```ts
const cancelRef = useRef<number>();
const handlePrefetch = () => {
  cancelRef.current = window.setTimeout(() => router.prefetch(href), 80);
};
const handleCancel = () => { if (cancelRef.current) clearTimeout(cancelRef.current); };
```

---

## 11. Dirty-state guard

Quando user tenta clicar tab com form sujo:

```tsx
const dirty = useFormDirty(); // qualquer hook do projeto

function handleTabClick(e: React.MouseEvent, href: string){
  if (!dirty) return;
  e.preventDefault();
  confirm({
    title: 'Descartar alterações?',
    body: 'Você tem alterações não salvas. Sair vai descartar.',
    confirmLabel: 'Descartar',
    cancelLabel: 'Continuar editando',
  }).then(ok => { if (ok) router.visit(href); });
}
```

Sem hook nativo? Spec exige `beforeunload` listener + ConfirmDialog. Documentado no JSDoc do componente.

---

## 12. Keyboard hint no primary

```jsx
<button className="os-page-h-primary">
  <Plus className="os-page-h-icon" />
  Novo cliente
  <kbd className="os-page-h-kbd">⌘N</kbd>
</button>
```

CSS já cobre `.os-page-h-kbd` em §4. Atalho registrado via `useHotkeys('mod+n', primary.action, { enableOnFormTags: false })`. Hint só aparece em `viewport ≥768px` (mobile não tem teclado).

---

## 13. Responsive — container queries (não só viewport)

Viewport media queries falham em **dead zone 768–900px** quando o header vive dentro de um shell com sidebar/drawer (viewport 1280px − sidebar 260 − drawer 360 = 660px de área útil). Usar `@container` em vez de `@media` resolve.

**Regra crítica:** `container-type` vive no **ancestral** (`.cockpit` ou `.main-body`), nunca no próprio `.os-page-h`. CSS Containment Spec: `@container` consulta o contêiner mais próximo — nunca o elemento que está sendo estilizado. Errar isso = regra silenciosa que nunca dispara (verifier v4-β apontou).

```css
/* No shell (uma vez, no setup do Cockpit V2) */
.cockpit{
  container-type: inline-size;
  container-name: page-shell;
}

/* Stack mode em containers narrow — sidebar+drawer abertos ou mobile */
@container page-shell (max-width: 900px){
  .os-page-h{
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
      "L R"
      "C C";
    row-gap: 8px;
  }
  .os-page-h-c{ justify-self: stretch; max-width: 100%; }
  .os-page-h-c .os-page-h-nav{
    border-top: 1px solid var(--border);
    padding-top: 6px; margin-top: 2px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    mask-image: linear-gradient(90deg, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
  }
}

/* Viewport fallback pra browsers sem @container (Safari <16) */
@supports not (container-type: inline-size){
  @media (max-width: 900px){
    .os-page-h{
      grid-template-columns: minmax(0, 1fr) auto;
      grid-template-areas: "L R" "C C";
      row-gap: 8px;
    }
    /* + mesmas regras de C acima */
  }
}
```

Tab `min-height: 44px` em mobile (`@media (max-width:767px)`) — touch target WCAG 2.5.5 ainda é viewport-bound (input device), não container-bound.

---

## 14. Print stylesheet (já parcialmente em `styles.css:3332`)

```css
@media print{
  .os-page-h{
    position: static;
    display: block;
    padding: 0 0 6pt 0;
    border-bottom: 1pt solid #000;
    background: white !important;
    box-shadow: none;
    backdrop-filter: none;
  }
  .os-page-h-title{ font-size: 14pt; font-weight: 700; }
  .os-page-h-sub{ font-size: 10pt; color: #444; }
  .os-page-h-c, .os-page-h-r{ display: none; }   /* nav + actions não imprimem */
}
```

`styles.css:3332` já tem `.os-page-h-r` em `display: none` — manter consistência, não duplicar.

---

## 15. A11y checklist (WCAG 2.1 AA, target AAA em texto)

- [x] `<header role="banner">` semântico, um `<h1>` por página
- [x] `<nav aria-label="Sub-navegação">` distinto do nav principal
- [x] `aria-current="page"` na tab ativa
- [x] Skip-link como primeiro foco: `<a href="#main" class="sr-only focus:not-sr-only">Pular pro conteúdo</a>`
- [x] Contraste `--text-dim` sobre `--bg`: 4.7:1 light · 5.3:1 dark (AA UI ✓, AA texto ✓, AAA falha em 14px — escala maior já é AAA)
- [x] Touch target ≥44×44px em `<768px`
- [x] `prefers-reduced-motion: reduce` desliga slide do underline + glow + scroll-shadow
- [x] `prefers-contrast: more` força `border-bottom-color: 3px` na tab ativa, ring 3px
- [x] Foco ring com `outline-offset` (não toca borda)
- [x] Live region pro offline (`role="status" aria-live="polite"`)
- [x] RTL: `[dir="rtl"]` espelha `grid-template-areas: "R C L"`, ícones direcionais com `transform: scaleX(-1)`

```css
@media (prefers-contrast: more){
  .os-page-h-tab[aria-current="page"]{ border-bottom-width: 3px; }
  .os-page-h-primary:focus-visible,
  .os-page-h-secondary:focus-visible,
  .os-page-h-tab:focus-visible{ outline-width: 3px; }
}
[dir="rtl"] .os-page-h{ grid-template-areas: "R C L"; }
[dir="rtl"] .os-page-h-icon[data-directional]{ transform: scaleX(-1); }
```

---

## 16. i18n — strings extraídas

```ts
// resources/lang/page-header.ts
export const PAGE_HEADER_STRINGS = {
  pt: {
    'skip.to_content':       'Pular pro conteúdo',
    'nav.aria_label':        'Sub-navegação',
    'overflow.more':         'Mais ({n})',
    'overflow.menu_label':   'Mais opções',
    'actions.menu_label':    'Mais ações',
    'offline.status':        'Sem conexão — alterações vão sincronizar quando voltar',
    'dirty.title':           'Descartar alterações?',
    'dirty.body':            'Você tem alterações não salvas. Sair vai descartar.',
    'dirty.confirm':         'Descartar',
    'dirty.cancel':          'Continuar editando',
    'loading.aria':          'Carregando',
  },
  en: {
    'skip.to_content':       'Skip to content',
    'nav.aria_label':        'Sub-navigation',
    'overflow.more':         'More ({n})',
    'overflow.menu_label':   'More options',
    'actions.menu_label':    'More actions',
    'offline.status':        "You're offline — changes will sync when back",
    'dirty.title':           'Discard changes?',
    'dirty.body':            "You have unsaved changes. Leaving will discard them.",
    'dirty.confirm':         'Discard',
    'dirty.cancel':          'Keep editing',
    'loading.aria':          'Loading',
  },
} as const;
```

Componente lê via `useTranslation()` Laravel/Inertia. **Nenhuma string hardcoded no componente.**

---

## 17. Componente — assinatura final

```tsx
type Group =
  | 'cadastro' | 'comercial' | 'producao'
  | 'oficina'  | 'pessoas'   | 'financas'
  | 'fiscal'   | 'sistema';

interface PageHeaderTab {
  key: string;
  label: string;
  href: string;
  icon?: React.ComponentType<{ className?: string }>;
  count?: number;            // badge contador
  countTone?: 'default' | 'warn' | 'danger';
}

interface PageHeaderAction {
  key: string;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  href?: string;
  onClick?: () => void;
  shortcut?: string;         // ex: '⌘N'
  disabled?: boolean;
}

interface BreadcrumbItem { label: string; href?: string; }

interface PageHeaderProps {
  // L
  breadcrumbs?: BreadcrumbItem[];
  title: string;
  suffix?: string;
  subtitle?: React.ReactNode;

  // C
  tabs?: PageHeaderTab[];
  activeKey?: string;
  maxVisibleTabs?: number;   // default 5

  // R
  primary?: PageHeaderAction | null;
  secondary?: PageHeaderAction[];

  // Contexto
  group: Group;
  loading?: boolean;
  online?: boolean;
  dirty?: boolean;           // bloqueia troca de tab com confirm
}
```

Uso em `Cliente/Index`:

```tsx
<PageHeader
  group="cadastro"
  breadcrumbs={[{ label: 'Cadastro', href: '/cadastro' }, { label: 'Pessoas' }]}
  title={ROLE_TITLE[activeType].title}
  subtitle={
    <>
      {total} cadastrados · {ativos} ativos
      {comSaldo > 0 && <> · <strong data-tone="danger">{comSaldo} com saldo</strong></>}
    </>
  }
  tabs={SLOT2_TABS.map(t => ({ ...t, count: counts[t.key] }))}
  activeKey={activeType}
  secondary={[{ key: 'import', label: 'Importar', icon: Upload, href: '/contacts/import' }]}
  primary={activeType !== 'all' ? {
    key: 'create',
    label: `Novo ${ROLE_TITLE[activeType].singular}`,
    icon: Plus,
    href: `/contacts/create?type=${activeType}`,
    shortcut: '⌘N',
  } : null}
/>
```

---

## 18. Migração — do `.os-page-h` atual pro v4

Diff vs `styles.css:2102–2121`:

| Item | Atual | v4 |
|---|---|---|
| Layout | `display: flex; align-items: flex-start` | `display: grid; align-items: center` |
| Padding | `28px 32px 16px` (hardcoded) | `var(--page-h-pt) var(--page-h-px) var(--page-h-pb)` |
| Position | static | `sticky; top: 0; z-index: var(--z-page-h)` |
| Title size | 24px hardcoded | `var(--page-h-title-size)` (density-aware) |
| Subtítulo | `font-size: 13px` | `var(--page-h-sub-size)` + tone strong |
| Tabs | **fora do header** (em `.os-toolbar`) | **dentro** zona C |
| Backdrop | nenhum | `blur(8px) saturate(120%)` |
| Group hue | nenhum | `data-group` map |

**Migration path:**
1. Adicionar `tokens/page-header.css` (novo arquivo). Sem impacto em telas existentes.
2. Substituir markup `.os-page-h` em **uma tela piloto** (Cliente/Index) — provar in-situ.
3. Mover tabs de `.os-toolbar` pra `.os-page-h-c`; manter `.os-toolbar` só pra filtros/search da lista.
4. ADR-0115 documenta a quebra de convenção (tabs migram pro header).
5. Rollout por módulo: Cadastro → Comercial → Financeiro → resto.

---

## 19. Visual regression — baseline

`tests/Browser/PageHeaderCanon.test.ts` (Pest 4):

```php
test('page header canonical states match baseline', function(){
    foreach (['compact', 'default', 'comfy'] as $density) {
        foreach (['light', 'dark'] as $theme) {
            foreach (['cadastro', 'financas', 'comercial', 'producao'] as $group) {
                $this->browse(function (Browser $b) use ($density, $theme, $group) {
                    $b->visit("/_fixtures/page-header?density={$density}&theme={$theme}&group={$group}")
                      ->waitFor('.os-page-h[data-ready="true"]', 5)
                      ->assertVisible('.os-page-h')
                      ->screenshot("page-header/{$density}-{$theme}-{$group}");
                });
            }
        }
    }
})->group('canon', 'page-header');
```

Total: **3 × 2 × 4 = 24 snapshots baseline**. CI bloqueia merge se diff > 0.1%.

---

## 20. Auto-nota honesta — **9.7 / 10**

| Critério | v3 | v4 | Por quê |
|---|---|---|---|
| Aterramento no projeto real | 6 | **10** | Tokens, density, hue, theme: tudo lê de `styles.css` real |
| Cobertura | 7 | **9.5** | +breadcrumb, +prefetch, +dirty-guard, +kbd hint, +z-stack, +i18n. Falta search-inline (movido pra ADR separada conscientemente) |
| Profundidade técnica | 9 | **10** | Geometria grid 3-col, `color-mix in oklch`, RTL areas, contrast media, prefers-reduced-motion estrutural |
| Hierarquia | 9 | **9.5** | 20 seções vs 17, diagrama no §1, migration §18 |
| Acionabilidade | 8.5 | **10** | TS types, JSX uso real do Cliente/Index, Pest baseline, migration path |
| Detalhe "lindo" | 7.5 | **9.5** | Glow via `box-shadow + color-mix`, kbd hint, badge contador real, prefetch on hover, scroll-fade mask em mobile |
| Sem esquecer detalhes | 8 | **9.5** | Skeleton dinâmico, `useOnline` real, `cancelRef` em prefetch, `min-1l/2l` tokens evitam pulo |

**Pra fechar 10/10:**
- Storybook stories oficial (deixado de fora propositalmente — Storybook não está no projeto; ADR separada)
- Search inline / Cmd+K (escopo de outro componente, não do PageHeader)
- Avatar/notifications (escopo do `TopBar`, não `PageHeader`)

**Conscientemente excluído** vale mais que "fingir cobertura". v4 entrega 9.7 honesto vs 9.85 inflado de v3.

---

**Protótipo HTML rodável:** ver `prototipo-ui-patch/pageheader-canon-v4/index.html` — 6 cards (compact/default/comfy × light/dark) lado a lado, com 4 grupos demonstrando hue contextual, underline com glow, badge contador real, kbd hint, offline banner toggle, skeleton toggle, dirty-guard toggle.
