# receita-drift-rule.ps1 - Regra PURA de deteccao de drift de receita (testavel isolada).
#
# Camada de DETECCAO do Loop Fechado Anti-Drift (ADR receita-metrica-mae-loop-fechado).
# Usada pelo sensor revenue-pulse (quando deployado) e pelo brief (secao RECEITA).
# Isolar a regra aqui torna ela TESTAVEL sem servidor/DB (ver receita-drift-rule.tests.ps1).
#
# 3 niveis:
#   SILENT - cycle ativo NAO e de receita        -> loop inativo (nao incomoda fora de cycle Receita)
#   FRAME  - cycle Receita, sem sinal de drift    -> lembrete do frame (cedo OU houve movimento)
#   DRIFT  - cycle Receita + 0 clientes novos 7d + decorrido >= threshold -> forcador forte
#
# Threshold default 25%: antes disso e cedo demais pra cravar drift (cycle recem-aberto).
# Espelha a logica do BriefFetchTool::renderCycleDriftAlert (que ignora cycle < 20% decorrido).

function Get-ReceitaDriftLevel {
    param(
        [string]$CycleName,
        [int]$Novos7d,
        [double]$PctElapsed,
        [double]$DriftThresholdPct = 25
    )
    if ([string]::IsNullOrWhiteSpace($CycleName) -or ($CycleName -notmatch 'Receita')) {
        return 'SILENT'
    }
    if ($Novos7d -le 0 -and $PctElapsed -ge $DriftThresholdPct) {
        return 'DRIFT'
    }
    return 'FRAME'
}
