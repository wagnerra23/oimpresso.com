<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;

/**
 * Área do estudo (etapa 07 P-04): criar estudo, incluir participante, registrar saída.
 *
 * Só quem tem `fisio.pesquisa` (pesquisador do estudo); `fisio.access` sozinho não basta.
 * O `business_id` vem da sessão; estudo, participante e paciente são buscados com o escopo da
 * empresa, então id de outra empresa responde 404 (ADR 0093).
 */
class PesquisaController extends Controller
{
    public const PERMISSAO = 'fisio.pesquisa';

    public function __construct(private readonly EstudoService $estudos) {}

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'cep_opinion_number' => ['required', 'string', 'max:60'],
            'protocol_version' => ['required', 'string', 'max:30'],
            'tcle_version' => ['required', 'string', 'max:30'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'momentos' => ['required', 'array', 'min:1', 'max:12'],
            'momentos.*.label' => ['required', 'string', 'max:60'],
            'momentos.*.day_offset' => ['required', 'integer', 'min:0', 'max:3650'],
            'momentos.*.window_days' => ['required', 'integer', 'min:0', 'max:60'],
            'momentos.*.instruments' => ['present', 'array'],
            'momentos.*.instruments.*' => ['string', 'in:'.implode(',', CatalogoQuestionarios::LIVRES)],
        ]);
        $biz = (int) $request->session()->get('user.business_id');

        $estudo = $this->estudos->criar($biz, $dados + ['responsible_user_id' => (int) $request->user()->id], $dados['momentos'], (int) $request->user()->id);

        return response()->json(['id' => $estudo->id], 201);
    }

    public function incluir(Request $request, int $study): JsonResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'patient_id' => ['required', 'integer'],
            // O TCLE foi assinado na clínica, na versão aprovada pelo CEP.
            'tcle_assinado' => ['accepted'],
        ]);
        $biz = $this->empresa($request);
        $estudo = Study::where('business_id', $biz)->findOrFail($study);
        $paciente = Patient::where('business_id', $biz)->findOrFail((int) $dados['patient_id']);
        if ($estudo->status !== Study::STATUS_ATIVO) {
            return response()->json(['erro' => 'estudo_fora_de_andamento'], 422);
        }

        try {
            $p = $this->estudos->incluir($estudo, $paciente, (int) $request->user()->id, $request->ip());
        } catch (InvalidArgumentException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $p->id, 'code' => $p->code, 'entered_on' => $p->entered_on->toDateString()], 201);
    }

    public function saida(Request $request, int $study, int $participant): JsonResponse
    {
        $this->autorizar($request);
        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:'.implode(',', StudyParticipant::SAIDAS)],
            'motivo' => ['required', 'string', 'max:500'],
        ]);
        $p = StudyParticipant::where('business_id', $this->empresa($request))->where('study_id', $study)->findOrFail($participant);

        try {
            $this->estudos->registrarSaida($p, (string) $dados['tipo'], (string) $dados['motivo'], (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json(['exited_on' => $p->exited_on?->toDateString(), 'exit_type' => $p->exit_type]);
    }

    /** Empresa da sessão: toda consulta do controller filtra por ela, além do escopo global (ADR 0093). */
    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function autorizar(Request $request): void
    {
        abort_unless((bool) $request->user()?->can(self::PERMISSAO), 403);
    }
}
