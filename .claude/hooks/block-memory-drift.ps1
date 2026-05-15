# Hook PreToolUse — BLOQUEIA edits em canon paths sem branch claude/* + workflow PR.
#
# Vetor de risco fechado: time MCP entra (Felipe/Maiara/Luiz/Eliana) com Claude Code +
# token MCP cada um. Sem este hook, qualquer um pode editar memory/decisions/0094-*.md
# inline em branch main e o canon vira mentira pro MCP server. Maratona WhatsApp 14-15/mai
# catalogou 5 drifts de origem PR-less; este hook fecha o vetor "doc canônico".
#
# Constituição v2 (ADR 0094) Artigo 3 + ADR 0061/0094/0130 + proibições "ADRs CANON
# são append-only" — formalizado como check automático aqui.
#
# Regras (em ordem):
#   A) Branch main/master + edit canon → BLOCK sempre. Workflow: branch claude/* + PR.
#   B) Edit em ADR existente (memory/decisions/NNNN-*.md NNNN já usado) → BLOCK sempre.
#      ADRs accepted são append-only IRREVOGÁVEIS. Crie nova ADR com supersedes: [NNNN].
#   C) Edit em handoff existente → BLOCK sempre (ADR 0130 append-only).
#      Crie novo handoff em memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md
#   D) Write criando ADR nova (NNNN único nunca usado) em claude/* → ALLOW
#   E) Write criando handoff novo (data-slug não existe) → ALLOW
#   F) Edit em outros canon (governance/*, proibicoes.md, regras-time.md, what-/why-/how-)
#      em branch != claude/* → BLOCK (workflow PR).
#   G) Edit em memory/governance/CONSTITUTION.md em qualquer branch → BLOCK
#      (supremo, só Wagner via ADR + version bump).
#
# Override emergencial (Wagner Tier 0): env $env:OIMPRESSO_MEMORY_OVERRIDE=1
#   → pula check + imprime warning loud no stderr "OVERRIDE ATIVO — abrir PR follow-up".
#
# Convenção paths canon (atualizar AQUI se ADR nova mudar):
#   memory/decisions/NNNN-*.md           (ADR Nygard, append-only)
#   memory/handoffs/YYYY-MM-DD-*.md      (handoff append-only, ADR 0130)
#   memory/governance/CONSTITUTION.md    (supremo, ADR 0094)
#   memory/governance/TRUST-TIERS.md     (canon)
#   memory/governance/ENFORCEMENT.md     (canon)
#   memory/governance/ARCHITECTURE.md    (canon)
#   memory/governance/srs/*.md           (append-only, futuro)
#   memory/proibicoes.md                 (Tier 0)
#   memory/regras-time.md                (canon)
#   memory/what-oimpresso.md             (canon)
#   memory/why-oimpresso.md              (canon)
#   memory/how-trabalhar.md              (canon)
#
# Paths editáveis NÃO bloqueados (out of scope):
#   memory/decisions/proposals/**        (ADRs em rascunho — Wagner ativa promovendo)
#   memory/sessions/**                   (append-only por convenção mas hoje sem hook)
#   memory/reference/**                  (canon mas editável — ADR 0061 migração)
#   memory/requisitos/**                 (SPECs/RUNBOOKs vivos por módulo)

$ErrorActionPreference = 'Stop'

# Lê stdin (Claude Code padrão: JSON via stdin)
$payloadJson = [Console]::In.ReadToEnd()

if (-not $payloadJson) { exit 0 }

