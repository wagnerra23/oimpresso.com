---
id: reference-feedback-claude-worktree-isolation
name: Claude edita canon do oimpresso via atomic Bash chain (worktrees ad-hoc não servem)
description: Regra Wagner 2026-05-27 — quando Claude edita arquivos canon (memory/decisions, memory/reference, .claude/skills, ADRs etc) do oimpresso, e working dir é o main repo D:/oimpresso.com onde Wagner trabalha em paralelo trocando branches, SEMPRE usar atomic Bash chain (stash + checkout + edit + commit + push + checkout + stash pop em sequência sem janela pro Wagner intervir). Worktrees ad-hoc do harness ficam em .claude/worktrees que é gitignored — NÃO servem como isolation real. 3 incidentes documentados + pegadinha worktree ignored.
type: feedback
---

# Atomic Bash chain pra Claude editar canon do oimpresso

## Regra (Wagner 2026-05-27)

Quando Claude precisa **editar arquivo canon do oimpresso** (memory/decisions/, memory/reference/, .claude/skills/, ADRs, charters, SPECs, runbooks) E o working dir é `D:/oimpresso.com` (main repo):

- **SEMPRE** usar atomic Bash chain (stash + checkout main + edit + commit + push + checkout original + stash pop) **em sequência mínima de Bash calls com branch guard**, sem expor janela pro Wagner trocar de branch no meio.
- **NUNCA** editar canon via múltiplos Bash + Edits intercalados sem verificação de branch entre cada commit.

## Why — 3 incidentes nesta sessão 2026-05-27

Wagner trabalha em paralelo no main repo `D:/oimpresso.com` trocando branches rapidamente entre sessões VS Code, terminais, e seu próprio Claude Code. Meus **Bash calls são shells separados** — `cd /d/oimpresso.com && git checkout main` em um Bash NÃO persiste pra próximo Bash. Mas o working dir Git compartilhado pode ter trocado de branch entre meus comandos pelo Wagner.

**Incidentes desta sessão:**

| Commit | Intent | Branch alvo | Branch real (acidente) | Resolução |
|---|---|---|---|---|
| `f54567689` | regra commit SVN inicial | `main` | `main` (Wagner ainda em main por sorte) | ✅ OK direto |
| `2b530cf51` | runbook setup Felipe | `main` | `fix/cliente-drawer-rows-ie-cpfcnpj-rg` | Cherry-pick pra main + reset local da fix |
| `062fe8a97` | errata sistema.wr2.com | `main` | `chore/whatsapp-post-merge-cleanup` | Cherry-pick pra main + branch chore agora tem commit duplicado (Wagner resolve no merge) |

Padrão: cada vez que Wagner abre/fecha PR ou troca de branch no main repo, meu próximo commit cai onde ele estiver.

## Pegadinha — worktrees do harness são gitignored

Tentei "fugir" do main repo usando uma git worktree dedicada (ex `D:\oimpresso.com\.claude\worktrees\frosty-greider-XXX`). NÃO FUNCIONA porque:

- `.claude/worktrees/` está no `.gitignore` do main repo (linha 38)
- Qualquer arquivo criado/editado dentro dessa pasta NÃO pode ser trackado por `git add`
- Tentativa de commit retorna: `The following paths are ignored by one of your .gitignore files: .claude/worktrees`
- O `git rev-parse --git-dir` aponta de volta pro `D:/oimpresso.com/.git` — ou seja, é o MESMO repo, só working tree separado

Worktree real precisaria estar **FORA** de `.claude/worktrees/` (ex `D:/oimpresso-claude-canon`). Wagner pode criar uma se quiser:

```bash
git -C "D:/oimpresso.com" worktree add "D:/oimpresso-claude-canon" main
```

Daí Claude tem isolamento real (branch da worktree só muda quando Claude faz checkout explícito). Mas até Wagner aprovar isso, o padrão canon é Bash chain abaixo.

## How to apply — Atomic Bash chain canônica

### Setup: 1 chain pra preparar

