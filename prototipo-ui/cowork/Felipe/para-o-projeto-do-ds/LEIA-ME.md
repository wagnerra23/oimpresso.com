# Dois arquivos para a raiz do projeto do DS

Copiar os dois para a **raiz** do projeto do design system (49a36f76), ao lado de
`_ds_manifest.json` e `_ds_bundle.js`:

- `conferir-ds.mjs` — o porteiro (12 testes). `node conferir-ds.mjs .`
- `pre-export-ds.md` — o par legível: por que cada teste existe, o ciclo do pull, e a
  lista fechada do que ele **não** cobre.

Foram escritos no projeto do protótipo porque de lá não dá para gravar em outro projeto.
Não pertencem ao protótipo: lá o porteiro é outro (`conferir-export.mjs` + `pre-export.md`),
com outros dez testes.

## O ciclo

1. puxar as atualizações do DS do Wagner
2. `node conferir-ds.mjs .` — testes 1 a 9 têm de passar; o 10 lista o que mudou
3. conferir que a lista do 10 é o pull esperado
4. `node conferir-ds.mjs . --baseline` — grava o recibo
5. exportar
6. `node conferir-ds.mjs ./pacote-ds` no Code, antes de promover

O passo 3 é o que o porteiro do protótipo não tem: no DS, **diferença de tamanho não é
defeito, é o pull**. Se o teste 10 disser "nada mudou" logo depois de puxar, o pull não
chegou.

## Procedência da validação

Não há `node` no Cowork, e o sandbox não varre outro projeto. A lógica dos testes 2, 3, 4,
5, 5.1, 6, 6.1, 7 e 8 foi rodada contra o **espelho local** do DS (cópia da fonte viva):
namespace coerente bundle × manifest, 58 componentes iguais nos três lugares, sem alias,
`sourcePath` e `.d.ts` nos 58, CSS 19.917 / 1.366 / 5.705 B, fontes sem peso repetido,
7 templates com 0 referência quebrada.

Os testes 1, 10, 11 e 12 são **não executados** — dependem da raiz real do DS. A primeira
rodada de verdade é lá, e é lá que um erro meu de script aparece.