try {
    $payload = $payloadJson | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
if ($tool -notin @('Write', 'Edit', 'MultiEdit')) { exit 0 }

$path = $payload.tool_input.file_path
if (-not $path) { exit 0 }

# Override emergencial Wagner Tier 0
if ($env:OIMPRESSO_MEMORY_OVERRIDE -eq '1') {
    [Console]::Error.WriteLine("[block-memory-drift] OVERRIDE ATIVO (OIMPRESSO_MEMORY_OVERRIDE=1).")
    [Console]::Error.WriteLine("[block-memory-drift] Edit em '$path' liberado sob responsabilidade Wagner Tier 0.")
    [Console]::Error.WriteLine("[block-memory-drift] PR follow-up imediato OBRIGATORIO. Constituicao v2 Art. 3.")
    exit 0
}

# Normaliza path (forward slash + lowercase) e tira prefixo absoluto
$pathFwd = $path.Replace('\', '/')
$pathLower = $pathFwd.ToLower()

# Pega só o sufixo a partir de "memory/" se houver
$memoryIdx = $pathLower.IndexOf('memory/')
if ($memoryIdx -lt 0) { exit 0 }  # path fora de memory/ — out of scope

$relPath = $pathLower.Substring($memoryIdx)  # ex: memory/decisions/0094-foo.md
$relPathOriginalCase = $pathFwd.Substring($pathFwd.ToLower().IndexOf('memory/'))

# Classifica path canon
$isAdrPath = $relPath -match '^memory/decisions/(\d{4})-[a-z0-9\-]+\.md$'
$isAdrProposal = $relPath -match '^memory/decisions/proposals/'
$isHandoff = $relPath -match '^memory/handoffs/(\d{4}-\d{2}-\d{2}[a-z0-9\-]*)\.md$'
$isConstitution = $relPath -match '^memory/governance/constitution\.md$'
$isGovernanceCanon = $relPath -match '^memory/governance/(trust-tiers|enforcement|architecture|identity-mesh)\.md$'
$isGovernanceSrs = $relPath -match '^memory/governance/srs/'
$isRootCanon = $relPath -match '^memory/(proibicoes|regras-time|what-oimpresso|why-oimpresso|how-trabalhar)\.md$'

# ADR proposals são editáveis (rascunho até promoção)
if ($isAdrProposal) { exit 0 }

# Path fora dos canon protegidos → out of scope
$isCanonPath = $isAdrPath -or $isHandoff -or $isConstitution -or $isGovernanceCanon -or $isGovernanceSrs -or $isRootCanon
if (-not $isCanonPath) { exit 0 }

# Detecta branch ativa via git
$branch = ''
try {
    $branch = (& git rev-parse --abbrev-ref HEAD 2>$null).Trim()
} catch {
    $branch = ''
}

# Helper: emite block JSON + sai
function Deny-Edit {
    param([string]$Reason, [string]$Msg)
    @{
        decision      = 'deny'
        reason        = $Reason
        systemMessage = $Msg
    } | ConvertTo-Json -Compress
    exit 0
}

# Helper: arquivo existe? (resolve case-insensitive para Windows)
function Test-CanonFile {
    param([string]$RelPathFwd)
    # tenta achar a raiz do repo (parent do .claude/)
    $repoRoot = $PSScriptRoot
    while ($repoRoot -and -not (Test-Path (Join-Path $repoRoot '.git'))) {
        $parent = Split-Path $repoRoot -Parent
        if (-not $parent -or $parent -eq $repoRoot) { break }
        $repoRoot = $parent
    }
    # worktrees têm .git como FILE, não DIR — Test-Path com -PathType Container falharia.
    # Test-Path -Path '.git' aceita ambos. Continuamos a subir até achar memory/.
    while ($repoRoot -and -not (Test-Path (Join-Path $repoRoot 'memory'))) {
        $parent = Split-Path $repoRoot -Parent
        if (-not $parent -or $parent -eq $repoRoot) { break }
        $repoRoot = $parent
    }
    if (-not $repoRoot) { return $false }
    $full = Join-Path $repoRoot $RelPathFwd.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
    return Test-Path $full -PathType Leaf
}

# Regra G — Constitution.md em qualquer branch → BLOCK
if ($isConstitution) {
    Deny-Edit -Reason 'CONSTITUTION.md eh supremo (Constituicao v2 Art. 3)' -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

REGRA: memory/governance/CONSTITUTION.md eh o documento SUPREMO da Constituicao v2
(ADR 0094). Mudar inline = bug de governanca grave.

Caminho correto:
  1. Abrir ADR Nygard nova em memory/decisions/NNNN-emendation-constitution-v2-X.md
  2. PR + Wagner aprova
  3. Bump de versao na propria CONSTITUTION.md (ex: 1.1.0 -> 1.2.0) acontece NO MESMO PR
  4. Merge atualiza ambas no main em 1 commit atomico

Override emergencial Wagner Tier 0:
  `$env:OIMPRESSO_MEMORY_OVERRIDE='1'` (PowerShell) antes do Edit. PR follow-up obrigatorio.
"@
}

# Regra B — ADR existente em qualquer branch → BLOCK (append-only IRREVOGÁVEL)
if ($isAdrPath -and (Test-CanonFile -RelPathFwd $relPath)) {
    $nnnn = $matches[1]
    Deny-Edit -Reason 'ADRs CANON sao append-only (Constituicao Art. 3 + ADR 0094)' -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

REGRA: ADRs CANON sao APPEND-ONLY IRREVOGAVEIS (Constituicao v2 Art. 3, ADR 0094).
NUNCA editar ADR aceita inline — mesmo correcoes de typo viram nova ADR.

Caminho correto:
  1. Criar nova ADR: memory/decisions/<proxNNNN>-<slug>.md
  2. Frontmatter incluir 'supersedes: [$nnnn]' apontando pra ADR atual
  3. Texto explica o que mudou e por que
  4. PR + Wagner aprova + merge
  5. ADR antiga ($nnnn) ganha status historical ou mantida (depende lifecycle ADR 0095)

Se for so ajuste editorial sem mudanca semantica (ex: link quebrado), abrir PR e Wagner
decide se cabe append-only emendation patch ou nova ADR.

Override emergencial Wagner Tier 0:
  `$env:OIMPRESSO_MEMORY_OVERRIDE='1'` antes do Edit. PR follow-up obrigatorio.
"@
}

# Regra D — Write criando ADR nova (NNNN unico) em claude/* → ALLOW silencioso
# (cai aqui se isAdrPath=true MAS Test-CanonFile=false — arquivo nao existe ainda)
if ($isAdrPath -and -not (Test-CanonFile -RelPathFwd $relPath)) {
    # ADR nova: exige branch claude/*
    if ($branch -notmatch '^claude/') {
        Deny-Edit -Reason 'ADR nova precisa de branch claude/*' -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

Voce esta tentando criar uma ADR nova ($relPathOriginalCase) na branch '$branch'.
Convencao: toda mudanca canonica vai por PR a partir de branch claude/<slug>.

Caminho correto:
  1. git checkout -b claude/<slug-descritivo>
  2. Criar ADR
  3. PR + Wagner aprova + merge
"@
    }
    exit 0  # branch claude/* + ADR nova OK
}

# Regra C — Handoff existente em qualquer branch → BLOCK (append-only ADR 0130)
if ($isHandoff -and (Test-CanonFile -RelPathFwd $relPath)) {
    Deny-Edit -Reason 'Handoffs sao append-only (ADR 0130)' -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

REGRA: Handoffs sao APPEND-ONLY (ADR 0130). Cada handoff registra estado num momento;
mudar handoff antigo apaga historico de transicao.

Caminho correto:
  1. Criar handoff NOVO em memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md
  2. Snapshot 'Estado MCP no momento do fechamento' obrigatorio (ADR 0130)
  3. Atualizar indice memory/08-handoff.md adicionando 1 linha no topo (truncar 5o)

Override emergencial Wagner Tier 0:
  `$env:OIMPRESSO_MEMORY_OVERRIDE='1'` antes do Edit. PR follow-up obrigatorio.
"@
}

# Regra E — Write criando handoff novo → ALLOW (qualquer branch, pq handoff documenta sessao)
if ($isHandoff -and -not (Test-CanonFile -RelPathFwd $relPath)) {
    exit 0  # handoff novo OK em qualquer branch
}

# Regra F + A — outros canon (governance, proibicoes, regras-time, what/why/how) →
# BLOCK se branch != claude/*
if ($isGovernanceCanon -or $isGovernanceSrs -or $isRootCanon) {
    if ($branch -in @('main', 'master')) {
        Deny-Edit -Reason "Edit em canon path em branch '$branch' eh proibido" -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

REGRA: Canon paths nao se editam direto em 'main'/'master'. Toda mudanca canon vai por PR.

Caminho correto:
  1. git checkout -b claude/<slug-descritivo>
  2. Editar o canon
  3. git add + commit + push
  4. gh pr create + Wagner aprova + merge

Por que: time MCP (Felipe/Maiara/Luiz/Eliana) vai entrar; sem PR review, drift de canon
servido pelo MCP server fica indetectavel.

Override emergencial Wagner Tier 0:
  `$env:OIMPRESSO_MEMORY_OVERRIDE='1'` antes do Edit. PR follow-up obrigatorio.
"@
    }

    if ($branch -notmatch '^claude/') {
        Deny-Edit -Reason "Edit em canon path exige branch claude/*" -Msg @"
[block-memory-drift] $tool em '$relPathOriginalCase' BLOQUEADO.

Branch ativa: '$branch'. Canon paths editaveis SO em branch 'claude/<slug>'.

Caminho correto:
  1. git stash (ou commit do que ja tem)
  2. git checkout -b claude/<slug-descritivo> origin/main
  3. Reaplicar mudancas
  4. PR + Wagner aprova + merge

Override emergencial Wagner Tier 0:
  `$env:OIMPRESSO_MEMORY_OVERRIDE='1'` antes do Edit. PR follow-up obrigatorio.
"@
    }

    # branch claude/* + canon path → ALLOW (vai pra PR)
    exit 0
}

# Safety net — qualquer path canon nao classificado acima permite (default open)
exit 0
