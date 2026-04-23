# ADR UI-0001 (_DesignSystem) · Tailwind 4 como fundação de CSS

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

Projeto começou com CSS tradicional (arquivos `.css` por página, Bootstrap 3 no legado Blade). Ao migrar pra React/Inertia, precisamos escolher abordagem de estilo. Três opções pesadas:

1. **CSS Modules**: acoplado, bundles crescem, não tem design tokens.
2. **CSS-in-JS (styled-components)**: runtime overhead, SSR complicado com Inertia.
3. **Tailwind**: utility-first, tree-shake agressivo, ecossistema shadcn.

## Decisão

Tailwind **v4** (release 2025) como ÚNICA fonte de estilo para código novo. Zero `.css` próprio exceto `resources/css/app.css` com `@theme` (tokens).

## Consequências

**Positivas:**
- Bundle final pequeno (Tailwind só inclui classes usadas).
- Design tokens via `@theme` — dark mode e rebranding sem refactor.
- shadcn/ui já pronto em Tailwind, copy-paste.
- Grep no código acha uso de cor/espaçamento imediatamente.

**Negativas:**
- `className` longo em JSX (mitigado com `cn()` e componentes shadcn).
- Aprendizado inicial pra quem veio de CSS puro.

## Alternativas consideradas

- **Tailwind v3**: rejeitado — v4 usa Oxide (PostCSS nativo, 10× mais rápido) e tokens via `@theme` direto (vs `tailwind.config.js`).
- **UnoCSS**: API similar, ecossistema menor — não justifica sair do padrão do Starter Kit oficial Laravel 12.
- **Bootstrap 5**: rejeitado — prescritivo demais, quebra consistência com shadcn.
