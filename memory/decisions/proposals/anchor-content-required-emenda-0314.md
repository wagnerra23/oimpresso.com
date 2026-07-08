---
status: proposal
title: Emenda à 0314 — `anchor-content-check` promovido a REQUIRED (exceção consciente à "required = só Tier-0")
proposed_by: Wagner + Claude
proposed_at: 2026-07-08
relates_to:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
---

# EMENDA à 0314 — `anchor-content-check` a REQUIRED

> **Status:** `proposal`. **Wagner autorizou explícito** ("resolva todos, eu autorizo", 2026-07-08) após a revisão adversarial do processo de fidelidade. É uma **exceção consciente** à política "required = só Tier-0" da [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) — documentada aqui append-only (não edito a 0314).

## Contexto

A revisão adversarial (2026-07-08, RUNBOOK-fidelidade-fingerprint §Furos) achou o **F2/F6**: **zero** da máquina de fidelidade de design é **required em CI**. Especificamente `anchor-content-check.mjs` — o sentinela que abre o arquivo da âncora e classifica `MISSING / SHELL / NO-MODULE / OK` — roda **advisory** (`continue-on-error`, dentro do `design-memory-gate.yml`). Uma âncora podre (`related_prototype` = shell do app OU arquivo sumido) **mergeia verde**.

**Reincidência dura:** a âncora podre do Financeiro/Unificado (`→ oimpresso.com.html` shell) foi pega pelo Wagner em **2026-07-06**, o charter foi corrigido, e o **mesmo erro voltou em 2026-07-08** (o agente comparou de novo contra o shell). Um gate advisory **provou** não prevenir a reincidência. *"Presença ≠ correção"* (L-24): o selftest prova que a **máquina** está sã, nunca que o **merge** é limpo.

## Decisão

Promover `anchor-content-check.mjs --check` a **required**, num job dedicado hard-fail (`.github/workflows/anchor-content-required.yml`, job `Ancora de design nao-shell (F2/F6 required)`), separado do `design-memory-gate` (que fica advisory com seus outros steps). O gate falha o merge só em **MISSING/SHELL** (sinal duro, determinístico, headless, zero-LLM, sem falso-positivo); `NO-MODULE` segue warn.

Enactment: adicionar o context do job aos required checks de `main` via branch protection (feito após o job rodar verde uma vez — verificado pra não travar PR por nome divergente).

## Por que é exceção à 0314 (e por que vale)

A 0314 (proposta) fixa **"required = só Tier-0 (dinheiro/PII/multi-tenant/fiscal)"** e rebaixou design-memory-gate a advisory. `anchor-content-check` é **fidelidade de design**, não Tier-0 por essa régua. A exceção se justifica por **três** coisas que a régua Tier-0 não pesa:

1. **Reincidência provada** (07-06→07-08) — o critério da própria 0271/0314 pra required é "gate que MORDE"; um advisory que deixou o erro voltar é o oposto.
2. **Custo ~zero e determinístico** — headless, sem browser, sem flake; verde no main na promoção (6 âncoras, 0 podre). Não é teatro nem catraca de forma.
3. **É a peça que fecha o meta-princípio** — *superfície não-hookável → enforcement na máquina que produz o artefato, **E essa máquina precisa ser required***. Sem isso, a trava do `--compare` (fail-closed, mergeada #3967) e o check de conteúdo (#3971) continuam sendo "lembre de rodar".

## Resíduo HONESTO (declarado)

- O gate só pega **MISSING/SHELL**. Um `related_prototype` que aponta pro **arquivo de OUTRA tela do mesmo módulo** passa (`OK` — existe, não é shell, menciona o módulo). Sem oráculo acima do charter, isso é indetectável.
- É fidelidade de **âncora** (o charter aponta pro arquivo certo), não de **render** (o pixel bate) — essa é a trava do `--compare` + o check de conteúdo F5, que são browser-bound e não gateáveis headless.

## Gate de reversão
Se em ≥N runs o gate der falso-positivo (bloqueou merge legítimo sem âncora podre real), reabrir esta emenda e rebaixar. Até hoje: 0 falso-positivos (0 podre no corpus).
