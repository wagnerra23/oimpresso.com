<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\Montavel\RelatorioMontavel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * US-REL-001 — executa, salva, lista e abre relatórios montáveis (JSON; a tela vem depois do OK de design).
 *
 * - Flag `relatorios.montavel.enabled` desligada → 404.
 * - Cada fonte exige a permissão do relatório pronto da mesma área (PERMISSAO_POR_FONTE);
 *   custo e valor em estoque seguem `view_purchase_price`.
 * - Relatório salvo é da empresa: id de outra empresa → 404. Locais permitidos do usuário valem sempre.
 */
class RelatorioMontavelController extends Controller
{
    public const PERMISSAO_POR_FONTE = [
        'vendas' => 'purchase_n_sell_report.view',
        'titulos' => 'financeiro.relatorios.view',
        'estoque' => 'stock_report.view',
        'os' => 'job_sheet.view_all',
    ];

    public function __construct(private readonly RelatorioMontavel $relatorio) {}

    public function executar(Request $request): JsonResponse
    {
        $this->autorizar($request->input('definicao.fonte'));
        try {
            return response()->json($this->relatorio->executar($this->empresa(), (array) $request->input('definicao'), $this->veCusto(), auth()->user()->permitted_locations()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function salvos(): JsonResponse
    {
        $this->autorizar();

        // Só os salvos cuja fonte o usuário pode ver: quem não vê títulos não vê nem o nome do relatório.
        $salvos = DB::table('relatorios_salvos')->where('business_id', $this->empresa())->orderBy('nome')
            ->get(['id', 'nome', 'definicao', 'criado_por'])
            ->map(fn ($r) => ['id' => $r->id, 'nome' => $r->nome, 'definicao' => json_decode($r->definicao, true), 'criado_por' => $r->criado_por])
            ->filter(fn ($r) => $this->podeFonte($r['definicao']['fonte'] ?? null))
            ->values();

        return response()->json($salvos);
    }

    public function salvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request->input('definicao.fonte'));
        $nome = trim((string) $request->input('nome'));
        if ($nome === '' || mb_strlen($nome) > 120) {
            return response()->json(['message' => 'Dê um nome de até 120 caracteres.'], 422);
        }
        try {
            // Salva a definição limpa. Quem não vê custo não grava campo de custo.
            $def = $this->relatorio->validar((array) $request->input('definicao'), $this->veCusto());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $dados = ['nome' => $nome, 'definicao' => json_encode($def, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];

        if ($id !== null) {
            $n = DB::table('relatorios_salvos')->where('business_id', $this->empresa())->where('id', $id)->update($dados);
            abort_if($n === 0 && ! DB::table('relatorios_salvos')->where('business_id', $this->empresa())->where('id', $id)->exists(), 404);

            return response()->json(['id' => $id]);
        }

        $novo = DB::table('relatorios_salvos')->insertGetId($dados + [
            'business_id' => $this->empresa(), 'criado_por' => auth()->id(), 'created_at' => now(),
        ]);

        return response()->json(['id' => $novo], 201);
    }

    public function abrir(int $id): JsonResponse
    {
        $this->autorizar();
        $r = DB::table('relatorios_salvos')->where('business_id', $this->empresa())->where('id', $id)->first();
        abort_if($r === null, 404);
        $def = json_decode($r->definicao, true);
        abort_unless($this->podeFonte($def['fonte'] ?? null), 403);

        try {
            return response()->json(['id' => $r->id, 'nome' => $r->nome, 'definicao' => $def]
                + $this->relatorio->executar($this->empresa(), $def, $this->veCusto(), auth()->user()->permitted_locations()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Flag + (com fonte) a permissão do relatório pronto daquela área. */
    private function autorizar(mixed $fonte = null): void
    {
        abort_unless(config('relatorios.montavel.enabled'), 404);
        abort_unless(collect(self::PERMISSAO_POR_FONTE)->contains(fn ($p) => auth()->user()->can($p)), 403);
        if ($fonte !== null && isset(self::PERMISSAO_POR_FONTE[$fonte])) {
            abort_unless($this->podeFonte($fonte), 403);
        }
    }

    private function podeFonte(mixed $fonte): bool
    {
        return is_string($fonte) && isset(self::PERMISSAO_POR_FONTE[$fonte]) && auth()->user()->can(self::PERMISSAO_POR_FONTE[$fonte]);
    }

    private function empresa(): int
    {
        return (int) auth()->user()->business_id;
    }

    private function veCusto(): bool
    {
        return auth()->user()->can('view_purchase_price');
    }
}
