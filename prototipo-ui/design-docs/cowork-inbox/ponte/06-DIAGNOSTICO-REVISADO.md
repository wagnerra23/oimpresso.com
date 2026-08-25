---
sessao: "06"
titulo: Diagnóstico REVISADO — o que a medição do [CL] derrubou
autor: "[CC]"
criado: 2026-08-23
substitui: 05-DIAGNOSTICO-PRODUCAO.md (hipóteses H1–H6, escritas SEM medição)
fonte_dos_numeros: aferição do [CL] no main, mesma data — reportada no cabeçalho de _pedido-CL-instrumentos.md
---

# Diagnóstico revisado

## O que a medição derrubou

O `05-DIAGNOSTICO` foi escrito por leitura de arquivo, sem número. A medição chegou e **falsificou a hipótese central**. Registro sem maquiar:

| Hipótese minha | O que eu afirmei | O que foi medido | Veredito |
|---|---|---|---|
| **H1** gargalo humano: charter nunca vira `live` | "charters de 11/07 ainda draft — seis semanas" | **54 charters `status: live`** (48 com sinal no gate) | **FALSA** |
| **H4** advisory eterno: nada trava | "toda catraca se declara não-bloqueante" | **46 contexts required com `enforce_admins`** | **FALSA** |
| **P.19/P.20** talvez não haja deploy | "é possível que o merge aconteça e o deploy não" | **deploy contínuo** | **FALSA** |
| **H3** o trio não fecha | 103 telas sem `casos.md` | **readiness 29/54 prontas** → 25 não prontas | **confirmada, e agora nominal** |
| **H2** "pronto" não é binário | "nenhum arquivo diz que a tela está em produção" | `charter-live-signal.mjs` **existe e é required** | **FALSA** — eu ia propor `PRODUCAO.md` duplicando o dono |

**Cinco das seis conclusões do 05 estavam erradas.** A causa: eu diagnostiquei por leitura de arquivo e chamei de diagnóstico. Isso é exatamente o que meu próprio V.06 pune — número afirmado sem base.

## A contradição que precisa de resposta sua

Você disse: *"nunca consegui colocar em produção nenhuma tela."*
A medição diz: **54 charters `live`, 48 com sinal de produção, deploy contínuo.**

As duas coisas não podem ser verdade ao mesmo tempo. Três leituras possíveis — **só você sabe qual**:

1. **As telas estão em produção e o registro não te alcança.** O gate marca `live`, o deploy sobe, e você não tem uma tela onde ler "isto está no ar". Aí o problema é de **visibilidade**, não de pipeline.
2. **`live` é uma afirmação, não um fato.** 48 dependem de sinal — e o `route-hits.json` **expirou em 25/07**. Se muitas dessas 48 se apoiam no ledger vencido, o número é herdado, não medido. É o R.03 do pedido de instrumentos: quantas por `prod-flags`, quantas por ledger, quantas por `smoke:` datado.
3. **"Em produção" pra você significa outra coisa** — a Larissa usando no balcão, não o código no servidor. Nesse caso o gate mede deploy e você quer medir **adoção**, e nenhum instrumento do repo mede isso hoje.

**R.03 responde a leitura 2 em uma rodada.** As leituras 1 e 3 são definição, e a definição é sua.

## A causa real, com o que sobrou

Não é trava. É **cegueira instrumentada**:

| Evidência medida | O que significa |
|---|---|
| lane do Ponto roda **11 de 37** testes | 26 testes existem e nunca correram por PR — a allowlist esconde |
| `MultiTenantIsolationTest` com assert que nunca reprova | um teste **Tier 0** é decoração; o verde dele não vale nada |
| `route-hits.json` expirado desde **25/07** | o sinal de produção envelhece **calado** |
| readiness **29/54** | 25 telas param por trio, não por qualidade |

O pipeline **trava** (46 required). O que ele trava está **mal medido**. Uma tela pode atravessar 9 portões verdes sem que nenhum deles tenha olhado o que importa — e é isso que produz a sensação de "nada chega", com todo indicador verde.

## O que muda no plano

| Arquivo | Estado |
|---|---|
| `05-DIAGNOSTICO-PRODUCAO.md` | **superado** — vale como registro do erro, não como diagnóstico |
| `_pedido-CL-instrumentos.md` | **é a resposta certa** — R.01/R.02/R.03 + C.01–C.05 atacam a cegueira, não a trava |
| `01-LISTA-COMPLETA.md` §7.08 "flip para required" | **obsoleto** — já há 46 required |
| `04-PENDENTES.md` | reescrito: 7.08 sai, R.03 entra no topo |
| S9 (cobertura) | inalterado — H3 é a única hipótese que sobreviveu, e é a fila do readiness |

## Ordem agora

1. **R.03** — as 48 por via. Diz se `live` é fato ou herança. Uma rodada.
2. **R.01** — a lista nominal das 25 não-prontas. É a fila de trabalho real.
3. **C.01** — sentinela de allowlist. Mata o 11-de-37.
4. **C.03** — anti-tautologia no `MultiTenantIsolationTest`.
5. **Você responde:** "em produção" é deploy ou é a Larissa usando?

**A pergunta 5 vale mais que as quatro medições.** Se for adoção, o repo inteiro mede a coisa errada — e isso é uma decisão de instrumento, não de tela.
