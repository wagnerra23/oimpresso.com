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

## Entrada: `<tela>.secoes.json` (versionado ao lado do alvo)

O `--alvo` recebe as seções por arquivo (`--secoes`). Ele fica **aqui**, com o mesmo slug do alvo,
porque o alvo tem de ser re-executável por quem não viu a sessão: `<id>: { seletor, campos? }`.
Chave que começa com `_` é nota de proveniência (de onde os seletores foram colhidos) e a sonda a ignora.
Os seletores vêm do `--mapa` (DOM vivo), nunca de lembrança — o §2 do PROTOCOLO tem os 4 seletores
inventados que isso evita.

## Páginas com carga em fases — `--aguardar-sumir` e `--quieto-ms`

"Duas leituras iguais" aprova qualquer fase que fique parada 400 ms — e o Painel da Jana tem três
(`jm-sk-nota` → `.jm-sk` → conteúdo, re-armado quando a empresa do shell chega). Medido 2026-09-06:
o mesmo comando devolveu **719** nós num run e **1011** no seguinte. As duas flags fecham isso:
`--aguardar-sumir .jm-sk` (só mede depois que o esqueleto entrou **e** saiu; se nunca sair, exit 2)
e `--quieto-ms 2000` (janela mínima sem mudança no nº de nós). O alvo grava as duas como
proveniência (`aguardou_sumir`, `quieto_ms`) — quem re-rodar sem elas não reproduz o arquivo, e é
isso que o byte-idêntico denuncia.

## Determinismo

Duas execuções seguidas produzem bytes idênticos (chaves ordenadas, geometria arredondada, zero
timestamp) — sem isso o `--check` do A3 acusaria ruído como regressão. A medida só acontece depois
de `window.__oiLazyDone` **e** de duas leituras iguais de `querySelectorAll('*').length`: número que
ainda está subindo não é medida, é retrato de meio-caminho (§5 2026-08-24).

Bite-test verde 6/6 em 2026-09-03 e 9/9 em 2026-09-06 (`--selftest --browser`, chromium local) — os
3 novos provam `--aguardar-sumir` (esqueleto de 700 ms · controle negativo que nunca sai → NÃO MEDI)
e `--quieto-ms` (carga em 2 fases, 300 e 900 ms → mede o estado final).

## Alvos exportados

| tela | slug | seções | como reproduzir |
|---|---|---|---|
| Jana/Index (Painel `/ia/dashboard`) | `jana--index` | 9 (header · tabs · brief · kpis · metas · analises_titulo · analises · acoes_titulo · acoes) | espelho servido (rota default `chat` + tab `painel`, dark) · `npm run alvo:medir -- http://127.0.0.1:5550/ --tela Jana--Index --secoes prototipo-ui/alvos/jana--index.secoes.json --aguardar-sumir .jm-sk --quieto-ms 2000` · viewport 1280×900 |
