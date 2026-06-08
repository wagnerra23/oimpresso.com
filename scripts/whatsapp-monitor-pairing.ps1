# whatsapp-monitor-pairing.ps1
# Monitor re-pairing de canal WhatsApp Baileys após PR #828 (async queue history sync).
# Roda local na máquina Wagner; SSH Hostinger pra coletar logs + count DB.
#
# Uso:
#   pwsh ./scripts/whatsapp-monitor-pairing.ps1                          # default channel=6 biz=1
#   pwsh ./scripts/whatsapp-monitor-pairing.ps1 -ChannelId 6 -BusinessId 1
#   pwsh ./scripts/whatsapp-monitor-pairing.ps1 -ChannelId 5 -BusinessId 4 -IntervalSeconds 10
#
# Ctrl+C pra parar.

param(
    [int]$ChannelId = 6,
    [int]$BusinessId = 1,
    [int]$IntervalSeconds = 15
)

$SshKey = "$env:USERPROFILE\.ssh\id_ed25519_oimpresso"
$SshHost = "u906587222@148.135.133.115"
$SshPort = 65002
$RepoPath = "~/domains/oimpresso.com/public_html"
$LogPath = "$RepoPath/storage/logs/laravel.log"

function Invoke-HostingerSsh {
    param([string]$Command)
    ssh -4 -o ConnectTimeout=30 -o ServerAliveInterval=3 -o ServerAliveCountMax=60 `
        -i $SshKey -p $SshPort $SshHost $Command 2>$null
}

function Warm-Up {
    Write-Host "Warm-up SSH (5 curl hits IPv4)..." -ForegroundColor DarkGray
    1..5 | ForEach-Object {
        curl.exe -s -o NUL --max-time 15 https://oimpresso.com/login 2>$null
    }
}

function Get-Counts {
    $tinker = @"
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Illuminate\Support\Facades\DB;

// SUPERADMIN: script monitoring CLI sem session — operador Wagner valida re-pairing.
\$ch = Channel::withoutGlobalScopes()->find($ChannelId);
\$chStatus = \$ch ? \$ch->status : 'NOT_FOUND';
\$chLabel = \$ch ? \$ch->label : '?';

\$convs = Conversation::withoutGlobalScopes()->where('channel_id', $ChannelId)->count();
\$msgs = Message::withoutGlobalScopes()
    ->whereIn('conversation_id', function (\$q) {
        \$q->select('id')->from('conversations')->where('channel_id', $ChannelId);
    })->count();

\$jobsPending = DB::table('jobs')->where('queue', 'whatsapp-history')->count();
\$jobsFailed = DB::table('failed_jobs')->where('queue', 'whatsapp-history')->count();

echo json_encode([
    'channel_id' => $ChannelId,
    'business_id' => $BusinessId,
    'channel_status' => \$chStatus,
    'channel_label' => \$chLabel,
    'conversations' => \$convs,
    'messages' => \$msgs,
    'jobs_pending' => \$jobsPending,
    'jobs_failed' => \$jobsFailed,
]);
"@

    $b64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($tinker))
    $cmd = "cd $RepoPath && echo $b64 | base64 -d | php artisan tinker --execute=`"\$(cat)`""
    $json = Invoke-HostingerSsh -Command $cmd
    if ($json) {
        try { return $json | ConvertFrom-Json } catch { return $null }
    }
    return $null
}

function Get-RecentLogs {
    $cmd = "tail -200 $LogPath 2>/dev/null | grep -iE 'history.sync|PersistHistorySyncBatchJob|history-sync-job|whatsapp-history' | tail -8"
    Invoke-HostingerSsh -Command $cmd
}

function Render-Frame {
    param($counts, $logs, $iteration)

    Clear-Host
    Write-Host "═══ WhatsApp re-pairing monitor — iter #$iteration ═══" -ForegroundColor Cyan
    Write-Host "Channel: $ChannelId (biz=$BusinessId)  ·  Refresh: ${IntervalSeconds}s  ·  Ctrl+C pra parar" -ForegroundColor DarkGray
    Write-Host ""

    if ($null -eq $counts) {
        Write-Host "[!] Falha ao buscar contadores via SSH/tinker (retry próxima iter)" -ForegroundColor Yellow
    } else {
        $statusColor = if ($counts.channel_status -eq 'active') { 'Green' }
                       elseif ($counts.channel_status -eq 'setup') { 'Yellow' }
                       else { 'Red' }
        Write-Host "Channel `"$($counts.channel_label)`" status: " -NoNewline
        Write-Host $counts.channel_status -ForegroundColor $statusColor

        Write-Host ("Conversations: {0,6}   Messages: {1,8}" -f $counts.conversations, $counts.messages)
        Write-Host ("Jobs pending:  {0,6}   Failed:   {1,8}" -f $counts.jobs_pending, $counts.jobs_failed) -ForegroundColor $(if ($counts.jobs_failed -gt 0) { 'Red' } else { 'DarkGray' })

        if ($counts.conversations -gt 0) {
            Write-Host ""
            Write-Host "[OK] FIX VALIDADO — conversations > 0 pra channel $ChannelId" -ForegroundColor Green
        }
    }

    Write-Host ""
    Write-Host "── Últimas 8 linhas log (filtro history-sync) ──" -ForegroundColor DarkGray
    if ($logs) {
        $logs | ForEach-Object {
            $line = $_
            $color = if ($line -match 'ERROR|FATAL|failed') { 'Red' }
                     elseif ($line -match 'WARN|warning') { 'Yellow' }
                     else { 'Gray' }
            Write-Host $line -ForegroundColor $color
        }
    } else {
        Write-Host "(nenhum log history-sync nas últimas 200 linhas)" -ForegroundColor DarkGray
    }
}

# ─── Main loop ───
Warm-Up

$iter = 0
while ($true) {
    $iter++
    $counts = Get-Counts
    $logs = Get-RecentLogs
    Render-Frame -counts $counts -logs $logs -iteration $iter
    Start-Sleep -Seconds $IntervalSeconds
}
