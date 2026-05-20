---
name: oimpresso-design
description: Use this skill to generate well-branded interfaces and assets for Oimpresso ERP, either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping the Cockpit ERP layout and all modules.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

## Quick start

1. **Read** `README.md` for full context
2. **Import** `resources/css/cockpit.css` — este é o CSS canônico completo do Cockpit
3. **Reference** `ui_kits/cockpit/index.html` — protótipo interativo de referência
4. **Follow** `memory/requisitos/_DesignSystem/SPEC.md` — regras R-DS-001 a R-DS-012

## Layout obrigatório

Toda tela operacional usa o Cockpit: `260px sidebar (dark) | 1fr main | 320px apps vinculados`.

```html
<div class="cockpit" data-theme="light" data-density="default">
  <aside class="sb">...</aside>
  <div class="main">
    <header class="topbar">...</header>
    <div class="main-body"><!-- conteúdo do módulo --></div>
  </div>
  <aside class="linked">...</aside>
</div>
```

## Tokens CSS essenciais

```css
/* Sempre usar — nunca cor hardcoded */
--bg, --bg-2, --surface, --border, --border-2
--text, --text-dim, --text-mute
--accent, --accent-2, --accent-soft
--font-sans, --font-mono
--row-h, --radius, --radius-sm, --radius-lg
```

## Origin badges (5 módulos canônicos)

```css
--origin-OS-bg/fg   /* amber  — Ordens de Serviço */
--origin-CRM-bg/fg  /* blue   — CRM/Clientes */
--origin-FIN-bg/fg  /* green  — Financeiro */
--origin-PNT-bg/fg  /* violet — Ponto/RH */
--origin-MFG-bg/fg  /* orange — Produção */
```

## Regras críticas

- PT-BR em todo label/copy/comentário
- Ícones: lucide-react exclusivamente
- Cores: sempre via tokens CSS (sem hardcode)
- localStorage com prefixo `oimpresso.*`
- Dark mode obrigatório em toda tela nova
- Fonte: IBM Plex Sans + IBM Plex Mono

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts *or* production code, depending on the need.
