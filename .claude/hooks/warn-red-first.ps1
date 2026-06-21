# warn-red-first.ps1 - PreToolUse (advisory - red-first WARN / SDD Semana 0 passo FV-T0)
# Avisa quando Edit/Write toca arquivo de PRODUCAO (app/**, Modules/**/{Services,Entities,Http}/**)
# sem nenhum teste (*Test.php) tocado/criado na sessao recente (uncommitted via git status
# OU commitado na janela recente via git log). Ensina: escreva o teste que FALHA primeiro
# (red), depois o codigo que o faz passar (green), depois refatore.
#
# Origem: plano-mae memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md
#         (Semana 0, frente FV, passo T0) + audit 2026-06-12 P1 item 5 (red-first hook).
# Padrao: segue nudge-test-contract-anchor.ps1 (advisory, fail-open, ASCII puro PS 5.1).
#
# ADVISORY DE NASCENCA: exit 0 SEMPRE, nunca bloqueia nesta fase (gates novos nascem advisory).
#
# CRITERIO DE PROMOCAO A BLOQUEADOR (escrito ANTES da medicao, anti promotion-fatigue):
#   - Taxa de falso-positivo <10% medida em 14 dias de uso real.
#     Falso-positivo = warn disparado em edicao que NAO precisava de teste novo
#     (ex.: rename mecanico, fix de typo em string, codemod, hotfix com teste ja verde).
#   - Promocao = PR + ADR propria + calendario de promocoes (max 1 promocao/semana,
#     plano-mae secao 3 correcao 6). NUNCA flip silencioso.
#   - Fase WARN deliberadamente LENIENTE: QUALQUER *Test.php tocado na sessao conta
#     (nao exige nome correspondente). Estreitar pra correspondencia por nome e
#     decisao da fase de promocao, com dados dos 14 dias.
#
# Env overrides (teste/tuning):
#   OIMPRESSO_REDFIRST_MODE         = warn (default) | off
#   OIMPRESSO_REDFIRST_REPO_ROOT    = raiz do repo git (default: 2 niveis acima deste script)
#   OIMPRESSO_REDFIRST_WINDOW_HOURS = janela "sessao recente" pra commits (default 4)

$ErrorActionPreference = 'SilentlyContinue'
try {
    if ($env:OIMPRESSO_REDFIRST_MODE -eq 'off') { exit 0 }

    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $path = [string]$payload.tool_input.file_path
    if (-not $path) { exit 0 }

    # Normaliza separadores
    $norm = $path -replace '\\', '/'

    # Raiz do repo (override pra teste isolado em repo temporario)
    $repoRoot = $env:OIMPRESSO_REDFIRST_REPO_ROOT
    if (-not $repoRoot) {
        $hooksDir = Split-Path $MyInvocation.MyCommand.Path -Parent
        $repoRoot = Split-Path (Split-Path $hooksDir -Parent) -Parent
    }
    $rootNorm = ($repoRoot -replace '\\', '/').TrimEnd('/')

    # Caminho relativo a raiz (se absoluto dentro do repo)
    $rel = $norm
    if ($rel.StartsWith($rootNorm + '/', [System.StringComparison]::OrdinalIgnoreCase)) {
        $rel = $rel.Substring($rootNorm.Length + 1)
    }

    # Exclusoes: teste e markdown NUNCA disparam
    if ($rel -match '(?i)Test\.php$') { exit 0 }
    if ($rel -match '(?i)\.md$') { exit 0 }

    # So arquivo de PRODUCAO: app/** ou Modules/<Mod>/(Services|Entities|Http)/** (.php)
    $isProd = ($rel -match '(?i)^app/.+\.php$') -or
              ($rel -match '(?i)^Modules/[^/]+/(Services|Entities|Http)/.+\.php$')
    if (-not $isProd) { exit 0 }

    # Janela da sessao recente (commits)
    $hours = 4
    if ($env:OIMPRESSO_REDFIRST_WINDOW_HOURS -match '^\d+$') {
        $hours = [int]$env:OIMPRESSO_REDFIRST_WINDOW_HOURS
    }

    # 1) Teste tocado e ainda nao commitado (git status: modified/added/untracked)
    # -uall: sem ele, dir untracked novo aparece como "?? tests/" e o *Test.php dentro some
    $touched = @()
    $statusOut = & git -C $repoRoot status --porcelain -uall 2>$null
    if ($statusOut) {
        $touched += @($statusOut | Where-Object { $_ -match '(?i)Test\.php$' })
    }

    # 2) Teste commitado dentro da janela recente
    if ($touched.Count -eq 0) {
        $logOut = & git -C $repoRoot log --since="$hours hours ago" --name-only --pretty=format: 2>$null
        if ($logOut) {
            $touched += @($logOut | Where-Object { $_ -match '(?i)Test\.php$' })
        }
    }

    # Red-first cumprido nesta fase: algum teste foi tocado na sessao recente
    if ($touched.Count -gt 0) { exit 0 }

    $base = [System.IO.Path]::GetFileNameWithoutExtension($rel)

    Write-Host ""
    Write-Host "[RED-FIRST - advisory / SDD FV-T0]"
    Write-Host "  Voce vai editar codigo de PRODUCAO ($rel) sem nenhum teste tocado nesta sessao."
    Write-Host "  Escreva o teste que FALHA primeiro: crie/ajuste ${base}Test.php, rode, veja"
    Write-Host "  VERMELHO, e so entao escreva o codigo que o faz passar (red -> green -> refactor)."
    Write-Host "  Teste escrito DEPOIS do codigo tende a copiar o comportamento atual (tautologico)."
    Write-Host "  Aviso advisory - exit 0, nao bloqueia nada nesta fase."
    Write-Host "  Ref: plano SDD memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (FV-T0)."
    Write-Host ""
    exit 0
} catch { exit 0 }
