<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shift extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)
    use LogsActivity;     // D7 LGPD audit Wave 27 — escala de jornada é base CLT

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'essentials_shifts';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD (D7 Wave 27) — escala de jornada (CLT Art. 74).
     *
     * Loga business_id + nome + type + start_time/end_time + auto_clockout flag.
     * `holidays` array fica fora pra evitar payload gigante em activity_log.
     *
     * @see Modules\Essentials\Config\retention.php (shift: null — config; user_shift: 1825d)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['business_id', 'name', 'type', 'start_time', 'end_time', 'auto_clockout'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.shift');
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'holidays' => 'array',
    ];

    public function user_shifts($value = '')
    {
        return $this->hasMany(\Modules\Essentials\Entities\EssentialsUserShift::class, 'essentials_shift_id');
    }

    public static function getGivenShiftInfo($business_id, $shift_id)
    {
        $shift = Shift::where('business_id', $business_id)
                    ->find($shift_id);

        return $shift;
    }
}
