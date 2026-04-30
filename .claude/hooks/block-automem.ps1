# Hook PreToolUse — BLOQUEIA Write/Edit em auto-mem privada (~/.claude/projects/*/memory/*.md).
# ADR 0061: todo conhecimento canônico vai pra git/MCP, ZERO auto-mem.
#
# Wagner 2026-04-30: "auto-mem local não deve existir, coloque trigger para tornar na rede"

$ErrorActionPreference = 'Stop'
$input = [Console]::In.ReadToEnd()

try {
    $payload = $input | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
$path = $payload.tool_input.file_path

if (-not $path) { exit 0 }

# Path normalizado
$pathLower = $path.Replace('\', '/').ToLower()

# Padrões proibidos (auto-mem privada Claude Code)
$proibidos = @(
    '\.claude/projects/.*?/memory/.*\.md',
    'AppData/Local/.*\.claude.*?/memory/',
    'AppData/Roaming/.*\.claude.*?/memory/'
)

$bloquear = $false
foreach ($p in $proibidos) {
    if ($pathLower -match $p) { $bloquear = $true; break }
}

if (-not $bloquear) { exit 0 }

# Decide: deny + sugestão pro git
$alvo_git = "memory/decisions/NNNN-slug.md (ADR Nygard)"
if ($pathLower -match 'reference_|reference-') { $alvo_git = "memory/requisitos/{Modulo}/RUNBOOK-tema.md OU memory/decisions/" }
elseif ($pathLower -match 'feedback_|feedback-') { $alvo_git = "memory/decisions/NNNN-slug.md (ADR pq decisão)" }
elseif ($pathLower -match 'project_|project-') { $alvo_git = "memory/requisitos/{Modulo}/SPEC.md OU CHANGELOG.md" }
elseif ($pathLower -match 'session_|session-') { $alvo_git = "memory/sessions/YYYY-MM-DD-slug.md" }
elseif ($pathLower -match 'comparativ') { $alvo_git = "memory/comparativos/slug_capterra.md" }
elseif ($pathLower -match 'cliente_|client_') { $alvo_git = "memory/requisitos/{Modulo}/quirks.md OU ADR" }

@{
    decision      = 'deny'
    reason        = "ADR 0061 viola: auto-mem privada PROIBIDA"
    systemMessage = "[block-automem] $tool em '$path' BLOQUEADO. ADR 0061: todo conhecimento canônico vai pra git/MCP, ZERO auto-mem privada. Devs não enxergam auto-mem (silo). Migre pra: $alvo_git e commit + push (webhook GitHub sincroniza pro MCP em <60s). Exceções (4): credencial temporária dev, working memory ad-hoc, cache de tools, hint pessoal Wagner-only — todas EXPLICITADAS pelo Wagner antes de criar."
} | ConvertTo-Json -Compress

exit 0
