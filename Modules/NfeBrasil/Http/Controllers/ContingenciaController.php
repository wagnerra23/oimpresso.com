<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\NfeBrasil\Exceptions\ContingenciaException;
use Modules\NfeBrasil\Http\Requests\AtivarContingenciaRequest;
use Modules\NfeBrasil\Services\ContingenciaService;

/**
 * US-NFE-006 / ADR TECH-0002 — liga/desliga contingência do tenant.
 *
 * O controller é fino de propósito: toda a regra (motivo obrigatório, preservar o
 * relógio de ativação, audit log) vive no ContingenciaService, que também é chamável
 * de command/job. Aqui só traduz HTTP ⇄ domínio.
 */
class ContingenciaController extends Controller
{
    public function __construct(private readonly ContingenciaService $contingencia) {}

    public function ativar(AtivarContingenciaRequest $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        try {
            $this->contingencia->ativar($businessId, (string) $request->input('motivo'), $request->user());
        } catch (ContingenciaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Contingência ATIVADA. As próximas notas saem em contingência e ficam aguardando transmissão — '
            . 'desative assim que a SEFAZ voltar.'
        );
    }

    public function desativar(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('nfe.contingencia.manage') ?? false, 403);

        $businessId = (int) $request->session()->get('business.id');

        try {
            $this->contingencia->desativar($businessId, $request->user());
        } catch (ContingenciaException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Não prometer o que não foi feito: desativar só muda as PRÓXIMAS emissões.
        return back()->with(
            'success',
            'Contingência desativada — as próximas notas voltam a ser transmitidas na hora. '
            . 'As emitidas em contingência seguem na fila de retransmissão.'
        );
    }
}
