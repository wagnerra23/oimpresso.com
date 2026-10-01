<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Arquivos\Http\Requests\RetentionRunRequest;
use Modules\Arquivos\Jobs\SimularRetencaoJob;

/**
 * Simular a retenção — onda 3 · PR-8 (thread 04 do playbook Arquivos). D4: sem purge pela UI.
 *
 * `POST arquivos/retencao/simular` → `RetentionRunRequest`. Este controller:
 *   - RECUSA `purge` (D4) — erro na sessão, nada despachado;
 *   - FORÇA `dry_run = true`, ignorando o que vier no request (canário: trocar o `true`
 *     abaixo por `false` reprova o teste UC-INDEX-09);
 *   - despacha `SimularRetencaoJob` DEPOIS da resposta — a simulação nunca roda no request.
 *
 * Vive fora do `ArquivosAdminController` de propósito: aquele arquivo tem um assert
 * (UC-INDEX-01) que proíbe `dispatch(` nele inteiro, e a tela do acervo segue sem enfileirar.
 *
 * Multi-tenant Tier 0 (ADR 0093): o business vem da SESSÃO, nunca do request.
 */
class RetencaoSimulacaoController extends Controller
{
    public function simular(RetentionRunRequest $request): RedirectResponse
    {
        if ($request->boolean('purge')) {
            return back()->withErrors([
                'purge' => 'A tela só simula: apagar de verdade não existe aqui (decisão D4).',
            ]);
        }

        $businessId = (int) $request->session()->get('user.business_id');

        SimularRetencaoJob::dispatchAfterResponse(
            $businessId,
            (int) $request->input('retention_days'),
            true,
            false,
            $request->user()?->id,
            $request->input('batch_tag'),
        );

        return back()->with('status', [
            'success' => true,
            'msg'     => 'Simulação pedida — nada foi apagado. O relatório fica pronto em instantes.',
        ]);
    }
}
