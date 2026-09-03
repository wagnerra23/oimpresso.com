---
date: "2026-09-03"
topic: "Watchdog G6 vermelho: os 3 achados separados nos casos (a)/(b)/(c) — e o silêncio que resolve 1 de 3"
authors: [C]
related_adrs:
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
us: [US-COPI-145, US-COPI-140]
---

# Watchdog G6 — os 3 achados, e por que o registro de silêncio não apaga o vermelho

## TL;DR

- Os 3 achados são **de dois eixos diferentes** do watchdog, e isso decide o que cada um aceita
  como conserto. Achado 1 = **eixo 3** (run falhou). Achados 2 e 3 = **eixo 2** (artefato velho).
- **Achado 1 tem causa raiz medida e já registrada:** a conta OpenAI está sem crédito desde
  2026-08-31 ~20:02 ([L-OP-005](../requisitos/Jana/LICOES-OPERACAO.md)). O canary é a 4ª vítima
  da mesma causa, não um defeito próprio. É **US-COPI-145 — decisão [W]**, fora do repo.
- **Achados 2 e 3: veredito (b) já estava estabelecido** em [2026-08-31](2026-08-31-ragas-obra-parada-veredito-b.md),
  por varredura contada — **zero escritores automáticos** nos dois. Refiz a varredura e ela bate.
- **O achado novo desta sessão:** `governance/cron-vermelho-esperado.json` **não deixa o gate
  verde**. O silêncio alcança só o eixo 3; provado rodando, não lendo — com o silêncio ativo o
  canary vira 🔇 e `0 🔴 falhando`, e o **exit continua 1** pelos 2 baselines do eixo 2.

## Medição (main fresco `8e63a2606d`, worktree limpo — não citado de segunda mão)

```
🩺 cron-watchdog — 24 crons agendados · 24 vivos · 0 bootstrap · 0 ⛔ não medidos · 0 🔴 mortos
🎯 sucesso — 24 medido(s) de 24 · 23 ok · 1 🔴 falhando
🔴 jana-ragas-canary.yml — última run agendada CONCLUIU 'failure' (2026-09-03T10:25:13Z)
📦 entrega — 17 artefato(s) com data interna (de 267) · limite 60d · 2 🔴 parado(s)
🔴 governance/jana-ragas-baseline.json      — parado há 64d (data interna 2026-07-01)
🔴 governance/jana-ragas-real-baseline.json — parado há 64d (data interna 2026-07-01)
EXIT=1
```

## Achado 1 — o canary: causa medida, e não é (a), (b) nem (c)

As 3 falhas **não têm a mesma causa aparente** — medi as três em vez de assumir:

| run | data | 429 | `erro=` | issue |
|---|---|---|---|---|
| 33499344761 | 2026-09-01 | 0 | — (`FAIL — 2 regressão(ões) > 5.0%`) | #6491 · #6521 |
| 33618372236 | 2026-09-02 | 20 | *ausente* | #6536 |
| 33744191426 | 2026-09-03 | 20 | **`credit_balance_exhausted`** | #6619 |

