# nudge-diagnosis-without-evidence.ps1 — Stop (advisory, estende R1 / ADR 0233)
# Detecta diagnostico/causa afirmado SEM evidencia (grep/log/SQL/trace/curl).
# Origem: sessao 2026-05-29 chutou causa do HTTP 500 2x antes de ler o log.
# Advisory: exit 0 SEMPRE (nunca bloqueia). Fail-open.

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $tp = [string]$payload.transcript_path
    if (-not $tp -or -not (Test-Path $tp)) { exit 0 }

    $lines = @(Get-Content $tp -Tail 50 -Encoding UTF8)
    $text = $null
    for ($i = $lines.Count - 1; $i -ge 0; $i--) {
        $o = $null
        try { $o = $lines[$i] | ConvertFrom-Json } catch { continue }
        if ($o.type -eq 'assistant' -and $o.message.content) {
            $t = ($o.message.content | Where-Object { $_.type -eq 'text' } | ForEach-Object { $_.text }) -join "`n"
            if ($t) { $text = $t; break }
        }
    }
    if (-not $text) { exit 0 }

    # Afirmacao de causa/diagnostico com certeza
    $diag = $text -match '(?i)(a causa (e|é|raiz|foi)|o problema (e|é|foi)|isso (acontece|ocorre|quebra|d[aá]) porque|com certeza (e|é)|root cause|o motivo (e|é)|porque o banco|porque a tabela)'
    # Marcadores de evidencia real
    $evidence = $text -match '(?i)(grep|tail |laravel\.log|SQLSTATE|stack ?trace|\.php:\d|getComputedStyle|curl|HTTP \d{3}|migrate:status|Schema::has|linha \d|confirmad[oa]|verifiquei)'

    if ($diag -and -not $evidence) {
        Write-Output "[R1+ / ADR 0233] Voce AFIRMOU uma causa/diagnostico. Mostre a EVIDENCIA (grep/log/SQL/trace/Read) que prova, antes de cravar. Nao chute (sessao 2026-05-29 chutou causa do 500 2x). Se ainda nao tem evidencia, diga 'hipotese a confirmar'."
    }
    exit 0
} catch {
    exit 0
}
