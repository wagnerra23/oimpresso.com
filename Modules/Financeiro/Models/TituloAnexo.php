<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * TituloAnexo — Onda 20 (2026-05-19) #50.
 *
 * Anexos relacionados a Titulo (NF PDF, recibo, comprovante TED).
 * Storage local em `storage/app/private/financeiro/anexos/{biz_id}/{titulo_id}/`.
 * Multi-tenant via BusinessScope trait.
 */
class TituloAnexo extends Model
{
    use SoftDeletes, BusinessScope;

    protected $table = 'fin_titulo_anexos';

    protected $fillable = [
        'business_id', 'titulo_id', 'nome', 'path', 'mime',
        'tamanho_bytes', 'hash_sha256', 'uploaded_by',
    ];

    protected $casts = [
        'tamanho_bytes' => 'integer',
    ];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class);
    }
}
