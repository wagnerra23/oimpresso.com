<?php

namespace Modules\Auditoria\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas leituras de /auditoria quando a empresa da sessão não tem o pacote `auditoria_module`
 * (Camada 1, Superadmin > Pacotes) ou o usuário não tem `auditoria.view` (Camada 3). Superadmin
 * passa direto, como no menu (DataController::modifyAdminMenu).
 *
 * Antes deste middleware só o menu conferia: qualquer usuário logado abria a trilha da própria
 * empresa pela URL, com `properties` trazendo e-mail, celular e valores.
 *
 * O business vem da sessão, a mesma fonte que o AuditoriaController usa para filtrar o
 * activity_log. O ModuleUtil vem do container para os testes trocarem o pacote (mesmo padrão do
 * ComprasLiberado). As duas mensagens são distintas para o teste provar qual camada recusou.
 */
class AuditoriaLiberado
{
    public const PACOTE = 'auditoria_module';

    public const PERMISSAO = 'auditoria.view';

    public const SEM_PACOTE = 'O pacote da empresa não inclui o módulo Auditoria.';

    public const SEM_PERMISSAO = 'Usuário sem permissão para ver a trilha de auditoria.';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user->can('superadmin')) {
            return $next($request);
        }

        $pacoteAtivo = (bool) app(ModuleUtil::class)->hasThePermissionInSubscription(
            (int) $request->session()->get('user.business_id'),
            self::PACOTE,
            'superadmin_package'
        );

        abort_unless($pacoteAtivo, 403, self::SEM_PACOTE);
        abort_unless($user->can(self::PERMISSAO), 403, self::SEM_PERMISSAO);

        return $next($request);
    }
}
