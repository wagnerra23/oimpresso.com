<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * MEM-CC-team-1 (ADR 0055/0056) — Tool MCP que busca em sessões Claude Code
 * indexadas (`mcp_cc_messages` via FULLTEXT). Cross-dev quando user tem
 * permission `copiloto.cc.read.all`; senão só próprias sessões.
 *
 * Uso típico (Felipe pergunta no Claude Code dele):
 *   "Já resolvi 504 do Telescope antes nesse projeto?"
 *   → Claude chama cc-search query="504 Telescope" → retorna 3 hits cross-dev
 *   → Felipe lê o fix de Wagner em vez de re-explorar
 *
 * Economia: -30-70% tokens em queries que reutilizam knowledge anterior.
 */
class CcSearchTool extends Tool
{
    protected string $name = 'cc-search';

    protected string $title = 'Buscar nas sessões Claude Code do time';

    protected string $description = 'Full-text search nas sessões Claude Code do time inteiro (msgs, tool_uses, tool_results indexados em mcp_cc_messages). Cross-dev quando user tem copiloto.cc.read.all; senão só próprias sessões. Útil pra reutilizar knowledge de sessões anteriores em vez de re-explorar.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->description('Termos de busca (ex: "telescope crash", "fix N+1 query", "configurar octane")'),
            'tool_filter' => $schema->string()
                ->description('Filtrar por tool específica (Bash, Edit, Read, Grep, Agent...). Omite pra todos.'),
            'user_filter' => $schema->string()
                ->description('Email do dev (Wagner/Felipe/Maíra/Luiz/Eliana). Omite pra todos visíveis.'),
            'days_ago' => $schema->integer()
                ->min(1)
                ->max(365)
                ->default(30)
                ->description('Janela em dias retroativos (default 30, max 365)'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->default(8)
                ->description('Quantos hits retornar (default 8, max 20)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query', '');
        $toolFilter = $request->get('tool_filter');
        $userFilter = $request->get('user_filter');
        $daysAgo = max(1, min(365, (int) $request->get('days_ago', 30)));
        $limit = max(1, min(20, (int) $request->get('limit', 8)));

        if (trim($query) === '') {
            return Response::error('Parâmetro "query" obrigatório.');
        }

        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        // Verifica se a tabela existe (MEM-CC-1 pode não estar migrada ainda)
        if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_cc_messages')) {
            return Response::text(
                "ℹ️ MEM-CC-1 ainda não está ativo. Schema commitado mas migrations não rodaram.\n" .
                "Por enquanto, knowledge cross-dev fica em `memory/sessions/*.md` (use sessions-recent)."
            );
        }

        $base = DB::table('mcp_cc_messages as m')
            ->leftJoin('mcp_cc_sessions as s', 's.id', '=', 'm.session_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id');

        // RBAC: copiloto.cc.read.all → cross-dev; senão só próprias
        $canReadAll = method_exists($user, 'can') && $user->can('copiloto.cc.read.all');
        if (! $canReadAll) {
            $base->where('m.user_id', $user->id);
        }

        // Filtros opcionais
        if ($toolFilter !== null && $toolFilter !== '') {
            $base->where('m.tool_name', $toolFilter);
        }
        if ($userFilter !== null && $userFilter !== '') {
            $base->where('u.email', $userFilter);
        }
        $base->where('m.ts', '>=', now()->subDays($daysAgo));

        // FULLTEXT search
        $rows = $base->whereRaw(
                'MATCH(m.content_text) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$query]
            )
            ->orderByDesc('m.ts')
            ->limit($limit)
            ->get([
                'm.id', 'm.msg_uuid', 'm.msg_type', 'm.tool_name',
                'm.content_text', 'm.ts',
                's.session_uuid', 's.project_path', 's.git_branch',
                'u.email as dev_email',
                DB::raw('COALESCE(NULLIF(TRIM(CONCAT_WS(" ", u.first_name, u.last_name)), ""), u.username, "—") as dev_nome'),
            ]);

        if ($rows->isEmpty()) {
            return Response::text(
                "Nenhum hit em sessões Claude Code pra: \"$query\"\n" .
                "(janela: últimos $daysAgo dias" .
                ($canReadAll ? '' : ', escopo: suas próprias') .
                ($toolFilter ? ", tool=$toolFilter" : '') .
                ($userFilter ? ", user=$userFilter" : '') .
                ')'
            );
        }

        $output = "Encontrados {$rows->count()} hit(s) cross-dev pra \"$query\":\n\n";
        foreach ($rows as $r) {
            $shortTs = \Carbon\Carbon::parse($r->ts)->diffForHumans(['short' => true]);
            $project = basename($r->project_path ?? '');

            $output .= "## " . ($r->tool_name ?? $r->msg_type) . " · $shortTs · {$r->dev_nome}\n";
            $output .= "**project:** `$project`";
            if ($r->git_branch) $output .= " · branch `$r->git_branch`";
            $output .= "\n";

            $snippet = mb_substr($r->content_text ?? '', 0, 300);
            $output .= "```\n$snippet\n```\n";
            $output .= "_session: {$r->session_uuid}, msg #{$r->id}_\n\n";
        }

        return Response::text($output);
    }
}
