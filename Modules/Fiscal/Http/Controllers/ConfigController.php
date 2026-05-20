<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * Cert/Cfg fiscal (sub-página 6 do design KB-9.75).
 *
 * Status do certificado A1 + ambiente (homolog/prod) + regime tributário
 * + tributação default (NCM/CST/CSOSN cascata).
 *
 * Apenas leitura no PR. Edits via Modules/NfeBrasil/Pages/NfeBrasil/Configuracao/Certificado.tsx
 * existente. HasBusinessScope ADR 0093.
 */
class ConfigController extends Controller
{
    public function index(): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.config.edit')) {
            abort(403, 'Sem permissão fiscal.config.edit');
        }

        $cert = NfeCertificado::query()
            ->where('ativo', true)
            ->orderByDesc('valido_ate')
            ->first();

        $config = NfeBusinessConfig::query()->first();

        return Inertia::render('Fiscal/Config', [
            'certificado' => $cert ? [
                'uuid'         => $cert->uuid,
                'cnpjTitular'  => $cert->cnpj_titular,
                'validoAteIso' => $cert->valido_ate?->toIso8601String(),
                'validoAteBr'  => $cert->valido_ate?->format('d/m/Y'),
                'diasRestantes'=> $cert->valido_ate
                    ? (int) now()->startOfDay()->diffInDays($cert->valido_ate, false)
                    : null,
                'ativo'        => (bool) $cert->ativo,
            ] : null,
            'config' => $config ? [
                'regime'             => $config->regime ?? 'lucro_presumido',
                'autoEmissionEnabled'=> (bool) ($config->auto_emission_enabled ?? false),
                'tributacaoDefault'  => $config->tributacao_default ?? [],
            ] : null,
        ]);
    }
}
