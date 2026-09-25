# Porteiro do export do DESIGN SYSTEM

> **§0 · Contrato de leitura.** Este documento tem um par executável: `conferir-ds.mjs`.
> O documento explica **por que** cada teste existe; o script **é** o teste. Se discordarem,
> o script vence — ele mede, o texto lembra. Nenhum número aqui é para ser copiado para
> código: tudo sai do `_ds_manifest.json` ou do cabeçalho `@ds-bundle` do bundle.
> **Não acrescentar teste sem a seção de mesmo número aqui, e vice-versa.**

## Onde estes dois arquivos moram

Na **raiz do projeto do DS**, ao lado de `_ds_manifest.json` e `_ds_bundle.js`. Foram
escritos no projeto do protótipo porque é de lá que eu escrevo — **não consigo gravar em
outro projeto**. Copiar os dois para o DS: `conferir-ds.mjs` e `pre-export-ds.md`.

Este é o porteiro do **DS**. O do protótipo é outro (`pre-export.md` +
`conferir-export.mjs`), com outros dez testes. Não misturar: quatro testes do protótipo não
fazem sentido no DS (espelho único em `_ds/`, código lendo o namespace, linha de base do
espelho, caminhos das pastas apagadas) porque descrevem o **consumidor**, não a fonte.

## Como usar

```bash
node conferir-ds.mjs .                 # antes de exportar, depois de puxar do Wagner
node conferir-ds.mjs ./pacote-ds       # no Code, sobre o pacote descompactado
node conferir-ds.mjs . --baseline      # logo DEPOIS de puxar do Wagner e conferir
```

Sai `0` se passou, `1` se reprovou. `INFO` nunca reprova.

**A ordem do ciclo importa:**

1. puxar as atualizações do DS do Wagner
2. `node conferir-ds.mjs .` — os testes 1 a 9 têm de passar; o teste 10 vai **listar o que
   mudou** desde o último pull
3. conferir que a lista do 10 é o pull que você esperava
4. `node conferir-ds.mjs . --baseline` — grava o recibo
5. exportar
6. `node conferir-ds.mjs ./pacote-ds` no Code, antes de promover

O passo 3 é o que o protótipo não tem: no DS, **diferença não é defeito, é o pull**. O
teste só exige que ela seja consciente. Se o 10 disser "nada mudou" logo depois de você
puxar, o pull não chegou.

---

## Os testes

**1 · `_ds_manifest.json` e `_ds_bundle.js` na raiz, sem segunda cópia.**
Um DS com dois bundles é dois DS. No protótipo isso custou três pastas de espelho vivendo
ao mesmo tempo, e só uma sendo carregada.

**2 · Cabeçalho `@ds-bundle` legível, e o namespace igual ao do manifest.**
O bundle abre com um comentário JSON que traz `namespace` e o catálogo. É a única fonte do
nome publicado, e é o que todo consumidor deve ler. Quando esse cabeçalho falta, quem
consome crava o nome em prosa — foi exatamente assim que 6 arquivos do protótipo ficaram
lendo um nome que o bundle não publicava mais, **sem um erro no console**.

**3 · O bundle publica exatamente o catálogo.**
Compara os três lugares: `manifest.components`, o cabeçalho e as atribuições
`__ds_ns.X =` do próprio código. Chaves internas (`__errors`) são ignoradas — **eu mesmo
contei 59 componentes por incluir `__errors` na conta; são 58.** Componente no catálogo e
não publicado renderiza vazio; publicado e fora do catálogo não aparece na galeria.

**4 · O bundle termina em `})();`, sem alias acrescentado.**
`window.<nomeAntigo> = window.<nomeNovo>` no fim é contorno de consumidor, não do DS. O
gerador não produz essa linha, então toda regeneração a apaga em silêncio. Se o DS exportar
com alias, ele está exportando o contorno de alguém.

**5 · O `sourcePath` de todo componente do manifest existe no disco.**
É o teste do "exportou pela metade": manifest completo, pasta de componente faltando.
**5.1 (INFO)** — `.d.ts` ao lado, que é o contrato de props. Hoje: 58 de 58 têm.

