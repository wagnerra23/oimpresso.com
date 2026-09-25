# Porteiro do export — o que conferir antes de mandar o zip

> **§0 · Contrato de leitura.** Este documento tem um par executável: `conferir-export.mjs`.
> O documento explica **por que** cada teste existe; o script **é** o teste. Se os dois
> discordarem, o script vence — ele mede, o texto lembra. Nenhum número aqui é para ser
> copiado para código: o namespace sai do cabeçalho do bundle, os tamanhos saem de
> `_export-baseline.json` (na raiz do projeto — `_ds/` é cópia do DS e não guarda nota nossa). **Não acrescentar teste sem acrescentar a seção de mesmo
> número aqui, e não acrescentar seção sem teste.**

## Como usar — dois ambientes, o mesmo porteiro

**No Code (node disponível):** `conferir-export.mjs`, um comando, os dez testes.

```bash
node conferir-export.mjs ./pacote       # depois de descompactar
node conferir-export.mjs . --baseline   # SÓ depois de regenerar o espelho do DS
```

Sai `0` se passou, `1` se reprovou. Uma linha por teste; `INFO` nunca reprova. São **onze**
testes — o 11 nasceu depois dos dez primeiros e ficou no fim da numeração de propósito: renumerar
quebraria as referências "ver seção N" já escritas em outros documentos.

**No Cowork (não há node; há o sandbox do `run_script`):** `conferir-export.cowork.js`,
o mesmo porteiro em dez etapas — o sandbox corta em 30s e o projeto tem ~280 arquivos de
código. Um `run_script` por etapa, trocando só a última palavra:

```js
const src = await readFile('conferir-export.cowork.js');
await new Function('ctx', src + '\nreturn conferir(ctx);')({ readFile, readFileBinary, ls, log, etapa: 'ds' });
```

Etapas, nesta ordem: `ds` · `raiz-1` · `raiz-2` · `raiz-3` · `raiz-4` · `sub-1a` · `sub-1b` ·
`sub-2` · `sub-3` · `crlf`. **Dez chamadas, sempre todas** — parar no meio é não conferir.
(Até 24/09/2026 eram nove: `sub-1` lia `erp-shell-v2` inteiro e estourou os 30 s duas vezes.
Nenhum teste mudou, só a divisão da pasta em duas metades.)

**Quando rodar:** antes de todo export (aqui), e de novo na importação (lá). Os dois lados
rodam a mesma lógica, então "passou aqui e quebrou lá" vira uma diferença medida, não uma
discussão.

**Quando rodar com `--baseline`:** só logo depois de copiar o espelho da fonte viva do DS
(`/projects/49a36f76-…/`). Reescrever a base sem regenerar é apagar o alarme em vez de
atender ao chamado.

---

## Os testes, e o incidente que criou cada um

&gt; Ordem de leitura: 1–8 e 11 reprovam; 9 e 10 são `INFO`.

**1 · Espelho único em `_ds/`, e nenhum `_ds/` aninhado.**
Houve três pastas de DS ao mesmo tempo (`wagner-…49a36f76`, `019dd02f`, `atual-d7f88676`) e
mais duas dentro dos pacotes de handoff. Só uma era carregada, e atualizar as outras não
mudava nada em runtime — sintoma silencioso. Duas cópias do mesmo DS sempre divergem; a
única defesa é não ter duas.

**2 · O namespace sai do cabeçalho `@ds-bundle` do bundle.**
O bundle abre com um comentário JSON que traz `namespace` e a lista de componentes. Ler dali
é a diferença entre medir e lembrar. O erro de origem foi eu cravar o nome em prosa e em 19
lugares; quando o gerador do DS mudou o nome, nada avisou.

**3 · O bundle termina em `})();`, sem alias acrescentado.**
Durante meses o espelho carregava `window.<nome antigo> = window.<nome novo>` no fim. O DS
não gera essa linha: **toda regeneração a apagava em silêncio**, e todas as páginas
renderizavam vazias sem um erro no console. Removido em 21/09/2026. A regra que substituiu o
contorno: as páginas leem o nome que o bundle publica, e o espelho é cópia byte a byte.

**4 · Todo código lê só o namespace publicado.**
Cobre `window.<Nome>` no JS e `component-from-global-scope="<Nome>.Componente"` nos `.dc.html`.
O modo de falha é o pior possível: `|| {}` resolve, a tela monta, os componentes somem, o
console fica limpo. Um `.md` que cita o nome antigo como histórico **não** é defeito — menção
não é leitura, e o teste só varre código.

**5 · O espelho bate com `_export-baseline.json` (raiz).**
Pega os dois casos de uma vez: alguém editou o espelho à mão (voltou a ser fonte), ou ele foi
regenerado e a base não. `_ds/` **nunca é fonte** — é cópia para o protótipo rodar, e envelhece.

**6 · As fontes têm pesos distintos e ≥ 55 KB.**
`ibm-plex-sans-500/600/700.woff2` tinham 45.712 bytes cada — os três eram a 400 copiada. A
prévia mostrava peso de fonte errado e nada acusava. Tamanho idêntico entre pesos é o sinal.

**7 · Nenhum caminho de código aponta para espelho apagado.**
`019dd02f`, `d7f88676`, `office-impresso-atual`. Um `<link>` para pasta inexistente não
derruba a página: ela abre sem os tokens e parece só "meio errada".

**8 · Toda referência local de HTML existe no pacote.**
Páginas em subpasta apontando para `_ds/` da raiz já saíram quebradas do zip. `src`/`href`
que não começa com `http:`, `data:` ou `//` tem que resolver em disco.

