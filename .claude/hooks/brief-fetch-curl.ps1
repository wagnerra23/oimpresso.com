# brief-fetch-curl.ps1 — força chamada brief-fetch no SessionStart
#
# Resolve o problema: Claude Code em worktree filho não conecta MCP harness,
# então tool brief-fetch fica invisível e Claude pula brief (sinal degradação #1).
#
# Este hook chama brief-fetch DIRETO via curl (HTTP POST JSON-RPC autenticado)
# e imprime o brief no stdout — vira system-reminder do SessionStart.
#
# Falhas graciosas (3 cenários):
#   1. token ausente em .claude/settings.local.json → fallback handoff index
#   2. servidor MCP unreachable (timeout 10s)        → fallback handoff index
#   3. JSON-RPC error / parse fail                   → fallback handoff index
#
# Custo: ~3k tokens fixo por sessão, cache 5min server-side (skill brief-first §"Cache").
#
# Referências:
# - Skill brief-first SKILL.md (.claude/skills/brief-first/SKILL.md)
# - ADR 0091 Daily Brief (memory/decisions/0091-daily-brief.md)
# - Sinal degradação #1 catalogado (memory/proibicoes.md §"Comportamento Claude")

$ErrorActionPreference = 'Stop'

# Força UTF-8 no stdout — sem isso, acentos PT-BR viram mojibake (â€, Ã§, etc)
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

function Write-Fallback {
    param([string]$Reason)
    Write-Host ""
    Write-Host "=== [brief-fetch hook] FALLBACK ATIVADO — motivo: $Reason ==="
    Write-Host "MCP brief-fetch indisponível. Use ÍNDICE de handoffs como contexto inicial:"
    Write-Host ""
    if (Test-Path 'memory/08-handoff.md') {
        Get-Content 'memory/08-handoff.md' -Tail 30 -Encoding UTF8
    } else {
        Write-Host "(memory/08-handoff.md não encontrado neste worktree)"
    }
    Write-Host ""
    Write-Host "⚠ Claude — sem brief, você opera com dados parciais. Pergunte ao Wagner se quer rodar brief manual via Bash curl."
    Write-Host ""
}

# Resolver caminho do settings.local.json — primeiro local, fallback projeto principal
$settingsPath = $null
if (Test-Path '.claude/settings.local.json') {
    $settingsPath = '.claude/settings.local.json'
} elseif (Test-Path 'D:/oimpresso.com/.claude/settings.local.json') {
    $settingsPath = 'D:/oimpresso.com/.claude/settings.local.json'
}

if (-not $settingsPath) {
    Write-Fallback "settings.local.json não encontrado (token MCP indisponível)"
    exit 0
}

# Ler token Bearer do settings.local.json
try {
    $settings = Get-Content $settingsPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $authHeader = $settings.mcpServers.oimpresso.headers.Authorization
    if (-not $authHeader -or -not $authHeader.StartsWith('Bearer mcp_')) {
        Write-Fallback "header Authorization inválido em $settingsPath"
        exit 0
    }
} catch {
    Write-Fallback "erro lendo settings.local.json: $($_.Exception.Message)"
    exit 0
}

# JSON-RPC payload — tools/call brief-fetch sem argumentos (cache wins)
$body = @{
    jsonrpc = '2.0'
    id      = 1
    method  = 'tools/call'
    params  = @{
        name      = 'brief-fetch'
        arguments = @{}
    }
} | ConvertTo-Json -Depth 5 -Compress

# POST autenticado via curl.exe — PowerShell 5.1 Invoke-RestMethod tem bug com
# charset UTF-8 (decodifica como Windows-1252). curl.exe respeita UTF-8 nativamente.
# Timeout 10s pra não atrasar SessionStart se servidor lento.
$bodyTmp = [System.IO.Path]::GetTempFileName()
try {
    [System.IO.File]::WriteAllText($bodyTmp, $body, [System.Text.UTF8Encoding]::new($false))
    $curlOutput = & curl.exe -s --max-time 10 `
        -H 'Content-Type: application/json; charset=utf-8' `
        -H "Authorization: $authHeader" `
        --data-binary "@$bodyTmp" `
        https://mcp.oimpresso.com/api/mcp 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fallback "curl falhou (exit $LASTEXITCODE): $curlOutput"
        exit 0
    }
    $response = $curlOutput -join "`n" | ConvertFrom-Json
} catch {
    Write-Fallback "POST MCP falhou: $($_.Exception.Message)"
    exit 0
} finally {
    if (Test-Path $bodyTmp) { Remove-Item $bodyTmp -Force }
}

# Validar resposta JSON-RPC
if ($response.error) {
    Write-Fallback "MCP retornou error: $($response.error.message)"
    exit 0
}

if (-not $response.result -or -not $response.result.content) {
    Write-Fallback "MCP retornou estrutura inesperada (sem result.content)"
    exit 0
}

# Extrair texto markdown do content[0].text (formato MCP Tool Result canônico)
$briefText = ''
foreach ($block in $response.result.content) {
    if ($block.type -eq 'text' -and $block.text) {
        $briefText += $block.text + "`n"
    }
}

if ([string]::IsNullOrWhiteSpace($briefText)) {
    Write-Fallback "MCP retornou content vazio"
    exit 0
}

# SUCESSO — imprime brief no stdout (vira system-reminder do SessionStart)
Write-Host ""
Write-Host "=== [brief-fetch] Daily Brief — estado consolidado MCP oimpresso ==="
Write-Host ""
Write-Host $briefText
Write-Host ""
Write-Host "=== [brief-fetch] FIM brief — use como contexto base, NÃO refaça queries que já estão aqui ==="
Write-Host ""
