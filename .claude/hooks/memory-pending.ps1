#!/usr/bin/env pwsh
# Stop hook — detecta arquivos em memory/, MEMORY*.md, TASKS.md, CURRENT.md, *.SPEC.md
# que estão modificados/novos sem push e avisa o agente que precisa rodar /sync-mem
# antes de encerrar o turno.
#
# Por que: webhook GitHub → MCP só sincroniza após push. Esquecer de pushar = team
# (Eliana, Felipe, etc) não enxerga a mudança via tools MCP. Ver MANUAL_CLAUDE_CODE.md
# §5 (bug recorrente do push de assets — mesma classe de problema).

$ErrorActionPreference = 'SilentlyContinue'

# Status só dos paths canônicos de memória/governança
$tracked = git status --porcelain -- `
    'memory/' `
    'MEMORY.md' `
    'CURRENT.md' `
    'TASKS.md' `
    'TEAM.md' `
    'CLAUDE.md' `
    'DESIGN.md' `
    'INFRA.md' `
    'MANUAL_CLAUDE_CODE.md' 2>$null

if (-not $tracked) {
    # Nada pendente — sai silencioso
    exit 0
}

# Conta arquivos modificados/novos
$lines = $tracked -split "`n" | Where-Object { $_ -ne '' }
$count = $lines.Count

# Mensagem em stderr (Claude lê)
[Console]::Error.WriteLine("")
[Console]::Error.WriteLine("⚠️  $count arquivo(s) em memory/governança sem push:")
foreach ($line in $lines | Select-Object -First 10) {
    [Console]::Error.WriteLine("    $line")
}
if ($count -gt 10) {
    [Console]::Error.WriteLine("    ... +$($count - 10) outros")
}
[Console]::Error.WriteLine("")
[Console]::Error.WriteLine("→ Rode /sync-mem antes de encerrar pra propagar pro MCP server (team Eliana/Felipe enxergam via decisions-search).")
[Console]::Error.WriteLine("")

# exit 0 = não bloqueia, só avisa.
exit 0
