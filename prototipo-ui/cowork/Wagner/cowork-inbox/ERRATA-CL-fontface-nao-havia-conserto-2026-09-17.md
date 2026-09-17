# ERRATA — o "conserto das @font-face" que eu anunciei não existiu

**De:** [CL] · **Data:** 2026-09-17 · **Corrige:** o que escrevi no PR #7456, no `PEDIDO-DS-tokens-fg-e-manifest-2026-09-17.md` e no `github.md` do projeto DS

---

## O que eu afirmei, em três lugares

Ao empurrar os 8 tokens ontem, escrevi que o push tinha trazido "de carona" um conserto nas `@font-face`:

> *"O do repo ainda tinha os pesos 500/600/700 do IBM Plex Sans apontando para `ibm-plex-sans-400.woff2` — montar sobre ele reintroduziria esse bug no espelho vivo. Por isso o diff tem 11 linhas e não 8."*

Disse isso no commit do #7456, no corpo daquele PR, no pedido que te mandei hoje de manhã, e no `## Last sync` do `github.md` do DS — que é **proveniência**, ou seja, a próxima sessão leria como fato estabelecido.

**É falso.** Não houve conserto nenhum.

---

## O que foi medido

**(a) No espelho do repo, os arquivos não existem.**

```
git ls-files prototipo-ui/design-system/assets/fonts/
  ibm-plex-mono-400.woff2
  ibm-plex-mono-500.woff2
  ibm-plex-mono-600.woff2
  ibm-plex-sans-400.woff2      ← só o 400
```

As três linhas que eu escrevi apontam para `ibm-plex-sans-{500,600,700}.woff2`, que **não estão lá**. Consequência: o preview servido desse diretório (ADR 0401 E2) não carrega a fonte e cai no fallback do `font-family`. O estado anterior — três pesos caindo no arquivo 400 — era feio e **funcionava**.

**(b) No Cowork os três existem, mas são cópias do 400.**

Esse pedaço não é meu: é da sessão irmã, no **PR #7461**, que recusou o handoff do DS por R4 e mediu — mesmo `sha256`, **45.712 B** os quatro. Nas palavras daquele PR: *"o DS não tem esses pesos de verdade"*.

**Juntando (a) e (b):** o DS não tem os pesos 500/600/700. Apontar os três para o 400 é a forma **correta** nesse cenário. Eu vi o padrão `peso 500 → arquivo 400`, reconheci como bug conhecido, e **não conferi se o alvo existia**.

---

## O que foi feito

- **PR #7462** reverte as 3 linhas no repo. Os 8 tokens seguem intactos (conferido: 8), `ds-mirror-drift` 0, `cowork-ssot-guard` OK.
- O `github.md` do DS recebeu **errata datada ao lado do bullet falso** — o texto original fica, porque é registro do que foi afirmado naquele dia; o que muda é a nota de correção embaixo.
- **Os 3 arquivos NÃO foram trazidos do Cowork**, de propósito: seriam três cópias byte-idênticas do 400, exatamente a duplicata que o R4 proíbe e que o #7461 acabou de recusar. A desduplicação é da origem, e já está pedida lá.

Fica uma divergência de 3 linhas entre repo e DS vivo. Ela é **cosmética** — os dois lados renderizam o peso 400 nos três casos — e desaparece quando a origem desduplicar.

---

## Uma correção ao que eu te mandei hoje de manhã

No `PEDIDO-DS-tokens-fg-e-manifest-2026-09-17.md` eu listei, como algo que você deveria saber, que *"o scaffold do push foi o arquivo do handoff 22 (leitura do vivo), então o vivo manteve os pesos certos e o repo recebeu o conserto"*. **Ignore essa frase.** O resto daquele documento segue de pé — os cinco veredictos, o `-fg` pela ADR UI-0033, o controle positivo do sentinela, os 8 tokens stale no manifest.

---

## Estado dos 8, medido agora

| onde | resultado |
|---|---|
| **DS vivo** (`019dd02f`) | **8 de 8 novos** — o push de ontem sobreviveu |
| **`_ds/` do projeto de telas** (`019dcfd3`) | **8 de 8 velhos** — o bind não foi refrescado |
| **repo** (`main`) | correto desde o #7456 |

O refresh que [W] rodou foi no **projeto DS**, e o #7461 mostra por que não moveria nada: aquele pacote não trazia conteúdo novo — 249 arquivos idênticos e 2 alterados, sendo os 2 os **nossos próprios pushes de ontem voltando de carona**.

**O que move os 8 no seu render é o re-bind dentro do projeto de telas** (`019dcfd3`), que é quem hospeda o `_ds/`. Enquanto isso não acontecer, o tweak `git` segue sendo o único jeito de ver os valores novos aí — exatamente como você disse.

---

## Placar

Somando hoje, você me corrigiu **três vezes** e todas procederam. Esta quarta correção não é sua — é minha sobre mim, e veio de ler o PR da sessão irmã. As três medições que a sustentam (`git ls-files`, o `sha256` das fontes, o diff do #7456) são de três minutos de trabalho que eu não fiz ontem antes de chamar aquilo de conserto.
