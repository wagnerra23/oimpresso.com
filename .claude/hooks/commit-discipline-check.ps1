# commit-discipline check — Skill Tier A enforcement
# Disparado em PreToolUse Bash quando comando é git commit/add
# Ver .claude/skills/commit-discipline/SKILL.md + ADR 0094 §5

$ErrorActionPreference = 'SilentlyContinue'

# Ler input via stdin (Claude Code envia o tool call em JSON)
$input = [Console]::In.ReadToEnd()

# Extrair campo command do JSON
if ($input -notmatch '"command"\s*:\s*"([^"]+)"') {
    exit 0  # não é comando bash com command — ignora
}
$command = $Matches[1]

# Só ativa pra git commit/add — ignora outros bash commands
if ($command -notmatch '^\s*git\s+(commit|add|push)\b') {
    exit 0
}

# Para git push, alerta sobre force/main
if ($command -match 'git\s+push.*--force\b' -and $command -notmatch '--force-with-lease') {
    Write-Host ""
    Write-Host "[commit-discipline] AVISO: force push detectado SEM --force-with-lease."
    Write-Host "  Best-practice: use --force-with-lease pra evitar sobrescrever trabalho de outros."
    Write-Host ""
}

# Pra git commit, validar diff staged
if ($command -match '^\s*git\s+commit\b') {
    # Conta linhas no diff staged
    $diffStat = git diff --cached --shortstat 2>$null
    if ($diffStat -match '(\d+) insertion') {
        $lines = [int]$Matches[1]
        if ($lines -gt 300) {
            Write-Host ""
            Write-Host "[commit-discipline] AVISO: diff staged tem $lines linhas (alvo ≤300)."
            Write-Host "  Considere dividir em PRs menores. Se for refactor amplo justificado, ok seguir."
            Write-Host ""
        }
    }

    # Detecta PII no diff staged (CPF/CNPJ/email/tel — não bloqueia, só alerta)
    $diffPii = git diff --cached 2>$null | Out-String
    if ($diffPii -match '\b\d{3}\.\d{3}\.\d{3}-\d{2}\b' -or
        $diffPii -match '\b\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}\b') {
        Write-Host ""
        Write-Host "[commit-discipline] ⚠️ POSSÍVEL PII no diff (CPF/CNPJ formatado)."
        Write-Host "  LGPD: dados reais NUNCA em commit. Use [REDACTED] ou data fake (123.456.789-09)."
        Write-Host "  Se for fake (CPF inválido pra teste), seguir é OK."
        Write-Host ""
    }
}

exit 0
