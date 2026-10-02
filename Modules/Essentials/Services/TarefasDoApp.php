<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Contracts\Tarefas\TarefasEssentials;
use App\User;
use Carbon\Carbon;
use Modules\Essentials\Entities\ToDo;

/**
 * Implementação do contrato App\Contracts\Tarefas\TarefasEssentials (aba Tarefas do app das
 * lojas). Registrada no EssentialsServiceProvider. Reusa a regra de visibilidade da tela web
 * (TodoService::scopedQueryForUser) em vez de duplicá-la no núcleo.
 *
 * O gate de pacote (essentials_module) fica no núcleo, no TarefasController.
 */
final class TarefasDoApp implements TarefasEssentials
{
    public function __construct(private TodoService $todos)
    {
    }

    public function pendentes(User $user, int $businessId): array
    {
        return $this->todos->scopedQueryForUser($businessId, $user)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'completed'))
            ->orderBy('date')
            ->limit(100)
            ->get()
            ->map(function ($t) {
                /** @var ToDo $t */
                $prazo = $t->end_date ?? $t->date;

                return [
                    'id' => (int) $t->id,
                    'titulo' => trim(strip_tags((string) $t->task)),
                    'subtitulo' => $this->rotulo($t),
                    'prazo' => $prazo ? Carbon::parse($prazo)->toDateString() : null,
                ];
            })
            ->values()
            ->all();
    }

    public function concluir(User $user, int $businessId, int $id): bool
    {
        /** @var ToDo|null $todo */
        $todo = $this->todos->scopedQueryForUser($businessId, $user)->find($id);
        if (! $todo) {
            return false;
        }

        $todo->update(['status' => 'completed']);

        return true;
    }

    public function detalhe(User $user, int $businessId, int $id): ?array
    {
        /** @var ToDo|null $t */
        $t = $this->todos->scopedQueryForUser($businessId, $user)->find($id);
        if (! $t) {
            return null;
        }

        $prazo = $t->end_date ?? $t->date;
        $descricao = trim(strip_tags((string) $t->description));
        $responsaveis = $t->users()->get()->map(fn ($u) => $this->nome($u))->filter()->implode(', ');

        return [
            'id' => (int) $t->id,
            'titulo' => trim(strip_tags((string) $t->task)),
            'descricao' => $descricao !== '' ? $descricao : null,
            'rotulo' => $this->rotulo($t),
            'responsavel' => $responsaveis !== '' ? $responsaveis : null,
            'prazo' => $prazo ? Carbon::parse($prazo)->toDateString() : null,
            'concluida' => $t->status === 'completed',
            'comentarios' => $t->comments()->with('added_by')->reorder('id')->get()->map(fn ($c) => [
                'quando' => $c->created_at ? Carbon::parse($c->created_at)->toIso8601String() : null,
                'autor' => $c->added_by ? $this->nome($c->added_by) : null,
                'texto' => trim(strip_tags((string) $c->comment)),
            ])->values()->all(),
        ];
    }

    private function rotulo(ToDo $t): string
    {
        return 'Tarefa' . ($t->priority ? ' · ' . $this->prioridade((string) $t->priority) : '');
    }

    private function nome(object $u): ?string
    {
        $n = trim(preg_replace('/\s+/', ' ', ($u->first_name ?? '') . ' ' . ($u->last_name ?? '')));

        return $n !== '' ? $n : null;
    }

    private function prioridade(string $p): string
    {
        return ['low' => 'baixa', 'medium' => 'média', 'high' => 'alta', 'urgent' => 'urgente'][$p] ?? $p;
    }
}
