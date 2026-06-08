<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProdutoGrupo extends Model
{
    protected $table = 'produto_grupo';

    protected $fillable = [
        'descricao',
        'referencia',
        'codplanocontas',
        'created_by',
        'officeimpresso_codigo',
        'officeimpresso_dt_alteracao',
        'business_id'
    ];

    /**
     * Get the user who created this record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include records for a specific business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
