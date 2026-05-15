# Hook PreToolUse — AVISO (não bloqueia) quando Claude tenta Edit/Write em
# Modules/<X>/ sem ter lido SPEC.md/RUNBOOK/charter do módulo X na sessão.
#
# Implementa FASE 1 PRÉ-FLIGHT da Regra Primária Tier 0 (proibicoes.md):
# "vai mexer no módulo, leia o briefing".
#
# Wagner 2026-05-15: "mexe não registra, altera sem ler as regras do modulo
# fica sempre errando, caramba se organiza caralho seja responsavel porra.
# vao entrar os outros no MCP e isso vai ficar uma zona caralho"
#
# Como funciona:
# 1. Detecta tool Edit/Write/MultiEdit em path `Modules/<X>/...`
# 2. Extrai nome do módulo <X>
# 3. Verifica se transcript da sessão atual contém Read/Glob/Grep de
#    memory/requisitos/<X>/ OR ADR contendo "<x>"
# 4. Se NÃO encontrou evidência de leitura → imprime warning no stderr
#    (Claude vê) mas NÃO bloqueia (exit 0)
#
# Por que NÃO bloqueia (em vez de block-mwart-violation que bloqueia):
# - Regra é cultural/comportamental, não fail-secure
# - Edit válido em config de teste OR fix urgente NÃO deveria parar
# - Sessões Wagner Tier 0 superadmin podem precisar mexer rápido
# - Bloquear quebra mais workflow que conserta
#
# Override: nenhum necessário (não bloqueia)

$ErrorActionPreference = 'Continue'

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

$pathFwd = $path.Replace('\', '/')

# Match Modules/<X>/... (exclui Tests, Database/Migrations sem código novo)
if ($pathFwd -notmatch 'Modules/([A-Z][A-Za-z0-9]*)/') { exit 0 }

$moduleName = $Matches[1]
$moduleLower = $moduleName.ToLower()

# Caminho do transcript da sessão atual (Claude Code grava jsonl)
$projectDir = $env:CLAUDE_PROJECT_DIR
if (-not $projectDir) { $projectDir = (Get-Location).Path }

# Procura últimos transcripts da sessão atual
$transcriptDir = Join-Path $env:USERPROFILE '.claude\projects'
$projectKey = ($projectDir -replace '[\\:]', '-').TrimEnd('-')
$sessionDir = Join-Path $transcriptDir $projectKey

if (-not (Test-Path $sessionDir)) { exit 0 }

# Pega o transcript mais recente (sessão atual)
$transcript = Get-ChildItem $sessionDir -Filter '*.jsonl' -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1

if (-not $transcript) { exit 0 }

# Verifica se Claude leu briefing do módulo nessa sessão
$content = Get-Content $transcript.FullName -Raw -ErrorAction SilentlyContinue
if (-not $content) { exit 0 }

# Patterns que indicam leitura de briefing do módulo:
# - Read em memory/requisitos/<X>/SPEC.md OR RUNBOOK*.md OR CAPTERRA*.md
# - Read em Modules/<X>/README.md
# - Read em *.charter.md relacionado
# - decisions-search/Read ADR com "<x>" no slug/title
# - skill como-integrar invocada com módulo
$readPatterns = @(
    "memory/requisitos/$moduleName/",
    "Modules/$moduleName/README",
    "$moduleLower.*charter",
    "$moduleLower.*spec",
    "decisions-search.*$moduleLower",
    "como-integrar.*$moduleLower"
)

$foundRead = $false
foreach ($pattern in $readPatterns) {
    if ($content -match $pattern) {
        $foundRead = $true
        break
    }
}

if ($foundRead) { exit 0 }

# Não achou evidência — imprime warning (Claude vê via stderr)
$warning = @"

⚠️  PRÉ-FLIGHT MISSING — Edit/Write em Modules/$moduleName/ sem ter lido briefing do módulo nesta sessão.

Regra primária Tier 0 (memory/proibicoes.md):
  FASE 1 PRÉ-FLIGHT obrigatória ANTES de qualquer Edit em Modules/<X>/

Leia ANTES de continuar:
  - memory/requisitos/$moduleName/SPEC.md (US-XXX-NNN do módulo)
  - memory/requisitos/$moduleName/RUNBOOK*.md (se MWART)
  - memory/requisitos/$moduleName/CAPTERRA*.md (se existir)
  - Charter da página (se Pages/$moduleName/*.charter.md)
  - decisions-search "$moduleLower" (ADRs aplicáveis)
  - skill como-integrar se feature parcialmente feita

Wagner 2026-05-15: "mexeu na merda do módulo registra caralho. (...) altera sem
ler as regras do modulo fica sempre errando, se organiza caralho seja responsavel"

Detalhe: memory/reference/feedback-modulo-mexeu-registra-sempre.md

(Hook é AVISO, não bloqueia — Edit prossegue, mas você foi informado.)
"@

[Console]::Error.WriteLine($warning)
exit 0

