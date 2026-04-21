<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Rep extends Model
{
    protected $table = 'ponto_reps';

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'business_id',
        'tipo',
        'identificador',
        'descricao',
        'local',
        'cnpj',
        'ultimo_nsr',
        'certificado_info',
        'ativo',
    ];

    protected $casts = [
        'certificado_info' => 'array',
        'ativo'            => 'boolean',
    ];

    public const TIPO_REP_P = 'REP_P';
    public const TIPO_REP_C = 'REP_C';
    public const TIPO_REP_A = 'REP_A';

    public function marcacoes(): HasMany
    {
        return $this->hasMany(Marcacao::class, 'rep_id');
    }

    public function proximoNsr(): int
    {
        return ++$this->ultimo_nsr;
    }
}
