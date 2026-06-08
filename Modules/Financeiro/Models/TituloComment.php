<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Comentário inline por título financeiro (Onda Comments + Audit DB 2026-05-18).
 *
 * Append-only (sem updated_at — created_at apenas). Espelha contrato do mock
 * Cowork canon FinCommentsThread (financeiro-curation.jsx) — antes localStorage,
 * agora DB sincronizado.
 *
 * Tier 0 multi-tenant via BusinessScope trait — todas queries filtram por
 * session('user.business_id'). FK CASCADE em business + titulo (delete em
 * cascade preserva consistência).
 */
class TituloComment extends Model
{
    use HasFactory, BusinessScope;

    protected $table = 'fin_titulo_comments';

    public $timestamps = false; // só created_at

    protected $fillable = [
        'business_id', 'titulo_id', 'user_id', 'body',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    /**
     * Append-only: bloqueia delete pra preservar histórico de curadoria.
     * Hard delete só via comando admin (não exposto na UI).
     */
    public function delete()
    {
        throw new \DomainException(
            'fin_titulo_comments é append-only. Comentários não podem ser deletados.'
        );
    }
}
