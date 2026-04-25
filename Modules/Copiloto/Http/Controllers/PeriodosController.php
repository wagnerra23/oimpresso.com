<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Copiloto\Entities\MetaPeriodo;

/** STUB spec-ready: CRUD de períodos aninhado em meta. */
class PeriodosController extends Controller
{
    public function store(Request $request, $metaId)
    {
        $data = $request->validate([
            'tipo_periodo' => 'required|in:mes,trim,ano,custom',
            'data_ini'     => 'required|date',
            'data_fim'     => 'required|date|after_or_equal:data_ini',
            'valor_alvo'   => 'required|numeric',
            'trajetoria'   => 'nullable|in:linear,sazonal,exponencial,manual',
        ]);

        MetaPeriodo::create(array_merge($data, ['meta_id' => $metaId]));

        return redirect()->route('copiloto.metas.show', $metaId);
    }

    public function update(Request $request, $metaId, $id)
    {
        $periodo = MetaPeriodo::where('meta_id', $metaId)->findOrFail($id);
        $periodo->update($request->only(['tipo_periodo', 'data_ini', 'data_fim', 'valor_alvo', 'trajetoria']));
        return redirect()->route('copiloto.metas.show', $metaId);
    }

    public function destroy($metaId, $id)
    {
        MetaPeriodo::where('meta_id', $metaId)->findOrFail($id)->delete();
        return redirect()->route('copiloto.metas.show', $metaId);
    }
}
