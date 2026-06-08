#!/usr/bin/env pwsh
# SessionStart hook — detecta skills modificadas/novas em .claude/skills/
# desde o último start deste dev. Avisa o agente que precisa rodar /sync-skills.
#
# Por que: Claude Code só carrega skills no startup. Se outro dev mergeou skill
# nova entre dois inícios de sessão, este dev fica desatualizado sem saber.
# Hook detecta drift sem custo perceptível e sinaliza.
#
# Estado salvo em .claude/.last-skills-sync (gitignored). Cada dev tem o seu.

$ErrorActionPreference = 'SilentlyContinue'

$lastSyncFile = '.claude/.last-skills-sync'
$skillsDir    = '.claude/skills'

if (-not (Test-Path $skillsDir)) {
    exit 0  # projeto sem skills, sai silencioso
}

# Primeira execução — só registra timestamp atual e sai sem alertar
if (-not (Test-Path $lastSyncFile)) {
    Get-Date -Format 'yyyy-MM-ddTHH:mm:ss' | Out-File -FilePath $lastSyncFile -Encoding utf8 -NoNewline
    exit 0
}

$lastSync = (Get-Content $lastSyncFile -Raw -ErrorAction SilentlyContinue).Trim()
if (-not $lastSync) {
    Get-Date -Format 'yyyy-MM-ddTHH:mm:ss' | Out-File -FilePath $lastSyncFile -Encoding utf8 -NoNewline
    exit 0
}

# Lista SKILL.md tocados por commits desde o último sync
$tocados = git log --since="$lastSync" --name-only --diff-filter=AMR --pretty=format: -- '.claude/skills/*/SKILL.md' 2>$null `
    | Where-Object { $_ -and $_.Trim() -ne '' } `
    | Sort-Object -Unique

if (-not $tocados) {
    exit 0  # nada mudou desde último start, sai silencioso
}

$count = ($tocados | Measure-Object).Count

# Extrai slugs únicos (.claude/skills/<slug>/SKILL.md → <slug>)
$slugs = $tocados | ForEach-Object {
    if ($_ -match '\.claude/skills/([^/]+)/SKILL\.md') {
        $Matches[1]
    }
} | Sort-Object -Unique

[Console]::Error.WriteLine("")
[Console]::Error.WriteLine("[skills] $count skill(s) modificada(s) desde sua ultima sessao:")
foreach ($slug in $slugs | Select-Object -First 8) {
    [Console]::Error.WriteLine("    - $slug")
}
if ($slugs.Count -gt 8) {
    [Console]::Error.WriteLine("    ... +$($slugs.Count - 8) outras")
}
[Console]::Error.WriteLine("")
[Console]::Error.WriteLine("-> Rode /sync-skills pra ver o que mudou e ler conteudo novo.")
[Console]::Error.WriteLine("-> Skills NOVAS exigem reiniciar Claude Code pra ativar matching automatico.")
[Console]::Error.WriteLine("")

# NÃO atualiza .last-skills-sync aqui — /sync-skills atualiza após o dev ler.
# Se atualizasse no hook, o aviso some no próximo start sem o dev ter visto o conteúdo.

exit 0  # nunca bloqueia
