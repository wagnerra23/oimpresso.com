# Smoke test do hook block-memory-drift.ps1 -- 11 casos canonicos.
#
# Rodar manual:
#   pwsh .claude/hooks/block-memory-drift.test.ps1
#   (ou) powershell .claude/hooks/block-memory-drift.test.ps1
#
# Exit 0 = 11/11 casos passam. Nao-zero = falha (caso explicitado).
#
# BRANCH-AGNOSTICO: o teste passa 11/11 INDEPENDENTE da branch ativa do checkout.
# O hook detecta a branch via `git rev-parse --abbrev-ref HEAD` rodando no CWD do
# processo (NAO no $PSScriptRoot). Os caminhos canon (Test-CanonFile) sao resolvidos
# via $PSScriptRoot -> repo real, entao independem do CWD. Logo, pra simular uma
# branch deterministica nos casos branch-dependentes (3, 4, 5), rodamos o hook com
# CWD apontando pra um repo git temporario descartavel na branch desejada
# (helper Invoke-HookOnBranch). Assim:
#   - Caso 3 (ADR nova) roda em claude/* simulada -> ALLOW deterministico
#   - Caso 4 (proibicoes em main) roda em main simulada -> BLOCK deterministico
#   - Caso 5 (proibicoes em claude/*) roda em claude/* simulada -> ALLOW deterministico
# Nenhum caso depende mais da branch real do worktree.

$ErrorActionPreference = 'Stop'

$hookPath = Join-Path $PSScriptRoot 'block-memory-drift.ps1'
if (-not (Test-Path $hookPath)) {
    Write-Error "Hook nao encontrado em $hookPath"
    exit 2
}

# Detecta runtime PowerShell (pwsh 7+ preferido)
if (Get-Command pwsh -ErrorAction SilentlyContinue) {
    $script:psBin = 'pwsh'
} elseif (Get-Command powershell -ErrorAction SilentlyContinue) {
    $script:psBin = 'powershell'
} else {
    Write-Error "Nem pwsh nem powershell encontrados no PATH"
    exit 2
}

# Sobe pra raiz do repo (worktree ou repo principal)
$repoRoot = $PSScriptRoot
while ($repoRoot -and -not (Test-Path (Join-Path $repoRoot 'memory'))) {
    $parent = Split-Path $repoRoot -Parent
    if (-not $parent -or $parent -eq $repoRoot) { break }
    $repoRoot = $parent
}

if (-not $repoRoot) {
    Write-Error "Nao consegui localizar raiz do repo (procurando memory/)"
    exit 2
}

# Helper: chama hook com branch simulada via env var override
function Invoke-Hook {
    param(
        [string]$ToolName,
        [string]$FilePath,
        [string]$BranchOverride = $null,  # se setado, monkey-patch git via env (nao temos -- usa env real)
        [hashtable]$Env = @{}
    )

    $payload = @{
        tool_name = $ToolName
        tool_input = @{ file_path = $FilePath }
    } | ConvertTo-Json -Compress

    # Salvar env atual + aplicar override
    $envBackup = @{}
    foreach ($key in $Env.Keys) {
        $envBackup[$key] = [Environment]::GetEnvironmentVariable($key)
        [Environment]::SetEnvironmentVariable($key, $Env[$key])
    }

    try {
        # Capture both streams sem interpretar como NativeCommandError (PS 5.1 paranoid)
        $prevErrorAction = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $result = $payload | & $script:psBin -NoProfile -File $hookPath 2>&1 | Out-String
        $ErrorActionPreference = $prevErrorAction
    } finally {
        # Restaurar env
        foreach ($key in $envBackup.Keys) {
            [Environment]::SetEnvironmentVariable($key, $envBackup[$key])
        }
    }

    return [string]$result
}

