<#
.SYNOPSIS
  Lista worktrees ativos + status (uncommitted changes, branch, último commit).

.DESCRIPTION
  Mostra todas as sessões Claude paralelas que estão rodando ou foram esquecidas.
  Útil pra:
    - Ver o que cada sessão está mexendo
    - Identificar worktrees órfãos pra remover
    - Confirmar que cada sessão tá na branch certa

.EXAMPLE
  .\tools\list-claude-sessions.ps1
#>

[CmdletBinding()]
param()

$ErrorActionPreference = 'Continue'

$repoRoot = (git rev-parse --show-toplevel).Trim()
if (-not $repoRoot) {
    Write-Error "Não estou em repo git."
    exit 1
}

# Parse 'git worktree list --porcelain' (formato estável pra script)
$raw = git worktree list --porcelain
if (-not $raw) {
    Write-Host "Nenhum worktree encontrado (estranho — devia ter pelo menos o principal)." -ForegroundColor Yellow
    exit 0
}

$worktrees = @()
$current = $null

foreach ($line in $raw) {
    if ($line -match '^worktree (.+)$') {
        if ($current) { $worktrees += $current }
        $current = [PSCustomObject]@{
            Path     = $matches[1]
            Branch   = $null
            HEAD     = $null
            IsMain   = $false
            IsBare   = $false
        }
    }
    elseif ($line -match '^HEAD (.+)$') {
        $current.HEAD = $matches[1].Substring(0, 8)
    }
    elseif ($line -match '^branch refs/heads/(.+)$') {
        $current.Branch = $matches[1]
    }
    elseif ($line -match '^bare$') {
        $current.IsBare = $true
    }
    elseif ($line -match '^detached$') {
        $current.Branch = '(detached)'
    }
}
if ($current) { $worktrees += $current }

# Marca o principal
$mainWt = $worktrees | Where-Object { $_.Path -eq $repoRoot }
if ($mainWt) { $mainWt.IsMain = $true }

Write-Host ""
Write-Host "Sessoes Claude paralelas (git worktrees):" -ForegroundColor Cyan
Write-Host ""

foreach ($wt in $worktrees) {
    $marker = if ($wt.IsMain) { '[MAIN]' } else { '      ' }
    $branchDisplay = if ($wt.Branch) { $wt.Branch } else { '(sem branch)' }

    Write-Host "$marker $branchDisplay" -ForegroundColor Green
    Write-Host "        $($wt.Path)"

    # Status do worktree (uncommitted changes)
    if (Test-Path $wt.Path) {
        Push-Location $wt.Path
        try {
            $status = git status --porcelain 2>$null
            $uncommittedCount = if ($status) { @($status).Count } else { 0 }
            $lastCommit = git log -1 --format='%h %s' 2>$null
            if ($uncommittedCount -gt 0) {
                Write-Host "        $uncommittedCount arquivo(s) com mudancas nao-commitadas" -ForegroundColor Yellow
            }
            if ($lastCommit) {
                Write-Host "        ultimo: $lastCommit" -ForegroundColor DarkGray
            }
        }
        finally {
            Pop-Location
        }
    }
    else {
        Write-Host "        (path nao existe — worktree orfao, rodar 'git worktree prune')" -ForegroundColor Red
    }
    Write-Host ""
}

Write-Host "Comandos uteis:" -ForegroundColor Cyan
Write-Host "  Criar nova:   .\tools\new-claude-session.ps1 -Name <nome>"
Write-Host "  Remover:      git worktree remove <path>"
Write-Host "  Limpar orfaos: git worktree prune"
Write-Host ""
