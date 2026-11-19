<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;
use Modules\ComunicacaoVisual\Services\ProtocoloInstalacao;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * InstalacaoCampoController — TELA do instalador no celular (US-COMVIS-007 · UC-PCV-25).
 *
 *   GET /comunicacao-visual/instalacoes/{id}           → ComunicacaoVisual/Instalacao
 *   GET /comunicacao-visual/instalacoes/{id}/protocolo → PDF do protocolo (só instalação concluída; senão 422)
 *
 * A tela só LÊ por aqui. Foto, assinatura e concluir vão pela API do comprovante
 * (InstalacaoComprovanteController), que é quem decide o que pode — a tela mostra a recusa.
 *
 * Props (todas eager: é uma instalação só, e a tela no celular precisa de tudo no primeiro paint):
 *   - instalacao    OS, cliente, endereço do cliente, início/fim, equipe, veículo e estado
 *   - comprovante   ComprovanteInstalacao::estado (o que já foi colhido e se pode concluir)
 *   - podeRegistrar comvis.os.update_status (ou superadmin) — a API confere de novo
 *
 * Tier 0 (ADR 0093): business_id vem da sessão; instalação de outra empresa = 404.
 */
class InstalacaoCampoController extends Controller
{
    public function __construct(
        private readonly ComprovanteInstalacao $comprovante,
        private readonly ProtocoloInstalacao $protocolo,
    ) {}

    public function show(int $id): Response
    {
        $user = auth()->user();
        $superadmin = $this->exigirLeitura();

        $businessId = (int) (session('user.business_id') ?? session('business.id'));
        try {
            $instalacao = $this->comprovante->cabecalho($businessId, $id);
            $comprovante = $this->comprovante->estado($businessId, $id);
        } catch (ModelNotFoundException) {
            abort(404, 'Instalação não encontrada.');
        }

        return Inertia::render('ComunicacaoVisual/Instalacao', [
            'instalacao' => $instalacao,
            'comprovante' => $comprovante,
            'podeRegistrar' => $superadmin || (bool) $user?->can('comvis.os.update_status'),
        ]);
    }

    public function protocolo(int $id): HttpResponse
    {
        $this->exigirLeitura();
        $businessId = (int) (session('user.business_id') ?? session('business.id'));
        try {
            $pdf = $this->protocolo->pdf($businessId, $id);
            $os = $this->comprovante->cabecalho($businessId, $id)['os_numero'] ?? (string) $id;
        } catch (ModelNotFoundException) {
            abort(404, 'Instalação não encontrada.');
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return $pdf->stream('protocolo-instalacao-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $os).'.pdf');
    }

    /** 403 sem comvis.os.view nem comvis.os.update_status; devolve se é superadmin. */
    private function exigirLeitura(): bool
    {
        $user = auth()->user();
        $superadmin = $user !== null && $user->can('superadmin');
        if (! $superadmin && ! ($user?->can('comvis.os.view') || $user?->can('comvis.os.update_status'))) {
            abort(403, 'Sem permissão na instalação (comvis.os.view ou comvis.os.update_status).');
        }

        return $superadmin;
    }
}
