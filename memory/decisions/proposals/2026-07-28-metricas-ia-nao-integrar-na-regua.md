---
status: proposal
title: "Métricas de IA: NÃO integrar na régua required — o slot já existe, está vazio, e o gargalo é fazer a métrica que já existe medir"
proposed_by: Claude — pedido [W] 2026-07-28 "como integrar com a régua do sistema as métricas do relatório?"
proposed_at: 2026-07-28
relates_to:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
---

# PROPOSAL — a resposta é não integrar, e o motivo é melhor que a pergunta

## A pergunta

[W]: *"como integrar com a régua do sistema as métricas do relatório?"*

O relatório da camada de IA de 2026-07-28 produziu uma grade de 10 dimensões com notas e seis achados
concretos. A pergunta natural é ligá-los ao que o projeto já mede.

**A resposta é não ligar.** Quatro razões, cada uma medida.

## Razão 1 — metade já tem dono, e um deles é required

O achado *"4 flags lidas via `env()` fora de `config/`"* tem dono e é **required**:

```
identifier: larastan.noEnvCallsOutsideOfConfig
gate:       "PHPStan / Larastan · ratchet vs baseline"   ← nos contexts required
```

A dívida já está catalogada e congelada em baseline. Integrar seria duplicar régua consolidada —
a lápide de `proibicoes.md` §5 2026-07-09.

