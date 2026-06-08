<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\NfeBrasil\Http\Requests\UpsertRegraTributariaRequest;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;

/**
 * US-NFE-010 fase 2 · UI tributação (configuração default + regras NCM).
 *
 * Permissão: `nfe.tributacao.manage` (FormRequest::authorize +
 * `DataController::user_permissions`).
 *
 * Pattern: Inertia (status() = render; mutações = redirect+flash). ADR 0029.
 */
class TributacaoController extends Controller
{
    /**
     * GET /nfe-brasil/tributacao
     * Lista regras + mostra config default. Página principal.
     *
     * Wave 25 D3 — Inertia::defer aplicado em `regras` (paginate-style query DB
     * com map pesado) + `templates` (Service call lista de fixtures). Skill
     * `inertia-defer-default` (Tier B). `config` permanece eager — query single
     * leve (~1ms). Frontend wrap em <Deferred data="regras|templates"> com skeleton.
     */
    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('business.id');

        $config = NfeBusinessConfig::where('business_id', $businessId)->first();

        return Inertia::render('NfeBrasil/Tributacao/Index', [
            // EAGER (leve): config single-row + scalar
            'config'    => $config ? [
                'regime'                 => $config->regime,
                'tributacao_default'     => $config->tributacao_default,
                'auto_emission_enabled'  => (bool) $config->auto_emission_enabled,
            ] : null,

            // DEFERRED (pesado): query DB com map de N regras + scoped por tenant
            'regras'    => Inertia::defer(fn () => $this->buildRegrasPayload($businessId)),

            // DEFERRED (Service call): listagem de templates de fixtures
            'templates' => Inertia::defer(fn () => app(TributacaoTemplateService::class)->listar()),
        ]);
    }

    /**
     * Payload de regras tributárias (extraído pra defer). Wave 25 D3.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRegrasPayload(int $businessId): array
    {
        return NfeFiscalRule::where('business_id', $businessId)
            ->orderBy('ncm')
            ->orderBy('uf_origem')
            ->orderByRaw('uf_destino IS NULL DESC')
            ->orderBy('uf_destino')
            ->get([
                'id', 'ncm', 'uf_origem', 'uf_destino',
                'cfop', 'csosn', 'cst',
                'aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi',
                'mva', 'fcp',
                'created_at',
            ])
            ->map(fn ($r) => [
                'id'              => $r->id,
                'ncm'             => $r->ncm,
                'uf_origem'       => $r->uf_origem,
                'uf_destino'      => $r->uf_destino,
                'cfop'            => $r->cfop,
                'csosn'           => $r->csosn,
                'cst'             => $r->cst,
                'aliquota_icms'   => (float) $r->aliquota_icms,
                'aliquota_pis'    => (float) $r->aliquota_pis,
                'aliquota_cofins' => (float) $r->aliquota_cofins,
                'aliquota_ipi'    => (float) $r->aliquota_ipi,
                'mva'             => $r->mva !== null ? (float) $r->mva : null,
                'fcp'             => $r->fcp !== null ? (float) $r->fcp : null,
            ])
            ->toArray();
    }

    /**
     * POST /nfe-brasil/tributacao/auto-emission/toggle
     *
     * Per-business gate pra emissão automática (ADR 0093 multi-tenant Tier 0).
     * Tenant opt-in explícito antes de listeners dispatcharem Job.
     *
     * Validation inline: `enabled` é boolean obrigatório. Sem FormRequest
     * separado pra escopo enxuto (1 campo).
     *
     * Falha graciosamente se NfeBusinessConfig não existir — instrui Wagner
     * a aplicar template tributário primeiro (cria a row).
     */
    public function toggleAutoEmission(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');
        $enabled = (bool) $request->boolean('enabled');

        $config = NfeBusinessConfig::where('business_id', $businessId)->first();

        if (! $config) {
            return redirect()
                ->route('nfe-brasil.tributacao.index')
                ->with('error', 'Configure a tributação primeiro (aplique um template) antes de habilitar emissão automática.');
        }

        $config->update(['auto_emission_enabled' => $enabled]);

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->performedOn($config)
            ->withProperties([
                'business_id' => $businessId,
                'enabled'     => $enabled,
            ])
            ->log('auto_emission.toggled');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', $enabled
                ? 'Emissão automática habilitada neste tenant.'
                : 'Emissão automática desabilitada neste tenant.');
    }

    /**
     * POST /nfe-brasil/tributacao/templates/{slug}/aplicar
     *
     * Aplica template tributário pré-configurado no business — cria/atualiza
     * `nfe_business_configs` (regime + tributacao_default). Não toca em
     * regras NCM existentes (`nfe_fiscal_rules`).
     */
    public function aplicarTemplate(Request $request, string $slug): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        try {
            $resultado = app(TributacaoTemplateService::class)->aplicar($businessId, $slug);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $resultado['criou']
            ? 'Template aplicado — configuração tributária criada.'
            : ($resultado['mudou']
                ? 'Template aplicado — configuração tributária atualizada.'
                : 'Template já estava aplicado — nada a fazer.');

        return redirect()->route('nfe-brasil.tributacao.index')->with('success', $msg);
    }

    /** GET /nfe-brasil/tributacao/regras/create */
    public function create(): Response
    {
        return Inertia::render('NfeBrasil/Tributacao/RegraForm', [
            'regra' => null,
        ]);
    }

    /** POST /nfe-brasil/tributacao/regras */
    public function store(UpsertRegraTributariaRequest $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        NfeFiscalRule::create(array_merge(
            $request->validated(),
            ['business_id' => $businessId],
        ));

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $businessId,
                'ncm'         => $request->input('ncm'),
                'uf_origem'   => $request->input('uf_origem'),
                'uf_destino'  => $request->input('uf_destino'),
            ])
            ->log('regra.created');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', 'Regra tributária criada.');
    }

    /** GET /nfe-brasil/tributacao/regras/{id}/edit */
    public function edit(Request $request, int $id): Response
    {
        $businessId = (int) $request->session()->get('business.id');

        $regra = NfeFiscalRule::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('NfeBrasil/Tributacao/RegraForm', [
            'regra' => [
                'id'              => $regra->id,
                'ncm'             => $regra->ncm,
                'uf_origem'       => $regra->uf_origem,
                'uf_destino'      => $regra->uf_destino,
                'cfop'            => $regra->cfop,
                'csosn'           => $regra->csosn,
                'cst'             => $regra->cst,
                'aliquota_icms'   => (float) $regra->aliquota_icms,
                'aliquota_pis'    => (float) $regra->aliquota_pis,
                'aliquota_cofins' => (float) $regra->aliquota_cofins,
                'aliquota_ipi'    => (float) $regra->aliquota_ipi,
                'mva'             => $regra->mva !== null ? (float) $regra->mva : null,
                'fcp'             => $regra->fcp !== null ? (float) $regra->fcp : null,
            ],
        ]);
    }

    /** PUT /nfe-brasil/tributacao/regras/{id} */
    public function update(UpsertRegraTributariaRequest $request, int $id): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        $regra = NfeFiscalRule::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        $regra->update($request->validated());

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->performedOn($regra)
            ->withProperties(['business_id' => $businessId])
            ->log('regra.updated');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', 'Regra tributária atualizada.');
    }

    /** DELETE /nfe-brasil/tributacao/regras/{id} */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        $regra = NfeFiscalRule::where('business_id', $businessId)
            ->where('id', $id)
            ->firstOrFail();

        $regra->delete();

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->performedOn($regra)
            ->withProperties(['business_id' => $businessId])
            ->log('regra.deleted');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', 'Regra tributária removida.');
    }
}
