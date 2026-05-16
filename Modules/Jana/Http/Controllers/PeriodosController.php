<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Http\Requests\StorePeriodoRequest;
use Modules\Jana\Http\Requests\UpdatePeriodoRequest;

/** STUB spec-ready: CRUD de períodos aninhado em meta. */
class PeriodosController extends Controller
{
    public function store(StorePeriodoRequest $request, $metaId)
    {
        MetaPeriodo::create(array_merge($request->validated(), ['meta_id' => $metaId]));

        return redirect()->route('jana.metas.show', $metaId);
    }

    public function update(UpdatePeriodoRequest $request, $metaId, $id)
    {
        $periodo = MetaPeriodo::where('meta_id', $metaId)->findOrFail($id);
        $periodo->update($request->validated());
        return redirect()->route('jana.metas.show', $metaId);
    }

    public function destroy($metaId, $id)
    {
        MetaPeriodo::where('meta_id', $metaId)->findOrFail($id)->delete();
        return redirect()->route('jana.metas.show', $metaId);
    }
}
