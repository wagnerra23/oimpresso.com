<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Record;

/**
 * Prontuário do paciente (US-FISIO-005; Res. COFFITO 414/2012).
 *
 * Só existe criação. Registro assinado não se edita nem se apaga — não há rota para isso
 * (PUT/DELETE respondem 405) e o banco recusa por trigger. Correção é um `adendo` com
 * `amends_id` para o original, que fica intacto (UC-FPS-04, UC-FPS-05).
 *
 * O carimbo (`signed_by`, `signed_crefito`, `signed_at`) é montado aqui: usuário logado,
 * CREFITO informado na assinatura e relógio do servidor. Nada disso vem do corpo
 * (UC-FPS-03). `telemonitoramento` não é criável pela tela: quem gera é o portal.
 *
 * Paciente bloqueado (Lei 13.787/2018: "excluir" é bloquear e o prontuário fica guardado)
 * só recebe adendo — corrigir o que já está assinado continua possível; atendimento novo,
 * não (UC-FPS-17).
 */
class RegistrosController extends Controller
{
    /** Campos mínimos de uma avaliação (Res. COFFITO 414/2012). */
    private const CAMPOS_AVALIACAO = [
        'queixa_principal', 'historia_doenca', 'exame_fisico',
        'diagnostico_cinesiologico', 'objetivos', 'plano_tratamento',
    ];

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        // Paciente de outra empresa: 404 pelo escopo, antes de validar (UC-FPS-01).
        $paciente = Patient::query()->findOrFail($id);
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');

        $dados = $request->validate($this->regras((string) $request->input('kind'), $paciente, $biz), [
            'amends_id.exists' => 'O adendo precisa apontar um registro deste paciente.',
        ]);

        if ($paciente->estaBloqueado() && $dados['kind'] !== Record::KIND_ADENDO) {
            throw ValidationException::withMessages(['kind' => 'Paciente bloqueado: o prontuário só aceita adendo de correção.']);
        }

        $registro = Record::query()->create([
            'business_id' => $biz,
            'patient_id' => $paciente->id,
            'kind' => $dados['kind'],
            'body' => $dados['body'],
            'amends_id' => $dados['kind'] === Record::KIND_ADENDO ? (int) $dados['amends_id'] : null,
            'signed_by' => $request->user()->id,
            'signed_crefito' => trim($dados['crefito']),
            'signed_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $registro->id], 201);
        }

        return back()->with('status', 'Registro assinado.');
    }

    /** Não existe edição nem exclusão de registro: corrige-se por adendo (UC-FPS-04). */
    public function imutavel(): never
    {
        abort(405, 'Registro do prontuário não se altera nem se apaga. Corrija com um adendo.');
    }

    /** @return array<string, mixed> */
    private function regras(string $kind, Patient $paciente, int $biz): array
    {
        $regras = [
            'kind' => ['required', Rule::in([Record::KIND_AVALIACAO, Record::KIND_EVOLUCAO, Record::KIND_ADENDO])],
            'crefito' => ['required', 'string', 'max:40'],
            'body' => ['required', 'array'],
        ];

        return $regras + match ($kind) {
            Record::KIND_AVALIACAO => array_fill_keys(
                array_map(fn (string $c) => "body.{$c}", self::CAMPOS_AVALIACAO),
                ['required'],
            ),
            Record::KIND_EVOLUCAO => [
                'body.conduta' => ['required', 'string', 'max:5000'],
                'body.resposta_paciente' => ['required', 'string', 'max:5000'],
            ],
            Record::KIND_ADENDO => [
                'amends_id' => [
                    'required', 'integer',
                    Rule::exists('fisio_records', 'id')
                        ->where('business_id', $biz)
                        ->where('patient_id', $paciente->id),
                ],
                'body.texto' => ['required', 'string', 'max:5000'],
            ],
            default => [],
        };
    }
}
