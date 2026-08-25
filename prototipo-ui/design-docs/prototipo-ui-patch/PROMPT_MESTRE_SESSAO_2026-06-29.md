# PROMPT MESTRE PARA CLAUDE CODE — Sessão DS + Financeiro ([W] 2026-06-29)

> Cole UMA vez no Claude Code. [CC] propõe · [CL] numera ADRs, aplica no git canônico, abre PR, merge sob CI verde + OK de [W]. NADA está commitado (Cowork é read-only no git).
> Roxo canônico `oklch(0.55 0.15 295)` **intocado** em tudo. Todas as mudanças de cor são **aditivas/token**.
> Reality-check @main já feito: tokens vivem em **DTCG** `resources/css/tokens/semantic.tokens.json` (Style Dictionary, ADR 0300 — `_generated-*.css` é SAÍDA, não editar à mão). Telas = **bundles escopados** (`.fin-cowork`, `.sells-cowork`, etc.) gerados por `scripts/scope-*-css.py`, não 1:1 com os arquivos do protótipo.

Esta sessão tem **3 frentes**. Faça em ADRs separados ou um guarda-chuva, como preferir.

═══════════════════════════════════════════════════════════
## FRENTE 1 — Tokens semânticos novos no DS (DTCG)
═══════════════════════════════════════════════════════════
Detalhe completo + valores: `_PROPOSTA-tokens-semanticos-frescor-kind-sla-canal.md` e o DTCG pronto `resources/css/tokens/_PARCIAL-domain-semantic.tokens.json` (URLs abaixo).

Adicionar em `semantic.tokens.json` (light `$value` + dark `com.oimpresso.dark`), grupo `domain` ou conforme layering da Constituição UI v2:
- **`sla`** — `fresh / aging / late / expired / paid` (+ `-soft` / `-dot` / `-line`). **ESCALA TEMPORAL ÚNICA E CANÔNICA** ([W] aprovou).
- **`kind`** — `customer / supplier / employee / representative` (+ `-soft`).
- **`kpi-feature`** — `bg / bg-hi / line / fg / fg-2` (card navy de destaque).
- **`vip`** — `vip` / `vip-soft` (selo dourado).
- **`canal`** — `email / ig / fb / ml` (`-tint` / `-bg` / `-fg`).
- **`frescor` NÃO entra** — foi **consolidado em `sla`** ([W] 2026-06-24). recente→fresh · fresc→aging · distante→late · frio→expired.

Regras: rodar build do Style Dictionary + `dtcg-equivalence.mjs`; NÃO sobrescrever `cockpit.semantic` pos/neg/warn; reconciliar valores com o espelho `ds-v6/tokens.css` ciente do chroma-bump Cowork (VIDA 06-11). `sla.paid` é alias pro neutro (`text-mute`/`bg-2`).

═══════════════════════════════════════════════════════════
## FRENTE 2 — Migrar telas pra consumir os tokens (matar drift)
═══════════════════════════════════════════════════════════
Cada espelho Cowork (URLs abaixo) mostra o mapeamento classe→token. Aplicar nos bundles escopados equivalentes do git. Remover overrides `[data-theme=dark]` que os tokens tornam redundantes.

- **SLA = escala única de 4 passos** convergir TODAS as pílulas de tempo/vencimento:
  - `inbox-page.css` → `.om-sla-pill` (já era 4 passos) + tom por canal `.om-bub.ch-*` / `.om-thread-c` → `--canal-*`.
  - `vendas.css` → `.vd-sla-*`: fresco→fresh · atrasando→aging · estourado→expired · paga→paid.
  - `financeiro.css` → `.fin-frescor-*`: fresh/soon→fresh · warning→aging · **today→late (ganhou o passo laranja)** · overdue→expired · paid→paid.
  - `kb-page.css` → `.kb-fresh`: fresh→fresh · aging→aging · stale→late · expired→expired.
