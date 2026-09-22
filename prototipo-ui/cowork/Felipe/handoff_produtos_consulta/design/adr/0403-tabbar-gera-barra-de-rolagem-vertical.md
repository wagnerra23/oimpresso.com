# ADR 0403 — `TabBar` do DS gera barra de rolagem vertical de 1px

**Data:** 2026-08-19 · **Status:** proposto (aguarda decisão) · **Camada:** 1 · Fundações

## Contexto

O `TabBar` do design system declara `overflowX: 'auto'` no `<nav>`. O navegador não permite um eixo
`visible` quando o outro é `auto`: `overflow-y` é promovido a `auto`. Os botões da aba têm
`height: 36` com `marginBottom: -1`, então o conteúdo mede 36px numa caixa de 35px.

Medido em `nav[aria-label="Sub-navegação"]` na Consulta de Produtos:
`{ overflowX: "auto", overflowY: "auto", scrollHeight: 36, clientHeight: 35 }` — 1px de overflow
suficiente para o navegador desenhar uma barra de rolagem vertical com setas à direita da última
aba. O usuário já havia reportado essa barra quando as abas eram locais.

## Decisão

**Nesta tela (aplicado):** uma regra no topo do arquivo, mirando atributo semântico —
`nav[aria-label="Sub-navegação"] { overflow-y: hidden; }`. Não resolve por wrapper: a barra é
interna ao `<nav>`, um pai com `overflow: hidden` não a remove.

**No DS (proposto):** declarar `overflowY: 'hidden'` no `<nav>` do `TabBar`, ou remover o
`marginBottom: -1` compensando com `paddingBottom` no contêiner.

## Consequências

- **Se aprovado:** a regra local deixa de existir; qualquer tela que use `TabBar` fica limpa.
- **Se não aprovado:** cada tela com `TabBar` precisa repetir o override. Três repetições são
  evidência de que a correção pertence ao DS, não às telas.
- **Impacto visual:** nenhum — o 1px oculto é a compensação do `marginBottom: -1`, que existe para
  a borda ativa cobrir a borda do `<nav>`.

## Referências

- `_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js` — `TabBar`. (O caminho citado na medição original, `_ds/office-impresso-atual-d7f88676-…`, foi apagado em 21/09/2026.)
- `design/LAUDO-conferencia-consulta-produtos.md` — achado de regressão.
