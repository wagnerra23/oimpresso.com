<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Http\Middleware\CheckPontoAccess;
use Modules\Ponto\Services\FilaGestorRepPService;

/**
 * Marcações a validar — fila do gestor do REP-P no app das lojas (tela 39).
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.1.
 *
 * Só marcações do celular FORA do geofence, últimos 7 dias. A regra é a da tela web
 * /ponto/aprovacoes (FilaGestorRepPService, um lugar só):
 *  - validar → registro na trilha; a marcação não muda;
 *  - recusar → Marcacao::anular(): lançamento NOVO de anulação. Nunca UPDATE/DELETE em
 *    ponto_marcacoes (Portaria 671/2021).
 * Acesso = o do módulo Ponto (CheckPontoAccess::permite); recusar exige ainda
 * `ponto.aprovacoes.manage`, como a rota web.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em tudo; marcação de outro business = 404.
 */
class PontoAprovacoesController extends Controller
{
    private const ESTADOS = ['pendente', 'validada', 'recusada', 'todas'];

    public function __construct(private FilaGestorRepPService $fila)
    {
    }

    /** Área `ponto_gestor` do Início: a mesma regra que abre esta rota. */
    public function podeVerFila(User $user): bool
    {
        return CheckPontoAccess::permite($user);
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
                'campos' => ['estado' => ['Use pendente, validada, recusada ou todas.']],
            ], 422);
        }

        $bizId = (int) $user->business_id;
        $marcacoes = $this->fila->fila($bizId);
        $estados = $this->fila->estados($bizId, $marcacoes->map(fn (Marcacao $m) => (string) $m->id)->all());
        $nomes = $this->fila->nomes($bizId, $marcacoes);

        $contadores = ['pendente' => 0, 'validada' => 0, 'recusada' => 0, 'todas' => $marcacoes->count()];
        foreach ($estados as $e) {
            $contadores[strtolower($e)]++;
        }

        $itens = $marcacoes
            ->filter(fn (Marcacao $m) => $estado === 'todas' || strtolower($estados[(string) $m->id]) === $estado)
            ->map(fn (Marcacao $m) => [
                'id' => (string) $m->id,
                'colaborador_nome' => $nomes[$m->colaborador_config_id] ?? '—',
                'tipo' => (string) $m->tipo,
                'local_texto' => $this->localTexto($bizId, $m),
                'marcada_em' => $m->momento?->toIso8601String(),
                'nsr' => (int) $m->nsr,
                // O REP-P não grava a precisão do GPS na marcação (só no log de auditoria).
                'gps_precisao_m' => null,
                'dispositivo' => $m->dispositivo_id !== null ? (string) $m->dispositivo_id : null,
                'hash_curto' => substr((string) $m->hash, 0, 8),
                'estado' => strtolower($estados[(string) $m->id]),
            ])
            ->values();

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
        $bizId = (int) $user->business_id;
        $m = $this->fila->marcacao($bizId, $id);
        if (! $m) {
            return $this->naoEncontrada();
        }
        if ($this->fila->estado($bizId, $m) !== FilaGestorRepPService::PENDENTE) {
            return $this->jaRevisada();
        }
        if (! $this->fila->validar($user, $bizId, $m)) {
            return response()->json([
                'erro' => 'trilha_desligada',
                'mensagem' => 'A trilha de auditoria está desligada — a validação não foi registrada.',
            ], 503);
        }

        return response()->json(['estado' => 'validada']);
    }

    public function recusar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerFila($user) || ! $user->can('ponto.aprovacoes.manage')) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $m = $this->fila->marcacao($bizId, $id);
        if (! $m) {
            return $this->naoEncontrada();
        }
        if ($this->fila->estado($bizId, $m) !== FilaGestorRepPService::PENDENTE) {
            return $this->jaRevisada();
        }

        $anulacao = $this->fila->recusar($user, $m);

        return response()->json(['estado' => 'recusada', 'nsr_anulacao' => (int) $anulacao->nsr]);
    }

    private function localTexto(int $bizId, Marcacao $m): ?string
    {
        $d = $this->fila->distanciaMetros($bizId, $m);
        if ($d === null) {
            return null;
        }

        return $d >= 1000
            ? 'A ' . number_format($d / 1000, 1, ',', '.') . ' km do local de trabalho'
            : 'A ' . (int) round($d) . ' m do local de trabalho';
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
