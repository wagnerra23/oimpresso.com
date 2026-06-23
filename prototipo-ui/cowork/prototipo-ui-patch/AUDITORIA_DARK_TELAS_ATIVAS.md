# Dark-readiness — Auditoria das telas ativas (protótipo Cowork)

> **[W] 2026-06-20:** "quero a auditoria tela por tela: o que aplicar de dark nas outras."
> **Método:** varredura das 23 folhas ativas (`oimpresso.com.html`) procurando **cor crua que não vira no dark** — superfície/borda com `white`/`#fff`/`oklch` claro **fora** de blocos `[data-theme="dark"]`. Removidos falso-positivos (`color:white` sobre fundo colorido = OK; tokens do `styles.css` que o `ds-v6/tokens.css` já sobrescreve no dark).
> **Regra de ouro:** dark é **automático** se a tela usa só tokens (`var(--surface/--bg/--text/--border/--accent)` e os `--*-soft`). Quebra só quando hardcoda cor clara. **Nunca** criar sistema paralelo tipo `--omd-*` (erro da Caixa).

---

## 🔴 TIER 1 — quebra a tela inteira no dark (prioridade)

### `oficina-os-page.css` — paleta crua, **zero** tratamento dark
Define a paleta inteira em hex/oklch claro e **não tem nenhum** `[data-theme="dark"]`:
`--ink:#1c1a17 · --paper:#f4f1ec · --card:#fffdf9 · --line:#e7e0d4 · --good-bg/warn-bg/bad-bg/info-bg` (todos claros).
→ No dark a tela continua **paper/clara inteira**.
**Fix:** trocar a paleta própria pelos tokens do ds-v6 — `--paper→var(--bg)`, `--card→var(--surface)`, `--ink→var(--text)`, `--line→var(--border)`, `*-bg→--*-soft`. Some o `--acc` próprio se for cor de marca → `var(--accent)`.

### `mockup-pages.css` — redefine `--surface:#fff` / `--bg` claro, **zero** dark
Sobrescreve os tokens de superfície pra valores claros no escopo `.mockup-page` e carrega **depois** do `tokens.css` → vence e força claro em qualquer tela que use `.mockup-page`. Também tem `.bub.them{background:white}`, `.wa-thread .msgs` claro.
**Fix:** **remover** os redefs `--surface/--bg` (deixar herdar do ds-v6) e trocar os `background:white`/oklch claro por `var(--surface)`/`--*-soft`. ⚠️ confirmar quais rotas ativas ainda usam `.mockup-page` (pode ser layer legado).

### `oficina-page.css` — superfícies `background: white`, zero dark
L28/610/674/687 = painéis/cards com `background: white` fixo, sem override.
**Fix:** `background: white` → `var(--surface)`; conferir bordas.

### `kb-page.css` — 55 fundos claros, só 12 blocos dark (cobertura parcial)
Linhas/cards/realces em `oklch(0.94–0.99 …)` em boa parte não cobertos.
**Fix:** passar superfícies pra `var(--surface)`/`var(--bg)` e realces pra `--accent-soft`; manter só os dark-overrides que sobrarem.

---

## 🟡 TIER 2 — "soft-chips" claros (legível, mas não combina com o dark)
Chips/pílulas de status com fundo pastel claro fixo. No dark ficam **claros sobre escuro** — texto continua legível (escuro sobre pastel), mas destoa. Fix uniforme: trocar `oklch(0.9x …)` pelos tokens `--pos-soft / --warn-soft / --neg-soft / --accent-soft` (já têm versão dark).

| Tela | Onde | nº |
|---|---|---|
| `crm-page.css` | `.crm-card.win/.bad`, `.crm-badge.ok/warn/bad` | ~9 |
| `prod-page-extras.css` | `.prod-stock-badge.*`, hover de linha da grade | ~11 |
| `cobranca-recorrente-page.css` | `.cr-pill.*`, `.cr-star:hover` | ~9 |
| `clientes-page.css` | `.cli-kpihero-tone-*-icon` (tints dos KPIs) | ~35 (todos icon-tint) |
| `forja-page.css` | `.fj-flag-*`, `.fj-fresco-*`, `.fj-pal-kind-*` | ~36 |
| `fin-boletos.css` | `.bol-funnel-step.active/.alert` | ~3 |
| `vendas.css` | `.vd-ai-*` cards/cta com `background: white` (painel IA) | ~13 |
| `equipe-page.css` | `.eq-list li.sel`, `.eq-msg:hover` | ~2 |
| `pg-styles.css` | `.bg-stone-100` shim, realces 295 | ~4 |

> Tier 2 é cosmético — dá pra fazer numa passada só (find-replace `oklch(claro)` → `--*-soft`). Não bloqueia nada.

---

## ✅ TIER 3 — já prontas pro dark (usam tokens)
`vendas-create-page.css` · `compras-page.css` · `oficina-fila.css` · `crm-ficha.css` · `prod-mec.css` · `chat-jana.css` · `financeiro.css` (1 chip verde trivial) · `styles.css` (tokens claros são sobrescritos pelo `ds-v6` + 33 blocos dark) · `inbox-page.css` (bespoke, mas 98 blocos dark + superfícies já apontadas pro padrão nesta semana).

---

## Recipe pro Code (produção React/Tailwind)
Mesma regra, vocabulário Tailwind:
- superfície → `bg-card` / `bg-background` (nunca `bg-white`/`bg-stone-50`)
- texto → `text-foreground` / `text-muted-foreground` (nunca `text-stone-900`)
- borda → `border-border`
- chip soft → `bg-*/10` + `text-*` semântico (success/warning/destructive), que já flipam
- acento → `text-primary` / `bg-primary`
- `[data-theme=dark]`/`dark:` só no caso raro que o token não cobre — derivando do token, **sem** paleta paralela.

## Ordem sugerida
1. **Tier 1** (3 telas) — ✅ FEITO no protótipo (2026-06-20): `oficina-os-page.css` (flip da paleta `.ofx`), `mockup-pages.css` (re-flip dos tokens), `oficina-page.css` (`background:white`→`var(--surface)`).
2. **Tier 2** — ✅ FEITO (2026-06-20): auto-flip dos soft-chips em 10 folhas (`crm +11`, `prod +11`, `cobranca +9`, `clientes +36`, `forja +29`, `kb +49`, `boletos +3`, `equipe +2`, `pg +4`, `vendas` superfícies brancas). Cada bloco está no fim do arquivo, marcado `DARK auto-flip dos soft-chips`. Light intacto; só selectores sem override dark prévio.
3. Tier 3 = nada a fazer.

> Espelho dos arquivos pro Code: `prototipo-ui-patch/prototipos/_dark-tier1/*` e `_dark-tier2/*`.
> **Receita p/ produção:** os blocos gerados são `[data-theme="dark"] .chip{ background: oklch(0.27 c h); color: oklch(0.84 c h); }` — em Tailwind isso vira `bg-*/10 text-*` semântico (success/warning/destructive), que já flipam sozinhos. Não portar o oklch cru; usar os utilitários.
