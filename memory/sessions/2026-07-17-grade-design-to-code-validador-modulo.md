---
date: "2026-07-17"
topic: "Grade design→código (7,0→7,7) + validador-modulo + NO-SECTION + deadlock Charter/SPEC"
hour: "20:24 BRT"
prs: [4422, 4423, 4424, 4428, 4441, 4445]
related_adrs: [0290-fidelity-lock-v0-recusado, 0327-anchor-content-required-emenda-0314, 0264-governanca-executavel-trio-dominio-e2e]
outcomes:
  - "6 PRs mergeados (cluster C9 + NO-SECTION + validador-modulo)"
  - "Grade design→código re-medida: ~7,7 (era 7,0) com evidência no repo vivo"
  - "1 processo novo (validador-modulo) + 1 gate estendido (NO-SECTION) + 1 lápide §5"
---

# Sessão 2026-07-17 — Grade design→código + validador-modulo

## TL;DR

Sessão iniciada no chip **C9** (design→código, 7,0) que pedia "razão de fidelidade no `--json` do
`style-fingerprint` + baseline por tela". **O chip caiu na verificação** — a razão-de-fidelidade
agrega vereditos incomensuráveis (a lápide C9 já matou). O trabalho real virou **cobertura + loop
de validação por módulo**: mergeei o cluster C9 (coverage 3→10, trava-rota/nudge), construí o gate
**NO-SECTION** (#4441) e o **validador-modulo-prototipo** (#4445, o "fluxo/processo que valida o
módulo inteiro ligado ao protótipo" que o [W] pediu), diagnostiquei e resolvi um **deadlock de
merge** real, e rodei a **grade design→código** de fresh main: **~7,7**.

## Contexto

Origem: grade de réguas 2026-07-17 diagnosticou design→código em 7,0 ("geração sem número de
fidelidade"). O diagnóstico estava **errado** e esta sessão o enterrou.

## O que foi feito (arco)

1. **Verifiquei o chip C9 adversarialmente → caiu.** Metade ("baseline por tela") **já existe** e
   melhor (`render-proto-baseline.mjs`, 3 baselines íntegros). A outra metade (razão única) é
   **contraindicada** pelo próprio mecanismo (`render-proto-baseline.mjs:23-26` "direção NÃO é
   uniforme; o compare REPORTA, humano DECIDE") — otimizar o número **regride a prod**. Lápide C9
   já cobria; a sessão paralela ([W] replica prompt) tinha ido além e escrito a versão longa.

2. **Mergeei o cluster C9** (sessão paralela): #4422 (trava rota-fantasma + nudge por case), #4423
   (kb âncora repo-relativa), #4424 (cobertura proto-baseline 3→10), #4428 (lápide C9). Todos
   verdes antes do merge.

3. **Passe adversarial "validar módulo ligado ao protótipo"** (workflow 15 agentes, cobaia
   Financeiro): provou que "validar o módulo" **NÃO é gate novo** — é orquestrador fino sobre os
   donos existentes + o loop de protótipo local, mais **1 eixo sem dono**: coerência charter↔código.
   Achado concreto: os 4 charters Financeiro compartilham `financeiro-telas-extras.jsx (TelaX)` e o
   gate required jogava fora o `(TelaX)`.

4. **NO-SECTION** (#4441) — estendi o `anchor-content-check` (required) pra pegar dead-anchor de
   **fragmento** (`(TelaX)` que não resolve a export). WARN, não hard-fail (L-24); lê só o que o
   charter DECLAROU (nunca deriva do nome — §5 2026-06-30). Self-test 40 checks; `seção morta: 0`
   hoje (guarda latente).

5. **validador-modulo-prototipo** (#4445) — workflow + skill parametrizado por `<Mod>`, 4 fases
   (inventário → coerência charter↔código com prova `file:line` → fidelidade local → síntese), todo
   achado aterrissando num dono existente. Zero gate próprio (anti-§5).

6. **Deadlock de merge diagnosticado e resolvido.** #4445 travou em `BLOCKED` com tudo verde. Causa
   real (não lag): #4439/ADR 0341 tornou `Charter`/`SPEC` **required** às 16:55; meu branch foi
   cortado de base **anterior** ao #4439, então carregava o `memory-schema-gate` path-filtrado
   antigo → os contexts now-required nunca reportavam. Fix: `gh pr update-branch` trouxe o gate
   **always-run** (#4439 removeu o `paths:` justamente por isso) → skip-as-pass → auto-merge landou.
   Sem `--admin` (enforce_admins on).

7. **Grade design→código** de fresh main (worktree detached): **~7,7**. Ver §Grade.

## Grade — design→código (~7,7)

- **1 acima-de-categoria:** `anchor-content-check` required abre a âncora e classifica o conteúdo
  (MISSING/SHELL/NO-MODULE/NO-SECTION) — ninguém no mercado verifica integridade de proveniência
  semântica como condição de merge. Limite: "âncora de charter" é conceito bespoke nosso.
- **1 à-frente-por-integração:** a triagem tri-modal **só como TODO recursivo no contexto** (a peça
  isolada foi **REFUTADA** — Chromatic faz "intended change" igual). Diferencial de instanciação,
  não de categoria.
- **Gaps reais (só 2):** F1 SSIM backstop por-região (LOCAL) · F2 pseudo-estados + mobile 375
  (LOCAL). O resto que a pesquisa marcou como fraqueza a verificação derrubou (component-registry =
  "Code Connect do projeto"; tokens DTCG + Style Dictionary v4; VRT perceptual YIQ required; matriz
  cross-browser 3-engine enforcing) ou é decisão consciente (write-back visual por ADR 0283; teto de
  11 telas ancoráveis por ADR 0290).
- **Chips abertos:** C-F1 (SSIM), C-F2 (estados+mobile), C-F3 (promover component-registry — **exige
  reabrir ADR 0314**), C-F4 (build consome DTCG, baixa prio). Os 4 rodando em sessões próprias.
- **Risco da própria grade:** rodou só 1 eixo (CONSTRUIR-E-GOVERNAR). RODAR-E-OBSERVAR (ADR 0333) e
  SERVIR-O-NEGÓCIO (ADR 0334) **sem retrato hoje** — flagado pro próximo retrato.

## Lições catalogadas

- **A grade corrigiu a própria pesquisa:** `render-proto-baseline --check` é **advisory** por design
  (`design-memory-gate.yml`), não required como a pesquisa alegou. A verificação-no-repo-vivo (lição
  7/9) pegou.
- **Nova lápide §5:** a claim "recusar agregar fidelidade é só nosso" é **REFUTADA** (Chromatic +
  governança de design-token-drift já fazem). Sobrevive o TODO recursivo, não a peça. Registrada em
  `proibicoes.md §5` nesta sessão.
- **Deadlock por base-antiga:** required novo + gate path-filtrado + branch cortado antes do flip =
  merge trava pra sempre. `update-branch` traz o always-run que resolve. (O #4439 já previu isso
  removendo o `paths:` — a lição é: base velha reintroduz o problema já resolvido no main.)

## Pointers detalhados

- Grade completa: resultado do workflow `wf_c8501dd8-202` (12 agentes) — placar + 6 fraquezas com
  nota+evidência + chips + §8 rejeitados.
- Mecanismos design→código: `prototipo-ui/{style-fingerprint,render-proto-baseline,fingerprint-harness,ancora}.mjs`
  + `scripts/governance/anchor-content-check.mjs` + `.claude/workflows/validador-modulo-prototipo.js`.
