# Hook PreToolUse — Warn se eu (Claude) for usar Read/Glob/Grep em memory/*
# Wagner 2026-04-30: forçar reflexo MCP-first sem precisar pingar.
#
# Lê JSON do stdin (formato Claude Code hooks):
#   { "tool_name": "Read", "tool_input": { "file_path": "memory/decisions/0053-...md" } }
#
# Se path bate com pattern, retorna systemMessage avisando pra usar MCP.

$ErrorActionPreference = 'Stop'
$input = [Console]::In.ReadToEnd()

try {
    $payload = $input | ConvertFrom-Json
} catch {
    exit 0  # Falha silente — não bloquear
}

$tool = $payload.tool_name
$path = $payload.tool_input.file_path
$pattern = $payload.tool_input.pattern

# Patterns que devem usar tool MCP
$mcpPatterns = @(
    'memory/decisions/',
    'memory/sessions/',
    'memory/requisitos/.*\.md',
    'memory/comparativos/',
    'memory/08-handoff',
    'CURRENT\.md$'
)

$target = if ($path) { $path } else { $pattern }
if (-not $target) { exit 0 }

$matched = $false
foreach ($p in $mcpPatterns) {
    if ($target -match $p) { $matched = $true; break }
}

if (-not $matched) { exit 0 }

# Sugestão pelo tipo de path
$suggestion = ''
switch -regex ($target) {
    'memory/decisions/(\d+-[\w-]+)\.md'        { $suggestion = "decisions-fetch slug:`"$($Matches[1])`""; break }
    'memory/decisions/'                         { $suggestion = "decisions-search query:`"...`""; break }
    'memory/sessions/'                          { $suggestion = "sessions-recent limit:5"; break }
    'CURRENT\.md|memory/08-handoff'             { $suggestion = "tasks-current"; break }
    default                                     { $suggestion = "decisions-search ou cc-search" }
}

# Output JSON pro Claude Code: systemMessage warn (não bloquear)
@{
    decision     = 'allow'
    systemMessage = "[oimpresso-mcp-first] Você ia $tool em '$target'. Considere tool MCP `'$suggestion`' — auditado em mcp_audit_log + 73% menos tokens. Filesystem só se MCP fora do ar. Ver SKILL .claude/skills/oimpresso-mcp-first/SKILL.md."
} | ConvertTo-Json -Compress

exit 0
