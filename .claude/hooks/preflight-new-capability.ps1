# Hook PreToolUse — AVISA ao CRIAR capability NOVA sem checar o que já existe.
#
# Causa raiz das reinvenções (sessão 2026-05-29): construí um DriftChecker/--check
# bespoke quando o framework ADR 0216 já existia (3ª reinvenção da sessão). Este hook
# dispara no MOMENTO EXATO da criação de um arquivo de capability nova e força o reflexo
# "saber o que existe antes" (skills como-integrar + mcp-first).
#
# Lê JSON stdin: { "tool_name": "Write", "tool_input": { "file_path": "...", "content": "..." } }
# Só dispara em Write de ARQUIVO NOVO (não existe) que casa padrão de capability, sob Modules/ ou app/.
# Advisory (permissionDecision=allow) — aparece no contexto na hora, não bloqueia.

$ErrorActionPreference = 'Stop'
$raw = [Console]::In.ReadToEnd()
try { $p = $raw | ConvertFrom-Json } catch { exit 0 }

if ($p.tool_name -ne 'Write') { exit 0 }
$path = [string]$p.tool_input.file_path
if (-not $path) { exit 0 }

# Padrão de capability -> framework/registry que JÁ existe (não reinventar)
$caps = [ordered]@{
    'Checker\.php$'    = "DriftChecker (ADR 0216): implemente Modules\Governance\Contracts\DriftChecker + registre em config('governance.drift_checkers'). NAO crie comando/cron/alerta bespoke."
    'Reconciler\.php$' = "Reconciler JA existe (ChannelsReconcilerCommand WhatsApp); governance:audit ja orquestra. NAO crie orquestrador novo."
    'Tool\.php$'       = "MCP Tools vivem em Modules\Jana\Mcp\Tools\ + registry OimpressoMcpServer. Veja as tools existentes."
    'Command\.php$'    = "rode decisions-search '<dominio>' + grep comando similar. Pode ja existir (ou ser DriftChecker)."
    'Service\.php$'    = "grep Service similar em Modules/**/Services antes de criar."
}

$reason = $null
foreach ($pat in $caps.Keys) {
    if ($path -match $pat -and ($path -match 'Modules[\\/]' -or $path -match 'app[\\/]')) {
        if (-not (Test-Path -LiteralPath $path)) { $reason = $caps[$pat] }  # só arquivo NOVO
        break
    }
}

if (-not $reason) { exit 0 }

@{
    hookSpecificOutput = @{
        hookEventName            = 'PreToolUse'
        permissionDecision       = 'allow'
        permissionDecisionReason = "[oimpresso-anti-reinvencao] Criando capability NOVA: $path. ANTES de codar, saiba o que JA existe -> $reason  (licao 2026-05-29: bespoke duplicou framework existente). Skills: como-integrar, mcp-first."
    }
} | ConvertTo-Json -Compress -Depth 3

exit 0
