# ADR 0401 — Cores cruas da referência de produção na Consulta de Produtos

**Data:** 2026-08-19 · **Status:** proposto (aguarda decisão) · **Camada:** 1 · Fundações

## Contexto

O produto pediu que a faixa de abas, o cabeçalho da tabela, o rodapé de paginação e a linha
selecionada da Consulta de Produtos reproduzissem **exatamente** as cores da Consulta de Contatos
em produção, informadas em RGB:

| Elemento | Valor pedido | Token equivalente hoje |
|---|---|---|
| Aba ativa (fundo) | `rgb(231, 248, 253)` | nenhum — o accent do DS é roxo hue 295 |
| Aba inativa (hover) | `rgb(241, 245, 249)` | `--bg-2` (aproximado, hue quente) |
| Badge de contagem (aba inativa) | `rgb(46, 52, 55)` | nenhum — o DS usa `--bg-2` claro |
| Cabeçalho da tabela e rodapé | `rgb(250, 249, 248)` | `--bg-2` (aproximado) |
| Linha selecionada | `rgb(248, 247, 252)` | `--accent-soft` (mais saturado) |

Duas divergências de sistema ficam evidentes:

1. O azul/ciano da aba ativa em produção **não existe** na paleta do DS, cujo acento canônico é
   roxo hue 295 (ADR 0190).
2. O badge de contagem em produção é **escuro sobre fundo claro**; o `TabBar` do DS usa badge
   claro (`--bg-2`) com texto `--text-dim`.

Nenhum desses cinco valores tem par declarado para tema escuro. Como são superfícies claras
literais, no escuro o texto que carregam inverte o contraste.

## Decisão

**Nesta tela (aplicado):** os cinco valores foram escritos literalmente, por pedido explícito do
produto e por fidelidade à referência de produção. As abas e o menu foram reimplementados
localmente (exceções AP2 nº1 e nº3) porque o `TabBar` e o `DropdownMenu` do DS não expõem esses
estados.

**Nas fundações (proposto):** promover os cinco valores a tokens de superfície com par claro/escuro
— `--tab-active-soft`, `--tab-hover-soft`, `--badge-count-bg`/`-fg`, `--table-head-bg`,
`--row-selected-bg` — em `01-fundacoes/css/`, e decidir se o azul/ciano de aba ativa é um desvio
de produção a corrigir ou um segundo acento legítimo do sistema.

## Consequências

- **Se aprovado:** a tela troca cinco literais por `var(--*)`, AP1 volta a passar, e o tema escuro
  fica possível sem reescrever a tela. O `TabBar` do DS pode então absorver os estados e as
  exceções AP2 nº1 e nº3 morrem.
- **Se não aprovado:** as cinco cores permanecem cruas; o tema escuro fica bloqueado nesta tela e
  qualquer outra listagem que copie o padrão duplica os literais. **Três telas com o mesmo literal
  é a evidência de que a correção pertence ao DS, não às telas.**
- **Impacto visual:** nenhum, se os tokens receberem exatamente estes valores no tema claro.

## Referências

- ADR 0190 — acento roxo hue 295 como primário.
- ADR 0322 — precedente de correção de contraste de token do DS em camada 1.
- `design/LAUDO-conferencia-consulta-produtos.md` §3, achado [ALTA].
