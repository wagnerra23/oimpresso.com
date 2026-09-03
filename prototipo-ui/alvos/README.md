# `prototipo-ui/alvos/` — o ALVO medido de cada seção

Saída de `scripts/design-sync/alvo.mjs --alvo` (PR-A1 do protocolo de export). Um arquivo
`<tela>.alvo.json` por tela: o que a seção **tem de ter**, medido no DOM do espelho servido —
nós · filhos · ordem das classes · computed style · truncamento · retângulo.

É **fonte de teste**, não retrato: existe para o `secao-check` (PR-A3) comparar contra o render
e reprovar nomeando o ausente. Não é documentação e não se edita à mão — se o número está
errado, re-rode o comando.

## Por que NÃO fica em `prototipo-ui/contrato/`

Aquela pasta tem outro dono e outro vocabulário:

| | `contrato/` | `alvos/` (aqui) |
|---|---|---|
| Dono | `contract.schema.json` + `scripts/contrato-de-tela.mjs` | `scripts/design-sync/alvo.mjs` |
| Como mede | **estático** — copy literal + âncora `data-contract` no `.tsx`, sem render | **runtime** — DOM medido no browser |
| Chave `alvo` significa | "dirs/arquivos de produção checados" | (não existe — o arquivo inteiro é o alvo) |

Gravar `<tela>.alvo.json` lá colidiria de pasta **e** de vocabulário: `alvo` já quer dizer outra
coisa naquele schema. O gate estático continua sendo o dono da fidelidade de copy/ordem no fonte
(ADR 0290 derrubou o render pareado em CI; o v1 estático é o que sobreviveu) — este diretório é a
camada de runtime que ele deliberadamente não cobre.

## Comandos

```bash
npm run alvo:mapa -- <url> --raiz <seletor>          # explora: stdout only, nunca grava
npm run alvo:medir -- <url> --tela <slug> --secoes <arq.json>
npm run alvo:selftest                                 # parte pura (sem browser)
npm run alvo:selftest:browser                         # bite-test real: 2 runs idênticos + injeção muda
```

O `--mapa` **não grava de propósito** — mapa é comando, não arquivo ([ADR 0256](../../memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md):
derivado sobrevive, escrito apodrece).

## Determinismo

Duas execuções seguidas produzem bytes idênticos (chaves ordenadas, geometria arredondada, zero
timestamp) — sem isso o `--check` do A3 acusaria ruído como regressão. A medida só acontece depois
de `window.__oiLazyDone` **e** de duas leituras iguais de `querySelectorAll('*').length`: número que
ainda está subindo não é medida, é retrato de meio-caminho (§5 2026-08-24).

Bite-test verde 6/6 em 2026-09-03 (`--selftest --browser`, chromium local).
