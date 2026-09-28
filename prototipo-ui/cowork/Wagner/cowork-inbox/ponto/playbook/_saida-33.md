---
sessao: "33"
titulo: "ALVO ponto--espelho--index — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 33-alvo-espelho-lista.md
veredito: "entregue — alvo medido (4 seções, 0 ausentes), duas medidas byte-idênticas; seletores estruturais confirmados pelo mapa; destrava a 14."
---

# _saída 33 · ALVO do Espelho (lista)

Entregue no mesmo PR das threads 32 e 34.

## O que saiu

| arquivo | origem |
|---|---|
| `governance/design/targets/ponto--espelho--index.secoes.json` | a semente da thread, conferida com `alvo:mapa`. Nenhum seletor corrigido. |
| `governance/design/targets/ponto--espelho--index.alvo.json` | saída do `alvo:medir`, nunca editado à mão |
| linha na tabela "Alvos exportados" do `README.md` da pasta | — |

## Como foi medido

- Mesmo servidor, mesmo pré-requisito e mesmo `--preview-ds` (exit 0) da `_saida-32`.
- `npm run alvo:mapa -- http://127.0.0.1:5550/ --rota pt-espelho --raiz '.ponto-root .pt-body'` retornou **2 filhos div**. O `[1]` envolve a barra (Mês de referência · Escala · Buscar · Só com divergência). O `[2]` envolve a section "Colaboradores".
- Não há `Alert` condicional antes da barra no estado medido, então o `nth-child` pega o que a semente previa.
- `npm run alvo:medir -- http://127.0.0.1:5550/ --tela ponto--espelho--index --rota pt-espelho --secoes governance/design/targets/ponto--espelho--index.secoes.json --quieto-ms 2000`, rodado duas vezes. Os dois arquivos são **byte-idênticos**, sha256 `e4772d3b5c4f71ab…`.
- Resultado: 4 seções, 0 ausentes, `nos_totais` 707, viewport 1280×900, dark.

## Provas de efeito

- `secao-check --todos --servir-espelho`: `ponto--espelho--index — 4 seção(ões) conforme`.
- `pedido.mjs --tela Ponto/Espelho/Index --secao lista` sai **0**.

## Nota para a 14 (campo inexistente)

O protótipo mostra as colunas **Escala · Trabalhado · HE · Saldo BH · Controla ponto**. O `EspelhoController@index` entrega só `id · matricula · cpf · nome · email` (paginado 25). Essas 5 colunas são **`campo inexistente`** no `main`. Como a seção `lista` existe, elas não viram `_ausentes`, e a 14 não pode prometê-las sem backend.

## Sugestão da thread (não aplicada aqui)

O mapa confirmou as duas regiões. Os nomes `espelho-lista-filtros` e `espelho-lista-colaboradores` servem para o `contrato=` que o Cowork acrescentaria no protótipo, com a 17 gravando o par no vivo. Quando isso existir, a semente troca `nth-child` por `data-contract`, e o alvo é re-medido.

## PARAR SE

Nenhum disparou.
