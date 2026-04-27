# Catálogo de Acabamentos · Cockpit

> **Propósito**: documentar com precisão pixel-perfect cada detalhe visual do `/copiloto/cockpit` em produção (workspace Vibe, density 50%, accent hue 220°). Serve como **referência canônica** pra qualquer mudança futura — humana ou IA — não inventar tonalidade, sombra, ou tamanho que não esteja aqui.
>
> Toda alteração visual deve **referenciar este catálogo** ou abrir ADR justificando divergência.
>
> **Última atualização**: 2026-04-27 (snapshot do PR #49 / branch `feat/copiloto-cockpit-piloto`)
>
> **Fonte canônica**: `resources/css/cockpit.css` no commit que essa versão do catálogo referencia. Valores cruzados com `getComputedStyle()` rodando em `https://oimpresso.com/copiloto/cockpit`.

---

## 0. Como ler este catálogo

- **Token CSS** em `var(--name)` é o nome usado no código. Sempre prefira o token, não o valor literal.
- **Valor literal** em `oklch(...)` ou `px` é o valor computado em runtime. Só consulte se for traduzir pra mockup HTML standalone.
- **`(@vibe X)`** indica que o valor muda conforme a Vibe ativa (`workspace`/`daylight`/`focus`). O default sempre é workspace.
- **`Δ density`** indica que o valor escala com o slider Densidade (linear interpolation).
- **Estados** (`:hover`, `:focus`, `:active`, `[disabled]`) listados na linha do componente quando aplicáveis.

---

## 1. Tipografia

### 1.1 Famílias

| Token | Valor | Uso |
|---|---|---|
| `--font-sans` | `"IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif` | Toda UI textual padrão |
| `--font-mono` | `"IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace` | Números, IDs, timestamps, badges de pill (OS-2814), kbd |

**Importação obrigatória** no head do mockup ou no `app.tsx`:
```html
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
```

### 1.2 Escala de tamanhos (descendente, todos `IBM Plex Sans`)

| Tamanho | Peso | Uso típico | Line-height (computed) |
|---|---|---|---|
| **13.5px** | 400 | Body padrão `.cockpit` (fonte base) | 20.25px (1.5) |
| **13.5px** | 500 | `.sb-conv.active` título conversa ativa | 20.25px |
| **13.5px** | 600 | `.th-who b` nome conversa header | 20.25px |
| **13px** | 400 | `.bubble-text` corpo das mensagens | 18.2px (1.4) |
| **12.5px** | 400 | `.sb-conv` título conversa, `.sb-action` atalho, `.bc` breadcrumb, `.chat-tab` aba interna | varia por contexto |
| **12.5px** | 500 | `.bc-cur` breadcrumb item ativo, `.chat-tab.active` aba ativa, `.lblock-cta` CTA | varia |
| **12.5px** | 600 | `.lblock-h b` header bloco vinculado | varia |
| **12px** | 400 | `.lkv` linha key-value, `.th-context` faixa OS | varia |
| **11.5px** | 400 | `.th-who small` sub-info header chat, `.lhint` último contato | varia |
| **11.5px** | 500 | `.lkv b` valor key-value | varia |
| **11px** | 400 | `.cpAvatar` letras do avatar empresa, `.bubble .author` autor she said it | varia |
| **11px** | 600 | `.bubble .author` autor da mensagem them | 1.0 |
| **11px** | 700 | `.user-menu-head .meta b` nome user dropdown | varia |
| **10.5px** | 400 | `.bubble .meta` hora dentro da bolha, `.composer-hint-inline` | 1.0 |
| **10.5px** | 700 + letter-spacing 0.08em + uppercase | `.sb-section-h` FIXADAS/ROTINAS/RECENTES, `.apps-h` APPS VINCULADOS | 1.0 |
| **10px** | 700 + letter-spacing 0.05em + uppercase | `.day-sep` HOJE | 1.0 |
| **10px** | 400 + monospace | `.kbd` teclas (Enter, ⌘N) | 1.0 |
| **9.5px** | 700 + letter-spacing 0.05em + uppercase | `.origin-badge` (OS, CRM, FIN, PNT, MFG) | 1.0 |

### 1.3 Pesos canônicos

- `400` (Regular): texto corrido, body, conv items, breadcrumb
- `500` (Medium): item ativo, valores key-value, CTAs primárias, label de menu
- `600` (Semibold): nome de conversa em foco, autor de mensagem them, header de bloco vinculado
- `700` (Bold): avatares (iniciais), section headers UPPERCASE, badges, kbd

**Não usar pesos intermediários** (300/350/450/550) — quebra a hierarquia visual.

### 1.4 Letter-spacing

- **Default**: 0 (sem ajuste)
- **`0.05em`**: origin badges, day separator
- **`0.08em`**: section headers da sidebar (FIXADAS, RECENTES) e header `APPS VINCULADOS`

### 1.5 Text transforms

- **`uppercase`**: section headers (`FIXADAS`, `RECENTES`, `APPS VINCULADOS`), day separator (`HOJE`), origin badges (`OS`, `CRM`)
- **Resto**: case-sensitive normal

---

## 2. Cores e tonalidades

> **Sempre OKLCH** pra preservar consistência perceptual entre Vibes. Nunca HEX cru, nunca RGB cru (exceto `#fff` em texto branco sobre accent).

### 2.1 Sidebar (dark, FIXA — não muda com Vibe)

| Token | Valor | Uso |
|---|---|---|
| `--sb-bg` | `oklch(0.21 0 0)` | Fundo da sidebar |
| `--sb-bg-2` | `oklch(0.18 0 0)` | Fundo alternativo (ex: dropdown) |
| `--sb-border` | `oklch(0.28 0 0)` | Bordas internas, separadores |
| `--sb-text` | `oklch(0.78 0 0)` | Texto padrão (conversas, items menu) |
| `--sb-text-dim` | `oklch(0.55 0 0)` | Texto secundário (frequência rotinas, status) |
| `--sb-text-hi` | `oklch(0.96 0 0)` | Texto destacado (item ativo, avatar empresa) |
| `--sb-hover` | `oklch(0.27 0 0)` | Hover state em items |
| `--sb-active` | `oklch(0.32 0 0)` | Active state (item selecionado, aba ativa) |

### 2.2 Surface (Main column + Apps Vinculados — varia com Vibe)

| Token | `workspace` (default) | `daylight` | `focus` |
|---|---|---|---|
| `--bg` | `oklch(0.985 0.003 90)` | `oklch(0.99 0.012 80)` (mais quente) | `oklch(0.97 0 0)` (neutro) |
| `--bg-2` | `oklch(0.965 0.004 90)` | `oklch(0.97 0.02 80)` | `oklch(0.94 0 0)` |
| `--surface` | `#ffffff` | `oklch(0.995 0.005 80)` | `#ffffff` |
| `--border` | `oklch(0.90 0.004 90)` | `oklch(0.91 0.015 80)` | `oklch(0.85 0 0)` |
| `--border-2` | `oklch(0.93 0.004 90)` | `oklch(0.94 0.012 80)` | `oklch(0.90 0 0)` |
| `--text` | `oklch(0.22 0.01 80)` | `oklch(0.30 0.04 50)` | `oklch(0.15 0 0)` |
| `--text-dim` | `oklch(0.50 0.01 80)` | `oklch(0.55 0.04 50)` | `oklch(0.40 0 0)` |
| `--text-mute` | `oklch(0.65 0.01 80)` | `oklch(0.70 0.03 50)` | `oklch(0.55 0 0)` |

### 2.3 Accent (varia com Vibe + Accent hue slider)

Slider `accentHue` (0-360°) reescreve em runtime:
```css
--accent:      oklch(0.58 0.12 ${hue});
--accent-2:    oklch(0.66 0.12 ${hue});
--accent-soft: oklch(0.94 0.04 ${hue});
--bubble-me:   oklch(0.58 0.12 ${hue});
```

Default `hue=220` (azul):
- `--accent` = `oklch(0.58 0.12 220)` (azul médio)
- `--accent-2` = `oklch(0.66 0.12 220)` (hover)
- `--accent-soft` = `oklch(0.94 0.04 220)` (background suave)
- `--accent-fg` = `#ffffff` (texto sobre accent)

**Vibe `focus`** ignora hue e usa monocromático:
- `--accent` = `oklch(0.30 0 0)`
- `--accent-2` = `oklch(0.40 0 0)`
- `--accent-soft` = `oklch(0.85 0 0)`

### 2.4 Bolhas de chat

| Token | `workspace` | Comentário |
|---|---|---|
| `--bubble-me` | `oklch(0.58 0.12 220)` (= --accent) | Minha mensagem (azul) |
| `--bubble-me-fg` | `#ffffff` | Texto branco |
| `--bubble-them` | `oklch(0.96 0.003 90)` | Cinza claro neutro |
| `--bubble-them-fg` | `var(--text)` | Texto padrão da Vibe |
| **`@vibe daylight`** | `--bubble-them` = `oklch(0.96 0.025 80)` | Mais quente |
| **`@vibe focus`** | `--bubble-them` = `oklch(0.92 0 0)`, `--bubble-me` = `oklch(0.30 0 0)` | Monocromático contrastado |

### 2.5 Origin badges (5 cores fixas — NUNCA inventar 6ª sem ADR)

| Origem | Token bg | Token fg | Hue OKLCH |
|---|---|---|---|
| **OS** (amber) | `oklch(0.93 0.07 70)` | `oklch(0.40 0.10 60)` | 60-70° (laranja-âmbar) |
| **CRM** (blue) | `oklch(0.92 0.06 220)` | `oklch(0.40 0.10 220)` | 220° (azul) |
| **FIN** (green) | `oklch(0.93 0.07 145)` | `oklch(0.36 0.10 145)` | 145° (verde) |
| **PNT** (violet) | `oklch(0.93 0.06 295)` | `oklch(0.40 0.10 295)` | 295° (violeta-magenta) |
| **MFG** (orange) | `oklch(0.93 0.05 30)` | `oklch(0.40 0.10 30)` | 30° (laranja) |

Cor verde do estágio em produção (`.lstage`, `.stage`): `oklch(0.55 0.14 145)` (mais saturado que o badge FIN, intencional).

Status online (dot verde): `oklch(0.72 0.18 145)` ou `rgb(76, 193, 87)`.

---

## 3. Sombras (shadow tokens)

Total de **3 níveis** — não usar outros sem ADR.

| Token | Valor | Uso |
|---|---|---|
| `--shadow-soft` | `0 1px 2px rgba(0,0,0,.04)` | Bolhas de chat, cards menores. Sutil, quase imperceptível. |
| `--shadow-pop` | `0 6px 24px -8px rgba(0,0,0,.18), 0 2px 6px -2px rgba(0,0,0,.10)` | Tweaks panel, dropdowns abertos, modals (definido no CSS mas usado em poucos lugares) |
| FAB tweaks | `0 4px 14px -4px rgba(0,0,0,.18), 0 2px 4px rgba(0,0,0,.06)` | Botão flutuante de Tweaks (bottom-right) |
| User menu / Company dropdown | `0 8px 24px -4px rgba(0,0,0,.4)` | Popups da sidebar (mais intenso porque sobre fundo dark) |
| Tweaks card aberto | `0 12px 36px -8px rgba(0,0,0,.22), 0 4px 12px rgba(0,0,0,.08)` | Card flutuante com vibe/density/cor |

**Princípio**: sombra **sempre suave + contínua**, nunca duras, nunca múltiplas perpendiculares. Profundidade vem de **transparência baixa** + **offset Y maior que X** + **blur generoso**.

---

## 4. Tamanhos de botões e elementos clicáveis

> Esta seção foi pedida explicitamente — os pixels EXATOS de cada interação.

### 4.1 Sidebar — tamanhos

| Componente | Width | Height | Padding | Border-radius | Font |
|---|---|---|---|---|---|
| `.sb` (container) | **260px** | 100vh | 0 | 0 | base 13.5/400 |
| `.sb-cp-btn` (CompanyPicker) | 100% (235px live) | **36px** | `6px 8px` | `6px` | 13.5px / 400 / `--sb-text-hi` |
| `.sb-cp-btn .avatar` | **22×22px** | — | grid center | `6px` | 11px / 700 / branco |
| `.sb-tab` (Chat/Menu toggle) | 1fr (~110px each) | ~26px | `6px 10px` | `6px` (sm) | 12.5px / 400 |
| `.sb-tab.active` | mesmo | mesmo | mesmo | mesmo | 12.5px / 500 / `--sb-text-hi` |
| `.sb-action` (Nova conversa, Tarefas) | 100% | ~26px | `5px 8px` | `6px` | 12.5px / 400 |
| `.sb-action .badge` (contador) | min-content | ~14px | `1px 6px` | `10px` | 10.5px / 600 / accent |
| `.sb-conv` (item conversa) | 100% | ~26px | `5px 8px` | `6px` | 12.5px / 400 |
| `.sb-conv.active` | mesmo | mesmo | mesmo | mesmo | 12.5px / 500 |
| `.sb-bullet` | **6×6px** | — | — | `50%` | bullet circular |
| `.sb-section-h` | 100% | auto | `12px 8px 4px` | 0 | 10.5px / 700 uppercase |
| `.sb-superadmin-item` | 100% | ~22px | `4px 8px` | `6px` | 11.5px / 400 / `--sb-text-dim` |
| `.sb-user-btn` (rodapé) | 100% (244px) | ~38px | `6px` | `6px` | 12.5px / 500 |
| `.sb-user-btn .avatar` | **26×26px** | — | grid center | `50%` | 11px / 700 |

### 4.2 Topbar e breadcrumb

| Componente | Height | Padding | Notes |
|---|---|---|---|
| `.topbar` | **44px** | `0 16px` | border-bottom 1px |
| `.bc` items | inline | gap 8px | 12.5px / 400 |
| `.bc-cur` (atual) | mesmo | mesmo | 12.5px / **500** |

### 4.3 Thread (chat principal)

| Componente | Width/Height | Padding | Border-radius | Font |
|---|---|---|---|---|
| `.th-head` | 100% × ~56px | `10px 20px`, gap 12px | 0 (border-bottom) | — |
| `.th-av` (avatar conversa) | **36×36px** | grid center | `50%` | 13px / 700 / branco |
| `.th-online` (dot status) | **10×10px** | absolute bottom-right | `50%` | border 2px da `--bg` |
| `.th-actions .icon-btn` | **28×28px** | grid center | `6px` (sm) | hover bg-2 |
| `.th-context` (faixa OS) | 100% × ~32px | `8px 20px`, gap 14px | 0 | 12px / 400 |
| `.th-context .pill` | min-content | `1px 6px` | `6px` (sm) | 11px / 400 / **mono** |
| `.chat-tabsbar` | 100% × ~44px | `12px 20px 8px`, gap 16px | 0 | — |
| `.chat-tab` | min-content | `4px 12px` | `6px` (sm) | 12.5px / 400 |
| `.chat-tab.active` | mesmo | mesmo | mesmo | 12.5px / 500 / accent-fg, bg accent |
| `.chat-search` | 320px max | `4px 10px`, gap 6px | `6px` (sm) | 12.5px |
| `.day-sep` (HOJE) | 100% center | margin 8px 0 | 0 | 11px / 700 uppercase |

### 4.4 Bolhas de mensagem

| Componente | Max-width | Padding | Border-radius | Font |
|---|---|---|---|---|
| `.bubble.me` | **60%** | `8px 12px` | `12px 12px 4px 12px` | 13/400 / 18.2px line-height |
| `.bubble.them` | **60%** | `8px 12px` | `12px 12px 12px 4px` | 13/400 / 18.2px line-height |
| `.bubble.continued` | mesmo | mesmo | só altera o canto: `12px 12px 12px 12px` ou inverte conforme lado | mesmo |
| `.bubble-av` | **28×28px** | grid center | `50%` | 10.5px / 700 |
| `.bubble-av-spacer` | **28px** (vazio) | — | — | (mantém alinhamento em msg continued) |
| `.bubble .author` | min-content | padding-left 2px | 0 | 11px / **600** / `--text-dim` |
| `.bubble .meta` | min-content | margin-top 4px | 0 | 10.5px / 400 / opacity 0.75 |

### 4.5 Composer

| Componente | Width/Height | Padding | Border-radius | Font |
|---|---|---|---|---|
| `.composer` | 100% × auto | `12px 20px` | 0 (border-top) | — |
| `.composer-box` | 100% | `10px 12px` | `8px` | — |
| `.composer-box textarea` | 100% × **24-160px** | 0 | 0 | 13/400 / 1.4 line-height |
| `.composer .icon-btn` | **28×28px** | grid center | `6px` (sm) | hover bg-2 |
| `.send-btn` | min-content × **28px** | `0 14px` | `6px` (sm) | 12.5px / 500 / accent-fg, bg accent |
| `.send-btn[disabled]` | mesmo | mesmo | mesmo | opacity **0.45**, cursor not-allowed |
| `.composer-hint-inline` | min-content | margin-right 8px | 0 | 10.5px / 400 / `--text-mute` |
| `.kbd` | min-content | `1px 5px` | `4px` | 10/400 / **mono**, bg-2, border 1px |

### 4.6 Apps Vinculados (coluna direita)

| Componente | Width/Height | Padding | Border-radius | Font |
|---|---|---|---|---|
| `.apps` | **320px** × 100vh | 16px | 0 (border-left) | — |
| `.apps-h` | 100% | `4px 0 12px` | 0 | 10.5px / 700 uppercase |
| `.lblock` (card) | 100% × auto | 0 (interno) | `8px` | margin-bottom 12px |
| `.lblock-h` (header) | 100% | `10px 12px`, gap 8px | 0 | 12.5px / **600** |
| `.lblock-b` (body) | 100% | `0 12px 12px`, padding-top 10px | 0 | — |
| `.origin-badge` | min-content | `1px 5px` | `3px` | 9.5/700 uppercase, letter-spacing 0.05em |
| `.lblock-cta` | 100% × auto | `7px 10px`, margin-top 10px | `6px` (sm) | 12/500 / accent-fg, bg accent |
| `.lbtn-sec` | 1fr cada | `6px` | `6px` (sm) | 11.5/400 |
| `.lkv` | 100% | `4px 0`, gap 8px | 0 | 12/400, label min-width 88px |
| `.lkv b` (valor) | min-content | 0 | 0 | 12/500 / `--text` |
| `.lkv b.mono` | mesmo | 0 | 0 | 11.5/400 / **mono** |
| `.latt` (anexo) | 100% | `6px 8px`, gap 8px | `6px` (sm) | bg-2 + border 1px |
| `.latt-body b` | min-content | 0 | 0 | 12/400, ellipsis |
| `.latt-body small` | min-content | 0 | 0 | 10.5/400 / `--text-mute` |
| `.lhist` (timeline) | 100% × auto | gap 8px entre items | 0 | — |
| `.lhist li::before` (bullet) | **4×4px** | absolute left 0 top 5px | `50%` | bg accent |
| `.lhist-when` | min-content | min-width 70px | 0 | 10.5/400 / **mono** / `--text-mute` |

### 4.7 Tweaks panel (FAB + card)

| Componente | Width/Height | Padding | Border-radius | Notes |
|---|---|---|---|---|
| `.cockpit-tweaks-fab` | **44×44px** | grid center | `50%` | bg surface, border 1px, shadow |
| `.cockpit-tweaks-card` | **280px** × auto | 0 (interno) | `12px` (lg) | shadow forte |
| `.cockpit-tweaks-card-h` | 100% | `12px 14px` | 0 (border-bottom) | 12.5/600 |
| `.cockpit-tweaks-section` | 100% | `12px 14px` | 0 | border-bottom entre |
| `.cockpit-tweaks-radio` | 100% | 3px | `6px` (sm) | grid 1fr×3, gap 4px |
| `.cockpit-tweaks-radio button` | 1fr | `5px 8px` | `4px` | 11.5/400 |
| `.cockpit-tweaks-slider` | 100% × **4px** | 0 | `2px` | thumb 14×14px circular |

---

## 5. Border-radius — escala canônica

| Token | Valor | Uso |
|---|---|---|
| **2px** | thumb do slider | (não tokenizado) |
| **3px** | `.origin-badge` | tokens hardcoded |
| **4px** | `.kbd`, cantos pequenos das bolhas continued | (hardcoded) |
| **6px** (`--radius-sm`) | botões secundários, inputs, items de menu, cards menores, abas | mais comum |
| **8px** (`--radius`) | composer-box, lblock cards, dropdown empresas | médio |
| **12px** (`--radius-lg`) | bolhas de mensagem, tweaks card, FAB (50% pra ser circular) | grande |
| **50%** | avatares, bullets, dots online | circular |

**Regra**: `--radius-sm` (6px) é o default pra qualquer elemento clicável.

---

## 6. Spacing (gap, padding, margin)

Múltiplos de 4px (R-DS-004 SPEC):

- **2px**: micro (margin entre author e bolha)
- **4px**: ícone para texto, gap interno de listas densas
- **6px**: padding interno de items pequenos (`.sb-action`, `.sb-conv`)
- **8px**: gap padrão entre componentes em sidebar, padding de bolhas vertical
- **10px**: padding interno de cards médios (`.lblock-h`)
- **12px**: padding bolhas horizontal, padding section topbar, gap entre cards verticais
- **16px**: padding container `.apps`, padding topbar lateral
- **20px**: padding lateral thread + composer (mais respiro)
- **24px**: padding vertical thread (entre msgs e composer)

**Atenção `Δ density`**: padding interno de cards (`--card-pad`) escala com slider — `8px` (skim) → `16px` (briefing). Altura de linha (`--row-h`) escala `26px` → `42px`.

---

## 7. Transições e microinterações

### 7.1 Hover transitions

Default: **sem transição** (mudança instantânea). Exceções:

| Componente | Property | Duration |
|---|---|---|
| `.cockpit-tweaks-fab` | transform, color | **120ms ease** |
| `.lblock-chev` | transform (rotação) | **150ms ease** |
| `.sb-group-h .chev` (não usado no Cockpit ainda) | transform | **150ms ease** |
| `.cockpit-tweaks-section` collapse (futuro) | height | **200ms ease-out** |

**Princípio**: hover é instantâneo (rapidez). Rotações de chevron e movimento de FAB têm transição curta pra parecer fluido.

### 7.2 Animação typing indicator

```css
@keyframes cockpit-typing {
  0%, 60%, 100% { opacity: 0.35; transform: translateY(0); }
  30%           { opacity: 1;    transform: translateY(-3px); }
}
.cockpit .typing span {
  animation: cockpit-typing 1.2s infinite ease-in-out;
}
.cockpit .typing span:nth-child(2) { animation-delay: 0.15s; }
.cockpit .typing span:nth-child(3) { animation-delay: 0.30s; }
```

Cada dot é **6×6px**, cor `var(--text-mute)`, spacing `gap: 4px`. Bolha typing tem padding `10px 14px`, border-radius `var(--radius-lg)` com canto inferior esquerdo `4px`.

### 7.3 Estados disabled

- `.send-btn[disabled]`: `opacity: 0.45`, `cursor: not-allowed`, hover **mantém** background original (não dá feedback de hover quando disabled).
- `.sb-tab[disabled]`: `opacity: 0.5`, `cursor: not-allowed` (raramente usado).

---

## 8. Pixel perfect — checklist de degradação

Pra detectar se algo "desandou" visualmente, comparar contra estes valores:

- [ ] Sidebar tem exatamente **260px** de largura
- [ ] Topbar tem exatamente **44px** de altura
- [ ] Apps Vinculados tem exatamente **320px** (ou 0 se colapsado)
- [ ] Avatar empresa: **22×22px** com border-radius **6px** (NÃO 50%)
- [ ] Avatar user (rodapé): **26×26px** com border-radius **50%**
- [ ] Avatar conversa header: **36×36px** circular com dot online **10×10px**
- [ ] Avatar bolhas them: **28×28px** circular
- [ ] Bolha max-width: **60%** (sempre — não vira 100% em mobile sem ADR)
- [ ] Composer textarea: min **24px** / max **160px** (auto-grow no meio)
- [ ] Send button: **28px** altura, padding `0 14px` lateral
- [ ] Icon buttons (composer / topbar / linked): **28×28px** sempre
- [ ] FAB Tweaks: **44×44px** circular
- [ ] Origin badge: padding `1px 5px`, border-radius `3px`, fonte 9.5px/700 uppercase com letter-spacing
- [ ] Bullet conversa: **6×6px** (filled = accent, outline = border 1.4px)
- [ ] Day separator: 11/700 uppercase, letter-spacing 0.05em

---

## 9. Não-existem (proibições)

- ❌ Cores hardcoded fora dos tokens (`bg-blue-500`, `#1a73e8`, `rgb(255,0,0)`)
- ❌ Fontes diferentes de IBM Plex Sans/Mono
- ❌ Sombras múltiplas perpendiculares (todas saem do eixo Y)
- ❌ Border-radius "estranho" (5px, 7px, 10px, 16px) — só 3/4/6/8/12/50%
- ❌ Pesos 300, 350, 450, 550, 650 — só 400/500/600/700
- ❌ Tamanhos de fonte fora da escala documentada (§1.2)
- ❌ Transição padrão > 200ms (parece lento)
- ❌ Origin badge com cor fora das 5 (OS/CRM/FIN/PNT/MFG)

---

## 10. Como Claude Design usa este catálogo

Quando ela voltar (com créditos):

1. Lê este arquivo **antes de desenhar qualquer coisa**
2. Usa exatamente esses tokens nos mockups HTML
3. Quando hesitar entre 2 valores, **escolhe sempre o que está aqui**
4. Se precisar de um valor novo (ex: nova fonte de tamanho), **abre proposta de adição** ao catálogo em vez de inventar inline
5. Cada mockup HTML **importa IBM Plex** no head e **respeita escopo `.cockpit`**

Isso evita o que você temeu: "desandar" o acabamento. Tokens explícitos = referência objetiva.

---

## 11. Snapshot reproducible

Pra reproduzir o estado exato deste catálogo localmente:

```bash
git checkout feat/copiloto-cockpit-piloto
git rev-parse HEAD  # deve ser d47dac9b ou descendente
npm run build:inertia
# abrir oimpresso.test/copiloto/cockpit no Herd
```

Estado canônico em produção: `https://oimpresso.com/copiloto/cockpit` (Vibe `workspace`, accent hue `220`, density `50`).

---

> **Aviso pra agente IA futuro**: este catálogo é a **fonte da verdade visual**. Se algo no código diverge daqui, ou o código está errado (pull request pra alinhar) ou o catálogo está obsoleto (atualizar com ADR justificando). Não há terceira opção.
