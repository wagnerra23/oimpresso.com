# CODE_DESIGN_CONTRACT.md

> Contrato visual entre **Cowork [CC]** e **Claude Code [CL]**.
> Vigente desde: **2026-05-27**.
> Source of truth: **`Design System.html`** (na raiz do projeto Cowork) + `tokens.css` / `design-system.css` no repo.

---

## 🟢 A regra única

> **Se a feature pediu algo que não está no Design System, o Code PARA, atualiza o DS primeiro, e só depois aplica.**

Sem isso, é gambiarra garantida. É a causa raiz dos conflitos de CSS que estamos vivendo.

---

## Por que esse contrato existe

Conflitos recorrentes identificados no Cowork em 2026-05-27:

- **7 arquivos CSS** (`styles.css`, `vendas.css`, `financeiro.css`, `clientes-page.css`, `oficina-page.css`, `compras-page.css`, `chat-jana.css`, `pg-styles.css`) — sem hierarquia clara entre eles
- **Classes redeclaradas** (ex.: `.os-search` em `styles.css:2229` E `styles.css:4854` com valores diferentes)
- **Tokens espalhados** — cada arquivo redefine `--accent`, spacing, radius
- **4 variantes do mesmo módulo** em `prototipo-ui-patch/` (`vendas-financeiro-completo/`, `vendas-refino-kb-9.75/`, etc.) — Code abre, lê tudo, fica perdido
- **Sem contrato visual** — Code recebe JSX + CSS solto e tem que adivinhar a INTENÇÃO

Resultado: Code aplica → conflita → ele honestamente não sabe se errou ou se o Cowork não bate.

---

## Hierarquia de fontes (canônicas)

```
1. Design System.html       ← SOURCE OF TRUTH visual (Cowork)
2. tokens.css               ← single :root + dark theme (repo)
3. design-system.css        ← componentes canônicos (repo)
4. <modulo>-overrides.css   ← APENAS overrides justificados (mínimo)
```

**Tudo o que está em 1, 2, 3 é canônico.** Override em 4 só com justificativa registrada em comentário CSS.

---

## Checklist pre-commit (obrigatório)

Antes de cada `git commit` do Code:

```bash
# 1. Cor — zero hex/rgb fora dos tokens
grep -RE '(#[0-9a-f]{3,8}|rgb\()' resources/css/<modulo>.css | grep -v tokens.css

# 2. Componente — classe existe no DS?
# Se a feature usou .foo-bar-baz que não está em design-system.css → STOP
grep -E '\.(foo|bar|baz|qux)-' resources/css/<modulo>.css

# 3. Spacing — usei --s-N?
grep -E 'padding:\s*[0-9]+px' resources/css/<modulo>.css   # se != 0, suspeito
grep -E 'margin:\s*[0-9]+px' resources/css/<modulo>.css

# 4. Radius — usei --radius*?
grep -E 'border-radius:\s*[0-9]+px' resources/css/<modulo>.css

# 5. Type — escala canônica?
grep -E 'font-size:\s*[0-9]+(\.[0-9]+)?px' resources/css/<modulo>.css

# 6. Sem redeclaração — classe não duplicada?
grep -RE '\.pageheader|\.btn|\.badge|\.datatable' resources/css/  # só 1 lugar

# 7. CSS novo desnecessário — tudo justificado?
git diff resources/css/  # revisar linha-a-linha
```

Falhou qualquer? Não commita. Atualiza DS primeiro.

---

## Quando o Code DEVE parar e pedir

| Situação | Ação |
|---|---|
| Cliente pediu componente que **não existe** no DS | Parar · escrever proposta no `COWORK_NOTES.md` · esperar [CC] atualizar DS |
| Variação de cor que **não está nos tokens** | Parar · propor ampliação dos tokens · esperar aprovação |
| Layout que **não bate com PT-01..N** | Parar · propor PT-novo · esperar aprovação |
| Existe **classe parecida** no DS mas não 100% | Usar a do DS + override mínimo justificado em comentário · não criar paralela |
| **Conflito** entre CSS de módulo e DS | Sempre vence o DS · ajustar o módulo |

---

## Template de proposta de extensão do DS

Quando o Code precisa de algo novo, ele escreve em `COWORK_NOTES.md`:

