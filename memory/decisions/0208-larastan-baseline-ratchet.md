---
slug: 0208-larastan-baseline-ratchet
number: 208
title: "Larastan PHPStan baseline ratchet — enforcement passivo de anti-padrões PHP"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-28
module: Infra
quarter: 2026-Q2
tags: [enforcement, static-analysis, phpstan, larastan, anti-pattern, prevencao-bugs]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
pii: false
review_triggers:
  - "PRs abertos por agentes externos (não-Wagner) >5/semana"
  - "Onda Larissa-like (5+ bugs cliente-reportados em 1 sessão)"
---

# ADR 0208 — Larastan PHPStan baseline ratchet

## Contexto

Sessão Larissa 2026-05-28 entregou 4 PRs corretivos (R7 race scanner, R8 type drift, R9 fallback silencioso, R10 audit perdido) — todos preveníveis, todos cabendo em anti-padrões JÁ catalogados em [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) (15 técnicos + 6 meta).

Dossier estado-da-arte 2026 ([`memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md`](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md)) confirmou: o oimpresso tem o **catálogo de anti-padrões mais maduro** que o agent viu em codebase deste porte, mas **opera 100% no nível "doc que humano/IA lê"** — falta a camada de **enforcement passivo por AST analyzer** que Stripe/Shopify/Linear usam.

`composer.json` confirmado **sem** `nunomaduro/larastan`, **sem** `phpstan/phpstan`. Lints existentes são só:
- `app/Console/Commands/UiLintCommand.php` — regex `Select-String` em `resources/js/` (cobre R1-R6: cor hardcoded, FontAwesome, emoji, Blade directives adjacent)
- `app/Console/Commands/FsmScanDriftCommand.php` — específico FSM
- `app/Console/Commands/MemAuditCommand.php` — auditoria memory/

Nenhum analyzer AST PHP-side. Anti-padrões T-AP-1..T-AP-15 dependem inteiramente de humano/IA ler o LICOES antes de codar. R7-R10 prova que isso não é suficiente.

Alternativas avaliadas:

1. **PHPStan vanilla** (~3M downloads/mês) — generic, não Laravel-aware. Falta inferência de Eloquent, facades, `Inertia::render`.
2. **Larastan** (Laravel-aware extension de PHPStan, ~250k stars combinado) — entende `Model::find()` retorna `?Model`, `Inertia::render` signature, `Auth::user()` typing.
3. **Psalm** (~similar a PHPStan, sintaxe própria) — adoption menor em Laravel ecosystem.
4. **Rector** — refactor automation, não detection. Complementar, não substitui.

## Decisão

**Adotar `larastan/larastan` v2.11+** como tier mínimo nível 5 com **baseline JSON ratchet** — mesmo padrão do [`ui-lint.yml`](../../.github/workflows/ui-lint.yml) que já roda em prod desde 2026-04 sem fricção.

**Especificação técnica:**

- `composer require --dev larastan/larastan` (Laravel 13 compat verificada)
- `phpstan.neon.dist` na raiz, level 5 inicial (depois subir pra 7 incremental conforme baseline limpa)
- `phpstan-baseline.neon` gerado com violações pre-existentes (ratchet — falha só em REGRESSÃO)
- Workflow `.github/workflows/phpstan-gate.yml` espelhando estrutura de `ui-lint.yml`:
  - Dispara em PR
  - Roda `vendor/bin/phpstan analyse --memory-limit=1G`
  - Compara contra baseline; falha só se delta > 0
  - Output formato `--error-format=github` pra annotations inline no PR
- `composer.json` script `composer phpstan` pra rodar local
- Skill `commit-discipline` Tier A ganha menção: "Antes de PR final, rodar `composer phpstan`" — não bloqueia local mas economiza round-trip CI

**Lifecycle:**

- Nível 5 mantido até baseline limpa em 90d
- Após limpo: PR canon pra subir nível 6 → 7 (max recomendado Laravel)
- Custom rules entram em [ADR 0212](0212-defensive-logging-fallback-paths.md) e ADRs futuros (NoMissingTenantScope, NoInventedModel, NoNopMutationController)

## Justificativa

**Por que ratchet baseline e não nível 0 limpo desde o início:** codebase tem ~14k arquivos PHP e 200+ ADRs históricos. Tentar PHPStan limpo seria PR de 5000+ linhas — viola [`commit-discipline`](../../.claude/skills/commit-discipline/SKILL.md) Tier A. Pattern ratchet idêntico ao `ui-lint` provou-se: PRs de 200-300 linhas, baseline `+0 vs origin/main`, zero objeção em CI.