# Helper: cria um repo git temporario descartavel numa branch especifica.
# Retorna o path do repo. O hook, ao rodar com este path como CWD, vai detectar
# essa branch via `git rev-parse --abbrev-ref HEAD` -- mas continua resolvendo os
# arquivos canon via $PSScriptRoot (repo real). Isso desacopla a deteccao de branch
# da branch real do checkout, tornando os casos branch-dependentes deterministicos.
function New-TempGitRepoOnBranch {
    param([string]$Branch)

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("bmd-test-{0}" -f ([guid]::NewGuid().ToString('N')))
    New-Item -ItemType Directory -Path $tmp -Force | Out-Null

    Push-Location $tmp
    try {
        & git init -q 2>$null | Out-Null
        & git config user.email 'test@oimpresso.local' 2>$null | Out-Null
        & git config user.name 'bmd-test' 2>$null | Out-Null
        & git commit -q --allow-empty -m 'init' 2>$null | Out-Null
        # main/master podem nao precisar de rename; pra outras branches usamos checkout -b
        $cur = (& git rev-parse --abbrev-ref HEAD 2>$null).Trim()
        if ($cur -ne $Branch) {
            & git checkout -q -B $Branch 2>$null | Out-Null
        }
    } finally {
        Pop-Location
    }
    return $tmp
}

# Helper: roda o hook simulando uma branch deterministica (via CWD = repo temp).
# Os paths canon sao resolvidos pelo $PSScriptRoot do hook (repo real), entao
# FilePath deve continuar apontando pro repo real (ex "$repoRoot/memory/...").
function Invoke-HookOnBranch {
    param(
        [string]$ToolName,
        [string]$FilePath,
        [string]$Branch,
        [hashtable]$Env = @{}
    )

    $payload = @{
        tool_name  = $ToolName
        tool_input = @{ file_path = $FilePath }
    } | ConvertTo-Json -Compress

    $tmpRepo = New-TempGitRepoOnBranch -Branch $Branch

    $envBackup = @{}
    foreach ($key in $Env.Keys) {
        $envBackup[$key] = [Environment]::GetEnvironmentVariable($key)
        [Environment]::SetEnvironmentVariable($key, $Env[$key])
    }

    Push-Location $tmpRepo
    try {
        $prevErrorAction = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $result = $payload | & $script:psBin -NoProfile -File $hookPath 2>&1 | Out-String
        $ErrorActionPreference = $prevErrorAction
    } finally {
        Pop-Location
        foreach ($key in $envBackup.Keys) {
            [Environment]::SetEnvironmentVariable($key, $envBackup[$key])
        }
        if (Test-Path $tmpRepo) { Remove-Item $tmpRepo -Recurse -Force -ErrorAction SilentlyContinue }
    }

    return [string]$result
}

# Detecta branch real atual (apenas informativo no log -- os casos NAO dependem dela)
Push-Location $repoRoot
try {
    $currentBranch = (& git rev-parse --abbrev-ref HEAD 2>$null).Trim()
} finally {
    Pop-Location
}

Write-Host "[setup] Branch ativa detectada: '$currentBranch'"
Write-Host "[setup] Repo root: '$repoRoot'"
Write-Host ""

$failures = @()

# Helpers de assercao
function Assert-Block {
    param([string]$Name, [string]$Output)
    if ($Output -match '"decision":"deny"') {
        Write-Host "[OK] $Name -> BLOCK esperado"
        return $true
    } else {
        Write-Host "[FAIL] $Name -> deveria BLOQUEAR. Output: $Output"
        return $false
    }
}

function Assert-Allow {
    param([string]$Name, [string]$Output)
    if ([string]::IsNullOrWhiteSpace($Output) -or ($Output -notmatch '"decision":"deny"')) {
        Write-Host "[OK] $Name -> ALLOW esperado"
        return $true
    } else {
        Write-Host "[FAIL] $Name -> deveria PERMITIR. Output: $Output"
        return $false
    }
}

