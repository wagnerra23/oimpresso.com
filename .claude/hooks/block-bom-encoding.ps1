# Hook PreToolUse -- BLOQUEIA Write/Edit que reintroduza UTF-8 BOM (EF BB BF) em arquivos de codigo.
#
# Origem: post-mortem v4 go-live (memory/reference/post-mortem-v4-go-live.md) anti-pattern A.
# Incidente raiz: PR #984 (2026-05-16) -- PowerShell 5.1 'Set-Content -Encoding utf8' grava
# UTF-8 COM BOM (EF BB BF prefix), violando o spec. PHP nao aceita 'classe' antes de <?php
# se houver bytes antes da tag, e o arquivo crasha com:
#   "Namespace declaration statement has to be the very first statement"
# Cinco arquivos (CmsController.php + 4 Crm/Entities) quebraram oimpresso.com inteiro.
#
# Catalogado em memory/proibicoes.md secao Ambiente:
#   "NUNCA usar Set-Content -Encoding utf8 sem o sufixo NoBOM em PS 5.1.
#    Validar pos-write: file <path> deve dizer 'UTF-8 text' sem 'with BOM'."
#
# Este hook aplica a regra automaticamente em runtime de Write/Edit do Claude Code.
#
# Match: Write/Edit/MultiEdit em arquivos *.php *.js *.ts *.tsx *.jsx *.mjs *.cjs *.vue *.css *.scss
# Skip: arquivos de teste explicito que precisam BOM (ex: fixture *.bom.txt), arquivos binarios,
#       arquivos *.md/*.yaml/*.json (BOM legivel para LSPs, sem crash PHP).
#
# Modo de operacao (env var OIMPRESSO_BOM_HOOK_MODE):
#   - warn   (DEFAULT 4 semanas calibracao) -> imprime warning stderr, exit 0
#   - strict (apos calibracao)              -> exit 0 com JSON deny no stdout
#   - off                                   -> exit 0 sem checar
#
# Override emergencial: env var OIMPRESSO_BOM_OVERRIDE=1 (Wagner Tier 0).

$ErrorActionPreference = 'Stop'
# Forca UTF-8 no stdin -- PS 5.1 default eh OEM cp que perde U+FEFF como '?'
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
$mode = if ($env:OIMPRESSO_BOM_HOOK_MODE) { $env:OIMPRESSO_BOM_HOOK_MODE.ToLower() } else { 'warn' }
if ($mode -eq 'off') { exit 0 }

# Override
if ($env:OIMPRESSO_BOM_OVERRIDE -eq '1') {
    [Console]::Error.WriteLine("[block-bom-encoding] OVERRIDE ATIVO -- $tool em '$path' liberado.")
    exit 0
}

# Filtra extensoes que crasham com BOM (PHP/JS/TS/CSS) ou que ferramentas reclamam (Vue)
$pathLower = $path.ToLower()
$matchesExt = $false
foreach ($ext in @('.php', '.js', '.ts', '.tsx', '.jsx', '.mjs', '.cjs', '.vue', '.css', '.scss')) {
    if ($pathLower.EndsWith($ext)) { $matchesExt = $true; break }
}
if (-not $matchesExt) { exit 0 }

# Skip explicito: fixtures de teste de BOM
if ($pathLower -match '\.bom\.' -or $pathLower -match '/fixtures/.*bom') { exit 0 }

# Pega o conteudo que Claude esta tentando escrever
$content = $null
if ($tool -eq 'Write') {
    $content = $payload.tool_input.content
} elseif ($tool -eq 'Edit') {
    $content = $payload.tool_input.new_string
} elseif ($tool -eq 'MultiEdit') {
    # MultiEdit nao tem 1 string -- checa cada new_string
    $edits = $payload.tool_input.edits
    if ($edits) {
        foreach ($edit in $edits) {
            if ($edit.new_string -and $edit.new_string.StartsWith([char]0xFEFF)) {
                $content = $edit.new_string
                break
            }
        }
    }
}

if (-not $content) { exit 0 }

# Detecta BOM: caractere U+FEFF no inicio (PowerShell ConvertFrom-Json decoda EF BB BF byte sequence
# como caractere U+FEFF na string -- precisamos checar string-level)
$hasBom = $content.Length -gt 0 -and [int][char]$content[0] -eq 0xFEFF

if (-not $hasBom) { exit 0 }

# BOM detectado -- bloquear ou warn
$msg = "[block-bom-encoding] $tool em '$path' contem UTF-8 BOM (EF BB BF) no inicio. " +
       "Anti-pattern A do post-mortem v4 (incidente PR #984 quebrou prod). " +
       "PHP/JS nao aceitam bytes antes de <?php / shebang. " +
       "Remova o BOM antes do primeiro caractere ou use [System.IO.File]::WriteAllText com UTF8Encoding sem BOM. " +
       "Override emergencial: env OIMPRESSO_BOM_OVERRIDE=1."

if ($mode -eq 'warn') {
    [Console]::Error.WriteLine($msg)
    [Console]::Error.WriteLine("[block-bom-encoding] modo warn -- prosseguindo. Vai virar strict apos 4 semanas calibracao.")
    exit 0
}

# strict: bloqueia via JSON deny
@{
    decision      = 'deny'
    reason        = 'UTF-8 BOM detectado em arquivo de codigo (anti-pattern A post-mortem v4)'
    systemMessage = $msg
} | ConvertTo-Json -Compress

exit 0
