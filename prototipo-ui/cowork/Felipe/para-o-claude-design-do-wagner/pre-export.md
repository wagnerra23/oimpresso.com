# Porteiro do export — o que conferir antes de mandar o zip

> **§0 · Contrato de leitura.** Este documento tem um par executável: `conferir-export.mjs`.
> O documento explica **por que** cada teste existe; o script **é** o teste. Se os dois
> discordarem, o script vence — ele mede, o texto lembra. Nenhum número aqui é para ser
> copiado para código: o nome global do design system sai do cabeçalho `@ds-bundle` do
> bundle, os tamanhos saem de `_export-baseline.json` (na raiz do projeto — a pasta do
> espelho é cópia do DS e não guarda nota nossa). **Não acrescentar teste sem a seção de
> mesmo número aqui, e não acrescentar seção sem teste.**

## Como usar

```bash
node conferir-export.mjs .              # antes de gerar o zip
node conferir-export.mjs ./pacote       # depois de descompactar, antes de promover
node conferir-export.mjs . --baseline   # SÓ logo após regenerar o espelho do DS
```

Sai `0` se passou, `1` se reprovou. Uma linha por teste; `INFO` nunca reprova. São **onze**
testes — o 11 nasceu depois dos dez primeiros e ficou no fim da numeração de propósito:
renumerar quebraria as referências "ver seção N" escritas em outros documentos.

Rodar nos **dois** lados. Quando o mesmo arquivo roda antes de exportar e depois de
importar, "passou aqui e quebrou lá" vira uma diferença medida, não uma discussão.

`--baseline` **só** logo depois de copiar o espelho da fonte viva do DS. Reescrever a base
sem regenerar é apagar o alarme em vez de atender ao chamado.

### O que ajustar antes da primeira rodada

Só o bloco `CFG` no topo do script:

- `dirEspelho` — a pasta da cópia local do DS (padrão `_ds`).
- `caminhosMortos` — **comece vazio.** Acrescente o nome de cada espelho que você apagar.
  Sem isso o teste 7 não tem o que procurar, e ele avisa que está vazio.
- `pisoFonte` — piso de bytes por arquivo de fonte. O padrão (20 KB) serve para subsets
  latinos; famílias grandes pedem mais.

---

## Os testes, e o incidente que criou cada um

Os incidentes são reais, de um protótipo que consome um DS pelo mesmo mecanismo (espelho
local + bundle compilado + nome global). Cada um custou pelo menos um export refeito.

> Ordem de leitura: 1–8 e 11 reprovam; 9 e 10 são `INFO`.

**1 · Espelho único, e nenhum aninhado.**
Chegamos a ter três pastas de DS ao mesmo tempo e mais duas dentro de pacotes de handoff.
Só uma era carregada, e atualizar as outras não mudava nada em runtime — sintoma
silencioso. Duas cópias do mesmo DS sempre divergem; a única defesa é não ter duas.

**2 · O nome global sai do cabeçalho `@ds-bundle`.**
O bundle abre com um comentário JSON que traz `namespace` e o catálogo. Ler dali é a
diferença entre medir e lembrar. O erro de origem foi cravar o nome em prosa: quando o
gerador do DS mudou o nome, nada avisou.

**3 · O bundle termina em `})();`, sem alias acrescentado.**
Durante meses o espelho carregava `window.<nomeAntigo> = window.<nomeNovo>` no fim. O DS
não gera essa linha: **toda regeneração a apagava em silêncio**, e todas as páginas
renderizavam vazias sem um erro no console. A saída não foi lembrar melhor — foi remover o
contorno e fazer as páginas lerem o nome que o bundle publica.

**4 · Todo código lê só o nome publicado.**
Cobre `window.<Nome>` no JS e `component-from-global-scope="<Nome>.Componente"` nos
`.dc.html`. O modo de falha é o pior possível: `|| {}` resolve, a tela monta, os
componentes somem, o console fica limpo. Um `.md` que cita um nome antigo como histórico
**não** é defeito — menção não é leitura, e o teste só varre código.

