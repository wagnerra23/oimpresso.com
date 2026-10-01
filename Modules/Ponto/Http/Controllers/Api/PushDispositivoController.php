<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\PushDispositivo;

/**
 * Registro do aparelho para o lembrete de bater ponto (ADR 0423).
 *
 * Chamado pela própria tela `/ponto/mobile` quando roda dentro do app Capacitor: o plugin
 * entrega o token FCM e a página o envia com a sessão do ERP.
 *
 * Tier 0 ([ADR 0093]): usuário e business vêm SEMPRE do usuário autenticado; o body traz só
 * o token e a plataforma. Só quem tem cadastro de ponto ativo registra — o lembrete é da
 * jornada dele. O opt-out desativa apenas linhas do próprio usuário no próprio business.
 */
class PushDispositivoController extends Controller
{
    /** POST /ponto/mobile/push/dispositivo — `ponto.mobile.push.registrar` */
    public function registrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', Rule::in(PushDispositivo::PLATAFORMAS)],
        ]);

        $colab = $this->colaboradorDoUsuario($request);
        if (! $colab) {
            return response()->json(['erro' => 'sem_colaborador'], 403);
        }

        DB::transaction(function () use ($dados, $colab) {
            // SUPERADMIN: o token identifica o APARELHO, não o tenant. Aparelho compartilhado
            // na loja muda de dono a cada login (ADR 0423): a linha antiga, de qualquer business,
            // passa a ser do usuário autenticado — senão o lembrete iria para quem saiu.
            $disp = PushDispositivo::withoutGlobalScopes()
                ->where('token', $dados['token'])
                ->lockForUpdate()
                ->first() ?? new PushDispositivo(['token' => $dados['token']]);

            $disp->fill([
                'business_id' => (int) $colab->business_id,
                'user_id' => (int) $colab->user_id,
                'plataforma' => $dados['plataforma'],
                'ativo' => true,
                'ultimo_uso_at' => now(),
            ])->save();
        });

        return response()->json(['ativo' => true], 200);
    }

    /** DELETE /ponto/mobile/push/dispositivo — opt-out (`ponto.mobile.push.desativar`) */
    public function desativar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $afetados = PushDispositivo::query()
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->where('token', $dados['token'])
            ->update(['ativo' => false]);

        return response()->json(['ativo' => false, 'afetados' => $afetados], 200);
    }

    /** O colaborador do usuário autenticado, no empregador DELE, que controla ponto. */
    private function colaboradorDoUsuario(Request $request): ?Colaborador
    {
        $user = $request->user();

        return Colaborador::query()
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->where('controla_ponto', true)
            ->first();
    }
}
