<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Http\Requests\StorePeriodoRequest;
use Modules\Jana\Http\Requests\UpdatePeriodoRequest;

/** STUB spec-ready: CRUD de períodos aninhado em meta. */
class PeriodosController extends Controller
{
    /**
     * Gate Tier 0 (ADR 0093): valida que a meta pai é do tenant ANTES de tocar
     * o filho. `Meta` tem HasBusinessScope, então `findOrFail` de meta_id
     * cross-tenant 404 antes de qualquer escrita. Explícito, não confia só no
     * backstop ScopeByBusinessViaParent (que não cobre INSERT — só SELECT).
     * É o gate que os FormRequests (Store/UpdatePeriodoRequest) já documentavam
     * mas o controller nunca executava. Fecha IDOR cross-tenant (follow-up #4474).
     */
    private function assertMetaDoTenant($metaId): void
    {
        Meta::findOrFail($metaId);
    }

    public function store(StorePeriodoRequest $request, $metaId)
    {
        $this->assertMetaDoTenant($metaId);
        MetaPeriodo::create(array_merge($request->validated(), ['meta_id' => $metaId]));

        return redirect()->route('jana.metas.show', $metaId);
    }

    public function update(UpdatePeriodoRequest $request, $metaId, $id)
    {
        $this->assertMetaDoTenant($metaId);
        $periodo = MetaPeriodo::where('meta_id', $metaId)->findOrFail($id);
        $periodo->update($request->validated());
        return redirect()->route('jana.metas.show', $metaId);
    }

    public function destroy($metaId, $id)
    {
        $this->assertMetaDoTenant($metaId);
        MetaPeriodo::where('meta_id', $metaId)->findOrFail($id)->delete();
        return redirect()->route('jana.metas.show', $metaId);
    }
}
