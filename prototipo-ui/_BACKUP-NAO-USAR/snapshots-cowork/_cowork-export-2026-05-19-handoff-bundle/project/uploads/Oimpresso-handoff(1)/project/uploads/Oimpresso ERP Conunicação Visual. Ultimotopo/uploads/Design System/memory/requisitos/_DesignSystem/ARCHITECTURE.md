# Arquitetura · Design System

## Stack visual

| Camada | Tech | Versão | Papel |
|---|---|---|---|
| CSS utility | **Tailwind 4** | 4.x | Fundação (zero CSS próprio em 95% dos casos) |
| Component library | **shadcn/ui** | última | Primitivas acessíveis copy-paste, não NPM |
| Ícones | **lucide-react** | 0.x | Iconografia única pra todo sistema |
| Animações | Tailwind transitions + CSS | — | Sem Framer Motion |
| Forms | React Hook Form (quando complexo) + useForm Inertia | — | |
| Tabela de dados | HTML `<table>` + Tailwind | — | Sem TanStack Table |
| Chart | Canvas custom quando simples, sem lib | — | Se precisar de lib: Recharts |

## Estrutura de arquivos

```
resources/js/
├── Components/ui/         ← primitivas shadcn copy-paste (button, card, badge, etc.)
├── Components/shared/     ← componentes específicos do produto (AppShell, Kpi, etc.)
├── Layouts/               ← layouts Inertia (AppShell, GuestShell)
├── Pages/{Modulo}/        ← telas por módulo
└── lib/utils.ts           ← cn() helper do shadcn
```

## Camadas de decisão

### Tokens (nível CSS global)
Vivem em `resources/css/app.css` via `@theme` do Tailwind 4. Cores, espaçamentos, raios, font-family.

### Variants (nível componente)
shadcn-style: `cva()` com `variant` (default, outline, destructive, secondary) + `size` (sm, default, lg, icon).

### Compositions (nível tela)
Montagem de primitivas — ex.: `<Card><CardHeader><CardTitle /></CardHeader><CardContent /></Card>`.

## Dark mode

- Baseado em classe `dark` no `<html>`.
- Preferência por usuário salva em `user_preferences.theme` (`light` | `dark` | `system`).
- Tokens vêm via `oklch()` pra consistência perceptual entre modos.

## Acessibilidade (AA baseline)

- Contraste mínimo 4.5:1 em texto normal, 3:1 em texto grande.
- Todos elementos interativos têm `:focus-visible` outline.
- Inputs de formulário têm `<label>` associado (explícito ou via `aria-label`).
- Atalhos: `/` abre busca, `Esc` fecha modal/dropdown (padrão shadcn).

## Convenções de nomenclatura CSS

- Utility-first: prefira `className="flex items-center gap-2"` a criar classes custom.
- Quando preciso de classe custom: kebab-case + prefixo `doc-` (ex.: `.doc-chart-bar`).
- NÃO usar `!important` (Tailwind tem `!` prefix se precisar em último caso).
