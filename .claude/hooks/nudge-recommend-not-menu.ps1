# nudge-recommend-not-menu.ps1 — Stop (advisory, R13 / ADR 0233)
# Detecta resposta terminando em MENU de decisao tecnica sem recomendacao cravada.
# Advisory: exit 0 SEMPRE (nunca bloqueia/looa). Fail-open.

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $tp = [string]$payload.transcript_path
    if (-not $tp -or -not (Test-Path $tp)) { exit 0 }

    # Ultima mensagem assistant (texto)
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

    $hasRecommend = $text -match '(?i)recomend|minha recomenda|sugiro cravad'
    $hasMenuList  = $text -match '(?im)^\s*(\d[\.\)]|[-*]|\|)\s'
    $hasChoiceQ   = $text -match '(?i)(qual (voc[eê]|prefere|escolh|deles|op[cç][aã]o)|o que (voc[eê] )?prefere|voc[eê] (decide|escolhe|quem decide)|prefere\?|qual (e|é) (a )?melhor)'

    if ($hasMenuList -and $hasChoiceQ -and -not $hasRecommend) {
        Write-Output "[R13] Sua resposta parece terminar com MENU de decisao. Se for calculo tecnico (ROI/prioridade/sequencia/arquitetura), CRAVE uma recomendacao com razao e peca so validacao (Wagner valida, nao calcula). Menu so vale pra gosto/preferencia. Ref: memory/reference/feedback-recomendar-nao-menu.md"
    }
    exit 0
} catch {
    exit 0
}