E a sessão paralela que atacou esse achado ([PR #4977](https://github.com/wagnerra23/oimpresso.com/pull/4977))
mostrou que ele nem era o que parecia: **as chaves estavam inoperantes em produção**. O deploy roda
`config:cache`, e com o config cacheado o `.env` nunca é lido — `env()` devolve sempre o default.
Medido em prod, com controle negativo (`env('APP_ENV')` → `NULL`). Não era higiene: era kill-switch
morto, incluindo o que desliga a redação de PII.

## Razão 2 — o slot da camada de IA já existe nessa régua, e está vazio

Das 13 métricas do `governance/sdd-scorecard.json`, **12 mediram**. A que não mediu é de IA:

```
recall_eval_violations    status=not_yet_measured    value=null
ragas_real_uptime         status=measured            value=75
```

E a que mediu oscila: `null → 100 → 66,7 → 75` em 24 dias, porque depende de um cron semanal no
CT 100. Se tivesse sido armada no 100, o gate estaria vermelho por ruído de infraestrutura, não por
queda de qualidade da IA.

**Somar métrica nova a uma fila que ninguém consome é teatro.** O pré-requisito é fazer as duas que
já existem medirem de forma estável.

## Razão 3 — as notas são opinião, e a régua não aceita opinião por construção

As 10 notas da grade foram levantadas e pontuadas por um agente. O `SDD scorecard ratchet` só arma
uma métrica após **3 medições válidas consecutivas da fonte real** (sem erro, não-mock, valor
não-nulo). Nota auto-declarada não passa nesse critério — e colocá-la num baseline versionado seria
catraca sobre campo auto-declarado, a forma já rejeitada três vezes no §5 com nomes diferentes
(`last_validated`, `verificado_em`, reconciliação por data).

## Razão 4 — o denominador não fecha

O `module:grade` avalia **diretório físico** — `gradeAllModules` faz `scandir(base_path('Modules'))`.
A camada de IA atravessa cinco: Jana 73 · ADS 88 · Crm 87 · Whatsapp 80 · KB 77. Zero representação
do conjunto. Atribuir a nota a um módulo, ou somar os cinco, é denominador inventado
(§5 2026-07-27).

## O que já existe e ninguém ligou

`Modules/Jana/module.json` declara `governance.bucket: ai_central`, atribuído por **[W] em
2026-05-17**, com justificativa que cita textualmente *"dimensões específicas
hallucination/recall/cost"*.

E `memory/scorecards/jana.yaml` existe, com 388 linhas e dimensões quase 1:1 com o relatório:

| Dimensão | Peso |
|---|---|
| A1 taxa de alucinação | 15 |
| A2 custo por requisição | 12 |
| A3 latência p99 do RAG | 10 |
| A4 recall@5 | 10 |
| A5 redação de PII pré-LLM | 10 |
| A6 sentinela de drift | 10 |

**Não é bug de caminho.** O cabeçalho declara `Status: EXPERIMENTAL — valida formato antes de
implementar`, e escreve o próprio plano de canonização: mover para o módulo, `ModuleGradeService` v4
carregar YAML em vez de rubrica fixa, gate bloqueando regressão por bucket. O avaliador lê de outro
diretório porque o arquivo **ainda não foi promovido**.

Ou seja: a integração que a pergunta busca **já foi desenhada e aprovada**. Falta executar a
promoção — e ela depende das métricas medirem, que é a Razão 2.

## Achado colateral — a dimensão de observabilidade mede presença

`D9 Observability` pontua a proporção de arquivos em `Modules/<X>/Services/` que **mencionam** OTel:

```bash
find Modules/Jana/Services -name "*.php" | wc -l              # 88
grep -rlE "OtelHelper::span" Modules/Jana/Services | wc -l     # 51
```

É presence-gate: conta menção no arquivo, não emissão no caminho executado. Um serviço com uma única
chamada instrumentada num método frio pontua igual a um instrumentado ponta a ponta.

**Não proponho corrigir agora.** Endurecer predicado sem medir falso-positivo antes é o erro que o §5
cataloga quatro vezes. Fica registrado como achado.

## Recomendação

1. **Não integrar** a grade nem as notas em régua nenhuma.
2. **Publicar os achados como relatório datado**, com o comando ao lado — nunca como nota.
3. **Mandar cada achado ao dono que já existe.** Quatro dos seis já fecharam assim no mesmo dia:
   [#4977](https://github.com/wagnerra23/oimpresso.com/pull/4977) flags ·
   [#4978](https://github.com/wagnerra23/oimpresso.com/pull/4978) tabela fantasma ·
   [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979) degradação vs ausência ·
   [#4980](https://github.com/wagnerra23/oimpresso.com/pull/4980) tokens no turno correto.
4. **O gargalo real é `recall_eval_violations`.** Enquanto estiver `not_yet_measured`, qualquer
   métrica de IA nova entra numa fila parada. Fazer essa medir é o que destrava a promoção do
   scorecard por bucket que [W] já aprovou em maio.

## O que esta proposta pede a [W]

Nada de novo a decidir sobre integração — a recomendação é não fazer. O que precisa de decisão:
**vale investir em fazer `recall_eval_violations` medir?** Se sim, a promoção do `jana.yaml` deixa de
ser hipótese e vira trabalho com pré-requisito claro.

## Lição de método — e um erro meu que entra nela

Ao levantar o relatório, afirmei que o chat com streaming **não emitia rastro algum**. Está errado:
`LangfuseAgentTelemetryListener` assina `StreamingAgent` e `AgentStreamed` e produz trace e
generation. A evidência estava no **mesmo arquivo que eu li** — o `LaravelAiSdkDriver` tem, em
comentário, *"trace Langfuse de SUCESSO agora emitido globalmente pelo LangfuseAgentTelemetryListener"*.

Eu fiz `grep` no **método** para responder uma pergunta sobre um **listener global de eventos**. É a
classe LC-08 outra vez, e desta vez sem desculpa: a fonte certa estava a três linhas da que eu citei.
Quem corrigiu foi uma sessão paralela, medindo.

O residual continua real, e é mais preciso: falta um **rastro raiz** que costure a jornada (acerto de
cache, clarificação, persistência, erro parcial, fim da transmissão) — hoje a chamada ao modelo
aparece sem contexto em volta. A nota de observabilidade da grade subiu de 4 para 6.

**A regra que sai disso:** quando a pergunta é *"o sistema emite X?"*, o oráculo não é o método — é
quem assina o evento. Grep no caminho responde outra pergunta, e responde com confiança.
