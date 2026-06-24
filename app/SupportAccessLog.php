<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Modo Suporte (ADR 0305) — registro APPEND-ONLY de acesso do suporte a um tenant (RF3).
 *
 * Imutável por design: update e delete são barrados no boot (mesma família de
 * `ponto_marcacoes`). v1 enforça no Model; um trigger MySQL pode endurecer depois (questão
 * aberta da SPEC/PLAN). Sem global scope de business — `business_id` é a empresa-ALVO
 * auditada, consultada cross-tenant pelo operador, não um particionamento de tenant.
 *
 * @see App\Services\Support\SupportAuditService — quem grava.
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SupportAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'support_access_logs';

    /** Append-only: só `created_at` (preenchido por DEFAULT useCurrent na migration). */
    public $timestamps = false;

    protected $fillable = [
        'support_user_id',
        'business_id',
        'target_user_id',
        'action',
        'route',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'support_user_id' => 'integer',
        'business_id'     => 'integer',
        'target_user_id'  => 'integer',
        'created_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('support_access_logs é append-only (ADR 0305) — update proibido.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('support_access_logs é append-only (ADR 0305) — delete proibido.');
        });
    }
}
