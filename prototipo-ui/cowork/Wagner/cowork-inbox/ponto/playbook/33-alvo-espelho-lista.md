<!-- SESSÃO FRIA · abra esta thread sozinha. Prompt de abertura: `_SESSAO-FRIA.md` (linha "ALVO"). Não leia as outras threads. -->

# 33 · ALVO `ponto--espelho--index` — trava a 14

> Mesma razão da 32. Slug = `Ponto/Espelho/Index`.
> **Esta tela não tem `data-contract`** nem no protótipo (`EspelhoLista` sem `contrato=`) nem no vivo (`Espelho/Index.tsx`: 0). A semente é estrutural — o `alvo:mapa` é obrigatório.
> **Achado que muda a 14:** o protótipo mostra Escala · Trabalhado · HE · Saldo BH · Controla ponto; o `EspelhoController@index` entrega só `id · matricula · cpf · nome · email` (paginado 25, `:41-58`). Essas 5 colunas são **campo inexistente** no `main`.

## Entrega (1 PR · 2 arquivos + linha no README)
`ponto--espelho--index.secoes.json` (conferido) · `ponto--espelho--index.alvo.json` (medido).

## Comando
```bash
npm run alvo:mapa  -- http://127.0.0.1:5550/ --rota pt-espelho
npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--espelho--index --rota pt-espelho \
  --secoes governance/design/targets/ponto--espelho--index.secoes.json --quieto-ms 2000
```

## Semente
```json
{
  "_": "semente [CC] 2026-09-28 — tela sem data-contract; seletores estruturais a confirmar pelo alvo:mapa. dado lido em EspelhoController.php@main 0c23a1349c08.",
  "header": { "seletor": ".ponto-root > .cli-ph > header", "dado": "shell — PageHeader" },
  "tabs":   { "seletor": ".ponto-root > nav.ds-tabbar", "dado": "shell — PontoSubNav (ADR 0182)" },
  "barra":  { "seletor": ".ponto-root .pt-body > div:nth-child(1)", "dado": "mes (eager, query ?mes=Y-m). Filtros Escala / Só com divergência: sem parâmetro no controller" },
  "lista":  { "seletor": ".ponto-root .pt-body > div:nth-child(2)", "dado": "EspelhoController::buildColaboradoresPagina — Colaborador controla_ponto, sem desligamento, paginate(25): id · matricula · cpf · nome · email" },
  "_ausentes": {}
}
```
As colunas sem backend **não** viram seção ausente aqui (a seção `lista` existe); elas entram como nota na 14 e no `_saida-33`: "Escala · Trabalhado · HE · Saldo BH · Controla ponto = `campo inexistente` em `EspelhoController@index`".
**Sugestão, não pedido:** se o mapa confirmar as duas regiões, nomear `espelho-lista-filtros` e `espelho-lista-colaboradores` — eu acrescento o `contrato=` no protótipo e a 17 grava o par no vivo.

## PARAR SE
- `header` ausente → o build de 28/09 (`.cli-ph`) ainda não desceu. Importe primeiro.
- `nth-child` pegar outra coisa (ex.: `Alert` condicional antes da barra) → medir com o que o mapa devolveu e declarar.

## Prova
`ponto--espelho--index.alvo.json` com `secoes`. Fechar com `_saida-33.md`.
