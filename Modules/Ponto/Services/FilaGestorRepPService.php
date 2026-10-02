<?php

declare(strict_types=1);

namespace Modules\Ponto\Services;

use App\User;
use Illuminate\Support\Collection;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Spatie\Activitylog\Models\Activity;

/**
 * Fila do gestor do REP-P — marcações do celular FORA do geofence (thread 06 · D3).
 *
 * Um lugar só para a tela web (/ponto/aprovacoes, seção "mobile") e para o app das lojas
 * (GET/POST /api/app/ponto/aprovacoes, tela 39). As duas portas decidem igual.
 *
 * O geofence SINALIZA (não recusa): a marcação já entrou e vale. O gestor
 *  - VALIDA  → registro na trilha (activity_log `ponto.repp` / `validada`), sem tocar a marcação;
 *  - RECUSA  → `Marcacao::anular()`: lançamento NOVO com ORIGEM_ANULACAO (Portaria 671/2021).
 * Nenhum dos dois faz UPDATE/DELETE em `ponto_marcacoes`.
 *
 * Tier 0 (ADR 0093): business_id explícito em toda consulta.
 */
class FilaGestorRepPService
{
    public const PENDENTE = 'PENDENTE';
    public const VALIDADA = 'VALIDADA';
    public const RECUSADA = 'RECUSADA';

    public const MOTIVO_RECUSA = 'Recusada na validação REP-P';

    public function __construct(private MobileMarcacaoService $mobile)
    {
    }

    /**
     * Marcações REP-P dos últimos 7 dias que o geofence sinalizou, mais nova primeiro.
     *
     * @return Collection<int, Marcacao>
     */
    public function fila(int $businessId): Collection
    {
        // O service devolve Collection<Model> (sem genérico de Marcacao) — por isso a closure
        // não tipa o parâmetro.
        /** @var Collection<int, Marcacao> $candidatas */
        $candidatas = collect($this->mobile->listarMarcacoesMobilePendentesValidacao($businessId)->all());

        return $candidatas
            ->filter(fn ($m) => $m->latitude !== null && $m->longitude !== null
                && ! $this->mobile->validarGeolocation((float) $m->latitude, (float) $m->longitude, $businessId))
            ->values();
    }

    /** Uma marcação da fila (REP-P do celular) do business, ou null. */
    public function marcacao(int $businessId, string $id): ?Marcacao
    {
        return Marcacao::query()
            ->where('business_id', $businessId)
            ->where('dispositivo_id', 'like', 'mobile:%')
            ->where('origem', Marcacao::ORIGEM_REP_P)
            ->whereKey($id)
            ->first();
    }

    /**
     * RECUSADA se existe anulação apontando pra ela · VALIDADA se há registro na trilha (não
     * revertido) · senão PENDENTE.
     *
     * @param  array<int,string>  $ids
     * @return array<string,string>
     */
    public function estados(int $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $recusadas = Marcacao::query()
            ->where('business_id', $businessId)
            ->where('origem', Marcacao::ORIGEM_ANULACAO)
            ->whereIn('marcacao_anulada_id', $ids)
            ->pluck('marcacao_anulada_id')
            ->flip();
        $validadas = Activity::query()
            ->where('log_name', 'ponto.repp')
            ->where('event', 'validada')
            ->where('business_id', $businessId)
            ->whereNull('reverted_at')
            ->where('created_at', '>=', now()->subDays(8)) // a fila é de 7 dias (+1 de folga no fuso)
            ->get()
            ->map(fn (Activity $a) => (string) $a->getExtraProperty('marcacao_id'))
            ->flip();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = isset($recusadas[$id]) ? self::RECUSADA : (isset($validadas[$id]) ? self::VALIDADA : self::PENDENTE);
        }

        return $out;
    }

    public function estado(int $businessId, Marcacao $m): string
    {
        return $this->estados($businessId, [(string) $m->id])[(string) $m->id];
    }

    /**
     * Nome do colaborador por colaborador_config_id.
     *
     * @param  Collection<int, Marcacao>  $marcacoes
     * @return array<int|string,string>
     */
    public function nomes(int $businessId, Collection $marcacoes): array
    {
        return Colaborador::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $marcacoes->pluck('colaborador_config_id')->unique())
            ->with('user:id,first_name,last_name')
            ->get()
            ->mapWithKeys(fn (Colaborador $c) => [$c->id => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name) ?: '—'])
            ->all();
    }

    /** Distância (m) até o centro do geofence do business; null sem geofence ou sem coordenada. */
    public function distanciaMetros(int $businessId, Marcacao $m): ?float
    {
        if ($m->latitude === null || $m->longitude === null) {
            return null;
        }

        return $this->mobile->distanciaDoGeofenceMetros((float) $m->latitude, (float) $m->longitude, $businessId);
    }

    /**
     * Registra a validação na trilha. Devolve false se o logger estiver desligado
     * (ACTIVITY_LOGGER_ENABLED=false): o chamador falha visível em vez de dizer "validada".
     */
    public function validar(User $gestor, int $businessId, Marcacao $m): bool
    {
        $registro = activity('ponto.repp')
            ->event('validada')
            ->causedBy($gestor)
            ->withProperties(['marcacao_id' => (string) $m->id, 'nsr' => (int) $m->nsr])
            ->tap(fn (Activity $a) => $a->setAttribute('business_id', $businessId))
            ->log('Marcação REP-P validada');

        return $registro !== null;
    }

    /** Grava a anulação (lançamento novo); a original não muda. */
    public function recusar(User $gestor, Marcacao $m): Marcacao
    {
        return $m->anular((int) $gestor->id, self::MOTIVO_RECUSA);
    }
}
