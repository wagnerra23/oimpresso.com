<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers\Concerns;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;

/**
 * Empresa da sessão com o Producao no pacote (auditoria lote 2.4, P-03 do eixo 5). Os cadastros e a
 * etiqueta da OS só checavam a permissão Spatie: a empresa que saiu do pacote seguia gravando roteiro,
 * posto e custo-hora. Mesma regra dos outros 13 controllers do módulo. Superadmin passa.
 */
trait ExigePacoteProducao
{
    protected function empresaComPacote(Request $request): int
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin')
            || app(ModuleUtil::class)->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode, 403);

        return $biz;
    }
}