- **Forja** `forja-page.css` → `.fj-fresco` (lido/inferido/sync) = **procedência, NÃO tempo** → `--pos` / `--warn` / neutro (NÃO `--sla-*`).
- **Clientes** `clientes-page.css` → frescor (agora `--sla-*`), `--kind-*`, `--kpi-feature-*`, `--vip`.
- **KB** `kb-page.css` → **unificada no roxo** ([W]): azul accent 240 → `var(--accent)` / `-soft` / `-line`. Status verde/amber/vermelho → `--pos`/`--warn`/`--neg`. (Mantido só azul de categoria/tag e hues quentes de conteúdo.)
- **Produtos** `prod-page-extras.css` → pills de estoque/margem/compat/grade → `--pos`/`--warn`/`--neg` (mantido azul grade-info/hover).
- **styles.css** (shell) → ~80 literais semânticos (vermelho-urgente, verde, amber, accent) → tokens. NÃO tocar: `#fff`/`#000`, gradientes decorativos, rampa neutra fria hue-250 (bespoke).
- **Financeiro está ~95% tokenizado** e é o exemplo de referência. `--cb-hue` (filtros) é scalar de hue REGISTRADO usado semanticamente (verde receber · vermelho pagar/atraso · azul pagas) — **manter, é code de cor, não drift**.

═══════════════════════════════════════════════════════════
## FRENTE 3 — Financeiro: igualar git↔protótipo + comentários [W]
═══════════════════════════════════════════════════════════
Espelhos: `financeiro.css` + `financeiro-page.jsx` + `qa-conformance.js` (URLs abaixo). Detalhe do filtro: `PONTE_CL_financeiro-filtro-igualar.md`.

**3a · Filtro de ciclo (igualar — [W] "pílula"):**
- Forma = **pílula** (`border-radius:99px`, h24, box ✓ unicode) — git já é pílula, protótipo foi alinhado. OK.
- **Contador transparente** ([W] 2026-06-16): git ainda usa pílula preenchida no `.fin-filter-cb.on .fin-filter-ct` → trocar pra `background:transparent; color:oklch(0.40 0.15 var(--cb-hue))` (mesmo tratamento on/off). Ver PONTE_CL.
- **Refino premium** ([W] 2026-06-29): bordas dos chips agora translúcidas via `color-mix(in oklch, oklch(0.55 0.13 var(--cb-hue)) X%, transparent)` (22% off · 50% on) + sombra suave no `.on`. Assinatura premium da Produção. Aplicar no git pra manter paridade.

**3b · Datas personalizadas ([W] "como vai ficar?"):** novo preset **"Personalizado"** na PeriodBar. Ao ativar (`period==="custom"`), o nav vira 2 `input[type=date]` (início *até* fim) filtrando de verdade. Estado `customRange {from,to}`; `periodWindow(mode, anchor, custom)` ganhou 3º param; `periodLabel`/`periodLabelShort` cobrem "custom". CSS `.fin-pb-custom` / `.fin-pb-date` (mono, borda token, foco accent). Ver `financeiro-page.jsx`.

**3c · Densidade ([W] "remova a opção 3"):** removido o botão **"Espaçosa"** (`spacious`). Sobram Compacta + Confortável (git já fazia isso na Onda 12.6 — paridade).

**3d · Refino premium na tabela ([W] 2026-06-29):**
- `DirIcon` (seta in/out, col. 3): borda `1px color-mix(in oklch, <pos|neg> 22%, transparent)` + `box-shadow 0 1px 3px -1px color-mix(... 28%)`, stroke 2.
- `StatusBadge` (col. 7): borda `1px color-mix(in oklch, <cor-status> 22%, transparent)` mantendo dot + fundo soft. Cada `STATUS_STYLES` ganhou campo `c` (cor base).

