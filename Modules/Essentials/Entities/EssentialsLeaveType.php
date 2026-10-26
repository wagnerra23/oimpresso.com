<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $ferias_clt Licença aprovada deste tipo abate o saldo de férias da CLT no Ponto (US-PONTO-018).
 */
class EssentialsLeaveType extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public static function forDropdown($business_id)
    {
        $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                                    ->pluck('leave_type', 'id');

        return $leave_types;
    }
}
