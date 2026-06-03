# block-test-fora-ct100.test.ps1 — smoke do hook block-test-fora-ct100.ps1
# Roda standalone:  powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/block-test-fora-ct100.test.ps1
# Exit 0 = todos os casos OK; exit 1 = alguma falha.

$hook = Join-Path $PSScriptRoot 'block-test-fora-ct100.ps1'
$fail = 0

function Check([string]$name, [string]$cmd, [bool]$expectBlock) {
    $json = @{ tool_input = @{ command = $cmd } } | ConvertTo-Json -Compress
    $json | & powershell -NoProfile -ExecutionPolicy Bypass -File $hook 2>$null | Out-Null
    $blocked = ($LASTEXITCODE -eq 2)
    if ($blocked -ne $expectBlock) {
        Write-Host ("FAIL: {0} (exit {1}, esperava block={2})" -f $name, $LASTEXITCODE, $expectBlock)
        $script:fail++
    } else {
        Write-Host ("ok  : {0}" -f $name)
    }
}

# --- deve BLOQUEAR (execucao local) ---
Check 'pest local'              'vendor/bin/pest --filter=Foo'                         $true
Check 'phpstan local'           'vendor/bin/phpstan analyse Modules/Financeiro'        $true
Check 'artisan test local'      'php artisan test'                                     $true
Check 'phpunit local'           'vendor/bin/phpunit'                                   $true
Check 'composer phpstan local'  'composer phpstan'                                     $true
Check 'pest com cd local'       'cd D:/oimpresso.com && php artisan test --filter=X'   $true
Check 'hostinger ssh test'      'ssh u906587222@148.135.133.115 "php artisan test"'    $true

# --- deve LIBERAR (CT 100 ou nao-runner) ---
Check 'ct100 docker exec'       'tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test"'  $false
Check 'ct100 phpstan'           'tailscale ssh root@ct100-mcp "docker exec oimpresso-staging vendor/bin/phpstan analyse"' $false
Check 'le baseline (cat)'       'cat phpstan-baseline.neon'                            $false
Check 'grep phpstan log'        'gh run view 123 --log | grep -iE "phpstan|larastan"'  $false
Check 'git normal'              'git status'                                           $false
Check 'npm test (frontend)'     'npm test'                                             $false
Check 'override explicito'      'vendor/bin/pest # test-local-override'                $false

if ($fail -gt 0) { Write-Host ("`n{0} FALHA(S)" -f $fail); exit 1 } else { Write-Host "`nTODOS OK"; exit 0 }
