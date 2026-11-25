<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Http\Requests\ImportRegrasCsvRequest;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvConferencia;
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
    /** Chave de sessão com o business em que o preview foi conferido (UC-NFIM-04). */
    private const SESSAO_BUSINESS = ImportRegrasCsvConferencia::SESSAO_BUSINESS;

    /** Chave de sessão com a referência do lote conferido — as linhas ficam em arquivo (UC-NFIM-15). */
    private const SESSAO_REF = ImportRegrasCsvConferencia::SESSAO_REF;

    public function __construct(
        private readonly ImportRegrasCsvService $service,
        private readonly ImportRegrasCsvConferencia $conferencia,
    ) {}

    /** GET /nfe-brasil/tributacao/import */
    public function show(): Response
    {
        return Inertia::render('NfeBrasil/Tributacao/ImportCsv', [
            'colunas_obrigatorias' => ImportRegrasCsvService::COLUNAS_OBRIGATORIAS,
            // UC-NFIM-12 · o resumo da conferência vem do flash do `preview()`. O middleware do
            // Inertia não compartilha a chave `preview`, então ela vai como prop da página.
            'preview' => session('preview'),
        ]);
    }

    /** POST /nfe-brasil/tributacao/import/preview */
    public function preview(ImportRegrasCsvRequest $request): RedirectResponse
    {
        $resultado = $this->service->parse($request->file('arquivo'));
        $businessId = (int) $request->session()->get('business.id');

        // As validadas vão para um arquivo e a sessão guarda só a referência (UC-NFIM-15): um CSV de
        // 5 MB virava 30,9 MB de sessão, lida e regravada a cada request. O carimbo do business
        // segue na sessão porque o aplicar acontece noutro request e o confere (UC-NFIM-04).
        $ref = $this->conferencia->guardar($businessId, (int) $request->user()->id, $resultado['linhas']);
        session()->put(self::SESSAO_REF, $ref);
        session()->put(self::SESSAO_BUSINESS, $businessId);

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
        $userId = (int) $request->user()->id;
        $ref = session(self::SESSAO_REF);

        if (! is_string($ref) || $ref === '') {
            return redirect()
                ->route('nfe-brasil.tributacao.import.show')
                ->withErrors(['arquivo' => 'Nenhuma linha válida — faça upload e preview primeiro.']);
        }

        // UC-NFIM-04 · as linhas só gravam no business em que foram conferidas. Empresa trocada
        // entre o preview e o aplicar (ou carimbo ausente) = recusa e descarta o lote.
        $businessDoPreview = (int) session(self::SESSAO_BUSINESS, 0);
        if ($businessDoPreview <= 0 || $businessDoPreview !== $businessId) {
            if ($businessDoPreview > 0) {
                $this->conferencia->descartar($businessDoPreview, $userId, $ref);
            }
            session()->forget([self::SESSAO_REF, self::SESSAO_BUSINESS]);

            return redirect()
                ->route('nfe-brasil.tributacao.import.show')
                ->withErrors(['arquivo' => 'O arquivo foi conferido em outra empresa. Confira de novo nesta empresa antes de aplicar.']);
        }

        // O caminho do lote é montado com o business e o usuário de quem aplica: lote conferido
        // noutra empresa não é achado aqui, mesmo que o carimbo acima falhasse.
        $linhas = $this->conferencia->ler($businessId, $userId, $ref);

        if (empty($linhas)) {
            session()->forget([self::SESSAO_REF, self::SESSAO_BUSINESS]);

            return redirect()
                ->route('nfe-brasil.tributacao.import.show')
                ->withErrors(['arquivo' => 'A conferência expirou ou já foi aplicada — faça upload e preview de novo.']);
        }

        $resumo = $this->service->aplicar($businessId, $linhas);

        $this->conferencia->descartar($businessId, $userId, $ref);
        session()->forget([self::SESSAO_REF, self::SESSAO_BUSINESS]);

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
