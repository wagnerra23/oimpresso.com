---
tela: caixa-unificada
tema: dark
veredito: rejeitado
responsavel: gate-automatico
status: pendente
---
# Caixa Unificada — dark mode com paleta bespoke `--omd-*`

- **detecção:** ao aplicar o handoff "n" em `prototipo-ui/prototipos/caixa-unificada/`, o `ds-guard`
  barrou `inbox-page.css` por **paleta inventada `--omd-*` (13 tokens)**. A baseline em `origin/main`
  tinha **0** ocorrências — regressão **nova** trazida pelo handoff, não dívida pré-existente.
- **motivo:** viola **L-02** (cor só por `.<tela>-scope{--accent}`, nunca paleta `--x-*` paralela ao DS)
  e contraria **[ADR 0281]** (dark ativa por `[data-theme="dark"]` flipando tokens canônicos —
  **sem cunhar token novo, sem paleta por-tela**).
- **padrão (o que o redesign deve seguir):**
  - **neutros** (`--omd-canvas/panel/raise/line`) → tokens canônicos que já flipam no dark:
    `var(--bg)`, `var(--surface)`, `var(--color-card)`, `var(--color-border)`.
  - **âmbar** (`--omd-amb*`) → `var(--color-warning)` / `--color-warning-soft` / `-fg`.
  - **verde da marca** (`--omd-sel*/me*/grn`, identidade da inbox/WhatsApp) → **accent scoped**:
    `.caixa-unificada-scope { --accent: <verde> }` + `var(--accent)` (única forma de cor por-tela
    permitida pelo L-02). NÃO usar o primary roxo 295 ([ADR 0190]) à força.
- **ação Cowork:** refazer o dark da Caixa no **data-theme bridge** ([ADR 0281]); zero `--omd-*`.
- **decisão Tier-0 pendente [W]:** harmonizar via Code (mesmo mapeamento acima, sob gate visual)
  **ou** devolver pro Cowork refazer na fonte. Registrado como `D-01` da Caixa quando a decisão sair.
