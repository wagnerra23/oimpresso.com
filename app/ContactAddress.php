<?php

declare(strict_types=1);

namespace App;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ContactAddress — endereço de um contato (US-CRM-078).
 *
 * 1 Contact hasMany ContactAddress (matriz/filial/casa/obra). O endereço
 * inline de `contacts` (zip_code/address_line_1/...) permanece como o
 * "principal" (compat UltimatePOS / NFe / Sells) e é espelhado no endereço
 * `is_default = true`.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - business_id NOT NULL + FK + index (migration).
 *   - HasBusinessScope (ScopeByBusiness) filtra por session('user.business_id').
 *   - Jobs/CLI: ->withoutGlobalScope(ScopeByBusiness::class) + where business_id.
 *   - business_id / contact_id NÃO são mass-assignable (setados server-side).
 *
 * @property int         $id
 * @property int         $business_id
 * @property int         $contact_id
 * @property string|null $label
 * @property string|null $zip_code
 * @property string|null $address_line_1
 * @property string|null $numero
 * @property string|null $address_line_2
 * @property string|null $neighborhood
 * @property string|null $city
 * @property string|null $state
 * @property string|null $city_code
 * @property bool        $is_default
 * @property bool        $is_shipping
 *
 * @see app/Contact.php::addresses()
 * @see memory/requisitos/Cliente/SPEC.md §US-CRM-078
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ContactAddress extends Model
{
    use HasBusinessScope;
    use SoftDeletes;

    protected $table = 'contact_addresses';

    /**
     * Mass assignment explícito — NUNCA business_id / contact_id via request
     * (setados server-side a partir do contato + sessão multi-tenant).
     */
    protected $fillable = [
        'label',
        'zip_code',
        'address_line_1',
        'numero',
        'address_line_2',
        'neighborhood',
        'city',
        'state',
        'city_code',
        'is_default',
        'is_shipping',
    ];

    protected $casts = [
        'is_default' => 'bool',
        'is_shipping' => 'bool',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Endereço formatado em 1 linha (UI seletor de entrega na venda).
     * Ex.: "Av Paulista, 1578 — Bela Vista — São Paulo/SP · 01310-100".
     */
    public function getOneLineAttribute(): string
    {
        $rua = trim((string) $this->address_line_1);
        if ($this->numero !== null && $this->numero !== '') {
            $rua = $rua !== '' ? "{$rua}, {$this->numero}" : (string) $this->numero;
        }

        $cidadeUf = null;
        if ($this->city && $this->state) {
            $cidadeUf = "{$this->city}/{$this->state}";
        } elseif ($this->city || $this->state) {
            $cidadeUf = (string) ($this->city ?: $this->state);
        }

        $parts = array_filter(
            [$rua, $this->neighborhood, $cidadeUf],
            static fn ($p) => $p !== null && $p !== ''
        );
        $line = implode(' — ', $parts);

        if ($this->zip_code) {
            $line = $line !== '' ? "{$line} · {$this->zip_code}" : (string) $this->zip_code;
        }

        return $line;
    }

    /**
     * Snapshot dos campos canônicos pra espelhar no endereço inline de
     * `contacts` (compat UPOS) e gerar o `shipping_address` (text livre) da venda.
     *
     * @return array<string, string|null>
     */
    public function toInlineArray(): array
    {
        return [
            'zip_code' => $this->zip_code,
            'address_line_1' => $this->address_line_1,
            'numero' => $this->numero,
            'address_line_2' => $this->address_line_2,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'city_code' => $this->city_code,
        ];
    }

    /**
     * Backfill idempotente: copia o endereço inline de cada contact que tenha
     * QUALQUER campo de endereço preenchido para 1 `contact_addresses` default
     * (is_default = true, is_shipping = true, label "Principal"), preservando
     * business_id do próprio contact.
     *
     * Usa DB facade (sem Eloquent/scope) — seguro em migration/CLI sem sessão.
     * Idempotente: só insere se o contact ainda não tem endereço cadastrado.
     *
     * @param  int|null  $businessId  limita a 1 tenant (null = todos).
     * @return int  quantidade de endereços inseridos.
     */
    public static function backfillInline(?int $businessId = null): int
    {
        if (! Schema::hasTable('contacts') || ! Schema::hasTable('contact_addresses')) {
            return 0;
        }

        $addressCols = ['zip_code', 'address_line_1', 'address_line_2', 'neighborhood', 'city', 'state'];
        $hasNumero = Schema::hasColumn('contacts', 'numero');
        $hasCityCode = Schema::hasColumn('contacts', 'city_code');

        $select = array_merge(['id', 'business_id'], $addressCols);
        if ($hasNumero) {
            $select[] = 'numero';
        }
        if ($hasCityCode) {
            $select[] = 'city_code';
        }

        $inserted = 0;

        DB::table('contacts')
            ->select($select)
            ->whereNull('deleted_at')
            ->when($businessId !== null, static fn ($q) => $q->where('business_id', $businessId))
            ->where(static function ($q) use ($addressCols) {
                foreach ($addressCols as $c) {
                    $q->orWhere(static function ($qq) use ($c) {
                        $qq->whereNotNull($c)->where($c, '!=', '');
                    });
                }
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($hasNumero, $hasCityCode, &$inserted) {
                $now = now();
                $batch = [];
                foreach ($rows as $r) {
                    // Idempotência: pula se o contact já tem QUALQUER endereço.
                    $already = DB::table('contact_addresses')
                        ->where('contact_id', $r->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($already) {
                        continue;
                    }
                    $batch[] = [
                        'business_id' => $r->business_id,
                        'contact_id' => $r->id,
                        'label' => 'Principal',
                        'zip_code' => $r->zip_code,
                        'address_line_1' => $r->address_line_1,
                        'numero' => $hasNumero ? ($r->numero ?? null) : null,
                        'address_line_2' => $r->address_line_2,
                        'neighborhood' => $r->neighborhood,
                        'city' => $r->city,
                        'state' => $r->state,
                        'city_code' => $hasCityCode ? ($r->city_code ?? null) : null,
                        'is_default' => true,
                        'is_shipping' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($batch !== []) {
                    DB::table('contact_addresses')->insert($batch);
                    $inserted += count($batch);
                }
            });

        return $inserted;
    }
}
