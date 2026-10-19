<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Support\Collection;
use Modules\Fisio\Entities\Patient;
use Spatie\Activitylog\Models\Activity;

/**
 * Fisioterapeuta responsável pelo paciente (UC-FPS-30..32, UC-FPI-20).
 *
 * Quem pode ser responsável: usuário ATIVO da mesma empresa que tem acesso ao Fisio (a mesma
 * regra do FisioAcesso: `fisio.access` ou superadmin). Usuário de outra empresa nunca entra na
 * lista e nunca é aceito como destino.
 *
 * A transferência não guarda histórico em tabela própria: vai para o `activity_log`, que o
 * projeto já usa como trilha por registro (Modules/Auditoria) e que nunca perde linha. O
 * `business_id` da linha vem do paciente (ActivityCauserKindObserver), nunca da sessão.
 */
class ResponsavelPaciente
{
    public const EVENTO = 'fisio_paciente_transferido';

    public const LOG = 'fisio';

    /**
     * Profissionais da empresa que podem receber pacientes, em ordem de nome.
     *
     * @return Collection<int, array{id: int, nome: string}>
     */
    public function profissionais(int $businessId): Collection
    {
        return User::query()
            ->where('business_id', $businessId)
            ->where('user_type', 'user')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'business_id', 'first_name', 'last_name', 'username'])
            ->filter(fn (User $u) => $this->podeAtender($u))
            ->map(fn (User $u) => ['id' => (int) $u->id, 'nome' => self::nome($u)])
            ->values();
    }

    /** O usuário pode ser responsável por pacientes desta empresa? */
    public function podeReceber(int $userId, int $businessId): bool
    {
        $user = User::query()->whereKey($userId)->where('business_id', $businessId)
            ->where('user_type', 'user')->where('status', 'active')->first();

        return $user !== null && $this->podeAtender($user);
    }

    /**
     * Troca o responsável e registra quem fez, de quem, para quem e quando. Não apaga nada:
     * cada transferência é uma linha nova no activity_log.
     */
    public function transferir(Patient $paciente, int $paraUserId, User $quem): void
    {
        $de = $paciente->responsible_user_id !== null ? User::query()->find($paciente->responsible_user_id) : null;
        $para = User::query()->findOrFail($paraUserId);

        $paciente->responsible_user_id = $paraUserId;
        $paciente->save();

        activity(self::LOG)
            ->performedOn($paciente)
            ->causedBy($quem)
            ->event('transferido')
            ->withProperties([
                'de_user_id' => $de?->id !== null ? (int) $de->id : null,
                'de_nome' => $de !== null ? self::nome($de) : null,
                'para_user_id' => (int) $para->id,
                'para_nome' => self::nome($para),
            ])
            ->log(self::EVENTO);
    }

    /**
     * Transferências do paciente, mais recente primeiro. Filtra a empresa também aqui
     * (defesa em profundidade): o Activity não tem escopo global de empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function historico(Patient $paciente): array
    {
        return Activity::query()
            ->where('business_id', (int) $paciente->business_id)
            ->where('subject_type', $paciente->getMorphClass())
            ->where('subject_id', $paciente->id)
            ->where('description', self::EVENTO)
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Activity $a): array {
                $p = $a->properties;

                return [
                    'id' => (int) $a->id,
                    'quando' => $a->created_at?->toIso8601String(),
                    'por' => $a->causer instanceof User ? self::nome($a->causer) : null,
                    'de' => $p->get('de_nome'),
                    'para' => $p->get('para_nome'),
                ];
            })
            ->all();
    }

    public static function nome(User $u): string
    {
        $nome = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));

        return $nome !== '' ? $nome : (string) $u->username;
    }

    private function podeAtender(User $u): bool
    {
        return $u->can(FisioAcesso::PERMISSAO) || $u->can('superadmin');
    }
}
