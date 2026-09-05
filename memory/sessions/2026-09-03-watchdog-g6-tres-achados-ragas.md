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

---

## Desfecho da mesma tarde — sessão irmã (append, não reescrita)

> Esta seção foi acrescentada por outra sessão de 2026-09-03, depois do fechamento acima. Nada
> do que está escrito antes foi alterado: o retrato daquele momento era honesto e fica. O que
> mudou foi o mundo, em duas horas — e uma atribuição precisa de correção.

### O que mudou: [W] recarregou o crédito, e a medição pós-recarga é decisiva

`credit_balance_exhausted` (a causa do Achado 1, corretamente diagnosticada acima e ancorada em
L-OP-005) foi resolvido por [W] durante a tarde. Medido depois, com `update_baseline=false` para
não tocar em nada — run `33755023279`:

| métrica | referência 2026-07-01 | medido 2026-09-03 | delta |
|---|---|---|---|
| `faithfulness` | 1,0 | **1,0** | 0,00% |
| `answer_relevancy` | 0,851 | **0,851** | 0,00% |

`gate_status: pass` · `regressions: []`. **A referência de julho estava certa o tempo todo.** Os
64 dias do eixo 2 mediam ausência de revisão, nunca conteúdo inválido — o que fecha o veredito
(b) com prova, não só com a varredura de escritores.

### Correção de atribuição: o canary baseline NÃO dependia do CT 100

O parágrafo `⛔ Re-curar não é regravar` acima conclui: *"Nos dois casos a data só se move quando
houver medição confiável — e ela depende do CT 100"*. **Para o canary isso é falso**, e a
[sessão de 2026-08-31](2026-08-31-ragas-obra-parada-veredito-b.md) já havia refutado essa
atribuição na sua §Reconciliação: o canary é medido **no GitHub Actions**, e o CT 100 não
participa desse caminho. A consequência prática do erro foi concreta — levou a concluir que a
re-cura estava bloqueada, quando ela só dependia do crédito.

A metade da frase que valia para o `real-baseline` **segue valendo inteira**: aquele depende do
CT 100, e continua parado.

### Re-cura do canary baseline: executada pelo caminho sancionado

