---
name: Branch de trabalho atual é 6.7-bootstrap
description: Wagner está commitando direto em 6.7-bootstrap, não em main. Verificar branch atual antes de commit/PR.
type: project
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Branch ativa de desenvolvimento em 2026-04-24 é `6.7-bootstrap` (não `main`).

**Why:** Wagner está refazendo o bootstrap da v6.7 nessa branch após a migração da v3.7. Commits recentes dele e os 4 fixes de /sells + timezone (2026-04-24) foram todos em `6.7-bootstrap`. A `main` está atrás.

**How to apply:**
- Antes de commit/PR, confirmar `git branch --show-current` — se for `main`, provavelmente está errado
- Ao abrir PR: base deve ser `6.7-bootstrap`, não `main`
- Deploy no Hostinger: `git pull origin 6.7-bootstrap`
- Quando `6.7-bootstrap` for mergeada em `main` (ainda não datado), esta memória fica obsoleta — atualizar ou remover
