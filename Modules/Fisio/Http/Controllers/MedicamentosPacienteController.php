<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\MedicamentoPaciente;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\MedicamentosDoPaciente;

/**
 * Medicamentos em uso na ficha (US-FISIO-063, parte 2). Toda escrita exige `fisio.editar`.
 * Paciente e medicamento de outra empresa: 404 pelo escopo e por `where business_id`, antes de
 * validar (ADR 0093). Medicamento tirado da lista não se altera.
 */
class MedicamentosPacienteController extends Controller
{
    public function __construct(private MedicamentosDoPaciente $medicamentos) {}

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $paciente = Patient::query()->where('business_id', $this->empresa($request))->findOrFail($id);
        $this->exigirEdicao($request);
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['nome' => 'Paciente bloqueado: não recebe registro novo.']);
        }
        $dados = $request->validate(MedicamentosDoPaciente::regras(), MedicamentosDoPaciente::mensagens());
        $m = $this->medicamentos->incluir($paciente, $dados, $request->user());

        return $this->resposta($request, ['id' => $m->id], 'Medicamento incluído.', 201);
    }

    public function update(Request $request, int $medicamento): JsonResponse|RedirectResponse
    {
        $m = $this->medicamento($request, $medicamento);
        $this->exigirEdicao($request);
        if ($m->removed_at !== null) {
            throw ValidationException::withMessages(['nome' => 'Medicamento fora da lista não muda.']);
        }
        $dados = $request->validate(MedicamentosDoPaciente::regras(true), MedicamentosDoPaciente::mensagens());
        $this->medicamentos->alterar($m, $dados, $request->user());

        return $this->resposta($request, ['id' => $m->id], 'Medicamento atualizado.');
    }

    public function tirar(Request $request, int $medicamento): JsonResponse|RedirectResponse
    {
        $m = $this->medicamento($request, $medicamento);
        $this->exigirEdicao($request);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']], [
            'motivo.required' => 'Diga por que o medicamento sai da lista.',
            'motivo.min' => 'Diga por que o medicamento sai da lista.',
        ]);
        $tirou = $this->medicamentos->tirar($m, $request->user(), trim($dados['motivo']));

        return $this->resposta($request, ['tirado' => $tirou], $tirou ? 'Medicamento tirado da lista.' : 'O medicamento já estava fora da lista.');
    }

    private function medicamento(Request $request, int $id): MedicamentoPaciente
    {
        /** @var MedicamentoPaciente $m */
        $m = MedicamentoPaciente::query()->where('business_id', $this->empresa($request))->findOrFail($id);

        return $m;
    }

    /** @param array<string, mixed> $json */
    private function resposta(Request $request, array $json, string $mensagem, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($json, $status);
        }

        return back()->with('success', $mensagem);
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
