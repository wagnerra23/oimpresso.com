<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Copiloto\Entities\Meta;
use Modules\Copiloto\Entities\MetaFonte;

/**
 * STUB spec-ready: configuração da fonte de apuração da meta.
 * Permissão `copiloto.fontes.edit` exigida (ver ARCHITECTURE.md seção 7).
 */
class FontesController extends Controller
{
    public function show($metaId)
    {
        $meta = Meta::findOrFail($metaId);
        return view('copiloto::fontes.show', compact('meta'));
    }

    public function update(Request $request, $metaId)
    {
        $data = $request->validate([
            'driver'      => 'required|in:sql,php,http',
            'config_json' => 'required|array',
            'cadencia'    => 'required|in:diaria,horaria,manual',
        ]);

        // TODO: validações por driver (ver adr/tech/0001-drivers-apuracao-plugaveis.md):
        //  - sql: precisa começar com SELECT/WITH; binds obrigatórios
        //  - php: callable precisa estar em allowlist
        //  - http: url HTTPS em produção

        MetaFonte::updateOrCreate(['meta_id' => $metaId], $data);

        return redirect()->route('copiloto.metas.show', $metaId);
    }
}
