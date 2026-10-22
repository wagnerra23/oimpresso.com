<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Http\Requests\AprovarOrcamentoRequest;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;

/**
 * OrcamentoController — API JSON de orçamentos de comunicação visual.
 *
 * Sprint 1 — US-COMVIS-001: cálculo m² authoritative server-side.
 *
 * Endpoints:
 *   POST /comunicacao-visual/api/calcular       → preview sem persistência
 *   GET  /comunicacao-visual/api/orcamentos     → últimos orçamentos da empresa (reabrir)
 *   POST /comunicacao-visual/api/orcamentos     → persiste + retorna 201
 *   GET  /comunicacao-visual/api/orcamentos/{id} → retorna com itens
 *   PUT  /comunicacao-visual/api/orcamentos/{id} → regrava o orçamento reaberto (sem OS)
 *   POST /comunicacao-visual/api/orcamentos/{id}/aprovar → aprova e cria a OS (UC-FLX-01)
 *
 * Princípio server-side authoritative: valores area_m2 / subtotal / total vindos
 * do frontend são DESCARTADOS. O Service recalcula tudo antes de persistir.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id resolvido via session('user.business_id') — nunca do input.
 *
 * @see Modules\ComunicacaoVisual\Services\OrcamentoCalculator
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class OrcamentoController extends Controller
{
    /** Status em que o orçamento reaberto ainda pode ser regravado. */
    public const EDITAVEIS = ['rascunho', 'enviado'];

    public function __construct(
        private readonly OrcamentoCalculator $calculator,
        private readonly OrcamentoGravador $gravador,
    ) {}

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/calcular
    // Preview server-side — NÃO persiste, apenas retorna cálculo authoritative
    // ------------------------------------------------------------------

    public function calcular(Request $request): JsonResponse
    {
        $validated = $this->validarPayload($request);

        try {
            $resultado = $this->calculator->calcular($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado, 200);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/orcamentos
    // Persiste orçamento + itens, retorna 201 com relações
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $this->exigirCriar();
        $validated = $this->validarPayload($request);

        try {
            $calculado = $this->calculator->calcular($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $orcamento = $this->gravador->gravar($this->businessId(), $calculado);

        return response()->json($orcamento, 201);
    }

    // ------------------------------------------------------------------
    // GET /comunicacao-visual/api/orcamentos
    // Os 20 mais recentes da empresa, pra reabrir na calculadora.
    // ------------------------------------------------------------------

    public function index(): JsonResponse
    {
        $this->exigirVer();

        return response()->json(self::recentes($this->businessId()), 200);
    }

    /** Lista curta pra tela (a rota do hub usa o mesmo formato). `business_id` explícito (Tier 0). */
    public static function recentes(int $businessId, int $limite = 20): array
    {
        return Orcamento::query()
            ->where('comvis_orcamentos.business_id', $businessId)
            ->withCount('itens')
            ->orderByDesc('id')
            ->limit($limite)
            ->get(['id', 'numero', 'status', 'total', 'data_emissao'])
            ->map(fn (Orcamento $o) => [
                'id'           => $o->id,
                'numero'       => $o->numero,
                'status'       => $o->status,
                'total'        => (float) $o->total,
                // cast 'date' (Carbon), mas o @property da entity diz string: normaliza pelos dois.
                'data_emissao' => $o->data_emissao ? Carbon::parse($o->data_emissao)->toDateString() : null,
                'itens'        => (int) $o->itens_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Orçamento no formato que a calculadora reabre (`?orcamento={id}` no hub). Null se não for da
     * empresa (o `business_id` explícito vale mesmo sem sessão, ADR 0093).
     */
    public static function paraTela(int $businessId, int $id): ?array
    {
        $o = Orcamento::query()->where('comvis_orcamentos.business_id', $businessId)->find($id);
        if ($o === null) {
            return null;
        }
        $itens = OrcamentoItem::query()->where('business_id', $businessId)->where('orcamento_id', $o->id)
            ->orderBy('ordem')->orderBy('id')->get();

        return [
            'id'               => $o->id,
            'numero'           => $o->numero,
            'status'           => $o->status,
            'desconto'         => (float) $o->desconto,
            'extras'           => (float) $o->extras,
            'custo_instalacao' => (float) $o->custo_instalacao,
            'custo_entrega'    => (float) $o->custo_entrega,
            'total'            => (float) $o->total,
            'itens'            => $itens->map(fn (OrcamentoItem $i) => [
                'material_id'       => $i->material_id,
                'descricao'         => $i->descricao,
                'largura_m'         => (float) $i->largura_m,
                'altura_m'          => (float) $i->altura_m,
                'quantidade'        => (int) $i->quantidade,
                'preco_unitario_m2' => (float) $i->preco_unitario_m2,
            ])->values()->all(),
        ];
    }

    // ------------------------------------------------------------------
    // PUT /comunicacao-visual/api/orcamentos/{id}
    // Regrava o orçamento reaberto com os valores recalculados no servidor. Mesmo número.
    // 422 se já virou OS ou foi recusado/expirado: aí o orçamento é registro, não rascunho.
    // ------------------------------------------------------------------

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigirCriar();
        $businessId = $this->businessId();
        $validated = $this->validarPayload($request);

        try {
            $calculado = $this->calculator->calcular($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Trava a linha: aprovar ao mesmo tempo (OrcamentoViraOs também trava) espera esta gravação.
        return DB::transaction(function () use ($businessId, $id, $calculado) {
            $orcamento = Orcamento::query()->where('comvis_orcamentos.business_id', $businessId)
                ->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($orcamento->status, self::EDITAVEIS, true)
                || $orcamento->os()->where('business_id', $businessId)->exists()) {
                return response()->json(['message' => "O orçamento {$orcamento->numero} está {$orcamento->status} e não pode mais ser alterado."], 422);
            }

            return response()->json($this->gravador->regravar($businessId, $orcamento, $calculado), 200);
        });
    }

    // ------------------------------------------------------------------
    // GET /comunicacao-visual/api/orcamentos/{id}
    // Retorna orçamento com itens (multi-tenant: global scope filtra automaticamente)
    // ------------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $this->exigirVer();
        $orcamento = Orcamento::with('itens')
            ->where('comvis_orcamentos.business_id', $this->businessId())
            ->findOrFail($id);

        return response()->json($orcamento, 200);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/orcamentos/{id}/aprovar
    // UC-FLX-01 · US-COMVIS-020: aprova e cria a OS com itens, medidas e arquivos.
    // 201 = OS criada agora · 200 = a OS já existia (reenvio não duplica) · 422 = recusado/expirado
    // 404 = orçamento de outra empresa (não 403: não confirma que o id existe — UC-CV-05).
    // ------------------------------------------------------------------

    public function aprovar(AprovarOrcamentoRequest $request, int $id, OrcamentoViraOs $viraOs): JsonResponse
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.orcamento.create')) {
            abort(403, 'Sem permissão para aprovar orçamento (comvis.orcamento.create).');
        }

        $businessId = (int) (session('user.business_id') ?? session('business.id'));

        try {
            [$os, $criada] = $viraOs->aprovar($businessId, $id, (int) $user->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($os, $criada ? 201 : 200);
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function businessId(): int
    {
        return (int) (session('user.business_id') ?? session('business.id'));
    }

    private function exigirCriar(): void
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.orcamento.create')) {
            abort(403, 'Sem permissão para salvar orçamento (comvis.orcamento.create).');
        }
    }

    private function exigirVer(): void
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.orcamento.view') && ! $user->can('comvis.orcamento.create')) {
            abort(403, 'Sem permissão para ver orçamento (comvis.orcamento.view).');
        }
    }

    /**
     * Validação centralizada usada por calcular() e store().
     * Retorna array validado pronto para o Service.
     *
     * É o ÚNICO contrato de payload destes endpoints (largura_m, altura_m, preco_unitario_m2,
     * desconto, extras). Existia uma CalcularOrcamentoRequest com outro contrato (largura_mm,
     * preco_m2 obrigatório, desconto_tipo/valor) que ninguém usava; plugá-la aqui dava 422 em
     * todo cálculo da tela. Removida em 2026-09-23 (refino R5) — se um dia virar FormRequest,
     * ela nasce destas regras, não daquelas.
     */
    private function validarPayload(Request $request): array
    {
        return $request->validate([
            // Cabeçalho
            'data_emissao'   => ['required', 'date'],
            'data_validade'  => ['nullable', 'date', 'after_or_equal:data_emissao'],
            'contato_id'     => ['nullable', 'integer', 'min:1'],
            'vendedor_id'    => ['nullable', 'integer', 'min:1'],
            'desconto'       => ['nullable', 'numeric', 'min:0'],
            'extras'         => ['nullable', 'numeric', 'min:0'],
            'custo_instalacao' => ['nullable', 'numeric', 'min:0'],
            'custo_entrega'  => ['nullable', 'numeric', 'min:0'],
            'observacoes'    => ['nullable', 'string', 'max:2000'],

            // Itens (mínimo 1)
            'itens'          => ['required', 'array', 'min:1'],
            'itens.*.material_id'       => ['nullable', 'integer', 'min:1'],
            'itens.*.descricao'         => ['required', 'string', 'max:500'],
            'itens.*.largura_m'         => ['required', 'numeric', 'gt:0'],
            'itens.*.altura_m'          => ['required', 'numeric', 'gt:0'],
            'itens.*.quantidade'        => ['required', 'integer', 'min:1'],
            'itens.*.preco_unitario_m2' => ['nullable', 'numeric', 'gt:0'],
            'itens.*.observacoes'       => ['nullable', 'string', 'max:500'],
            // UC-FLX-33: posto e horas de máquina só alimentam o custo previsto, não o preço.
            'itens.*.posto_id'          => $this->regraPosto(),
            'itens.*.horas_maquina'     => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [
            // Mensagens de validação em PT-BR
            'data_emissao.required'    => 'A data de emissão é obrigatória.',
            'data_emissao.date'        => 'A data de emissão deve ser uma data válida.',
            'data_validade.after_or_equal' => 'A data de validade deve ser igual ou posterior à data de emissão.',
            'desconto.min'             => 'O desconto não pode ser negativo.',
            'extras.min'               => 'O valor de extras não pode ser negativo.',
            'custo_instalacao.min'     => 'O custo de instalação não pode ser negativo.',
            'custo_entrega.min'        => 'O custo de entrega não pode ser negativo.',
            'itens.required'           => 'O orçamento deve ter pelo menos 1 item.',
            'itens.min'                => 'O orçamento deve ter pelo menos 1 item.',
            'itens.*.descricao.required' => 'A descrição do item é obrigatória.',
            'itens.*.largura_m.required' => 'A largura do item é obrigatória.',
            'itens.*.largura_m.gt'     => 'A largura do item deve ser maior que zero.',
            'itens.*.altura_m.required' => 'A altura do item é obrigatória.',
            'itens.*.altura_m.gt'      => 'A altura do item deve ser maior que zero.',
            'itens.*.quantidade.required' => 'A quantidade do item é obrigatória.',
            'itens.*.quantidade.min'   => 'A quantidade do item deve ser pelo menos 1.',
            'itens.*.preco_unitario_m2.gt' => 'O preço por m² deve ser maior que zero.',
            'itens.*.posto_id.exists'  => 'O posto informado não existe nesta empresa.',
            'itens.*.horas_maquina.min' => 'As horas de máquina não podem ser negativas.',
        ]);
    }

    /**
     * Posto do PCP do item: precisa ser da empresa da sessão (Tier 0, ADR 0093). Sem o módulo
     * Producao instalado não há tabela; aí só se exige inteiro e o gravador segue sem custo de
     * máquina (a interface CustoHoraDoPosto não está ligada).
     */
    private function regraPosto(): array
    {
        $regra = ['nullable', 'integer', 'min:1'];
        if (Schema::hasTable('pcp_postos')) {
            $businessId = (int) (session('user.business_id') ?? session('business.id'));
            $regra[] = Rule::exists('pcp_postos', 'id')->where('business_id', $businessId);
        }

        return $regra;
    }
}
