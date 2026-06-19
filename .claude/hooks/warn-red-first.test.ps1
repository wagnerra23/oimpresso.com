# Smoke test -- warn-red-first.ps1 (SDD FV-T0)
# Roda: powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/warn-red-first.test.ps1
#
# Usa repos git TEMPORARIOS como fixture (OIMPRESSO_REDFIRST_REPO_ROOT) pra que o
# estado do repo real nao contamine o resultado (deterministico em qualquer maquina).

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'warn-red-first.ps1'

$failures = 0
$total = 0

function New-FixtureRepo {
    param([switch]$WithUncommittedTest, [switch]$WithCommittedTest)

    $dir = Join-Path $env:TEMP ("redfirst-fixture-" + [Guid]::NewGuid().ToString('N').Substring(0, 8))
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
    # NAO usar 2>$null em git nativo aqui: PS 5.1 + ErrorActionPreference Stop converte
    # stderr redirecionado em NativeCommandError fatal. Config local neutraliza CRLF global.
    & git -C $dir init -q
    & git -C $dir config user.email "test@local"
    & git -C $dir config user.name "fixture"
    & git -C $dir config core.autocrlf false
    & git -C $dir config core.safecrlf false
    & git -C $dir config commit.gpgsign false

    # Commit base SEM teste (so um arquivo de producao + readme)
    New-Item -ItemType Directory -Path (Join-Path $dir 'app/Services') -Force | Out-Null
    Set-Content -Path (Join-Path $dir 'app/Services/Foo.php') -Value '<?php class Foo {}' -Encoding Ascii
    Set-Content -Path (Join-Path $dir 'README.txt') -Value 'fixture' -Encoding Ascii
    & git -C $dir add -A
    & git -C $dir commit -q -m "base sem teste"

    if ($WithCommittedTest) {
        New-Item -ItemType Directory -Path (Join-Path $dir 'tests/Unit') -Force | Out-Null
        Set-Content -Path (Join-Path $dir 'tests/Unit/FooTest.php') -Value '<?php class FooTest {}' -Encoding Ascii
        & git -C $dir add -A
        & git -C $dir commit -q -m "teste recem commitado"
    }

    if ($WithUncommittedTest) {
        New-Item -ItemType Directory -Path (Join-Path $dir 'tests/Unit') -Force | Out-Null
        Set-Content -Path (Join-Path $dir 'tests/Unit/BarTest.php') -Value '<?php class BarTest {}' -Encoding Ascii
        # fica untracked (aparece no git status --porcelain)
    }

    return $dir
}

function Test-Hook {
    param([string]$Name, [string]$FilePath, [string]$RepoRoot, [bool]$ExpectWarn, [string]$Mode = 'warn')

    $script:total++
    $env:OIMPRESSO_REDFIRST_MODE = $Mode
    $env:OIMPRESSO_REDFIRST_REPO_ROOT = $RepoRoot

    $payload = @{ tool_name = 'Edit'; tool_input = @{ file_path = $FilePath } } | ConvertTo-Json -Compress -Depth 10
    $output = $payload | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
    $exitCode = $LASTEXITCODE

    $warned = $output -match '\[RED-FIRST'
    $okExit = ($exitCode -eq 0)
    $okWarn = ($warned -eq $ExpectWarn)

    if ($okExit -and $okWarn) {
        Write-Host "  OK  $Name (warn=$warned exit=$exitCode)"
    } else {
        Write-Host "  FAIL $Name -- expected warn=$ExpectWarn got warn=$warned exit=$exitCode" -ForegroundColor Red
        $script:failures++
    }
}

Write-Host "=== warn-red-first smoke (FV-T0) ==="

# Fixtures
$repoSemTeste = New-FixtureRepo
$repoTesteUncommitted = New-FixtureRepo -WithUncommittedTest
$repoTesteCommitado = New-FixtureRepo -WithCommittedTest

