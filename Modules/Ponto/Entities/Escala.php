<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Tabela `ponto_escalas` tem coluna `business_id`. Trait `HasBusinessScope`
 * garante isolamento Model-level. Marcacao (append-only) NÃO recebe trait —
 * portaria 671/2021 protege via trigger MySQL diferente; Escala é dado cadastral
 * (Wave 11 D7.b LogsActivity já presente — agora Tier 0 também).
 */
class Escala extends Model
{
    use HasBusinessScope;
    use HasFactory;
    use LogsActivity;

    protected $table = 'ponto_escalas';

    /**
     * Wave 11 D7.b — audit trail LGPD pra escalas de trabalho.
     *
     * Escalas afetam jornada CLT (Art. 58, 59, 71) — mudanças precisam ser auditáveis
     * pro RH e pra fiscalização MTE. Não é append-only (escala pode ser corrigida),
     * mas TODO update vira histórico via spatie/activity_log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nome',
                'codigo',
                'tipo',
                'carga_diaria_minutos',
                'carga_semanal_minutos',
                'permite_banco_horas',
                'ativo',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto_escala');
    }

    protected $fillable = [
        'business_id',
        'nome',
        'codigo',
        'tipo',
        'carga_diaria_minutos',
        'carga_semanal_minutos',
        'permite_banco_horas',
        'dias_semana',
        'horarios_padrao',
        'ativo',
    ];

    protected $casts = [
        'dias_semana'         => 'array',
        'horarios_padrao'     => 'array',
        'permite_banco_horas' => 'boolean',
        'ativo'               => 'boolean',
    ];

    public const TIPO_FIXA         = 'FIXA';
    public const TIPO_FLEXIVEL     = 'FLEXIVEL';
    public const TIPO_ESCALA_12X36 = 'ESCALA_12X36';
    public const TIPO_ESCALA_6X1   = 'ESCALA_6X1';
    public const TIPO_ESCALA_5X2   = 'ESCALA_5X2';

    public function turnos(): HasMany
    {
        return $this->hasMany(EscalaTurno::class, 'escala_id');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'escala_atual_id');
    }
}
