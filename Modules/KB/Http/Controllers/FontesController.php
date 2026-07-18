<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;

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
        // Gate Tier 0 (ADR 0093): valida que a meta é do tenant ANTES de gravar
        // a fonte. Meta usa HasBusinessScope (business_id global scope), então
        // findOrFail de meta_id cross-tenant 404. Sem isto,
        // `MetaFonte::updateOrCreate(['meta_id' => $metaId])` grava
        // driver+config_json na meta de OUTRO business (o backstop via parent
        // não cobre o INSERT do updateOrCreate) → injeção de `driver:sql`
        // cross-tenant que roda na apuração. Espelha o gate que o próprio show()
        // já faz. Fecha IDOR (follow-up #4474).
        Meta::findOrFail($metaId);

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

        return redirect()->route('jana.metas.show', $metaId);
    }
}
