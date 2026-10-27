<?php

declare(strict_types=1);

namespace Modules\Compras\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Compras\Services\CompraDaNotaRecusadaException;
use Modules\Compras\Services\ImportarDfeComoCompraService;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Entrada\EntradaXmlRecusadaException;
use Modules\NfeBrasil\Services\Entrada\ImportarXmlEntradaService;

/**
 * Conferência da NF-e do fornecedor antes de virar compra (US-COM-003 · ADR entrada por XML, fase 4).
 * Desenho: aba Entradas de `prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx` (TrEntradas, UC-TRB-18).
 *
 * Permissão `compras.import_xml`. Atrás de `nfebrasil.entrada_xml_enabled`: desligada, as rotas
 * respondem 404. Tier 0 (ADR 0093): tudo pelo business da sessão.
 */
class EntradaXmlController extends Controller
{
    /** CFOP de saída do fornecedor → entrada (proposta exibida; não é gravada). */
    private const CFOP_ENTRADA = ['5102' => '1102', '6102' => '2102', '5101' => '1101', '6101' => '2101', '5405' => '1403', '6403' => '2403', '5949' => '1949'];

    public function index(Request $request): Response
    {
        $biz = $this->autorizar();
        $notas = NfeDfeRecebido::where('business_id', $biz)->whereNull('transaction_id')
            ->orderByDesc('data_emissao')->limit(50)
            ->get(['id', 'chave_44', 'cnpj_emitente', 'nome_emitente', 'valor_total', 'data_emissao', 'origem']);
        $selecionada = $notas->firstWhere('id', (int) $request->query('nota')) ?? $notas->first();

        return Inertia::render('Compras/EntradaXml', [
            'notas' => $notas->map(fn ($n) => [
                'id' => $n->id, 'numero' => ltrim(substr((string) $n->chave_44, 25, 9), '0'), 'fornecedor' => $n->nome_emitente,
                'valor_total' => (float) $n->valor_total, 'data' => optional($n->data_emissao)->format('d/m/Y'), 'origem' => $n->origem,
            ])->values(),
            'nota' => $selecionada ? $this->itens($biz, $selecionada) : null,
            'locais' => DB::table('business_locations')->where('business_id', $biz)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function upload(Request $request, ImportarXmlEntradaService $svc): RedirectResponse
    {
        $biz = $this->autorizar();
        $request->validate(['arquivo' => 'required|file|max:2048'], ['arquivo.required' => 'Escolha o XML da nota.']);

        try {
            $nota = $svc->importar($biz, (string) $request->file('arquivo')->get());
        } catch (EntradaXmlRecusadaException $e) {
            return back()->withErrors(['arquivo' => $e->getMessage()]);
        }

        return redirect()->route('compras.entrada-xml', ['nota' => $nota->id]);
    }

    public function gerar(Request $request, int $id, ImportarDfeComoCompraService $svc): RedirectResponse
    {
        $biz = $this->autorizar();
        $dados = $request->validate([
            'location_id' => 'required|integer',
            'vinculos' => 'array', 'vinculos.*' => 'nullable|integer',
        ], ['location_id.required' => 'Escolha o local de estoque.']);

        try {
            $compra = $svc->executar($biz, $id, (int) auth()->id(), (int) $dados['location_id'], array_filter(array_map('intval', $dados['vinculos'] ?? [])));
        } catch (CompraDaNotaRecusadaException $e) {
            return back()->withErrors(['gerar' => $e->getMessage()]);
        }

        return redirect('/purchases')->with('status', ['success' => 1, 'msg' => 'Compra ' . $compra->ref_no . ' criada a partir da NF-e.']);
    }

    /** Busca de produto para o vínculo (só da empresa). */
    public function produtos(Request $request): JsonResponse
    {
        $biz = $this->autorizar();
        $q = trim((string) $request->query('q'));

        return response()->json($q === '' ? [] : $this->variacoes($biz)
            ->where(fn ($w) => $w->where('products.name', 'like', "%{$q}%")->orWhere('variations.sub_sku', 'like', "{$q}%"))
            ->limit(20)->get());
    }

    private function itens(int $biz, NfeDfeRecebido $nota): array
    {
        $itens = NfeDfeItem::where('business_id', $biz)->where('dfe_recebido_id', $nota->id)->orderBy('id')->get();
        $lembrados = DB::table('compras_produto_fornecedor')->where('business_id', $biz)
            ->where('cnpj_fornecedor', $nota->cnpj_emitente)->pluck('variation_id', 'codigo_fornecedor');
        $ids = $itens->map(fn ($i) => $i->variation_id ?? $lembrados[$i->codigo_fornecedor] ?? null)->filter()->all();
        $eans = $itens->pluck('ean')->filter()->all();
        $variacoes = $this->variacoes($biz)
            ->where(fn ($w) => $w->whereIn('variations.id', $ids ?: [0])->orWhereIn('variations.sub_sku', $eans ?: ['']))
            ->get();

        return [
            'id' => $nota->id, 'fornecedor' => $nota->nome_emitente, 'cnpj' => $nota->cnpj_emitente,
            'valor_total' => (float) $nota->valor_total,
            'fornecedor_cadastrado' => DB::table('contacts')->where('business_id', $biz)->whereIn('type', ['supplier', 'both'])->whereNull('deleted_at')
                ->whereRaw("REPLACE(REPLACE(REPLACE(tax_number, '.', ''), '/', ''), '-', '') = ?", [$nota->cnpj_emitente])->exists(),
            'itens' => $itens->map(function ($i) use ($lembrados, $variacoes) {
                $vinculado = $i->variation_id ?? $lembrados[$i->codigo_fornecedor] ?? null;
                // Sugestão por EAN igual ao SKU cadastrado: só sugere, a pessoa confirma (ADR D3).
                $sugestao = $vinculado ? null : $variacoes->firstWhere('sku', $i->ean);

                return [
                    'id' => $i->id, 'codigo' => $i->codigo_fornecedor, 'descricao' => $i->descricao, 'ncm' => $i->ncm,
                    'cfop' => $i->cfop, 'cfop_entrada' => self::CFOP_ENTRADA[$i->cfop] ?? null,
                    'cst' => $i->cst_csosn, 'st_retida' => in_array($i->cst_csosn, ['010', '030', '060', '070', '201', '202', '203', '500'], true),
                    'icms' => $i->icms_valor !== null ? (float) $i->icms_valor : null,
                    'quantidade' => (float) $i->quantidade, 'valor_total' => (float) $i->valor_total, 'unidade' => $i->unidade,
                    'produto' => $vinculado ? $variacoes->firstWhere('variation_id', (int) $vinculado) : null,
                    'sugestao' => $sugestao,
                ];
            })->values(),
        ];
    }

    private function variacoes(int $biz)
    {
        return DB::table('variations')->join('products', 'products.id', '=', 'variations.product_id')
            ->where('products.business_id', $biz)->whereNull('variations.deleted_at')
            ->select(['variations.id as variation_id', 'products.name as nome', 'variations.sub_sku as sku']);
    }

    private function autorizar(): int
    {
        abort_unless(config('nfebrasil.entrada_xml_enabled'), 404);
        abort_unless(auth()->user()?->can('compras.import_xml'), 403);

        return (int) session('user.business_id');
    }
}
