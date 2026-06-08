# Hook PreToolUse — BLOQUEIA Edit/Write em Pages/<Mod>/<Tela>.tsx sem RUNBOOK existir.
# Camada 2 de enforcement do processo MWART canônico (ADR 0104 § Enforcement).
#
# Wagner 2026-05-08: "Falhas não são aceitáveis. Não pode ter 2 caminhos de desenvolvimento."
# Garante que F1 PLAN (RUNBOOK + SPEC) acontece ANTES de F3 FRONTEND (codar Page Inertia).
#
# Match: resources/js/Pages/<Mod>/<Tela>.tsx — exige RUNBOOK em memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md
#
# Override autorizado (sai do bloqueio): comentar '/mwart-override <razão>' em PR.
#   O CI workflow .github/workflows/mwart-gate.yml registra exceção em ADR per-tela.
#
# Exempções (não dispara hook):
#   - Pages/<Mod>/_components/* (privado do módulo, não é tela)
#   - Pages/<Mod>/<Sub>/_*.tsx (helpers internos)
#   - Pages/_Showcase/* (componente preview)

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

# Normalizar pra forward slashes
$pathFwd = $path.Replace('\', '/')

# Match Pages/<Mod>/<Tela>.tsx (top-level OU 1 nível de subdir tipo Pages/<Mod>/<Sub>/<Tela>.tsx)
# Captura o módulo (1ª pasta após Pages/) e a tela (último arquivo .tsx)
$regex = 'resources/js/Pages/([^/_][^/]*)/(?:[^/]+/)?([A-Za-z][A-Za-z0-9]*)\.tsx$'
if ($pathFwd -notmatch $regex) { exit 0 }

$modulo = $matches[1]
$tela = $matches[2]

# Exempções por nome do módulo (helpers gerais, não telas migráveis)
if ($modulo -in @('_Showcase', '_components', '_internal')) { exit 0 }

# Exempções por nome de tela (helpers internos)
if ($tela -match '^_' -or $tela -in @('App', 'Layout')) { exit 0 }

# Converter PascalCase pra kebab-case (ex: NfceStatus → nfce-status)
$telaKebab = ($tela -creplace '([a-z0-9])([A-Z])', '$1-$2').ToLower()

# Pasta de requisitos esperada (case-insensitive)
$requisitosBase = "memory/requisitos"
$modPasta = $null

# Tenta achar a pasta do módulo (case-insensitive — Sells, sells, SELLS)
if (Test-Path $requisitosBase) {
    $modCandidato = Get-ChildItem $requisitosBase -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ieq $modulo }
    if ($modCandidato) { $modPasta = $modCandidato.FullName }
}

if (-not $modPasta) {
    # Pasta do módulo nem existe — F1 nunca aconteceu
    @{
        decision      = 'deny'
        reason        = 'MWART F1 incompleta — pasta de requisitos do módulo não existe'
        systemMessage = "[mwart-process] $tool em '$pathFwd' BLOQUEADO. ADR 0104 §F1 PLAN exige RUNBOOK em 'memory/requisitos/$modulo/RUNBOOK-$telaKebab.md'. A pasta 'memory/requisitos/$modulo/' nem existe — F1 (PLAN) nunca rolou. Antes de codar a Page Inertia, rode '/cockpit-runbook /<rota>' pra gerar RUNBOOK + SPEC. Override: comentar '/mwart-override <razão>' em PR (vira ADR per-tela)."
    } | ConvertTo-Json -Compress
    exit 0
}

# Procurar RUNBOOK-<tela-kebab>.md (case-insensitive — Windows é case-blind, mas Linux/Mac CI não)
$runbookEsperado = Join-Path $modPasta "RUNBOOK-$telaKebab.md"
$existe = Test-Path $runbookEsperado

if (-not $existe) {
    # Tenta variantes case-insensitive no nome do arquivo
    $matched = Get-ChildItem $modPasta -Filter "RUNBOOK-*.md" -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ieq "RUNBOOK-$telaKebab.md" }
    if ($matched) { $existe = $true }
}

if (-not $existe) {
    @{
        decision      = 'deny'
        reason        = 'MWART F1 incompleta — RUNBOOK ausente'
        systemMessage = "[mwart-process] $tool em '$pathFwd' BLOQUEADO. ADR 0104 §F1 PLAN exige RUNBOOK 'memory/requisitos/$modulo/RUNBOOK-$telaKebab.md' antes de F3 FRONTEND (codar Page). Rode '/cockpit-runbook /<rota>' pra gerar (~12min com IA-pair). Override: comentar '/mwart-override <razão>' em PR (vira ADR per-tela)."
    } | ConvertTo-Json -Compress
    exit 0
}

# RUNBOOK existe — processo OK, pode prosseguir
exit 0

