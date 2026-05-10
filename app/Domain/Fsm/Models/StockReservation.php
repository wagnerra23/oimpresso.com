<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Reserva de estoque atrelada a transação (ADR 0129 + US-SELL-013).
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 *
 * Status workflow: active → consumed | released | expired (terminais).
 */
class StockReservation extends Model
{
    use HasBusinessScope;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'stock_reservations';

    protected $guarded = ['id'];

    protected $casts = [
        'qty_reserved' => 'decimal:4',
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