**Por que Larastan e não PHPStan vanilla:** Laravel-aware é crítico — sem ele, `Auth::user()->business_id` reportado como `mixed` (falso positivo); `Model::find($id)->save()` reportado como erro (falso positivo). Larastan adiciona ~50 stubs Laravel + macros Eloquent.

**Por que nível 5 inicial:** estatística Larastan project leadership recomenda: nível 5 captura 80% dos bugs typing (undefined methods, type mismatch params, null pointer). Níveis 6-7 entram em refinamentos (generic Collection, strict comparison). Pular pra 7 direto = baseline explode 10x.

**Por que ADR canon vs spike experimental:** Larastan vira **dependência de processo permanente** (rules customizadas, gate CI, baseline mantido). Sem ADR formalizando, primeira rotação de prioridade desativa silenciosamente. Pattern análogo: [ADR 0114 prototipo-ui Cowork loop](0114-prototipo-ui-cowork-loop-formalizado.md) — formalização do que já existia ad-hoc.

## Consequências

**Positivas:**

- **Habilita 4+ custom rules** (próximos ADRs): NoMissingTenantScope (T-AP-2 Tier 0 cobertura), NoInventedModel (T-AP-1), NoSilentFallback (R9), NoNopMutationController (T-AP-13)
- Tipo errors em PR aparecem no review (GitHub annotations) — Wagner vê inline, não precisa rodar CI mental
- Refactor incremental: Models lentamente ganhando `@property` + `@method` annotations corretos beneficia IDE também (PHPStorm, VSCode Intelephense)
- Pattern reutilizável: próxima onda (TanStack Query, Wayfinder) segue mesmo modelo ratchet
- Reduz dependência de leitura humana do LICOES — esquecer vira impossível em paths PHP cobertos

**Negativas / Trade-offs:**

- **Custo CI:** +2-3 min por PR (PHPStan analyse é caro). Mitigação: cache PHPStan no GitHub Actions cache (já feito com Composer cache).
- **Curva de aprendizado time futuro:** Felipe/Maiara/Eliana/Luiz vão precisar entender baseline + ratchet. Mitigação: skill nova `phpstan-ratchet-workflow` (Tier C, on-demand) com runbook.
- **Falsos positivos iniciais nível 5:** projeto tem padrões UPOS legacy não-Laravel-canon (ex: `$request->session()->get('user.business_id')` em vez de `Auth::user()->business_id`). Baseline absorve.
- **Custos pacotes upstream:** Larastan precisa updates Laravel major; manter compat = 1-2h por upgrade Laravel.

**Riscos mitigados:**

- R8 type drift (controller devolve 11, frontend lê 5) **continua mitigado por Wayfinder** ([ADR 0210](0210-type-safety-end-to-end-wayfinder.md)) — Larastan não cobre frontend
- Tier 0 multi-tenant scope violation (T-AP-2) — base pra NoMissingTenantScope (próximo ADR)
- R9 fallback silencioso — base pra NoSilentFallbackRule (próximo ADR)
- Bugs T-AP-1 (modelo inventado) — frequente em sessões com agente externo F3 Cowork

**Riscos não-mitigados (gaps assumidos):**

- Race conditions JS-side (R7) — não cobre. Resolve via [ADR 0211 TanStack Query](0211-tanstack-query-data-fetching-padrao.md)
- Drift entre TypeScript e PHP types — não cobre. Resolve via [ADR 0210 Wayfinder](0210-type-safety-end-to-end-wayfinder.md)

## Referências

- ADR 0094 — Constituição v2 §princípio 7 (transparência) + §princípio 5 (SoC brutal)
- ADR 0095 — Skills tiers convenção interna (Tier 0 enforcement)
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 15 anti-padrões técnicos catalogados
- [`memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md`](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) — Frente 5 do dossier
- [Tomas Votruba — Custom PHPStan rules Symfony](https://tomasvotruba.com/blog/custom-phpstan-rules-to-improve-every-symfony-project)
- [LTSCommerce — Project-level PHPStan rules](https://ltscommerce.dev/articles/phpstan-project-level-rules.html)
- [larastan/larastan GitHub](https://github.com/larastan/larastan)
