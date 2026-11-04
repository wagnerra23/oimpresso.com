<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Http\Requests\UpsertConfigDefaultRequest;
use Modules\NfeBrasil\Models\NfeBusinessConfig;

/**
 * US-NFE-010 fase 2 · Config default do business — alimenta cascade Nível 4
 * do MotorTributarioService (ADR ARQ-0006).
 *
 * Permissão: `nfe.tributacao.manage`.
 */
class ConfigDefaultController extends Controller
{
    /** GET /nfe-brasil/tributacao/config-default */
    public function show(Request $request): Response
    {
        $businessId = (int) $request->session()->get('business.id');

        $config = NfeBusinessConfig::where('business_id', $businessId)->first();

        return Inertia::render('NfeBrasil/Tributacao/ConfigDefault', [
            'config' => $config ? [
                'regime' => $config->regime,
                'tributacao_default' => $config->tributacao_default,
            ] : [
                'regime' => 'simples',
                'tributacao_default' => [
                    'ncm_default'     => '00000000',
                    'cfop_default'    => '5102',
                    'csosn'           => '102',
                    'aliquota_icms'   => 0.0,
                    'aliquota_pis'    => 0.0,
                    'aliquota_cofins' => 0.0,
                ],
            ],
        ]);
    }

    /** POST /nfe-brasil/tributacao/config-default */
    public function upsert(UpsertConfigDefaultRequest $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');
        $data = $request->validated();

        $tributacao = [
            'ncm_default'     => $data['ncm_default'],
            'cfop_default'    => $data['cfop_default'],
            'cfop'            => $data['cfop_default'], // alias usado pelo motor
            'aliquota_icms'   => (float) $data['aliquota_icms'],
            'aliquota_pis'    => (float) $data['aliquota_pis'],
            'aliquota_cofins' => (float) $data['aliquota_cofins'],
        ];

        if (! empty($data['csosn'])) {
            $tributacao['csosn'] = $data['csosn'];
        }
        if (! empty($data['cst'])) {
            $tributacao['cst'] = $data['cst'];
        }
        if (isset($data['aliquota_ipi'])) {
            $tributacao['aliquota_ipi'] = (float) $data['aliquota_ipi'];
        }
        // Sabor Brasil 2026-11-04: marca de substituto — só alimenta os avisos de CST/CFOP
        // (CoerenciaFiscalDoItem); o cálculo da nota não lê esta chave.
        $tributacao['substituto_tributario'] = (bool) ($data['substituto_tributario'] ?? false);

        NfeBusinessConfig::updateOrCreate(
            ['business_id' => $businessId],
            [
                'regime'             => $data['regime'],
                'tributacao_default' => $tributacao,
            ],
        );

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $businessId,
                'regime'      => $data['regime'],
                'ncm_default' => $data['ncm_default'],
            ])
            ->log('config_default.upserted');

        $crt = in_array($data['regime'], ['lucro_presumido', 'lucro_real'], true) ? 3 : 1;
        $avisos = \Modules\NfeBrasil\Services\Tributacao\CoerenciaFiscalDoItem::avisos(
            $crt, $data['cfop_default'], $data['cst'] ?? null, $data['csosn'] ?? null, null,
            $tributacao['substituto_tributario'],
        );

        $resp = redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', 'Configuração default salva.');

        return $avisos === [] ? $resp : $resp->with('info', 'Salva, mas confira: '.implode(' ', $avisos));
    }
}
