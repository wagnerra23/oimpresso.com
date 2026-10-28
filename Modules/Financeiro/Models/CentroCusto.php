<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Centro de custo da empresa (US-FIN-075).
 *
 * @property int $id
 * @property int $business_id
 * @property string|null $codigo
 * @property string $nome
 * @property bool $ativo
 */
class CentroCusto extends Model
{
    use SoftDeletes, BusinessScope, LogsActivity;

    protected $table = 'fin_centros_custo';

    protected $fillable = ['business_id', 'codigo', 'nome', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'nome', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.centro_custo');
    }

    public function rateios(): HasMany
    {
        return $this->hasMany(TituloRateio::class, 'centro_custo_id');
    }

    /** Centro com rateio gravado não sai do banco: inative em vez disso. */
    public function delete()
    {
        if ($this->rateios()->exists()) {
            throw new \DomainException("Centro de custo '{$this->nome}' tem títulos rateados. Inative em vez disso.");
        }

        return parent::delete();
    }
}
