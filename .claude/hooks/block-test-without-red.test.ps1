# Smoke test -- block-test-without-red.ps1 (SDD FV-T0 / red-first com DENTES)
# Roda: powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/block-test-without-red.test.ps1
#
# Espelha warn-red-first.test.ps1: repos git TEMPORARIOS como fixture
# (OIMPRESSO_REDFIRST_REPO_ROOT) pra que o estado do repo real nao contamine o
# resultado. Prova que a catraca MORDE (modo block -> exit 2) E LIBERA corretamente
# (override / header / evidencia / teste existente / arquivo nao-teste / modo off).
#
# NOTA: modo block escreve a mensagem em stderr e faz exit 2. NAO redirecionamos
# stderr de processo nativo (evita NativeCommandError sob EAP=Stop no PS 5.1) --
# a assercao primaria e o EXIT CODE; o stdout (modos warn/override) e checado a parte.

$ErrorActionPreference = 'Stop'
$here = Split-Path $MyInvocation.MyCommand.Path -Parent
$hook = Join-Path $here 'block-test-without-red.ps1'

$failures = 0
$total = 0

function New-FixtureRepo {
    param([switch]$WithCommittedTest, [switch]$WithRedEvidence)

    $dir = Join-Path $env:TEMP ("blockred-fixture-" + [Guid]::NewGuid().ToString('N').Substring(0, 8))
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
    # NAO usar 2>$null em git nativo aqui: PS 5.1 + EAP Stop converte stderr
    # redirecionado em NativeCommandError fatal. Config local neutraliza CRLF global.
    & git -C $dir init -q
    & git -C $dir config user.email "test@local"
    & git -C $dir config user.name "fixture"
    & git -C $dir config core.autocrlf false
    & git -C $dir config core.safecrlf false
    & git -C $dir config commit.gpgsign false

    # Commit base: um arquivo de producao + readme (SEM teste)
    New-Item -ItemType Directory -Path (Join-Path $dir 'app/Services') -Force | Out-Null
    Set-Content -Path (Join-Path $dir 'app/Services/Foo.php') -Value '<?php class Foo {}' -Encoding Ascii
    Set-Content -Path (Join-Path $dir 'README.txt') -Value 'fixture' -Encoding Ascii
    & git -C $dir add -A
    & git -C $dir commit -q -m "base sem teste"

    if ($WithCommittedTest) {
        New-Item -ItemType Directory -Path (Join-Path $dir 'tests/Unit') -Force | Out-Null
        Set-Content -Path (Join-Path $dir 'tests/Unit/ExistenteTest.php') -Value '<?php class ExistenteTest {}' -Encoding Ascii
        & git -C $dir add -A
        & git -C $dir commit -q -m "teste ja versionado"
    }

    if ($WithRedEvidence) {
        New-Item -ItemType Directory -Path (Join-Path $dir '.claude/run') -Force | Out-Null
        Set-Content -Path (Join-Path $dir '.claude/run/red-evidence-novo.txt') `
            -Value 'FAIL  Tests\Unit\NovoTest > soma  Failed asserting that 0 matches 2.' -Encoding Ascii
    }

    return $dir
}

function Test-Hook {
    param(
        [string]$Name,
        [string]$FilePath,
        [string]$RepoRoot,
        [int]$ExpectExit,
        [string]$Tool = 'Write',
        [string]$Content = '',
        [string]$Mode = 'warn',
        [string]$ExpectStdout = $null
    )

    $script:total++
    $env:OIMPRESSO_REDFIRST_BLOCK_MODE = $Mode
    $env:OIMPRESSO_REDFIRST_REPO_ROOT = $RepoRoot

    $ti = @{ file_path = $FilePath }
    if ($Content -ne '') { $ti['content'] = $Content }
    $payload = @{ tool_name = $Tool; tool_input = $ti } | ConvertTo-Json -Compress -Depth 10

    # So stdout capturado (modos warn/override). Modo block escreve stderr + exit 2:
    # stderr vai pro console (nao redirecionado) -- so checamos o exit code.
    $output = $payload | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
    $exitCode = $LASTEXITCODE

    $okExit = ($exitCode -eq $ExpectExit)
    $okMsg = $true
    if ($ExpectStdout) { $okMsg = ($output -match $ExpectStdout) }

    if ($okExit -and $okMsg) {
        Write-Host "  OK  $Name (exit=$exitCode)"
    } else {
        Write-Host "  FAIL $Name -- expected exit=$ExpectExit got exit=$exitCode; stdoutOk=$okMsg" -ForegroundColor Red
        $script:failures++
    }
}

Write-Host "=== block-test-without-red smoke (FV-T0) ==="

$repo = New-FixtureRepo
$repoTracked = New-FixtureRepo -WithCommittedTest
$repoEvid = New-FixtureRepo -WithRedEvidence

$novo = "$repo/tests/Unit/NovoTest.php"          # nao existe / untracked -> teste NOVO

# T1: teste NOVO sem evidencia, modo BLOCK -> exit 2 (a catraca MORDE)
Test-Hook -Name 'T1 novo-sem-evidencia BLOCK->2' `
    -FilePath $novo -RepoRoot $repo -Mode 'block' -ExpectExit 2

# T2: teste NOVO sem evidencia, modo WARN (default) -> exit 0 (avisa, NAO barra)
Test-Hook -Name 'T2 novo-sem-evidencia WARN->0' `
    -FilePath $novo -RepoRoot $repo -Mode 'warn' -ExpectExit 0 -ExpectStdout '\[RED-FIRST'

# T3: teste NOVO com cabecalho red-first no content, modo BLOCK -> exit 0 (libera)
Test-Hook -Name 'T3 novo-com-header-redfirst BLOCK->0' `
    -FilePath $novo -RepoRoot $repo -Mode 'block' -ExpectExit 0 `
    -Content '<?php // red-first: rodei pest NovoTest, FALHOU (assert 0==2) antes de implementar'

# T4: teste NOVO com red-first-override, modo BLOCK -> exit 0 (libera + razao no stdout)
Test-Hook -Name 'T4 novo-com-override BLOCK->0' `
    -FilePath $novo -RepoRoot $repo -Mode 'block' -ExpectExit 0 -ExpectStdout 'override aceito' `
    -Content '<?php // red-first-override: characterization de legado UPos sem red possivel'

# T5: teste JA VERSIONADO (tracked), Write overwrite, modo BLOCK -> exit 0 (nao re-exige red)
Test-Hook -Name 'T5 teste-existente-tracked BLOCK->0' `
    -FilePath "$repoTracked/tests/Unit/ExistenteTest.php" -RepoRoot $repoTracked -Mode 'block' -ExpectExit 0 `
    -Content '<?php class ExistenteTest { /* novo caso */ }'

# T6: arquivo NAO-teste (producao), modo BLOCK -> exit 0 (nunca dispara)
Test-Hook -Name 'T6 nao-teste BLOCK->0' `
    -FilePath "$repo/app/Services/Bar.php" -RepoRoot $repo -Mode 'block' -ExpectExit 0 `
    -Content '<?php class Bar {}'

# T7: modo OFF -> exit 0 mesmo com teste novo sem evidencia
Test-Hook -Name 'T7 modo-off->0' `
    -FilePath $novo -RepoRoot $repo -Mode 'off' -ExpectExit 0

# T8: tool EDIT (nao Write) em teste novo, modo BLOCK -> exit 0 (so criacao via Write morde)
Test-Hook -Name 'T8 tool-edit BLOCK->0' `
    -FilePath $novo -RepoRoot $repo -Mode 'block' -ExpectExit 0 -Tool 'Edit'

# T9: teste NOVO sem header MAS com .claude/run/red-evidence-*.txt recente, modo BLOCK -> exit 0
Test-Hook -Name 'T9 red-evidence-file BLOCK->0' `
    -FilePath "$repoEvid/tests/Unit/NovoTest.php" -RepoRoot $repoEvid -Mode 'block' -ExpectExit 0 `
    -Content '<?php class NovoTest {}'

# T10: stdin vazio -> exit 0 (fail-open)
$script:total++
$env:OIMPRESSO_REDFIRST_BLOCK_MODE = 'block'
$out10 = '' | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
if ($LASTEXITCODE -eq 0) { Write-Host "  OK  T10 stdin-vazio fail-open" }
else { Write-Host "  FAIL T10 stdin-vazio -- exit=$LASTEXITCODE" -ForegroundColor Red; $failures++ }

# T11: payload sem file_path -> exit 0 (fail-open)
$script:total++
$env:OIMPRESSO_REDFIRST_REPO_ROOT = $repo
$out11 = '{"tool_name":"Write","tool_input":{}}' | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook | Out-String
if ($LASTEXITCODE -eq 0) { Write-Host "  OK  T11 payload-sem-path fail-open" }
else { Write-Host "  FAIL T11 payload-sem-path -- exit=$LASTEXITCODE" -ForegroundColor Red; $failures++ }

# Cleanup
Remove-Item -Recurse -Force $repo, $repoTracked, $repoEvid -ErrorAction SilentlyContinue
$env:OIMPRESSO_REDFIRST_BLOCK_MODE = $null
$env:OIMPRESSO_REDFIRST_REPO_ROOT = $null
$env:OIMPRESSO_REDFIRST_EVID_MINUTES = $null

Write-Host ""
Write-Host "Total: $total | Failures: $failures"
if ($failures -gt 0) { exit 1 } else { exit 0 }
