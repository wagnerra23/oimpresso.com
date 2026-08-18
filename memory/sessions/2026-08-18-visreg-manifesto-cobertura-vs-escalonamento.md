---
date: "2026-08-18"
hour: "19:30 BRT"
duration: "0.7h"
topic: "Cobertura do manifesto do visual-regression vs o escalonamento do classificador"
authors: [W, C]
outcomes:
  - "Hipotese de bloqueio sistemico REFUTADA por medicao — 4 dos 5 PRs atingidos mergearam"
  - "Assimetria real medida: manifesto 18 telas vs 37 escaladas em mudanca de componente compartilhado"
prs: [5918]
us: []
related_adrs: ["0108-regressao-visual-pest-browser-tier-2"]
---

# Sessão — o `visual-regression` bloqueia quem toca componente compartilhado?

## TL;DR

A onda 1 da paridade da Jana bateu no fail-closed *"Tela sem contrato visual"* com **29 telas**.
Levantei a hipótese de que **qualquer** PR que tocasse componente compartilhado estaria bloqueado
por construção. [W] mandou investigar a fundo. **A hipótese está REFUTADA**: dos 5 PRs atingidos
desde o nascimento do gate, **4 mergearam**. O que sobra é uma **assimetria real e cara**, não um
bloqueio — e ela morde raro.

## O mecanismo, medido

`scripts/governance/ui-impact.mjs` classifica por **path**, e não distingue mudança aditiva de
destrutiva (é conservador por desenho):

```
resources/js/Components/PageHeader/PageHeader.tsx | global   | frontend-compartilhado
resources/js/Pages/Jana/Pro.tsx                   | targeted | page-inertia
```