**3e · Remover Conformância DS ([W] "remover, não faz mais sentido"):** em `qa-conformance.js`, removido o launcher UI (`#qa-launch` + `#qa-panel` + atalho ⌘⇧Q + chamada `mount()`/`gated()`). **Mantida** a API `window.QAConformance` (ritual/verificador). No git: remover o equivalente do botão flutuante de conformância da tela; manter o gate de CI (`conformance-gate.mjs`) que é outra coisa.

**3f · Drawer de detalhe do título — diferenças git↔protótipo (incluir):**
Diferença ESTRUTURAL (não é bug, é arquitetura):
- **Protótipo:** `<aside>` custom `position:fixed; top:0; right:0` (classe `.fin-drawer-wide`) + backdrop `bg-black/20`. Tokens herdam naturalmente do ancestral `.fin-root`/`.cockpit`.
- **Git:** shadcn `<Sheet>` via **Portal no `<body>`** (fora do wrapper `.fin-cowork`) → exige os fixes **Onda 22–26** já no `fin-cowork.css`: `[role="dialog"].fin-cowork` re-injeta `--bg/--text/--accent/--border` + força `background:#fff !important` (colisão com `bg-background` do Tailwind dava transparente), `.fin-drawer-tabs{margin:0!important}` (margin negativo de sangria quebrava no portal), `border-stone-100`→stone-200, alinhamento `.fin-drawer-tab-glyph`, padding do SheetContent.
- **Direção:** [CL] **mantém o `<Sheet>` portal + os fixes Onda 22–26** (é o real Inertia/shadcn). Só **garante paridade visual** com o spec do protótipo abaixo.

Spec visual do drawer (do protótipo, referência pra conferir no git):
- Largura **560px** (`max-w 92vw`), `box-shadow: var(--sh-2)` (flutua, 1 fonte de luz).
- **Header:** DirIcon (size 16, **agora premium** — borda color-mix + sombra, vem do shared component da Frente 3d) + título + nav **J/K** (`.fin-dw-nav-btn` com chrome de repouso: `border:1px var(--border); background:var(--surface)` — [W] 2026-06-16, não "sem CSS").
- **Hero fixo** (fora do scroll): luz da identidade — `radial-gradient(440px 150px at 88% -35%, color-mix(in oklab, var(--accent) 15%, transparent)…)` + linear accent 4–8%. Mesmo espírito da Onda 28 (hero claro) já no git.
- **Tabs** `.fin-drawer-tabs`: Detalhes + **✦ IA empurrada à direita** (`margin-left:auto` — IA é ferramenta, não aba irmã), ativa sublinhada em accent; IA em accent, Editar em warn (`-edit`).
- **StatusBadge** no hero: premium (borda color-mix) via Frente 3d.
- **Footer** `.fin-drawer-footer`: troubleshooter + hints J/K, **overflow-safe** (troubleshooter encolhe, hint J/K some quando troubleshooter existe, botões `flex-shrink:0`).
- KV grid 2col (`gap:10px 20px`), painel Editar `warn-soft` full-width, FSM stepper compacto no hero.

═══════════════════════════════════════════════════════════
## EM ABERTO p/ [W] (não decidir sozinho)
═══════════════════════════════════════════════════════════
- Preset "Personalizado" aparece também na **Cobrança recorrente** (reusa a PeriodBar) mas lá não está wirado — decidir wirar ou esconder.

═══════════════════════════════════════════════════════════
## ESPELHOS COWORK (fetch por curl — referência de valores/mapeamento)
═══════════════════════════════════════════════════════════
[URLs coladas por [CC] no envio — válidas ~1h]

## NÃO fazer
- Não mudar o roxo 295. Não criar `--accent` por módulo. Não consolidar frescor sem o que já foi decidido. Não editar `_generated-*.css` à mão. Não sobrescrever pos/neg/warn do git. Não remover o `conformance-gate.mjs` de CI (só o botão de UI).

git add/commit/push + PR + merge quando CI verde. Atualizar `SYNC_LOG.md` + `CODE_NOTES.md` + a vitrine do DS publicado (seção de tokens semânticos).