**5 · O espelho bate com `_export-baseline.json`.**
Pega dois casos de uma vez: alguém editou o espelho à mão (ele voltou a ser tratado como
fonte), ou ele foi regenerado e a base não. O espelho **nunca é fonte** — é cópia para o
protótipo rodar, e envelhece.

**6 · Pesos de fonte com tamanhos distintos.**
Três pesos saíram com exatamente o mesmo número de bytes: eram o peso 400 copiado sobre os
outros. A página abre, o peso está errado, nada acusa. Tamanho idêntico entre pesos da
mesma família é o sinal; o teste agrupa por nome de família e compara.

**7 · Nenhum caminho de código para espelho apagado.**
Um `<link>` para pasta inexistente não derruba a página: ela abre sem os tokens e parece só
"meio errada". Alimente `CFG.caminhosMortos` toda vez que apagar um espelho.

**8 · Toda referência local de HTML existe no pacote.**
Páginas em subpasta apontando para a pasta do DS na raiz já saíram quebradas do zip.
`src`/`href` que não começa com `http:`, `data:` ou `//` tem que resolver em disco.

**11 · Toda página que monta tela do DS carrega o `_ds_bundle.js`.**
Se uma página HTML carrega `.jsx` locais que leem o nome global e **nenhum**
`_ds_bundle.js`, ela abre **em branco**: o `createRoot` falha com `type is invalid … got:
undefined` e o erro **não aparece no console**. Aconteceu ao repontar uma página de uma
cópia antiga dos `.jsx` (que não usava o DS) para os arquivos atuais (que usam) — um
conserto de duplicata virou tela morta. É o único teste que liga as duas pontas: o que a
página carrega contra o que esses arquivos leem.

**9 · `INFO` — arquivos idênticos em dois endereços.**
Não reprova porque às vezes a duplicata é deliberada (um pacote de handoff leva sua cópia).
Mas toda cópia diverge com o tempo: par novo na lista, decida qual é o dono antes de
exportar. Duas das nossas divergiram em 5 de 6 arquivos sem ninguém notar.

**10 · `INFO` — quebra de linha, medida por byte.**
`0x0D 0x0A` no binário, nunca por leitura de texto: o leitor de arquivo entrega o conteúdo
já normalizado, e "li e está em LF" é cegueira da ferramenta, não medição. Se os arquivos
estão em LF e o zip sai em CRLF, quem converteu foi o empacotador — o teste reporta o
número para que o lado certo seja corrigido, e nunca reprova o pacote por isso.

---

## O que o script **não** cobre

Lista **fechada** — se algo não está aqui nem nos onze testes, ninguém está olhando:

- **Se o espelho está atualizado em relação ao DS vivo.** O teste 5 compara com a última
  regeneração, não com a fonte. Comparar as duas pontas continua sendo trabalho humano.
- **Estado visual em prévia oculta.** Nenhum teste julga pixel, e há um motivo além do
  escopo: numa prévia `document.visibilityState` pode ser `"hidden"`, e aí o relógio de
  animação não avança (`getAnimations()` parado em `currentTime: 0`, `playState:
  "running"`). Toda propriedade **transicionada** congela no valor inicial — um indicador
  que "não acompanha o clique" costuma ser isso, não defeito. Ler o que não transiciona
  (`aria-*`, `style` inline) e medir o relógio em duas leituras antes de abrir defeito.
- **Se o conteúdo das telas está certo.** Nenhum teste abre a página. Aderência ao DS,
  contraste, densidade e regra de negócio continuam sendo leitura.
- **Se os `.md` dizem a verdade.** O teste 4 ignora documentação de propósito. Documento que
  descreve contrato revogado engana quem lê, e só revisão pega.
- **Nomes de arquivo com acento**, quando a conferência é feita por ferramenta que recusa
  esses caminhos. O `node` não tem esse limite; um sandbox de agente pode ter. Arquivo não
  lido é **não medido**, que é diferente de "sem defeito" — exija que a saída os liste.

## Registro

- Crie aqui uma linha por rodada: data, o que reprovou, o que foi consertado. É o histórico
  que impede o mesmo defeito de voltar com outro nome.
