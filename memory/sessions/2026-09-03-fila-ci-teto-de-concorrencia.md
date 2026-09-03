---
date: "2026-09-03"
topic: "Fila do CI: o gargalo era o teto de 20 jobs simultâneos, não a duração dos jobs"
authors: ["W", "C"]
prs: [6621, 6622]
outcomes: ["GitHub Pro ativado (teto 20→40)", "cancel-in-progress em 19 workflows (#6621)", "68 advisory sem synchronize (#6622)"]
---

# Fila do CI — teto de concorrência, medido

**Pedido [W]:** *"está tudo gargalando no GitHub, fico muito tempo esperando. qual a melhor solução?"* → depois *"pode fazer o resto"*.

**TL;DR:** um PR que poderia ficar verde em 15 min levava 100; 85 desses minutos eram fila. A conta estava no plano Free (teto de 20 jobs simultâneos) com 848 runs esperando. [W] subiu para Pro (teto 40) em 2026-09-03. O resto foi em dois PRs: `cancel-in-progress` nos 19 workflows que não tinham, e 68 workflows advisory deixando de rodar a cada push.

## 1. O que foi medido (2026-09-02, API do Actions)

| Medida | Valor |
|---|---|
| Runs criados no dia | 11.770 |
| Runs na fila no momento | 848 |
| PRs abertos | 21 |
| Workflows disparando em `pull_request` | 109 de 127 |
| Jobs por push (PR #6570) | 118 (45 required · 73 não-required) |
| Jobs que rodam em menos de 1 min | 104 de 118 |
| Espera mediana na fila por job | 62 min (36 min na amostra de 5 PRs) |
| Execução mediana por job | 24 s |
| Caminho crítico (visual-regression) | 15 min |
| Do abrir ao verde (PR #6570) | 100 min |
| Pico de jobs simultâneos (união de 5 PRs) | 19 |

Grupos de `concurrency`: 90 por ref, 0 globais. Nenhum serializa entre PRs. O pico de 19 bate com o teto de 20 do plano Free na tabela oficial de limites do Actions.

**Sobre a lápide §5 2026-08-08** (*"CI saturado" era diagnóstico falso*): ela estava certa na data e exigia sinal medido para reabrir. Este é o sinal. A lição que fica é a mesma dela, lida dos dois lados: **fila cheia não prova teto batido, e teto batido não se vê sem contar jobs simultâneos** — a medida é `started_at − created_at` por job, nunca a duração do run.

## 2. O que foi descartado

- **Trial Enterprise 30 dias:** a doc do trial diz que não inclui aumento de concorrência. Exigiria mover o repo para org e cairia em estado rebaixado ao fim.
- **Consolidar jobs:** continua a opção mais cara (2–3 sessões + os 4 achados dos céticos da lápide 2026-08-08). Fica para depois de medir o efeito do Pro + PRs.
- **Runner self-hosted no CT 100:** repo público; GitHub desaconselha. Decisão [W] se o Pro não bastar.
- **Cache de dependências:** não é alavanca; o job trivial já roda em 24 s com checkout incluído.

## 3. O que foi feito

- [W] ativou **GitHub Pro** (US$4/mês, teto 40). Única linha do plano que se aplica: o repo é público, o resto do Pro é para repo privado.
- [#6621](https://github.com/wagnerra23/oimpresso.com/pull/6621) — `cancel-in-progress` nos 19 workflows de PR que seguravam slot de run superado. `handoff-sign-submit` fora de propósito (`false` declarado).
- [#6622](https://github.com/wagnerra23/oimpresso.com/pull/6622) — 68 advisory em `types: [opened, reopened, ready_for_review]` + `workflow_dispatch`. Volume por PR −49%. Excluídos de propósito: brl-scan, xss-content-gate, infra-contract-required, contrato-de-tela, visual-regression, memory-schema-gate, handoff-sign-submit. **Quem promover um deles a required reinsere `synchronize` antes do flip.**

## 4. Pendência honesta

Uma hora depois do upgrade: 564 runs na fila e **13 jobs rodando**, não 40. Ou o novo teto ainda não propagou, ou há outro limite que a medição não enxergou. Re-medir com `jobs in_progress` somados sobre os runs `in_progress`; se seguir ≤20 por mais de um dia, abrir ticket no suporte (o Pro inclui suporte por e-mail).
