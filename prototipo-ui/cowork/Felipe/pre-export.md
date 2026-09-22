# Porteiro do export — o que conferir antes de mandar o zip

> **§0 · Contrato de leitura.** Este documento tem um par executável: `conferir-export.mjs`.
> O documento explica **por que** cada teste existe; o script **é** o teste. Se os dois
> discordarem, o script vence — ele mede, o texto lembra. Nenhum número aqui é para ser
> copiado para código: o namespace sai do cabeçalho do bundle, os tamanhos saem de
> `_ds/_export-baseline.json`. **Não acrescentar teste sem acrescentar a seção de mesmo
> número aqui, e não acrescentar seção sem teste.**

## Como usar

```bash
node conferir-export.mjs .          # aqui, antes de gerar o zip
node conferir-export.mjs ./pacote   # no Code, depois de descompactar
node conferir-export.mjs . --baseline   # SÓ depois de regenerar o espelho do DS
```

Sai `0` se passou, `1` se reprovou. Uma linha por teste; `INFO` nunca reprova.

**Quando rodar:** antes de todo export, e de novo na importação. Os dois lados rodam o
mesmo arquivo, então "passou aqui e quebrou lá" vira uma diferença medida, não uma
discussão.

**Quando rodar com `--baseline`:** só logo depois de copiar o espelho da fonte viva do DS
(`/projects/49a36f76-…/`). Reescrever a base sem regenerar é apagar o alarme em vez de
atender ao chamado.

---

## Os testes, e o incidente que criou cada um

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

**5 · O espelho bate com `_ds/_export-baseline.json`.**
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
  **Ainda não executei o `conferir-export.mjs`** — não há `node` no ambiente do Cowork. A
  lógica dos testes 2, 3 e 4 foi validada aqui contra os arquivos reais (namespace lido do
  cabeçalho, zero alias, zero leitura fora do namespace); os testes 1, 5, 6, 7, 8, 9 e 10
  são **não executados**. A primeira rodada de verdade é no Code, na próxima importação — se
  algum quebrar por erro meu de script, é ali que aparece.
