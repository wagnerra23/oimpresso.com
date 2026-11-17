<?php

declare(strict_types=1);

namespace Modules\Vestuario\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Vestuario\Exceptions\RemarcacaoRecusada;
use Modules\Vestuario\Services\Remarcacao;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

/**
 * US-VEST-032 — remarcação por coleção (decisão [W] 2026-10-27).
 *
 *   GET  /vestuario/remarcacao                     → tela
 *   POST /vestuario/remarcacao/previa              → antes → depois, não grava
 *   POST /vestuario/remarcacao                     → grava (exige a assinatura da prévia)
 *   POST /vestuario/remarcacao/{lote}/reverter     → devolve o preço de antes
 *   GET  /vestuario/remarcacao/{lote}/etiquetas    → itens no formato do lote de etiquetas
 *
 * Desligado por padrão: o setting `remarcacao.enabled` do Vestuário liga por empresa (sem ele, 404).
 * Permissão própria (`PERMISSAO`) ou superadmin: a operação altera até 2.000 preços de uma vez e não
 * se confunde com editar um produto (decisão [W] 2026-11-09). Tier 0: `business_id` da sessão em tudo.
 */
class RemarcacaoController extends Controller
{
    public const PERMISSAO = 'vestuario.remarcacao.aplicar';

    public function __construct(private Remarcacao $svc)
    {
    }

    public function index(): Response
    {
        $biz = $this->guard();

        return Inertia::render('Vestuario/Remarcacao/Index', [
            'categorias' => DB::table('categories')->where('business_id', $biz)->where('category_type', 'product')
                ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'marcas' => DB::table('brands')->where('business_id', $biz)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'lotes' => Inertia::defer(fn () => DB::table('vestuario_remarcacoes')->where('business_id', $biz)
                ->orderByDesc('id')->limit(30)
                ->get(['id', 'nome', 'modo', 'valor', 'qtd_itens', 'status', 'created_at', 'revertida_em'])),
        ]);
    }

    public function previa(Request $request): JsonResponse
    {
        $biz = $this->guard();
        $d = $this->dados($request);

        return $this->tentar(function () use ($biz, $d) {
            $previa = $this->svc->previa($biz, $d['filtro'], $d['modo'], $d['valor']);

            return $previa + ['grupos' => $this->svc->precosDeGrupo(array_column($previa['linhas'], 'variation_id'))];
        });
    }

    public function store(Request $request): JsonResponse
    {
        $biz = $this->guard();
        $d = $this->dados($request, true);

        return $this->tentar(fn () => ['lote' => $this->svc->aplicar(
            $biz, (int) auth()->id(), $d['nome'], $d['filtro'], $d['modo'], $d['valor'], $d['assinatura']
        )]);
    }

    public function reverter(int $lote): JsonResponse
    {
        $biz = $this->guard();

        return $this->tentar(fn () => $this->svc->reverter($biz, (int) auth()->id(), $lote));
    }

    public function etiquetas(int $lote): JsonResponse
    {
        $biz = $this->guard();
        $status = DB::table('vestuario_remarcacoes')->where('business_id', $biz)->where('id', $lote)->value('status');
        abort_if($status === null, 404, 'Remarcação não encontrada.');
        if ($status !== 'aplicada') {
            return response()->json(['message' => 'Esta remarcação foi revertida: as etiquetas dela não valem mais.'], 422);
        }
        // O preço é o de hoje: a peça pode ter sido mexida depois da remarcação.
        $itens = DB::table('vestuario_remarcacao_itens as i')
            ->join('variations as v', 'v.id', '=', 'i.variation_id')->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.business_id', $biz)->where('p.business_id', $biz)->where('i.remarcacao_id', $lote)
            ->get(['i.product_id', 'i.variation_id', 'p.name as nome', 'v.name as tamanho', 'v.sell_price_inc_tax as preco', 'v.sub_sku as sku']);
        abort_if($itens->isEmpty(), 404, 'Remarcação não encontrada.');

        return response()->json(['items' => $itens->map(fn ($i) => [
            'product_id' => (int) $i->product_id, 'variation_id' => (int) $i->variation_id,
            'nome' => mb_substr((string) $i->nome, 0, 120),
            // Produto simples tem a variação interna DUMMY: sem tamanho, a etiqueta usa o padrão dela.
            'tamanho' => $i->tamanho === 'DUMMY' ? null : mb_substr((string) $i->tamanho, 0, 10),
            'preco' => round((float) $i->preco, 2), 'sku' => mb_substr((string) $i->sku, 0, 40),
        ])->values()]);
    }

    private function guard(): int
    {
        $biz = (int) (session('user.business_id') ?? session('business.id'));
        abort_unless(app(VestuarioSettingsResolver::class)->forBusiness($biz)->getBool('remarcacao.enabled', false), 404);
        $user = auth()->user();
        abort_unless($user->can('superadmin') || $user->can(self::PERMISSAO), 403, 'Sem permissão para remarcar preços.');

        return $biz;
    }

    private function dados(Request $request, bool $gravar = false): array
    {
        $d = $request->validate([
            'modo' => ['required', 'in:percentual,valor'],
            'valor' => ['required', 'numeric', $request->input('modo') === 'valor' ? 'between:0.01,99999' : 'between:-90,300'],
            'filtro' => ['array'],
            'filtro.category_id' => ['nullable', 'integer'],
            'filtro.sub_category_id' => ['nullable', 'integer'],
            'filtro.brand_id' => ['nullable', 'integer'],
            'filtro.nome' => ['nullable', 'string', 'max:60'],
            'nome' => [$gravar ? 'required' : 'nullable', 'string', 'max:80'],
            'assinatura' => [$gravar ? 'required' : 'nullable', 'string', 'size:64'],
        ], ['nome.required' => 'Dê um nome à remarcação.', 'valor.between' => 'Valor fora do permitido.']);
        $d['filtro'] = array_filter($d['filtro'] ?? [], fn ($v) => $v !== null && $v !== '');
        $d['valor'] = round((float) $d['valor'], 4);

        return $d;
    }

    private function tentar(callable $f): JsonResponse
    {
        try {
            return response()->json($f());
        } catch (RemarcacaoRecusada $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (QueryException $e) { // deadlock ou espera longa nas travas: a mensagem traz host e SQL
            report($e);

            return response()->json(['message' => 'Não foi possível concluir agora. Tente de novo.'], 500);
        }
    }
}