| medida | valor |
|---|---|
| telas escaladas ao tocar um componente compartilhado | **37** |
| entradas em `tests/Browser/visreg-screens.json` | **18** |
| telas sem contrato visual no meu caso | **29** |
| nascimento do fail-closed | [#5572](https://github.com/wagnerra23/oimpresso.com/pull/5572), 2026-08-11 |

O gate é **required**, então o fail-closed para o merge.

## O que REFUTOU a hipótese de bloqueio sistêmico

Busca por PRs que receberam o comentário desde 2026-08-11 — **5 no total**, e os desfechos são
diferentes entre si:

| PR | como saiu | arquivos de baseline/manifesto no diff |
|---|---|---|
| [#5891](https://github.com/wagnerra23/oimpresso.com/pull/5891) | **merged** | 2 — 1 `.snap` + a entrada de `Jana/Pro` no manifesto |
| [#5897](https://github.com/wagnerra23/oimpresso.com/pull/5897) | **merged** | 14 — regenerou as baselines dos fluxos de Compras |
| [#5865](https://github.com/wagnerra23/oimpresso.com/pull/5865) | **merged** | 0 — o check terminou `success` após rerun |
| [#5756](https://github.com/wagnerra23/oimpresso.com/pull/5756) | **fechado sem merge** | 0 |
| [#5918](https://github.com/wagnerra23/oimpresso.com/pull/5918) | resolvido reduzindo escopo | 0 |

**Nenhum deles precisou gerar as 29.** O caminho que funciona é: ou a tela do PR entra no
manifesto (o #5891 fez isso com UMA), ou o PR reduz o escopo para `targeted`.

E a frequência: **4 de 293 commits** em `main` desde 11/08 tocaram `resources/js/Components/**`
— **1,4%**. O caso é raro por construção, porque poucos PRs mexem em componente compartilhado.

## O que SOBREVIVE como achado

**A assimetria 18 × 37 é real e o preço é desproporcional.** Quem toca um componente
compartilhado precisa que **todas** as telas escaladas tenham baseline — e 29 não têm. Como o
próprio comentário do gate avisa, o modo update regenera ~68 `.snap`, e copiar os outros
*"muda em silêncio a referência de telas que seu PR não toca"*. Ou seja: o caminho "gere as
baselines" é **desaconselhado pelo próprio gate** para telas que não são as suas.

Consequência prática, e ela é a que interessa: **o custo recai sobre quem melhora o Design
System**. Na onda 1, o slot `titleBadge` (aditivo, opt-in, zero mudança de render para as 30
telas existentes) foi **removido por preço, não por mérito** — a tag `UPGRADE` foi para o
`leading`, que já existia, e o diff voltou a `targeted`. O registro do porquê está no
comentário do `leading` em `Pro.tsx` e no `Pro.charter.md`.

## O que NÃO proponho, e por quê

**Ensinar o classificador a distinguir mudança aditiva de destrutiva.** É a tentação óbvia — uma
prop nova opcional não muda o render de ninguém, e isso é decidível em alguns casos. Mas o
predicado geral ("esta mudança altera o render de algum consumidor?") é **semântico**, e o §5
tem 4 lápides medidas de guard sintático que reprovou o legítimo (allowlist-de-pasta 06-30 ·
`@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 100% FP 07-26). Um classificador que
errasse para menos deixaria passar regressão visual real — que é exatamente o que o gate existe
para pegar. **Conservador está certo aqui.**

O que teria valor é **fechar a lacuna do manifesto** (18 → o universo escalado), o que é
trabalho de plataforma com custo próprio, não conserto de um PR de tela. Fica como decisão [W].

## Dois números menores, com o denominador declarado

- **`timeout-minutes: 15`**: em 100 runs desde 11/08, **2** estouraram o teto do job (aparecem
  como step `cancelled` dentro de run `failure`). Dos 25 runs `cancelled`, **23 são curtos
  (<14min)** = `cancel-in-progress` por novo push, que é o comportamento correto. Corrigi aqui
  minha própria leitura: cheguei a reportar "3 timeouts hoje" a partir dos runs cancelados, e a
  maioria deles era push meu cancelando o anterior.
- **`main` desde 11/08**: **2 runs, ambos `failure`**. São dois — não sustenta conclusão
  sozinho, e fica registrado com o denominador à mostra em vez de virar "main está vermelho".

## Achado 2 — **14 de 71 baselines divergem SEM nenhuma mudança de código**

> Descoberto depois, ao executar o modo update pra atualizar UMA baseline (a `Jana/Pro`, com
> F1.5 aprovado). [W], ao ver que eu ia deixar os outros 17 snapshots regenerados de fora com
> uma nota de rodapé: **"não pode mudar em silêncio"**. Está certo — e a medição abaixo mostra
> que é pior do que parecia.

O modo update (`workflow_dispatch`, run 32177133506) regenerou **18 de 71** snapshots. Cruzando
cada um com o histórico de código:

| baseline divergente | mudança de código que explica |
|---|---|
| `Jana/Pro` | este PR (#5918) — F1.5 aprovado |
| `Jana/Chat` · `Jana/Memoria` | onda 2 (#5919), mergeada |
| `Produto/Unificado` | #5906 |
| **`Clientes`** · **`Compras`** · `clientes·estado_default` · `clientes·estado_empty` · `compras·estado_default` · 6× `compras·abrir_*` · 3× `financeiro_unificado·*` | **NENHUMA** |

**4 explicáveis · 14 sem causa de código.** Isso é **20% do parque de baselines**.

### A prova de que não é código

As baselines de `Clientes` e `Compras` foram regeneradas em 2026-08-18 pelo
[#5897](https://github.com/wagnerra23/oimpresso.com/pull/5897) (que mexeu em **tokens**, e por
isso tocou todas). Desde aquele commit até `origin/main` de hoje:

```
git diff --name-only 88fa460313 origin/main -- 'resources/css/**' 'resources/js/Components/**'
→ (vazio)
```

Nenhum CSS, nenhum componente compartilhado. Os únicos arquivos de UI que mudaram no intervalo
são de `Pages/Jana/**` e do Produto — que são exatamente os 4 explicáveis.

Ou seja: **a baseline foi gerada, nada que a afeta mudou, e o render de hoje não bate com ela.**

### Por que isto importa mais que o Achado 1

O gate é **required**. Se 20% das baselines não reproduzem, então:

1. **todo PR de escopo global entra vermelho** por telas que ele não tocou — e o autor é
   empurrado a "regenerar tudo", que é justamente o que o gate avisa pra não fazer;
2. **regressão visual real fica indistinguível de ruído** — o sinal que o gate existe pra dar
   perde valor na proporção do ruído;
3. o hábito de regenerar em lote **normaliza** mudar baseline de tela alheia — o silêncio que
   [W] apontou.

### O que NÃO afirmo

**Não afirmo qual é a fonte do não-determinismo.** O [#5852](https://github.com/wagnerra23/oimpresso.com/pull/5852)
já congelou o relógio ("regenera 24 baselines com o relógio congelado"), então tempo ao menos
em parte já foi endereçado. Candidatos não medidos: dado semeado variável, ordem de query sem
`ORDER BY`, fonte/antialiasing do runner, animação sem `prefers-reduced-motion`, lazy-load.
Dizer qual é sem medir seria a classe de erro que este mesmo documento registra acima.

O caminho de medição é barato e determinístico: **rodar o modo update duas vezes seguidas no
MESMO commit** e diffar os snapshots. O que divergir entre duas execuções idênticas é ruído por
definição — e aí o conjunto que sobra é o drift real.

### O que fiz aqui, e o que deixei

Peguei **1** snapshot (o da `Jana/Pro`, com F1.5). Os outros 17 **não entraram** no #5918 — não
por descuido, mas porque não são daquele PR. Ficam no
[#5932](https://github.com/wagnerra23/oimpresso.com/pull/5932) (e há um irmão, #5933, de outro
run), abertos, para decisão [W] — **e agora com a medição escrita**, que é o que faltava pra não
ser silêncio.

---

## Método — o que eu errei no caminho

1. **Afirmei bloqueio sistêmico a partir do meu próprio tropeço.** É a classe do §5 de
   2026-08-08: *"diagnosticar a saúde de um gate a partir do obstáculo que te barrou"*. O que me
   barrou era **um** caminho; a pergunta certa era quantos PRs de fato travaram — e a resposta
   (4 de 5 mergearam) inverteu o diagnóstico.
2. **Li `6 failure / 4 success` em `main`** sem filtrar por data — aqueles runs eram anteriores
   ao gate. Com o filtro, são 2.
3. **Tentei medir o impacto revertendo o arquivo no working tree**, mas o `ui-impact` compara
   **commits**. O teste não valia nada até eu commitar a reversão.
