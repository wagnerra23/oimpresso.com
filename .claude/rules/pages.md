---
paths:
  - "resources/js/Pages/**/*.tsx"
---

# Rule path-scoped — `resources/js/Pages/**/*.tsx`

> Carrega quando Claude lê/edita página Inertia React. Complementa skills Tier A `mwart-process`, `mwart-comparative`, `charter-first`.

## MWART canônico — único caminho ([ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md))

5 fases obrigatórias antes de qualquer Edit/Write em `<Tela>.tsx`:

1. **F1** — DISCOVERY (entender Blade legado se migração)
2. **F1.5** — Gate visual + protótipo `prototipo-ui/<modulo>/<tela>/` ([ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md))
3. **F2** — BACKEND BASELINE com Pest 5+ fixtures do `store()` ANTES de mexer
4. **F3** — FRONTEND (este passo) — ler charter `<Tela>.charter.md` ao lado obrigatório
5. **F4** — QA com smoke biz=1 ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))

**RUNBOOK obrigatório:** Edit em `.tsx` SEM `memory/requisitos/<Modulo>/RUNBOOK-<tela-kebab>.md` existir é BLOQUEADO pelo hook [`block-mwart-violation.ps1`](../hooks/block-mwart-violation.ps1) + CI workflow `mwart-gate.yml`. Override: `/mwart-override <razão>` em PR (vira ADR per-tela `lifecycle: historical`).

## Loop Cowork ↔ Claude Code formalizado ([ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md))

Skill `mwart-comparative V4` orquestra Claude Design plugin Anthropic (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis). 15 dimensões. Wagner aprova **SCREENSHOT** (não tabela markdown).

## Inertia::defer DEFAULT em props caras (Tier 0 desde 2026-05-15)

[RUNBOOK-inertia-defer-pattern.md](../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md): toda prop com `paginate()`, `count()`, `with()` eager-load, Service DB, subquery scalar, HTTP externo **DEVE** ser `Inertia::defer(fn () => $this->buildXxxPayload(...))`. Frontend wrap em `<Deferred data="..." fallback={skeleton}>`. Validado D-14: 300ms → 50ms.

## Anti-padrões F3 catalogados

Antes de Edit/Write em `<Tela>.tsx` ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 meta-anti-padrões + 15 técnicos catalogados sessão 2026-05-09 batch Financeiro rejeitado.

## Skills relacionadas

`mwart-process` (Tier A) · `mwart-comparative` (Tier A) · `charter-first` (Tier A) · `inertia-defer-default` (Tier B) · `migracao-blade-react` (Tier B)