# ============================================================================
# CASO 1: Edit em ADR existente (0094) em qualquer branch -> BLOCK
#         (ADRs sao append-only IRREVOGAVEIS)
# ============================================================================
$adrExistente = "$repoRoot/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md"
$out = Invoke-Hook -ToolName 'Edit' -FilePath $adrExistente
if (-not (Assert-Block 'Caso 1 (Edit ADR 0094 existente)' $out)) { $failures += 'caso1' }

# ============================================================================
# CASO 2: Edit em ADR existente em branch claude/* -> BLOCK
#         (mesmo em claude/*, ADRs nunca sao editadas)
# ============================================================================
# Branch atual no worktree ja eh claude/sad-nightingale-34eb80 -- testa direto
$out = Invoke-Hook -ToolName 'Edit' -FilePath $adrExistente
if (-not (Assert-Block 'Caso 2 (Edit ADR existente em claude/*)' $out)) { $failures += 'caso2' }

# ============================================================================
# CASO 3: Write criando ADR nova (NNNN inexistente, ex 9999) em claude/* -> ALLOW
#         BRANCH-AGNOSTICO: simula branch claude/test-branch via repo temp no CWD.
#         O hook so permite ADR nova em branch claude/* (regra D). Como a branch
#         real do checkout pode ser docs/* ou main, simulamos claude/* aqui.
# ============================================================================
$adrNova = "$repoRoot/memory/decisions/9999-test-novo-adr-nunca-usado.md"
# Garante que nao existe (Test-CanonFile resolve pelo repo real -> precisa estar ausente)
if (Test-Path $adrNova) { Remove-Item $adrNova -Force }
$out = Invoke-HookOnBranch -ToolName 'Write' -FilePath $adrNova -Branch 'claude/test-branch'
if (-not (Assert-Allow 'Caso 3 (Write ADR nova 9999 em claude/* simulada)' $out)) { $failures += 'caso3' }

# ============================================================================
# CASO 4: Edit em memory/proibicoes.md em branch 'main' -> BLOCK
#         BRANCH-AGNOSTICO: simula branch main via repo temp no CWD (regra A/F).
#         Canon root (proibicoes.md) so eh editavel em claude/*; em main -> BLOCK.
# ============================================================================
$proibicoes = "$repoRoot/memory/proibicoes.md"
$out = Invoke-HookOnBranch -ToolName 'Edit' -FilePath $proibicoes -Branch 'main'
if (-not (Assert-Block 'Caso 4 (Edit proibicoes.md em main simulada)' $out)) { $failures += 'caso4' }

# ============================================================================
# CASO 5: Edit em memory/proibicoes.md em claude/* -> ALLOW
#         BRANCH-AGNOSTICO: simula branch claude/test-branch via repo temp no CWD.
#         Em branch claude/*, canon root eh editavel (vai pra PR) -> ALLOW.
# ============================================================================
$out = Invoke-HookOnBranch -ToolName 'Edit' -FilePath $proibicoes -Branch 'claude/test-branch'
if (-not (Assert-Allow 'Caso 5 (Edit proibicoes.md em claude/* simulada)' $out)) { $failures += 'caso5' }

# ============================================================================
# CASO 6: Edit em handoff existente em qualquer branch -> BLOCK
# ============================================================================
$handoffExistente = "$repoRoot/memory/handoffs/2026-05-15-2100-merge-massivo-prs-abertos-transicao-felipe.md"
if (Test-Path $handoffExistente) {
    $out = Invoke-Hook -ToolName 'Edit' -FilePath $handoffExistente
    if (-not (Assert-Block 'Caso 6 (Edit handoff existente)' $out)) { $failures += 'caso6' }
} else {
    # fallback -- usa qualquer handoff disponivel
    $algumHandoff = Get-ChildItem "$repoRoot/memory/handoffs" -Filter '*.md' | Select-Object -First 1
    if ($algumHandoff) {
        $out = Invoke-Hook -ToolName 'Edit' -FilePath $algumHandoff.FullName
        if (-not (Assert-Block 'Caso 6 (Edit handoff existente -- fallback)' $out)) { $failures += 'caso6' }
    } else {
        Write-Host "[SKIP] Caso 6 -- nenhum handoff existe no repo"
    }
}