# T1: producao app/** sem teste tocado -> WARN (exit 0)
Test-Hook -Name 'T1 app-sem-teste WARN' `
    -FilePath "$repoSemTeste/app/Services/Foo.php" -RepoRoot $repoSemTeste -ExpectWarn $true

# T2: producao Modules/**/Services sem teste tocado -> WARN
Test-Hook -Name 'T2 Modules-Services-sem-teste WARN' `
    -FilePath "$repoSemTeste/Modules/Financeiro/Services/ContaService.php" -RepoRoot $repoSemTeste -ExpectWarn $true

# T3: producao Modules/**/Http sem teste tocado -> WARN
Test-Hook -Name 'T3 Modules-Http-sem-teste WARN' `
    -FilePath "$repoSemTeste/Modules/Nfe/Http/Controllers/NotaController.php" -RepoRoot $repoSemTeste -ExpectWarn $true

# T4: arquivo *Test.php (mesmo em path de producao) -> silencio
Test-Hook -Name 'T4 Test.php-excluido silencio' `
    -FilePath "$repoSemTeste/Modules/Nfe/Http/NotaTest.php" -RepoRoot $repoSemTeste -ExpectWarn $false

# T5: arquivo .md em path de producao -> silencio
Test-Hook -Name 'T5 markdown-excluido silencio' `
    -FilePath "$repoSemTeste/app/Services/README.md" -RepoRoot $repoSemTeste -ExpectWarn $false

# T6: fora de producao (resources/js) -> silencio
Test-Hook -Name 'T6 fora-de-producao silencio' `
    -FilePath "$repoSemTeste/resources/js/Pages/Foo/Index.tsx" -RepoRoot $repoSemTeste -ExpectWarn $false

# T7: Modules fora de Services|Entities|Http (ex.: Database) -> silencio
Test-Hook -Name 'T7 Modules-Database silencio' `
    -FilePath "$repoSemTeste/Modules/Nfe/Database/Migrations/2026_01_01_foo.php" -RepoRoot $repoSemTeste -ExpectWarn $false

# T8: teste UNCOMMITTED na sessao (git status) -> silencio (red-first cumprido)
Test-Hook -Name 'T8 teste-uncommitted silencio' `
    -FilePath "$repoTesteUncommitted/app/Services/Foo.php" -RepoRoot $repoTesteUncommitted -ExpectWarn $false

# T9: teste COMMITADO na janela recente (git log) -> silencio
Test-Hook -Name 'T9 teste-commitado-recente silencio' `
    -FilePath "$repoTesteCommitado/app/Services/Foo.php" -RepoRoot $repoTesteCommitado -ExpectWarn $false

# T10: modo off -> silencio mesmo sem teste
Test-Hook -Name 'T10 modo-off silencio' `
    -FilePath "$repoSemTeste/app/Services/Foo.php" -RepoRoot $repoSemTeste -ExpectWarn $false -Mode 'off'

# T11: payload sem file_path -> silencio, exit 0 (fail-open)
$script:total++
$env:OIMPRESSO_REDFIRST_MODE = 'warn'
$env:OIMPRESSO_REDFIRST_REPO_ROOT = $repoSemTeste
$out11 = '{"tool_name":"Edit","tool_input":{}}' | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
if ($LASTEXITCODE -eq 0 -and $out11 -notmatch '\[RED-FIRST') {
    Write-Host "  OK  T11 payload-sem-path fail-open"
} else {
    Write-Host "  FAIL T11 payload-sem-path -- exit=$LASTEXITCODE output=$out11" -ForegroundColor Red
    $failures++
}

# T12: stdin vazio -> exit 0 (fail-open)
$script:total++
$out12 = '' | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
if ($LASTEXITCODE -eq 0) {
    Write-Host "  OK  T12 stdin-vazio fail-open"
} else {
    Write-Host "  FAIL T12 stdin-vazio -- exit=$LASTEXITCODE" -ForegroundColor Red
    $failures++
}

# Cleanup fixtures + env
Remove-Item -Recurse -Force $repoSemTeste, $repoTesteUncommitted, $repoTesteCommitado -ErrorAction SilentlyContinue
$env:OIMPRESSO_REDFIRST_MODE = $null
$env:OIMPRESSO_REDFIRST_REPO_ROOT = $null

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
