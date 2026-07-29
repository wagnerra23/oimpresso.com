---
date: "2026-07-29"
topic: "Varredura de população em 3 rodadas — alarmes que afirmavam sem ter medido"
prs: [4997, 4999, 5000]
---

# Varredura de população — 3 rodadas, 3 alarmes que mentiam

**TL;DR:** a sessão começou com *"o que tem para fazer?"* e virou uma série de 3 rodadas com uma regra dura: **rodada só fecha com o problema RESOLVIDO, não achado**. O método validado foi **varrer a população inteira em vez de esperar o acaso**, e o predicado que achou os 3 defeitos foi sempre o mesmo — **confrontar a SAÍDA do mecanismo com a FONTE que ele diz resolver**. Nenhum dos três sairia de reler código.

## As 3 rodadas

| PR | o que estava mentindo | desfecho |
|---|---|---|
| [#4997](https://github.com/wagnerra23/oimpresso.com/pull/4997) | `lapide-recheck` gritava 4 chamados, **4 de 4 falso-positivo** | 0 FP; alarme segue mordendo em fixture (par BITE) |
| [#4999](https://github.com/wagnerra23/oimpresso.com/pull/4999) | `cron-watchdog` afirmava `✓ todos os 24 crons com heartbeat < limite` **tendo medido zero** | 3º estado ⛔ `cego`; cegueira total → `exit 1` |
| [#5000](https://github.com/wagnerra23/oimpresso.com/pull/5000) | guard de selftest órfão via **1 das 2 formas**; 9 selftests não rodavam | as duas formas vistas, fila em **0**, `--check` sem grandfathering |

> A Rodada 1 (#4997) veio de sessão paralela; as Rodadas 2 e 3 são desta.

## Rodada 2 — o watchdog que afirmava sem medir

A varredura atacou a população que a própria **LC-11** diz que *"segue não feita"*: mecanismos que decidem estado por presença. **Duas sub-populações saíram limpas**, e isso fica registrado como resultado, não como silêncio:

- guardas que caem em `exit 0` por input ausente → **5 de 5 inputs presentes**
- cobertura do `memory-schema-guard` → ADR/SPEC/BRIEFING/handoff/session/RUNBOOK **100% mapeados** (os 111 sem schema são proposals/templates, fora de escopo)

O defeito apareceu ao **rodar os alarmes advisory** e confrontar cada saída com a fonte. O `gh()` do `cron-watchdog` engolia qualquer falha (`catch → ''`) e devolvia o **mesmo `''`** de *"perguntei e não há run"*; o laço lia isso como 🟡 bootstrap (benigno), fora da lista `dead` → `exit 0` + a frase de verde.

**Recibo:** no host sem `gh`, **24 de 24** saíram bootstrap. Confrontado com a API do GitHub (clone raso — `git log` não vale como recibo), **`system-map.yml`, um dos 24, tem 16 runs agendadas**.

**O enquadramento que mudou tudo:** o fail-open era **deliberado** — o PR de origem [#3522](https://github.com/wagnerra23/oimpresso.com/pull/3522) diz textualmente *"fail-open em erro de API"*, e continua correto. O defeito é a **combinação**: fail-open + rótulo benigno + **afirmação de verde**. Um instrumento pode não medir; o que não pode é afirmar sobre o que não mediu.

Fix: três estados onde havia dois (`vivo` · `bootstrap` · ⛔ `cego`). Parcial fica visível e não derruba (preserva o fail-open contra falha transitória); total sai `exit 1`. **Controle de integração com um `gh` que responde: comportamento idêntico ao anterior** — o conserto só age quando não há medição.

## Rodada 3 — o guard que via metade das formas

Selftest vem em **duas formas**: arquivo `*.test.mjs` (que o guard via) e modo `--selftest` embutido (que não via). **78** scripts usam a forma embutida — e o `cron-watchdog` era um deles, com asserts que nenhum workflow rodava. É a explicação retroativa da Rodada 2.

**A regra do irmão foi MEDIDA antes de armar**, e é o que tornou o detector usável:

| critério | acusados | falso-positivo |
|---|---:|---:|
| ingênuo | 46 | **39 (85%)** |
| forma final (com a regra) | 7 | — |

Ela é **conservadora de propósito** (prefere deixar passar a acusar o legítimo) e tem **controle-negativo** pra não virar perdão cego: *irmão que existe mas ninguém roda **não** absolve* — presença ≠ cobertura.

Os 9 órfãos foram **rodados antes de wirar** (9 verdes) e distribuídos pela casa certa: 6 dependency-free no `governance-script-tests`; 2 no `memory-schema-gate`, o job que já paga o install. **Eram órfãos POR DEPENDÊNCIA** — o `governance-script-tests` não instala nada de propósito — e isso só se descobre **rodando**, não lendo.

Fila a zero → `--check` cobre as duas formas **sem grandfathering**: a catraca nasce segurando a linha, não perdoando dívida.

## Erros meus nesta sessão

**LC-08, ocorrência 26 — denominador truncado da paginação.** Contei o CI por `list_workflow_runs` e tratei os **30** itens da página como o todo: a API declarava **`total_count: 106`**. Foi assim que afirmei *"30 de 30 verdes"* (#4999) e *"24 success, 0 falhas"* (#5000) — enquanto um **required** (`PHP / Pest (Unit)`) sequer aparecia na amostra. Pior: a página 1 mostrava o `Memory schema gate` como `queued` e a página 2 como `success`, porque a lista é ordenada por data e **muda enquanto se pagina**.

Quem me desmentiu foi a **branch protection**, recusando o merge do #5000 e **nomeando o check** que a minha amostra não continha. O que salvou o #4999 não foi minha contagem — foi o GitHub ter enforçado os 34 required com `enforce_admins`.

**A lei:** verde de CI **não se conta por lista paginada**. A autoridade é a branch protection (tentar o merge, ou `get_check_runs` escopado ao head sha). É a mesma família do `head_limit` que cortou a varredura (§5 2026-07-15) e do denominador inventado (§5 2026-07-27) — *perguntar ao sistema que sabe, em vez de deduzir da amostra*. Ironia registrada: cometi a classe da Rodada 2 na hora de mergear a Rodada 3.

**Dois near-miss, ambos barrados por hook (funcionando como projetado):**

- `rm -f` num comparativo antes/depois → `block-destructive`. Refeito rodando a versão antiga **do scratchpad**, sem tocar a árvore (`.mjs` é ESM em qualquer diretório, então o contexto de módulo não muda — §5 2026-07-26).
- `git push --force-with-lease` → `block-destructive`. **O force era desnecessário**: o GitHub apaga a branch no merge, então o push normal criou branch nova.

**E um freio que evitou um falso achado:** 2 dos 3 selftests órfãos falhavam no meu container. Quase reportei como defeito — era `node_modules` ausente. Verificado antes de afirmar.

## O que NÃO virou máquina, e por quê

- **Acusar todo script com `--selftest` sem invocador**: 85% de FP na forma ingênua. Medido **antes**, não depois.
- **Lápide nova na Rodada 3**: não há classe de erro nova — a lápide da Rodada 2 já prescreveu o conserto, e o registro durável (85% FP + doutrina da regra do irmão) mora no **docblock, ao lado do mecanismo**. Inflar o §5 a cada conserto é o oposto do que ele serve.
- **Promover o `selftest-registry` a required** só porque a fila zerou: política vigente é *required = só Tier-0*, e higiene de teste não é Tier-0.

## O vão que segue aberto (não fechado por esta sessão)

A **LC-14** — *"o cron rodou e FALHOU"*, porque o eixo 1 do watchdog mede **recência**, não `conclusion`. É defeito **independente** do que a Rodada 2 consertou, segue **parado por two-strikes** (1ª ocorrência: conserta, não codifica) e exige FP medido contra os workflows agendados. **Consertar o `cego` não fechou o `conclusion`.**
