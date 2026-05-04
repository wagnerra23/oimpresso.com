# Hook PreToolUse - BLOQUEIA Bash que vai commitar/printar PII (CPF/CNPJ/cartao).
# US-COPI-086 (Cycle 01) - LGPD Art. 7 (principio de minimizacao).
#
# Escaneia commands de git commit OR scripts que produzem output.
# Em particular, blocking matrix:
#   - git commit -m "..." com PII no message
#   - git commit + git diff --staged contendo PII (escaneia o staged)
#   - cat/tail/grep de log com PII detectada na saida esperada
#
# Whitelist: PIIs reconhecidamente fake/fixture (123.456.789-09, 11.222.333/0001-81)

$ErrorActionPreference = 'Stop'
$rawInput = [Console]::In.ReadToEnd()
if (-not $rawInput) { exit 0 }

try {
    $payload = $rawInput | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
if ($tool -ne 'Bash') { exit 0 }

$cmd = $payload.tool_input.command
if (-not $cmd) { exit 0 }

# Fixtures fake bem conhecidos (whitelist)
$fakeWhitelist = @(
    '123\.456\.789-09',
    '111\.111\.111-11',
    '000\.000\.000-00',
    '11\.222\.333/0001-81',
    '00\.000\.000/0000-00',
    '4111[\s-]?1111[\s-]?1111[\s-]?1111',  # Visa test
    '5555[\s-]?5555[\s-]?5555[\s-]?4444'   # Mastercard test
)

# Padrões PII reais (regex)
$piiPatterns = @{
    'cpf'   = '\b\d{3}\.\d{3}\.\d{3}-\d{2}\b'
    'cnpj'  = '\b\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}\b'
    'cartao' = '\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b'
}

# Função: dado um texto, retorna lista de PIIs detectadas (não-whitelisted)
function Find-Pii([string]$text) {
    $found = @()
    foreach ($key in $piiPatterns.Keys) {
        $pattern = $piiPatterns[$key]
        $matches = [regex]::Matches($text, $pattern)
        foreach ($m in $matches) {
            $val = $m.Value
            $isFake = $false
            foreach ($w in $fakeWhitelist) {
                if ($val -match $w) { $isFake = $true; break }
            }
            if (-not $isFake) {
                $found += @{ tipo = $key; valor = $val }
            }
        }
    }
    return $found
}

# 1) Verifica PII no proprio comando (commit -m, echo, etc.)
$piiInCmd = @(Find-Pii $cmd)
if ($piiInCmd.Count -gt 0) {
    $first = $piiInCmd[0]
    $exemplo = $first.valor.Substring(0, [Math]::Min(6, $first.valor.Length)) + '...'
    @{
        decision      = 'deny'
        reason        = "[pii-redactor] PII real detectada no comando: $($first.tipo) '$exemplo'"
        systemMessage = "[pii-redactor] LGPD Art. 7 - comando contem $($piiInCmd.Count) PII real ($(($piiInCmd | ForEach-Object { $_.tipo }) -join ', ')). NUNCA commitar/printar PII real. Substitua por: CPF fake = 123.456.789-09; CNPJ fake = 11.222.333/0001-81; cartao fake = 4111-1111-1111-1111. Se for log de producao, sanitize com sed antes de colar (HOW_TO_ASK_CLAUDE secao 3.4)."
    } | ConvertTo-Json -Compress
    exit 0
}

# 2) Verifica se eh git commit (qualquer flavor) - escanear staged diff
if ($cmd -match '^\s*git\s+commit\b' -and $cmd -notmatch '--allow-pii') {
    # Tenta capturar `git diff --staged` no diretorio atual
    try {
        $stagedDiff = & git diff --staged 2>$null
        if ($stagedDiff) {
            $piiInDiff = @(Find-Pii ($stagedDiff -join "`n"))
            if ($piiInDiff.Count -gt 0) {
                $first = $piiInDiff[0]
                $exemplo = $first.valor.Substring(0, [Math]::Min(6, $first.valor.Length)) + '...'
                @{
                    decision      = 'deny'
                    reason        = "[pii-redactor] git commit BLOQUEADO: $($first.tipo) '$exemplo' no staged diff"
                    systemMessage = "[pii-redactor] LGPD: git diff --staged contem $($piiInDiff.Count) PII real ($(($piiInDiff | ForEach-Object { $_.tipo }) -join ', ')). Antes de commitar: 1) git restore --staged <arquivo>; 2) edite removendo PII (use [REDACTED] ou fixtures fake); 3) re-stage. Bypass justificado: adicione --allow-pii ao comando E confirme com Wagner."
                } | ConvertTo-Json -Compress
                exit 0
            }
        }
    } catch {
        # git falhou (nao eh repo, sem permissao) - nao bloqueia
    }
}

exit 0
