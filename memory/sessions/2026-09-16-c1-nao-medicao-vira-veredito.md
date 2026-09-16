---
date: "2026-09-16"
hour: "15:30 UTC"
topic: "O ciclo do C1 em 3 PRs de 2 sessoes: o predicado virou `>=1`, a nao-medicao parou de sair como verde, e `medido: false` passou a dizer QUAL porta fechou. O defeito do meio veio de CONFERIR o PR da sessao irma, nao de escrever codigo"
authors: [C]
prs: [7413, 7424, 7426, 7429]
related_adrs: [0344-two-strikes-cobre-processo]
outcomes:
  - "O `auditMudouDeCasa` tinha DOIS early-returns devolvendo o mesmo valor, e o chamador decide por `c1 !== null` — entao o caminho que o proprio comentario declarava como nao-medicao reportava `medido: true` e imprimia `C1 (advisory) = 0`, indistinguivel de ter medido (#7424)"
  - "Agravante achado ao escrever o teste: a linha `ⓘ NAO-MEDIDOS` (o contador LC-33 do #7402) e computada DENTRO do bloco que so roda quando ha medicao — o unico sinal que denunciaria a cegueira sumia junto com ela"
  - "Nao e hipotetico: o indice e `git ls-tree -r origin/main` e o `sh()` engole stderr; sem a ref no checkout (fetch parcial, clone raso) o gate afirmava verde tendo medido zero. Familia da §5 2026-08-11"
  - "Follow-up #7429: os dois caminhos de `null` davam a MESMA frase `sem base pra comparar`, que descreve so um deles. Cada porta passa a registrar o proprio motivo — consertos diferentes (buscar a base do diff x buscar a ref no checkout)"
  - "Colisao: abri o #7426 com o mesmo fix 8 minutos DEPOIS do #7424 dela. Fechei como duplicado. Coordenacao no inicio nao bastou — o outro lado estava produzindo naquele exato intervalo"
  - "3 sondas minhas erraram no dia (contagem de nome como proxy de comportamento, mutacao que caiu no early-return errado, `||` no fim de pipeline). Nenhuma virou afirmacao publicada; o que pegou as tres foi conferir QUAL caminho fora exercido"
---

# O C1: nao-medicao vira veredito proprio

## TL;DR

Tres PRs, duas sessoes, um mecanismo:

| PR | o que | de quem |
|---|---|---|
| #7413 | o predicado do `mudou_de_casa` vira `>=1` (a fracao era a metrica errada) | sessao irma |
| #7424 | nao-medicao para de sair como VERDE (`return null`) | sessao irma, achado meu |
| #7429 | `medido: false` passa a dizer QUAL porta fechou | meu |

O defeito do meio **nao veio de escrever codigo — veio de conferir o PR da outra sessao**.
Ela avisou que mexera em funcoes minhas; ao verificar as claims dela (todas verdadeiras),
li o bloco ao redor e achei um `return` que contradizia o comentario da linha de cima.

O estado de `main` e o contexto do ciclo estao no
[handoff 14:15](../handoffs/2026-09-16-1415-limiar-vira-predicado-e-ratificacao-0401.md)
da sessao irma — este log conta o **trabalho**, nao o estado.

## O defeito: dois caminhos, um valor

```js
const vazio = { achados: [], naoResolvidos: [] };
if (!docs.length) return vazio;   // benigno: PR nao toca memory/requisitos
if (!vivos.size)  return vazio;   // "sem indice nao ha medicao — NAO afirmar verde (LC-33)"
```

O chamador decide por `mudou_de_casa_medido: c1 !== null`. Como os dois devolviam objeto,
o caminho que o **proprio comentario** declarava como nao-medicao saia com `medido: true`.

| caso | medido | nao_resolv | texto |
|---|---|---|---|
| indice ok | `true` | 1 | `C1 (advisory)` + `ⓘ NAO-MEDIDOS` |
| indice vazio (antes) | `true` | 0 | `C1 (advisory) = 0` |
| indice vazio (depois) | `false` | `null` | `NAO MEDIDO (<motivo>)` |

