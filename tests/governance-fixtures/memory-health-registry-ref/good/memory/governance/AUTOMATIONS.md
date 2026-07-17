---
slug: selftest-automations-good
title: "Fixture BOA — registry de automações apontando pra arquivo VIVO"
type: governance-spec
authority: canonical
lifecycle: ativo
maintained_by: wagner
pii: false
---

# Fixture BOA — Check P (memory-health)

Registry cuja coluna "Arquivo" aponta pra um path que EXISTE em disco.
Espelha o estado saudável: o porte `.ps1`→`.mjs` atualizou o registry no mesmo PR.

## Hooks PreToolUse

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Bash` | `selftest-demo` | Hook de demonstração da fixture. | `.claude/hooks/selftest-demo.mjs` |

## Prosa que o Check P deve IGNORAR (controle de falso-positivo)

Estes casos são prosa, não promessa de path concreto — o check não pode acusá-los:

- glob: `.claude/worktrees/*` e `.claude/rules/*.md`
- placeholder: `.claude/hooks/<nome>.ps1` e `.claude/skills/<slug>/SKILL.md`
- diretório: `.claude/hooks/` e `.claude/agents/`
- template: `.claude/run/curl-evidence-NNNN.txt`