```md
### YYYY-MM-DD — Code propõe extensão do DS

**Feature pediu:** <descrição>
**Componente necessário:** <nome proposto>
**Por que não cabe no DS atual:** <justificativa>
**Proposta visual:** <ASCII art ou screenshot link>
**Token novo necessário?:** <sim/não, qual>
**Aguardando:** [CC] atualizar Design System.html antes de seguir.
```

[CC] (Cowork) responde:
1. Atualiza `Design System.html` com o novo componente
2. Atualiza `tokens.css` / `design-system.css` (se necessário)
3. Marca como **aprovado** no COWORK_NOTES
4. Devolve pro Code prosseguir

---

## Como Wagner deve pedir features daqui pra frente

Template curto:

```
TELA: <nome>
PADRÃO BASE: PT-01 Lista (ou PT-02 Drawer, etc.)
COMPONENTES USADOS: pageheader, moduletopnav, datatable, fsm-stepper, drawer
NOVOS (se houver): <descreve> — quer que eu adicione no DS primeiro?
ALVO DE NOTA: ≥ 9.0
TWEAKS: nenhum / [lista]
EMPACOTAMENTO: zero-touch pro Code (igual OS)
```

Se `NOVOS` não está vazio → [CC] para, atualiza o DS, mostra pro Wagner, depois aplica.

---

## Lista canônica de componentes (DS v3)

Tudo abaixo está no `Design System v3.html`. Comportamento em `ds-behavior.js` (vanilla) ou Radix (React).

### v1 — Componentes base (17)

| # | Componente | Classe | Variantes |
|---|---|---|---|
| 1 | Button | `.btn` | `primary`, `ghost`, `danger`, `icon`, `sm`/`lg`, `[disabled]` |
| 2 | Input | `.input` | em `.field` com label · `[disabled]`/`[readonly]` |
| 3 | Select | `.select` | idem |
| 4 | Textarea | `.textarea` | idem |
| 5 | Search box | `.search` | inline com icon |
| 6 | Badge | `.badge` | `ok`, `warn`, `danger`, `info`, `count` |
| 7 | Avatar | `.avatar` | `c1..c8`, `md`, `lg` |
| 8 | Frescor pill | `.frescor` | `fresc`, `recente`, `distante`, `frio` |
| 9 | PageHeader | `.pageheader` | + `-l`, `-icon`, `-title-wrap`, `-r` |
| 10 | ModuleTopNav | `.moduletopnav` | + `-tab`, `-n` |
| 11 | DataTable | `.datatable` | + `.urgent`, `.selected`, `.mono`, `.num`, `.archived` |
| 12 | FsmStepper | `.fsm-stepper` | `fsm-inline`, `fsm-full` |
| 13 | Drawer | `.drawer` | + `-h`, `-title-wrap`, `-section`, `-foot` |
| 14 | Modal | `.modal` | + `-foot` |
| 15 | Empty state | `.empty-state` | genérico |
| 16 | Toast | `.toast` | `ok`, `warn`, `danger` |
| 17 | Sidebar | `.sb` / `.sb-item` | **canon real do repo (cockpit.css)**: `.sb-top`/`.sb-cp-btn` (company picker) · `.sb-nfe` · `.sb-group-h` (colapsável) · `.sb-item` (+ `.active` barra accent, `.badge`) · `.sb-user-btn` (footer slim) · modo rail 56px + ⌘\ |

### v1.1 — Estados + suavização (Onda A, +8)

| # | Componente | Classe | Variantes |
|---|---|---|---|
| A1 | Skeleton | `.skel` | `.skel-text`, `.skel-avatar`, `.skel-row`, `.skel-card`, `.skel-stack` |
| A2a | Field error | `.field.has-error` | + `.field-error`, `.field-help` |
| A2b | Alert banner | `.alert` | `warn`, `info`, `ok` (default = danger) · `.retry` |
| A3a | Save bar | `.savebar` | `dirty`, `saving`, `saved`, `hidden` |
| A3b | Spinner | `.spinner` | `sm`, `lg` |
| A4 | Suavização | (transitions globais) | aplicada em btn, badge, fsm-dot, row, input, etc. |
| A6 | Empty matrix | `.empty-state` | `first`, `no-results`, `no-perm`, `offline`, `done`, `archived`, `filtered`, `error` |
| A7 | Disabled/Readonly | `[disabled]`, `[readonly]` | + `.archived` em row |
| A8 | Optimistic UI | `.optimistic` | `confirmed`, `rolled-back` |

