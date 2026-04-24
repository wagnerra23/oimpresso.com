<?php

namespace Modules\Officeimpresso\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log de eventos de autenticacao / acesso dos desktops.
 *
 * Dados chegam passivamente de:
 *   - trigger_mysql  — triggers em oauth_access_tokens/oauth_refresh_tokens
 *   - log_parser     — command que parsa storage/logs/laravel.log
 *   - desktop_audit  — endpoint opcional que o Delphi futuro pode chamar
 *   - admin_action   — acoes manuais (block, unblock, businessupdate)
 *
 * NAO ha UPDATE/DELETE por regra — so retencao via job agendado.
 */
class LicencaLog extends Model
{
    protected $table = 'licenca_log';

    public $timestamps = false; // so created_at, sem updated_at

    protected $fillable = [
        'licenca_id',
        'business_id',
        'business_location_id',
        'user_id',
        'event',
        'client_id',
        'token_hint',
        'ip',
        'user_agent',
        'endpoint',
        'http_method',
        'http_status',
        'duration_ms',
        'error_code',
        'error_message',
        'metadata',
        'source',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function licenca()
    {
        return $this->belongsTo(Licenca_Computador::class, 'licenca_id');
    }

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function businessLocation()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'business_location_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    /**
     * Cor do badge na UI por tipo de evento.
     */
    public function eventBadgeClass(): string
    {
        return match ($this->event) {
            'login_success', 'token_refresh' => 'label-success',
            'login_error'                    => 'label-danger',
            'login_attempt', 'api_call'      => 'label-info',
            'block'                          => 'label-warning',
            'unblock'                        => 'label-success',
            'create_licenca', 'update_licenca', 'businessupdate' => 'label-primary',
            default => 'label-default',
        };
    }
}
