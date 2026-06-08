# Teste do pii-redactor.ps1

$ErrorActionPreference = 'Continue'
$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$hook = Join-Path $base 'pii-redactor.ps1'

$cases = @(
    @{ name='T1 echo CPF real';       cmd='echo "CPF do cliente: 987.654.321-00"';     expectBlock=$true  }
    @{ name='T2 echo CPF fake';       cmd='echo "CPF: 123.456.789-09"';                expectBlock=$false }
    @{ name='T3 git commit message OK';cmd='git commit -m "feat: adiciona campo idade"';expectBlock=$false }
    @{ name='T4 commit -m com CNPJ';  cmd='git commit -m "fix: cliente CNPJ 12.345.678/0001-90 não importa"'; expectBlock=$true }
    @{ name='T5 grep log com cartão'; cmd='grep "4532-1488-0343-6467" log.txt';        expectBlock=$true  }
    @{ name='T6 cartão Visa fixture'; cmd='echo "test card 4111-1111-1111-1111"';      expectBlock=$false }
    @{ name='T7 ls -la';              cmd='ls -la';                                     expectBlock=$false }
    @{ name='T8 commit + cnpj 0000';  cmd='git commit -m "test 00.000.000/0000-00"';   expectBlock=$false }
    @{ name='T9 echo email só';       cmd='echo "contato: ana@example.com"';           expectBlock=$false }
    @{ name='T10 cmd com 4 padrões';  cmd='cat log.txt | grep "098.765.432-11"';       expectBlock=$true  }
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

    $sym = if ($ok) { '+' } else { 'X' }
    if ($ok) { $pass++ } else { $fail++ }

    Write-Host ("$sym {0,-32} expected={1,-5} actual={2,-5}" -f $c.name, $expected, $actual)
    if (-not $ok) {
        Write-Host "    cmd: $($c.cmd)"
        Write-Host "    out: $outputStr"
    }
}

Write-Host ""
Write-Host "Resultado: $pass passou, $fail falhou de $($cases.Count) testes."
exit $fail
