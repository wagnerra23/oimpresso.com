# Hook PreToolUse -- BLOQUEIA Write/Edit que contenha git merge conflict markers nao-resolvidos.
#
# Origem: post-mortem v4 go-live (memory/reference/post-mortem-v4-go-live.md) anti-pattern A.
# Incidentes raiz:
#   - PR #1000 (2026-05-17) -- conflito merge em SrsMemoryReader.php foi pra prod com
#     '<<<<<<<' no codigo, PHP parse error 'syntax error, unexpected token "<<"'.
#   - PR #1001 -- git markers em PHPDoc cross-projeto (4 arquivos Modules/*).
#
# Pattern: linhas comecadas com '<<<<<<< ', '======='  (exato 7 chars), '>>>>>>> '
# (conflito Git padrao -- 'diff3' adiciona '|||||||' tambem).
#
# Match: Write/Edit/MultiEdit em qualquer arquivo de codigo (*.php, *.js, *.ts, *.tsx, *.css, *.json,
#        *.yaml, *.yml, *.md, *.sh, *.ps1, *.py, *.html, *.blade.php). Skip arquivos binarios.
#
# Modo de operacao (env var OIMPRESSO_MERGE_HOOK_MODE):
#   - strict (DEFAULT)                         -> deny imediato (conflito = sempre bug)
#   - warn                                     -> imprime warning stderr, exit 0
#   - off                                      -> exit 0 sem checar
#
# Override emergencial: env var OIMPRESSO_MERGE_OVERRIDE=1 (Wagner Tier 0).
# Default strict porque conflito nao-resolvido em codigo = sempre quebra prod (zero falso-positivo
# legitimo -- doc explicando padrao usa contraplica como `<<<<<<<` em backticks, n�o em coluna 0).

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

# Modo
$mode = if ($env:OIMPRESSO_MERGE_HOOK_MODE) { $env:OIMPRESSO_MERGE_HOOK_MODE.ToLower() } else { 'strict' }
if ($mode -eq 'off') { exit 0 }

# Override
if ($env:OIMPRESSO_MERGE_OVERRIDE -eq '1') {
    [Console]::Error.WriteLine("[block-merge-markers] OVERRIDE ATIVO -- $tool em '$path' liberado.")
    exit 0
}

# Skip extensoes binarias
$pathLower = $path.ToLower()
$binExtensions = @('.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.pdf', '.zip', '.tar', '.gz', '.exe', '.dll', '.so')
foreach ($ext in $binExtensions) {
    if ($pathLower.EndsWith($ext)) { exit 0 }
}

# Skip arquivos de fixture/test que documentam conflict markers como string esperada
if ($pathLower -match '/fixtures/' -or $pathLower -match '\.fixture\.') { exit 0 }
# Self-exempt: este hook + test sibling + post-mortem doc que documentam o padrao
if ($pathLower -match '/\.claude/hooks/block-merge-markers\.(ps1|test\.ps1)$') { exit 0 }
if ($pathLower -match '/post-mortem-v4-go-live\.md$') { exit 0 }

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

# Regex git merge markers em inicio de linha (multiline mode):
# - <<<<<<< (7 chars + espaco + label) -- "ours" branch
# - ||||||| (7 chars) -- diff3 base
# - ======= (7 chars exato) -- separator
# - >>>>>>> (7 chars + espaco + label) -- "theirs" branch
$mergePattern = '(?m)^(<{7} |={7}$|>{7} |\|{7} )'

if ($content -notmatch $mergePattern) { exit 0 }

# Marker detectado
$msg = "[block-merge-markers] $tool em '$path' contem git conflict marker nao-resolvido. " +
       "Anti-pattern A do post-mortem v4 (incidente PR #1000 quebrou prod com parse error '<<'). " +
       "Resolva o conflito ANTES de salvar -- escolha 'ours'/'theirs' ou merge manual, " +
       "depois remova as linhas '<<<<<<<', '|||||||', '=======', '>>>>>>>'. " +
       "Override emergencial: env OIMPRESSO_MERGE_OVERRIDE=1."

if ($mode -eq 'warn') {
    [Console]::Error.WriteLine($msg)
    exit 0
}

# strict (default): bloqueia
@{
    decision      = 'deny'
    reason        = 'Git merge conflict markers nao-resolvidos detectados (anti-pattern A post-mortem v4)'
    systemMessage = $msg
} | ConvertTo-Json -Compress

exit 0
