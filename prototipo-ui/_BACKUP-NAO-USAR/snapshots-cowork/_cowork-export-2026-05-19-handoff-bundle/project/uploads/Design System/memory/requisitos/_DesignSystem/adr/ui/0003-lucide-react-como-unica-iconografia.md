# ADR UI-0003 (_DesignSystem) · lucide-react como única iconografia

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

Projeto legado usou Font Awesome (via Bootstrap 3). Telas React iniciais misturaram `@heroicons/react` + `lucide-react` + SVGs inline. Inconsistência de traço, tamanhos irregulares, bundle duplicado.

## Decisão

**Uma única lib de ícones para todo código React novo: `lucide-react`**.

Regras:
- Importação: `import { Icon } from 'lucide-react';`
- Tamanho padrão: `size={14}` inline em botões, `size={16}` em cards, `size={20-24}` em headers.
- Nunca `<i className="fa fa-...">` em componente React.
- Legado Blade mantém Font Awesome — não migrar proativamente.

## Consequências

**Positivas:**
- Traço consistente (2px stroke em todos).
- Tree-shakeable (só ícone usado entra no bundle).
- Acessibilidade: `aria-hidden` automático quando decorativo.
- 1200+ ícones cobrem 99% dos casos.

**Negativas:**
- Migração legado não vale o esforço agora — convive por enquanto.
- Ícones custom precisam ser SVG inline em componente (ok, raros).

## Alternativas consideradas

- **Heroicons**: cobertura 60% do lucide, traço inconsistente entre outline/solid.
- **React Icons (agregador)**: traz 10 libs, bundle monstro mesmo com tree-shake.
- **Phosphor Icons**: bonito mas menos coberto; requer plano pago pra algumas variantes.
- **SVG inline por ícone**: ingerenciável em escala.
