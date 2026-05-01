# Hook PreToolUse — BLOQUEIA Read/Glob/Grep em paths canonicos do projeto.
# ADR 0063: hierarquia de fontes -- MCP > SSH servidor > filesystem (apenas com permissao).
#
# Wagner 2026-04-30: "seu conhecimento e MCP"
# Eliana [E] 2026-04-30: "so o MCP e valido. Nao use nenhuma outra fonte vai me deixar frustrado"
#
# Override pra dev local: editar .claude/settings.local.json (gitignored) removendo este hook.

$ErrorActionPreference = 'Stop'
$payloadRaw = [Console]::In.ReadToEnd()

try {
    $payload = $payloadRaw | ConvertFrom-Json
} catch {
    exit 0  # Falha silente nao deve quebrar Claude
}

$tool = $payload.tool_name
$path = $payload.tool_input.file_path
$pattern = $payload.tool_input.pattern
$cmd = $payload.tool_input.command

# Captura o alvo (path pra Read, pattern pra Glob/Grep, cmd pra Bash com cat/head/tail)
$target = if ($path) { $path } elseif ($pattern) { $pattern } else { $cmd }
if (-not $target) { exit 0 }

# Patterns canonicos -- conhecimento que DEVE vir de MCP, nao filesystem
$mcpPatterns = @(
    'memory/decisions/',
    'memory/sessions/',
    'memory/requisitos/.*\.md',
    'memory/comparativos/',
    'memory/08-handoff',
    'memory/04-conventions',
    'memory/05-preferences',
    'memory/00-user-profile',
    'memory/INDEX',
    'CURRENT\.md$',
    'TASKS\.md$',
    'TEAM\.md$',
    'INFRA\.md$',
    'DESIGN\.md$',
    'AGENTS\.md$'
)

$matched = $false
foreach ($p in $mcpPatterns) {
    if ($target -match $p) { $matched = $true; break }
}

if (-not $matched) { exit 0 }

# Mapeia path -> tool MCP + SSH fallback
$mcpTool = ''
$sshFallback = ''
switch -regex ($target) {
    'memory/decisions/(\d{4}-[\w-]+)\.md' {
        $mcpTool = "decisions-fetch slug:`"$($Matches[1])`""
        break
    }
    'memory/decisions/' {
        $mcpTool = "decisions-search query:`"<termos>`""
        break
    }
    'memory/sessions/' {
        $mcpTool = "sessions-recent limit:5"
        break
    }
    'CURRENT\.md|memory/08-handoff|TASKS\.md' {
        $mcpTool = "tasks-current"
        break
    }
    'TEAM\.md' {
        $mcpTool = "decisions-search query:`"TEAM perfis WIP matriz`""
        break
    }
    'INFRA\.md' {
        $mcpTool = "decisions-search query:`"INFRA SSH Hostinger CT 100`""
        $sshFallback = "ssh hostinger (credenciais Vaultwarden vault.oimpresso.com)"
        break
    }
    'memory/requisitos/' {
        $mcpTool = "decisions-search query:`"<modulo>`" + decisions-fetch slug:`"adr-<modulo>-...`""
        break
    }
    'memory/comparativos/' {
        $mcpTool = "decisions-search query:`"comparativo <tema>`""
        break
    }
    default {
        $mcpTool = "decisions-search query:`"...`" ou cc-search query:`"...`""
    }
}

$sshHint = if ($sshFallback) {
    $sshFallback
} else {
    "Hostinger SSH (MySQL oimpresso) ou CT 100 Docker (MCP server / Meilisearch / Telescope)"
}

$systemMessage = "[mcp-first BLOCKED] $tool em '$target' BLOQUEADO pelo hook (ADR 0063). " +
    "Hierarquia de fontes: " +
    "(1) MCP server -> use $mcpTool. " +
    "(2) Se MCP nao cobrir, fonte viva: $sshHint. " +
    "(3) Filesystem local SO com permissao explicita do user na mensagem atual. " +
    "Eliana [E] frustrou-se 2026-04-30 quando Claude leu memory/ em vez de MCP. " +
    "Override em dev: edite .claude/settings.local.json removendo este hook."

@{
    decision      = 'deny'
    reason        = "ADR 0063: filesystem nao e fonte canonica do projeto. Use MCP."
    systemMessage = $systemMessage
} | ConvertTo-Json -Compress

exit 0
