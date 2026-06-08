# Hook PreToolUse -- BLOQUEIA Write/Edit em routes/*.php que use string legacy 'Controller@method'.
#
# Origem: post-mortem v4 go-live (memory/reference/post-mortem-v4-go-live.md) anti-pattern A.
# Incidente raiz: PR #843 (2026-05-14) -- 10 strings em routes/web.php linhas 231-239 + 259
# quebravam 'php artisan route:cache' com ReflectionException. Wagner ativou cache em prod
# sem perceber, rotas comecaram a 404 silenciosamente.
#
# Catalogado em .claude/rules/routes.md:
#   "FQCN obrigatorio: [Class::class, 'method'] -- strings legacy ja eram fallback Laravel
#    pre-cache; com cache ativo viram bug latente."
#
# Match: Write/Edit/MultiEdit em:
#   - routes/*.php
#   - Modules/*/Routes/*.php  (api.php, web.php, console.php)
#   - Modules/*/Http/routes.php  (padrao alternativo nWidart)
#
# Pattern proibido: ', 'XxxController@method'' ou '"XxxController@method"' como 2o arg de Route::*
# ou Route::resource('xxx', 'XxxController').
#
# Pattern permitido:
#   - [\Modules\X\Http\Controllers\YController::class, 'method']
#   - [YController::class, 'method'] (com use no topo)
#   - YController::class (Route::resource ou single-action)
#   - 'AppLevelController@method' permitido apenas em path = app/ (legado UltimatePOS pre-cache,
#     fora do escopo deste hook).
#
# Modo de operacao (env var OIMPRESSO_ROUTES_HOOK_MODE):
#   - strict (DEFAULT)  -> deny imediato (incidente catalogado, regra clara, falso-positivo raro)
#   - warn              -> warning stderr, exit 0
#   - off               -> sem checar
#
# Override emergencial: env var OIMPRESSO_ROUTES_OVERRIDE=1 (Wagner Tier 0).

$ErrorActionPreference = 'Stop'
[Console]::InputEncoding = New-Object System.Text.UTF8Encoding $false
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

# Normaliza path
$pathFwd = $path.Replace('\', '/').ToLower()

# Match patterns de routes
$isRoutesFile = $false
if ($pathFwd -match '/routes/[^/]+\.php$') { $isRoutesFile = $true }
elseif ($pathFwd -match '/modules/[^/]+/routes/[^/]+\.php$') { $isRoutesFile = $true }
elseif ($pathFwd -match '/modules/[^/]+/http/routes\.php$') { $isRoutesFile = $true }

if (-not $isRoutesFile) { exit 0 }

# Self-exempt: este hook + sibling + rules doc que cita o padrao em backticks
if ($pathFwd -match '/\.claude/hooks/block-routes-string-legacy\.(ps1|test\.ps1)$') { exit 0 }
if ($pathFwd -match '/\.claude/rules/routes\.md$') { exit 0 }

# Modo
$mode = if ($env:OIMPRESSO_ROUTES_HOOK_MODE) { $env:OIMPRESSO_ROUTES_HOOK_MODE.ToLower() } else { 'strict' }
if ($mode -eq 'off') { exit 0 }

# Override
if ($env:OIMPRESSO_ROUTES_OVERRIDE -eq '1') {
    [Console]::Error.WriteLine("[block-routes-string-legacy] OVERRIDE ATIVO -- $tool em '$path' liberado.")
    exit 0
}

# Pega o conteudo
$content = $null
if ($tool -eq 'Write') {
    $content = $payload.tool_input.content
} elseif ($tool -eq 'Edit') {
    $content = $payload.tool_input.new_string
} elseif ($tool -eq 'MultiEdit') {
    $edits = $payload.tool_input.edits
    if ($edits) {
        $content = ($edits | ForEach-Object { $_.new_string }) -join "`n"
    }
}

if (-not $content) { exit 0 }

# Regex pattern proibido: string legacy 'XxxController@method' como argumento.
# Heuristica: aspas + Pascal case acabando em 'Controller' + '@' + nome metodo.
# Captura tanto aspas simples quanto duplas.
# Falso-positivo aceito: docstring/comentario explicando padrao -- mitigado por linha-comentario check abaixo.
$legacyPattern = "['""]([A-Z][A-Za-z0-9_]*Controller)@([a-zA-Z_][a-zA-Z0-9_]*)['""]"

$matchesFound = [regex]::Matches($content, $legacyPattern)
if ($matchesFound.Count -eq 0) { exit 0 }

# Filtra falsos-positivos: linha que comeca com // ou * (comentario PHP) ou # (comentario shell-style)
$realMatches = @()
foreach ($m in $matchesFound) {
    # Encontra inicio da linha do match
    $lineStart = $content.LastIndexOf("`n", $m.Index)
    if ($lineStart -lt 0) { $lineStart = 0 } else { $lineStart++ }
    $lineEnd = $content.IndexOf("`n", $m.Index)
    if ($lineEnd -lt 0) { $lineEnd = $content.Length }
    $line = $content.Substring($lineStart, $lineEnd - $lineStart).TrimStart()

    # Pula comentarios
    if ($line.StartsWith('//') -or $line.StartsWith('*') -or $line.StartsWith('#')) { continue }
    if ($line.StartsWith('/*')) { continue }

    $realMatches += "Linha '$($line.Substring(0, [Math]::Min(80, $line.Length)))...': $($m.Value)"
}

if ($realMatches.Count -eq 0) { exit 0 }

# Match real detectado
$exemplo = $realMatches[0]
$msg = "[block-routes-string-legacy] $tool em '$path' usa string legacy 'Controller@method'. " +
       "Anti-pattern A do post-mortem v4 (incidente PR #843 quebrou route:cache prod). " +
       "Use FQCN: [\Modules\X\Http\Controllers\YController::class, 'method'] " +
       "ou 'use Modules\X\...\YController;' + [YController::class, 'method']. " +
       "Match detectado: $exemplo. " +
       "Override emergencial: env OIMPRESSO_ROUTES_OVERRIDE=1."

if ($mode -eq 'warn') {
    [Console]::Error.WriteLine($msg)
    exit 0
}

# strict: bloqueia
@{
    decision      = 'deny'
    reason        = 'String legacy Controller@method em routes (quebra route:cache, anti-pattern A post-mortem v4)'
    systemMessage = $msg
} | ConvertTo-Json -Compress

exit 0
