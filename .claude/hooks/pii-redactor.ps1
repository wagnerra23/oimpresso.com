# Hook PreToolUse - BLOQUEIA git commit que levaria PII real (CPF/CNPJ/cartao) pro repo.
# US-COPI-086 (Cycle 01) - LGPD Art. 7 (principio de minimizacao).
#
# Escopo (opcao B, 2026-06-13 - ver memory/sessions deste dia):
#   SO inspeciona `git commit`. Escaneia:
#     - a mensagem do commit (texto do comando, ex: -m "...")
#     - o staged diff (git diff --staged)
#   Ambos acabam no historico git -> sincronizam pro MCP server visivel ao time.
#
#   Comandos NAO-commit (mysql/grep/ssh/echo/cat ...) NAO sao mais inspecionados,
#   mesmo contendo CPF/CNPJ. Num ERP brasileiro, debug legitimo por CPF/CNPJ
#   (consultar producao, grep de log, snapshot Firebird) e operacao normal e
#   nao deve ser bloqueado. A versao anterior bloqueava esses comandos sem bypass.
#
# Bypass: adicione --allow-pii ao git commit (E confirme com Wagner).
# Whitelist: PIIs reconhecidamente fake/fixture (ver array $fakeWhitelist abaixo).

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

# Opcao B: so age em git commit. Qualquer outro comando passa direto (sem inspecao).
if ($cmd -notmatch '^\s*git\s+commit\b') { exit 0 }
# Bypass justificado.
if ($cmd -match '--allow-pii') { exit 0 }

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

# Padroes PII reais (regex)
$piiPatterns = @{
    'cpf'    = '\b\d{3}\.\d{3}\.\d{3}-\d{2}\b'
    'cnpj'   = '\b\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}\b'
    'cartao' = '\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b'
}

# Funcao: dado um texto, retorna lista de PIIs detectadas (nao-whitelisted)
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

# Monta o corpo a escanear: mensagem do commit (texto do comando) + staged diff.
$textoAEscanear = $cmd
try {
    $stagedDiff = & git diff --staged 2>$null
    if ($stagedDiff) {
        $textoAEscanear += "`n" + ($stagedDiff -join "`n")
    }
} catch {
    # git falhou (nao eh repo, sem permissao) - escaneia so a mensagem do commit
}

$piiFound = @(Find-Pii $textoAEscanear)
if ($piiFound.Count -gt 0) {
    $first = $piiFound[0]
    $exemplo = $first.valor.Substring(0, [Math]::Min(6, $first.valor.Length)) + '...'
    @{
        decision      = 'deny'
        reason        = "[pii-redactor] git commit BLOQUEADO: $($first.tipo) '$exemplo' (mensagem ou staged diff)"
        systemMessage = "[pii-redactor] LGPD Art. 7 - git commit contem $($piiFound.Count) PII real ($(($piiFound | ForEach-Object { $_.tipo }) -join ', ')) na mensagem e/ou no staged diff. Antes de commitar: 1) remova a PII da mensagem do commit; 2) git restore --staged <arquivo> + edite (use [REDACTED] ou fixtures fake) + re-stage. Bypass justificado: adicione --allow-pii ao comando E confirme com Wagner."
    } | ConvertTo-Json -Compress
    exit 0
}

exit 0
