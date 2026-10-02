<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipe do app das lojas (tela 26). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.2.
 * Só leitura. Lista os usuários do business (a mesma lista da tela web de Usuários) com função,
 * carga de trabalho e situação. Sem telefone e sem ponto ("Marcação de ponto fica no módulo Ponto").
 *
 * - funcao: o cargo do Essentials (categories hrm_designation), senão o cargo do CRM.
 * - carga: OS abertas atribuídas à pessoa (service_orders.assigned_user_id, mesmo universo do
 *   quadro web da Oficina: etapa não-terminal, ou OS de mecânica ainda sem pipeline). O ERP não
 *   atribui OP de produção a uma pessoa, então OP não entra.
 * - status: inativo → ausente; com carga → ocupado; senão → livre.
 *
 * Acesso = o da tela web de Usuários (`user.view`). Tier 0 (ADR 0093): business_id do token.
 */
class EquipeController extends Controller
{
    public function podeVerEquipe(User $user): bool
    {
        return $user->can('user.view');
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerEquipe($user)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Você não tem permissão para ver a equipe.'], 403);
        }
        $bizId = (int) $user->business_id;

        $pessoas = DB::table('users as u')
            ->leftJoin('categories as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 'u.essentials_designation_id')
                    ->where('c.business_id', $bizId)
                    ->where('c.category_type', 'hrm_designation')
                    ->whereNull('c.deleted_at');
            })
            ->where('u.business_id', $bizId)
            ->where('u.user_type', 'user')
            ->whereNull('u.deleted_at')
            ->orderBy('u.first_name')
            ->orderBy('u.last_name')
            ->get(['u.id', 'u.surname', 'u.first_name', 'u.last_name', 'u.username', 'u.status', 'u.crm_designation', 'c.name as cargo']);

        $os = $this->osAbertasPorPessoa($bizId);

        return response()->json([
            'itens' => $pessoas->map(function ($p) use ($os) {
                $qtd = (int) ($os[(int) $p->id] ?? 0);
                $nome = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: (string) $p->username;
                $funcao = trim((string) ($p->cargo ?: $p->crm_designation));

                return [
                    'id' => (int) $p->id,
                    'nome' => $nome,
                    'funcao' => $funcao !== '' ? $funcao : null,
                    'carga' => $qtd > 0 ? $qtd . ' OS' : null,
                    'status' => $this->status((string) $p->status, $qtd),
                ];
            })->values(),
        ]);
    }

    /** @return array{rotulo: string, tom: string} */
    private function status(string $situacao, int $carga): array
    {
        if ($situacao !== 'active') {
            return ['rotulo' => 'Inativo', 'tom' => 'ausente'];
        }

        return $carga > 0
            ? ['rotulo' => 'Em serviço', 'tom' => 'ocupado']
            : ['rotulo' => 'Disponível', 'tom' => 'livre'];
    }

    /**
     * OS abertas por pessoa atribuída. Sem a tabela (módulo Oficina ausente), nada.
     *
     * @return array<int, int>
     */
    private function osAbertasPorPessoa(int $bizId): array
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('sale_process_stages')) {
            return [];
        }

        return DB::table('service_orders as so')
            ->leftJoin('sale_process_stages as s', 's.id', '=', 'so.current_stage_id')
            ->where('so.business_id', $bizId)
            ->whereNull('so.deleted_at')
            ->whereNotNull('so.assigned_user_id')
            ->where(function ($w) {
                $w->where('s.is_terminal', false)
                    ->orWhere(fn ($w2) => $w2->whereNull('so.current_stage_id')->where('so.order_type', 'mecanica'));
            })
            ->groupBy('so.assigned_user_id')
            ->selectRaw('so.assigned_user_id as uid, COUNT(*) as total')
            ->pluck('total', 'uid')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
