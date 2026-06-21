<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * ADR 0119 (Tier 1) — "Quem está mexendo em quê AGORA?"
 *
 * Agrega sinais existentes (mcp_cc_sessions + mcp_cc_messages do watcher cc-search,
 * MEM-CC-1) pra responder coordenação Claude-A vs Claude-B. Zero estado novo: só
 * agregação derivada das tabelas que já existem.
 *
 * Caso de uso: skill Tier A `session-start-check` chama esta tool no SessionStart
 * pra alertar se outra sessão Claude tocou paths overlapping nas últimas N horas.
 *
 * Não é lock — é alerta passivo. Tier 2 (lease formal com TTL) está dormente até
 * 2× evidência empírica de incidente Claude-A vs Claude-B no mesmo arquivo.
 */
class WhatsActiveTool extends Tool
{
    protected string $name = 'whats-active';

    protected string $title = 'Sessões Claude ativas + paths tocados';

    protected string $description = 'Lista sessões Claude Code do time recentemente ativas + paths tocados nas últimas N horas. Use no início de sessão pra detectar se outro dev está mexendo no escopo que você vai pegar (alerta passivo, não bloqueia). ADR 0119 Tier 1.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'hours' => $schema->integer()
                ->min(1)
                ->max(24)
                ->default(2)
                ->description('Janela de "ativo": sessões com mensagem nas últimas N horas (default 2, max 24)'),
            'paths_window_hours' => $schema->integer()
                ->min(1)
                ->max(72)
                ->default(24)
                ->description('Janela pra agregar paths tocados (default 24, max 72)'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(30)
                ->default(15)
                ->description('Máx sessões retornadas (default 15)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $hours = max(1, min(24, (int) $request->get('hours', 2)));
        $pathsWindowHours = max(1, min(72, (int) $request->get('paths_window_hours', 24)));
        $limit = max(1, min(30, (int) $request->get('limit', 15)));

        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        // MEM-CC-1 pode não estar migrado em ambientes secundários
        if (! Schema::hasTable('mcp_cc_sessions') || ! Schema::hasTable('mcp_cc_messages')) {
            return Response::text(
                "ℹ️ MEM-CC-1 ainda não está ativo. Schema commitado mas migrations não rodaram.\n" .
                'Tool `whats-active` precisa de `mcp_cc_sessions` + `mcp_cc_messages`.'
            );
        }

        $activeSince = now()->subHours($hours);
        $pathsSince = now()->subHours($pathsWindowHours);

        // Sessões com atividade recente (status='active' OU última msg recente).
        // Fonte de verdade pra "ativo": existe message nas últimas N horas.
        $sessionIds = DB::table('mcp_cc_messages')
            ->select('session_id', DB::raw('MAX(ts) as last_activity_at'))
            ->where('ts', '>=', $activeSince)
            ->groupBy('session_id')
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->pluck('last_activity_at', 'session_id');

        if ($sessionIds->isEmpty()) {
            // B-SPOF-WA (ADR 0278 dim 10): "nenhuma msg recente" só é all-clear CONFIÁVEL
            // se o pipeline de ingest estiver VIVO. Se o heartbeat existe E nenhum host
            // está fresco (fresh=0), estamos CEGOS (watcher de ingest caído) — blind ≠ safe.
            // Se a tabela de heartbeat nem existe (feature não-deployada), não cria lobo:
            // mantém o all-clear original (sem sinal de liveness pra contradizê-lo).
            if (Schema::hasTable('mcp_ingest_heartbeat')) {
                $ingest = app(\Modules\TeamMcp\Services\IngestLivenessService::class)->summary();
                if ($ingest['fresh'] === 0) {
                    return Response::text(
                        "⚠️ Nenhuma sessão Claude Code vista nas últimas {$hours}h — MAS o pipeline de "
                        . "ingest está SEM heartbeat fresco (fresh=0 · stale={$ingest['stale']} · dead={$ingest['dead']}).\n"
                        . '_Posso estar CEGO (watcher de ingest caído): NÃO assuma escopo livre — confirme antes de pegar._'
                    );
                }
            }

            return Response::text(
                "✅ Nenhuma sessão Claude Code ativa nas últimas {$hours}h.\n" .
                '_Pode pegar qualquer escopo sem risco de overlap._'
            );
        }

        $sessions = DB::table('mcp_cc_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->whereIn('s.id', $sessionIds->keys())
            ->select([
                's.id',
                's.session_uuid',
                's.user_id',
                's.business_id',
                's.project_path',
                's.git_branch',
                's.started_at',
                's.cc_version',
                'u.email as dev_email',
                DB::raw('COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.username, "—") as dev_nome'),
            ])
            ->get()
            ->keyBy('id');

        // Agrega paths tocados por sessão (Edit/Write últimas N horas).
        $pathsBySession = DB::table('mcp_cc_messages')
            ->whereIn('session_id', $sessions->keys())
            ->whereIn('tool_name', ['Edit', 'Write', 'NotebookEdit'])
            ->where('ts', '>=', $pathsSince)
            ->whereNotNull('content_json')
            ->select('session_id', 'content_json')
            ->get()
            ->groupBy('session_id')
            ->map(function ($msgs) {
                return $msgs
                    ->map(function ($m) {
                        $json = is_string($m->content_json)
                            ? json_decode($m->content_json, true)
                            : $m->content_json;
                        if (! is_array($json)) {
                            return null;
                        }
                        return data_get($json, 'input.file_path')
                            ?? data_get($json, 'tool_input.file_path')
                            ?? data_get($json, 'file_path')
                            ?? data_get($json, 'parameters.file_path');
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            });

        $output = "## Sessões Claude ativas (últimas {$hours}h)\n\n";
        $output .= "**{$sessions->count()} sessão(ões)** · paths agregados últimas {$pathsWindowHours}h\n\n";

        foreach ($sessions as $sessId => $s) {
            $lastAt = $sessionIds->get($sessId);
            $shortAgo = $lastAt ? \Carbon\Carbon::parse($lastAt)->diffForHumans(['short' => true]) : '—';
            $project = basename($s->project_path ?? '—');
            $paths = $pathsBySession->get($sessId, []);

            $output .= "### {$s->dev_nome} · ativo {$shortAgo}\n";
            $output .= "- **project:** `{$project}`";
            if (! empty($s->git_branch)) {
                $output .= " · branch `{$s->git_branch}`";
            }
            $output .= "\n";
            $output .= "- **session:** `{$s->session_uuid}`\n";

            if (empty($paths)) {
                $output .= "- **paths tocados:** _(nenhum Edit/Write na janela)_\n";
            } else {
                $shown = array_slice($paths, 0, 8);
                $output .= "- **paths tocados (" . count($paths) . "):**\n";
                foreach ($shown as $p) {
                    $rel = $this->relativePath((string) $p, (string) $s->project_path);
                    $output .= "  - `{$rel}`\n";
                }
                if (count($paths) > 8) {
                    $output .= '  - _+' . (count($paths) - 8) . " outros_\n";
                }
            }
            $output .= "\n";
        }

        $output .= "---\n";
        $output .= '_Use `whats-active hours:N` pra ampliar/reduzir a janela. ' .
            'Esta tool é alerta passivo — não bloqueia ninguém. ADR 0119._';

        return Response::text($output);
    }

    private function relativePath(string $abs, string $projectPath): string
    {
        if ($projectPath === '' || ! str_starts_with($abs, $projectPath)) {
            return $abs;
        }
        return ltrim(substr($abs, strlen($projectPath)), DIRECTORY_SEPARATOR . '/');
    }
}
