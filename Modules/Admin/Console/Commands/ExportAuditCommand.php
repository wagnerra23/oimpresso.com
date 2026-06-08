<?php

declare(strict_types=1);

namespace Modules\Admin\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * admin:export-audit — Wave 23 (G3 FICHA W22 entregue).
 *
 * Exporta `mcp_admin_audit_log` em CSV/JSON pra arquivamento externo + auditoria
 * LGPD (Art. 37 — registro de operações). Wagner-only (Admin Center).
 *
 * Uso CLI:
 *   php artisan admin:export-audit --since=2026-05-01
 *   php artisan admin:export-audit --since=2026-05-01 --format=json
 *   php artisan admin:export-audit --since=2026-04-01 --until=2026-04-30 --format=csv --output=storage/app/audit-april.csv
 *
 * NÃO usa `--verbose` (Symfony reserved — vide rule path-scoped commands.md).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see Modules/Admin/Config/retention.php
 */
class ExportAuditCommand extends Command
{
    protected $signature = 'admin:export-audit
                            {--since= : Data inicial (YYYY-MM-DD). Default 30d atrás.}
                            {--until= : Data final (YYYY-MM-DD). Default agora.}
                            {--format=csv : Formato de output (csv|json).}
                            {--output= : Path destino (default storage/app/admin-audit-export-<ts>.<ext>).}
                            {--detail : Log linha por linha durante export.}';

    protected $description = 'Exporta mcp_admin_audit_log em CSV/JSON pra arquivamento + auditoria LGPD (Art. 37).';

    public function handle(): int
    {
        $startedAt = microtime(true);

        if (! Schema::hasTable('mcp_admin_audit_log')) {
            $this->error('mcp_admin_audit_log ausente — rode `php artisan migrate` em Modules/Admin.');
            return self::FAILURE;
        }

        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['csv', 'json'], true)) {
            $this->error("Formato inválido `{$format}` — use csv ou json.");
            return self::FAILURE;
        }

        try {
            $since = $this->option('since')
                ? Carbon::parse((string) $this->option('since'))->startOfDay()
                : now()->subDays(30)->startOfDay();
            $until = $this->option('until')
                ? Carbon::parse((string) $this->option('until'))->endOfDay()
                : now();
        } catch (\Throwable $e) {
            $this->error("Datas inválidas: " . $e->getMessage());
            return self::FAILURE;
        }

        if ($since->gt($until)) {
            $this->error("--since ({$since->toDateString()}) > --until ({$until->toDateString()}).");
            return self::FAILURE;
        }

        $output = (string) ($this->option('output')
            ?: 'admin-audit-export-' . now()->format('Ymd-His') . '.' . $format);

        $query = DB::table('mcp_admin_audit_log')
            ->whereBetween('created_at', [$since, $until])
            ->orderBy('created_at');

        $count = (int) $query->count();
        if ($count === 0) {
            $this->warn('Nenhuma entry no intervalo — nada exportado.');
            return self::SUCCESS;
        }

        $written = $format === 'csv'
            ? $this->writeCsv($query, $output)
            : $this->writeJson($query, $output);

        $elapsed = round((microtime(true) - $startedAt) * 1000);
        $this->info("Export OK — {$written} entries em {$output} ({$elapsed}ms).");
        $this->line("  Intervalo: {$since->toDateString()} → {$until->toDateString()}");
        $this->line("  Formato: {$format}");

        return self::SUCCESS;
    }

    private function writeCsv($query, string $output): int
    {
        $headers = ['id', 'user_id', 'business_id', 'action', 'route', 'ip', 'payload', 'created_at'];
        $lines = [implode(',', $headers)];

        $written = 0;
        $query->chunkById(500, function ($rows) use (&$lines, &$written) {
            foreach ($rows as $row) {
                $lines[] = implode(',', [
                    $row->id ?? '',
                    $row->user_id ?? '',
                    $row->business_id ?? '',
                    $this->csvEscape((string) ($row->action ?? '')),
                    $this->csvEscape((string) ($row->route ?? '')),
                    $row->ip ?? '',
                    $this->csvEscape((string) ($row->payload ?? '')),
                    $row->created_at ?? '',
                ]);
                $written++;
                if ($this->option('detail')) {
                    $this->line('  #' . $row->id . ' ' . ($row->action ?? ''));
                }
            }
        });

        Storage::disk('local')->put($output, implode("\n", $lines));
        return $written;
    }

    private function writeJson($query, string $output): int
    {
        $entries = [];
        $query->chunkById(500, function ($rows) use (&$entries) {
            foreach ($rows as $row) {
                $entries[] = (array) $row;
                if ($this->option('detail')) {
                    $this->line('  #' . $row->id . ' ' . ($row->action ?? ''));
                }
            }
        });

        Storage::disk('local')->put($output, json_encode([
            'exported_at' => now()->toIso8601String(),
            'count'       => count($entries),
            'entries'     => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return count($entries);
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
