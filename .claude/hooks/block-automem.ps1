# Hook PreToolUse — BLOQUEIA Write/Edit em auto-mem privada legada.
#
# Regra atualizada pelo ADR 0131 (2026-05-10):
#   - BLOQUEIA  ~/.claude/projects/*/memory/*.md         (auto-mem legada — em migração via skill automem-classify)
#   - BLOQUEIA  %AppData%/Local|Roaming/.claude*/memory/  (mesma família)
#   - PERMITE   ~/.claude/oimpresso-local/**             (escape valve — máquina-local pessoal)
#   - PERMITE   memory/ no worktree git                  (canônico — sem hook necessário)
#
# 3 tiers oficiais (ADR 0131):
#   1. Canônico  → git memory/ → MCP (time inteiro vê)
#   2. Local     → ~/.claude/oimpresso-local/ (só este dev)
#   3. Segredo   → Vaultwarden vault.oimpresso.com (cofre criptografado)
#
# Origem: ADR 0061 (Wagner 2026-04-30 "auto-mem local não deve existir")
# Refinamento: ADR 0131 (Wagner 2026-05-10 "como posso proteger os dados e fazer funcionar")

$ErrorActionPreference = 'Stop'
# NOTA: NÃO usar $input (variável automática PowerShell — conflita em 5.1).
$payloadJson = [Console]::In.ReadToEnd()

try {
    $payload = $payloadJson | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
$path = $payload.tool_input.file_path

if (-not $path) { exit 0 }

# Path normalizado (forward slash + lowercase)
$pathLower = $path.Replace('\', '/').ToLower()

# ESCAPE VALVE — oimpresso-local/ é zona pessoal explícita (ADR 0131)
if ($pathLower -match '\.claude/oimpresso-local/') {
    exit 0
}

# Padrões proibidos (auto-mem privada Claude Code legada)
$proibidos = @(
    '\.claude/projects/.*?/memory/.*\.md',
    'appdata/local/.*\.claude.*?/memory/',
    'appdata/roaming/.*\.claude.*?/memory/'
)

$bloquear = $false
foreach ($p in $proibidos) {
    if ($pathLower -match $p) { $bloquear = $true; break }
}

if (-not $bloquear) { exit 0 }

# Decide: deny + sugestão dos 3 tiers (ADR 0131)
$alvo_git = "memory/decisions/NNNN-slug.md (ADR Nygard)"
if ($pathLower -match 'reference_|reference-') { $alvo_git = "memory/requisitos/{Modulo}/RUNBOOK-tema.md OU memory/decisions/" }
elseif ($pathLower -match 'feedback_|feedback-') { $alvo_git = "memory/decisions/NNNN-slug.md (ADR pq decisão) OU memory/requisitos/{Modulo}/feedback.md" }
elseif ($pathLower -match 'project_|project-') { $alvo_git = "memory/requisitos/{Modulo}/SPEC.md OU CHANGELOG.md" }
elseif ($pathLower -match 'session_|session-') { $alvo_git = "memory/sessions/YYYY-MM-DD-slug.md" }
elseif ($pathLower -match 'comparativ') { $alvo_git = "memory/comparativos/slug_capterra.md" }
elseif ($pathLower -match 'cliente_|client_') { $alvo_git = "memory/requisitos/{Modulo}/quirks.md OU ADR" }

$reason = "ADR 0061 + 0131: auto-mem privada legada PROIBIDA. Use um dos 3 tiers oficiais."

$msg = @"
[block-automem] $tool em '$path' BLOQUEADO.

REGRA (ADR 0131 — 3 tiers de memória):
  1. CANÔNICO (time inteiro)  → git memory/ → MCP. Sugestão pra este path: $alvo_git
  2. MÁQUINA-LOCAL (só você)  → ~/.claude/oimpresso-local/ (livre, fora do git)
  3. SEGREDO (token/senha)    → Vaultwarden vault.oimpresso.com (NUNCA em arquivo plain)

Critério (1 pergunta): este fato é segredo? só seu? ou o time precisa ver?

Auto-mem em ~/.claude/projects/*/memory/ é LEGADA — em migração via skill 'automem-classify'.
NÃO criar arquivos novos lá. Read continua permitido (ler conteúdo legado durante migração).
"@

@{
    decision      = 'deny'
    reason        = $reason
    systemMessage = $msg
} | ConvertTo-Json -Compress

exit 0
