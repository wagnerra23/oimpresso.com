# ADR UI-0004 (_DesignSystem) · Dark mode por usuário via classe no <html>

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

Usuários trabalham em ambientes diferentes (escritório claro, chão de fábrica mal iluminado, oncall noturno). Tela só em light mode cansa. Três estratégias de dark mode:

1. **Auto via `prefers-color-scheme`**: tema do SO decide. Simples, mas não permite toggle manual.
2. **Classe `dark` no `<html>`**: Tailwind-friendly, permite toggle + persistência.
3. **CSS variables sem classes**: funciona, mas perde tree-shake do Tailwind.

## Decisão

**Classe `dark` no `<html>`** (estratégia Tailwind `darkMode: 'class'` — ou `darkMode: 'selector'` no v4).

Preferência persistida em `user_preferences.theme` (`light | dark | system`). Middleware Inertia injeta como prop. Hook `useTheme()` no front aplica/remove classe `dark` e salva via POST quando user toggleia.

## Consequências

**Positivas:**
- Usuário controla (não depende de SO).
- Consistência cross-device via DB.
- Tokens via `@theme` do Tailwind 4 já suportam `light-dark()`.
- Zero flash de conteúdo errado (SSR-friendly via cookie).

**Negativas:**
- Exige disciplina: cada nova tela testada em ambos os modos.
- Dev precisa lembrar de usar token semântico (nunca `text-gray-700` puro).

## Alternativas consideradas

- **Só `prefers-color-scheme`**: rejeitado — sem toggle.
- **Salvar em localStorage**: viável pra SPA pura; não é nosso caso (Inertia + multi-device).
- **Tema por módulo**: rejeitado — overkill.
