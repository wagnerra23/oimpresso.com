# post-merge-ui-smoke-required.ps1 - Tier 0 IRREVOGAVEL smoke visual pos-merge UI.
#
# Wagner regra reincidente 2026-05-18 (2 reincidencias mesma sessao):
#   "sempre estou tendo que fazer isso, os metodos de memoria ainda nao estao
#    sendo garantidos por favor melhore isso e faca"
#
# feedback-brave-mcp-primeiro-sempre.md (2026-05-15) era Tier B feedback cultural
# e nao impedia Claude declarar "deployed" sem screenshot. Este hook mecaniza:
#
#   1. PostToolUse Bash matcher: detecta `gh pr merge --admin` de PR que tocou
#      arquivos UI (.tsx/.css/.blade.php sob resources/js OR resources/css OR
#      resources/views) → marca timestamp em $env:TEMP/oimpresso-ui-merge-pending.flag
#
#   2. PreToolUse generico: se flag existe E age < 5min E Claude tenta usar
#      Bash com strings "pronto"/"deployed"/"funcionando"/"live em prod"/
#      "confirmacao total" → BLOQUEIA com mensagem pedindo screenshot Chrome.
#
#   3. Flag e LIMPADA automaticamente quando Claude chama:
#      - mcp__computer-use__screenshot
#      - mcp__Claude_in_Chrome__navigate / read_page / javascript_tool
#      (qualquer ferramenta de browser indica que Claude esta de fato olhando)
#
# Escape valve: PR body com `<!-- no-ui-smoke: <razao> -->` pula o flag.
# Var env $env:OIMPRESSO_UI_SMOKE_OVERRIDE=1 desativa global.
#
# Exit codes:
#   0 = continua
#   2 = BLOQUEIA + mensagem stderr
#
# Refs:
#   - memory/proibicoes.md §"Claim sem evidencia" bullet pos-merge UI
#   - memory/reference/feedback-brave-mcp-primeiro-sempre.md
#   - memory/sessions/2026-05-17-arte-evidencia-llm-agents.md (estado-da-arte)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

$flagPath = Join-Path $env:TEMP 'oimpresso-ui-merge-pending.flag'
$flagTtlMinutes = 5

# Le payload JSON via stdin
$rawInput = [Console]::In.ReadToEnd()
if ([string]::IsNullOrWhiteSpace($rawInput)) { exit 0 }

try {
    $payload = $rawInput | ConvertFrom-Json -ErrorAction Stop
} catch {
    exit 0
}

# Escape valve global
if ($env:OIMPRESSO_UI_SMOKE_OVERRIDE -eq '1') { exit 0 }

$toolName = $payload.tool_name
$hookEvent = $payload.hook_event_name

# ============================================================================
# CASO 1: PostToolUse — marca flag apos gh pr merge de PR com UI files
# ============================================================================
if ($hookEvent -eq 'PostToolUse' -and $toolName -eq 'Bash') {
    $command = $payload.tool_input.command
    if ([string]::IsNullOrWhiteSpace($command)) { exit 0 }

    # Detecta merge admin
    if ($command -notmatch 'gh\s+pr\s+merge.*--admin') { exit 0 }

    # Extrai PR number do command
    $prMatch = [regex]::Match($command, 'gh\s+pr\s+merge\s+(\d+)')
    if (-not $prMatch.Success) { exit 0 }
    $prNumber = $prMatch.Groups[1].Value

    # Verifica se PR tocou arquivos UI via gh CLI (silent fail OK)
    try {
        $files = & gh pr view $prNumber --json files -q '.files[].path' 2>$null
        if (-not $files) { exit 0 }

        $touchedUI = $false
        foreach ($f in $files -split "`n") {
            if ($f -match 'resources/(js|css)/.+\.(tsx?|css)$' -or
                $f -match 'resources/views/.+\.blade\.php$' -or
                $f -match 'Modules/.+/Resources/views/.+\.blade\.php$') {
                $touchedUI = $true
                break
            }
        }

        if (-not $touchedUI) { exit 0 }

        # Verifica escape valve no PR body
        $body = & gh pr view $prNumber --json body -q '.body' 2>$null
        if ($body -match '<!--\s*no-ui-smoke') { exit 0 }

        # Marca flag com timestamp + PR number
        $flagContent = "{0}|{1}" -f (Get-Date -Format 'o'), $prNumber
        Set-Content -Path $flagPath -Value $flagContent -Encoding UTF8 -NoNewline
        Write-Host "[ui-smoke-required] PR #$prNumber tocou UI files. Smoke Chrome MCP obrigatorio antes de declarar 'pronto'." -ForegroundColor Yellow
    } catch {
        # Silent fail — nao bloqueia em erro de gh CLI
    }
    exit 0
}

