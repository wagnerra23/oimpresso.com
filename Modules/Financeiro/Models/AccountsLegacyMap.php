<?php

namespace Modules\Financeiro\Models;

use App\Account;
use App\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Bridge entre `App\Account` (core UltimatePOS) e origem legacy externa.
 *
 * Tabela `accounts_legacy_map` criada por migration sob ADR 0118 — não
 * modificar `accounts` direto (proibições.md).
 *
 * Multi-tenant Tier 0 (ADR 0093) via trait BusinessScope — superadmin
 * com can('superadmin') vê cross-tenant deliberadamente (operação do
 * importer / Migration Factory).
 *
 * Fillable conservador — escrita esperada via importer Python (SQL direto)
 * ou Service\AccountsLegacyMapper (Modules/MigrationFactory futuro).
 */
class AccountsLegacyMap extends Model
{
    use BusinessScope;

    protected $table = 'accounts_legacy_map';

    protected $fillable = [
        'business_id',
        'account_id',
        'legacy_source',
        'legacy_id',
        'legacy_imported_at',
        'legacy_importer_version',
        'legacy_metadata',
    ];

    protected $casts = [
        'legacy_imported_at' => 'datetime',
        'legacy_metadata'    => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }
}
