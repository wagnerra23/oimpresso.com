---
date: "2026-08-08"
hour: "19:36 BRT"
topic: "O CI não estava saturado: promover 3 jobs a required no mesmo commit que removeu o `paths:` deixou 4 PRs esperando checks que nunca iam nascer — 2 dias de deadlock"
authors: [C, W]
prs: []
us: []
outcomes:
  - "Causa raiz PROVADA: o commit 9199a82a12f (05/08, ADR 0370, PR #5318) removeu o `paths:` de module-surface.yml e catalog-graph.yml E promoveu 3 jobs deles a required no MESMO commit. PRs abertos antes disso tiveram os check-runs computados quando ainda havia filtro de path — os jobs nunca nasceram naquele SHA, e agora são exigidos. `Expected — waiting for status` permanente."
  - "Fix aplicado e provado em 4 PRs (5077, 5068, 5255, 5119): `gh pr update-branch` traz os workflows novos para a branch e os 3 checks nascem com o nome CORRETO. Antes: 3 required ausentes cada. Depois: 0 falhas, CI processando."
  - "A prova do mecanismo veio de um controle negativo: disparar `catalog-graph.yml` por workflow_dispatch na branch VELHA gerou o check com o nome ANTIGO — `catalog.json == SCOPEs + Classes B (advisory)` — enquanto o required exige o nome sem o sufixo. A branch velha carrega o workflow velho; por isso dispatch não resolve e update-branch resolve."
  - "Minha primeira hipótese (saturação de concorrência) foi REFUTADA por medição própria: o CI leva 48min de mediana por PR (medido em 9 PRs, 977 check-runs), a fila drenou sozinha de 378 para 150 durante a sessão, e os PRs de hoje fecharam com 0 pendentes. 1 PR custa 65,7 min de máquina contra 28.800 min-slot/dia de capacidade — daria 438 PRs/dia. LC-08 ocorrência 59."
  - "Consolidação de 65 jobs advisory em steps: AVALIADA e DESCARTADA por medição (workflow de 11 agentes, 3,1M tokens). Ganho real ~17% (não os ~60% que eu havia estimado) porque `services:` e `strategy.matrix` são job-level e não existem como step; e 4 céticos acharam 25 problemas, 2 deles bloqueadores que derrubariam o required `Governance Gate` no primeiro PR (Check G do registry + Check M do teto ADR 0298). Vira lápide §5."
  - "Gap estrutural registrado: `required-always-run.mjs` dá verde (41 required / 41 always-run / 0 filtrados) porque mede o MAIN. Ninguém mede se PRs JÁ ABERTOS conseguem satisfazer um check recém-promovido — e é isso que trava o repo inteiro."
