# Teste do block-destructive.ps1
# Roda 7 cenários e valida bloqueio/passagem

$ErrorActionPreference = 'Continue'
$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$hook = Join-Path $base 'block-destructive.ps1'

$cases = @(
    @{ name='T1 rm -rf perigoso';    cmd='rm -rf /important/path';            expectBlock=$true  }
    @{ name='T2 rm -rf node_modules';cmd='rm -rf node_modules';                expectBlock=$false }
    @{ name='T3 rm -rf /tmp/foo';    cmd='rm -rf /tmp/foo';                    expectBlock=$false }
    @{ name='T4 git push --force';   cmd='git push --force origin main';       expectBlock=$true  }
    @{ name='T5 git reset --hard';   cmd='git reset --hard origin/main';       expectBlock=$true  }
    @{ name='T6 DROP TABLE';         cmd='mysql -e "DROP TABLE users"';        expectBlock=$true  }
    @{ name='T7 DELETE no WHERE';    cmd='mysql -e "DELETE FROM users"';       expectBlock=$true  }
    @{ name='T8 TRUNCATE';           cmd='mysql -e "TRUNCATE users"';          expectBlock=$true  }
    @{ name='T9 composer update';    cmd='composer update';                    expectBlock=$true  }
    @{ name='T10 composer update --lock'; cmd='composer update --lock';        expectBlock=$false }
    @{ name='T11 migrate:fresh';     cmd='php artisan migrate:fresh --seed';   expectBlock=$true  }
    @{ name='T12 migrate (normal)';  cmd='php artisan migrate';                expectBlock=$false }
    @{ name='T13 git status';        cmd='git status';                         expectBlock=$false }
    @{ name='T14 ls -la';            cmd='ls -la';                             expectBlock=$false }
)

$pass = 0; $fail = 0
foreach ($c in $cases) {
    $payload = @{
        tool_name  = 'Bash'
        tool_input = @{ command = $c.cmd }
    } | ConvertTo-Json -Compress

    $output = $payload | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook 2>&1
    $outputStr = ($output | Out-String)
    $blocked = [bool]($outputStr -match '"decision"\s*:\s*"deny"')

    $expected = if ($c.expectBlock) { 'BLOCK' } else { 'PASS' }
    $actual   = if ($blocked) { 'BLOCK' } else { 'PASS' }
    $ok       = ($blocked -eq $c.expectBlock)

    $sym = if ($ok) { '✓' } else { '✗' }
    if ($ok) { $pass++ } else { $fail++ }

    Write-Host ("$sym {0,-32} expected={1,-5} actual={2,-5}" -f $c.name, $expected, $actual)
    if (-not $ok) {
        Write-Host "    cmd: $($c.cmd)"
        Write-Host "    out: $output"
    }
}

Write-Host ""
Write-Host "Resultado: $pass passou, $fail falhou de $($cases.Count) testes."
exit $fail
