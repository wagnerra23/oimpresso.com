<?php

declare(strict_types=1);

namespace Modules\Jana\Services\AcoesDoChat;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Modules\Essentials\Services\TodoService;

/**
 * "Cria uma tarefa pra eu ligar pro fornecedor amanhã" → tarefa do Essentials.
 *
 * Grava por `TodoService::criar`, o mesmo caminho do `ToDoController::store`; a
 * permissão é a mesma da tela (`canAdd`: módulo no pacote + `essentials.add_todos`).
 * A tarefa fica com o próprio usuário: a Jana não atribui a terceiros.
 */
class CriarTarefaAcao implements AcaoDoChat
{
    private const PRIORIDADES = ['low' => 'baixa', 'medium' => 'média', 'high' => 'alta', 'urgent' => 'urgente'];

    public function __construct(private TodoService $todos) {}

    public function chave(): string
    {
        return 'criar-tarefa';
    }

    public function permitida(Model $user, int $businessId): bool
    {
        return $this->todos->canAdd($user, $businessId);
    }

    public function validar(array $campos): array
    {
        $dados = Validator::make($campos, [
            'titulo'     => 'required|string|max:255',
            'data'       => 'nullable|date',
            'prioridade' => 'nullable|in:'.implode(',', array_keys(self::PRIORIDADES)),
            'descricao'  => 'nullable|string|max:2000',
        ])->validate();

        return [
            'titulo'     => trim((string) $dados['titulo']),
            'data'       => Carbon::parse($dados['data'] ?? 'today')->format('Y-m-d'),
            'prioridade' => $dados['prioridade'] ?? 'medium',
            'descricao'  => $dados['descricao'] ?? null,
        ];
    }

    public function resumo(array $campos): string
    {
        return sprintf(
            'Criar a tarefa "%s" para %s, prioridade %s.',
            $campos['titulo'],
            Carbon::parse($campos['data'])->format('d/m/Y'),
            self::PRIORIDADES[$campos['prioridade']] ?? $campos['prioridade'],
        );
    }

    public function executar(int $businessId, Model $user, array $campos): array
    {
        $todo = $this->todos->criar($businessId, $user, [
            'task'        => $campos['titulo'],
            'date'        => $campos['data'],
            'priority'    => $campos['prioridade'],
            'description' => $campos['descricao'],
        ]);

        return [
            'id'     => (int) $todo->id,
            'rotulo' => 'Tarefa '.$todo->task_id,
            'url'    => '/essentials/todo/'.$todo->id,
        ];
    }
}
