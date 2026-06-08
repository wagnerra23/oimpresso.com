# Smoke test do hook block-module-drift.ps1
#
# Valida Mecanismo #3 (Pre-commit hook drift detection) de ENFORCEMENT.md §2.
# Cobre 6 casos. Exit 0 = 6/6 passam.
#
# Rodar manual:
#   pwsh .claude/hooks/block-module-drift.test.ps1
#   powershell .claude/hooks/block-module-drift.test.ps1

$ErrorActionPreference = 'Stop'

$hookPath = Join-Path $PSScriptRoot 'block-module-drift.ps1'
if (-not (Test-Path $hookPath)) {
    Write-Error "Hook nao encontrado em $hookPath"
    exit 2
}

# Detecta runtime PowerShell disponivel (pwsh 7+ preferido; powershell 5.1 fallback)
if (Get-Command pwsh -ErrorAction SilentlyContinue) {
    $script:psBin = 'pwsh'
} elseif (Get-Command powershell -ErrorAction SilentlyContinue) {
    $script:psBin = 'powershell'
} else {
    Write-Error "Nem pwsh nem powershell encontrados no PATH"
    exit 2
}

# Resolve CLAUDE_PROJECT_DIR pra raiz do worktree (2 niveis acima de .claude/hooks/)
$projectDir = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$script:claudeProjectDir = $projectDir.Path

function Invoke-Hook {
    param(
        [string]$ToolName,
        [string]$FilePath,
        [hashtable]$Env = @{}
    )

    $payload = @{
        tool_name  = $ToolName
        tool_input = @{ file_path = $FilePath }
    } | ConvertTo-Json -Compress

    # Define env vars do teste (CLAUDE_PROJECT_DIR + opcionais)
    $oldEnv = @{}
    $allEnv = @{ CLAUDE_PROJECT_DIR = $script:claudeProjectDir }
    foreach ($k in $Env.Keys) { $allEnv[$k] = $Env[$k] }

    foreach ($k in $allEnv.Keys) {
        $oldEnv[$k] = [System.Environment]::GetEnvironmentVariable($k)
        [System.Environment]::SetEnvironmentVariable($k, $allEnv[$k])
    }

    # Limpa env vars que podem vazar de teste anterior (mas nao estao em allEnv)
    foreach ($k in @('OIMPRESSO_DRIFT_HOOK_MODE', 'OIMPRESSO_DRIFT_OVERRIDE')) {
        if (-not $allEnv.ContainsKey($k)) {
            $oldEnv[$k] = [System.Environment]::GetEnvironmentVariable($k)
            [System.Environment]::SetEnvironmentVariable($k, $null)
        }
    }

    try {
        # Usa Start-Process pra capturar stdout/stderr/exitCode sem que PowerShell
        # interprete stderr nao-vazio como NativeCommandError.
        $tmpStdin  = [System.IO.Path]::GetTempFileName()
        $tmpStdout = [System.IO.Path]::GetTempFileName()
        $tmpStderr = [System.IO.Path]::GetTempFileName()
        try {
            Set-Content -Path $tmpStdin -Value $payload -NoNewline -Encoding ASCII

            # Wrapper cmd `<` redireciona stdin ao ps1 hook
            $cmdLine = "$script:psBin -NoProfile -File `"$hookPath`" < `"$tmpStdin`""

            $procInfo = Start-Process -FilePath 'cmd.exe' `
                -ArgumentList '/c', $cmdLine `
                -NoNewWindow -Wait -PassThru `
                -RedirectStandardOutput $tmpStdout `
                -RedirectStandardError $tmpStderr

            $stdout = Get-Content $tmpStdout -Raw -ErrorAction SilentlyContinue
            $stderr = Get-Content $tmpStderr -Raw -ErrorAction SilentlyContinue
            if (-not $stdout) { $stdout = '' }
            if (-not $stderr) { $stderr = '' }
            $exitCode = $procInfo.ExitCode
        } finally {
            Remove-Item $tmpStdin, $tmpStdout, $tmpStderr -ErrorAction SilentlyContinue
        }

        return @{
            stdout   = [string]$stdout
            stderr   = [string]$stderr
            exitCode = $exitCode
        }
    } finally {
        # Restaura env vars
        foreach ($k in $oldEnv.Keys) {
            [System.Environment]::SetEnvironmentVariable($k, $oldEnv[$k])
        }
    }
}

$failures = @()

# ============================================================================
# CASO 1: Controller DECLARADO em contains[] (Jana/Http/Controllers/ChatController.php)
#   -> exit 0, sem warning no stderr
# ============================================================================

$res1 = Invoke-Hook -ToolName 'Edit' -FilePath 'Modules/Jana/Http/Controllers/ChatController.php'

