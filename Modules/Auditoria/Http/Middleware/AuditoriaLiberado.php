<?php

namespace Modules\Auditoria\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas leituras de /auditoria quando o usuário não tem `auditoria.view` (Camada 3). Superadmin
 * passa direto, como no menu (DataController::modifyAdminMenu). O papel `Admin#{empresa}` passa
 * pelo Gate::before do AuthServiceProvider, como em qualquer permissão de empresa.
 *
 * Antes deste middleware só o menu conferia: qualquer usuário logado abria a trilha da própria
 * empresa pela URL, com `properties` trazendo e-mail, celular e valores.
 *
 * Pacote fora da regra (decisão de 2026-11-16): o #10646 exigia também o pacote
 * `auditoria_module`. Medido em 2026-11-09, só 2 das 12 empresas com assinatura ativa tinham a
 * chave no pacote, então a exigência tirava a trilha do Admin das outras 10 — a mesma tela que o
 * menu core "Activity log" e o botão de Arquivos continuam oferecendo. O vazamento era de usuário
 * comum, e a permissão sozinha o fecha. O menu do próprio módulo segue exigindo o pacote.
 *
 * As duas leituras usam o business da sessão, a mesma fonte que o AuditoriaController usa para
 * filtrar o activity_log.
 */
class AuditoriaLiberado
{
    public const PERMISSAO = 'auditoria.view';

    public const SEM_PERMISSAO = 'Usuário sem permissão para ver a trilha de auditoria.';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user->can('superadmin')) {
            return $next($request);
        }

        abort_unless($user->can(self::PERMISSAO), 403, self::SEM_PERMISSAO);

        return $next($request);
    }
}
