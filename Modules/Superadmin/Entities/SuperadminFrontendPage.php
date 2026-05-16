<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SuperadminFrontendPage extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * Auditoria LGPD D7.b — Wave 11 Superadmin.
     * Frontend pages = conteúdo institucional público (Sobre/Termos/Privacidade).
     * Mudança em Termos de Uso ou Política de Privacidade tem implicação legal
     * (LGPD Art. 9º informação ao titular) — trail append-only obrigatório
     * + retenção indefinida em activity_log (não pode ser podada sem ADR).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('superadmin.frontend_page');
    }
}
