---
id: requisitos-design-system-adr-ui-0036-hover-e-foco-do-shadcn-no-claro-seguem-o-shell
---

# ADR UI-0036 · No tema claro, hover e anel de foco do shadcn seguem o shell

- **Status**: proposto
- **Data**: 2026-11-25
- **Ratificação**: merge deste PR por [W].
- **Decisão de**: [W] 2026-11-25, textual: *"corrige também os 3 tokens azuis que sobraram no claro"*.
- **Decisores**: Wagner (pedido + merge), Claude Code (medição + execução)
- **Categoria**: ui · fundações · shell
- **Estende**: [UI-0035](0035-texto-shadcn-no-claro-usa-o-texto-do-shell.md), que deixou estes três
  tokens declarados como fora do escopo. Não supersede nada.
- **Refs**: [UI-0027](0027-dark-hue-240-supersede-0020-0022.md) (escuro) ·
  [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano na forma)

## Contexto

Depois da UI-0035, três tokens do `@theme` shadcn seguiam no azul do default no claro:

| token | valor no claro | onde pinta |
|---|---|---|
| `--color-accent-foreground` | `oklch(0.208 0.04 265.7)` | texto no hover de botão outline/ghost, item de menu e de select |
| `--color-secondary-foreground` | `oklch(0.208 0.04 265.7)` | texto do botão e do badge `secondary` |
| `--color-ring` | `oklch(0.137 0.036 258.5)` | anel de foco (`ring-ring/50`) e borda de campo focado (`border-ring`) |

O design system escreve com `--text` e desenha o foco de teclado do shell com o roxo de marca
(`colors_and_type.css`, bloco *UNIVERSAL KEYBOARD FOCUS*: `.cockpit … :focus-visible { outline-color:
var(--accent) }`; campos de formulário pintam halo de accent).

## Decisão

Na mesma regra da UI-0035, em `resources/css/cockpit.css`:

```css
.cockpit:not([data-theme="dark"]) {
  /* … os três da UI-0035 … */
  --color-secondary-foreground: var(--text);
  --color-accent-foreground: var(--text);
  --color-ring: var(--color-primary);
}
```

- O anel aponta para `--color-primary` e não para `--accent`: no claro os dois valem
  `oklch(0.55 0.15 295)`, e `--color-primary` não é reescrito em runtime (ver comentário do
  `.cockpit` em `cockpit.css`).
- Só no claro e só dentro do `.cockpit`, como na UI-0035. Nenhum token novo, nenhum `$value` muda.

## Medição (produção, 25/11/2026, WR2 Sistemas)

Linha de base = página com a regra da UI-0035 já aplicada, para isolar só esta mudança. Mesmas 12 telas
(`/products/create`, `/products`, `/home`, `/sells/create`, `/cliente`, `/purchases`, `/financeiro`,
`/ponto`, `/users`, `/ia`, `/fluxos`, `/business/settings`):

- Em repouso, **nenhum** texto visível usa `secondary-foreground` ou `accent-foreground` em nenhuma
  das 12 — o efeito aparece no hover e no foco, não no carregamento.
- Na lista de produtos, **81** elementos têm hover por `accent-foreground` e **162** usam o anel
  (`ring-ring`/`border-ring`).
- Valores resolvidos no `.cockpit`, claro: `accent/secondary-foreground` `0.208 0.04 265.7` →
  `0.22 0.01 80`; `ring` `0.137 0.036 258.5` → `0.55 0.15 295`.
- Escuro: valores resolvidos e contagem de textos por cor idênticos antes e depois nas 12 telas
  (uma rodada em `/products` saiu diferente por a lista ter carregado entre as leituras; repetida
  com controle, saiu idêntica).

## Fora do escopo

`--color-secondary` e `--color-accent` (fundos de botão secundário e de hover, `0.968 0.007 247.9`) e
`--color-primary-foreground` (`0.984 0.003 247.9`) ainda têm leve matiz azul no claro. Não medidos.

## Consequências

Foco de teclado e borda de campo focado passam a roxo no claro, como no protótipo. O escuro não muda.
