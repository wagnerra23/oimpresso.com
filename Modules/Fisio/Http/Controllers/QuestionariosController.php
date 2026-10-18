<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;
use Modules\Fisio\Services\Questionarios\PontuacaoQuestionario;
use Modules\Fisio\Services\Questionarios\RespostaInvalidaException;

/**
 * Questionários de uso livre na ficha do paciente (US-FISIO-011; ADR 0437 §8).
 *
 * O profissional transcreve as respostas do formulário da versão brasileira validada; a pontuação
 * é calculada AQUI, no servidor (`PontuacaoQuestionario`) — `score` vindo do corpo é ignorado
 * (UC-FPS-13). Questionário bloqueado por licença ou desconhecido é recusado (UC-FPS-14).
 *
 * Só criação: resposta de questionário é dado clínico, não se edita nem se apaga
 * (`SemExclusaoDeDadoClinico`). Paciente de outra empresa: 404 pelo escopo (UC-FPS-15).
 * O resultado é número; o software não o interpreta como conduta (SaMD, ADR 0437 §5).
 */
class QuestionariosController extends Controller
{
    public function store(Request $request, int $id, PontuacaoQuestionario $pontuacao): JsonResponse|RedirectResponse
    {
        // Paciente de outra empresa: 404 pelo escopo, antes de validar (UC-FPS-15).
        $paciente = Patient::query()->findOrFail($id);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');

        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['instrumento' => 'Paciente bloqueado: a ficha é só leitura.']);
        }

        $dados = $request->validate([
            'instrumento' => ['required', 'string'],
            'respostas' => ['required', 'array'],
        ]);

        $instrumento = (string) $dados['instrumento'];
        if (! in_array($instrumento, CatalogoQuestionarios::LIVRES, true)) {
            $msg = in_array($instrumento, CatalogoQuestionarios::BLOQUEADOS, true)
                ? 'Questionário bloqueado até haver licença de uso (ADR 0437 §8).'
                : 'Questionário desconhecido.';
            throw ValidationException::withMessages(['instrumento' => $msg]);
        }

        try {
            $resultado = $pontuacao->pontuar($instrumento, $dados['respostas']);
        } catch (RespostaInvalidaException $e) {
            throw ValidationException::withMessages([$e->campo => $e->getMessage()]);
        }

        $questionario = Questionnaire::query()->create([
            'business_id' => $biz,
            'patient_id' => $paciente->id,
            'instrument' => $instrumento,
            'answers' => [
                'definicao' => CatalogoQuestionarios::VERSAO,
                'respostas' => $resultado['respostas'],
                'detalhe' => $resultado['detalhe'],
                'registrado_por' => $request->user()->id,
            ],
            'score' => $resultado['escore'],
            'answered_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $questionario->id, 'score' => $resultado['escore']], 201);
        }

        return back()->with('status', 'Questionário registrado.');
    }
}
