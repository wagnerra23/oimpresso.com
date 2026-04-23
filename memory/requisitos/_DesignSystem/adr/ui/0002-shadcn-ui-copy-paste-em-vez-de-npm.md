# ADR UI-0002 (_DesignSystem) · shadcn/ui copy-paste em vez de npm package

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

Precisamos de primitivas acessíveis (Button, Dialog, Dropdown, Select, Popover, etc.) sem reinventar roda. Duas abordagens:

1. **Lib NPM** (Mantine, Chakra, Material UI, Ant Design): instalação simples, upgrades centralizados, custo: bundle grande + design opinativo difícil de mudar + dependência externa em cada upgrade Tailwind.
2. **shadcn/ui**: copia código fonte dos componentes pro teu repo (`Components/ui/`). Não é lib NPM. Você dono do código.

## Decisão

Adotar **shadcn/ui** com abordagem copy-paste. Primitivas vivem em `resources/js/Components/ui/`. Stock shadcn. Customização feita direto no arquivo copiado.

## Consequências

**Positivas:**
- Zero dependência NPM extra (Radix entra porque shadcn usa internamente).
- Customização direta no código — fork já aconteceu, é seu.
- Upgrade não-breaking: se shadcn lança v2 de Button, você escolhe migrar ou não.
- Acessibilidade Radix embutida (ARIA, foco, keyboard navigation).

**Negativas:**
- Upgrades manuais (você decide quando rebaixar `button.tsx` do shadcn).
- Spread de primitivas no repo — precisa disciplina pra manter consistência.

## Alternativas consideradas

- **Mantine**: rejeitado — design opinativo demais, difícil casar com Tailwind.
- **Chakra**: rejeitado — sistema de tokens paralelo ao Tailwind.
- **Headless UI (da Tailwind Labs)**: viável como complemento, mas cobertura menor que shadcn.
- **Buildar do zero sobre Radix**: rejeitado — shadcn já fez esse trabalho bem.
