<?php

namespace Modules\Captacao\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Captacao\Services\CaptacaoAcesso;

/**
 * Barra toda rota autenticada do /captacao quando a empresa não tem o pacote `captacao_module`
 * ou o usuário não tem `captacao.access` (403). Checa a cada request: desligar o pacote corta o
 * acesso no request seguinte.
 */
class CaptacaoLiberado
{
    public function handle(Request $request, Closure $next)
    {
        $liberado = app(CaptacaoAcesso::class)->liberado(
            $request->user(),
            $request->session()->get('user.business_id')
        );

        abort_unless($liberado, 403, __('captacao::captacao.sem_acesso'));

        return $next($request);
    }
}