A progressão não é do defeito, é da **instrumentação**: o [#6518](https://github.com/wagnerra23/oimpresso.com/pull/6518)
(01/09) fez o stderr do artisan sempre aparecer, e o [#6540](https://github.com/wagnerra23/oimpresso.com/pull/6540)
(02/09) fez o judge logar `error.code` e a issue distinguir "não medi" de "regrediu". Por isso
09-01 parecia regressão, 09-02 mostrou o 429 sem código, e só hoje o código apareceu.

**Causa raiz:** `credit_balance_exhausted` é billing. Bate com **L-OP-005** (2026-09-02), que já
registrou que o crédito acabou em **2026-08-31 ~20:02** e derrubou chat, sugestão de metas, PR UI
Judge e Daily Brief juntos. Confirmação cruzada independente: a última run **verde** do canary é
`2026-08-31T12:13Z` (= 09:13 BRT, **antes**), e a primeira falha é 01/09 (**depois**).

Ou seja, o canary **não** é um 4º caso a diagnosticar: é a 4ª manifestação de uma causa já
diagnosticada, cujo dono é **US-COPI-145 (credencial/billing = [W])**.

## Achados 2 e 3 — os baselines: (b), confirmado

A varredura por **sítio de escrita** (não por basename) devolve **zero escritores automáticos**:

| Arquivo | Quem escreve |
|---|---|
| `jana-ragas-baseline.json` | só `jana-ragas-canary.yml` em **`workflow_dispatch` + `update_baseline=true`** (o cron nunca passa a flag); `EvalReconciler` é **leitor** |
| `jana-ragas-real-baseline.json` | **ninguém** — `JanaRagasRealEvalCommand` **lê** (é o dono dos pisos, US-COPI-136) e escreve em `storage/app/...` |

Sem escritor, **não é (a)**. É **(b)** — referência curada por decisão —, o mesmo desfecho do
precedente [#4822](https://github.com/wagnerra23/oimpresso.com/pull/4822) (os 5 scorecards).

⛔ **Re-curar não é regravar.** No `real-baseline`, "atualizar" significaria rebaixar
`thresholds_regressao` para acomodar o colapso de `context_recall` (0,40 → 0,03) que o próprio
arquivo registra em `_alerta_2026_08_31_colapso_do_eval` — editar o baseline para ficar verde
(§5 2026-08-26). No canary baseline, com o juiz mudo os scores são `0.0`, e o próprio workflow
avisa que gravá-los **destruiria a régua**. Nos dois casos a data só se move quando houver
medição confiável — e ela depende do CT 100, que segue fora (medido: `000`, controle `200`).

## O que fiz

**Um conserto, pequeno, dentro do repo** — commit `3cd8ff78a9`, 2 arquivos, +4/−2:
a tabela que a issue do canary imprime não listava `credit_balance_exhausted`, então o código
que de fato apareceu caía em *"outro → ler o stderr completo"* e mandava o operador investigar
do zero uma causa já conhecida. O projeto já trata os dois códigos como a mesma família em
`HealthCheckCommand::QUOTA_CODIGOS_SEM_CREDITO`; o comentário do judge passa a **apontar** para
essa constante em vez de repetir a lista (§5 2026-07-17 — um dono só por fato).

Provas locais (`php` e CT 100 fora; veredito é do CI): teste de identidade (reverter devolve o
YAML byte-idêntico a `origin/main`), YAML parseia com `js-yaml`, BOM=0, CRLF 389/389 idêntico ao
blob (pré-existente).

## O que deliberadamente NÃO fiz

- **Não registrei silêncio.** Além de ser ato de [W] (o merge é a aprovação), medi que ele não
  resolve: zera o eixo 3 e deixa `EXIT=1` pelo eixo 2.
- **Não toquei baseline nenhum** — nem data, nem piso.
- **Não mexi no watchdog.** Ele está certo nos 3 eixos: mediu, acusou e mandou investigar.
- **Não consertei o 429** — é billing, fora do repo.

## Aberto para decisão [W]

1. **US-COPI-145 — crédito do provedor.** Enquanto não voltar, o canary falha todo dia, e a Jana
   segue muda (L-OP-005). É a causa de 1 dos 3 achados e de vários sintomas fora deste tema.
2. **Os 2 baselines (b).** Re-curar depende de medição confiável → depende do CT 100.
3. **Silenciar ou não o canary.** Só o eixo 3 aceita; o vermelho do G6 **não** apaga com isso.
   Silenciar torna o relatório mais legível (a falha vira 🔇 com razão e prazo) mas não muda o
   exit — e, com a causa já nomeada em L-OP-005, o valor é de bookkeeping, não de diagnóstico.

## Estado MCP no fechamento

**Não consultado — MCP inacessível.** `mcp.oimpresso.com/api/mcp/health` → **000** com controle
positivo `oimpresso.com/login` → **200**. `whats-active`, `cycles-active` e `my-work` rodam
contra o CT 100, que segue fora desde ~2026-08-28. Substituto medido: `gh pr list` não mostra
nenhum PR aberto tocando ragas/canary/watchdog (zero colisão detectável por essa via).

## Refs

- [session 2026-08-31](2026-08-31-ragas-obra-parada-veredito-b.md) — veredito (b) e a varredura original
- [session 2026-09-02](2026-09-02-ragas-real-colapso-diagnostico-bloqueado-ct100.md) — colapso do eval real
- [handoff 2026-09-02 20:12](../handoffs/2026-09-02-2012-ct100-retorno-pos-outage-preparado.md) — retorno do CT 100
- L-OP-005 em [LICOES-OPERACAO.md](../requisitos/Jana/LICOES-OPERACAO.md) · ADR 0317 · ADR 0318
