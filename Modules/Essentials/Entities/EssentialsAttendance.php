<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EssentialsAttendance extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)
    use LogsActivity;     // D7 LGPD audit Wave 27 — marcação de ponto = registro CLT Art. 74 §3

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD (D7 Wave 27) — marcação de ponto + IP/geolocalização.
     *
     * Loga metadata operacional (clock_in/out, shift_id, status) + business_id.
     * NÃO logamos `ip_address`/`clock_in_note` (podem conter PII livre).
     *
     * @see Modules\Essentials\Config\retention.php (essentials_attendance: 1825 dias — CLT Art. 74 §3)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'business_id', 'essentials_shift_id', 'clock_in_time', 'clock_out_time'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.attendance');
    }

    public function employee()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(\Modules\Essentials\Entities\Shift::class, 'essentials_shift_id');
    }
}
