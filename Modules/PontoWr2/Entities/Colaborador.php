<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bridge entre users (UltimatePOS) e o domínio de Ponto.
 * Não é o User em si — é a configuração de ponto associada a ele.
 */
class Colaborador extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ponto_colaborador_config';

    protected $fillable = [
        'business_id',
        'user_id',
        'matricula',
        'pis',
        'cpf',
        'escala_atual_id',
        'controla_ponto',
        'usa_banco_horas',
        'admissao',
        'desligamento',
        'metadata',
    ];

    protected $casts = [
        'controla_ponto'   => 'boolean',
        'usa_banco_horas'  => 'boolean',
        'admissao'         => 'date',
        'desligamento'     => 'date',
        'metadata'         => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('pontowr2.ultimatepos.user_model'), 'user_id');
    }

    public function escalaAtual(): BelongsTo
    {
        return $this->belongsTo(Escala::class, 'escala_atual_id');
    }

    public function marcacoes(): HasMany
    {
        return $this->hasMany(Marcacao::class, 'colaborador_config_id');
    }

    public function intercorrencias(): HasMany
    {
        return $this->hasMany(Intercorrencia::class, 'colaborador_config_id');
    }

    public function apuracoes(): HasMany
    {
        return $this->hasMany(ApuracaoDia::class, 'colaborador_config_id');
    }

    public function bancoHorasSaldo()
    {
        return $this->hasOne(BancoHorasSaldo::class, 'colaborador_config_id');
    }

    public function bancoHorasMovimentos(): HasMany
    {
        return $this->hasMany(BancoHorasMovimento::class, 'colaborador_config_id');
    }
}
