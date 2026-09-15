# Pedido de patch — DS `TabBar`: matar a barra de rolagem visível

**Origem:** [W] em revisão da tela `Sistema · Arquivos` (`arquivos-page.jsx`, `<div data-contract="abas">`), 2026-08-26.
**Alvo:** `components/TabBar/TabBar.jsx` no DS (git SSOT). **Não** é patch de tela.
**Quem executa:** [CL] / mantenedor do DS. [CC] não escreve no git.

## Sintoma

A `<nav aria-label="Sub-navegação">` do `TabBar` mostra uma **barra de rolagem horizontal nativa** sempre que a soma das abas passa da largura do container. Aparece crua (estilo do SO), sem tratamento — destoa das outras áreas roláveis do shell, que já usam scrollbar fina tokenizada (`.topbar-tabs`, `.topnav`, `.sb-body` em `styles.css`).

## Causa (lida na fonte)

`TabBar.jsx` aplica `overflowX: 'auto'` **inline** na `<nav>` e não suprime nem estiliza a barra:

```js
style: {
  display: 'flex', alignItems: 'center', gap: 0,
  borderBottom: '1px solid var(--border)',
  overflowX: 'auto'          // ← sem scrollbar-width / ::-webkit-scrollbar
}
```

Como é estilo inline num componente sem classe, **nenhuma folha da app consegue** ajustar `overflow`; e `scrollbar-width` / `::-webkit-scrollbar` não existem em lugar nenhum pro componente. Por isso o defeito é do DS, não da tela.

## Abrangência (uma correção, todas as telas)

Mesmo componente, mesmo defeito, hoje em: **Arquivos** · **Patrimônio** (3 usos: sub-abas, alocações, drawer) · **Estoque** (ajustes, transferências) · **Repair** · **Relatórios** · **Documentação** · **Programa** · **Dash legacy**. Corrigir no `TabBar` resolve em todas de uma vez — é exatamente por isso que não deve virar CSS por tela.

## Patch pedido

Manter o scroll (é o comportamento certo pra muitas abas) e **esconder a barra**, no espírito das outras áreas roláveis do shell:

1. Dar uma **classe** à `<nav>` (ex.: `ds-tabbar`) além do estilo inline, pra a barra ficar endereçável por CSS.
2. Na `<nav>`: `scrollbarWidth: 'none'` + `msOverflowStyle: 'none'`, e no CSS do DS `.ds-tabbar::-webkit-scrollbar{display:none}`.
3. **Affordance no lugar da barra** (senão o overflow fica invisível): máscara de fade nas bordas quando há conteúdo cortado — `mask-image: linear-gradient(to right, transparent 0, #000 12px, #000 calc(100% - 12px), transparent 100%)` aplicada só quando `scrollWidth > clientWidth`.
4. Manter `scroll-behavior: smooth` e garantir que a aba ativa entre no viewport da nav ao mudar de aba **sem** `scrollIntoView` (usar `scrollLeft` calculado) — hoje uma aba ativa fora de vista fica escondida.
5. Preservar o que já é canon: underline-active em `--accent`, contadores mono, `aria-current="page"`, `whiteSpace: nowrap` nos botões, `borderBottom` de 1px em `--border` com `marginBottom: -1` nos botões.

**Sem regressão de a11y:** a nav continua rolável por teclado (Tab percorre os botões) e por trackpad/shift-scroll; esconder a barra não pode virar conteúdo inalcançável — daí o item 4 ser obrigatório junto do 2.

## Aceite

- Em `Arquivos` (Acervo/Retenção/Cofre/Trilha) a 1280px: sem barra nativa visível, fade à direita enquanto houver aba cortada.
- Em `Patrimônio` e `Estoque` (mais abas + contadores): mesmo comportamento, sem CSS local nas telas.
- Trocar de aba com uma aba fora de vista traz ela pro viewport da nav.
- Nenhuma tela precisou de regra CSS nova pra isso.

## Nota de processo

Espelho do DS neste projeto (`_ds/…/_ds_bundle.js`) é derivado — **não** patchear ele aqui (L-42). O conserto entra no DS no git e volta pelo push `git→design`.
