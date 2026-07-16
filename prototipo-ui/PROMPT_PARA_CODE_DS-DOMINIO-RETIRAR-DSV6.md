# PONTE → CODE · Domínio pro DS (retirar o ds-v6 congelado)

**Contexto:** o protótipo Cowork foi religado ao DS VIVO (`_ds/…/colors_and_type.css`, <html class="cockpit">). O `ds-v6/tokens.css` virou adaptador; a ÚNICA coisa que impede deletá-lo são estes **56 tokens de domínio** que só existem nele e o app usa — o DS não os tem nesta forma.

**Ação [CL] (Tier 0, [W] aprova):** adicionar ao token JSON do DS v6 (`resources/css/tokens/*.tokens.json`, Style Dictionary) com light `$value` + dark `com.oimpresso.dark`, recompilar, re-espelhar. Muitos já têm primo no DS (`--color-sla-*`, `--color-canal-*(-soft)`, `--color-kpi-feature-*`, `--kind-*`, `--vip`) → aliasar em vez de duplicar; só os que faltam (origins, stage, sla -dot/-line, canal -bg, kind -soft) entram como novos. Depois disso o `ds-v6/tokens.css` colapsa num shim de apelidos e pode ser deletado.

| token | light | dark |
|---|---|---|
| `--canal-email-bg` | `oklch(0.85 0.07 280)` | `oklch(0.40 0.10 280)` |
| `--canal-email-fg` | `oklch(0.20 0.10 280)` | `oklch(0.92 0.06 280)` |
| `--canal-email-tint` | `oklch(0.97 0.012 280)` | `oklch(0.26 0.04 280)` |
| `--canal-fb-bg` | `oklch(0.85 0.07 250)` | `oklch(0.40 0.10 250)` |
| `--canal-fb-fg` | `oklch(0.20 0.10 250)` | `oklch(0.92 0.06 250)` |
| `--canal-fb-tint` | `oklch(0.97 0.012 250)` | `oklch(0.26 0.04 250)` |
| `--canal-ig-bg` | `oklch(0.85 0.08 30)` | `oklch(0.42 0.11 30)` |
| `--canal-ig-fg` | `oklch(0.22 0.10 30)` | `oklch(0.92 0.07 30)` |
| `--canal-ig-tint` | `oklch(0.97 0.012 30)` | `oklch(0.26 0.04 30)` |
| `--canal-ml-bg` | `oklch(0.85 0.10 95)` | `oklch(0.42 0.12 95)` |
| `--canal-ml-fg` | `oklch(0.22 0.10 95)` | `oklch(0.92 0.08 95)` |
| `--canal-ml-tint` | `oklch(0.97 0.012 95)` | `oklch(0.26 0.05 95)` |
| `--kind-customer-soft` | `oklch(0.94 0.04 145)` | `oklch(0.275 0.05 145)` |
| `--kind-employee-soft` | `oklch(0.94 0.03 250)` | `oklch(0.275 0.05 250)` |
| `--kind-representative-soft` | `oklch(0.93 0.06 290)` | `oklch(0.275 0.06 290)` |
| `--kind-supplier-soft` | `oklch(0.94 0.05 60)` | `oklch(0.275 0.05 60)` |
| `--kpi-feature-bg` | `oklch(0.21 0.03 264)` | `oklch(0.238 0.02 264)` |
| `--kpi-feature-bg-hi` | `oklch(0.23 0.03 264)` | `oklch(0.27 0.025 264)` |
| `--kpi-feature-fg` | `oklch(0.98 0 0)` | `oklch(0.98 0 0)` |
| `--kpi-feature-fg-2` | `oklch(0.72 0.02 250)` | `oklch(0.70 0.02 250)` |
| `--kpi-feature-line` | `oklch(0.31 0.04 264)` | `oklch(0.34 0.03 264)` |
| `--origin-CRM-bg` | `oklch(0.93 0.07 245)` | `oklch(0.32 0.08 250)` |
| `--origin-CRM-fg` | `oklch(0.50 0.13 250)` | `oklch(0.83 0.12 250)` |
| `--origin-FIN-bg` | `oklch(0.93 0.08 150)` | `oklch(0.32 0.08 150)` |
| `--origin-FIN-fg` | `oklch(0.47 0.13 150)` | `oklch(0.84 0.14 150)` |
| `--origin-MFG-bg` | `oklch(0.93 0.06 30)` | `oklch(0.34 0.07 30)` |
| `--origin-MFG-fg` | `oklch(0.50 0.12 30)` | `oklch(0.85 0.12 30)` |
| `--origin-OS-bg` | `oklch(0.93 0.08 70)` | `oklch(0.34 0.08 70)` |
| `--origin-OS-fg` | `oklch(0.50 0.13 60)` | `oklch(0.86 0.12 75)` |
| `--origin-PNT-bg` | `oklch(0.93 0.07 295)` | `oklch(0.33 0.08 295)` |
| `--origin-PNT-fg` | `oklch(0.50 0.13 295)` | `oklch(0.85 0.13 295)` |
| `--sla-aging` | `oklch(0.42 0.13 60)` | `oklch(0.84 0.13 60)` |
| `--sla-aging-dot` | `oklch(0.62 0.13 60)` | `oklch(0.72 0.14 60)` |
| `--sla-aging-line` | `oklch(0.80 0.10 60)` | `oklch(0.42 0.08 60)` |
| `--sla-aging-soft` | `oklch(0.96 0.05 60)` | `oklch(0.275 0.05 60)` |
| `--sla-expired` | `oklch(0.46 0.18 25)` | `oklch(0.84 0.16 25)` |
| `--sla-expired-dot` | `oklch(0.50 0.18 25)` | `oklch(0.70 0.18 25)` |
| `--sla-expired-line` | `oklch(0.80 0.12 25)` | `oklch(0.45 0.10 25)` |
| `--sla-expired-soft` | `oklch(0.94 0.06 25)` | `oklch(0.30 0.06 25)` |
| `--sla-fresh` | `oklch(0.36 0.13 145)` | `oklch(0.84 0.13 145)` |
| `--sla-fresh-dot` | `oklch(0.52 0.13 145)` | `oklch(0.70 0.14 145)` |
| `--sla-fresh-line` | `oklch(0.80 0.10 145)` | `oklch(0.42 0.08 145)` |
| `--sla-fresh-soft` | `oklch(0.95 0.05 145)` | `oklch(0.275 0.05 145)` |
| `--sla-late` | `oklch(0.45 0.16 30)` | `oklch(0.84 0.16 30)` |
| `--sla-late-dot` | `oklch(0.62 0.18 30)` | `oklch(0.72 0.18 30)` |
| `--sla-late-line` | `oklch(0.80 0.12 30)` | `oklch(0.45 0.10 30)` |
| `--sla-late-soft` | `oklch(0.94 0.07 30)` | `oklch(0.30 0.07 30)` |
| `--sla-paid` | `var(--text-3)` | `(herda light)` |
| `--sla-paid-soft` | `var(--sunken)` | `(herda light)` |
| `--stage-emerald` | `oklch(0.60 0.13 155)` | `oklch(0.68 0.14 155)` |
| `--stage-green` | `oklch(0.66 0.15 145)` | `oklch(0.74 0.15 145)` |
| `--stage-indigo` | `oklch(0.55 0.16 270)` | `oklch(0.66 0.16 270)` |
| `--stage-rose` | `oklch(0.62 0.16 20)` | `oklch(0.68 0.16 20)` |
| `--stage-slate` | `oklch(0.58 0.025 250)` | `oklch(0.66 0.03 250)` |
| `--vip` | `oklch(0.42 0.13 75)` | `oklch(0.84 0.13 75)` |
| `--vip-soft` | `oklch(0.92 0.07 80)` | `oklch(0.275 0.07 80)` |
