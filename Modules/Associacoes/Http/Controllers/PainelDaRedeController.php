<?php

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\PendenciasDaRede;

/**
 * Painel da rede — tela inicial do /associacoes (ADR 0451, onda 5 · US-ASSOC-004 · PT-04).
 *
 * Só números e nomes de associação: nenhum dado pessoal de associada sai daqui (D5).
 * Alcance pela regra D4 ({@see AssociacoesAcesso}): a diretoria (`associacoes.rede`) vê a rede;
 * o representante vê só as associações dele. O pacote + `associacoes.access` já foram exigidos
 * pelo middleware AssociacoesLiberado. Escopo de empresa: global scope dos Models + `business_id`
 * explícito no Service de pendências (defesa dupla, ADR 0093). Tudo que consulta é `Inertia::defer`.
 */
class PainelDaRedeController extends Controller
{
    public function __construct(
        private readonly AssociacoesAcesso $acesso,
        private readonly PendenciasDaRede $pendencias,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $biz = (int) $request->session()->get('user.business_id');

        return Inertia::render('Associacoes/Painel', [
            'alcance' => $this->acesso->veARede($user) ? 'rede' : 'associacao',
            'prazoAtaDias' => (int) config('associacoes.prazo_ata_diretoria_dias', 30),
            'kpis' => Inertia::defer(fn () => $this->kpis($user, $biz)),
            'associadasPorAssociacao' => Inertia::defer(fn () => $this->associadasPorAssociacao($user, $biz)),
            'pendencias' => Inertia::defer(fn () => $this->pendencias->calcular($biz, $this->acesso->associacoesVisiveis($user))),
        ]);
    }

    /** @return array<string, mixed> */
    private function kpis($user, int $biz): array
    {
        $porSituacao = $this->acesso->restringir(Associacao::query()->where('business_id', $biz), $user, 'id')
            ->select('situacao', DB::raw('COUNT(*) as total'))
            ->groupBy('situacao')
            ->pluck('total', 'situacao');

        $cidades = $this->acesso->restringir(Associacao::query()->where('business_id', $biz), $user, 'id')
            ->where('situacao', '!=', 'desligada')
            ->distinct()
            ->count('cidade');

        $ativas = $this->acesso->restringir(Associada::query()->where('business_id', $biz), $user)
            ->where('situacao', 'ativa')
            ->count();

        $pendencias = collect($this->pendencias->calcular($biz, $this->acesso->associacoesVisiveis($user)));

        return [
            'associacoes' => (int) $porSituacao->sum(),
            'porSituacao' => collect(Associacao::SITUACOES)
                ->mapWithKeys(fn ($s) => [$s => (int) ($porSituacao[$s] ?? 0)])
                ->all(),
            'cidades' => (int) $cidades,
            'associadasAtivas' => (int) $ativas,
            'pendencias' => $pendencias->count(),
            'atrasadas' => $pendencias->where('situacao', 'atrasada')->count(),
        ];
    }

    /** @return list<array{id:int, nome:string, cidade:string, ativas:int}> */
    private function associadasPorAssociacao($user, int $biz): array
    {
        $ativas = $this->acesso->restringir(Associada::query()->where('business_id', $biz), $user)
            ->where('situacao', 'ativa')
            ->select('associacao_id', DB::raw('COUNT(*) as total'))
            ->groupBy('associacao_id')
            ->pluck('total', 'associacao_id');

        return $this->acesso->restringir(Associacao::query()->where('business_id', $biz), $user, 'id')
            ->where('situacao', '!=', 'desligada')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cidade'])
            ->map(fn ($a) => [
                'id' => (int) $a->getAttribute('id'),
                'nome' => (string) $a->getAttribute('nome'),
                'cidade' => (string) $a->getAttribute('cidade'),
                'ativas' => (int) ($ativas[$a->getAttribute('id')] ?? 0),
            ])
            ->values()
            ->all();
    }
}
