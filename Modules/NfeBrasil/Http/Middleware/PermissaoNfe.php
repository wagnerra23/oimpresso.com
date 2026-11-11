<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Lote 3.2 da auditoria NfeBrasil (N5-04/N5-05): gate de permissão nas rotas que só exigiam
 * login. Passa quem tiver QUALQUER uma das permissões listadas; senão 403.
 *
 * Uso: `->middleware(PermissaoNfe::class.':perm.a,perm.b')`. Um item `role:<nome>` aceita o
 * papel `<nome>#{business_id}` da sessão (o formato dos papéis do UltimatePOS) e o nome puro,
 * como o `CancelarNfeRequest` já faz com `fiscal.inutilizar`.
 *
 * As listas de quem passa em cada rota ficam em constantes aqui, para a rota e o teste lerem
 * a mesma fonte. O dono do negócio (`Admin#{biz}`) passa pelo `Gate::before`.
 */
class PermissaoNfe
{
    /** Ver as notas de uma venda (lista, status, DANFE): quem já vê a venda ou a nota fiscal. */
    public const CONSULTA = 'nfebrasil.consult.view,nfebrasil.emit.manage,fiscal.nfe.view,sell.view,direct_sell.view,view_own_sell_only';

    /** Reenviar o DANFE por e-mail ao cliente: ato de quem emite. */
    public const REENVIAR = 'nfebrasil.emit.manage,fiscal.nfe.acoes';

    /** Ping na SEFAZ com o A1: quem chega na configuração fiscal. */
    public const TESTAR_CERTIFICADO = 'nfe.configuracao.manage,fiscal.config.edit,superadmin';

    /** Ligar a emissão automática e aplicar template: mesma permissão das outras mutações da tributação. */
    public const TRIBUTACAO = 'nfe.tributacao.manage';

    /** Lista de inutilizações: quem inutiliza ou consulta notas. */
    public const INUTILIZACOES = 'role:fiscal.inutilizar,nfebrasil.consult.view,fiscal.nfe.view';

    public function handle(Request $request, Closure $next, string ...$permissoes)
    {
        $user = $request->user();
        $businessId = (int) $request->session()->get('business.id', 0);

        foreach ($permissoes as $p) {
            if ($user === null) {
                break;
            }
            if (str_starts_with($p, 'role:')) {
                $papel = substr($p, 5);
                if (($businessId > 0 && $user->hasRole("{$papel}#{$businessId}")) || $user->hasRole($papel)) {
                    return $next($request);
                }

                continue;
            }
            if ($user->can($p)) {
                return $next($request);
            }
        }

        abort(403, 'Sem permissão para esta ação fiscal.');
    }
}
