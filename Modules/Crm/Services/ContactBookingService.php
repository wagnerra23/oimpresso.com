<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Restaurant\Booking;
use App\Utils\Util;
use Carbon\Carbon;

/**
 * ContactBookingService — orquestrador thin de reserva de contato (bookings).
 *
 * Service thin extraído de `ContactBookingController` (Wave J D4.a boost — 2026-05-16).
 * Encapsula a checagem de conflito de horário + criação da reserva via
 * `Booking::createBooking` já existente. Controller fica responsável por
 * auth + response shape JSON (zero regressão UI).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * caller obrigatoriamente passa `$businessId` + `$userId` resolvidos da session.
 *
 * Padrão thin: zero side-effect além das chamadas Repository já existentes.
 * Compatível 100% com response shape legacy.
 *
 * @see Modules\Crm\Http\Controllers\ContactBookingController
 * @see App\Restaurant\Booking::createBooking
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactBookingService
{
    public function __construct(
        private readonly Util $commonUtil,
    ) {
    }

    /**
     * Verifica se já existe reserva no intervalo solicitado e cria caso disponível.
     *
     * Retorna tupla `[Booking|null, array $output]` — Controller só formata a
     * resposta JSON com base no `$output` (compatível com legacy).
     *
     * @param  array<string, mixed>  $input  Payload completo do request (já normalizado)
     * @return array{0: ?\App\Restaurant\Booking, 1: array{success: int, msg: string}}
     */
    public function attemptBooking(array $input, int $businessId, int $userId): array
    {
        $bookingStart = $this->commonUtil->uf_date($input['booking_start'], true);
        $bookingEnd   = $this->commonUtil->uf_date($input['booking_end'], true);
        $dateRange = [$bookingStart, $bookingEnd];

        $existing = $this->findOverlap(
            businessId: $businessId,
            locationId: (int) $input['location_id'],
            contactId: (int) $input['contact_id'],
            dateRange: $dateRange,
        );

        if ($existing) {
            $timeRange = $this->commonUtil->format_date($existing->booking_start, true)
                .' ~ '
                .$this->commonUtil->format_date($existing->booking_end, true);

            return [
                null,
                [
                    'success' => 0,
                    'msg' => trans('restaurant.booking_not_available', [
                        'customer_name'      => $existing->customer->name ?? '',
                        'booking_time_range' => $timeRange,
                    ]),
                ],
            ];
        }

        $input['business_id']   = $businessId;
        $input['created_by']    = $userId;
        $input['booking_start'] = $bookingStart;
        $input['booking_end']   = $bookingEnd;

        $booking = Booking::createBooking($input);

        return [
            $booking,
            [
                'success' => 1,
                'msg' => trans('lang_v1.added_success'),
            ],
        ];
    }

    /**
     * Busca booking conflitante no business+location+contact dentro do range.
     *
     * Multi-tenant: `where business_id` é obrigatório — sem ele, vaza cross-tenant.
     *
     * @param  array<int, string|\DateTimeInterface>  $dateRange  [start, end]
     */
    public function findOverlap(int $businessId, int $locationId, int $contactId, array $dateRange): ?Booking
    {
        return Booking::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where('contact_id', $contactId)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('booking_start', $dateRange)
                    ->orWhereBetween('booking_end', $dateRange);
            })
            ->first();
    }
}
