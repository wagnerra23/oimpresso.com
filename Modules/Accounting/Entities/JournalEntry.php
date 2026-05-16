<?php

namespace Modules\Accounting\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\BusinessLocation;
use Modules\Accounting\Entities\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JournalEntry extends Model
{
    use BelongsToBusinessViaParent; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 15 D1 MT rescue; child de BusinessLocation via location_id)
    use LogsActivity;

    /**
     * Parent relation pra ScopeByBusinessViaParent — BusinessLocation tem business_id direto.
     */
    protected string $businessParentRelation = 'business_location';

    protected $table = "journal_entries";
    protected $fillable = ['reversed'];

    /**
     * Auditoria LGPD — D7 LGPD compliance (Wave 11 sessão 2026-05-16).
     *
     * Append-only via Spatie activity_log. Notes/reference são livres e podem
     * conter PII (CPF/CNPJ fornecedor). Use AccountingAuditLogger pra payloads
     * customizados sanitizados; mudanças auto-tracked pela trait passam pelo
     * mecanismo padrão Spatie (dirty fields).
     *
     * Retenção: ver Modules/Accounting/Config/retention.php — lancamentos 1825d
     * (Art. 195 CTN — 5 anos).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function chart_of_account()
    {
        return $this->hasOne(ChartOfAccount::class, 'id', 'chart_of_account_id')->withDefault();
    }

    public function business_location()
    {
        return $this->hasOne(BusinessLocation::class, 'id', 'location_id')
            ->where('business_locations.business_id', session('business.id'))
            ->withDefault();
    }

    public function created_by()
    {
        return $this->hasOne(User::class, 'id', 'created_by_id')->withDefault();
    }

    public function scopeNotReversed($query)
    {
        return $query->where('reversed', 0);
    }

    public function scopeReconcileEntry($query)
    {
        return $query->where('transaction_type', 'reconcile_entry');
    }
    
    public function scopeForBusiness($query)
    {
        return $query->whereHas('business_location', function ($q) {
            $q->where('business_locations.business_id', session('business.id'));
        });
    }

    public function getAmountAttribute()
    {
        return $this->credit - $this->debit;
    }
}
