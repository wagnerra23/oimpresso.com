# receita-loop-check.ps1 - SessionStart: FORCA frame de receita quando o cycle ativo e de Receita.
#
# Camada 3 (actuator) do Loop Fechado Anti-Drift (ADR proposto receita-metrica-mae-loop-fechado).
#
# Problema que resolve: o sistema deriva pra "qualidade de engenharia" e larga a receita
#   (drift 104/104 commits fora do cycle, 2026-05-31). Mudar o cycle uma vez NAO basta -
#   sem um forcador, em 7 dias deriva de novo. Este hook reabre o frame de receita a CADA
#   sessao enquanto o cycle ativo for de Receita, e exige a acao comercial do dia antes de codar.
#
# Como funciona:
#   1. Chama cycles-active via curl JSON-RPC autenticado (modelo: brief-fetch-curl.ps1)
#   2. Se o cycle ativo contem "Receita" (nome/goal) -> injeta forcador no stdout (system-reminder)
#   3. Caso contrario (ou falha) -> exit 0 silencioso (nunca bloqueia SessionStart)
#
# ATENCAO encoding: PowerShell 5.1 le .ps1 como Windows-1252. NAO usar em-dash nem emoji
#   no arquivo (o byte 0x94 do em-dash colide com aspas e quebra o parser). ASCII-only.
#
# Custo: 1 chamada MCP/sessao (cache server-side), timeout 8s. Falha graciosa total.
# Refs: ADR proposto receita-metrica-mae-loop-fechado, CYCLE-08 Receita, Principio duro 4.

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

# Resolver settings.local.json (token MCP) - local primeiro, fallback projeto principal
$settingsPath = $null
if (Test-Path '.claude/settings.local.json') {
    $settingsPath = '.claude/settings.local.json'
} elseif (Test-Path 'D:/oimpresso.com/.claude/settings.local.json') {
    $settingsPath = 'D:/oimpresso.com/.claude/settings.local.json'
}
if (-not $settingsPath) { exit 0 }

try {
    $settings = Get-Content $settingsPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $authHeader = $settings.mcpServers.oimpresso.headers.Authorization
    if (-not $authHeader -or -not $authHeader.StartsWith('Bearer mcp_')) { exit 0 }
} catch { exit 0 }

# JSON-RPC: tools/call cycles-active (project default COPI)
$body = @{
    jsonrpc = '2.0'
    id      = 1
    method  = 'tools/call'
    params  = @{ name = 'cycles-active'; arguments = @{} }
} | ConvertTo-Json -Depth 5 -Compress

$cycleText = ''
$bodyTmp = [System.IO.Path]::GetTempFileName()
try {
    [System.IO.File]::WriteAllText($bodyTmp, $body, [System.Text.UTF8Encoding]::new($false))
    $curlOutput = & curl.exe -s --max-time 8 `
        -H 'Content-Type: application/json; charset=utf-8' `
        -H "Authorization: $authHeader" `
        --data-binary "@$bodyTmp" `
        https://mcp.oimpresso.com/api/mcp 2>&1
    if ($LASTEXITCODE -ne 0) { exit 0 }
    $response = $curlOutput -join "`n" | ConvertFrom-Json
    if ($response.error -or -not $response.result -or -not $response.result.content) { exit 0 }
    foreach ($block in $response.result.content) {
        if ($block.type -eq 'text' -and $block.text) { $cycleText += $block.text + "`n" }
    }
} catch {
    exit 0
} finally {
    if (Test-Path $bodyTmp) { Remove-Item $bodyTmp -Force }
}

# So dispara se o cycle ativo e de RECEITA (nome do cycle contem "Receita")
if ($cycleText -notmatch 'Receita') { exit 0 }

# FORCADOR - frame de receita + exigencia da acao comercial do dia
Write-Host ""
Write-Host "=== [receita-loop] NORTE = RECEITA (loop fechado anti-drift) ==="
Write-Host ""
Write-Host "O cycle ativo e de RECEITA. Antes de qualquer codigo/refactor/design nesta sessao:"
Write-Host ""
Write-Host "  1. DECLARE a acao comercial de hoje: qual cliente da carteira voce toca?"
Write-Host "     Placar: memory/clientes/_pipeline-migracao-legacy.md"
Write-Host "  2. ATUALIZE o progresso dos goals: cycle-goals-track cycle:CYCLE-08"
Write-Host "  3. REGRA (ADR Receita metrica-mae): so passa trabalho COM sinal de cliente pagante."
Write-Host "     Excecao justificada por sinal: ComVis V1 (destrava ~30 graficas) + boletos cliente real."
Write-Host ""
Write-Host "  Se esta sessao NAO tem acao comercial NEM trabalho com sinal de cliente pagante,"
Write-Host "  PERGUNTE ao Wagner se e a prioridade certa antes de prosseguir (anti-drift)."
Write-Host ""
Write-Host "=== [receita-loop] fim - Principio duro 4: loop fechado por metrica (= receita) ==="
Write-Host ""
exit 0
