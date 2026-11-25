---
id: requisitos-design-system-adr-ui-0035-texto-shadcn-no-claro-usa-o-texto-do-shell
---

# ADR UI-0035 · No tema claro, o texto dos componentes shadcn usa o texto do shell

- **Status**: proposto
- **Data**: 2026-11-25
- **Ratificação**: merge deste PR por [W].
- **Origem**: item 7 de "Pendências do Cadastro de produto" (relatório trazido por [W], medido em
  produção em 24/11/2026, empresa WR2 Sistemas): no claro, título da página, títulos dos blocos,
  botões secundários e Cancelar saem em `oklch(0.137 0.036 258.5)`; o protótipo usa
  `oklch(0.22 0.01 80)`. O relatório não mediu outras telas.
- **Decisores**: Wagner (merge), Claude Code (medição + execução)
- **Categoria**: ui · fundações · shell
- **Refs**: [UI-0013](0013-constituicao-ui-v2-camadas.md) (camadas) ·
  [UI-0027](0027-dark-hue-240-supersede-0020-0022.md) (o escuro já alinhou o `@theme` ao shell) ·
  [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano na forma, cor incluída)

## Contexto

O app tem duas camadas de cor de texto, declaradas de propósito em `PIPELINE-TOKENS.md`: a do
Tailwind `@theme` (shadcn — `--color-foreground`, `--color-card-foreground`,
`--color-popover-foreground`) e a do shell `.cockpit` (`--text`). O shell e o protótipo escrevem
com `--text`. Os componentes shadcn (Card, Button outline/ghost, `h1` com `text-foreground`)
escrevem com os três do `@theme`.

No **escuro** as duas camadas já convergiram ([UI-0027](0027-dark-hue-240-supersede-0020-0022.md)).
No **claro** os três ficaram no default do shadcn, `oklch(0.137 0.036 258.5)` — o mesmo valor que a
UI-0027 descreve como *"nunca foi decisão de ninguém"*. Os outros textos do `@theme` claro já são
warm (`--color-muted-foreground` é `0.55 0.012 90`); só estes três sobraram.

### Medição (produção, 25/11/2026, tema claro, 1440px)

Blocos de texto visíveis dentro de `.main-body` pintados em `oklch(0.137 0.036 258.5)`, antes e
depois de aplicar a regra desta ADR na própria página (mesma sonda, mesmo DOM):

Como foi medido: empresa WR2 Sistemas, transições de CSS desligadas durante a leitura, regra
injetada como `<style>` na página carregada de produção. "Escuro igual" = snapshot da sonda
`design-diff --probe` e contagem por cor idênticos antes e depois, com `.cockpit[data-theme]` e
`html.dark` trocados no DOM.

| Rota (onde caiu) | textos visíveis | em azul antes | em azul depois | escuro igual |
|---|---:|---:|---:|:---:|
| `/products/create` | 43 | 17 | 0 | ✓ |
| `/products` | 988 | 5 | 0 | ✓ |
| `/home` | 154 | 16 | 0 | ✓ |
| `/sells` | 895 | 0 | 0 | ✓ |
| `/sells/create` | 83 | 16 | 0 | ✓ |
| `/cliente` | 730 | 135 | 0 | ✓ |
| `/purchases` | 81 | 23 | 0 | ✓ |
| `/financeiro/unificado` | 1511 | 511 | 0 | ✓ |
| `/ponto` | 68 | 14 | 0 | ✓ |
| `/business/settings` | 79 | 1 | 0 | ✓ |
| `/users` | 70 | 13 | 0 | ✓ |
| `/repair/job-sheet` | 19 | 3 | 0 | ✓ |
| `/asset/assets` | 49 | 2 | 0 | ✓ |
| `/ia` | 123 | 55 | 0 | ✓ |
| `/fluxos` | 151 | 0 | 0 | ✓ |

Três rotas da amostra não renderizaram o shell e ficaram fora (`/reports/profit-loss`,
`/whatsapp/inbox`, `/products/1/edit`). Resultado: **13 de 15** telas com shell tinham texto no
azul; em todas caiu para 0, e o escuro não mudou em nenhuma. É amostra, não censo: o
`@theme` shadcn tem 319 arquivos `.tsx` consumidores (contagem da UI-0027).

## Decisão

Dentro do shell no tema claro, os três tokens de texto do shadcn apontam para `--text`:

```css
.cockpit:not([data-theme="dark"]) {
  --color-foreground: var(--text);
  --color-card-foreground: var(--text);
  --color-popover-foreground: var(--text);
}
```

- **Nenhum token novo, nenhum `$value` alterado** no `semantic.tokens.json`. É ligação entre
  camadas, no mesmo sentido em que a UI-0027 fez convergir o escuro.
- **Só no claro.** No escuro os valores diferem de propósito (`--color-foreground`
  `0.965 0.004 240` × `--text` `0.94 0.005 90`, decisão da UI-0027) e não mudam.
- **Só dentro do `.cockpit`.** Site, login e conteúdo em portal fora do shell seguem com o valor do
  `@theme`. Portal que precisa de token monta dentro do `.cockpit` (lápide §5 2026-10-19).

## Fora do escopo (medido, não decidido aqui)

- **Fundo.** O fundo de página já é `--bg` (`0.985 0.003 90`) — vem do `.cockpit`. O branco que o
  relatório viu é o cabeçalho fixo da tela, que usa `bg-background/85`. `--color-background` é
  **superfície** (botão outline, diálogo, sheet, thumb do switch: 226 usos em 109 arquivos) e deve
  continuar branco como o `--surface` do protótipo; por isso ele não é remapeado. O cabeçalho fixo
  com `bg-background` aparece em 8 telas e é decisão por tela, não de token.
- `--color-secondary-foreground`, `--color-accent-foreground` e `--color-ring` ainda estão em
  `0.208 0.04 265.7` / `0.137 0.036 258.5` no claro. Não foram medidos nesta leva.

## Consequências

- Toda tela clara que usa Card/Button shadcn passa a escrever no texto do DS. É mudança de pixel
  em massa no claro: baselines visuais de telas claras podem precisar de regravação.
- O escuro não muda.
