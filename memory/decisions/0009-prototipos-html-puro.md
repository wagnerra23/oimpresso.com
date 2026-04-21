# ADR 0009 — Protótipos visuais em HTML + Tailwind + Chart.js (não React)

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

Para validar visualmente telas antes de implementar em Blade/AdminLTE, precisamos de previews rápidos. Opções testadas:

1. **React + shadcn oficial via CDN + Babel inline** — tentado primeiro. **Falhou** silenciosamente, tela em branco, logs confusos
2. **HTML puro + Tailwind CDN + Chart.js CDN + Lucide CDN** — rende instantâneo, sem build

A Eliana foi explícita sobre economia: *"primeiro me ensine como funciona para eu não gastar credito"*. Gastar tempo configurando build Node para um preview que vai ser descartado é desperdício.

## Decisão

**Protótipos visuais em HTML auto-contido com Tailwind CDN + Chart.js CDN + Lucide icons CDN. Nada de React, nada de npm, nada de build step.**

Quando precisar interatividade mínima, usar JavaScript vanilla ou Alpine.js via CDN.

Arquivos de protótipo ficam em `outputs/` (temporários, fora do repo). Quando validados, a UI vai para `Resources/views/` em Blade/AdminLTE — que é o stack real de produção (ADR: stack UltimatePOS é Blade + AdminLTE + jQuery, não React).

## Consequências

### Positivas

- Preview instantâneo — double-click no arquivo, abre no navegador
- Zero dependência de build. Funciona em qualquer máquina
- Foco no design, não em ferramenta
- Chart.js cobre 95% dos gráficos que precisamos

### Negativas

- Estilo do protótipo (Tailwind/shadcn) não é o estilo da produção (AdminLTE/Bootstrap 4). Há tradução a fazer quando for implementar
- Interatividade complexa exigiria JS vanilla verboso

### Warning aceito

O Tailwind CDN emite um `console.warn` dizendo "não use em produção". Isso é esperado e ignorado — protótipos não são produção.

### Porta aberta

Se no futuro migrarmos UI de produção para Tailwind + Vue/Livewire (Fase 5+), o stack de protótipo pode virar o stack de produção.
