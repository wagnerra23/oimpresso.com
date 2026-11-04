<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Services\Fiscal\FilaFiscalDoDia;

/**
 * Documentos fiscais do dia (US-FISIO-023): NFS-e (clínica CNPJ) e fila do Receita Saúde
 * (autônomo CPF). Leitura do núcleo; a única escrita é a marca de envio do recibo.
 */
class FiscalController extends Controller
{
    public function __construct(private readonly FilaFiscalDoDia $fila) {}

    public function index(Request $request): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $dia = $request->date('dia')?->toDateString() ?? now()->toDateString();

        return Inertia::render('Fisio/Fiscal/Index', [
            'dia' => $dia,
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'notas' => Inertia::defer(fn () => $this->fila->notas($biz, $dia)),
            'recibos' => Inertia::defer(fn () => $this->fila->recibos($biz, $dia)),
        ]);
    }

    public function marcarEnviado(Request $request, int $payment): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        abort_unless($this->fila->pagamentoDePaciente($biz, $payment), 404);
        $numero = $request->validate(['receipt_number' => ['nullable', 'string', 'max:40']])['receipt_number'] ?? null;

        $this->fila->marcarEnviado($biz, $payment, $numero, (int) $request->user()->id);

        return back()->with('success', 'Recibo marcado como enviado.');
    }
}
