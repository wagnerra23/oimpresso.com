# Hook PreToolUse -- DETECTA DRIFT de Controllers fora do SCOPE.md do modulo.
#
# Implementa Mecanismo #3 (Pre-commit hook) de ENFORCEMENT.md secao 2:
#   "Hook git local que avisa (ou bloqueia, configuravel) quando dev cria
#    controller fora do SCOPE.md do modulo."
#
# Constituicao v1.1.0 Artigo 7 (Module Charter -- ADR 0080) define que cada
# Module/<X> tem SCOPE.md com `contains[]` listando Controllers permitidos.
# Controller fora do scope = drift catalogado pra mover ou declarar.
#
# Fontes canonicas (ENFORCEMENT.md secao 2 #3):
#   - NIST SP 800-207 endpoint validation
#   - OPA local enforcement em developer machines
#
# Modo de operacao (env var OIMPRESSO_DRIFT_HOOK_MODE):
#   - warn   (DEFAULT -- 4 semanas calibracao) -> imprime warning stderr, exit 0
#   - strict (apos calibracao)                 -> exit 0 com JSON deny no stdout
#   - off    (desabilita check)                -> exit 0 sem checar
#
# Override emergencial (env var OIMPRESSO_DRIFT_OVERRIDE=1):
#   - Wagner Tier 0 superadmin pula check (registrar uso obrigatorio).
#
# Match: Modules/<X>/Http/Controllers/**/*Controller.php
#   - top-level:        Modules/Jana/Http/Controllers/ChatController.php
#   - Admin subfolder:  Modules/Jana/Http/Controllers/Admin/CustosController.php
#   - sub-sub:          Modules/Whatsapp/Http/Controllers/Api/MetaWebhookController.php
#
# Por que NAO bloqueia em warn (default):
#   - Alinhado com modulo-preflight-warning.ps1 (regra cultural, nao fail-secure)
#   - Alinhado com ADR 0086 (ActionGate warn-only durante calibracao)
#   - Bloquear Edit valido (ex: editar Controller ja existente) quebra workflow
#
# Quando virar STRICT (recomendacao ENFORCEMENT.md #3):
#   - Apos 4 semanas de warn-only com zero false positive
#   - Wagner aprova via ADR 0082 (Pre-commit hook ADR derivada -- pendente)
#
# NOTA: arquivo em ASCII puro -- PowerShell 5.1 (default Windows) le scripts
# como code-page legacy, e UTF-8 sem BOM quebra parse em chars acentuados.

$ErrorActionPreference = 'Continue'

# --- 1. Le payload Claude Code (stdin JSON) ---

