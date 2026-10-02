<?php

declare(strict_types=1);

namespace Modules\Ponto\Services;

use App\Contracts\Tarefas\JustificativasPonto;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Http\Controllers\IntercorrenciaController;
use Modules\Ponto\Http\Middleware\CheckPontoAccess;

/**
 * Implementação do contrato App\Contracts\Tarefas\JustificativasPonto (aba Tarefas do app das
 * lojas). Registrada no PontoServiceProvider. Reusa o critério de acesso das aprovações
 * (CheckPontoAccess::permite) e os rótulos de tipo da tela de intercorrências.
 *
 * Tier 0 (ADR 0093): business_id explícito na intercorrência E na config do colaborador.
 */
final class JustificativasDoApp implements JustificativasPonto
{
    public function aprova(User $user): bool
    {
        return CheckPontoAccess::permite($user);
    }

    public function pendentes(User $user, int $businessId): array
    {
        $q = DB::table('ponto_intercorrencias as i')
            ->join('ponto_colaborador_config as c', 'c.id', '=', 'i.colaborador_config_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('i.business_id', $businessId)
            ->where('c.business_id', $businessId)
            ->where('i.estado', Intercorrencia::ESTADO_PENDENTE);

        if (! $this->aprova($user)) {
            $q->where('c.user_id', $user->id);
        }

        return $q->orderBy('i.data')
            ->limit(100)
            ->get(['i.id', 'i.codigo', 'i.tipo', 'i.data', 'u.first_name', 'u.last_name'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'titulo' => 'Justificativa ' . $i->codigo,
                'subtitulo' => trim(((string) $i->first_name) . ' ' . ((string) $i->last_name)) . ' · ' . $this->tipo((string) $i->tipo),
                'prazo' => substr((string) $i->data, 0, 10),
            ])
            ->values()
            ->all();
    }

    private function tipo(string $t): string
    {
        foreach (IntercorrenciaController::tiposDisponiveis() as $op) {
            if ($op['value'] === $t) {
                return $op['label'];
            }
        }

        return $t;
    }
}
