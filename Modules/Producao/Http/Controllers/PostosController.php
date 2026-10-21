<?php

namespace Modules\Producao\Http\Controllers;

use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Entities\ColaboradorCusto;
use Modules\Producao\Entities\Ferramenta;
use Modules\Producao\Entities\Motivo;
use Modules\Producao\Entities\Posto;

/**
 * Postos, máquinas e veículos, com os motivos de pausa e de refugo de cada posto — thread 03 +
 * emenda E03 do playbook de Produção (ADR 0431). Contrato: UC-PCV-33 (o celular só oferece os
 * motivos do posto). O custo-hora da pessoa só sai para quem tem `producao.desempenho.ver`.
 */
class PostosController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('producao.access'), 403);
        $veCusto = $request->user()->can('producao.desempenho.ver');
        $defineOperadores = $request->user()->can('producao.etapa.mover');
        $biz = (int) $request->session()->get('user.business_id');

        return Inertia::render('Producao/Postos/Index', [
            'motivos_padrao' => Motivo::PADRAO,
            'pode_ver_custo_pessoa' => $veCusto,
            'pode_definir_operadores' => $defineOperadores,
            'postos' => Inertia::defer(fn () => $this->postos($biz)),
            // US-PCP-030 · UC-FLX-08: candidatos a operador = gente da empresa com acesso à Produção
            // (a mesma regra que o OperadoresDoPosto::definir confere ao gravar).
            'usuarios' => Inertia::defer(fn () => $defineOperadores ? $this->candidatosAOperador($biz) : []),
            'ferramentas' => Inertia::defer(fn () => Ferramenta::query()->orderBy('nome')->get(['id', 'codigo_qr', 'nome', 'ativo'])),
            'colaboradores' => Inertia::defer(fn () => $veCusto ? $this->colaboradores($biz) : []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $biz = $this->autorizar($request);
        $dados = $this->validarPosto($request, $biz);
        DB::transaction(function () use ($dados, $biz) {
            $posto = Posto::create($dados + ['business_id' => $biz]);
            foreach (Motivo::PADRAO as $tipo => $nomes) {
                $this->gravarMotivos($posto, $tipo, $nomes);
            }
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Posto salvo.']);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $biz = $this->autorizar($request);
        $posto = Posto::query()->where('business_id', $biz)->findOrFail($id);
        $dados = $this->validarPosto($request, $biz, $posto->id);
        // Custo-hora digitado é o direto: se mudou, a parcela do custeio RKW sai até aplicar de novo.
        if (round((float) $dados['custo_hora'], 2) !== round((float) $posto->custo_hora, 2)) {
            $dados['custo_hora_direto'] = null;
        }
        $posto->update($dados);

        return back()->with('status', ['success' => 1, 'msg' => 'Posto salvo.']);
    }

    /** Lista fechada de motivos do posto: o que vier substitui a lista daquele tipo. */
    public function motivos(Request $request, int $id): RedirectResponse
    {
        $biz = $this->autorizar($request);
        $posto = Posto::query()->where('business_id', $biz)->findOrFail($id);
        $dados = $request->validate([
            'tipo' => ['required', Rule::in([Motivo::PAUSA, Motivo::REFUGO])],
            'nomes' => ['required', 'array', 'min:1', 'max:20'],
            'nomes.*' => ['required', 'string', 'max:80', 'distinct'],
        ]);
        DB::transaction(fn () => $this->gravarMotivos($posto, $dados['tipo'], $dados['nomes']));

        return back()->with('status', ['success' => 1, 'msg' => 'Motivos salvos.']);
    }

    private function autorizar(Request $request): int
    {
        abort_unless($request->user()->can('producao.access'), 403);

        return (int) $request->session()->get('user.business_id');
    }

    private function validarPosto(Request $request, int $biz, ?int $ignorar = null): array
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:40', Rule::unique('pcp_postos', 'codigo')->where('business_id', $biz)->ignore($ignorar)],
            'nome' => ['required', 'string', 'max:120'],
            'setor' => ['nullable', 'string', 'max:60'],
            'tipo_custo' => ['required', Rule::in([Posto::MAQUINA, Posto::PESSOA])],
            'veiculo' => ['boolean'],
            'placa' => ['nullable', 'required_if:veiculo,true', 'string', 'max:10'],
            'custo_hora' => ['required', 'numeric', 'min:0', 'max:99999'],
            'custo_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'capacidade_por_hora' => ['nullable', 'numeric', 'min:0'],
            'unidade_capacidade' => ['nullable', 'string', 'max:10'],
            'ativo' => ['boolean'],
        ]);
        // E02: veículo é posto de máquina — custa pela hora e pelo km.
        if (! empty($dados['veiculo'])) {
            $dados['tipo_custo'] = Posto::MAQUINA;
        }

        return $dados;
    }

    /** @param  array<int, string>  $nomes */
    private function gravarMotivos(Posto $posto, string $tipo, array $nomes): void
    {
        Motivo::query()->where('posto_id', $posto->id)->where('tipo', $tipo)->delete();
        foreach (array_values($nomes) as $ordem => $nome) {
            Motivo::create(['business_id' => $posto->business_id, 'posto_id' => $posto->id, 'tipo' => $tipo,
                'nome' => trim($nome), 'ordem' => $ordem]);
        }
    }

    private function postos(int $biz): array
    {
        $motivos = Motivo::query()->where('ativo', true)->orderBy('ordem')->get(['posto_id', 'tipo', 'nome'])->groupBy('posto_id');
        // US-PCP-030: operadores de cada posto (lista vazia = posto sem cadastro, aceita qualquer pessoa).
        $operadores = DB::table('pcp_posto_operadores as po')->join('users as u', 'u.id', '=', 'po.user_id')
            ->where('po.business_id', $biz)->where('u.business_id', $biz)
            ->orderBy('u.first_name')->get(['po.posto_id', 'u.id', 'u.first_name', 'u.last_name'])
            ->groupBy('posto_id');

        return Posto::query()->orderBy('veiculo')->orderBy('nome')->get()->map(function (Posto $p) use ($motivos, $operadores) {
            $lista = $motivos->get($p->id, collect());

            return $p->only(['id', 'codigo', 'nome', 'setor', 'tipo_custo', 'veiculo', 'placa', 'custo_hora', 'custo_km',
                'capacidade_por_hora', 'unidade_capacidade', 'ativo']) + ['operadores' => $operadores->get($p->id, collect())
                    ->map(fn ($u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])->values()->all(),
                    'motivos' => [
                    Motivo::PAUSA => $lista->where('tipo', Motivo::PAUSA)->pluck('nome')->values(),
                    Motivo::REFUGO => $lista->where('tipo', Motivo::REFUGO)->pluck('nome')->values(),
                ]];
        })->all();
    }

    /** @return list<array{id: int, nome: string}> */
    private function candidatosAOperador(int $biz): array
    {
        return User::query()->where('business_id', $biz)->where('user_type', 'user')->orderBy('first_name')->get()
            ->filter(fn (User $u) => $u->can('producao.access'))
            ->map(fn (User $u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])->values()->all();
    }

    private function colaboradores(int $biz): array
    {
        $custos = ColaboradorCusto::query()->pluck('custo_hora', 'user_id');

        return DB::table('users')->where('business_id', $biz)->whereNull('deleted_at')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn ($u) => ['id' => $u->id, 'nome' => trim($u->first_name.' '.$u->last_name), 'custo_hora' => $custos[$u->id] ?? null])
            ->all();
    }
}
