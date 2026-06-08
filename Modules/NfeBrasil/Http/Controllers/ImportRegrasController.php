<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Http\Requests\ImportRegrasCsvRequest;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

/**
 * US-NFE-010 fase 3 · UI Import CSV de regras tributárias.
 *
 * Fluxo 2-step:
 *   1. show() → form upload
 *   2. preview() → parse CSV + retorna linhas válidas + erros (sem persistir)
 *   3. aplicar() → persiste linhas válidas (idempotente)
 *
 * Permissão: `nfe.tributacao.manage`.
 */
class ImportRegrasController extends Controller
{
    public function __construct(private readonly ImportRegrasCsvService $service) {}

    /** GET /nfe-brasil/tributacao/import */
    public function show(): Response
    {
        return Inertia::render('NfeBrasil/Tributacao/ImportCsv', [
            'colunas_obrigatorias' => ImportRegrasCsvService::COLUNAS_OBRIGATORIAS,
        ]);
    }

    /** POST /nfe-brasil/tributacao/import/preview */
    public function preview(ImportRegrasCsvRequest $request): RedirectResponse
    {
        $resultado = $this->service->parse($request->file('arquivo'));

        // Salva validadas em session pra apply chamar sem re-upload
        session()->put('nfe_import_csv_linhas', $resultado['linhas']);

        return redirect()
            ->route('nfe-brasil.tributacao.import.show')
            ->with('preview', [
                'total_validas'   => count($resultado['linhas']),
                'total_erros'     => count($resultado['erros']),
                'amostras'        => array_slice($resultado['linhas'], 0, 10),
                'erros'           => array_slice($resultado['erros'], 0, 20),
            ]);
    }

    /** POST /nfe-brasil/tributacao/import/aplicar */
    public function aplicar(Request $request): RedirectResponse
    {
        if (! $request->user()?->can('nfe.tributacao.manage')) {
            abort(403);
        }

        $businessId = (int) $request->session()->get('business.id');
        $linhas = (array) session('nfe_import_csv_linhas', []);

        if (empty($linhas)) {
            return redirect()
                ->route('nfe-brasil.tributacao.import.show')
                ->withErrors(['arquivo' => 'Nenhuma linha válida — faça upload e preview primeiro.']);
        }

        $resumo = $this->service->aplicar($businessId, $linhas);

        session()->forget('nfe_import_csv_linhas');

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->withProperties(['business_id' => $businessId] + $resumo)
            ->log('regras.import_csv');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', sprintf(
                'Import concluído: %d criadas, %d atualizadas, %d falharam.',
                $resumo['criadas'],
                $resumo['atualizadas'],
                $resumo['falhas'],
            ));
    }
}