# ============================================================================
# CASO 2: PreToolUse — limpa flag se Claude chama browser MCP
# ============================================================================
if ($hookEvent -eq 'PreToolUse') {
    if ($toolName -match '^mcp__(computer-use|Claude_in_Chrome|Windows-MCP)__' -and
        $toolName -match '(screenshot|navigate|read_page|javascript_tool|get_page_text|find)') {
        if (Test-Path $flagPath) {
            Remove-Item $flagPath -Force -ErrorAction SilentlyContinue
        }
        exit 0
    }
}

# ============================================================================
# CASO 3: PreToolUse Bash — bloqueia se flag valida E texto claim
# ============================================================================
if ($hookEvent -eq 'PreToolUse' -and $toolName -eq 'Bash') {
    if (-not (Test-Path $flagPath)) { exit 0 }

    # Le flag + checa TTL
    try {
        $flagRaw = Get-Content $flagPath -Raw -ErrorAction Stop
        $parts = $flagRaw -split '\|'
        $flagTs = [DateTime]::Parse($parts[0])
        $flagPR = if ($parts.Length -gt 1) { $parts[1] } else { '?' }
        $age = (Get-Date) - $flagTs
        if ($age.TotalMinutes -ge $flagTtlMinutes) {
            Remove-Item $flagPath -Force -ErrorAction SilentlyContinue
            exit 0
        }
    } catch {
        Remove-Item $flagPath -Force -ErrorAction SilentlyContinue
        exit 0
    }

    $command = $payload.tool_input.command
    if ([string]::IsNullOrWhiteSpace($command)) { exit 0 }

    # Aceita echo comum, blocking so claims explicitos em messages
    # (echo "pronto" ou bash heredoc com claim) — pratico mas conservador
    if ($command -notmatch '(?i)(pronto|deployed|funcionando|live em prod|confirmacao total|smoke ok|merge concluido)') {
        exit 0
    }

    # BLOQUEIA
    $msg = @"
[BLOCKED: Smoke visual pos-merge UI obrigatorio Tier 0]

PR #$flagPR mergeado ha $([Math]::Round($age.TotalSeconds, 0))s tocou arquivos UI (.tsx/.css/.blade.php).
Wagner regra IRREVOGAVEL 2026-05-18 (memory/proibicoes.md §Claim sem evidencia):
  Apos qualquer merge UI, OBRIGATORIO Chrome MCP + screenshot ANTES de declarar
  'pronto/deployed/funcionando/live em prod/smoke ok/merge concluido'.

Comando bloqueado: $command

A FAZER (ordem):
  1. mcp__Claude_in_Chrome__navigate pra rota afetada (https://oimpresso.com/...)
  2. mcp__Claude_in_Chrome__javascript_tool ou mcp__computer-use__screenshot
  3. Relatar o que viu no chat
  4. AI declarar 'pronto' / 'deployed'

Escape valve: rodar com `\$env:OIMPRESSO_UI_SMOKE_OVERRIDE='1'` (justifique no chat).
Ou marcar PR body com `<!-- no-ui-smoke: <razao> -->`.

Refs:
  memory/proibicoes.md §"Claim sem evidencia"
  memory/reference/feedback-brave-mcp-primeiro-sempre.md
"@
    [Console]::Error.WriteLine($msg)
    exit 2
}

exit 0