### v1.2 — Componentes faltando (Onda B, +10)

| # | Componente | Classe | Variantes |
|---|---|---|---|
| B1 | Tooltip | `[data-tooltip]` | `data-tooltip-pos="top"` (default) / `bottom` / `right` |
| B2 | Popover / Menu | `.popover` | `.menu-item` (+ `.danger`) · `.menu-section` · `.menu-sep` · `.kbd-hint` |
| B3 | Tabs (conteúdo) | `.tabs` | `.tab[aria-selected]` · `.count` |
| B4 | Breadcrumb | `.breadcrumb` | `<a>` · `.sep` · `.current` |
| B5 | Pagination | `.pagination` | `.pg-btn[aria-current]` · `.pg-ellipsis` · `.pg-meta` |
| B6 | Filter chip | `.filter-chip` | `.label` · `.value` · `.remove` |
| B7 | Combobox | `.combobox` | `.combobox-input` · `.combobox-list` · `.combobox-item[aria-selected]` · `<mark>` |
| B8 | Date picker | `.datepicker` | `.dp-input` · `.datepicker-cal` · `.day` (`.today`/`.selected`/`.muted`/`.in-range`) |
| B9 | Toggle / Switch | `.toggle` | `<input type=checkbox>` + `.track` + `.lbl` (com `<small>`) · `.disabled` |
| B10 | ⌘K Command palette | `.cmdk-back` | `.cmdk` · `.cmdk-search` · `.cmdk-list` · `.cmdk-group` (+ `-h`) · `.cmdk-item[aria-selected]` · `.cmdk-foot` |

**Padrões:**

### v1.3 — Tokens+ (Onda C)

| Token/util | Uso |
|---|---|
| `--shadow-0..4` | elevation scale: 0 flat · 1 card · 2 hover · 3 drawer/popover · 4 modal/cmdk |
| `--z-base..z-max` | z-stack completo (0/10/25/40/50/60/80/90/100) |
| `--bp-mobile..ultra` | breakpoints (640/900/1200/1440/1920) |
| `[data-density]` | `compact`/`default`/`comfy` — escala row-h, field-h, pad |
| `.cq-host` + `.cq-col-optional` / `.cq-hide-sm` / `.cq-stack-xs` | container queries |

### v2 — Sistêmico (Onda D)

| # | Componente | Classe |
|---|---|---|
| D3 | Data viz | `.viz-spark` (up/down) · `.viz-bars` · `.viz-gauge` (ok/warn/danger) · `.viz-trend` · `.viz-progress` · `.viz-ageing` · `.viz-heat` |
| D4 | Saved views | `.saved-views` · `.saved-views-trigger` · `.saved-views-menu` · `.saved-view-item` |
| D5 | Bulk action bar | `.bulk-bar` · `.bulk-count` · `.bulk-act` (+ `.danger`) · `.bulk-close` |
| D6 | Form wizard | `.wizard` · `.wizard-rail` · `.wizard-step` (done/current/todo) · `.wizard-body` · `.wizard-foot` |
| D7 | Side-by-side diff | `.diff` · `.diff-side` (old/new) · `.diff-row` (added/removed) |
| D8 | Inline editing | `.cell-edit` (+ `.editing`) · `.edit-hint` |
| D1 | A11y helpers | `.sr-only` · `.focus-ring` + contrato ARIA por componente |
| D2 | Dark mode | `[data-theme="dark"]` (tokens em tokens.css) |
- PT-01 Lista (Index de módulo) · 4 slots: pageheader + moduletopnav + toolbar + datatable
- PT-02 Drawer detalhe · ao clicar linha
- PT-03 Form drawer · multi-step (Nova/Editar)
- PT-04 Modal confirmação · só pra ações destrutivas

---

## Proibições explícitas (charter)

Tudo abaixo já apareceu no projeto e causou ruído. Não repetir:

- ❌ **Inventar cor** fora dos tokens
- ❌ **`border-radius` ≥ 12px** em componentes operacionais
- ❌ **Sombra forte** em cards estáticos
- ❌ **Gradiente** em background de tela ou card
- ❌ **Emoji em UI cliente-facing** — use ícones SVG
- ❌ **Modal full-screen** pra detalhe — use drawer (PT-02)
- ❌ **Inglês em UI cliente-facing** — "Vendas" não "Sells"
- ❌ **CTA WhatsApp cliente-facing** dentro do produto
- ❌ **Redeclarar tokens** em CSS de módulo
- ❌ **Redeclarar componentes** com mesma classe
- ❌ **Inventar nome de classe novo** sem checar o DS

