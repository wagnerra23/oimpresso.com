<?php

declare(strict_types=1);

namespace Modules\Ponto\Services;

use App\Contracts\Ponto\FilaGestorPonto;
use App\User;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Http\Middleware\CheckPontoAccess;

/**
 * Implementação do contrato do núcleo FilaGestorPonto (tela 39 do app das lojas), sobre o
 * mesmo FilaGestorRepPService da tela web /ponto/aprovacoes — as duas portas decidem igual.
 * Registrada no PontoServiceProvider.
 *
 * Tier 0 (ADR 0093): business_id explícito em tudo (FilaGestorRepPService filtra por ele).
 */
final class FilaGestorDoApp implements FilaGestorPonto
{
    public function __construct(private FilaGestorRepPService $fila)
    {
    }

    public function podeVer(User $user): bool
    {
        return CheckPontoAccess::permite($user);
    }

    public function marcacoes(int $businessId): array
    {
        $marcacoes = $this->fila->fila($businessId);
        $estados = $this->fila->estados($businessId, $marcacoes->map(fn (Marcacao $m) => (string) $m->id)->all());
        $nomes = $this->fila->nomes($businessId, $marcacoes);

        return $marcacoes->map(fn (Marcacao $m) => [
            'id' => (string) $m->id,
            'colaborador_nome' => $nomes[$m->colaborador_config_id] ?? '—',
            'tipo' => (string) $m->tipo,
            'local_texto' => $this->localTexto($businessId, $m),
            'marcada_em' => $m->momento?->toIso8601String(),
            'nsr' => (int) $m->nsr,
            'dispositivo' => $m->dispositivo_id !== null ? (string) $m->dispositivo_id : null,
            'hash_curto' => substr((string) $m->hash, 0, 8),
            'estado' => strtolower($estados[(string) $m->id]),
        ])->values()->all();
    }

    public function validar(User $user, int $businessId, string $id): string
    {
        $m = $this->fila->marcacao($businessId, $id);
        if (! $m) {
            return self::NAO_ENCONTRADA;
        }
        if ($this->fila->estado($businessId, $m) !== FilaGestorRepPService::PENDENTE) {
            return self::JA_REVISADA;
        }

        return $this->fila->validar($user, $businessId, $m) ? self::VALIDADA : self::TRILHA_DESLIGADA;
    }

    public function recusar(User $user, int $businessId, string $id): array
    {
        $m = $this->fila->marcacao($businessId, $id);
        if (! $m) {
            return ['resultado' => self::NAO_ENCONTRADA, 'nsr_anulacao' => null];
        }
        if ($this->fila->estado($businessId, $m) !== FilaGestorRepPService::PENDENTE) {
            return ['resultado' => self::JA_REVISADA, 'nsr_anulacao' => null];
        }

        return ['resultado' => self::RECUSADA, 'nsr_anulacao' => (int) $this->fila->recusar($user, $m)->nsr];
    }

    private function localTexto(int $businessId, Marcacao $m): ?string
    {
        $d = $this->fila->distanciaMetros($businessId, $m);
        if ($d === null) {
            return null;
        }

        return $d >= 1000
            ? 'A ' . number_format($d / 1000, 1, ',', '.') . ' km do local de trabalho'
            : 'A ' . (int) round($d) . ' m do local de trabalho';
    }
}
