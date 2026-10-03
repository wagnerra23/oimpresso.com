<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Ponto\FilaGestorPonto;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marcações a validar — fila do gestor do REP-P no app das lojas (tela 39).
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.1.
 *
 * Só marcações do celular FORA do geofence, últimos 7 dias. A regra é a da tela web
 * /ponto/aprovacoes, e chega aqui pelo contrato do núcleo FilaGestorPonto, que o módulo Ponto
 * implementa (a seta de dependência fica módulo → núcleo):
 *  - validar → registro na trilha; a marcação não muda;
 *  - recusar → Marcacao::anular(): lançamento NOVO de anulação. Nunca UPDATE/DELETE em
 *    ponto_marcacoes (Portaria 671/2021).
 * Acesso = o do módulo Ponto; recusar exige ainda `ponto.aprovacoes.manage`, como a rota web.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em tudo; marcação de outro business = 404.
 */
class PontoAprovacoesController extends Controller
{
    private const ESTADOS = ['pendente', 'validada', 'recusada', 'todas'];

    public function __construct(private FilaGestorPonto $fila)
    {
    }

    /** Área `ponto_gestor` do Início: a mesma regra que abre esta rota. */
    public function podeVerFila(User $user): bool
    {
        return $this->fila->podeVer($user);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFila($user)) {
            return $this->semPermissao();
        }
        $estado = (string) $request->query('estado', 'pendente');
        if (! in_array($estado, self::ESTADOS, true)) {
            return response()->json([
                'erro' => 'validacao',
                'mensagem' => 'Filtro de estado inválido.',
                'campos' => ['estado' => 'Use pendente, validada, recusada ou todas.'],
            ], 422);
        }

        $marcacoes = $this->fila->marcacoes((int) $user->business_id);

        $contadores = ['pendente' => 0, 'validada' => 0, 'recusada' => 0, 'todas' => count($marcacoes)];
        foreach ($marcacoes as $m) {
            $contadores[$m['estado']]++;
        }

        $itens = [];
        foreach ($marcacoes as $m) {
            if ($estado !== 'todas' && $m['estado'] !== $estado) {
                continue;
            }
            $itens[] = [
                'id' => $m['id'],
                'colaborador_nome' => $m['colaborador_nome'],
                'tipo' => $m['tipo'],
                'local_texto' => $m['local_texto'],
                'marcada_em' => $m['marcada_em'],
                'nsr' => $m['nsr'],
                // O REP-P não grava a precisão do GPS na marcação (só no log de auditoria).
                'gps_precisao_m' => null,
                'dispositivo' => $m['dispositivo'],
                'hash_curto' => $m['hash_curto'],
                'estado' => $m['estado'],
            ];
        }

        return response()->json([
            'itens' => $itens,
            'contadores' => $contadores,
            'pode_recusar' => $user->can('ponto.aprovacoes.manage'),
        ]);
    }

    public function validar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFila($user)) {
            return $this->semPermissao();
        }

        return match ($this->fila->validar($user, (int) $user->business_id, $id)) {
            FilaGestorPonto::VALIDADA => response()->json(['estado' => 'validada']),
            FilaGestorPonto::JA_REVISADA => $this->jaRevisada(),
            FilaGestorPonto::TRILHA_DESLIGADA => response()->json([
                'erro' => 'trilha_desligada',
                'mensagem' => 'A trilha de auditoria está desligada — a validação não foi registrada.',
            ], 503),
            default => $this->naoEncontrada(),
        };
    }

    public function recusar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFila($user) || ! $user->can('ponto.aprovacoes.manage')) {
            return $this->semPermissao();
        }

        $r = $this->fila->recusar($user, (int) $user->business_id, $id);

        return match ($r['resultado']) {
            FilaGestorPonto::RECUSADA => response()->json(['estado' => 'recusada', 'nsr_anulacao' => $r['nsr_anulacao']]),
            FilaGestorPonto::JA_REVISADA => $this->jaRevisada(),
            default => $this->naoEncontrada(),
        };
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Você não tem permissão para validar o ponto.'], 403);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Marcação não encontrada.'], 404);
    }

    private function jaRevisada(): JsonResponse
    {
        return response()->json(['erro' => 'ja_revisada', 'mensagem' => 'Esta marcação já foi decidida.'], 409);
    }
}