---

## Onda de migração — do estado atual

| Onda | Objetivo | Estimativa |
|---|---|---|
| 1 | Extrair `tokens.css` do `styles.css` (single source of truth) | 1 sessão |
| 2 | Consolidar `design-system.css` com componentes canônicos · auditar duplicatas | 1-2 sessões |
| 3 | Migrar módulos (Clientes ✓ · OS ✓ pós-F1 · Vendas · Compras · Financeiro · …) — 1 por sprint | 5-6 sprints |
| 4 | Visual regression: snapshots em compact/default/comfy × light/dark · CI bloqueia regressão | 1 sessão |

Resultado esperado: de 7 arquivos CSS / ~6000 linhas → **2 arquivos** (`tokens.css` + `design-system.css`) + ~800 linhas. **Zero conflito** porque tudo aponta pro mesmo lugar.

---

## Versionamento do DS

| Versão | Data | Mudanças |
|---|---|---|
| **v1** | 2026-05-27 | Versão inicial. 17 componentes + 4 padrões + tokens canônicos. |
| **v1.1** | 2026-05-27 | Onda A — Estados + suavização. +8 componentes: skeleton, field-error, alert, savebar, spinner, empty matrix (9 cenários), disabled/readonly canon, optimistic UI, suavização aplicada, reduced motion pervasive. Score DS: **5,2 → 7,0**. |
| **v1.2** | 2026-05-27 | Onda B — Componentes faltando. +10 componentes: tooltip, popover/menu, tabs de conteúdo, breadcrumb, pagination, filter chip, combobox, date picker, toggle, ⌘K command palette. Score DS: **7,0 → 8,3**. |
| **v1.3** | 2026-05-28 | Onda C — Refinamento tokens. Shadow elevation 5 níveis, z-stack completo, breakpoints, density modes aplicados, container queries. |
| **v2** | 2026-05-28 | Onda D — Sistêmico. Data viz primitives (spark/bars/gauge/trend/progress/ageing/heat), saved views, bulk action bar, form wizard, side-by-side diff, inline editing, a11y contract por componente, dark mode matrix. **DS completo.** Score DS: **8,3 → 9,3**. |
| **v3** | 2026-05-28 | Onda E — Produção. Tokens semânticos (camada de intenção), componentes vivos (comportamento real + ARIA aplicado via `ds-behavior.js`/Radix), PT-05 a PT-08, setup de visual regression. Recalibrado honesto: **8,4 → 9,6**. |
| **v3.1** | 2026-05-28 | Sidebar canon = real do repo (cockpit.css). Tokens `--sb-*` viram dark azul-petróleo fixo (`oklch(0.18 .006 240)`), não charcoal. +company picker, NfeCertBadge, grupos colapsáveis, item ativo c/ barra accent, footer slim, modo rail 56px + ⌘\. DS deixa de idealizar a sidebar e adota a verdade de produção. |

Cada mudança no DS:
1. Bump de versão (v1.1, v2, etc.)
2. Entrada no `Design System.html` § "Histórico"
3. ADR registrada em `memory/decisions/`
4. Migração agendada nos módulos afetados (não obrigatória imediata; depreciar antes)

---

## Como o Code deve resistir a pressão

Quando pressionado a "só faz funcionar":
- **Não inventar CSS**
- **Pedir extensão do DS** (template acima)
- **Documentar a fricção** no `CODE_NOTES.md` pra Wagner ver
- **Recusar commit** se a checklist falhar

O custo de 1 dia parado pedindo extensão << custo de 6 meses de débito técnico de conflitos CSS.

---

## Referências cruzadas

- **Source of truth visual:** `Design System.html` (Cowork, raiz)
- **Tokens canônicos:** `tokens.css` (repo, raiz CSS)
- **Componentes canônicos:** `design-system.css` (repo)
- **Loop formal:** `PROTOCOL.md` (repo, 6 papéis × 7 fases)
- **Briefing visual:** `CLAUDE_DESIGN_BRIEFING.md` (repo)
- **Constituição UI v2:** ADR UI-0013 (repo)
- **PT-01 Lista:** `memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md` (repo)

Tudo encaixado. Nada sobrando.
