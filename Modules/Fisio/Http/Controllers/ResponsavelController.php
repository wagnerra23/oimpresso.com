<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ResponsavelPaciente;

/**
 * Transferir o paciente para outro fisioterapeuta da empresa (UC-FPS-31, UC-FPS-32).
 *
 * Exige `fisio.editar` (a mesma permissão que edita o paciente). Paciente de outra empresa
 * responde 404 pelo escopo do model. Destino de outra empresa, inativo ou sem acesso ao Fisio
 * responde 422 e nada muda. `business_id` vem sempre da sessão.
 */
class ResponsavelController extends Controller
{
    public function __construct(private readonly ResponsavelPaciente $responsavel) {}

    public function store(Request $request, int $id): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $paciente = Patient::query()->findOrFail($id);
        $biz = (int) $request->session()->get('user.business_id');

        $dados = $request->validate([
            'para_user_id' => ['required', 'integer'],
        ], [
            'para_user_id.required' => 'Escolha o fisioterapeuta que vai receber o paciente.',
        ]);
        $para = (int) $dados['para_user_id'];

        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['para_user_id' => 'Paciente bloqueado: a ficha não aceita alteração.']);
        }
        if (! $this->responsavel->podeReceber($para, $biz)) {
            throw ValidationException::withMessages(['para_user_id' => 'Este usuário não pode receber pacientes desta empresa.']);
        }
        if ((int) $paciente->responsible_user_id === $para) {
            throw ValidationException::withMessages(['para_user_id' => 'Este profissional já é o responsável.']);
        }

        /** @var User $quem */
        $quem = $request->user();
        $this->responsavel->transferir($paciente, $para, $quem);

        return back()->with('status', 'Paciente transferido.');
    }
}
