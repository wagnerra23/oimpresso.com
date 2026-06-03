# block-test-fora-ct100.ps1 — PreToolUse:Bash|PowerShell
# Enforcement do feedback Wagner 2026-06-01 (testes/PHPStan rodam no CT 100,
# NUNCA na maquina local / Hostinger): bloqueia execucao de Pest / PHPStan /
# PHPUnit / 'php artisan test' fora do CT 100. Lugar correto: container
# oimpresso-staging (CT 100 — tem CPU/RAM + stack completo + DB sqlite isolado).
#
# Wagner textual: "os testes nao devem ser feito local, as maquinas nao
# suportariam faca no ct 100 obrigatoriamente la tem recursos para isso.
# e o lugar correto anote na memoria para nao errar denovo."
# Ref: memory/reference/feedback-testes-no-ct100-nao-local.md + ADR 0062.
#
# Fail-open: qualquer erro/parse-fail -> exit 0 (NUNCA trava sessao).
# Escape valve: incluir 'test-local-override' no comando (Wagner aprovou emergencia).

$ErrorActionPreference = 'SilentlyContinue'
try {
    $raw = [Console]::In.ReadToEnd()
    if (-not $raw) { exit 0 }
    $payload = $raw | ConvertFrom-Json
    $cmd = [string]$payload.tool_input.command
    if (-not $cmd) { exit 0 }

    # Escape valve explicito (Wagner aprovou rodar local nesta vez)
    if ($cmd -match 'test-local-override') { exit 0 }

    # E uma EXECUCAO de teste/analise estatica (runner), nao leitura de arquivo?
    $isRunner =
        ($cmd -match '(?<![\w.-])php\s+artisan\s+test(\s|$|")') -or
        ($cmd -match 'vendor[/\\]bin[/\\](phpstan|pest|phpunit)(\.phar)?(\s|$|")') -or
        ($cmd -match '(?<![\w/\\.-])(phpstan|pest|phpunit)\s+(analyse|analyze|--|tests?\b)') -or
        ($cmd -match 'composer\s+(run\s+)?(test|pest|phpstan|phpunit|larastan)(\s|$|@|")')
    if (-not $isRunner) { exit 0 }

    # Ja aponta pro CT 100 (tailscale ssh / docker exec staging / ssh CT100)? -> liberado
    $isCt100 =
        ($cmd -match 'tailscale\s+ssh') -or
        ($cmd -match 'docker\s+exec\s+\S*oimpresso-(staging|mcp)') -or
        ($cmd -match 'ssh\s+root@(100\.99\.207\.66|ct100)') -or
        ($cmd -match 'ct100-mcp')
    if ($isCt100) { exit 0 }

    # Local (Herd/Windows) ou Hostinger -> BLOQUEIA
    Write-Error @"
[FEEDBACK 2026-06-01 / ADR 0062] BLOQUEADO: teste/PHPStan na maquina LOCAL.

Wagner (textual): "os testes nao devem ser feito local, as maquinas nao
suportariam, faca no ct 100 obrigatoriamente la tem recursos para isso."

RODE NO CT 100 (container oimpresso-staging, DB sqlite :memory: isolado):

  # Pest (filtro ou arquivo):
  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=NomeDoTeste"

  # PHPStan (analise estatica):
  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging vendor/bin/phpstan analyse <path> --memory-limit=1G --no-progress"

  # Levar codigo de um branch/PR pro staging antes (se preciso):
  tailscale ssh root@ct100-mcp "cd /opt/oimpresso-staging/code && git fetch origin && git checkout <branch> && git reset --hard origin/<branch>"

Por que: a workstation/Herd NAO aguenta a suite (3000+ testes); o CT 100 tem
CPU/RAM + stack completo (OTel SDK, larastan em require-dev). CI GitHub continua
sendo o gate de merge.

Ref: memory/reference/feedback-testes-no-ct100-nao-local.md
Escape (so se Wagner aprovou explicito): inclua 'test-local-override' no comando.
"@
    exit 2
} catch {
    exit 0
}