Autorizada por [W] depois de ver o antes→depois. Feita por `workflow_dispatch` com
`update_baseline=true` (o arquivo proíbe edição à mão na sua própria `_meta.description`), run
`33756454462` → [#6638](https://github.com/wagnerra23/oimpresso.com/pull/6638), mergeado
`15dbe59d11`. Diff medido com `--numstat`, não com regex sobre o texto: **`2 2`**, e as duas
linhas são os dois `last_updated`. Valores intactos — a medição de hoje não achou nada a
corrigir na referência.

Efeito medido rodando o watchdog na base já com o merge: o eixo 2 caiu de **2 🔴 para 1 🔴**, e
o `rc` do node **segue 1** pelo `real-baseline`. (O `rc` foi lido do `node` direto; num pipeline
`node … | grep`, o `$?` é do grep — §5 2026-08-13.)

### Enforcement medido: o G6 é advisory, e nenhum PR estava bloqueado por ele

Contra a proteção viva, lendo a **união** clássica ∪ rulesets (§5 2026-08-08, onde ler uma fonte
só produziu deadlock): `classic_protection.contexts` = 44 · `rulesets` = 1 adicional · união =
**45**, e `crons de governança vivos? (watchdog G6 · ADR 0317)` não estava entre eles. O job
também não tem `continue-on-error`, e isso está correto — advisory significa *fora do required*,
nunca *não pode ficar vermelho* (§5 2026-07-09). Registrado porque uma sessão irmã relatou o
vermelho como se reprovasse o PR dela.

### As 4 issues auto-abertas foram fechadas com recibo

#6491 · #6521 · #6536 · #6619 descreviam condição extinta. Cada uma recebeu comentário com o
`erro=` extraído do log, a tabela antes→depois e o ponteiro pro #6638 antes de fechar. Os
títulos "regrediu > 5%" das três primeiras seguem registrados como má descrição do que houve —
a progressão da instrumentação (#6518, #6540) já está explicada acima.

### O `real-baseline` fica vermelho, e a razão mudou

O que era *"causa NÃO medida"* passou a ser **hipótese nomeada por [W]**: perguntado sobre
re-curar os pisos, ele respondeu *"as antigas historicamente estão sempre erradas pois estou
trocando o layout"* e confirmou estar **reorganizando o corpus** que o `mcp_memory_documents`
indexa. Se procede, a queda de `context_recall` (0,3951 → 0,0314) e as duas semanas com
`no_context=51` são efeito da reorganização, não regressão da Jana — e o piso derivado em julho
descreve um corpus que não existe mais. Registrado no próprio arquivo em
`gaps_conhecidos._errata_2026_09_03_causa_nomeada_por_w`, **sem** tocar pisos nem data interna.

[W] havia escolhido re-curar os pisos hoje; não foi feito, e o motivo é que o insumo não existe:
sem CT 100 não há medição pós-reorganização, e derivar piso do histórico é exatamente o que ele
declarou não valer. Carimbar a data interna zeraria o relógio de 60d até novembro, silenciando
por dois meses a defasagem que ele acabou de confirmar ser real.

### O que esta sessão deliberadamente não fez

- **Não registrou silêncio.** `governance/cron-vermelho-esperado.json` segue `{"silencios": []}`.
  A medição desta seção concorda com a de cima: o silêncio alcança só o eixo 3.
- **Não tocou piso nem data interna** do `real-baseline` — e provou, rodando o watchdog depois
  da edição, que o conjunto de datas que ele enxerga é idêntico ao de antes (LC-22: mudança em
  artefato que a máquina lê se valida rodando a máquina, não revisando o texto).
- **Não mexeu no watchdog.** Ele acertou nos dois eixos, incluindo o eixo 3 — que era falha real
  e foi consertada na origem, não silenciada.

---

## Errata 2026-09-04 — o bloqueio caducou, o insumo foi produzido, e ele diz NÃO (append, não reescrita)

Sessão seguinte, provocada pelo mesmo vermelho do G6 em PRs novos ([#6705](https://github.com/wagnerra23/oimpresso.com/pull/6705), [#6711](https://github.com/wagnerra23/oimpresso.com/pull/6711)).
O veredito **(b)** desta página segue de pé e foi reconfirmado por varredura própria, não citado
de segunda mão: **31** arquivos citam o path, **6** casam primitiva de escrita, **6/6
falso-positivo** (todos gravam outro path — `Kernel.php` é comentário, `sdd-scorecard.mjs` grava
o scorecard, `reguas-workflow.test.mjs` grava fixture). Zero escritores automáticos. O dono do
inventário corrobora com contraste: [`MAQUINAS-INVENTARIO.md:595`](../reference/MAQUINAS-INVENTARIO.md)
marca este arquivo como `agente, script` e o irmão do canary como `ci`.

O que esta errata corrige são as afirmações de **bloqueio**; o que ela acrescenta é a **medição
que faltava**.

### O que caducou: as frases sobre o CT 100 (L78, L105, L156-157, L199)

Esta página afirma em presente que re-curar *"depende do CT 100, que segue fora"*. Medido hoje:

| sonda | 2026-09-03 | 2026-09-04 |
|---|---|---|
| `mcp.oimpresso.com/api/mcp/health` | `000` | **`200`** (controle `oimpresso.com/login` = `200`) |
| container `oimpresso-staging` | — | **Up 2 weeks (healthy)** — sobreviveu ao outage |
| crons do host (`crontab -l`, rc=0) | — | **os 2 intactos**: `0 6 * * 0` eval · `30 8 * * 0` publish |
| eixo 3 do watchdog | 23 ok · 1 🔴 canary | **24 ok · 0 🔴** (crédito recarregado) |

Nenhum insumo estava faltando hoje. Registrado porque afirmação de bloqueio em doc canon vira
instrução de desistência para a próxima sessão (§5 2026-09-01) — e esta página é o dono do tema.

### A medição que faltava: gold-set N=51, e ele REPROVA

Esta página fecha com *"[W] havia escolhido re-curar os pisos hoje; não foi feito, e o motivo é
que o insumo não existe"*. O insumo foi produzido — `jana:ragas-real-eval --json` (N=51 completo,
US$ 0,0867, `ran_at 2026-09-04T07:52:25-03:00`, `RC_ARTISAN=1`):

| métrica | julho/2026 (baseline) | 2026-09-04 (N=51) | piso vigente | Δ vs julho | vs piso |
|---|---|---|---|---|---|
| `faithfulness` | 0,6916 | **0,6282** | 0,65 | −9,2% | ❌ abaixo |
| `answer_relevancy` | 0,8039 | **0,7294** | 0,75 | −9,3% | ❌ abaixo |
| `context_recall` | 0,3839 | **0,3106** | 0,36 | −19,1% | ❌ abaixo |

`n_evaluated=51 · n_no_context=0 · n_synth_failed=0 · n_judge_failed=0 · 26 passed · 25 failed`,
das quais **9 com as três métricas zeradas** (não recuperaram nada). `gate_status: fail`.

Duas leituras, e elas são diferentes:

1. **O colapso catastrófico ACABOU.** `n_no_context` saiu de **51/51** (08-02 e 08-23) para **0**.
   O pipeline voltou a recuperar contexto — o que o `_alerta_2026_08_31_colapso_do_eval` descreve
   como estado agudo não é mais o estado de hoje.
2. **A degradação REAL persiste.** ~9% em faithfulness e relevancy, **−19%** em recall, reprovando
   os três pisos. Isso não é artefato velho: é qualidade da Jana abaixo da régua, medida.

### Por que a re-cura NÃO foi feita — e agora a razão é outra

Ontem o motivo era *falta de insumo*. Hoje o insumo existe e **diz não**:

- **Rebaixar os pisos** para acomodar 0,6282 / 0,7294 / 0,3106 é editar o baseline para ficar
  verde (§5 2026-08-26) — e seria pior que ontem, porque agora há prova de que a queda é real.
- **Atualizar só os números medidos** deixaria o arquivo registrando valores que reprovam os
  próprios pisos que ele é dono de definir (US-COPI-136) — incoerente.
- **Mover só a `gerado_em`** apagaria o vermelho do G6 até novembro sem tocar em nada do que ele
  marca. O watchdog lê a data **mais recente** de `CHAVES_DATA` no texto (`cron-watchdog.mjs:494`),
  então qualquer chave de data nova no arquivo teria esse efeito colateral.

Pisos, números e data interna seguem **intocados**. O vermelho do G6 continua sendo o marcador
correto de uma defasagem que agora está quantificada.

### O número que quantifica a hipótese de [W]

[W] atribuiu a queda à reorganização do corpus. Medido no staging:
`SELECT COUNT(*) FROM mcp_memory_documents` = **2.555**, contra os **1.153** que o próprio
`_meta.ambiente` declara para julho — **+121%**. O piso de julho foi derivado sobre um corpus com
menos da metade do tamanho do atual. A hipótese deixa de ser só relato e passa a ter ordem de
grandeza.

⚠️ E [W] **previu o resultado antes da medição** (*"acho que o baseline ainda vai continuar
errando"*), o que ficou registrado antes de o run terminar.

### Ressalva sobre um smoke N=5 desta sessão — não usar como evidência

Antes do gold-set, rodei `--sample-size=5` e ele deu `gate pass · context_recall 0,614 ·
n_no_context 0`. **Esse número é enviesado para cima e não deve ser citado:** `--sample-size` é
*slice das N primeiras* do gold-set, não amostra aleatória (a falha veio como `idx: 0`, a primeira
pergunta). As 5 primeiras são vizinhas, e o N=51 devolveu 0,3106 no mesmo dia. Fica registrado
para que a próxima sessão não o encontre solto e conclua que o recall recuperou.

### Efeito colateral introduzido nesta sessão, e mitigado

`JanaRagasRealEvalCommand::persistReport()` grava
`storage/app/governance/ragas-real-eval-latest.json` **incondicionalmente** (não olha
`--sample-size`), e `ct100-ragas-publish.sh:47` **prefere** exatamente esse arquivo. Os dois runs
manuais de hoje sobrescreveram o artefato que o transporte publica; sem log de fallback no
container, o publish de domingo 08:30 teria publicado um run de **sexta** como semana 09-06,
corrompendo a métrica de uptime que a série existe para medir.

Mitigado: o arquivo foi **renomeado** (não apagado) para
`ragas-real-eval-MANUAL-2026-09-04-n51.json.bak`. O contrato preferido volta a estar ausente, o
publish cairia em `FATAL ... Semana fica como GAP (inválida). Nada publicado` — downtime honesto —
e o eval de domingo 06:00 grava o dele normalmente.

### Aberto para [W] — a natureza do item mudou

O que estava aberto era *bookkeeping de artefato* (re-curar uma referência velha). O que está
aberto agora é **produto**: a Jana responde 25 de 51 abaixo da régua, com 9 respostas sem
recuperar contexto algum, num corpus que dobrou. Isso pertence a US-COPI-140 / ADR 0334, não ao
watchdog — o G6 apenas segurou o fio até aqui.

---

## Ratificação 2026-09-05 — [W] confirma (b); a varredura foi REFEITA, não herdada (append, não reescrita)

Sessão provocada pelo mesmo vermelho do G6, agora no run `33939624591` (PR #6790, que só tocava
`.md`). [W] **ratificou o veredito (b)** nesta data. As afirmações que o sustentam foram remedidas
em base fresca em vez de citadas desta página — herdar conclusão de doc canon sem re-medir é o que
a §5 2026-09-01 proíbe, e esta página é justamente o dono do tema.

### A varredura de escritores, contada de novo: 7 de 7 falso-positivo

| Etapa | N | Como |
|---|---|---|
| citam o path | **31** | `git grep -l -F "jana-ragas-real-baseline" origin/main` |
| casam primitiva de escrita | **7 de 31** | filtro `file_put_contents` / `writeFileSync` / `fwrite` / `Storage::put` / `->put(` / `tee` / redireção |
| escrevem de fato | **0 de 7** | auditado sítio a sítio |

`Kernel.php` = comentário · `sdd-scorecard.mjs` grava o scorecard (`OUT`, L599) · `reguas-workflow.test.mjs`
grava fixture · `ct100-jana-evals.sh` grava o report (`tee "$RAGAS_OUT"`) · os outros 3 são markdown
citando primitivas em prosa. Varrido também o path montado por **constante**, que o basename sozinho
não pega: `JanaRagasRealEvalCommand::BASELINE_PATH` aparece em `:252` dentro de `resolveThresholds()`
como `File::exists` + `File::get` — **lê**; a única escrita do comando é
`File::put(…'ragas-real-eval-latest.json')` em `:529`, outro path. `EvalReconciler::BASELINE_PATH`
aponta pro irmão canary, arquivo diferente.

**Sem escritor, não é (a).** Terceira contagem independente (08-31, 09-04, hoje) com o mesmo resultado.

### O teste da tautologia — exigido ANTES de cogitar regravação, e ele REPROVA a hipótese

A dúvida levantada era se este baseline cai na família do `jana:drift-sentinel` (§5 2026-07-17 —
alarme tautológico cujo `--update-baseline` está bloqueado por guard). **Não cai**, e a prova é
estrutural, não o comentário do código:

- São instrumentos **distintos**: o drift-sentinel compara contra
  `Modules/Jana/Tests/Feature/Ai/fixtures/baseline-responses.json`, não contra este arquivo.
- Em `JanaRagasRealEvalCommand` o contexto vem do retriever real (`:359 $kb->retrieve`) e a resposta
  da síntese real (`:376 $kb->synthesize`); os três scores (`:393-395`) consomem esses. Só o
  `scoreContextRecall` toca `groundTruth`, e **corretamente** — recall é, por definição, "o contexto
  recuperado cobre a verdade?".
- E **discrimina empiricamente**: 26 pass / 25 fail na run de 04/09. O sintoma da tautologia
  (1,0 uniforme × 51, que foi o que condenou o drift-sentinel) não aparece.

Corolário que **reforça** a decisão de não mexer, em vez de afrouxá-la: como o instrumento mede de
verdade, os números que ele produziu são reais — regravar aqui seria pior do que num alarme quebrado.

### Medição de hoje (main `22aa822b89`, worktree limpo, `rc` lido do node direto)

```
🩺 24 crons agendados · 24 vivos · 0 ⛔ não medidos · 0 🔴 mortos
🎯 24 medido(s) de 24 · 24 ok · 0 🔴 falhando
📦 18 artefato(s) de estado com data interna (de 268) · limite 60d · 1 🔴 parado(s)
🔴 governance/jana-ragas-real-baseline.json — parado há 66d (última data interna: 2026-07-01)
rc=1
```

Os eixos 1 e 3 (cron e sucesso) estão **verdes** — o canary saiu do vermelho depois da recarga de
crédito de 03/09. Sobrou o eixo 2, e sobrou sozinho.

**Enforcement, medido nesta data** (união clássica ∪ rulesets, porque ler uma fonte só já produziu
deadlock em 2026-07-02): `classic 44` + `rulesets 1` = **45 required**, e
`crons de governança vivos? (watchdog G6 · ADR 0317)` **não estava entre eles** — nenhum PR foi
bloqueado por este vermelho. (Fato datado; quem é required tem dono único em
[`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json).)

### O que esta sessão NÃO fez

- **Não tocou o baseline** — nem pisos, nem `gerado_em`, nem chave nova. A razão é a que a errata de
  04/09 já estabeleceu e a de hoje não muda: o insumo existe e **diz não**. Rebaixar piso para
  acomodar 0,2954 é editar o baseline para ficar verde (§5 2026-08-26); mover `gerado_em` — a chave
  PT-BR que o watchdog lê em `CHAVES_DATA` — zeraria o relógio de 60d até novembro, apagando o único
  marcador de uma defasagem já confirmada por medição.
- **Não registrou silêncio** e **não mexeu no watchdog**. Ele acertou nos três eixos.
- **Não abriu análise paralela**: o tema já tinha dono (esta página, mais as sessões de 08-31, 09-02,
  09-03 e 09-04), e o registro de hoje é append nela, não um sétimo arquivo sobre o mesmo assunto.

### O que segue aberto, e é de [W]

Inalterado desde 04/09, e é **produto, não bookkeeping**: aceitar 0,2954 como piso novo (assumir a
degradação) **ou** manter os pisos de julho e tratar o vermelho como dívida de retrieval a pagar em
US-COPI-140 / ADR 0334. O gargalo tem nome medido — o corte de 400 chars do início do doc em
`KbAnswerService::renderFontes`, que a US-COPI-133 nomeia desde 2026-07-12.

Resíduo honesto que ninguém fechou: as três semanas com `n_no_context=51` (08-02, 08-23, 08-30)
seguem **sem causa**; nem a flag nem o corte as explicam, e o `laravel.log` já não cobre aquela
janela, o que torna o mecanismo indecidível a posteriori.
