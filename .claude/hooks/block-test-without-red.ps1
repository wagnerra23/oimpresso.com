# block-test-without-red.ps1 - PreToolUse (red-first com DENTES / SDD Semana 0 passo FV-T0)
#
# Barra a criacao de um TESTE NOVO (*Test.php) que nao traz evidencia de ter FALHADO
# vermelho antes. Complementa, do lado do TESTE e COM poder de bloqueio:
#   - warn-red-first.ps1            (advisory, lado da PRODUCAO: "escreveu codigo sem teste")
#   - nudge-test-contract-anchor.ps1 (advisory, "ancore a assercao num contrato, nao no codigo")
# Esta e a peca P1 da auditoria 2026-06-12 que faltava: warn-red-first faz exit 0 hardcoded
# (so sabe avisar); ESTE sabe exit 2 (bloqueia quando armado).
#
# POR QUE red-first do lado do teste: teste escrito DEPOIS do codigo e que nunca foi visto
# VERMELHO tende a copiar o comportamento atual (tautologico) -- trava o drift em vez de
# pega-lo (memory/proibicoes.md "Ideias avaliadas e DESCARTADAS" 2026-06-05). A evidencia
# de red prova que o teste DISCRIMINA certo de errado.
#
# Origem: plano-mae memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md
#         (Semana 0, frente FV, passo T0) + audit 2026-06-12 P1 item 5 (red-first hook bloqueador).
# Padrao: warn-red-first.ps1 (ASCII puro PS 5.1, fail-open) + block-claim-without-evidence.ps1 (.claude/run/ + escape valve).
#
# NASCE EM MODO WARN (default) -- NAO bloqueia. Gates novos nascem advisory (plano §3 correcoes
# 1 e 6; block-claim-without-evidence foi rebaixado a advisory por ADR 0224 com a regra canonica
# "hook bloqueia SO o deterministico-obrigatorio"). O MOTOR de bloqueio (exit 2) esta implementado
# e provado pelo .test.ps1; arma-lo = OIMPRESSO_REDFIRST_BLOCK_MODE=block.
#
# CRITERIO DE PROMOCAO A BLOCK (escrito ANTES da medicao -- espelha warn-red-first.ps1):
#   - 14 dias de warn-red-first.ps1 com falso-positivo <10% (a medicao ja esta rodando)
#   - PR + ADR propria + calendario de promocoes (max 1 promocao/semana -- plano §3 correcao 6)
#   - flip = setar OIMPRESSO_REDFIRST_BLOCK_MODE=block (1 env, VISIVEL -- nunca silencioso)
#
# EVIDENCIA DE RED (qualquer UMA destrava a criacao do teste novo):
#   1. cabecalho no proprio teste:  // red-first: rodei <cmd>, FALHOU com <erro> antes de implementar
#   2. arquivo .claude/run/red-evidence-*.txt modificado <60min (saida do run vermelho)
#   3. override legitimo no conteudo: red-first-override: <razao>
#        (characterization de legado, golden/snapshot, regressao pos-bug onde o RED foi o bug report)
#
# So morde TESTE NOVO (Write de *Test.php nao-rastreado no git). Edit de teste existente
# NAO re-exige red. Arquivo nao-teste nunca dispara. Fail-open em qualquer erro (exit 0).
#
# Env overrides (teste/tuning):
#   OIMPRESSO_REDFIRST_BLOCK_MODE   = warn (default) | block | off
#   OIMPRESSO_REDFIRST_REPO_ROOT    = raiz do repo git (default: 2 niveis acima deste script)
#   OIMPRESSO_REDFIRST_EVID_MINUTES = janela do red-evidence-*.txt em minutos (default 60)

$ErrorActionPreference = 'SilentlyContinue'
try {
    $mode = $env:OIMPRESSO_REDFIRST_BLOCK_MODE
    if (-not $mode) { $mode = 'warn' }
    if ($mode -eq 'off') { exit 0 }

    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $tool = [string]$payload.tool_name
    $path = [string]$payload.tool_input.file_path
    if (-not $path) { exit 0 }

    # So *Test.php (qualquer caminho)
    $norm = $path -replace '\\', '/'
    if ($norm -notmatch '(?i)Test\.php$') { exit 0 }

    # So a CRIACAO conta: Edit/MultiEdit operam sobre arquivo ja existente -> nao re-exige red.
    if ($tool -ne 'Write') { exit 0 }

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

    # Teste NOVO = nao rastreado no git. Se ja esta versionado, Write e overwrite de
    # arquivo existente -> nao re-exige red (mesma semantica de Edit).
    $tracked = & git -C $repoRoot ls-files --error-unmatch -- "$rel" 2>$null
    if ($LASTEXITCODE -eq 0 -and $tracked) { exit 0 }

    # Conteudo do Write (pra checar header de evidencia / override)
    $content = [string]$payload.tool_input.content

    # 3. Override legitimo (characterization/golden/regressao pos-bug)
    $ov = [regex]::Match($content, '(?im)red-first-override:\s*(\S.*)$')
    if ($ov.Success) {
        Write-Host "[RED-FIRST/block - override aceito] $rel"
        Write-Host ("  razao: " + $ov.Groups[1].Value.Trim())
        exit 0
    }

    # 1. Cabecalho de evidencia de red no proprio teste
    if ($content -match '(?im)red-first:\s*\S') { exit 0 }

    # 2. Arquivo de evidencia recente em .claude/run/
    $evidMin = 60
    if ($env:OIMPRESSO_REDFIRST_EVID_MINUTES -match '^\d+$') { $evidMin = [int]$env:OIMPRESSO_REDFIRST_EVID_MINUTES }
    $runDir = Join-Path $repoRoot '.claude/run'
    if (Test-Path $runDir) {
        $cutoff = (Get-Date).AddMinutes(-$evidMin)
        $recent = Get-ChildItem $runDir -Filter 'red-evidence-*.txt' -ErrorAction SilentlyContinue |
                  Where-Object { $_.LastWriteTime -gt $cutoff }
        if ($recent) { exit 0 }
    }

    # Sem evidencia -> montar mensagem
    $base = [System.IO.Path]::GetFileNameWithoutExtension($rel)
    $lines = @(
        "",
        "[RED-FIRST - teste novo sem evidencia de vermelho / SDD FV-T0]",
        "  Teste NOVO ($base em $rel) sem prova de ter FALHADO vermelho antes (red-first evita teste tautologico).",
        "  Satisfaca QUALQUER UMA:",
        "    1. cabecalho no $base : // red-first: rodei <cmd>, FALHOU com <erro> antes de implementar",
        "    2. .claude/run/red-evidence-*.txt salvo nos ultimos $evidMin min (saida do run vermelho)",
        "    3. caso legitimo (characterization/golden/regressao pos-bug): red-first-override: <razao>",
        "  Ref: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (FV-T0)."
    )

    if ($mode -eq 'block') {
        foreach ($l in $lines) { [Console]::Error.WriteLine($l) }
        [Console]::Error.WriteLine("  Modo BLOCK: criacao barrada (exit 2). Use override acima ou OIMPRESSO_REDFIRST_BLOCK_MODE=off.")
        exit 2
    }

    # modo warn (default): avisa, NAO barra
    foreach ($l in $lines) { Write-Host $l }
    Write-Host "  Modo WARN (default): aviso advisory - exit 0, nao bloqueia. Promocao a block via ADR + env (ver cabecalho)."
    Write-Host ""
    exit 0
} catch { exit 0 }
