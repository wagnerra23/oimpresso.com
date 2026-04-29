<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * MEM-MEM-MCP-1 (ADR 0056) — Tool MCP que busca fatos persistentes da memória
 * do Copiloto (`copiloto_memoria_facts`), filtrados por business + user.
 *
 * Uso:
 *   - Copiloto chat web (Laravel app) chama via McpMemoriaDriver no recall
 *   - Claude Code do Wagner chama via "memoria-search" pra ver lembranças do projeto
 *
 * Como conhecemos o business_id/user_id?
 *   - Vem do user autenticado pelo Bearer token MCP (mcp_user attribute)
 *   - Server-side calls (ex: Copiloto chat usando system token) passam
 *     scope_business_id explícito como parâmetro
 *
 * Cross-tenant safety: assert business_id do user do token === business_id da query.
 * Excepão: superadmin pode passar qualquer.
 */
class MemoriaSearchTool extends Tool
{
    protected string $name = 'memoria-search';

    protected string $title = 'Buscar memória do Copiloto';

    protected string $description = 'Busca fatos persistentes na memória do Copiloto (copiloto_memoria_facts) filtrados por business. Retorna top-K snippets relevantes via FULLTEXT search. Use pra reter contexto entre conversas.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->description('Termos de busca (ex: "meta de faturamento", "preferência cliente", "última conversa sobre X")'),
            'business_id' => $schema->integer()
                ->min(1)
                ->description('Business ID alvo. Se omitido, usa o business do user autenticado.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->default(5)
                ->description('Quantos fatos retornar (default 5, max 20)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query', '');
        $bizParaBusca = (int) ($request->get('business_id') ?? 0);
        $limit = max(1, min(20, (int) $request->get('limit', 5)));

        if (trim($query) === '') {
            return Response::error('Parâmetro "query" obrigatório.');
        }

        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        // Resolve business_id: parâmetro OR user.business_id
        if ($bizParaBusca === 0) {
            $bizParaBusca = (int) ($user->business_id ?? 0);
        }

        // Cross-tenant safety: user só pode buscar memória do próprio business
        // (exceto superadmin)
        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole('superadmin');
        if (! $isSuperadmin && $bizParaBusca !== (int) ($user->business_id ?? 0)) {
            return Response::error(sprintf(
                'Cross-tenant violation: user biz=%d tentou acessar biz=%d',
                (int) ($user->business_id ?? 0),
                $bizParaBusca,
            ));
        }

        if ($bizParaBusca === 0) {
            return Response::error('business_id não pôde ser resolvido.');
        }

        // Busca via FULLTEXT em copiloto_memoria_facts
        // (não usa Meilisearch direto pra ter controle de filter biz/user/valid_until aqui;
        //  Meilisearch fica como cache/index secundário — pode ser refinement futuro)
        $rows = DB::table('copiloto_memoria_facts')
            ->where('business_id', $bizParaBusca)
            ->whereNull('valid_until')   // só fatos atuais
            ->whereNull('deleted_at')
            ->whereRaw(
                'MATCH(fato) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$query]
            )
            ->orderByDesc(DB::raw('MATCH(fato) AGAINST(?) IN NATURAL LANGUAGE MODE'))
            ->limit($limit)
            ->get(['id', 'fato', 'metadata', 'valid_from', 'created_at']);

        if ($rows->isEmpty()) {
            return Response::text("Nenhum fato encontrado pra: \"$query\".");
        }

        $output = "Encontrados {$rows->count()} fato(s) na memória pra \"$query\":\n\n";
        foreach ($rows as $r) {
            $meta = is_string($r->metadata) ? json_decode($r->metadata, true) : ($r->metadata ?? []);
            $cat = $meta['categoria'] ?? '';
            $relev = $meta['relevancia'] ?? '';

            $output .= "## Fato #{$r->id}";
            if ($cat) $output .= " [$cat]";
            if ($relev) $output .= " · relevância $relev";
            $output .= "\n";
            $output .= $r->fato . "\n";
            $output .= "_Persistido em: {$r->valid_from}_\n\n";
        }

        return Response::text($output);
    }
}
