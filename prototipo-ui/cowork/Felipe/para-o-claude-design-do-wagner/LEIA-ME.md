# Para o agente do projeto do Wagner — instalar o porteiro de export

Três arquivos, quinze minutos, e o export para de sair quebrado em silêncio. Isto veio de
outro projeto que consome o mesmo design system pelo mesmo mecanismo (espelho local +
bundle compilado + nome global em `window`). Os incidentes citados são reais e cada um
custou pelo menos um export refeito.

## O que instalar

Copiar os dois arquivos para a **raiz** do projeto:

- **`conferir-export.mjs`** — o porteiro. Onze testes de máquina, zero dependências.
- **`pre-export.md`** — o par legível: por que cada teste existe e a lista **fechada** do
  que ele não cobre.

Depois, acrescentar ao `CLAUDE.md` do projeto o bloco do fim deste arquivo — sem isso, um
chat novo não sabe que o porteiro existe e exporta sem rodar nada.

## Ajustar antes da primeira rodada

Só o bloco `CFG` no topo do `conferir-export.mjs`:

| campo | o que é | padrão |
|---|---|---|
| `dirEspelho` | pasta da cópia local do DS | `'_ds'` |
| `caminhosMortos` | nomes de espelhos já apagados que não podem mais ser citados em código | `[]` — preencher ao apagar um |
| `pisoFonte` | piso de bytes por arquivo de fonte | `20000` |

## Rodar

```bash
node conferir-export.mjs .              # antes de gerar o zip
node conferir-export.mjs ./pacote       # depois de descompactar, antes de promover
node conferir-export.mjs . --baseline   # SÓ logo após regenerar o espelho do DS
```

A primeira rodada quase certamente reprova o teste 5 (`_export-baseline.json` não existe).
Isso é esperado: regenere o espelho a partir da fonte viva do DS, confira que está certo, e
só então rode `--baseline` para gravar o recibo.

## Se não houver `node` no ambiente

O porteiro é Node puro de propósito — é o que permite rodá-lo dos dois lados. Num ambiente
de agente sem `node`, a saída é portar a mesma lógica para o executor de scripts disponível,
em etapas (um orçamento de tempo curto não varre centenas de arquivos numa chamada só), e
declarar quais testes ficaram de fora. **Teste não executado não é teste que passou.**

## Um aviso que vale mais que os onze testes

Todo teste aqui nasceu de um defeito **silencioso**: a página abre, nada aparece no console,
e o erro só é descoberto dias depois por quem importa o pacote. Por isso o porteiro mede
byte, nome e caminho — e não "parece certo". Ao acrescentar um teste, escreva junto a seção
de mesmo número no `pre-export.md` com o incidente que o criou. Teste sem história vira
teste que alguém desliga.

---

## Bloco para colar no `CLAUDE.md` do projeto

```markdown
- `pre-export.md` + `conferir-export.mjs` + `_export-baseline.json` — **o porteiro do
  pacote, e ele é obrigatório.** Onze testes de máquina que reprovam o zip antes de ele
  sair: espelho único na pasta do DS, nome global lido do cabeçalho `@ds-bundle` do bundle,
  ausência de alias, código lendo só o nome publicado, espelho igual à linha de base, pesos
  de fonte distintos, caminhos mortos, referências locais, **página que monta tela do DS
  carregando o `_ds_bundle.js`** (sem ele a tela abre em branco e o console não acusa),
  duplicatas e CRLF medido por byte.

  **Antes de QUALQUER export — inclusive em chat novo, inclusive depois de uma mudança
  pequena — rodar `node conferir-export.mjs .`** e relatar o resultado. Reprovou: consertar
  e rodar de novo, ou dizer qual teste reprovou e por que vai assim mesmo — nunca exportar
  em silêncio. Rodar de novo do outro lado, sobre o pacote descompactado. `--baseline` **só**
  logo após regenerar o espelho da fonte viva. Teste novo entra com a seção de mesmo número
  no `pre-export.md`, nunca sozinho.

- **A nota de tamanhos (`_export-baseline.json`) mora na raiz**, nunca dentro da pasta do
  espelho — ela é cópia do DS, e nota nossa lá dentro se perde na próxima regeneração.

- **Quebra de linha não se mede lendo texto.** O leitor de arquivo entrega o conteúdo já
  normalizado: "li e está em LF" é cegueira da ferramenta. LF/CRLF só por byte (`0x0D 0x0A`).

- **Contorno que o gerador não gera é dívida com prazo.** Alias, shim e remendo no espelho
  somem em silêncio na próxima regeneração. A saída não é lembrar de reaplicar: é remover o
  contorno e alinhar as duas pontas. Quando não der, o contorno vira teste no porteiro.

- **Estado visual em documento oculto não é estado da tela.** Numa prévia,
  `document.visibilityState` pode ser `"hidden"`, e o relógio de animação não avança
  (`getAnimations()` em `currentTime: 0` · `playState: "running"`): toda propriedade
  transicionada congela no valor inicial. Antes de chamar isso de defeito, ler o que **não**
  transiciona (`aria-*`, `style` inline) e medir o relógio em duas leituras.
```