**11 · Toda página que monta tela do DS carrega o `_ds_bundle.js`.**
Se uma página HTML carrega `.jsx` locais que leem o namespace e **nenhum** `_ds_bundle.js`,
ela abre **em branco** — o `createRoot` falha com `type is invalid … got: undefined` e o erro
**não aparece no console**. Foi o que aconteceu em 22/09/2026 com
`handoff_fabricacao/design/Fabricacao - Guia de Producao.html`: ao repontar os `.jsx` da cópia
local (pré-onda-A, sem DS) para os da raiz (pós-onda-A, com DS), a página parou de renderizar e
nada acusou. Um conserto de duplicata virou tela morta. O teste compara **o que a página carrega**
com **o que esses arquivos leem** — é o único que liga as duas pontas.
*Conferido em teste negativo: removendo a tag do bundle, o teste reprova com os 5 consumidores
nomeados.*

**9 · `INFO` — arquivos idênticos em dois endereços.**
Não reprova porque às vezes a duplicata é deliberada (o pacote de handoff leva sua cópia).
Mas toda cópia diverge com o tempo: se aparecer par novo, decida qual é o dono antes de
exportar.

**10 · `INFO` — quebra de linha.**
Medido byte a byte (`0x0D 0x0A`), não pelo leitor de texto. **Esta distinção custou uma
afirmação errada minha:** disse "medi, está tudo em LF" quando o que eu tinha lido era texto
já normalizado pela ferramenta. Se os arquivos do projeto estão em LF e o zip sai em CRLF,
quem converteu foi o empacotador — o teste reporta o número para que o lado certo seja
corrigido, e nunca reprova o pacote por isso.

---

## O que o script **não** cobre (e por isso continua sendo trabalho humano)

Lista **fechada** — se algo não está aqui nem nos dez testes, ninguém está olhando:

- **Se o espelho está atualizado em relação ao DS vivo.** O teste 5 compara com a última
  regeneração, não com a fonte. Antes de qualquer auditoria, comparar `_ds/` com
  `/projects/49a36f76-…/` e regenerar se divergir — o script não alcança outro projeto.
- **Estado visual em prévia oculta.** Nenhum teste julga pixel, e há um motivo além do escopo:
  na prévia `document.visibilityState` pode ser `"hidden"`, e aí o relógio de animação não
  avança (`getAnimations()` parado em `currentTime: 0`, `playState: "running"`). Toda
  propriedade **transicionada** congela no valor inicial — um indicador que "não acompanha o
  clique" costuma ser isso, não defeito. Ler o que não transiciona (`aria-*`, `style` inline) e
  medir o relógio em duas leituras antes de abrir defeito. Medido em 22/09/2026 no `TabBar` da
  página-guia (ficha P-4 do `plano-ondas-fabricacao-v2.md`).
- **Se o conteúdo das telas está certo.** Nenhum teste abre a página. Aderência ao DS,
  contraste, densidade e regra de negócio continuam sendo leitura.
- **Se os `.md` dizem a verdade.** O teste 4 ignora documentação de propósito. Documento que
  descreve contrato revogado engana quem lê, e só revisão pega.
- **Os 4 arquivos de `erp-shell-v2/` com acento no nome.** As ferramentas de leitura em lote
  do Cowork recusam esses caminhos; `node` não tem esse limite, então **o script os cobre e
  eu não**. Se a conferência for feita só daqui, eles ficam de fora.

## Registro

- **21/09/2026** — criado depois do terceiro export com o mesmo defeito de namespace.
  Testes 1–10 escritos a partir dos incidentes de 09/09 a 21/09.
- **21/09/2026, mesma sessão — EXECUTADO.** As nove etapas do `conferir-export.cowork.js`
  rodaram aqui contra o projeto real. Resultado: **testes 1, 2, 3, 4, 5, 6, 7 e 10 passam**
  (espelho único; namespace `OfficeImpressoPontoWR2DesignSystem_019dd0` lido do cabeçalho, 58
  componentes; bundle termina em `})();`; 233 arquivos de código lendo só esse nome; espelho
  igual à base nos 14 arquivos; fontes 400:63,0k 500:66,7k 600:67,1k 700:63,0k; zero caminho
  morto; amostra de 10 arquivos toda em LF). O **teste 8 reprovou** — ver pendências abaixo.
- O `conferir-export.mjs` (versão node) **não foi executado**: não há node no Cowork. Ele
  implementa os mesmos dez testes mais o 9 (sha1 de duplicatas) e a varredura completa de
  referências; a primeira rodada de verdade é no Code. Se algum quebrar por erro meu de
  script, é ali que aparece.

### Dois achados do teste 8 — anteriores a esta sessão, não corrigidos

Nenhum dos dois é do namespace, e nenhum dos dois eu inventei: saíram do porteiro na
primeira rodada. Deixados como estão porque corrigi-los é decisão de quem é dono da tela,
não do porteiro.

1. `erp-shell-v2/Auditoria UI.html` referencia 5 imagens que não existem em lugar nenhum do
   projeto: `audit-oficina.png`, `audit-compras.png`, `audit-os.png`, `audit-financeiro.png`,
   `audit-boletos.png`. A página abre com 5 quadros vazios.
2. `importado_telas/clientes/Oimpresso ERP - Clientes.html` carrega `chat-icons.jsx` e
   `chat-sidebar.jsx`, que não estão no pacote.

### O limite do porteiro no Cowork

Quatro arquivos de `erp-shell-v2/` têm acento no nome (`Bench Mecânica.html`, os dois
`Diagnóstico …`, `Produção Oficina - Tela.html`) e o leitor do sandbox **recusa esses
caminhos**. A saída os lista como "NÃO LIDOS" — que é diferente de "sem defeito". O
`conferir-export.mjs` no Code não tem esse limite e cobre os quatro.
