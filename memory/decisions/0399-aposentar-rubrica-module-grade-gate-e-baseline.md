---
slug: 0399-aposentar-rubrica-module-grade-gate-e-baseline
number: 399
title: "Aposentar a rubrica module-grade, seu gate de CI e seu baseline"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-15"
module: governance
tags: [governanca, gates, poda, module-grade, ci, catraca, advisory]
supersedes:
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0156-module-grade-v3-errata-otel-helper-na-justified
  - 0157-module-grade-v3-d2-detection-hardening
  - 0158-module-grade-v3-d1-heuristica-hardening
  - 0159-module-grade-v3-errata-meta-97-realismo
superseded_by: []
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0161-governance-v4-aposentar-hacks-0159-redundantes
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
pii: false
---

# ADR 0399 — aposentar a rubrica module-grade, seu gate e seu baseline

## Contexto

A rubrica `module-grade` (ADR 0153 → 0159) dava a cada `Modules/<X>` uma nota composta 0–100 em
9 dimensões. O gate `module-grades-gate.yml` rodava em todo PR, gerava as notas do head e
reprovava se a nota de qualquer módulo caísse contra `governance/module-grades-baseline.json`,
ou se um módulo novo entrasse sem label.

**Ela já havia sido demovida de required.** A [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) D-1
(2026-06-30) a classificou *"check mais caro do repo, métrica composta, não-catástrofe"* e a
passou a advisory. Medido em 2026-09-15 em `governance/required-checks-baseline.json`:
`"module grades"` = **0 ocorrências** nos contexts required. Desde a demoção ela nunca bloqueou
um merge.

O que sobrou foi custo. Três fatos medidos:

1. **O piso não era mantível.** O próprio baseline registra **4 rebaselines** (v3.5.1 → v3.5.4),
   todos pela mesma causa: piso travado acima do que o CI alcança, produzindo vermelho crônico em
   PR de terceiros que ninguém podia consertar dentro do próprio PR.
2. **O artefato era curado à mão e ninguém o regerava.** O `cron-watchdog` (eixo 2, entrega)
   passou a acusá-lo como obra parada por volta de 30/08 — data interna `2026-07-16`, limite 60d.
   Rodando a função do próprio watchdog em 2026-09-15: `dataInterna=2026-07-16`, `61d`, vermelho.
   Esse vermelho aparecia em **todo PR aberto**, sem relação com o que o PR tocava.
3. **Não havia nada para a catraca travar.** Medição de 2026-09-15 pela fonte canônica do próprio
   arquivo (artefato `module-grades-current` do CI, campo `score`; 10 runs de 05/09 a 15/09, zero
   oscilação): **32 de 32 módulos casam com o baseline e há ZERO regressões**. Os únicos 4 deltas
   são `+1` (Arquivos 84 · Crm 88 · Essentials 87 · Governance 89), todos abaixo do corte de
   materialidade `≥3` que a própria v3.6.6 havia declarado. O vermelho era de **idade**, não de
   qualidade.

Decisão de [W] em 2026-09-15, textual: *"aposentar essa merda, isso só me incomoda"* e
*"porque isso sempre gerou problemas graves"*.

> ⚠️ **Nota de método, porque quase repetimos o erro.** A tarefa que chegou a esta sessão pedia
> *"re-curar o baseline no CT 100"*. O próprio baseline proíbe isso, e repete a proibição em toda
> versão: *"FONTE DO NÚMERO = CI, NÃO CT 100 — o CT 100 mede ~+1 sistemático, e travar o piso
> acima do que o CI alcança é o modo de falha que causou os rebaselines v3.5.1/v3.5.2/v3.5.3/v3.5.4"*.
> Medir no CT 100 para re-curar era exatamente o defeito histórico, com passos extras.

## Decisão

Aposentar a rubrica `module-grade` e toda a maquinaria que existia para alimentá-la ou para
consumi-la como régua. A poda sai em ondas, cada uma deixando a árvore verde:

| Onda | Escopo |
|---|---|
| **1 (esta)** | o gate de CI, o baseline e os leitores que dependiam dele |
| 2 | superfície de produto: `ModuleGradeController`, rotas, topnav, as 2 Pages e seus artefatos por-tela |
| 3 | motor: `ModuleGradeService`, `ModuleGradeCommand`, `ModuleGradeSnapshotCommand` e os 12 arquivos de teste |
| 4 | dado: tabela `mcp_module_grades_history`, o cron `module:grade-snapshot` (06:05 BRT) e o health-check que vigia o frescor dele — migration destrutiva em PR próprio, porque neste pipeline o merge **é** o ato de dropar em produção |
| 5 | canon: skills `avaliar-modulo` / `module-grades-gate`, RUNBOOKs e referências |

## Consequências

**O que melhora.** O vermelho crônico do watchdog em todo PR desaparece na raiz (o arquivo sai do
universo do eixo 2, que enumera por `git ls-files`). Morre o check mais caro do repo. Morre a
classe de chore "travar o ganho no baseline", que gerou um fluxo contínuo de PRs de curadoria.

**O que se perde — declarado, não silencioso:**

1. **`bucket-change-detection` (ADR 0160 Wave 20) morre junto.** Ele vivia no mesmo arquivo de
   workflow e também era advisory desde a 0314. O conceito de buckets **sobrevive** nos scorecards
   YAML e no `ScopedScorecardEvaluator`; o que morre é o gate de CI que exigia label para mudar
   `governance.bucket`. Se isso voltar a importar, nasce como gate próprio — não se ressuscita
   este arquivo.
2. **`memory-health` Check X perde uma das duas pernas.** Ele qualificava um módulo para auditoria
   profunda por ser Tier-0 **ou** por ter nota < 70. O conjunto Tier-0 é hardcoded e nunca dependeu
   da rubrica, então a perna que importa **fica**; a perna "nota baixa" sai. Ele deixou de enxergar
   *"módulo não-Tier-0 com nota baixa sem auditoria"* — se isso voltar a importar, o gatilho tem de
   ser outro sinal, nunca a nota morta.
3. **`service-scorecard` perde o sinal QUALIDADE.** O check `graded` sai do **denominador**
   (`applicable: false`) em vez de virar um check que nunca fica verde, e `grade` fica `null` com
   `aposentada: "ADR 0399"`. Nunca `0` inventado: ausência de medição não é nota do módulo.
4. **Nada Tier-0 se perde.** A dimensão D1 (multi-tenant) tem required próprio e independente —
   o context `No hardcode business_id (Tier 0)`. Dinheiro, PII e fiscal nunca dependeram desta
   métrica.
5. **Os ~115 arquivos que citam a rubrica em docblock ficam.** São fato datado ("este arquivo
   satisfaz D9.b"), escritos pelas Waves de saturação; não apodrecem por serem passado, e limpá-los
   em massa é o big-bang que o §5 2026-07-12 proíbe.
6. **Transitório declarado:** as 2 Pages `governance/ModuleGrades/*.tsx` linkam o baseline no
   GitHub. O link fica 404 até a Onda 2, que deleta as telas.

**Reversibilidade.** Tudo é git: o workflow e o baseline voltam por `git revert`. Mas reabrir exige
ADR sucessora que enfrente os três fatos medidos do Contexto — em particular o histórico de 4
rebaselines —, não um PR direto.
