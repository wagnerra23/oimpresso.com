# Hook PreToolUse — BLOQUEIA comandos Bash destrutivos sem confirmação humana.
# US-COPI-085 (Cycle 01) — guardrails Bash em produção.
#
# Bloqueia 7 categorias:
#  1. rm -rf em paths críticos (não /tmp/)
#  2. git push --force / -f (em main/master/origin)
#  3. git reset --hard origin/* (sobrescreve trabalho remoto)
#  4. DROP TABLE / DROP DATABASE em SQL
#  5. DELETE FROM ... sem WHERE (ou WHERE 1=1)
#  6. composer update sem --lock (drift do composer.lock — ADR 0063)
#  7. php artisan migrate:fresh / migrate:reset em produção (Hostinger/CT 100 prod)
#  8. truncate / TRUNCATE TABLE
#
# Bypass legítimo: usar `--allow-destructive` no prompt original do user OU
# confirmar explicitamente. O hook só BLOQUEIA o reflexo do agente.

$ErrorActionPreference = 'Stop'
$rawInput = [Console]::In.ReadToEnd()

if (-not $rawInput) { exit 0 }

try {
    $payload = $rawInput | ConvertFrom-Json
} catch {
    exit 0
}

$tool = $payload.tool_name
if ($tool -ne 'Bash') { exit 0 }

$cmd = $payload.tool_input.command
if (-not $cmd) { exit 0 }

# Normaliza espaços múltiplos pra regex consistente
$cmdNorm = ($cmd -replace '\s+', ' ').Trim()

# Whitelist: caminhos seguros pra rm -rf
$whitelistRm = @(
    '^rm -rf /tmp/',
    '^rm -rf ~/\.cache/',
    '^rm -rf node_modules\b',
    '^rm -rf vendor\b',
    '^rm -rf storage/framework/(views|cache|sessions)/',
    '^rm -rf bootstrap/cache/',
    '^rm -rf public/build',
    '^rm -rf public/build-inertia',
    '^rm -rf \.next/',
    '^rm -rf dist/',
    '^rm -rf coverage/'
)

# Padrões PROIBIDOS (regex)
$padroes = @{
    'rm-rf-perigoso' = @{
        regex = '(^|[\s;&|])rm\s+-[rRf]+\s+'
        razao = 'rm -rf pode apagar trabalho não commitado / config / dados de prod'
        sugestao = 'use rm com path específico, ou whitelist: /tmp/, node_modules, vendor, storage/framework/{views,cache}, public/build*'
    }
    'git-force-push' = @{
        regex = 'git\s+push\s+(--force\b|-f\b|.*\s--force(-with-lease)?\b)'
        razao = 'force push sobrescreve histórico remoto — risco de perder commits do time'
        sugestao = 'rebase local + push normal, OU usar --force-with-lease com confirmação explícita do Wagner'
    }
    'git-reset-hard-origin' = @{
        regex = 'git\s+reset\s+--hard\s+(origin|upstream)/'
        razao = 'reset --hard contra remote descarta TODO trabalho local não-pushed'
        sugestao = 'git stash primeiro, depois reset; OU criar branch backup antes'
    }
    'sql-drop-table' = @{
        regex = '(?i)\bDROP\s+(TABLE|DATABASE|SCHEMA)\b'
        razao = 'DROP TABLE/DATABASE é irreversível — perde dados de produção'
        sugestao = 'rodar em staging primeiro, OU criar migration drop_*_table com plan mode + revisão Wagner'
    }
    'sql-delete-no-where' = @{
        regex = '(?i)\bDELETE\s+FROM\s+\w+(?!\s+WHERE\b)'
        razao = 'DELETE sem WHERE apaga TODA a tabela'
        sugestao = 'sempre adicionar WHERE explícito, mesmo que seja WHERE id IN (...)'
    }
    'sql-delete-where-1' = @{
        regex = '(?i)\bDELETE\s+FROM\s+\w+\s+WHERE\s+1(\s*=\s*1)?\b'
        razao = 'DELETE WHERE 1=1 = wipe da tabela inteira'
        sugestao = 'usar filtro real (WHERE id < N OR created_at < ...)'
    }
    'sql-truncate' = @{
        regex = '(?i)\bTRUNCATE\s+(TABLE\s+)?\w+'
        razao = 'TRUNCATE wipa a tabela inteira (mais rápido que DELETE, mesmo efeito)'
        sugestao = 'só em fixtures/seed locais; em prod usar migration formal'
    }
    'composer-update-sem-lock' = @{
        regex = '(?<!#\s)composer\s+update(?!\s+--lock\b)(?!.*\s--lock\b)'
        razao = 'composer update sem --lock causa drift do composer.lock (ADR 0063)'
        sugestao = 'composer update --lock (atualiza só o lock sem instalar) OU composer require pacote:versao'
    }
    'artisan-migrate-fresh-prod' = @{
        regex = 'php\s+artisan\s+migrate:(fresh|reset|wipe|rollback\s+--step=\d{2,})'
        razao = 'migrate:fresh/reset/wipe DROPA todas as tabelas — apaga produção'
        sugestao = 'usar migrate:rollback --step=1 com revisão; OU em prod, criar migration formal com down() controlado'
    }
}

$matched = $null
foreach ($key in $padroes.Keys) {
    $p = $padroes[$key]
    if ($cmdNorm -match $p.regex) {
        # Pra rm-rf, checa whitelist antes de bloquear
        if ($key -eq 'rm-rf-perigoso') {
            $whitelisted = $false
            foreach ($w in $whitelistRm) {
                if ($cmdNorm -match $w) { $whitelisted = $true; break }
            }
            if ($whitelisted) { continue }
        }
        $matched = @{ key = $key; data = $p }
        break
    }
}

if (-not $matched) { exit 0 }

$key = $matched.key
$razao = $matched.data.razao
$sugestao = $matched.data.sugestao

@{
    decision      = 'deny'
    reason        = "[$key] comando destrutivo bloqueado: $razao"
    systemMessage = "[block-destructive] Bash BLOQUEADO ($key). Motivo: $razao. Sugestão: $sugestao. Se for intencional e Wagner autorizou explicitamente, use abordagem alternativa OU peça Wagner pra rodar manualmente. NUNCA forçar bypass deste hook sem ADR justificando."
} | ConvertTo-Json -Compress

exit 0
