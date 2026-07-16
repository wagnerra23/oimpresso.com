---
slug: 0327-anchor-content-required-emenda-0314
number: 327
title: "Emenda à 0314 — anchor-content-check promovido a REQUIRED (exceção consciente à 'required = só Tier-0')"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-08"
module: governance
tags: [governance, gates, ci, required, ancora, fidelidade, design, branch-protection]
supersedes: []
superseded_by: []
related:
  - 0326-trava-ancora-compare-fingerprint
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
---

# ADR 0327 — emenda à 0314: `anchor-content-check` a REQUIRED

> **Status:** `aceito` (2026-07-08, Wagner "resolva todos, eu autorizo" + "promova"). **Enactado:** o flip do branch protection foi feito (required `main` **23 → 24**; context `Ancora de design nao-shell (F2/F6 required)` agora required; PRs abertos re-disparados pra não travar). Exceção **consciente** à política "required = só Tier-0" da [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) — append-only (não edito a 0314).

## Contexto

A revisão adversarial (2026-07-08, [RUNBOOK-fidelidade-fingerprint](../../prototipo-ui/RUNBOOK-fidelidade-fingerprint.md) §Furos) achou o **F2/F6**: **zero** da máquina de fidelidade de design é **required em CI**. Especificamente `anchor-content-check.mjs` — o sentinela que abre o arquivo da âncora e classifica `MISSING / SHELL / NO-MODULE / OK` — rodava **advisory** (`continue-on-error`, dentro do `design-memory-gate.yml`). Uma âncora podre (`related_prototype` = shell do app OU arquivo sumido) **mergeava verde**.

**Reincidência dura:** a âncora podre do Financeiro/Unificado (`→ oimpresso.com.html` shell) foi pega pelo Wagner em **2026-07-06**, o charter foi corrigido, e o **mesmo erro voltou em 2026-07-08** (o agente comparou de novo contra o shell). Um gate advisory **provou** não prevenir a reincidência. *"Presença ≠ correção"* (L-24): o selftest prova que a **máquina** está sã, nunca que o **merge** é limpo.

## Decisão

Promover `anchor-content-check.mjs --check` a **required**, num job dedicado hard-fail (`.github/workflows/anchor-content-required.yml`, context `Ancora de design nao-shell (F2/F6 required)`), separado do `design-memory-gate` (que fica advisory com seus outros steps). O gate falha o merge só em **MISSING/SHELL** (sinal duro, determinístico, headless, zero-LLM, sem falso-positivo); `NO-MODULE` segue warn.

**Enactment (feito):** o context foi adicionado aos required checks de `main` via branch protection APÓS o job rodar verde uma vez em main (pra não travar PR por nome divergente). Required `main`: 23 → 24.

## Por que é exceção à 0314 (e por que vale)

A 0314 fixa **"required = só Tier-0 (dinheiro/PII/multi-tenant/fiscal)"** e rebaixou design-memory-gate a advisory. `anchor-content-check` é **fidelidade de design**, não Tier-0 por essa régua. A exceção se justifica por **três** coisas que a régua Tier-0 não pesa:

1. **Reincidência provada** (07-06→07-08) — o critério da própria 0271/0314 pra required é "gate que MORDE"; um advisory que deixou o erro voltar é o oposto.
2. **Custo ~zero e determinístico** — headless, sem browser, sem flake; verde no main na promoção (6 âncoras, 0 podre). Não é teatro nem catraca de forma.
3. **É a peça que fecha o meta-princípio** ([ADR 0326](0326-trava-ancora-compare-fingerprint.md)) — *superfície não-hookável → enforcement na máquina que produz o artefato, **E essa máquina precisa ser required***. Sem isso, a trava do `--compare` (fail-closed) e o check de conteúdo continuam sendo "lembre de rodar".

## Resíduo HONESTO (declarado)

- O gate só pega **MISSING/SHELL**. Um `related_prototype` que aponta pro **arquivo de OUTRA tela do mesmo módulo** passa (`OK` — existe, não é shell, menciona o módulo). Sem oráculo acima do charter, isso é indetectável.
- É fidelidade de **âncora** (o charter aponta pro arquivo certo), não de **render** (o pixel bate) — essa é a trava do `--compare` + o check de conteúdo F5 ([ADR 0326](0326-trava-ancora-compare-fingerprint.md)), browser-bound e não gateáveis headless.

## Gate de reversão

Se em ≥N runs o gate der falso-positivo (bloqueou merge legítimo sem âncora podre real), reabrir esta ADR e rebaixar (remover o context dos required). Até hoje: 0 falso-positivos (0 podre no corpus).
