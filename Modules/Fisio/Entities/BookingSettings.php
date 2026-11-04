<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_booking_settings` — regras da agenda e do agendamento pelo app, uma linha por empresa
 * (A8, A9, A21). Nasce desligado (`online_enabled=false`). `deposit_percent` é gancho do
 * Financeiro e fica 0 enquanto a cobrança não existir. Escopo por empresa (ADR 0093).
 */
class BookingSettings extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_booking_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'online_enabled' => 'boolean',
        'open_slots' => 'array',
        'waitlist_enabled' => 'boolean',
        'checkin_enabled' => 'boolean',
    ];

    /** A linha da empresa, criada com os padrões na primeira leitura. */
    public static function daEmpresa(int $businessId): self
    {
        // SUPERADMIN: o portal público não tem sessão; o business vem do token, explícito.
        $linha = static::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId]);

        // Recém-criada, a linha só tem o business_id: os padrões estão no banco.
        return $linha->wasRecentlyCreated ? $linha->refresh() : $linha;
    }
}
