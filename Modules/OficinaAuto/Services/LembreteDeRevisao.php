<?php

namespace Modules\OficinaAuto\Services;

use App\Contracts\Atendimento\AvisaClienteWhatsapp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lembrete de revisão do veículo por WhatsApp (US-AUTO-014, decisão [W] 2026-10-26).
 *
 * Duas metas, opcionais e independentes, no cadastro do veículo:
 *   km   — `next_service_km`: avisa quando faltarem até KM_ANTES km. O sistema não lê o hodômetro, então
 *          ESTIMA o km de hoje pela média diária entre as duas últimas OS com km (`mileage_at_service`
 *          e `entered_at`): km de hoje = km da última + km/dia × dias desde ela. Sem duas OS com km
 *          crescente em dias diferentes, não estima e não avisa por km.
 *   data — `next_service_date`: avisa a partir de DIAS_ANTES dias antes.
 * Cada meta avisa uma vez (`oficina_lembretes_revisao`, por veículo + tipo + alvo); trocar a meta avisa
 * de novo. Só para cliente com opt-in explícito (`contacts.whatsapp_consent` = 1, decisão [W]: "envio
 * só para cliente com opt-in") — nulo não basta aqui.
 *
 * O envio é do Modules/Whatsapp pelo contrato `AvisaClienteWhatsapp`; só se registra o aviso que ele
 * aceitou. Tier 0: tudo pelo `$businessId` recebido; veículo e contato da mesma empresa.
 */
final class LembreteDeRevisao
{
    public const KM_ANTES = 500;

    public const DIAS_ANTES = 7;

    public function kmEstimado(int $businessId, int $vehicleId, Carbon $hoje): ?int
    {
        $os = DB::table('service_orders')->where('business_id', $businessId)->where('vehicle_id', $vehicleId)
            ->whereNull('deleted_at')->whereNotNull('mileage_at_service')->whereNotNull('entered_at')
            ->orderByDesc('entered_at')->orderByDesc('id')->limit(2)->get(['mileage_at_service', 'entered_at']);
        if ($os->count() < 2) {
            return null;
        }
        [$ultima, $anterior] = [$os[0], $os[1]];
        $dias = Carbon::parse($anterior->entered_at)->startOfDay()->diffInDays(Carbon::parse($ultima->entered_at)->startOfDay());
        $km = (int) $ultima->mileage_at_service - (int) $anterior->mileage_at_service;
        if ($dias <= 0 || $km <= 0) {
            return null;
        }
        $desde = max(0, Carbon::parse($ultima->entered_at)->startOfDay()->diffInDays($hoje->copy()->startOfDay()));

        return (int) round((int) $ultima->mileage_at_service + $km / $dias * $desde);
    }

    /**
     * Avisos devidos hoje, ainda não avisados.
     *
     * @return list<array{vehicle_id: int, contact_id: int, tipo: string, alvo: string, texto: string}>
     */
    public function pendentes(int $businessId, Carbon $hoje): array
    {
        $empresa = (string) DB::table('business')->where('id', $businessId)->value('name');
        $veiculos = DB::table('vehicles as v')
            ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 'v.contact_id')->where('c.business_id', '=', $businessId))
            ->where('v.business_id', $businessId)->whereNull('v.deleted_at')->whereNull('c.deleted_at')
            ->where('c.whatsapp_consent', true)
            ->where(fn ($q) => $q->whereNotNull('v.next_service_km')->orWhereNotNull('v.next_service_date'))
            ->get(['v.id', 'v.plate', 'v.contact_id', 'v.next_service_km', 'v.next_service_date', 'c.name']);
        $jaAvisados = DB::table('oficina_lembretes_revisao')->where('business_id', $businessId)
            ->whereIn('vehicle_id', $veiculos->pluck('id'))->get(['vehicle_id', 'tipo', 'alvo'])
            ->map(fn ($l) => $l->vehicle_id.'|'.$l->tipo.'|'.$l->alvo)->flip();

        $saida = [];
        foreach ($veiculos as $v) {
            $base = ['vehicle_id' => (int) $v->id, 'contact_id' => (int) $v->contact_id];
            if ($v->next_service_km !== null) {
                $estimado = $this->kmEstimado($businessId, (int) $v->id, $hoje);
                $alvo = (string) (int) $v->next_service_km;
                if ($estimado !== null && (int) $v->next_service_km - $estimado <= self::KM_ANTES && ! isset($jaAvisados[$v->id.'|km|'.$alvo])) {
                    $saida[] = $base + ['tipo' => 'km', 'alvo' => $alvo, 'texto' => sprintf(
                        'Olá, %s! O veículo %s está chegando aos %s km da próxima revisão. Responda esta mensagem para agendar com a %s.',
                        $v->name, $v->plate, number_format((int) $v->next_service_km, 0, ',', '.'), $empresa,
                    )];
                }
            }
            if ($v->next_service_date !== null) {
                $data = Carbon::parse($v->next_service_date);
                $alvo = $data->toDateString();
                if ($hoje->copy()->startOfDay()->greaterThanOrEqualTo($data->copy()->subDays(self::DIAS_ANTES)->startOfDay()) && ! isset($jaAvisados[$v->id.'|data|'.$alvo])) {
                    $saida[] = $base + ['tipo' => 'data', 'alvo' => $alvo, 'texto' => sprintf(
                        'Olá, %s! A revisão do veículo %s está marcada para %s. Responda esta mensagem para agendar com a %s.',
                        $v->name, $v->plate, $data->format('d/m/Y'), $empresa,
                    )];
                }
            }
        }

        return $saida;
    }

    /**
     * Manda os avisos devidos hoje. Sem o Modules/Whatsapp ligado, não manda nem registra nada.
     *
     * @return array{devidos: int, enviados: int, recusados: int}
     */
    public function enviar(int $businessId, Carbon $hoje, bool $simular = false): array
    {
        $pendentes = $this->pendentes($businessId, $hoje);
        $placar = ['devidos' => count($pendentes), 'enviados' => 0, 'recusados' => 0];
        if ($simular || $pendentes === [] || ! app()->bound(AvisaClienteWhatsapp::class)) {
            return $placar;
        }
        $avisa = app(AvisaClienteWhatsapp::class);
        foreach ($pendentes as $p) {
            $r = $avisa->avisar($businessId, $p['contact_id'], $p['texto']);
            if (! $r['enviado']) {
                $placar['recusados']++;
                Log::info('oficina.lembrete_revisao.recusado', ['business_id' => $businessId, 'vehicle_id' => $p['vehicle_id'], 'motivo' => $r['motivo']]);

                continue;
            }
            DB::table('oficina_lembretes_revisao')->insertOrIgnore([
                'business_id' => $businessId, 'vehicle_id' => $p['vehicle_id'], 'tipo' => $p['tipo'], 'alvo' => $p['alvo'],
                'avisado_em' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $placar['enviados']++;
        }

        return $placar;
    }
}
