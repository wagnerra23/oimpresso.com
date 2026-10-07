<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Http\Requests\DestroyRegraTributariaRequest;
use Modules\NfeBrasil\Http\Requests\SimularTributacaoRequest;
use Modules\NfeBrasil\Http\Requests\UpsertRegraTributariaRequest;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;
use RuntimeException;

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

            // EAGER (1 linha): local padrão pro autocomplete de produto do simulador (thread 08).
            'localPadrao' => DB::table('business_locations')
                ->where('business_id', $businessId)->orderBy('id')->value('id'),

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
            // Thread 07: versões encerradas (valida_ate no passado) ficam de fora da lista — são
            // o histórico que explica notas antigas, não regras a editar.
            ->when(NfeFiscalRule::temVersionamento(), fn ($q) => $q->vigenteEm(now()->toDateString()))
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
            // O NCM padrão escolhido no drawer "Configurar pelo certificado" (thread 22). Sem ele,
            // o serviço cai no NCM que a empresa já tem — e o que a tela mostrou não seria o aplicado.
            $ncm = $request->input('ncm_default');
            $resultado = app(TributacaoTemplateService::class)->aplicar(
                $businessId,
                $slug,
                is_string($ncm) && $ncm !== '' ? $ncm : null,
            );
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

        $regra = $this->regraEditavel($businessId, $id);

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
                // Reforma tributária (US-FISCAL-021 · thread 05). O form agora envia os 5 campos;
                // sem eles na prop, a edição abriria a seção vazia e o "Atualizar" gravaria nulo
                // por cima do que estava no banco (UC-NFRF-08).
                'c_class_trib'    => $regra->c_class_trib,
                'cst_ibs'         => $regra->cst_ibs,
                'cst_cbs'         => $regra->cst_cbs,
                'aliquota_ibs'    => (float) $regra->aliquota_ibs, // NOT NULL default 0
                'aliquota_cbs'    => (float) $regra->aliquota_cbs, // NOT NULL default 0
            ],
        ]);
    }

    /** PUT /nfe-brasil/tributacao/regras/{id} */
    public function update(UpsertRegraTributariaRequest $request, int $id): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        $regra = $this->regraEditavel($businessId, $id);

        // R-NFE-019 · editar gera versão nova; a antiga só ganha `valida_ate` e continua explicando
        // as notas emitidas com ela. Sem a migração da thread 07 (schema de teste), edita no lugar.
        if (NfeFiscalRule::temVersionamento()) {
            $regra = $regra->novaVersao($request->validated());
        } else {
            $regra->update($request->validated());
        }

        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->performedOn($regra)
            ->withProperties(['business_id' => $businessId])
            ->log('regra.updated');

        return redirect()
            ->route('nfe-brasil.tributacao.index')
            ->with('success', 'Regra tributária atualizada.');
    }

    /**
     * A regra do tenant (404 se for de outro — ADR 0093) e ainda vigente. Versão encerrada é
     * histórico: editá-la abriria uma segunda versão em paralelo com a atual.
     */
    private function regraEditavel(int $businessId, int $id): NfeFiscalRule
    {
        return NfeFiscalRule::where('business_id', $businessId)
            ->where('id', $id)
            ->when(
                NfeFiscalRule::temVersionamento(),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', now()->toDateString())),
            )
            ->firstOrFail();
    }

    /**
     * GET /nfe-brasil/tributacao/simular — prévia read-only (playbook Fiscal thread 08 · D-SIM).
     *
     * NÃO calcula nada próprio: monta a mesma linha que a emissão monta para um item e chama
     * `NfeService::montarItensNfe`, a função que a NF-e/NFC-e usa (UC-NFTR-08). Nada é gravado.
     *
     * Só os insumos que a emissão de hoje usa: produto (o NCM vem do cadastro), quantidade, valor
     * e UF de destino. Operação, destinatário e regime não entram — a emissão ainda não os passa ao
     * motor, e mostrá-los aqui faria a prévia divergir da nota.
     *
     * Tier 0: o produto é buscado com `business_id` da sessão — de outra empresa é 404 (UC-NFTR-09).
     */
    public function simular(SimularTributacaoRequest $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        $produto = DB::table('products')
            ->where('business_id', $businessId)
            ->where('id', (int) $request->validated('product_id'))
            ->first(['id', 'name', 'sku', 'ncm']);
        abort_if($produto === null, 404);

        $business = DB::table('business')->where('id', $businessId)->first(['id', 'ncm_padrao']);
        $config   = NfeBusinessConfig::where('business_id', $businessId)->first();

        // Mesmas duas leituras de `NfeService::emitirParaTransaction` (NCM padrão) e
        // `NfeService::resolverUF` (privado). A igualdade é travada por teste (UC-NFTR-08).
        $ncmDefault = (string) ($config?->tributacao_default['ncm_default'] ?? $business?->ncm_padrao ?? '');
        $estado     = DB::table('business_locations')->where('business_id', $businessId)->orderBy('id')->value('state') ?? '';
        $ufOrigem   = preg_match('/^[A-Z]{2}$/', (string) $estado) ? (string) $estado : 'SP';
        $ufDestino  = (string) $request->validated('uf_destino');

        $qtd = (float) $request->validated('quantidade');
        $vun = (float) $request->validated('valor_unitario');

        try {
            $r = app(NfeService::class)->montarItensNfe(
                linhas: [[
                    'sell_line_id'   => null,
                    'product_id'     => (int) $produto->id,
                    'cprod'          => (string) ($produto->sku ?: $produto->id),
                    'xprod'          => (string) $produto->name,
                    'ncm'            => (string) ($produto->ncm ?? ''),
                    'unidade'        => 'UN',
                    'quantidade'     => $qtd,
                    'valor_unitario' => $vun,
                ]],
                valorNota:  round($qtd * $vun, 2),
                frete:      0.0,
                ncmDefault: $ncmDefault,
                motor:      app(MotorTributarioService::class),
                businessId: $businessId,
                ufOrigem:   $ufOrigem,
                ufDestino:  $ufDestino,
            );
        } catch (NcmObrigatorioException|TributacaoNaoConfiguradaException|RuntimeException $e) {
            return response()->json(['bloqueio' => $e->getMessage()], 422);
        }

        $det = $r['dets'][0];
        $base = (float) $det['ibscbs']['vbc'];

        return response()->json([
            'produto'         => ['id' => (int) $produto->id, 'nome' => (string) $produto->name],
            'ncm'             => $det['ncm'],
            'ncm_padrao_usado' => $r['metadata'] !== null,
            'cfop'            => $det['cfop'],
            'nivel'           => $det['nivel_tributacao'],
            'uf_origem'       => $ufOrigem,
            'uf_destino'      => $ufDestino,
            'base'            => $base,
            'tributos'        => [
                ['tributo' => 'ICMS', 'aliquota' => $det['icms']['picms'], 'valor' => $det['icms']['vicms'], 'codigo' => $det['icms']['cst_csosn']],
                ['tributo' => 'PIS', 'aliquota' => $det['pis']['ppis'], 'valor' => $det['pis']['vpis'], 'codigo' => $det['pis']['cst']],
                ['tributo' => 'COFINS', 'aliquota' => $det['cofins']['pcofins'], 'valor' => $det['cofins']['vcofins'], 'codigo' => $det['cofins']['cst']],
                ['tributo' => 'IBS', 'aliquota' => $det['ibscbs']['aliquota_ibs'], 'valor' => $det['ibscbs']['valor_ibs'], 'codigo' => $det['ibscbs']['cst']],
                ['tributo' => 'CBS', 'aliquota' => $det['ibscbs']['aliquota_cbs'], 'valor' => $det['ibscbs']['valor_cbs'], 'codigo' => $det['ibscbs']['cst_cbs']],
            ],
            'total_destacado' => round(
                $r['total']['v_icms'] + $r['total']['v_pis'] + $r['total']['v_cofins'] + $r['total']['v_ibs'] + $r['total']['v_cbs'],
                2,
            ),
        ]);
    }

    /** DELETE /nfe-brasil/tributacao/regras/{id} */
    public function destroy(DestroyRegraTributariaRequest $request, int $id): RedirectResponse
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
