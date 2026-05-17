# block-claim-without-evidence.ps1 - Camada B enforcement claim-evidence (sessao 2026-05-17).
#
# Hook PreToolUse Bash matcher.
# Detecta gh pr create / gh pr merge --admin / git push pra branch que toca infra critica
# e BLOQUEIA se body do PR (ou ultimos N commits) NAO tem evidencia curl/HTTP literal.
#
# Defesa em profundidade pareada com:
# - Camada A: .github/workflows/infra-contract-required.yml (CI gate, mais robusto)
# - Skill Tier B: .claude/skills/smoke-prod-evidence/SKILL.md (cultural)
# - Template: memory/templates/INFRA-CONTRACT.md (formato canon)
#
# Escape valve:
#   commit message ou PR body contem: # evidence-override: <razao> (linha)
#   ou: <!-- evidence-override: <razao> --> (PR body HTML comment)
#   variavel env OIMPRESSO_EVIDENCE_OVERRIDE=1 (Wagner Tier 0 emergencia)
#
# Exit codes:
#   0 = continua (ok ou irrelevante)
#   2 = BLOQUEIA + mensagem stderr
#
# Ref: memory/sessions/2026-05-17-arte-evidencia-llm-agents.md (pesquisa estado-da-arte)
# Ref: memory/templates/INFRA-CONTRACT.md (template Sprint Contract upfront)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

# Le payload JSON via stdin (Claude Code hook protocol)
$rawInput = [Console]::In.ReadToEnd()

if ([string]::IsNullOrWhiteSpace($rawInput)) {
    exit 0
}

try {
    $payload = $rawInput | ConvertFrom-Json -ErrorAction Stop
} catch {
    exit 0
}

# So olha Bash tool
if ($payload.tool_name -ne 'Bash') {
    exit 0
}

$command = $payload.tool_input.command
if ([string]::IsNullOrWhiteSpace($command)) {
    exit 0
}

# Escape valve global: env var
if ($env:OIMPRESSO_EVIDENCE_OVERRIDE -eq '1') {
    Write-Warning '[block-claim-without-evidence] OIMPRESSO_EVIDENCE_OVERRIDE=1 ativo - pulando check.'
    exit 0
}

# Triggers: gh pr create / gh pr merge --admin / git push pra branch claude/...
$triggersRegex = '(gh pr create|gh pr merge.*--admin|gh pr merge.*--squash)'
if ($command -notmatch $triggersRegex) {
    exit 0
}

# Detecta se branch atual ou diff toca infra critica
$infraPathsRegex = '\.htaccess|app[/\\]Http[/\\]Middleware|app[/\\]Http[/\\]Kernel\.php|^routes[/\\]|app[/\\]Providers[/\\][A-Z][a-zA-Z]*ServiceProvider\.php|bootstrap[/\\]app\.php'

try {
    # git diff vs origin/main (mais robusto que current branch)
    $diff = git diff --name-only origin/main...HEAD 2>$null
    if (-not $diff) {
        # Fallback: diff staged + unstaged
        $diff = git diff --name-only HEAD 2>$null
    }
    if (-not $diff) {
        exit 0
    }
} catch {
    exit 0
}

$infraFiles = $diff | Where-Object { $_ -match $infraPathsRegex }
if (-not $infraFiles) {
    # Diff nao toca infra critica - irrelevante
    exit 0
}

# Toca infra. Buscar evidencia:
# 1. PR body (se gh pr create --body fornecido inline)
# 2. Commit messages das ultimas 5 commits da branch
# 3. .claude/run/curl-evidence-*.txt criado <30min atras

$evidenceFound = $false
$overrideFound = $false
$overrideReason = ''

# Check 1: command line tem --body com evidencia
if ($command -match '--body') {
    $bodyMatch = [regex]::Match($command, '--body[\s=]+["\x27](.+?)["\x27]', [System.Text.RegularExpressions.RegexOptions]::Singleline)
    if ($bodyMatch.Success) {
        $body = $bodyMatch.Groups[1].Value
        if ($body -match 'curl -sv|< HTTP/1\.[01]|HTTP/2|## Infra Contract|## Valida') {
            $evidenceFound = $true
        }
        if ($body -match '<!--\s*evidence-override:\s*([^>]+?)\s*-->') {
            $overrideFound = $true
            $overrideReason = $matches[1]
        }
    }
}

# Check 2: ultimos 5 commits messages
if (-not $evidenceFound -and -not $overrideFound) {
    try {
        $commits = git log -5 --format='%B' 2>$null | Out-String
        if ($commits -match 'curl -sv|< HTTP/1\.[01]|HTTP/2|## Valida|## Infra Contract|smoke prod ok|smoke real') {
            $evidenceFound = $true
        }
        if ($commits -match '#\s*evidence-override:\s*(.+)$' -or $commits -match '<!--\s*evidence-override:\s*([^>]+?)\s*-->') {
            $overrideFound = $true
            $overrideReason = $matches[1]
        }
    } catch {
        # silent
    }
}

# Check 3: arquivo evidencia recente em .claude/run/
if (-not $evidenceFound -and -not $overrideFound) {
    $runDir = Join-Path (Get-Location) '.claude/run'
    if (Test-Path $runDir) {
        $cutoff = (Get-Date).AddMinutes(-30)
        $recentEvidence = Get-ChildItem $runDir -Filter 'curl-evidence-*.txt' -ErrorAction SilentlyContinue |
                          Where-Object { $_.LastWriteTime -gt $cutoff }
        if ($recentEvidence) {
            $evidenceFound = $true
        }
    }
}

if ($overrideFound) {
    Write-Warning ('[block-claim-without-evidence] Evidence override ativo: ' + $overrideReason)
    Write-Warning '[block-claim-without-evidence] Wagner audita override via governance:detect-drift cron'
    exit 0
}

if ($evidenceFound) {
    Write-Host '[block-claim-without-evidence] OK - evidencia curl/HTTP detectada'
    exit 0
}

# BLOQUEIA
$infraFilesList = ($infraFiles | Select-Object -First 5) -join ', '

$msg = @'

================================================================================
BLOQUEADO: PR toca infra critica sem evidencia curl/HTTP
================================================================================

Arquivos infra detectados no diff:
  __INFRA_FILES__

Antes de criar/mergear PR, voce precisa UM dos seguintes:

  [1] PR body com secao "## Infra Contract" OU "## Validacao prod" contendo:
      - Comando "curl -sv https://oimpresso.com/<rota>"
      - Status code literal "< HTTP/1.1 NNN" colado
      Template: memory/templates/INFRA-CONTRACT.md

  [2] Commit message recente (ultimas 5 commits) com "curl -sv" ou status HTTP literal

  [3] Arquivo .claude/run/curl-evidence-*.txt criado nos ultimos 30 minutos

  [4] Hotfix legitimo: adicione no PR body:
      <!-- evidence-override: razao concreta -->
      OU no commit message:
      # evidence-override: razao

  [5] Emergencia Tier 0 Wagner:
      $env:OIMPRESSO_EVIDENCE_OVERRIDE='1' antes do comando

Origem do bloqueio: 3 PRs em cascata #1024 #1026 #1028 (17/mai/2026)
                    - declaracoes precoces sem curl prod.

Pesquisa estado-da-arte que motivou:
  memory/sessions/2026-05-17-arte-evidencia-llm-agents.md

Skill cultural pareada (Tier B auto-trigger):
  .claude/skills/smoke-prod-evidence/SKILL.md
================================================================================
'@

$msg = $msg.Replace('__INFRA_FILES__', $infraFilesList)
[Console]::Error.WriteLine($msg)
exit 2
