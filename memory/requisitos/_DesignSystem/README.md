---
module: _DesignSystem
alias: design-system
status: ativo
migration_target: N/A
migration_priority: alta
risk: baixo
areas: [ui, tokens, componentes, acessibilidade]
last_generated: 2026-04-22
version: 0.1
---

# Design System (cross-cutting)

Decisões de UI que atravessam todos os módulos — tokens Tailwind 4, componentes shadcn/ui, iconografia lucide, dark mode, acessibilidade e convenções visuais.

## Propósito

Hoje cada módulo decide seu CSS isoladamente. Isso gera drift: `border-border` em um lugar, `border` em outro; `text-muted-foreground` vs `text-gray-500`; bolhas de chat vs cards genéricos. Este módulo virtual consolida as decisões visuais num só lugar, com ADRs rastreáveis.

**NÃO é um módulo Laravel** — não tem controller, rota nem migration. É pasta de documentação cross-cutting.

## Índice

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — stack visual (Tailwind 4, shadcn/ui, lucide, Inertia)
- **[SPEC.md](SPEC.md)** — regras que todo módulo deve seguir (tokens, espaçamento, dark mode)
- **[CHANGELOG.md](CHANGELOG.md)** — evolução do design system
- **[GLOSSARY.md](GLOSSARY.md)** — termos (token, variant, utility, primitive)
- **[adr/ui/](adr/ui/)** — decisões UI globais numeradas

## Módulos impactados

Todos que usam Inertia+React (atualmente 100%). Quando adicionar nova tela, consultar antes ADRs aqui.
