# Hook PreToolUse — WARN/BLOQUEIA Edit/Write em Pages/<Mod>/<Tela>.tsx sem chamada charter-fetch prévia.
# Camada de enforcement do princípio #3 da Constituição V2 (Charter > Spec — ADR 0094 + ADR 0101).
# GAP-ANALYSIS-91-100-2026-05-13 (C1 P0 Onda 4) — ativa Page Charters S4.
#
# Wagner 2026-05-13: 26 charters em prod mas tool charter-fetch acabou de chegar (Onda 4).
# Modo default WARNING (não bloqueia) — vira BLOQUEANTE quando ROI provado (P1):
#   - ≥5 sessões usando charter-fetch
#   - ≥1 caso de drift evitado via Anti-hooks do charter
#   - Wagner sign-off
#
# Match: resources/js/Pages/<Mod>/<Tela>.tsx OU resources/js/Pages/<Mod>/<Sub>/<Tela>.tsx
# Quando .charter.md irmão existe + status: live/draft/rascunho, força reflexo chamar charter-fetch.
#
# Modo bloqueante (futuro): exportar ENV `CHARTER_VALIDATE_STRICT=1`.

$ErrorActionPreference = 'Stop'
$rawInput = [Console]::In.ReadToEnd()

if (-not $rawInput) { exit 0 }

try {
    $payload = $rawInput | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
if ($tool -notin @('Write', 'Edit', 'MultiEdit')) { exit 0 }

$path = $payload.tool_input.file_path
if (-not $path) { exit 0 }

# Normalizar pra forward slashes (Windows path → posix)
$pathFwd = $path.Replace('\', '/')

# Match Pages/<Mod>/<Tela>.tsx (top-level OU 1 nível de subdir)
$regex = 'resources/js/Pages/([^/_][^/]*)/(?:[^/]+/)?([A-Za-z][A-Za-z0-9]*)\.tsx$'
if ($pathFwd -notmatch $regex) { exit 0 }

# Exemptions
$modulo = $matches[1]
$tela = $matches[2]
if ($modulo -in @('_Showcase', '_components', '_internal')) { exit 0 }
if ($tela -match '^_' -or $tela -in @('App', 'Layout')) { exit 0 }

# Charter esperado ao lado: <path-sem-tsx>.charter.md
$charterPath = $path.Substring(0, $path.Length - 4) + '.charter.md'
if (-not (Test-Path $charterPath)) { exit 0 }  # Sem charter, sem enforcement

# Ler status do charter (primeiros 30 linhas — frontmatter)
try {
    $head = Get-Content $charterPath -TotalCount 30 -Encoding UTF8 -ErrorAction SilentlyContinue
    $status = ($head | Where-Object { $_ -match '^status:\s*(\S+)' } | Select-Object -First 1)
    if ($status -match '^status:\s*(\S+)') {
        $charterStatus = $matches[1].Trim('"', "'")
    } else {
        $charterStatus = 'unknown'
    }
} catch {
    $charterStatus = 'unknown'
}

# Determinar modo: warning-mode default, strict-mode se env CHARTER_VALIDATE_STRICT=1
$strictMode = ($env:CHARTER_VALIDATE_STRICT -eq '1')

$charterRelative = $charterPath.Replace('\', '/')
$msg = "[charter-first] $tool em '$pathFwd' detectado — esta tela TEM contrato vivo em '$charterRelative' (status: $charterStatus). "
$msg += "Princípio Constituição V2 #3 (Charter > Spec — ADR 0094 + ADR 0101): chame tool MCP `charter-fetch page_id:`'$pathFwd`'` ANTES de editar pra carregar Mission/Goals/Non-Goals/UX targets/Anti-hooks. "
$msg += "Skill `charter-first` Tier A. "

if ($strictMode) {
    $msg += "Modo STRICT (env CHARTER_VALIDATE_STRICT=1) — Edit BLOQUEADO."
    @{
        decision      = 'deny'
        reason        = 'charter-first Tier A — strict mode'
        systemMessage = $msg
    } | ConvertTo-Json -Compress
    exit 0
}

# Modo warning default: allow + systemMessage
$msg += 'Modo warning-mode (P1 — vira bloqueante quando ROI provado em ≥5 sessões).'
@{
    decision      = 'allow'
    systemMessage = $msg
} | ConvertTo-Json -Compress

exit 0

