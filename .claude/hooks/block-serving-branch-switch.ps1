# block-serving-branch-switch.ps1 — PreToolUse:Bash
# Enforcement da R8 (ADR 0233): bloqueia troca de branch no checkout MAIN
# (D:\oimpresso.com) que serve o oimpresso.test via Herd. Trabalho de feature
# vai em worktree isolado. Worktrees linkados (.claude/worktrees/*) sao liberados.
#
# Fail-open: qualquer erro/parse-fail -> exit 0 (NUNCA trava sessao).
# Escape valve: incluir 'serving-branch-override' no comando (Wagner aprovou).

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $cmd = [string]$payload.tool_input.command
    if (-not $cmd) { exit 0 }

    # Escape valve explicito
    if ($cmd -match 'serving-branch-override') { exit 0 }

    # E uma TROCA de branch? (git switch <x>, git switch -c, git checkout -b, git checkout <ref>)
    $isSwitch = ($cmd -match 'git\s+switch\s+\S') -or
                ($cmd -match 'git\s+checkout\s+-b\s') -or
                ($cmd -match 'git\s+checkout\s+(?!--)(?!-)\S')
    if (-not $isSwitch) { exit 0 }
    # git checkout -- <path> (restaurar arquivo) nao e troca de branch
    if ($cmd -match 'git\s+checkout\s+--') { exit 0 }

    # Caminho efetivo do comando: 'cd <path>' explicito OU cwd do payload
    $path = $null
    if ($cmd -match 'cd\s+"?([A-Za-z]:[\\/][^"&;|]+|/[^"&;|]+)"?') { $path = $matches[1].Trim() }
    if (-not $path) { $path = [string]$payload.cwd }
    if (-not $path) { exit 0 }
    $path = ($path -replace '/', '\').TrimEnd('\')

    # Worktree linkado -> liberado (e o lugar certo de trabalhar)
    if ($path -match '\.claude\\worktrees\\') { exit 0 }

    # Checkout MAIN (raiz oimpresso.com) + troca de branch -> BLOQUEIA
    if ($path -match 'oimpresso\.com$') {
        Write-Error @"
[R8 / ADR 0233] BLOQUEADO: troca de branch no checkout MAIN ($path).
Esse e o checkout que serve o oimpresso.test (Herd). Trocar a branch aqui muda
o que o cliente ve e quebra codigo-x-banco (foi o erro da sessao 2026-05-29).

FACA NUM WORKTREE ISOLADO:
  cd D:\oimpresso.com
  git worktree add -b feat/<slug> .claude/worktrees/<slug> origin/main
  # trabalhe, commite e abra PR de dentro do worktree

Escape (so se Wagner aprovou explicito): inclua 'serving-branch-override' no comando.
"@
        exit 2
    }
    exit 0
} catch {
    exit 0
}