**O agravante so apareceu ao escrever o teste:** a linha `ⓘ NAO-MEDIDOS` — o contador LC-33
que eu mesmo pusera no #7402 — e computada **dentro** do bloco que so roda quando ha medicao.
O unico sinal que denunciaria a cegueira desaparecia exatamente no caso em que era necessario.

E nao e hipotetico: o indice vem de `git ls-tree -r origin/main`, o `sh()` engole stderr, e
sem a ref no checkout o Map sai vazio. Mesma forma da §5 2026-08-11 (`|| true` deixando 4
required verdes sem validar nada).

## O que o #7429 acrescenta

Depois do #7424 restavam **dois** caminhos de `null` com a **mesma** frase:

```
docsDoDiffC1:      if (!base) return null;         // nao achei a BASE do diff
auditMudouDeCasa:  if (!vivos.size) return null;   // nao achei a REF origin/main
```

Consertos diferentes — o primeiro e `git fetch` da branch-base, o segundo e `fetch-depth`
ou refspec do remote. Quem lesse `sem base pra comparar` diante do segundo mexeria no lugar
errado. Cada porta passa a registrar o proprio motivo.

O bite-test `(e7)` tem uma propriedade que vale reusar: **um fixture so** exercita os dois
caminhos, porque a FLAG e que escolhe — sem `refs/remotes/origin/main`, `--todos` bypassa o
merge-base e chega ao indice vazio; sem a flag, para antes. E o `(e7-c)` compara os dois
motivos exigindo que **DIFIRAM**: sem ele, um fix que pusesse a mesma frase nas duas portas
passaria em todos os outros asserts e o campo viraria decoracao.

## A colisao que a coordenacao no inicio nao evitou

Avisei antes de pegar, medi que nao havia PR aberto no arquivo, criei worktree — e ela abriu
o #7424 **naquele intervalo**. O meu #7426 nasceu 8 minutos depois do dela, com escopo
identico. Fechei como duplicado.

Isso e a emenda da §5 2026-09-05 em acao: **checar colisao no inicio nao basta quando o outro
lado esta produzindo agora**. O que funcionou nao foi a checagem inicial — foi ter avisado por
mensagem *antes de produzir artefato*, o que fez as duas medicoes se encontrarem em minutos em
vez de no merge.

## As tres sondas que erraram, e por que nenhuma virou afirmacao

1. **Contagem de nome como proxy de comportamento.** Para conferir se ela mexera nas minhas
   funcoes, contei ocorrencias do identificador: `pointersOf2` 2 -> 3, `naoResolvidos` 5 -> 11,
   "MUDOU". Pelo **diff do corpo**, `pointersOf2` estava identico (a 3a era comentario) e os 6
   usos novos eram os early-returns dela + a saida texto. Tres falsos alarmes.

2. **Mutacao que caiu no early-return errado.** Para expor o bug, troquei `origin/main` no
   arquivo inteiro. O fluxo morreu no `if (!base) return null` e devolveu `medido=false` — pelo
   motivo errado. Um verde enganoso que quase virou "esta tudo certo". So peguei conferindo
   QUAL caminho fora exercido, e foi isso que levou o `(e6)`/`(e7)` a usarem fixture em vez de
   mutacao.

3. **`||` no fim de pipeline.** `grep ... | sed ... || echo "nao achou"` — o `||` se aplica ao
   `sed`, que sempre sai 0, entao nem o match nem o fallback apareceram. §5 2026-08-13(b).

Nenhuma virou afirmacao publicada. O padrao comum das tres: **a sonda respondia uma pergunta
vizinha com confianca**, e o que separou foi perguntar *qual caminho isto exercita?* antes de
ler o numero.

## Residuo

O `mudou_de_casa` segue **advisory**, e a razao que vale hoje e o contador `nao_resolvidos`
(117 pares em 35 linhas, 8 sem medicao alguma) — `0 acusacoes` com 8 linhas nao medidas nao e
`tudo medido`. Os 8 achados reais que o C1 passou a expor no modo texto precisam de redacao
nova pelo autor de cada linha; estao listados no handoff da sessao irma.
