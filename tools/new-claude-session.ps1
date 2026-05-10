<#
.SYNOPSIS
  Cria worktree git isolado pra nova sessão Claude Code paralela.

.DESCRIPTION
  Resolve o problema de 2-3 sessões Claude Code abertas simultaneamente
  no mesmo D:\oimpresso.com pisarem nas branches uma das outras
  (commits caindo na branch errada, git add capturando arquivos de sessão
  vizinha, etc).

  Cada sessão paralela ganha seu próprio diretório (worktree) + branch própria.
  Worktrees ficam em .claude/worktrees/<nome> que já é gitignored (CLAUDE.md).

.PARAMETER Name
  Nome curto da sessão (ex: "autopecas", "arquivos-sprint4").
  Vira branch claude/<name> e diretório .claude/worktrees/<name>.

.PARAMETER Base
  Branch base. Default: origin/main.

.EXAMPLE
  .\tools\new-claude-session.ps1 -Name autopecas
  # cria worktree em .claude/worktrees/autopecas com branch claude/autopecas

.EXAMPLE
  .\tools\new-claude-session.ps1 -Name nfe-fix -Base origin/release-2026-q2
  # base diferente
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory, Position = 0)]
    [string]$Name,

    [string]$Base = 'origin/main'
)

$ErrorActionPreference = 'Stop'

# Sanitiza nome
$safeName = ($Name -replace '[^a-zA-Z0-9_-]', '-').ToLower()
if ($safeName -ne $Name.ToLower()) {
    Write-Host "Nome ajustado: '$Name' -> '$safeName'" -ForegroundColor Yellow
}

$branch = "claude/$safeName"
$relPath = ".claude/worktrees/$safeName"

# Garante que estamos na raiz do repo
$repoRoot = (git rev-parse --show-toplevel).Trim()
if (-not $repoRoot) {
    Write-Error "Não estou em repo git."
    exit 1
}
Set-Location $repoRoot

# Fetch latest pra base estar fresca
Write-Host "Fetching origin..." -ForegroundColor Cyan
git fetch origin --quiet

# Verifica que base existe
git rev-parse --verify $Base *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Base '$Base' não existe. Tenta 'origin/main'."
    exit 1
}

# Verifica que worktree não existe
if (Test-Path $relPath) {
    Write-Error "Worktree já existe em $relPath. Use 'tools\list-claude-sessions.ps1' pra ver, ou remova com 'git worktree remove $relPath'."
    exit 1
}

# Verifica que branch não existe
$existingBranch = git branch --list $branch
if ($existingBranch) {
    Write-Error "Branch '$branch' já existe. Escolha outro nome ou apague: 'git branch -D $branch'."
    exit 1
}

# Cria worktree
Write-Host "Criando worktree $relPath na branch $branch (base $Base)..." -ForegroundColor Cyan
git worktree add -b $branch $relPath $Base
if ($LASTEXITCODE -ne 0) {
    Write-Error "git worktree add falhou."
    exit 1
}

$absPath = (Resolve-Path $relPath).Path

Write-Host ""
Write-Host "Worktree pronto:" -ForegroundColor Green
Write-Host "  Path:   $absPath"
Write-Host "  Branch: $branch"
Write-Host "  Base:   $Base"
Write-Host ""
Write-Host "Pra abrir uma sessao Claude Code isolada nele:" -ForegroundColor Cyan
Write-Host ""
Write-Host "  cd '$absPath'"
Write-Host "  claude"
Write-Host ""
Write-Host "Quando terminar (depois do PR mergeado):" -ForegroundColor Yellow
Write-Host ""
Write-Host "  git worktree remove $relPath"
Write-Host "  # branch ja foi mergeada via PR, nao precisa apagar manual"
Write-Host ""
