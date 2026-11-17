<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\SituacaoDaOrdem;

/**
 * Ações de tela da OS por item (ADR 0436) em JSON: Liberar, Refazer e Cancelar item. A Ficha da OS
 * (lote 3 da auditoria) é a tela que vai chamar; até lá, sem o Liberar a OS de empresa sem sinal
 * mínimo ficava parada em Aguardando liberação (SituacaoDaOrdem::liberar só era chamada em teste).
 *
 * Portões, nesta ordem: módulo no pacote + `producao.access` aqui; o papel da ação no motor FSM
 * (`producao.liberar#{biz}` para Liberar e Refazer, `vendas.gerente#{biz}` para Cancelar item —
 * ProcessoDaOrdem::ACOES). Sem o papel → 403. Ação que não vale na situação atual → 409. A empresa vem
 * da sessão; OS de outra empresa → 404 (ADR 0093).
 */
class OrdemSituacaoController extends Controller
{
    private const HTTP = [
        ApontamentoRecusado::NAO_ENCONTRADO => 404,
        ApontamentoRecusado::SINAL_PENDENTE => 409,
        ApontamentoRecusado::OS_NAO_LIBERADA => 409,
    ];

    public function __construct(private readonly SituacaoDaOrdem $situacao, private readonly ModuleUtil $moduleUtil) {}

    /** Corpo: `responsaveis` = { programação: usuário } das etapas abertas (decisão [W] 16/10, opção c). */
    public function liberar(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);
        $dados = $request->validate(['responsaveis' => ['sometimes', 'array'], 'responsaveis.*' => ['integer', 'min:1']]);
        $responsaveis = [];
        foreach ($dados['responsaveis'] ?? [] as $prog => $uid) {
            abort_unless(ctype_digit((string) $prog), 422, 'Etapa inválida.');
            $responsaveis[(int) $prog] = (int) $uid;
        }

        return $this->executar(fn () => $this->situacao->liberar($biz, $id, $request->user(), $responsaveis));
    }

    public function refazer(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);

        return $this->executar(fn () => $this->situacao->refazer($biz, $id, $request->user()));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $biz = $this->empresa($request);

        return $this->executar(fn () => $this->situacao->cancelarItem($biz, $id, $request->user()));
    }

    private function executar(callable $acao): JsonResponse
    {
        try {
            /** @var Ordem $ordem */
            $ordem = $acao();
        } catch (ApontamentoRecusado $e) {
            $status = self::HTTP[$e->codigo] ?? 422;
            abort_if($status === 404, 404);

            return response()->json(['erro' => $e->codigo, 'mensagem' => $e->getMessage(), 'contexto' => $e->contexto], $status);
        } catch (UnauthorizedActionException) {
            abort(403);
        } catch (InvalidActionForCurrentStageException $e) {
            return response()->json(['erro' => 'acao_invalida', 'mensagem' => 'Esta ação não vale na situação atual da OS.'], 409);
        }

        return response()->json(['id' => (int) $ordem->id, 'situacao' => $ordem->fresh('currentStage')->currentStage?->key]);
    }

    private function empresa(Request $request): int
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.access'), 403);

        return $biz;
    }
}