**6 · Os CSS de `globalCssPaths` existem e não são stub.**
**6.1 (INFO) — `cockpit_domains.css`.** Ele **não** está em `globalCssPaths`, mas o shell do
protótipo o carrega. Eu já afirmei que esse arquivo não existia, removi, e tive de restaurar:
tinha em mãos um stub vazio criado por mim e concluí a ausência a partir dele. Daí o teste
olhar o **tamanho**, não só a presença.

**7 · As fontes declaradas existem, e pesos diferentes têm tamanhos diferentes.**
`ibm-plex-sans-500/600/700.woff2` já saíram com 45.712 bytes cada — os três eram a 400
copiada. Nada acusava: a página abre, o peso está errado. **O defeito nasceu do lado do DS**,
por isso o teste vive aqui e não só no protótipo.

**8 · Todo template abre, e suas referências locais resolvem.**
`entryPath` existente, e todo `src`/`href` local do arquivo apontando para algo que está no
pacote. Pega o caso mais chato: `templates/_shared/ds-base.js` com a linha `base` apontando
para o caminho de um projeto consumidor em vez de `'../..'`.

**9 · O DS não contém caminho do espelho do protótipo.**
`_ds/wagner-…`, `_ds/office-impresso-…`, `/projects/<id>/`. O DS é a **fonte**; caminho de
consumidor dentro dele inverte a direção e é a semente do próximo alias. Este teste tem um
caso negativo medido: quando o espelho local do protótipo foi regenerado, a ferramenta de
cópia reescreveu `templates/_shared/ds-base.js` com o caminho do consumidor — o teste
acusaria. (Corrigido no espelho para `'../..'`.)

**10 · INFO — recibo do último pull (`_export-baseline-ds.json`).**
Não reprova nunca. Lista o que mudou de tamanho, de namespace e de contagem desde o último
`--baseline`. Serve para duas perguntas opostas: *o pull chegou?* e *alguém editou o DS à
mão?*

**11 · INFO — arquivos idênticos em dois endereços.** Toda cópia diverge com o tempo.

**12 · INFO — quebra de linha, medida por byte** (`0x0D 0x0A`), nunca por leitura de texto:
o leitor de arquivo entrega o conteúdo já normalizado, e "li e está em LF" é cegueira da
ferramenta. Se o projeto está em LF e o zip sai em CRLF, quem converteu foi o empacotador.

---

## O que o porteiro **não** cobre

Lista **fechada** — se algo não está aqui nem nos doze testes, ninguém está olhando:

- **Se o seu DS está igual ao do Wagner.** O teste 10 compara com o **seu** último pull, não
  com a fonte dele. Comparar as duas pontas continua sendo trabalho de quem puxa.
- **Se o componente está certo.** Nenhum teste renderiza nada. Props, contraste, tokens e
  aderência continuam sendo leitura.
- **Se os `.md` do DS dizem a verdade.** O `HANDOFF.md` do DS, por exemplo, ainda descreve o
  alias de namespace como contorno vigente — ele foi removido em 21/09/2026 do lado do
  protótipo. Texto de documento nenhum teste lê.
- **O que o empacotador faz depois.** Estrutura do zip, CRLF e wrapper `project/` são do
  exportador; o teste 12 mede, não conserta.

## Registro

- **21/09/2026** — escrito no projeto do protótipo, para ser copiado ao DS.
- **Validado por execução, com uma ressalva de procedência.** Não há `node` no Cowork e eu
  não consigo varrer outro projeto pelo sandbox, então rodei a lógica dos testes 2, 3, 4, 5,
  5.1, 6, 6.1, 7 e 8 contra o **espelho local** `_ds/wagner-…49a36f76…/`, que é cópia do DS
  vivo. Resultado: namespace coerente entre bundle e manifest; 58 componentes iguais nos três
  lugares; sem alias; `sourcePath` e `.d.ts` presentes nos 58; `colors_and_type.css` 19.917 B,
  `styles.css` 1.366 B, `cockpit_domains.css` 5.705 B; sans 400:63,0k 500:66,7k 600:67,1k
  700:63,0k e mono 400:14,7k 500:14,9k 600:15,6k, sem peso repetido; 7 templates abrem com 0
  referência quebrada. O teste 9 foi validado no caso negativo (ver seção 9). Os testes 1,
  10, 11 e 12 são **não executados** — dependem da raiz real do DS.
- **O espelho não é o DS.** A validação acima é proxy declarada. A primeira rodada de verdade
  é no projeto do DS, e é lá que um erro meu de script aparece.