```bash
cd /d/oimpresso.com && \
ORIG=$(git branch --show-current) && \
echo "Branch original: $ORIG" && \
git stash push -u -m "claude-temp: editing canon during ${ORIG}" 2>&1 | tail -1 && \
git checkout main && \
git pull --ff-only origin main && \
git log --oneline -1
```

Output esperado: `Branch original: <X>`, stash OK, `Switched to branch 'main'`, último commit de main.

### Edits: tools Edit/Write rapidamente (sem perguntas)

Edit/Write tools rodam fora de Bash chain. Fazer rapidamente em sequência, sem AskUserQuestion no meio (cada pergunta dá janela pro Wagner trocar branch).

### Commit + restore: 1 chain final COM BRANCH GUARD

```bash
cd /d/oimpresso.com && \
CUR=$(git branch --show-current) && \
if [ "$CUR" != "main" ]; then \
  echo "ABORT: Wagner trocou pra branch $CUR durante meus edits — cherry-pick depois"; \
  exit 1; \
fi && \
git add <arquivos canon específicos> && \
git commit -m "..." && \
git push origin main && \
git checkout <branch-original> && \
git stash pop 2>&1 | tail -3
```

Se branch guard ABORTA: Wagner trocou de branch no meio. Recovery:
1. Aceitar que o stash do Wagner ficou em outra branch — listar com `git stash list` e identificar
2. Cherry-pick os edits depois (em chain nova)

### Anti-pattern (anti-Padrão C) — NUNCA fazer

```bash
# Bash 1: cd && git checkout main → "Switched to main"
# (Wagner pode trocar branch agora — invisível pra mim)
# Edit arquivo X
# Edit arquivo Y
# Bash 2: git add X Y → git commit → git push
#         (commit cai em branch Z que Wagner trocou no meio)
```

Vários commits/Edits intercalados sem verificação de branch entre cada um. Foi exatamente o que aconteceu nos 3 incidentes desta sessão.

## When to apply

Aplica QUANDO **todas** condições verdadeiras:

1. ✅ Vai editar arquivo canon (memory/, .claude/skills/, ADRs, charters, SPECs)
2. ✅ Working dir é `D:/oimpresso.com` (main repo)
3. ✅ Wagner ou outro humano está ativo trabalhando em paralelo (típico — Wagner sempre tá ativo durante sessão)

NÃO aplica quando:
- Working dir é worktree REAL fora de `.claude/worktrees/` (ex `D:/oimpresso-claude-canon` se Wagner criar)
- Edit é em arquivo NÃO canônico (config local, temp, scratch) — branch importa menos
- Sessão é fora de horário Wagner (raro, mas existe — confirmar antes)

## Validation pós-commit

```bash
# 1. Branch alvo (main) tem meu commit?
git -C "D:/oimpresso.com" log origin/main --oneline -1
# Deve mostrar meu commit como HEAD

# 2. Branch original (do Wagner) intocada?
git -C "D:/oimpresso.com" log <branch-original> --oneline -3
# Deve mostrar trabalho do Wagner SEM commits meus acidentalmente nela
```

Se commit caiu em branch errada: cherry-pick imediato pra main + decidir se reset --hard local na branch errada (apenas se branch é local-only e não pushed; senão deixa duplicado pro Wagner resolver no merge).

## Ver também

- [feedback-commits-delphi-svn.md](feedback-commits-delphi-svn.md) — regra SVN READ-ONLY (Wagner pediu nesta mesma sessão)
- [feedback-recomendado-quando-tecnico.md](feedback-recomendado-quando-tecnico.md) — seguir recommended sem perguntar minimiza janela de race
- [ADR 0094 — Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Princípio 5 "SoC brutal: uma coisa, um lugar, um dono"
- [publication-policy](../../.claude/skills/publication-policy/) — escalação Wagner em publicação externa

## Histórico

| Data | Aprendizado | Custo (min) | Documentado em |
|---|---|---|---|
| 2026-05-27 | 3 incidentes commits em branch errada (sessão SVN Delphi). Tentativa de fugir via `.claude/worktrees` falhou (gitignored). Solução: atomic chain Bash com branch guard. | ~20 min de cherry-pick + reset + 1 tentativa worktree frustrada | este arquivo |
