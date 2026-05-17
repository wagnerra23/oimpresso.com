<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UsageCsvExporter — Wave 18 D4 SATURATION (2026-05-16).
 *
 * Extrai export CSV de audit log antes embutido em `TeamController::exportCsv()`.
 *
 * Service streama via `php://output` em chunks (cursor query) — não materializa
 * dataset inteiro em memória (segura pra audit log com milhões de rows).
 *
 * **D7 LGPD considerations**: CSV contém `user_email` + `tool_or_resource` —
 * download é restrito a `copiloto.mcp.usage.all` (superadmin) e auditado via
 * `mcp_audit_log` (request → tool=team-export → user_id=Wagner).
 *
 * @see Modules\TeamMcp\Http\Controllers\TeamController::exportCsv (uses this)
 */
class UsageCsvExporter
{
    /**
     * Streama CSV de audit log no intervalo [de, ate] (datas YYYY-MM-DD).
     *
     * Caller define filename via `Content-Disposition` header — Service entrega
     * apenas StreamedResponse pronto pra return.
     */
    public function streamCsv(string $de, string $ate, string $filename): StreamedResponse
    {
        return OtelHelper::spanBiz('teammcp.usage.export_csv', function () use ($de, $ate, $filename) {
            $headers = [
                'Content-Type'        => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            return new StreamedResponse(function () use ($de, $ate) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['ts', 'user_id', 'user_email', 'endpoint', 'tool', 'status', 'tokens_total', 'custo_brl', 'duration_ms']);

                DB::table('mcp_audit_log as a')
                    ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                    ->whereBetween('a.ts', [$de . ' 00:00:00', $ate . ' 23:59:59'])
                    ->orderBy('a.ts')
                    ->select(
                        'a.ts', 'a.user_id', 'u.email', 'a.endpoint',
                        'a.tool_or_resource', 'a.status', 'a.tokens_in', 'a.tokens_out',
                        'a.custo_brl', 'a.duration_ms',
                    )
                    ->cursor()
                    ->each(function ($r) use ($h) {
                        fputcsv($h, [
                            $r->ts,
                            $r->user_id,
                            $r->email ?? '-',
                            $r->endpoint,
                            $r->tool_or_resource ?? '',
                            $r->status,
                            ((int) ($r->tokens_in ?? 0)) + ((int) ($r->tokens_out ?? 0)),
                            number_format((float) ($r->custo_brl ?? 0), 6, '.', ''),
                            $r->duration_ms ?? '',
                        ]);
                    });

                fclose($h);
            }, 200, $headers);
        }, ['module' => 'TeamMcp', 'range_start' => $de, 'range_end' => $ate]);
    }
}