if ($res1.exitCode -eq 0 -and ($res1.stderr -notmatch 'DRIFT DETECTADO') -and ($res1.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 1: ChatController declarado em SCOPE.md -> passou limpo"
} else {
    Write-Host "[FAIL] Caso 1: Controller declarado nao deveria disparar drift. stderr=$($res1.stderr) stdout=$($res1.stdout)"
    $failures += 'caso1'
}

# ============================================================================
# CASO 2: Controller NAO DECLARADO, modo WARN (default)
#   -> exit 0, warning no stderr, sem deny no stdout
# ============================================================================

$res2 = Invoke-Hook -ToolName 'Write' -FilePath 'Modules/Jana/Http/Controllers/NaoDeclaradoXyzController.php'

if ($res2.exitCode -eq 0 -and ($res2.stderr -match 'DRIFT DETECTADO') -and ($res2.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 2: drift em modo WARN -> exit 0 + warning stderr"
} else {
    Write-Host "[FAIL] Caso 2: warn deveria gerar stderr sem bloquear. exitCode=$($res2.exitCode) stderr=$($res2.stderr) stdout=$($res2.stdout)"
    $failures += 'caso2'
}

# ============================================================================
# CASO 3: Modo STRICT + drift
#   -> emite JSON deny no stdout
# ============================================================================

$res3 = Invoke-Hook -ToolName 'Write' -FilePath 'Modules/Jana/Http/Controllers/NaoDeclaradoStrictController.php' -Env @{
    OIMPRESSO_DRIFT_HOOK_MODE = 'strict'
}

if ($res3.stdout -match '"decision":"deny"') {
    Write-Host "[OK] Caso 3: modo STRICT bloqueia drift via decision deny"
} else {
    Write-Host "[FAIL] Caso 3: strict deveria emitir decision deny. stdout=$($res3.stdout) stderr=$($res3.stderr)"
    $failures += 'caso3'
}

# ============================================================================
# CASO 4: Modo OFF
#   -> exit 0 sem nem ler SCOPE.md
# ============================================================================

$res4 = Invoke-Hook -ToolName 'Write' -FilePath 'Modules/Jana/Http/Controllers/SeraIgnoradoController.php' -Env @{
    OIMPRESSO_DRIFT_HOOK_MODE = 'off'
}

if ($res4.exitCode -eq 0 -and ($res4.stderr -notmatch 'DRIFT') -and ($res4.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 4: modo OFF -> silencioso"
} else {
    Write-Host "[FAIL] Caso 4: off deveria silenciar tudo. stderr=$($res4.stderr) stdout=$($res4.stdout)"
    $failures += 'caso4'
}

# ============================================================================
# CASO 5: Override env var (OIMPRESSO_DRIFT_OVERRIDE=1) mesmo em STRICT
#   -> exit 0 sem checar
# ============================================================================

$res5 = Invoke-Hook -ToolName 'Write' -FilePath 'Modules/Jana/Http/Controllers/EmergenciaController.php' -Env @{
    OIMPRESSO_DRIFT_HOOK_MODE = 'strict'
    OIMPRESSO_DRIFT_OVERRIDE  = '1'
}

if ($res5.exitCode -eq 0 -and ($res5.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 5: OIMPRESSO_DRIFT_OVERRIDE=1 pula check (Tier 0 emergencia)"
} else {
    Write-Host "[FAIL] Caso 5: override deveria pular check. stdout=$($res5.stdout) stderr=$($res5.stderr)"
    $failures += 'caso5'
}

# ============================================================================
# CASO 6: Path fora de Modules/<X>/Http/Controllers/
#   -> exit 0 sem checar (nao eh Controller de modulo)
# ============================================================================

$res6 = Invoke-Hook -ToolName 'Write' -FilePath 'app/Http/Controllers/AlgumController.php'

if ($res6.exitCode -eq 0 -and ($res6.stderr -notmatch 'DRIFT') -and ($res6.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Caso 6: path fora de Modules/ ignorado"
} else {
    Write-Host "[FAIL] Caso 6: path fora de Modules deveria ser ignorado. stderr=$($res6.stderr) stdout=$($res6.stdout)"
    $failures += 'caso6'
}

# ============================================================================
# BONUS: Controller em subfolder Admin/ declarado (CustosController) -> passa
# ============================================================================

$res7 = Invoke-Hook -ToolName 'Edit' -FilePath 'Modules/Jana/Http/Controllers/Admin/CustosController.php'

if ($res7.exitCode -eq 0 -and ($res7.stderr -notmatch 'DRIFT') -and ($res7.stdout -notmatch '"decision":"deny"')) {
    Write-Host "[OK] Bonus: Admin/CustosController em subfolder declarado -> passou limpo"
} else {
    Write-Host "[FAIL] Bonus: Admin/CustosController declarado nao deveria disparar. stderr=$($res7.stderr) stdout=$($res7.stdout)"
    $failures += 'bonus'
}

Write-Host ""
if ($failures.Count -eq 0) {
    Write-Host "[PASS] 7/7 casos validados (Mecanismo #3 ENFORCEMENT operacional)" -ForegroundColor Green
    exit 0
} else {
    Write-Host "[FAIL] $($failures.Count)/7 casos falharam: $($failures -join ', ')" -ForegroundColor Red
    exit 1
}
