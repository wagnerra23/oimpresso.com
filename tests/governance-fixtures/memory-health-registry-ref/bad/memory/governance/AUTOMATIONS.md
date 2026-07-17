---
slug: selftest-automations-bad
title: "Fixture RUIM — registry apontando pra arquivo MORTO (porte esqueceu o registry)"
type: governance-spec
authority: canonical
lifecycle: ativo
maintained_by: wagner
pii: false
---

# Fixture RUIM — Check P (memory-health)

Reproduz a reincidência real (PRs #4028/#4035 → 4 refs mortas em main, consertadas no
#4416): o hook FOI portado `.ps1`→`.mjs` (o `.mjs` existe nesta fixture), mas a coluna
"Arquivo" do registry continua apontando pro `.ps1` que não existe mais.

## Hooks PreToolUse

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Bash` | `selftest-demo` | Hook de demonstração da fixture. | `.claude/hooks/selftest-demo.ps1` |

## Prosa que o Check P deve IGNORAR (controle de falso-positivo)

Idêntica à fixture boa — se o check acusasse estes, o `bad` viraria vermelho pelo
motivo ERRADO e o teste não provaria nada:

- glob: `.claude/worktrees/*` e `.claude/rules/*.md`
- placeholder: `.claude/hooks/<nome>.ps1` e `.claude/skills/<slug>/SKILL.md`
- diretório: `.claude/hooks/` e `.claude/agents/`
- template: `.claude/run/curl-evidence-NNNN.txt`
