<?php

namespace App;

use App\Concerns\BusinessScope;
use App\Concerns\BusinessScopeImpl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * US-CRM-080 -- endereco de entrega de um contato (1:N).
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): trait BusinessScope aplica
 * global scope por business_id em todas as queries + auto-preenche na criacao.
 *
 * Convencao UPOS: models de negocio core vivem em app/ root (igual Contact,
 * Transaction), nao em app/Models.
 *
 * is_default: exatamente 1 default por (business_id, contact_id). Guard de
 * unicidade no metodo markAsDefault() + cobertura Pest (nao via constraint DB,
 * pra permitir reordenacao transitoria).
 *
 * PII LGPD: NAO usa LogsActivity (endereco e PII; nao deve ir pra activity_log).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactAddress extends Model
{
    use BusinessScope;
    use SoftDeletes;

    protected $table = 'contact_addresses';

    protected $guarded = ['id'];

    protected $casts = [
        'is_default' => 'bool',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Scope: apenas o endereco default.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Marca este endereco como default e desmarca os demais do mesmo contato.
     * Garante unicidade de default por (business_id, contact_id) em app layer.
     *
     * Roda em transacao pra nao deixar zero-default ou dois-default num crash.
     */
    public function markAsDefault(): void
    {
        \DB::transaction(function () {
            static::query()
                ->where('contact_id', $this->contact_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * Monta a string de endereco achatada (compat com snapshot de venda e
     * consumidores legado Connector/Woocommerce que esperam texto unico).
     * NAO loga -- so monta string em memoria.
     */
    public function toFlatString(): string
    {
        $partes = array_filter([
            trim(implode(', ', array_filter([$this->address_line_1, $this->numero]))),
            $this->address_line_2,
            $this->neighborhood,
            trim(implode(' - ', array_filter([$this->city, $this->state]))),
            $this->zip_code,
        ]);

        return implode(', ', $partes);
    }
}
