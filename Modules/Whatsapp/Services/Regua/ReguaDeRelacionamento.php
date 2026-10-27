<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Regua;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;
use Modules\Whatsapp\Services\Notify\ResultadoNotificacao;

/**
 * Régua de relacionamento por WhatsApp — aniversário e recompra (US-WA-326).
 *
 * Roda uma vez por dia por empresa (`whatsapp:regua-relacionamento`). É marketing, não aviso de
 * transação: só entra cliente com opt-in de marketing (`contacts.whatsapp_opt_in_at`, o mesmo da
 * campanha, ADR 0268) — e o `NotificadorClienteWhatsapp` ainda confere a recusa de WhatsApp,
 * celular e canal no ar.
 *
 * - **Aniversário:** `aniversario_mmdd` (MM-DD) do dia; sem ele, o mês/dia de `dob`. Uma vez por ano.
 * - **Recompra:** a última venda final do cliente foi há exatamente `dias_sem_compra` dias. Uma vez
 *   por última compra.
 *
 * Anti-ban: os envios saem espaçados (`INTERVALO_MIN_S`..`INTERVALO_MAX_S`) pela fila e no máximo
 * `LIMITE_POR_DIA` por empresa e régua. Rodar de novo no mesmo dia não repete (registro em
 * `whatsapp_regua_envios`, chave única). Tier 0: tudo filtrado pela empresa.
 */
final class ReguaDeRelacionamento
{
    public const INTERVALO_MIN_S = 20;

    public const INTERVALO_MAX_S = 45;

    public const LIMITE_POR_DIA = 150;

    public function __construct(private readonly NotificadorClienteWhatsapp $notificador) {}

    /**
     * @param  callable(int, int): int|null  $sortear  intervalo entre envios (teste injeta)
     * @return array{aniversario: int, recompra: int}
     */
    public function executar(int $businessId, CarbonImmutable $hoje, ?callable $sortear = null): array
    {
        $sortear ??= fn (int $a, int $z): int => random_int($a, $z);
        $reguas = DB::table('whatsapp_reguas_relacionamento')
            ->where('business_id', $businessId)->where('ativo', true)->get()->keyBy('tipo');
        $enviados = ['aniversario' => 0, 'recompra' => 0];
        $quando = $hoje;

        foreach (['aniversario', 'recompra'] as $tipo) {
            $regua = $reguas->get($tipo);
            if ($regua === null || trim((string) $regua->texto) === '') {
                continue;
            }
            [$clientes, $referencia] = $tipo === 'aniversario'
                ? [$this->aniversariantes($businessId, $hoje), (string) $hoje->year]
                : [$this->paraRecompra($businessId, $hoje, (int) $regua->dias_sem_compra), null];

            foreach ($clientes as $c) {
                if ($enviados[$tipo] >= self::LIMITE_POR_DIA) {
                    break;
                }
                $ref = $referencia ?? (string) $c->ultima_compra;
                if (! $this->reservar($businessId, $tipo, (int) $c->id, $ref)) {
                    continue; // já foi nesta ocasião
                }
                $primeiro = trim(explode(' ', trim((string) $c->name))[0]);
                $texto = str_replace('{nome}', $primeiro !== '' ? $primeiro : 'cliente', (string) $regua->texto);

                $quando = $quando->addSeconds($sortear(self::INTERVALO_MIN_S, self::INTERVALO_MAX_S));
                $r = $this->notificador->notificar($businessId, (int) $c->id, 'outbound_default', $texto, [], $quando);
                DB::table('whatsapp_regua_envios')
                    ->where('business_id', $businessId)->where('tipo', $tipo)->where('contact_id', (int) $c->id)->where('referencia', $ref)
                    ->update(['resultado' => $r->value]);
                if ($r === ResultadoNotificacao::Enviado) {
                    $enviados[$tipo]++;
                }
            }
        }

        return $enviados;
    }

    /** @return iterable<object> */
    private function aniversariantes(int $businessId, CarbonImmutable $hoje): iterable
    {
        $mmdd = $hoje->format('m-d');

        return DB::table('contacts')
            ->where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->whereNull('deleted_at')
            ->whereNotNull('whatsapp_opt_in_at')
            ->where(fn ($q) => $q->where('aniversario_mmdd', $mmdd)
                ->orWhere(fn ($q2) => $q2->whereNull('aniversario_mmdd')
                    ->whereMonth('dob', $hoje->month)->whereDay('dob', $hoje->day)))
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /** @return iterable<object> */
    private function paraRecompra(int $businessId, CarbonImmutable $hoje, int $dias): iterable
    {
        if ($dias <= 0) {
            return [];
        }
        $ultima = DB::table('transactions')
            ->where('business_id', $businessId)->where('type', 'sell')->where('status', 'final')
            ->groupBy('contact_id')
            ->selectRaw('contact_id, DATE(MAX(transaction_date)) as ultima_compra');

        return DB::table('contacts as c')
            ->joinSub($ultima, 'u', 'u.contact_id', '=', 'c.id')
            ->where('c.business_id', $businessId)
            ->whereIn('c.type', ['customer', 'both'])
            ->whereNull('c.deleted_at')
            ->whereNotNull('c.whatsapp_opt_in_at')
            ->where('u.ultima_compra', $hoje->subDays($dias)->toDateString())
            ->orderBy('c.id')
            ->get(['c.id', 'c.name', 'u.ultima_compra']);
    }

    /** Grava a ocasião antes de enviar; se já existe, quem chamou pula. */
    private function reservar(int $businessId, string $tipo, int $contactId, string $referencia): bool
    {
        try {
            DB::table('whatsapp_regua_envios')->insert([
                'business_id' => $businessId, 'tipo' => $tipo, 'contact_id' => $contactId,
                'referencia' => $referencia, 'resultado' => 'reservado', 'created_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