try {
    $rawInput = [Console]::In.ReadToEnd()
    if (-not $rawInput) { exit 0 }
    $payload = $rawInput | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
if ($tool -notin @('Write', 'Edit', 'MultiEdit')) { exit 0 }

$path = $payload.tool_input.file_path
if (-not $path) { exit 0 }

# --- 2. Filtra: so Modules/<X>/Http/Controllers/**/*Controller.php ---

$pathFwd = $path.Replace('\', '/')

# Regex captura: modulo (PascalCase) + Controller base name (sem .php)
# Aceita: Modules/<X>/Http/Controllers/<Sub>/<...>Controller.php
$ctrlRegex = 'Modules/([A-Z][A-Za-z0-9]*)/Http/Controllers/(?:[^/]+/)*([A-Za-z][A-Za-z0-9]*Controller)\.php$'
if ($pathFwd -notmatch $ctrlRegex) { exit 0 }

$moduleName = $Matches[1]
$controllerName = $Matches[2]

# --- 3. Modo de operacao (env var) ---

$mode = $env:OIMPRESSO_DRIFT_HOOK_MODE
if (-not $mode) { $mode = 'warn' }
$mode = $mode.ToLower()

if ($mode -eq 'off') { exit 0 }

# Override emergencial Wagner Tier 0
if ($env:OIMPRESSO_DRIFT_OVERRIDE -eq '1') { exit 0 }

# --- 4. Localiza SCOPE.md do modulo ---

$projectDir = $env:CLAUDE_PROJECT_DIR
if (-not $projectDir) { $projectDir = (Get-Location).Path }

$scopePath = Join-Path $projectDir "Modules/$moduleName/SCOPE.md"

if (-not (Test-Path $scopePath)) {
    # SCOPE.md ainda nao existe -- nao conseguimos checar.
    if ($mode -eq 'strict') {
        $msg = "[block-module-drift] $tool em '$pathFwd' BLOQUEADO. SCOPE.md ausente em Modules/$moduleName/. Crie SCOPE.md (ADR 0080, copie template de outro modulo) declarando contains[] antes de adicionar Controllers."
        @{
            decision      = 'deny'
            reason        = 'SCOPE.md ausente -- Artigo 7 Module Charter exige'
            systemMessage = $msg
        } | ConvertTo-Json -Compress
        exit 0
    } else {
        [Console]::Error.WriteLine("`n[drift-hook] AVISO: Modules/$moduleName/SCOPE.md ausente. Nao foi possivel validar drift de $controllerName. Crie SCOPE.md (ADR 0080).`n")
        exit 0
    }
}

# --- 5. Parse YAML frontmatter pragmatico (sem ConvertFrom-Yaml) ---
#
# Estrutura esperada:
#   ---
#   module: Jana
#   contains:
#     - "ChatController -- UI chat principal"
#     - "Admin/CustosController -- dashboard custos LLM"
#     - "DataController"
#   not_contains:
#     - "MemoriaController -> Modules/KB"
#   ---

$scopeContent = Get-Content $scopePath -Raw -ErrorAction SilentlyContinue
if (-not $scopeContent) {
    if ($mode -eq 'strict') {
        $msg = "[block-module-drift] $tool em '$pathFwd' BLOQUEADO. SCOPE.md em Modules/$moduleName/ vazio ou ilegivel."
        @{
            decision      = 'deny'
            reason        = 'SCOPE.md vazio'
            systemMessage = $msg
        } | ConvertTo-Json -Compress
        exit 0
    } else {
        exit 0
    }
}

# Extrai frontmatter (entre 2 primeiras linhas '---')
$frontmatterMatch = [regex]::Match($scopeContent, '(?s)^---\s*\r?\n(.*?)\r?\n---\s*\r?\n')
if (-not $frontmatterMatch.Success) {
    if ($mode -eq 'strict') {
        $msg = "[block-module-drift] $tool em '$pathFwd' BLOQUEADO. SCOPE.md em Modules/$moduleName/ sem frontmatter YAML valido (esperado entre delimitadores ---)."
        @{
            decision      = 'deny'
            reason        = 'SCOPE.md frontmatter invalido'
            systemMessage = $msg
        } | ConvertTo-Json -Compress
        exit 0
    } else {
        [Console]::Error.WriteLine("`n[drift-hook] AVISO: SCOPE.md em Modules/$moduleName/ sem frontmatter YAML valido -- nao foi possivel validar $controllerName.`n")
        exit 0
    }
}

$frontmatter = $frontmatterMatch.Groups[1].Value

# Extrai bloco 'contains:' (ate proxima chave top-level ou fim)
# Linha 'contains:' seguida de itens indentados ('  - ...' ou comentarios '  # ...')
$containsBlockMatch = [regex]::Match($frontmatter, '(?ms)^contains:\s*\r?\n((?:[ \t]+(?:#[^\r\n]*|-[^\r\n]+)\r?\n?)*)')
if (-not $containsBlockMatch.Success) {
    if ($mode -eq 'strict') {
        $msg = "[block-module-drift] $tool em '$pathFwd' BLOQUEADO. SCOPE.md em Modules/$moduleName/ sem bloco 'contains:' declarado. ADR 0080 Artigo 7 exige lista explicita."
        @{
            decision      = 'deny'
            reason        = 'SCOPE.md sem contains[] declarado'
            systemMessage = $msg
        } | ConvertTo-Json -Compress
        exit 0
    } else {
        [Console]::Error.WriteLine("`n[drift-hook] AVISO: Modules/$moduleName/SCOPE.md sem bloco 'contains:' -- drift de $controllerName nao pode ser validado.`n")
        exit 0
    }
}

$containsBlock = $containsBlockMatch.Groups[1].Value

# Extrai cada item '  - "..."' ou '  - ...' (sem aspas)
$items = @()
foreach ($line in $containsBlock -split "`n") {
    $trimmed = $line.Trim()
    if ($trimmed -match '^#') { continue }
    if ($trimmed -notmatch '^-\s+(.+)$') { continue }
    $item = $Matches[1].Trim()
    if ($item -match '^"(.*)"$' -or $item -match "^'(.*)'$") {
        $item = $Matches[1]
    }
    if ($item) { $items += $item }
}

# --- 6. Verifica se Controller esta declarado (substring match) ---
#
# Item pode ser: "ChatController", "ChatController -- UI chat principal",
# "Admin/CustosController -- dashboard custos LLM", "Api/MetaWebhookController -- ..."
# Match: o nome do Controller aparece como substring em qualquer item.

$declared = $false
foreach ($item in $items) {
    if ($item -match [regex]::Escape($controllerName)) {
        $declared = $true
        break
    }
}

if ($declared) { exit 0 }

# --- 7. DRIFT detectado -- modo determina acao ---

$enforcementDoc = 'memory/governance/ENFORCEMENT.md secao 2 #3'
$scopeRelPath = "Modules/$moduleName/SCOPE.md"

if ($mode -eq 'strict') {
    # STRICT: bloqueia via decision deny
    $msgStrict = "[block-module-drift] $tool em '$pathFwd' BLOQUEADO. Controller '$controllerName' NAO esta declarado em $scopeRelPath (campo contains[]). Constituicao v1.1.0 Artigo 7 + ADR 0080 + $enforcementDoc exigem declaracao. Caminhos validos: (a) ADICIONE '$controllerName' em contains[] do SCOPE.md se ele pertence ao Modules/$moduleName/; (b) MOVA o Controller pro modulo correto e atualize esse SCOPE.md; (c) override emergencial: defina env var OIMPRESSO_DRIFT_OVERRIDE=1 (registrar uso obrigatorio)."

    @{
        decision      = 'deny'
        reason        = "Controller fora de SCOPE.md.contains[] (drift L5 Module Charter)"
        systemMessage = $msgStrict
    } | ConvertTo-Json -Compress
    exit 0
}

# WARN (default): imprime no stderr e deixa passar
$warning = @"

[drift-hook] DRIFT DETECTADO -- Controller fora do SCOPE.md do modulo.

  Modulo:     Modules/$moduleName
  Controller: $controllerName
  Path:       $pathFwd
  SCOPE.md:   $scopeRelPath

Constituicao v1.1.0 Artigo 7 (Module Charter -- ADR 0080) + $enforcementDoc
exigem que todo Controller esteja declarado em contains[] do SCOPE.md do modulo
proprietario. Este Controller NAO esta declarado.

Caminhos validos:
  (a) DECLARE: adicione '$controllerName' em contains[] do $scopeRelPath
      (se ele realmente pertence ao Modules/$moduleName/).

  (b) MOVA: realoque o Controller pro modulo correto (Modules/<Y>/) e
      atualize contains[] daquele SCOPE.md.

  (c) Override emergencial (Wagner Tier 0):
      `$env:OIMPRESSO_DRIFT_OVERRIDE='1'  # PowerShell
      OIMPRESSO_DRIFT_OVERRIDE=1          # Bash/CI

Modo atual: WARN (4 semanas calibracao). Vira STRICT apos:
  `$env:OIMPRESSO_DRIFT_HOOK_MODE='strict'

Hook NAO bloqueia em modo warn -- Edit prossegue. Mas voce foi avisado.
"@

[Console]::Error.WriteLine($warning)
exit 0
