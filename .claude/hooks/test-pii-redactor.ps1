# Teste do pii-redactor.ps1 (opcao B - so inspeciona git commit)
#
# Rode de preferencia de um diretorio SEM git (ou sem nada staged) pra que o
# scan de staged diff fique vazio e o resultado dependa so da mensagem do commit.

$ErrorActionPreference = 'Continue'
$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$hook = Join-Path $base 'pii-redactor.ps1'

$cases = @(
    # Comandos NAO-commit nunca bloqueiam (mudanca da opcao B) ---------------
    @{ name='T1 echo CPF real (nao-commit)';   cmd='echo "CPF do cliente: 987.654.321-00"';         expectBlock=$false } # pii-allowlist
    @{ name='T2 echo CPF fake';                cmd='echo "CPF: 123.456.789-09"';                    expectBlock=$false } # pii-allowlist
    @{ name='T5 grep cartao em log (nao-commit)'; cmd='grep "4532-1488-0343-6467" log.txt';          expectBlock=$false }
    @{ name='T6 cartao Visa fixture';          cmd='echo "test card 4111-1111-1111-1111"';          expectBlock=$false }
    @{ name='T7 ls -la';                       cmd='ls -la';                                        expectBlock=$false }
    @{ name='T9 echo email so';                cmd='echo "contato: ana@example.com"';               expectBlock=$false }
    @{ name='T10 cat|grep CPF (nao-commit)';   cmd='cat log.txt | grep "098.765.432-11"';           expectBlock=$false } # pii-allowlist
    @{ name='T13 mysql WHERE cpf (debug ERP)'; cmd="mysql -e ""SELECT * FROM contacts WHERE cpf='987.654.321-00'"""; expectBlock=$false } # pii-allowlist

    # git commit continua protegido (mensagem + staged diff) -----------------
    @{ name='T3 git commit message OK';        cmd='git commit -m "feat: adiciona campo idade"';    expectBlock=$false }
    @{ name='T4 commit -m com CNPJ real';      cmd='git commit -m "fix: cliente CNPJ 12.345.678/0001-90 nao importa"'; expectBlock=$true } # pii-allowlist
    @{ name='T8 commit + cnpj 0000 fake';      cmd='git commit -m "test 00.000.000/0000-00"';       expectBlock=$false } # pii-allowlist
    @{ name='T11 commit -m com CPF real';      cmd='git commit -m "ajuste do CPF 987.654.321-00"';  expectBlock=$true } # pii-allowlist
    @{ name='T12 commit --allow-pii bypass';   cmd='git commit --allow-pii -m "CPF 987.654.321-00 autorizado por Wagner"'; expectBlock=$false } # pii-allowlist
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

    Write-Host ("$sym {0,-34} expected={1,-5} actual={2,-5}" -f $c.name, $expected, $actual)
    if (-not $ok) {
        Write-Host "    cmd: $($c.cmd)"
        Write-Host "    out: $outputStr"
    }
}

Write-Host ""
Write-Host "Resultado: $pass passou, $fail falhou de $($cases.Count) testes."
exit $fail
