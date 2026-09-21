# ADR 0402 — `Pagination` do DS não cobre primeira/última página nem indicador "N / M"

**Data:** 2026-08-19 · **Status:** proposto (aguarda decisão) · **Camada:** 0 · Design System

## Contexto

O padrão de paginação em produção (Consulta de Contatos) é: meta à esquerda ("Mostrando 1–50 de
13.433"), e à direita rótulo "Por página" + seletor, **primeira**, **anterior**, indicador
`1 / 269`, **próxima**, **última**.

O `Pagination` do DS oferece: lista de números com elipse, botões rotulados "Anterior"/"Próximo",
meta "N–M de T" e `pageSize`/`onPageSize`. Faltam:

| Requisito da referência | `Pagination` do DS |
|---|---|
| Botão primeira página | ausente |
| Botão última página | ausente |
| Indicador `página / total` | ausente (usa números clicáveis) |
| Navegação só por ícone | usa chevron + palavra |

Com 269 páginas, a lista de números do DS ocupa a faixa inteira e não oferece o salto para a
última — exatamente a ação que o operador usa para conferir o fim do catálogo.

## Decisão

**Nesta tela (aplicado):** rodapé de paginação próprio, com os controles da referência
(exceção AP2 nº2). Reusa os tokens do DS; nenhuma cor nova além das já registradas na ADR 0401.

**No DS (proposto):** acrescentar ao `Pagination` as variantes `edges` (primeira/última) e
`indicator="fraction"` (`N / M` em lugar da lista de números), mantendo o comportamento atual como
padrão. Duas telas já precisaram do mesmo desvio (Contatos em produção e Produtos).

## Consequências

- **Se aprovado:** a exceção AP2 nº2 morre e o rodapé desta tela passa a ser uma composição do DS.
- **Se não aprovado:** cada listagem reimplementa navegação, estados desabilitados e acessibilidade
  do rodapé — comportamento que o DS já garantia.

## Referências

- `_ds/office-impresso-atual-d7f88676-…/_ds_bundle.js` — `Pagination`.
- `design/CHECKLIST-15D-consulta-produtos.md` Anexo A, AP2.