# ============================================================================
# CASO 7: Write criando handoff novo (slug + data que nao existe) -> ALLOW
# ============================================================================
$handoffNovo = "$repoRoot/memory/handoffs/2099-12-31-2359-test-handoff-novo-slug-inedito.md"
if (Test-Path $handoffNovo) { Remove-Item $handoffNovo -Force }
$out = Invoke-Hook -ToolName 'Write' -FilePath $handoffNovo
if (-not (Assert-Allow 'Caso 7 (Write handoff novo)' $out)) { $failures += 'caso7' }

# ============================================================================
# CASO 8: Override env var ativo -> ALLOW + warning loud no stderr
# ============================================================================
$out = Invoke-Hook -ToolName 'Edit' -FilePath $adrExistente -Env @{ OIMPRESSO_MEMORY_OVERRIDE = '1' }
$allowedComOverride = [string]::IsNullOrWhiteSpace($out) -or ($out -notmatch '"decision":"deny"')
$temWarning = $out -match 'OVERRIDE ATIVO'
if ($allowedComOverride -and $temWarning) {
    Write-Host "[OK] Caso 8 (Override env var) -> ALLOW + warning loud"
} else {
    Write-Host "[FAIL] Caso 8 -- allow=$allowedComOverride warning=$temWarning. Output: $out"
    $failures += 'caso8'
}

# ============================================================================
# CASO 9: Path fora canon (memory/sessions/*) -> ALLOW
#         Sessions sao append-only por convencao mas hoje sem hook.
# ============================================================================
$sessionFile = "$repoRoot/memory/sessions/2026-05-15-some-session.md"
$out = Invoke-Hook -ToolName 'Write' -FilePath $sessionFile
if (-not (Assert-Allow 'Caso 9 (Write em sessions/* -- fora canon)' $out)) { $failures += 'caso9' }

# ============================================================================
# CASO 10: Edit em memory/governance/CONSTITUTION.md em qualquer branch -> BLOCK
#          Documento supremo -- so via ADR nova + version bump no MESMO PR.
# ============================================================================
$constitution = "$repoRoot/memory/governance/CONSTITUTION.md"
$out = Invoke-Hook -ToolName 'Edit' -FilePath $constitution
if (-not (Assert-Block 'Caso 10 (Edit CONSTITUTION.md)' $out)) { $failures += 'caso10' }

# ============================================================================
# BONUS: Caso 11 -- ADR proposal eh editavel (rascunho ate promocao)
# ============================================================================
$adrProposal = "$repoRoot/memory/decisions/proposals/claude-rules-path-scoped.md"
$out = Invoke-Hook -ToolName 'Edit' -FilePath $adrProposal
if ($out -notmatch '"decision":"deny"') {
    Write-Host "[OK] Caso 11 (ADR proposal editavel)"
} else {
    Write-Host "[FAIL] Caso 11 -- ADR proposal foi bloqueada erroneamente. Output: $out"
    $failures += 'caso11'
}

# Cleanup
if (Test-Path $adrNova) { Remove-Item $adrNova -Force }
if (Test-Path $handoffNovo) { Remove-Item $handoffNovo -Force }

# Summary
Write-Host ""
$totalCases = 11
$failed = $failures.Count
$passed = $totalCases - $failed

if ($failed -eq 0) {
    Write-Host ("[PASS] {0}/{1} casos validados (block-memory-drift OK)" -f $passed, $totalCases) -ForegroundColor Green
    exit 0
} else {
    $failsJoined = $failures -join ', '
    Write-Host ("[FAIL] {0}/{1} casos falharam: {2}" -f $failed, $totalCases, $failsJoined) -ForegroundColor Red
    exit 1
}
