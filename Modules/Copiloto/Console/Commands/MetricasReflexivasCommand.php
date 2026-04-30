<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando reflexivo: mostra ratio MCP-tools vs Read filesystem por dev,
 * baseado em mcp_cc_messages (Claude Code sessions ingestadas).
 *
 * Uso:
 *   php artisan copiloto:metricas-reflexivas --days=7
 *   php artisan copiloto:metricas-reflexivas --days=30 --user=1
 *
 * Permite Wagner verificar: "Claude tá realmente usando MCP primeiro?"
 * sem precisar lembrar manualmente em cada sessão.
 */
class MetricasReflexivasCommand extends Command
{
    protected $signature = 'copiloto:metricas-reflexivas
        {--days=7 : Janela em dias retroativos}
        {--user= : Filtra por user_id (Wagner=1, Felipe, etc)}';

    protected $description = 'Métricas reflexivas: MCP-first vs Read filesystem ratio (LGPD-aware)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $this->info("📊 Métricas reflexivas — últimos {$days} dias" . ($userId ? " (user $userId)" : ''));
        $this->newLine();

        // Tools MCP do oimpresso
        $mcpTools = [
            'mcp__Oimpresso_MCP___Wagner__tasks-current',
            'mcp__Oimpresso_MCP___Wagner__decisions-search',
            'mcp__Oimpresso_MCP___Wagner__decisions-fetch',
            'mcp__Oimpresso_MCP___Wagner__sessions-recent',
            'mcp__Oimpresso_MCP___Wagner__memoria-search',
            'mcp__Oimpresso_MCP___Wagner__cc-search',
            'mcp__Oimpresso_MCP___Wagner__claude-code-usage-self',
        ];

        $query = DB::table('mcp_cc_messages as m')
            ->join('mcp_cc_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.ts', '>=', now()->subDays($days))
            ->whereNotNull('m.tool_name');

        if ($userId) {
            $query->where('s.user_id', $userId);
        }

        // Por dev
        $stats = (clone $query)
            ->selectRaw('
                s.user_id,
                SUM(CASE WHEN m.tool_name IN ("' . implode('","', $mcpTools) . '") THEN 1 ELSE 0 END) as mcp_calls,
                SUM(CASE WHEN m.tool_name IN ("Read","Glob","Grep") THEN 1 ELSE 0 END) as fs_calls,
                COUNT(*) as total_tools
            ')
            ->groupBy('s.user_id')
            ->get();

        if ($stats->isEmpty()) {
            $this->warn('Sem dados ainda. Watcher cc-watcher já rodou? Ver scripts/cc-watcher/README.md');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($stats as $s) {
            $totalReadable = (int) $s->mcp_calls + (int) $s->fs_calls;
            $ratio = $totalReadable > 0 ? round(((int) $s->mcp_calls / $totalReadable) * 100, 1) : 0;
            $emoji = $ratio >= 70 ? '✅' : ($ratio >= 40 ? '🟡' : '🔴');
            $rows[] = [
                'User' => "#{$s->user_id}",
                'MCP calls' => (int) $s->mcp_calls,
                'Read/Glob/Grep' => (int) $s->fs_calls,
                'Total tools' => (int) $s->total_tools,
                'MCP-first %' => "$emoji $ratio%",
            ];
        }

        $this->table(['User', 'MCP calls', 'Read/Glob/Grep', 'Total tools', 'MCP-first %'], $rows);

        // Top filesystem reads (sinal de violação da regra)
        $this->newLine();
        $this->info('🔴 Top 10 paths Read filesystem (que poderiam ser tool MCP):');
        $reads = (clone $query)
            ->where('m.tool_name', 'Read')
            ->selectRaw('SUBSTRING(m.content_text, 1, 200) as snippet, COUNT(*) c')
            ->whereRaw('m.content_text REGEXP "memory/(decisions|sessions|requisitos|comparativos)|CURRENT\\.md|08-handoff"')
            ->groupBy('snippet')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        if ($reads->isEmpty()) {
            $this->info('  ✅ Nenhuma violação detectada — Claude usando MCP-first.');
        } else {
            foreach ($reads as $r) {
                $this->line("  {$r->c}× — " . str_replace("\n", ' ', substr($r->snippet, 0, 100)));
            }
        }

        $this->newLine();
        $this->comment('Regra: ratio >= 70% = OK. Abaixo = sinal de violar oimpresso-mcp-first.');
        $this->comment('Se baixo: cobrar Claude / revisar skill .claude/skills/oimpresso-mcp-first/');

        return self::SUCCESS;
    }
}
