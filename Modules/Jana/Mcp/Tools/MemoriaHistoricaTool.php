<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Services\Memoria\HistoricoMemoriaService;

/**
 * memoria-historica (ADR 0295, T4 slice 2) — TIME-TRAVEL na memoria Jana.
 *
 * Espelha MemoriaSearchTool, mas em vez de buscar fatos ATUAIS, responde "quais
 * fatos eram EVENT-validos em as_of?" (bi-temporal event-time). Usa Eloquent
 * DIRETO (HistoricoMemoriaService) — NUNCA Scout: o fato superseded que o
 * time-travel precisa ver fica fora do index Meilisearch.
 *
 * Cross-tenant safety (ADR 0093): user so acessa o proprio business (superadmin
 * qualquer) — MESMA checagem do MemoriaSearchTool. Scope business_id + user_id.
 */
class MemoriaHistoricaTool extends Tool
{
    protected string $name = 'memoria-historica';

    protected string $title = 'Time-travel na memória do Copiloto';

    protected string $description = 'Time-travel na memória do Copiloto (jana_memoria_facts): retorna os fatos que eram EVENT-válidos num instante (as_of), INCLUSIVE fatos já superseded — que não aparecem no memoria-search. Use pra "o que valia em DD/MM?" (bi-temporal event-time, ADR 0295).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'as_of' => $schema->string()
                ->description('Instante-mundo de referência (ex: "2026-04-15" ou "2026-04-15 10:00:00"). Omitido = agora (estado atual).'),
            'business_id' => $schema->integer()
                ->min(1)
                ->description('Business ID alvo. Se omitido, usa o business do user autenticado.'),
            'user_id' => $schema->integer()
                ->min(1)
                ->description('User ID alvo dentro do business. Se omitido, usa o próprio user autenticado.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->default(5)
                ->description('Quantos fatos retornar (default 5, max 20)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $asOfRaw = $request->get('as_of');
        $bizParaBusca = (int) ($request->get('business_id') ?? 0);
        $userParaBusca = (int) ($request->get('user_id') ?? 0);
        $limit = max(1, min(20, (int) $request->get('limit', 5)));

        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        // Resolve business_id: parâmetro OR user.business_id
        if ($bizParaBusca === 0) {
            $bizParaBusca = (int) ($user->business_id ?? 0);
        }

        // Cross-tenant safety (espelha MemoriaSearchTool): user só acessa o
        // próprio business — exceto superadmin (ADR 0093).
        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole('superadmin');
        if (HistoricoMemoriaService::violacaoCrossTenant((int) ($user->business_id ?? 0), $bizParaBusca, $isSuperadmin)) {
            return Response::error(sprintf(
                'Cross-tenant violation: user biz=%d tentou acessar biz=%d',
                (int) ($user->business_id ?? 0),
                $bizParaBusca,
            ));
        }

        if ($bizParaBusca === 0) {
            return Response::error('business_id não pôde ser resolvido.');
        }

        // user_id default = próprio user autenticado (memória pessoal — scope ADR 0093)
        if ($userParaBusca === 0) {
            $userParaBusca = (int) ($user->id ?? 0);
        }
        if ($userParaBusca === 0) {
            return Response::error('user_id não pôde ser resolvido.');
        }

        $asOf = HistoricoMemoriaService::normalizarAsOf($asOfRaw);
        $fatos = app(HistoricoMemoriaService::class)
            ->buscarHistorico($bizParaBusca, $userParaBusca, $asOf, $limit);

        if ($fatos->isEmpty()) {
            return Response::text(sprintf(
                "Nenhum fato event-válido em %s (biz=%d, user=%d).",
                $asOf->toDateTimeString(),
                $bizParaBusca,
                $userParaBusca,
            ));
        }

        $output = sprintf(
            "%d fato(s) event-válido(s) em %s (time-travel):\n\n",
            $fatos->count(),
            $asOf->toDateTimeString(),
        );
        foreach ($fatos as $f) {
            $meta = $f->metadata ?? [];
            $cat = (string) ($meta['categoria'] ?? '');

            $output .= "## Fato #{$f->id}";
            if ($cat !== '') {
                $output .= " [{$cat}]";
            }
            $output .= $f->valid_until !== null ? ' · (superseded no sistema)' : ' · (ativo)';
            $output .= "\n".$f->fato."\n";
            $ev = $f->event_valid_from?->toDateTimeString() ?? 'desde sempre';
            $ate = $f->event_valid_until?->toDateTimeString() ?? 'ainda vale';
            $output .= "_Event-time: {$ev} → {$ate}_\n\n";
        }

        return Response::text($output);
    }
}
