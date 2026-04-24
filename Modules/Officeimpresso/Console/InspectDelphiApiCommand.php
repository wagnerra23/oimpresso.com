<?php

namespace Modules\Officeimpresso\Console;

use Illuminate\Console\Command;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Inspeciona as ultimas chamadas do Delphi ao /connector/api/* — mostra
 * body, headers, formato detectado. Util quando o Delphi muda payload e
 * a gente precisa descobrir o que ele esta mandando agora.
 *
 * Uso:
 *   php artisan officeimpresso:inspect-api                   # ultimos 10
 *   php artisan officeimpresso:inspect-api --limit=30        # ultimos 30
 *   php artisan officeimpresso:inspect-api --format=pipe     # so formato pipe
 *   php artisan officeimpresso:inspect-api --endpoint=registrar
 *   php artisan officeimpresso:inspect-api --business=164    # so Vargas
 *   php artisan officeimpresso:inspect-api --full            # body inteiro sem truncar
 */
class InspectDelphiApiCommand extends Command
{
    protected $signature = 'officeimpresso:inspect-api
                            {--limit=10}
                            {--format= : array_tabelas|json_flat|pipe|empty|unknown}
                            {--endpoint= : substring do endpoint}
                            {--business= : business_id}
                            {--full : mostra body inteiro sem truncar}';

    protected $description = 'Inspeciona ultimas chamadas do Delphi com body completo';

    public function handle(): int
    {
        $q = LicencaLog::where('source', 'delphi_middleware')
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'));

        if ($fmt = $this->option('format')) {
            $q->where('metadata', 'like', '%"body_format":"' . $fmt . '"%');
        }
        if ($ep = $this->option('endpoint')) {
            $q->where('endpoint', 'like', '%' . $ep . '%');
        }
        if ($bid = $this->option('business')) {
            $q->where('business_id', $bid);
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            $this->warn('Nenhum registro encontrado com esses filtros.');
            return self::SUCCESS;
        }

        $full = $this->option('full');

        foreach ($rows as $log) {
            $meta = is_array($log->metadata) ? $log->metadata : (json_decode((string) $log->metadata, true) ?: []);
            $this->line('');
            $this->line(str_repeat('═', 80));
            $this->line(sprintf(
                '#%d  %s  %s %s  → %d  (%dms)',
                $log->id,
                $log->created_at,
                $log->http_method,
                $log->endpoint,
                $log->http_status,
                $log->duration_ms ?? 0,
            ));
            $this->line(sprintf('biz=%s  lic=%s  hd=%s  format=%s  size=%sB',
                $log->business_id ?? '—',
                $log->licenca_id ?? '—',
                $meta['hd'] ?? '—',
                $meta['body_format'] ?? '—',
                $meta['body_size'] ?? 0,
            ));
            if (! empty($meta['request_headers'])) {
                $this->line('headers: ' . json_encode($meta['request_headers'], JSON_UNESCAPED_SLASHES));
            }
            $this->line('body:');
            $body = (string) ($meta['body_preview'] ?? '');
            if (! $full && strlen($body) > 500) {
                $body = substr($body, 0, 500) . '…[' . (strlen($body) - 500) . ' chars restantes — use --full]';
            }
            $this->line($body !== '' ? $body : '(vazio)');
        }

        $this->line('');
        $this->info($rows->count() . ' registro(s).');
        return self::SUCCESS;
    }
}
