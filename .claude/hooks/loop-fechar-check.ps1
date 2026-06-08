# loop-fechar-check.ps1 - Rotina "Fechar o Loop" do IA-OS
# Disparado em SessionStart (apos brief-fetch) pelo .claude/settings.json
# Origem: AUDIT IA-OS 2026-05-29 (nota 68/100). Wagner pediu rotina idempotente
#   atrelada ao brief que verifica o que ja foi feito e avanca o que falta.
# REGRA DURA: NUNCA toca Brain B / autonomia ADS / ads-route (decisao Wagner: nao
#   ligar 2o cerebro agora - custo recorrente nao desejado).
# Manifesto: .claude/loop-fechar-o-loop.json (fonte de verdade + estado).
# PS 5.1 compat: ASCII puro, sem emoji.

$ErrorActionPreference = 'SilentlyContinue'

try {
    $manifestPath = Join-Path $PSScriptRoot '..\loop-fechar-o-loop.json'
    if (-not (Test-Path $manifestPath)) { exit 0 }

    $repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
    $m = Get-Content $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
    if (-not $m.itens) { exit 0 }

    # Resolve estado de cada item (idempotencia)
    $itens = @()
    foreach ($it in $m.itens) {
        $done = $false
        if ($it.detect.tipo -eq 'manual') {
            $done = [bool]$it.done
        } elseif ($it.detect.tipo -eq 'file_any') {
            foreach ($p in $it.detect.paths) {
                if (Test-Path (Join-Path $repo $p)) { $done = $true; break }
            }
        }
        $itens += [pscustomobject]@{
            ordem = [int]$it.ordem; gap = $it.gap; titulo = $it.titulo
            done = $done; prio = $it.prioridade; custo = $it.custo_recorrente
            aprova = [bool]$it.precisa_aprovacao_wagner; nota = $it.nota_aprovacao
        }
    }
    $itens = $itens | Sort-Object ordem

    $pendentes = @($itens | Where-Object { -not $_.done })

    Write-Host ""
    Write-Host "=== ROTINA: FECHAR O LOOP DO IA-OS (audit 2026-05-29) ==="
    foreach ($i in $itens) {
        $mark = if ($i.done) { "[OK]" } else { "[--]" }
        Write-Host ("  {0} #{1} {2} - {3}" -f $mark, $i.gap, $i.prio, $i.titulo)
    }

    if ($pendentes.Count -eq 0) {
        Write-Host ""
        Write-Host "  LOOP FECHADO - nada a fazer. IA-OS com painel + alarme + LGPD no ar."
        Write-Host "  (Para reabrir um item, mude 'done' no manifesto.)"
    } else {
        $next = $pendentes[0]
        Write-Host ""
        Write-Host ("  PROXIMO PENDENTE: #{0} - {1}" -f $next.gap, $next.titulo)
        Write-Host ("  Custo recorrente: {0}" -f $next.custo)
        if ($next.aprova) {
            Write-Host "  >> EXIGE APROVACAO DO WAGNER antes de avancar (custo/risco). Nota:"
            Write-Host ("     {0}" -f $next.nota)
        }
        Write-Host ""
        Write-Host "  ACAO CLAUDE: avise o Wagner que ha item do loop pendente e pergunte"
        Write-Host ("  'quer que eu faca o #{0} agora?'. NAO comece sem ele confirmar." -f $next.gap)
        Write-Host "  NUNCA inclua Brain B / autonomia ADS nesta rotina (decisao Wagner)."
    }
    Write-Host ""
} catch {
    # Falha silenciosa: rotina-nudge nao deve quebrar inicio de sessao.
    exit 0
}
