<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Http\Controllers\Controller;
use App\Services\AppLojas\AcoesDoPedido;
use App\Transaction;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/app/pedidos/{id}/acao — avançar a etapa do pedido pelo app (P1 · tela 22).
 * Contrato: memory/requisitos/AppMobile/api/tela-22-avancar-etapa.md.
 *
 * Executa pelo ExecuteStageActionService (o mesmo da web), só para as ações da lista segura
 * (AcoesDoPedido) liberadas pelo ajuste `app_acoes_fsm_liberadas`. Nenhuma delas mexe em valor,
 * estoque, cobrança ou fiscal.
 *
 * - `etapa_esperada` ≠ etapa atual → 409 etapa_mudou (duplo toque, dois usuários).
 * - `Idempotency-Key` em `app_idempotencia` (rota `pedidos.acao`), reservada na MESMA transação de
 *   banco da ação: se a ação falha, a reserva some junto; repetida depois do sucesso, devolve a
 *   mesma resposta.
 * - A venda é travada (`lockForUpdate`) antes de conferir a etapa: duas chaves diferentes ao mesmo
 *   tempo não avançam duas vezes.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta; pedido de outro business ou
 * fora do "só os próprios" → 404, sem dizer que existe.
 */
class PedidoAcaoController extends Controller
{
    private const ROTA = 'pedidos.acao';

    public function executar(Request $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $pedidos = app(PedidosController::class);
        if ($user === null || ! $pedidos->podeVerVendas($user)) {
            return $this->erro(403, 'sem_permissao', 'Seu usuário não tem acesso aos pedidos.');
        }
        $bizId = (int) $user->business_id;

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'acao' => ['required', 'string', 'regex:/^[a-z0-9_]{1,80}$/'],
            'etapa_esperada' => ['required', 'string', 'max:80'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'acao.required' => 'Informe a ação.',
            'acao.regex' => 'Ação inválida.',
            'etapa_esperada.required' => 'Informe a etapa que o app mostrava.',
            'motivo.max' => 'O motivo tem no máximo 500 caracteres.',
            'motivo.string' => 'O motivo precisa ser texto.',
        ]);
        if ($v->fails()) {
            return response()->json([
                'erro' => 'validacao',
                'mensagem' => 'Confira os campos.',
                'campos' => collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all(),
            ], 422);
        }
        $d = $v->validated();
        $acaoChave = (string) $d['acao'];
        $esperada = (string) $d['etapa_esperada'];
        $motivo = trim((string) ($d['motivo'] ?? ''));
        $motivo = $motivo !== '' ? $motivo : null;

        // Visível ao usuário (mesmas regras da lista): senão 404, inclusive outro business.
        if (! $pedidos->baseVisivel($user)->where('t.id', $id)->exists()) {
            return $this->naoEncontrado();
        }

        $hash = hash('sha256', (string) json_encode([$id, $acaoChave, $esperada, $motivo]));
        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        if (! in_array($acaoChave, AcoesDoPedido::liberadas($bizId), true)) {
            return $this->erro(403, 'sem_permissao', 'Esta ação só pode ser feita na web.');
        }

        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => self::ROTA, 'chave' => $chave,
                    'hash_corpo' => $hash, 'transaction_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Outra requisição com a mesma chave ganhou a corrida (o INSERT esperou o lock).
                DB::rollBack();
                $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
            }

            $venda = Transaction::query()->where('business_id', $bizId)->whereKey($id)->lockForUpdate()->first();
            $atual = $venda?->current_stage_id === null ? null : DB::table('sale_process_stages')
                ->where('id', (int) $venda->current_stage_id)->value('key');
            if ($venda === null || $atual !== $esperada) {
                DB::rollBack();

                return $this->etapaMudou();
            }

            $acao = SaleStageAction::query()
                ->where('stage_id', (int) $venda->current_stage_id)
                ->where('key', $acaoChave)
                ->first();
            if ($acao === null) {
                DB::rollBack();

                return $this->etapaMudou();
            }
            if (! AcoesDoPedido::podeNoApp($acao, AcoesDoPedido::liberadas($bizId))
                || ! app(StageActionPolicy::class)->canExecute($user, $venda, $acaoChave)) {
                DB::rollBack();

                return $this->erro(403, 'sem_permissao', 'Seu usuário não pode executar esta ação.');
            }

            // O motivo vai para a trilha (sale_stage_history.payload_snapshot), como o payload da web.
            app(ExecuteStageActionService::class)->execute(
                $venda,
                $acaoChave,
                $user,
                array_filter(['origem' => 'app', 'motivo' => $motivo], fn ($x) => $x !== null)
            );

            $resposta = $pedidos->show($request, $id);
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'resposta' => json_encode($resposta->getData(true)),
                'updated_at' => now(),
            ]);
            DB::commit();

            return $resposta;
        } catch (UnauthorizedActionException) {
            DB::rollBack();

            return $this->erro(403, 'sem_permissao', 'Seu usuário não pode executar esta ação.');
        } catch (InvalidActionForCurrentStageException) {
            DB::rollBack();

            return $this->etapaMudou();
        } catch (\App\Services\Sells\VendaNaoCancelavelException $e) {
            DB::rollBack();

            return $this->erro(422, 'nao_cancelavel', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API app pedidos.acao: ' . $e->getMessage(), ['business_id' => $bizId, 'transaction_id' => $id]);

            return $this->erro(500, 'falha', 'Não foi possível avançar o pedido.');
        }
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', self::ROTA)->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return $this->erro(422, 'idempotencia_conflito', 'Esta chave já foi usada para outra ação. Gere uma chave nova.');
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 200);
    }

    private function emAndamento(): JsonResponse
    {
        return $this->erro(409, 'em_andamento', 'Esta ação ainda está sendo registrada. Aguarde.');
    }

    private function etapaMudou(): JsonResponse
    {
        return $this->erro(409, 'etapa_mudou', 'O pedido mudou de etapa. Atualize a tela.');
    }

    private function naoEncontrado(): JsonResponse
    {
        return $this->erro(404, 'nao_encontrado', 'Pedido não encontrado.');
    }

    private function erro(int $status, string $erro, string $mensagem): JsonResponse
    {
        return response()->json(['erro' => $erro, 'mensagem' => $mensagem], $status);
    }
}
