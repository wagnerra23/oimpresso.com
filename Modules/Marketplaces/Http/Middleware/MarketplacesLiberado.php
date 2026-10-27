<?php

namespace Modules\Marketplaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Marketplaces\Services\MarketplacesAcesso;

/**
 * 403 em toda rota autenticada do /marketplaces quando a empresa não tem o pacote
 * `marketplaces_module` ou o usuário não tem a permissão pedida. Checa a cada request.
 * Uso: `marketplaces.liberado` ou `marketplaces.liberado:marketplaces.account.connect`.
 */
class MarketplacesLiberado
{
    public function handle(Request $request, Closure $next, string $permissao = MarketplacesAcesso::PERMISSAO)
    {
        $liberado = app(MarketplacesAcesso::class)->liberado(
            $request->user(),
            $request->session()->get('user.business_id'),
            $permissao,
        );

        abort_unless($liberado, 403, 'Marketplaces não liberado para esta empresa ou usuário.');

        return $next($request);
    }
}