related_adrs: [0370-module-surface-catalog-graph-required-emenda-0314, 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314, 0298-teto-de-governanca-anti-proliferacao-gates, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
---

# Sessão 2026-08-08 — o deadlock que parecia fila

> Pedido do [W], nove palavras: *"os testes do github estao trancando por 2 dias ja. tem solução?"*
> Terminou com a causa provada, 4 PRs destravados, e uma consolidação de CI recusada por medição —
> depois de eu ter recomendado a consolidação com números que a própria investigação derrubou.

## TL;DR

O CI **não estava travado**. Ele leva ~48min por PR e completa. O que travava eram **3 checks
required que nunca nasciam** em PRs abertos antes de 2026-08-05 — exatamente os "2 dias" do [W].

---

## 1. A hipótese errada (e por que ela era plausível)

Medi primeiro o que era fácil de medir: **7.421 runs em 24h**, **345 runs na fila**, 14 jobs
rodando contra um teto de 20, **1 PR gerando 148 jobs**. Montei a conta — demanda 5,22× a
capacidade — e apresentei **saturação de concorrência** como causa, com a recomendação de
consolidar os jobs advisory.

Os números estavam certos. **A conclusão não.** Eu tinha medido a fila e inferido que era ela
que travava os PRs, sem nunca perguntar ao oráculo certo: *o que exatamente falta em cada PR?*

O que derrubou a hipótese, tudo medido depois:

| medida | valor | o que diz |
|---|---|---|
| tempo de CI por PR (9 PRs, 977 check-runs) | **48 min** mediana, 55 max | não é "2 dias" |
| checks pendentes nos PRs de hoje | **0** | eles concluíram |
| fila durante a sessão | 378 → **150** | drena sozinha |
| jobs rodando × teto | 14 de 20, depois 10 de 20 | **teto não saturado** |
| custo de máquina por PR | 65,7 min | capacidade daria **438 PRs/dia** |

O sinal mais claro eu tinha ignorado desde o começo: **14 jobs rodando com teto de 20**. Fila
cheia com teto sobrando não é saturação — é outra coisa segurando.

---

## 2. A causa real

Com o oráculo certo — `mergeable_state` por PR + quais required faltam — o quadro apareceu:

```
PR 5077 (30/07) — required AUSENTES: 3 de 41   (91 checks presentes)
PR 5068 (30/07) — required AUSENTES: 3 de 41   (92 checks presentes)
PR 5255 (03/08) — required AUSENTES: 3 de 41   (94 checks presentes)
PR 5119 (31/07) — required AUSENTES: 1 de 41   (110 checks presentes)
```

Os três órfãos:

- `SUPERFICIE.md == árvore (módulos vivos + adotados)`
- `Self-test — classificação por papel + montagem determinística`
- `catalog.json == SCOPEs + Classes B`

Os três vêm de `module-surface.yml` e `catalog-graph.yml`. E o commit que os promoveu:

> **`9199a82a12f`** — 2026-08-05 — *"promove module-surface e catalog-graph a required — ADR 0370"* ([#5318](https://github.com/wagnerra23/oimpresso.com/pull/5318))

O diff desse commit tem, nos dois arquivos, a linha `-    paths:` **e** a alteração de
`governance/required-checks-baseline.json`. Ou seja: **remover o filtro e promover a required
aconteceram no mesmo commit**.

Consequência: PR aberto antes disso teve seus check-runs computados quando o workflow ainda
tinha `paths:`. Se o PR não casava o filtro, o job **não nasceu naquele SHA**. Depois o job
virou obrigatório. O GitHub passa a esperar por um check que não existe e não vai existir —
`Expected — waiting for status`, indefinidamente.

É o mesmo **sintoma** já catalogado em [`proibicoes.md` §Ambiente](../proibicoes.md) (02/07:
*"main BLOCKED com 54/54 checks verdes"*), mas por **vetor diferente**: lá foi mojibake nos
nomes ao re-postar a protection; aqui o nome está certo — o check é que nunca nasce.

---

## 3. A prova (controle negativo)

Antes de aplicar o fix em massa, testei o mecanismo num PR só. Disparei `catalog-graph.yml`
por `workflow_dispatch` na branch do PR 5077. Resultado:

```
queued   catalog.json == SCOPEs + Classes B (advisory)     ← nome ANTIGO
```

E `module-surface.yml` recusou: `Workflow does not have 'workflow_dispatch' trigger`.

**Isso prova o mecanismo inteiro**: o dispatch usa o workflow **da branch**, não do main. A
branch velha tem o arquivo velho — cujo job se chamava `... (advisory)` e cujo gatilho não
tinha dispatch. O required exige o nome **sem** o sufixo. Por isso dispatch não resolve.

O que resolve é trazer o main para a branch:

```bash
gh pr update-branch 5077
```

Depois disso, no head novo:

```
queued   catalog.json == SCOPEs + Classes B                ← nome CORRETO
queued   SUPERFICIE.md == árvore (módulos vivos + adotados)
queued   Self-test — classificação por papel + montagem determinística
```

Aplicado em 5068, 5255 e 5119. Os quatro saíram de "3 required órfãos" para **0 falhas** e
CI processando (93 a 111 checks rodando).

---

## 4. Estado dos PRs abertos ao fechar

| causa | PRs | ação |
|---|---|---|
| **destravados nesta sessão** | 5077, 5068, 5255, 5119 | nenhuma — CI rodando |
| **conflito de merge** (`dirty`) | 5397, 5304, 5071, 5069 | resolução de conteúdo, decisão [W] |
| **required realmente vermelho** | 5407 (`visual-regression`), 5382 (`PHP / Pest (Ponto · MySQL)`) | corrigir o teste — o Ponto é a **US-PONTO-014** da [sessão irmã de 07/08](2026-08-07-lanes-required-vermelhas-e-quarentena.md) |
| mergeável | 5417 | só `dup-detector` (advisory) vermelho |
| CI normal | 5420, 5413, 5404 | ~48min |

Também cancelei **22 runs órfãos** (branches sem PR aberto). Efeito medido: fila 378 → 370 —
praticamente nada, o que confirma que a fila **não era lixo**: varrida inteira, **93% era
trabalho legítimo** de PR vivo e `superseded = 0`.

---

## 5. A consolidação que não foi feita

Com o [W] tendo autorizado ("pode fazer"), rodei um workflow de **11 agentes** (6 extratores +
1 arquiteto + 4 céticos, 3,1M tokens, 0 erros) para consolidar os jobs advisory em steps.

**O resultado recusou o próprio trabalho**, e o detalhe está na [lápide §5](../proibicoes.md).
Resumo do que a medição mostrou:

- **O ganho que eu prometi não existe.** Eu disse "148 → 58 jobs/PR". Real: **148 → ~123 (−17%)**,
  porque `services:` (MySQL) e `strategy.matrix` são **construtos job-level** no GitHub Actions —
  não existe service nem matrix de step. Os 9 workflows Pest e o `modules-pest` (6 checks por
  matrix) são intocáveis por construção.
- **2 bloqueadores duros**, achados por 3 céticos independentes: criar `gates-advisory.yml` sem
  registrá-lo em `scripts/governance/gates-registry.json` reprova o **Check G**; sem
  `terminal`+`anchor`+`promote_by` reprova o **Check M** (teto, [ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md)).
  Os dois derrubam o required `Governance Gate` — o PR travaria a si próprio.
- **Contexto que eu não tinha:** o context `Governance Gate` **não está** na proteção clássica;
  está em **rulesets** (`Governance Gate — main (ADR 0258)`). Minha lista de "40 required" estava
  incompleta — são **41**.
- E 20+ achados finos e reais: `design-memory-gate.test.mjs` **asserta sobre o texto** do workflow
  que a consolidação esvaziaria; `skipped` lido como verde (a família presence-gate do §5);
  `npm ci` compartilhado virando SPOF; 11 gates hoje path-scoped virando always-run **aumentando**
  o custo; perda dos `timeout-minutes` de job.

O material completo (76 receitas de gate + projeto + 4 vereditos) ficou no scratchpad da sessão.

---

## 6. O gap que isso revelou

`node scripts/governance/required-always-run.mjs` dá **verde**:

```
REQUIRED ALWAYS-RUN — 41 contexts required · 41 always-run · 0 FILTRADO(s)
✅ todo context required nasce em todo PR.
```

E está certo — **para o main**. O lint responde *"todo required nasce em PR novo?"*. A pergunta
que ninguém faz é *"os PRs **já abertos** conseguem satisfazer o check que acabei de promover?"*.

Enquanto a promoção mudar o **nome** do job ou o **gatilho**, todo PR aberto vira órfão em
silêncio — e como a proteção é do branch, trava o repo inteiro, não só aquele PR.

**Antídoto barato, sem máquina nova:** ao promover gate a required, rodar `gh pr update-branch`
nos PRs abertos **no mesmo PR da promoção**. Foi o que fiz aqui, e está agora no
[RUNBOOK-branch-protection](../requisitos/Infra/RUNBOOK-branch-protection.md) §Promoção de check.

Não propus gate para isso: seria máquina nova, e a regra do projeto exige **FP medido antes**
(regra "LIGUE A MÁQUINA" item 4). Fica como par candidato — a 2ª ocorrência já nasce com o
trabalho pronto ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) two-strikes).

---

## 7. Meus erros nesta sessão (LC-08 ocorrência 59)

Três instâncias da mesma classe, todas de **medir a fonte errada e chamar de verificado**:

**(a) O diagnóstico inteiro.** Medi a fila e afirmei saturação como **causa**, sem nunca medir
o que travava cada PR. O oráculo certo (`mergeable_state` + required ausentes) estava a um
comando e respondeu em segundos. Cheguei a recomendar ao [W] um trabalho grande baseado nisso.

**(b) `ls -la a b c || echo "nenhum CODEOWNERS"`.** O `ls` saiu com código ≠ 0 porque 2 dos 3
paths não existem — e o `echo` disparou dizendo que **não havia** CODEOWNERS, com o arquivo
listado uma linha acima. É literalmente a lápide §5 de 2026-07-17 (*"`cmd || echo` mente quando
o `cmd` falha por outro motivo"*), cometida de novo.

**(c) `pull_request:` lido como AUSENTE.** Meu parser YAML testou `pr is None` — mas
`pull_request:` com valor **vazio** parseia como `None`, que é a forma **mais permissiva**
(dispara em todo PR), não ausência. Escrevi "pull_request: AUSENTE" nos dois workflows e quase
construí a causa raiz em cima disso.

Nas três, o padrão é o mesmo e é o que o LC-08 já descreve: **o instrumento respondeu uma
pergunta parecida com a que eu fiz, e devolveu um número** — e número dá confiança.

---

## Ref

- [ADR 0370](../decisions/0370-module-surface-catalog-graph-required-emenda-0314.md) — a promoção que causou (não é defeito da ADR: é do procedimento que faltou)
- [Sessão irmã 07/08](2026-08-07-lanes-required-vermelhas-e-quarentena.md) — a ADR 0369 promoveu 3 lanes Pest **no mesmo dia**; lá o sintoma foi lane vermelha, aqui foi check ausente
- [RUNBOOK-branch-protection](../requisitos/Infra/RUNBOOK-branch-protection.md) — dono do tema, estendido nesta sessão
- [proibicoes.md §5](../proibicoes.md) — lápide da consolidação recusada
- [LICOES_CODE.md](../LICOES_CODE.md) — LC-08 → 59
