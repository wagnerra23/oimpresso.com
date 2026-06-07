# licoes-code-two-strikes.ps1 - Gatilho mecanizado do loop de aprendizado de codigo.
# Disparado em SessionStart (apos brief-fetch) pelo .claude/settings.json.
# Origem: sessao 2026-06-06 (Wagner: "meu sistema esta preparado a evoluir quando
#   esses erros aparecem? quando deve ser acionado o aprendizado?").
# Le memory/LICOES_CODE.md e ALARMA quando uma classe de erro repetiu
#   (Ocorrencias >= 2) e ainda NAO virou defesa mecanica (Gate: none).
# E so um nudge: NUNCA bloqueia, sempre exit 0. ASCII puro (PS 5.1 compat).
# Override de path pra teste: $env:OIMPRESSO_LICOES_CODE_PATH
# Limiar configuravel: $env:OIMPRESSO_LICOES_THRESHOLD (default 2)

$ErrorActionPreference = 'SilentlyContinue'

try {
    if ($env:OIMPRESSO_LICOES_CODE_PATH) {
        $path = $env:OIMPRESSO_LICOES_CODE_PATH
    } else {
        $repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
        $path = Join-Path $repo 'memory\LICOES_CODE.md'
    }
    if (-not (Test-Path $path)) { exit 0 }

    $threshold = 2
    if ($env:OIMPRESSO_LICOES_THRESHOLD) {
        $parsed = 0
        if ([int]::TryParse($env:OIMPRESSO_LICOES_THRESHOLD, [ref]$parsed)) { $threshold = $parsed }
    }

    $lines = Get-Content $path -Encoding UTF8

    $licoes = @()
    $cur = $null
    foreach ($ln in $lines) {
        if ($ln -match '^##\s+(LC-\S+)\s*[-—]?\s*(.*)$') {
            if ($cur) { $licoes += $cur }
            $cur = [pscustomobject]@{ id = $Matches[1]; titulo = $Matches[2].Trim(); ocorr = 0; gate = '' }
            continue
        }
        if (-not $cur) { continue }
        if ($ln -match '\*\*Ocorr.*?(\d+)') { $cur.ocorr = [int]$Matches[1] }
        elseif ($ln -match '\*\*Gate.*?:\s*(.+?)\s*$') { $cur.gate = ($Matches[1] -replace '\*\*','').Trim() }
    }
    if ($cur) { $licoes += $cur }

    # Sem gate = gate vazio, 'none', 'nenhum' ou '-'
    function Test-SemGate([string]$g) {
        if (-not $g) { return $true }
        return ($g -match '^(none|nenhum|nenhuma|-|n/a|na)$')
    }

    $alarme = @($licoes | Where-Object { $_.ocorr -ge $threshold -and (Test-SemGate $_.gate) })
    $watch  = @($licoes | Where-Object { $_.ocorr -lt $threshold -and (Test-SemGate $_.gate) })

    if ($alarme.Count -eq 0 -and $watch.Count -eq 0) { exit 0 }

    function ConvertTo-Ascii([string]$s) { return ($s -replace '[^\x20-\x7E]', '.') }

    Write-Host ""
    Write-Host "=== LICOES [CODE] - gatilho two-strikes (audit loop de aprendizado) ==="

    if ($alarme.Count -gt 0) {
        Write-Host ("  [!] {0} classe(s) repetiram (>= {1}x) e NAO tem gate. PROMOVER A DEFESA MECANICA:" -f $alarme.Count, $threshold)
        foreach ($a in $alarme) {
            Write-Host ("      {0} - {1}  ({2}x, sem gate)" -f $a.id, (ConvertTo-Ascii $a.titulo), $a.ocorr)
        }
        Write-Host "  ACAO: avise o Wagner e proponha o gate/hook/baseline que mata essa classe."
        Write-Host "  (Quando criar o gate, troque 'Gate: none' pelo nome dele em LICOES_CODE.md - o alarme some.)"
    }

    if ($watch.Count -gt 0) {
        Write-Host ("  [.] {0} classe(s) em WATCH (sem gate, < {1}x). Se reincidirem, viram alarme." -f $watch.Count, $threshold)
    }

    Write-Host ""
} catch {
    # Falha silenciosa: nudge nunca quebra inicio de sessao.
    exit 0
}
exit 0
