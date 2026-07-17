---
name: validador-modulo
description: >
  ATIVAR quando Wagner pedir "valida o módulo X inteiro", "confere a estrutura
  de <Mod> ligada ao protótipo", "passe adversarial do módulo <Mod>", "valida
  todos os arquivos de <Mod>", "/validador-modulo <Mod>", OU antes de fechar uma
  onda de um módulo. É o irmão do `capterra-senior` (que compara com o mercado) e
  do `sdd-avaliador-processo` (que audita o processo): este valida UM MÓDULO
  INTEIRO ligado ao PROTÓTIPO — inventaria charters+âncoras+casos+baselines, roda
  os DONOS já existentes scopados ao módulo, dirige o loop de protótipo LOCAL
  (--check hermético + comandos --gerar/--compare por tela ancorável, fronteira
  ADR 0290), e faz o passe de COERÊNCIA charter↔código (o eixo sem dono: cada
  Automation Anti-hook confrontado com Controller/Service, PROVA file:line
  exigida — o vetor Fiscal/Cockpit). Entrega achados ranqueados que ATERRISSAM
  nos donos existentes (UC no casos.md sob casos-gate G-2, errata de charter,
  teste na allowlist do pest-lane). ZERO gate/verde-vermelho próprio (anti-§5).
  Dispara o workflow versionado `.claude/workflows/validador-modulo-prototipo.js`.
tier: B
status: active
version: "1.0"
authority: canonical
related_adrs: [0290-fidelity-lock-v0-recusado, 0264-governanca-executavel-trio-dominio-e2e, 0327-anchor-content-required-emenda-0314]
---

# Skill: validador-modulo — valida um módulo inteiro ligado ao protótipo (adversarial)

> **Origem (2026-07-17, Wagner):** *"aqui precisa de adversários, acho que criar um fluxo um
> processo que valida toda a estrutura do módulo, validar todos arquivos, e ligar com o
> protótipo."* Método destilado de um passe adversarial de design (15 agentes, módulo-cobaia
> Financeiro) que mapeou a cobertura existente e provou: **o "validar o módulo" NÃO é gate
> novo** — é um orquestrador FINO que roda os donos que já existem + o loop de protótipo local,
> mais o único eixo sem dono (coerência charter↔código) como passe adversarial de agente.

## O que ele faz (4 fases)

1. **Inventário** — Glob dos charters de `<Mod>`, resolve a âncora de cada (`ancora.mjs`, nunca no
   olho), marca `casos.md`/`proto-baseline` presente, e roda os **donos existentes** scopados
   (`anchor-content-check`, `casos-coverage-guard`, pest-lane do módulo).
2. **Coerência charter↔código** (o coração, o eixo SEM dono) — céticos confrontam cada *Automation
   Anti-hook* do charter com o Controller/Service, exigindo **prova `file:line`** de contradição.
   É o vetor Fiscal/Cockpit (anti-hook "cache só agregado" × código que cacheia por business =
   vazamento cross-tenant). Resolve pela **precedência Tier 0** (teste-verde-citando-UC > casos >
   charter > SPEC) e corrige o PERDEDOR. Ausência de contradição é o resultado **saudável** — não
   fabrica achado.
3. **Fidelidade** — roda o `render-proto-baseline.mjs --check` (hermético) e emite os comandos
   **LOCAIS** (`--gerar`/`--compare`) por tela ancorável. **Fronteira ADR 0290:** o compare é local;
   em CI só o `--check`. Nunca render pareado.
4. **Síntese** — achados ranqueados por severidade (tier0-cross-tenant > tier0-valor > regressão >
   divergência), cada um **aterrissando num dono existente**. Zero gate novo.

## Como rodar

```
Workflow({ scriptPath: ".claude/workflows/validador-modulo-prototipo.js", args: "<Mod>" })
```

Ex.: `args: "Financeiro"` (default se omitido), `"ComunicacaoVisual"`, `"OficinaAuto"`. O processo
é **módulo-agnóstico** — reusa os mesmos passos com a allowlist/dicionário/âncoras de cada módulo.
Read-only: o validador ACHA, o humano corrige nos donos.

> **Pré-requisito (base-freshness):** medir contra `origin/main` fresco. O workflow injeta o guard
> de checkout em cada agente, mas se você rodar de um checkout stale os achados de medição são
> inválidos (precedente registrado no `sdd-avaliador`).

## As leis que impedem o validador de virar teatro (anti-§5)

1. **Todo achado aterrissa num DONO existente** — nunca "criar gate novo" (§5: catraca redundante
   com régua consolidada). Antes de "falta mecanismo", achar o dono.
2. **Presença ≠ correção (L-24)** — achado precisa de prova de COMPORTAMENTO (teste vermelho,
   caminho concreto de valor/cross-tenant), não de "artefato existe".
3. **A coerência é por PROVA adversarial, não por presença** — o `charter-sync-gate` (§5 2026-07-01)
   tentou fazer isso por "artefato foi tocado" e foi rejeitado. Aqui exige-se `file:line`, e a
   autoridade é o teste verde citando o UC — **nunca** uma máquina que leia a prosa do anti-hook.
4. **Fidelidade prod×proto é LOCAL** (ADR 0290) — render pareado em CI passa verde quando os dois
   lados quebram. Em CI só o `--check` hermético.

## Relação com os vizinhos (não duplica)

- `capterra-senior` — compara o módulo com o **mercado** (feature-nível, nota 0-100).
- `sdd-avaliador-processo` — audita o **processo** SDD inteiro (o gate morde?).
- `avaliar-modulo` (`module:grade`) — nota interna do módulo em 9 dimensões.
- **`validador-modulo`** (este) — o único que faz **coerência charter↔código + fidelidade de
  protótipo POR módulo**, aterrissando nos donos. É o "validar a estrutura + ligar com o protótipo".
