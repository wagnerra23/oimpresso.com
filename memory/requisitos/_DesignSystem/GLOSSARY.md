# Glossário · Design System

## cn()
Helper do shadcn em `resources/js/lib/utils.ts`. Concatena classes Tailwind lidando com conflitos (via `tailwind-merge`). Exemplo: `cn('px-4', props.className)`.

## Primitive
Componente shadcn/ui em `Components/ui/`. Agnóstico de domínio (Button, Card, Dialog). Nunca importa nada do negócio.

## Shared component
Componente em `Components/shared/`. Específico do produto mas reusável entre módulos (AppShell, Kpi, Breadcrumb).

## Token semântico
Nome de cor/espaço ligado a função, não a valor. Ex.: `primary`, `muted-foreground`, `border` (vs tokens cruos `blue-500`, `gray-600`).

## Utility class
Classe Tailwind single-purpose: `flex`, `text-sm`, `gap-2`. Oposto de classe componente (`.btn-primary`).

## Variant
Variação visual de um componente. Ex.: Button tem `default | outline | destructive | secondary | ghost | link`.

## CVA
`class-variance-authority` — lib usada pelo shadcn pra tipar variants. Retorna função que mapeia props em classes.

## oklch
Espaço de cor percepto-uniforme usado pelos tokens do Tailwind 4 (substitui HSL). Dá consistência de brilho entre cores.

## Dark mode class strategy
Abordagem do Tailwind: classe `dark` no `<html>`. Diferente de `media` (prefers-color-scheme) — permite toggle manual.

## Focus ring
Contorno visível em elementos com foco de teclado. Padrão shadcn: `focus-visible:ring-2 ring-ring ring-offset-2`.

## Radix
Biblioteca de primitivas acessíveis (sem estilo) que shadcn/ui usa por baixo pra Dialog, Popover, Dropdown, etc.
